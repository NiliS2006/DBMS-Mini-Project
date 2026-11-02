<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin','dept_manager','project_manager']);
$pdo = getDB();
$uid  = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

$id = sanitizeInt($_GET['id'] ?? 0);
if (!$id) { setFlash('Invalid expense.','danger'); redirect(BASE_URL.'/approvals/index.php'); }

$expenseScope = getExpenseScopeSql($role, (int)$uid, 'e');
$stmt = $pdo->prepare("SELECT e.*, u.name as submitter_name, u.email as submitter_email,
    ec.name as cat_name, p.name as proj_name, p.id as proj_id, d.name as dept_name, o.name as org_name,
    a.name as approver_name
    FROM expenses e
    JOIN users u ON u.id=e.submitted_by
    LEFT JOIN expense_categories ec ON ec.id=e.category_id
    LEFT JOIN projects p ON p.id=e.project_id
    LEFT JOIN departments d ON d.id=e.department_id
    LEFT JOIN organizations o ON o.id=e.organization_id
    LEFT JOIN users a ON a.id=e.approved_by
    WHERE e.id=? AND ({$expenseScope[0]})");
$stmt->execute(array_merge([$id], $expenseScope[1])); $exp = $stmt->fetch();

if (!$exp) { setFlash('Expense not found.','danger'); redirect(BASE_URL.'/approvals/index.php'); }
if ($exp['status'] !== 'pending') {
    setFlash('This expense has already been ' . $exp['status'] . '.', 'warning');
    redirect(BASE_URL.'/expenses/view.php?id='.$id);
}
// Prevent self-approval
if ($exp['submitted_by'] == $uid) {
    setFlash('You cannot approve your own expense submission.', 'danger');
    redirect(BASE_URL.'/expenses/view.php?id='.$id);
}

// Project budget remaining
$budgetInfo = null;
if ($exp['proj_id']) {
    $b = $pdo->prepare("SELECT allocated_budget AS budget, approved_expenditure AS spent
        FROM v_project_budget_summary WHERE project_id=? AND fiscal_year=?");
    $b->execute([$exp['proj_id'], (int)substr($exp['expense_date'], 0, 4)]);
    $budgetInfo = $b->fetch();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid CSRF token.'; }
    $action = trim($_POST['action'] ?? '');
    $reason = trim($_POST['rejection_reason'] ?? '');
    $notes  = trim($_POST['notes'] ?? '');

    if (!in_array($action, ['approved','rejected'])) $errors[] = 'Invalid action.';
    if ($action === 'rejected' && !$reason) $errors[] = 'A rejection reason is required.';
    if (strlen($reason) > 2000 || strlen($notes) > 2000) $errors[] = 'Decision notes must be 2000 characters or fewer.';

    // Double-check self-approval on POST
    if ($exp['submitted_by'] == $uid) $errors[] = 'You cannot approve your own expense.';

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare("UPDATE expenses SET status=?, approved_by=?, approved_at=NOW(), rejection_reason=? WHERE id=? AND status='pending'");
            $update->execute([$action, $uid, $action==='rejected' ? $reason : null, $id]);
            if ($update->rowCount() !== 1) {
                $pdo->rollBack();
                $errors[] = 'This expense was already reviewed. Refresh the approval queue.';
            } else {
                $pdo->prepare("INSERT INTO approval_history (expense_id, action, action_by, notes) VALUES (?,?,?,?)")
                    ->execute([$id, $action, $uid, $notes ?: null]);

                $pdo->commit();
                setFlash('Expense has been ' . $action . ' successfully.', $action==='approved'?'success':'warning');
                redirect(BASE_URL.'/approvals/index.php');
            }
        } catch (Exception $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Expense approval failed: ' . $ex->getMessage());
            $errors[] = 'Failed to process decision. Please try again.';
        }
    }
}

$pageTitle = 'Review Expense';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa fa-gavel" style="color:var(--color-primary)"></i> Review Expense</h1>
      <p class="page-subtitle">Review the expense details and make an approval decision</p>
    </div>
    <a href="<?= BASE_URL ?>/approvals/index.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back to Queue</a>
  </div>

  <?php foreach ($errors as $err): ?><div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= e($err) ?></div><?php endforeach; ?>

  <div class="grid-2">
    <!-- Expense Summary -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fa fa-receipt"></i> Expense Summary</div><?= statusBadge($exp['status']) ?></div>
      <div class="card-body">
        <h3 style="font-size:1.1rem;margin-bottom:12px;"><?= e($exp['title']) ?></h3>
        <div class="info-row" style="margin-bottom:16px;">
          <div class="info-item"><span class="info-label">Amount</span><span class="info-value text-success fw-700" style="font-size:1.4rem;"><?= formatMoney((float)$exp['amount']) ?></span></div>
          <div class="info-item"><span class="info-label">Category</span><span class="info-value"><?= e($exp['cat_name'] ?? '–') ?></span></div>
          <div class="info-item"><span class="info-label">Expense Date</span><span class="info-value"><?= formatDate($exp['expense_date']) ?></span></div>
          <div class="info-item"><span class="info-label">Submitted By</span><span class="info-value"><?= e($exp['submitter_name']) ?></span></div>
          <div class="info-item"><span class="info-label">Submitted On</span><span class="info-value"><?= formatDateTime($exp['created_at']) ?></span></div>
        </div>
        <?php if ($exp['description']): ?>
        <div style="background:var(--color-bg-3);border-radius:var(--radius);padding:12px;margin-bottom:16px;">
          <p style="font-size:0.85rem;color:var(--color-text-2);"><?= nl2br(e($exp['description'])) ?></p>
        </div>
        <?php endif; ?>
        <div class="info-row">
          <?php if ($exp['org_name']): ?><div class="info-item"><span class="info-label">Organization</span><span class="info-value"><?= e($exp['org_name']) ?></span></div><?php endif; ?>
          <?php if ($exp['dept_name']): ?><div class="info-item"><span class="info-label">Department</span><span class="info-value"><?= e($exp['dept_name']) ?></span></div><?php endif; ?>
          <?php if ($exp['proj_name']): ?><div class="info-item"><span class="info-label">Project</span><span class="info-value"><?= e($exp['proj_name']) ?></span></div><?php endif; ?>
        </div>
        <?php if ($exp['receipt_path']): ?>
        <div style="margin-top:14px;">
          <a href="<?= BASE_URL ?>/expenses/receipt.php?id=<?= $id ?>" target="_blank" rel="noopener" class="btn btn-sm btn-secondary"><i class="fa fa-paperclip"></i> View Receipt</a>
        </div>
        <?php endif; ?>

        <!-- Budget Impact -->
        <?php if ($budgetInfo): ?>
        <hr class="divider">
        <?php
          $remaining = (float)$budgetInfo['budget'] - (float)$budgetInfo['spent'];
          $afterApproval = $remaining - (float)$exp['amount'];
        ?>
        <p style="font-size:0.82rem;font-weight:600;color:var(--color-text-2);margin-bottom:8px;">Project Budget Impact</p>
        <div class="info-row">
          <div class="info-item"><span class="info-label">Project Budget</span><span class="info-value"><?= formatMoney((float)$budgetInfo['budget']) ?></span></div>
          <div class="info-item"><span class="info-label">Already Spent</span><span class="info-value"><?= formatMoney((float)$budgetInfo['spent']) ?></span></div>
          <div class="info-item"><span class="info-label">Remaining Now</span><span class="info-value <?= $remaining < 0 ? 'text-danger' : 'text-success' ?>"><?= formatMoney($remaining) ?></span></div>
          <div class="info-item"><span class="info-label">After Approval</span><span class="info-value <?= $afterApproval < 0 ? 'text-danger fw-600' : 'text-success' ?>"><?= formatMoney($afterApproval) ?></span></div>
        </div>
        <?php if ($afterApproval < 0): ?>
        <div class="alert alert-danger" style="margin-top:10px;margin-bottom:0;">
          <i class="fa fa-exclamation-triangle"></i> Approving this expense will cause a <strong><?= formatMoney(abs($afterApproval)) ?> budget overrun</strong> on this project.
        </div>
        <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Decision Form -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fa fa-check-circle"></i> Your Decision</div></div>
      <div class="card-body">
        <form method="POST" id="decisionForm">
          <?= csrfField() ?>
          <input type="hidden" name="action" id="actionInput" value="">

          <div class="form-group">
            <label>Notes (Optional)</label>
            <textarea name="notes" placeholder="Add a note for the submitter (optional)..."></textarea>
          </div>

          <div class="form-group" id="rejectionBox" style="display:none;">
            <label class="required">Rejection Reason</label>
            <textarea name="rejection_reason" id="rejectionReason" placeholder="Explain why this expense is being rejected..."></textarea>
            <p class="form-hint">This reason will be visible to the submitter.</p>
          </div>

          <div style="display:flex;gap:12px;margin-top:20px;">
            <button type="button" onclick="submitDecision('approved')" class="btn btn-success" style="flex:1;justify-content:center;">
              <i class="fa fa-check"></i> Approve
            </button>
            <button type="button" onclick="submitDecision('rejected')" class="btn btn-danger" style="flex:1;justify-content:center;">
              <i class="fa fa-times"></i> Reject
            </button>
          </div>
          <a href="<?= BASE_URL ?>/approvals/index.php" class="btn btn-secondary btn-block" style="margin-top:10px;justify-content:center;">Cancel – Decide Later</a>
        </form>
      </div>
    </div>
  </div>
</main>

<script>
function submitDecision(action) {
  const rejBox = document.getElementById('rejectionBox');
  const rejReason = document.getElementById('rejectionReason');
  if (action === 'rejected') {
    rejBox.style.display = '';
    if (!rejReason.value.trim()) {
      rejReason.focus();
      rejReason.style.borderColor = 'var(--color-danger)';
      alert('Please provide a rejection reason before rejecting.');
      return;
    }
  }
  if (action === 'approved') {
    if (!confirm('Are you sure you want to APPROVE this expense of <?= formatMoney((float)$exp['amount']) ?>?')) return;
  }
  document.getElementById('actionInput').value = action;
  document.getElementById('decisionForm').submit();
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

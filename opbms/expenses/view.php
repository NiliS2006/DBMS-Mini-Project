<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$pdo = getDB();
$uid  = $_SESSION['user_id'];
$role = $_SESSION['user_role'];
if ($role === 'personal') { redirect(BASE_URL.'/personal/dashboard.php'); }

$id = sanitizeInt($_GET['id'] ?? 0);
if (!$id) { setFlash('Invalid expense.','danger'); redirect(BASE_URL.'/expenses/index.php'); }

$expenseScope = getExpenseScopeSql($role, (int)$uid, 'e');
$stmt = $pdo->prepare("SELECT e.*, u.name as submitter_name, u.email as submitter_email,
    ec.name as cat_name, p.name as proj_name, d.name as dept_name, o.name as org_name,
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
if (!$exp) { setFlash('Expense not found.','danger'); redirect(BASE_URL.'/expenses/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('Invalid CSRF token.', 'danger');
        redirect(BASE_URL.'/expenses/view.php?id='.$id);
    }
    if ((int)$exp['submitted_by'] !== $uid || $exp['status'] !== 'pending') {
        setFlash('Only your pending expense submissions can be cancelled.', 'danger');
        redirect(BASE_URL.'/expenses/view.php?id='.$id);
    }

    try {
        $pdo->beginTransaction();
        $cancel = $pdo->prepare("UPDATE expenses SET status='cancelled' WHERE id=? AND submitted_by=? AND status='pending'");
        $cancel->execute([$id, $uid]);
        if ($cancel->rowCount() !== 1) {
            $pdo->rollBack();
            setFlash('This expense was already reviewed and cannot be cancelled.', 'warning');
        } else {
            $historyStmt = $pdo->prepare("INSERT INTO approval_history (expense_id, action, action_by, notes)
                VALUES (?, 'cancelled', ?, 'Cancelled by submitter before review.')");
            $historyStmt->execute([$id, $uid]);
            $pdo->commit();
            setFlash('Expense cancelled.', 'success');
        }
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Expense cancellation failed: ' . $ex->getMessage());
        setFlash('Failed to cancel the expense. Please try again.', 'danger');
    }
    redirect(BASE_URL.'/expenses/view.php?id='.$id);
}

// Approval history
$hist = $pdo->prepare("SELECT ah.*, u.name as actor_name FROM approval_history ah JOIN users u ON u.id=ah.action_by WHERE ah.expense_id=? ORDER BY ah.action_at ASC");
$hist->execute([$id]); $history = $hist->fetchAll();

$pageTitle = e($exp['title']);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <?= flashMessage() ?>
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa fa-receipt" style="color:var(--color-primary)"></i> <?= e($exp['title']) ?></h1>
      <p class="page-subtitle">Submitted by <?= e($exp['submitter_name']) ?> on <?= formatDate($exp['expense_date']) ?></p>
    </div>
    <div class="page-actions">
      <?php if ($exp['status']==='pending' && (int)$exp['submitted_by']===$uid): ?>
      <a href="<?= BASE_URL ?>/expenses/edit.php?id=<?= $id ?>" class="btn btn-secondary"><i class="fa fa-edit"></i> Edit</a>
      <form method="POST" onsubmit="return confirm('Cancel this pending expense?');">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="cancel">
        <button type="submit" class="btn btn-danger"><i class="fa fa-times"></i> Cancel Expense</button>
      </form>
      <?php endif; ?>
      <?php if ($exp['status']==='pending' && in_array($role,['admin','dept_manager','project_manager'])): ?>
      <a href="<?= BASE_URL ?>/approvals/review.php?id=<?= $id ?>" class="btn btn-warning"><i class="fa fa-check-circle"></i> Review &amp; Decide</a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/expenses/index.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back</a>
    </div>
  </div>

  <div class="grid-2">
    <!-- Expense Details -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fa fa-info-circle"></i> Expense Details</div><?= statusBadge($exp['status']) ?></div>
      <div class="card-body">
        <div class="info-row" style="margin-bottom:16px;">
          <div class="info-item"><span class="info-label">Amount</span><span class="info-value text-success fw-700" style="font-size:1.3rem;"><?= formatMoney((float)$exp['amount']) ?></span></div>
          <div class="info-item"><span class="info-label">Category</span><span class="info-value"><?= e($exp['cat_name'] ?? '–') ?></span></div>
          <div class="info-item"><span class="info-label">Date</span><span class="info-value"><?= formatDate($exp['expense_date']) ?></span></div>
          <div class="info-item"><span class="info-label">Submitted</span><span class="info-value"><?= formatDateTime($exp['created_at']) ?></span></div>
        </div>
        <?php if ($exp['description']): ?>
        <div style="background:var(--color-bg-3);border-radius:var(--radius);padding:12px;margin-bottom:16px;">
          <p style="font-size:0.85rem;color:var(--color-text-2);"><?= nl2br(e($exp['description'])) ?></p>
        </div>
        <?php endif; ?>
        <div class="info-row">
          <?php if ($exp['org_name']): ?><div class="info-item"><span class="info-label">Organization</span><span class="info-value"><?= e($exp['org_name']) ?></span></div><?php endif; ?>
          <?php if ($exp['dept_name']): ?><div class="info-item"><span class="info-label">Department</span><span class="info-value"><?= e($exp['dept_name']) ?></span></div><?php endif; ?>
          <?php if ($exp['proj_name']): ?><div class="info-item"><span class="info-label">Project</span><span class="info-value"><a href="<?= BASE_URL ?>/projects/view.php?id=<?= $exp['project_id'] ?>"><?= e($exp['proj_name']) ?></a></span></div><?php endif; ?>
        </div>
        <?php if ($exp['receipt_path']): ?>
        <div style="margin-top:16px;">
          <a href="<?= BASE_URL ?>/expenses/receipt.php?id=<?= $id ?>" target="_blank" rel="noopener" class="btn btn-sm btn-secondary"><i class="fa fa-paperclip"></i> View Receipt</a>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Status & Decision -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fa fa-gavel"></i> Approval Decision</div></div>
      <div class="card-body">
        <?php if ($exp['status'] === 'approved'): ?>
        <div class="alert alert-success"><i class="fa fa-check-circle"></i> <strong>Approved</strong> by <?= e($exp['approver_name'] ?? '–') ?> on <?= formatDateTime($exp['approved_at']) ?></div>
        <?php elseif ($exp['status'] === 'rejected'): ?>
        <div class="alert alert-danger"><i class="fa fa-times-circle"></i> <strong>Rejected</strong> by <?= e($exp['approver_name'] ?? '–') ?> on <?= formatDateTime($exp['approved_at']) ?></div>
        <?php if ($exp['rejection_reason']): ?>
        <div class="approval-box"><strong>Rejection Reason:</strong><br><?= nl2br(e($exp['rejection_reason'])) ?></div>
        <?php endif; ?>
        <?php elseif ($exp['status'] === 'cancelled'): ?>
        <div class="alert alert-info"><i class="fa fa-ban"></i> <strong>Cancelled</strong> by the submitter before review.</div>
        <?php else: ?>
        <div class="alert alert-warning"><i class="fa fa-clock"></i> <strong>Pending review.</strong> Awaiting manager decision.</div>
        <?php if (in_array($role,['admin','dept_manager','project_manager'])): ?>
        <a href="<?= BASE_URL ?>/approvals/review.php?id=<?= $id ?>" class="btn btn-warning" style="margin-top:8px;"><i class="fa fa-check-circle"></i> Review &amp; Decide</a>
        <?php endif; ?>
        <?php endif; ?>

        <hr class="divider">
        <p style="font-size:0.82rem;font-weight:600;color:var(--color-text-2);margin-bottom:12px;">Activity History</p>
        <?php if (empty($history)): ?>
        <p class="text-muted" style="font-size:0.85rem;">No history available.</p>
        <?php else: ?>
        <?php foreach ($history as $h): ?>
        <div style="display:flex;gap:12px;margin-bottom:12px;align-items:flex-start;">
          <div style="width:32px;height:32px;border-radius:50%;background:var(--color-<?= $h['action']==='approved'?'success':($h['action']==='rejected'?'danger':'primary') ?>-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fa fa-<?= $h['action']==='approved'?'check':($h['action']==='rejected'?'times':'paper-plane') ?>" style="color:var(--color-<?= $h['action']==='approved'?'success':($h['action']==='rejected'?'danger':'primary') ?>);font-size:0.8rem;"></i>
          </div>
          <div>
            <span style="font-size:0.82rem;font-weight:600;"><?= e($h['actor_name']) ?></span>
            <span style="font-size:0.78rem;color:var(--color-text-3);margin-left:6px;"><?= ucfirst($h['action']) ?></span><br>
            <span style="font-size:0.75rem;color:var(--color-text-3);"><?= formatDateTime($h['action_at']) ?></span>
            <?php if ($h['notes']): ?><br><span style="font-size:0.78rem;color:var(--color-text-2);"><?= e($h['notes']) ?></span><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

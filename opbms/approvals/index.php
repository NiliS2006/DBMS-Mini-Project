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

$pendingExpenses = $pdo->query("SELECT e.*, u.name as submitter_name, p.name as proj_name, d.name as dept_name, ec.name as cat_name
    FROM expenses e
    JOIN users u ON u.id=e.submitted_by
    LEFT JOIN projects p ON p.id=e.project_id
    LEFT JOIN departments d ON d.id=e.department_id
    LEFT JOIN expense_categories ec ON ec.id=e.category_id
    WHERE e.status='pending'
    ORDER BY e.created_at ASC")->fetchAll();

$pageTitle = 'Pending Approvals';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <?= flashMessage() ?>
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa fa-check-circle" style="color:var(--color-primary)"></i> Pending Approvals</h1>
      <p class="page-subtitle">Expenses awaiting your review and decision</p>
    </div>
    <div style="background:var(--color-warning-bg);border:1px solid var(--color-warning);border-radius:var(--radius);padding:8px 14px;font-size:0.85rem;color:var(--color-warning);">
      <i class="fa fa-clock"></i> <?= count($pendingExpenses) ?> expense<?= count($pendingExpenses)!==1?'s':'' ?> pending
    </div>
  </div>

  <div class="card">
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Title</th><th>Amount</th><th>Category</th><th>Project</th><th>Submitted By</th><th>Date</th><th>Waiting</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php if (empty($pendingExpenses)): ?>
            <tr><td colspan="8">
              <div class="empty-state">
                <i class="fa fa-check-double"></i>
                <h3>All Clear!</h3>
                <p>No pending expenses require your attention.</p>
              </div>
            </td></tr>
            <?php else: ?>
            <?php foreach ($pendingExpenses as $exp):
              $waiting = floor((time() - strtotime($exp['created_at'])) / 86400);
            ?>
            <tr>
              <td><a href="<?= BASE_URL ?>/expenses/view.php?id=<?= $exp['id'] ?>"><strong><?= e($exp['title']) ?></strong></a></td>
              <td class="font-mono fw-600"><?= formatMoney((float)$exp['amount']) ?></td>
              <td><?= e($exp['cat_name'] ?? '–') ?></td>
              <td><?= e($exp['proj_name'] ?? '–') ?></td>
              <td><?= e($exp['submitter_name']) ?></td>
              <td><?= formatDate($exp['expense_date']) ?></td>
              <td>
                <span class="badge <?= $waiting > 3 ? 'badge-danger' : ($waiting > 1 ? 'badge-warning' : 'badge-secondary') ?>">
                  <?= $waiting === 0 ? 'Today' : $waiting.'d ago' ?>
                </span>
              </td>
              <td>
                <a href="<?= BASE_URL ?>/approvals/review.php?id=<?= $exp['id'] ?>" class="btn btn-sm btn-warning">
                  <i class="fa fa-gavel"></i> Review
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin']);
$pdo = getDB();
$orgs = $pdo->query("SELECT o.*, u.name as creator, 
    (SELECT COUNT(*) FROM departments d WHERE d.organization_id=o.id AND d.is_active=1) AS dept_count,
    (SELECT COALESCE(SUM(amount),0) FROM budgets b WHERE b.entity_type='organization' AND b.entity_id=o.id AND b.status='approved' AND b.fiscal_year=YEAR(CURDATE())) AS approved_budget,
    (SELECT COALESCE(SUM(e.amount),0) FROM expenses e WHERE e.organization_id=o.id AND e.status='approved' AND YEAR(e.expense_date)=YEAR(CURDATE())) AS total_exp
    FROM organizations o JOIN users u ON u.id=o.created_by WHERE o.is_active=1 ORDER BY o.name")->fetchAll();
$pageTitle = 'Organizations';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <?= flashMessage() ?>
  <div class="page-header">
    <div><h1 class="page-title"><i class="fa fa-building" style="color:var(--color-primary)"></i> Organizations</h1></div>
    <div class="page-actions"><a href="<?= BASE_URL ?>/organization/create.php" class="btn btn-primary"><i class="fa fa-plus"></i> New Organization</a></div>
  </div>
  <div class="grid-2">
    <?php foreach ($orgs as $org): ?>
    <div class="card" style="margin-bottom:0;">
      <div class="card-header">
        <div class="card-title"><i class="fa fa-building"></i> <?= e($org['name']) ?></div>
        <div style="display:flex;gap:6px;">
          <a href="<?= BASE_URL ?>/organization/edit.php?id=<?= $org['id'] ?>" class="btn btn-sm btn-secondary"><i class="fa fa-edit"></i></a>
          <a href="<?= BASE_URL ?>/organization/view.php?id=<?= $org['id'] ?>" class="btn btn-sm btn-primary"><i class="fa fa-eye"></i></a>
        </div>
      </div>
      <div class="card-body">
        <p style="font-size:0.85rem;color:var(--color-text-2);margin-bottom:12px;"><?= e($org['description'] ?? '') ?></p>
        <div class="info-row">
          <div class="info-item"><span class="info-label">Departments</span><span class="info-value"><?= $org['dept_count'] ?></span></div>
          <div class="info-item"><span class="info-label">Budget (<?= date('Y') ?>)</span><span class="info-value"><?= formatMoney((float)$org['approved_budget']) ?></span></div>
          <div class="info-item"><span class="info-label">Expenditure</span><span class="info-value <?= $org['total_exp'] > $org['approved_budget'] && $org['approved_budget'] > 0 ? 'text-danger' : '' ?>"><?= formatMoney((float)$org['total_exp']) ?></span></div>
          <?php if ($org['approved_budget'] > 0): $util = $org['total_exp']/$org['approved_budget']*100; ?>
          <div class="info-item"><span class="info-label">Utilization</span><span class="info-value <?= $util>=100?'text-danger':($util>=80?'text-warning':'text-success') ?>"><?= number_format($util,1) ?>%</span></div>
          <?php endif; ?>
        </div>
        <?php if ($org['contact_email']): ?>
        <div style="margin-top:10px;font-size:0.8rem;color:var(--color-text-3);"><i class="fa fa-envelope"></i> <?= e($org['contact_email']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($orgs)): ?>
    <div class="empty-state"><i class="fa fa-building"></i><h3>No Organizations</h3><p>Create your first organization.</p><a href="<?= BASE_URL ?>/organization/create.php" class="btn btn-primary" style="margin-top:12px;">Create</a></div>
    <?php endif; ?>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

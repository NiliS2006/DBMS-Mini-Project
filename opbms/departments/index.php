<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin','dept_manager']);
$pdo = getDB();
$uid  = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

if ($role === 'admin') {
    $depts = $pdo->query("SELECT d.*, o.name as org_name, u.name as mgr_name,
        COALESCE(b.amount,0) AS budget,
        (SELECT COALESCE(SUM(e.amount),0) FROM expenses e WHERE e.department_id=d.id AND e.status='approved') AS expenditure
        FROM departments d
        JOIN organizations o ON o.id=d.organization_id
        LEFT JOIN users u ON u.id=d.manager_id
        LEFT JOIN budgets b ON b.entity_type='department' AND b.entity_id=d.id AND b.status='approved'
        WHERE d.is_active=1 ORDER BY o.name, d.name")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT d.*, o.name as org_name, u.name as mgr_name,
        COALESCE(b.amount,0) AS budget,
        (SELECT COALESCE(SUM(e.amount),0) FROM expenses e WHERE e.department_id=d.id AND e.status='approved') AS expenditure
        FROM departments d
        JOIN organizations o ON o.id=d.organization_id
        LEFT JOIN users u ON u.id=d.manager_id
        LEFT JOIN budgets b ON b.entity_type='department' AND b.entity_id=d.id AND b.status='approved'
        WHERE d.manager_id=? AND d.is_active=1 ORDER BY d.name");
    $stmt->execute([$uid]);
    $depts = $stmt->fetchAll();
}
$pageTitle = 'Departments';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <?= flashMessage() ?>
  <div class="page-header">
    <div><h1 class="page-title"><i class="fa fa-sitemap" style="color:var(--color-primary)"></i> Departments</h1></div>
    <?php if ($role === 'admin'): ?>
    <a href="<?= BASE_URL ?>/departments/create.php" class="btn btn-primary"><i class="fa fa-plus"></i> New Department</a>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Department</th><th>Organization</th><th>Manager</th><th>Budget</th><th>Expenditure</th><th>Utilization</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if (empty($depts)): ?>
            <tr><td colspan="7"><div class="empty-state"><i class="fa fa-sitemap"></i><h3>No Departments</h3></div></td></tr>
            <?php else: ?>
            <?php foreach ($depts as $d):
              $util = $d['budget'] > 0 ? $d['expenditure']/$d['budget']*100 : 0;
            ?>
            <tr>
              <td><strong><?= e($d['name']) ?></strong></td>
              <td><?= e($d['org_name']) ?></td>
              <td><?= e($d['mgr_name'] ?? '<em>Unassigned</em>') ?></td>
              <td class="font-mono"><?= formatMoney((float)$d['budget']) ?></td>
              <td class="font-mono <?= $d['expenditure']>$d['budget'] && $d['budget']>0 ? 'text-danger' : '' ?>"><?= formatMoney((float)$d['expenditure']) ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:8px;">
                  <?= utilizationBar($util) ?>
                  <span style="font-size:0.8rem;min-width:42px;"><?= number_format($util,1) ?>%</span>
                </div>
              </td>
              <td>
                <div style="display:flex;gap:6px;">
                  <a href="<?= BASE_URL ?>/departments/view.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-secondary"><i class="fa fa-eye"></i></a>
                  <?php if ($role==='admin'): ?>
                  <a href="<?= BASE_URL ?>/departments/edit.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-primary"><i class="fa fa-edit"></i></a>
                  <?php endif; ?>
                </div>
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

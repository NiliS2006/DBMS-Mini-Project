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

[$projectScope, $projectParams] = getProjectScopeSql($role, (int)$uid);
$projectStmt = $pdo->prepare("SELECT p.*, d.name as dept_name, o.name as org_name, u.name as mgr_name,
        COALESCE(v.allocated_budget,0) AS proj_budget,
        COALESCE(v.approved_expenditure,0) AS proj_exp,
        COALESCE(v.pending_amount,0) AS pending_exp
    FROM projects p
    JOIN departments d ON d.id=p.department_id
    JOIN organizations o ON o.id=d.organization_id
    LEFT JOIN users u ON u.id=p.manager_id
    LEFT JOIN v_project_budget_summary v ON v.project_id=p.id AND v.fiscal_year=YEAR(CURDATE())
    WHERE {$projectScope}
    ORDER BY o.name, d.name, p.status, p.name");
$projectStmt->execute($projectParams);
$projects = $projectStmt->fetchAll();

$pageTitle = 'Projects';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <?= flashMessage() ?>
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa fa-project-diagram" style="color:var(--color-primary)"></i> Projects</h1>
      <p class="page-subtitle">All projects and their budget utilization</p>
    </div>
    <div class="page-actions">
      <?php if ($role === 'admin'): ?>
      <a href="<?= BASE_URL ?>/projects/create.php" class="btn btn-primary"><i class="fa fa-plus"></i> New Project</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Status filter pills -->
  <?php
  $statusFilter = $_GET['status'] ?? 'all';
  $statuses = ['all'=>'All', 'active'=>'Active', 'completed'=>'Completed', 'on_hold'=>'On Hold', 'cancelled'=>'Cancelled'];
  ?>
  <div class="pill-tabs">
    <?php foreach ($statuses as $k => $v): ?>
    <a href="?status=<?= $k ?>" class="pill-tab <?= $statusFilter === $k ? 'active' : '' ?>"><?= $v ?></a>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Project</th><th>Department</th><th>Manager</th><th>Budget</th><th>Expenditure</th><th>Utilization</th><th>Status</th><th>Dates</th><th>Actions</th></tr></thead>
          <tbody>
            <?php
            $filtered = array_filter($projects, fn($p) => $statusFilter === 'all' || $p['status'] === $statusFilter);
            if (empty($filtered)): ?>
            <tr><td colspan="9"><div class="empty-state"><i class="fa fa-project-diagram"></i><h3>No Projects Found</h3><p>No projects match the current filter.</p></div></td></tr>
            <?php else: ?>
            <?php foreach ($filtered as $p):
              $pu = $p['proj_budget'] > 0 ? $p['proj_exp']/$p['proj_budget']*100 : 0;
            ?>
            <tr>
              <td>
                <a href="<?= BASE_URL ?>/projects/view.php?id=<?= $p['id'] ?>"><strong><?= e($p['name']) ?></strong></a>
                <?php if ($p['proj_exp'] > $p['proj_budget'] && $p['proj_budget'] > 0): ?>
                <span class="badge badge-danger" style="margin-left:4px;">Overrun</span>
                <?php endif; ?>
              </td>
              <td><?= e($p['dept_name']) ?><br><span style="font-size:0.75rem;color:var(--color-text-3);"><?= e($p['org_name']) ?></span></td>
              <td><?= e($p['mgr_name'] ?? 'Unassigned') ?></td>
              <td class="font-mono"><?= formatMoney((float)$p['proj_budget']) ?></td>
              <td>
                <span class="font-mono <?= $p['proj_exp'] > $p['proj_budget'] && $p['proj_budget'] > 0 ? 'text-danger' : '' ?>"><?= formatMoney((float)$p['proj_exp']) ?></span>
                <?php if ($p['pending_exp'] > 0): ?>
                <br><span style="font-size:0.72rem;color:var(--color-warning);">+<?= formatMoney((float)$p['pending_exp']) ?> pending</span>
                <?php endif; ?>
              </td>
              <td style="min-width:140px;">
                <div style="display:flex;align-items:center;gap:8px;">
                  <?= utilizationBar($pu) ?>
                  <span style="font-size:0.8rem;min-width:42px;"><?= number_format($pu,1) ?>%</span>
                </div>
              </td>
              <td><?= statusBadge($p['status']) ?></td>
              <td style="font-size:0.78rem;color:var(--color-text-2);">
                <?= $p['start_date'] ? formatDate($p['start_date']) : '-' ?><br>
                <?= $p['end_date'] ? '→ '.formatDate($p['end_date']) : '' ?>
              </td>
              <td>
                <div style="display:flex;gap:6px;">
                  <a href="<?= BASE_URL ?>/projects/view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary"><i class="fa fa-eye"></i></a>
                  <?php if ($role === 'admin'): ?>
                  <a href="<?= BASE_URL ?>/projects/edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary"><i class="fa fa-edit"></i></a>
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

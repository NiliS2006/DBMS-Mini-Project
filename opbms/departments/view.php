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

$id = sanitizeInt($_GET['id'] ?? 0);
if (!$id) { setFlash('Invalid department.','danger'); redirect(BASE_URL.'/departments/index.php'); }

$stmt = $pdo->prepare("SELECT d.*, o.name as org_name, u.name as mgr_name
    FROM departments d
    JOIN organizations o ON o.id=d.organization_id
    LEFT JOIN users u ON u.id=d.manager_id
    WHERE d.id=? AND d.is_active=1");
$stmt->execute([$id]); $dept = $stmt->fetch();
if (!$dept) { setFlash('Department not found.','danger'); redirect(BASE_URL.'/departments/index.php'); }

// Dept manager can only see their own dept
if ($role === 'dept_manager' && $dept['manager_id'] != $uid) {
    http_response_code(403); require_once __DIR__.'/../includes/403.php'; exit;
}

// Budget
$summaryStmt = $pdo->prepare("SELECT allocated_budget, approved_expenditure FROM v_dept_budget_summary
    WHERE dept_id=? AND fiscal_year=YEAR(CURDATE())");
$summaryStmt->execute([$id]);
$summary = $summaryStmt->fetch() ?: ['allocated_budget'=>0, 'approved_expenditure'=>0];
$budget = (float)$summary['allocated_budget'];
$approvedExp = (float)$summary['approved_expenditure'];

$pendExp = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses
    WHERE department_id=? AND status='pending' AND YEAR(expense_date)=YEAR(CURDATE())");
$pendExp->execute([$id]); $pendingExp = (float)$pendExp->fetchColumn();

$remaining = $budget - $approvedExp;
$util = $budget > 0 ? round($approvedExp / $budget * 100, 2) : 0;

// Projects in this dept
$projQ = "SELECT p.*, u.name as mgr_name,
    COALESCE(v.allocated_budget,0) AS proj_budget,
    COALESCE(v.approved_expenditure,0) AS proj_exp
    FROM projects p
    LEFT JOIN users u ON u.id=p.manager_id
    LEFT JOIN v_project_budget_summary v ON v.project_id=p.id AND v.fiscal_year=YEAR(CURDATE())
    WHERE p.department_id=?
    ORDER BY p.status, p.name";
$projStmt = $pdo->prepare($projQ);
$projStmt->execute([$id]); $projects = $projStmt->fetchAll();

// Recent expenses
$expStmt = $pdo->prepare("SELECT e.*, u.name as submitter_name, ec.name as cat_name
    FROM expenses e JOIN users u ON u.id=e.submitted_by
    LEFT JOIN expense_categories ec ON ec.id=e.category_id
    WHERE e.department_id=? AND YEAR(e.expense_date)=YEAR(CURDATE()) ORDER BY e.created_at DESC LIMIT 8");
$expStmt->execute([$id]); $expenses = $expStmt->fetchAll();

$pageTitle = e($dept['name']);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <?= flashMessage() ?>
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa fa-sitemap" style="color:var(--color-primary)"></i> <?= e($dept['name']) ?></h1>
      <p class="page-subtitle"><?= e($dept['org_name']) ?> &mdash; Manager: <?= e($dept['mgr_name'] ?? 'Unassigned') ?></p>
    </div>
    <div class="page-actions">
      <?php if ($role === 'admin'): ?>
      <a href="<?= BASE_URL ?>/departments/edit.php?id=<?= $id ?>" class="btn btn-secondary"><i class="fa fa-edit"></i> Edit</a>
      <a href="<?= BASE_URL ?>/projects/create.php?dept_id=<?= $id ?>" class="btn btn-primary"><i class="fa fa-plus"></i> Add Project</a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/departments/index.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back</a>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card"><div class="stat-icon"><i class="fa fa-wallet"></i></div><div class="stat-value" style="font-size:1.1rem;"><?= formatMoney($budget) ?></div><div class="stat-label">Approved Budget</div></div>
    <div class="stat-card warning"><div class="stat-icon"><i class="fa fa-receipt"></i></div><div class="stat-value" style="font-size:1.1rem;"><?= formatMoney($approvedExp) ?></div><div class="stat-label">Approved Expenditure</div></div>
    <div class="stat-card <?= $remaining < 0 ? 'danger' : 'success' ?>"><div class="stat-icon"><i class="fa fa-balance-scale"></i></div><div class="stat-value" style="font-size:1.1rem;"><?= formatMoney(abs($remaining)) ?></div><div class="stat-label"><?= $remaining < 0 ? 'Overrun' : 'Remaining' ?></div></div>
    <div class="stat-card info"><div class="stat-icon"><i class="fa fa-clock"></i></div><div class="stat-value" style="font-size:1.1rem;"><?= formatMoney($pendingExp) ?></div><div class="stat-label">Pending Expenses</div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fa fa-project-diagram"></i></div><div class="stat-value"><?= count($projects) ?></div><div class="stat-label">Projects</div></div>
  </div>

  <?php if ($budget > 0): ?>
  <div class="card" style="margin-bottom:20px;">
    <div class="card-body">
      <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
        <span style="font-size:0.85rem;font-weight:600;">Budget Utilization</span>
        <span class="badge <?= $util >= 100 ? 'badge-danger' : ($util >= 80 ? 'badge-warning' : 'badge-success') ?>"><?= number_format($util, 1) ?>%</span>
      </div>
      <div class="progress-bar-wrap" style="height:12px;">
        <div class="progress-bar bg-<?= $util >= 100 ? 'danger' : ($util >= 80 ? 'warning' : 'success') ?>" style="width:<?= min(100,$util) ?>%"></div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Projects -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fa fa-project-diagram"></i> Projects</div>
      <?php if ($role === 'admin'): ?>
      <a href="<?= BASE_URL ?>/projects/create.php?dept_id=<?= $id ?>" class="btn btn-sm btn-primary"><i class="fa fa-plus"></i> Add</a>
      <?php endif; ?>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Project</th><th>Manager</th><th>Budget</th><th>Expenditure</th><th>Utilization</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if (empty($projects)): ?>
            <tr><td colspan="7"><div class="empty-state"><i class="fa fa-project-diagram"></i><p>No projects in this department.</p></div></td></tr>
            <?php else: ?>
            <?php foreach ($projects as $p):
              $pu = $p['proj_budget'] > 0 ? $p['proj_exp']/$p['proj_budget']*100 : 0;
            ?>
            <tr>
              <td><a href="<?= BASE_URL ?>/projects/view.php?id=<?= $p['id'] ?>"><strong><?= e($p['name']) ?></strong></a></td>
              <td><?= e($p['mgr_name'] ?? 'Unassigned') ?></td>
              <td class="font-mono"><?= formatMoney((float)$p['proj_budget']) ?></td>
              <td class="font-mono <?= $p['proj_exp'] > $p['proj_budget'] && $p['proj_budget'] > 0 ? 'text-danger' : '' ?>"><?= formatMoney((float)$p['proj_exp']) ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:8px;">
                  <?= utilizationBar($pu) ?>
                  <span style="font-size:0.8rem;min-width:42px;"><?= number_format($pu,1) ?>%</span>
                </div>
              </td>
              <td><?= statusBadge($p['status']) ?></td>
              <td><a href="<?= BASE_URL ?>/projects/view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary"><i class="fa fa-eye"></i></a></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Recent Expenses -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fa fa-receipt"></i> Recent Expenses</div>
      <a href="<?= BASE_URL ?>/expenses/index.php?dept_id=<?= $id ?>" class="btn btn-sm btn-secondary">View All</a>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Title</th><th>Category</th><th>Amount</th><th>Date</th><th>Submitted By</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (empty($expenses)): ?>
            <tr><td colspan="6"><div class="empty-state"><i class="fa fa-receipt"></i><p>No expenses found.</p></div></td></tr>
            <?php else: ?>
            <?php foreach ($expenses as $exp): ?>
            <tr>
              <td><a href="<?= BASE_URL ?>/expenses/view.php?id=<?= $exp['id'] ?>"><?= e($exp['title']) ?></a></td>
              <td><?= e($exp['cat_name'] ?? '-') ?></td>
              <td class="font-mono"><?= formatMoney((float)$exp['amount']) ?></td>
              <td><?= formatDate($exp['expense_date']) ?></td>
              <td><?= e($exp['submitter_name']) ?></td>
              <td><?= statusBadge($exp['status']) ?></td>
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

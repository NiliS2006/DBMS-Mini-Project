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
if (!$id) { setFlash('Invalid project.','danger'); redirect(BASE_URL.'/projects/index.php'); }

$projectScope = getProjectScopeSql($role, (int)$uid);
$stmt = $pdo->prepare("SELECT p.*, d.name as dept_name, d.id as dept_id, o.name as org_name, o.id as org_id, u.name as mgr_name
    FROM projects p
    JOIN departments d ON d.id=p.department_id
    JOIN organizations o ON o.id=d.organization_id
    LEFT JOIN users u ON u.id=p.manager_id
    WHERE p.id=? AND ({$projectScope[0]})");
$stmt->execute(array_merge([$id], $projectScope[1])); $project = $stmt->fetch();
if (!$project) { setFlash('Project not found.','danger'); redirect(BASE_URL.'/projects/index.php'); }

// Budget summary
$summaryStmt = $pdo->prepare("SELECT allocated_budget, approved_expenditure, pending_amount
    FROM v_project_budget_summary WHERE project_id=? AND fiscal_year=YEAR(CURDATE())");
$summaryStmt->execute([$id]);
$summary = $summaryStmt->fetch() ?: ['allocated_budget'=>0, 'approved_expenditure'=>0, 'pending_amount'=>0];
$budget = (float)$summary['allocated_budget'];
$approvedExp = (float)$summary['approved_expenditure'];
$pendingExp = (float)$summary['pending_amount'];
$remaining = $budget - $approvedExp;
$util = $budget > 0 ? round($approvedExp / $budget * 100, 2) : 0;

// Team members
$members = $pdo->prepare("SELECT pm.role as proj_role, u.name, u.email, u.role as sys_role
    FROM project_members pm JOIN users u ON u.id=pm.user_id WHERE pm.project_id=? ORDER BY pm.role, u.name");
$members->execute([$id]); $team = $members->fetchAll();

// Expenses
$expStmt = $pdo->prepare("SELECT e.*, u.name as submitter_name, ec.name as cat_name, a.name as approver_name
    FROM expenses e
    JOIN users u ON u.id=e.submitted_by
    LEFT JOIN expense_categories ec ON ec.id=e.category_id
    LEFT JOIN users a ON a.id=e.approved_by
    WHERE e.project_id=? ORDER BY e.created_at DESC LIMIT 12");
$expStmt->execute([$id]); $expenses = $expStmt->fetchAll();

// Category breakdown (for chart)
$catBreakdown = $pdo->prepare("SELECT ec.name as cat_name, COALESCE(SUM(e.amount),0) as total
    FROM expenses e LEFT JOIN expense_categories ec ON ec.id=e.category_id
    WHERE e.project_id=? AND e.status='approved' AND YEAR(e.expense_date)=YEAR(CURDATE())
    GROUP BY e.category_id, ec.name ORDER BY total DESC LIMIT 8");
$catBreakdown->execute([$id]); $catData = $catBreakdown->fetchAll();

$pageTitle = e($project['name']);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <?= flashMessage() ?>
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa fa-project-diagram" style="color:var(--color-primary)"></i> <?= e($project['name']) ?></h1>
      <p class="page-subtitle"><?= e($project['org_name']) ?> / <?= e($project['dept_name']) ?> &mdash; <?= statusBadge($project['status']) ?></p>
    </div>
    <div class="page-actions">
      <?php if ($role === 'admin'): ?>
      <a href="<?= BASE_URL ?>/projects/edit.php?id=<?= $id ?>" class="btn btn-secondary"><i class="fa fa-edit"></i> Edit</a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/expenses/create.php?project_id=<?= $id ?>" class="btn btn-primary"><i class="fa fa-plus"></i> Add Expense</a>
      <a href="<?= BASE_URL ?>/budgets/create.php?entity_type=project&entity_id=<?= $id ?>" class="btn btn-secondary"><i class="fa fa-wallet"></i> Set Budget</a>
      <a href="<?= BASE_URL ?>/projects/index.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back</a>
    </div>
  </div>

  <?php if ($project['description']): ?>
  <div class="card" style="margin-bottom:16px;">
    <div class="card-body"><p style="color:var(--color-text-2);font-size:0.9rem;"><?= e($project['description']) ?></p></div>
  </div>
  <?php endif; ?>

  <div class="stats-grid">
    <div class="stat-card"><div class="stat-icon"><i class="fa fa-wallet"></i></div><div class="stat-value" style="font-size:1.1rem;"><?= formatMoney($budget) ?></div><div class="stat-label">Approved Budget</div></div>
    <div class="stat-card warning"><div class="stat-icon"><i class="fa fa-receipt"></i></div><div class="stat-value" style="font-size:1.1rem;"><?= formatMoney($approvedExp) ?></div><div class="stat-label">Approved Expenditure</div></div>
    <div class="stat-card <?= $remaining < 0 ? 'danger' : 'success' ?>"><div class="stat-icon"><i class="fa fa-balance-scale"></i></div><div class="stat-value" style="font-size:1.1rem;"><?= formatMoney(abs($remaining)) ?></div><div class="stat-label"><?= $remaining < 0 ? 'Overrun' : 'Remaining Budget' ?></div></div>
    <div class="stat-card info"><div class="stat-icon"><i class="fa fa-clock"></i></div><div class="stat-value" style="font-size:1.1rem;"><?= formatMoney($pendingExp) ?></div><div class="stat-label">Pending Expenses</div></div>
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
      <?php if ($util >= 80): ?>
      <div class="alert alert-<?= $util >= 100 ? 'danger' : 'warning' ?>" style="margin-top:10px;margin-bottom:0;">
        <i class="fa fa-exclamation-triangle"></i>
        <?= $util >= 100 ? 'Budget overrun! This project has exceeded its approved budget.' : 'Warning: Over 80% of budget utilized.' ?>
        <?php if ($remaining < 0): ?> Overrun: <?= formatMoney(abs($remaining)) ?><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="grid-2">
    <!-- Team Members -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fa fa-users"></i> Team Members</div>
      </div>
      <div class="card-body" style="padding:0;">
        <?php if (empty($team)): ?>
        <div class="empty-state"><i class="fa fa-users"></i><p>No team members assigned.</p></div>
        <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Name</th><th>System Role</th><th>Project Role</th></tr></thead>
            <tbody>
              <?php foreach ($team as $m): ?>
              <tr>
                <td><?= e($m['name']) ?><br><span style="font-size:0.75rem;color:var(--color-text-3);"><?= e($m['email']) ?></span></td>
                <td><?= statusBadge($m['sys_role']) ?></td>
                <td><?= $m['proj_role'] === 'manager' ? '<span class="badge badge-primary">Manager</span>' : '<span class="badge badge-secondary">Member</span>' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Category Breakdown Chart -->
    <?php if (!empty($catData)): ?>
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fa fa-chart-pie"></i> Expense by Category</div></div>
      <div class="card-body">
        <div class="chart-container"><canvas id="catChart"></canvas></div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Project Dates -->
  <div class="card">
    <div class="card-header"><div class="card-title"><i class="fa fa-calendar"></i> Project Info</div></div>
    <div class="card-body">
      <div class="info-row">
        <div class="info-item"><span class="info-label">Start Date</span><span class="info-value"><?= $project['start_date'] ? formatDate($project['start_date']) : '-' ?></span></div>
        <div class="info-item"><span class="info-label">End Date</span><span class="info-value"><?= $project['end_date'] ? formatDate($project['end_date']) : '-' ?></span></div>
        <div class="info-item"><span class="info-label">Manager</span><span class="info-value"><?= e($project['mgr_name'] ?? 'Unassigned') ?></span></div>
        <div class="info-item"><span class="info-label">Department</span><span class="info-value"><?= e($project['dept_name']) ?></span></div>
        <div class="info-item"><span class="info-label">Organization</span><span class="info-value"><?= e($project['org_name']) ?></span></div>
        <div class="info-item"><span class="info-label">Status</span><span class="info-value"><?= statusBadge($project['status']) ?></span></div>
      </div>
    </div>
  </div>

  <!-- Expenses List -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fa fa-receipt"></i> Expenses</div>
      <div style="display:flex;gap:8px;">
        <a href="<?= BASE_URL ?>/expenses/create.php?project_id=<?= $id ?>" class="btn btn-sm btn-primary"><i class="fa fa-plus"></i> Add Expense</a>
        <a href="<?= BASE_URL ?>/expenses/index.php?project_id=<?= $id ?>" class="btn btn-sm btn-secondary">View All</a>
      </div>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Title</th><th>Category</th><th>Amount</th><th>Date</th><th>Submitted By</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
            <?php if (empty($expenses)): ?>
            <tr><td colspan="7"><div class="empty-state"><i class="fa fa-receipt"></i><p>No expenses yet.</p></div></td></tr>
            <?php else: ?>
            <?php foreach ($expenses as $exp): ?>
            <tr>
              <td><a href="<?= BASE_URL ?>/expenses/view.php?id=<?= $exp['id'] ?>"><?= e($exp['title']) ?></a></td>
              <td><?= e($exp['cat_name'] ?? '-') ?></td>
              <td class="font-mono"><?= formatMoney((float)$exp['amount']) ?></td>
              <td><?= formatDate($exp['expense_date']) ?></td>
              <td><?= e($exp['submitter_name']) ?></td>
              <td><?= statusBadge($exp['status']) ?></td>
              <td>
                <?php if ($exp['status'] === 'pending' && in_array($role, ['admin','dept_manager','project_manager'])): ?>
                <a href="<?= BASE_URL ?>/approvals/review.php?id=<?= $exp['id'] ?>" class="btn btn-sm btn-warning">Review</a>
                <?php endif; ?>
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

<script>
<?php if (!empty($catData)): ?>
const catLabels = <?= json_encode(array_column($catData, 'cat_name')) ?>;
const catValues = <?= json_encode(array_map(fn($r) => (float)$r['total'], $catData)) ?>;
const catCtx = document.getElementById('catChart');
if (catCtx) {
  new Chart(catCtx, {
    type: 'doughnut',
    data: {
      labels: catLabels,
      datasets: [{
        data: catValues,
        backgroundColor: ['#4f8ef7','#22c55e','#f59e0b','#ef4444','#7c5cfc','#3b82f6','#06b6d4','#84cc16'],
        borderWidth: 0,
        hoverOffset: 8
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { position: 'right', labels: { color: '#94a3b8', font: { size: 12 } } },
        tooltip: { callbacks: { label: ctx => ' ₹' + ctx.parsed.toLocaleString('en-IN') } }
      }
    }
  });
}
<?php endif; ?>
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

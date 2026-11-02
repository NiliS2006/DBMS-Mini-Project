<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
if ($_SESSION['user_role'] === 'personal') {
    redirect(BASE_URL . '/personal/dashboard.php');
}

$pdo  = getDB();
$role = $_SESSION['user_role'];
$uid  = (int)$_SESSION['user_id'];

// ---- Fetch summary stats ----
// Total organisations
$totalOrgs = $pdo->query("SELECT COUNT(*) FROM organizations WHERE is_active=1")->fetchColumn();
// Total depts
$totalDepts = $pdo->query("SELECT COUNT(*) FROM departments WHERE is_active=1")->fetchColumn();
// Total projects
$projectScope = getProjectScopeSql($role, $uid);
$projectCount = $pdo->prepare("SELECT COUNT(*) FROM projects p WHERE p.status='active' AND ({$projectScope[0]})");
$projectCount->execute($projectScope[1]);
$totalProjects = $projectCount->fetchColumn();

// Keep budget totals at the role's own allocation level.
if ($role === 'admin') {
    $budgetStmt = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM budgets WHERE entity_type='organization' AND status='approved' AND fiscal_year=YEAR(CURDATE())");
} elseif ($role === 'dept_manager') {
    $budgetStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM budgets
        WHERE entity_type='department' AND status='approved' AND fiscal_year=YEAR(CURDATE())
          AND entity_id IN (SELECT id FROM departments WHERE manager_id=?)");
    $budgetStmt->execute([$uid]);
} else {
    $budgetStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM budgets
        WHERE entity_type='project' AND status='approved' AND fiscal_year=YEAR(CURDATE())
          AND entity_id IN (SELECT p.id FROM projects p WHERE {$projectScope[0]})");
    $budgetStmt->execute($projectScope[1]);
}
$totalBudget = $budgetStmt->fetchColumn();

$expenseScope = getExpenseScopeSql($role, $uid, 'e');
$expenditureStmt = $pdo->prepare("SELECT COALESCE(SUM(e.amount),0) FROM expenses e
    WHERE e.status='approved' AND YEAR(e.expense_date)=YEAR(CURDATE()) AND ({$expenseScope[0]})");
$expenditureStmt->execute($expenseScope[1]);
$totalExpenditure = $expenditureStmt->fetchColumn();

// Pending expenses count
$pendingSql = "SELECT COUNT(*) FROM expenses e WHERE e.status='pending' AND ({$expenseScope[0]})";
$pendingParams = $expenseScope[1];
if ($role !== 'employee') {
    $pendingSql .= ' AND e.submitted_by <> ?';
    $pendingParams[] = $uid;
}
$pendingStmt = $pdo->prepare($pendingSql);
$pendingStmt->execute($pendingParams);
$pendingCount = $pendingStmt->fetchColumn();

// Total income (current year)
$incomeScope = getIncomeScopeSql($role, $uid, 'i');
$incomeStmt = $pdo->prepare("SELECT COALESCE(SUM(i.amount),0) FROM income_records i
    WHERE YEAR(i.income_date)=YEAR(CURDATE()) AND ({$incomeScope[0]})");
$incomeStmt->execute($incomeScope[1]);
$totalIncome = $incomeStmt->fetchColumn();

// Recent expenses
$recentQ = "SELECT e.*, u.name as submitter_name, ec.name as cat_name, p.name as proj_name
            FROM expenses e
            JOIN users u ON u.id=e.submitted_by
            LEFT JOIN expense_categories ec ON ec.id=e.category_id
            LEFT JOIN projects p ON p.id=e.project_id
            WHERE {$expenseScope[0]} ORDER BY e.created_at DESC LIMIT 8";
$stmt = $pdo->prepare($recentQ);
$stmt->execute($expenseScope[1]);
$recentExpenses = $stmt->fetchAll();

// Monthly expense trend (last 6 months) - for chart
$trendStmt = $pdo->prepare("SELECT DATE_FORMAT(e.expense_date,'%b %Y') AS label, SUM(e.amount) AS total
    FROM expenses e WHERE e.status='approved' AND e.expense_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
      AND ({$expenseScope[0]})
    GROUP BY YEAR(e.expense_date), MONTH(e.expense_date) ORDER BY YEAR(e.expense_date), MONTH(e.expense_date)");
$trendStmt->execute($expenseScope[1]);
$trendData = $trendStmt->fetchAll();

// Project utilization (top 5)
$projectSummaryStmt = $pdo->prepare("SELECT * FROM v_project_budget_summary
    WHERE fiscal_year=YEAR(CURDATE()) AND project_id IN (SELECT p.id FROM projects p WHERE {$projectScope[0]})
    ORDER BY utilization_pct DESC LIMIT 5");
$projectSummaryStmt->execute($projectScope[1]);
$projUtil = $projectSummaryStmt->fetchAll();

// Pending approvals for managers
$pendingApprovals = [];
if (in_array($role, ['admin', 'dept_manager', 'project_manager'])) {
    $approvalStmt = $pdo->prepare("SELECT e.*, u.name as submitter_name, p.name as proj_name
        FROM expenses e JOIN users u ON u.id=e.submitted_by LEFT JOIN projects p ON p.id=e.project_id
        WHERE e.status='pending' AND e.submitted_by <> ? AND ({$expenseScope[0]})
        ORDER BY e.created_at ASC LIMIT 5");
    $approvalStmt->execute(array_merge([$uid], $expenseScope[1]));
    $pendingApprovals = $approvalStmt->fetchAll();
}

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<main class="main-content">
  <?= flashMessage() ?>
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa fa-tachometer-alt" style="color:var(--color-primary)"></i> Dashboard</h1>
      <p class="page-subtitle">Welcome back, <?= e($_SESSION['user_name']) ?>! Here's your financial overview.</p>
    </div>
    <div class="page-actions">
      <?php if (in_array($role, ['admin', 'dept_manager', 'project_manager', 'employee'])): ?>
        <a href="<?= BASE_URL ?>/expenses/create.php" class="btn btn-primary"><i class="fa fa-plus"></i> New Expense</a>
      <?php endif; ?>
      <?php if ($role === 'admin'): ?>
        <a href="<?= BASE_URL ?>/organization/create.php" class="btn btn-secondary"><i class="fa fa-building"></i> New Org</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- STAT CARDS -->
  <div class="stats-grid">
    <?php if (in_array($role, ['admin'])): ?>
    <div class="stat-card info">
      <div class="stat-icon"><i class="fa fa-building"></i></div>
      <div class="stat-value"><?= $totalOrgs ?></div>
      <div class="stat-label">Organizations</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon"><i class="fa fa-sitemap"></i></div>
      <div class="stat-value"><?= $totalDepts ?></div>
      <div class="stat-label">Departments</div>
    </div>
    <?php endif; ?>
    <div class="stat-card success">
      <div class="stat-icon"><i class="fa fa-project-diagram"></i></div>
      <div class="stat-value"><?= $totalProjects ?></div>
      <div class="stat-label">Active Projects</div>
    </div>
    <?php if (in_array($role, ['admin', 'dept_manager', 'project_manager'])): ?>
    <div class="stat-card">
      <div class="stat-icon"><i class="fa fa-wallet"></i></div>
      <div class="stat-value" style="font-size:1.2rem;"><?= formatMoney((float)$totalBudget) ?></div>
      <div class="stat-label">Total Budget (<?= date('Y') ?>)</div>
    </div>
    <div class="stat-card warning">
      <div class="stat-icon"><i class="fa fa-receipt"></i></div>
      <div class="stat-value" style="font-size:1.2rem;"><?= formatMoney((float)$totalExpenditure) ?></div>
      <div class="stat-label">Approved Expenditure</div>
    </div>
    <div class="stat-card success">
      <div class="stat-icon"><i class="fa fa-hand-holding-dollar"></i></div>
      <div class="stat-value" style="font-size:1.2rem;"><?= formatMoney((float)$totalIncome) ?></div>
      <div class="stat-label">Total Income</div>
    </div>
    <?php endif; ?>
    <div class="stat-card <?= $pendingCount > 0 ? 'warning' : '' ?>">
      <div class="stat-icon"><i class="fa fa-clock"></i></div>
      <div class="stat-value"><?= $pendingCount ?></div>
      <div class="stat-label">Pending Expenses</div>
    </div>
  </div>

  <div class="grid-2">
    <!-- Monthly Trend Chart -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fa fa-chart-line"></i> Monthly Expenditure Trend</div>
      </div>
      <div class="card-body">
        <div class="chart-container">
          <canvas id="trendChart"></canvas>
        </div>
      </div>
    </div>

    <!-- Project Utilization -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fa fa-tasks"></i> Project Budget Utilization</div>
        <a href="<?= BASE_URL ?>/projects/index.php" class="btn btn-sm btn-secondary">View All</a>
      </div>
      <div class="card-body">
        <?php if (empty($projUtil)): ?>
          <div class="empty-state"><i class="fa fa-inbox"></i><p>No project data.</p></div>
        <?php else: ?>
          <?php foreach ($projUtil as $p): ?>
          <div style="margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
              <span style="font-size:0.85rem;font-weight:500;"><?= e($p['project_name']) ?></span>
              <span class="badge <?= $p['utilization_pct'] >= 100 ? 'badge-danger' : ($p['utilization_pct'] >= 80 ? 'badge-warning' : 'badge-success') ?>">
                <?= number_format($p['utilization_pct'], 1) ?>%
              </span>
            </div>
            <div class="progress-bar-wrap">
              <div class="progress-bar bg-<?= $p['utilization_pct'] >= 100 ? 'danger' : ($p['utilization_pct'] >= 80 ? 'warning' : 'success') ?>"
                   style="width:<?= min(100, $p['utilization_pct']) ?>%"></div>
            </div>
            <div style="font-size:0.75rem;color:var(--color-text-3);margin-top:3px;">
              <?= formatMoney((float)$p['approved_expenditure']) ?> / <?= formatMoney((float)$p['allocated_budget']) ?>
              <?php if ($p['remaining_budget'] < 0): ?>
                <span class="overrun"> ⚠ Overrun <?= formatMoney(abs((float)$p['remaining_budget'])) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Pending Approvals for Managers -->
  <?php if (!empty($pendingApprovals)): ?>
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fa fa-check-circle"></i> Pending Approvals</div>
      <a href="<?= BASE_URL ?>/approvals/index.php" class="btn btn-sm btn-warning">View All</a>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Title</th><th>Amount</th><th>Project</th><th>Submitted By</th><th>Date</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($pendingApprovals as $exp): ?>
            <tr>
              <td><?= e($exp['title']) ?></td>
              <td class="text-right font-mono"><?= formatMoney((float)$exp['amount']) ?></td>
              <td><?= e($exp['proj_name'] ?? '-') ?></td>
              <td><?= e($exp['submitter_name']) ?></td>
              <td><?= formatDate($exp['expense_date']) ?></td>
              <td>
                <a href="<?= BASE_URL ?>/approvals/review.php?id=<?= $exp['id'] ?>" class="btn btn-sm btn-primary">Review</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Recent Expenses -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fa fa-receipt"></i> Recent Expenses</div>
      <div class="dashboard-tools">
        <input type="search" data-filter-search="recentExpensesTable" aria-label="Search recent expenses" placeholder="Search expenses">
        <select data-filter-status="recentExpensesTable" aria-label="Filter expenses by status">
          <option value="">All statuses</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
      <a href="<?= BASE_URL ?>/expenses/index.php" class="btn btn-sm btn-secondary">View All</a>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table id="recentExpensesTable">
          <thead><tr><th>Title</th><th>Category</th><th>Amount</th><th>Project</th><th>Date</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (empty($recentExpenses)): ?>
            <tr><td colspan="6"><div class="empty-state"><i class="fa fa-inbox"></i><p>No expenses yet.</p></div></td></tr>
            <?php else: ?>
              <?php foreach ($recentExpenses as $exp): ?>
              <tr data-filter-item>
                <td><a href="<?= BASE_URL ?>/expenses/view.php?id=<?= $exp['id'] ?>"><?= e($exp['title']) ?></a></td>
                <td><?= e($exp['cat_name'] ?? '-') ?></td>
                <td class="text-right font-mono"><?= formatMoney((float)$exp['amount']) ?></td>
                <td><?= e($exp['proj_name'] ?? '-') ?></td>
                <td><?= formatDate($exp['expense_date']) ?></td>
                <td><?= statusBadge($exp['status']) ?></td>
              </tr>
              <?php endforeach; ?>
              <tr data-filter-empty hidden><td colspan="6"><div class="empty-state"><i class="fa fa-search"></i><p>No expenses match these filters.</p></div></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<script>
// Monthly Trend Chart
const trendLabels = <?= json_encode(array_column($trendData, 'label')) ?>;
const trendValues = <?= json_encode(array_map(fn($r) => (float)$r['total'], $trendData)) ?>;
const ctx = document.getElementById('trendChart');
if (ctx) {
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: trendLabels,
      datasets: [{
        label: 'Approved Expenditure',
        data: trendValues,
        borderColor: '#087e78',
        backgroundColor: 'rgba(8,126,120,0.1)',
        tension: 0.4,
        fill: true,
        pointBackgroundColor: '#087e78',
        pointRadius: 5,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { labels: { color: '#596d79' } } },
      scales: {
        x: { ticks: { color: '#85949c' }, grid: { color: '#e4eaec' } },
        y: { ticks: { color: '#85949c', callback: v => '₹' + (v/1000).toFixed(0) + 'K' }, grid: { color: '#e4eaec' } }
      }
    }
  });
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

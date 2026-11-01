<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$pdo  = getDB();
$role = $_SESSION['user_role'];
if ($role === 'personal') { redirect(BASE_URL . '/personal/dashboard.php'); }

// ─── Filters ────────────────────────────────────────────────────────────────
$yearFilter = sanitizeInt($_GET['year'] ?? date('Y'));
$orgFilter  = sanitizeInt($_GET['org_id'] ?? 0);

// ─── KPI totals ─────────────────────────────────────────────────────────────
$kpiParams = [$yearFilter];
$orgCond   = '';
if ($orgFilter) { $orgCond = ' AND e.organization_id = ?'; $kpiParams[] = $orgFilter; }

$kpi = $pdo->prepare("
    SELECT
        SUM(CASE WHEN e.status='approved' THEN e.amount ELSE 0 END) AS total_approved,
        SUM(CASE WHEN e.status='pending'  THEN e.amount ELSE 0 END) AS total_pending,
        SUM(CASE WHEN e.status='rejected' THEN e.amount ELSE 0 END) AS total_rejected,
        COUNT(*)                                                      AS total_count,
        SUM(CASE WHEN e.status='approved' THEN 1 ELSE 0 END)        AS approved_count,
        SUM(CASE WHEN e.status='pending'  THEN 1 ELSE 0 END)        AS pending_count,
        SUM(CASE WHEN e.status='rejected' THEN 1 ELSE 0 END)        AS rejected_count
    FROM expenses e
    WHERE YEAR(e.expense_date) = ?
    $orgCond
");
$kpi->execute($kpiParams);
$kpi = $kpi->fetch();

// ─── Total income for the year ───────────────────────────────────────────────
$incomeParams = [$yearFilter];
$incomeOrgCond = '';
if ($orgFilter) { $incomeOrgCond = ' AND organization_id = ?'; $incomeParams[] = $orgFilter; }
$totalIncome = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM income_records WHERE YEAR(income_date) = ? $incomeOrgCond");
$totalIncome->execute($incomeParams);
$totalIncome = (float) $totalIncome->fetchColumn();

// ─── Monthly expense vs income (approved) ───────────────────────────────────
$monthlyExpStmt = $pdo->prepare("
    SELECT MONTH(expense_date) AS m, SUM(amount) AS total
    FROM expenses
    WHERE status='approved' AND YEAR(expense_date)=?
    " . ($orgFilter ? ' AND organization_id=?' : '') . "
    GROUP BY MONTH(expense_date)
    ORDER BY m
");
$monthlyExpStmt->execute($orgFilter ? [$yearFilter, $orgFilter] : [$yearFilter]);
$monthlyExpRows = $monthlyExpStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$monthlyIncStmt = $pdo->prepare("
    SELECT MONTH(income_date) AS m, SUM(amount) AS total
    FROM income_records
    WHERE YEAR(income_date)=?
    " . ($orgFilter ? ' AND organization_id=?' : '') . "
    GROUP BY MONTH(income_date)
    ORDER BY m
");
$monthlyIncStmt->execute($orgFilter ? [$yearFilter, $orgFilter] : [$yearFilter]);
$monthlyIncRows = $monthlyIncStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$monthlyExp  = [];
$monthlyInc  = [];
for ($i = 1; $i <= 12; $i++) {
    $monthlyExp[] = (float)($monthlyExpRows[$i] ?? 0);
    $monthlyInc[] = (float)($monthlyIncRows[$i] ?? 0);
}

// ─── Expense by category (approved) ─────────────────────────────────────────
$catParams = [$yearFilter];
$catCond   = '';
if ($orgFilter) { $catCond = ' AND e.organization_id=?'; $catParams[] = $orgFilter; }
$catStmt = $pdo->prepare("
    SELECT ec.name, COALESCE(SUM(e.amount),0) AS total
    FROM expenses e
    LEFT JOIN expense_categories ec ON ec.id=e.category_id
    WHERE e.status='approved' AND YEAR(e.expense_date)=? $catCond
    GROUP BY ec.name
    ORDER BY total DESC
    LIMIT 10
");
$catStmt->execute($catParams);
$catRows  = $catStmt->fetchAll();
$catNames = array_column($catRows, 'name');
$catTotals = array_map('floatval', array_column($catRows, 'total'));

// ─── Department budget utilization ──────────────────────────────────────────
$deptStmt = $pdo->prepare("
    SELECT * FROM v_dept_budget_summary
    " . ($orgFilter ? ' WHERE org_name IN (SELECT name FROM organizations WHERE id=?)' : '') . "
    ORDER BY utilization_pct DESC
    LIMIT 10
");
$deptStmt->execute($orgFilter ? [$orgFilter] : []);
$deptRows = $deptStmt->fetchAll();

// ─── Project budget utilization ──────────────────────────────────────────────
$projStmt = $pdo->prepare("
    SELECT * FROM v_project_budget_summary ORDER BY utilization_pct DESC LIMIT 8
");
$projStmt->execute();
$projRows = $projStmt->fetchAll();

// ─── Top 5 spenders ─────────────────────────────────────────────────────────
$topSpendersStmt = $pdo->prepare("
    SELECT u.name, SUM(e.amount) AS total, COUNT(*) AS cnt
    FROM expenses e
    JOIN users u ON u.id=e.submitted_by
    WHERE e.status='approved' AND YEAR(e.expense_date)=? $orgCond
    GROUP BY u.id, u.name
    ORDER BY total DESC
    LIMIT 5
");
$topSpendersStmt->execute($kpiParams);
$topSpenders = $topSpendersStmt->fetchAll();

// ─── Available orgs and years for filters ────────────────────────────────────
$orgs  = $pdo->query("SELECT id, name FROM organizations WHERE is_active=1 ORDER BY name")->fetchAll();
$years = $pdo->query("
    SELECT DISTINCT YEAR(expense_date) y FROM expenses
    UNION
    SELECT DISTINCT YEAR(income_date)  y FROM income_records
    ORDER BY y DESC
")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Financial Reports';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <?= flashMessage() ?>
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa fa-chart-bar" style="color:var(--color-primary)"></i> Financial Reports</h1>
      <p class="page-subtitle">Budget utilization, income vs expense, and spending analysis</p>
    </div>
  </div>

  <!-- Filters -->
  <form method="GET" class="filter-bar" style="margin-bottom:24px;">
    <select name="year" onchange="this.form.submit()">
      <?php foreach ($years as $y): ?>
      <option value="<?= $y ?>" <?= $yearFilter==$y?'selected':'' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
    <select name="org_id" onchange="this.form.submit()">
      <option value="">All Organizations</option>
      <?php foreach ($orgs as $o): ?>
      <option value="<?= $o['id'] ?>" <?= $orgFilter==$o['id']?'selected':'' ?>><?= e($o['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($orgFilter): ?>
    <a href="<?= BASE_URL ?>/reports/index.php?year=<?= $yearFilter ?>" class="btn btn-secondary">Clear</a>
    <?php endif; ?>
  </form>

  <!-- KPI Cards -->
  <div class="stats-grid" style="margin-bottom:28px;">
    <div class="stat-card">
      <div class="stat-icon" style="background:linear-gradient(135deg,#10b981,#059669)">
        <i class="fa fa-hand-holding-dollar"></i>
      </div>
      <div class="stat-info">
        <div class="stat-value"><?= formatMoney($totalIncome) ?></div>
        <div class="stat-label">Total Income</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:linear-gradient(135deg,#6366f1,#4f46e5)">
        <i class="fa fa-receipt"></i>
      </div>
      <div class="stat-info">
        <div class="stat-value"><?= formatMoney((float)($kpi['total_approved'] ?? 0)) ?></div>
        <div class="stat-label">Approved Expenses</div>
        <div class="stat-sub"><?= (int)($kpi['approved_count'] ?? 0) ?> records</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
        <i class="fa fa-clock"></i>
      </div>
      <div class="stat-info">
        <div class="stat-value"><?= formatMoney((float)($kpi['total_pending'] ?? 0)) ?></div>
        <div class="stat-label">Pending Expenses</div>
        <div class="stat-sub"><?= (int)($kpi['pending_count'] ?? 0) ?> awaiting review</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:linear-gradient(135deg,<?= ($totalIncome - (float)($kpi['total_approved'] ?? 0)) >= 0 ? '#10b981,#059669' : '#ef4444,#dc2626' ?>)">
        <i class="fa fa-scale-balanced"></i>
      </div>
      <div class="stat-info">
        <?php $netBalance = $totalIncome - (float)($kpi['total_approved'] ?? 0); ?>
        <div class="stat-value"><?= formatMoney(abs($netBalance)) ?></div>
        <div class="stat-label">Net Balance</div>
        <div class="stat-sub"><?= $netBalance >= 0 ? 'Surplus' : 'Deficit' ?></div>
      </div>
    </div>
  </div>

  <!-- Charts Row 1 -->
  <div class="grid-2" style="gap:24px;margin-bottom:24px;">
    <!-- Monthly Income vs Expense -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fa fa-chart-line"></i> Monthly Income vs Expenses (<?= $yearFilter ?>)</h3>
      </div>
      <div class="card-body">
        <canvas id="monthlyChart" height="280"></canvas>
      </div>
    </div>
    <!-- Category Breakdown -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fa fa-chart-pie"></i> Expense by Category</h3>
      </div>
      <div class="card-body">
        <?php if (empty($catRows)): ?>
        <div class="empty-state"><i class="fa fa-chart-pie"></i><p>No approved expenses for this period.</p></div>
        <?php else: ?>
        <canvas id="catChart" height="280"></canvas>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Department Budget Utilization Table -->
  <div class="card" style="margin-bottom:24px;">
    <div class="card-header">
      <h3 class="card-title"><i class="fa fa-sitemap"></i> Department Budget Utilization</h3>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Department</th>
              <th>Organization</th>
              <th>Allocated Budget</th>
              <th>Spent</th>
              <th>Remaining</th>
              <th>Utilization</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($deptRows)): ?>
            <tr><td colspan="6"><div class="empty-state"><i class="fa fa-sitemap"></i><p>No department budget data found.</p></div></td></tr>
            <?php else: ?>
            <?php foreach ($deptRows as $d): ?>
            <?php $pct = (float)$d['utilization_pct']; ?>
            <tr>
              <td><strong><?= e($d['dept_name']) ?></strong></td>
              <td><?= e($d['org_name']) ?></td>
              <td class="font-mono"><?= formatMoney((float)$d['allocated_budget']) ?></td>
              <td class="font-mono"><?= formatMoney((float)$d['approved_expenditure']) ?></td>
              <td class="font-mono <?= $d['remaining_budget'] < 0 ? 'text-danger' : '' ?>"><?= formatMoney((float)$d['remaining_budget']) ?></td>
              <td style="min-width:150px;">
                <?= utilizationBar($pct) ?>
                <small style="color:<?= $pct>=100?'var(--color-danger)':($pct>=80?'var(--color-warning)':'var(--color-success)') ?>"><?= number_format($pct,1) ?>%</small>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Project Budget Utilization -->
  <div class="card" style="margin-bottom:24px;">
    <div class="card-header">
      <h3 class="card-title"><i class="fa fa-project-diagram"></i> Project Budget Utilization</h3>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Project</th>
              <th>Department</th>
              <th>Status</th>
              <th>Allocated</th>
              <th>Spent</th>
              <th>Pending</th>
              <th>Remaining</th>
              <th>Utilization</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($projRows)): ?>
            <tr><td colspan="8"><div class="empty-state"><i class="fa fa-project-diagram"></i><p>No project budget data.</p></div></td></tr>
            <?php else: ?>
            <?php foreach ($projRows as $p): ?>
            <?php $pct = (float)$p['utilization_pct']; ?>
            <tr>
              <td><strong><?= e($p['project_name']) ?></strong></td>
              <td><?= e($p['department_name']) ?></td>
              <td><?= statusBadge($p['status']) ?></td>
              <td class="font-mono"><?= formatMoney((float)$p['allocated_budget']) ?></td>
              <td class="font-mono"><?= formatMoney((float)$p['approved_expenditure']) ?></td>
              <td class="font-mono text-warning"><?= formatMoney((float)$p['pending_amount']) ?></td>
              <td class="font-mono <?= $p['remaining_budget'] < 0 ? 'text-danger' : '' ?>"><?= formatMoney((float)$p['remaining_budget']) ?></td>
              <td style="min-width:140px;">
                <?= utilizationBar($pct) ?>
                <small style="color:<?= $pct>=100?'var(--color-danger)':($pct>=80?'var(--color-warning)':'var(--color-success)') ?>"><?= number_format($pct,1) ?>%</small>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Top Spenders -->
  <?php if (!empty($topSpenders)): ?>
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa fa-trophy"></i> Top 5 Expense Submitters (<?= $yearFilter ?>)</h3>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table>
          <thead><tr><th>#</th><th>Employee</th><th>No. of Expenses</th><th>Total Approved</th></tr></thead>
          <tbody>
            <?php foreach ($topSpenders as $i => $s): ?>
            <tr>
              <td><strong><?= $i+1 ?></strong></td>
              <td><?= e($s['name']) ?></td>
              <td><?= $s['cnt'] ?></td>
              <td class="font-mono fw-600"><?= formatMoney((float)$s['total']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
</main>

<script>
// Monthly Income vs Expense Chart
(function() {
  const ctx = document.getElementById('monthlyChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?= json_encode($monthLabels) ?>,
      datasets: [
        {
          label: 'Income',
          data: <?= json_encode($monthlyInc) ?>,
          backgroundColor: 'rgba(16,185,129,0.75)',
          borderRadius: 6,
          order: 2
        },
        {
          label: 'Expenses (Approved)',
          data: <?= json_encode($monthlyExp) ?>,
          backgroundColor: 'rgba(99,102,241,0.75)',
          borderRadius: 6,
          order: 2
        }
      ]
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'top' } },
      scales: {
        y: {
          ticks: {
            callback: v => '₹' + (v >= 1000 ? (v/1000).toFixed(0)+'K' : v)
          }
        }
      }
    }
  });
})();

// Category Doughnut Chart
(function() {
  const ctx = document.getElementById('catChart');
  if (!ctx) return;
  const palette = [
    '#6366f1','#10b981','#f59e0b','#ef4444','#3b82f6',
    '#8b5cf6','#14b8a6','#f97316','#ec4899','#84cc16'
  ];
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: <?= json_encode($catNames) ?>,
      datasets: [{
        data: <?= json_encode($catTotals) ?>,
        backgroundColor: palette,
        borderWidth: 2,
        borderColor: '#1a1a2e'
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'right' },
        tooltip: {
          callbacks: {
            label: ctx => ' ₹' + ctx.parsed.toLocaleString('en-IN', {maximumFractionDigits:0})
          }
        }
      }
    }
  });
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

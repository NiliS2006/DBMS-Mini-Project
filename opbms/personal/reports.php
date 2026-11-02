<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['personal', 'employee', 'admin']);
$pdo  = getDB();
$uid  = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

// ─── Filters ─────────────────────────────────────────────────────────────────
$yearFilter  = sanitizeInt($_GET['year']  ?? date('Y'));
$monthFilter = sanitizeInt($_GET['month'] ?? 0); // 0 = all months
if ($yearFilter < 2020 || $yearFilter > 2035) $yearFilter = (int)date('Y');
if ($monthFilter < 0 || $monthFilter > 12) $monthFilter = 0;

$monthCond  = $monthFilter ? ' AND MONTH(t.transaction_date)=?' : '';
$monthParam = $monthFilter ? [$uid, $yearFilter, $monthFilter] : [$uid, $yearFilter];

// ─── Monthly totals ───────────────────────────────────────────────────────────
$monthlyStmt = $pdo->prepare("
    SELECT MONTH(transaction_date) AS m,
           type,
           SUM(amount) AS total
    FROM personal_transactions
    WHERE user_id=? AND YEAR(transaction_date)=?
    GROUP BY MONTH(transaction_date), type
    ORDER BY m
");
$monthlyStmt->execute([$uid, $yearFilter]);
$monthlyRaw = $monthlyStmt->fetchAll();

$monthlyInc = array_fill(1, 12, 0.0);
$monthlyExp = array_fill(1, 12, 0.0);
foreach ($monthlyRaw as $row) {
    if ($row['type'] === 'income')  $monthlyInc[(int)$row['m']] = (float)$row['total'];
    if ($row['type'] === 'expense') $monthlyExp[(int)$row['m']] = (float)$row['total'];
}
$monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

// ─── Overall totals ───────────────────────────────────────────────────────────
$totStmt = $pdo->prepare("
    SELECT type, SUM(amount) AS total
    FROM personal_transactions
    WHERE user_id=? AND YEAR(transaction_date)=? $monthCond
    GROUP BY type
");
$totStmt->execute($monthParam);
$totals = ['income' => 0.0, 'expense' => 0.0];
foreach ($totStmt->fetchAll() as $t) $totals[$t['type']] = (float)$t['total'];
$savings = $totals['income'] - $totals['expense'];

// ─── Category breakdown (expenses) ───────────────────────────────────────────
$catStmt = $pdo->prepare("
    SELECT ec.name, SUM(t.amount) AS total, COUNT(*) AS cnt
    FROM personal_transactions t
    LEFT JOIN expense_categories ec ON ec.id=t.category_id
    WHERE t.user_id=? AND t.type='expense' AND YEAR(t.transaction_date)=? $monthCond
    GROUP BY ec.name
    ORDER BY total DESC
");
$catStmt->execute($monthParam);
$catRows   = $catStmt->fetchAll();
$catNames  = array_column($catRows, 'name');
$catTotals = array_map('floatval', array_column($catRows, 'total'));

// ─── Budget vs actual (this month or selected month) ─────────────────────────
$bvMonth = $monthFilter ?: (int)date('m');
$bvYear  = $yearFilter;
$bvStmt  = $pdo->prepare("
    SELECT ec.name AS cat,
           pb.amount AS budget,
           COALESCE(SUM(t.amount),0) AS spent
    FROM personal_budgets pb
    JOIN expense_categories ec ON ec.id=pb.category_id
    LEFT JOIN personal_transactions t
           ON t.user_id=pb.user_id
          AND t.category_id=pb.category_id
          AND t.type='expense'
          AND MONTH(t.transaction_date)=pb.month
          AND YEAR(t.transaction_date)=pb.year
    WHERE pb.user_id=? AND pb.month=? AND pb.year=?
    GROUP BY pb.id, ec.name, pb.amount
    ORDER BY (COALESCE(SUM(t.amount),0)/pb.amount) DESC
");
$bvStmt->execute([$uid, $bvMonth, $bvYear]);
$bvRows = $bvStmt->fetchAll();

// ─── Years available ─────────────────────────────────────────────────────────
$years = $pdo->prepare("
    SELECT DISTINCT YEAR(transaction_date) y FROM personal_transactions WHERE user_id=? ORDER BY y DESC
");
$years->execute([$uid]);
$years = $years->fetchAll(PDO::FETCH_COLUMN);
if (empty($years)) $years = [(int)date('Y')];

$monthNames = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
               7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];

$pageTitle = 'Personal Reports';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<main class="main-content">
  <?= flashMessage() ?>
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa fa-chart-line" style="color:var(--color-primary)"></i> Personal Financial Reports</h1>
      <p class="page-subtitle">Your income, expense trends, and budget adherence</p>
    </div>
  </div>

  <!-- Filters -->
  <form method="GET" class="filter-bar" style="margin-bottom:24px;">
    <select name="year" onchange="this.form.submit()">
      <?php foreach ($years as $y): ?>
      <option value="<?= $y ?>" <?= $yearFilter==$y?'selected':'' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
    <select name="month" onchange="this.form.submit()">
      <option value="0">All Months</option>
      <?php foreach ($monthNames as $num => $name): ?>
      <option value="<?= $num ?>" <?= $monthFilter==$num?'selected':'' ?>><?= $name ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <!-- KPI Cards -->
  <div class="stats-grid" style="margin-bottom:28px;">
    <div class="stat-card">
      <div class="stat-icon" style="background:linear-gradient(135deg,#10b981,#059669)">
        <i class="fa fa-arrow-down"></i>
      </div>
      <div class="stat-info">
        <div class="stat-value"><?= formatMoney($totals['income']) ?></div>
        <div class="stat-label">Total Income</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:linear-gradient(135deg,#ef4444,#dc2626)">
        <i class="fa fa-arrow-up"></i>
      </div>
      <div class="stat-info">
        <div class="stat-value"><?= formatMoney($totals['expense']) ?></div>
        <div class="stat-label">Total Expenses</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:linear-gradient(135deg,<?= $savings >= 0 ? '#6366f1,#4f46e5' : '#f59e0b,#d97706' ?>)">
        <i class="fa fa-piggy-bank"></i>
      </div>
      <div class="stat-info">
        <div class="stat-value"><?= formatMoney(abs($savings)) ?></div>
        <div class="stat-label">Net <?= $savings >= 0 ? 'Savings' : 'Deficit' ?></div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:linear-gradient(135deg,#3b82f6,#2563eb)">
        <i class="fa fa-percent"></i>
      </div>
      <div class="stat-info">
        <div class="stat-value">
          <?= $totals['income'] > 0 ? number_format($totals['expense']/$totals['income']*100,1).'%' : '–' ?>
        </div>
        <div class="stat-label">Expense Ratio</div>
      </div>
    </div>
  </div>

  <!-- Charts Row -->
  <div class="grid-2" style="gap:24px;margin-bottom:24px;">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fa fa-chart-bar"></i> Monthly Income vs Expenses (<?= $yearFilter ?>)</h3>
      </div>
      <div class="card-body">
        <canvas id="monthlyChart" height="270"></canvas>
      </div>
    </div>
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fa fa-chart-pie"></i> Spending by Category</h3>
      </div>
      <div class="card-body">
        <?php if (empty($catRows)): ?>
        <div class="empty-state"><i class="fa fa-chart-pie"></i><p>No expense data for this period.</p></div>
        <?php else: ?>
        <canvas id="catChart" height="270"></canvas>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Budget vs Actual -->
  <?php if (!empty($bvRows)): ?>
  <div class="card" style="margin-bottom:24px;">
    <div class="card-header">
      <h3 class="card-title">
        <i class="fa fa-sliders"></i>
        Budget vs Actual — <?= $monthNames[$bvMonth] ?> <?= $bvYear ?>
      </h3>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Category</th><th>Budget</th><th>Spent</th><th>Remaining</th><th>Utilization</th></tr>
          </thead>
          <tbody>
            <?php foreach ($bvRows as $bv): ?>
            <?php
              $pct  = $bv['budget'] > 0 ? ($bv['spent'] / $bv['budget'] * 100) : 0;
              $rem  = $bv['budget'] - $bv['spent'];
            ?>
            <tr>
              <td><strong><?= e($bv['cat'] ?? 'Uncategorized') ?></strong></td>
              <td class="font-mono"><?= formatMoney((float)$bv['budget']) ?></td>
              <td class="font-mono"><?= formatMoney((float)$bv['spent']) ?></td>
              <td class="font-mono <?= $rem < 0 ? 'text-danger' : '' ?>"><?= formatMoney(abs($rem)) ?> <?= $rem < 0 ? '<small>(over)</small>' : '' ?></td>
              <td style="min-width:150px;">
                <?= utilizationBar($pct) ?>
                <small style="color:<?= $pct>=100?'var(--color-danger)':($pct>=80?'var(--color-warning)':'var(--color-success)') ?>"><?= number_format($pct,1) ?>%</small>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Category breakdown table -->
  <?php if (!empty($catRows)): ?>
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa fa-list"></i> Spending Breakdown by Category</h3>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Category</th><th>Transactions</th><th>Total Spent</th><th>% of Total</th></tr></thead>
          <tbody>
            <?php foreach ($catRows as $c): ?>
            <tr>
              <td><?= e($c['name'] ?? 'Uncategorized') ?></td>
              <td><?= $c['cnt'] ?></td>
              <td class="font-mono fw-600"><?= formatMoney((float)$c['total']) ?></td>
              <td><?= $totals['expense'] > 0 ? number_format($c['total']/$totals['expense']*100,1).'%' : '–' ?></td>
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
          data: <?= json_encode(array_values($monthlyInc)) ?>,
          backgroundColor: 'rgba(16,185,129,0.8)',
          borderRadius: 5
        },
        {
          label: 'Expenses',
          data: <?= json_encode(array_values($monthlyExp)) ?>,
          backgroundColor: 'rgba(239,68,68,0.75)',
          borderRadius: 5
        }
      ]
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'top' } },
      scales: {
        y: {
          ticks: { callback: v => '₹' + (v >= 1000 ? (v/1000).toFixed(0)+'K' : v) }
        }
      }
    }
  });
})();

(function() {
  const ctx = document.getElementById('catChart');
  if (!ctx) return;
  const palette = ['#ef4444','#f59e0b','#6366f1','#10b981','#3b82f6','#8b5cf6','#14b8a6','#f97316','#ec4899','#84cc16'];
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
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

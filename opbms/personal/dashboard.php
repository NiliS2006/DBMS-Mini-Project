<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
if (!in_array($_SESSION['user_role'], ['personal','employee','admin'])) {
    http_response_code(403); require_once __DIR__.'/../includes/403.php'; exit;
}
$pdo = getDB();
$uid  = $_SESSION['user_id'];

$month = sanitizeInt($_GET['month'] ?? date('n'));
$year  = sanitizeInt($_GET['year']  ?? date('Y'));
if ($month < 1 || $month > 12) $month = (int)date('n');
if ($year < 2020 || $year > 2035) $year = (int)date('Y');

// Monthly totals
$totals = $pdo->prepare("SELECT type, COALESCE(SUM(amount),0) AS total
    FROM personal_transactions WHERE user_id=? AND MONTH(transaction_date)=? AND YEAR(transaction_date)=?
    GROUP BY type");
$totals->execute([$uid, $month, $year]); $totalsRow = $totals->fetchAll(PDO::FETCH_KEY_PAIR);
$monthIncome  = (float)($totalsRow['income']  ?? 0);
$monthExpense = (float)($totalsRow['expense'] ?? 0);
$monthSavings = $monthIncome - $monthExpense;

// Category breakdown for current month
$catBreak = $pdo->prepare("SELECT ec.name as cat_name, COALESCE(SUM(pt.amount),0) as total
    FROM personal_transactions pt
    LEFT JOIN expense_categories ec ON ec.id=pt.category_id
    WHERE pt.user_id=? AND pt.type='expense'
    AND MONTH(pt.transaction_date)=? AND YEAR(pt.transaction_date)=?
    GROUP BY pt.category_id, ec.name ORDER BY total DESC");
$catBreak->execute([$uid, $month, $year]); $catData = $catBreak->fetchAll();

// Budget limits for current month
$budgets = $pdo->prepare("SELECT pb.*, ec.name as cat_name
    FROM personal_budgets pb JOIN expense_categories ec ON ec.id=pb.category_id
    WHERE pb.user_id=? AND pb.month=? AND pb.year=?");
$budgets->execute([$uid, $month, $year]); $budgetLimits = $budgets->fetchAll();

// Merge actual spending into budgets
$catSpend = array_column($catData, 'total', 'cat_name');
foreach ($budgetLimits as &$bl) {
    $bl['spent'] = (float)($catSpend[$bl['cat_name']] ?? 0);
    $bl['remaining'] = (float)$bl['amount'] - $bl['spent'];
    $bl['pct'] = $bl['amount'] > 0 ? round($bl['spent'] / (float)$bl['amount'] * 100, 1) : 0;
}
unset($bl);

// Last 6 months trend
$trend = $pdo->prepare("SELECT DATE_FORMAT(transaction_date,'%b %Y') as label,
    YEAR(transaction_date) as yr, MONTH(transaction_date) as mo,
    SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as income,
    SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as expense
    FROM personal_transactions WHERE user_id=?
    AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(transaction_date), MONTH(transaction_date)
    ORDER BY yr, mo");
$trend->execute([$uid]); $trendData = $trend->fetchAll();

// Recent transactions
$recent = $pdo->prepare("SELECT pt.*, ec.name as cat_name FROM personal_transactions pt
    LEFT JOIN expense_categories ec ON ec.id=pt.category_id
    WHERE pt.user_id=? AND MONTH(pt.transaction_date)=? AND YEAR(pt.transaction_date)=?
    ORDER BY pt.transaction_date DESC, pt.created_at DESC LIMIT 10");
$recent->execute([$uid, $month, $year]); $recentTx = $recent->fetchAll();

$months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$pageTitle = 'Personal Budget';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <?= flashMessage() ?>
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa fa-piggy-bank" style="color:var(--color-primary)"></i> Personal Budget</h1>
      <p class="page-subtitle">Your personal finances for <?= $months[$month-1] . ' ' . $year ?></p>
    </div>
    <div class="page-actions">
      <!-- Month Navigator -->
      <?php
      $prevM = $month == 1 ? 12 : $month - 1; $prevY = $month == 1 ? $year - 1 : $year;
      $nextM = $month == 12 ? 1 : $month + 1; $nextY = $month == 12 ? $year + 1 : $year;
      ?>
      <a href="?month=<?= $prevM ?>&year=<?= $prevY ?>" class="btn btn-secondary"><i class="fa fa-chevron-left"></i></a>
      <span style="padding:0 8px;font-weight:600;color:var(--color-text);"><?= $months[$month-1].' '.$year ?></span>
      <a href="?month=<?= $nextM ?>&year=<?= $nextY ?>" class="btn btn-secondary"><i class="fa fa-chevron-right"></i></a>
      <a href="<?= BASE_URL ?>/personal/transactions.php?type=income&month=<?= $month ?>&year=<?= $year ?>" class="btn btn-success"><i class="fa fa-plus"></i> Add Income</a>
      <a href="<?= BASE_URL ?>/personal/transactions.php?type=expense&month=<?= $month ?>&year=<?= $year ?>" class="btn btn-primary"><i class="fa fa-minus"></i> Add Expense</a>
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="stats-grid">
    <div class="stat-card success">
      <div class="stat-icon"><i class="fa fa-arrow-down"></i></div>
      <div class="stat-value" style="font-size:1.2rem;"><?= formatMoney($monthIncome) ?></div>
      <div class="stat-label">Income This Month</div>
    </div>
    <div class="stat-card warning">
      <div class="stat-icon"><i class="fa fa-arrow-up"></i></div>
      <div class="stat-value" style="font-size:1.2rem;"><?= formatMoney($monthExpense) ?></div>
      <div class="stat-label">Expenses This Month</div>
    </div>
    <div class="stat-card <?= $monthSavings >= 0 ? 'success' : 'danger' ?>">
      <div class="stat-icon"><i class="fa fa-piggy-bank"></i></div>
      <div class="stat-value" style="font-size:1.2rem;"><?= formatMoney(abs($monthSavings)) ?></div>
      <div class="stat-label"><?= $monthSavings >= 0 ? 'Savings' : 'Deficit' ?></div>
    </div>
    <?php if ($monthIncome > 0): ?>
    <div class="stat-card">
      <div class="stat-icon"><i class="fa fa-percent"></i></div>
      <div class="stat-value"><?= number_format($monthExpense/$monthIncome*100,1) ?>%</div>
      <div class="stat-label">Spent of Income</div>
    </div>
    <?php endif; ?>
  </div>

  <div class="grid-2">
    <!-- 6-Month Trend Chart -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fa fa-chart-line"></i> 6-Month Trend</div></div>
      <div class="card-body">
        <div class="chart-container"><canvas id="trendChart"></canvas></div>
      </div>
    </div>

    <!-- Category Breakdown Chart -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fa fa-chart-pie"></i> Expense Breakdown</div></div>
      <div class="card-body">
        <?php if (empty($catData)): ?>
        <div class="empty-state"><i class="fa fa-chart-pie"></i><p>No expenses this month.</p></div>
        <?php else: ?>
        <div class="chart-container"><canvas id="catChart"></canvas></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Budget vs Actual -->
  <?php if (!empty($budgetLimits)): ?>
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fa fa-sliders"></i> Budget vs. Actual</div>
      <a href="<?= BASE_URL ?>/personal/budgets.php?month=<?= $month ?>&year=<?= $year ?>" class="btn btn-sm btn-secondary">Manage Limits</a>
    </div>
    <div class="card-body">
      <?php foreach ($budgetLimits as $bl): ?>
      <div style="margin-bottom:18px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
          <span style="font-size:0.88rem;font-weight:600;"><?= e($bl['cat_name']) ?></span>
          <div style="display:flex;gap:10px;align-items:center;font-size:0.8rem;">
            <span class="text-muted"><?= formatMoney($bl['spent']) ?> / <?= formatMoney((float)$bl['amount']) ?></span>
            <span class="badge <?= $bl['pct'] >= 100 ? 'badge-danger' : ($bl['pct'] >= 80 ? 'badge-warning' : 'badge-success') ?>"><?= $bl['pct'] ?>%</span>
          </div>
        </div>
        <div class="progress-bar-wrap">
          <div class="progress-bar bg-<?= $bl['pct'] >= 100 ? 'danger' : ($bl['pct'] >= 80 ? 'warning' : 'success') ?>" style="width:<?= min(100,$bl['pct']) ?>%"></div>
        </div>
        <?php if ($bl['remaining'] < 0): ?>
        <span class="overrun" style="font-size:0.75rem;"><i class="fa fa-exclamation-triangle"></i> Overrun by <?= formatMoney(abs($bl['remaining'])) ?></span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php else: ?>
  <div class="card">
    <div class="card-body" style="text-align:center;padding:32px;">
      <i class="fa fa-sliders" style="font-size:2rem;color:var(--color-text-3);margin-bottom:12px;"></i>
      <p style="color:var(--color-text-2);margin-bottom:12px;">Set monthly budget limits to track your spending against targets.</p>
      <a href="<?= BASE_URL ?>/personal/budgets.php?month=<?= $month ?>&year=<?= $year ?>" class="btn btn-primary">Set Budget Limits</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- Recent Transactions -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fa fa-exchange-alt"></i> Recent Transactions</div>
      <a href="<?= BASE_URL ?>/personal/transactions.php?month=<?= $month ?>&year=<?= $year ?>" class="btn btn-sm btn-secondary">View All</a>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Date</th><th>Description</th><th>Category</th><th>Type</th><th>Amount</th></tr></thead>
          <tbody>
            <?php if (empty($recentTx)): ?>
            <tr><td colspan="5">
              <div class="empty-state"><i class="fa fa-exchange-alt"></i><p>No transactions this month.</p>
              <a href="<?= BASE_URL ?>/personal/transactions.php" class="btn btn-primary" style="margin-top:10px;"><i class="fa fa-plus"></i> Add Transaction</a></div>
            </td></tr>
            <?php else: ?>
            <?php foreach ($recentTx as $tx): ?>
            <tr>
              <td><?= formatDate($tx['transaction_date']) ?></td>
              <td><?= e($tx['description'] ?? '–') ?></td>
              <td><?= e($tx['cat_name'] ?? '–') ?></td>
              <td><?= $tx['type']==='income' ? '<span class="badge badge-success">Income</span>' : '<span class="badge badge-danger">Expense</span>' ?></td>
              <td class="font-mono fw-600 <?= $tx['type']==='income'?'text-success':'text-danger' ?>">
                <?= $tx['type']==='income'?'+':'-' ?><?= formatMoney((float)$tx['amount']) ?>
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
// Trend Chart
const trendLabels = <?= json_encode(array_column($trendData, 'label')) ?>;
const trendIncome  = <?= json_encode(array_map(fn($r) => (float)$r['income'], $trendData)) ?>;
const trendExpense = <?= json_encode(array_map(fn($r) => (float)$r['expense'], $trendData)) ?>;
const trendCtx = document.getElementById('trendChart');
if (trendCtx) {
  new Chart(trendCtx, {
    type: 'bar',
    data: {
      labels: trendLabels,
      datasets: [
        { label: 'Income',  data: trendIncome,  backgroundColor: '#22c55e88', borderColor: '#22c55e', borderWidth: 2, borderRadius: 4 },
        { label: 'Expense', data: trendExpense, backgroundColor: '#ef444488', borderColor: '#ef4444', borderWidth: 2, borderRadius: 4 }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { labels: { color: '#94a3b8' } } },
      scales: {
        x: { ticks: { color: '#64748b' }, grid: { color: '#242b3d' } },
        y: { ticks: { color: '#64748b', callback: v => '₹'+(v/1000).toFixed(0)+'K' }, grid: { color: '#242b3d' } }
      }
    }
  });
}

// Category Pie
const catLabels = <?= json_encode(array_column($catData, 'cat_name')) ?>;
const catValues  = <?= json_encode(array_map(fn($r) => (float)$r['total'], $catData)) ?>;
const catCtx = document.getElementById('catChart');
if (catCtx && catLabels.length) {
  new Chart(catCtx, {
    type: 'doughnut',
    data: {
      labels: catLabels,
      datasets: [{
        data: catValues,
        backgroundColor: ['#4f8ef7','#22c55e','#f59e0b','#ef4444','#7c5cfc','#3b82f6','#06b6d4','#84cc16','#f97316'],
        borderWidth: 0, hoverOffset: 8
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { position: 'right', labels: { color: '#94a3b8', font: { size: 11 } } },
        tooltip: { callbacks: { label: ctx => ' ₹' + ctx.parsed.toLocaleString('en-IN') } }
      }
    }
  });
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

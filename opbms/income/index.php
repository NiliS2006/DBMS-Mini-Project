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

$orgFilter  = sanitizeInt($_GET['org_id']  ?? 0);
$projFilter = sanitizeInt($_GET['proj_id'] ?? 0);
$fromDate   = $_GET['from'] ?? '';
$toDate     = $_GET['to']   ?? '';

$incomeScope = getIncomeScopeSql($role, (int)$uid, 'ir');
$where  = [$incomeScope[0]];
$params = $incomeScope[1];
if ($orgFilter)  { $where[] = 'ir.organization_id = ?'; $params[] = $orgFilter; }
if ($projFilter) { $where[] = 'ir.project_id = ?'; $params[] = $projFilter; }
if ($fromDate && isValidDate($fromDate)) { $where[] = 'ir.income_date >= ?'; $params[] = $fromDate; }
if ($toDate   && isValidDate($toDate))   { $where[] = 'ir.income_date <= ?'; $params[] = $toDate; }

$sql = "SELECT ir.*, u.name as recorder_name, o.name as org_name, d.name as dept_name, p.name as proj_name
    FROM income_records ir
    JOIN users u ON u.id=ir.recorded_by
    LEFT JOIN organizations o ON o.id=ir.organization_id
    LEFT JOIN departments d ON d.id=ir.department_id
    LEFT JOIN projects p ON p.id=ir.project_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY ir.income_date DESC, ir.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$totalIncome = array_sum(array_column($records, 'amount'));

if ($role === 'admin') {
    $orgs = $pdo->query("SELECT id, name FROM organizations WHERE is_active=1 ORDER BY name")->fetchAll();
} elseif ($role === 'dept_manager') {
    $orgStmt = $pdo->prepare("SELECT DISTINCT o.id, o.name FROM organizations o JOIN departments d ON d.organization_id=o.id WHERE d.manager_id=? AND d.is_active=1 ORDER BY o.name");
    $orgStmt->execute([(int)$uid]);
    $orgs = $orgStmt->fetchAll();
} else {
    [$projectScope, $projectParams] = getProjectScopeSql($role, (int)$uid);
    $orgStmt = $pdo->prepare("SELECT DISTINCT o.id, o.name FROM organizations o JOIN departments d ON d.organization_id=o.id JOIN projects p ON p.department_id=d.id WHERE $projectScope ORDER BY o.name");
    $orgStmt->execute($projectParams);
    $orgs = $orgStmt->fetchAll();
}

[$projectScope, $projectParams] = getProjectScopeSql($role, (int)$uid);
$projectStmt = $pdo->prepare("SELECT p.id, p.name FROM projects p WHERE $projectScope ORDER BY p.name");
$projectStmt->execute($projectParams);
$projs = $projectStmt->fetchAll();

$pageTitle = 'Income Records';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <?= flashMessage() ?>
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa fa-hand-holding-dollar" style="color:var(--color-primary)"></i> Income Records</h1>
      <p class="page-subtitle">Total: <strong class="text-success"><?= formatMoney($totalIncome) ?></strong></p>
    </div>
    <div class="page-actions">
      <?php if (in_array($role,['admin','dept_manager','project_manager'])): ?>
      <a href="<?= BASE_URL ?>/income/create.php" class="btn btn-success"><i class="fa fa-plus"></i> Add Income</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Filters -->
  <form method="GET" class="filter-bar">
    <select name="org_id" onchange="this.form.submit()">
      <option value="">All Organizations</option>
      <?php foreach ($orgs as $o): ?>
      <option value="<?= $o['id'] ?>" <?= $orgFilter==$o['id']?'selected':'' ?>><?= e($o['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="proj_id" onchange="this.form.submit()">
      <option value="">All Projects</option>
      <?php foreach ($projs as $p): ?>
      <option value="<?= $p['id'] ?>" <?= $projFilter==$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="from" value="<?= e($fromDate) ?>" placeholder="From date">
    <input type="date" name="to" value="<?= e($toDate) ?>" placeholder="To date">
    <button type="submit" class="btn btn-secondary"><i class="fa fa-filter"></i> Filter</button>
    <?php if ($orgFilter||$projFilter||$fromDate||$toDate): ?>
    <a href="<?= BASE_URL ?>/income/index.php" class="btn btn-secondary">Clear</a>
    <?php endif; ?>
  </form>

  <div class="card">
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Source</th><th>Amount</th><th>Date</th><th>Organization</th><th>Project</th><th>Recorded By</th><th>Description</th></tr>
          </thead>
          <tbody>
            <?php if (empty($records)): ?>
            <tr><td colspan="7">
              <div class="empty-state">
                <i class="fa fa-hand-holding-dollar"></i>
                <h3>No Income Records</h3>
                <p>No records match the current filters.</p>
                <a href="<?= BASE_URL ?>/income/create.php" class="btn btn-success" style="margin-top:12px;"><i class="fa fa-plus"></i> Add Income</a>
              </div>
            </td></tr>
            <?php else: ?>
            <?php foreach ($records as $r): ?>
            <tr>
              <td><strong><?= e($r['source']) ?></strong></td>
              <td class="font-mono text-success fw-600"><?= formatMoney((float)$r['amount']) ?></td>
              <td><?= formatDate($r['income_date']) ?></td>
              <td><?= e($r['org_name'] ?? '–') ?></td>
              <td><?= e($r['proj_name'] ?? '–') ?></td>
              <td><?= e($r['recorder_name']) ?></td>
              <td style="font-size:0.8rem;color:var(--color-text-2);"><?= e(mb_strimwidth($r['description'] ?? '', 0, 60, '…')) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php if (!empty($records)): ?>
    <div class="card-footer" style="text-align:right;">
      <strong>Total Income (filtered): <span class="text-success"><?= formatMoney($totalIncome) ?></span></strong>
    </div>
    <?php endif; ?>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

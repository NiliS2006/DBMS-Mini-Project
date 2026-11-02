<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$pdo = getDB();
$uid  = $_SESSION['user_id'];
$role = $_SESSION['user_role'];
if ($role === 'personal') { redirect(BASE_URL.'/personal/dashboard.php'); }

$statusFilter   = $_GET['status']     ?? 'all';
$projectFilter  = sanitizeInt($_GET['project_id'] ?? 0);
$deptFilter     = sanitizeInt($_GET['dept_id']    ?? 0);

// Build query
$expenseScope = getExpenseScopeSql($role, (int)$uid, 'e');
$where = [$expenseScope[0]];
$params = $expenseScope[1];
if ($statusFilter !== 'all') { $where[] = 'e.status = ?'; $params[] = $statusFilter; }
if ($projectFilter) { $where[] = 'e.project_id = ?'; $params[] = $projectFilter; }
if ($deptFilter)    { $where[] = 'e.department_id = ?'; $params[] = $deptFilter; }

$sql = "SELECT e.*, u.name as submitter_name, ec.name as cat_name,
        p.name as proj_name, d.name as dept_name
        FROM expenses e
        JOIN users u ON u.id=e.submitted_by
        LEFT JOIN expense_categories ec ON ec.id=e.category_id
        LEFT JOIN projects p ON p.id=e.project_id
        LEFT JOIN departments d ON d.id=e.department_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY e.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

[$projectScope, $projectParams] = getProjectScopeSql($role, (int)$uid);
$projectStmt = $pdo->prepare("SELECT id, name FROM projects p WHERE $projectScope ORDER BY name");
$projectStmt->execute($projectParams);
$projects = $projectStmt->fetchAll();

if ($role === 'admin') {
    $depts = $pdo->query("SELECT id, name FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();
} elseif ($role === 'dept_manager') {
    $deptStmt = $pdo->prepare("SELECT id, name FROM departments WHERE is_active=1 AND manager_id=? ORDER BY name");
    $deptStmt->execute([(int)$uid]);
    $depts = $deptStmt->fetchAll();
} elseif ($role === 'project_manager') {
    $deptStmt = $pdo->prepare("SELECT DISTINCT d.id, d.name FROM departments d JOIN projects p ON p.department_id=d.id WHERE $projectScope ORDER BY d.name");
    $deptStmt->execute($projectParams);
    $depts = $deptStmt->fetchAll();
} else {
    $depts = [];
}

$pageTitle = 'Expenses';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <?= flashMessage() ?>
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa fa-receipt" style="color:var(--color-primary)"></i> Expenses</h1>
      <p class="page-subtitle">All expense submissions and their approval status</p>
    </div>
    <div class="page-actions">
      <a href="<?= BASE_URL ?>/expenses/create.php" class="btn btn-primary"><i class="fa fa-plus"></i> Submit Expense</a>
    </div>
  </div>

  <!-- Filters -->
  <form method="GET" class="filter-bar">
    <select name="status" onchange="this.form.submit()">
      <option value="all" <?= $statusFilter==='all'?'selected':'' ?>>All Statuses</option>
      <option value="pending"  <?= $statusFilter==='pending'?'selected':'' ?>>Pending</option>
      <option value="approved" <?= $statusFilter==='approved'?'selected':'' ?>>Approved</option>
      <option value="rejected" <?= $statusFilter==='rejected'?'selected':'' ?>>Rejected</option>
      <option value="cancelled" <?= $statusFilter==='cancelled'?'selected':'' ?>>Cancelled</option>
    </select>
    <?php if ($role !== 'employee'): ?>
    <select name="project_id" onchange="this.form.submit()">
      <option value="">All Projects</option>
      <?php foreach ($projects as $p): ?>
      <option value="<?= $p['id'] ?>" <?= $projectFilter==$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="dept_id" onchange="this.form.submit()">
      <option value="">All Departments</option>
      <?php foreach ($depts as $d): ?>
      <option value="<?= $d['id'] ?>" <?= $deptFilter==$d['id']?'selected':'' ?>><?= e($d['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <?php if ($statusFilter!=='all'||$projectFilter||$deptFilter): ?>
    <a href="<?= BASE_URL ?>/expenses/index.php" class="btn btn-secondary">Clear</a>
    <?php endif; ?>
  </form>

  <div class="card">
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Category</th>
              <th>Amount</th>
              <th>Date</th>
              <?php if ($role !== 'employee'): ?><th>Submitted By</th><?php endif; ?>
              <th>Project</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($expenses)): ?>
            <tr><td colspan="8">
              <div class="empty-state">
                <i class="fa fa-receipt"></i>
                <h3>No Expenses Found</h3>
                <p>No expenses match the current filters.</p>
                <a href="<?= BASE_URL ?>/expenses/create.php" class="btn btn-primary" style="margin-top:12px;"><i class="fa fa-plus"></i> Submit Expense</a>
              </div>
            </td></tr>
            <?php else: ?>
            <?php foreach ($expenses as $exp): ?>
            <tr>
              <td><a href="<?= BASE_URL ?>/expenses/view.php?id=<?= $exp['id'] ?>"><strong><?= e($exp['title']) ?></strong></a></td>
              <td><?= e($exp['cat_name'] ?? '–') ?></td>
              <td class="font-mono fw-600"><?= formatMoney((float)$exp['amount']) ?></td>
              <td><?= formatDate($exp['expense_date']) ?></td>
              <?php if ($role !== 'employee'): ?><td><?= e($exp['submitter_name']) ?></td><?php endif; ?>
              <td><?= e($exp['proj_name'] ?? '–') ?></td>
              <td><?= statusBadge($exp['status']) ?></td>
              <td>
                <div style="display:flex;gap:6px;">
                  <a href="<?= BASE_URL ?>/expenses/view.php?id=<?= $exp['id'] ?>" class="btn btn-sm btn-secondary"><i class="fa fa-eye"></i></a>
                  <?php if ($exp['status']==='pending' && in_array($role,['admin','dept_manager','project_manager'])): ?>
                  <a href="<?= BASE_URL ?>/approvals/review.php?id=<?= $exp['id'] ?>" class="btn btn-sm btn-warning"><i class="fa fa-check"></i></a>
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

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

$errors = [];
if ($role === 'admin') {
    $orgs = $pdo->query("SELECT id, name FROM organizations WHERE is_active=1 ORDER BY name")->fetchAll();
    $depts = $pdo->query("SELECT d.id, d.name, o.name as org_name FROM departments d JOIN organizations o ON o.id=d.organization_id WHERE d.is_active=1 AND o.is_active=1 ORDER BY o.name, d.name")->fetchAll();
    $projectScope = '1=1';
    $projectParams = [];
} elseif ($role === 'dept_manager') {
    $orgStmt = $pdo->prepare("SELECT DISTINCT o.id, o.name FROM organizations o JOIN departments d ON d.organization_id=o.id WHERE d.manager_id=? AND d.is_active=1 AND o.is_active=1 ORDER BY o.name");
    $orgStmt->execute([(int)$uid]);
    $orgs = $orgStmt->fetchAll();
    $deptStmt = $pdo->prepare("SELECT d.id, d.name, o.name as org_name FROM departments d JOIN organizations o ON o.id=d.organization_id WHERE d.manager_id=? AND d.is_active=1 AND o.is_active=1 ORDER BY o.name, d.name");
    $deptStmt->execute([(int)$uid]);
    $depts = $deptStmt->fetchAll();
    [$projectScope, $projectParams] = getProjectScopeSql($role, (int)$uid);
} else {
    [$projectScope, $projectParams] = getProjectScopeSql($role, (int)$uid);
    $orgStmt = $pdo->prepare("SELECT DISTINCT o.id, o.name FROM organizations o JOIN departments d ON d.organization_id=o.id JOIN projects p ON p.department_id=d.id WHERE d.is_active=1 AND o.is_active=1 AND $projectScope ORDER BY o.name");
    $orgStmt->execute($projectParams);
    $orgs = $orgStmt->fetchAll();
    $deptStmt = $pdo->prepare("SELECT DISTINCT d.id, d.name, o.name as org_name FROM departments d JOIN organizations o ON o.id=d.organization_id JOIN projects p ON p.department_id=d.id WHERE d.is_active=1 AND o.is_active=1 AND $projectScope ORDER BY o.name, d.name");
    $deptStmt->execute($projectParams);
    $depts = $deptStmt->fetchAll();
}
$projStmt = $pdo->prepare("SELECT p.id, p.name, d.name as dept_name FROM projects p JOIN departments d ON d.id=p.department_id JOIN organizations o ON o.id=d.organization_id WHERE p.status='active' AND d.is_active=1 AND o.is_active=1 AND ($projectScope) ORDER BY d.name, p.name");
$projStmt->execute($projectParams);
$projs = $projStmt->fetchAll();

$data = [
    'source'          => '',
    'amount'          => '',
    'income_date'     => date('Y-m-d'),
    'description'     => '',
    'organization_id' => '',
    'department_id'   => '',
    'project_id'      => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid CSRF token.'; }
    $data['source']          = trim($_POST['source'] ?? '');
    $data['amount']          = trim($_POST['amount'] ?? '');
    $data['income_date']     = trim($_POST['income_date'] ?? '');
    $data['description']     = trim($_POST['description'] ?? '');
    $data['organization_id'] = sanitizeInt($_POST['organization_id'] ?? 0) ?: null;
    $data['department_id']   = sanitizeInt($_POST['department_id'] ?? 0) ?: null;
    $data['project_id']      = sanitizeInt($_POST['project_id'] ?? 0) ?: null;

    $amount = sanitizeFloat($data['amount']);
    if (!$data['source']) $errors[] = 'Income source is required.';
    if (strlen($data['source']) > 255) $errors[] = 'Income source must be 255 characters or fewer.';
    if (!isValidMoney($data['amount'])) $errors[] = 'Enter a valid amount with up to two decimal places.';
    if (!isValidDate($data['income_date'])) $errors[] = 'Valid income date is required.';

    if ($data['project_id']) {
        $check = $pdo->prepare("SELECT p.department_id, d.organization_id
            FROM projects p JOIN departments d ON d.id=p.department_id
            JOIN organizations o ON o.id=d.organization_id
            WHERE p.id=? AND p.status='active' AND d.is_active=1 AND o.is_active=1 AND ($projectScope)");
        $check->execute(array_merge([$data['project_id']], $projectParams));
        $project = $check->fetch();
        if (!$project) {
            $errors[] = 'Select an active project you are authorized to use.';
        } else {
            if ($data['department_id'] && (int)$data['department_id'] !== (int)$project['department_id']) {
                $errors[] = 'The selected project does not belong to that department.';
            }
            if ($data['organization_id'] && (int)$data['organization_id'] !== (int)$project['organization_id']) {
                $errors[] = 'The selected project does not belong to that organization.';
            }
            $data['department_id'] = (int)$project['department_id'];
            $data['organization_id'] = (int)$project['organization_id'];
        }
    } elseif ($data['department_id']) {
        $check = $pdo->prepare("SELECT d.organization_id FROM departments d
            JOIN organizations o ON o.id=d.organization_id WHERE d.id=? AND d.is_active=1 AND o.is_active=1");
        $check->execute([$data['department_id']]);
        $departmentOrgId = $check->fetchColumn();
        if (!$departmentOrgId) {
            $errors[] = 'Select a valid active department.';
        } else {
            if ($data['organization_id'] && (int)$data['organization_id'] !== (int)$departmentOrgId) {
                $errors[] = 'The selected department does not belong to that organization.';
            }
            if ($role === 'dept_manager') {
                $check = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE id=? AND manager_id=?");
                $check->execute([$data['department_id'], (int)$uid]);
                if (!$check->fetchColumn()) $errors[] = 'You are not authorized to use this department.';
            }
            $data['organization_id'] = (int)$departmentOrgId;
        }
    } elseif ($role === 'project_manager' || $role === 'dept_manager') {
        $errors[] = 'Select a department you manage or one of its projects.';
    }

    if ($data['organization_id']) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM organizations WHERE id=? AND is_active=1");
        $check->execute([$data['organization_id']]);
        if (!$check->fetchColumn()) $errors[] = 'Select a valid active organization.';
    } elseif ($role === 'admin') {
        $errors[] = 'Select an organization for this income record.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO income_records (source, amount, income_date, description, organization_id, department_id, project_id, recorded_by)
            VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$data['source'], $amount, $data['income_date'], $data['description'],
            $data['organization_id'], $data['department_id'], $data['project_id'], $uid]);
        setFlash('Income record added successfully.', 'success');
        redirect(BASE_URL . '/income/index.php');
    }
}

$pageTitle = 'Add Income';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <div class="page-header">
    <div><h1 class="page-title"><i class="fa fa-hand-holding-dollar" style="color:var(--color-primary)"></i> Add Income Record</h1></div>
    <a href="<?= BASE_URL ?>/income/index.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back</a>
  </div>
  <div class="card" style="max-width:680px;">
    <div class="card-body">
      <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
      <form method="POST">
        <?= csrfField() ?>
        <div class="form-group">
          <label class="required">Income Source</label>
          <input type="text" name="source" value="<?= e($data['source']) ?>" required placeholder="e.g., Client Payment – Infosys, Government Grant">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="required">Amount (₹)</label>
            <input type="number" name="amount" value="<?= e($data['amount']) ?>" min="0.01" step="0.01" required placeholder="0.00">
          </div>
          <div class="form-group">
            <label class="required">Income Date</label>
            <input type="date" name="income_date" value="<?= e($data['income_date']) ?>" required>
          </div>
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea name="description" placeholder="Optional description or context..."><?= e($data['description']) ?></textarea>
        </div>
        <div style="background:var(--color-bg-3);border:1px solid var(--color-border);border-radius:var(--radius);padding:16px;margin-bottom:18px;">
          <p style="font-size:0.82rem;font-weight:600;color:var(--color-text-2);margin-bottom:12px;">Link this income to:</p>
          <div class="form-row">
            <div class="form-group">
              <label>Organization</label>
              <select name="organization_id">
                <option value="">– None –</option>
                <?php foreach ($orgs as $o): ?>
                <option value="<?= $o['id'] ?>" <?= $data['organization_id']==$o['id']?'selected':'' ?>><?= e($o['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Department</label>
              <select name="department_id">
                <option value="">– None –</option>
                <?php foreach ($depts as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $data['department_id']==$d['id']?'selected':'' ?>><?= e($d['org_name']) ?> / <?= e($d['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Project</label>
              <select name="project_id">
                <option value="">– None –</option>
                <?php foreach ($projs as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $data['project_id']==$p['id']?'selected':'' ?>><?= e($p['dept_name']) ?> / <?= e($p['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
        <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save Income Record</button>
      </form>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

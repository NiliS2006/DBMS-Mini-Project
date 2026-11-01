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
$orgs  = $pdo->query("SELECT id, name FROM organizations WHERE is_active=1 ORDER BY name")->fetchAll();
$depts = $pdo->query("SELECT d.id, d.name, o.name as org_name FROM departments d JOIN organizations o ON o.id=d.organization_id WHERE d.is_active=1 ORDER BY o.name, d.name")->fetchAll();
$projs = $pdo->query("SELECT p.id, p.name, d.name as dept_name FROM projects p JOIN departments d ON d.id=p.department_id ORDER BY d.name, p.name")->fetchAll();

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
    if ($amount <= 0)     $errors[] = 'Amount must be greater than zero.';
    if (!isValidDate($data['income_date'])) $errors[] = 'Valid income date is required.';

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

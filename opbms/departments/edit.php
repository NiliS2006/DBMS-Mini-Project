<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin']);
$pdo = getDB();

$id = sanitizeInt($_GET['id'] ?? 0);
if (!$id) { setFlash('Invalid department.','danger'); redirect(BASE_URL.'/departments/index.php'); }

$stmt = $pdo->prepare("SELECT * FROM departments WHERE id=? AND is_active=1");
$stmt->execute([$id]); $dept = $stmt->fetch();
if (!$dept) { setFlash('Department not found.','danger'); redirect(BASE_URL.'/departments/index.php'); }

$orgs     = $pdo->query("SELECT id, name FROM organizations WHERE is_active=1 ORDER BY name")->fetchAll();
$managers = $pdo->query("SELECT id, name, email FROM users WHERE role IN ('dept_manager','admin') AND is_active=1 ORDER BY name")->fetchAll();

$errors = [];
$data = ['organization_id' => $dept['organization_id'], 'name' => $dept['name'], 'description' => $dept['description'] ?? '', 'manager_id' => $dept['manager_id'] ?? ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid CSRF token.'; }
    $data['organization_id'] = sanitizeInt($_POST['organization_id'] ?? 0);
    $data['name']            = trim($_POST['name'] ?? '');
    $data['description']     = trim($_POST['description'] ?? '');
    $data['manager_id']      = sanitizeInt($_POST['manager_id'] ?? 0) ?: null;

    if (!$data['organization_id']) $errors[] = 'Please select an organization.';
    if (!$data['name'])            $errors[] = 'Department name is required.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE departments SET organization_id=?, name=?, description=?, manager_id=? WHERE id=?");
        $stmt->execute([$data['organization_id'], $data['name'], $data['description'], $data['manager_id'], $id]);
        setFlash('Department updated successfully.', 'success');
        redirect(BASE_URL . '/departments/index.php');
    }
}
$pageTitle = 'Edit Department';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <div class="page-header">
    <div><h1 class="page-title"><i class="fa fa-edit" style="color:var(--color-primary)"></i> Edit Department</h1></div>
    <a href="<?= BASE_URL ?>/departments/index.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back</a>
  </div>
  <div class="card" style="max-width:640px;">
    <div class="card-body">
      <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
      <form method="POST">
        <?= csrfField() ?>
        <div class="form-group">
          <label class="required">Organization</label>
          <select name="organization_id" required>
            <?php foreach ($orgs as $o): ?>
            <option value="<?= $o['id'] ?>" <?= $data['organization_id'] == $o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="required">Department Name</label>
          <input type="text" name="name" value="<?= e($data['name']) ?>" required>
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea name="description"><?= e($data['description']) ?></textarea>
        </div>
        <div class="form-group">
          <label>Department Manager</label>
          <select name="manager_id">
            <option value="">-- No Manager --</option>
            <?php foreach ($managers as $m): ?>
            <option value="<?= $m['id'] ?>" <?= $data['manager_id'] == $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?> (<?= e($m['email']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Update Department</button>
          <a href="<?= BASE_URL ?>/departments/index.php" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin']);
$pdo = getDB();
$errors = [];
$data = ['name'=>'','description'=>'','address'=>'','contact_email'=>''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid CSRF token.'; }
    $data['name']          = trim($_POST['name'] ?? '');
    $data['description']   = trim($_POST['description'] ?? '');
    $data['address']       = trim($_POST['address'] ?? '');
    $data['contact_email'] = trim($_POST['contact_email'] ?? '');
    if (!$data['name']) $errors[] = 'Organization name is required.';
    if ($data['contact_email'] && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO organizations (name,description,address,contact_email,created_by) VALUES (?,?,?,?,?)");
        $stmt->execute([$data['name'],$data['description'],$data['address'],$data['contact_email'],$_SESSION['user_id']]);
        setFlash('Organization created successfully.', 'success');
        redirect(BASE_URL . '/organization/index.php');
    }
}
$pageTitle = 'New Organization';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <div class="page-header">
    <div><h1 class="page-title">New Organization</h1></div>
    <a href="<?= BASE_URL ?>/organization/index.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back</a>
  </div>
  <div class="card" style="max-width:640px;">
    <div class="card-body">
      <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
      <form method="POST">
        <?= csrfField() ?>
        <div class="form-group"><label class="required">Organization Name</label><input type="text" name="name" value="<?= e($data['name']) ?>" required></div>
        <div class="form-group"><label>Description</label><textarea name="description"><?= e($data['description']) ?></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Contact Email</label><input type="email" name="contact_email" value="<?= e($data['contact_email']) ?>"></div>
        </div>
        <div class="form-group"><label>Address</label><textarea name="address" rows="2"><?= e($data['address']) ?></textarea></div>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Create Organization</button>
      </form>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

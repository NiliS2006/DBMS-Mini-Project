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

// Fetch categories for org expenses
$cats = $pdo->query("SELECT id, name FROM expense_categories WHERE type IN ('organizational','both') AND is_active=1 ORDER BY name")->fetchAll();

// Projects user is allowed to submit against
if ($role === 'employee') {
    $projStmt = $pdo->prepare("SELECT p.id, p.name, d.name as dept_name
        FROM projects p JOIN departments d ON d.id=p.department_id
        JOIN project_members pm ON pm.project_id=p.id
        WHERE pm.user_id=? AND p.status='active' ORDER BY p.name");
    $projStmt->execute([$uid]);
} else {
    $projStmt = $pdo->query("SELECT p.id, p.name, d.name as dept_name FROM projects p JOIN departments d ON d.id=p.department_id WHERE p.status='active' ORDER BY p.name");
}
$projects = $projStmt->fetchAll();

$orgs = $pdo->query("SELECT id, name FROM organizations WHERE is_active=1 ORDER BY name")->fetchAll();
$depts = $pdo->query("SELECT d.id, d.name, o.name as org_name FROM departments d JOIN organizations o ON o.id=d.organization_id WHERE d.is_active=1 ORDER BY o.name, d.name")->fetchAll();

$errors = [];
$data = [
    'title'       => '',
    'description' => '',
    'amount'      => '',
    'category_id' => sanitizeInt($_GET['cat_id'] ?? 0),
    'expense_date' => date('Y-m-d'),
    'organization_id' => '',
    'department_id' => '',
    'project_id'  => sanitizeInt($_GET['project_id'] ?? 0),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid CSRF token.'; }

    $data['title']           = trim($_POST['title'] ?? '');
    $data['description']     = trim($_POST['description'] ?? '');
    $data['amount']          = trim($_POST['amount'] ?? '');
    $data['category_id']     = sanitizeInt($_POST['category_id'] ?? 0) ?: null;
    $data['expense_date']    = trim($_POST['expense_date'] ?? '');
    $data['organization_id'] = sanitizeInt($_POST['organization_id'] ?? 0) ?: null;
    $data['department_id']   = sanitizeInt($_POST['department_id'] ?? 0) ?: null;
    $data['project_id']      = sanitizeInt($_POST['project_id'] ?? 0) ?: null;

    $amount = sanitizeFloat($data['amount']);

    if (!$data['title'])       $errors[] = 'Expense title is required.';
    if ($amount <= 0)          $errors[] = 'Amount must be greater than zero.';
    if (!$data['expense_date'] || !isValidDate($data['expense_date'])) $errors[] = 'Valid expense date is required.';
    if (strtotime($data['expense_date']) > time()) $errors[] = 'Expense date cannot be in the future.';

    // Employees must submit against a project they belong to
    if ($role === 'employee' && $data['project_id']) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM project_members WHERE project_id=? AND user_id=?");
        $check->execute([$data['project_id'], $uid]);
        if (!$check->fetchColumn()) $errors[] = 'You are not authorised to submit expenses against this project.';
    }

    // Budget warning (non-blocking, just informational)
    $budgetWarning = '';
    if ($data['project_id'] && empty($errors)) {
        $budg = $pdo->prepare("SELECT COALESCE(b.amount,0) - COALESCE((SELECT SUM(e2.amount) FROM expenses e2 WHERE e2.project_id=? AND e2.status='approved'),0) AS remaining
            FROM budgets b WHERE b.entity_type='project' AND b.entity_id=? AND b.status='approved' LIMIT 1");
        $budg->execute([$data['project_id'], $data['project_id']]);
        $row = $budg->fetch();
        if ($row && $row['remaining'] !== null && $amount > (float)$row['remaining']) {
            $budgetWarning = 'Warning: This expense (₹'.number_format($amount,2).') exceeds the remaining project budget (₹'.number_format((float)$row['remaining'],2).'). It will still be submitted for approval.';
        }
    }

    // Handle receipt upload
    $receiptPath = null;
    if (!empty($_FILES['receipt']['name'])) {
        $allowed = ['jpg','jpeg','png','pdf','webp'];
        $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) { $errors[] = 'Receipt must be JPG, PNG, PDF or WEBP.'; }
        elseif ($_FILES['receipt']['size'] > 5 * 1024 * 1024) { $errors[] = 'Receipt file too large (max 5 MB).'; }
        else {
            $uploadDir = __DIR__ . '/../assets/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename = 'rcpt_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $uploadDir . $filename)) {
                $receiptPath = 'assets/uploads/' . $filename;
            } else {
                $errors[] = 'Failed to upload receipt. Please try again.';
            }
        }
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO expenses
                (title, description, amount, category_id, expense_date, organization_id, department_id, project_id, submitted_by, status, receipt_path)
                VALUES (?,?,?,?,?,?,?,?,?,'pending',?)");
            $stmt->execute([$data['title'], $data['description'], $amount,
                $data['category_id'], $data['expense_date'],
                $data['organization_id'], $data['department_id'], $data['project_id'],
                $uid, $receiptPath]);
            $expId = $pdo->lastInsertId();

            // Log in approval_history
            $hist = $pdo->prepare("INSERT INTO approval_history (expense_id, action, action_by, notes) VALUES (?,'submitted',?,?)");
            $hist->execute([$expId, $uid, null]);

            $pdo->commit();

            if ($budgetWarning) setFlash($budgetWarning, 'warning');
            else setFlash('Expense submitted successfully and is pending approval.', 'success');
            redirect(BASE_URL . '/expenses/view.php?id=' . $expId);
        } catch (Exception $ex) {
            $pdo->rollBack();
            $errors[] = 'Failed to submit expense. Please try again.';
        }
    }
}

$pageTitle = 'Submit Expense';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <div class="page-header">
    <div><h1 class="page-title"><i class="fa fa-plus-circle" style="color:var(--color-primary)"></i> Submit Expense</h1></div>
    <a href="<?= BASE_URL ?>/expenses/index.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back</a>
  </div>
  <div class="card" style="max-width:760px;">
    <div class="card-body">
      <?php foreach ($errors as $err): ?><div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= e($err) ?></div><?php endforeach; ?>
      <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <div class="form-group">
          <label class="required">Expense Title</label>
          <input type="text" name="title" value="<?= e($data['title']) ?>" required placeholder="e.g., AWS Cloud Services - October">
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea name="description" placeholder="Provide details about this expense..."><?= e($data['description']) ?></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="required">Amount (₹)</label>
            <input type="number" name="amount" value="<?= e($data['amount']) ?>" min="0.01" step="0.01" required placeholder="0.00">
          </div>
          <div class="form-group">
            <label class="required">Expense Date</label>
            <input type="date" name="expense_date" value="<?= e($data['expense_date']) ?>" max="<?= date('Y-m-d') ?>" required>
          </div>
        </div>
        <div class="form-group">
          <label>Category</label>
          <select name="category_id">
            <option value="">-- Select Category --</option>
            <?php foreach ($cats as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $data['category_id']==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="background:var(--color-bg-3);border:1px solid var(--color-border);border-radius:var(--radius);padding:16px;margin-bottom:18px;">
          <p style="font-size:0.82rem;font-weight:600;color:var(--color-text-2);margin-bottom:12px;">Link this expense to:</p>
          <div class="form-row">
            <?php if ($role !== 'employee'): ?>
            <div class="form-group">
              <label>Organization</label>
              <select name="organization_id">
                <option value="">-- None --</option>
                <?php foreach ($orgs as $o): ?>
                <option value="<?= $o['id'] ?>" <?= $data['organization_id']==$o['id']?'selected':'' ?>><?= e($o['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Department</label>
              <select name="department_id">
                <option value="">-- None --</option>
                <?php foreach ($depts as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $data['department_id']==$d['id']?'selected':'' ?>><?= e($d['org_name']) ?> / <?= e($d['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <div class="form-group">
              <label>Project</label>
              <select name="project_id">
                <option value="">-- None --</option>
                <?php foreach ($projects as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $data['project_id']==$p['id']?'selected':'' ?>><?= e($p['dept_name']) ?> / <?= e($p['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>Receipt / Supporting Document</label>
          <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf,.webp">
          <p class="form-hint">Optional. Accepted: JPG, PNG, PDF, WEBP (max 5 MB)</p>
        </div>

        <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Submit for Approval</button>
      </form>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

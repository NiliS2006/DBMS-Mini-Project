<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
if ($_SESSION['user_role'] === 'personal') { redirect(BASE_URL.'/personal/dashboard.php'); }

$pdo = getDB();
$uid = (int)$_SESSION['user_id'];
$id = sanitizeInt($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) { setFlash('Invalid expense.', 'danger'); redirect(BASE_URL.'/expenses/index.php'); }

$expenseStmt = $pdo->prepare("SELECT * FROM expenses WHERE id=? AND submitted_by=? AND status='pending'");
$expenseStmt->execute([$id, $uid]);
$expense = $expenseStmt->fetch();
if (!$expense) {
    setFlash('Only your pending expense submissions can be edited.', 'warning');
    redirect(BASE_URL.'/expenses/index.php');
}

$categories = $pdo->query("SELECT id, name FROM expense_categories
    WHERE type IN ('organizational','both') AND is_active=1 ORDER BY name")->fetchAll();
$errors = [];
$data = [
    'title' => $expense['title'],
    'description' => $expense['description'] ?? '',
    'amount' => number_format((float)$expense['amount'], 2, '.', ''),
    'category_id' => $expense['category_id'] ?? '',
    'expense_date' => $expense['expense_date'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) $errors[] = 'Invalid CSRF token.';
    $data['title'] = trim($_POST['title'] ?? '');
    $data['description'] = trim($_POST['description'] ?? '');
    $data['amount'] = trim($_POST['amount'] ?? '');
    $data['category_id'] = sanitizeInt($_POST['category_id'] ?? 0) ?: null;
    $data['expense_date'] = trim($_POST['expense_date'] ?? '');

    if ($data['title'] === '' || strlen($data['title']) > 255) $errors[] = 'Enter a title of 1 to 255 characters.';
    if (!isValidMoney($data['amount'])) $errors[] = 'Enter a valid amount with up to two decimal places.';
    if (!isValidDate($data['expense_date'])) $errors[] = 'Enter a valid expense date.';
    elseif (strtotime($data['expense_date']) > time()) $errors[] = 'Expense date cannot be in the future.';
    if ($data['category_id']) {
        $categoryCheck = $pdo->prepare("SELECT COUNT(*) FROM expense_categories
            WHERE id=? AND is_active=1 AND type IN ('organizational','both')");
        $categoryCheck->execute([$data['category_id']]);
        if (!$categoryCheck->fetchColumn()) $errors[] = 'Select a valid organizational expense category.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $lock = $pdo->prepare("SELECT id FROM expenses WHERE id=? AND submitted_by=? AND status='pending' FOR UPDATE");
            $lock->execute([$id, $uid]);
            if (!$lock->fetch()) {
                $pdo->rollBack();
                $errors[] = 'This expense is no longer pending and cannot be edited.';
            } else {
                $update = $pdo->prepare("UPDATE expenses SET title=?, description=?, amount=?, category_id=?, expense_date=?
                    WHERE id=? AND submitted_by=? AND status='pending'");
                $update->execute([$data['title'], $data['description'], sanitizeFloat($data['amount']),
                    $data['category_id'], $data['expense_date'], $id, $uid]);
                $history = $pdo->prepare("INSERT INTO approval_history (expense_id, action, action_by, notes)
                    VALUES (?, 'resubmitted', ?, 'Expense details updated before review.')");
                $history->execute([$id, $uid]);
                $pdo->commit();
                setFlash('Expense updated and remains pending approval.', 'success');
                redirect(BASE_URL.'/expenses/view.php?id='.$id);
            }
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Expense edit failed: ' . $ex->getMessage());
            $errors[] = 'Failed to update the expense. Please try again.';
        }
    }
}

$pageTitle = 'Edit Expense';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <div class="page-header">
    <div><h1 class="page-title"><i class="fa fa-edit" style="color:var(--color-primary)"></i> Edit Expense</h1></div>
    <a href="<?= BASE_URL ?>/expenses/view.php?id=<?= $id ?>" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back</a>
  </div>
  <div class="card" style="max-width:760px;">
    <div class="card-body">
      <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-group">
          <label class="required">Expense Title</label>
          <input type="text" name="title" value="<?= e($data['title']) ?>" maxlength="255" required>
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea name="description"><?= e($data['description']) ?></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="required">Amount (₹)</label>
            <input type="number" name="amount" value="<?= e($data['amount']) ?>" min="0.01" step="0.01" required>
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
            <?php foreach ($categories as $category): ?>
            <option value="<?= (int)$category['id'] ?>" <?= (int)$data['category_id']===(int)$category['id']?'selected':'' ?>><?= e($category['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Changes</button>
      </form>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

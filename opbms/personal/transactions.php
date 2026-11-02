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
$typeFilter = in_array($_GET['type'] ?? 'all', ['all','income','expense'], true) ? ($_GET['type'] ?? 'all') : 'all';

$cats = $pdo->query("SELECT id, name FROM expense_categories WHERE type IN ('personal','both') AND is_active=1 ORDER BY name")->fetchAll();

$errors = [];

// Handle Add Transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tx'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid CSRF token.'; }
    $txType  = in_array($_POST['tx_type'] ?? '', ['income','expense']) ? $_POST['tx_type'] : '';
    $txAmtRaw = trim($_POST['tx_amount'] ?? '');
    $txAmt   = sanitizeFloat($txAmtRaw);
    $txDesc  = trim($_POST['tx_description'] ?? '');
    $txDate  = trim($_POST['tx_date'] ?? '');
    $txCat   = sanitizeInt($_POST['tx_category_id'] ?? 0) ?: null;

    if (!$txType)  $errors[] = 'Transaction type is required.';
    if (!isValidMoney($txAmtRaw)) $errors[] = 'Enter a valid amount with up to two decimal places.';
    if (!$txDate || !isValidDate($txDate)) $errors[] = 'Valid date required.';
    if (strlen($txDesc) > 500) $errors[] = 'Description must be 500 characters or fewer.';
    if ($txCat) {
        $categoryCheck = $pdo->prepare("SELECT COUNT(*) FROM expense_categories WHERE id=? AND is_active=1 AND type IN ('personal','both')");
        $categoryCheck->execute([$txCat]);
        if (!$categoryCheck->fetchColumn()) $errors[] = 'Select a valid personal finance category.';
    }

    if (empty($errors)) {
        $pdo->prepare("INSERT INTO personal_transactions (user_id, type, amount, category_id, description, transaction_date) VALUES (?,?,?,?,?,?)")
            ->execute([$uid, $txType, $txAmt, $txCat, $txDesc, $txDate]);
        setFlash('Transaction added successfully.', 'success');
        redirect(BASE_URL.'/personal/transactions.php?month='.$month.'&year='.$year);
    }
}

// Handle Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tx'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    }
    $txId = sanitizeInt($_POST['tx_id'] ?? 0);
    $txType = in_array($_POST['tx_type'] ?? '', ['income','expense'], true) ? $_POST['tx_type'] : '';
    $txAmtRaw = trim($_POST['tx_amount'] ?? '');
    $txAmt = sanitizeFloat($txAmtRaw);
    $txDesc = trim($_POST['tx_description'] ?? '');
    $txDate = trim($_POST['tx_date'] ?? '');
    $txCat = sanitizeInt($_POST['tx_category_id'] ?? 0) ?: null;

    if (!$txId) $errors[] = 'Invalid transaction.';
    if (!$txType) $errors[] = 'Transaction type is required.';
    if (!isValidMoney($txAmtRaw)) $errors[] = 'Enter a valid amount with up to two decimal places.';
    if (!$txDate || !isValidDate($txDate)) $errors[] = 'Valid date required.';
    if (strlen($txDesc) > 500) $errors[] = 'Description must be 500 characters or fewer.';
    if ($txCat) {
        $categoryCheck = $pdo->prepare("SELECT COUNT(*) FROM expense_categories WHERE id=? AND is_active=1 AND type IN ('personal','both')");
        $categoryCheck->execute([$txCat]);
        if (!$categoryCheck->fetchColumn()) $errors[] = 'Select a valid personal finance category.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE personal_transactions
            SET type=?, amount=?, category_id=?, description=?, transaction_date=?
            WHERE id=? AND user_id=?");
        $stmt->execute([$txType, $txAmt, $txCat, $txDesc, $txDate, $txId, $uid]);
        if ($stmt->rowCount() > 0) {
            setFlash('Transaction updated successfully.', 'success');
        } else {
            $check = $pdo->prepare("SELECT id FROM personal_transactions WHERE id=? AND user_id=?");
            $check->execute([$txId, $uid]);
            if (!$check->fetch()) {
                setFlash('Transaction not found.', 'danger');
            } else {
                setFlash('No transaction changes were made.', 'info');
            }
        }
        redirect(BASE_URL.'/personal/transactions.php?month='.$month.'&year='.$year);
    }
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_tx'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { setFlash('Invalid token.','danger'); redirect(BASE_URL.'/personal/transactions.php'); }
    $delId = sanitizeInt($_POST['tx_id'] ?? 0);
    // Ensure user owns this transaction
    $check = $pdo->prepare("SELECT id FROM personal_transactions WHERE id=? AND user_id=?");
    $check->execute([$delId, $uid]);
    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM personal_transactions WHERE id=? AND user_id=?")->execute([$delId, $uid]);
        setFlash('Transaction deleted.', 'success');
    }
    redirect(BASE_URL.'/personal/transactions.php?month='.$month.'&year='.$year);
}

// Fetch transactions
$where  = ['pt.user_id=?', 'MONTH(pt.transaction_date)=?', 'YEAR(pt.transaction_date)=?'];
$params = [$uid, $month, $year];
if (in_array($typeFilter, ['income','expense'])) { $where[] = 'pt.type=?'; $params[] = $typeFilter; }

$txStmt = $pdo->prepare("SELECT pt.*, ec.name as cat_name FROM personal_transactions pt
    LEFT JOIN expense_categories ec ON ec.id=pt.category_id
    WHERE " . implode(' AND ', $where) . " ORDER BY pt.transaction_date DESC, pt.created_at DESC");
$txStmt->execute($params); $transactions = $txStmt->fetchAll();

$months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$pageTitle = 'Transactions';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <?= flashMessage() ?>
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa fa-exchange-alt" style="color:var(--color-primary)"></i> Transactions</h1>
      <p class="page-subtitle"><?= $months[$month-1].' '.$year ?></p>
    </div>
    <div class="page-actions">
      <?php
      $prevM = $month==1?12:$month-1; $prevY = $month==1?$year-1:$year;
      $nextM = $month==12?1:$month+1; $nextY = $month==12?$year+1:$year;
      ?>
      <a href="?month=<?= $prevM ?>&year=<?= $prevY ?>&type=<?= $typeFilter ?>" class="btn btn-secondary"><i class="fa fa-chevron-left"></i></a>
      <a href="?month=<?= $nextM ?>&year=<?= $nextY ?>&type=<?= $typeFilter ?>" class="btn btn-secondary"><i class="fa fa-chevron-right"></i></a>
      <a href="<?= BASE_URL ?>/personal/dashboard.php?month=<?= $month ?>&year=<?= $year ?>" class="btn btn-secondary"><i class="fa fa-tachometer-alt"></i> Dashboard</a>
    </div>
  </div>

  <div class="grid-2">
    <!-- Add Transaction Form -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fa fa-plus"></i> Add Transaction</div></div>
      <div class="card-body">
        <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="add_tx" value="1">
          <div class="form-row">
            <div class="form-group">
              <label class="required">Type</label>
              <select name="tx_type" required>
                <option value="">– Select –</option>
                <option value="income"  <?= ($typeFilter==='income')?'selected':'' ?>>Income</option>
                <option value="expense" <?= ($typeFilter==='expense')?'selected':'' ?>>Expense</option>
              </select>
            </div>
            <div class="form-group">
              <label class="required">Amount (₹)</label>
              <input type="number" name="tx_amount" min="0.01" step="0.01" required placeholder="0.00">
            </div>
          </div>
          <div class="form-group">
            <label>Category</label>
            <select name="tx_category_id">
              <option value="">– No Category –</option>
              <?php foreach ($cats as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="required">Date</label>
            <input type="date" name="tx_date" value="<?= sprintf('%04d-%02d-01', $year, $month) ?>" required>
          </div>
          <div class="form-group">
            <label>Description</label>
            <input type="text" name="tx_description" placeholder="e.g., Monthly rent, Grocery shopping">
          </div>
          <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-plus"></i> Add Transaction</button>
        </form>
      </div>
    </div>

    <!-- Filter + Transactions List -->
    <div>
      <!-- Type filter -->
      <div class="pill-tabs">
        <a href="?month=<?= $month ?>&year=<?= $year ?>&type=all" class="pill-tab <?= $typeFilter==='all'?'active':'' ?>">All</a>
        <a href="?month=<?= $month ?>&year=<?= $year ?>&type=income" class="pill-tab <?= $typeFilter==='income'?'active':'' ?>">Income</a>
        <a href="?month=<?= $month ?>&year=<?= $year ?>&type=expense" class="pill-tab <?= $typeFilter==='expense'?'active':'' ?>">Expense</a>
      </div>

      <div class="card">
        <div class="card-body" style="padding:0;max-height:520px;overflow-y:auto;">
          <?php if (empty($transactions)): ?>
          <div class="empty-state"><i class="fa fa-exchange-alt"></i><p>No transactions this month.</p></div>
          <?php else: ?>
          <?php foreach ($transactions as $tx): ?>
          <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--color-border);">
            <div style="width:36px;height:36px;border-radius:50%;background:var(--color-<?= $tx['type']==='income'?'success':'danger' ?>-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i class="fa fa-<?= $tx['type']==='income'?'arrow-down':'arrow-up' ?>" style="color:var(--color-<?= $tx['type']==='income'?'success':'danger' ?>);font-size:0.85rem;"></i>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="font-size:0.88rem;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($tx['description'] ?? '–') ?></div>
              <div style="font-size:0.75rem;color:var(--color-text-3);"><?= e($tx['cat_name'] ?? 'Uncategorized') ?> &bull; <?= formatDate($tx['transaction_date']) ?></div>
            </div>
            <div style="text-align:right;flex-shrink:0;">
              <div class="font-mono fw-600 <?= $tx['type']==='income'?'text-success':'text-danger' ?>">
                <?= $tx['type']==='income'?'+':'-' ?><?= formatMoney((float)$tx['amount']) ?>
              </div>
            <button type="button" class="btn btn-sm btn-secondary" onclick="toggleTransactionEdit(<?= (int)$tx['id'] ?>)">Edit</button>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this transaction?');">
                <?= csrfField() ?>
                <input type="hidden" name="delete_tx" value="1">
                <input type="hidden" name="tx_id" value="<?= $tx['id'] ?>">
                <button type="submit" class="btn btn-sm" style="padding:2px 6px;background:transparent;color:var(--color-danger);border:none;cursor:pointer;font-size:0.7rem;" title="Delete"><i class="fa fa-trash"></i></button>
              </form>
            </div>
          </div>
          <form method="POST" id="edit-tx-<?= (int)$tx['id'] ?>" class="card-body" style="display:none;border-bottom:1px solid var(--color-border);">
            <?= csrfField() ?>
            <input type="hidden" name="update_tx" value="1">
            <input type="hidden" name="tx_id" value="<?= (int)$tx['id'] ?>">
            <div class="form-row">
              <div class="form-group">
                <label>Type</label>
                <select name="tx_type" required>
                  <option value="income" <?= $tx['type']==='income'?'selected':'' ?>>Income</option>
                  <option value="expense" <?= $tx['type']==='expense'?'selected':'' ?>>Expense</option>
                </select>
              </div>
              <div class="form-group">
                <label>Amount (₹)</label>
                <input type="number" name="tx_amount" value="<?= e(number_format((float)$tx['amount'],2,'.','')) ?>" min="0.01" step="0.01" required>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Category</label>
                <select name="tx_category_id">
                  <option value="">– No Category –</option>
                  <?php foreach ($cats as $c): ?>
                  <option value="<?= (int)$c['id'] ?>" <?= (int)$tx['category_id']===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Date</label>
                <input type="date" name="tx_date" value="<?= e($tx['transaction_date']) ?>" required>
              </div>
            </div>
            <div class="form-group">
              <label>Description</label>
              <input type="text" name="tx_description" value="<?= e($tx['description'] ?? '') ?>" maxlength="500">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="toggleTransactionEdit(<?= (int)$tx['id'] ?>)">Cancel</button>
          </form>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</main>
<script>
function toggleTransactionEdit(id) {
  const form = document.getElementById('edit-tx-' + id);
  form.style.display = form.style.display === 'none' ? '' : 'none';
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

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

$cats = $pdo->query("SELECT id, name FROM expense_categories WHERE type IN ('personal','both') AND is_active=1 ORDER BY name")->fetchAll();

$errors = [];

// Save budget limits
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid CSRF token.'; }
    $amounts = $_POST['budget'] ?? [];
    if (empty($errors)) {
        foreach ($amounts as $catId => $amt) {
            $catId = (int)$catId;
            $amt   = sanitizeFloat($amt);
            if ($catId <= 0) continue;
            if ($amt <= 0) {
                // Remove if set to 0
                $pdo->prepare("DELETE FROM personal_budgets WHERE user_id=? AND category_id=? AND month=? AND year=?")
                    ->execute([$uid, $catId, $month, $year]);
            } else {
                $pdo->prepare("INSERT INTO personal_budgets (user_id, category_id, amount, month, year)
                    VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE amount=?")
                    ->execute([$uid, $catId, $amt, $month, $year, $amt]);
            }
        }
        setFlash('Budget limits saved successfully.', 'success');
        redirect(BASE_URL.'/personal/budgets.php?month='.$month.'&year='.$year);
    }
}

// Load existing limits
$existing = $pdo->prepare("SELECT category_id, amount FROM personal_budgets WHERE user_id=? AND month=? AND year=?");
$existing->execute([$uid, $month, $year]);
$limits = array_column($existing->fetchAll(), 'amount', 'category_id');

$months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$pageTitle = 'Budget Limits';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <?= flashMessage() ?>
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa fa-sliders" style="color:var(--color-primary)"></i> Monthly Budget Limits</h1>
      <p class="page-subtitle">Set spending limits per category for <?= $months[$month-1].' '.$year ?></p>
    </div>
    <div class="page-actions">
      <?php
      $prevM = $month==1?12:$month-1; $prevY = $month==1?$year-1:$year;
      $nextM = $month==12?1:$month+1; $nextY = $month==12?$year+1:$year;
      ?>
      <a href="?month=<?= $prevM ?>&year=<?= $prevY ?>" class="btn btn-secondary"><i class="fa fa-chevron-left"></i></a>
      <span style="padding:0 8px;font-weight:600;"><?= $months[$month-1].' '.$year ?></span>
      <a href="?month=<?= $nextM ?>&year=<?= $nextY ?>" class="btn btn-secondary"><i class="fa fa-chevron-right"></i></a>
      <a href="<?= BASE_URL ?>/personal/dashboard.php?month=<?= $month ?>&year=<?= $year ?>" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Dashboard</a>
    </div>
  </div>

  <div class="card" style="max-width:600px;">
    <div class="card-header"><div class="card-title"><i class="fa fa-sliders"></i> Set Limits for <?= $months[$month-1].' '.$year ?></div></div>
    <div class="card-body">
      <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
      <div class="alert alert-info" style="margin-bottom:16px;">
        <i class="fa fa-info-circle"></i> Enter monthly spending limits per category. Leave blank or enter 0 to remove a limit.
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <?php foreach ($cats as $c): ?>
        <div class="form-row" style="align-items:center;margin-bottom:10px;">
          <div class="form-group" style="margin-bottom:0;flex:1;">
            <label style="font-size:0.88rem;"><?= e($c['name']) ?></label>
          </div>
          <div class="form-group" style="margin-bottom:0;max-width:180px;">
            <div style="position:relative;">
              <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--color-text-3);font-size:0.85rem;">₹</span>
              <input type="number" name="budget[<?= $c['id'] ?>]"
                value="<?= isset($limits[$c['id']]) ? number_format((float)$limits[$c['id']],2,'.','') : '' ?>"
                min="0" step="0.01" placeholder="No limit"
                style="padding-left:24px;">
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <div style="margin-top:20px;">
          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Budget Limits</button>
        </div>
      </form>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

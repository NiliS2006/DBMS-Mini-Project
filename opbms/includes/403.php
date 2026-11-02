<?php
$pageTitle = "403 Forbidden";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <div class="error-page">
    <div class="error-icon"><i class="fa fa-ban"></i></div>
    <h1>Access Denied</h1>
    <p>You do not have permission to view this page.</p>
    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary">Go to Dashboard</a>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

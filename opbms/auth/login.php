<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    requireLogin();
    redirect(getDashboardUrl());
}

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Your login form expired. Please try again.';
    } elseif (!$email || !$password) {
        $error = 'Please enter both email and password.';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role, is_active FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {
            loginUser($user);
            redirect(getDashboardUrl());
        }
        $error = 'Invalid credentials or inactive account.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log in | OPBMS</title>
<meta name="description" content="Log in to your OPBMS account.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="auth-page">
<main class="auth-layout">
  <section class="auth-story">
    <a class="site-brand auth-brand" href="<?= BASE_URL ?>/"><span class="brand-mark"><i class="fa-solid fa-chart-pie"></i></span><span>opbms<span class="brand-period">.</span></span></a>
    <div class="auth-story-copy">
      <span class="section-eyebrow">WELCOME BACK</span>
      <h1>Your finances, in focus.</h1>
      <p>Pick up where you left off. Your budgets, projects, and financial picture are right where you need them.</p>
      <div class="auth-quote"><span class="auth-quote-mark">“</span><p>Clear numbers make space for better decisions.</p><span class="auth-quote-rule"></span><small>THE OPBMS PRINCIPLE</small></div>
    </div>
    <div class="auth-story-footer"><i class="fa-solid fa-shield-halved"></i> Secure, role-based access to your OPBMS workspace.</div>
    <span class="auth-decoration auth-decoration-one"></span><span class="auth-decoration auth-decoration-two"></span>
  </section>
  <section class="auth-panel">
    <div class="auth-panel-inner">
      <a class="auth-mobile-brand site-brand" href="<?= BASE_URL ?>/"><span class="brand-mark"><i class="fa-solid fa-chart-pie"></i></span><span>opbms<span class="brand-period">.</span></span></a>
      <div class="auth-heading">
        <span class="auth-heading-icon"><i class="fa-solid fa-arrow-right-to-bracket"></i></span>
        <span class="section-eyebrow">YOUR WORKSPACE AWAITS</span>
        <h2>Welcome back.</h2>
        <p>Enter your details to access your account.</p>
      </div>
      <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?></div>
      <?php endif; ?>
      <form class="auth-form" method="post" action="<?= BASE_URL ?>/auth/login.php">
        <?= csrfField() ?>
        <div class="form-group">
          <label for="email" class="required">Email address</label>
          <div class="auth-input-wrap"><i class="fa-regular fa-envelope"></i><input id="email" name="email" type="email" required autocomplete="email" placeholder="you@example.com" value="<?= e($email) ?>"></div>
        </div>
        <div class="form-group">
          <div class="auth-label-row"><label for="password" class="required">Password</label></div>
          <div class="auth-input-wrap"><i class="fa-solid fa-lock"></i><input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Enter your password"></div>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg auth-submit">Log in to OPBMS <i class="fa-solid fa-arrow-right"></i></button>
      </form>
      <div class="auth-demo">
        <div class="auth-demo-heading"><i class="fa-solid fa-circle-info"></i><span>Trying the demo?</span></div>
        <p>Use a seeded demo account with the shared password <code>password</code>.</p>
        <div class="auth-demo-accounts">
          <span><b>Admin</b> admin@opbms.com</span>
          <span><b>Manager</b> dept@opbms.com</span>
          <span><b>Employee</b> employee@opbms.com</span>
          <span><b>Personal</b> personal@opbms.com</span>
        </div>
      </div>
      <p class="auth-switch">New to OPBMS? <a href="<?= BASE_URL ?>/auth/register.php">Create a personal account</a></p>
      <a class="auth-back-link" href="<?= BASE_URL ?>/"><i class="fa-solid fa-arrow-left"></i> Back to OPBMS</a>
    </div>
  </section>
</main>
</body>
</html>

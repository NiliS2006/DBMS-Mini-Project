<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    redirect(getDashboardUrl());
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$email || !$password) {
        $error = 'Please enter both email and password.';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role, is_active FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {
            loginUser($user);
            redirect(getDashboardUrl());
        } else {
            $error = 'Invalid credentials or inactive account.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | OPBMS</title>
<meta name="description" content="Login to OPBMS - Organizational and Personal Budget Management System">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="login-wrapper">
  <div class="login-card">
    <div class="login-logo">
      <i class="fa fa-chart-pie"></i>
      <h1>OPBMS</h1>
      <p>Organizational &amp; Personal Budget Management</p>
    </div>
    <?php if ($error): ?>
      <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= e($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="">
      <?= csrfField() ?>
      <div class="form-group">
        <label for="email" class="required">Email Address</label>
        <input type="email" id="email" name="email" required autocomplete="email"
               value="<?= e($_POST['email'] ?? '') ?>" placeholder="you@example.com">
      </div>
      <div class="form-group">
        <label for="password" class="required">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
      </div>
      <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:8px;">
        <i class="fa fa-sign-in-alt"></i> Sign In
      </button>
    </form>
    <hr class="divider">
    <div style="font-size:0.8rem; color:var(--color-text-3);">
      <strong style="color:var(--color-text-2);">Demo Accounts</strong> (password: <code>password</code>)<br><br>
      <table style="width:100%;border-collapse:collapse;font-size:0.78rem;">
        <tr><td style="padding:3px 0;"><span class="badge badge-primary">Admin</span></td><td>admin@opbms.com</td></tr>
        <tr><td style="padding:3px 0;"><span class="badge badge-info">Dept Mgr</span></td><td>dept@opbms.com</td></tr>
        <tr><td style="padding:3px 0;"><span class="badge badge-success">Proj Mgr</span></td><td>proj@opbms.com</td></tr>
        <tr><td style="padding:3px 0;"><span class="badge badge-warning">Employee</span></td><td>employee@opbms.com</td></tr>
        <tr><td style="padding:3px 0;"><span class="badge badge-secondary">Personal</span></td><td>personal@opbms.com</td></tr>
      </table>
    </div>
  </div>
</div>
</body>
</html>

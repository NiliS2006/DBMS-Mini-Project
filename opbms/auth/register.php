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

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your sign-up form expired. Please try again.';
    } elseif (strlen($name) < 2 || strlen($name) > 120) {
        $errors[] = 'Name must be between 2 and 120 characters.';
    } elseif (strlen($email) > 180 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Use a password with at least 8 characters.';
    } elseif ($password !== $passwordConfirm) {
        $errors[] = 'The passwords do not match.';
    } else {
        $pdo = getDB();
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'An account with that email already exists.';
        } else {
            try {
                $insert = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'personal')");
                $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                loginUser([
                    'id' => (int)$pdo->lastInsertId(),
                    'name' => $name,
                    'email' => $email,
                    'role' => 'personal',
                ]);
                redirect(getDashboardUrl());
            } catch (PDOException $exception) {
                if ($exception->getCode() !== '23000') {
                    throw $exception;
                }
                $errors[] = 'An account with that email already exists.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create account | OPBMS</title>
<meta name="description" content="Create your personal OPBMS account.">
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
      <span class="section-eyebrow">YOUR MONEY, IN VIEW</span>
      <h1>Make a little more room for what matters.</h1>
      <p>Bring your income, spending, and monthly budgets together in one calm, clear view.</p>
      <div class="auth-benefits">
        <div><i class="fa-solid fa-check"></i><span>Track income and expenses in one place</span></div>
        <div><i class="fa-solid fa-check"></i><span>Set category budgets that work for you</span></div>
        <div><i class="fa-solid fa-check"></i><span>Keep your personal financial data private</span></div>
      </div>
    </div>
    <div class="auth-story-footer"><i class="fa-solid fa-shield-halved"></i> Your personal account is scoped to your own records.</div>
    <span class="auth-decoration auth-decoration-one"></span><span class="auth-decoration auth-decoration-two"></span>
  </section>
  <section class="auth-panel">
    <div class="auth-panel-inner">
      <a class="auth-mobile-brand site-brand" href="<?= BASE_URL ?>/"><span class="brand-mark"><i class="fa-solid fa-chart-pie"></i></span><span>opbms<span class="brand-period">.</span></span></a>
      <div class="auth-heading">
        <span class="auth-heading-icon"><i class="fa-solid fa-user-plus"></i></span>
        <span class="section-eyebrow">CREATE YOUR ACCOUNT</span>
        <h2>Start with a clearer view.</h2>
        <p>Set up your personal OPBMS account in a few steps.</p>
      </div>
      <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert"><i class="fa-solid fa-circle-exclamation"></i><div><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div></div>
      <?php endif; ?>
      <form class="auth-form" method="post" action="<?= BASE_URL ?>/auth/register.php">
        <?= csrfField() ?>
        <div class="form-group">
          <label for="name" class="required">Full name</label>
          <div class="auth-input-wrap"><i class="fa-regular fa-user"></i><input id="name" name="name" type="text" required minlength="2" maxlength="120" autocomplete="name" placeholder="Your name" value="<?= e($name) ?>"></div>
        </div>
        <div class="form-group">
          <label for="email" class="required">Email address</label>
          <div class="auth-input-wrap"><i class="fa-regular fa-envelope"></i><input id="email" name="email" type="email" required maxlength="180" autocomplete="email" placeholder="you@example.com" value="<?= e($email) ?>"></div>
        </div>
        <div class="form-group">
          <label for="password" class="required">Password</label>
          <div class="auth-input-wrap"><i class="fa-solid fa-lock"></i><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters"></div>
        </div>
        <div class="form-group">
          <label for="password_confirm" class="required">Confirm password</label>
          <div class="auth-input-wrap"><i class="fa-solid fa-lock"></i><input id="password_confirm" name="password_confirm" type="password" required minlength="8" autocomplete="new-password" placeholder="Re-enter your password"></div>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg auth-submit">Create personal account <i class="fa-solid fa-arrow-right"></i></button>
      </form>
      <p class="auth-switch">Already have an account? <a href="<?= BASE_URL ?>/auth/login.php">Log in</a></p>
      <a class="auth-back-link" href="<?= BASE_URL ?>/"><i class="fa-solid fa-arrow-left"></i> Back to OPBMS</a>
    </div>
  </section>
</main>
</body>
</html>

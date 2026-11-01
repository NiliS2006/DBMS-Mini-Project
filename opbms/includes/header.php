<?php
// Top page header (HTML head + navbar)
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('BASE_URL')) define('BASE_URL', '/opbms');
require_once __DIR__ . '/../includes/functions.php';
$currentUser = getCurrentUser();
$pageTitle   = $pageTitle ?? 'OPBMS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> | OPBMS</title>
<meta name="description" content="Organizational and Personal Budget Management System">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-wrapper">

<!-- TOP NAVBAR -->
<header class="topbar">
  <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
    <i class="fa fa-bars"></i>
  </button>
  <div class="topbar-brand">
    <i class="fa fa-chart-pie brand-icon"></i>
    <span>OPBMS</span>
  </div>
  <nav class="topbar-right">
    <a href="<?= BASE_URL ?>/notifications.php" class="topbar-icon-btn" title="Notifications">
      <i class="fa fa-bell"></i>
    </a>
    <?php if ($currentUser): ?>
    <div class="topbar-user">
      <div class="user-avatar"><?= strtoupper(substr($currentUser['name'], 0, 1)) ?></div>
      <div class="user-meta">
        <span class="user-name"><?= e($currentUser['name']) ?></span>
        <span class="user-role"><?= e(ucwords(str_replace('_', ' ', $currentUser['role']))) ?></span>
      </div>
      <a href="<?= BASE_URL ?>/auth/logout.php" class="btn btn-sm btn-outline-danger" title="Logout">
        <i class="fa fa-sign-out-alt"></i>
      </a>
    </div>
    <?php endif; ?>
  </nav>
</header>

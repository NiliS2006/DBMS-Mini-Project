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
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-wrapper">

<!-- TOP NAVBAR -->
<header class="topbar">
  <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar" aria-controls="sidebar" aria-expanded="false">
    <i class="fa fa-bars"></i>
  </button>
  <a class="topbar-brand" href="<?= BASE_URL ?>/">
    <span class="brand-mark"><i class="fa-solid fa-chart-pie"></i></span>
    <span>OPBMS</span>
  </a>
  <nav class="topbar-right">
    <?php
    $currentRole = $currentUser['role'] ?? '';
    $activityUrl = $currentRole === 'personal'
        ? BASE_URL . '/personal/transactions.php'
        : (in_array($currentRole, ['admin', 'dept_manager', 'project_manager'], true)
            ? BASE_URL . '/approvals/index.php'
            : BASE_URL . '/expenses/index.php');
    ?>
    <a href="<?= $activityUrl ?>" class="topbar-icon-btn" title="<?= in_array($currentRole, ['admin', 'dept_manager', 'project_manager'], true) ? 'Review approvals' : 'View activity' ?>" aria-label="<?= in_array($currentRole, ['admin', 'dept_manager', 'project_manager'], true) ? 'Review approvals' : 'View activity' ?>">
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

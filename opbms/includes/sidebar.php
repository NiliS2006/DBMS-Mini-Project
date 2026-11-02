<?php
// Sidebar navigation - role-aware
$role = $_SESSION['user_role'] ?? '';
$uri  = $_SERVER['REQUEST_URI'];
function navLink(string $href, string $icon, string $label, string $uri): string {
    $active = (strpos($uri, $href) !== false) ? ' active' : '';
    return '<li><a href="' . $href . '" class="nav-link' . $active . '">
        <i class="fa fa-' . $icon . '"></i><span>' . htmlspecialchars($label, ENT_QUOTES) . '</span></a></li>';
}
$base = BASE_URL;
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <i class="fa fa-chart-pie"></i>
    <span>OPBMS</span>
  </div>
  <nav class="sidebar-nav">
    <ul>
<?php if ($role !== 'personal'): ?>
      <li class="nav-section">Main</li>
      <?= navLink("$base/dashboard.php", 'tachometer-alt', 'Dashboard', $uri) ?>

<?php if (in_array($role, ['admin'])): ?>
      <li class="nav-section">Administration</li>
      <?= navLink("$base/organization/index.php", 'building', 'Organizations', $uri) ?>
      <?= navLink("$base/admin/users.php", 'users', 'Users', $uri) ?>
<?php endif; ?>

<?php if (in_array($role, ['admin', 'dept_manager', 'project_manager'])): ?>
      <li class="nav-section">Management</li>
<?php endif; ?>
<?php if (in_array($role, ['admin', 'dept_manager'])): ?>
      <?= navLink("$base/departments/index.php", 'sitemap', 'Departments', $uri) ?>
<?php endif; ?>
<?php if (in_array($role, ['admin', 'dept_manager', 'project_manager'])): ?>
      <?= navLink("$base/projects/index.php", 'project-diagram', 'Projects', $uri) ?>
      <?= navLink("$base/budgets/index.php", 'wallet', 'Budgets', $uri) ?>
<?php endif; ?>

      <li class="nav-section">Finance</li>
      <?= navLink("$base/expenses/index.php", 'receipt', 'Expenses', $uri) ?>
      <?= navLink("$base/income/index.php", 'hand-holding-dollar', 'Income', $uri) ?>
<?php if (in_array($role, ['admin', 'dept_manager', 'project_manager'])): ?>
      <?= navLink("$base/approvals/index.php", 'check-circle', 'Approvals', $uri) ?>
<?php endif; ?>

      <li class="nav-section">Reports</li>
      <?= navLink("$base/reports/index.php", 'chart-bar', 'Reports', $uri) ?>

<?php endif; /* not personal */ ?>

<?php if ($role === 'personal' || in_array($role, ['admin', 'employee'])): ?>
      <li class="nav-section">Personal Finance</li>
      <?= navLink("$base/personal/dashboard.php", 'piggy-bank', 'Personal Budget', $uri) ?>
      <?= navLink("$base/personal/transactions.php", 'exchange-alt', 'Transactions', $uri) ?>
      <?= navLink("$base/personal/budgets.php", 'sliders', 'Budget Limits', $uri) ?>
      <?= navLink("$base/personal/reports.php", 'chart-line', 'Personal Reports', $uri) ?>
<?php endif; ?>

    </ul>
  </nav>
  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>/auth/logout.php" class="nav-link logout-link">
      <i class="fa fa-sign-out-alt"></i><span>Logout</span>
    </a>
  </div>
</aside>

<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin']);
$pdo = getDB();

$id = sanitizeInt($_GET['id'] ?? 0);
if (!$id) { setFlash('Invalid organization.','danger'); redirect(BASE_URL.'/organization/index.php'); }

$stmt = $pdo->prepare("SELECT o.*, u.name as creator FROM organizations o JOIN users u ON u.id=o.created_by WHERE o.id=? AND o.is_active=1");
$stmt->execute([$id]);
$org = $stmt->fetch();
if (!$org) { setFlash('Organization not found.','danger'); redirect(BASE_URL.'/organization/index.php'); }

// Budget summary
$budget = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM budgets WHERE entity_type='organization' AND entity_id=? AND status='approved' AND fiscal_year=YEAR(CURDATE())");
$budget->execute([$id]); $totalBudget = (float)$budget->fetchColumn();

$approved = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE organization_id=? AND status='approved' AND YEAR(expense_date)=YEAR(CURDATE())");
$approved->execute([$id]); $totalExp = (float)$approved->fetchColumn();

$pending = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE organization_id=? AND status='pending' AND YEAR(expense_date)=YEAR(CURDATE())");
$pending->execute([$id]); $pendingExp = (float)$pending->fetchColumn();

$income = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM income_records WHERE organization_id=? AND YEAR(income_date)=YEAR(CURDATE())");
$income->execute([$id]); $totalIncome = (float)$income->fetchColumn();

$remaining = $totalBudget - $totalExp;
$util = $totalBudget > 0 ? round($totalExp / $totalBudget * 100, 2) : 0;

// Departments
$depts = $pdo->prepare("SELECT d.*, u.name as mgr_name,
    COALESCE(v.allocated_budget,0) AS dept_budget,
    COALESCE(v.approved_expenditure,0) AS dept_exp,
    (SELECT COUNT(*) FROM projects p WHERE p.department_id=d.id AND p.status='active') AS proj_count
    FROM departments d
    LEFT JOIN users u ON u.id=d.manager_id
    LEFT JOIN v_dept_budget_summary v ON v.dept_id=d.id AND v.fiscal_year=YEAR(CURDATE())
    WHERE d.organization_id=? AND d.is_active=1 ORDER BY d.name");
$depts->execute([$id]); $departments = $depts->fetchAll();

// Recent income
$incRec = $pdo->prepare("SELECT ir.*, u.name as recorder FROM income_records ir JOIN users u ON u.id=ir.recorded_by WHERE ir.organization_id=? AND YEAR(ir.income_date)=YEAR(CURDATE()) ORDER BY ir.income_date DESC LIMIT 6");
$incRec->execute([$id]); $incomeRecs = $incRec->fetchAll();

$pageTitle = e($org['name']);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <?= flashMessage() ?>
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa fa-building" style="color:var(--color-primary)"></i> <?= e($org['name']) ?></h1>
      <p class="page-subtitle">Organization Overview &mdash; Created by <?= e($org['creator']) ?> on <?= formatDate($org['created_at']) ?></p>
    </div>
    <div class="page-actions">
      <a href="<?= BASE_URL ?>/organization/edit.php?id=<?= $id ?>" class="btn btn-secondary"><i class="fa fa-edit"></i> Edit</a>
      <a href="<?= BASE_URL ?>/departments/create.php?org_id=<?= $id ?>" class="btn btn-primary"><i class="fa fa-plus"></i> Add Department</a>
      <a href="<?= BASE_URL ?>/organization/index.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back</a>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon"><i class="fa fa-wallet"></i></div>
      <div class="stat-value" style="font-size:1.2rem;"><?= formatMoney($totalBudget) ?></div>
      <div class="stat-label">Approved Budget</div>
    </div>
    <div class="stat-card warning">
      <div class="stat-icon"><i class="fa fa-receipt"></i></div>
      <div class="stat-value" style="font-size:1.2rem;"><?= formatMoney($totalExp) ?></div>
      <div class="stat-label">Approved Expenditure</div>
    </div>
    <div class="stat-card <?= $remaining < 0 ? 'danger' : 'success' ?>">
      <div class="stat-icon"><i class="fa fa-balance-scale"></i></div>
      <div class="stat-value" style="font-size:1.2rem;"><?= formatMoney(abs($remaining)) ?><?= $remaining < 0 ? ' <span style="font-size:0.7rem">OVERRUN</span>' : '' ?></div>
      <div class="stat-label">Remaining Budget</div>
    </div>
    <div class="stat-card success">
      <div class="stat-icon"><i class="fa fa-hand-holding-dollar"></i></div>
      <div class="stat-value" style="font-size:1.2rem;"><?= formatMoney($totalIncome) ?></div>
      <div class="stat-label">Total Income</div>
    </div>
    <div class="stat-card info">
      <div class="stat-icon"><i class="fa fa-clock"></i></div>
      <div class="stat-value" style="font-size:1.2rem;"><?= formatMoney($pendingExp) ?></div>
      <div class="stat-label">Pending Expenses</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon"><i class="fa fa-sitemap"></i></div>
      <div class="stat-value"><?= count($departments) ?></div>
      <div class="stat-label">Departments</div>
    </div>
  </div>

  <!-- Utilization Bar -->
  <?php if ($totalBudget > 0): ?>
  <div class="card" style="margin-bottom:20px;">
    <div class="card-body">
      <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
        <span style="font-size:0.85rem;font-weight:600;">Budget Utilization</span>
        <span class="badge <?= $util >= 100 ? 'badge-danger' : ($util >= 80 ? 'badge-warning' : 'badge-success') ?>"><?= number_format($util, 1) ?>%</span>
      </div>
      <div class="progress-bar-wrap" style="height:12px;">
        <div class="progress-bar bg-<?= $util >= 100 ? 'danger' : ($util >= 80 ? 'warning' : 'success') ?>" style="width:<?= min(100,$util) ?>%"></div>
      </div>
      <?php if ($util >= 90): ?>
      <div class="alert alert-<?= $util >= 100 ? 'danger' : 'warning' ?>" style="margin-top:10px;margin-bottom:0;">
        <i class="fa fa-exclamation-triangle"></i>
        <?= $util >= 100 ? 'Budget overrun! Expenditure has exceeded allocated budget.' : 'Warning: Over 90% of budget has been utilized.' ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Departments Table -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fa fa-sitemap"></i> Departments</div>
      <a href="<?= BASE_URL ?>/departments/create.php?org_id=<?= $id ?>" class="btn btn-sm btn-primary"><i class="fa fa-plus"></i> Add</a>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Department</th><th>Manager</th><th>Budget</th><th>Expenditure</th><th>Utilization</th><th>Projects</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if (empty($departments)): ?>
            <tr><td colspan="7"><div class="empty-state"><i class="fa fa-sitemap"></i><p>No departments yet.</p></div></td></tr>
            <?php else: ?>
            <?php foreach ($departments as $d):
              $du = $d['dept_budget'] > 0 ? $d['dept_exp']/$d['dept_budget']*100 : 0;
            ?>
            <tr>
              <td><strong><?= e($d['name']) ?></strong><?= $d['description'] ? '<br><span style="font-size:0.75rem;color:var(--color-text-3);">'.e(mb_strimwidth($d['description'],0,60,'...')).'</span>' : '' ?></td>
              <td><?= e($d['mgr_name'] ?? '<em>Unassigned</em>') ?></td>
              <td class="font-mono"><?= formatMoney((float)$d['dept_budget']) ?></td>
              <td class="font-mono <?= $d['dept_exp'] > $d['dept_budget'] && $d['dept_budget'] > 0 ? 'text-danger' : '' ?>"><?= formatMoney((float)$d['dept_exp']) ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:8px;">
                  <?= utilizationBar($du) ?>
                  <span style="font-size:0.8rem;min-width:42px;"><?= number_format($du,1) ?>%</span>
                </div>
              </td>
              <td><?= $d['proj_count'] ?></td>
              <td>
                <div style="display:flex;gap:6px;">
                  <a href="<?= BASE_URL ?>/departments/view.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-secondary"><i class="fa fa-eye"></i></a>
                  <a href="<?= BASE_URL ?>/departments/edit.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-primary"><i class="fa fa-edit"></i></a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Recent Income -->
  <?php if (!empty($incomeRecs)): ?>
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fa fa-hand-holding-dollar"></i> Recent Income</div>
      <a href="<?= BASE_URL ?>/income/index.php" class="btn btn-sm btn-secondary">View All</a>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Source</th><th>Amount</th><th>Date</th><th>Recorded By</th></tr></thead>
          <tbody>
            <?php foreach ($incomeRecs as $ir): ?>
            <tr>
              <td><?= e($ir['source']) ?></td>
              <td class="font-mono text-success"><?= formatMoney((float)$ir['amount']) ?></td>
              <td><?= formatDate($ir['income_date']) ?></td>
              <td><?= e($ir['recorder']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin','dept_manager','project_manager']);
$pdo = getDB();
$uid  = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

// Fetch all budgets based on role
if ($role === 'admin') {
    $budgets = $pdo->query("
        SELECT b.*,
            CASE
                WHEN b.entity_type='organization' THEN (SELECT name FROM organizations WHERE id=b.entity_id)
                WHEN b.entity_type='department'   THEN (SELECT name FROM departments WHERE id=b.entity_id)
                WHEN b.entity_type='project'      THEN (SELECT name FROM projects WHERE id=b.entity_id)
            END AS entity_name,
            u.name as created_by_name, a.name as approved_by_name
        FROM budgets b
        JOIN users u ON u.id=b.created_by
        LEFT JOIN users a ON a.id=b.approved_by
        ORDER BY b.fiscal_year DESC, b.entity_type, entity_name
    ")->fetchAll();
} else {
    [$projectScope, $projectParams] = getProjectScopeSql($role, (int)$uid);
    $budgetWhere = ["(b.entity_type='project' AND b.entity_id IN (SELECT p.id FROM projects p WHERE $projectScope))"];
    $budgetParams = $projectParams;
    if ($role === 'dept_manager') {
        $budgetWhere[] = "(b.entity_type='department' AND b.entity_id IN (SELECT id FROM departments WHERE manager_id=?))";
        $budgetParams[] = (int)$uid;
    }
    $budgets = $pdo->prepare("
        SELECT b.*,
            CASE
                WHEN b.entity_type='department' THEN (SELECT name FROM departments WHERE id=b.entity_id)
                WHEN b.entity_type='project'    THEN (SELECT name FROM projects WHERE id=b.entity_id)
            END AS entity_name,
            u.name as created_by_name, a.name as approved_by_name
        FROM budgets b
        JOIN users u ON u.id=b.created_by
        LEFT JOIN users a ON a.id=b.approved_by
        WHERE " . implode(' OR ', $budgetWhere) . "
        ORDER BY b.fiscal_year DESC, b.entity_type, entity_name
    ");
    $budgets->execute($budgetParams);
    $budgets = $budgets->fetchAll();
}

$pageTitle = 'Budgets';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <?= flashMessage() ?>
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa fa-wallet" style="color:var(--color-primary)"></i> Budgets</h1>
      <p class="page-subtitle">Budget allocations across all organizational levels</p>
    </div>
    <div class="page-actions">
      <?php if (in_array($role, ['admin','dept_manager','project_manager'], true)): ?>
      <a href="<?= BASE_URL ?>/budgets/create.php" class="btn btn-primary"><i class="fa fa-plus"></i> Allocate Budget</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Filter pills by entity type -->
  <?php $typeFilter = in_array($_GET['type'] ?? 'all', ['all','organization','department','project'], true) ? ($_GET['type'] ?? 'all') : 'all'; ?>
  <div class="pill-tabs">
    <a href="?type=all" class="pill-tab <?= $typeFilter === 'all' ? 'active' : '' ?>">All</a>
    <a href="?type=organization" class="pill-tab <?= $typeFilter === 'organization' ? 'active' : '' ?>">Organization</a>
    <a href="?type=department" class="pill-tab <?= $typeFilter === 'department' ? 'active' : '' ?>">Department</a>
    <a href="?type=project" class="pill-tab <?= $typeFilter === 'project' ? 'active' : '' ?>">Project</a>
  </div>

  <div class="card">
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Entity</th><th>Type</th><th>Amount</th><th>Fiscal Year</th><th>Period</th><th>Status</th><th>Approved By</th><th>Notes</th><th>Actions</th></tr></thead>
          <tbody>
            <?php
            $filtered = array_filter($budgets, fn($b) => $typeFilter === 'all' || $b['entity_type'] === $typeFilter);
            if (empty($filtered)): ?>
            <tr><td colspan="9"><div class="empty-state"><i class="fa fa-wallet"></i><h3>No Budgets Found</h3><p>No budget allocations match the current filter.</p></div></td></tr>
            <?php else:
            foreach ($filtered as $b): ?>
            <tr>
              <td><strong><?= e($b['entity_name'] ?? '–') ?></strong></td>
              <td><span class="badge badge-<?= $b['entity_type']==='organization' ? 'info' : ($b['entity_type']==='department' ? 'primary' : 'secondary') ?>"><?= ucfirst($b['entity_type']) ?></span></td>
              <td class="font-mono text-success fw-600"><?= formatMoney((float)$b['amount']) ?></td>
              <td><?= e($b['fiscal_year']) ?></td>
              <td style="font-size:0.8rem;"><?= formatDate($b['period_start']) ?><br>→ <?= formatDate($b['period_end']) ?></td>
              <td><?= statusBadge($b['status']) ?></td>
              <td><?= e($b['approved_by_name'] ?? '–') ?></td>
              <td style="font-size:0.8rem;max-width:180px;"><?= e(mb_strimwidth($b['notes'] ?? '', 0, 60, '…')) ?></td>
              <td>
                <?php if ($role === 'admin' && $b['status'] !== 'approved'): ?>
                <form method="POST" action="<?= BASE_URL ?>/budgets/approve.php" style="display:inline;">
                  <?= csrfField() ?>
                  <input type="hidden" name="id" value="<?= $b['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-success"><i class="fa fa-check"></i> Approve</button>
                </form>
                <?php else: ?>
                <span class="text-muted" style="font-size:0.8rem;">–</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

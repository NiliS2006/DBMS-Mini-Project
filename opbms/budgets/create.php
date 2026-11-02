<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin','dept_manager','project_manager']);
$pdo = getDB();
$uid = (int)$_SESSION['user_id'];
$role = $_SESSION['user_role'];

$errors = [];
$preType = trim($_GET['entity_type'] ?? '');
$preId   = sanitizeInt($_GET['entity_id'] ?? 0);

if ($role === 'admin') {
    $orgs = $pdo->query("SELECT id, name FROM organizations WHERE is_active=1 ORDER BY name")->fetchAll();
    $depts = $pdo->query("SELECT d.id, d.name, o.name as org_name FROM departments d JOIN organizations o ON o.id=d.organization_id WHERE d.is_active=1 ORDER BY o.name, d.name")->fetchAll();
    $projs = $pdo->query("SELECT p.id, p.name, d.name as dept_name FROM projects p JOIN departments d ON d.id=p.department_id ORDER BY d.name, p.name")->fetchAll();
} elseif ($role === 'dept_manager') {
    $orgs = [];
    $deptStmt = $pdo->prepare("SELECT d.id, d.name, o.name as org_name FROM departments d JOIN organizations o ON o.id=d.organization_id WHERE d.manager_id=? AND d.is_active=1 ORDER BY o.name, d.name");
    $deptStmt->execute([$uid]);
    $depts = $deptStmt->fetchAll();
    [$projectScope, $projectParams] = getProjectScopeSql($role, $uid);
    $projectStmt = $pdo->prepare("SELECT p.id, p.name, d.name as dept_name FROM projects p JOIN departments d ON d.id=p.department_id WHERE $projectScope ORDER BY d.name, p.name");
    $projectStmt->execute($projectParams);
    $projs = $projectStmt->fetchAll();
} else {
    $orgs = [];
    $depts = [];
    [$projectScope, $projectParams] = getProjectScopeSql($role, $uid);
    $projectStmt = $pdo->prepare("SELECT p.id, p.name, d.name as dept_name FROM projects p JOIN departments d ON d.id=p.department_id WHERE $projectScope ORDER BY d.name, p.name");
    $projectStmt->execute($projectParams);
    $projs = $projectStmt->fetchAll();
}

$data = [
    'entity_type'  => $role === 'admin' ? ($preType ?: 'project') : ($role === 'dept_manager' ? 'department' : 'project'),
    'entity_id'    => $preId,
    'amount'       => '',
    'fiscal_year'  => date('Y'),
    'period_start' => date('Y-01-01'),
    'period_end'   => date('Y-12-31'),
    'status'       => $role === 'admin' ? 'approved' : 'proposed',
    'notes'        => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid CSRF token.'; }
    $data['entity_type']  = trim($_POST['entity_type'] ?? '');
    $data['entity_id']    = sanitizeInt($_POST['entity_id'] ?? 0);
    $data['amount']       = trim($_POST['amount'] ?? '');
    $data['fiscal_year']  = sanitizeInt($_POST['fiscal_year'] ?? date('Y'));
    $data['period_start'] = trim($_POST['period_start'] ?? '');
    $data['period_end']   = trim($_POST['period_end'] ?? '');
    $data['status']       = trim($_POST['status'] ?? 'approved');
    $data['notes']        = trim($_POST['notes'] ?? '');

    $amount = sanitizeFloat($data['amount']);

    if (!in_array($data['entity_type'], ['organization','department','project'])) $errors[] = 'Invalid entity type.';
    if (($role === 'dept_manager' && !in_array($data['entity_type'], ['department','project'], true))
        || ($role === 'project_manager' && $data['entity_type'] !== 'project')) {
        $errors[] = 'You are not authorized to allocate a budget at this level.';
    }
    if ($role !== 'admin' && $data['status'] !== 'proposed') {
        $errors[] = 'Manager budget allocations must be submitted as proposals for administrator approval.';
    }
    if (!$data['entity_id']) $errors[] = 'Please select an entity.';
    if (!isValidMoney($data['amount'])) $errors[] = 'Enter a valid budget amount with up to two decimal places.';
    if (!in_array($data['status'], ['proposed','approved','revised'], true)) $errors[] = 'Invalid budget status.';
    if ($data['fiscal_year'] < 2020 || $data['fiscal_year'] > 2035) $errors[] = 'Fiscal year must be between 2020 and 2035.';
    if (!isValidDate($data['period_start'])) $errors[] = 'Invalid period start date.';
    if (!isValidDate($data['period_end']))   $errors[] = 'Invalid period end date.';
    if ($data['period_end'] < $data['period_start']) $errors[] = 'Period end must be after period start.';
    if (isValidDate($data['period_start']) && isValidDate($data['period_end'])
        && ((int)substr($data['period_start'], 0, 4) !== $data['fiscal_year']
            || (int)substr($data['period_end'], 0, 4) !== $data['fiscal_year'])) {
        $errors[] = 'Budget dates must fall within the selected fiscal year.';
    }

    $parentType = null;
    $parentId = null;
    if (empty($errors)) {
        if ($data['entity_type'] === 'organization') {
            $entityCheck = $pdo->prepare("SELECT id FROM organizations WHERE id=? AND is_active=1");
            $entityCheck->execute([$data['entity_id']]);
            if (!$entityCheck->fetch()) $errors[] = 'Select a valid active organization.';
        } elseif ($data['entity_type'] === 'department') {
            $entityCheck = $pdo->prepare("SELECT organization_id FROM departments WHERE id=? AND is_active=1");
            $entityCheck->execute([$data['entity_id']]);
            $parentId = $entityCheck->fetchColumn();
            if (!$parentId) $errors[] = 'Select a valid active department.';
            else $parentType = 'organization';
            if ($role === 'dept_manager') {
                $managerCheck = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE id=? AND manager_id=?");
                $managerCheck->execute([$data['entity_id'], $uid]);
                if (!$managerCheck->fetchColumn()) $errors[] = 'You are not authorized to budget this department.';
            }
        } else {
            $entityCheck = $pdo->prepare("SELECT department_id FROM projects WHERE id=?");
            $entityCheck->execute([$data['entity_id']]);
            $parentId = $entityCheck->fetchColumn();
            if (!$parentId) $errors[] = 'Select a valid project.';
            else $parentType = 'department';
            if ($role !== 'admin') {
                [$allowedProjects, $allowedProjectParams] = getProjectScopeSql($role, $uid);
                $projectAuth = $pdo->prepare("SELECT COUNT(*) FROM projects p WHERE p.id=? AND ($allowedProjects)");
                $projectAuth->execute(array_merge([$data['entity_id']], $allowedProjectParams));
                if (!$projectAuth->fetchColumn()) $errors[] = 'You are not authorized to budget this project.';
            }
        }
    }

    if (empty($errors)) {
        $duplicateCheck = $pdo->prepare("SELECT COUNT(*) FROM budgets
            WHERE entity_type=? AND entity_id=? AND fiscal_year=? AND period_start=? AND period_end=?
              AND status IN ('approved','revised')");
        $duplicateCheck->execute([$data['entity_type'], $data['entity_id'], $data['fiscal_year'], $data['period_start'], $data['period_end']]);
        if ($duplicateCheck->fetchColumn()) {
            $errors[] = 'An approved budget already exists for this entity and period; duplicate allocations are not allowed.';
        }
    }

    if (empty($errors) && $parentType !== null) {
        $parentBudget = $pdo->prepare("SELECT MAX(amount) FROM budgets
            WHERE entity_type=? AND entity_id=? AND fiscal_year=? AND period_start<=? AND period_end>=? AND status='approved'");
        $parentBudget->execute([$parentType, $parentId, $data['fiscal_year'], $data['period_start'], $data['period_end']]);
        $parentAmount = $parentBudget->fetchColumn();
        if ($parentAmount === null) {
            $errors[] = 'An approved parent budget covering this period is required first.';
        } else {
            if ($data['entity_type'] === 'department') {
                $allocated = $pdo->prepare("SELECT COALESCE(SUM(b.amount),0) FROM budgets b
                    WHERE b.entity_type='department' AND b.entity_id IN
                    (SELECT id FROM departments WHERE organization_id=?)
                    AND b.fiscal_year=? AND b.period_start=? AND b.period_end=?
                    AND b.status IN ('approved','revised','proposed')");
            } else {
                $allocated = $pdo->prepare("SELECT COALESCE(SUM(b.amount),0) FROM budgets b
                    WHERE b.entity_type='project' AND b.entity_id IN
                    (SELECT id FROM projects WHERE department_id=?)
                    AND b.fiscal_year=? AND b.period_start=? AND b.period_end=?
                    AND b.status IN ('approved','revised','proposed')");
            }
            $allocated->execute([$parentId, $data['fiscal_year'], $data['period_start'], $data['period_end']]);
            if ((float)$allocated->fetchColumn() + $amount > (float)$parentAmount) {
                $errors[] = 'This allocation would exceed the available parent budget.';
            }
        }
    }

    if (empty($errors)) {
        $approvedBy = $role === 'admin' && $data['status'] === 'approved' ? $uid : null;
        $approvedAt = $approvedBy !== null ? date('Y-m-d H:i:s') : null;
        $stmt = $pdo->prepare("INSERT INTO budgets (entity_type, entity_id, amount, fiscal_year, period_start, period_end, status, approved_by, approved_at, notes, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$data['entity_type'], $data['entity_id'], $amount, $data['fiscal_year'],
            $data['period_start'], $data['period_end'], $data['status'],
            $approvedBy, $approvedAt, $data['notes'], $_SESSION['user_id']]);
        setFlash('Budget allocated successfully.', 'success');
        redirect(BASE_URL . '/budgets/index.php');
    }
}
$pageTitle = 'Allocate Budget';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <div class="page-header">
    <div><h1 class="page-title"><i class="fa fa-wallet" style="color:var(--color-primary)"></i> Allocate Budget</h1></div>
    <a href="<?= BASE_URL ?>/budgets/index.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back</a>
  </div>
  <div class="card" style="max-width:680px;">
    <div class="card-body">
      <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
      <form method="POST" id="budgetForm">
        <?= csrfField() ?>
        <div class="form-row">
          <div class="form-group">
            <label class="required">Entity Type</label>
            <select name="entity_type" id="entityType" required onchange="updateEntityList()">
              <?php if ($role === 'admin'): ?>
              <option value="organization" <?= $data['entity_type']==='organization'?'selected':'' ?>>Organization</option>
              <?php endif; ?>
              <?php if (in_array($role, ['admin','dept_manager'], true)): ?>
              <option value="department"   <?= $data['entity_type']==='department'?'selected':'' ?>>Department</option>
              <?php endif; ?>
              <option value="project"      <?= $data['entity_type']==='project'?'selected':'' ?>>Project</option>
            </select>
          </div>
          <div class="form-group">
            <label class="required">Fiscal Year</label>
            <input type="number" name="fiscal_year" value="<?= e($data['fiscal_year']) ?>" min="2020" max="2035" required>
          </div>
        </div>

        <!-- Dynamic entity select lists -->
        <div class="form-group" id="orgSelect" style="display:none;">
          <label class="required">Organization</label>
          <select name="entity_id_org">
            <option value="">-- Select --</option>
            <?php foreach ($orgs as $o): ?>
            <option value="<?= $o['id'] ?>" <?= ($data['entity_type']==='organization' && $data['entity_id']==$o['id'])? 'selected':'' ?>><?= e($o['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" id="deptSelect" style="display:none;">
          <label class="required">Department</label>
          <select name="entity_id_dept">
            <option value="">-- Select --</option>
            <?php foreach ($depts as $d): ?>
            <option value="<?= $d['id'] ?>" <?= ($data['entity_type']==='department' && $data['entity_id']==$d['id'])? 'selected':'' ?>><?= e($d['org_name']) ?> / <?= e($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" id="projSelect" style="display:none;">
          <label class="required">Project</label>
          <select name="entity_id_proj">
            <option value="">-- Select --</option>
            <?php foreach ($projs as $p): ?>
            <option value="<?= $p['id'] ?>" <?= ($data['entity_type']==='project' && $data['entity_id']==$p['id'])? 'selected':'' ?>><?= e($p['dept_name']) ?> / <?= e($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <input type="hidden" name="entity_id" id="entityIdHidden" value="<?= $data['entity_id'] ?>">

        <div class="form-row">
          <div class="form-group">
            <label class="required">Budget Amount (₹)</label>
            <input type="number" name="amount" value="<?= e($data['amount']) ?>" min="1" step="0.01" required placeholder="e.g., 500000">
          </div>
          <div class="form-group">
            <label class="required">Status</label>
            <?php if ($role === 'admin'): ?>
            <select name="status">
              <option value="proposed" <?= $data['status']==='proposed'?'selected':'' ?>>Proposed</option>
              <option value="approved" <?= $data['status']==='approved'?'selected':'' ?>>Approved</option>
              <option value="revised"  <?= $data['status']==='revised'?'selected':'' ?>>Revised</option>
            </select>
            <?php else: ?>
            <input type="hidden" name="status" value="proposed">
            <input type="text" value="Proposed (requires administrator approval)" disabled>
            <?php endif; ?>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="required">Period Start</label>
            <input type="date" name="period_start" value="<?= e($data['period_start']) ?>" required>
          </div>
          <div class="form-group">
            <label class="required">Period End</label>
            <input type="date" name="period_end" value="<?= e($data['period_end']) ?>" required>
          </div>
        </div>
        <div class="form-group">
          <label>Notes</label>
          <textarea name="notes" placeholder="Optional notes about this budget allocation..."><?= e($data['notes']) ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Budget</button>
      </form>
    </div>
  </div>
</main>
<script>
function updateEntityList() {
    const type = document.getElementById('entityType').value;
    document.getElementById('orgSelect').style.display  = type === 'organization' ? '' : 'none';
    document.getElementById('deptSelect').style.display = type === 'department'   ? '' : 'none';
    document.getElementById('projSelect').style.display = type === 'project'      ? '' : 'none';
}
// Sync entity_id hidden field on form submit
document.getElementById('budgetForm').addEventListener('submit', function() {
    const type = document.getElementById('entityType').value;
    const map = { organization: 'entity_id_org', department: 'entity_id_dept', project: 'entity_id_proj' };
    const sel = document.querySelector(`select[name="${map[type]}"]`);
    document.getElementById('entityIdHidden').value = sel ? sel.value : '';
});
// Init
updateEntityList();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

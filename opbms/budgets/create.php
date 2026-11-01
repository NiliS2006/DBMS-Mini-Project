<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin']);
$pdo = getDB();

$errors = [];
$preType = trim($_GET['entity_type'] ?? '');
$preId   = sanitizeInt($_GET['entity_id'] ?? 0);

$orgs    = $pdo->query("SELECT id, name FROM organizations WHERE is_active=1 ORDER BY name")->fetchAll();
$depts   = $pdo->query("SELECT d.id, d.name, o.name as org_name FROM departments d JOIN organizations o ON o.id=d.organization_id WHERE d.is_active=1 ORDER BY o.name, d.name")->fetchAll();
$projs   = $pdo->query("SELECT p.id, p.name, d.name as dept_name FROM projects p JOIN departments d ON d.id=p.department_id ORDER BY d.name, p.name")->fetchAll();

$data = [
    'entity_type'  => $preType ?: 'project',
    'entity_id'    => $preId,
    'amount'       => '',
    'fiscal_year'  => date('Y'),
    'period_start' => date('Y-01-01'),
    'period_end'   => date('Y-12-31'),
    'status'       => 'approved',
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
    if (!$data['entity_id']) $errors[] = 'Please select an entity.';
    if ($amount <= 0) $errors[] = 'Budget amount must be greater than zero.';
    if (!isValidDate($data['period_start'])) $errors[] = 'Invalid period start date.';
    if (!isValidDate($data['period_end']))   $errors[] = 'Invalid period end date.';
    if ($data['period_end'] < $data['period_start']) $errors[] = 'Period end must be after period start.';

    if (empty($errors)) {
        $approvedBy = $data['status'] === 'approved' ? $_SESSION['user_id'] : null;
        $approvedAt = $data['status'] === 'approved' ? date('Y-m-d H:i:s') : null;
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
              <option value="organization" <?= $data['entity_type']==='organization'?'selected':'' ?>>Organization</option>
              <option value="department"   <?= $data['entity_type']==='department'?'selected':'' ?>>Department</option>
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
            <select name="status">
              <option value="proposed" <?= $data['status']==='proposed'?'selected':'' ?>>Proposed</option>
              <option value="approved" <?= $data['status']==='approved'?'selected':'' ?>>Approved</option>
              <option value="revised"  <?= $data['status']==='revised'?'selected':'' ?>>Revised</option>
            </select>
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

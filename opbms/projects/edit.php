<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin']);
$pdo = getDB();

$id = sanitizeInt($_GET['id'] ?? 0);
if (!$id) { setFlash('Invalid project.','danger'); redirect(BASE_URL.'/projects/index.php'); }

$stmt = $pdo->prepare("SELECT * FROM projects WHERE id=?");
$stmt->execute([$id]); $project = $stmt->fetch();
if (!$project) { setFlash('Project not found.','danger'); redirect(BASE_URL.'/projects/index.php'); }

$depts    = $pdo->query("SELECT d.id, d.name, o.name as org_name FROM departments d JOIN organizations o ON o.id=d.organization_id WHERE d.is_active=1 ORDER BY o.name, d.name")->fetchAll();
$managers = $pdo->query("SELECT id, name, email FROM users WHERE role IN ('project_manager','admin') AND is_active=1 ORDER BY name")->fetchAll();

// Current members
$currMembers = $pdo->prepare("SELECT user_id FROM project_members WHERE project_id=? AND role='member'");
$currMembers->execute([$id]); $currentMemberIds = array_column($currMembers->fetchAll(), 'user_id');
$employees = $pdo->query("SELECT id, name, email FROM users WHERE role IN ('employee','project_manager','admin') AND is_active=1 ORDER BY name")->fetchAll();

$errors = [];
$data = [
    'department_id' => $project['department_id'],
    'name'          => $project['name'],
    'description'   => $project['description'] ?? '',
    'manager_id'    => $project['manager_id'] ?? '',
    'start_date'    => $project['start_date'] ?? '',
    'end_date'      => $project['end_date'] ?? '',
    'status'        => $project['status']
];
$selectedMembers = $currentMemberIds;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid CSRF token.'; }
    $data['department_id'] = sanitizeInt($_POST['department_id'] ?? 0);
    $data['name']          = trim($_POST['name'] ?? '');
    $data['description']   = trim($_POST['description'] ?? '');
    $data['manager_id']    = sanitizeInt($_POST['manager_id'] ?? 0) ?: null;
    $data['start_date']    = trim($_POST['start_date'] ?? '');
    $data['end_date']      = trim($_POST['end_date'] ?? '');
    $data['status']        = trim($_POST['status'] ?? 'active');
    $selectedMembers       = array_filter(array_map('intval', $_POST['members'] ?? []));

    if (!$data['department_id']) $errors[] = 'Please select a department.';
    if (!$data['name'])          $errors[] = 'Project name is required.';
    if ($data['start_date'] && $data['end_date'] && $data['end_date'] < $data['start_date']) $errors[] = 'End date must be after start date.';

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE projects SET department_id=?, name=?, description=?, manager_id=?, start_date=?, end_date=?, status=? WHERE id=?");
            $stmt->execute([$data['department_id'], $data['name'], $data['description'], $data['manager_id'],
                $data['start_date'] ?: null, $data['end_date'] ?: null, $data['status'], $id]);
            // Sync members: remove all, re-add
            $pdo->prepare("DELETE FROM project_members WHERE project_id=?")->execute([$id]);
            if ($data['manager_id']) {
                $pdo->prepare("INSERT IGNORE INTO project_members (project_id, user_id, role) VALUES (?,?,'manager')")->execute([$id, $data['manager_id']]);
            }
            foreach ($selectedMembers as $memberId) {
                if ($memberId === (int)$data['manager_id']) continue;
                $pdo->prepare("INSERT IGNORE INTO project_members (project_id, user_id, role) VALUES (?,?,'member')")->execute([$id, $memberId]);
            }
            $pdo->commit();
            setFlash('Project updated successfully.', 'success');
            redirect(BASE_URL . '/projects/view.php?id=' . $id);
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Failed to update project.';
        }
    }
}
$pageTitle = 'Edit Project';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <div class="page-header">
    <div><h1 class="page-title"><i class="fa fa-edit" style="color:var(--color-primary)"></i> Edit Project</h1></div>
    <a href="<?= BASE_URL ?>/projects/view.php?id=<?= $id ?>" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back</a>
  </div>
  <div class="card" style="max-width:760px;">
    <div class="card-body">
      <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
      <form method="POST">
        <?= csrfField() ?>
        <div class="form-row">
          <div class="form-group">
            <label class="required">Department</label>
            <select name="department_id" required>
              <?php foreach ($depts as $d): ?>
              <option value="<?= $d['id'] ?>" <?= $data['department_id'] == $d['id'] ? 'selected' : '' ?>><?= e($d['org_name']) ?> / <?= e($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status">
              <?php foreach (['active','on_hold','completed','cancelled'] as $s): ?>
              <option value="<?= $s ?>" <?= $data['status'] === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="required">Project Name</label>
          <input type="text" name="name" value="<?= e($data['name']) ?>" required>
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea name="description"><?= e($data['description']) ?></textarea>
        </div>
        <div class="form-group">
          <label>Project Manager</label>
          <select name="manager_id">
            <option value="">-- No Manager --</option>
            <?php foreach ($managers as $m): ?>
            <option value="<?= $m['id'] ?>" <?= $data['manager_id'] == $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Start Date</label>
            <input type="date" name="start_date" value="<?= e($data['start_date']) ?>">
          </div>
          <div class="form-group">
            <label>End Date</label>
            <input type="date" name="end_date" value="<?= e($data['end_date']) ?>">
          </div>
        </div>
        <div class="form-group">
          <label>Team Members</label>
          <select name="members[]" multiple style="min-height:120px;">
            <?php foreach ($employees as $emp): ?>
            <option value="<?= $emp['id'] ?>" <?= in_array($emp['id'], $selectedMembers) ? 'selected' : '' ?>><?= e($emp['name']) ?> &lt;<?= e($emp['email']) ?>&gt;</option>
            <?php endforeach; ?>
          </select>
          <p class="form-hint">Hold Ctrl/Cmd to select multiple members.</p>
        </div>
        <div style="display:flex;gap:10px;">
          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Update Project</button>
          <a href="<?= BASE_URL ?>/projects/view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

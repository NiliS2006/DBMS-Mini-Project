<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin']);
$pdo = getDB();

$depts    = $pdo->query("SELECT d.id, d.name, o.name as org_name FROM departments d JOIN organizations o ON o.id=d.organization_id WHERE d.is_active=1 ORDER BY o.name, d.name")->fetchAll();
$managers = $pdo->query("SELECT id, name, email FROM users WHERE role IN ('project_manager','admin') AND is_active=1 ORDER BY name")->fetchAll();
$employees = $pdo->query("SELECT id, name, email FROM users WHERE role IN ('employee','project_manager','admin') AND is_active=1 ORDER BY name")->fetchAll();

$errors = [];
$data = [
    'department_id' => sanitizeInt($_GET['dept_id'] ?? 0),
    'name' => '', 'description' => '', 'manager_id' => '',
    'start_date' => '', 'end_date' => '', 'status' => 'active'
];
$selectedMembers = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid CSRF token.'; }
    $data['department_id'] = sanitizeInt($_POST['department_id'] ?? 0);
    $data['name']          = trim($_POST['name'] ?? '');
    $data['description']   = trim($_POST['description'] ?? '');
    $data['manager_id']    = sanitizeInt($_POST['manager_id'] ?? 0) ?: null;
    $data['start_date']    = trim($_POST['start_date'] ?? '');
    $data['end_date']      = trim($_POST['end_date'] ?? '');
    $data['status']        = trim($_POST['status'] ?? 'active');
    $membersInput = $_POST['members'] ?? [];
    if (!is_array($membersInput)) {
        $selectedMembers = [];
        $errors[] = 'Invalid project team selection.';
    } else {
        $selectedMembers = array_values(array_unique(array_filter(array_map('intval', $membersInput), fn($memberId) => $memberId > 0)));
    }

    if (!$data['department_id']) $errors[] = 'Please select a department.';
    if (!$data['name'] || strlen($data['name']) > 200) $errors[] = 'Project name must be between 1 and 200 characters.';
    if (!in_array($data['status'], ['active','completed','on_hold','cancelled'], true)) $errors[] = 'Invalid project status.';
    if ($data['start_date'] && !isValidDate($data['start_date'])) $errors[] = 'Invalid start date.';
    if ($data['end_date'] && !isValidDate($data['end_date'])) $errors[] = 'Invalid end date.';
    if ($data['start_date'] && $data['end_date'] && $data['end_date'] < $data['start_date']) $errors[] = 'End date must be after start date.';

    if ($data['department_id']) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE id=? AND is_active=1");
        $check->execute([$data['department_id']]);
        if (!$check->fetchColumn()) $errors[] = 'Select an active department.';
    }
    if ($data['manager_id']) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id=? AND is_active=1 AND role IN ('project_manager','admin')");
        $check->execute([$data['manager_id']]);
        if (!$check->fetchColumn()) $errors[] = 'Select an active project manager.';
    }
    if ($selectedMembers) {
        $placeholders = implode(',', array_fill(0, count($selectedMembers), '?'));
        $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id IN ($placeholders) AND is_active=1 AND role IN ('employee','project_manager','admin')");
        $check->execute($selectedMembers);
        if ((int)$check->fetchColumn() !== count($selectedMembers)) $errors[] = 'Project team contains an invalid or inactive user.';
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO projects (department_id, name, description, manager_id, start_date, end_date, status) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([
                $data['department_id'], $data['name'], $data['description'],
                $data['manager_id'],
                $data['start_date'] ?: null, $data['end_date'] ?: null, $data['status']
            ]);
            $projId = $pdo->lastInsertId();
            // Add manager as project member
            if ($data['manager_id']) {
                $pm = $pdo->prepare("INSERT IGNORE INTO project_members (project_id, user_id, role) VALUES (?,?,'manager')");
                $pm->execute([$projId, $data['manager_id']]);
            }
            // Add other members
            foreach ($selectedMembers as $memberId) {
                if ($memberId === (int)$data['manager_id']) continue;
                $pm = $pdo->prepare("INSERT IGNORE INTO project_members (project_id, user_id, role) VALUES (?,?,'member')");
                $pm->execute([$projId, $memberId]);
            }
            $pdo->commit();
            setFlash('Project created successfully.', 'success');
            redirect(BASE_URL . '/projects/view.php?id=' . $projId);
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Failed to create project. Please try again.';
        }
    }
}
$pageTitle = 'New Project';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <div class="page-header">
    <div><h1 class="page-title"><i class="fa fa-plus-circle" style="color:var(--color-primary)"></i> New Project</h1></div>
    <a href="<?= BASE_URL ?>/projects/index.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back</a>
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
              <option value="">-- Select Department --</option>
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
          <input type="text" name="name" value="<?= e($data['name']) ?>" required placeholder="e.g., ERP System Development">
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea name="description" placeholder="Brief project description..."><?= e($data['description']) ?></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Project Manager</label>
            <select name="manager_id">
              <option value="">-- No Manager --</option>
              <?php foreach ($managers as $m): ?>
              <option value="<?= $m['id'] ?>" <?= $data['manager_id'] == $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
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
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Create Project</button>
      </form>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

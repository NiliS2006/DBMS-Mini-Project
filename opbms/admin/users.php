<?php
session_start();
define('BASE_URL', '/opbms');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole(['admin']);
$pdo = getDB();

// ─── Handle POST actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('Invalid request.', 'danger');
        redirect(BASE_URL . '/admin/users.php');
    }

    $action = $_POST['action'] ?? '';
    $userId = sanitizeInt($_POST['user_id'] ?? 0);

    // Prevent admin from deactivating themselves
    if ($userId === (int)$_SESSION['user_id'] && in_array($action, ['deactivate', 'delete'])) {
        setFlash('You cannot deactivate or delete your own account.', 'danger');
        redirect(BASE_URL . '/admin/users.php');
    }

    if ($action === 'toggle_active' && $userId > 0) {
        $stmt = $pdo->prepare("UPDATE users SET is_active = 1 - is_active WHERE id = ?");
        $stmt->execute([$userId]);
        setFlash('User status updated.', 'success');
        redirect(BASE_URL . '/admin/users.php');
    }

    if ($action === 'change_role' && $userId > 0) {
        $newRole = $_POST['role'] ?? '';
        $validRoles = ['admin','dept_manager','project_manager','employee','personal'];
        if (!in_array($newRole, $validRoles)) {
            setFlash('Invalid role selected.', 'danger');
            redirect(BASE_URL . '/admin/users.php');
        }
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$newRole, $userId]);
        setFlash('User role updated successfully.', 'success');
        redirect(BASE_URL . '/admin/users.php');
    }

    if ($action === 'create_user') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role_new = $_POST['role'] ?? 'employee';
        $errors   = [];
        if (strlen($name) < 2)        $errors[] = 'Name must be at least 2 characters.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
        if (strlen($password) < 6)    $errors[] = 'Password must be at least 6 characters.';
        if (!in_array($role_new, ['admin','dept_manager','project_manager','employee','personal'])) $errors[] = 'Invalid role.';

        if (empty($errors)) {
            // Check duplicate email
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $errors[] = 'Email already in use.';
            }
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)");
            $stmt->execute([$name, $email, $hash, $role_new]);
            setFlash("User '{$name}' created successfully.", 'success');
            redirect(BASE_URL . '/admin/users.php');
        } else {
            setFlash(implode(' ', $errors), 'danger');
            redirect(BASE_URL . '/admin/users.php?create=1');
        }
    }

    if ($action === 'reset_password' && $userId > 0) {
        $newPass = $_POST['new_password'] ?? '';
        if (strlen($newPass) < 6) {
            setFlash('Password must be at least 6 characters.', 'danger');
            redirect(BASE_URL . '/admin/users.php');
        }
        $hash = password_hash($newPass, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $userId]);
        setFlash('Password reset successfully.', 'success');
        redirect(BASE_URL . '/admin/users.php');
    }

    redirect(BASE_URL . '/admin/users.php');
}

// ─── List users ───────────────────────────────────────────────────────────────
$roleFilter   = $_GET['role']   ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$search       = trim($_GET['q'] ?? '');

$where  = ['1=1'];
$params = [];
if ($roleFilter !== 'all')   { $where[] = 'role = ?';      $params[] = $roleFilter; }
if ($statusFilter === '1')   { $where[] = 'is_active = 1'; }
if ($statusFilter === '0')   { $where[] = 'is_active = 0'; }
if ($search !== '')          { $where[] = '(name LIKE ? OR email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

$users = $pdo->prepare("
    SELECT * FROM users WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC
");
$users->execute($params);
$users = $users->fetchAll();

// Role counts for quick stats
$roleCounts = $pdo->query("
    SELECT role, COUNT(*) AS cnt, SUM(is_active) AS active FROM users GROUP BY role
")->fetchAll();
$roleStats = [];
foreach ($roleCounts as $r) $roleStats[$r['role']] = $r;

$showCreate = isset($_GET['create']);

$pageTitle = 'User Management';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
  <?= flashMessage() ?>
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="fa fa-users" style="color:var(--color-primary)"></i> User Management</h1>
      <p class="page-subtitle">Create, manage roles, and control access for all system users</p>
    </div>
    <div class="page-actions">
      <a href="?create=1" class="btn btn-primary"><i class="fa fa-user-plus"></i> Add User</a>
    </div>
  </div>

  <!-- Role Summary Cards -->
  <div class="stats-grid" style="margin-bottom:28px;">
    <?php
    $roleInfo = [
      'admin'           => ['label'=>'Admins',          'icon'=>'user-shield',   'color'=>'#ef4444,#dc2626'],
      'dept_manager'    => ['label'=>'Dept Managers',   'icon'=>'sitemap',       'color'=>'#6366f1,#4f46e5'],
      'project_manager' => ['label'=>'Proj Managers',   'icon'=>'project-diagram','color'=>'#f59e0b,#d97706'],
      'employee'        => ['label'=>'Employees',       'icon'=>'user-tie',      'color'=>'#10b981,#059669'],
      'personal'        => ['label'=>'Personal Users',  'icon'=>'user',          'color'=>'#3b82f6,#2563eb'],
    ];
    foreach ($roleInfo as $r => $info):
      $cnt    = $roleStats[$r]['cnt']    ?? 0;
      $active = $roleStats[$r]['active'] ?? 0;
    ?>
    <div class="stat-card" style="cursor:pointer;" onclick="location.href='?role=<?= $r ?>'">
      <div class="stat-icon" style="background:linear-gradient(135deg,<?= $info['color'] ?>)">
        <i class="fa fa-<?= $info['icon'] ?>"></i>
      </div>
      <div class="stat-info">
        <div class="stat-value"><?= $cnt ?></div>
        <div class="stat-label"><?= $info['label'] ?></div>
        <div class="stat-sub"><?= $active ?> active</div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Create User Modal Inline Panel -->
  <?php if ($showCreate): ?>
  <div class="card" style="margin-bottom:24px;border:2px solid var(--color-primary);">
    <div class="card-header">
      <h3 class="card-title"><i class="fa fa-user-plus"></i> Create New User</h3>
      <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-sm btn-secondary"><i class="fa fa-times"></i></a>
    </div>
    <div class="card-body">
      <form method="POST" action="<?= BASE_URL ?>/admin/users.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create_user">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Full Name *</label>
            <input type="text" name="name" class="form-control" required placeholder="e.g. John Doe">
          </div>
          <div class="form-group">
            <label class="form-label">Email Address *</label>
            <input type="email" name="email" class="form-control" required placeholder="user@example.com">
          </div>
          <div class="form-group">
            <label class="form-label">Password *</label>
            <input type="password" name="password" class="form-control" required placeholder="Min 6 characters" minlength="6">
          </div>
          <div class="form-group">
            <label class="form-label">Role *</label>
            <select name="role" class="form-control" required>
              <option value="employee">Employee</option>
              <option value="project_manager">Project Manager</option>
              <option value="dept_manager">Department Manager</option>
              <option value="personal">Personal User</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </div>
        <div style="display:flex;gap:12px;margin-top:8px;">
          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Create User</button>
          <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- Filters -->
  <form method="GET" class="filter-bar" style="margin-bottom:20px;">
    <input type="text" name="q" class="form-control" placeholder="Search name or email..." value="<?= e($search) ?>" style="min-width:220px;">
    <select name="role" onchange="this.form.submit()">
      <option value="all" <?= $roleFilter==='all'?'selected':'' ?>>All Roles</option>
      <option value="admin"           <?= $roleFilter==='admin'?'selected':'' ?>>Admin</option>
      <option value="dept_manager"    <?= $roleFilter==='dept_manager'?'selected':'' ?>>Dept Manager</option>
      <option value="project_manager" <?= $roleFilter==='project_manager'?'selected':'' ?>>Project Manager</option>
      <option value="employee"        <?= $roleFilter==='employee'?'selected':'' ?>>Employee</option>
      <option value="personal"        <?= $roleFilter==='personal'?'selected':'' ?>>Personal</option>
    </select>
    <select name="status" onchange="this.form.submit()">
      <option value="all" <?= $statusFilter==='all'?'selected':'' ?>>All Statuses</option>
      <option value="1"   <?= $statusFilter==='1'?'selected':'' ?>>Active</option>
      <option value="0"   <?= $statusFilter==='0'?'selected':'' ?>>Inactive</option>
    </select>
    <button type="submit" class="btn btn-secondary"><i class="fa fa-search"></i> Search</button>
    <?php if ($roleFilter!=='all'||$statusFilter!=='all'||$search!==''): ?>
    <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-secondary">Clear</a>
    <?php endif; ?>
  </form>

  <!-- Users Table -->
  <div class="card">
    <div class="card-body" style="padding:0;">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($users)): ?>
            <tr><td colspan="7">
              <div class="empty-state">
                <i class="fa fa-users"></i>
                <h3>No Users Found</h3>
                <p>No users match the current filters.</p>
              </div>
            </td></tr>
            <?php else: ?>
            <?php foreach ($users as $u): ?>
            <tr>
              <td><?= $u['id'] ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div class="user-avatar" style="width:34px;height:34px;font-size:14px;flex-shrink:0;">
                    <?= strtoupper(substr($u['name'], 0, 1)) ?>
                  </div>
                  <strong><?= e($u['name']) ?></strong>
                </div>
              </td>
              <td><?= e($u['email']) ?></td>
              <td>
                <!-- Inline role change form -->
                <form method="POST" style="display:inline-flex;gap:4px;align-items:center;">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="change_role">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <select name="role" class="form-control" style="padding:4px 6px;font-size:13px;min-width:130px;"
                    onchange="if(confirm('Change role for <?= e(addslashes($u['name'])) ?>?')) this.form.submit();">
                    <?php foreach (['admin','dept_manager','project_manager','employee','personal'] as $r): ?>
                    <option value="<?= $r ?>" <?= $u['role']===$r?'selected':'' ?>><?= ucwords(str_replace('_',' ',$r)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </td>
              <td>
                <?php if ($u['is_active']): ?>
                  <span class="badge badge-success">Active</span>
                <?php else: ?>
                  <span class="badge badge-danger">Inactive</span>
                <?php endif; ?>
              </td>
              <td><?= formatDate($u['created_at']) ?></td>
              <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                  <!-- Toggle active -->
                  <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                  <form method="POST" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <button type="submit" class="btn btn-sm <?= $u['is_active']?'btn-warning':'btn-success' ?>"
                      title="<?= $u['is_active']?'Deactivate':'Activate' ?>"
                      onclick="return confirm('<?= $u['is_active']?'Deactivate':'Activate' ?> this user?')">
                      <i class="fa fa-<?= $u['is_active']?'ban':'check' ?>"></i>
                    </button>
                  </form>
                  <?php endif; ?>

                  <!-- Reset password (modal trigger) -->
                  <button class="btn btn-sm btn-secondary" title="Reset Password"
                    onclick="openPwdReset(<?= $u['id'] ?>, '<?= e(addslashes($u['name'])) ?>')">
                    <i class="fa fa-key"></i>
                  </button>
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
</main>

<!-- Password Reset Modal -->
<div id="pwdModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000;align-items:center;justify-content:center;">
  <div class="card" style="min-width:360px;max-width:460px;width:90%;">
    <div class="card-header">
      <h3 class="card-title"><i class="fa fa-key"></i> Reset Password</h3>
      <button onclick="document.getElementById('pwdModal').style.display='none'" class="btn btn-sm btn-secondary">
        <i class="fa fa-times"></i>
      </button>
    </div>
    <div class="card-body">
      <p id="pwdModalUser" style="margin-bottom:16px;color:var(--color-text-muted)"></p>
      <form method="POST" action="<?= BASE_URL ?>/admin/users.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="user_id" id="pwdUserId">
        <div class="form-group">
          <label class="form-label">New Password *</label>
          <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Min 6 characters">
        </div>
        <div style="display:flex;gap:12px;margin-top:8px;">
          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Reset</button>
          <button type="button" class="btn btn-secondary" onclick="document.getElementById('pwdModal').style.display='none'">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function openPwdReset(id, name) {
  document.getElementById('pwdUserId').value = id;
  document.getElementById('pwdModalUser').textContent = 'Resetting password for: ' + name;
  const m = document.getElementById('pwdModal');
  m.style.display = 'flex';
}
document.getElementById('pwdModal').addEventListener('click', function(e) {
  if (e.target === this) this.style.display = 'none';
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

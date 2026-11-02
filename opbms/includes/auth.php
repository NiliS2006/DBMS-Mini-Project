<?php
// Authentication and session management helpers
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }

    $now = time();
    if (isset($_SESSION['last_activity']) && $now - (int)$_SESSION['last_activity'] > 1800) {
        logoutUser();
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }

    $stmt = getDB()->prepare('SELECT name, email, role, is_active FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || !$user['is_active']) {
        logoutUser();
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }

    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['last_activity'] = $now;
}

function requireRole(array $roles): void {
    requireLogin();
    if (!in_array($_SESSION['user_role'], $roles, true)) {
        http_response_code(403);
        include __DIR__ . '/../includes/403.php';
        exit;
    }
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user === null) {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id, name, email, role, is_active FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['last_activity'] = time();
}

function logoutUser(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    session_destroy();
}

function hasRole(string $role): bool {
    return isLoggedIn() && $_SESSION['user_role'] === $role;
}

function canManageOrg(): bool {
    return isLoggedIn() && $_SESSION['user_role'] === 'admin';
}

function canApproveDept(): bool {
    return isLoggedIn() && in_array($_SESSION['user_role'], ['admin', 'dept_manager'], true);
}

function canApproveProject(): bool {
    return isLoggedIn() && in_array($_SESSION['user_role'], ['admin', 'dept_manager', 'project_manager'], true);
}

function getProjectScopeSql(string $role, int $userId, string $alias = 'p'): array {
    if (!preg_match('/^[a-z][a-z0-9_]*$/iD', $alias)) {
        throw new InvalidArgumentException('Invalid SQL alias.');
    }

    if ($role === 'admin') {
        return ['1=1', []];
    }
    if ($role === 'employee') {
        return [
            "$alias.id IN (SELECT project_id FROM project_members WHERE user_id = ?)",
            [$userId]
        ];
    }
    if ($role === 'dept_manager') {
        return [
            "($alias.department_id IN (SELECT id FROM departments WHERE manager_id = ?)
              OR $alias.manager_id = ?
              OR $alias.id IN (SELECT project_id FROM project_members WHERE user_id = ? AND role = 'manager'))",
            [$userId, $userId, $userId]
        ];
    }
    if ($role === 'project_manager') {
        return [
            "($alias.manager_id = ?
              OR $alias.id IN (SELECT project_id FROM project_members WHERE user_id = ? AND role = 'manager'))",
            [$userId, $userId]
        ];
    }

    return ['1=0', []];
}

function getExpenseScopeSql(string $role, int $userId, string $alias = 'e'): array {
    if (!preg_match('/^[a-z][a-z0-9_]*$/iD', $alias)) {
        throw new InvalidArgumentException('Invalid SQL alias.');
    }

    if ($role === 'admin') {
        return ['1=1', []];
    }
    if ($role === 'employee') {
        return ["$alias.submitted_by = ?", [$userId]];
    }

    [$projectScope, $projectParams] = getProjectScopeSql($role, $userId, 'p');
    if ($role === 'dept_manager') {
        return [
            "($alias.department_id IN (SELECT id FROM departments WHERE manager_id = ?)
              OR $alias.project_id IN (SELECT p.id FROM projects p WHERE $projectScope))",
            array_merge([$userId], $projectParams)
        ];
    }
    if ($role === 'project_manager') {
        return [
            "$alias.project_id IN (SELECT p.id FROM projects p WHERE $projectScope)",
            $projectParams
        ];
    }

    return ['1=0', []];
}

function getIncomeScopeSql(string $role, int $userId, string $alias = 'i'): array {
    if (!preg_match('/^[a-z][a-z0-9_]*$/iD', $alias)) {
        throw new InvalidArgumentException('Invalid SQL alias.');
    }

    if ($role === 'admin') {
        return ['1=1', []];
    }

    [$projectScope, $projectParams] = getProjectScopeSql($role, $userId, 'p');
    if ($role === 'dept_manager') {
        return [
            "($alias.department_id IN (SELECT id FROM departments WHERE manager_id = ?)
              OR $alias.project_id IN (SELECT p.id FROM projects p WHERE $projectScope))",
            array_merge([$userId], $projectParams)
        ];
    }
    if ($role === 'project_manager') {
        return [
            "$alias.project_id IN (SELECT p.id FROM projects p WHERE $projectScope)",
            $projectParams
        ];
    }

    return ['1=0', []];
}

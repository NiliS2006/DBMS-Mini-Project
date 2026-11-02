<?php
// Global helper functions for OPBMS

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function formatMoney(float $amount, string $currency = '₹'): string {
    return $currency . number_format($amount, 2, '.', ',');
}

function formatDate(?string $date): string {
    if (!$date) return '-';
    return date('d M Y', strtotime($date));
}

function formatDateTime(?string $dt): string {
    if (!$dt) return '-';
    return date('d M Y, h:i A', strtotime($dt));
}

function statusBadge(string $status): string {
    $map = [
        'pending'   => '<span class="badge badge-warning">Pending</span>',
        'approved'  => '<span class="badge badge-success">Approved</span>',
        'rejected'  => '<span class="badge badge-danger">Rejected</span>',
        'active'    => '<span class="badge badge-success">Active</span>',
        'completed' => '<span class="badge badge-info">Completed</span>',
        'on_hold'   => '<span class="badge badge-warning">On Hold</span>',
        'cancelled' => '<span class="badge badge-danger">Cancelled</span>',
        'proposed'  => '<span class="badge badge-secondary">Proposed</span>',
        'revised'   => '<span class="badge badge-info">Revised</span>',
    ];
    return $map[$status] ?? '<span class="badge badge-secondary">' . e(ucfirst($status)) . '</span>';
}

function utilizationBar(float $pct): string {
    $color = $pct >= 100 ? 'danger' : ($pct >= 80 ? 'warning' : 'success');
    $pct   = min($pct, 100);
    return '<div class="progress-bar-wrap">
        <div class="progress-bar bg-' . $color . '" style="width:' . $pct . '%"></div>
        </div>';
}

function flashMessage(string $key = 'flash'): string {
    if (!empty($_SESSION[$key])) {
        $msg  = $_SESSION[$key];
        $type = $_SESSION[$key . '_type'] ?? 'info';
        unset($_SESSION[$key], $_SESSION[$key . '_type']);
        return '<div class="alert alert-' . e($type) . '">' . e($msg) . '</div>';
    }
    return '';
}

function setFlash(string $msg, string $type = 'success', string $key = 'flash'): void {
    $_SESSION[$key]            = $msg;
    $_SESSION[$key . '_type']  = $type;
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function getDashboardUrl(): string {
    $role = $_SESSION['user_role'] ?? '';
    return match($role) {
        'admin'           => BASE_URL . '/dashboard.php',
        'dept_manager'    => BASE_URL . '/dashboard.php',
        'project_manager' => BASE_URL . '/dashboard.php',
        'employee'        => BASE_URL . '/dashboard.php',
        'personal'        => BASE_URL . '/personal/dashboard.php',
        default           => BASE_URL . '/auth/login.php',
    };
}

function paginationLinks(int $total, int $page, int $perPage, string $baseUrl): string {
    $pages = (int) ceil($total / $perPage);
    if ($pages <= 1) return '';
    $html = '<div class="pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        $active = ($i === $page) ? ' active' : '';
        $html  .= '<a href="' . e($baseUrl) . '&page=' . $i . '" class="page-link' . $active . '">' . $i . '</a>';
    }
    $html .= '</div>';
    return $html;
}

function sanitizeFloat(mixed $val): float {
    return max(0, (float) filter_var($val, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
}

function isValidMoney(mixed $val, bool $allowZero = false): bool {
    if (!is_string($val) && !is_int($val) && !is_float($val)) {
        return false;
    }

    $value = trim((string)$val);
    if (!preg_match('/^\d{1,13}(?:\.\d{1,2})?$/D', $value)) {
        return false;
    }

    return $allowZero ? (float)$value >= 0 : (float)$value > 0;
}

function sanitizeInt(mixed $val): int {
    return max(0, (int) filter_var($val, FILTER_SANITIZE_NUMBER_INT));
}

function isValidDate(string $date): bool {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

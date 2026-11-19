<?php
declare(strict_types=1);

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    global $config;
    $idleMinutes = (int)($config['security']['session_idle_minutes'] ?? 120);
    $last = (int)($_SESSION['last_activity'] ?? time());
    if (time() - $last > $idleMinutes * 60) {
        session_unset();
        session_destroy();
        return null;
    }
    $_SESSION['last_activity'] = time();

    $stmt = db()->prepare('SELECT u.id, u.employee_id, u.username, u.role, u.status, e.nik, e.full_name, e.position_name, e.department_name FROM users u LEFT JOIN employees e ON e.id=u.employee_id WHERE u.id=? LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || $user['status'] !== 'active') {
        session_unset();
        session_destroy();
        return null;
    }
    return $user;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        json_response(['ok' => false, 'error' => 'unauthorized', 'message' => 'Silakan login kembali.'], 401);
    }
    return $user;
}

function require_roles(array $roles): array
{
    $user = require_auth();
    if (!in_array($user['role'], $roles, true)) {
        security_event((int)$user['id'], $user['employee_id'] ? (int)$user['employee_id'] : null, 'access_denied', 'warning', 'Akses ditolak ke ' . route_path());
        json_response(['ok' => false, 'error' => 'forbidden', 'message' => 'Anda tidak memiliki hak akses.'], 403);
    }
    return $user;
}

function public_user(array $user): array
{
    $permissions = [];
    if (function_exists('sigap_get_user_permissions')) {
        try { $permissions = sigap_get_user_permissions($user); } catch (Throwable $e) { $permissions = []; }
    }
    return [
        'id' => (int)$user['id'],
        'employee_id' => $user['employee_id'] === null ? null : (int)$user['employee_id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'nik' => $user['nik'] ?? null,
        'full_name' => $user['full_name'] ?? null,
        'position_name' => $user['position_name'] ?? null,
        'department_name' => $user['department_name'] ?? null,
        'menu_permissions' => $permissions,
        'permissions' => $permissions,
    ];
}

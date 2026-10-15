<?php
/**
 * Authentication & Strict Multi-Role Access Control (RBAC)
 * 5 Roles: Owner, Admin, Karyawan (In-House Staff), Freelancer (External Talent), Client (Client Portal)
 */

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/database.php';

function release_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

function is_logged_in(): bool {
    return !empty($_SESSION['logged_in']) && !empty($_SESSION['user']);
}

function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return $_SESSION['user'] ?? null;
}

function current_user_role(): string {
    return $_SESSION['user']['role'] ?? '';
}

function current_user_client_id(): ?int {
    return !empty($_SESSION['user']['client_id']) ? (int)$_SESSION['user']['client_id'] : null;
}

function is_owner(): bool {
    return current_user_role() === 'owner';
}

function is_admin(): bool {
    $role = current_user_role();
    return ($role === 'owner' || $role === 'admin');
}

function is_karyawan(): bool {
    return current_user_role() === 'karyawan';
}

function is_freelancer(): bool {
    return current_user_role() === 'freelancer';
}

function is_worker(): bool {
    $role = current_user_role();
    return ($role === 'karyawan' || $role === 'freelancer' || $role === 'admin' || $role === 'owner');
}

function is_client(): bool {
    return current_user_role() === 'client';
}

function home_url_for_role(?string $role = null): string {
    if ($role === null) {
        $role = current_user_role();
    }
    switch ($role) {
        case 'owner':
            return url('owner-dashboard');
        case 'admin':
            return url('admin-dashboard');
        case 'karyawan':
        case 'freelancer':
            return url('tasks');
        case 'client':
            return url('client-portal');
        default:
            return url('login');
    }
}

function current_portal(): string {
    $role = current_user_role();
    if ($role === 'owner') return 'owner';
    if ($role === 'admin') return 'admin';
    if ($role === 'client') return 'client';
    return 'worker';
}

function require_auth() {
    if (!is_logged_in()) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $isApi = (strpos($uri, '/api/') !== false) ||
                 (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                 (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Sesi berakhir atau belum login. Silakan login kembali.', 'redirect' => url('login')]);
            exit;
        }
        header('Location: ' . url('login'));
        exit;
    }
}

function require_owner() {
    require_auth();
    if (!is_owner()) {
        header('Location: ' . home_url_for_role());
        exit;
    }
}

function require_admin() {
    require_auth();
    if (!is_admin()) {
        header('Location: ' . home_url_for_role());
        exit;
    }
}

function require_worker() {
    require_auth();
    if (!is_worker()) {
        header('Location: ' . home_url_for_role());
        exit;
    }
}

function require_client() {
    require_auth();
    if (!is_client() && !is_admin()) {
        header('Location: ' . home_url_for_role());
        exit;
    }
}

function log_activity($type, $title, $description = null, $amount = null) {
    try {
        $db = Database::getConnection();
        $user = current_user();
        $userId = $user ? $user['id'] : null;
        $stmt = $db->prepare("INSERT INTO activities (user_id, type, title, description, amount) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $type, $title, $description, $amount]);
    } catch (Exception $e) {
        // silent fail
    }
}

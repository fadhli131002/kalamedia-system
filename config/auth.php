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

/**
 * Multi-Slot Session Architecture:
 * - 'kala_internal': For Owner, Admin, Karyawan, Freelancer (Internal Workspace)
 * - 'kala_client_portal': For Clients (Client Portal)
 * Eliminates multi-tab session overwrites so Owner and Client can be logged in concurrently.
 */
function get_auth_context(): string {
    // 1. Explicit query or post context
    if (!empty($_GET['auth_context']) && in_array($_GET['auth_context'], ['internal', 'client'])) {
        return $_GET['auth_context'];
    }
    if (!empty($_POST['auth_context']) && in_array($_POST['auth_context'], ['internal', 'client'])) {
        return $_POST['auth_context'];
    }
    // 2. HTTP header context
    if (!empty($_SERVER['HTTP_X_AUTH_CONTEXT']) && in_array($_SERVER['HTTP_X_AUTH_CONTEXT'], ['internal', 'client'])) {
        return $_SERVER['HTTP_X_AUTH_CONTEXT'];
    }

    // 3. HTTP referer check (for fetch/AJAX calls originating from Client Portal)
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (strpos($referer, 'client-portal') !== false) {
        return 'client';
    }

    // 4. Request URI or route check
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, 'client-portal') !== false) {
        return 'client';
    }

    // 5. Global current page router
    $currentPage = $GLOBALS['currentPage'] ?? ($_GET['page'] ?? '');
    if ($currentPage === 'client-portal' || $currentPage === 'client') {
        return 'client';
    }

    return 'internal';
}

function get_session_slot(?string $context = null): string {
    $ctx = $context ?: get_auth_context();
    return ($ctx === 'client') ? 'kala_client_portal' : 'kala_internal';
}

function is_logged_in(?string $context = null): bool {
    $slot = get_session_slot($context);

    // Check specific multi-slot
    if (!empty($_SESSION[$slot]['logged_in']) && !empty($_SESSION[$slot]['user'])) {
        return true;
    }

    // Client Portal Preview Mode: If Owner/Admin is logged in internally, allow viewing Client Portal
    if ($slot === 'kala_client_portal' && !empty($_SESSION['kala_internal']['logged_in']) && !empty($_SESSION['kala_internal']['user'])) {
        $internalRole = $_SESSION['kala_internal']['user']['role'] ?? '';
        if ($internalRole === 'owner' || $internalRole === 'admin') {
            return true;
        }
    }

    // Backward compatibility migration from legacy $_SESSION['user']
    if (!empty($_SESSION['logged_in']) && !empty($_SESSION['user'])) {
        $legacyRole = $_SESSION['user']['role'] ?? '';
        $targetSlot = ($legacyRole === 'client') ? 'kala_client_portal' : 'kala_internal';
        if (empty($_SESSION[$targetSlot])) {
            $_SESSION[$targetSlot] = [
                'logged_in' => true,
                'user' => $_SESSION['user'],
                'last_activity' => time()
            ];
        }
        if ($targetSlot === $slot) {
            return true;
        }
    }

    return false;
}

function current_user(?string $context = null): ?array {
    $slot = get_session_slot($context);

    if (!empty($_SESSION[$slot]['user'])) {
        return $_SESSION[$slot]['user'];
    }

    // Fallback preview for Owner/Admin on Client Portal
    if ($slot === 'kala_client_portal' && !empty($_SESSION['kala_internal']['user'])) {
        $role = $_SESSION['kala_internal']['user']['role'] ?? '';
        if ($role === 'owner' || $role === 'admin') {
            return $_SESSION['kala_internal']['user'];
        }
    }

    // Fallback to legacy
    if (!empty($_SESSION['user'])) {
        return $_SESSION['user'];
    }

    return null;
}

function current_user_role(?string $context = null): string {
    $u = current_user($context);
    return $u['role'] ?? '';
}

function current_user_client_id(?string $context = null): ?int {
    $u = current_user($context);
    return !empty($u['client_id']) ? (int)$u['client_id'] : null;
}

function is_owner(?string $context = null): bool {
    return current_user_role($context) === 'owner';
}

function is_admin(?string $context = null): bool {
    $role = current_user_role($context);
    return ($role === 'owner' || $role === 'admin');
}

function is_karyawan(?string $context = null): bool {
    return current_user_role($context) === 'karyawan';
}

function is_freelancer(?string $context = null): bool {
    return current_user_role($context) === 'freelancer';
}

function is_worker(?string $context = null): bool {
    $role = current_user_role($context);
    return in_array($role, ['karyawan', 'freelancer', 'admin', 'owner']);
}

function is_client(?string $context = null): bool {
    return current_user_role($context) === 'client';
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

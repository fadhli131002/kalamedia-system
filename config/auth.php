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
 * Dual-Session Architecture Based on Context Route & Slot ('internal' vs 'client'):
 * - $_SESSION['kala_auth']['internal']: For Owner, Admin, Karyawan, Freelancer (Internal Workspace)
 * - $_SESSION['kala_auth']['client']: For Clients (Client Portal)
 * Eliminates multi-tab session overwrites so Owner and Client can remain logged in concurrently across tabs.
 */
function resolveActiveSessionContext(): string {
    // 1. If explicit session_name is KALASESS_CLIENT
    if (session_name() === 'KALASESS_CLIENT') {
        $slotParam = $_GET['_slot'] ?? $_POST['_slot'] ?? ($_GET['portal'] ?? ($_POST['portal'] ?? ($_GET['context'] ?? ($_POST['context'] ?? ''))));
        if ($slotParam === 'internal' || isset($_GET['preview'])) {
            return 'internal';
        }
        return 'client';
    }

    if (function_exists('detect_session_context')) {
        return detect_session_context();
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $slotParam = $_GET['_slot'] ?? $_POST['_slot'] ?? ($_GET['auth_context'] ?? ($_POST['auth_context'] ?? ($_SERVER['HTTP_X_KALA_SLOT'] ?? ($_SERVER['HTTP_X_AUTH_CONTEXT'] ?? ''))));

    if ($slotParam === 'client' || $slotParam === 'internal') {
        return $slotParam;
    }

    if (
        $script === 'client_portal.php' ||
        strpos($uri, '/client-portal') !== false ||
        (isset($_GET['portal']) && $_GET['portal'] === 'client') ||
        (isset($_GET['page']) && in_array($_GET['page'], ['client-portal', 'client_portal']))
    ) {
        return 'client';
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (strpos($referer, '/client-portal') !== false || strpos($referer, 'portal=client') !== false || strpos($referer, 'client_portal.php') !== false) {
        return 'client';
    }

    $currentPage = $GLOBALS['currentPage'] ?? ($_GET['page'] ?? '');
    if ($currentPage === 'client-portal' || $currentPage === 'client') {
        return 'client';
    }

    return 'internal';
}

function get_auth_context(): string {
    return resolveActiveSessionContext();
}

function get_session_slot(?string $context = null): string {
    $ctx = $context ?: resolveActiveSessionContext();
    return ($ctx === 'client') ? 'kala_client_portal' : 'kala_internal';
}

function is_logged_in(?string $context = null): bool {
    $ctx = $context ?: resolveActiveSessionContext();

    // 1. Primary storage: $_SESSION['kala_auth'][$ctx]
    if (!empty($_SESSION['kala_auth'][$ctx]['user_id'])) {
        return true;
    }

    // 2. Fallback to generic user session if role matches context
    if (!empty($_SESSION['user']['id']) && !empty($_SESSION['user']['role'])) {
        $uRole = $_SESSION['user']['role'];
        if ($ctx === 'client' && $uRole === 'client') {
            return true;
        }
        if ($ctx === 'internal' && in_array($uRole, ['owner', 'admin', 'karyawan', 'freelancer'])) {
            return true;
        }
    }

    // 3. Legacy multi-slot: $_SESSION['kala_internal'] or $_SESSION['kala_client_portal']
    $legacySlot = ($ctx === 'client') ? 'kala_client_portal' : 'kala_internal';
    if (!empty($_SESSION[$legacySlot]['logged_in']) && !empty($_SESSION[$legacySlot]['user'])) {
        return true;
    }

    return false;
}

function current_user(?string $context = null): ?array {
    $ctx = $context ?: resolveActiveSessionContext();

    // 1. Primary storage: $_SESSION['kala_auth'][$ctx]
    if (!empty($_SESSION['kala_auth'][$ctx]['user_id'])) {
        $data = $_SESSION['kala_auth'][$ctx];
        return [
            'id' => (int)$data['user_id'],
            'name' => $data['name'] ?? '',
            'email' => $data['email'] ?? '',
            'role' => $data['role'] ?? '',
            'employment_type' => $data['employment_type'] ?? ($ctx === 'client' ? 'client' : 'internal'),
            'phone_wa' => $data['phone_wa'] ?? null,
            'client_id' => !empty($data['client_id']) ? (int)$data['client_id'] : null,
            'avatar' => $data['avatar'] ?? null,
        ];
    }

    // 2. Generic user if role matches
    if (!empty($_SESSION['user']['id']) && !empty($_SESSION['user']['role'])) {
        $u = $_SESSION['user'];
        $uRole = $u['role'] ?? '';
        if (($ctx === 'client' && $uRole === 'client') || ($ctx === 'internal' && $uRole !== 'client')) {
            return [
                'id' => (int)$u['id'],
                'name' => $u['name'] ?? '',
                'email' => $u['email'] ?? '',
                'role' => $u['role'] ?? '',
                'employment_type' => $u['employment_type'] ?? ($ctx === 'client' ? 'client' : 'internal'),
                'phone_wa' => $u['phone_wa'] ?? null,
                'client_id' => !empty($u['client_id']) ? (int)$u['client_id'] : null,
                'avatar' => $u['avatar'] ?? null,
            ];
        }
    }

    // 3. Legacy multi-slot storage
    $legacySlot = ($ctx === 'client') ? 'kala_client_portal' : 'kala_internal';
    if (!empty($_SESSION[$legacySlot]['user'])) {
        $u = $_SESSION[$legacySlot]['user'];
        $uRole = $u['role'] ?? '';
        if (($ctx === 'client' && $uRole === 'client') || ($ctx === 'internal' && $uRole !== 'client')) {
            return $u;
        }
    }

    return null;
}

function current_user_id(?string $context = null): ?int {
    $u = current_user($context);
    return !empty($u['id']) ? (int)$u['id'] : null;
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
    $ctx = $context ?: resolveActiveSessionContext();
    return current_user_role($ctx) === 'owner';
}

function is_admin(?string $context = null): bool {
    $ctx = $context ?: resolveActiveSessionContext();
    $role = current_user_role($ctx);
    return ($role === 'owner' || $role === 'admin');
}

function is_karyawan(?string $context = null): bool {
    $ctx = $context ?: resolveActiveSessionContext();
    return current_user_role($ctx) === 'karyawan';
}

function is_freelancer(?string $context = null): bool {
    $ctx = $context ?: resolveActiveSessionContext();
    return current_user_role($ctx) === 'freelancer';
}

function is_worker(?string $context = null): bool {
    $ctx = $context ?: resolveActiveSessionContext();
    $role = current_user_role($ctx);
    return in_array($role, ['karyawan', 'freelancer', 'admin', 'owner']);
}

function is_client(?string $context = null): bool {
    $ctx = $context ?: resolveActiveSessionContext();
    return current_user_role($ctx) === 'client';
}

function home_url_for_role(?string $roleOrContext = null): string {
    $role = $roleOrContext;
    if ($role === null || $role === 'internal' || $role === 'client') {
        $role = current_user_role($role);
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
    $ctx = resolveActiveSessionContext();
    if ($ctx === 'client') return 'client';
    $role = current_user_role('internal');
    if ($role === 'owner') return 'owner';
    if ($role === 'admin') return 'admin';
    return 'worker';
}

function require_auth(?string $context = null) {
    if (!is_logged_in($context)) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $isApi = (strpos($uri, '/api/') !== false) ||
                 (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                 (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        $loginUrl = url('login') . ($context === 'client' ? '?context=client' : '');
        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Sesi berakhir atau belum login. Silakan login kembali.', 'redirect' => $loginUrl]);
            exit;
        }
        header('Location: ' . $loginUrl);
        exit;
    }
}

function require_owner() {
    require_auth('internal');
    if (!is_owner('internal')) {
        header('Location: ' . home_url_for_role(current_user_role('internal')));
        exit;
    }
}

function require_admin() {
    require_auth('internal');
    if (!is_admin('internal')) {
        header('Location: ' . home_url_for_role(current_user_role('internal')));
        exit;
    }
}

function require_worker() {
    require_auth('internal');
    if (!is_worker('internal')) {
        header('Location: ' . home_url_for_role(current_user_role('internal')));
        exit;
    }
}

function require_client() {
    $ctx = resolveActiveSessionContext();
    if ($ctx === 'internal') {
        require_auth('internal');
        if (!is_admin('internal')) {
            header('Location: ' . home_url_for_role(current_user_role('internal')));
            exit;
        }
    } else {
        if (!is_logged_in('client') && is_admin('internal')) {
            return;
        }
        require_auth('client');
        if (!is_client('client') && !is_admin('internal')) {
            header('Location: ' . url('login?context=client'));
            exit;
        }
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

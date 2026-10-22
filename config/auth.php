<?php
/**
 * Authentication & Strict Multi-Role Access Control (RBAC)
 * Anti-Collision Multi-Tab Architecture (?_u=u_<id> + sessionStorage + $_SESSION['kala_accounts'])
 * 5 Roles: Owner, Admin, Karyawan, Freelancer, Client
 */

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/database.php';

function release_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

/**
 * Check if the current request is for a Client Portal route
 */
function is_client_portal_route(): bool {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $page = $_GET['page'] ?? ($GLOBALS['currentPage'] ?? '');
    $slotParam = $_GET['_slot'] ?? $_POST['_slot'] ?? ($_GET['context'] ?? ($_POST['context'] ?? ($_GET['portal'] ?? '')));

    if ($slotParam === 'client') return true;
    if ($slotParam === 'internal') return false;

    if ($script === 'client_portal.php') return true;
    if (in_array($page, ['client-portal', 'client_portal', 'client'])) return true;
    if (strpos($uri, '/client-portal') !== false || strpos($uri, 'portal=client') !== false || strpos($uri, 'context=client') !== false) {
        return true;
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (strpos($uri, '/api/') !== false && (strpos($referer, '/client-portal') !== false || strpos($referer, 'portal=client') !== false)) {
        return true;
    }

    return false;
}

/**
 * Resolves the active user account for the current request / tab
 * 1. Checks ?_u= or POST _u or HTTP_X_KALA_UID
 * 2. Checks $_SESSION['kala_accounts'][$uid_key]
 * 3. Fallback route:
 *    - Route /client-portal: STRICTLY searches for a 'client' account in $_SESSION['kala_accounts']
 *    - Route internal: STRICTLY searches for 'owner'/'admin'/'karyawan'/'freelancer' and FORBIDS taking 'client'
 */
function resolve_active_account(): ?array {
    if (isset($GLOBALS['__ACTIVE_RESOLVED_ACCOUNT__'])) {
        return $GLOBALS['__ACTIVE_RESOLVED_ACCOUNT__'];
    }

    if (!isset($_SESSION['kala_accounts']) || !is_array($_SESSION['kala_accounts'])) {
        $_SESSION['kala_accounts'] = [];
    }

    // 1. Read UID token from request (?_u=, POST _u, or Header X-Kala-Uid)
    $uidKey = $_GET['_u'] ?? $_POST['_u'] ?? ($_SERVER['HTTP_X_KALA_UID'] ?? '');
    if (!empty($uidKey) && is_numeric($uidKey)) {
        $uidKey = 'u_' . $uidKey;
    }

    $selectedAccount = null;

    // Direct token lookup
    if (!empty($uidKey) && isset($_SESSION['kala_accounts'][$uidKey])) {
        $selectedAccount = $_SESSION['kala_accounts'][$uidKey];
    }

    // 2. If token is missing or not found in accounts, determine from route
    if (!$selectedAccount && !empty($_SESSION['kala_accounts'])) {
        $isClientRoute = is_client_portal_route();
        if ($isClientRoute) {
            // Find client account
            foreach (array_reverse($_SESSION['kala_accounts']) as $acc) {
                if (($acc['role'] ?? '') === 'client') {
                    $selectedAccount = $acc;
                    break;
                }
            }
        } else {
            // Internal Route: WAJIB HANYA mencari owner/admin/karyawan/freelancer! DILARANG mengambil client!
            foreach (array_reverse($_SESSION['kala_accounts']) as $acc) {
                if (in_array($acc['role'] ?? '', ['owner', 'admin', 'karyawan', 'freelancer'])) {
                    $selectedAccount = $acc;
                    break;
                }
            }
        }
    }

    // 3. Fallback for legacy session structures (if kala_accounts was not yet populated)
    if (!$selectedAccount) {
        if (!empty($_SESSION['user']['id']) && !empty($_SESSION['user']['role'])) {
            $uRole = $_SESSION['user']['role'];
            $isClientRoute = is_client_portal_route();
            if (($isClientRoute && $uRole === 'client') || (!$isClientRoute && $uRole !== 'client')) {
                $uKey = 'u_' . (int)$_SESSION['user']['id'];
                $selectedAccount = [
                    'user_id'         => (int)$_SESSION['user']['id'],
                    'id'              => (int)$_SESSION['user']['id'],
                    'name'            => $_SESSION['user']['name'] ?? '',
                    'email'           => $_SESSION['user']['email'] ?? '',
                    'role'            => $_SESSION['user']['role'] ?? '',
                    'employment_type' => $_SESSION['user']['employment_type'] ?? ($uRole === 'client' ? 'client' : 'internal'),
                    'phone_wa'        => $_SESSION['user']['phone_wa'] ?? null,
                    'client_id'       => !empty($_SESSION['user']['client_id']) ? (int)$_SESSION['user']['client_id'] : null,
                    'avatar'          => $_SESSION['user']['avatar'] ?? null,
                ];
                $_SESSION['kala_accounts'][$uKey] = $selectedAccount;
            }
        }
    }

    // If active account determined, normalize and synchronize for this request
    if ($selectedAccount) {
        $normalized = [
            'id'              => (int)($selectedAccount['id'] ?? $selectedAccount['user_id']),
            'user_id'         => (int)($selectedAccount['id'] ?? $selectedAccount['user_id']),
            'name'            => $selectedAccount['name'] ?? '',
            'email'           => $selectedAccount['email'] ?? '',
            'role'            => $selectedAccount['role'] ?? '',
            'employment_type' => $selectedAccount['employment_type'] ?? ($selectedAccount['role'] === 'client' ? 'client' : 'internal'),
            'phone_wa'        => $selectedAccount['phone_wa'] ?? null,
            'client_id'       => !empty($selectedAccount['client_id']) ? (int)$selectedAccount['client_id'] : null,
            'avatar'          => $selectedAccount['avatar'] ?? null,
        ];

        // Sync to legacy session fields so external templates don't break
        $_SESSION['user_id'] = $normalized['id'];
        $_SESSION['role'] = $normalized['role'];
        $_SESSION['client_id'] = $normalized['client_id'];
        $_SESSION['user'] = $normalized;

        $GLOBALS['__ACTIVE_RESOLVED_ACCOUNT__'] = $normalized;
        return $normalized;
    }

    $GLOBALS['__ACTIVE_RESOLVED_ACCOUNT__'] = null;
    return null;
}

function current_tab_uid(): string {
    $u = resolve_active_account();
    return $u ? ('u_' . $u['id']) : '';
}

function render_tab_uid_script(): void {
    if (function_exists('render_pwa_meta_tags')) {
        render_pwa_meta_tags();
    }
    $currentUid = current_tab_uid();
    ?>
    <script>
      (function() {
        var serverUid = '<?= htmlspecialchars($currentUid, ENT_QUOTES, 'UTF-8') ?>';
        var urlParams = new URLSearchParams(window.location.search);
        var urlUid = urlParams.get('_u');
        if (urlUid) {
          sessionStorage.setItem('kala_tab_uid', urlUid);
        } else if (serverUid) {
          sessionStorage.setItem('kala_tab_uid', serverUid);
          if (!window.location.pathname.endsWith('/login') && !window.location.pathname.endsWith('/logout')) {
            var sep = window.location.search ? '&' : '?';
            window.history.replaceState(null, '', window.location.pathname + window.location.search + sep + '_u=' + encodeURIComponent(serverUid) + window.location.hash);
          }
        } else {
          var stored = sessionStorage.getItem('kala_tab_uid');
          if (stored && !window.location.pathname.endsWith('/login') && !window.location.pathname.endsWith('/logout')) {
            var sep = window.location.search ? '&' : '?';
            window.history.replaceState(null, '', window.location.pathname + window.location.search + sep + '_u=' + encodeURIComponent(stored) + window.location.hash);
          }
        }
      })();
    </script>
    <?php
}

function resolveActiveSessionContext(): string {
    return is_client_portal_route() ? 'client' : 'internal';
}

function get_auth_context(): string {
    return resolveActiveSessionContext();
}

function is_logged_in(?string $context = null): bool {
    $u = resolve_active_account();
    if (!$u) return false;
    if ($context === 'client') {
        return ($u['role'] === 'client');
    }
    if ($context === 'internal') {
        return in_array($u['role'], ['owner', 'admin', 'karyawan', 'freelancer']);
    }
    return true;
}

function current_user(?string $context = null): ?array {
    $u = resolve_active_account();
    if (!$u) return null;
    if ($context === 'client' && $u['role'] !== 'client') {
        return null;
    }
    if ($context === 'internal' && $u['role'] === 'client') {
        return null;
    }
    return $u;
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
            return url('client-portal?tab=deliverables');
        default:
            return url('login', false);
    }
}

function current_portal(): string {
    if (is_client_portal_route() || is_client()) return 'client';
    $role = current_user_role();
    if ($role === 'owner') return 'owner';
    if ($role === 'admin') return 'admin';
    return 'worker';
}

function check_and_expire_client_contracts(?PDO $db = null): void {
    try {
        if (!$db) {
            $db = Database::getConnection();
        }
        $today = date('Y-m-d');
        $stmt = $db->prepare("
            SELECT id FROM clients 
            WHERE contract_end_date IS NOT NULL 
              AND contract_end_date < ? 
              AND contract_status != 'expired' 
              AND COALESCE(is_deleted, 0) = 0
        ");
        $stmt->execute([$today]);
        $expiredClientIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($expiredClientIds)) {
            $inClause = implode(',', array_map('intval', $expiredClientIds));
            $db->exec("UPDATE clients SET contract_status = 'expired', status = 'expired' WHERE id IN ($inClause)");
            $db->exec("UPDATE users SET is_active = 0 WHERE role = 'client' AND client_id IN ($inClause)");
        }
    } catch (Throwable $e) {}
}

function require_auth(?string $context = null) {
    check_and_expire_client_contracts();
    $targetContext = $context ?: (is_client_portal_route() ? 'client' : 'internal');
    $isLoggedIn = is_logged_in($targetContext);

    if (!$isLoggedIn) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $isApi = (strpos($uri, '/api/') !== false) ||
                 (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                 (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        $loginUrl = url('login', false) . ($targetContext === 'client' ? '?context=client' : '');
        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Sesi berakhir atau belum login. Silakan login kembali.', 'redirect' => $loginUrl]);
            exit;
        }
        header('Location: ' . $loginUrl);
        exit;
    }

    $u = current_user();
    // Prevent client role from accessing internal workspace
    if ($targetContext === 'internal' && ($u['role'] ?? '') === 'client') {
        header('Location: ' . url('client-portal?tab=deliverables'));
        exit;
    }
}

function require_owner() {
    require_auth('internal');
    if (!is_owner()) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $isApi = (strpos($uri, '/api/') !== false) ||
                 (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                 (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya Owner yang berwenang.']);
            exit;
        }
        header('Location: ' . home_url_for_role());
        exit;
    }
}

function require_admin() {
    require_auth('internal');
    if (!is_admin()) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $isApi = (strpos($uri, '/api/') !== false) ||
                 (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                 (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya Admin / Owner yang berwenang.']);
            exit;
        }
        header('Location: ' . home_url_for_role());
        exit;
    }
}

function require_worker() {
    require_auth('internal');
    if (!is_worker()) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $isApi = (strpos($uri, '/api/') !== false) ||
                 (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                 (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya Anggota Tim (Karyawan / Freelancer) yang berwenang.']);
            exit;
        }
        header('Location: ' . home_url_for_role());
        exit;
    }
}

function require_client() {
    check_and_expire_client_contracts();
    $u = resolve_active_account();
    // Allow admin/owner to preview client portal
    if ($u && ($u['role'] === 'owner' || $u['role'] === 'admin')) {
        return;
    }
    if (!$u || $u['role'] !== 'client') {
        $loginUrl = url('login', false) . '?context=client';
        header('Location: ' . $loginUrl);
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

<?php
/**
 * Kalamedia Agency Financial & Project Management System
 * Core Application Configuration
 */

if (!function_exists('detect_session_context')) {
    function detect_session_context(): string {
        if (!empty($GLOBALS['__ACTIVE_SESSION_CONTEXT_OVERRIDE__'])) {
            return $GLOBALS['__ACTIVE_SESSION_CONTEXT_OVERRIDE__'];
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $slotParam = $_GET['_slot'] ?? $_POST['_slot'] ?? $_GET['portal'] ?? $_POST['portal'] ?? $_GET['context'] ?? $_POST['context'] ?? $_POST['auth_context'] ?? $_GET['auth_context'] ?? $_SERVER['HTTP_X_KALA_SLOT'] ?? $_SERVER['HTTP_X_AUTH_CONTEXT'] ?? '';
        $page = $_GET['page'] ?? '';
        $roleParam = $_POST['role'] ?? ($_GET['role'] ?? '');
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $isPreview = isset($_GET['preview']) || isset($_POST['preview']) || (isset($_GET['mode']) && $_GET['mode'] === 'preview');

        if ($slotParam === 'internal' || $isPreview) {
            return 'internal';
        }
        if ($slotParam === 'client') {
            return 'client';
        }

        $isClientRoute = (
            $script === 'client_portal.php' ||
            strpos($uri, '/client-portal') !== false ||
            strpos($uri, 'portal=client') !== false ||
            strpos($uri, 'context=client') !== false ||
            strpos($uri, '_slot=client') !== false ||
            in_array($page, ['client-portal', 'client_portal', 'client']) ||
            $roleParam === 'client'
        );

        if ($isClientRoute) {
            // If only internal session exists, keep internal session for admin preview
            if (isset($_COOKIE['KALASESS_INTERNAL']) && !isset($_COOKIE['KALASESS_CLIENT'])) {
                return 'internal';
            }
            return 'client';
        }

        if (
            strpos($referer, '/client-portal') !== false ||
            strpos($referer, 'portal=client') !== false ||
            strpos($referer, 'context=client') !== false ||
            strpos($referer, 'client_portal.php') !== false
        ) {
            if (isset($_COOKIE['KALASESS_INTERNAL']) && !isset($_COOKIE['KALASESS_CLIENT'])) {
                return 'internal';
            }
            return 'client';
        }

        return 'internal';
    }
}

if (!function_exists('switch_session_context')) {
    function switch_session_context(string $context): void {
        $GLOBALS['__ACTIVE_SESSION_CONTEXT_OVERRIDE__'] = $context;
        $targetName = ($context === 'client') ? 'KALASESS_CLIENT' : 'KALASESS_INTERNAL';
        if (headers_sent()) {
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (session_name() === $targetName) {
                return;
            }
            session_write_close();
        }
        ini_set('session.gc_maxlifetime', 7200);
        ini_set('session.cookie_lifetime', 7200);
        session_name($targetName);
        session_start();
    }
}

if (session_status() === PHP_SESSION_NONE) {
    // 2-hour session lifetime (7200 seconds)
    ini_set('session.gc_maxlifetime', 7200);
    ini_set('session.cookie_lifetime', 7200);
    // Single unified session cookie for all tabs to enable Google-style multi-account isolation
    session_name('KALASESS');
    session_start();
}

// Timezone
date_default_timezone_set('Asia/Jakarta');

// App Info
define('APP_NAME', 'PT Kala Kausa Prima');
define('AGENCY_NAME', 'PT Kala Kausa Prima');
define('AGENCY_EMAIL', 'support@kalamediacreative.com');
define('AGENCY_WEBSITE', 'kalamediacreative.com');
define('AGENCY_INSTAGRAM', '@kalamedia.creative');
define('AGENCY_TAGLINE', 'Built for What’s Next');
define('AGENCY_PHONE', '+62 812-3456-7890');
define('AGENCY_ADDRESS', "Jl. Padat Karya, Cisauk Girang, RT 001/RW 005,\nKel. Cisauk, Tangerang,\nBanten 15341");
define('AGENCY_BANK_NAME', 'Mandiri');
define('AGENCY_BANK_ACCOUNT', '1640010196501');
define('AGENCY_BANK_HOLDER', 'Kala Kausa Prima');
define('AGENCY_LOGO', 'assets/img/Logo-1.webp');

// Paths
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_DIR', BASE_PATH . '/assets/uploads/receipts');
define('UPLOAD_URL', 'assets/uploads/receipts');

// Webhook Configuration (Make.com, Zapier, n8n, Google Calendar Integration)
define('GCAL_WEBHOOK_URL', 'https://hook.eu1.make.com/0n6j7k79y4kh9cb39pf1i6njb27n09h3');

// Session Activity Timeout (2 hours = 7200s)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    session_unset();
    session_destroy();
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
        exit;
    }
    header('Location: ' . get_base_url() . '/login?expired=1');
    exit;
}
$_SESSION['last_activity'] = time();

// Helper Functions
function get_base_url() {
    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script_dir === '/' || $script_dir === '\\') {
        $script_dir = '';
    }
    // Remove /api or /scratch subfolder if current script is inside api or scratch folder
    $script_dir = preg_replace('#/(api|scratch)$#i', '', $script_dir);
    return rtrim($script_dir, '/');
}

function url($path = '', $preserveUid = true) {
    $base = get_base_url();
    $path = ltrim($path, '/');
    $fullUrl = $base ? $base . '/' . $path : '/' . $path;

    if (!$preserveUid || empty($path)) {
        return $fullUrl;
    }

    // Ignore assets, protocol links, anchors, or javascript:
    if (
        strpos($path, 'assets/') === 0 ||
        strpos($path, 'http://') === 0 ||
        strpos($path, 'https://') === 0 ||
        strpos($path, '#') === 0 ||
        strpos($path, 'javascript:') === 0
    ) {
        return $fullUrl;
    }

    // If _u already present in path, don't append again
    if (strpos($path, '_u=') !== false) {
        return $fullUrl;
    }

    $tabUid = '';
    if (function_exists('current_tab_uid')) {
        $tabUid = current_tab_uid();
    } elseif (!empty($_GET['_u'])) {
        $tabUid = trim($_GET['_u']);
    }

    if (!empty($tabUid)) {
        $sep = (strpos($fullUrl, '?') !== false) ? '&' : '?';
        return $fullUrl . $sep . '_u=' . urlencode($tabUid);
    }

    return $fullUrl;
}

function format_rupiah($number, $with_prefix = true) {
    $val = number_format(floatval($number), 0, ',', '.');
    return $with_prefix ? 'Rp ' . $val : $val;
}

function format_date($date_str, $with_time = false) {
    if (!$date_str) return '-';
    $timestamp = strtotime($date_str);
    if (!$timestamp) return $date_str;
    
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $d = date('d', $timestamp);
    $m = $months[(int)date('m', $timestamp)];
    $y = date('Y', $timestamp);
    
    if ($with_time) {
        return "$d $m $y, " . date('H:i', $timestamp) . " WIB";
    }
    return "$d $m $y";
}

function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function flash_error(string $message): void {
    set_flash('danger', $message);
}

function flash_success(string $message): void {
    set_flash('success', $message);
}

function flash_warning(string $message): void {
    set_flash('warning', $message);
}

function flash_info(string $message): void {
    set_flash('info', $message);
}

function render_pwa_meta_tags(): void {
    ?>
    <!-- PWA Settings & Portal Kala Mobile Capabilities -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2563EB">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Portal Kala">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Portal Kala">
    <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="assets/img/icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="assets/img/icon-512.png">
    <?php
}

/**
 * Smart Role & Specialization Detection: Tim Ads / Spesialis Iklan
 * Checks service_specialty & position for 'Ads' or 'Iklan'
 */
function is_ads_specialist(?array $user = null): bool {
    $u = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$u) return false;
    $specialty = strtolower($u['service_specialty'] ?? '');
    $position = strtolower($u['position'] ?? '');
    return (strpos($specialty, 'ads') !== false || strpos($specialty, 'iklan') !== false ||
            strpos($position, 'ads') !== false || strpos($position, 'iklan') !== false);
}

/**
 * Smart Role & Specialization Detection: Tim Kreatif (Desain Grafis, Video Editor, Copywriter, Sosmed)
 */
function is_creative_specialist(?array $user = null): bool {
    $u = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$u) return false;
    $specialty = strtolower($u['service_specialty'] ?? '');
    $position = strtolower($u['position'] ?? '');
    return (strpos($specialty, 'desain') !== false || strpos($specialty, 'design') !== false ||
            strpos($specialty, 'video') !== false || strpos($specialty, 'editor') !== false ||
            strpos($specialty, 'copywriter') !== false || strpos($specialty, 'sosmed') !== false ||
            strpos($position, 'desain') !== false || strpos($position, 'design') !== false ||
            strpos($position, 'video') !== false || strpos($position, 'editor') !== false ||
            strpos($position, 'copywriter') !== false || strpos($position, 'sosmed') !== false);
}

/**
 * Checks if a project or service is strictly 'Desain Eceran / Satuan' (without Kelola Sosmed)
 */
function is_pure_eceran_service(?string $serviceType, ?string $packageName = null): bool {
    $st = strtolower($serviceType ?? '');
    $pkg = strtolower($packageName ?? '');
    $isEceran = (strpos($st, 'eceran') !== false || strpos($st, 'satuan') !== false || strpos($st, '1-an') !== false ||
                 strpos($pkg, 'eceran') !== false || strpos($pkg, 'satuan') !== false || strpos($pkg, '1-an') !== false);
    $hasSosmed = (strpos($st, 'sosmed') !== false || strpos($pkg, 'sosmed') !== false || strpos($pkg, 'social media') !== false);
    return ($isEceran && !$hasSosmed);
}

/**
 * Checks if a client has any active 'Kelola Sosmed' project or service
 */
function client_has_sosmed_service(int $clientId): bool {
    if ($clientId <= 0) return false;
    try {
        $db = Database::getConnection();
        $stmtC = $db->prepare("SELECT service_type, package_name FROM clients WHERE id = ?");
        $stmtC->execute([$clientId]);
        $c = $stmtC->fetch(PDO::FETCH_ASSOC);
        if ($c) {
            $cType = strtolower($c['service_type'] ?? '');
            $cPkg = strtolower($c['package_name'] ?? '');
            if (strpos($cType, 'sosmed') !== false || strpos($cPkg, 'sosmed') !== false || strpos($cPkg, 'social media') !== false) {
                return true;
            }
        }
        $stmtP = $db->prepare("SELECT service_type FROM projects WHERE client_id = ? AND COALESCE(is_deleted,0)=0");
        $stmtP->execute([$clientId]);
        $projs = $stmtP->fetchAll(PDO::FETCH_COLUMN);
        foreach ($projs as $p) {
            if (strpos(strtolower($p ?: ''), 'sosmed') !== false) {
                return true;
            }
        }
    } catch (Throwable $e) {}
    return false;
}

/**
 * Checks if a worker/freelancer has any 'Kelola Sosmed' project assigned
 */
function worker_has_sosmed_projects(int $userId): bool {
    if ($userId <= 0) return false;
    try {
        $db = Database::getConnection();
        $stmtU = $db->prepare("SELECT name, role, service_specialty, position FROM users WHERE id = ?");
        $stmtU->execute([$userId]);
        $u = $stmtU->fetch(PDO::FETCH_ASSOC);
        if (!$u) return false;
        
        $uName = $u['name'] ?? '';
        $stmt = $db->prepare("
            SELECT DISTINCT p.service_type, c.service_type as client_service_type 
            FROM projects p
            JOIN clients c ON p.client_id = c.id
            WHERE COALESCE(p.is_deleted,0)=0
              AND (
                p.id IN (SELECT DISTINCT project_id FROM tasks WHERE assigned_to_user_id = ? AND COALESCE(is_deleted,0)=0)
                OR p.client_id IN (SELECT client_id FROM employee_clients WHERE user_id = ?)
                OR p.id IN (SELECT DISTINCT project_id FROM freelancer_payouts WHERE (freelancer_name = ? OR freelancer_name LIKE ?) AND COALESCE(is_deleted,0)=0)
              )
        ");
        $stmt->execute([$userId, $userId, $uName, '%' . $uName . '%']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            $spec = strtolower($u['service_specialty'] ?? '');
            $pos = strtolower($u['position'] ?? '');
            return (strpos($spec, 'sosmed') !== false || strpos($pos, 'sosmed') !== false);
        }
        foreach ($rows as $r) {
            $pType = strtolower($r['service_type'] ?? '');
            $cType = strtolower($r['client_service_type'] ?? '');
            if (strpos($pType, 'sosmed') !== false || strpos($cType, 'sosmed') !== false) {
                return true;
            }
        }
    } catch (Throwable $e) {}
    return false;
}



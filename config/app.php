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

        if ($slotParam === 'client') {
            return 'client';
        }
        if ($slotParam === 'internal') {
            return 'internal';
        }

        if (
            $script === 'client_portal.php' ||
            strpos($uri, '/client-portal') !== false ||
            strpos($uri, 'portal=client') !== false ||
            strpos($uri, 'context=client') !== false ||
            strpos($uri, '_slot=client') !== false
        ) {
            return 'client';
        }

        if (in_array($page, ['client-portal', 'client_portal', 'client'])) {
            return 'client';
        }

        if ($roleParam === 'client') {
            return 'client';
        }

        if (
            strpos($referer, '/client-portal') !== false ||
            strpos($referer, 'portal=client') !== false ||
            strpos($referer, 'context=client') !== false ||
            strpos($referer, 'client_portal.php') !== false
        ) {
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
    $activeCtx = detect_session_context();
    session_name($activeCtx === 'client' ? 'KALASESS_CLIENT' : 'KALASESS_INTERNAL');
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

function url($path = '') {
    $base = get_base_url();
    $path = ltrim($path, '/');
    return $base ? $base . '/' . $path : '/' . $path;
}

function format_rupiah($number, $with_prefix = true) {
    $val = number_format(floatval($number), 0, ',', '.');
    return $with_prefix ? 'Rp' . $val : $val;
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

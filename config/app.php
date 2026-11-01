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
define('APP_VERSION', 'v3.0');
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

if (!defined('BASE_URL')) {
    define('BASE_URL', get_base_url());
}

if (!function_exists('e')) {
    function e($str): string {
        return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
    }
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
    <meta name="theme-color" content="#121316">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Portal Kala">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Portal Kala">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicon-16x16.png">
    <link rel="shortcut icon" href="favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/img/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="assets/img/icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="assets/img/icon-512.png">
    <?php
}

/**
 * Smart Role & Specialization Detection: Tim Ads / Spesialis Iklan
 * Checks service_specialty, position, and jobdesk for 'Ads' or 'Iklan'
 */
function is_ads_specialist(?array $user = null): bool {
    $u = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$u || empty($u['id'])) return false;

    $specialty = strtolower($u['service_specialty'] ?? '');
    $position = strtolower($u['position'] ?? '');
    $jobdesk = strtolower($u['jobdesk'] ?? '');

    // Fallback: If missing from array or session, fetch directly from database to ensure 100% accuracy
    if (empty($specialty) && empty($position) && empty($jobdesk)) {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT service_specialty, position, jobdesk FROM users WHERE id = ?");
            $stmt->execute([(int)$u['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $specialty = strtolower($row['service_specialty'] ?? '');
                $position = strtolower($row['position'] ?? '');
                $jobdesk = strtolower($row['jobdesk'] ?? '');
                if (isset($GLOBALS['__ACTIVE_RESOLVED_ACCOUNT__']) && $GLOBALS['__ACTIVE_RESOLVED_ACCOUNT__']['id'] == $u['id']) {
                    $GLOBALS['__ACTIVE_RESOLVED_ACCOUNT__']['service_specialty'] = $row['service_specialty'];
                    $GLOBALS['__ACTIVE_RESOLVED_ACCOUNT__']['position'] = $row['position'];
                    $GLOBALS['__ACTIVE_RESOLVED_ACCOUNT__']['jobdesk'] = $row['jobdesk'];
                }
            }
        } catch (Throwable $e) {}
    }

    return (
        strpos($specialty, 'ads') !== false || strpos($specialty, 'iklan') !== false ||
        strpos($position, 'ads') !== false || strpos($position, 'iklan') !== false ||
        strpos($jobdesk, 'ads') !== false || strpos($jobdesk, 'iklan') !== false
    );
}

/**
 * Smart Role & Specialization Detection: Tim Kreatif (Desain Grafis, Video Editor, Copywriter, Sosmed)
 */
function is_creative_specialist(?array $user = null): bool {
    $u = $user ?? (function_exists('current_user') ? current_user() : null);
    if (!$u || empty($u['id'])) return false;

    // Strict isolation: Ads specialist is not creative specialist
    if (is_ads_specialist($u)) return false;

    $specialty = strtolower($u['service_specialty'] ?? '');
    $position = strtolower($u['position'] ?? '');
    $jobdesk = strtolower($u['jobdesk'] ?? '');

    if (empty($specialty) && empty($position) && empty($jobdesk)) {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT service_specialty, position, jobdesk FROM users WHERE id = ?");
            $stmt->execute([(int)$u['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $specialty = strtolower($row['service_specialty'] ?? '');
                $position = strtolower($row['position'] ?? '');
                $jobdesk = strtolower($row['jobdesk'] ?? '');
                if (isset($GLOBALS['__ACTIVE_RESOLVED_ACCOUNT__']) && $GLOBALS['__ACTIVE_RESOLVED_ACCOUNT__']['id'] == $u['id']) {
                    $GLOBALS['__ACTIVE_RESOLVED_ACCOUNT__']['service_specialty'] = $row['service_specialty'];
                    $GLOBALS['__ACTIVE_RESOLVED_ACCOUNT__']['position'] = $row['position'];
                    $GLOBALS['__ACTIVE_RESOLVED_ACCOUNT__']['jobdesk'] = $row['jobdesk'];
                }
            }
        } catch (Throwable $e) {}
    }

    return (
        strpos($specialty, 'desain') !== false || strpos($specialty, 'design') !== false ||
        strpos($specialty, 'video') !== false || strpos($specialty, 'editor') !== false ||
        strpos($specialty, 'copywriter') !== false || strpos($specialty, 'sosmed') !== false ||
        strpos($position, 'desain') !== false || strpos($position, 'design') !== false ||
        strpos($position, 'video') !== false || strpos($position, 'editor') !== false ||
        strpos($position, 'copywriter') !== false || strpos($position, 'sosmed') !== false ||
        strpos($jobdesk, 'desain') !== false || strpos($jobdesk, 'design') !== false ||
        strpos($jobdesk, 'video') !== false || strpos($jobdesk, 'editor') !== false ||
        strpos($jobdesk, 'sosmed') !== false
    );
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
 * Strict Rule: Ads specialists NEVER have Sosmed projects
 */
function worker_has_sosmed_projects(int $userId): bool {
    if ($userId <= 0) return false;
    
    // STRICT RULE: If the worker is an Ads Specialist, they do NOT handle Sosmed Content Calendar!
    if (is_ads_specialist(['id' => $userId])) {
        return false;
    }

    try {
        $db = Database::getConnection();
        $stmtU = $db->prepare("SELECT name, role, service_specialty, position, jobdesk FROM users WHERE id = ?");
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

/**
 * Retrieve a setting value from settings / agency_settings / system_meta
 */
function get_agency_setting(string $key, string $default = ''): string {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        if ($val !== false && $val !== null) {
            return (string)$val;
        }
        $stmt2 = $db->prepare("SELECT setting_value FROM agency_settings WHERE setting_key = ? LIMIT 1");
        $stmt2->execute([$key]);
        $val2 = $stmt2->fetchColumn();
        if ($val2 !== false && $val2 !== null) {
            return (string)$val2;
        }
        $stmt3 = $db->prepare("SELECT `value` FROM system_meta WHERE `key` = ? LIMIT 1");
        $stmt3->execute([$key]);
        $val3 = $stmt3->fetchColumn();
        if ($val3 !== false && $val3 !== null) {
            return (string)$val3;
        }
    } catch (Throwable $e) {}
    return $default;
}

/**
 * Persist a setting value to settings, agency_settings, and system_meta
 */
function set_agency_setting(string $key, string $value): bool {
    try {
        $db = Database::getConnection();
        $driver = Database::getDriver();
        $now = date('Y-m-d H:i:s');
        if ($driver === 'mysql') {
            $stmt = $db->prepare("INSERT INTO settings (`setting_key`, `setting_value`, `updated_at`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`), `updated_at` = VALUES(`updated_at`)");
            $stmt->execute([$key, $value, $now]);
            $stmt2 = $db->prepare("INSERT INTO agency_settings (`setting_key`, `setting_value`, `updated_at`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`), `updated_at` = VALUES(`updated_at`)");
            $stmt2->execute([$key, $value, $now]);
            $stmt3 = $db->prepare("INSERT INTO system_meta (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
            $stmt3->execute([$key, $value]);
        } else {
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at");
            $stmt->execute([$key, $value, $now]);
            $stmt2 = $db->prepare("INSERT INTO agency_settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at");
            $stmt2->execute([$key, $value, $now]);
            $stmt3 = $db->prepare("INSERT INTO system_meta (`key`, `value`) VALUES (?, ?) ON CONFLICT(`key`) DO UPDATE SET `value` = excluded.`value`");
            $stmt3->execute([$key, $value]);
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Rank Gemini model IDs according to Kala AI Pro Auto-Discovery priority:
 * 1. gemini-3.1-pro / gemini-3-pro / gemini-3.0-pro
 * 2. gemini-3.0-flash / gemini-3-flash / gemini-3.1-flash
 * 3. gemini-2.5-pro
 * 4. gemini-2.5-flash / gemini-flash-latest / gemini-pro-latest
 * 5. Other active Gemini text generation models
 */
function kala_rank_gemini_model_priority(string $modelId): int {
    $id = strtolower(preg_replace('#^models/#i', '', trim($modelId)));

    // Exclude non-standard / specialized modalities from top auto-fallback priority
    if (
        strpos($id, 'tts') !== false ||
        strpos($id, 'audio') !== false ||
        strpos($id, 'image') !== false ||
        strpos($id, 'imagen') !== false ||
        strpos($id, 'veo') !== false ||
        strpos($id, 'embedding') !== false ||
        strpos($id, 'robotics') !== false ||
        strpos($id, 'computer-use') !== false ||
        strpos($id, 'thinking-exp') !== false
    ) {
        return 10;
    }

    // Priority 1: Gemini 3.1 Pro / 3.0 Pro / 3 Pro
    if (
        strpos($id, 'gemini-3.1-pro') !== false ||
        strpos($id, 'gemini-3.0-pro') !== false ||
        strpos($id, 'gemini-3-pro') !== false
    ) {
        // Prefer main preview/stable over customtools variants
        if (strpos($id, 'customtools') !== false) {
            return 950;
        }
        return 1000;
    }

    // Priority 2: Gemini 3.0 Flash / 3 Flash / 3.1 Flash
    if (
        strpos($id, 'gemini-3.0-flash') !== false ||
        strpos($id, 'gemini-3-flash') !== false ||
        strpos($id, 'gemini-3.1-flash') !== false
    ) {
        if (strpos($id, 'lite') !== false) {
            return 880;
        }
        return 900;
    }

    // Priority 3: Gemini 2.5 Pro
    if (strpos($id, 'gemini-2.5-pro') !== false) {
        return 800;
    }

    // Priority 4: Gemini 2.5 Flash / gemini-pro-latest / gemini-flash-latest
    if (strpos($id, 'gemini-pro-latest') !== false) {
        return 760;
    }
    if (strpos($id, 'gemini-2.5-flash') !== false || strpos($id, 'gemini-flash-latest') !== false) {
        if (strpos($id, 'lite') !== false) {
            return 710;
        }
        return 740;
    }

    // Other Gemini models
    if (strpos($id, 'gemini-') === 0) {
        return 500;
    }

    return 100;
}

/**
 * Live Auto-Discovery of active models on the user's API key via GET /v1beta/models
 */
function kala_discover_gemini_models(string $apiKey): array {
    $apiKey = trim($apiKey);
    if ($apiKey === '' || !function_exists('curl_init')) {
        return [
            'success' => false,
            'models' => [],
            'model_ids' => [],
            'error' => 'API Key kosong atau cURL tidak tersedia.'
        ];
    }

    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models?pageSize=100&key=" . rawurlencode($apiKey);
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0
    ]);

    $responseBody = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false || !empty($curlErr)) {
        return [
            'success' => false,
            'models' => [],
            'model_ids' => [],
            'error' => 'Gagal menghubungi Google Gemini API: ' . ($curlErr ?: 'Network Error')
        ];
    }

    $decoded = json_decode($responseBody, true);
    if ($httpCode < 200 || $httpCode >= 300 || !isset($decoded['models']) || !is_array($decoded['models'])) {
        $apiError = $decoded['error']['message'] ?? ('HTTP Error ' . $httpCode);
        return [
            'success' => false,
            'models' => [],
            'model_ids' => [],
            'error' => $apiError
        ];
    }

    $models = [];
    foreach ($decoded['models'] as $m) {
        $methods = $m['supportedGenerationMethods'] ?? [];
        if (!in_array('generateContent', $methods, true)) {
            continue;
        }
        $rawName = $m['name'] ?? '';
        $id = preg_replace('#^models/#i', '', $rawName);
        $lowerId = strtolower($id);
        if (
            $id === '' ||
            strpos($lowerId, 'customtools') !== false ||
            strpos($lowerId, 'embedding') !== false ||
            strpos($lowerId, 'aqa') !== false ||
            strpos($lowerId, 'imagen') !== false ||
            strpos($lowerId, 'veo') !== false ||
            strpos($lowerId, 'tts') !== false ||
            strpos($lowerId, 'audio') !== false ||
            strpos($lowerId, 'robotics') !== false ||
            strpos($lowerId, 'computer-use') !== false ||
            strpos($lowerId, 'learnlm') !== false ||
            strpos($lowerId, 'bison') !== false
        ) {
            continue;
        }

        $priority = kala_rank_gemini_model_priority($id);
        $isPro = (stripos($id, 'pro') !== false);
        $models[] = [
            'id' => $id,
            'display_name' => $m['displayName'] ?? $id,
            'is_pro' => $isPro,
            'priority' => $priority,
            'description' => $m['description'] ?? ''
        ];
    }

    usort($models, function ($a, $b) {
        if ($a['priority'] !== $b['priority']) {
            return $b['priority'] <=> $a['priority'];
        }
        if ($a['is_pro'] !== $b['is_pro']) {
            return $a['is_pro'] ? -1 : 1;
        }
        return strcmp($b['id'], $a['id']);
    });

    $modelIds = array_map(function ($item) {
        return $item['id'];
    }, $models);

    return [
        'success' => true,
        'models' => $models,
        'model_ids' => $modelIds,
        'error' => null
    ];
}

/**
 * Summarize a verbose Google Gemini API error into a clear, honest status reason
 */
function kala_summarize_gemini_error(int $httpCode, string $rawError): string {
    $firstLine = trim( strtok($rawError, "\n") ?: $rawError );
    if ($httpCode === 429 || stripos($rawError, 'quota') !== false || stripos($rawError, 'rate limit') !== false || stripos($rawError, 'RESOURCE_EXHAUSTED') !== false) {
        if (stripos($rawError, 'limit: 0') !== false) {
            return "429 Kuota Model 0 pada Tier API Key Ini (Model membutuhkan Billing/Paid Tier atau gunakan Gemini 3 Flash)";
        }
        return "429 Kuota / Rate Limit Terlampaui ({$firstLine})";
    }
    if (stripos($rawError, 'no longer available') !== false || stripos($rawError, 'deprecated') !== false) {
        return "Model Tidak Lagi Tersedia untuk Akun Baru ({$firstLine})";
    }
    if ($httpCode === 404 || stripos($rawError, 'not found') !== false || stripos($rawError, 'not supported') !== false) {
        return "404 Model Tidak Ditemukan / Belum Tersedia ({$firstLine})";
    }
    if ($httpCode === 503 || stripos($rawError, 'overloaded') !== false || stripos($rawError, 'unavailable') !== false) {
        return "503 Server Model Sedang Sibuk ({$firstLine})";
    }
    return "HTTP {$httpCode}: {$firstLine}";
}

/**
 * Execute generateContent with automatic GET /v1beta/models live discovery fallback
 * and automatic DB model update when a fallback model succeeds.
 */
function kala_call_gemini_with_autodiscovery(
    string $apiKey,
    string $preferredModel,
    array $payload,
    int $timeoutSec = 60,
    $validator = null
): array {
    if (!is_callable($validator)) {
        $validator = null;
    }
    $apiKey = trim($apiKey);
    $preferredModel = preg_replace('#^models/#i', '', trim($preferredModel));
    if ($preferredModel === '') {
        $preferredModel = 'gemini-2.5-pro';
    }

    $startTime = microtime(true);
    $attempts = [];
    $discoveredModels = [];
    $discoveryCalled = false;

    // Helper closure to test a single model against :generateContent
    $trySingleModel = function (string $candidateModel) use ($apiKey, $payload, $timeoutSec, $validator): array {
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/" . rawurlencode($candidateModel) . ":generateContent?key=" . rawurlencode($apiKey);
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);

        $responseBody = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($responseBody === false || !empty($curlErr)) {
            $errMsg = "Koneksi cURL gagal: " . ($curlErr ?: 'Timeout / Network Error');
            return [
                'ok' => false,
                'http_code' => $httpCode,
                'error' => $errMsg,
                'summary' => $errMsg,
                'fatal_key_error' => false
            ];
        }

        $decoded = json_decode($responseBody, true);
        if ($httpCode >= 200 && $httpCode < 300 && !empty($decoded['candidates'][0]['content']['parts'])) {
            $replyText = '';
            foreach ($decoded['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['text'])) {
                    $replyText .= $part['text'];
                }
            }
            $replyText = trim($replyText);

            if ($validator !== null) {
                $validationResult = $validator($replyText, $decoded, $candidateModel);
                if (empty($validationResult['valid'])) {
                    $valErr = $validationResult['error'] ?? "Format output dari {$candidateModel} tidak sesuai skema.";
                    return [
                        'ok' => false,
                        'http_code' => $httpCode,
                        'error' => $valErr,
                        'summary' => $valErr,
                        'fatal_key_error' => false
                    ];
                }
                return [
                    'ok' => true,
                    'http_code' => $httpCode,
                    'reply_text' => $replyText,
                    'decoded' => $decoded,
                    'validated_data' => $validationResult['data'] ?? null
                ];
            }

            return [
                'ok' => true,
                'http_code' => $httpCode,
                'reply_text' => $replyText,
                'decoded' => $decoded,
                'validated_data' => null
            ];
        }

        $apiError = $decoded['error']['message'] ?? ('HTTP Error ' . $httpCode);
        $isFatalKeyError = (
            ($httpCode === 400 || $httpCode === 401 || $httpCode === 403) &&
            (
                stripos($apiError, 'API_KEY_INVALID') !== false ||
                stripos($apiError, 'API key not valid') !== false ||
                stripos($apiError, 'key not valid') !== false ||
                stripos($apiError, 'API key expired') !== false
            )
        );

        return [
            'ok' => false,
            'http_code' => $httpCode,
            'error' => $apiError,
            'summary' => kala_summarize_gemini_error($httpCode, $apiError),
            'fatal_key_error' => $isFatalKeyError
        ];
    };

    // 1. Try the user's selected/preferred model first
    $firstRes = $trySingleModel($preferredModel);
    if ($firstRes['ok']) {
        $latencyMs = (int)round((microtime(true) - $startTime) * 1000);
        $attempts[] = [
            'model' => $preferredModel,
            'http_code' => $firstRes['http_code'],
            'status' => 'success',
            'detail' => "Berhasil terhubung ({$latencyMs} ms)"
        ];
        set_agency_setting('gemini_api_key', $apiKey);
        set_agency_setting('gemini_model', $preferredModel);

        return [
            'success' => true,
            'model_requested' => $preferredModel,
            'model_used' => $preferredModel,
            'used_model' => $preferredModel,
            'used_fallback' => false,
            'latency_ms' => $latencyMs,
            'reply_text' => $firstRes['reply_text'],
            'raw_text' => $firstRes['reply_text'],
            'decoded' => $firstRes['decoded'],
            'validated_data' => $firstRes['validated_data'],
            'attempts' => $attempts,
            'discovered_models' => []
        ];
    }

    // Record primary model failure honestly
    $primaryError = $firstRes['error'];
    $primarySummary = $firstRes['summary'];
    $attempts[] = [
        'model' => $preferredModel,
        'http_code' => $firstRes['http_code'],
        'status' => 'failed',
        'detail' => $primarySummary
    ];

    // If the API key itself is invalid, stop immediately without trying fallbacks
    if (!empty($firstRes['fatal_key_error'])) {
        return [
            'success' => false,
            'http_code' => $firstRes['http_code'],
            'model_requested' => $preferredModel,
            'model_used' => null,
            'used_model' => null,
            'used_fallback' => false,
            'primary_error' => $primaryError,
            'message' => "API Key Tidak Valid: {$primaryError}",
            'error_message' => "API Key Tidak Valid: {$primaryError}",
            'attempts' => $attempts,
            'discovered_models' => []
        ];
    }

    // 2. Auto-Discovery via GET /v1beta/models to find active latest-generation models on this API key
    $discovery = kala_discover_gemini_models($apiKey);
    $discoveryCalled = true;
    $discoveredModels = $discovery['models'] ?? [];
    $discoveredIds = $discovery['model_ids'] ?? [];

    // Check if Google's error message explicitly recommended a replacement model (e.g. "models/gemini-3.0-flash")
    $suggestedModels = [];
    if (preg_match_all('#models/([a-zA-Z0-9._-]+)#i', $primaryError, $matches)) {
        foreach ($matches[1] as $mName) {
            if (strcasecmp($mName, $preferredModel) !== 0) {
                $suggestedModels[] = $mName;
            }
        }
    }

    // Build prioritized candidate list from live GET /v1beta/models discovery
    // Priority order enforced by kala_discover_gemini_models:
    // 1. gemini-3.1-pro / gemini-3-pro / gemini-3.0-pro
    // 2. gemini-3.0-flash / gemini-3-flash
    // 3. gemini-2.5-pro
    // 4. gemini-2.5-flash / gemini-flash-latest / gemini-pro-latest
    $fallbackQueue = [];
    if (!empty($discoveredIds)) {
        // Put discovered models first in exact priority order, plus any Google-suggested model that exists in discoveredIds
        foreach ($discoveredIds as $dId) {
            if (strcasecmp($dId, $preferredModel) !== 0) {
                $fallbackQueue[] = $dId;
            }
        }
        foreach ($suggestedModels as $sId) {
            if (!in_array($sId, $fallbackQueue, true) && strcasecmp($sId, $preferredModel) !== 0) {
                $fallbackQueue[] = $sId;
            }
        }
    } else {
        // If GET /v1beta/models failed, use modern priority order (never ending on deprecated 2.5-flash if 3.x is suggested)
        $staticModernCandidates = array_merge(
            $suggestedModels,
            [
                'gemini-3.1-pro-preview',
                'gemini-3.0-pro',
                'gemini-3-pro-preview',
                'gemini-3.0-flash',
                'gemini-3-flash-preview',
                'gemini-2.5-pro',
                'gemini-pro-latest',
                'gemini-flash-latest'
            ]
        );
        foreach ($staticModernCandidates as $cId) {
            if (strcasecmp($cId, $preferredModel) !== 0 && !in_array($cId, $fallbackQueue, true)) {
                $fallbackQueue[] = $cId;
            }
        }
    }

    // Try up to 5 discovered active models so we find a working model quickly
    $maxFallbackTries = 5;
    $triedCount = 0;

    foreach ($fallbackQueue as $candidateModel) {
        if ($triedCount >= $maxFallbackTries) {
            break;
        }
        $triedCount++;

        $res = $trySingleModel($candidateModel);
        if ($res['ok']) {
            $latencyMs = (int)round((microtime(true) - $startTime) * 1000);
            $attempts[] = [
                'model' => $candidateModel,
                'http_code' => $res['http_code'],
                'status' => 'success',
                'detail' => "Berhasil terhubung via Auto-Discovery ({$latencyMs} ms)"
            ];

            // Automatically save working API key and auto-discovered model to DB
            set_agency_setting('gemini_api_key', $apiKey);
            set_agency_setting('gemini_model', $candidateModel);

            return [
                'success' => true,
                'model_requested' => $preferredModel,
                'model_used' => $candidateModel,
                'used_model' => $candidateModel,
                'used_fallback' => true,
                'latency_ms' => $latencyMs,
                'reply_text' => $res['reply_text'],
                'raw_text' => $res['reply_text'],
                'decoded' => $res['decoded'],
                'validated_data' => $res['validated_data'],
                'attempts' => $attempts,
                'discovered_models' => $discoveredModels
            ];
        }

        $attempts[] = [
            'model' => $candidateModel,
            'http_code' => $res['http_code'],
            'status' => 'failed',
            'detail' => $res['summary']
        ];
    }

    // All attempts failed — report the primary model's error honestly along with the full attempt log
    $attemptSummaryParts = [];
    foreach ($attempts as $att) {
        $attemptSummaryParts[] = "{$att['model']} ({$att['detail']})";
    }

    return [
        'success' => false,
        'http_code' => $firstRes['http_code'],
        'model_requested' => $preferredModel,
        'model_used' => null,
        'used_model' => null,
        'used_fallback' => true,
        'primary_error' => $primaryError,
        'message' => "Model {$preferredModel} gagal: {$primarySummary}",
        'error_message' => "Model {$preferredModel} gagal: {$primarySummary}",
        'attempts' => $attempts,
        'attempts_summary' => implode(' | ', $attemptSummaryParts),
        'discovered_models' => $discoveredModels
    ];
}

// Compatibility aliases for camelCase callers
if (!function_exists('getSystemSetting')) {
    function getSystemSetting($dbOrKey, string $keyOrDefault = '', string $default = ''): string {
        if ($dbOrKey instanceof PDO) {
            return get_agency_setting($keyOrDefault, $default);
        }
        return get_agency_setting((string)$dbOrKey, $keyOrDefault);
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn(?string $context = null): bool {
        if (function_exists('is_logged_in')) {
            return is_logged_in($context);
        }
        return !empty($_SESSION['user_id']) || !empty($_SESSION['kala_accounts']);
    }
}

if (!function_exists('getUserRole')) {
    function getUserRole(?string $context = null): string {
        if (function_exists('current_user_role')) {
            return current_user_role($context);
        }
        return $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
    }
}

if (!function_exists('logActivity')) {
    function logActivity($dbOrType, string $typeOrTitle = '', ?string $titleOrDesc = null, $descOrAmount = null): void {
        if ($dbOrType instanceof PDO) {
            if (function_exists('log_activity')) {
                log_activity($typeOrTitle, (string)$titleOrDesc, is_string($descOrAmount) ? $descOrAmount : null);
            }
        } else {
            if (function_exists('log_activity')) {
                log_activity((string)$dbOrType, $typeOrTitle, $titleOrDesc, $descOrAmount);
            }
        }
    }
}





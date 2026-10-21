<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SERVER['DOCUMENT_ROOT'] = 'c:/xampp/htdocs/Kalamedia';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$ownerSession = [
    'kala_auth' => [
        'internal' => ['user_id' => 1, 'role' => 'owner', 'name' => 'Owner', 'email' => 'owner@kalamedia.id', 'employment_type' => 'internal']
    ],
    'user' => ['id' => 1, 'role' => 'owner', 'name' => 'Owner', 'email' => 'owner@kalamedia.id']
];

$views = [
    'owner_dashboard.php',
    'admin_dashboard.php',
    'invoices.php',
    'expenses.php',
    'salaries.php',
    'clients.php',
    'team.php',
    'tasks.php',
    'content_dashboard.php',
    'reports.php',
    'settings.php'
];

$_SESSION = $ownerSession;
$allClean = true;

foreach ($views as $v) {
    $_GET = [];
    $notices = [];
    set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$notices) {
        if (error_reporting() === 0) return false;
        $notices[] = "Line $errline: $errstr";
        return true;
    });

    ob_start();
    $exc = null;
    try {
        include BASE_PATH . '/views/' . $v;
    } catch (\Throwable $e) {
        $exc = $e->getMessage();
    }
    $out = ob_get_clean();
    restore_error_handler();

    if ($exc !== null) {
        echo "[FAIL] $v: Exception: $exc\n";
        $allClean = false;
    } elseif (!empty($notices)) {
        echo "[FAIL] $v: Notices: " . implode('; ', $notices) . "\n";
        $allClean = false;
    } else {
        echo "[PASS] $v: Rendered successfully (" . strlen($out) . " bytes)\n";
    }
}

if ($allClean) {
    echo "\n🎉 ALL EMPTY-STATE VIEWS RENDERED 100% CLEAN WITHOUT NOTICES OR ERRORS!\n";
}

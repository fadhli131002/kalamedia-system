<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Setup environment & mocks
$_SERVER['DOCUMENT_ROOT'] = 'c:/xampp/htdocs/Kalamedia';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$_SESSION['logged_in'] = true;
$_SESSION['user'] = [
    'id' => 1,
    'role' => 'owner',
    'name' => 'Muhammad Fadhli',
    'email' => 'owner@kalamedia.id'
];

$errorsFound = [];

// Custom error handler to capture notices and warnings
set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$errorsFound) {
    if (error_reporting() === 0) return false;
    $errorsFound[] = [
        'errno' => $errno,
        'errstr' => $errstr,
        'file' => $errfile,
        'line' => $errline
    ];
    return true;
});

echo "=== AUDITING ALL VIEWS & SCRIPTS FOR PHP NOTICES / WARNINGS ===\n\n";

$views = [
    'owner_dashboard' => __DIR__ . '/../views/owner_dashboard.php',
    'admin_dashboard' => __DIR__ . '/../views/admin_dashboard.php',
    'invoices' => __DIR__ . '/../views/invoices.php',
    'invoice_view' => __DIR__ . '/../views/invoice_view.php',
    'expenses' => __DIR__ . '/../views/expenses.php',
    'salaries' => __DIR__ . '/../views/salaries.php',
    'clients' => __DIR__ . '/../views/clients.php',
    'reports' => __DIR__ . '/../views/reports.php',
    'reports_form' => __DIR__ . '/../views/reports_form.php',
    'report_deck' => __DIR__ . '/../views/report_deck.php',
    'content_dashboard' => __DIR__ . '/../views/content_dashboard.php',
    'settings' => __DIR__ . '/../views/settings.php',
    'login' => __DIR__ . '/../views/login.php',
];

$_GET['id'] = 1;
$_GET['tab'] = 'payroll';

foreach ($views as $name => $path) {
    if (!file_exists($path)) {
        echo "⚠️ $name: File not found at $path\n";
        continue;
    }
    
    $startErrCount = count($errorsFound);
    ob_start();
    try {
        include $path;
    } catch (\Throwable $e) {
        $errorsFound[] = [
            'errno' => E_ERROR,
            'errstr' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
    }
    $output = ob_get_clean();
    
    $viewErrors = array_slice($errorsFound, $startErrCount);
    if (empty($viewErrors)) {
        echo "✔ PASS: $name (0 notices/warnings, size: " . strlen($output) . " bytes)\n";
    } else {
        echo "✖ ISSUES FOUND IN $name:\n";
        foreach ($viewErrors as $err) {
            echo "   • Line {$err['line']}: {$err['errstr']} in {$err['file']}\n";
        }
    }
}

echo "\nTotal notices/warnings found: " . count($errorsFound) . "\n";
if (empty($errorsFound)) {
    echo "🎉 ALL VIEWS AUDITED CLEAN! ZERO PHP NOTICES/WARNINGS!\n";
}

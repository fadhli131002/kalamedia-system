<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

$_SESSION['user'] = [
    'id' => 2,
    'name' => 'Siti Rahma',
    'email' => 'admin@kalamedia.id',
    'role' => 'admin'
];

$viewFiles = [
    'admin-dashboard' => 'admin_dashboard.php',
    'invoices' => 'invoices.php',
    'expenses' => 'expenses.php',
    'clients' => 'clients.php'
];

foreach ($viewFiles as $route => $file) {
    $_GET['page'] = $route;
    $GLOBALS['currentPage'] = $route;
    ob_start();
    try {
        require dirname(__DIR__) . "/views/{$file}";
        $html = ob_get_clean();
        echo "[OK] (Admin) Route '$route' -> views/$file rendered successfully (" . strlen($html) . " bytes)\n";
    } catch (Throwable $e) {
        ob_end_clean();
        echo "[FAIL] (Admin) Route '$route' threw error: " . $e->getMessage() . "\n";
    }
}

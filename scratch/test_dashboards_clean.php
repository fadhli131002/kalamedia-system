<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$suites = [
    'Owner Dashboard' => [
        'file' => 'views/owner_dashboard.php',
        'role' => 'owner',
        'id' => 1
    ],
    'Admin Dashboard' => [
        'file' => 'views/admin_dashboard.php',
        'role' => 'admin',
        'id' => 2
    ]
];

foreach ($suites as $name => $s) {
    $_SESSION['kala_accounts'] = [
        'u_test' => ['user_id' => $s['id'], 'role' => $s['role'], 'name' => 'Test User', 'email' => 'test@kalamedia.id']
    ];
    $_SESSION['kala_auth'] = [
        'internal' => ['user_id' => $s['id'], 'role' => $s['role'], 'name' => 'Test User', 'email' => 'test@kalamedia.id']
    ];
    $_SESSION['logged_in'] = true;
    $_SESSION['user'] = ['id' => $s['id'], 'role' => $s['role'], 'name' => 'Test User', 'email' => 'test@kalamedia.id'];
    $_GET = ['_u' => 'u_test'];

    $notices = [];
    set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$notices) {
        if (error_reporting() === 0) return false;
        $notices[] = "Line $errline: $errstr in " . basename($errfile);
        return true;
    });

    ob_start();
    try {
        include __DIR__ . '/../' . $s['file'];
        $len = strlen(ob_get_clean());
        restore_error_handler();
        if (!empty($notices)) {
            echo "✖ $name NOTICES:\n   " . implode("\n   ", $notices) . "\n";
        } else {
            echo "✔ $name: Clean render ($len bytes)\n";
        }
    } catch (\Throwable $e) {
        ob_end_clean();
        restore_error_handler();
        echo "✖ $name EXCEPTION: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}

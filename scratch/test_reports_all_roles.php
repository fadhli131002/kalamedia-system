<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$roles = ['owner', 'admin', 'karyawan', 'freelancer', 'client'];
foreach ($roles as $r) {
    $_SESSION['kala_accounts'] = [
        'u_t' => ['user_id' => 1, 'role' => $r, 'name' => 'Test ' . $r, 'email' => $r . '@test.com', 'client_id' => 1]
    ];
    $_SESSION['kala_auth'] = [
        ($r === 'client' ? 'client' : 'internal') => ['user_id' => 1, 'role' => $r, 'name' => 'Test ' . $r, 'email' => $r . '@test.com', 'client_id' => 1]
    ];
    $_SESSION['logged_in'] = true;
    $_SESSION['user'] = ['id' => 1, 'role' => $r, 'name' => 'Test ' . $r, 'email' => $r . '@test.com', 'client_id' => 1];
    $_GET = ['_u' => 'u_t'];
    
    $notices = [];
    set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$notices) {
        if (error_reporting() === 0) return false;
        $notices[] = "Line $errline: $errstr in " . basename($errfile);
        return true;
    });

    ob_start();
    try {
        include __DIR__ . '/../views/reports.php';
        $len = strlen(ob_get_clean());
        restore_error_handler();
        if (!empty($notices)) {
            echo "Role $r NOTICES: " . implode('; ', $notices) . "\n";
        } else {
            echo "Role $r: PASS ($len bytes)\n";
        }
    } catch (\Throwable $e) {
        ob_end_clean();
        restore_error_handler();
        echo "Role $r EXCEPTION: " . $e->getMessage() . "\n";
    }
}

<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$_SESSION['logged_in'] = true;
$_SESSION['active_role'] = 'admin';

$user = current_user();
echo "Current User:\n";
print_r($user);

ob_start();
$_GET['page'] = 'admin-dashboard';
$GLOBALS['currentPage'] = 'admin-dashboard';
include __DIR__ . '/../views/admin_dashboard.php';
$html = ob_get_clean();

if (str_contains($html, 'Halo, Ilham')) {
    echo "\nPASS: Header shows 'Halo, Ilham'!\n";
} else {
    echo "\nFAIL: Header does not show 'Halo, Ilham'!\n";
}

if (str_contains($html, 'Ilham Lanang')) {
    echo "PASS: Sidebar shows 'Ilham Lanang'!\n";
} else {
    echo "FAIL: Sidebar does not show 'Ilham Lanang'!\n";
}

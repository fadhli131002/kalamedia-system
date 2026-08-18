<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$_SESSION['logged_in'] = true;
$_SESSION['active_role'] = 'admin';

ob_start();
$_GET['page'] = 'salaries';
$GLOBALS['currentPage'] = 'salaries';
include __DIR__ . '/../views/salaries.php';
$html = ob_get_clean();

echo "Salaries page rendered length: " . strlen($html) . " bytes\n";
if (str_contains($html, 'Plus Jakarta Sans')) {
    echo "PASS: Plus Jakarta Sans is in the document!\n";
}
if (str_contains($html, 'Asset 3.png')) {
    echo "PASS: Official Kala Media Logo Asset 3.png is present in slip modal!\n";
}
if (str_contains($html, 'Ilham Lanang')) {
    echo "PASS: Ilham Lanang is in the signature line!\n";
}

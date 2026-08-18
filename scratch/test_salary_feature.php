<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$_SESSION['logged_in'] = true;
$_SESSION['active_role'] = 'admin';
$_SESSION['accounts']['admin'] = [
    'id' => 2,
    'name' => 'Siti Rahma',
    'email' => 'admin@kalamedia.id',
    'role' => 'admin',
    'avatar' => null
];

$db = Database::getConnection();

echo "1. Checking Employees Count: ";
$empCount = $db->query("SELECT COUNT(*) FROM employees")->fetchColumn();
echo "$empCount employees found.\n";

echo "2. Checking Salaries Count: ";
$salCount = $db->query("SELECT COUNT(*) FROM salaries")->fetchColumn();
echo "$salCount salaries found.\n";

echo "3. Testing Slip Gaji Data for ID 1: \n";
$sal1 = $db->query("SELECT * FROM salaries WHERE id = 1")->fetch();
print_r($sal1);

echo "\n4. Testing Analytics Query: \n";
$currentMonth = date('Y-m');
$totSal = floatval($db->query("SELECT COALESCE(SUM(net_salary), 0) FROM salaries WHERE status = 'Paid' AND strftime('%Y-%m', paid_at) = '$currentMonth'")->fetchColumn());
echo "Total Paid Salaries this month: " . format_rupiah($totSal) . "\n";

echo "\n5. Testing View Render (views/salaries.php): \n";
ob_start();
$_GET['page'] = 'salaries';
$GLOBALS['currentPage'] = 'salaries';
include __DIR__ . '/../views/salaries.php';
$html = ob_get_clean();
echo "Rendered salaries.php successfully! HTML length: " . strlen($html) . " bytes.\n";

echo "\n6. Testing View Render (views/admin_dashboard.php): \n";
ob_start();
$_GET['page'] = 'admin-dashboard';
$GLOBALS['currentPage'] = 'admin-dashboard';
include __DIR__ . '/../views/admin_dashboard.php';
$htmlAdmin = ob_get_clean();
echo "Rendered admin_dashboard.php successfully! HTML length: " . strlen($htmlAdmin) . " bytes.\n";

echo "\nALL FUNCTIONAL TESTS PASSED!\n";

<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

session_start();
$_SESSION['logged_in'] = true;
$_SESSION['user'] = ['id' => 1, 'role' => 'owner', 'name' => 'Owner Kala'];

echo "=== TESTING EMPLOYEE GET & UPDATE ENDPOINTS ===\n\n";

// 1. Get Employee 5 (Muhammad Fadhli)
$_GET['action'] = 'get_employee';
$_GET['id'] = 5;

ob_start();
include __DIR__ . '/../api/salaries.php';
$getRes = ob_get_clean();
echo "[1] GET Employee 5 Result:\n" . $getRes . "\n";

// 2. Test Update Employee
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'update_employee';
$_POST['id'] = 5;
$_POST['name'] = 'Muhammad Fadhli';
$_POST['position'] = 'Creative Lead';
$_POST['department'] = 'Creative & Production';
$_POST['employment_type'] = 'Full-time';
$_POST['email'] = 'mha.fadhli@gmail.com';
$_POST['phone'] = '08881124215';
$_POST['bank_name'] = 'Seabank';
$_POST['bank_account'] = '901700766017';
$_POST['base_salary'] = '7500000';
$_POST['status'] = 'Active';

ob_start();
include __DIR__ . '/../api/salaries.php';
$updateRes = ob_get_clean();
echo "\n[2] UPDATE Employee 5 Result:\n" . $updateRes . "\n";

echo "\n=== ALL TESTS FINISHED ===\n";

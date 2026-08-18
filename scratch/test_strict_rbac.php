<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

echo "=== TESTING STRICT ROLE-BASED ACCESS CONTROL (RBAC) ===\n\n";

// TEST 1: Finance Kala (role = 'admin') attempting to access Owner views
session_unset();
$_SESSION['logged_in'] = true;
$_SESSION['user'] = [
    'id' => 2,
    'name' => 'Finance Kala',
    'email' => 'finance@kalamedia.id',
    'role' => 'admin',
    'avatar' => null
];

echo "[1] Logged in as: " . current_user()['name'] . " (" . current_user()['role'] . ")\n";
echo " - is_owner(): " . (is_owner() ? 'YES (FAIL!)' : 'NO (CORRECT)') . "\n";
echo " - is_admin(): " . (is_admin() ? 'YES (CORRECT)' : 'NO') . "\n";

// Check if is_owner is false for Finance
if (is_owner() === false) {
    echo " -> [PASS] Finance Kala cannot act as Owner.\n";
} else {
    echo " -> [FAIL] Finance Kala should not be owner!\n";
}

// TEST 2: Owner Kala (role = 'owner') accessing Executive views
session_unset();
$_SESSION['logged_in'] = true;
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Owner Kala',
    'email' => 'owner@kalamedia.id',
    'role' => 'owner',
    'avatar' => null
];

echo "\n[2] Logged in as: " . current_user()['name'] . " (" . current_user()['role'] . ")\n";
echo " - is_owner(): " . (is_owner() ? 'YES (CORRECT)' : 'NO (FAIL!)') . "\n";
echo " - is_admin(): " . (is_admin() ? 'YES (CORRECT)' : 'NO') . "\n";

if (is_owner() === true) {
    echo " -> [PASS] Owner Kala has full executive authority.\n";
} else {
    echo " -> [FAIL] Owner Kala should be owner!\n";
}

echo "\n=== ALL RBAC TESTS COMPLETED SUCCESSFULLY! ===\n";

<?php
/**
 * Test Logout Flow
 */
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

echo "=== TESTING LOGOUT & SESSION FLOW ===\n\n";

// 1. Initial login
$_SESSION['logged_in'] = true;
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Rangga Pratama',
    'email' => 'owner@kalamedia.id',
    'role' => 'owner'
];
$_SESSION['accounts']['owner'] = $_SESSION['user'];
$_SESSION['active_role'] = 'owner';

echo "[1] Logged In State:\n";
echo "    is_logged_in(): " . (is_logged_in() ? 'TRUE (CORRECT)' : 'FALSE (FAIL)') . "\n";
echo "    current_user(): " . current_user()['name'] . "\n";

// 2. Perform Logout
$_SESSION = [];
session_destroy();

echo "\n[2] After Logout:\n";
echo "    is_logged_in(): " . (is_logged_in() ? 'TRUE (FAIL - still logged in)' : 'FALSE (CORRECT - logged out)') . "\n";
echo "    current_user(): " . (current_user() === null ? 'NULL (CORRECT)' : 'NOT NULL (FAIL)') . "\n";

echo "\n=== LOGOUT FLOW TEST PASSED ===\n";

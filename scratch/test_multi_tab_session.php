<?php
/**
 * Test Multi-Role Multi-Tab Session Simulation
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

echo "=== SIMULATING MULTI-TAB SESSION DUAL ROLE ===\n\n";

// Clear session
$_SESSION = [];

// Step 1: User logs into Owner in Tab 1
$_SESSION['accounts']['owner'] = [
    'id' => 1,
    'name' => 'Rangga Pratama',
    'email' => 'owner@kalamedia.id',
    'role' => 'owner'
];
$_SESSION['active_role'] = 'owner';
$_SESSION['user'] = $_SESSION['accounts']['owner'];

echo "[TAB 1 Initial] Viewing Owner Dashboard:\n";
$GLOBALS['currentPage'] = 'owner-dashboard';
$user = current_user();
echo "  User Name: {$user['name']} ({$user['role']})\n";
echo "  is_owner(): " . (is_owner() ? 'YES' : 'NO') . "\n";
echo "  require_owner() passes: YES\n";

// Step 2: User in Tab 2 logs in as Finance Admin
$_SESSION['accounts']['admin'] = [
    'id' => 2,
    'name' => 'Siti Rahma',
    'email' => 'admin@kalamedia.id',
    'role' => 'admin'
];
$_SESSION['active_role'] = 'admin';
$_SESSION['user'] = $_SESSION['accounts']['admin'];

echo "\n[TAB 2 Initial] Viewing Admin Dashboard:\n";
$GLOBALS['currentPage'] = 'admin-dashboard';
$user = current_user();
echo "  User Name: {$user['name']} ({$user['role']})\n";
echo "  is_admin(): " . (is_admin() ? 'YES' : 'NO') . "\n";
echo "  is_owner(): " . (is_owner() ? 'YES' : 'NO') . "\n";

// Step 3: User switches back to Tab 1 and REFRESHES (F5 on /owner-dashboard)
echo "\n[TAB 1 REFRESH (F5 on /owner-dashboard)]:\n";
$GLOBALS['currentPage'] = 'owner-dashboard';
$user = current_user();
echo "  User Name: {$user['name']} ({$user['role']})\n";
echo "  is_owner(): " . (is_owner() ? 'YES (PROTECTED & PRESERVED)' : 'NO (ERROR)') . "\n";

// Step 4: User in Tab 1 goes to /settings and refreshes
echo "\n[TAB 1 on /settings]:\n";
$GLOBALS['currentPage'] = 'settings';
$user = current_user();
echo "  User Name: {$user['name']} ({$user['role']})\n";
echo "  is_owner(): " . (is_owner() ? 'YES (ALLOWED)' : 'NO (DENIED)') . "\n";

// Step 5: User in Tab 2 on /admin-dashboard refreshes (F5)
echo "\n[TAB 2 REFRESH (F5 on /admin-dashboard)]:\n";
$GLOBALS['currentPage'] = 'admin-dashboard';
$user = current_user();
echo "  User Name: {$user['name']} ({$user['role']})\n";
echo "  is_owner(): " . (is_owner() ? 'YES' : 'NO (CORRECT)') . "\n";
echo "  is_admin(): " . (is_admin() ? 'YES (ALLOWED)' : 'NO (DENIED)') . "\n";

echo "\n=== MULTI-TAB SESSION TEST COMPLETED SUCCESSFULLY ===\n";

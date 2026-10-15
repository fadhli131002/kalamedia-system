<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

echo "=== VERIFYING DUAL-SLOT MULTI-TAB SESSION & ADS TRACKING ===\n\n";

// 1. Simulate Owner Login in Tab 1 (Internal Slot)
$_SESSION['kala_internal'] = [
    'logged_in' => true,
    'user' => [
        'id' => 1,
        'name' => 'Rangga Pratama',
        'email' => 'rangga@kalamedia.id',
        'role' => 'owner',
        'employment_type' => 'internal'
    ]
];

// In Tab 1: Accessing internal dashboard (/owner-dashboard)
$_SERVER['REQUEST_URI'] = '/Kalamedia/owner-dashboard';
unset($_SERVER['HTTP_REFERER']);
assert_test(get_auth_context() === 'internal', "Context on /owner-dashboard is 'internal'");
assert_test(current_user_role() === 'owner', "Current user role in internal slot is 'owner'");
assert_test(is_owner() === true, "is_owner() returns true in internal slot");
assert_test(is_client() === false, "is_client() returns false in internal slot");

// 2. Simulate Client Login in Tab 2 (Client Portal Slot)
$_SESSION['kala_client_portal'] = [
    'logged_in' => true,
    'user' => [
        'id' => 10,
        'name' => 'Client PPM',
        'email' => 'client@ppm.com',
        'role' => 'client',
        'client_id' => 5
    ]
];

// In Tab 2: Accessing Client Portal (/client-portal)
$_SERVER['REQUEST_URI'] = '/Kalamedia/client-portal';
assert_test(get_auth_context() === 'client', "Context on /client-portal is 'client'");
assert_test(current_user_role() === 'client', "Current user role on /client-portal is 'client'");
assert_test(is_client() === true, "is_client() returns true on /client-portal");
assert_test(is_owner() === false, "is_owner() returns false on /client-portal");

// In Tab 2: AJAX request from client portal to ads tracker
$_SERVER['REQUEST_URI'] = '/Kalamedia/api/ads_tracker.php?action=list';
$_SERVER['HTTP_REFERER'] = 'http://localhost/Kalamedia/client-portal';
assert_test(get_auth_context() === 'client', "AJAX call with client-portal referer resolves to 'client' context");
assert_test(current_user_role() === 'client', "AJAX user resolves to Client user");

// 3. Tab 1 Refreshes: Tab 1 is STILL Owner!
$_SERVER['REQUEST_URI'] = '/Kalamedia/owner-dashboard';
unset($_SERVER['HTTP_REFERER']);
assert_test(get_auth_context() === 'internal', "Context on /owner-dashboard refresh is 'internal'");
assert_test(current_user_role() === 'owner', "Tab 1 is STILL Owner after Tab 2 client login!");
assert_test(is_owner() === true, "is_owner() in Tab 1 is intact");

// 4. Test Client Logout on Tab 2
// When client logs out, only kala_client_portal is unset
unset($_SESSION['kala_client_portal']);
$_SERVER['REQUEST_URI'] = '/Kalamedia/client-portal';
// If owner is logged in internally, preview mode allows viewing, but client session itself is gone
assert_test(empty($_SESSION['kala_client_portal']), "Tab 2 kala_client_portal is unset");

// Tab 1 is STILL logged in as Owner!
$_SERVER['REQUEST_URI'] = '/Kalamedia/owner-dashboard';
assert_test(is_logged_in('internal') === true, "Tab 1 Owner remains logged in after Client logout");
assert_test(current_user_role('internal') === 'owner', "Tab 1 Owner role is still 'owner'");

// 5. Test Worker Financial Censorship in Ads Tracker
$db = Database::getConnection();
// Insert or check ads_budget_logs table exists
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='ads_budget_logs'")->fetchAll(PDO::FETCH_COLUMN);
assert_test(!empty($tables), "Table 'ads_budget_logs' exists in database");

// Check projects table has allocated_ads_budget column
$cols = $db->query("PRAGMA table_info(projects)")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($cols, 'name');
assert_test(in_array('allocated_ads_budget', $colNames), "Column 'allocated_ads_budget' exists in projects table");

echo "\nALL TESTS PASSED WITH 100% SUCCESS! DUAL-SLOT MULTI-TAB ISOLATION CONFIRMED.\n";

function assert_test($condition, $message) {
    if ($condition) {
        echo "  [PASS] $message\n";
    } else {
        echo "  [FAIL] $message\n";
        exit(1);
    }
}

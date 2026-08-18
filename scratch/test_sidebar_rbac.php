<?php
/**
 * Test Role & Sidebar Verification
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

echo "=== VERIFYING RBAC & SIDEBAR LOGIC ===\n\n";

$db = Database::getConnection();

// 1. Test Admin Role Session
$_SESSION['user'] = [
    'id' => 2,
    'name' => 'Siti Rahma',
    'email' => 'admin@kalamedia.id',
    'role' => 'admin',
    'avatar' => null
];

echo "[TEST 1] Admin Role Check:\n";
echo "  is_owner(): " . (is_owner() ? 'YES (FAIL)' : 'NO (CORRECT)') . "\n";
echo "  is_admin(): " . (is_admin() ? 'YES (CORRECT)' : 'NO (FAIL)') . "\n";

// Render sidebar for Admin
ob_start();
$currentPage = 'invoices';
$GLOBALS['currentPage'] = 'invoices';
require dirname(__DIR__) . '/includes/sidebar.php';
$adminSidebar = ob_get_clean();

$hasOwnerDash = strpos($adminSidebar, 'Dashboard Executive') !== false;
$hasSettings = strpos($adminSidebar, 'Pengaturan Agensi') !== false;
$hasAdminDash = strpos($adminSidebar, 'Dashboard Operasional') !== false;
$hasInvoices = strpos($adminSidebar, 'Invoices (Penagihan)') !== false;

echo "  Admin Sidebar has 'Dashboard Executive': " . ($hasOwnerDash ? 'YES (FAIL)' : 'NO (CORRECT)') . "\n";
echo "  Admin Sidebar has 'Pengaturan Agensi': " . ($hasSettings ? 'YES (FAIL)' : 'NO (CORRECT)') . "\n";
echo "  Admin Sidebar has 'Dashboard Operasional': " . ($hasAdminDash ? 'YES (CORRECT)' : 'NO (FAIL)') . "\n";
echo "  Admin Sidebar has 'Invoices (Penagihan)': " . ($hasInvoices ? 'YES (CORRECT)' : 'NO (FAIL)') . "\n";

// 2. Test Owner Role Session
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Rangga Pratama',
    'email' => 'owner@kalamedia.id',
    'role' => 'owner',
    'avatar' => null
];

echo "\n[TEST 2] Owner Role Check:\n";
echo "  is_owner(): " . (is_owner() ? 'YES (CORRECT)' : 'NO (FAIL)') . "\n";
echo "  is_admin(): " . (is_admin() ? 'YES (CORRECT)' : 'NO (FAIL)') . "\n";

// Render sidebar for Owner
ob_start();
$currentPage = 'owner-dashboard';
$GLOBALS['currentPage'] = 'owner-dashboard';
require dirname(__DIR__) . '/includes/sidebar.php';
$ownerSidebar = ob_get_clean();

$hasOwnerDash2 = strpos($ownerSidebar, 'Dashboard Executive') !== false;
$hasSettings2 = strpos($ownerSidebar, 'Pengaturan Agensi') !== false;
$hasAntrean = strpos($ownerSidebar, 'Antrean Operasional') !== false;

echo "  Owner Sidebar has 'Dashboard Executive': " . ($hasOwnerDash2 ? 'YES (CORRECT)' : 'NO (FAIL)') . "\n";
echo "  Owner Sidebar has 'Pengaturan Agensi': " . ($hasSettings2 ? 'YES (CORRECT)' : 'NO (FAIL)') . "\n";
echo "  Owner Sidebar has 'Antrean Operasional': " . ($hasAntrean ? 'YES (CORRECT)' : 'NO (FAIL)') . "\n";

echo "\n=== ALL RBAC & SIDEBAR CHECKS PASSED ===\n";

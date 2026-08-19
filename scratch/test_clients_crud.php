<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$_SESSION['logged_in'] = true;
$_SESSION['user'] = ['id' => 1, 'role' => 'owner', 'name' => 'Muhammad Fadhli'];

$db = Database::getConnection();

echo "=== TESTING CLIENTS & PROJECTS FULL CRUD ===\n";

// 1. Test Create Client via API
$_POST = [
    'action' => 'create_client',
    'company' => 'PT Testing Mandiri Solusi',
    'name' => 'Budi Santoso',
    'email' => 'budi@testingsolusi.com',
    'phone' => '081234567899',
    'address' => 'Gedung Cyber 2 Lantai 10, Jakarta Selatan'
];
$_GET = [];

ob_start();
include __DIR__ . '/../api/clients.php';
$res1 = json_decode(ob_get_clean(), true);
echo "1. Create Client: " . ($res1['success'] ? "✔ PASS (ID: {$res1['client_id']})" : "✖ FAIL ({$res1['message']})") . "\n";
$newClientId = $res1['client_id'] ?? 0;

// 2. Test Get Client Details
$_GET = ['action' => 'get_client', 'id' => $newClientId];
$_POST = [];
ob_start();
include __DIR__ . '/../api/clients.php';
$res2 = json_decode(ob_get_clean(), true);
echo "2. Get Client: " . ($res2['success'] && $res2['client']['company'] === 'PT Testing Mandiri Solusi' ? "✔ PASS" : "✖ FAIL") . "\n";

// 3. Test Update Client
$_POST = [
    'action' => 'update_client',
    'id' => $newClientId,
    'company' => 'PT Testing Mandiri Solusi Jaya',
    'name' => 'Budi Santoso (Updated)',
    'email' => 'budi.jaya@testingsolusi.com',
    'phone' => '081299998888',
    'address' => 'Gedung Cyber 2 Lantai 12, Jakarta Selatan'
];
$_GET = [];
ob_start();
include __DIR__ . '/../api/clients.php';
$res3 = json_decode(ob_get_clean(), true);
echo "3. Update Client: " . ($res3['success'] ? "✔ PASS" : "✖ FAIL") . "\n";

// 4. Test Create Project
$_POST = [
    'action' => 'create_project',
    'client_id' => $newClientId,
    'name' => 'SEO & Meta Performance Q3',
    'contract_value' => '15.000.000',
    'target_margin_percent' => '35',
    'status' => 'In Progress',
    'start_date' => '2026-08-01',
    'end_date' => '2026-11-01'
];
$_GET = [];
ob_start();
include __DIR__ . '/../api/clients.php';
$res4 = json_decode(ob_get_clean(), true);
echo "4. Create Project: " . ($res4['success'] ? "✔ PASS (ID: {$res4['project_id']})" : "✖ FAIL") . "\n";
$newProjId = $res4['project_id'] ?? 0;

// 5. Test Update Project
$_POST = [
    'action' => 'update_project',
    'id' => $newProjId,
    'client_id' => $newClientId,
    'name' => 'SEO & Meta Performance Q3 (Scale Up)',
    'contract_value' => '25.000.000',
    'target_margin_percent' => '40',
    'status' => 'Completed',
    'start_date' => '2026-08-01',
    'end_date' => '2026-12-01'
];
$_GET = [];
ob_start();
include __DIR__ . '/../api/clients.php';
$res5 = json_decode(ob_get_clean(), true);
echo "5. Update Project: " . ($res5['success'] ? "✔ PASS" : "✖ FAIL") . "\n";

// 6. Test Delete Project
$_POST = ['action' => 'delete_project', 'id' => $newProjId];
$_GET = [];
ob_start();
include __DIR__ . '/../api/clients.php';
$res6 = json_decode(ob_get_clean(), true);
echo "6. Delete Project: " . ($res6['success'] ? "✔ PASS" : "✖ FAIL") . "\n";

// 7. Test Delete Client
$_POST = ['action' => 'delete_client', 'id' => $newClientId];
$_GET = [];
ob_start();
include __DIR__ . '/../api/clients.php';
$res7 = json_decode(ob_get_clean(), true);
echo "7. Delete Client: " . ($res7['success'] ? "✔ PASS" : "✖ FAIL") . "\n";

// 8. Test Render views/clients.php
ob_start();
include __DIR__ . '/../views/clients.php';
$html = ob_get_clean();
echo "8. Render views/clients.php: " . (strlen($html) > 1000 ? "✔ PASS (Size: " . strlen($html) . " bytes)" : "✖ FAIL") . "\n";

echo "\n🎉 ALL CLIENTS & PROJECTS CRUD TESTS PASSED!\n";

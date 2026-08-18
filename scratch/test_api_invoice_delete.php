<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/auth.php';

$db = Database::getConnection();

// Create a temporary invoice to test delete
$stmt = $db->prepare("INSERT INTO invoices (invoice_number, client_id, project_id, issue_date, due_date, subtotal, tax_amount, discount_amount, total_amount, status) VALUES ('INV-TEST-001', 1, 1, '2026-08-18', '2026-08-30', 100000, 0, 0, 100000, 'Draft')");
$stmt->execute();
$testInvId = $db->lastInsertId();

echo "Created test invoice ID: $testInvId\n";

// Test 1: As Owner
$_SESSION['logged_in'] = true;
$_SESSION['user'] = ['id' => 1, 'name' => 'Rangga Pratama', 'email' => 'rangga@kalamedia.id', 'role' => 'owner'];
$_SESSION['active_role'] = 'owner';

echo "is_owner() result: " . (is_owner() ? 'TRUE' : 'FALSE') . "\n";

$_GET['action'] = 'delete';
$_POST['invoice_id'] = $testInvId;

ob_start();
require __DIR__ . '/../api/invoices.php';
$res = ob_get_clean();

echo "API Response: $res\n";

// Check if soft deleted in DB
$check = $db->query("SELECT is_deleted FROM invoices WHERE id = $testInvId")->fetchColumn();
echo "is_deleted in DB: " . var_export($check, true) . "\n";

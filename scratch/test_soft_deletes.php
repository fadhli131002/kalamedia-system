<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

// Create dummy test record in invoices
$db->exec("INSERT INTO invoices (invoice_number, client_id, issue_date, due_date, subtotal, total_amount, status, is_deleted) VALUES ('INV-TEST-SOFT', 1, '2026-08-18', '2026-08-25', 1000000, 1000000, 'Sent', 0)");
$testId = $db->lastInsertId();

// Verify it exists with is_deleted = 0
$check1 = $db->query("SELECT COUNT(*) FROM invoices WHERE id = $testId AND COALESCE(is_deleted, 0) = 0")->fetchColumn();
echo "Before soft delete active count: $check1 (Expected: 1)\n";

// Execute soft delete
$db->exec("UPDATE invoices SET is_deleted = 1 WHERE id = $testId");

// Verify it is excluded when filtering is_deleted = 0
$check2 = $db->query("SELECT COUNT(*) FROM invoices WHERE id = $testId AND COALESCE(is_deleted, 0) = 0")->fetchColumn();
echo "After soft delete active count: $check2 (Expected: 0)\n";

// Verify row still exists in DB
$check3 = $db->query("SELECT is_deleted FROM invoices WHERE id = $testId")->fetchColumn();
echo "Row in DB is_deleted value: $check3 (Expected: 1)\n";

// Clean up test record
$db->exec("DELETE FROM invoices WHERE id = $testId");
echo "Test completed successfully!\n";

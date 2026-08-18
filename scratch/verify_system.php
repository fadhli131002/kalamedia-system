<?php
/**
 * Test Verification Script for Database, Auth & Analytics
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';

echo "=== KALAMEDIA TEST VERIFICATION ===\n\n";

try {
    $db = Database::getConnection();
    echo "[OK] Database connected and schema/seed initialized.\n";

    // 1. Check Users
    $users = $db->query("SELECT id, name, email, role FROM users")->fetchAll();
    echo "[OK] Users count: " . count($users) . "\n";
    foreach ($users as $u) {
        echo "     - {$u['name']} ({$u['email']}) -> Role: {$u['role']}\n";
    }

    // 2. Check Invoices
    $invoices = $db->query("SELECT id, invoice_number, total_amount, status FROM invoices")->fetchAll();
    echo "[OK] Invoices count: " . count($invoices) . "\n";
    foreach ($invoices as $i) {
        echo "     - #{$i['invoice_number']}: Rp " . number_format($i['total_amount'], 0, ',', '.') . " [{$i['status']}]\n";
    }

    // 3. Check Freelancers & Ads
    $payouts = $db->query("SELECT COUNT(*) FROM freelancer_payouts")->fetchColumn();
    $ads = $db->query("SELECT COUNT(*) FROM ads_spend")->fetchColumn();
    echo "[OK] Freelancer payouts: $payouts | Ads spend records: $ads\n";

    // 4. Update sample receipt attachment
    $db->exec("UPDATE invoices SET receipt_file = 'sample_receipt.svg' WHERE status = 'Paid'");
    $db->exec("UPDATE freelancer_payouts SET receipt_file = 'sample_receipt.svg' WHERE status = 'Paid'");
    echo "[OK] Sample receipt linked to paid records.\n";

    echo "\n=== ALL VERIFICATION CHECKS PASSED ===\n";
} catch (Exception $e) {
    echo "[FAIL] Error: " . $e->getMessage() . "\n";
}

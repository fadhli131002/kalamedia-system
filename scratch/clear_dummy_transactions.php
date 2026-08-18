<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';

$db = Database::getConnection();

echo "=== CLEARING DUMMY TRANSACTION DATABASE ===\n";

try {
    $db->beginTransaction();

    // 1. Delete invoice items & invoices
    $db->exec("DELETE FROM invoice_items");
    $db->exec("DELETE FROM invoices");

    // 2. Delete freelancer payouts
    $db->exec("DELETE FROM freelancer_payouts");

    // 3. Delete ads spend
    $db->exec("DELETE FROM ads_spend");

    // 4. Delete activities
    $db->exec("DELETE FROM activities");

    // 5. Reset auto-increment counters in sqlite_sequence
    $db->exec("DELETE FROM sqlite_sequence WHERE name IN ('invoices', 'invoice_items', 'freelancer_payouts', 'ads_spend', 'activities')");

    $db->commit();
    echo "[SUCCESS] All dummy transactions (invoices, payouts, ads spend, activities) have been cleared.\n";

    // Clean up uploaded test receipts
    $files = glob(dirname(__DIR__) . '/assets/uploads/receipts/*');
    $deletedFiles = 0;
    foreach ($files as $file) {
        if (is_file($file) && basename($file) !== 'sample_receipt.svg') {
            unlink($file);
            $deletedFiles++;
        }
    }
    echo "[SUCCESS] Cleaned $deletedFiles uploaded test receipt files.\n";

    // Check counts
    $invCount = $db->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
    $payoutCount = $db->query("SELECT COUNT(*) FROM freelancer_payouts")->fetchColumn();
    $adsCount = $db->query("SELECT COUNT(*) FROM ads_spend")->fetchColumn();
    $actCount = $db->query("SELECT COUNT(*) FROM activities")->fetchColumn();

    echo "[INFO] Current Invoices: $invCount | Payouts: $payoutCount | Ads Spend: $adsCount | Activities: $actCount\n";
    echo "[INFO] User accounts intact: " . $db->query("SELECT COUNT(*) FROM users")->fetchColumn() . " accounts.\n";
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo "[ERROR] Failed to clear transactions: " . $e->getMessage() . "\n";
}

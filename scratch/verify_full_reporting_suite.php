<?php
/**
 * Kalamedia Full-Funnel Performance Marketing Suite - End-to-End Verification
 */

session_start();
$_SESSION['logged_in'] = true;
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Muhammad Fadhli',
    'email' => 'owner@kalamedia.id',
    'role' => 'owner'
];

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$db = Database::getConnection();

echo "=== 1. VERIFY DATABASE SCHEMA & SEED DATA ===\n";
$stmt = $db->query("
    SELECT r.id, r.client_id, r.report_period, r.objective, r.total_ad_spend, r.revenue, r.roas, r.total_conversions, r.cpl_cpa,
           r.winning_content_url, r.underperforming_content_url, c.company, c.name as client_pic
    FROM performance_reports r
    JOIN clients c ON r.client_id = c.id
    WHERE COALESCE(r.is_deleted, 0) = 0
    ORDER BY r.id ASC
");
$seeds = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($seeds) < 2) {
    echo "ERROR: Expected at least 2 seed reports, found " . count($seeds) . "\n";
    exit(1);
}

echo "Found " . count($seeds) . " seeded performance reports:\n";
foreach ($seeds as $s) {
    echo "• [ID: {$s['id']}] {$s['company']} | {$s['report_period']} | Obj: {$s['objective']}\n";
    echo "  Spend: Rp " . number_format($s['total_ad_spend']) . " | Revenue: Rp " . number_format($s['revenue']) . " | ROAS: {$s['roas']}x | Conv: {$s['total_conversions']} | CPA: Rp " . number_format($s['cpl_cpa']) . "\n";
    echo "  Winning: {$s['winning_content_url']} | Underperforming: {$s['underperforming_content_url']}\n";
}

echo "\n=== 2. VERIFY CRUD API (CREATE, READ, UPDATE, DELETE) ===\n";

// Test Create
$insertStmt = $db->prepare("
    INSERT INTO performance_reports (
        client_id, report_period, objective,
        total_ad_spend, revenue, roas, roi, total_conversions, cpl_cpa,
        ads_reach, ads_impressions, ads_ctr, ads_cpc, ads_cpm,
        lost_is_rank, lost_is_budget, ads_evaluation,
        content_identity, total_views, followers_gained, avg_video_retention, engagement_rate,
        what_worked, what_didnt_work, next_action_plan,
        created_at, updated_at, is_deleted
    ) VALUES (
        ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?,
        datetime('now', 'localtime'), datetime('now', 'localtime'), 0
    )
");
$insertStmt->execute([
    $seeds[0]['client_id'],
    'September 2026',
    'Scaling Test Campaign',
    20000000.00,
    160000000.00,
    8.00,
    700.00,
    400,
    50000.00,
    150000,
    450000,
    4.20,
    1100.00,
    44000.00,
    3.50,
    8.00,
    'Scaling test ads evaluation notes.',
    'UGC Video Concept 1',
    200000,
    1500,
    58.00,
    6.50,
    'UGC video format worked very well.',
    'Static image was not profitable.',
    'Scale budget 50%.'
]);

$testId = $db->lastInsertId();
echo "Created Test Performance Report ID: {$testId}\n";

// Test Read
$testRead = $db->query("SELECT * FROM performance_reports WHERE id = {$testId}")->fetch(PDO::FETCH_ASSOC);
if (!$testRead || $testRead['roas'] != 8.00) {
    echo "ERROR: Failed to read created test report!\n";
    exit(1);
}
echo "Read verified: ROAS = {$testRead['roas']}x, Spend = Rp " . number_format($testRead['total_ad_spend']) . "\n";

// Test Update
$updateStmt = $db->prepare("UPDATE performance_reports SET revenue = 200000000, roas = 10.00 WHERE id = ?");
$updateStmt->execute([$testId]);
$testUpdated = $db->query("SELECT roas, revenue FROM performance_reports WHERE id = {$testId}")->fetch(PDO::FETCH_ASSOC);
if ($testUpdated['roas'] != 10.00) {
    echo "ERROR: Failed to update test report!\n";
    exit(1);
}
echo "Update verified: New ROAS = {$testUpdated['roas']}x, New Revenue = Rp " . number_format($testUpdated['revenue']) . "\n";

// Test Soft Delete
$delStmt = $db->prepare("UPDATE performance_reports SET is_deleted = 1 WHERE id = ?");
$delStmt->execute([$testId]);
$delCheck = $db->query("SELECT COUNT(*) FROM performance_reports WHERE id = {$testId} AND COALESCE(is_deleted, 0) = 0")->fetchColumn();
if ($delCheck != 0) {
    echo "ERROR: Soft delete failed!\n";
    exit(1);
}
echo "Soft delete verified: Active record count = 0\n";

// Cleanup test row completely
$db->exec("DELETE FROM performance_reports WHERE id = {$testId}");
echo "Cleaned up test row.\n";

echo "\n=== 3. VERIFY VIEW RENDERING ===\n";

// Test render reports.php
ob_start();
require __DIR__ . '/../views/reports.php';
$reportsHtml = ob_get_clean();
echo "Rendered views/reports.php: " . strlen($reportsHtml) . " bytes\n";
if (strpos($reportsHtml, 'Laporan Kinerja Full-Funnel Performance Marketing') === false) {
    echo "ERROR: views/reports.php missing title!\n";
    exit(1);
}

// Test render reports_form.php (New)
$_GET['id'] = '';
ob_start();
require __DIR__ . '/../views/reports_form.php';
$formNewHtml = ob_get_clean();
echo "Rendered views/reports_form.php (New): " . strlen($formNewHtml) . " bytes\n";
if (strpos($formNewHtml, 'Business Summary & Full-Funnel Economics') === false) {
    echo "ERROR: views/reports_form.php missing tab content!\n";
    exit(1);
}

// Test render reports_form.php (Edit)
$_GET['id'] = $seeds[0]['id'];
ob_start();
require __DIR__ . '/../views/reports_form.php';
$formEditHtml = ob_get_clean();
echo "Rendered views/reports_form.php (Edit ID {$seeds[0]['id']}): " . strlen($formEditHtml) . " bytes\n";

// Test render report_deck.php
$_GET['id'] = $seeds[0]['id'];
ob_start();
require __DIR__ . '/../views/report_deck.php';
$deckHtml = ob_get_clean();
echo "Rendered views/report_deck.php (Deck ID {$seeds[0]['id']}): " . strlen($deckHtml) . " bytes\n";
if (strpos($deckHtml, 'QBR PERFORMANCE REPORT') === false || strpos($deckHtml, 'Creative Showdown') === false) {
    echo "ERROR: views/report_deck.php missing deck sections!\n";
    exit(1);
}

echo "\n============================================\n";
echo "100% SUCCESS: All Full-Funnel Reporting components verified!\n";
echo "============================================\n";

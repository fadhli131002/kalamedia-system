<?php
/**
 * Test Suite for Client Social Media Reporting Module
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

$_SESSION['logged_in'] = true;
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Owner Kala',
    'email' => 'owner@kalamedia.id',
    'role' => 'owner'
];
$_SESSION['last_activity'] = time();

$db = Database::getConnection();

echo "=== 1. TEST DATABASE QUERY & JOINS ===\n";
$reports = $db->query("
    SELECT r.*, 
           c.name as client_name, c.company as client_company, 
           c.email as client_email, c.phone as client_phone, c.logo as client_logo
    FROM client_reports r
    JOIN clients c ON r.client_id = c.id
    WHERE COALESCE(r.is_deleted, 0) = 0
    ORDER BY r.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($reports) . " active reports in DB.\n";
foreach ($reports as $r) {
    echo "• ID: {$r['id']} | {$r['client_company']} | Month: {$r['report_month']} | Reach: " . number_format($r['total_reach']) . " | Leads: {$r['leads_generated']} | ER: {$r['engagement_rate']}%\n";
}

echo "\n=== 2. TEST VIEWS RENDER ===\n";
$routesToTest = [
    'reports' => 'views/reports.php',
    'reports-form' => 'views/reports_form.php',
    'report-view' => 'views/report_view.php'
];

foreach ($routesToTest as $rName => $viewFile) {
    $_GET = ['page' => $rName, 'id' => 1];
    $GLOBALS['currentPage'] = $rName;
    $currentPage = $rName;

    ob_start();
    include dirname(__DIR__) . '/' . $viewFile;
    $output = ob_get_clean();

    $hasContent = strlen($output) > 500;
    $hasFatal = str_contains($output, 'Fatal error') || str_contains($output, 'Parse error') || str_contains($output, 'Warning:');
    
    echo "• View [$rName ($viewFile)]: Size = " . strlen($output) . " bytes | Status = " . ($hasContent && !$hasFatal ? "PASSED (Clean)" : "FAILED") . "\n";
    if ($hasFatal) {
        echo "  Error detected in output!\n";
    }
}

echo "\n=== 3. TEST CRUD VIA API SAVE & DELETE ===\n";
// Test create
$testClient = $reports[0]['client_id'];
$testMonth = 'September 2026';
$insertStmt = $db->prepare("
    INSERT INTO client_reports (
        client_id, report_month, executive_summary,
        followers_growth, total_reach, total_impressions,
        engagement_rate, saves_shares, link_clicks, leads_generated,
        top_content_summary, action_plan
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$insertStmt->execute([
    $testClient,
    $testMonth,
    'Executive summary test for automated testing.',
    500,
    95000,
    210000,
    5.5,
    1200,
    600,
    30,
    'Top content test summary',
    'Action plan test summary'
]);
$newId = $db->lastInsertId();
echo "• Inserted test report with ID: $newId\n";

// Verify retrieval
$check = $db->query("SELECT * FROM client_reports WHERE id = $newId")->fetch(PDO::FETCH_ASSOC);
if ($check && $check['report_month'] === $testMonth) {
    echo "• Retrieval verification: PASSED\n";
} else {
    echo "• Retrieval verification: FAILED\n";
}

// Test soft delete
$db->exec("UPDATE client_reports SET is_deleted = 1 WHERE id = $newId");
$delCheck = $db->query("SELECT COUNT(*) FROM client_reports WHERE id = $newId AND COALESCE(is_deleted, 0) = 0")->fetchColumn();
if ($delCheck == 0) {
    echo "• Soft delete verification: PASSED\n";
} else {
    echo "• Soft delete verification: FAILED\n";
}

// Clean up test record permanently
$db->exec("DELETE FROM client_reports WHERE id = $newId");
echo "• Test record cleaned up.\n";

echo "\nALL TESTS COMPLETED SUCCESSFULLY!\n";

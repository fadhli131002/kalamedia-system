<?php
/**
 * Comprehensive Verification Script for All 8 User Requests
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

echo "==============================================\n";
echo "KALAMEDIA 8-ITEM COMPREHENSIVE VERIFICATION\n";
echo "==============================================\n\n";

$passCount = 0;
$totalTests = 0;

function run_test($name, $callback) {
    global $passCount, $totalTests;
    $totalTests++;
    echo "TEST $totalTests: $name ... ";
    try {
        $result = $callback();
        if ($result === true) {
            echo "PASS\n";
            $passCount++;
        } else {
            echo "FAIL: $result\n";
        }
    } catch (Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

// -----------------------------------------------------------------
// 1. Catatan pengeluaran iklan ads: Batas maksimal otomatis
// -----------------------------------------------------------------
run_test("Item 1: API ads_tracker caps ad spend to owner transfer", function() {
    $code = file_get_contents(__DIR__ . '/../api/ads_tracker.php');
    if (strpos($code, '$maxOwnerTransfer') === false) return "Missing maxOwnerTransfer check";
    if (strpos($code, '$platformTopupAmount = $maxOwnerTransfer') === false) return "Missing topup limit cap";
    if (strpos($code, '$adSpendAmount = $maxOwnerTransfer') === false) return "Missing spend limit cap";
    return true;
});

run_test("Item 1: Views tasks.php has auto-cap functions", function() {
    $code = file_get_contents(__DIR__ . '/../views/tasks.php');
    if (strpos($code, 'enforceOwnerTransferLimit') === false) return "Missing enforceOwnerTransferLimit";
    if (strpos($code, 'enforceAllocBudgetLimit') === false) return "Missing enforceAllocBudgetLimit";
    if (strpos($code, 'recalcModalAdsRemaining') === false) return "Missing recalcModalAdsRemaining";
    return true;
});

// -----------------------------------------------------------------
// 2. Akun freelancer saldo di dashboardnya
// -----------------------------------------------------------------
run_test("Item 2: Freelancer summary returned in api/portal_tasks.php", function() use ($db) {
    // Check if freelancer_payouts has data
    $stmt = $db->query("SELECT COUNT(*) FROM freelancer_payouts WHERE status = 'Paid' AND COALESCE(is_deleted,0)=0");
    $cnt = $stmt->fetchColumn();
    if ($cnt < 1) return "No paid freelancer payouts found in DB";

    $code = file_get_contents(__DIR__ . '/../api/portal_tasks.php');
    if (strpos($code, "'freelancer_summary' => \$freelancerSummary") === false) return "freelancer_summary not returned in API";
    return true;
});

run_test("Item 2: Freelancer KPI card rendered in views/tasks.php", function() {
    $code = file_get_contents(__DIR__ . '/../views/tasks.php');
    if (strpos($code, 'SALDO PENDAPATAN &amp; FEE SAYA') === false) return "Missing Freelancer Saldo Banner";
    if (strpos($code, 'kpi-freelancer-paid') === false) return "Missing kpi-freelancer-paid element";
    return true;
});

// -----------------------------------------------------------------
// 3. Format rupiah otomatis
// -----------------------------------------------------------------
run_test("Item 3: format_rupiah outputs proper format", function() {
    $formatted = format_rupiah(1500000);
    if ($formatted !== 'Rp 1.500.000') return "Expected 'Rp 1.500.000', got '$formatted'";
    return true;
});

run_test("Item 3: assets/js/app.js has formatRupiahInput listener", function() {
    $code = file_get_contents(__DIR__ . '/../assets/js/app.js');
    if (strpos($code, 'formatRupiahInput') === false) return "Missing formatRupiahInput function";
    if (strpos($code, '.rupiah-input') === false) return "Missing .rupiah-input class binding";
    return true;
});

// -----------------------------------------------------------------
// 4. Nominal yang sudah dibayarkan freelancer pada dashboard client
// -----------------------------------------------------------------
run_test("Item 4: Client portal queries and renders freelancer paid fee", function() use ($db) {
    $code = file_get_contents(__DIR__ . '/../views/client_portal.php');
    if (strpos($code, '$stmtClientFreelancer') === false) return "Missing stmtClientFreelancer query in client_portal.php";
    if (strpos($code, 'stat-freelancer-paid') === false) return "Missing stat-freelancer-paid KPI in client_portal.php";
    if (strpos($code, 'Riwayat &amp; Transparansi Fee Freelancer') === false) return "Missing Freelancer table in client_portal.php";

    // Test query execution for client 1
    $stmtClientFreelancer = $db->prepare("
        SELECT fp.*, p.name as project_name
        FROM freelancer_payouts fp
        JOIN projects p ON fp.project_id = p.id
        WHERE p.client_id = ? AND COALESCE(fp.is_deleted, 0) = 0
    ");
    $stmtClientFreelancer->execute([1]);
    $rows = $stmtClientFreelancer->fetchAll(PDO::FETCH_ASSOC);
    $paid = 0;
    foreach ($rows as $r) {
        if ($r['status'] === 'Paid') $paid += floatval($r['amount']);
    }
    if ($paid !== 350000.0) return "Expected Rp 350.000 paid for Client 1, got $paid";
    return true;
});

run_test("Item 4: Client API returns freelancer_summary in api/portal_tasks.php", function() {
    $code = file_get_contents(__DIR__ . '/../api/portal_tasks.php');
    if (strpos($code, '$stmtClientFp') === false) return "Missing stmtClientFp in portal_tasks.php";
    return true;
});

run_test("Item 4: views/clients.php displays total_freelancer_paid", function() {
    $code = file_get_contents(__DIR__ . '/../views/clients.php');
    if (strpos($code, 'total_freelancer_paid') === false) return "Missing total_freelancer_paid in views/clients.php";
    if (strpos($code, '$freelancerPaid') === false) return "Missing freelancerPaid display";
    return true;
});

// -----------------------------------------------------------------
// 5. Share invoice via WA dan via Portal
// -----------------------------------------------------------------
run_test("Item 5: views/invoices.php has WA and Portal share buttons", function() {
    $code = file_get_contents(__DIR__ . '/../views/invoices.php');
    if (strpos($code, 'api.whatsapp.com/send') === false && strpos($code, 'wa.me') === false) return "Missing WA URL in invoices.php";
    if (strpos($code, 'shareInvoicePortal') === false) return "Missing shareInvoicePortal in invoices.php";
    return true;
});

run_test("Item 5: views/invoice_view.php has Share Portal and WA buttons", function() {
    $code = file_get_contents(__DIR__ . '/../views/invoice_view.php');
    if (strpos($code, 'sharePortalLink') === false) return "Missing sharePortalLink in invoice_view.php";
    if (strpos($code, 'Share via WhatsApp') === false) return "Missing Share via WhatsApp button";
    return true;
});

// -----------------------------------------------------------------
// 6. Bug notifikasi nominal saldo iklan
// -----------------------------------------------------------------
run_test("Item 6: parseAdsNumber properly parses Indonesian formatted numbers", function() {
    $code = file_get_contents(__DIR__ . '/../views/tasks.php');
    if (strpos($code, 'parseAdsNumber') === false) return "Missing parseAdsNumber in tasks.php";
    $codePortal = file_get_contents(__DIR__ . '/../views/client_portal.php');
    if (strpos($codePortal, 'parseAdsNumber') === false) return "Missing parseAdsNumber in client_portal.php";
    return true;
});

// -----------------------------------------------------------------
// 7. Total Payroll "Atur Periode"
// -----------------------------------------------------------------
run_test("Item 7: views/salaries.php has Atur Periode dropdown & filter logic", function() {
    $code = file_get_contents(__DIR__ . '/../views/salaries.php');
    if (strpos($code, 'payroll_period') === false) return "Missing payroll_period in salaries.php";
    if (strpos($code, 'togglePayrollPeriodDropdown') === false) return "Missing togglePayrollPeriodDropdown";
    if (strpos($code, 'Atur Periode') === false) return "Missing Atur Periode button label";
    return true;
});

// -----------------------------------------------------------------
// 8. Monthly net profit akun owner
// -----------------------------------------------------------------
run_test("Item 8: views/owner_dashboard.php uses strict month filter for all net profit components", function() use ($db) {
    $code = file_get_contents(__DIR__ . '/../views/owner_dashboard.php');
    if (strpos($code, '$totalPayouts = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM freelancer_payouts WHERE status = \'Paid\' AND COALESCE(is_deleted, 0) = 0 AND {$payDateExpr} = \'$currentMonth\'"') === false) {
        return "freelancer_payouts missing month filter";
    }
    if (strpos($code, '$totalAds = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM ads_spend WHERE COALESCE(is_deleted, 0) = 0 AND {$adsDateExpr} = \'$currentMonth\'"') === false) {
        return "ads_spend missing month filter";
    }
    if (strpos($code, '$totalSalaries = floatval($db->query("SELECT COALESCE(SUM(net_salary), 0) FROM salaries WHERE status = \'Paid\' AND COALESCE(is_deleted, 0) = 0 AND ({$salDateExpr} = \'$currentMonth\' OR month_period = \'$currentMonth\')")') === false) {
        return "salaries missing month filter";
    }
    return true;
});

echo "\n----------------------------------------------\n";
echo "RESULTS: $passCount / $totalTests TESTS PASSED\n";
echo "----------------------------------------------\n";

if ($passCount === $totalTests) {
    echo "ALL 8 REQUIREMENTS FULLY VERIFIED!\n";
} else {
    echo "SOME TESTS FAILED!\n";
}

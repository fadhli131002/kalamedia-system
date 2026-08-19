<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$_SESSION['logged_in'] = true;
$_SESSION['user'] = ['id' => 1, 'role' => 'owner', 'name' => 'Muhammad Fadhli'];

$db = Database::getConnection();

echo "=== TESTING SALARY SLIP & FREELANCER VOUCHER APIS ===\n";

// 1. Test get_slip_data
$salId = $db->query("SELECT id FROM salaries WHERE COALESCE(is_deleted, 0) = 0 ORDER BY id DESC LIMIT 1")->fetchColumn();
if ($salId) {
    $_GET = ['action' => 'get_slip_data', 'id' => $salId];
    ob_start();
    include __DIR__ . '/../api/salaries.php';
    $res = json_decode(ob_get_clean(), true);
    if ($res && $res['success']) {
        echo "1. Get Slip Gaji Data (ID: $salId): ✔ PASS ({$res['salary']['employee_name']} - Net: {$res['formatted']['net_salary']})\n";
    } else {
        echo "1. Get Slip Gaji Data (ID: $salId): ✖ FAIL (" . json_encode($res) . ")\n";
    }
} else {
    echo "1. Get Slip Gaji Data: ⚠️ No salary records found in DB\n";
}

// 2. Test get_payout_voucher
$payoutId = $db->query("SELECT id FROM freelancer_payouts WHERE COALESCE(is_deleted, 0) = 0 ORDER BY id DESC LIMIT 1")->fetchColumn();
if ($payoutId) {
    $_GET = ['action' => 'get_payout_voucher', 'id' => $payoutId];
    ob_start();
    include __DIR__ . '/../api/expenses.php';
    $res = json_decode(ob_get_clean(), true);
    if ($res && $res['success']) {
        echo "2. Get Freelancer Voucher (ID: $payoutId): ✔ PASS ({$res['payout']['freelancer_name']} - Amount: {$res['formatted']['amount']} - Voucher: {$res['formatted']['voucher_number']})\n";
    } else {
        echo "2. Get Freelancer Voucher (ID: $payoutId): ✖ FAIL (" . json_encode($res) . ")\n";
    }
} else {
    echo "2. Get Freelancer Voucher: ⚠️ No payout records found in DB\n";
}

echo "\nTest Completed!\n";

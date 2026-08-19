<?php
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

$_GET['action'] = 'list';

register_shutdown_function(function() {
    $out = ob_get_contents();
    $data = json_decode($out, true);
    if ($data) {
        echo "API SUCCESS: " . ($data['success'] ? 'true' : 'false') . "\n";
        echo "Reports count: " . ($data['total'] ?? 0) . "\n";
        echo "Blended ROAS: " . ($data['summary']['blended_roas'] ?? 0) . "x\n";
        echo "Total Revenue: Rp " . number_format($data['summary']['total_revenue'] ?? 0) . "\n";
        echo "Total Ad Spend: Rp " . number_format($data['summary']['total_spend'] ?? 0) . "\n";
        echo "First Report Client: " . ($data['data'][0]['client_company'] ?? '') . "\n";
        echo "First Report Objective: " . ($data['data'][0]['objective'] ?? '') . "\n";
    } else {
        echo "Raw output: " . $out . "\n";
    }
});
ob_start();
require __DIR__ . '/../api/reports.php';

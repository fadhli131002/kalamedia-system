<?php
session_start();
$_SESSION['logged_in'] = true;
$_SESSION['user'] = ['id' => 1, 'role' => 'owner', 'name' => 'Owner Kala'];
$_GET['action'] = 'project_financial_breakdown';
$_GET['project_id'] = 1;

include __DIR__ . '/../api/analytics.php';

echo "RESPONSE:\n" . $json . "\n";
$data = json_decode($json, true);
if ($data && isset($data['success']) && $data['success']) {
    echo "\nTEST PASSED: Project financial breakdown data returned successfully!\n";
    echo "Project: " . $data['project']['name'] . "\n";
    echo "Contract Value: " . $data['financials']['formatted']['contract_value'] . "\n";
    echo "Production Cost: " . $data['financials']['formatted']['production_cost'] . "\n";
    echo "Net Profit: " . $data['financials']['formatted']['net_profit'] . "\n";
    echo "Margin: " . $data['financials']['formatted']['margin_percent'] . "\n";
    echo "Invoices Count: " . count($data['invoices']) . "\n";
    echo "Freelancers Count: " . count($data['freelancers']) . "\n";
    echo "Ads Count: " . count($data['ads']) . "\n";
} else {
    echo "\nTEST FAILED!\n";
}

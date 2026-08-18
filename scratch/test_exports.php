<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$_SESSION['logged_in'] = true;
$_SESSION['user'] = ['id' => 1, 'name' => 'Owner', 'role' => 'owner'];
$_SESSION['accounts']['owner'] = $_SESSION['user'];

// 1. Test CSV
ob_start();
$_GET['action'] = 'export_csv';
include __DIR__ . '/../api/analytics.php';
$csv = ob_get_clean();

echo "--- CSV (First 3 lines) ---\n";
$lines = explode("\n", $csv);
for ($i = 0; $i < min(4, count($lines)); $i++) {
    echo "Line $i: " . $lines[$i] . "\n";
}

// 2. Test Excel
ob_start();
$_GET['action'] = 'export_excel';
include __DIR__ . '/../api/analytics.php';
$xls = ob_get_clean();

echo "\n--- Excel HTML Length: " . strlen($xls) . " bytes ---\n";
echo "Contains table: " . (strpos($xls, '<table') !== false ? 'YES' : 'NO') . "\n";

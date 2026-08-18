<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$_SESSION['logged_in'] = true;
$_SESSION['user'] = ['id' => 1, 'name' => 'Owner', 'role' => 'owner'];
$_SESSION['accounts']['owner'] = $_SESSION['user'];

ob_start();
$_GET['action'] = 'export_csv';
include __DIR__ . '/../api/analytics.php';
$csvOutput = ob_get_clean();

echo "CSV Output Length: " . strlen($csvOutput) . " bytes\n";
echo "First 400 chars of CSV:\n";
echo substr($csvOutput, 0, 400) . "\n";

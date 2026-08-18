<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$_SESSION['logged_in'] = true;
$_SESSION['user'] = ['id' => 1, 'name' => 'Owner', 'role' => 'owner'];
$_SESSION['accounts']['owner'] = $_SESSION['user'];

ob_start();
$_GET['action'] = 'export_pdf';
include __DIR__ . '/../api/analytics.php';
$html = ob_get_clean();

echo "PDF Report Length: " . strlen($html) . " bytes\n";
echo "Contains DOCTYPE: " . (strpos($html, '<!DOCTYPE html>') !== false ? 'YES' : 'NO') . "\n";
echo "Contains top-action-bar: " . (strpos($html, 'top-action-bar') !== false ? 'YES' : 'NO') . "\n";
echo "Contains report-canvas: " . (strpos($html, 'report-canvas') !== false ? 'YES' : 'NO') . "\n";

<?php
session_start();
$_SESSION['logged_in'] = true;
$_SESSION['user'] = ['id' => 1, 'role' => 'owner', 'name' => 'Owner Kala'];
$_GET['action'] = 'test_gcal_webhook';

ob_start();
include __DIR__ . '/../api/content.php';
$output = ob_get_clean();

echo "TEST GCAL WEBHOOK ENDPOINT OUTPUT:\n" . $output . "\n";

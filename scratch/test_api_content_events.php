<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/auth.php';

$_SESSION['logged_in'] = true;
$_SESSION['user'] = ['id' => 1, 'name' => 'Rangga Pratama', 'email' => 'rangga@kalamedia.id', 'role' => 'owner'];

$_GET['action'] = 'get_events';
$_GET['start'] = date('Y-m-01T00:00:00Z');
$_GET['end'] = date('Y-m-31T23:59:59Z');

ob_start();
require __DIR__ . '/../api/content.php';
$json = ob_get_clean();

$data = json_decode($json, true);
echo "Fetched events count: " . count($data) . "\n";
if (!empty($data)) {
    echo "First event title: " . $data[0]['title'] . "\n";
    echo "First event start: " . $data[0]['start'] . "\n";
    echo "First event platform: " . $data[0]['extendedProps']['platform'] . "\n";
    echo "First event status: " . $data[0]['extendedProps']['status'] . "\n";
    echo "First event client: " . $data[0]['extendedProps']['client_company'] . "\n";
}

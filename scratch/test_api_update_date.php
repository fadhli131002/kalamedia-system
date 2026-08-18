<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/auth.php';

$_SESSION['logged_in'] = true;
$_SESSION['user'] = ['id' => 1, 'name' => 'Rangga Pratama', 'email' => 'rangga@kalamedia.id', 'role' => 'owner'];

// Test update_date
$_GET = [];
$_POST = [
    'action' => 'update_date',
    'id' => 1,
    'publish_date' => '2026-08-19',
    'publish_time' => '11:30'
];

ob_start();
require __DIR__ . '/../api/content.php';
$json = ob_get_clean();

echo "Update Date Response: " . $json . "\n";

// Check updated date in DB
$db = Database::getConnection();
$row = $db->query("SELECT id, title, publish_date, publish_time FROM content_planner WHERE id = 1")->fetch();
echo "Updated DB Record: ID={$row['id']}, Title={$row['title']}, Date={$row['publish_date']}, Time={$row['publish_time']}\n";

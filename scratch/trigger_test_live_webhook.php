<?php
require_once __DIR__ . '/../config/app.php';
$_SESSION['logged_in'] = true;
$_SESSION['user'] = ['id' => 1, 'role' => 'owner', 'name' => 'Owner Kala'];
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/content.php';

echo "Sending live webhook to: " . GCAL_WEBHOOK_URL . "\n";
$res = sendGoogleCalendarWebhook(1);
print_r($res);

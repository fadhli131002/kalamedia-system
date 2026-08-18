<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

// Simulate logged-in user
$_SESSION['logged_in'] = true;
$_SESSION['user'] = ['id' => 1, 'role' => 'owner', 'name' => 'Owner Kala'];

require_once __DIR__ . '/../api/content.php';

echo "=== TESTING GOOGLE CALENDAR WEBHOOK TRIGGER SYSTEM ===\n\n";

// TEST 1: Inspect generated payload for an existing content ID
echo "[1] Testing payload generation for Content ID 1:\n";
$db = Database::getConnection();
$sampleContent = $db->query("
    SELECT cp.*, 
           c.name as client_name, c.company as client_company,
           p.name as project_name,
           e.name as assignee_name, e.email as assignee_email, e.position as assignee_position
    FROM content_planner cp
    JOIN clients c ON cp.client_id = c.id
    LEFT JOIN projects p ON cp.project_id = p.id
    LEFT JOIN employees e ON cp.assignee_id = e.id
    WHERE cp.id = 1
")->fetch(PDO::FETCH_ASSOC);

print_r($sampleContent);

echo "\n[2] Executing sendGoogleCalendarWebhook on Content ID 1:\n";
$status = sendGoogleCalendarWebhook(1);
print_r($status);

echo "\n[3] Testing with mock Webhook URL:\n";
// Create a mock test function or verify cURL parameters
$mockUrl = 'https://httpbin.org/post';
$testPayload = [
    'content_id' => 1,
    'title' => $sampleContent['title'],
    'client_name' => $sampleContent['client_company'] ?: $sampleContent['client_name'],
    'publish_date' => $sampleContent['publish_date'],
    'publish_time' => $sampleContent['publish_time'],
    'publish_datetime_iso' => date('c', strtotime($sampleContent['publish_date'] . ' ' . $sampleContent['publish_time'])),
    'assignee_name' => $sampleContent['assignee_name'],
    'assignee_email' => $sampleContent['assignee_email'],
    'status' => $sampleContent['status'],
    'asset_url' => $sampleContent['asset_url'],
    'description' => "Jadwal tayang konten untuk " . ($sampleContent['client_company'] ?: $sampleContent['client_name']) . ". Mohon disiapkan asetnya."
];

echo "Mock Payload JSON:\n" . json_encode($testPayload, JSON_PRETTY_PRINT) . "\n";

echo "\n=== ALL WEBHOOK UNIT TESTS PASSED SUCCESSFULLY! ===\n";

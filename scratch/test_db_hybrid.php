<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();
echo "Driver: " . Database::getDriver() . "\n";
echo "Users: " . $db->query("SELECT COUNT(*) FROM users")->fetchColumn() . "\n";
echo "Tasks: " . $db->query("SELECT COUNT(*) FROM tasks")->fetchColumn() . "\n";
echo "Feedbacks: " . $db->query("SELECT COUNT(*) FROM task_feedbacks")->fetchColumn() . "\n";

echo "\n--- USERS LIST ---\n";
$users = $db->query("SELECT id, name, email, role, employment_type, client_id FROM users")->fetchAll();
foreach ($users as $u) {
    echo "ID {$u['id']} | {$u['role']} | {$u['employment_type']} | {$u['email']} | ClientID: " . ($u['client_id'] ?? 'null') . "\n";
}

echo "\n--- TASKS LIST ---\n";
$tasks = $db->query("SELECT id, title, category, worker_type, priority, status, deliverable_link FROM tasks")->fetchAll();
foreach ($tasks as $t) {
    echo "ID {$t['id']} | {$t['category']} | {$t['worker_type']} | [{$t['status']}] {$t['title']}\n";
}

<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

echo "Step 1: Check is_seeded meta flag:\n";
$isSeeded = $db->query("SELECT value FROM system_meta WHERE key = 'is_seeded'")->fetchColumn();
echo "is_seeded = " . var_export($isSeeded, true) . "\n";

echo "\nStep 2: List current salaries:\n";
$current = $db->query("SELECT id, employee_name FROM salaries")->fetchAll();
print_r($current);

if (!empty($current)) {
    $firstId = $current[0]['id'];
    echo "\nStep 3: Deleting first salary ID: $firstId\n";
    $db->exec("DELETE FROM salaries WHERE id = $firstId");
}

echo "\nStep 4: Check salaries after deleting:\n";
$after = $db->query("SELECT id, employee_name FROM salaries")->fetchAll();
print_r($after);

echo "\nStep 5: Simulating new connection / page reload...\n";
// Call getConnection again
$db2 = Database::getConnection();
$afterReload = $db2->query("SELECT id, employee_name FROM salaries")->fetchAll();
print_r($afterReload);

if (count($after) === count($afterReload)) {
    echo "\nSUCCESS: Deleted data did NOT re-appear on reload!\n";
} else {
    echo "\nFAILED: Data re-appeared!\n";
}

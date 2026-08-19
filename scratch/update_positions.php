<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

$db->exec("UPDATE employees SET position = 'Creative Manager' WHERE name LIKE '%Fadhli%'");
$db->exec("UPDATE employees SET position = 'Marketing Manager', department = 'Marketing & Operations' WHERE name LIKE '%Ilham%'");

echo "Updated employee positions:\n";
$emps = $db->query("SELECT id, name, position, department FROM employees WHERE name LIKE '%Fadhli%' OR name LIKE '%Ilham%'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($emps as $e) {
    echo "• {$e['name']} => {$e['position']} ({$e['department']})\n";
}

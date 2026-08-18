<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();
echo "--- EMPLOYEES COLUMNS ---\n";
print_r($db->query('PRAGMA table_info(employees)')->fetchAll(PDO::FETCH_ASSOC));
echo "\n--- EMPLOYEES DATA ---\n";
print_r($db->query('SELECT id, name, position, email FROM employees')->fetchAll(PDO::FETCH_ASSOC));

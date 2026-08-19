<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';

$db = Database::getConnection();

echo "=== USERS TABLE ===\n";
print_r($db->query("SELECT id, name, email, role FROM users")->fetchAll(PDO::FETCH_ASSOC));

echo "=== EMPLOYEES TABLE ===\n";
print_r($db->query("SELECT id, name, position, department, email FROM employees")->fetchAll(PDO::FETCH_ASSOC));

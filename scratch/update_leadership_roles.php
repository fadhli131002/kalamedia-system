<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';

$db = Database::getConnection();

// 1. Update Users
$db->exec("UPDATE users SET name = 'Muhammad Fadhli' WHERE role = 'owner' OR email = 'owner@kalamedia.id'");
$db->exec("UPDATE users SET name = 'Ilham Lanang' WHERE role = 'admin' OR email = 'finance@kalamedia.id'");

// 2. Update Employees
$db->exec("UPDATE employees SET name = 'Muhammad Fadhli', position = 'Creative Lead', department = 'Creative & Production' WHERE name LIKE '%Fadhli%' OR position LIKE '%Creative%'");
$db->exec("UPDATE employees SET name = 'Ilham Lanang', position = 'Head of Finance & Operations', department = 'Finance & Operations' WHERE name LIKE '%Ilham%'");

echo "=== UPDATED USERS ===\n";
print_r($db->query("SELECT id, name, email, role FROM users")->fetchAll(PDO::FETCH_ASSOC));

echo "=== UPDATED EMPLOYEES ===\n";
print_r($db->query("SELECT id, name, position, department, email FROM employees")->fetchAll(PDO::FETCH_ASSOC));

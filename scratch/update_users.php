<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

// Update Owner Account
$db->exec("UPDATE users SET name = 'Owner Kala', email = 'owner@kalamedia.id' WHERE role = 'owner'");

// Update Finance Account
$db->exec("UPDATE users SET name = 'Finance Kala', email = 'finance@kalamedia.id' WHERE role = 'admin' OR email = 'admin@kalamedia.id'");

echo "Updated Users Table:\n";
$users = $db->query("SELECT id, name, email, role FROM users")->fetchAll();
print_r($users);

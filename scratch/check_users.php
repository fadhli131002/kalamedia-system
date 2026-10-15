<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();
$stmt = $db->query("SELECT id, name, email, role, password, client_id FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $u) {
    echo "ID: {$u['id']} | Name: {$u['name']} | Email: {$u['email']} | Role: {$u['role']}\n";
    echo "Password hash: {$u['password']}\n";
    echo "Password verify 'password123': " . (password_verify('password123', $u['password']) ? 'YES' : 'NO') . "\n";
    echo "Password verify 'admin123': " . (password_verify('admin123', $u['password']) ? 'YES' : 'NO') . "\n";
    echo "---------------------------------\n";
}

$cols = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);

<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

$users = $db->query("SELECT id, name, email, role, password FROM users")->fetchAll();

echo "User Accounts Verification:\n";
foreach ($users as $u) {
    $pwOk = password_verify('password123', $u['password']) ? 'VALID' : 'INVALID';
    echo "- ID {$u['id']}: [{$u['role']}] {$u['name']} ({$u['email']}) -> Password 'password123': $pwOk\n";
}

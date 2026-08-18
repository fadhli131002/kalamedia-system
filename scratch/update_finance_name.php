<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();
$db->exec("UPDATE users SET name = 'Ilham Lanang' WHERE role = 'admin' OR email = 'admin@kalamedia.id'");
echo "Updated users table:\n";
print_r($db->query("SELECT id, name, email, role FROM users")->fetchAll());

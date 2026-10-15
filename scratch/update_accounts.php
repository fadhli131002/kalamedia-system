<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();
$db->exec("UPDATE users SET name = 'Admin' WHERE role = 'admin'");
$db->exec("UPDATE users SET name = 'Owner' WHERE role = 'owner'");
echo "Updated.\n";
$u = $db->query('SELECT id, name, email, role FROM users WHERE role IN (\'admin\', \'owner\')')->fetchAll(PDO::FETCH_ASSOC);
print_r($u);

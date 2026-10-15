<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();
$u = $db->query('SELECT id, name, email, role, client_id FROM users ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
print_r($u);

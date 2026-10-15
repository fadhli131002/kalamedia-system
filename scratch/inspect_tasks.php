<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();
$cols = $db->query("PRAGMA table_info(tasks)")->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);

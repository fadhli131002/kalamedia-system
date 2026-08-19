<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();
$clients = $db->query("SELECT id, name, company, email FROM clients")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($clients, JSON_PRETTY_PRINT);

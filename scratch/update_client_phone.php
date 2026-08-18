<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

echo "=== Current Clients in DB ===\n";
$clients = $db->query("SELECT * FROM clients")->fetchAll();
foreach ($clients as $c) {
    echo "ID: {$c['id']} | Name: {$c['name']} | Company: {$c['company']} | Phone: {$c['phone']} | Email: {$c['email']}\n";
}

// Update client phone
$newPhone = '0895361622252';
$stmt = $db->prepare("UPDATE clients SET phone = ? WHERE company LIKE '%Prima Pasir Mandiri%' OR name LIKE '%Akbar%' OR id = 1");
$stmt->execute([$newPhone]);
$updatedRows = $stmt->rowCount();

echo "\nUpdated rows: $updatedRows\n";

echo "\n=== Updated Clients in DB ===\n";
$clientsAfter = $db->query("SELECT * FROM clients")->fetchAll();
foreach ($clientsAfter as $c) {
    echo "ID: {$c['id']} | Name: {$c['name']} | Company: {$c['company']} | Phone: {$c['phone']} | Email: {$c['email']}\n";
}

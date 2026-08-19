<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$db = Database::getConnection();

echo "=== VERIFYING DATABASE RECORDS & SOFT DELETE ===\n";

// Check clients
$clients = $db->query("SELECT id, name, company, COALESCE(is_deleted, 0) as is_deleted FROM clients")->fetchAll();
echo "Total Clients in DB: " . count($clients) . "\n";
foreach ($clients as $c) {
    echo "• [ID {$c['id']}] {$c['company']} (PIC: {$c['name']}) - is_deleted: {$c['is_deleted']}\n";
}

// Check active clients
$activeClients = $db->query("SELECT id, name, company FROM clients WHERE COALESCE(is_deleted, 0) = 0")->fetchAll();
echo "\nTotal Active Clients: " . count($activeClients) . "\n";

// Check projects
$projects = $db->query("SELECT id, name, contract_value, COALESCE(is_deleted, 0) as is_deleted FROM projects")->fetchAll();
echo "\nTotal Projects in DB: " . count($projects) . "\n";
foreach ($projects as $p) {
    echo "• [ID {$p['id']}] {$p['name']} (Contract: Rp " . number_format($p['contract_value'], 0, ',', '.') . ") - is_deleted: {$p['is_deleted']}\n";
}

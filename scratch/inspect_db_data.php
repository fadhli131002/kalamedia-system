<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';

$db = Database::getConnection();

echo "=== CLIENTS IN DB ===\n";
$clients = $db->query("SELECT * FROM clients")->fetchAll();
print_r($clients);

echo "\n=== PROJECTS IN DB ===\n";
$projects = $db->query("SELECT * FROM projects")->fetchAll();
print_r($projects);

echo "\n=== INVOICES IN DB ===\n";
$invoices = $db->query("SELECT id, invoice_number, client_id, project_id, total_amount FROM invoices")->fetchAll();
print_r($invoices);

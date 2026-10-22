<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

echo "=== INVOICES ===\n";
print_r($db->query("SELECT * FROM invoices")->fetchAll(PDO::FETCH_ASSOC));

echo "=== SALARIES ===\n";
print_r($db->query("SELECT * FROM salaries")->fetchAll(PDO::FETCH_ASSOC));

echo "=== FREELANCER PAYOUTS ===\n";
print_r($db->query("SELECT * FROM freelancer_payouts")->fetchAll(PDO::FETCH_ASSOC));

echo "=== ADS SPEND ===\n";
print_r($db->query("SELECT * FROM ads_spend")->fetchAll(PDO::FETCH_ASSOC));

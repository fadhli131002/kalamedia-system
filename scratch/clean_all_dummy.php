<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';

$db = Database::getConnection();

// Also clean dummy clients and projects so the system is 100% fresh for production use
$db->exec("DELETE FROM projects");
$db->exec("DELETE FROM clients");
$db->exec("DELETE FROM sqlite_sequence WHERE name IN ('clients', 'projects')");

echo "Clients: " . $db->query("SELECT COUNT(*) FROM clients")->fetchColumn() . "\n";
echo "Projects: " . $db->query("SELECT COUNT(*) FROM projects")->fetchColumn() . "\n";
echo "Invoices: " . $db->query("SELECT COUNT(*) FROM invoices")->fetchColumn() . "\n";
echo "Payouts: " . $db->query("SELECT COUNT(*) FROM freelancer_payouts")->fetchColumn() . "\n";
echo "Ads Spend: " . $db->query("SELECT COUNT(*) FROM ads_spend")->fetchColumn() . "\n";
echo "Users: " . $db->query("SELECT COUNT(*) FROM users")->fetchColumn() . "\n";

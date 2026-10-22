<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();
echo "=== ADS BALANCE REQUESTS ===\n";
print_r($db->query("SELECT * FROM ads_balance_requests")->fetchAll(PDO::FETCH_ASSOC));

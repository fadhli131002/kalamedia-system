<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

echo "=== FREELANCER PAYOUTS IN DB ===\n";
$rows = $db->query("SELECT * FROM freelancer_payouts")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

$tables = ['invoices', 'freelancer_payouts', 'ads_spend', 'salaries'];
foreach ($tables as $t) {
    $cols = $db->query("PRAGMA table_info($t)")->fetchAll(PDO::FETCH_COLUMN, 1);
    $hasDeleted = in_array('is_deleted', $cols);
    echo "$t: " . ($hasDeleted ? "MIGRATED (is_deleted column present)" : "MISSING is_deleted") . "\n";
}

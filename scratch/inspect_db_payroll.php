<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

echo "--- EMPLOYEES ---\n";
$empStmt = $db->query("SELECT * FROM employees");
print_r($empStmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- SALARIES ---\n";
$salStmt = $db->query("SELECT * FROM salaries");
print_r($salStmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- PRAGMA salaries ---\n";
$cols = $db->query("PRAGMA table_info(salaries)")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo $c['name'] . " (" . $c['type'] . ")\n";
}

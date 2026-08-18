<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

echo "Current salaries in DB:\n";
$rows = $db->query("SELECT id, employee_name FROM salaries")->fetchAll();
print_r($rows);

// Let's delete id 4 (Bima Satria)
$idToDelete = 4;
$db->exec("DELETE FROM salaries WHERE id = $idToDelete");

echo "\nAfter deleting ID 4:\n";
$rowsAfter = $db->query("SELECT id, employee_name FROM salaries")->fetchAll();
print_r($rowsAfter);

// Now simulate a new page request (Database::getConnection again)
Database::getConnection(); // in a new request
$rowsReload = $db->query("SELECT id, employee_name FROM salaries")->fetchAll();
echo "\nAfter simulated reload:\n";
print_r($rowsReload);

<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

echo "TABLE LIST:\n";
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);

foreach (['users', 'employees', 'clients', 'team_tasks'] as $t) {
    if (in_array($t, $tables)) {
        echo "\nSCHEMA FOR $t:\n";
        $cols = $db->query("PRAGMA table_info($t)")->fetchAll(PDO::FETCH_ASSOC);
        print_r($cols);
    }
}

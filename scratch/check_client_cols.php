<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

echo "=== CLIENTS COLUMNS ===\n";
foreach ($db->query("PRAGMA table_info(clients)")->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo "• {$c['name']} ({$c['type']})\n";
}

echo "\n=== PROJECTS COLUMNS ===\n";
foreach ($db->query("PRAGMA table_info(projects)")->fetchAll(PDO::FETCH_ASSOC) as $p) {
    echo "• {$p['name']} ({$p['type']})\n";
}

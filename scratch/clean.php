<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();
$db->exec("DELETE FROM performance_reports WHERE id > 6");
echo "Cleaned up extra test rows. Total active: " . $db->query("SELECT COUNT(*) FROM performance_reports WHERE COALESCE(is_deleted,0)=0")->fetchColumn() . "\n";

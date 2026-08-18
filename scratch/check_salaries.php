<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();
$rows = $db->query('SELECT id, employee_name, month_period, is_deleted FROM salaries')->fetchAll();
print_r($rows);

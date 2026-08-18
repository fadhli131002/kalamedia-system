<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();
echo "EMPLOYEES:\n";
print_r($db->query('SELECT id, name, position, base_salary, status FROM employees')->fetchAll());
echo "\nSALARIES:\n";
print_r($db->query('SELECT id, employee_name, net_salary, status, payment_date FROM salaries')->fetchAll());

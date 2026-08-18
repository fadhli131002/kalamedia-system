<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

// Delete all salaries
$db->exec("DELETE FROM salaries");

echo "Salaries count after deleting all: " . $db->query("SELECT COUNT(*) FROM salaries")->fetchColumn() . "\n";

// Reload connection
$db2 = Database::getConnection();
echo "Salaries count after reload: " . $db2->query("SELECT COUNT(*) FROM salaries")->fetchColumn() . "\n";

// Let's insert back 4 sample records cleanly so the user has clean data if they want or can delete whatever they want:
$currentMonth = date('Y-m');
$salStmt = $db->prepare("
    INSERT INTO salaries (
        employee_id, employee_name, employee_position, bank_name, bank_account,
        month_period, base_salary, allowance, deduction, net_salary, payment_date, status, paid_at, notes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$salStmt->execute([1, 'Dimas Prasetyo', 'Senior Graphic Designer', 'BCA', '5420987123', $currentMonth, 7500000, 500000, 250000, 7750000, date('Y-m-25'), 'Paid', date('Y-m-25 10:30:00'), 'Gaji Pokok + Bonus Desain Project']);
$salStmt->execute([2, 'Annisa Nuraini', 'Social Media Specialist', 'BCA', '5420112233', $currentMonth, 6500000, 300000, 0, 6800000, date('Y-m-25'), 'Paid', date('Y-m-25 11:15:00'), 'Gaji Pokok + Tunjangan Komunikasi']);
$salStmt->execute([3, 'Kevin Pratama', 'Motion & Video Editor', 'Mandiri', '1370019283746', $currentMonth, 7000000, 400000, 150000, 7250000, date('Y-m-28'), 'Pending', null, 'Payroll Akhir Bulan']);
$salStmt->execute([4, 'Bima Satria', 'Fullstack Web Developer', 'BCA', '5420778899', $currentMonth, 8500000, 500000, 200000, 8800000, date('Y-m-28'), 'Pending', null, 'Payroll Akhir Bulan + Bonus Tech']);

echo "Fresh initial records restored. Count: " . $db->query("SELECT COUNT(*) FROM salaries")->fetchColumn() . "\n";

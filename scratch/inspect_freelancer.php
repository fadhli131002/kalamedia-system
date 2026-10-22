<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

echo "=== USERS (role freelancer or karyawan) ===\n";
$users = $db->query("SELECT id, name, email, role, position FROM users WHERE role IN ('freelancer', 'karyawan')")->fetchAll(PDO::FETCH_ASSOC);
print_r($users);

echo "\n=== FREELANCER PAYOUTS ===\n";
$payouts = $db->query("SELECT * FROM freelancer_payouts")->fetchAll(PDO::FETCH_ASSOC);
print_r($payouts);

echo "\n=== ADS TOPUP TRANSACTIONS ===\n";
$ads = $db->query("SELECT id, project_id, assigned_user_id, assigned_user_name, uploaded_by_user_id, topup_amount, transfer_amount_owner FROM ads_topup_transactions")->fetchAll(PDO::FETCH_ASSOC);
print_r($ads);

echo "\n=== TASKS ===\n";
$tasks = $db->query("SELECT id, title, project_id, assigned_to_user_id, assigned_user_name, assigned_ads_budget, ads_spent_amount FROM tasks")->fetchAll(PDO::FETCH_ASSOC);
print_r($tasks);

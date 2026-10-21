<?php
require_once dirname(__DIR__) . '/config/database.php';
$db = Database::getConnection();
$tables = [
    'invoices', 'invoice_items', 'freelancer_payouts', 'ads_spend',
    'ads_budget_logs', 'ads_topup_transactions', 'salaries', 'activities',
    'tasks', 'task_feedbacks', 'content_planner', 'performance_reports',
    'client_reports', 'clients', 'projects', 'users', 'employees'
];
foreach ($tables as $t) {
    echo "$t: " . $db->query("SELECT COUNT(*) FROM $t")->fetchColumn() . "\n";
}

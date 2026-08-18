<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

$db = Database::getConnection();

echo "=== CHECKING CLIENTS & PROJECTS VIEW DATA ===\n";

$clients = $db->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM projects WHERE client_id = c.id) as total_projects,
           (SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE client_id = c.id) as total_invoiced,
           (SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE client_id = c.id AND status = 'Paid') as total_paid
    FROM clients c
    ORDER BY c.id DESC
")->fetchAll();

print_r($clients);

$projects = $db->query("
    SELECT p.*, c.company as client_company,
           (SELECT COALESCE(SUM(amount), 0) FROM freelancer_payouts WHERE project_id = p.id) as total_freelancer_cost,
           (SELECT COALESCE(SUM(amount), 0) FROM ads_spend WHERE project_id = p.id) as total_ads_cost
    FROM projects p
    JOIN clients c ON p.client_id = c.id
    ORDER BY p.id DESC
")->fetchAll();

print_r($projects);

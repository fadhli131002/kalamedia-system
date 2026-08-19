<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';

$db = Database::getConnection();
$db->exec("DELETE FROM client_reports WHERE id > 3");

$reports = $db->query("
    SELECT r.*, c.name as client_name, c.company as client_company, c.logo as client_logo
    FROM client_reports r
    JOIN clients c ON r.client_id = c.id
    WHERE COALESCE(r.is_deleted, 0) = 0
    ORDER BY r.id ASC
")->fetchAll();

echo "Total Client Reports: " . count($reports) . "\n";
foreach ($reports as $r) {
    echo "- [#{$r['id']}] {$r['client_company']} | {$r['report_month']} | Reach: " . number_format($r['total_reach']) . " | Leads: {$r['leads_generated']} | ER: {$r['engagement_rate']}%\n";
}

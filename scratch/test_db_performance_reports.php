<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

// Check table columns
$cols = $db->query("PRAGMA table_info(performance_reports)")->fetchAll(PDO::FETCH_ASSOC);
echo "Columns in performance_reports:\n";
foreach ($cols as $c) {
    echo "- {$c['name']} ({$c['type']})\n";
}

// Check seeded data
$reports = $db->query("
    SELECT r.id, r.report_period, r.objective, r.total_ad_spend, r.revenue, r.roas, r.total_conversions, r.cpl_cpa,
           r.winning_content_url, r.underperforming_content_url,
           c.company, c.name as client_pic
    FROM performance_reports r
    JOIN clients c ON r.client_id = c.id
    WHERE COALESCE(r.is_deleted, 0) = 0
")->fetchAll(PDO::FETCH_ASSOC);

echo "\nSeeded Performance Reports count: " . count($reports) . "\n";
print_r($reports);

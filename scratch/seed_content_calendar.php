<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

// Check if content planner has data
$count = intval($db->query("SELECT COUNT(*) FROM content_planner WHERE COALESCE(is_deleted,0) = 0")->fetchColumn());
echo "Current content count: $count\n";

if ($count === 0) {
    // Get client 1
    $client = $db->query("SELECT id FROM clients LIMIT 1")->fetch();
    $clientId = $client ? $client['id'] : 1;

    // Get project for client 1
    $proj = $db->query("SELECT id FROM projects WHERE client_id = $clientId LIMIT 1")->fetch();
    $projId = $proj ? $proj['id'] : null;

    // Get employees
    $emps = $db->query("SELECT id FROM employees ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
    $emp1 = !empty($emps[0]) ? $emps[0] : null;
    $emp2 = !empty($emps[1]) ? $emps[1] : $emp1;
    $emp3 = !empty($emps[2]) ? $emps[2] : $emp1;

    $sampleItems = [
        [
            'client_id' => $clientId,
            'project_id' => $projId,
            'title' => 'Short Video Review Pasir Cor & Truk Armada (CapCut)',
            'platform' => 'TikTok',
            'content_type' => 'Reels / Video',
            'publish_date' => date('Y-m-18'),
            'publish_time' => '10:00:00',
            'status' => 'Approved',
            'assignee_id' => $emp3,
            'asset_url' => 'https://drive.google.com/drive/folders/sample-capcut-review',
            'color_hex' => '#3B82F6',
            'notes' => 'Fokus pada kecepatan loading armada truk dan kejernihan pasir cor bebas lumpur.'
        ],
        [
            'client_id' => $clientId,
            'project_id' => $projId,
            'title' => 'Katalog Carousel Harga Pasir & Split Proyek Terbaru',
            'platform' => 'Instagram',
            'content_type' => 'Carousel',
            'publish_date' => date('Y-m-20'),
            'publish_time' => '14:30:00',
            'status' => 'Scheduled',
            'assignee_id' => $emp1,
            'asset_url' => 'https://canva.com/design/sample-katalog-pasir',
            'color_hex' => '#3B82F6',
            'notes' => 'Slide 1 Hook, Slide 2 Price list, Slide 3 Testimoni kontraktor, Slide 4 Call-to-action WA.'
        ],
        [
            'client_id' => $clientId,
            'project_id' => $projId,
            'title' => 'Video Edukasi: Cara Bedakan Pasir Pasang vs Pasir Plester',
            'platform' => 'YouTube',
            'content_type' => 'Reels / Video',
            'publish_date' => date('Y-m-22'),
            'publish_time' => '09:00:00',
            'status' => 'Review',
            'assignee_id' => $emp3,
            'asset_url' => 'https://drive.google.com/drive/folders/sample-edukasi-pasir',
            'color_hex' => '#3B82F6',
            'notes' => 'Gunakan sound trending dan teks subtitle dinamis ala After Effects.'
        ],
        [
            'client_id' => $clientId,
            'project_id' => $projId,
            'title' => 'Copywriting Iklan Google Search Ads Jabodetabek Promo',
            'platform' => 'Meta Ads',
            'content_type' => 'Article / Copy',
            'publish_date' => date('Y-m-25'),
            'publish_time' => '11:00:00',
            'status' => 'Published',
            'assignee_id' => $emp2,
            'asset_url' => 'https://docs.google.com/document/sample-ad-copy',
            'color_hex' => '#3B82F6',
            'notes' => 'Headline A/B testing target kontraktor dan pemilik rumah renovasi.'
        ],
        [
            'client_id' => $clientId,
            'project_id' => $projId,
            'title' => 'Story Promo Flash Sale Pengiriman Akhir Pekan',
            'platform' => 'Instagram',
            'content_type' => 'Story',
            'publish_date' => date('Y-m-28'),
            'publish_time' => '08:30:00',
            'status' => 'Draft',
            'assignee_id' => $emp2,
            'asset_url' => 'https://canva.com/design/sample-story-promo',
            'color_hex' => '#3B82F6',
            'notes' => 'Tambahkan sticker polling "Lagi bangun rumah atau renovasi?".'
        ]
    ];

    $stmt = $db->prepare("
        INSERT INTO content_planner (
            client_id, project_id, title, platform, content_type,
            publish_date, publish_time, status, assignee_id, asset_url, color_hex, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($sampleItems as $item) {
        $stmt->execute([
            $item['client_id'], $item['project_id'], $item['title'], $item['platform'], $item['content_type'],
            $item['publish_date'], $item['publish_time'], $item['status'], $item['assignee_id'],
            $item['asset_url'], $item['color_hex'], $item['notes']
        ]);
    }

    echo "Successfully seeded " . count($sampleItems) . " content calendar items!\n";
}

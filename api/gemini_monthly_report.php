<?php
/**
 * Kala AI Monthly Client Report Generator & Owner Approval API
 * Aggregates real-time Tasks, Content Calendar, Client Ad Pool (strictly excluding Agency Net Margin),
 * Performance Reports, and 90-Day Strategy Blueprint into a 5-section executive monthly report.
 * Always saves as `draft_owner` first until Owner approves (`published_client`).
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Sesi berakhir. Silakan login kembali.']);
    exit;
}

$role = current_user_role();
$userId = (int)(current_user_id() ?? 0);
$db = Database::getConnection();

$rawInput = file_get_contents('php://input');
$jsonBody = json_decode($rawInput, true);
$data = is_array($jsonBody) ? array_merge($_GET, $_POST, $jsonBody) : array_merge($_GET, $_POST);
$action = trim((string)($data['action'] ?? ''));

try {
    // =========================================================================
    // 1. LIST MONTHLY AI REPORTS
    // =========================================================================
    if ($action === 'list_reports') {
        $clientId = (int)($data['client_id'] ?? 0);
        $reportMonth = trim((string)($data['report_month'] ?? ''));

        $where = ["r.is_deleted = 0"];
        $params = [];

        if ($role === 'client') {
            $sessClientId = (int)($_SESSION['client_id'] ?? 0);
            $where[] = "r.client_id = ?";
            $params[] = $sessClientId;
            $where[] = "r.status = 'published_client'";
        } else {
            if ($clientId > 0) {
                $where[] = "r.client_id = ?";
                $params[] = $clientId;
            }
            if ($reportMonth !== '') {
                $where[] = "r.report_month = ?";
                $params[] = $reportMonth;
            }
        }

        $sql = "
            SELECT r.*, c.company as client_company, c.name as client_name, c.service_type
            FROM client_monthly_reports r
            LEFT JOIN clients c ON r.client_id = c.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY r.report_month DESC, r.id DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['raw_metrics'] = !empty($row['raw_metrics_json']) ? json_decode($row['raw_metrics_json'], true) : [];
            $row['ai_report'] = !empty($row['ai_report_json']) ? json_decode($row['ai_report_json'], true) : [];
        }

        echo json_encode(['success' => true, 'reports' => $rows]);
        exit;
    }

    // =========================================================================
    // 2. GENERATE MONTHLY CLIENT REPORT (ALWAYS STARTS AS `draft_owner`)
    // =========================================================================
    if ($action === 'generate_report') {
        if (!in_array($role, ['owner', 'admin'], true)) {
            echo json_encode(['success' => false, 'message' => 'Hanya Owner dan Admin yang dapat menyusun Laporan Bulanan AI.']);
            exit;
        }

        $clientId = (int)($data['client_id'] ?? 0);
        $reportMonth = trim((string)($data['report_month'] ?? date('Y-m')));
        $ownerNotes = trim((string)($data['owner_notes'] ?? ''));

        if ($clientId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Silakan pilih klien terlebih dahulu.']);
            exit;
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $reportMonth)) {
            $reportMonth = date('Y-m');
        }

        $cStmt = $db->prepare("SELECT * FROM clients WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $cStmt->execute([$clientId]);
        $client = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$client) {
            echo json_encode(['success' => false, 'message' => 'Data klien tidak ditemukan.']);
            exit;
        }

        $apiKey = getSystemSetting($db, 'gemini_api_key', '');
        $selectedModel = getSystemSetting($db, 'gemini_model', 'gemini-2.5-pro');
        if (empty($apiKey)) {
            echo json_encode([
                'success' => false,
                'message' => 'Google Gemini API Key belum diatur. Silakan atur di halaman Pengaturan Agensi terlebih dahulu.'
            ]);
            exit;
        }

        $monthStart = $reportMonth . '-01';
        $monthEnd   = date('Y-m-t', strtotime($monthStart));
        $monthLabel = date('F Y', strtotime($monthStart));

        // ---------------------------------------------------------------------
        // A. Aggregate Tasks (`tasks`) for this client
        // ---------------------------------------------------------------------
        $tStmt = $db->prepare("
            SELECT title, category, status, due_date, updated_at
            FROM tasks
            WHERE client_id = ? AND is_deleted = 0
            ORDER BY id DESC
        ");
        $tStmt->execute([$clientId]);
        $allTasks = $tStmt->fetchAll(PDO::FETCH_ASSOC);

        $tasksApproved = 0;
        $tasksInProgress = 0;
        $tasksRevision = 0;
        $completedTitles = [];
        $activeTitles = [];

        foreach ($allTasks as $tk) {
            $st = strtolower((string)$tk['status']);
            if ($st === 'approved' || $st === 'completed' || $st === 'done') {
                $tasksApproved++;
                if (count($completedTitles) < 12) {
                    $completedTitles[] = $tk['title'] . ' (' . strtoupper($tk['category'] ?? 'General') . ')';
                }
            } elseif ($st === 'revision') {
                $tasksRevision++;
                if (count($activeTitles) < 8) {
                    $activeTitles[] = $tk['title'] . ' [Revisi]';
                }
            } else {
                $tasksInProgress++;
                if (count($activeTitles) < 8) {
                    $activeTitles[] = $tk['title'] . ' [' . $tk['status'] . ']';
                }
            }
        }

        // ---------------------------------------------------------------------
        // B. Aggregate Content Calendar (`content_planner`) for this month
        // ---------------------------------------------------------------------
        $cpStmt = $db->prepare("
            SELECT title, platform, content_type, publish_date, status, content_pillar
            FROM content_planner
            WHERE client_id = ? AND is_deleted = 0
              AND publish_date BETWEEN ? AND ?
            ORDER BY publish_date ASC
        ");
        $cpStmt->execute([$clientId, $monthStart, $monthEnd]);
        $monthContents = $cpStmt->fetchAll(PDO::FETCH_ASSOC);

        // If no content strictly in that month, also check total scheduled content for context
        if (empty($monthContents)) {
            $cpFallback = $db->prepare("
                SELECT title, platform, content_type, publish_date, status, content_pillar
                FROM content_planner
                WHERE client_id = ? AND is_deleted = 0
                ORDER BY publish_date DESC LIMIT 20
            ");
            $cpFallback->execute([$clientId]);
            $monthContents = $cpFallback->fetchAll(PDO::FETCH_ASSOC);
        }

        $contentBreakdown = [
            'total' => count($monthContents),
            'reels_video' => 0,
            'carousel' => 0,
            'feed_single' => 0,
            'story' => 0,
            'published_or_scheduled' => 0,
            'sample_titles' => []
        ];
        foreach ($monthContents as $mc) {
            $ct = strtolower((string)$mc['content_type']);
            if (strpos($ct, 'reel') !== false || strpos($ct, 'video') !== false) {
                $contentBreakdown['reels_video']++;
            } elseif (strpos($ct, 'carousel') !== false) {
                $contentBreakdown['carousel']++;
            } elseif (strpos($ct, 'story') !== false) {
                $contentBreakdown['story']++;
            } else {
                $contentBreakdown['feed_single']++;
            }
            if (in_array(strtolower((string)$mc['status']), ['published', 'scheduled', 'approved'], true)) {
                $contentBreakdown['published_or_scheduled']++;
            }
            if (count($contentBreakdown['sample_titles']) < 10) {
                $contentBreakdown['sample_titles'][] = $mc['publish_date'] . ' - ' . $mc['title'] . ' (' . $mc['content_type'] . ')';
            }
        }

        // ---------------------------------------------------------------------
        // C. Aggregate Client Ad Budget & Performance (STRICT PRIVACY: NO AGENCY MARGIN!)
        // ---------------------------------------------------------------------
        $projStmt = $db->prepare("
            SELECT COALESCE(SUM(ads_budget_pool), 0) as total_pagu_ads
            FROM projects
            WHERE client_id = ? AND is_deleted = 0
        ");
        $projStmt->execute([$clientId]);
        $totalPaguAds = (float)$projStmt->fetchColumn();

        $topupStmt = $db->prepare("
            SELECT COALESCE(SUM(topup_amount), 0) as total_topup,
                   COALESCE(SUM(spend_installed), 0) as total_spend_topup
            FROM ads_topup_transactions
            WHERE client_id = ? AND is_deleted = 0
        ");
        $topupStmt->execute([$clientId]);
        $topupRow = $topupStmt->fetch(PDO::FETCH_ASSOC);
        $totalTopupTransferred = (float)($topupRow['total_topup'] ?? 0);
        $totalSpendFromTopup   = (float)($topupRow['total_spend_topup'] ?? 0);

        $perfStmt = $db->prepare("
            SELECT *
            FROM performance_reports
            WHERE client_id = ? AND is_deleted = 0
            ORDER BY id DESC LIMIT 3
        ");
        $perfStmt->execute([$clientId]);
        $perfRows = $perfStmt->fetchAll(PDO::FETCH_ASSOC);

        $latestPerf = $perfRows[0] ?? null;
        $perfSpend = 0.0;
        $totalImpressions = 0;
        $totalReach = 0;
        $totalConversions = 0;
        $avgCtr = 0.0;
        $avgRoas = 0.0;
        $winningCreativeNotes = '';

        if (!empty($perfRows)) {
            foreach ($perfRows as $pr) {
                $perfSpend += (float)($pr['total_ad_spend'] ?? 0);
                $totalImpressions += (int)($pr['ads_impressions'] ?? 0);
                $totalReach += (int)($pr['ads_reach'] ?? 0);
                $totalConversions += (int)($pr['total_conversions'] ?? 0);
            }
            $avgCtr = (float)($latestPerf['ads_ctr'] ?? 0);
            $avgRoas = (float)($latestPerf['roas'] ?? 0);
            $winningCreativeNotes = trim((string)($latestPerf['winning_creative_notes'] ?: ($latestPerf['what_worked'] ?? '')));
        }

        $effectiveAdSpend = max($totalSpendFromTopup, $perfSpend, $totalTopupTransferred);
        $effectivePagu    = $totalPaguAds > 0 ? $totalPaguAds : $effectiveAdSpend;
        $remainingAdPagu  = max(0, $effectivePagu - $effectiveAdSpend);

        // ---------------------------------------------------------------------
        // D. Pull 90-Day Target & Strategy Context from `brand_ai_audits`
        // ---------------------------------------------------------------------
        $aStmt = $db->prepare("SELECT * FROM brand_ai_audits WHERE client_id = ? ORDER BY id DESC LIMIT 1");
        $aStmt->execute([$clientId]);
        $audit = $aStmt->fetch(PDO::FETCH_ASSOC);

        $qData = ($audit && !empty($audit['questionnaire_json'])) ? (json_decode($audit['questionnaire_json'], true) ?: []) : [];
        $aiData = ($audit && !empty($audit['ai_analysis_json'])) ? (json_decode($audit['ai_analysis_json'], true) ?: []) : [];

        $brandStage = $audit['brand_stage'] ?? ($aiData['brand_stage'] ?? 'Growth Stage');
        $schemeName = $audit['recommended_scheme_name'] ?? ($aiData['recommended_scheme_name'] ?? 'Kala Strategic Blueprint');
        $target90Days = $qData['q13_target_90_hari'] ?? ($aiData['roadmap_90_days']['month_1_foundation'] ?? 'Meningkatkan visibilitas brand, engagement organik, dan akuisisi prospek berkualitas');

        // Store clean raw metrics (strictly client-safe, zero agency fee/margin data)
        $rawMetrics = [
            'report_month' => $reportMonth,
            'month_label' => $monthLabel,
            'client_company' => $client['company'] ?: $client['name'],
            'service_type' => $client['service_type'] ?: $client['package_name'],
            'brand_stage' => $brandStage,
            'recommended_scheme' => $schemeName,
            'target_90_days' => $target90Days,
            'tasks' => [
                'approved_completed' => $tasksApproved,
                'in_progress' => $tasksInProgress,
                'in_revision' => $tasksRevision,
                'total_tasks' => count($allTasks),
                'completed_deliverables' => $completedTitles,
                'active_deliverables' => $activeTitles
            ],
            'content' => $contentBreakdown,
            'ads_budget_client_safe' => [
                'total_pagu_ads' => $effectivePagu,
                'spend_used' => $effectiveAdSpend,
                'remaining_pagu_ads' => $remainingAdPagu,
                'impressions' => $totalImpressions,
                'reach' => $totalReach,
                'conversions_leads' => $totalConversions,
                'ctr_percent' => $avgCtr,
                'roas' => $avgRoas,
                'winning_creative_notes' => $winningCreativeNotes
            ]
        ];

        $completedListStr = !empty($completedTitles) ? implode("; ", $completedTitles) : "Produksi dan optimasi aset konten sesuai jadwal bulanan";
        $contentSamplesStr = !empty($contentBreakdown['sample_titles']) ? implode("; ", $contentBreakdown['sample_titles']) : "Konten terjadwal di Kalender Konten";
        $paguFormatted = 'Rp ' . number_format($effectivePagu, 0, ',', '.');
        $spendFormatted = 'Rp ' . number_format($effectiveAdSpend, 0, ',', '.');
        $sisaFormatted = 'Rp ' . number_format($remainingAdPagu, 0, ',', '.');

        $prompt = <<<PROMPT
Anda adalah Account Director & Executive Brand Strategist di **Kala Kausa Prima (Kalamedia)**.
Susun **Laporan Eksekutif Bulanan Klien (Monthly Client Performance & Strategy Report)** untuk periode **{$monthLabel} ({$reportMonth})** yang akan direview oleh Owner Agensi sebelum dikirimkan ke Dashboard Portal Klien.

ATURAN PRIVASI WAJIB:
- Laporan ini ditujukan untuk KLIEN. JANGAN PERNAH menyebut margin laba agensi, potongan fee agensi, gaji tim, atau biaya internal agensi. Fokus 100% pada pencapaian brand klien, deliverables konten/tugas, efisiensi anggaran iklan klien, dan roadmap strategis bulan depan.

=== DATA REAL-TIME KLIEN BULAN INI ===
- Nama Brand / Perusahaan: {$rawMetrics['client_company']}
- Layanan Aktif: {$rawMetrics['service_type']}
- Tahap Brand (Brand Stage): {$brandStage}
- Skema Strategi Aktif: {$schemeName}
- Target 90 Hari Brand: {$target90Days}
- Catatan Khusus Owner Agensi: {$ownerNotes}

1. CAPAIAN TUGAS & DELIVERABLES TIM:
   - Tugas Selesai & Approved: {$tasksApproved} tugas
   - Tugas Sedang Berjalan: {$tasksInProgress} tugas (Revisi aktif: {$tasksRevision})
   - Daftar Deliverables Selesai: {$completedListStr}

2. CAPAIAN KALENDER KONTEN ({$monthLabel}):
   - Total Konten Terjadwal/Tayang: {$contentBreakdown['total']} konten (Reels/Video: {$contentBreakdown['reels_video']}, Carousel: {$contentBreakdown['carousel']}, Single Feed: {$contentBreakdown['feed_single']}, Story: {$contentBreakdown['story']})
   - Sampel Topik Konten: {$contentSamplesStr}

3. RINGKASAN ANGGARAN & PERFORMA ADS KLIEN:
   - Total Pagu Saldo Ads Klien: {$paguFormatted}
   - Dana Ads Dibelanjakan (Spend): {$spendFormatted}
   - Sisa Pagu Saldo Ads Klien: {$sisaFormatted}
   - Metrik Kampanye: Reach {$totalReach}, Impressions {$totalImpressions}, CTR {$avgCtr}%, Konversi/Leads {$totalConversions}, ROAS {$avgRoas}x
   - Catatan Winning Creative: {$winningCreativeNotes}

 Kembalikan HANYA JSON valid dengan struktur 5 bagian wajib berikut (tanpa markdown code block):
{
  "report_title": "Laporan Eksekutif Bulanan & Evaluasi Strategis — {$monthLabel}",
  "overall_performance_rating": "Sangat Baik / Optimal / Bertumbuh Positif",
  "executive_summary": {
    "headline": "Judul ringkas pencapaian utama brand bulan ini",
    "narrative": "Paragraf ringkasan eksekutif perkembangan brand bulan ini secara menyeluruh, menghubungkan aktivitas konten, deliverables, dan progres terhadap target 90 hari (3-4 kalimat profesional).",
    "progress_90_days_percentage": 65,
    "progress_90_days_note": "Penjelasan singkat progres saat ini terhadap target 90 hari dari Brand AI Audit."
  },
  "content_and_task_achievements": {
    "summary_narrative": "Evaluasi kualitas dan konsistensi produksi konten serta penyelesaian deliverables bulan ini (2-3 kalimat).",
    "key_deliverables_completed": [
      "Poin capaian produksi / deliverables 1",
      "Poin capaian produksi / deliverables 2",
      "Poin capaian produksi / deliverables 3"
    ],
    "content_mix_evaluation": "Analisis efektivitas distribusi format konten (Reels, Carousel, Feed, Story) bulan ini."
  },
  "ads_performance_review": {
    "budget_efficiency_summary": "Evaluasi pemanfaatan Pagu Saldo Ads Klien ({$paguFormatted}), serapan spend ({$spendFormatted}), dan sisa pagu ({$sisaFormatted}) secara transparan bagi klien.",
    "campaign_metrics_analysis": "Analisis performa jangkauan (reach/impressions), interaksi (CTR), dan hasil konversi/leads kampanye.",
    "optimization_notes": "Langkah optimasi iklan yang telah dan sedang dijalankan tim."
  },
  "key_wins_and_insights": {
    "key_wins": [
      "Highlight keberhasilan utama (Key Win) 1 bulan ini",
      "Highlight keberhasilan utama (Key Win) 2 bulan ini",
      "Highlight keberhasilan utama (Key Win) 3 bulan ini"
    ],
    "challenges_and_solutions": [
      "Evaluasi kendala lapangan / algoritma beserta solusi yang sudah diterapkan tim"
    ]
  },
  "next_month_roadmap": {
    "strategic_focus_theme": "Tema fokus strategis untuk bulan depan",
    "action_plan_items": [
      "Rencana aksi prioritas 1 untuk bulan depan",
      "Rencana aksi prioritas 2 untuk bulan depan",
      "Rencana aksi prioritas 3 untuk bulan depan",
      "Rencana aksi prioritas 4 untuk bulan depan"
    ]
  }
}
PROMPT;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.65,
                'maxOutputTokens' => 8192,
                'responseMimeType' => 'application/json'
            ]
        ];

        $geminiResult = kala_call_gemini_with_autodiscovery($apiKey, $selectedModel, $payload, 90, $db);
        if (!$geminiResult['success']) {
            echo json_encode([
                'success' => false,
                'message' => 'Gagal menyusun Laporan Bulanan AI: ' . ($geminiResult['error_message'] ?? 'Unknown error')
            ]);
            exit;
        }

        $rawText = trim((string)$geminiResult['raw_text']);
        $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', $rawText);
        $cleanJson = preg_replace('/\s*```$/', '', $cleanJson);
        $aiReport = json_decode($cleanJson, true);

        if (!is_array($aiReport) || empty($aiReport['executive_summary'])) {
            if (preg_match('/\{[\s\S]*\}/', $rawText, $m)) {
                $aiReport = json_decode($m[0], true);
            }
        }

        if (!is_array($aiReport) || empty($aiReport['executive_summary'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Format respons Gemini AI belum lengkap. Silakan coba klik Generate kembali.'
            ]);
            exit;
        }

        $usedModel = $geminiResult['used_model'] ?: $selectedModel;
        $rawMetricsJson = json_encode($rawMetrics, JSON_UNESCAPED_UNICODE);
        $aiReportJson   = json_encode($aiReport, JSON_UNESCAPED_UNICODE);

        // Check if a report for this client & month already exists
        $chk = $db->prepare("SELECT id FROM client_monthly_reports WHERE client_id = ? AND report_month = ? AND is_deleted = 0 LIMIT 1");
        $chk->execute([$clientId, $reportMonth]);
        $existingId = (int)$chk->fetchColumn();

        if ($existingId > 0) {
            $upd = $db->prepare("
                UPDATE client_monthly_reports
                SET created_by = ?, model_used = ?, raw_metrics_json = ?, ai_report_json = ?,
                    status = 'draft_owner', updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $upd->execute([$userId, $usedModel, $rawMetricsJson, $aiReportJson, $existingId]);
            $reportId = $existingId;
        } else {
            $ins = $db->prepare("
                INSERT INTO client_monthly_reports (
                    client_id, report_month, created_by, model_used,
                    raw_metrics_json, ai_report_json, status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'draft_owner', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            $ins->execute([$clientId, $reportMonth, $userId, $usedModel, $rawMetricsJson, $aiReportJson]);
            $reportId = (int)$db->lastInsertId();
        }

        logActivity(
            $db,
            'report',
            "Draft Laporan Bulanan AI Disusun ({$rawMetrics['client_company']})",
            "Menyusun draft Laporan Bulanan AI periode {$monthLabel} (Status: Menunggu Review & Approval Owner)."
        );

        echo json_encode([
            'success' => true,
            'message' => "Laporan Bulanan AI periode {$monthLabel} berhasil disusun sebagai Draft Review Owner.",
            'report_id' => $reportId,
            'model_used' => $usedModel,
            'status' => 'draft_owner',
            'raw_metrics' => $rawMetrics,
            'ai_report' => $aiReport
        ]);
        exit;
    }

    // =========================================================================
    // 3. SAVE OWNER EDITS TO MONTHLY REPORT
    // =========================================================================
    if ($action === 'save_edits') {
        if (!in_array($role, ['owner', 'admin'], true)) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
            exit;
        }

        $reportId = (int)($data['report_id'] ?? 0);
        $aiReport = $data['ai_report'] ?? null;
        if (is_string($aiReport)) {
            $aiReport = json_decode($aiReport, true);
        }

        if ($reportId <= 0 || !is_array($aiReport)) {
            echo json_encode(['success' => false, 'message' => 'Data perubahan laporan tidak valid.']);
            exit;
        }

        $stmt = $db->prepare("
            UPDATE client_monthly_reports
            SET ai_report_json = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND is_deleted = 0
        ");
        $stmt->execute([json_encode($aiReport, JSON_UNESCAPED_UNICODE), $reportId]);

        echo json_encode([
            'success' => true,
            'message' => 'Perubahan narasi Laporan Bulanan berhasil disimpan.'
        ]);
        exit;
    }

    // =========================================================================
    // 4. APPROVE & PUBLISH TO CLIENT PORTAL (OR REVERT TO DRAFT)
    // =========================================================================
    if ($action === 'publish_report' || $action === 'unpublish_report') {
        if (!in_array($role, ['owner', 'admin'], true)) {
            echo json_encode(['success' => false, 'message' => 'Hanya Owner/Admin yang dapat melakukan approval laporan.']);
            exit;
        }

        $reportId = (int)($data['report_id'] ?? 0);
        if ($reportId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID Laporan tidak valid.']);
            exit;
        }

        $newStatus = ($action === 'publish_report') ? 'published_client' : 'draft_owner';
        $pubTime   = ($action === 'publish_report') ? date('Y-m-d H:i:s') : null;

        $stmt = $db->prepare("
            UPDATE client_monthly_reports
            SET status = ?, published_at = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND is_deleted = 0
        ");
        $stmt->execute([$newStatus, $pubTime, $reportId]);

        $infoStmt = $db->prepare("
            SELECT r.report_month, c.company, c.name
            FROM client_monthly_reports r
            LEFT JOIN clients c ON r.client_id = c.id
            WHERE r.id = ? LIMIT 1
        ");
        $infoStmt->execute([$reportId]);
        $info = $infoStmt->fetch(PDO::FETCH_ASSOC);
        $clientLabel = $info ? ($info['company'] ?: $info['name']) : 'Klien';
        $periodLabel = $info['report_month'] ?? '';

        if ($newStatus === 'published_client') {
            logActivity(
                $db,
                'report',
                "Laporan Bulanan AI Di-Approve & Dikirim ({$clientLabel})",
                "Owner menyetujui & mempublikasikan Laporan Bulanan AI periode {$periodLabel} ke Dashboard Portal Klien."
            );
            $msg = "Laporan Bulanan ({$periodLabel}) telah di-Approve dan resmi ditampilkan di Dashboard Portal Klien ({$clientLabel}).";
        } else {
            $msg = "Laporan Bulanan ({$periodLabel}) dikembalikan ke status Draft Review Owner (ditarik dari Portal Klien).";
        }

        echo json_encode([
            'success' => true,
            'status' => $newStatus,
            'published_at' => $pubTime,
            'message' => $msg
        ]);
        exit;
    }

    // =========================================================================
    // 5. DELETE MONTHLY REPORT
    // =========================================================================
    if ($action === 'delete_report') {
        if (!in_array($role, ['owner', 'admin'], true)) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
            exit;
        }
        $reportId = (int)($data['report_id'] ?? 0);
        $db->prepare("UPDATE client_monthly_reports SET is_deleted = 1 WHERE id = ?")->execute([$reportId]);
        echo json_encode(['success' => true, 'message' => 'Laporan bulanan berhasil dihapus.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Aksi API tidak dikenali.']);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Kesalahan server: ' . $e->getMessage()
    ]);
}


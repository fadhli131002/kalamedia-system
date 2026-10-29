<?php
/**
 * Kala AI Content Calendar Generator API
 * Generates 7, 14, or 30-day actionable content schedules directly from Brand AI Strategy Blueprint
 * and syncs approved items to Kalender Konten (`content_planner` & `content_calendar`) + Client Portal.
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
    // 1. GET CLIENT STRATEGY SUMMARY
    // =========================================================================
    if ($action === 'get_client_strategy') {
        $clientId = (int)($data['client_id'] ?? 0);
        if ($clientId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID Klien tidak valid.']);
            exit;
        }

        $cStmt = $db->prepare("SELECT id, name, company, service_type, package_name FROM clients WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $cStmt->execute([$clientId]);
        $client = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$client) {
            echo json_encode(['success' => false, 'message' => 'Data klien tidak ditemukan.']);
            exit;
        }

        $aStmt = $db->prepare("SELECT * FROM brand_ai_audits WHERE client_id = ? ORDER BY id DESC LIMIT 1");
        $aStmt->execute([$clientId]);
        $audit = $aStmt->fetch(PDO::FETCH_ASSOC);

        $hasStrategy = false;
        $strategySummary = null;

        if ($audit && !empty($audit['ai_analysis_json'])) {
            $aiData = json_decode($audit['ai_analysis_json'], true);
            $qData  = json_decode($audit['questionnaire_json'] ?? '{}', true);
            if (is_array($aiData)) {
                $hasStrategy = true;
                $pillars = [];
                if (!empty($aiData['content_pillars']) && is_array($aiData['content_pillars'])) {
                    foreach ($aiData['content_pillars'] as $p) {
                        if (!empty($p['pillar_name'])) {
                            $pillars[] = $p['pillar_name'] . (isset($p['percentage']) ? ' (' . $p['percentage'] . '%)' : '');
                        }
                    }
                }
                $strategySummary = [
                    'audit_id' => (int)$audit['id'],
                    'brand_stage' => $audit['brand_stage'] ?: ($aiData['brand_stage'] ?? 'Growth'),
                    'health_score' => (int)($audit['health_score'] ?: ($aiData['health_score'] ?? 75)),
                    'recommended_scheme_name' => $audit['recommended_scheme_name'] ?: ($aiData['recommended_scheme_name'] ?? 'Kala Growth Strategy'),
                    'pillars_list' => $pillars,
                    'weekly_schedule' => $aiData['weekly_content_schedule'] ?? [],
                    'usp' => $qData['q2_produk_usp'] ?? '',
                    'target_audience' => $qData['q3_target_audiens'] ?? '',
                    'updated_at' => $audit['updated_at']
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'client' => $client,
            'has_strategy' => $hasStrategy,
            'strategy' => $strategySummary
        ]);
        exit;
    }

    // =========================================================================
    // 2. GENERATE AI CONTENT CALENDAR (7 / 14 / 30 HARI)
    // =========================================================================
    if ($action === 'generate_calendar') {
        if (!in_array($role, ['owner', 'admin'], true)) {
            echo json_encode(['success' => false, 'message' => 'Hanya Owner dan Admin yang dapat menjalankan generator Kalender AI.']);
            exit;
        }

        $clientId = (int)($data['client_id'] ?? 0);
        $durationDays = (int)($data['duration_days'] ?? 7);
        if (!in_array($durationDays, [7, 14, 30], true)) {
            $durationDays = 7;
        }
        $startDate = trim((string)($data['start_date'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = date('Y-m-d');
        }
        $customFocus = trim((string)($data['custom_focus'] ?? ''));

        if ($clientId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Silakan pilih klien terlebih dahulu.']);
            exit;
        }

        $cStmt = $db->prepare("SELECT * FROM clients WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $cStmt->execute([$clientId]);
        $client = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$client) {
            echo json_encode(['success' => false, 'message' => 'Klien tidak ditemukan.']);
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

        // Pull latest Brand AI Audit & Strategy Blueprint
        $aStmt = $db->prepare("SELECT * FROM brand_ai_audits WHERE client_id = ? ORDER BY id DESC LIMIT 1");
        $aStmt->execute([$clientId]);
        $audit = $aStmt->fetch(PDO::FETCH_ASSOC);

        $qData = ($audit && !empty($audit['questionnaire_json'])) ? (json_decode($audit['questionnaire_json'], true) ?: []) : [];
        $aiData = ($audit && !empty($audit['ai_analysis_json'])) ? (json_decode($audit['ai_analysis_json'], true) ?: []) : [];

        $brandName = $qData['q1_nama_industri'] ?? ($client['company'] ?: $client['name']);
        $brandStage = $audit['brand_stage'] ?? ($aiData['brand_stage'] ?? 'Growth & Authority Building');
        $schemeName = $audit['recommended_scheme_name'] ?? ($aiData['recommended_scheme_name'] ?? 'Strategi Konten & Konversi Terpadu');
        $usp = $qData['q2_produk_usp'] ?? ($client['service_type'] ?? 'Produk & layanan unggulan brand');
        $audience = $qData['q3_target_audiens'] ?? 'Target audiens potensial di Indonesia';
        $toneOfVoice = $qData['q14_tone_of_voice'] ?? 'Profesional, relevan, solutif, dan menarik perhatian';
        $target90Days = $qData['q13_target_90_hari'] ?? 'Meningkatkan brand awareness, engagement, dan konversi leads';

        $pillarsText = '';
        if (!empty($aiData['content_pillars']) && is_array($aiData['content_pillars'])) {
            foreach ($aiData['content_pillars'] as $idx => $p) {
                $pName = $p['pillar_name'] ?? ('Pilar ' . ($idx + 1));
                $pPct  = $p['percentage'] ?? '';
                $pObj  = $p['objective'] ?? '';
                $pIdeas = is_array($p['topic_ideas'] ?? null) ? implode('; ', $p['topic_ideas']) : '';
                $pillarsText .= "- {$pName} ({$pPct}%): {$pObj}. Contoh ide: {$pIdeas}\n";
            }
        } else {
            $pillarsText = "- Edukasi & Solusi Masalah Audiens (40%)\n- Social Proof, Portofolio & Testimoni (30%)\n- Behind The Scenes & Brand Authority (15%)\n- Hard/Soft Promo & Call to Action Konversi (15%)\n";
        }

        $weeklyMixText = '';
        if (!empty($aiData['weekly_content_schedule']) && is_array($aiData['weekly_content_schedule'])) {
            $ws = $aiData['weekly_content_schedule'];
            $weeklyMixText = sprintf(
                "Reels: %s/minggu, Carousel: %s/minggu, Single Feed: %s/minggu, Story: %s/hari. Waktu Posting Terbaik: %s",
                $ws['reels_per_week'] ?? 3,
                $ws['carousel_per_week'] ?? 2,
                $ws['single_feed_per_week'] ?? 1,
                $ws['stories_per_day'] ?? 3,
                $ws['best_posting_times'] ?? '12:00 & 19:00 WIB'
            );
        } else {
            $weeklyMixText = "Kombinasi seimbang antara Instagram Reels, Carousel Edukasi, Single Image Promo, dan IG Story Interaktif. Waktu prime time: 11:30 - 13:00 WIB atau 18:30 - 20:30 WIB.";
        }

        $hooksText = '';
        if (!empty($aiData['hook_and_copywriting_formulas']['scroll_stopping_hooks']) && is_array($aiData['hook_and_copywriting_formulas']['scroll_stopping_hooks'])) {
            $hooksText = implode(" | ", $aiData['hook_and_copywriting_formulas']['scroll_stopping_hooks']);
        }
        $captionStructure = $aiData['hook_and_copywriting_formulas']['caption_structure'] ?? 'Hook Kuat -> Agitasi Masalah -> Solusi/Value -> Call to Action (CTA) Jelas + Hashtag Relevan';

        // Determine target item count so 7/14/30 days produce rich, realistic calendars without token truncation
        $targetItemCount = $durationDays === 7 ? 7 : ($durationDays === 14 ? 14 : 24);
        $endDate = date('Y-m-d', strtotime($startDate . ' + ' . ($durationDays - 1) . ' days'));

        $prompt = <<<PROMPT
Anda adalah Creative Content Director & Social Media Strategist Senior di **Kala Kausa Prima (Kalamedia)**.
Tugas Anda adalah menyusun **Rekomendasi Kalender Konten Harian/Mingguan yang Spesifik, Siap Produksi, dan Berkonversi Tinggi** untuk klien berikut berdasarkan Hasil Analisis & Skema Strategi AI mereka:

=== PROFIL & SKEMA STRATEGI AI KLIEN ===
- Nama Perusahaan / Brand: {$brandName} ({$client['company']})
- Tahap Brand Saat Ini (Brand Stage): {$brandStage}
- Skema Strategi Utama: {$schemeName}
- Produk / Layanan & USP: {$usp}
- Target Audiens & Psikografis: {$audience}
- Gaya Bahasa (Tone of Voice): {$toneOfVoice}
- Target Utama 90 Hari: {$target90Days}
- Fokus Tambahan Khusus Periode Ini: {$customFocus}

=== PILAR KONTEN ACUAN ===
{$pillarsText}

=== RASIO FORMAT & JADWAL MINGGUAN ===
{$weeklyMixText}
- Referensi Formula Hook Brand: {$hooksText}
- Struktur Copywriting Caption: {$captionStructure}

=== INSTRUKSI PENYUSUNAN KALENDER KONTEN ===
1. Rentang Waktu Kalender: **{$durationDays} Hari** (Mulai dari tanggal **{$startDate}** sampai **{$endDate}**).
2. Susun tepat **{$targetItemCount} item rencana konten** yang terdistribusi merata sepanjang rentang tanggal {$startDate} s/d {$endDate}.
3. Setiap item konten WAJIB memiliki variasi format yang proporsional (`Instagram Reels`, `Carousel Edukasi`, `Single Image Promo`, `IG Story Interaktif`) dan mengacu pada Pilar Konten di atas.
4. Untuk setiap item, buat:
   - `scheduled_date`: Tanggal tayang format `YYYY-MM-DD` (antara {$startDate} hingga {$endDate}, urut kronologis).
   - `best_time`: Jam posting terbaik format `HH:MM` (contoh: `12:00`, `18:30`, `19:30`).
   - `platform`: `Instagram` (atau `Instagram & TikTok` untuk Reels).
   - `content_format`: Pilih salah satu seperti `Instagram Reels`, `Carousel Edukasi`, `Single Image Promo`, atau `IG Story Interaktif`.
   - `content_pillar`: Nama pilar konten yang menjadi acuan item tersebut.
   - `title`: Judul/topik utama konten yang spesifik dan menarik.
   - `hook_text`: Kalimat Hook 3 detik pertama (untuk Reels) atau judul headline Slide 1 (untuk Carousel/Single) yang memancing audiens berhenti scroll.
   - `visual_brief`: Arahan pengambilan video/desain visual yang jelas dan aplikatif untuk Tim Editor/Desainer.
   - `caption_draft`: Draft caption lengkap (pembuka, isi bernilai, Call-to-Action jelas, dan 4-6 hashtag relevan).

Kembalikan HANYA JSON valid dengan struktur persis berikut (tanpa markdown code block):
{
  "calendar_summary": "Ringkasan singkat strategi distribusi kalender konten {$durationDays} hari ini dan fokus konversinya (2-3 kalimat).",
  "items": [
    {
      "scheduled_date": "{$startDate}",
      "best_time": "19:00",
      "platform": "Instagram",
      "content_format": "Instagram Reels",
      "content_pillar": "Nama Pilar Konten",
      "title": "Judul Topik Utama Konten",
      "hook_text": "Kalimat hook 3 detik pertama / judul slide 1...",
      "visual_brief": "Arahan visual detail untuk videografer/editor/desainer...",
      "caption_draft": "Draft caption lengkap beserta CTA dan #hashtag..."
    }
  ]
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
                'temperature' => 0.72,
                'maxOutputTokens' => 8192,
                'responseMimeType' => 'application/json'
            ]
        ];

        $geminiResult = kala_call_gemini_with_autodiscovery($apiKey, $selectedModel, $payload, 90, $db);
        if (!$geminiResult['success']) {
            echo json_encode([
                'success' => false,
                'message' => 'Gagal menyusun Kalender Konten AI: ' . ($geminiResult['error_message'] ?? 'Unknown error')
            ]);
            exit;
        }

        $rawText = trim((string)$geminiResult['raw_text']);
        $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', $rawText);
        $cleanJson = preg_replace('/\s*```$/', '', $cleanJson);
        $parsed = json_decode($cleanJson, true);

        if (!is_array($parsed) || empty($parsed['items']) || !is_array($parsed['items'])) {
            if (preg_match('/\{[\s\S]*\}/', $rawText, $m)) {
                $parsed = json_decode($m[0], true);
            }
        }

        if (!is_array($parsed) || empty($parsed['items']) || !is_array($parsed['items'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Respons AI tidak dalam format daftar kalender yang valid. Silakan coba klik Generate kembali.'
            ]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Berhasil menyusun ' . count($parsed['items']) . ' rekomendasi jadwal konten untuk ' . ($client['company'] ?: $client['name']) . '.',
            'model_used' => $geminiResult['used_model'],
            'duration_days' => $durationDays,
            'start_date' => $startDate,
            'calendar_summary' => $parsed['calendar_summary'] ?? '',
            'items' => $parsed['items']
        ]);
        exit;
    }

    // =========================================================================
    // 3. APPLY SELECTED ITEMS TO CONTENT CALENDAR & CLIENT PORTAL
    // =========================================================================
    if ($action === 'apply_to_calendar') {
        if (!in_array($role, ['owner', 'admin'], true)) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
            exit;
        }

        $clientId = (int)($data['client_id'] ?? 0);
        $items = $data['items'] ?? [];
        if (is_string($items)) {
            $items = json_decode($items, true);
        }

        if ($clientId <= 0 || !is_array($items) || empty($items)) {
            echo json_encode(['success' => false, 'message' => 'Pilih minimal 1 item rencana konten untuk diterapkan ke kalender.']);
            exit;
        }

        // Get active project for this client if available
        $pStmt = $db->prepare("SELECT id FROM projects WHERE client_id = ? AND is_deleted = 0 ORDER BY id DESC LIMIT 1");
        $pStmt->execute([$clientId]);
        $projectId = (int)($pStmt->fetchColumn() ?: 0);

        $cStmt = $db->prepare("SELECT company, name FROM clients WHERE id = ? LIMIT 1");
        $cStmt->execute([$clientId]);
        $clientRow = $cStmt->fetch(PDO::FETCH_ASSOC);
        $clientLabel = $clientRow ? ($clientRow['company'] ?: $clientRow['name']) : 'Klien #' . $clientId;

        $insertPlanner = $db->prepare("
            INSERT INTO content_planner (
                client_id, project_id, title, platform, content_type,
                publish_date, publish_time, status, color_hex, notes,
                content_pillar, hook_text, visual_brief, caption_draft, is_ai_generated, is_deleted
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?, ?, ?, ?, ?, ?, 1, 0)
        ");

        $insertCalendar = $db->prepare("
            INSERT INTO content_calendar (
                client_id, project_id, scheduled_date, best_time, platform,
                content_format, content_pillar, title, hook_text, visual_brief, caption_draft, status, is_deleted
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', 0)
        ");

        $savedCount = 0;
        $db->beginTransaction();
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $title = trim((string)($item['title'] ?? ''));
            if ($title === '') continue;

            $scheduledDate = trim((string)($item['scheduled_date'] ?? date('Y-m-d')));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduledDate)) {
                $scheduledDate = date('Y-m-d');
            }

            $bestTime = trim((string)($item['best_time'] ?? '19:00'));
            if (preg_match('/^(\d{1,2}:\d{2})/', $bestTime, $tm)) {
                $publishTimeSql = strlen($tm[1]) === 4 ? ('0' . $tm[1] . ':00') : ($tm[1] . ':00');
                $bestTimeShort = substr($publishTimeSql, 0, 5);
            } else {
                $publishTimeSql = '19:00:00';
                $bestTimeShort = '19:00';
            }

            $platform = trim((string)($item['platform'] ?? 'Instagram'));
            if (stripos($platform, 'TikTok') !== false && stripos($platform, 'Instagram') === false) {
                $platformNormalized = 'TikTok';
            } else {
                $platformNormalized = 'Instagram';
            }

            $contentFormat = trim((string)($item['content_format'] ?? 'Instagram Reels'));
            // Map to standard FullCalendar content_type badge while keeping rich format
            $fmtLower = strtolower($contentFormat);
            if (strpos($fmtLower, 'reel') !== false || strpos($fmtLower, 'video') !== false || strpos($fmtLower, 'tiktok') !== false) {
                $contentType = 'Reels / Video';
                $colorHex = '#EC4899';
            } elseif (strpos($fmtLower, 'carousel') !== false) {
                $contentType = 'Carousel';
                $colorHex = '#6366F1';
            } elseif (strpos($fmtLower, 'story') !== false || strpos($fmtLower, 'stories') !== false) {
                $contentType = 'Story';
                $colorHex = '#F59E0B';
            } else {
                $contentType = 'Feed Single';
                $colorHex = '#10B981';
            }

            $pillar = trim((string)($item['content_pillar'] ?? 'Pilar Strategi Brand'));
            $hook = trim((string)($item['hook_text'] ?? ''));
            $visualBrief = trim((string)($item['visual_brief'] ?? ''));
            $captionDraft = trim((string)($item['caption_draft'] ?? ''));

            $compiledNotes = "[Kala AI Content Strategy]\n"
                . "Format: {$contentFormat} | Pilar: {$pillar}\n"
                . ($hook !== '' ? "Hook (3 Detik / Slide 1): {$hook}\n" : "")
                . ($visualBrief !== '' ? "Visual Brief: {$visualBrief}\n" : "")
                . ($captionDraft !== '' ? "\nDraft Caption:\n{$captionDraft}" : "");

            $insertPlanner->execute([
                $clientId,
                $projectId > 0 ? $projectId : null,
                $title,
                $platformNormalized,
                $contentType,
                $scheduledDate,
                $publishTimeSql,
                $colorHex,
                $compiledNotes,
                $pillar,
                $hook,
                $visualBrief,
                $captionDraft
            ]);

            $insertCalendar->execute([
                $clientId,
                $projectId > 0 ? $projectId : null,
                $scheduledDate,
                $bestTimeShort,
                $platform,
                $contentFormat,
                $pillar,
                $title,
                $hook,
                $visualBrief,
                $captionDraft
            ]);

            $savedCount++;
        }
        $db->commit();

        logActivity(
            $db,
            'content',
            "Kalender Konten AI Diterapkan ({$savedCount} Konten)",
            "Menyimpan {$savedCount} jadwal konten rekomendasi Kala AI ke Kalender Konten & Portal Klien untuk {$clientLabel}."
        );

        echo json_encode([
            'success' => true,
            'saved_count' => $savedCount,
            'message' => "Berhasil menerapkan {$savedCount} jadwal konten ke Kalender Konten & menampilkan di Portal Klien ({$clientLabel})."
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Aksi API tidak dikenali.']);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Kesalahan server: ' . $e->getMessage()
    ]);
}


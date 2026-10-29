<?php
/**
 * Kalamedia - Kala AI Brand Diagnostic & Strategy Blueprint Engine
 * Powered by Google Gemini Pro REST API (generateContent)
 */

header('Content-Type: application/json; charset=utf-8');
set_time_limit(120);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Sesi login tidak ditemukan. Silakan login kembali.'
    ]);
    exit;
}

$db = Database::getConnection();
$driver = Database::getDriver();
$pkDef = ($driver === 'mysql') ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY AUTOINCREMENT";

// Ensure brand_ai_audits table & model_used column exist
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS brand_ai_audits (
            id {$pkDef},
            client_id INT NOT NULL,
            created_by INT DEFAULT NULL,
            model_used VARCHAR(100) DEFAULT 'gemini-2.5-pro',
            questionnaire_json LONGTEXT DEFAULT NULL,
            ai_analysis_json LONGTEXT DEFAULT NULL,
            brand_stage VARCHAR(150) DEFAULT NULL,
            health_score INT DEFAULT 0,
            recommended_scheme_name VARCHAR(200) DEFAULT NULL,
            is_published_to_client TINYINT DEFAULT 0,
            published_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");
} catch (Throwable $e) {}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_audit';
$userRole = current_user_role();
$currentUserId = current_user_id();

/**
 * Compute status badge label for a brand_ai_audits record
 */
function resolve_audit_status(?array $row): string {
    if (!$row) {
        return 'Belum Dianalisis';
    }
    if ((int)($row['is_published_to_client'] ?? 0) === 1) {
        return 'Terkirim ke Klien';
    }
    $hasQ = !empty($row['questionnaire_json']) && $row['questionnaire_json'] !== '{}' && $row['questionnaire_json'] !== '[]';
    $hasA = !empty($row['ai_analysis_json']) && $row['ai_analysis_json'] !== '{}' && $row['ai_analysis_json'] !== '[]';
    if ($hasQ || $hasA) {
        return 'Draft Analisis';
    }
    return 'Belum Dianalisis';
}

// ============================================================================
// 1. GET AUDIT (Accessible by Owner/Admin for any client, or Client if published)
// ============================================================================
if ($action === 'get_audit') {
    $clientId = (int)($_GET['client_id'] ?? $_POST['client_id'] ?? 0);
    if ($userRole === 'client') {
        $clientId = (int)current_user_client_id('client');
    }

    if ($clientId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID Klien tidak valid.']);
        exit;
    }

    $stmtClient = $db->prepare("SELECT id, name, company, email, phone, package_name, service_type FROM clients WHERE id = ? LIMIT 1");
    $stmtClient->execute([$clientId]);
    $client = $stmtClient->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        echo json_encode(['success' => false, 'message' => 'Data klien tidak ditemukan.']);
        exit;
    }

    $stmtAudit = $db->prepare("SELECT * FROM brand_ai_audits WHERE client_id = ? ORDER BY id DESC LIMIT 1");
    $stmtAudit->execute([$clientId]);
    $audit = $stmtAudit->fetch(PDO::FETCH_ASSOC);

    if ($userRole === 'client') {
        if (!$audit || (int)$audit['is_published_to_client'] !== 1) {
            echo json_encode([
                'success' => true,
                'client' => $client,
                'audit' => null,
                'is_published' => false
            ]);
            exit;
        }
        // Strip raw questionnaire for client privacy
        unset($audit['questionnaire_json']);
    }

    $questionnaire = [];
    $analysis = null;
    if ($audit) {
        if (!empty($audit['questionnaire_json'])) {
            $decodedQ = json_decode($audit['questionnaire_json'], true);
            if (is_array($decodedQ)) $questionnaire = $decodedQ;
        }
        if (!empty($audit['ai_analysis_json'])) {
            $decodedA = json_decode($audit['ai_analysis_json'], true);
            if (is_array($decodedA)) $analysis = $decodedA;
        }
    }

    echo json_encode([
        'success' => true,
        'client' => $client,
        'audit' => $audit ? [
            'id' => (int)$audit['id'],
            'client_id' => (int)$audit['client_id'],
            'model_used' => $audit['model_used'] ?: get_agency_setting('gemini_model', 'gemini-2.5-pro'),
            'brand_stage' => $audit['brand_stage'] ?? '',
            'health_score' => (int)($audit['health_score'] ?? 0),
            'recommended_scheme_name' => $audit['recommended_scheme_name'] ?? '',
            'is_published_to_client' => (int)($audit['is_published_to_client'] ?? 0),
            'published_at' => $audit['published_at'] ?? null,
            'updated_at' => $audit['updated_at'] ?? null,
            'status_badge' => resolve_audit_status($audit),
            'questionnaire' => $questionnaire,
            'analysis' => $analysis
        ] : null,
        'configured_model' => get_agency_setting('gemini_model', 'gemini-2.5-pro') ?: 'gemini-2.5-pro',
        'has_api_key' => !empty(get_agency_setting('gemini_api_key', ''))
    ]);
    exit;
}

// All mutating actions require Owner or Admin
if (!is_admin('internal')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Akses ditolak. Hanya Owner dan Admin yang dapat mengelola Kala AI Brand Audit.'
    ]);
    exit;
}

$rawInput = file_get_contents('php://input');
$jsonBody = !empty($rawInput) ? json_decode($rawInput, true) : [];

// ============================================================================
// 2. SAVE DRAFT QUESTIONNAIRE (17 Poin Diagnostik)
// ============================================================================
if ($action === 'save_draft') {
    $clientId = (int)($_POST['client_id'] ?? ($jsonBody['client_id'] ?? 0));
    $answers  = $_POST['questionnaire'] ?? ($jsonBody['questionnaire'] ?? []);
    if (is_string($answers)) {
        $answers = json_decode($answers, true) ?: [];
    }

    if ($clientId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID Klien tidak valid.']);
        exit;
    }

    $normalizedAnswers = [];
    for ($i = 1; $i <= 17; $i++) {
        $key = 'q' . $i;
        $normalizedAnswers[$key] = trim((string)($answers[$key] ?? ''));
    }
    $qJson = json_encode($normalizedAnswers, JSON_UNESCAPED_UNICODE);
    $now = date('Y-m-d H:i:s');

    $stmtExist = $db->prepare("SELECT * FROM brand_ai_audits WHERE client_id = ? ORDER BY id DESC LIMIT 1");
    $stmtExist->execute([$clientId]);
    $existing = $stmtExist->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $stmtUpd = $db->prepare("UPDATE brand_ai_audits SET questionnaire_json = ?, updated_at = ? WHERE id = ?");
        $stmtUpd->execute([$qJson, $now, $existing['id']]);
        $auditId = (int)$existing['id'];
        $existing['questionnaire_json'] = $qJson;
        $statusBadge = resolve_audit_status($existing);
    } else {
        $modelDefault = get_agency_setting('gemini_model', 'gemini-2.5-pro') ?: 'gemini-2.5-pro';
        $stmtIns = $db->prepare("
            INSERT INTO brand_ai_audits (client_id, created_by, model_used, questionnaire_json, is_published_to_client, created_at, updated_at)
            VALUES (?, ?, ?, ?, 0, ?, ?)
        ");
        $stmtIns->execute([$clientId, $currentUserId, $modelDefault, $qJson, $now, $now]);
        $auditId = (int)$db->lastInsertId();
        $statusBadge = 'Draft Analisis';
    }

    echo json_encode([
        'success' => true,
        'message' => 'Draft jawaban kuesioner diagnostik berhasil disimpan!',
        'audit_id' => $auditId,
        'client_id' => $clientId,
        'status_badge' => $statusBadge,
        'questionnaire' => $normalizedAnswers
    ]);
    exit;
}

// ============================================================================
// 3. RUN GEMINI PRO BRAND DIAGNOSTIC & STRATEGY BLUEPRINT
// ============================================================================
if ($action === 'run_analysis') {
    $clientId = (int)($_POST['client_id'] ?? ($jsonBody['client_id'] ?? 0));
    $answers  = $_POST['questionnaire'] ?? ($jsonBody['questionnaire'] ?? []);
    if (is_string($answers)) {
        $answers = json_decode($answers, true) ?: [];
    }

    if ($clientId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID Klien tidak valid.']);
        exit;
    }

    $stmtClient = $db->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
    $stmtClient->execute([$clientId]);
    $client = $stmtClient->fetch(PDO::FETCH_ASSOC);
    if (!$client) {
        echo json_encode(['success' => false, 'message' => 'Data klien tidak ditemukan.']);
        exit;
    }

    // Normalize & save draft first so user answers are never lost
    $normalizedAnswers = [];
    $filledCount = 0;
    for ($i = 1; $i <= 17; $i++) {
        $key = 'q' . $i;
        $val = trim((string)($answers[$key] ?? ''));
        $normalizedAnswers[$key] = $val;
        if ($val !== '') $filledCount++;
    }

    if ($filledCount === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Mohon isi minimal beberapa poin kuesioner diagnostik (misal: Nama Akun & Niche, Target Utama, atau Kendala Terbesar) sebelum menjalankan analisis AI.'
        ]);
        exit;
    }

    $qJson = json_encode($normalizedAnswers, JSON_UNESCAPED_UNICODE);
    $now = date('Y-m-d H:i:s');
    $preferredModel = get_agency_setting('gemini_model', 'gemini-2.5-pro') ?: 'gemini-2.5-pro';
    $preferredModel = preg_replace('#^models/#i', '', $preferredModel);

    $stmtExist = $db->prepare("SELECT * FROM brand_ai_audits WHERE client_id = ? ORDER BY id DESC LIMIT 1");
    $stmtExist->execute([$clientId]);
    $existing = $stmtExist->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $stmtUpd = $db->prepare("UPDATE brand_ai_audits SET questionnaire_json = ?, updated_at = ? WHERE id = ?");
        $stmtUpd->execute([$qJson, $now, $existing['id']]);
        $auditId = (int)$existing['id'];
    } else {
        $stmtIns = $db->prepare("
            INSERT INTO brand_ai_audits (client_id, created_by, model_used, questionnaire_json, is_published_to_client, created_at, updated_at)
            VALUES (?, ?, ?, ?, 0, ?, ?)
        ");
        $stmtIns->execute([$clientId, $currentUserId, $preferredModel, $qJson, $now, $now]);
        $auditId = (int)$db->lastInsertId();
    }

    $apiKey = get_agency_setting('gemini_api_key', '');
    if ($apiKey === '') {
        echo json_encode([
            'success' => false,
            'saved_draft' => true,
            'message' => 'Draft jawaban telah disimpan, namun Google Gemini API Key belum dikonfigurasi. Silakan atur API Key terlebih dahulu di menu Pengaturan Agensi.'
        ]);
        exit;
    }

    $questionLabels = [
        'q1'  => '1. Nama Akun & Niche (Username IG, bidang bisnis, produk/jasa utama)',
        'q2'  => '2. Harga & Model Bisnis (Kisaran harga & cara pelanggan membeli/channel closing)',
        'q3'  => '3. Target Utama 3 Bulan ke Depan (Gol paling mendesak dari Instagram)',
        'q4'  => '4. Unique Selling Proposition / USP (Alasan kuat beli di sini vs kompetitor)',
        'q5'  => '5. Profil Pembeli Ideal (Usia, gender, domisili, daya beli, pain points/kebutuhan)',
        'q6'  => '6. Akun Kompetitor & Referensi (2-3 kompetitor langsung & 1-2 akun referensi gaya konten)',
        'q7'  => '7. Isi Bio & Link Saat Ini (Kalimat bio sekarang & arah link in bio)',
        'q8'  => '8. Pinned Posts & Highlight (Konten yang di-pin & isi Story Highlights saat ini)',
        'q9'  => '9. Statistik Saat Ini (Followers, rata-rata views Reels, reach & interaksi 30 hari terakhir)',
        'q10' => '10. Frekuensi & Format Posting (Frekuensi mingguan Reels/Carousel/Single/Story & format dominan)',
        'q11' => '11. Konten Terbaik & Terburuk (Topik/format 2 konten paling ramai vs paling sepi)',
        'q12' => '12. Gaya Bahasa & Wajah Brand (Sosok talent/founder di kamera & tone of voice)',
        'q13' => '13. Kualitas Interaksi (Kualitas diskusi/tanya produk di komentar & DM)',
        'q14' => '14. Alur Penanganan DM (Siapa admin balas DM, kecepatan respon, isu tanya harga lalu ghosting)',
        'q15' => '15. Tim & Alat Produksi (Struktur tim produksi & alat syuting/editing)',
        'q16' => '16. Pengalaman Iklan & Kolaborasi (Riwayat Boost Post/Meta Ads Manager & KOL/Influencer)',
        'q17' => '17. Kendala Terbesar Saat Ini (1-2 bottleneck paling krusial saat mengelola akun)'
    ];

    $diagnosticText = "DATA PROFIL KLIEN KALAMEDIA:\n";
    $diagnosticText .= "- Nama Brand / Perusahaan: " . ($client['company'] ?: '-') . "\n";
    $diagnosticText .= "- Nama PIC Klien: " . ($client['name'] ?: '-') . "\n";
    $diagnosticText .= "- Layanan Diambil: " . ($client['service_type'] ?: ($client['package_name'] ?: 'Kelola Sosmed & Ads')) . "\n\n";
    $diagnosticText .= "JAWABAN 17 POIN KUESIONER DIAGNOSTIK BRAND (HULU KE HILIR):\n";

    foreach ($questionLabels as $qKey => $qTitle) {
        $ans = $normalizedAnswers[$qKey] !== '' ? $normalizedAnswers[$qKey] : '(Belum ada data spesifik — berikan rekomendasi best-practice sesuai konteks brand)';
        $diagnosticText .= "{$qTitle}:\nJawaban: {$ans}\n\n";
    }

    $systemInstruction = <<<SYS
Anda adalah Principal Social Media & Growth Strategist Senior di Kalamedia Creative Agency (PT Kala Kausa Prima).
Tugas Anda adalah membedah secara tajam, objektif, taktis, dan sangat aplikatif akun Instagram brand klien baru berdasarkan 17 poin diagnostik hulu-ke-hilir, lalu merancang "Kala AI Brand Diagnostic & Strategy Blueprint" untuk 90 hari ke depan.

ATURAN KUALITAS ANALISIS (WAJIB DIIKUTI):
1. DILARANG memberikan saran normatif/klise seperti "posting yang konsisten" atau "buat konten menarik" tanpa contoh konkret.
2. Semua output harus merujuk langsung pada produk, harga, USP, audiens, kompetitor, dan masalah spesifik yang disebutkan di kuesioner klien.
3. Berikan copywriting Bio Instagram yang siap copy-paste (2 opsi berbeda), contoh kalimat Hook 3 detik pertama yang siap dipakai syuting, ide topik konten spesifik, serta skrip percakapan DM kata-demi-kata untuk mengatasi calon pembeli yang tanya harga lalu menghilang (ghosting).
4. Anda WAJIB mengembalikan respons HANYA dalam format JSON valid (tanpa markdown code block ```json) sesuai struktur skema yang diminta.
SYS;

    $userPrompt = <<<PROMPT
Berdasarkan data diagnostik klien berikut, susun laporan lengkap "Kala AI Brand Diagnostic & Strategy Blueprint" dalam bahasa Indonesia yang profesional, tajam, dan siap dipresentasikan ke klien eksekutif.

{$diagnosticText}

Kembalikan HANYA objek JSON valid dengan struktur persis berikut:
{
  "brand_stage": "Nama Fase/Stage Brand saat ini (contoh: 'Stage 1: Early Storefront — Awareness Rendah & Funnel Belum Siap' atau 'Stage 2: High Reach, Low Conversion — Ramai Penonton tapi Bocor di Closing' atau 'Stage 3: Ready to Scale — Fondasi Kuat, Siap Akselerasi Ads')",
  "brand_stage_reason": "Penjelasan tajam 2-3 kalimat mengapa brand berada di fase ini berdasarkan kondisi profil, konten, dan konversi saat ini.",
  "health_score": {
    "overall": 68,
    "storefront_score": 65,
    "content_score": 70,
    "engagement_closing_score": 60,
    "production_ads_score": 75,
    "score_verdict": "Ringkasan evaluasi skor kesehatan akun (1-2 kalimat tegas)."
  },
  "executive_diagnosis": {
    "overview": "Ringkasan eksekutif bedah kondisi akun dari hulu ke hilir (2-3 kalimat).",
    "strengths": [
      "Poin kekuatan 1 yang sudah berjalan baik atau menjadi modal kuat brand",
      "Poin kekuatan 2",
      "Poin kekuatan 3"
    ],
    "core_bottlenecks": [
      "Titik bocor / akar masalah utama 1 (jelaskan dampaknya ke omzet/engagement)",
      "Titik bocor / akar masalah utama 2",
      "Titik bocor / akar masalah utama 3"
    ]
  },
  "recommended_scheme": {
    "scheme_name": "Nama Skema Strategi Utama 90 Hari (contoh: 'Trust-First Authority & WhatsApp Closing Funnel' atau 'Viral Problem-Hook & High-Ticket Consultation Engine')",
    "philosophy": "Penjelasan filosofi & arsitektur strategi ini mengapa paling tepat untuk mencapai target 3 bulan klien.",
    "north_star_metric": "Metrik kesuksesan utama yang wajib dipantau (contoh: Saves/Shares Rate > 5% & Rasio Closing DM > 25%)"
  },
  "storefront_revamp": {
    "bio_option_1": "Baris 1: Value Proposition / Positioning\nBaris 2: Bukti Sosial / Keunggulan Utama (USP)\nBaris 3: Call to Action tegas mengarah ke Link",
    "bio_option_2": "Baris 1: Problem-Solution Hook untuk Target Audiens\nBaris 2: Spesialisasi Produk & Garansi/Keunikan\nBaris 3: CTA Spesifik Promo/Konsultasi",
    "link_in_bio_funnel": "Rekomendasi arsitektur Link in Bio & landing funnel terbaik agar pengunjung tidak bingung dan langsung mengarah ke pembelian/konsultasi.",
    "pinned_posts": [
      {
        "slot": "Pinned Post #1 (Brand Story / Why Us)",
        "title": "Judul Konsep Konten Pin 1",
        "format": "Reels / Carousel",
        "concept": "Detail isi slide/adegan dan tujuan psikologisnya bagi pengunjung baru."
      },
      {
        "slot": "Pinned Post #2 (Social Proof / Best Seller Breakdown)",
        "title": "Judul Konsep Konten Pin 2",
        "format": "Carousel / Reels",
        "concept": "Detail isi konten yang menjawab keraguan terbesar pembeli."
      },
      {
        "slot": "Pinned Post #3 (How to Order / Promo Offer)",
        "title": "Judul Konsep Konten Pin 3",
        "format": "Carousel / Reels",
        "concept": "Panduan jelas cara order, katalog paket, atau penawaran entry-point."
      }
    ],
    "story_highlights": [
      "Nama Highlight 1: Isi & fungsinya membangun trust",
      "Nama Highlight 2: Isi & fungsinya",
      "Nama Highlight 3: Isi & fungsinya",
      "Nama Highlight 4: Isi & fungsinya",
      "Nama Highlight 5: Isi & fungsinya"
    ]
  },
  "content_strategy": {
    "content_pillars": [
      {
        "pillar_name": "Nama Pilar Konten 1 (beserta persentase porsi, misal 40%)",
        "objective": "Tujuan pilar (Awareness / Trust / Conversion)",
        "topic_ideas": [
          "Ide judul konten konkret 1 siap produksi",
          "Ide judul konten konkret 2 siap produksi",
          "Ide judul konten konkret 3 siap produksi"
        ]
      },
      {
        "pillar_name": "Nama Pilar Konten 2 (misal 35%)",
        "objective": "Tujuan pilar",
        "topic_ideas": [
          "Ide judul konten konkret 1",
          "Ide judul konten konkret 2",
          "Ide judul konten konkret 3"
        ]
      },
      {
        "pillar_name": "Nama Pilar Konten 3 (misal 25%)",
        "objective": "Tujuan pilar",
        "topic_ideas": [
          "Ide judul konten konkret 1",
          "Ide judul konten konkret 2",
          "Ide judul konten konkret 3"
        ]
      }
    ],
    "weekly_schedule": {
      "composition_ratio": "Rasio komposisi format mingguan (contoh: 4x Reels, 2x Carousel Edukasi/Katalog, 1x Single Proof, 3-5 Story/hari)",
      "schedule_plan": "Rincian jadwal hari Senin s/d Minggu beserta fokus formatnya."
    },
    "hook_and_persona": {
      "persona_and_tone": "Arahan pembawaan talent/wajah brand di kamera dan gaya bahasa yang paling cocok dengan target audiens.",
      "hook_formulas": [
        "Contoh kalimat Hook 3 detik pertama #1 (Problem/Pain Hook)",
        "Contoh kalimat Hook 3 detik pertama #2 (Curiosity/Myth-Busting Hook)",
        "Contoh kalimat Hook 3 detik pertama #3 (Social Proof/Transformation Hook)"
      ]
    }
  },
  "closing_and_ads_blueprint": {
    "dm_sop_script": {
      "response_sla": "Target standar waktu balas DM & aturan dasar admin",
      "step_1_greeting_diagnose": "Skrip balasan pertama saat prospek tanya 'Berapa harganya kak?' (jangan langsung lempar angka mati, bangun value & tanya kebutuhan dulu)",
      "step_2_value_and_price": "Skrip cara menyebutkan harga yang dibungkus value/benefit + rekomendasi paket",
      "step_3_anti_ghosting_followup": "Skrip follow-up elegan H+1 dan H+3 jika calon pembeli belum membalas (anti-ghosting)"
    },
    "ads_and_kol_strategy": {
      "meta_ads_funnel": "Strategi penggunaan Instagram/Meta Ads (objektif kampanye, targeting, materi winning content yang di-boost/diiklankan, dan alokasi budget efisien)",
      "kol_collaboration": "Strategi pemilihan tier KOL/Influencer, skema barter/paid review, dan brief angle konten KOL agar menghasilkan konversi nyata"
    }
  },
  "roadmap_90_days": {
    "month_1": {
      "phase_title": "Bulan 1: Foundation, Storefront Revamp & Quick Wins",
      "focus": "Fokus utama bulan pertama",
      "action_items": [
        "Langkah aksi konkret minggu 1-2",
        "Langkah aksi konkret minggu 2-3",
        "Langkah aksi konkret minggu 3-4",
        "Target KPI Bulan 1"
      ]
    },
    "month_2": {
      "phase_title": "Bulan 2: Content Momentum, Trust & Engagement Growth",
      "focus": "Fokus utama bulan kedua",
      "action_items": [
        "Langkah aksi konkret 1",
        "Langkah aksi konkret 2",
        "Langkah aksi konkret 3",
        "Target KPI Bulan 2"
      ]
    },
    "month_3": {
      "phase_title": "Bulan 3: Funnel Optimization, Paid Ads & Scale-Up",
      "focus": "Fokus utama bulan ketiga",
      "action_items": [
        "Langkah aksi konkret 1",
        "Langkah aksi konkret 2",
        "Langkah aksi konkret 3",
        "Target KPI Bulan 3"
      ]
    }
  }
}
PROMPT;

    $payload = [
        'systemInstruction' => [
            'parts' => [
                ['text' => $systemInstruction]
            ]
        ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $userPrompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.4,
            'responseMimeType' => 'application/json'
        ]
    ];

    // Validator closure to ensure Gemini response is valid structured Blueprint JSON
    $jsonValidator = function (string $rawText, array $decoded, string $candidateModel): array {
        $cleanText = preg_replace('/^```(?:json)?\s*/i', '', trim($rawText));
        $cleanText = preg_replace('/\s*```$/', '', $cleanText);
        $candidateJson = json_decode($cleanText, true);

        if (is_array($candidateJson) && (!empty($candidateJson['brand_stage']) || !empty($candidateJson['recommended_scheme']))) {
            return [
                'valid' => true,
                'data' => $candidateJson
            ];
        }
        return [
            'valid' => false,
            'error' => "Format output AI dari {$candidateModel} tidak valid JSON."
        ];
    };

    $aiCallResult = kala_call_gemini_with_autodiscovery($apiKey, $preferredModel, $payload, 95, $jsonValidator);

    if (empty($aiCallResult['success']) || empty($aiCallResult['validated_data'])) {
        $errMsg = $aiCallResult['message'] ?? 'Gagal memanggil Google Gemini Pro API.';
        echo json_encode([
            'success' => false,
            'saved_draft' => true,
            'model_requested' => $preferredModel,
            'primary_error' => $aiCallResult['primary_error'] ?? '',
            'attempts' => $aiCallResult['attempts'] ?? [],
            'message' => 'Gagal menjalankan analisis Gemini Pro: ' . $errMsg
        ]);
        exit;
    }

    $parsedAnalysis = $aiCallResult['validated_data'];
    $usedModel = $aiCallResult['model_used'] ?: $preferredModel;
    $usedFallback = !empty($aiCallResult['used_fallback']);
    $attemptsLog = $aiCallResult['attempts'] ?? [];

    $brandStage = trim((string)($parsedAnalysis['brand_stage'] ?? 'Stage 1: Foundation & Storefront Optimization'));
    $overallScore = (int)($parsedAnalysis['health_score']['overall'] ?? ($parsedAnalysis['health_score'] ?? 70));
    $overallScore = max(0, min(100, $overallScore));
    $schemeName = trim((string)($parsedAnalysis['recommended_scheme']['scheme_name'] ?? 'Kala 90-Day Growth & Conversion Blueprint'));

    $parsedAnalysis['_meta'] = [
        'model_requested' => $preferredModel,
        'model_used' => $usedModel,
        'used_fallback' => $usedFallback,
        'attempts' => $attemptsLog,
        'generated_at' => $now,
        'generated_by' => current_user()['name'] ?? 'Admin Kalamedia'
    ];

    $analysisJson = json_encode($parsedAnalysis, JSON_UNESCAPED_UNICODE);

    $stmtSave = $db->prepare("
        UPDATE brand_ai_audits
        SET model_used = ?,
            questionnaire_json = ?,
            ai_analysis_json = ?,
            brand_stage = ?,
            health_score = ?,
            recommended_scheme_name = ?,
            updated_at = ?
        WHERE id = ?
    ");
    $stmtSave->execute([
        $usedModel,
        $qJson,
        $analysisJson,
        $brandStage,
        $overallScore,
        $schemeName,
        $now,
        $auditId
    ]);

    try {
        $stmtLog = $db->prepare("INSERT INTO activities (user_id, type, title, description) VALUES (?, 'ai_audit', ?, ?)");
        $stmtLog->execute([
            $currentUserId,
            "Analisis Kala AI: " . ($client['company'] ?: 'Klien'),
            "Menjalankan Kala AI Brand Diagnostic ({$usedModel}) — Skor: {$overallScore}/100 • Skema: {$schemeName}"
        ]);
    } catch (Throwable $e) {}

    $stmtFresh = $db->prepare("SELECT * FROM brand_ai_audits WHERE id = ? LIMIT 1");
    $stmtFresh->execute([$auditId]);
    $freshRow = $stmtFresh->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => $usedFallback
            ? "Analisis Kala AI berhasil disusun menggunakan {$usedModel} (Auto-Discovery dari {$preferredModel})!"
            : "Analisis Kala AI berhasil disusun menggunakan {$usedModel}!",
        'model_requested' => $preferredModel,
        'model_used' => $usedModel,
        'used_fallback' => $usedFallback,
        'attempts' => $attemptsLog,
        'audit' => [
            'id' => $auditId,
            'client_id' => $clientId,
            'model_used' => $usedModel,
            'brand_stage' => $brandStage,
            'health_score' => $overallScore,
            'recommended_scheme_name' => $schemeName,
            'is_published_to_client' => (int)($freshRow['is_published_to_client'] ?? 0),
            'published_at' => $freshRow['published_at'] ?? null,
            'updated_at' => $now,
            'status_badge' => resolve_audit_status($freshRow),
            'questionnaire' => $normalizedAnswers,
            'analysis' => $parsedAnalysis
        ]
    ]);
    exit;
}

// ============================================================================
// 4. SAVE EDITED ANALYSIS (Admin/Owner Refines AI Output)
// ============================================================================
if ($action === 'save_edited_analysis') {
    $clientId = (int)($_POST['client_id'] ?? ($jsonBody['client_id'] ?? 0));
    $analysis = $_POST['analysis'] ?? ($jsonBody['analysis'] ?? null);
    if (is_string($analysis)) {
        $analysis = json_decode($analysis, true);
    }

    if ($clientId <= 0 || !is_array($analysis)) {
        echo json_encode(['success' => false, 'message' => 'Data hasil analisis tidak valid.']);
        exit;
    }

    $stmtExist = $db->prepare("SELECT * FROM brand_ai_audits WHERE client_id = ? ORDER BY id DESC LIMIT 1");
    $stmtExist->execute([$clientId]);
    $existing = $stmtExist->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        echo json_encode(['success' => false, 'message' => 'Data audit klien belum ditemukan.']);
        exit;
    }

    $brandStage = trim((string)($analysis['brand_stage'] ?? $existing['brand_stage'] ?? ''));
    $overallScore = (int)($analysis['health_score']['overall'] ?? $existing['health_score'] ?? 0);
    $overallScore = max(0, min(100, $overallScore));
    $schemeName = trim((string)($analysis['recommended_scheme']['scheme_name'] ?? $existing['recommended_scheme_name'] ?? ''));
    $now = date('Y-m-d H:i:s');

    $analysis['_meta'] = array_merge($analysis['_meta'] ?? [], [
        'last_edited_at' => $now,
        'last_edited_by' => current_user()['name'] ?? 'Admin Kalamedia'
    ]);

    $analysisJson = json_encode($analysis, JSON_UNESCAPED_UNICODE);

    $stmtUpd = $db->prepare("
        UPDATE brand_ai_audits
        SET ai_analysis_json = ?,
            brand_stage = ?,
            health_score = ?,
            recommended_scheme_name = ?,
            updated_at = ?
        WHERE id = ?
    ");
    $stmtUpd->execute([
        $analysisJson,
        $brandStage,
        $overallScore,
        $schemeName,
        $now,
        $existing['id']
    ]);

    $existing['ai_analysis_json'] = $analysisJson;
    $existing['brand_stage'] = $brandStage;
    $existing['health_score'] = $overallScore;
    $existing['recommended_scheme_name'] = $schemeName;

    echo json_encode([
        'success' => true,
        'message' => 'Perubahan hasil analisis & skema strategi berhasil disimpan!',
        'status_badge' => resolve_audit_status($existing),
        'brand_stage' => $brandStage,
        'health_score' => $overallScore,
        'recommended_scheme_name' => $schemeName,
        'analysis' => $analysis
    ]);
    exit;
}

// ============================================================================
// 5. TOGGLE PUBLISH TO CLIENT PORTAL DASHBOARD
// ============================================================================
if ($action === 'toggle_publish') {
    $clientId = (int)($_POST['client_id'] ?? ($jsonBody['client_id'] ?? 0));
    $publish  = isset($_POST['publish']) ? (int)$_POST['publish'] : (int)($jsonBody['publish'] ?? 1);
    $publish  = $publish ? 1 : 0;

    if ($clientId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID Klien tidak valid.']);
        exit;
    }

    $stmtExist = $db->prepare("SELECT * FROM brand_ai_audits WHERE client_id = ? ORDER BY id DESC LIMIT 1");
    $stmtExist->execute([$clientId]);
    $existing = $stmtExist->fetch(PDO::FETCH_ASSOC);

    if (!$existing || empty($existing['ai_analysis_json'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Belum ada hasil analisis AI untuk klien ini. Silakan jalankan analisis terlebih dahulu.'
        ]);
        exit;
    }

    $now = date('Y-m-d H:i:s');
    $publishedAt = $publish ? $now : null;

    $stmtUpd = $db->prepare("
        UPDATE brand_ai_audits
        SET is_published_to_client = ?,
            published_at = ?,
            updated_at = ?
        WHERE id = ?
    ");
    $stmtUpd->execute([$publish, $publishedAt, $now, $existing['id']]);

    $existing['is_published_to_client'] = $publish;
    $existing['published_at'] = $publishedAt;

    echo json_encode([
        'success' => true,
        'is_published_to_client' => $publish,
        'published_at' => $publishedAt,
        'status_badge' => resolve_audit_status($existing),
        'message' => $publish
            ? '🚀 Hasil Analisis & Skema Strategi berhasil dikirim dan ditampilkan di Dashboard Portal Klien!'
            : 'Hasil Analisis telah ditarik (disembunyikan) dari Dashboard Portal Klien.'
    ]);
    exit;
}

echo json_encode([
    'success' => false,
    'message' => 'Aksi endpoint tidak valid.'
]);


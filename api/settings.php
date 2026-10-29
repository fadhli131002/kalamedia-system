<?php
/**
 * Kalamedia Settings & Google Gemini Pro Engine Configuration API
 * Restricted to: Owner & Admin
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!is_logged_in('internal') || !is_admin('internal')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Akses ditolak. Hanya Owner dan Admin yang dapat mengakses pengaturan integrasi AI.'
    ]);
    exit;
}

$db = Database::getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? 'save_gemini';

// Ensure settings tables exist
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS settings (
            `setting_key` VARCHAR(100) PRIMARY KEY,
            `setting_value` TEXT,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS agency_settings (
            `setting_key` VARCHAR(100) PRIMARY KEY,
            `setting_value` TEXT,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");
} catch (Throwable $e) {}

if ($action === 'get_settings') {
    $apiKey = get_agency_setting('gemini_api_key', '');
    $model  = get_agency_setting('gemini_model', 'gemini-2.5-pro') ?: 'gemini-2.5-pro';
    echo json_encode([
        'success' => true,
        'gemini_api_key' => $apiKey,
        'gemini_model' => $model,
        'has_key' => !empty($apiKey)
    ]);
    exit;
}

if ($action === 'save_gemini') {
    $rawInput = file_get_contents('php://input');
    $jsonBody = !empty($rawInput) ? json_decode($rawInput, true) : [];

    $apiKey = trim($_POST['gemini_api_key'] ?? ($jsonBody['gemini_api_key'] ?? ''));
    $model  = trim($_POST['gemini_model'] ?? ($jsonBody['gemini_model'] ?? 'gemini-2.5-pro'));
    $customModel = trim($_POST['gemini_custom_model'] ?? ($jsonBody['gemini_custom_model'] ?? ''));

    if ($model === 'custom' && $customModel !== '') {
        $model = $customModel;
    }
    // Strip leading "models/" if user pasted full resource name
    $model = preg_replace('#^models/#i', '', $model);
    if ($model === '') {
        $model = 'gemini-2.5-pro';
    }

    set_agency_setting('gemini_api_key', $apiKey);
    set_agency_setting('gemini_model', $model);

    try {
        $uid = current_user_id('internal');
        $stmtLog = $db->prepare("INSERT INTO activities (user_id, type, title, description) VALUES (?, 'system', 'Update Konfigurasi Kala AI Pro', ?)");
        $stmtLog->execute([$uid, "Memperbarui konfigurasi Google Gemini Pro Engine (Model: {$model})"]);
    } catch (Throwable $e) {}

    echo json_encode([
        'success' => true,
        'message' => "Konfigurasi Integrasi Kala AI berhasil disimpan! (Model Utama: {$model})",
        'gemini_model' => $model,
        'has_key' => !empty($apiKey)
    ]);
    exit;
}

if ($action === 'list_models') {
    $rawInput = file_get_contents('php://input');
    $jsonBody = !empty($rawInput) ? json_decode($rawInput, true) : [];

    $apiKey = trim($_POST['gemini_api_key'] ?? $_GET['gemini_api_key'] ?? ($jsonBody['gemini_api_key'] ?? ''));
    if ($apiKey === '') {
        $apiKey = get_agency_setting('gemini_api_key', '');
    }

    if ($apiKey === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Silakan masukkan Google Gemini API Key terlebih dahulu untuk memuat daftar model dari akun Anda.'
        ]);
        exit;
    }

    $discovery = kala_discover_gemini_models($apiKey);
    if (empty($discovery['success'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal memuat daftar model: ' . ($discovery['error'] ?? 'Unknown Error')
        ]);
        exit;
    }

    $models = $discovery['models'] ?? [];
    set_agency_setting('gemini_api_key', $apiKey);

    echo json_encode([
        'success' => true,
        'message' => 'Berhasil memuat ' . count($models) . ' model aktif dari akun Google Gemini Anda!',
        'models' => $models
    ]);
    exit;
}

if ($action === 'test_gemini') {
    $rawInput = file_get_contents('php://input');
    $jsonBody = !empty($rawInput) ? json_decode($rawInput, true) : [];

    // 1. Read API Key & Model directly from screen form inputs (never stale DB values if provided)
    $apiKey = trim($_POST['gemini_api_key'] ?? ($jsonBody['gemini_api_key'] ?? ''));
    if ($apiKey === '') {
        $apiKey = get_agency_setting('gemini_api_key', '');
    }

    $preferredModel = trim($_POST['gemini_model'] ?? ($jsonBody['gemini_model'] ?? ''));
    $customModel    = trim($_POST['gemini_custom_model'] ?? ($jsonBody['gemini_custom_model'] ?? ''));
    if ($preferredModel === 'custom' && $customModel !== '') {
        $preferredModel = $customModel;
    }
    if ($preferredModel === '' || $preferredModel === 'custom') {
        $preferredModel = get_agency_setting('gemini_model', 'gemini-2.5-pro');
    }
    $preferredModel = preg_replace('#^models/#i', '', $preferredModel);
    if ($preferredModel === '') {
        $preferredModel = 'gemini-2.5-pro';
    }

    if ($apiKey === '') {
        echo json_encode([
            'success' => false,
            'message' => 'API Key Google Gemini masih kosong. Silakan tempelkan Gemini API Key Anda terlebih dahulu.'
        ]);
        exit;
    }

    if (!function_exists('curl_init')) {
        echo json_encode([
            'success' => false,
            'message' => 'Ekstensi PHP cURL belum aktif di server ini.'
        ]);
        exit;
    }

    // Auto-save current input before testing
    set_agency_setting('gemini_api_key', $apiKey);
    set_agency_setting('gemini_model', $preferredModel);

    $payload = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => 'Balas dalam 1 kalimat singkat Bahasa Indonesia: Konfirmasi bahwa Kala AI Brand Diagnostic (Google Gemini Pro Engine) siap digunakan.']
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.2,
            'maxOutputTokens' => 256
        ]
    ];

    // 2. Call Gemini with Auto-Discovery from GET /v1beta/models on 404 / deprecated / 429
    $result = kala_call_gemini_with_autodiscovery($apiKey, $preferredModel, $payload, 35);

    if (!empty($result['success'])) {
        $usedModel = $result['model_used'];
        $latencyMs = $result['latency_ms'];
        $isFallback = !empty($result['used_fallback']);
        $replyText = trim($result['reply_text'] ?? '') ?: 'Koneksi Google Gemini Pro Engine aktif & siap menganalisis brand.';

        echo json_encode([
            'success' => true,
            'message' => $isFallback
                ? "Koneksi Berhasil via Auto-Discovery! Model aktif dialihkan otomatis ke {$usedModel} ({$latencyMs} ms)."
                : "Koneksi Gemini Pro Berhasil! Model aktif: {$usedModel} ({$latencyMs} ms).",
            'model_requested' => $preferredModel,
            'model_used' => $usedModel,
            'used_fallback' => $isFallback,
            'latency_ms' => $latencyMs,
            'sample_reply' => $replyText,
            'attempts' => $result['attempts'] ?? [],
            'discovered_models' => $result['discovered_models'] ?? []
        ]);
        exit;
    }

    echo json_encode([
        'success' => false,
        'http_code' => $result['http_code'] ?? 400,
        'model_requested' => $preferredModel,
        'primary_error' => $result['primary_error'] ?? '',
        'message' => 'Tes Koneksi Gagal: ' . ($result['message'] ?? 'Tidak dapat terhubung ke model Gemini.'),
        'attempts' => $result['attempts'] ?? [],
        'discovered_models' => $result['discovered_models'] ?? []
    ]);
    exit;
}

echo json_encode([
    'success' => false,
    'message' => 'Aksi tidak dikenali.'
]);

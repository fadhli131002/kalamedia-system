<?php
/**
 * Kalamedia Full-Funnel Performance Marketing Reports API Handler
 * Handles CRUD operations, advanced metrics calculations, and creative screenshot uploads
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_auth();

$db = Database::getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// Helper function to handle creative screenshot file uploads
function handleCreativeUpload(string $fileKey, ?string $existingUrl = null): ?string {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE) {
        return $existingUrl;
    }

    if ($_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Gagal mengunggah file untuk {$fileKey}. Error code: " . $_FILES[$fileKey]['error']);
    }

    $file = $_FILES[$fileKey];
    $maxSize = 5 * 1024 * 1024; // 5MB limit
    if ($file['size'] > $maxSize) {
        throw new Exception("Ukuran file kreatif melebihi batas maksimal 5MB!");
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($fileExt, $allowedExtensions)) {
        throw new Exception("Format file gambar tidak didukung. Harap gunakan format JPG, PNG, WEBP, atau SVG.");
    }

    $reportsUploadDir = BASE_PATH . '/assets/uploads/reports';
    if (!is_dir($reportsUploadDir)) {
        mkdir($reportsUploadDir, 0777, true);
    }

    $newFileName = 'creative_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
    $destination = $reportsUploadDir . '/' . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception("Gagal menyimpan file gambar kreatif ke direktori server.");
    }

    return 'assets/uploads/reports/' . $newFileName;
}

function parse_report_number($val): float {
    if (is_numeric($val)) return floatval($val);
    $val = trim((string)$val);
    if ($val === '') return 0.0;
    $val = preg_replace('/[^\d.,]/', '', $val);
    if (substr_count($val, '.') > 1 && strpos($val, ',') !== false) {
        $val = str_replace('.', '', $val);
        $val = str_replace(',', '.', $val);
    } elseif (substr_count($val, '.') > 1) {
        $val = str_replace('.', '', $val);
    } elseif (substr_count($val, ',') > 1) {
        $val = str_replace(',', '', $val);
    } elseif (strpos($val, ',') !== false && strpos($val, '.') === false) {
        $val = str_replace(',', '.', $val);
    }
    return floatval($val);
}

function parse_report_int($val): int {
    if (is_numeric($val)) return intval($val);
    $val = preg_replace('/[^\d]/', '', (string)$val);
    return intval($val ?: 0);
}

// 1. LIST PERFORMANCE REPORTS
if ($action === 'list') {
    try {
        $clientId = !empty($_GET['client_id']) ? intval($_GET['client_id']) : 0;
        $period = trim($_GET['period'] ?? $_GET['month'] ?? '');
        $search = trim($_GET['search'] ?? '');

        $where = ["COALESCE(r.is_deleted, 0) = 0"];
        $params = [];

        if ($clientId > 0) {
            $where[] = "r.client_id = ?";
            $params[] = $clientId;
        }

        if (!empty($period)) {
            $where[] = "r.report_period LIKE ?";
            $params[] = "%$period%";
        }

        if (!empty($search)) {
            $where[] = "(c.company LIKE ? OR c.name LIKE ? OR r.report_period LIKE ? OR r.objective LIKE ? OR r.content_identity LIKE ? OR r.what_worked LIKE ?)";
            $s = "%$search%";
            for ($i = 0; $i < 6; $i++) {
                $params[] = $s;
            }
        }

        $whereSql = implode(' AND ', $where);

        $stmt = $db->prepare("
            SELECT r.*, 
                   c.name as client_name, c.company as client_company, 
                   c.email as client_email, c.phone as client_phone,
                   c.address as client_address, c.logo as client_logo
            FROM performance_reports r
            JOIN clients c ON r.client_id = c.id
            WHERE $whereSql
            ORDER BY r.id DESC
        ");
        $stmt->execute($params);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Aggregate Summary Metrics
        $summaryStmt = $db->prepare("
            SELECT 
                COUNT(*) as total_reports,
                COALESCE(SUM(r.total_ad_spend), 0) as total_spend,
                COALESCE(SUM(r.revenue), 0) as total_revenue,
                COALESCE(SUM(r.total_conversions), 0) as total_conversions,
                COALESCE(SUM(r.ads_reach), 0) as total_reach,
                COALESCE(SUM(r.ads_impressions), 0) as total_impressions,
                COALESCE(SUM(r.total_views), 0) as total_views,
                COALESCE(AVG(r.avg_video_retention), 0) as avg_retention,
                COALESCE(AVG(r.engagement_rate), 0) as avg_er
            FROM performance_reports r
            JOIN clients c ON r.client_id = c.id
            WHERE $whereSql
        ");
        $summaryStmt->execute($params);
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

        $totalSpend = floatval($summary['total_spend'] ?? 0);
        $totalRevenue = floatval($summary['total_revenue'] ?? 0);
        $blendedRoas = $totalSpend > 0 ? round($totalRevenue / $totalSpend, 2) : 0;
        $totalConversions = intval($summary['total_conversions'] ?? 0);
        $avgCplCpa = $totalConversions > 0 ? round($totalSpend / $totalConversions, 2) : 0;

        $summary['blended_roas'] = $blendedRoas;
        $summary['avg_cpl_cpa'] = $avgCplCpa;

        echo json_encode([
            'success' => true,
            'total' => count($reports),
            'summary' => $summary,
            'data' => $reports
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 2. GET SINGLE PERFORMANCE REPORT DETAILS
if ($action === 'get_details') {
    try {
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID laporan tidak valid.']);
            exit;
        }

        $stmt = $db->prepare("
            SELECT r.*, 
                   c.name as client_name, c.company as client_company, 
                   c.email as client_email, c.phone as client_phone,
                   c.address as client_address, c.logo as client_logo
            FROM performance_reports r
            JOIN clients c ON r.client_id = c.id
            WHERE r.id = ? AND COALESCE(r.is_deleted, 0) = 0
        ");
        $stmt->execute([$id]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$report) {
            echo json_encode(['success' => false, 'message' => 'Laporan kinerja performa tidak ditemukan.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'data' => $report
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 3. SAVE PERFORMANCE REPORT (CREATE / UPDATE)
if ($action === 'save') {
    try {
        $id = intval($_POST['id'] ?? 0);
        $clientId = intval($_POST['client_id'] ?? 0);
        $reportPeriod = trim($_POST['report_period'] ?? $_POST['report_month'] ?? '');
        $objective = trim($_POST['objective'] ?? '');

        // Tab 1: Business Summary Metrics
        $totalAdSpend = parse_report_number($_POST['total_ad_spend'] ?? '0');
        $revenue = parse_report_number($_POST['revenue'] ?? '0');
        $totalConversions = parse_report_int($_POST['total_conversions'] ?? '0');
        
        // Compute ROAS, ROI, and CPL/CPA
        $roas = !empty($_POST['roas']) 
            ? parse_report_number($_POST['roas']) 
            : ($totalAdSpend > 0 ? round($revenue / $totalAdSpend, 2) : 0);

        $roi = !empty($_POST['roi']) 
            ? parse_report_number($_POST['roi']) 
            : ($totalAdSpend > 0 ? round((($revenue - $totalAdSpend) / $totalAdSpend) * 100, 2) : 0);

        $cplCpa = !empty($_POST['cpl_cpa']) 
            ? parse_report_number($_POST['cpl_cpa']) 
            : ($totalConversions > 0 ? round($totalAdSpend / $totalConversions, 2) : 0);

        // Tab 2: Paid Ads Performance
        $adsReach = parse_report_int($_POST['ads_reach'] ?? '0');
        $adsImpressions = parse_report_int($_POST['ads_impressions'] ?? '0');
        $adsCtr = parse_report_number($_POST['ads_ctr'] ?? '0');
        $adsCpc = parse_report_number($_POST['ads_cpc'] ?? '0');
        $adsCpm = parse_report_number($_POST['ads_cpm'] ?? '0');
        $lostIsRank = parse_report_number($_POST['lost_is_rank'] ?? '0');
        $lostIsBudget = parse_report_number($_POST['lost_is_budget'] ?? '0');
        $adsEvaluation = trim($_POST['ads_evaluation'] ?? '');

        // Tab 3: Content & Retention
        $contentIdentity = trim($_POST['content_identity'] ?? '');
        $totalViews = parse_report_int($_POST['total_views'] ?? '0');
        $followersGained = parse_report_int($_POST['followers_gained'] ?? '0');
        $avgVideoRetention = parse_report_number($_POST['avg_video_retention'] ?? '0');
        $engagementRate = parse_report_number($_POST['engagement_rate'] ?? '0');

        // Handle Image Uploads or Existing URLs
        $existingWinningUrl = trim($_POST['existing_winning_content_url'] ?? $_POST['winning_content_url'] ?? '');
        $existingUnderperformingUrl = trim($_POST['existing_underperforming_content_url'] ?? $_POST['underperforming_content_url'] ?? '');

        $winningContentUrl = handleCreativeUpload('winning_content_file', $existingWinningUrl);
        $underperformingContentUrl = handleCreativeUpload('underperforming_content_file', $existingUnderperformingUrl);

        // Tab 4: Insight & Action Plan
        $whatWorked = trim($_POST['what_worked'] ?? '');
        $whatDidntWork = trim($_POST['what_didnt_work'] ?? '');
        $nextActionPlan = trim($_POST['next_action_plan'] ?? '');

        // Validation
        if ($clientId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Silakan pilih Klien terlebih dahulu.']);
            exit;
        }

        if (empty($reportPeriod)) {
            echo json_encode(['success' => false, 'message' => 'Periode laporan wajib diisi (misal: August 2026).']);
            exit;
        }

        // Verify client exists
        $clientStmt = $db->prepare("SELECT company, name FROM clients WHERE id = ?");
        $clientStmt->execute([$clientId]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
        if (!$client) {
            echo json_encode(['success' => false, 'message' => 'Data klien tidak ditemukan di database.']);
            exit;
        }

        if ($id > 0) {
            // Update existing performance report
            $stmt = $db->prepare("
                UPDATE performance_reports SET
                    client_id = ?,
                    report_period = ?,
                    objective = ?,
                    total_ad_spend = ?,
                    revenue = ?,
                    roas = ?,
                    roi = ?,
                    total_conversions = ?,
                    cpl_cpa = ?,
                    ads_reach = ?,
                    ads_impressions = ?,
                    ads_ctr = ?,
                    ads_cpc = ?,
                    ads_cpm = ?,
                    lost_is_rank = ?,
                    lost_is_budget = ?,
                    ads_evaluation = ?,
                    content_identity = ?,
                    total_views = ?,
                    followers_gained = ?,
                    avg_video_retention = ?,
                    engagement_rate = ?,
                    winning_content_url = ?,
                    underperforming_content_url = ?,
                    what_worked = ?,
                    what_didnt_work = ?,
                    next_action_plan = ?,
                    updated_at = datetime('now', 'localtime')
                WHERE id = ? AND COALESCE(is_deleted, 0) = 0
            ");
            $stmt->execute([
                $clientId,
                $reportPeriod,
                $objective,
                $totalAdSpend,
                $revenue,
                $roas,
                $roi,
                $totalConversions,
                $cplCpa,
                $adsReach,
                $adsImpressions,
                $adsCtr,
                $adsCpc,
                $adsCpm,
                $lostIsRank,
                $lostIsBudget,
                $adsEvaluation,
                $contentIdentity,
                $totalViews,
                $followersGained,
                $avgVideoRetention,
                $engagementRate,
                $winningContentUrl,
                $underperformingContentUrl,
                $whatWorked,
                $whatDidntWork,
                $nextActionPlan,
                $id
            ]);

            log_activity('report', 'Update Performance Report', "Memperbarui laporan performance marketing {$reportPeriod} ({$objective}) untuk {$client['company']}", $totalAdSpend);

            echo json_encode([
                'success' => true,
                'message' => 'Laporan Performance Marketing berhasil diperbarui!',
                'report_id' => $id,
                'redirect' => url("report-deck?id=$id")
            ]);
            exit;
        } else {
            // Create new performance report
            $stmt = $db->prepare("
                INSERT INTO performance_reports (
                    client_id, report_period, objective,
                    total_ad_spend, revenue, roas, roi, total_conversions, cpl_cpa,
                    ads_reach, ads_impressions, ads_ctr, ads_cpc, ads_cpm,
                    lost_is_rank, lost_is_budget, ads_evaluation,
                    content_identity, total_views, followers_gained, avg_video_retention, engagement_rate,
                    winning_content_url, underperforming_content_url,
                    what_worked, what_didnt_work, next_action_plan,
                    created_at, updated_at, is_deleted
                ) VALUES (
                    ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?,
                    ?, ?, ?,
                    datetime('now', 'localtime'), datetime('now', 'localtime'), 0
                )
            ");
            $stmt->execute([
                $clientId,
                $reportPeriod,
                $objective,
                $totalAdSpend,
                $revenue,
                $roas,
                $roi,
                $totalConversions,
                $cplCpa,
                $adsReach,
                $adsImpressions,
                $adsCtr,
                $adsCpc,
                $adsCpm,
                $lostIsRank,
                $lostIsBudget,
                $adsEvaluation,
                $contentIdentity,
                $totalViews,
                $followersGained,
                $avgVideoRetention,
                $engagementRate,
                $winningContentUrl,
                $underperformingContentUrl,
                $whatWorked,
                $whatDidntWork,
                $nextActionPlan
            ]);

            $newId = $db->lastInsertId();
            log_activity('report', 'Buat Performance Report Baru', "Menerbitkan laporan performance marketing {$reportPeriod} ({$objective}) untuk {$client['company']}", $totalAdSpend);

            echo json_encode([
                'success' => true,
                'message' => 'Laporan Performance Marketing berhasil diterbitkan!',
                'report_id' => $newId,
                'redirect' => url("report-deck?id=$newId")
            ]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        exit;
    }
}

// 4. SOFT DELETE PERFORMANCE REPORT
if ($action === 'delete') {
    try {
        $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID laporan tidak valid.']);
            exit;
        }

        $stmt = $db->prepare("
            SELECT r.*, c.company 
            FROM performance_reports r 
            JOIN clients c ON r.client_id = c.id 
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $rep = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($rep) {
            $delStmt = $db->prepare("UPDATE performance_reports SET is_deleted = 1, updated_at = datetime('now', 'localtime') WHERE id = ?");
            $delStmt->execute([$id]);
            log_activity('report', 'Hapus Performance Report', "Menghapus laporan performa {$rep['report_period']} untuk {$rep['company']}");
        }

        echo json_encode([
            'success' => true,
            'message' => 'Laporan kinerja performa berhasil dihapus.'
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 5. STANDALONE UPLOAD IMAGE ENDPOINT (AJAX Preview)
if ($action === 'upload_image') {
    try {
        $fileKey = $_POST['file_key'] ?? 'file';
        $uploadedPath = handleCreativeUpload($fileKey);
        if ($uploadedPath) {
            echo json_encode([
                'success' => true,
                'file_path' => $uploadedPath,
                'file_url' => url($uploadedPath)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Tidak ada file yang diunggah.']);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Aksi laporan tidak valid.']);

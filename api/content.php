<?php
/**
 * Kalamedia Content Calendar & Planner API
 * Full CRUD, Lazy-loading date filtering, Drag-and-Drop date updater
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_auth();

$db = Database::getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Pre-defined client colors palette matching Slide 3
$clientColorPalette = [
    '#3B82F6', // Blue
    '#EC4899', // Pink / Magenta
    '#10B981', // Emerald
    '#8B5CF6', // Purple
    '#F59E0B', // Amber
    '#06B6D4', // Cyan
    '#EF4444', // Red
    '#6366F1'  // Indigo
];

$colorThemes = [
    '#3B82F6' => ['bg' => '#EFF6FF', 'text' => '#1D4ED8', 'border' => '#2563EB'],
    '#EC4899' => ['bg' => '#FDF2F8', 'text' => '#BE185D', 'border' => '#EC4899'],
    '#10B981' => ['bg' => '#F0FDF4', 'text' => '#15803D', 'border' => '#16A34A'],
    '#8B5CF6' => ['bg' => '#FAF5FF', 'text' => '#6D28D9', 'border' => '#8B5CF6'],
    '#F59E0B' => ['bg' => '#FFFBEB', 'text' => '#B45309', 'border' => '#F59E0B'],
    '#06B6D4' => ['bg' => '#ECFEFF', 'text' => '#0E7490', 'border' => '#06B6D4'],
    '#EF4444' => ['bg' => '#FEF2F2', 'text' => '#B91C1C', 'border' => '#EF4444'],
    '#6366F1' => ['bg' => '#EEF2FF', 'text' => '#4338CA', 'border' => '#6366F1'],
];

function get_client_color(int $clientId, ?string $customColor = null): string {
    global $clientColorPalette;
    if (!empty($customColor) && $customColor !== '#3B82F6') {
        return $customColor;
    }
    $index = ($clientId - 1) % count($clientColorPalette);
    return $clientColorPalette[$index] ?? '#3B82F6';
}

function get_color_theme(string $hex): array {
    global $colorThemes;
    return $colorThemes[$hex] ?? ['bg' => '#EFF6FF', 'text' => '#1D4ED8', 'border' => '#2563EB'];
}

/**
 * Dispatch Webhook to Make.com / Zapier for Google Calendar Push Notification Integration
 *
 * @param int|array $contentData Either the content ID or an associative array with content attributes
 * @return array Webhook delivery status & diagnostics
 */
function sendGoogleCalendarWebhook($contentData): array {
    $webhookUrl = defined('GCAL_WEBHOOK_URL') ? trim(GCAL_WEBHOOK_URL) : '';
    if (empty($webhookUrl)) {
        return [
            'sent' => false,
            'reason' => 'GCAL_WEBHOOK_URL is not configured in config/app.php'
        ];
    }

    try {
        $db = Database::getConnection();

        // 1. Fetch complete data from database if numeric ID is given
        if (is_numeric($contentData)) {
            $stmt = $db->prepare("
                SELECT cp.*, 
                       c.name as client_name, c.company as client_company,
                       p.name as project_name,
                       e.name as assignee_name, e.email as assignee_email, e.position as assignee_position
                FROM content_planner cp
                JOIN clients c ON cp.client_id = c.id
                LEFT JOIN projects p ON cp.project_id = p.id
                LEFT JOIN employees e ON cp.assignee_id = e.id
                WHERE cp.id = ?
            ");
            $stmt->execute([intval($contentData)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return ['sent' => false, 'reason' => 'Content row not found'];
            }
        } elseif (is_array($contentData)) {
            $row = $contentData;
            // Enrich missing client details if client_id exists
            if (empty($row['client_company']) && !empty($row['client_id'])) {
                $cStmt = $db->prepare("SELECT name, company FROM clients WHERE id = ?");
                $cStmt->execute([$row['client_id']]);
                $cData = $cStmt->fetch(PDO::FETCH_ASSOC);
                $row['client_name'] = $cData['name'] ?? '';
                $row['client_company'] = $cData['company'] ?? ($cData['name'] ?? 'Client');
            }
            // Enrich missing employee email if assignee_id exists
            if (empty($row['assignee_email']) && !empty($row['assignee_id'])) {
                $eStmt = $db->prepare("SELECT name, email, position FROM employees WHERE id = ?");
                $eStmt->execute([$row['assignee_id']]);
                $eData = $eStmt->fetch(PDO::FETCH_ASSOC);
                $row['assignee_name'] = $eData['name'] ?? '';
                $row['assignee_email'] = $eData['email'] ?? '';
            }
        } else {
            return ['sent' => false, 'reason' => 'Invalid content data parameter'];
        }

        $contentId = intval($row['id'] ?? $row['content_id'] ?? 0);
        $title = $row['title'] ?? 'Jadwal Konten Baru';
        $clientName = !empty($row['client_company']) ? $row['client_company'] : ($row['client_name'] ?? 'Client');
        $publishDate = $row['publish_date'] ?? date('Y-m-d');
        $publishTime = !empty($row['publish_time']) ? substr($row['publish_time'], 0, 8) : '10:00:00';
        if (strlen($publishTime) === 5) {
            $publishTime .= ':00';
        }

        // Format ISO 8601 strings (e.g. 2026-08-20T10:00:00+07:00)
        $startDateTimeStr = $publishDate . ' ' . $publishTime;
        $timestamp = strtotime($startDateTimeStr);
        $isoDateTime = $timestamp ? date('c', $timestamp) : ($publishDate . 'T' . $publishTime . '+07:00');
        $endIsoDateTime = $timestamp ? date('c', $timestamp + 3600) : null;

        $assigneeEmail = trim($row['assignee_email'] ?? '');
        $assigneeName = trim($row['assignee_name'] ?? '');
        $status = $row['status'] ?? 'Draft';
        $platform = $row['platform'] ?? 'Instagram';
        $contentType = $row['content_type'] ?? 'Reels / Video';
        $assetUrl = $row['asset_url'] ?? '';

        // Formatted structured description
        $description = "Jadwal tayang konten untuk {$clientName}. Mohon disiapkan asetnya.";
        $fullDescription = "Jadwal tayang konten untuk {$clientName}. Mohon disiapkan asetnya.\n\n"
                         . "• Platform: {$platform} ({$contentType})\n"
                         . "• Status: {$status}\n"
                         . (!empty($assigneeName) ? "• PIC Assignee: {$assigneeName} (" . ($assigneeEmail ?: 'no email') . ")\n" : "")
                         . (!empty($assetUrl) ? "• Link Aset: {$assetUrl}\n" : "")
                         . (!empty($row['notes']) ? "• Catatan: {$row['notes']}" : "");

        $payload = [
            'event' => 'content_plan_saved',
            'content_id' => $contentId,
            'title' => $title,
            'client_name' => $clientName,
            'platform' => $platform,
            'content_type' => $contentType,
            'publish_date' => $publishDate,
            'publish_time' => $publishTime,
            'publish_datetime_iso' => $isoDateTime,
            'end_datetime_iso' => $endIsoDateTime,
            'assignee_name' => $assigneeName,
            'assignee_email' => $assigneeEmail,
            'status' => $status,
            'asset_url' => $assetUrl,
            'description' => $description,
            'full_description' => $fullDescription,
            'triggered_at' => date('c')
        ];

        $jsonPayload = json_encode($payload);

        // Execute non-blocking / low-timeout cURL request
        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonPayload),
                'User-Agent: Kalamedia-Agency-System/1.0'
            ],
            CURLOPT_CONNECTTIMEOUT_MS => 1500, // 1.5 second connect timeout
            CURLOPT_TIMEOUT_MS => 2500,        // 2.5 second execution timeout
            CURLOPT_NOSIGNAL => 1,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("Google Calendar Webhook cURL Error: $curlError");
            return [
                'sent' => false,
                'error' => $curlError,
                'payload' => $payload
            ];
        }

        return [
            'sent' => ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'response' => $response,
            'payload' => $payload
        ];
    } catch (Exception $e) {
        error_log("Google Calendar Webhook Exception: " . $e->getMessage());
        return [
            'sent' => false,
            'error' => $e->getMessage()
        ];
    }
}

// 1. GET EVENTS (Formatted for FullCalendar.js with lazy loading)
if ($action === 'get_events') {
    $start = $_GET['start'] ?? '';
    $end = $_GET['end'] ?? '';
    $clientIdsRaw = $_GET['client_ids'] ?? '';
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $platform = trim($_GET['platform'] ?? '');

    $where = ["COALESCE(cp.is_deleted, 0) = 0"];
    $params = [];

    // Lazy load date range
    if (!empty($start)) {
        $startDate = substr($start, 0, 10);
        $where[] = "cp.publish_date >= ?";
        $params[] = $startDate;
    }
    if (!empty($end)) {
        $endDate = substr($end, 0, 10);
        $where[] = "cp.publish_date <= ?";
        $params[] = $endDate;
    }

    // Filter by client IDs (multi-select)
    if (!empty($clientIdsRaw)) {
        $clientIds = array_filter(array_map('intval', explode(',', $clientIdsRaw)));
        if (!empty($clientIds)) {
            $inPlaceholders = implode(',', array_fill(0, count($clientIds), '?'));
            $where[] = "cp.client_id IN ($inPlaceholders)";
            foreach ($clientIds as $cid) {
                $params[] = $cid;
            }
        }
    }

    // Search filter
    if (!empty($search)) {
        $where[] = "(cp.title LIKE ? OR cp.notes LIKE ? OR c.company LIKE ? OR c.name LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // Status filter
    if (!empty($status) && $status !== 'all') {
        $where[] = "cp.status = ?";
        $params[] = $status;
    }

    // Platform filter
    if (!empty($platform) && $platform !== 'all') {
        $where[] = "cp.platform = ?";
        $params[] = $platform;
    }

    $whereSql = implode(' AND ', $where);

    $sql = "
        SELECT cp.*, 
               c.name as client_name, c.company as client_company,
               p.name as project_name,
               e.name as assignee_name, e.position as assignee_position
        FROM content_planner cp
        JOIN clients c ON cp.client_id = c.id
        LEFT JOIN projects p ON cp.project_id = p.id
        LEFT JOIN employees e ON cp.assignee_id = e.id
        WHERE $whereSql
        ORDER BY cp.publish_date ASC, cp.publish_time ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $events = [];
    foreach ($rows as $r) {
        $color = get_client_color(intval($r['client_id']), $r['color_hex']);
        $theme = get_color_theme($color);
        $time = !empty($r['publish_time']) ? substr($r['publish_time'], 0, 5) : '10:00';
        $startDateTime = $r['publish_date'] . 'T' . (!empty($r['publish_time']) ? $r['publish_time'] : '10:00:00');

        $events[] = [
            'id' => strval($r['id']),
            'title' => $r['title'],
            'start' => $startDateTime,
            'backgroundColor' => $theme['bg'],
            'borderColor' => $theme['border'],
            'textColor' => $theme['text'],
            'allDay' => false,
            'extendedProps' => [
                'id' => $r['id'],
                'client_id' => $r['client_id'],
                'client_name' => $r['client_name'],
                'client_company' => $r['client_company'],
                'project_id' => $r['project_id'],
                'project_name' => $r['project_name'],
                'platform' => $r['platform'],
                'content_type' => $r['content_type'],
                'publish_date' => $r['publish_date'],
                'publish_time' => $time,
                'status' => $r['status'],
                'assignee_id' => $r['assignee_id'],
                'assignee_name' => $r['assignee_name'] ?: 'Belum ditugaskan',
                'assignee_position' => $r['assignee_position'] ?: '',
                'asset_url' => $r['asset_url'] ?: '',
                'color_hex' => $color,
                'theme' => $theme,
            ]
        ];
    }

    echo json_encode($events);
    exit;
}

// 2. GET EVENT DETAILS
if ($action === 'get_details') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $db->prepare("
        SELECT cp.*, 
               c.name as client_name, c.company as client_company,
               p.name as project_name,
               e.name as assignee_name, e.position as assignee_position
        FROM content_planner cp
        JOIN clients c ON cp.client_id = c.id
        LEFT JOIN projects p ON cp.project_id = p.id
        LEFT JOIN employees e ON cp.assignee_id = e.id
        WHERE cp.id = ? AND COALESCE(cp.is_deleted, 0) = 0
    ");
    $stmt->execute([$id]);
    $content = $stmt->fetch();

    if (!$content) {
        echo json_encode(['success' => false, 'message' => 'Jadwal konten tidak ditemukan atau telah dihapus.']);
        exit;
    }

    $content['color_hex'] = get_client_color(intval($content['client_id']), $content['color_hex']);
    $content['publish_time'] = !empty($content['publish_time']) ? substr($content['publish_time'], 0, 5) : '10:00';

    echo json_encode(['success' => true, 'content' => $content]);
    exit;
}

// 3. CREATE CONTENT PLAN
if ($action === 'create') {
    $clientId = intval($_POST['client_id'] ?? 0);
    $projectId = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $title = trim($_POST['title'] ?? '');
    $platform = trim($_POST['platform'] ?? 'Instagram');
    $contentType = trim($_POST['content_type'] ?? 'Reels / Video');
    $publishDate = trim($_POST['publish_date'] ?? date('Y-m-d'));
    $publishTime = trim($_POST['publish_time'] ?? '10:00');
    $status = trim($_POST['status'] ?? 'Draft');
    $assigneeId = !empty($_POST['assignee_id']) ? intval($_POST['assignee_id']) : null;
    $assetUrl = trim($_POST['asset_url'] ?? '');
    $colorHex = trim($_POST['color_hex'] ?? '#3B82F6');
    $notes = trim($_POST['notes'] ?? '');

    if ($clientId <= 0 || empty($title) || empty($publishDate)) {
        echo json_encode(['success' => false, 'message' => 'Klien, judul konten, dan tanggal tayang wajib diisi.']);
        exit;
    }

    // Append seconds to time if needed
    if (strlen($publishTime) === 5) {
        $publishTime .= ':00';
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO content_planner (
                client_id, project_id, title, platform, content_type,
                publish_date, publish_time, status, assignee_id, asset_url, color_hex, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $clientId, $projectId, $title, $platform, $contentType,
            $publishDate, $publishTime, $status, $assigneeId, $assetUrl, $colorHex, $notes
        ]);
        $newId = $db->lastInsertId();

        $clientCompany = $db->query("SELECT company FROM clients WHERE id = $clientId")->fetchColumn();
        log_activity('project', "Konten Baru Dijadwalkan: $title ($platform)", "Klien: $clientCompany | Tanggal: $publishDate");

        // Trigger Google Calendar Webhook (Non-blocking / Graceful timeout)
        $webhookStatus = sendGoogleCalendarWebhook($newId);

        echo json_encode([
            'success' => true,
            'message' => 'Jadwal konten berhasil ditambahkan!',
            'id' => $newId,
            'webhook' => [
                'configured' => defined('GCAL_WEBHOOK_URL') && !empty(GCAL_WEBHOOK_URL),
                'sent' => $webhookStatus['sent'] ?? false
            ]
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan konten: ' . $e->getMessage()]);
        exit;
    }
}

// 4. UPDATE CONTENT PLAN
if ($action === 'update') {
    $id = intval($_POST['id'] ?? 0);
    $clientId = intval($_POST['client_id'] ?? 0);
    $projectId = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $title = trim($_POST['title'] ?? '');
    $platform = trim($_POST['platform'] ?? 'Instagram');
    $contentType = trim($_POST['content_type'] ?? 'Reels / Video');
    $publishDate = trim($_POST['publish_date'] ?? date('Y-m-d'));
    $publishTime = trim($_POST['publish_time'] ?? '10:00');
    $status = trim($_POST['status'] ?? 'Draft');
    $assigneeId = !empty($_POST['assignee_id']) ? intval($_POST['assignee_id']) : null;
    $assetUrl = trim($_POST['asset_url'] ?? '');
    $colorHex = trim($_POST['color_hex'] ?? '#3B82F6');
    $notes = trim($_POST['notes'] ?? '');

    if ($id <= 0 || $clientId <= 0 || empty($title) || empty($publishDate)) {
        echo json_encode(['success' => false, 'message' => 'Data konten tidak valid atau belum lengkap.']);
        exit;
    }

    if (strlen($publishTime) === 5) {
        $publishTime .= ':00';
    }

    try {
        $stmt = $db->prepare("
            UPDATE content_planner SET
                client_id = ?,
                project_id = ?,
                title = ?,
                platform = ?,
                content_type = ?,
                publish_date = ?,
                publish_time = ?,
                status = ?,
                assignee_id = ?,
                asset_url = ?,
                color_hex = ?,
                notes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $clientId, $projectId, $title, $platform, $contentType,
            $publishDate, $publishTime, $status, $assigneeId, $assetUrl, $colorHex, $notes,
            $id
        ]);

        log_activity('project', "Jadwal Konten Diperbarui: $title", "Status: $status | Tanggal: $publishDate");

        // Trigger Google Calendar Webhook (Non-blocking / Graceful timeout)
        $webhookStatus = sendGoogleCalendarWebhook($id);

        echo json_encode([
            'success' => true,
            'message' => 'Jadwal konten berhasil diperbarui!',
            'id' => $id,
            'webhook' => [
                'configured' => defined('GCAL_WEBHOOK_URL') && !empty(GCAL_WEBHOOK_URL),
                'sent' => $webhookStatus['sent'] ?? false
            ]
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui konten: ' . $e->getMessage()]);
        exit;
    }
}

// 5. UPDATE DATE (Fast Drag-and-Drop / Resize Endpoint)
if ($action === 'update_date') {
    $id = intval($_POST['id'] ?? 0);
    $publishDate = trim($_POST['publish_date'] ?? '');
    $publishTime = trim($_POST['publish_time'] ?? '');

    if ($id <= 0 || empty($publishDate)) {
        echo json_encode(['success' => false, 'message' => 'Parameter tanggal tidak valid.']);
        exit;
    }

    try {
        if (!empty($publishTime)) {
            if (strlen($publishTime) === 5) $publishTime .= ':00';
            $stmt = $db->prepare("UPDATE content_planner SET publish_date = ?, publish_time = ? WHERE id = ?");
            $stmt->execute([$publishDate, $publishTime, $id]);
        } else {
            $stmt = $db->prepare("UPDATE content_planner SET publish_date = ? WHERE id = ?");
            $stmt->execute([$publishDate, $id]);
        }

        $title = $db->query("SELECT title FROM content_planner WHERE id = $id")->fetchColumn();
        log_activity('project', "Jadwal Konten Digeser (Drag & Drop): $title", "Tanggal Baru: $publishDate");

        // Trigger Google Calendar Webhook
        sendGoogleCalendarWebhook($id);

        echo json_encode([
            'success' => true,
            'message' => "Jadwal \"$title\" berhasil digeser ke " . format_date($publishDate) . "!"
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal menggeser tanggal: ' . $e->getMessage()]);
        exit;
    }
}

// 5b. TEST GCAL WEBHOOK DIAGNOSTIC ENDPOINT
if ($action === 'test_gcal_webhook') {
    $sampleData = [
        'content_id' => 999,
        'title' => 'Sample Test Content for Google Calendar Push',
        'client_id' => 1,
        'client_name' => 'Prima Pasir Mandiri',
        'client_company' => 'Prima Pasir Mandiri',
        'platform' => 'Instagram',
        'content_type' => 'Reels / Video',
        'publish_date' => date('Y-m-d'),
        'publish_time' => '14:00:00',
        'assignee_id' => 5,
        'assignee_name' => 'Muhammad Fadhli',
        'assignee_email' => 'mha.fadhli@gmail.com',
        'status' => 'Scheduled',
        'asset_url' => 'https://drive.google.com/sample',
        'notes' => 'Testing automated webhook sync'
    ];

    $result = sendGoogleCalendarWebhook($sampleData);
    echo json_encode([
        'success' => true,
        'diagnostic' => $result
    ]);
    exit;
}

// 6. UPDATE STATUS
if ($action === 'update_status') {
    $id = intval($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? 'Draft');

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID konten tidak valid.']);
        exit;
    }

    try {
        $stmt = $db->prepare("UPDATE content_planner SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);

        echo json_encode(['success' => true, 'message' => "Status konten berhasil diubah ke $status!"]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 7. DELETE (Soft delete)
if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID konten tidak valid.']);
        exit;
    }

    try {
        $title = $db->query("SELECT title FROM content_planner WHERE id = $id")->fetchColumn();
        $stmt = $db->prepare("UPDATE content_planner SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$id]);

        if ($title) {
            log_activity('project', "Jadwal Konten Dihapus: $title", "Dihapus dari kalender konten.");
        }

        echo json_encode(['success' => true, 'message' => 'Jadwal konten berhasil dihapus!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 8. GET METADATA (Clients with color badges, Projects, Employees)
if ($action === 'get_metadata') {
    $clients = $db->query("SELECT id, name, company FROM clients ORDER BY company ASC")->fetchAll();
    foreach ($clients as &$c) {
        $c['color_hex'] = get_client_color(intval($c['id']));
    }

    $projects = $db->query("SELECT id, client_id, name FROM projects ORDER BY name ASC")->fetchAll();
    $employees = $db->query("SELECT id, name, position, department FROM employees WHERE status = 'Active' ORDER BY name ASC")->fetchAll();

    echo json_encode([
        'success' => true,
        'clients' => $clients,
        'projects' => $projects,
        'employees' => $employees
    ]);
    exit;
}

// 9. EXPORT PDF REPORT (Filtered by Month or Custom Date Range, Client, Platform, Status)
if ($action === 'export_pdf') {
    $rangeType = $_GET['range_type'] ?? 'month';
    $monthParam = $_GET['month'] ?? date('Y-m');
    $startDateParam = $_GET['start_date'] ?? '';
    $endDateParam = $_GET['end_date'] ?? '';
    $clientId = $_GET['client_id'] ?? 'all';
    $platform = $_GET['platform'] ?? 'all';
    $status = $_GET['status'] ?? 'all';
    $autoDownload = ($_GET['auto_download'] ?? '0') === '1';

    if ($rangeType === 'month' && !empty($monthParam)) {
        $startDate = $monthParam . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        $periodLabel = "Bulan " . date('F Y', strtotime($startDate));
    } else {
        $startDate = !empty($startDateParam) ? $startDateParam : date('Y-m-01');
        $endDate = !empty($endDateParam) ? $endDateParam : date('Y-m-t');
        $periodLabel = date('d M Y', strtotime($startDate)) . " – " . date('d M Y', strtotime($endDate));
    }

    $where = [
        "COALESCE(cp.is_deleted, 0) = 0",
        "cp.publish_date >= " . $db->quote($startDate),
        "cp.publish_date <= " . $db->quote($endDate)
    ];

    $clientLabel = 'Semua Klien Agensi';
    if (!empty($clientId) && $clientId !== 'all') {
        $cid = intval($clientId);
        $where[] = "cp.client_id = $cid";
        $clientName = $db->query("SELECT company FROM clients WHERE id = $cid")->fetchColumn();
        if ($clientName) $clientLabel = $clientName;
    }

    $platformLabel = 'Semua Platform';
    if (!empty($platform) && $platform !== 'all') {
        $where[] = "cp.platform = " . $db->quote($platform);
        $platformLabel = $platform;
    }

    $statusLabel = 'Semua Status';
    if (!empty($status) && $status !== 'all') {
        $where[] = "cp.status = " . $db->quote($status);
        $statusLabel = $status;
    }

    $whereSql = implode(' AND ', $where);

    $sql = "
        SELECT cp.*, 
               c.name as client_name, c.company as client_company,
               p.name as project_name,
               e.name as assignee_name, e.position as assignee_position
        FROM content_planner cp
        JOIN clients c ON cp.client_id = c.id
        LEFT JOIN projects p ON cp.project_id = p.id
        LEFT JOIN employees e ON cp.assignee_id = e.id
        WHERE $whereSql
        ORDER BY cp.publish_date ASC, cp.publish_time ASC
    ";

    $contents = $db->query($sql)->fetchAll();

    // Summary counts
    $totalItems = count($contents);
    $publishedCount = 0;
    $scheduledCount = 0;
    $reviewCount = 0;
    $draftCount = 0;

    foreach ($contents as $c) {
        if ($c['status'] === 'Published') $publishedCount++;
        elseif ($c['status'] === 'Scheduled' || $c['status'] === 'Approved') $scheduledCount++;
        elseif ($c['status'] === 'Review') $reviewCount++;
        else $draftCount++;
    }

    $filename = "Laporan_Jadwal_Konten_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $clientLabel . '_' . $periodLabel) . ".pdf";

    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
      <meta charset="UTF-8">
      <title>Laporan Jadwal Konten - <?= htmlspecialchars($clientLabel) ?></title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
      <script src="../assets/js/html2pdf.bundle.min.js"></script>
      <style>
        * { box-sizing: border-box; }
        body {
          margin: 0;
          padding: 24px;
          background: #F8FAFC;
          font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, Arial, sans-serif;
          color: #101828;
        }

        .pdf-actions-bar {
          max-width: 1080px;
          margin: 0 auto 20px;
          display: flex;
          justify-content: space-between;
          align-items: center;
          gap: 12px;
        }
        .btn-action-back {
          background: #FFFFFF;
          color: #344054;
          border: 1px solid #D0D5DD;
          font-weight: 600;
          font-size: 13px;
          height: 36px;
          padding: 0 14px;
          border-radius: 8px;
          display: inline-flex;
          align-items: center;
          gap: 8px;
          text-decoration: none;
          box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
        }
        .btn-action-back:hover { background: #F9FAFB; color: #101828; }
        .btn-action-primary {
          background: #101828;
          color: #FFFFFF;
          border: 1px solid #101828;
          font-weight: 600;
          font-size: 13px;
          height: 36px;
          padding: 0 16px;
          border-radius: 8px;
          display: inline-flex;
          align-items: center;
          gap: 6px;
          cursor: pointer;
          box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
        }
        .btn-action-primary:hover { background: #1E293B; }
        .btn-action-secondary {
          background: #FFFFFF;
          color: #344054;
          border: 1px solid #D0D5DD;
          font-weight: 600;
          font-size: 13px;
          height: 36px;
          padding: 0 14px;
          border-radius: 8px;
          display: inline-flex;
          align-items: center;
          gap: 6px;
          cursor: pointer;
        }

        /* Printable Document Canvas (A4 Landscape Formatted) */
        .report-canvas {
          background: #FFFFFF;
          max-width: 1080px;
          margin: 0 auto;
          padding: 36px 44px;
          border: 1px solid #EAECF0;
          border-radius: 12px;
          box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        }

        /* Header */
        .report-header {
          display: flex;
          justify-content: space-between;
          align-items: flex-start;
          border-bottom: 2px solid #101828;
          padding-bottom: 18px;
          margin-bottom: 20px;
        }
        .brand-block h1 {
          font-size: 22px;
          font-weight: 900;
          letter-spacing: -0.5px;
          margin: 0 0 4px 0;
          color: #101828;
        }
        .brand-block p {
          font-size: 11.5px;
          color: #475467;
          margin: 0;
          line-height: 1.4;
        }
        .doc-title-block {
          text-align: right;
        }
        .doc-title-badge {
          display: inline-block;
          background: #101828;
          color: #FFFFFF;
          font-size: 10px;
          font-weight: 800;
          letter-spacing: 1px;
          text-transform: uppercase;
          padding: 3px 8px;
          border-radius: 4px;
          margin-bottom: 4px;
        }
        .doc-main-title {
          font-size: 18px;
          font-weight: 800;
          color: #101828;
          margin: 0 0 4px 0;
        }
        .doc-meta {
          font-size: 11.5px;
          color: #475467;
        }

        /* Metadata & KPI Cards */
        .kpi-row {
          display: grid;
          grid-template-columns: repeat(4, 1fr);
          gap: 12px;
          margin-bottom: 24px;
        }
        .kpi-card {
          background: #F8FAFC;
          border: 1px solid #EAECF0;
          border-radius: 8px;
          padding: 12px 14px;
        }
        .kpi-card-label {
          font-size: 11px;
          font-weight: 600;
          color: #667085;
          text-transform: uppercase;
          letter-spacing: 0.3px;
        }
        .kpi-card-val {
          font-size: 20px;
          font-weight: 800;
          color: #101828;
          margin-top: 2px;
        }

        /* Editorial Table */
        .report-table {
          width: 100%;
          border-collapse: collapse;
          font-size: 11px;
          margin-bottom: 28px;
        }
        .report-table th {
          background: #101828;
          color: #FFFFFF;
          font-weight: 700;
          text-transform: uppercase;
          font-size: 9.5px;
          letter-spacing: 0.5px;
          padding: 10px 8px;
          text-align: left;
        }
        .report-table td {
          padding: 10px 8px;
          border-bottom: 1px solid #EAECF0;
          vertical-align: top;
          color: #344054;
        }
        .report-table tr:nth-child(even) td {
          background: #FAFAFA;
        }

        /* Status & Platform Badges */
        .platform-badge {
          display: inline-block;
          font-size: 9.5px;
          font-weight: 700;
          padding: 2px 6px;
          border-radius: 4px;
          background: #EFF8FF;
          color: #175CD3;
          border: 1px solid #B2DDFF;
        }
        .status-pill {
          display: inline-block;
          font-size: 9.5px;
          font-weight: 700;
          padding: 2px 8px;
          border-radius: 9999px;
          text-transform: uppercase;
        }
        .status-Published { background: #ECFDF5; color: #027A48; border: 1px solid #A6F4C7; }
        .status-Scheduled, .status-Approved { background: #EFF8FF; color: #175CD3; border: 1px solid #B2DDFF; }
        .status-Review { background: #FFFBEB; color: #B45309; border: 1px solid #FDE68A; }
        .status-Draft { background: #F8FAFC; color: #475467; border: 1px solid #EAECF0; }

        /* Signatures */
        .signature-row {
          display: flex;
          justify-content: space-between;
          margin-top: 36px;
          padding-top: 14px;
          font-size: 11px;
          page-break-inside: avoid;
        }
        .sig-box {
          width: 200px;
          text-align: center;
        }
        .sig-title { color: #667085; margin-bottom: 45px; }
        .sig-line { font-weight: 700; border-top: 1px solid #101828; padding-top: 4px; color: #101828; }

        /* Print Media */
        @media print {
          @page { size: A4 landscape; margin: 8mm; }
          body { background: #FFFFFF; padding: 0; }
          .pdf-actions-bar { display: none !important; }
          .report-canvas {
            border: none;
            box-shadow: none;
            padding: 0;
            max-width: 100%;
          }
        }
      </style>
    </head>
    <body>

      <div class="pdf-actions-bar">
        <a href="<?= url('content-calendar') ?>" class="btn-action-back">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
          <span>Kembali ke Kalender</span>
        </a>

        <div style="display: flex; gap: 8px;">
          <button type="button" class="btn-action-secondary" onclick="window.print()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            <span>Cetak</span>
          </button>
          <button type="button" class="btn-action-primary" id="btn-download-pdf-doc" onclick="downloadReportPdf()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            <span>Download PDF Laporan</span>
          </button>
        </div>
      </div>

      <div class="report-canvas">
        <!-- Header -->
        <div class="report-header">
          <div class="brand-block">
            <h1><?= AGENCY_NAME ?></h1>
            <p><?= AGENCY_TAGLINE ?> &bull; Creative & Digital Growth Agency<br>
            <?= AGENCY_ADDRESS ?><br>
            WhatsApp/Telp: <?= AGENCY_PHONE ?> &bull; Email: <?= AGENCY_EMAIL ?></p>
          </div>
          <div class="doc-title-block">
            <span class="doc-title-badge">OFFICIAL EDITORIAL REPORT</span>
            <h2 class="doc-main-title">LAPORAN JADWAL KONTEN</h2>
            <div class="doc-meta">
              <strong>Periode:</strong> <?= htmlspecialchars($periodLabel) ?><br>
              <strong>Klien:</strong> <?= htmlspecialchars($clientLabel) ?><br>
              <strong>Platform:</strong> <?= htmlspecialchars($platformLabel) ?>
            </div>
          </div>
        </div>

        <!-- KPI Row -->
        <div class="kpi-row">
          <div class="kpi-card">
            <div class="kpi-card-label">Total Jadwal Konten</div>
            <div class="kpi-card-val"><?= $totalItems ?> Post</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-card-label">Sudah Tayang (Published)</div>
            <div class="kpi-card-val" style="color: #027A48;"><?= $publishedCount ?> Post</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-card-label">Siap / Terjadwal (Scheduled)</div>
            <div class="kpi-card-val" style="color: #175CD3;"><?= $scheduledCount ?> Post</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-card-label">Review / Draft</div>
            <div class="kpi-card-val" style="color: #B45309;"><?= $reviewCount + $draftCount ?> Post</div>
          </div>
        </div>

        <!-- Editorial Schedule Table -->
        <table class="report-table">
          <thead>
            <tr>
              <th style="width: 30px; text-align: center;">No.</th>
              <th style="width: 100px;">Tanggal & Jam</th>
              <th style="width: 140px;">Klien & Proyek</th>
              <th style="width: 110px;">Platform & Tipe</th>
              <th>Judul / Topik & Brief Konten</th>
              <th style="width: 120px;">PIC / Assignee</th>
              <th style="width: 95px; text-align: center;">Status</th>
              <th style="width: 100px;">Aset Cloud</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($contents)): ?>
              <tr>
                <td colspan="8" style="text-align: center; padding: 30px; color: #98A2B3; font-style: italic;">
                  Tidak ada jadwal konten yang sesuai dengan filter periode ini.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($contents as $idx => $item): ?>
                <tr>
                  <td style="text-align: center; font-weight: 700; color: #667085;"><?= $idx + 1 ?></td>
                  <td>
                    <div style="font-weight: 700; color: #101828;"><?= date('d/m/Y', strtotime($item['publish_date'])) ?></div>
                    <div style="font-size: 10px; color: #667085;">Jam: <?= !empty($item['publish_time']) ? substr($item['publish_time'], 0, 5) : '10:00' ?> WIB</div>
                  </td>
                  <td>
                    <div style="font-weight: 700; color: #101828;"><?= htmlspecialchars($item['client_company']) ?></div>
                    <div style="font-size: 10px; color: #667085;"><?= htmlspecialchars($item['project_name'] ?: 'Layanan Konten') ?></div>
                  </td>
                  <td>
                    <span class="platform-badge"><?= htmlspecialchars($item['platform']) ?></span>
                    <div style="font-size: 10px; color: #475467; margin-top: 3px; font-weight: 600;"><?= htmlspecialchars($item['content_type']) ?></div>
                  </td>
                  <td>
                    <div style="font-weight: 700; color: #101828; font-size: 11.5px;"><?= htmlspecialchars($item['title']) ?></div>
                    <?php if (!empty($item['notes'])): ?>
                      <div style="font-size: 10px; color: #475467; margin-top: 3px; line-height: 1.3;"><?= nl2br(htmlspecialchars($item['notes'])) ?></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div style="font-weight: 600; color: #101828;"><?= htmlspecialchars($item['assignee_name'] ?: 'Belum ditugaskan') ?></div>
                    <div style="font-size: 9.5px; color: #667085;"><?= htmlspecialchars($item['assignee_position'] ?: '-') ?></div>
                  </td>
                  <td style="text-align: center;">
                    <span class="status-pill status-<?= htmlspecialchars($item['status']) ?>">
                      <?= htmlspecialchars($item['status']) ?>
                    </span>
                  </td>
                  <td>
                    <?php if (!empty($item['asset_url'])): ?>
                      <a href="<?= htmlspecialchars($item['asset_url']) ?>" target="_blank" style="color: #2563EB; font-weight: 600; text-decoration: underline; font-size: 10px; display: inline-flex; align-items: center; gap: 3px;">
                        <span>Link Aset &rarr;</span>
                      </a>
                    <?php else: ?>
                      <span style="color: #98A2B3; font-size: 10px;">-</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

        <!-- Signatures -->
        <div class="signature-row">
          <div class="sig-box">
            <div class="sig-title">Disiapkan oleh (Creative Manager),</div>
            <div style="height: 48px; display: flex; align-items: center; justify-content: center; margin-bottom: 2px;">
              <img src="assets/Jpg/ttd-fadhli.png" alt="TTD Muhammad Fadhli" style="height: 44px; max-width: 130px; object-fit: contain;">
            </div>
            <div class="sig-line">Muhammad Fadhli</div>
          </div>
          <div class="sig-box">
            <div class="sig-title">Mengetahui (Marketing Manager),</div>
            <div style="height: 48px; display: flex; align-items: center; justify-content: center; margin-bottom: 2px;">
              <img src="assets/Jpg/ttd-ilham.png" alt="TTD Ilham Lanang" style="height: 44px; max-width: 130px; object-fit: contain;">
            </div>
            <div class="sig-line">Ilham Lanang</div>
          </div>
          <div class="sig-box">
            <div class="sig-title" style="margin-bottom: 46px;">Disetujui oleh Klien / PIC,</div>
            <div class="sig-line"><?= htmlspecialchars($contents[0]['client_name'] ?? 'Bapak / Ibu PIC Klien') ?></div>
          </div>
        </div>

      </div>

      <script>
      async function downloadReportPdf() {
        const element = document.querySelector('.report-canvas');
        const filename = <?= json_encode($filename) ?>;
        const btn = document.getElementById('btn-download-pdf-doc');
        const origText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<span>Menyiapkan PDF...</span>';

        const opt = {
          margin: [8, 8, 8, 8],
          filename: filename,
          image: { type: 'jpeg', quality: 0.98 },
          html2canvas: { scale: 2, useCORS: true, letterRendering: true, scrollY: 0 },
          jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' },
          pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
        };

        try {
          if (typeof html2pdf !== 'undefined') {
            await html2pdf().set(opt).from(element).save();
          } else {
            window.print();
          }
        } catch (err) {
          console.error('PDF Error:', err);
          window.print();
        } finally {
          btn.disabled = false;
          btn.innerHTML = origText;
        }
      }

      <?php if ($autoDownload): ?>
      window.addEventListener('DOMContentLoaded', () => {
        setTimeout(downloadReportPdf, 400);
      });
      <?php endif; ?>
      </script>
    </body>
    </html>
    <?php
    exit;
}

echo json_encode(['success' => false, 'message' => 'Aksi content calendar tidak valid.']);


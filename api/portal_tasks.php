<?php
/**
 * Kalamedia Agency - Client Portal, Task Management & Feedback RESTful API
 * Endpoints:
 * - GET  ?action=list_tasks
 * - GET  ?action=get_task_details&id=...
 * - GET  ?action=team_members
 * - GET  ?action=projects_dropdown
 * - POST ?action=assign_task
 * - POST ?action=update_progress
 * - POST ?action=submit_feedback
 * - POST ?action=delete_task
 * - POST ?action=mark_feedbacks_read
 */

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

// Ensure user is authenticated
require_auth();

$user = current_user();
$sessionRole = strtolower($_SESSION['role'] ?? '');
$userRole = strtolower($user['role'] ?? '');
$role = $sessionRole ?: ($userRole ?: current_user_role());
$userId = (int)$user['id'];
$isOwnerOrAdmin = in_array($role, ['owner', 'admin']);
$isWorkerUser = ($role === 'karyawan' || $role === 'freelancer');

// Release session lock immediately to prevent concurrent request blocking
release_session();
$clientId = current_user_client_id();

$db = Database::getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function parse_task_amount($val): float {
    if (is_numeric($val)) return floatval($val);
    $clean = preg_replace('/[^0-9,.]/', '', strval($val));
    if (strpos($clean, '.') !== false && strpos($clean, ',') !== false) {
        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);
    } elseif (strpos($clean, '.') !== false && substr_count($clean, '.') > 1) {
        $clean = str_replace('.', '', $clean);
    } elseif (strpos($clean, '.') !== false) {
        $parts = explode('.', $clean);
        if (strlen(end($parts)) === 3) {
            $clean = str_replace('.', '', $clean);
        }
    } elseif (strpos($clean, ',') !== false) {
        $clean = str_replace(',', '.', $clean);
    }
    return floatval($clean);
}

function decorate_task_ads_metrics(array $t): array {
    $budget = floatval($t['assigned_ads_budget'] ?? 0);
    $spent = floatval($t['ads_spent_amount'] ?? 0);
    $remaining = max(0, floatval($t['remaining_ads_balance'] ?? ($budget - $spent)));
    $spentPercent = ($budget > 0) ? min(100, round(($spent / $budget) * 100, 1)) : 0;

    $healthStatus = 'none';
    $healthLabel = 'Tidak Ada Saldo Ads';
    $healthBadgeClass = 'health-none';

    if ($budget > 0) {
        if ($remaining <= 0) {
            $healthStatus = 'empty';
            $healthLabel = 'SALDO HABIS (Rp 0)';
            $healthBadgeClass = 'health-empty';
        } elseif ($remaining <= 0.20 * $budget) {
            $healthStatus = 'warning';
            $healthLabel = 'Saldo Hampir Habis (Segera Top-Up)';
            $healthBadgeClass = 'health-warning';
        } else {
            $healthStatus = 'safe';
            $healthLabel = 'Saldo Masih Tersedia (Aman)';
            $healthBadgeClass = 'health-safe';
        }
    }

    $t['assigned_ads_budget'] = $budget;
    $t['ads_spent_amount'] = $spent;
    $t['remaining_ads_balance'] = $remaining;
    $t['assigned_ads_budget_formatted'] = format_rupiah($budget);
    $t['ads_spent_amount_formatted'] = format_rupiah($spent);
    $t['remaining_ads_balance_formatted'] = format_rupiah($remaining);
    $t['is_depleted'] = ($healthStatus === 'empty');
    $t['is_warning'] = ($healthStatus === 'warning');
    $t['is_safe'] = ($healthStatus === 'safe');
    $t['is_ads_task'] = ($budget > 0 || ($t['category'] ?? '') === 'ads');
    $t['spent_percent'] = $spentPercent;
    $t['balance_health_status'] = $healthStatus;
    $t['balance_health_label'] = $healthLabel;
    $t['balance_health_badge_class'] = $healthBadgeClass;
    $t['campaign_run_status'] = !empty($t['campaign_run_status']) ? $t['campaign_run_status'] : 'Iklan Jalan (Running)';
    $t['ads_platform'] = !empty($t['ads_platform']) ? $t['ads_platform'] : 'Meta Ads';
    $t['transfer_proof_url'] = !empty($t['transfer_proof_file']) ? url($t['transfer_proof_file']) : null;
    $t['ads_spent_proof_url'] = !empty($t['ads_spent_proof']) ? url($t['ads_spent_proof']) : null;

    return $t;
}

/**
 * Non-blocking PHP cURL Webhook to notify worker/freelancer via Make.com / WhatsApp Gateway
 */
function triggerWorkerWebhook(array $payload): bool {
    if (!defined('GCAL_WEBHOOK_URL') || empty(GCAL_WEBHOOK_URL)) {
        return false;
    }

    $url = GCAL_WEBHOOK_URL;
    $postData = json_encode($payload);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($postData)
    ]);
    // Non-blocking timeout (1 second max so user response is immediate)
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1200);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 800);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    return empty($error);
}

// -------------------------------------------------------------
// 1. GET: List Tasks (Strictly scoped by Role)
// -------------------------------------------------------------
if ($action === 'list_tasks') {
    try {
        if ($role === 'client') {
            // STRICT CLIENT PRIVACY: Filter strictly by client_id, hide internal agency costs and margins
            if (!$clientId) {
                // If client_id not in session, lookup client by user email
                $stmtC = $db->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
                $stmtC->execute([$user['email']]);
                $clientId = (int)$stmtC->fetchColumn();
            }

            if (!$clientId) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Akun klien ini belum dikaitkan dengan profil klien Kalamedia.'
                ]);
                exit;
            }

            // Fetch Client Project Info
            $stmtProj = $db->prepare("
                SELECT p.id, p.name as project_name, p.status as project_status,
                       c.company as client_company, c.name as client_pic, c.package_name, c.logo as client_logo
                FROM clients c
                LEFT JOIN projects p ON p.client_id = c.id AND p.is_deleted = 0
                WHERE c.id = ?
                ORDER BY p.id DESC LIMIT 1
            ");
            $stmtProj->execute([$clientId]);
            $clientInfo = $stmtProj->fetch() ?: [
                'client_company' => 'Klien Kalamedia',
                'client_pic' => $user['name'],
                'package_name' => 'Social Media & Performance Ads',
                'project_name' => 'Project Kalamedia Creative'
            ];

            // Fetch Tasks
            $stmt = $db->prepare("
                SELECT t.id, t.project_id, t.client_id, t.title, t.description, t.category,
                       t.worker_type, t.priority, t.status, t.deliverable_link, t.start_date, t.due_date,
                       t.assigned_ads_budget, t.ads_spent_amount, t.remaining_ads_balance,
                       t.campaign_run_status, t.transfer_proof_file, t.ads_platform, t.ads_spent_notes, t.ads_spent_proof,
                       t.created_at, t.updated_at,
                       p.name as project_name,
                       u.name as worker_name, u.role as worker_role, u.employment_type as worker_emp_type,
                       u.position as worker_position, u.jobdesk as worker_jobdesk,
                       u.avatar as worker_avatar, u.phone_wa as worker_phone,
                       (SELECT COUNT(*) FROM task_feedbacks WHERE task_id = t.id) as feedbacks_count,
                       (SELECT message FROM task_feedbacks WHERE task_id = t.id ORDER BY id DESC LIMIT 1) as latest_feedback
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.id
                LEFT JOIN users u ON t.assigned_to_user_id = u.id
                WHERE t.client_id = ? AND t.is_deleted = 0
                ORDER BY 
                    CASE 
                        WHEN t.status = 'client_review' THEN 1
                        WHEN t.status = 'revision' THEN 2
                        WHEN t.status = 'in_progress' THEN 3
                        WHEN t.status = 'internal_review' THEN 4
                        WHEN t.status = 'backlog' THEN 5
                        WHEN t.status = 'approved' THEN 6
                        ELSE 7
                    END,
                    t.due_date ASC, t.id DESC
            ");
            $stmt->execute([$clientId]);
            $tasks = array_map('decorate_task_ads_metrics', $stmt->fetchAll());

            // Calculate Project Statistics
            $totalTasks = count($tasks);
            $approvedCount = 0;
            $inProgressCount = 0;
            $clientReviewCount = 0;
            $revisionCount = 0;
            $backlogCount = 0;

            foreach ($tasks as $t) {
                if ($t['status'] === 'approved') $approvedCount++;
                elseif ($t['status'] === 'client_review') $clientReviewCount++;
                elseif ($t['status'] === 'revision') $revisionCount++;
                elseif (in_array($t['status'], ['in_progress', 'internal_review'])) $inProgressCount++;
                elseif ($t['status'] === 'backlog') $backlogCount++;
            }

            $overallProgress = ($totalTasks > 0) ? (int)round(($approvedCount / $totalTasks) * 100) : 0;

            echo json_encode([
                'success' => true,
                'role' => 'client',
                'client_info' => $clientInfo,
                'metrics' => [
                    'total_tasks' => $totalTasks,
                    'approved_count' => $approvedCount,
                    'in_progress_count' => $inProgressCount,
                    'client_review_count' => $clientReviewCount,
                    'revision_count' => $revisionCount,
                    'backlog_count' => $backlogCount,
                    'overall_progress' => $overallProgress
                ],
                'tasks' => $tasks
            ]);
            exit;

        } elseif ($role === 'karyawan' || $role === 'freelancer') {
            // WORKER WORKSPACE: Tasks assigned to logged in staff/freelancer
            $stmt = $db->prepare("
                SELECT t.id, t.project_id, t.client_id, t.title, t.description, t.category,
                       t.worker_type, t.priority, t.status, t.deliverable_link, t.start_date, t.due_date,
                       t.assigned_ads_budget, t.ads_spent_amount, t.remaining_ads_balance,
                       t.campaign_run_status, t.transfer_proof_file, t.ads_platform, t.ads_spent_notes, t.ads_spent_proof,
                       t.created_at, t.updated_at,
                       p.name as project_name,
                       c.company as client_company, c.name as client_pic,
                       u_by.name as assigned_by_name,
                       (SELECT COUNT(*) FROM task_feedbacks WHERE task_id = t.id) as feedbacks_count,
                       (SELECT COUNT(*) FROM task_feedbacks WHERE task_id = t.id AND is_read_by_worker = 0 AND user_id != ?) as unread_feedbacks_count,
                       (SELECT message FROM task_feedbacks WHERE task_id = t.id ORDER BY id DESC LIMIT 1) as latest_feedback
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.id
                LEFT JOIN clients c ON t.client_id = c.id
                LEFT JOIN users u_by ON t.assigned_by_user_id = u_by.id
                WHERE t.assigned_to_user_id = ? AND t.is_deleted = 0
                ORDER BY 
                    CASE 
                        WHEN t.status = 'revision' THEN 1
                        WHEN t.status = 'in_progress' THEN 2
                        WHEN t.status = 'internal_review' THEN 3
                        WHEN t.status = 'client_review' THEN 4
                        WHEN t.status = 'backlog' THEN 5
                        WHEN t.status = 'approved' THEN 6
                        ELSE 7
                    END,
                    t.due_date ASC, t.id DESC
            ");
            $stmt->execute([$userId, $userId]);
            $tasks = array_map('decorate_task_ads_metrics', $stmt->fetchAll());

            $total = count($tasks);
            $inProgress = 0;
            $revision = 0;
            $clientReview = 0;
            $approved = 0;
            $totalUnreadFeedbacks = 0;

            foreach ($tasks as $t) {
                if ($t['status'] === 'in_progress') $inProgress++;
                if ($t['status'] === 'revision') $revision++;
                if ($t['status'] === 'client_review') $clientReview++;
                if ($t['status'] === 'approved') $approved++;
                $totalUnreadFeedbacks += (int)$t['unread_feedbacks_count'];
            }

            // Calculate worker's dynamic ads metrics (Saldo & Sisa Saldo only)
            $stmtWorkerAds = $db->prepare("
                SELECT 
                    COALESCE(SUM(COALESCE(NULLIF(transfer_amount_owner, 0), topup_amount, 0)), 0) as total_transfer,
                    COALESCE(SUM(COALESCE(NULLIF(ad_spend_amount, 0), spend_installed, 0)), 0) as total_spent
                FROM ads_topup_transactions
                WHERE COALESCE(is_deleted, 0) = 0
                AND (
                    assigned_user_id = ? OR uploaded_by_user_id = ?
                    OR project_id IN (SELECT DISTINCT project_id FROM tasks WHERE assigned_to_user_id = ? AND COALESCE(is_deleted,0)=0)
                    OR client_id IN (SELECT client_id FROM employee_clients WHERE user_id = ?)
                )
            ");
            $stmtWorkerAds->execute([$userId, $userId, $userId, $userId]);
            $wAds = $stmtWorkerAds->fetch(PDO::FETCH_ASSOC);
            $totalDitransfer = floatval($wAds['total_transfer'] ?? 0);
            $totalDibelanjakan = floatval($wAds['total_spent'] ?? 0);
            $sisaSaldo = $totalDitransfer - $totalDibelanjakan;
            $statusIklan = ($sisaSaldo > 0) ? 'Iklan Jalan' : ($totalDitransfer > 0 ? 'Saldo Habis' : 'Belum Ada Saldo');

            echo json_encode([
                'success' => true,
                'role' => $role,
                'metrics' => [
                    'total_tasks' => $total,
                    'in_progress_count' => $inProgress,
                    'revision_count' => $revision,
                    'client_review_count' => $clientReview,
                    'approved_count' => $approved,
                    'unread_feedbacks_count' => $totalUnreadFeedbacks
                ],
                'ads_summary' => [
                    'total_ditransfer_owner' => $totalDitransfer,
                    'total_dibelanjakan_ads' => $totalDibelanjakan,
                    'sisa_saldo'             => $sisaSaldo,
                    'status_iklan'           => $statusIklan
                ],
                'tasks' => $tasks
            ]);
            exit;

        } else {
            // OWNER & ADMIN: Full Management View with Flexible Filters
            $where = ["t.is_deleted = 0"];
            $params = [];

            if (!empty($_GET['project_id'])) {
                $where[] = "t.project_id = ?";
                $params[] = (int)$_GET['project_id'];
            }
            if (!empty($_GET['client_id'])) {
                $where[] = "t.client_id = ?";
                $params[] = (int)$_GET['client_id'];
            }
            if (!empty($_GET['worker_type']) && in_array($_GET['worker_type'], ['karyawan', 'freelancer'])) {
                $where[] = "t.worker_type = ?";
                $params[] = $_GET['worker_type'];
            }
            if (!empty($_GET['assigned_to_user_id'])) {
                $where[] = "t.assigned_to_user_id = ?";
                $params[] = (int)$_GET['assigned_to_user_id'];
            }
            if (!empty($_GET['status'])) {
                $where[] = "t.status = ?";
                $params[] = $_GET['status'];
            }
            if (!empty($_GET['priority'])) {
                $where[] = "t.priority = ?";
                $params[] = $_GET['priority'];
            }
            if (!empty($_GET['service_type']) && $_GET['service_type'] !== 'all') {
                $where[] = "(c.service_type LIKE ? OR t.category LIKE ?)";
                $params[] = '%' . $_GET['service_type'] . '%';
                $params[] = '%' . $_GET['service_type'] . '%';
            }

            $sqlWhere = implode(" AND ", $where);

            $stmt = $db->prepare("
                SELECT t.id, t.project_id, t.client_id, t.title, t.description, t.category,
                       t.worker_type, t.priority, t.status, t.deliverable_link, t.start_date, t.due_date,
                       t.assigned_ads_budget, t.ads_spent_amount, t.remaining_ads_balance,
                       t.campaign_run_status, t.transfer_proof_file, t.ads_platform, t.ads_spent_notes, t.ads_spent_proof,
                       t.created_at, t.updated_at,
                       p.name as project_name,
                       c.company as client_company, c.name as client_pic, c.service_type as client_service_type,
                       u.name as worker_name, u.role as worker_role, u.employment_type as worker_emp_type, u.position as worker_position, u.jobdesk as worker_jobdesk,
                       u.service_specialty as worker_specialty,
                       u.avatar as worker_avatar, u.phone_wa as worker_phone,
                       u_by.name as assigned_by_name,
                       (SELECT COUNT(*) FROM task_feedbacks WHERE task_id = t.id) as feedbacks_count,
                       (SELECT COUNT(*) FROM task_feedbacks WHERE task_id = t.id AND is_read_by_worker = 0) as unread_feedbacks_count,
                       (SELECT message FROM task_feedbacks WHERE task_id = t.id ORDER BY id DESC LIMIT 1) as latest_feedback
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.id
                LEFT JOIN clients c ON t.client_id = c.id
                LEFT JOIN users u ON t.assigned_to_user_id = u.id
                LEFT JOIN users u_by ON t.assigned_by_user_id = u_by.id
                WHERE {$sqlWhere}
                ORDER BY 
                    CASE 
                        WHEN t.status = 'client_review' THEN 1
                        WHEN t.status = 'revision' THEN 2
                        WHEN t.status = 'in_progress' THEN 3
                        WHEN t.status = 'internal_review' THEN 4
                        WHEN t.status = 'backlog' THEN 5
                        WHEN t.status = 'approved' THEN 6
                        ELSE 7
                    END,
                    t.due_date ASC, t.id DESC
            ");
            $stmt->execute($params);
            $tasks = array_map('decorate_task_ads_metrics', $stmt->fetchAll());

            // Overall Stats across all tasks (scoped to client_id if passed)
            $clientStatsFilter = !empty($_GET['client_id']) ? " AND client_id = " . intval($_GET['client_id']) : "";
            $stmtAllStats = $db->query("
                SELECT 
                    COUNT(*) as total_tasks,
                    SUM(CASE WHEN status = 'backlog' THEN 1 ELSE 0 END) as backlog_count,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
                    SUM(CASE WHEN status = 'internal_review' THEN 1 ELSE 0 END) as internal_review_count,
                    SUM(CASE WHEN status = 'client_review' THEN 1 ELSE 0 END) as client_review_count,
                    SUM(CASE WHEN status = 'revision' THEN 1 ELSE 0 END) as revision_count,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                    SUM(CASE WHEN worker_type = 'karyawan' THEN 1 ELSE 0 END) as karyawan_count,
                    SUM(CASE WHEN worker_type = 'freelancer' THEN 1 ELSE 0 END) as freelancer_count
                FROM tasks WHERE is_deleted = 0 {$clientStatsFilter}
            ");
            $stats = $stmtAllStats->fetch();

            echo json_encode([
                'success' => true,
                'role' => $role,
                'metrics' => $stats,
                'tasks' => $tasks
            ]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal mengambil data tugas: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 2. GET: Task Details with Discussion & Feedback Thread
// -------------------------------------------------------------
if ($action === 'get_task_details') {
    $taskId = (int)($_GET['id'] ?? 0);
    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Task ID tidak valid']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            SELECT t.*,
                   p.name as project_name,
                   c.company as client_company, c.name as client_pic, c.package_name,
                   u.name as worker_name, u.role as worker_role, u.employment_type as worker_emp_type,
                   u.avatar as worker_avatar, u.phone_wa as worker_phone,
                   u_by.name as assigned_by_name
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN clients c ON t.client_id = c.id
            LEFT JOIN users u ON t.assigned_to_user_id = u.id
            LEFT JOIN users u_by ON t.assigned_by_user_id = u_by.id
            WHERE t.id = ? AND t.is_deleted = 0
        ");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();

        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Tugas tidak ditemukan']);
            exit;
        }

        // Security / Scope checks
        if ($role === 'client') {
            if ((int)$task['client_id'] !== $clientId) {
                echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki akses ke tugas ini']);
                exit;
            }
        } elseif ($role === 'karyawan' || $role === 'freelancer') {
            if ((int)$task['assigned_to_user_id'] !== $userId) {
                echo json_encode(['success' => false, 'message' => 'Tugas ini bukan bagian dari tanggung jawab Anda']);
                exit;
            }
            // Mark feedbacks as read for this worker
            $db->prepare("UPDATE task_feedbacks SET is_read_by_worker = 1 WHERE task_id = ? AND user_id != ?")
               ->execute([$taskId, $userId]);
        }

        // Fetch Feedback thread
        $stmtFb = $db->prepare("
            SELECT tf.*,
                   u.name as sender_name, u.role as sender_role, u.employment_type as sender_emp_type,
                   u.avatar as sender_avatar
            FROM task_feedbacks tf
            LEFT JOIN users u ON tf.user_id = u.id
            WHERE tf.task_id = ?
            ORDER BY tf.created_at ASC
        ");
        $stmtFb->execute([$taskId]);
        $feedbacks = $stmtFb->fetchAll();

        echo json_encode([
            'success' => true,
            'task' => decorate_task_ads_metrics($task),
            'feedbacks' => $feedbacks
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 3. POST: Assign Task (Owner / Admin)
// -------------------------------------------------------------
if ($action === 'assign_task') {
    if (!is_admin()) {
        echo json_encode(['success' => false, 'message' => 'Hanya Owner dan Admin yang dapat membagi tugas']);
        exit;
    }

    $projectId = (int)($_POST['project_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? 'Kelola Sosmed');
    $assignedTo = (int)($_POST['assigned_to_user_id'] ?? 0);
    $priority = trim($_POST['priority'] ?? 'medium');
    $startDate = trim($_POST['start_date'] ?? '');
    $dueDate = trim($_POST['due_date'] ?? '');
    $deliverableLink = trim($_POST['deliverable_link'] ?? '');
    $assignedAdsBudget = parse_task_amount($_POST['assigned_ads_budget'] ?? 0);
    $adsPlatform = trim($_POST['ads_platform'] ?? '');
    $campaignRunStatus = trim($_POST['campaign_run_status'] ?? 'running');

    if (empty($title) || !$projectId || !$assignedTo) {
        echo json_encode(['success' => false, 'message' => 'Judul tugas, proyek, dan PIC wajib diisi']);
        exit;
    }

    if (empty($startDate) || empty($dueDate)) {
        echo json_encode(['success' => false, 'message' => 'Mulai pengerjaan (start_date) dan tenggat selesai (due_date) wajib diisi']);
        exit;
    }

    if ($dueDate < $startDate) {
        echo json_encode(['success' => false, 'message' => 'Tenggat selesai (due_date) tidak boleh lebih awal dari tanggal mulai pengerjaan (start_date)']);
        exit;
    }

    try {
        // Find project & client
        $stmtP = $db->prepare("
            SELECT p.id, p.client_id, p.name as project_name, p.ads_budget_pool,
                   COALESCE((SELECT SUM(t2.assigned_ads_budget) FROM tasks t2 WHERE t2.project_id = p.id AND t2.is_deleted = 0), 0) as used_ads_budget
            FROM projects p WHERE p.id = ?
        ");
        $stmtP->execute([$projectId]);
        $proj = $stmtP->fetch();

        if (!$proj) {
            echo json_encode(['success' => false, 'message' => 'Proyek tidak ditemukan']);
            exit;
        }

        $clientIdOfProject = (int)$proj['client_id'];
        $pool = (float)($proj['ads_budget_pool'] ?? 0);
        $usedPool = (float)($proj['used_ads_budget'] ?? 0);
        $availableAdsPool = max(0, $pool - $usedPool);

        // Validation if assigned ads budget exceeds available pool
        if ($assignedAdsBudget > 0 && $pool > 0 && $assignedAdsBudget > $availableAdsPool) {
            echo json_encode([
                'success' => false,
                'message' => "Budget iklan yang dialokasikan (Rp " . number_format($assignedAdsBudget, 0, ',', '.') . ") melebihi sisa saldo ads proyek yang tersedia (Rp " . number_format($availableAdsPool, 0, ',', '.') . "). Silakan top-up saldo ads proyek terlebih dahulu."
            ]);
            exit;
        }

        // Determine worker_type (karyawan vs freelancer) & validate contracts
        $stmtU = $db->prepare("
            SELECT id, name, role, employment_type, phone_wa, service_specialty, contract_start_date, contract_end_date, is_active 
            FROM users WHERE id = ?
        ");
        $stmtU->execute([$assignedTo]);
        $assignee = $stmtU->fetch();

        if (!$assignee) {
            echo json_encode(['success' => false, 'message' => 'PIC tidak ditemukan di database']);
            exit;
        }

        // VALIDASI MASA KONTRAK KERJA KARYAWAN
        if (!empty($assignee['contract_start_date']) && !empty($assignee['contract_end_date'])) {
            if ($startDate < $assignee['contract_start_date'] || $dueDate > $assignee['contract_end_date']) {
                echo json_encode([
                    'success' => false,
                    'message' => "Gagal: Masa tugas ({$startDate} s/d {$dueDate}) tidak boleh kurang dari atau melebihi masa kontrak kerja {$assignee['name']} ({$assignee['contract_start_date']} s/d {$assignee['contract_end_date']})."
                ]);
                exit;
            }
        }

        // VALIDASI MASA KONTRAK KLIEN
        $stmtCl = $db->prepare("SELECT company, contract_start_date, contract_end_date, contract_status FROM clients WHERE id = ?");
        $stmtCl->execute([$clientIdOfProject]);
        $clientData = $stmtCl->fetch();
        if ($clientData && !empty($clientData['contract_end_date'])) {
            if (($clientData['contract_status'] ?? '') === 'expired' || $dueDate > $clientData['contract_end_date']) {
                echo json_encode([
                    'success' => false,
                    'message' => "Gagal: Tenggat tugas ({$dueDate}) melebihi masa kontrak klien {$clientData['company']} ({$clientData['contract_end_date']}). Silakan perpanjang kontrak klien terlebih dahulu."
                ]);
                exit;
            }
        }

        // Handle proof of transfer file upload
        $transferProofFile = null;
        if (!empty($_FILES['transfer_proof_file']['name']) && $_FILES['transfer_proof_file']['error'] === UPLOAD_ERR_OK) {
            $allowedExts = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
            $fileInfo = pathinfo($_FILES['transfer_proof_file']['name']);
            $ext = strtolower($fileInfo['extension'] ?? '');
            if (in_array($ext, $allowedExts)) {
                $uploadDir = BASE_PATH . '/assets/uploads/receipts/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $filename = 'proof_ads_' . time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['transfer_proof_file']['tmp_name'], $uploadDir . $filename)) {
                    $transferProofFile = 'assets/uploads/receipts/' . $filename;
                }
            }
        }

        $workerType = ($assignee['role'] === 'freelancer' || $assignee['employment_type'] === 'freelance') ? 'freelancer' : 'karyawan';

        $insert = $db->prepare("
            INSERT INTO tasks (
                project_id, client_id, assigned_to_user_id, assigned_by_user_id,
                title, description, category, worker_type, priority, status,
                deliverable_link, start_date, due_date,
                assigned_ads_budget, ads_spent_amount, remaining_ads_balance,
                campaign_run_status, transfer_proof_file, ads_platform
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'backlog', ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([
            $projectId,
            $clientIdOfProject,
            $assignedTo,
            $userId,
            $title,
            $description,
            $category,
            $workerType,
            $priority,
            $deliverableLink ?: null,
            $startDate,
            $dueDate,
            $assignedAdsBudget,
            0,
            $assignedAdsBudget,
            $assignedAdsBudget > 0 ? $campaignRunStatus : null,
            $transferProofFile,
            $adsPlatform ?: null
        ]);
        $newTaskId = (int)$db->lastInsertId();

        // Audit log for ads budget allocation
        if ($assignedAdsBudget > 0) {
            try {
                $db->prepare("
                    INSERT INTO ads_budget_logs (project_id, client_id, task_id, amount, type, notes, created_by)
                    VALUES (?, ?, ?, ?, 'task_allocation', ?, ?)
                ")->execute([
                    $projectId,
                    $clientIdOfProject,
                    $newTaskId,
                    $assignedAdsBudget,
                    "Alokasi budget iklan tugas: {$title} ({$adsPlatform}) ke {$assignee['name']}",
                    $userId
                ]);
            } catch (Exception $e) {}
        }

        log_activity('task', "Tugas Baru Dibagikan: {$title}", "Ditugaskan ke {$assignee['name']} ({$workerType}) untuk proyek {$proj['project_name']} ({$startDate} s/d {$dueDate})" . ($assignedAdsBudget > 0 ? " | Budget Ads: Rp " . number_format($assignedAdsBudget, 0, ',', '.') : ""));

        // Trigger webhook to notify worker
        triggerWorkerWebhook([
            'event' => 'task_assigned',
            'task_id' => $newTaskId,
            'task_title' => $title,
            'project_name' => $proj['project_name'],
            'category' => $category,
            'priority' => $priority,
            'start_date' => $startDate,
            'due_date' => $dueDate,
            'assigned_ads_budget' => $assignedAdsBudget,
            'ads_platform' => $adsPlatform,
            'worker_id' => $assignedTo,
            'worker_name' => $assignee['name'],
            'worker_phone' => $assignee['phone_wa'] ?? '',
            'worker_type' => $workerType,
            'assigned_by' => $user['name'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        echo json_encode([
            'success' => true,
            'message' => "Tugas berhasil dibagikan ke {$assignee['name']}!" . ($assignedAdsBudget > 0 ? " Budget iklan Rp " . number_format($assignedAdsBudget, 0, ',', '.') . " dialokasikan." : ""),
            'task_id' => $newTaskId
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal membagi tugas: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 3b. POST: Update Ads Campaign Metrics (Worker / Admin / Owner)
// -------------------------------------------------------------
if ($action === 'update_ads_campaign_metrics') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    $spentAmount = parse_task_amount($_POST['ads_spent_amount'] ?? 0);
    $campaignRunStatus = trim($_POST['campaign_run_status'] ?? 'running');
    $notes = trim($_POST['ads_spent_notes'] ?? '');

    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Task ID tidak valid']);
        exit;
    }

    $validStatuses = ['running', 'paused', 'stopped'];
    if (!in_array($campaignRunStatus, $validStatuses)) {
        $campaignRunStatus = 'running';
    }

    try {
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();

        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Tugas iklan tidak ditemukan']);
            exit;
        }

        if (!is_admin() && (int)$task['assigned_to_user_id'] !== $userId) {
            echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak akses memperbarui metrik iklan tugas ini']);
            exit;
        }

        $assignedBudget = (float)($task['assigned_ads_budget'] ?? 0);
        $remainingBalance = max(0, $assignedBudget - $spentAmount);

        // Handle screenshot upload
        $proofPath = $task['ads_spent_proof'] ?? null;
        if (!empty($_FILES['ads_spent_proof']['name']) && $_FILES['ads_spent_proof']['error'] === UPLOAD_ERR_OK) {
            $allowedExts = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
            $fileInfo = pathinfo($_FILES['ads_spent_proof']['name']);
            $ext = strtolower($fileInfo['extension'] ?? '');
            if (in_array($ext, $allowedExts)) {
                $uploadDir = BASE_PATH . '/assets/uploads/tasks/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $filename = 'spent_proof_' . $taskId . '_' . time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['ads_spent_proof']['tmp_name'], $uploadDir . $filename)) {
                    $proofPath = 'assets/uploads/tasks/' . $filename;
                }
            }
        }

        $stmtUpdate = $db->prepare("
            UPDATE tasks
            SET ads_spent_amount = ?,
                remaining_ads_balance = ?,
                campaign_run_status = ?,
                ads_spent_notes = ?,
                ads_spent_proof = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmtUpdate->execute([
            $spentAmount,
            $remainingBalance,
            $campaignRunStatus,
            $notes ?: $task['ads_spent_notes'],
            $proofPath,
            $taskId
        ]);

        // Insert log into ads_budget_logs
        try {
            $db->prepare("
                INSERT INTO ads_budget_logs (project_id, client_id, task_id, amount, type, notes, created_by)
                VALUES (?, ?, ?, ?, 'spend_update', ?, ?)
            ")->execute([
                $task['project_id'],
                $task['client_id'],
                $taskId,
                $spentAmount,
                "Update pemakaian iklan: Rp " . number_format($spentAmount, 0, ',', '.') . " (Sisa: Rp " . number_format($remainingBalance, 0, ',', '.') . ", Status: {$campaignRunStatus}) oleh {$user['name']}",
                $userId
            ]);
        } catch (Exception $e) {}

        log_activity('task', "Pemakaian Iklan Diperbarui: {$task['title']}", "Terpakai: Rp " . number_format($spentAmount, 0, ',', '.') . " | Sisa: Rp " . number_format($remainingBalance, 0, ',', '.') . " | Status: {$campaignRunStatus}");

        echo json_encode([
            'success' => true,
            'message' => 'Status dan pemakaian saldo iklan berhasil diperbarui!',
            'data' => [
                'task_id' => $taskId,
                'assigned_ads_budget' => $assignedBudget,
                'ads_spent_amount' => $spentAmount,
                'remaining_ads_balance' => $remainingBalance,
                'campaign_run_status' => $campaignRunStatus
            ]
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui pemakaian iklan: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 4. POST: Update Progress / Status & Deliverable Link
// -------------------------------------------------------------
if ($action === 'update_progress') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $deliverableLink = trim($_POST['deliverable_link'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $validStatuses = ['backlog', 'in_progress', 'internal_review', 'client_review', 'revision', 'approved'];
    if (!in_array($status, $validStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
        exit;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();

        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Tugas tidak ditemukan']);
            exit;
        }

        // Permission check
        if (!is_admin() && (int)$task['assigned_to_user_id'] !== $userId) {
            echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin mengubah status tugas ini']);
            exit;
        }

        // Update task
        $updateSql = "UPDATE tasks SET status = ?, updated_at = CURRENT_TIMESTAMP";
        $params = [$status];

        if (!empty($deliverableLink)) {
            $updateSql .= ", deliverable_link = ?";
            $params[] = $deliverableLink;
        }

        $updateSql .= " WHERE id = ?";
        $params[] = $taskId;

        $db->prepare($updateSql)->execute($params);

        // If notes provided, add as feedback comment
        if (!empty($notes)) {
            $db->prepare("
                INSERT INTO task_feedbacks (task_id, user_id, feedback_type, message, is_read_by_worker)
                VALUES (?, ?, 'comment', ?, 1)
            ")->execute([$taskId, $userId, $notes]);
        }

        log_activity('task', "Status Tugas Diperbarui: {$task['title']}", "Status diubah menjadi [{$status}] oleh {$user['name']}");

        // If status changed to client_review, notify via webhook
        if ($status === 'client_review') {
            triggerWorkerWebhook([
                'event' => 'task_ready_for_client_review',
                'task_id' => $taskId,
                'task_title' => $task['title'],
                'deliverable_link' => $deliverableLink ?: $task['deliverable_link'],
                'updated_by' => $user['name'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Progres tugas berhasil diperbarui!'
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 5. POST: Submit Feedback / Revision Request / Approval
// -------------------------------------------------------------
if ($action === 'submit_feedback') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    $feedbackType = trim($_POST['feedback_type'] ?? 'comment'); // 'comment', 'revision_request', 'approval'
    $message = trim($_POST['message'] ?? '');

    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Task ID tidak valid']);
        exit;
    }

    if (empty($message) && $feedbackType !== 'approval') {
        echo json_encode(['success' => false, 'message' => 'Catatan / pesan masukan wajib diisi']);
        exit;
    }

    if ($feedbackType === 'approval' && empty($message)) {
        $message = "Hasil kerja telah disetujui oleh " . $user['name'] . ". Luar biasa!";
    }

    try {
        $stmt = $db->prepare("
            SELECT t.*, p.name as project_name, c.company as client_company,
                   u.name as worker_name, u.phone_wa as worker_phone, u.role as worker_role
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN clients c ON t.client_id = c.id
            LEFT JOIN users u ON t.assigned_to_user_id = u.id
            WHERE t.id = ? AND t.is_deleted = 0
        ");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();

        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Tugas tidak ditemukan']);
            exit;
        }

        // Scope check for client
        if ($role === 'client' && (int)$task['client_id'] !== $clientId) {
            echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin memberikan masukan pada tugas ini']);
            exit;
        }

        // Handle attachment file upload if present
        $attachmentPath = null;
        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'mp4', 'svg'];
            $fileInfo = pathinfo($_FILES['attachment']['name']);
            $ext = strtolower($fileInfo['extension'] ?? '');

            if (in_array($ext, $allowedExts)) {
                $uploadDir = BASE_PATH . '/assets/uploads/tasks/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $filename = 'fb_' . $taskId . '_' . time() . '_' . uniqid() . '.' . $ext;
                $targetFile = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetFile)) {
                    $attachmentPath = 'assets/uploads/tasks/' . $filename;
                }
            }
        }

        // Insert into task_feedbacks
        $isRead = ($role === 'client') ? 0 : 1; // Unread for worker if submitted by client
        $fbInsert = $db->prepare("
            INSERT INTO task_feedbacks (task_id, user_id, feedback_type, message, attachment_path, is_read_by_worker)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $fbInsert->execute([$taskId, $userId, $feedbackType, $message, $attachmentPath, $isRead]);

        // Auto-update task status based on client feedback type
        $newStatus = null;
        if ($role === 'client') {
            if ($feedbackType === 'revision_request') {
                $newStatus = 'revision';
                $db->prepare("UPDATE tasks SET status = 'revision', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$taskId]);
            } elseif ($feedbackType === 'approval') {
                $newStatus = 'approved';
                $db->prepare("UPDATE tasks SET status = 'approved', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$taskId]);
            }
        }

        // Trigger non-blocking Webhook to Worker
        triggerWorkerWebhook([
            'event' => 'client_feedback_received',
            'task_id' => $taskId,
            'task_title' => $task['title'],
            'project_name' => $task['project_name'],
            'client_name' => $user['name'] . ' (' . ($task['client_company'] ?? 'Client') . ')',
            'feedback_type' => $feedbackType,
            'message' => $message,
            'attachment_url' => $attachmentPath ? url($attachmentPath) : null,
            'worker_id' => $task['assigned_to_user_id'],
            'worker_name' => $task['worker_name'],
            'worker_phone' => $task['worker_phone'] ?? '',
            'new_status' => $newStatus,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        log_activity('feedback', "Feedback Masuk: {$task['title']}", "Tipe: {$feedbackType} dari {$user['name']}");

        echo json_encode([
            'success' => true,
            'message' => ($feedbackType === 'approval') ? 'Pekerjaan berhasil disetujui! Terima kasih.' : 'Masukan Anda berhasil dikirim ke tim Kalamedia.',
            'new_status' => $newStatus
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 6. POST: Mark Feedbacks as Read
// -------------------------------------------------------------
if ($action === 'mark_feedbacks_read') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    if ($taskId) {
        $db->prepare("UPDATE task_feedbacks SET is_read_by_worker = 1 WHERE task_id = ? AND user_id != ?")
           ->execute([$taskId, $userId]);
    }
    echo json_encode(['success' => true]);
    exit;
}

// -------------------------------------------------------------
// 7. GET: Team Members for Assignment Dropdown
// -------------------------------------------------------------
if ($action === 'team_members') {
    if (!is_admin()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    try {
        $stmt = $db->query("
            SELECT id, name, email, role, employment_type, position, phone_wa, avatar,
                   service_specialty, contract_start_date, contract_end_date
            FROM users
            WHERE role IN ('karyawan', 'freelancer', 'admin', 'owner')
              AND (is_active IS NULL OR is_active = 1)
              AND COALESCE(is_deleted, 0) = 0
            ORDER BY 
                CASE 
                    WHEN role = 'karyawan' THEN 1
                    WHEN role = 'freelancer' THEN 2
                    WHEN role = 'admin' THEN 3
                    ELSE 4
                END,
                name ASC
        ");
        $members = $stmt->fetchAll();

        echo json_encode(['success' => true, 'members' => $members]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 8. GET: Projects & Clients Dropdown
// -------------------------------------------------------------
if ($action === 'projects_dropdown') {
    if (!is_admin()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    try {
        $stmt = $db->query("
            SELECT p.id as project_id, p.name as project_name, p.client_id,
                   p.service_fee_amount, p.ads_budget_pool, p.total_contract_value,
                   COALESCE((
                       SELECT SUM(t2.assigned_ads_budget) 
                       FROM tasks t2 
                       WHERE t2.project_id = p.id AND t2.is_deleted = 0
                   ), 0) as total_allocated_ads_budget,
                   c.company as client_company, c.name as client_pic,
                   c.service_type, c.contract_start_date, c.contract_end_date, c.contract_status
            FROM projects p
            JOIN clients c ON p.client_id = c.id
            WHERE p.is_deleted = 0 AND c.is_deleted = 0
            ORDER BY c.company ASC, p.id DESC
        ");
        $projects = $stmt->fetchAll();
        foreach ($projects as &$p) {
            $pool = (float)($p['ads_budget_pool'] ?? 0);
            $allocated = (float)($p['total_allocated_ads_budget'] ?? 0);
            $p['available_ads_pool'] = max(0, $pool - $allocated);
        }
        unset($p);

        echo json_encode(['success' => true, 'projects' => $projects]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 9. POST: Delete Task (Owner / Admin)
// -------------------------------------------------------------
if ($action === 'delete_task') {
    if (!is_admin()) {
        echo json_encode(['success' => false, 'message' => 'Hanya Owner dan Admin yang dapat menghapus tugas']);
        exit;
    }

    $taskId = (int)($_POST['task_id'] ?? 0);
    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Task ID tidak valid']);
        exit;
    }

    try {
        try {
            $db->prepare("DELETE FROM task_feedbacks WHERE task_id = ?")->execute([$taskId]);
        } catch (Exception $e) {}

        // Direct delete
        $del = $db->prepare("DELETE FROM tasks WHERE id = ?");
        $del->execute([$taskId]);

        // Soft delete fallback if still exists
        try {
            $db->prepare("UPDATE tasks SET is_deleted = 1 WHERE id = ?")->execute([$taskId]);
        } catch (Exception $e) {}

        log_activity('task', "Tugas Dihapus", "Task ID {$taskId} dihapus oleh {$user['name']}");

        echo json_encode(['success' => true, 'message' => 'Tugas berhasil dihapus']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus tugas: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 10. POST: Delete All Tasks (Owner / Admin)
// -------------------------------------------------------------
if ($action === 'delete_all_tasks') {
    if (!is_admin()) {
        echo json_encode(['success' => false, 'message' => 'Hanya Owner dan Admin yang dapat menghapus tugas']);
        exit;
    }

    try {
        $db->exec("DELETE FROM task_feedbacks");
        $db->exec("DELETE FROM tasks");
        log_activity('task', "Semua Tugas Dihapus", "Seluruh data tugas dibersihkan oleh {$user['name']}");

        echo json_encode(['success' => true, 'message' => 'Semua tugas berhasil dihapus bersih']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus semua tugas: ' . $e->getMessage()]);
        exit;
    }
}

if (!empty($action) && $action !== 'none') {
    echo json_encode(['success' => false, 'message' => 'Aksi API tidak valid']);
}

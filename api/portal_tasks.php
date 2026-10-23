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
                       (SELECT COUNT(*) FROM task_feedbacks WHERE task_id = t.id AND is_read_by_client = 0 AND user_id != ?) as unread_feedbacks_count,
                       (SELECT message FROM task_feedbacks WHERE task_id = t.id ORDER BY id DESC LIMIT 1) as latest_feedback,
                       (SELECT created_at FROM task_feedbacks WHERE task_id = t.id ORDER BY id DESC LIMIT 1) as latest_feedback_time
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
            $stmt->execute([$userId, $clientId]);
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

            // Calculate Client's Freelancer Payouts Summary (Nominal yang sudah dibayarkan ke freelancer)
            $stmtClientFp = $db->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN fp.status = 'Paid' THEN fp.amount ELSE 0 END), 0) as paid_amount,
                    COALESCE(SUM(CASE WHEN fp.status = 'Pending' THEN fp.amount ELSE 0 END), 0) as pending_amount
                FROM freelancer_payouts fp
                JOIN projects p ON fp.project_id = p.id
                WHERE p.client_id = ? AND COALESCE(fp.is_deleted, 0) = 0
            ");
            $stmtClientFp->execute([$clientId]);
            $clientFpRow = $stmtClientFp->fetch(PDO::FETCH_ASSOC);
            $clientFpPaid = floatval($clientFpRow['paid_amount'] ?? 0);
            $clientFpPending = floatval($clientFpRow['pending_amount'] ?? 0);

            $freelancerSummary = [
                'paid_amount' => $clientFpPaid,
                'pending_amount' => $clientFpPending,
                'total_fee' => $clientFpPaid + $clientFpPending,
                'formatted_paid' => format_rupiah($clientFpPaid),
                'formatted_pending' => format_rupiah($clientFpPending),
                'formatted_total' => format_rupiah($clientFpPaid + $clientFpPending)
            ];

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
                'freelancer_summary' => $freelancerSummary,
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
                    OR project_id IN (SELECT DISTINCT project_id FROM freelancer_payouts WHERE (freelancer_name = ? OR freelancer_name LIKE ?) AND COALESCE(is_deleted,0)=0)
                )
            ");
            $stmtWorkerAds->execute([$userId, $userId, $userId, $userId, $user['name'], '%' . $user['name'] . '%']);
            $wAds = $stmtWorkerAds->fetch(PDO::FETCH_ASSOC);
            $totalDitransfer = floatval($wAds['total_transfer'] ?? 0);
            $totalDibelanjakan = floatval($wAds['total_spent'] ?? 0);
            $sisaSaldo = $totalDitransfer - $totalDibelanjakan;
            $statusIklan = ($sisaSaldo > 0) ? 'Iklan Jalan' : ($totalDitransfer > 0 ? 'Saldo Habis' : 'Belum Ada Saldo');

            // Calculate Freelancer Fee / Honorarium if role is freelancer
            $freelancerSummary = null;
            if ($role === 'freelancer') {
                $stmtFp = $db->prepare("
                    SELECT 
                        COALESCE(SUM(CASE WHEN status = 'Paid' THEN amount ELSE 0 END), 0) as paid_amount,
                        COALESCE(SUM(CASE WHEN status = 'Pending' THEN amount ELSE 0 END), 0) as pending_amount
                    FROM freelancer_payouts 
                    WHERE COALESCE(is_deleted, 0) = 0 
                    AND (freelancer_name = ? OR freelancer_name LIKE ? OR project_id IN (SELECT DISTINCT project_id FROM tasks WHERE assigned_to_user_id = ? AND COALESCE(is_deleted, 0) = 0))
                ");
                $stmtFp->execute([$user['name'], '%' . $user['name'] . '%', $userId]);
                $fpRow = $stmtFp->fetch(PDO::FETCH_ASSOC);
                $freelancerPaid = floatval($fpRow['paid_amount'] ?? 0);
                $freelancerPending = floatval($fpRow['pending_amount'] ?? 0);

                $freelancerSummary = [
                    'paid_amount' => $freelancerPaid,
                    'pending_amount' => $freelancerPending,
                    'total_fee' => $freelancerPaid + $freelancerPending,
                    'formatted_paid' => format_rupiah($freelancerPaid),
                    'formatted_pending' => format_rupiah($freelancerPending),
                    'formatted_total' => format_rupiah($freelancerPaid + $freelancerPending)
                ];
            }

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
                'freelancer_summary' => $freelancerSummary,
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
                       (SELECT COUNT(*) FROM task_feedbacks WHERE task_id = t.id AND is_read_by_owner = 0 AND user_id != ?) as unread_feedbacks_count,
                       (SELECT message FROM task_feedbacks WHERE task_id = t.id ORDER BY id DESC LIMIT 1) as latest_feedback,
                       (SELECT created_at FROM task_feedbacks WHERE task_id = t.id ORDER BY id DESC LIMIT 1) as latest_feedback_time
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
            $stmt->execute(array_merge([$userId], $params));
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
// 2a. GET: List All Chat Rooms (with latest message and unread counts)
// -------------------------------------------------------------
if ($action === 'list_chat_rooms') {
    try {
        $where = ["t.is_deleted = 0"];
        $params = [];

        if ($role === 'client') {
            $where[] = "t.client_id = ?";
            $params[] = $clientId;
        } elseif ($role === 'karyawan' || $role === 'freelancer') {
            $where[] = "t.assigned_to_user_id = ?";
            $params[] = $userId;
        }
        // Owner and Admin see ALL tasks!

        $sqlWhere = implode(" AND ", $where);

        $unreadCol = ($role === 'client') 
            ? "is_read_by_client" 
            : (($role === 'karyawan' || $role === 'freelancer') ? "is_read_by_worker" : "is_read_by_owner");

        $sql = "
            SELECT t.id, t.title, t.category, t.status, t.deliverable_link, t.due_date,
                   t.client_id, c.company as client_company, c.name as client_pic_name,
                   t.assigned_to_user_id as worker_id, u.name as worker_name, u.role as worker_role,
                   u.avatar as worker_avatar, u.employment_type as worker_emp_type,
                   (SELECT COUNT(*) FROM task_feedbacks WHERE task_id = t.id) as feedbacks_count,
                   (SELECT COUNT(*) FROM task_feedbacks WHERE task_id = t.id AND {$unreadCol} = 0 AND user_id != ?) as unread_count,
                   (SELECT message FROM task_feedbacks WHERE task_id = t.id ORDER BY id DESC LIMIT 1) as latest_feedback,
                   (SELECT feedback_type FROM task_feedbacks WHERE task_id = t.id ORDER BY id DESC LIMIT 1) as latest_feedback_type,
                   (SELECT created_at FROM task_feedbacks WHERE task_id = t.id ORDER BY id DESC LIMIT 1) as latest_feedback_time,
                   (SELECT u_s.name FROM task_feedbacks tf_s JOIN users u_s ON tf_s.user_id = u_s.id WHERE tf_s.task_id = t.id ORDER BY tf_s.id DESC LIMIT 1) as latest_sender_name,
                   (SELECT u_s.role FROM task_feedbacks tf_s JOIN users u_s ON tf_s.user_id = u_s.id WHERE tf_s.task_id = t.id ORDER BY tf_s.id DESC LIMIT 1) as latest_sender_role
            FROM tasks t
            LEFT JOIN clients c ON t.client_id = c.id
            LEFT JOIN users u ON t.assigned_to_user_id = u.id
            WHERE {$sqlWhere}
            ORDER BY 
                CASE WHEN (SELECT COUNT(*) FROM task_feedbacks WHERE task_id = t.id AND {$unreadCol} = 0 AND user_id != ?) > 0 THEN 0 ELSE 1 END,
                COALESCE((SELECT MAX(created_at) FROM task_feedbacks WHERE task_id = t.id), t.updated_at, t.created_at) DESC
        ";

        $fullParams = array_merge([$userId], $params, [$userId]);
        $stmt = $db->prepare($sql);
        $stmt->execute($fullParams);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalUnread = 0;
        foreach ($rooms as &$r) {
            $totalUnread += (int)$r['unread_count'];
            $r['latest_feedback_snippet'] = !empty($r['latest_feedback']) 
                ? (mb_strlen($r['latest_feedback']) > 45 ? mb_substr($r['latest_feedback'], 0, 42) . '...' : $r['latest_feedback'])
                : 'Belum ada obrolan diskusi';
            $r['latest_feedback_time_formatted'] = !empty($r['latest_feedback_time'])
                ? date('H:i', strtotime($r['latest_feedback_time']))
                : '';
        }

        echo json_encode([
            'success' => true,
            'role' => $role,
            'user_name' => $user['name'] ?? '',
            'rooms' => $rooms,
            'total_unread' => $totalUnread
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
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
            // Mark feedbacks as read for client
            $db->prepare("UPDATE task_feedbacks SET is_read_by_client = 1 WHERE task_id = ? AND user_id != ?")
               ->execute([$taskId, $userId]);
        } elseif ($role === 'karyawan' || $role === 'freelancer') {
            if ((int)$task['assigned_to_user_id'] !== $userId && !$isOwnerOrAdmin) {
                echo json_encode(['success' => false, 'message' => 'Tugas ini bukan bagian dari tanggung jawab Anda']);
                exit;
            }
            // Mark feedbacks as read for worker
            $db->prepare("UPDATE task_feedbacks SET is_read_by_worker = 1 WHERE task_id = ? AND user_id != ?")
               ->execute([$taskId, $userId]);
        } else {
            // Owner and Admin mark feedbacks as read for owner
            $db->prepare("UPDATE task_feedbacks SET is_read_by_owner = 1 WHERE task_id = ? AND user_id != ?")
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
        $rawFeedbacks = $stmtFb->fetchAll(PDO::FETCH_ASSOC);
        $feedbacks = [];
        foreach ($rawFeedbacks as $fb) {
            $fb['is_self'] = ((int)$fb['user_id'] === $userId);
            $fb['time_formatted'] = !empty($fb['created_at']) ? date('H:i', strtotime($fb['created_at'])) : '';
            $fb['date_formatted'] = !empty($fb['created_at']) ? date('d M Y', strtotime($fb['created_at'])) : '';
            $feedbacks[] = $fb;
        }

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
        if ($assignedBudget > 0 && $spentAmount > $assignedBudget) {
            $spentAmount = $assignedBudget; // Otomatis batasi maksimal sesuai budget yang sudah diinput sebelumnya
        }
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

    $hasAttachment = (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK);
    if (empty($message) && !$hasAttachment && $feedbackType !== 'approval') {
        echo json_encode(['success' => false, 'message' => 'Catatan / pesan masukan wajib diisi']);
        exit;
    }

    if (empty($message) && $hasAttachment) {
        $message = 'Lampiran gambar / screenshot referensi';
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

        // Insert into task_feedbacks with 3-role read flags
        $isReadWorker = ($role === 'karyawan' || $role === 'freelancer') ? 1 : 0;
        $isReadClient = ($role === 'client') ? 1 : 0;
        $isReadOwner = $isOwnerOrAdmin ? 1 : 0;

        $fbInsert = $db->prepare("
            INSERT INTO task_feedbacks (task_id, user_id, feedback_type, message, attachment_path, is_read_by_worker, is_read_by_client, is_read_by_owner)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $fbInsert->execute([$taskId, $userId, $feedbackType, $message, $attachmentPath, $isReadWorker, $isReadClient, $isReadOwner]);
        $newFbId = $db->lastInsertId();

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
            'message' => ($feedbackType === 'approval') ? 'Pekerjaan berhasil disetujui! Terima kasih.' : 'Pesan berhasil dikirim ke room diskusi.',
            'new_status' => $newStatus,
            'feedback' => [
                'id' => (int)$newFbId,
                'task_id' => $taskId,
                'user_id' => $userId,
                'sender_name' => $user['name'],
                'sender_role' => $role,
                'sender_emp_type' => $user['employment_type'] ?? '',
                'sender_avatar' => $user['avatar'] ?? '',
                'feedback_type' => $feedbackType,
                'message' => $message,
                'attachment_path' => $attachmentPath,
                'created_at' => date('Y-m-d H:i:s'),
                'time_formatted' => date('H:i'),
                'date_formatted' => date('d M Y'),
                'is_self' => true
            ]
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
        if ($role === 'client') {
            $db->prepare("UPDATE task_feedbacks SET is_read_by_client = 1 WHERE task_id = ? AND user_id != ?")
               ->execute([$taskId, $userId]);
        } elseif ($role === 'karyawan' || $role === 'freelancer') {
            $db->prepare("UPDATE task_feedbacks SET is_read_by_worker = 1 WHERE task_id = ? AND user_id != ?")
               ->execute([$taskId, $userId]);
        } else {
            $db->prepare("UPDATE task_feedbacks SET is_read_by_owner = 1 WHERE task_id = ? AND user_id != ?")
               ->execute([$taskId, $userId]);
        }
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

// -------------------------------------------------------------
// 11. GET: Work Recap & Productivity (Bulanan / Per Proyek)
// -------------------------------------------------------------
if ($action === 'work_recap') {
    try {
        $filterMonth = trim($_GET['month'] ?? '');
        $filterProjectId = (int)($_GET['project_id'] ?? 0);
        $filterClientId = (int)($_GET['client_id'] ?? 0);
        $filterServiceType = trim($_GET['service_type'] ?? 'all');
        $filterWorkerId = (int)($_GET['worker_user_id'] ?? 0);

        // Strict Role Scoping
        if (is_client()) {
            $clientAuthId = current_user_client_id();
            if ($clientAuthId > 0) {
                $filterClientId = $clientAuthId;
            } else {
                echo json_encode(['success' => false, 'message' => 'Akun klien tidak terhubung ke data klien.']);
                exit;
            }
        } elseif ($role === 'karyawan' || $role === 'freelancer') {
            // Worker is locked to their own tasks
            $filterWorkerId = $userId;
        }

        $where = ["COALESCE(t.is_deleted, 0) = 0", "(p.id IS NULL OR COALESCE(p.is_deleted, 0) = 0)", "(c.id IS NULL OR COALESCE(c.is_deleted, 0) = 0)"];
        $params = [];

        // Month filter (YYYY-MM)
        if (!empty($filterMonth) && $filterMonth !== 'all') {
            $monthLike = $filterMonth . '%';
            $where[] = "(t.created_at LIKE ? OR t.due_date LIKE ? OR t.start_date LIKE ?)";
            $params[] = $monthLike;
            $params[] = $monthLike;
            $params[] = $monthLike;
        }

        // Project filter
        if ($filterProjectId > 0) {
            $where[] = "t.project_id = ?";
            $params[] = $filterProjectId;
        }

        // Client filter
        if ($filterClientId > 0) {
            $where[] = "(t.client_id = ? OR p.client_id = ?)";
            $params[] = $filterClientId;
            $params[] = $filterClientId;
        }

        // Worker filter
        if ($filterWorkerId > 0) {
            $where[] = "t.assigned_to_user_id = ?";
            $params[] = $filterWorkerId;
        }

        // Service Type filter
        if (!empty($filterServiceType) && $filterServiceType !== 'all') {
            if ($filterServiceType === 'eceran') {
                $where[] = "(LOWER(COALESCE(p.service_type, '')) LIKE '%eceran%' OR LOWER(COALESCE(p.service_type, '')) LIKE '%satuan%' OR LOWER(COALESCE(c.package_name, '')) LIKE '%eceran%' OR LOWER(COALESCE(c.package_name, '')) LIKE '%satuan%')";
            } elseif ($filterServiceType === 'sosmed') {
                $where[] = "(LOWER(COALESCE(p.service_type, '')) LIKE '%sosmed%' OR LOWER(COALESCE(p.service_type, '')) LIKE '%social%' OR LOWER(COALESCE(c.package_name, '')) LIKE '%sosmed%')";
            } elseif ($filterServiceType === 'web') {
                $where[] = "(LOWER(COALESCE(p.service_type, '')) LIKE '%web%' OR LOWER(COALESCE(c.package_name, '')) LIKE '%web%')";
            } elseif ($filterServiceType === 'ads') {
                $where[] = "(LOWER(COALESCE(p.service_type, '')) LIKE '%ads%' OR LOWER(COALESCE(p.service_type, '')) LIKE '%iklan%')";
            } else {
                $where[] = "p.service_type = ?";
                $params[] = $filterServiceType;
            }
        }

        $whereSql = implode(' AND ', $where);

        // Summary Aggregates (100% dynamic via SQL COUNT)
        $summarySql = "
            SELECT 
                COUNT(*) as total_tasks,
                SUM(CASE WHEN t.status = 'approved' THEN 1 ELSE 0 END) as approved_tasks,
                SUM(CASE WHEN t.status IN ('in_progress', 'internal_review', 'client_review') THEN 1 ELSE 0 END) as in_progress_tasks,
                SUM(CASE WHEN t.status = 'revision' THEN 1 ELSE 0 END) as revision_tasks,
                SUM(CASE WHEN t.status = 'backlog' THEN 1 ELSE 0 END) as backlog_tasks
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN clients c ON (t.client_id = c.id OR p.client_id = c.id)
            LEFT JOIN users u ON t.assigned_to_user_id = u.id
            WHERE $whereSql
        ";
        $stmtSum = $db->prepare($summarySql);
        $stmtSum->execute($params);
        $summary = $stmtSum->fetch(PDO::FETCH_ASSOC);

        // Fetch Detailed Tasks List
        $taskSql = "
            SELECT 
                t.id, t.project_id, t.client_id, t.assigned_to_user_id,
                t.title, t.description, t.category, t.worker_type, t.priority,
                t.status, t.deliverable_link, t.start_date, t.due_date,
                t.created_at, t.updated_at,
                p.name as project_name, p.service_type as project_service_type, c.package_name,
                c.name as client_name, c.company as client_company,
                u.name as worker_name, u.position as worker_position, u.service_specialty as worker_specialty,
                (
                    SELECT fb.message 
                    FROM task_feedbacks fb 
                    WHERE fb.task_id = t.id 
                    ORDER BY fb.id DESC 
                    LIMIT 1
                ) as latest_feedback_message,
                (
                    SELECT fb.feedback_type 
                    FROM task_feedbacks fb 
                    WHERE fb.task_id = t.id 
                    ORDER BY fb.id DESC 
                    LIMIT 1
                ) as latest_feedback_type
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN clients c ON (t.client_id = c.id OR p.client_id = c.id)
            LEFT JOIN users u ON t.assigned_to_user_id = u.id
            WHERE $whereSql
            ORDER BY 
                CASE 
                    WHEN t.status = 'revision' THEN 1
                    WHEN t.status = 'client_review' THEN 2
                    WHEN t.status = 'in_progress' THEN 3
                    WHEN t.status = 'approved' THEN 4
                    ELSE 5
                END,
                t.id DESC
        ";
        $stmtTasks = $db->prepare($taskSql);
        $stmtTasks->execute($params);
        $rawTasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

        $categoryLabels = [
            'design' => 'Desain Grafis',
            'video' => 'Edit Video',
            'copywriting' => 'Copywriting',
            'web' => 'Website / Dev',
            'ads' => 'Advertising',
            'social_media' => 'Kelola Sosmed'
        ];

        $statusLabels = [
            'approved' => 'Selesai & Disetujui (Approved)',
            'client_review' => 'Review Klien',
            'internal_review' => 'Internal Review',
            'revision' => 'Revisi Klien',
            'in_progress' => 'Sedang Dikerjakan',
            'backlog' => 'Antrean (Backlog)'
        ];

        $tasks = [];
        foreach ($rawTasks as $r) {
            $isEceran = is_pure_eceran_service($r['project_service_type'], $r['package_name']);
            $r['is_eceran'] = $isEceran;
            $r['service_badge'] = $isEceran ? 'Desain Eceran / Satuan' : ($r['project_service_type'] ?: 'Reguler');
            $r['category_label'] = $categoryLabels[$r['category']] ?? ucfirst($r['category'] ?: 'Pekerjaan');
            $r['status_label'] = $statusLabels[$r['status']] ?? ucfirst($r['status'] ?: 'Pending');
            $tasks[] = $r;
        }

        // Available Months
        $monthsSql = "
            SELECT DISTINCT substr(created_at, 1, 7) as m
            FROM tasks
            WHERE is_deleted = 0 AND created_at IS NOT NULL
            ORDER BY m DESC
        ";
        $months = $db->query($monthsSql)->fetchAll(PDO::FETCH_COLUMN);
        if (empty($months)) {
            $months = [date('Y-m')];
        }

        // Projects for Filter Dropdown
        $projWhere = ["COALESCE(p.is_deleted, 0) = 0", "COALESCE(c.is_deleted, 0) = 0"];
        $projParams = [];
        if (is_client()) {
            $projWhere[] = "p.client_id = ?";
            $projParams[] = current_user_client_id();
        } elseif ($role === 'karyawan' || $role === 'freelancer') {
            $projWhere[] = "p.id IN (SELECT DISTINCT project_id FROM tasks WHERE assigned_to_user_id = ? AND COALESCE(is_deleted, 0) = 0)";
            $projParams[] = $userId;
        }
        $projStmt = $db->prepare("
            SELECT DISTINCT p.id, p.name, p.service_type, c.company as client_company
            FROM projects p
            JOIN clients c ON p.client_id = c.id
            WHERE " . implode(' AND ', $projWhere) . "
            ORDER BY c.company ASC, p.name ASC
        ");
        $projStmt->execute($projParams);
        $filterProjects = $projStmt->fetchAll(PDO::FETCH_ASSOC);

        // Workers for Filter Dropdown (Owner/Admin)
        $filterWorkers = [];
        if (is_admin()) {
            $filterWorkers = $db->query("
                SELECT id, name, position, service_specialty, role
                FROM users
                WHERE role IN ('karyawan', 'freelancer') AND is_active = 1 AND COALESCE(is_deleted,0)=0
                ORDER BY name ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'success' => true,
            'summary' => [
                'total_tasks' => (int)($summary['total_tasks'] ?? 0),
                'approved_tasks' => (int)($summary['approved_tasks'] ?? 0),
                'in_progress_tasks' => (int)($summary['in_progress_tasks'] ?? 0),
                'revision_tasks' => (int)($summary['revision_tasks'] ?? 0),
                'backlog_tasks' => (int)($summary['backlog_tasks'] ?? 0),
            ],
            'tasks' => $tasks,
            'filters' => [
                'months' => $months,
                'projects' => $filterProjects,
                'workers' => $filterWorkers,
                'current' => [
                    'month' => $filterMonth,
                    'project_id' => $filterProjectId,
                    'client_id' => $filterClientId,
                    'service_type' => $filterServiceType,
                    'worker_user_id' => $filterWorkerId
                ]
            ]
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal memuat rekap pekerjaan: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 12. POST: Update Worker Profile (Phone/WA & Password)
// -------------------------------------------------------------
if ($action === 'update_my_profile') {
    if (!$isWorkerUser && !$isOwnerOrAdmin) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
        exit;
    }

    $targetUserId = $userId;
    $phone_wa = trim($_POST['phone_wa'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    try {
        $updateFields = ['phone_wa = ?'];
        $params = [$phone_wa];

        if (!empty($new_password)) {
            if (strlen($new_password) < 6) {
                echo json_encode(['success' => false, 'message' => 'Password minimal 6 karakter']);
                exit;
            }
            if ($new_password !== $confirm_password) {
                echo json_encode(['success' => false, 'message' => 'Konfirmasi password baru tidak cocok']);
                exit;
            }
            $updateFields[] = 'password = ?';
            $params[] = password_hash($new_password, PASSWORD_DEFAULT);
        }

        $params[] = $targetUserId;
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        echo json_encode([
            'success' => true, 
            'message' => 'Profil berhasil diperbarui!', 
            'phone_wa' => $phone_wa
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui profil: ' . $e->getMessage()]);
        exit;
    }
}

if (!empty($action) && $action !== 'none') {
    echo json_encode(['success' => false, 'message' => 'Aksi API tidak valid']);
}


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

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

// Ensure user is authenticated
require_auth();

$user = current_user();
$role = current_user_role();
$userId = (int)$user['id'];
$clientId = current_user_client_id();

$db = Database::getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

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
                       t.worker_type, t.priority, t.status, t.deliverable_link, t.due_date,
                       t.created_at, t.updated_at,
                       p.name as project_name,
                       u.name as worker_name, u.role as worker_role, u.employment_type as worker_emp_type,
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
            $tasks = $stmt->fetchAll();

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
                       t.worker_type, t.priority, t.status, t.deliverable_link, t.due_date,
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
            $tasks = $stmt->fetchAll();

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

            $sqlWhere = implode(" AND ", $where);

            $stmt = $db->prepare("
                SELECT t.id, t.project_id, t.client_id, t.title, t.description, t.category,
                       t.worker_type, t.priority, t.status, t.deliverable_link, t.due_date,
                       t.created_at, t.updated_at,
                       p.name as project_name,
                       c.company as client_company, c.name as client_pic,
                       u.name as worker_name, u.role as worker_role, u.employment_type as worker_emp_type,
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
            $tasks = $stmt->fetchAll();

            // Overall Stats across all tasks
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
                FROM tasks WHERE is_deleted = 0
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
            'task' => $task,
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
    $category = trim($_POST['category'] ?? 'design');
    $assignedTo = (int)($_POST['assigned_to_user_id'] ?? 0);
    $priority = trim($_POST['priority'] ?? 'medium');
    $dueDate = trim($_POST['due_date'] ?? '');
    $deliverableLink = trim($_POST['deliverable_link'] ?? '');

    if (empty($title) || !$projectId || !$assignedTo) {
        echo json_encode(['success' => false, 'message' => 'Judul tugas, proyek, dan PIC wajib diisi']);
        exit;
    }

    try {
        // Find client_id from project
        $stmtP = $db->prepare("SELECT client_id, name as project_name FROM projects WHERE id = ?");
        $stmtP->execute([$projectId]);
        $proj = $stmtP->fetch();

        if (!$proj) {
            echo json_encode(['success' => false, 'message' => 'Proyek tidak ditemukan']);
            exit;
        }

        $clientIdOfProject = (int)$proj['client_id'];

        // Determine worker_type (karyawan vs freelancer)
        $stmtU = $db->prepare("SELECT id, name, role, employment_type, phone_wa FROM users WHERE id = ?");
        $stmtU->execute([$assignedTo]);
        $assignee = $stmtU->fetch();

        if (!$assignee) {
            echo json_encode(['success' => false, 'message' => 'PIC tidak ditemukan di database']);
            exit;
        }

        $workerType = ($assignee['role'] === 'freelancer' || $assignee['employment_type'] === 'freelance') ? 'freelancer' : 'karyawan';

        $insert = $db->prepare("
            INSERT INTO tasks (
                project_id, client_id, assigned_to_user_id, assigned_by_user_id,
                title, description, category, worker_type, priority, status,
                deliverable_link, due_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'backlog', ?, ?)
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
            $dueDate ?: null
        ]);
        $newTaskId = (int)$db->lastInsertId();

        log_activity('task', "Tugas Baru Dibagikan: {$title}", "Ditugaskan ke {$assignee['name']} ({$workerType}) untuk proyek {$proj['project_name']}");

        // Trigger webhook to notify worker
        triggerWorkerWebhook([
            'event' => 'task_assigned',
            'task_id' => $newTaskId,
            'task_title' => $title,
            'project_name' => $proj['project_name'],
            'category' => $category,
            'priority' => $priority,
            'due_date' => $dueDate,
            'worker_id' => $assignedTo,
            'worker_name' => $assignee['name'],
            'worker_phone' => $assignee['phone_wa'] ?? '',
            'worker_type' => $workerType,
            'assigned_by' => $user['name'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        echo json_encode([
            'success' => true,
            'message' => "Tugas berhasil dibagikan ke {$assignee['name']}!",
            'task_id' => $newTaskId
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal membagi tugas: ' . $e->getMessage()]);
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
            SELECT id, name, email, role, employment_type, phone_wa, avatar
            FROM users
            WHERE role IN ('karyawan', 'freelancer', 'admin', 'owner')
              AND (is_active IS NULL OR is_active = 1)
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
                   c.company as client_company, c.name as client_pic
            FROM projects p
            JOIN clients c ON p.client_id = c.id
            WHERE p.is_deleted = 0 AND c.is_deleted = 0
            ORDER BY c.company ASC, p.id DESC
        ");
        $projects = $stmt->fetchAll();

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
        $db->prepare("UPDATE tasks SET is_deleted = 1 WHERE id = ?")->execute([$taskId]);
        log_activity('task', "Tugas Dihapus", "Task ID {$taskId} dihapus oleh {$user['name']}");

        echo json_encode(['success' => true, 'message' => 'Tugas berhasil dihapus']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Aksi API tidak valid']);

<?php
/**
 * Kalamedia Agency - Ads Budget Tracking & Spend Coordination API Handler
 * 100% Dynamic Aggregations (Zero Hardcoding) & Multi-Role Strict Isolation
 * 
 * Roles:
 * 1. Owner & Admin  : Full visibility of contracts, agency margins, client pagu, transfers, spend, and requests.
 * 2. Client         : Strict transparency of their own ad budget, 2-stage transaction proofs, and top-up limit approvals.
 * 3. Tim Ads        : Scoped only to assigned projects; strictly hides contracts & agency margins.
 */

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_auth();
$user = current_user();
$sessionRole = strtolower($_SESSION['role'] ?? '');
$userRole = strtolower($user['role'] ?? '');
$role = $sessionRole ?: ($userRole ?: current_user_role());
$userId = (int)$user['id'];
$isOwnerUser = ($role === 'owner');
$isAdminUser = ($role === 'admin' || $role === 'owner');
$isWorkerUser = ($role === 'karyawan' || $role === 'freelancer');
$isClientUser = ($role === 'client');

release_session();

$db = Database::getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

function parse_ads_amount($val): float {
    if (is_int($val) || is_float($val)) {
        return floatval($val);
    }
    $str = trim(strval($val));
    if ($str === '') return 0.0;
    
    // Clean currency and special chars
    $clean = preg_replace('/[^0-9,.]/', '', $str);
    if ($clean === '') return 0.0;
    
    if (strpos($clean, '.') !== false && strpos($clean, ',') !== false) {
        // e.g. 1.000.000,50
        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);
    } elseif (strpos($clean, '.') !== false) {
        $parts = explode('.', $clean);
        if (count($parts) > 2) {
            // e.g. 1.500.000
            $clean = str_replace('.', '', $clean);
        } elseif (count($parts) === 2 && strlen($parts[1]) === 3) {
            // e.g. 100.000 or 500.000
            $clean = str_replace('.', '', $clean);
        }
    } elseif (strpos($clean, ',') !== false) {
        $parts = explode(',', $clean);
        if (count($parts) === 2 && strlen($parts[1]) === 3) {
            $clean = str_replace(',', '', $clean);
        } else {
            $clean = str_replace(',', '.', $clean);
        }
    }
    return floatval($clean);
}

function save_uploaded_receipt(array $file, string $prefix, int $projectId): ?string {
    $fileExt = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    if (!in_array($fileExt, $allowedExts)) {
        return null;
    }
    $uploadDir = BASE_PATH . '/assets/uploads/receipts/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }
    $newFileName = $prefix . '_' . $projectId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $fileExt;
    $destPath = $uploadDir . $newFileName;
    
    $tmp = $file['tmp_name'] ?? '';
    if (empty($tmp) || (!file_exists($tmp) && !is_uploaded_file($tmp))) {
        return null;
    }

    $saved = false;
    if (is_uploaded_file($tmp)) {
        $saved = move_uploaded_file($tmp, $destPath);
    }
    if (!$saved) {
        $saved = @copy($tmp, $destPath);
    }

    return $saved ? ('assets/uploads/receipts/' . $newFileName) : null;
}

// -------------------------------------------------------------
// 1. GET: List Projects & Ads Budget (100% Dynamic SQL Aggregation)
// -------------------------------------------------------------
if ($action === 'list') {
    try {
        $targetClientId = null;
        if ($isClientUser) {
            $targetClientId = current_user_client_id();
            if (!$targetClientId) {
                $stmtC = $db->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
                $stmtC->execute([$user['email']]);
                $targetClientId = (int)$stmtC->fetchColumn();
            }
        } elseif (!empty($_GET['client_id']) && ($isAdminUser || $isOwnerUser) && (($_GET['portal'] ?? '') === 'client' || ($_GET['_slot'] ?? '') === 'client')) {
            $targetClientId = (int)$_GET['client_id'];
        }

        // Base Query with 100% Dynamic Aggregations
        $sqlProjects = "
            SELECT 
                p.id as project_id,
                p.name as project_name,
                p.client_id,
                p.status as project_status,
                c.company as client_company,
                c.name as client_pic,
                c.package_name,
                COALESCE(NULLIF(p.contract_value, 0), p.total_contract_value, 0) as total_kontrak_klien,
                COALESCE(NULLIF(p.ads_budget_pool, 0), p.allocated_ads_budget, 0) as total_pagu_ads_klien,
                COALESCE((
                    SELECT SUM(COALESCE(NULLIF(transfer_amount_owner, 0), topup_amount, 0))
                    FROM ads_topup_transactions
                    WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0
                ), 0) as total_ditransfer_ke_tim_ads,
                COALESCE((
                    SELECT SUM(COALESCE(NULLIF(platform_topup_amount, 0), topup_amount, 0))
                    FROM ads_topup_transactions
                    WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0 AND (platform_topup_status IS NULL OR platform_topup_status = 'completed')
                ), 0) as total_topup_ke_platform,
                COALESCE((
                    SELECT SUM(COALESCE(NULLIF(ad_spend_amount, 0), spend_installed, 0))
                    FROM ads_topup_transactions
                    WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0
                ), 0) as total_spend_terpakai,
                (
                    COALESCE(NULLIF(p.ads_budget_pool, 0), p.allocated_ads_budget, 0) -
                    COALESCE((
                        SELECT SUM(COALESCE(NULLIF(transfer_amount_owner, 0), topup_amount, 0))
                        FROM ads_topup_transactions
                        WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0
                    ), 0)
                ) as sisa_pagu_di_owner,
                (
                    COALESCE((
                        SELECT SUM(COALESCE(NULLIF(transfer_amount_owner, 0), topup_amount, 0))
                        FROM ads_topup_transactions
                        WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0
                    ), 0) -
                    COALESCE((
                        SELECT SUM(COALESCE(NULLIF(ad_spend_amount, 0), spend_installed, 0))
                        FROM ads_topup_transactions
                        WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0
                    ), 0)
                ) as sisa_saldo_di_tim_ads,
                (
                    COALESCE(NULLIF(p.contract_value, 0), p.total_contract_value, 0) -
                    COALESCE(NULLIF(p.ads_budget_pool, 0), p.allocated_ads_budget, 0)
                ) as margin_bersih_agensi
            FROM projects p
            JOIN clients c ON p.client_id = c.id
            WHERE COALESCE(p.is_deleted, 0) = 0 AND COALESCE(c.is_deleted, 0) = 0
        ";

        // Query Transactions with 2-Stage Proofs
        $sqlTransactions = "
            SELECT 
                t.id, t.client_id, t.project_id, t.uploaded_by_user_id, t.platform,
                COALESCE(NULLIF(t.transfer_amount_owner, 0), t.topup_amount, 0) as transfer_amount_owner,
                COALESCE(t.transfer_proof_owner, t.receipt_file) as transfer_proof_owner,
                COALESCE(NULLIF(t.platform_topup_amount, 0), t.topup_amount, 0) as platform_topup_amount,
                COALESCE(t.platform_topup_proof, t.receipt_file) as platform_topup_proof,
                COALESCE(t.platform_topup_status, 'completed') as platform_topup_status,
                COALESCE(NULLIF(t.ad_spend_amount, 0), t.spend_installed, 0) as ad_spend_amount,
                COALESCE(t.campaign_status, 'running') as campaign_status,
                COALESCE(t.stage_label, t.notes, 'Tahap 1') as stage_label,
                t.topup_amount, t.spend_installed, t.transaction_date, t.receipt_file, t.notes, t.status, t.created_at,
                t.assigned_user_id,
                COALESCE(t.assigned_user_name, u_assigned.name, 'Tim Ads Kalamedia') as assigned_user_name,
                COALESCE(u_up.name, 'Admin') as uploader_name,
                p.name as project_name, c.company as client_company
            FROM ads_topup_transactions t
            LEFT JOIN users u_up ON t.uploaded_by_user_id = u_up.id
            LEFT JOIN users u_assigned ON t.assigned_user_id = u_assigned.id
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN clients c ON t.client_id = c.id
            WHERE COALESCE(t.is_deleted, 0) = 0
        ";

        // -----------------------------
        // CLIENT SCOPE
        // -----------------------------
        if ($targetClientId) {
            $stmt = $db->prepare($sqlProjects . " AND p.client_id = ? ORDER BY p.id DESC");
            $stmt->execute([$targetClientId]);
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmtTopup = $db->prepare($sqlTransactions . " AND t.client_id = ? ORDER BY t.transaction_date DESC, t.id DESC");
            $stmtTopup->execute([$targetClientId]);
            $topupTransactions = $stmtTopup->fetchAll(PDO::FETCH_ASSOC);

            // Fetch pending limit request from Owner to Client
            $stmtReq = $db->prepare("
                SELECT * FROM ads_balance_requests 
                WHERE client_id = ? AND request_type = 'owner_to_client' AND status = 'pending' 
                ORDER BY id DESC LIMIT 1
            ");
            $stmtReq->execute([$targetClientId]);
            $pendingLimitRequest = $stmtReq->fetch(PDO::FETCH_ASSOC);

            // Aggregate Client Totals
            $clientPaguTotal = 0;
            $clientTransferredTotal = 0;
            $clientTopupPlatformTotal = 0;
            $clientSpendTotal = 0;
            $clientSisaPaguTotal = 0;
            $clientSisaDiTimAdsTotal = 0;

            foreach ($projects as &$p) {
                // Ensure client never sees internal agency margin
                unset($p['margin_bersih_agensi']);
                unset($p['total_kontrak_klien']);

                $clientPaguTotal += floatval($p['total_pagu_ads_klien']);
                $clientTransferredTotal += floatval($p['total_ditransfer_ke_tim_ads']);
                $clientTopupPlatformTotal += floatval($p['total_topup_ke_platform']);
                $clientSpendTotal += floatval($p['total_spend_terpakai']);
                $clientSisaPaguTotal += floatval($p['sisa_pagu_di_owner']);
                $clientSisaDiTimAdsTotal += floatval($p['sisa_saldo_di_tim_ads']);
            }

            echo json_encode([
                'success' => true,
                'role' => 'client',
                'summary' => [
                    'total_pagu_ads_klien'        => $clientPaguTotal,
                    'total_ditransfer_ke_tim_ads' => $clientTransferredTotal,
                    'total_topup_ke_platform'     => $clientTopupPlatformTotal,
                    'total_spend_terpakai'        => $clientSpendTotal,
                    'sisa_pagu_di_owner'          => $clientSisaPaguTotal,
                    'sisa_saldo_di_tim_ads'       => $clientSisaDiTimAdsTotal,
                    'campaign_status'             => ($clientSisaDiTimAdsTotal > 0 || $clientTopupPlatformTotal > 0) ? 'running' : 'waiting'
                ],
                'projects' => $projects,
                'topup_transactions' => $topupTransactions,
                'pending_limit_request' => $pendingLimitRequest ?: null
            ]);
            exit;

        // -----------------------------
        // TIM ADS (WORKER) SCOPE
        // -----------------------------
        } elseif ($isWorkerUser) {
            // Strict Worker Access: Projects assigned to this user (tasks, employee_clients, ads_topup, or freelancer_payouts)
            $stmt = $db->prepare($sqlProjects . "
                AND (
                    p.id IN (SELECT DISTINCT project_id FROM tasks WHERE assigned_to_user_id = ? AND COALESCE(is_deleted,0)=0)
                    OR p.client_id IN (SELECT client_id FROM employee_clients WHERE user_id = ?)
                    OR p.id IN (SELECT DISTINCT project_id FROM ads_topup_transactions WHERE (assigned_user_id = ? OR uploaded_by_user_id = ?) AND COALESCE(is_deleted,0)=0)
                    OR p.id IN (SELECT DISTINCT project_id FROM freelancer_payouts WHERE (freelancer_name = ? OR freelancer_name LIKE ?) AND COALESCE(is_deleted,0)=0)
                )
                ORDER BY c.company ASC, p.id DESC
            ");
            $stmt->execute([$userId, $userId, $userId, $userId, $user['name'], '%' . $user['name'] . '%']);
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmtTopup = $db->prepare($sqlTransactions . "
                AND (
                    t.assigned_user_id = ? OR t.uploaded_by_user_id = ?
                    OR t.project_id IN (SELECT DISTINCT project_id FROM tasks WHERE assigned_to_user_id = ? AND COALESCE(is_deleted,0)=0)
                    OR t.client_id IN (SELECT client_id FROM employee_clients WHERE user_id = ?)
                    OR t.project_id IN (SELECT DISTINCT project_id FROM freelancer_payouts WHERE (freelancer_name = ? OR freelancer_name LIKE ?) AND COALESCE(is_deleted,0)=0)
                )
                ORDER BY t.transaction_date DESC, t.id DESC
            ");
            $stmtTopup->execute([$userId, $userId, $userId, $userId, $user['name'], '%' . $user['name'] . '%']);
            $topupTransactions = $stmtTopup->fetchAll(PDO::FETCH_ASSOC);

            // Check pending balance requests from this worker to Owner
            $stmtWorkerReq = $db->prepare("
                SELECT project_id, status FROM ads_balance_requests 
                WHERE requester_user_id = ? AND request_type = 'worker_to_owner' AND status = 'pending'
            ");
            $stmtWorkerReq->execute([$userId]);
            $pendingProjIds = $stmtWorkerReq->fetchAll(PDO::FETCH_COLUMN);

            $workerTransferredTotal = 0;
            $workerTopupPlatformTotal = 0;
            $workerSpendTotal = 0;

            foreach ($topupTransactions as $t) {
                $workerTransferredTotal += floatval($t['transfer_amount_owner'] ?? 0);
                $workerTopupPlatformTotal += floatval($t['platform_topup_amount'] ?? 0);
                $workerSpendTotal += floatval($t['ad_spend_amount'] ?? 0);
            }
            $workerSisaTotal = $workerTransferredTotal - $workerSpendTotal;

            foreach ($projects as &$p) {
                // STRICT WORKER PROTECTION: Remove contracts, agency margins, and client pagu in Owner's hand
                unset($p['total_kontrak_klien']);
                unset($p['contract_value']);
                unset($p['margin_bersih_agensi']);
                unset($p['total_pagu_ads_klien']);
                unset($p['allocated_ads_budget']);
                unset($p['sisa_pagu_di_owner']);
                unset($p['remaining_ads_budget']);
                unset($p['agency_retained_balance']);

                $p['has_pending_request'] = in_array($p['project_id'], $pendingProjIds);
            }

            echo json_encode([
                'success' => true,
                'role' => $role,
                'summary' => [
                    'total_transferred_from_owner' => $workerTransferredTotal,
                    'total_topup_ke_platform'      => $workerTopupPlatformTotal,
                    'total_spend_terpakai'         => $workerSpendTotal,
                    'sisa_saldo_di_tim_ads'        => $workerSisaTotal,
                    'total_ditransfer_owner'       => $workerTransferredTotal,
                    'total_dibelanjakan_ads'       => $workerSpendTotal,
                    'sisa_saldo'                   => $workerSisaTotal,
                    'status_iklan'                 => ($workerSisaTotal > 0 ? 'Iklan Jalan' : ($workerTransferredTotal > 0 ? 'Saldo Habis' : 'Belum Ada Saldo')),
                    'campaign_status'              => ($workerSisaTotal > 0) ? 'running' : 'empty'
                ],
                'projects' => $projects,
                'topup_transactions' => $topupTransactions
            ]);
            exit;

        // -----------------------------
        // OWNER & ADMIN SCOPE
        // -----------------------------
        } else {
            $stmt = $db->query($sqlProjects . " ORDER BY c.company ASC, p.id DESC");
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmtTopup = $db->query($sqlTransactions . " ORDER BY t.transaction_date DESC, t.id DESC");
            $topupTransactions = $stmtTopup->fetchAll(PDO::FETCH_ASSOC);

            // Fetch pending balance requests (both worker_to_owner and owner_to_client)
            $stmtRequests = $db->query("
                SELECT r.*, p.name as project_name, c.company as client_company
                FROM ads_balance_requests r
                LEFT JOIN projects p ON r.project_id = p.id
                LEFT JOIN clients c ON r.client_id = c.id
                WHERE r.status = 'pending'
                ORDER BY r.id DESC
            ");
            $pendingRequests = $stmtRequests->fetchAll(PDO::FETCH_ASSOC);

            // Summary Totals for Owner
            $totalContract = 0;
            $totalPaguAds = 0;
            $totalTransferred = 0;
            $totalTopupPlatform = 0;
            $totalSpend = 0;
            $totalSisaPaguOwner = 0;
            $totalSisaTimAds = 0;
            $totalMargin = 0;

            foreach ($projects as &$pr) {
                // Map backward-compatible aliases
                $pr['allocated_ads_budget']    = $pr['total_pagu_ads_klien'];
                $pr['remaining_ads_budget']    = $pr['sisa_pagu_di_owner'];
                $pr['total_spend_installed']   = $pr['total_topup_ke_platform'];
                $pr['actual_spend']            = $pr['total_spend_terpakai'];
                $pr['worker_remaining']        = $pr['sisa_saldo_di_tim_ads'];
                $pr['agency_retained_balance'] = $pr['margin_bersih_agensi'];

                $totalContract       += floatval($pr['total_kontrak_klien']);
                $totalPaguAds        += floatval($pr['total_pagu_ads_klien']);
                $totalTransferred    += floatval($pr['total_ditransfer_ke_tim_ads']);
                $totalTopupPlatform  += floatval($pr['total_topup_ke_platform']);
                $totalSpend          += floatval($pr['total_spend_terpakai']);
                $totalSisaPaguOwner  += floatval($pr['sisa_pagu_di_owner']);
                $totalSisaTimAds     += floatval($pr['sisa_saldo_di_tim_ads']);
                $totalMargin         += floatval($pr['margin_bersih_agensi']);
            }

            echo json_encode([
                'success' => true,
                'role' => $role,
                'summary' => [
                    'total_contract_value'        => $totalContract,
                    'total_allocated_ads'         => $totalPaguAds,
                    'total_transferred_to_ads'    => $totalTransferred,
                    'total_spend_installed'       => $totalTopupPlatform,
                    'total_actual_spend'          => $totalSpend,
                    'total_remaining_ads'         => $totalSisaPaguOwner,
                    'total_worker_remaining'      => $totalSisaTimAds,
                    'total_agency_retained'       => $totalMargin
                ],
                'projects' => $projects,
                'topup_transactions' => $topupTransactions,
                'pending_requests' => $pendingRequests
            ]);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal memuat data pelacakan anggaran iklan: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 2. POST: Transfer Saldo dari Owner ke Tim Ads (Owner / Admin)
// -------------------------------------------------------------
if ($action === 'transfer_to_worker') {
    if (!$isAdminUser && !$isOwnerUser) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya Owner dan Admin yang dapat mencairkan saldo ads ke Tim Ads.']);
        exit;
    }

    $projectId = intval($_POST['project_id'] ?? 0);
    $assignedUserId = intval($_POST['assigned_user_id'] ?? 0);
    $platform = trim($_POST['platform'] ?? 'Meta Ads');
    $transferAmount = parse_ads_amount($_POST['transfer_amount'] ?? 0);
    $stageLabel = trim($_POST['stage_label'] ?? 'Tahap 1');
    $notes = trim($_POST['notes'] ?? '');

    if ($projectId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Pilih proyek klien terlebih dahulu.']);
        exit;
    }

    if ($transferAmount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Nominal transfer saldo harus lebih dari Rp 0.']);
        exit;
    }

    // Proof file is mandatory
    if (empty($_FILES['transfer_proof_file']['name']) || $_FILES['transfer_proof_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Bukti transfer dari Owner ke Tim Ads wajib diunggah!']);
        exit;
    }

    $file = $_FILES['transfer_proof_file'];
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($fileExt, $allowedExts)) {
        echo json_encode(['success' => false, 'message' => 'Format file tidak didukung. Harap upload format JPG, PNG, WEBP, atau PDF.']);
        exit;
    }

    try {
        // Check project & sisa pagu di Owner
        $stmtP = $db->prepare("
            SELECT p.id, p.client_id, p.name as project_name, c.company as client_company,
                   COALESCE(NULLIF(p.ads_budget_pool, 0), p.allocated_ads_budget, 0) as total_pagu,
                   COALESCE((
                       SELECT SUM(COALESCE(NULLIF(transfer_amount_owner, 0), topup_amount, 0))
                       FROM ads_topup_transactions
                       WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0
                   ), 0) as total_sudah_transfer
            FROM projects p 
            JOIN clients c ON p.client_id = c.id 
            WHERE p.id = ?
        ");
        $stmtP->execute([$projectId]);
        $proj = $stmtP->fetch();

        if (!$proj) {
            echo json_encode(['success' => false, 'message' => 'Proyek klien tidak ditemukan.']);
            exit;
        }

        $sisaPagu = floatval($proj['total_pagu']) - floatval($proj['total_sudah_transfer']);
        if ($transferAmount > $sisaPagu) {
            echo json_encode([
                'success' => false, 
                'message' => 'Nominal transfer (' . format_rupiah($transferAmount) . ') melebihi sisa pagu iklan yang tersedia di tangan Owner (' . format_rupiah($sisaPagu) . '). Silakan ajukan penambahan limit ke klien terlebih dahulu.'
            ]);
            exit;
        }

        // Get assigned worker name
        $assignedUserName = 'Tim Ads Kalamedia';
        if ($assignedUserId > 0) {
            $stmtUser = $db->prepare("SELECT name FROM users WHERE id = ?");
            $stmtUser->execute([$assignedUserId]);
            $uName = $stmtUser->fetchColumn();
            if ($uName) $assignedUserName = $uName;
        }

        $receiptRelPath = save_uploaded_receipt($file, 'tf_owner', $projectId);
        if (!$receiptRelPath) {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file bukti ke server.']);
            exit;
        }

        // Insert into ads_topup_transactions
        $stmtIns = $db->prepare("
            INSERT INTO ads_topup_transactions (
                client_id, project_id, uploaded_by_user_id, assigned_user_id, assigned_user_name,
                platform, topup_amount, transfer_amount_owner, transfer_proof_owner,
                platform_topup_amount, platform_topup_proof, platform_topup_status,
                spend_installed, ad_spend_amount, transaction_date, receipt_file, notes,
                stage_label, campaign_status, status, is_deleted, created_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                0, NULL, 'pending',
                0, 0, ?, ?, ?,
                ?, 'waiting', 'recorded', 0, CURRENT_TIMESTAMP
            )
        ");
        $stmtIns->execute([
            (int)$proj['client_id'],
            $projectId,
            $userId,
            $assignedUserId ?: null,
            $assignedUserName,
            $platform,
            $transferAmount,
            $transferAmount,
            $receiptRelPath,
            date('Y-m-d'),
            $receiptRelPath,
            $notes,
            $stageLabel
        ]);

        // Auto-approve pending balance request from worker if exists
        $db->prepare("
            UPDATE ads_balance_requests 
            SET status = 'approved', resolved_at = CURRENT_TIMESTAMP 
            WHERE project_id = ? AND request_type = 'worker_to_owner' AND status = 'pending'
        ")->execute([$projectId]);

        log_activity(
            'ads_tracking',
            "Transfer Saldo Ads: {$proj['client_company']}",
            "Owner mentransfer " . format_rupiah($transferAmount) . " ke {$assignedUserName} ({$platform} - {$stageLabel})",
            $transferAmount
        );

        echo json_encode([
            'success' => true,
            'message' => 'Pencairan saldo ads sebesar ' . format_rupiah($transferAmount) . ' berhasil ditransfer ke ' . $assignedUserName . '!'
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal memproses transfer saldo: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 3. POST: Konfirmasi Top-Up ke Meta/Google & Update Spend (Tim Ads)
// -------------------------------------------------------------
if ($action === 'confirm_platform_topup') {
    if (!$isWorkerUser && !$isAdminUser && !$isOwnerUser) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
        exit;
    }

    $transId = intval($_POST['transaction_id'] ?? 0);
    $platformTopupAmount = parse_ads_amount($_POST['platform_topup_amount'] ?? 0);
    $adSpendAmount = parse_ads_amount($_POST['ad_spend_amount'] ?? 0);
    $campaignStatus = trim($_POST['campaign_status'] ?? 'running');
    $notes = trim($_POST['notes'] ?? '');

    if ($transId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID Transaksi tidak valid.']);
        exit;
    }

    if ($platformTopupAmount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Nominal top-up ke platform iklan wajib diisi!']);
        exit;
    }

    try {
        $stmtT = $db->prepare("SELECT * FROM ads_topup_transactions WHERE id = ? AND is_deleted = 0");
        $stmtT->execute([$transId]);
        $trans = $stmtT->fetch();

        if (!$trans) {
            echo json_encode(['success' => false, 'message' => 'Transaksi saldo tidak ditemukan.']);
            exit;
        }

        // Otomatis batasi maksimal sesuai nominal transfer yang telah diinput sebelumnya oleh Owner
        $maxOwnerTransfer = floatval($trans['transfer_amount_owner'] > 0 ? $trans['transfer_amount_owner'] : $trans['topup_amount']);
        if ($maxOwnerTransfer > 0) {
            if ($platformTopupAmount > $maxOwnerTransfer) {
                $platformTopupAmount = $maxOwnerTransfer;
            }
            if ($adSpendAmount > $maxOwnerTransfer) {
                $adSpendAmount = $maxOwnerTransfer;
            }
        }

        $proofPath = $trans['platform_topup_proof'];
        if (!empty($_FILES['platform_receipt_file']['name']) && $_FILES['platform_receipt_file']['error'] === UPLOAD_ERR_OK) {
            $saved = save_uploaded_receipt($_FILES['platform_receipt_file'], 'topup_meta', (int)$trans['project_id']);
            if ($saved) {
                $proofPath = $saved;
            }
        }

        if (empty($proofPath)) {
            echo json_encode(['success' => false, 'message' => 'Bukti top-up / struk billing Meta/Google Ads wajib diunggah!']);
            exit;
        }

        $stmtUp = $db->prepare("
            UPDATE ads_topup_transactions
            SET platform_topup_amount = ?,
                platform_topup_proof = ?,
                platform_topup_status = 'completed',
                spend_installed = ?,
                ad_spend_amount = ?,
                campaign_status = ?,
                notes = CASE WHEN ? != '' THEN ? ELSE notes END,
                status = 'verified'
            WHERE id = ?
        ");
        $stmtUp->execute([
            $platformTopupAmount,
            $proofPath,
            $adSpendAmount,
            $adSpendAmount,
            $campaignStatus,
            $notes, $notes,
            $transId
        ]);

        log_activity(
            'ads_tracking',
            "Top-Up Platform Dikonfirmasi: {$trans['platform']}",
            "Tim Ads mengonfirmasi top-up " . format_rupiah($platformTopupAmount) . " dan spend " . format_rupiah($adSpendAmount),
            $platformTopupAmount
        );

        echo json_encode([
            'success' => true,
            'message' => 'Konfirmasi top-up ke ' . $trans['platform'] . ' berhasil disimpan dan tersinkron ke Portal Klien!'
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal mengonfirmasi top-up: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 4. POST: Kirim Notifikasi Permintaan Saldo (Berjenjang 3 Pihak)
// -------------------------------------------------------------
if ($action === 'request_balance') {
    $projectId = intval($_POST['project_id'] ?? 0);
    $requestType = trim($_POST['request_type'] ?? '');
    $amountRequested = parse_ads_amount($_POST['amount_requested'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($projectId <= 0 || !in_array($requestType, ['worker_to_owner', 'owner_to_client'])) {
        echo json_encode(['success' => false, 'message' => 'Permintaan tidak valid.']);
        exit;
    }

    try {
        $stmtP = $db->prepare("SELECT p.id, p.client_id, p.name as project_name, c.company FROM projects p JOIN clients c ON p.client_id = c.id WHERE p.id = ?");
        $stmtP->execute([$projectId]);
        $proj = $stmtP->fetch();

        if (!$proj) {
            echo json_encode(['success' => false, 'message' => 'Proyek tidak ditemukan.']);
            exit;
        }

        $db->prepare("
            INSERT INTO ads_balance_requests (
                project_id, client_id, request_type, requester_user_id, requester_name,
                amount_requested, notes, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP)
        ")->execute([
            $projectId,
            (int)$proj['client_id'],
            $requestType,
            $userId,
            $user['name'],
            $amountRequested,
            $notes
        ]);

        $msg = ($requestType === 'worker_to_owner')
            ? 'Laporan saldo habis & permintaan saldo tahap berikutnya berhasil dikirim ke Owner!'
            : 'Pengajuan penambahan limit saldo ads berhasil dikirimkan ke Portal Klien!';

        echo json_encode(['success' => true, 'message' => $msg]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal memproses permintaan: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 5. POST: Klien Setujui Tambah Limit Pagu & Upload Bukti Bayar
// -------------------------------------------------------------
if ($action === 'approve_client_limit') {
    $projectId = intval($_POST['project_id'] ?? 0);
    $requestId = intval($_POST['request_id'] ?? 0);
    $amountAdded = parse_ads_amount($_POST['amount_added'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($projectId <= 0 && $requestId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Proyek atau permintaan tidak valid.']);
        exit;
    }

    if ($amountAdded <= 0) {
        echo json_encode(['success' => false, 'message' => 'Nominal penambahan limit pagu iklan harus lebih dari Rp 0.']);
        exit;
    }

    if (empty($_FILES['client_proof_file']['name']) || $_FILES['client_proof_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Bukti transfer penambahan saldo wajib diunggah!']);
        exit;
    }

    $file = $_FILES['client_proof_file'];
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($fileExt, $allowedExts)) {
        echo json_encode(['success' => false, 'message' => 'Format file tidak didukung.']);
        exit;
    }

    try {
        $proofRelPath = save_uploaded_receipt($file, 'client_topup', $projectId);
        if (!$proofRelPath) {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file bukti ke server.']);
            exit;
        }

        // Increase ads_budget_pool and allocated_ads_budget on project
        $db->prepare("
            UPDATE projects 
            SET ads_budget_pool = COALESCE(ads_budget_pool, 0) + ?,
                allocated_ads_budget = COALESCE(allocated_ads_budget, 0) + ?
            WHERE id = ?
        ")->execute([$amountAdded, $amountAdded, $projectId]);

        // Resolve request
        if ($requestId > 0) {
            $db->prepare("
                UPDATE ads_balance_requests 
                SET status = 'approved', proof_file = ?, resolved_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ")->execute([$proofRelPath, $requestId]);
        } else {
            $db->prepare("
                UPDATE ads_balance_requests 
                SET status = 'approved', proof_file = ?, resolved_at = CURRENT_TIMESTAMP 
                WHERE project_id = ? AND request_type = 'owner_to_client' AND status = 'pending'
            ")->execute([$proofRelPath, $projectId]);
        }

        log_activity(
            'ads_tracking',
            "Top-Up Limit Pagu oleh Klien",
            "Klien menyetujui penambahan limit pagu iklan sebesar " . format_rupiah($amountAdded),
            $amountAdded
        );

        echo json_encode([
            'success' => true,
            'message' => 'Terima kasih! Tambah limit saldo ads sebesar ' . format_rupiah($amountAdded) . ' berhasil disetujui dan pagu saldo iklan Anda otomatis bertambah!'
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal memproses persetujuan tambah limit: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 6. POST: Quick Unified Upload Top-Up
// -------------------------------------------------------------
if ($action === 'upload_topup') {
    if (!$isWorkerUser && !$isAdminUser && !$isOwnerUser) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
        exit;
    }

    $projectId = intval($_POST['project_id'] ?? 0);
    $platform = trim($_POST['platform'] ?? 'Meta Ads');
    $topupAmount = parse_ads_amount($_POST['topup_amount'] ?? 0);
    $spendInstalled = parse_ads_amount($_POST['spend_installed'] ?? 0);
    $transactionDate = trim($_POST['transaction_date'] ?? date('Y-m-d'));
    $notes = trim($_POST['notes'] ?? '');

    if ($projectId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Pilih proyek & klien terlebih dahulu.']);
        exit;
    }

    if ($topupAmount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Nominal isi saldo harus lebih dari Rp 0.']);
        exit;
    }

    if (empty($_FILES['receipt_file']['name']) || $_FILES['receipt_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Bukti transaksi wajib diunggah!']);
        exit;
    }

    $file = $_FILES['receipt_file'];
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($fileExt, $allowedExts)) {
        echo json_encode(['success' => false, 'message' => 'Format file tidak didukung.']);
        exit;
    }

    try {
        $stmtP = $db->prepare("SELECT p.id, p.client_id, p.name as project_name, c.company as client_company FROM projects p JOIN clients c ON p.client_id = c.id WHERE p.id = ?");
        $stmtP->execute([$projectId]);
        $proj = $stmtP->fetch();

        if (!$proj) {
            echo json_encode(['success' => false, 'message' => 'Proyek klien tidak ditemukan.']);
            exit;
        }

        $receiptRelPath = save_uploaded_receipt($file, 'ads_receipt', $projectId);
        if (!$receiptRelPath) {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file ke direktori server.']);
            exit;
        }

        $stmtIns = $db->prepare("
            INSERT INTO ads_topup_transactions (
                client_id, project_id, uploaded_by_user_id, assigned_user_id, assigned_user_name,
                platform, topup_amount, transfer_amount_owner, transfer_proof_owner,
                platform_topup_amount, platform_topup_proof, platform_topup_status,
                spend_installed, ad_spend_amount, transaction_date, receipt_file,
                notes, stage_label, campaign_status, status, is_deleted, created_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, 'completed',
                ?, ?, ?, ?,
                ?, ?, 'running', 'verified', 0, CURRENT_TIMESTAMP
            )
        ");
        $stmtIns->execute([
            (int)$proj['client_id'],
            $projectId,
            $userId,
            $userId,
            $user['name'],
            $platform,
            $topupAmount,
            $topupAmount,
            $receiptRelPath,
            $topupAmount,
            $receiptRelPath,
            $spendInstalled,
            $spendInstalled,
            $transactionDate,
            $receiptRelPath,
            $notes,
            $notes ?: 'Tahap Berjalan'
        ]);

        log_activity(
            'ads_tracking',
            "Top-Up Saldo Ads: {$proj['client_company']}",
            "Isi saldo " . format_rupiah($topupAmount) . " ({$platform}) diunggah oleh " . $user['name'],
            $topupAmount
        );

        echo json_encode(['success' => true, 'message' => 'Bukti transaksi pengisian saldo berhasil disimpan & disinkronkan ke Portal Klien!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan bukti: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 7. POST: Atur Pagu Saldo Ads ke Proyek (Owner / Admin)
// -------------------------------------------------------------
if ($action === 'allocate_budget') {
    if (!$isAdminUser && !$isOwnerUser) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
        exit;
    }

    $projectId = intval($_POST['project_id'] ?? 0);
    $allocatedBudget = parse_ads_amount($_POST['allocated_budget'] ?? 0);

    if ($projectId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Proyek tidak valid.']);
        exit;
    }

    try {
        $stmtP = $db->prepare("SELECT p.id, p.name, p.contract_value, p.client_id, c.company FROM projects p JOIN clients c ON p.client_id = c.id WHERE p.id = ?");
        $stmtP->execute([$projectId]);
        $proj = $stmtP->fetch();

        if (!$proj) {
            echo json_encode(['success' => false, 'message' => 'Proyek tidak ditemukan.']);
            exit;
        }

        // Keep both columns in sync
        $db->prepare("UPDATE projects SET ads_budget_pool = ?, allocated_ads_budget = ? WHERE id = ?")
           ->execute([$allocatedBudget, $allocatedBudget, $projectId]);

        log_activity(
            'ads_tracking',
            "Pagu Saldo Ads Diatur: {$proj['company']}",
            "Pagu Saldo Ads ditetapkan sebesar " . format_rupiah($allocatedBudget) . " oleh " . $user['name'],
            $allocatedBudget
        );

        echo json_encode([
            'success' => true,
            'message' => 'Pagu alokasi saldo iklan untuk ' . $proj['company'] . ' berhasil disimpan!',
            'allocated_budget' => $allocatedBudget
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan alokasi: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 8. POST: Verifikasi & Hapus Transaksi (Owner / Admin)
// -------------------------------------------------------------
if ($action === 'verify_topup') {
    if (!$isAdminUser && !$isOwnerUser) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
        exit;
    }

    $transId = intval($_POST['transaction_id'] ?? 0);
    if ($transId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID Transaksi tidak valid.']);
        exit;
    }

    try {
        $db->prepare("UPDATE ads_topup_transactions SET status = 'verified' WHERE id = ?")->execute([$transId]);
        echo json_encode(['success' => true, 'message' => 'Transaksi isi saldo Ads berhasil diverifikasi!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

if ($action === 'delete_topup') {
    if (!$isAdminUser && !$isOwnerUser) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
        exit;
    }

    $transId = intval($_POST['transaction_id'] ?? 0);
    if ($transId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID Transaksi tidak valid.']);
        exit;
    }

    try {
        $db->prepare("UPDATE ads_topup_transactions SET is_deleted = 1 WHERE id = ?")->execute([$transId]);
        echo json_encode(['success' => true, 'message' => 'Transaksi isi saldo Ads berhasil dihapus.']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Aksi API tidak dikenali.']);

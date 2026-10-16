<?php
/**
 * Kalamedia Agency - Ads Budget Tracking & Spend Coordination API Handler
 * Endpoints:
 * - GET  ?action=list                  : Lists projects & ads budgets (Strictly role-scoped & privacy protected)
 * - POST ?action=allocate_budget       : (Owner/Admin) Allocate ads balance to project
 * - POST ?action=log_spend             : (Tim Ads / Workers / Admin) Log installed spend, proof screenshot & notes
 * - POST ?action=verify_log            : (Owner) Verify/Approve ads spend log for client transparency
 * - POST ?action=delete_log            : (Owner/Admin) Soft delete ads budget log
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_auth();
$user = current_user();
$role = current_user_role();
$userId = (int)$user['id'];
$isOwnerUser = is_owner();
$isAdminUser = is_admin();
$isWorkerUser = ($role === 'karyawan' || $role === 'freelancer');
$isClientUser = is_client();

release_session();

$db = Database::getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

function parse_ads_amount($val): float {
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

// -------------------------------------------------------------
// 1. GET: List Projects & Ads Budget Logs (Strict RBAC & Privacy)
// -------------------------------------------------------------
if ($action === 'list') {
    try {
        $targetClientId = null;
        if ($isClientUser) {
            // STRICT CLIENT SCOPE: Only see verified ads campaign status for their own company
            $targetClientId = current_user_client_id();
            if (!$targetClientId) {
                $stmtC = $db->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
                $stmtC->execute([$user['email']]);
                $targetClientId = (int)$stmtC->fetchColumn();
            }
        } elseif (!empty($_GET['client_id']) && ($isAdminUser || $isOwnerUser) && (($_GET['portal'] ?? '') === 'client' || ($_GET['_slot'] ?? '') === 'client')) {
            $targetClientId = (int)$_GET['client_id'];
        }

        if ($targetClientId) {
            $clientId = $targetClientId;

            $stmt = $db->prepare("
                SELECT p.id as project_id, p.name as project_name, p.status as project_status,
                       c.company as client_company, c.package_name,
                       p.allocated_ads_budget,
                       COALESCE((SELECT SUM(spend_installed) FROM ads_budget_logs WHERE project_id = p.id AND COALESCE(is_deleted,0) = 0), 0) as total_spend_installed
                FROM projects p
                JOIN clients c ON p.client_id = c.id
                WHERE p.client_id = ? AND COALESCE(p.is_deleted, 0) = 0
                ORDER BY p.id DESC
            ");
            $stmt->execute([$clientId]);
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch public/verified logs for this client
            $stmtLogs = $db->prepare("
                SELECT l.id, l.project_id, l.platform, l.spend_installed, l.status, l.notes,
                       l.attachment_proof, l.created_at, l.updated_at
                FROM ads_budget_logs l
                WHERE l.client_id = ? AND COALESCE(l.is_deleted, 0) = 0
                ORDER BY l.id DESC
            ");
            $stmtLogs->execute([$clientId]);
            $allLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

            // Group logs under each project
            $logsByProject = [];
            foreach ($allLogs as $log) {
                $logsByProject[$log['project_id']][] = $log;
            }

            foreach ($projects as &$p) {
                $p['logs'] = $logsByProject[$p['project_id']] ?? [];
            }

            // Fetch Top-Up Transactions for this client
            $stmtTopup = $db->prepare("
                SELECT t.id, t.client_id, t.project_id, t.platform, t.topup_amount, t.spend_installed,
                       t.transaction_date, t.receipt_file, t.notes, t.status, t.created_at,
                       u.name as uploader_name, p.name as project_name
                FROM ads_topup_transactions t
                LEFT JOIN users u ON t.uploaded_by_user_id = u.id
                LEFT JOIN projects p ON t.project_id = p.id
                WHERE t.client_id = ? AND COALESCE(t.is_deleted, 0) = 0
                ORDER BY t.transaction_date DESC, t.id DESC
            ");
            $stmtTopup->execute([$clientId]);
            $topupTransactions = $stmtTopup->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'role' => 'client',
                'projects' => $projects,
                'topup_transactions' => $topupTransactions
            ]);
            exit;

        } elseif ($isWorkerUser) {
            // STRICT WORKER PROTECTION:
            // Karyawan & Freelancer TIDAK BOLEH MELIHAT contract_value, invoice_amount, margin, profit, dll.
            // Hanya melihat: Proyek, Client Company, Pagu Saldo Ads yang dialokasikan, Anggaran Terpasang, Sisa Saldo, Status Kampanye.
            $stmt = $db->query("
                SELECT p.id as project_id, p.name as project_name, p.client_id,
                       c.company as client_company, c.name as client_pic,
                       COALESCE(p.allocated_ads_budget, 0) as allocated_ads_budget,
                       COALESCE((SELECT SUM(spend_installed) FROM ads_budget_logs WHERE project_id = p.id AND COALESCE(is_deleted,0) = 0), 0) as total_spend_installed,
                       (COALESCE(p.allocated_ads_budget, 0) - COALESCE((SELECT SUM(spend_installed) FROM ads_budget_logs WHERE project_id = p.id AND COALESCE(is_deleted,0) = 0), 0)) as remaining_ads_budget
                FROM projects p
                JOIN clients c ON p.client_id = c.id
                WHERE COALESCE(p.is_deleted, 0) = 0 AND COALESCE(c.is_deleted, 0) = 0
                ORDER BY c.company ASC, p.id DESC
            ");
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch ads logs without any financial margin data
            $stmtLogs = $db->query("
                SELECT l.id, l.project_id, l.client_id, l.platform, l.allocated_budget,
                       l.spend_installed, l.remaining_budget, l.status, l.notes,
                       l.attachment_proof, l.updated_by_name, l.is_verified_by_owner,
                       l.created_at, l.updated_at
                FROM ads_budget_logs l
                WHERE COALESCE(l.is_deleted, 0) = 0
                ORDER BY l.id DESC
            ");
            $allLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

            $logsByProject = [];
            foreach ($allLogs as $log) {
                $logsByProject[$log['project_id']][] = $log;
            }

            foreach ($projects as &$p) {
                // Ensure NO contract_value or margin exists
                unset($p['contract_value']);
                unset($p['target_margin_percent']);
                $p['logs'] = $logsByProject[$p['project_id']] ?? [];
            }

            // Fetch Top-Up Transactions
            $stmtTopup = $db->query("
                SELECT t.id, t.client_id, t.project_id, t.platform, t.topup_amount, t.spend_installed,
                       t.transaction_date, t.receipt_file, t.notes, t.status, t.created_at,
                       u.name as uploader_name, p.name as project_name, c.company as client_company
                FROM ads_topup_transactions t
                LEFT JOIN users u ON t.uploaded_by_user_id = u.id
                LEFT JOIN projects p ON t.project_id = p.id
                LEFT JOIN clients c ON t.client_id = c.id
                WHERE COALESCE(t.is_deleted, 0) = 0
                ORDER BY t.transaction_date DESC, t.id DESC
            ");
            $topupTransactions = $stmtTopup->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'role' => $role,
                'projects' => $projects,
                'topup_transactions' => $topupTransactions
            ]);
            exit;

        } else {
            // OWNER & ADMIN: Full Visibility (Client Contract vs Pagu Ads vs Anggaran Terpasang vs Margin Bersih)
            $stmt = $db->query("
                SELECT p.id as project_id, p.name as project_name, p.client_id,
                       p.contract_value, p.target_margin_percent, p.status as project_status,
                       c.company as client_company, c.name as client_pic, c.package_name,
                       COALESCE(p.allocated_ads_budget, 0) as allocated_ads_budget,
                       COALESCE((SELECT SUM(spend_installed) FROM ads_budget_logs WHERE project_id = p.id AND COALESCE(is_deleted,0) = 0), 0) as total_spend_installed,
                       (COALESCE(p.allocated_ads_budget, 0) - COALESCE((SELECT SUM(spend_installed) FROM ads_budget_logs WHERE project_id = p.id AND COALESCE(is_deleted,0) = 0), 0)) as remaining_ads_budget,
                       (p.contract_value - COALESCE(p.allocated_ads_budget, 0)) as agency_retained_balance,
                       (p.contract_value - COALESCE((SELECT SUM(spend_installed) FROM ads_budget_logs WHERE project_id = p.id AND COALESCE(is_deleted,0) = 0), 0)) as live_gross_margin
                FROM projects p
                JOIN clients c ON p.client_id = c.id
                WHERE COALESCE(p.is_deleted, 0) = 0 AND COALESCE(c.is_deleted, 0) = 0
                ORDER BY c.company ASC, p.id DESC
            ");
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch full logs
            $stmtLogs = $db->query("
                SELECT l.id, l.project_id, l.client_id, l.platform, l.allocated_budget,
                       l.spend_installed, l.remaining_budget, l.status, l.notes,
                       l.attachment_proof, l.updated_by_name, l.is_verified_by_owner,
                       l.created_at, l.updated_at
                FROM ads_budget_logs l
                WHERE COALESCE(l.is_deleted, 0) = 0
                ORDER BY l.id DESC
            ");
            $allLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

            $logsByProject = [];
            foreach ($allLogs as $log) {
                $logsByProject[$log['project_id']][] = $log;
            }

            foreach ($projects as &$p) {
                $p['logs'] = $logsByProject[$p['project_id']] ?? [];
            }

            // Summary Totals for Owner
            $totalContract = 0;
            $totalAllocatedAds = 0;
            $totalSpendInstalled = 0;
            foreach ($projects as $pr) {
                $totalContract += floatval($pr['contract_value']);
                $totalAllocatedAds += floatval($pr['allocated_ads_budget']);
                $totalSpendInstalled += floatval($pr['total_spend_installed']);
            }
            $totalRemainingAds = $totalAllocatedAds - $totalSpendInstalled;
            $totalRetained = $totalContract - $totalAllocatedAds;

            // Fetch Top-Up Transactions for Owner/Admin
            $stmtTopup = $db->query("
                SELECT t.id, t.client_id, t.project_id, t.platform, t.topup_amount, t.spend_installed,
                       t.transaction_date, t.receipt_file, t.notes, t.status, t.created_at,
                       u.name as uploader_name, p.name as project_name, c.company as client_company
                FROM ads_topup_transactions t
                LEFT JOIN users u ON t.uploaded_by_user_id = u.id
                LEFT JOIN projects p ON t.project_id = p.id
                LEFT JOIN clients c ON t.client_id = c.id
                WHERE COALESCE(t.is_deleted, 0) = 0
                ORDER BY t.transaction_date DESC, t.id DESC
            ");
            $topupTransactions = $stmtTopup->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'role' => $role,
                'summary' => [
                    'total_contract_value' => $totalContract,
                    'total_allocated_ads' => $totalAllocatedAds,
                    'total_spend_installed' => $totalSpendInstalled,
                    'total_remaining_ads' => $totalRemainingAds,
                    'total_agency_retained' => $totalRetained
                ],
                'projects' => $projects,
                'topup_transactions' => $topupTransactions
            ]);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal memuat data pelacakan anggaran iklan: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 2. POST: Allocate Ads Budget to Project (Owner / Admin Only)
// -------------------------------------------------------------
if ($action === 'allocate_budget') {
    if (!$isAdminUser) {
        echo json_encode(['success' => false, 'message' => 'Hanya Owner dan Admin yang memiliki otorisasi menentukan alokasi saldo iklan.']);
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

        // Update allocated_ads_budget in projects table
        $db->prepare("UPDATE projects SET allocated_ads_budget = ? WHERE id = ?")
           ->execute([$allocatedBudget, $projectId]);

        // Check if there is an existing log for this project; update allocated_budget & remaining_budget
        $db->prepare("
            UPDATE ads_budget_logs 
            SET allocated_budget = ?, 
                remaining_budget = (? - spend_installed),
                updated_at = CURRENT_TIMESTAMP
            WHERE project_id = ? AND COALESCE(is_deleted, 0) = 0
        ")->execute([$allocatedBudget, $allocatedBudget, $projectId]);

        log_activity(
            'ads_tracking',
            "Alokasi Saldo Ads Diperbarui: {$proj['company']}",
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
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan alokasi saldo iklan: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 3. POST: Log / Update Installed Spend & Proof (Tim Ads / Workers / Admin)
// -------------------------------------------------------------
if ($action === 'log_spend' || $action === 'update_spend') {
    $projectId = intval($_POST['project_id'] ?? 0);
    $platform = trim($_POST['platform'] ?? 'Meta Ads');
    $spendInstalled = parse_ads_amount($_POST['spend_installed'] ?? 0);
    $status = trim($_POST['status'] ?? 'sedang_berjalan');
    $notes = trim($_POST['notes'] ?? '');

    $allowedStatuses = ['menunggu_saldo', 'sedang_berjalan', 'saldo_hampir_habis', 'selesai'];
    if (!in_array($status, $allowedStatuses)) {
        $status = 'sedang_berjalan';
    }

    if ($projectId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Pilih proyek klien terlebih dahulu.']);
        exit;
    }

    try {
        $stmtP = $db->prepare("
            SELECT p.id, p.name, p.client_id, COALESCE(p.allocated_ads_budget, 0) as allocated_ads_budget, c.company 
            FROM projects p 
            JOIN clients c ON p.client_id = c.id 
            WHERE p.id = ?
        ");
        $stmtP->execute([$projectId]);
        $proj = $stmtP->fetch();

        if (!$proj) {
            echo json_encode(['success' => false, 'message' => 'Proyek tidak ditemukan.']);
            exit;
        }

        $allocatedBudget = floatval($proj['allocated_ads_budget']);
        $remainingBudget = $allocatedBudget - $spendInstalled;

        // Auto-recommend status if almost depleted
        if ($allocatedBudget > 0 && $remainingBudget <= (0.1 * $allocatedBudget) && $status === 'sedang_berjalan') {
            $status = 'saldo_hampir_habis';
        }

        // Handle attachment file upload (Screenshot Dashboard Ads)
        $attachmentProof = null;
        if (!empty($_FILES['attachment_proof']['name']) && $_FILES['attachment_proof']['error'] === UPLOAD_ERR_OK) {
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];
            $fileInfo = pathinfo($_FILES['attachment_proof']['name']);
            $ext = strtolower($fileInfo['extension'] ?? '');

            if (in_array($ext, $allowedExts)) {
                $uploadDir = BASE_PATH . '/assets/uploads/ads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $filename = 'proof_' . $projectId . '_' . time() . '_' . uniqid() . '.' . $ext;
                $targetPath = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['attachment_proof']['tmp_name'], $targetPath)) {
                    $attachmentProof = 'assets/uploads/ads/' . $filename;
                }
            }
        }

        // Insert new log entry
        $stmtIns = $db->prepare("
            INSERT INTO ads_budget_logs (
                project_id, client_id, platform, allocated_budget, spend_installed,
                remaining_budget, status, notes, attachment_proof,
                updated_by_user_id, updated_by_name, is_verified_by_owner, is_deleted,
                created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, 0, 0,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");
        $stmtIns->execute([
            $projectId,
            (int)$proj['client_id'],
            $platform,
            $allocatedBudget,
            $spendInstalled,
            $remainingBudget,
            $status,
            $notes,
            $attachmentProof,
            $userId,
            $user['name']
        ]);
        $newLogId = (int)$db->lastInsertId();

        log_activity(
            'ads_tracking',
            "Anggaran Ads Terpasang Diperbarui: {$proj['company']}",
            "Tim Ads ({$user['name']}) menginput spend terpasang " . format_rupiah($spendInstalled) . " ({$platform}). Sisa Saldo: " . format_rupiah($remainingBudget),
            $spendInstalled
        );

        echo json_encode([
            'success' => true,
            'message' => 'Pembaruan anggaran iklan terpasang berhasil disimpan dan dikoordinasikan ke Owner!',
            'log_id' => $newLogId,
            'remaining_budget' => $remainingBudget,
            'status' => $status
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan update anggaran iklan: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 4. POST: Verify / Approve Ads Log (Owner Only)
// -------------------------------------------------------------
if ($action === 'verify_log') {
    if (!$isOwnerUser && !$isAdminUser) {
        echo json_encode(['success' => false, 'message' => 'Hanya Owner / Admin yang dapat memverifikasi laporan dashboard iklan.']);
        exit;
    }

    $logId = intval($_POST['log_id'] ?? 0);
    if ($logId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Log ID tidak valid.']);
        exit;
    }

    try {
        $db->prepare("UPDATE ads_budget_logs SET is_verified_by_owner = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
           ->execute([$logId]);

        echo json_encode(['success' => true, 'message' => 'Laporan anggaran iklan berhasil diverifikasi!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 5. POST: Delete Ads Budget Log (Owner / Admin Only)
// -------------------------------------------------------------
if ($action === 'delete_log') {
    if (!$isAdminUser) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
        exit;
    }

    $logId = intval($_POST['log_id'] ?? 0);
    if ($logId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Log ID tidak valid.']);
        exit;
    }

    try {
        $db->prepare("UPDATE ads_budget_logs SET is_deleted = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
           ->execute([$logId]);

        echo json_encode(['success' => true, 'message' => 'Riwayat anggaran iklan berhasil dihapus.']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 6. POST: Upload Bukti Transaksi Isi Saldo Ads (Tim Ads / Workers / Admin / Owner)
// -------------------------------------------------------------
if ($action === 'upload_topup') {
    if (!$isWorkerUser && !$isAdminUser && !$isOwnerUser) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya Tim Ads dan Admin yang dapat mengupload bukti isi saldo.']);
        exit;
    }

    $projectId = intval($_POST['project_id'] ?? 0);
    $platform = trim($_POST['platform'] ?? 'Meta Ads');
    $topupAmount = parse_ads_amount($_POST['topup_amount'] ?? 0);
    $spendInstalled = parse_ads_amount($_POST['spend_installed'] ?? 0);
    $transactionDate = trim($_POST['transaction_date'] ?? date('Y-m-d'));
    $notes = trim($_POST['notes'] ?? '');

    $allowedPlatforms = ['Meta Ads', 'Google Ads', 'TikTok Ads'];
    if (!in_array($platform, $allowedPlatforms)) {
        $platform = 'Meta Ads';
    }

    if ($projectId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Pilih proyek & klien terlebih dahulu.']);
        exit;
    }

    if ($topupAmount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Nominal isi saldo (Top-Up) harus lebih dari Rp 0.']);
        exit;
    }

    if (empty($_FILES['receipt_file']['name']) || $_FILES['receipt_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Bukti transaksi (struk / screenshot transfer) wajib diunggah!']);
        exit;
    }

    $file = $_FILES['receipt_file'];
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($fileExt, $allowedExts)) {
        echo json_encode(['success' => false, 'message' => 'Format file tidak didukung. Harap upload format JPG, PNG, WEBP, atau PDF.']);
        exit;
    }

    $maxBytes = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxBytes) {
        echo json_encode(['success' => false, 'message' => 'Ukuran file bukti transaksi melebihi batas 10MB.']);
        exit;
    }

    try {
        $stmtP = $db->prepare("
            SELECT p.id, p.client_id, p.name as project_name, 
                   COALESCE(p.allocated_ads_budget, 0) as allocated_ads_budget, 
                   c.company as client_company 
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

        $clientId = (int)$proj['client_id'];

        // Save receipt to assets/uploads/receipts/
        $uploadDir = BASE_PATH . '/assets/uploads/receipts/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        $newFileName = 'ads_receipt_' . $projectId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $fileExt;
        $destPath = $uploadDir . $newFileName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file ke direktori server.']);
            exit;
        }

        $receiptRelPath = 'assets/uploads/receipts/' . $newFileName;

        // Insert into ads_topup_transactions
        $stmtIns = $db->prepare("
            INSERT INTO ads_topup_transactions (
                client_id, project_id, uploaded_by_user_id, platform,
                topup_amount, spend_installed, transaction_date, receipt_file,
                notes, status, is_deleted, created_at
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, 'recorded', 0, CURRENT_TIMESTAMP
            )
        ");
        $stmtIns->execute([
            $clientId,
            $projectId,
            $userId,
            $platform,
            $topupAmount,
            $spendInstalled,
            $transactionDate,
            $receiptRelPath,
            $notes
        ]);
        $transId = (int)$db->lastInsertId();

        // Sync with ads_budget_logs for live tracker
        $allocatedBudget = floatval($proj['allocated_ads_budget']);
        $remainingBudget = $allocatedBudget - $spendInstalled;
        $statusLog = ($allocatedBudget > 0 && $remainingBudget <= (0.1 * $allocatedBudget)) ? 'saldo_hampir_habis' : 'sedang_berjalan';

        $db->prepare("
            INSERT INTO ads_budget_logs (
                project_id, client_id, platform, allocated_budget, spend_installed,
                remaining_budget, status, notes, attachment_proof,
                updated_by_user_id, updated_by_name, is_verified_by_owner, is_deleted,
                created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, 0, 0,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ")->execute([
            $projectId,
            $clientId,
            $platform,
            $allocatedBudget,
            $spendInstalled,
            $remainingBudget,
            $statusLog,
            "Top-Up Saldo Ads " . format_rupiah($topupAmount) . " (" . $platform . "). " . $notes,
            $receiptRelPath,
            $userId,
            $user['name']
        ]);

        log_activity(
            'ads_tracking',
            "Isi Saldo Ads: {$proj['client_company']}",
            "Tim Ads ({$user['name']}) mengunggah bukti isi saldo {$platform} sebesar " . format_rupiah($topupAmount) . " (Spend terpasang: " . format_rupiah($spendInstalled) . ").",
            $topupAmount
        );

        echo json_encode([
            'success' => true,
            'message' => 'Bukti transaksi isi saldo sebesar ' . format_rupiah($topupAmount) . ' berhasil diunggah dan otomatis terbit di Portal Klien!',
            'transaction_id' => $transId,
            'receipt_file' => $receiptRelPath
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal memproses transaksi isi saldo: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 7. POST: Verify / Approve Top-Up Transaction (Owner Only)
// -------------------------------------------------------------
if ($action === 'verify_topup') {
    if (!$isOwnerUser && !$isAdminUser) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
        exit;
    }

    $transId = intval($_POST['transaction_id'] ?? 0);
    if ($transId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID Transaksi tidak valid.']);
        exit;
    }

    try {
        $db->prepare("UPDATE ads_topup_transactions SET status = 'verified' WHERE id = ?")
           ->execute([$transId]);

        echo json_encode(['success' => true, 'message' => 'Transaksi isi saldo Ads berhasil diverifikasi!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 8. POST: Delete Top-Up Transaction (Owner / Admin Only)
// -------------------------------------------------------------
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
        $db->prepare("UPDATE ads_topup_transactions SET is_deleted = 1 WHERE id = ?")
           ->execute([$transId]);

        echo json_encode(['success' => true, 'message' => 'Transaksi isi saldo Ads berhasil dihapus.']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Aksi API tidak dikenali.']);

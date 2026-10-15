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
        if ($isClientUser) {
            // STRICT CLIENT SCOPE: Only see verified ads campaign status for their own company
            $clientId = current_user_client_id();
            if (!$clientId) {
                $stmtC = $db->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
                $stmtC->execute([$user['email']]);
                $clientId = (int)$stmtC->fetchColumn();
            }

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

            echo json_encode([
                'success' => true,
                'role' => 'client',
                'projects' => $projects
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

            echo json_encode([
                'success' => true,
                'role' => $role,
                'projects' => $projects
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
                'projects' => $projects
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

echo json_encode(['success' => false, 'message' => 'Aksi API tidak dikenali.']);

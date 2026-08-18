<?php
/**
 * Expenses API Handler (Freelancer Payouts & Ads Top-Up)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_auth();

$db = Database::getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. Create Freelancer Payout
if ($action === 'create_payout') {
    $freelancerName = trim($_POST['freelancer_name'] ?? '');
    $freelancerBank = trim($_POST['freelancer_bank'] ?? 'BCA');
    $freelancerAccount = trim($_POST['freelancer_account'] ?? '');
    $clientId = intval($_POST['client_id'] ?? 0);
    $projectId = intval($_POST['project_id'] ?? 0);
    $taskDescription = trim($_POST['task_description'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $status = $_POST['status'] ?? 'Pending'; // 'Pending' or 'Paid'
    $paidAt = ($status === 'Paid') ? date('Y-m-d H:i:s') : null;

    if (empty($freelancerName) || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Nama freelancer dan nominal fee wajib diisi.']);
        exit;
    }

    // Auto-resolve or create project if client is selected
    if ($projectId <= 0 && $clientId > 0) {
        $pStmt = $db->prepare("SELECT id FROM projects WHERE client_id = ? ORDER BY id DESC LIMIT 1");
        $pStmt->execute([$clientId]);
        $foundProjId = $pStmt->fetchColumn();
        if ($foundProjId) {
            $projectId = intval($foundProjId);
        } else {
            $cName = $db->query("SELECT company FROM clients WHERE id = $clientId")->fetchColumn() ?: 'Klien';
            $db->prepare("INSERT INTO projects (client_id, name, contract_value, target_margin_percent, status, start_date, end_date) VALUES (?, ?, 0, 30, 'In Progress', date('now'), date('now', '+30 days'))")
               ->execute([$clientId, "Proyek Operasional - $cName"]);
            $projectId = intval($db->lastInsertId());
        }
    }

    if ($projectId <= 0) {
        // Fallback to latest project in system or create general project
        $firstProj = $db->query("SELECT id FROM projects ORDER BY id DESC LIMIT 1")->fetchColumn();
        if ($firstProj) {
            $projectId = intval($firstProj);
        } else {
            echo json_encode(['success' => false, 'message' => 'Silakan pilih Klien atau Proyek terkait fee freelancer ini.']);
            exit;
        }
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO freelancer_payouts (
                freelancer_name, freelancer_bank, freelancer_account,
                project_id, task_description, amount, status, paid_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $freelancerName, $freelancerBank, $freelancerAccount,
            $projectId, $taskDescription, $amount, $status, $paidAt
        ]);
        $payoutId = $db->lastInsertId();

        log_activity('payout', "Input Fee Freelancer: $freelancerName", "Tugas: $taskDescription - Nominal: " . format_rupiah($amount), $amount);

        echo json_encode([
            'success' => true,
            'message' => 'Data fee freelancer berhasil disimpan!',
            'payout_id' => $payoutId
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 2. Mark Freelancer Payout as Paid
if ($action === 'mark_payout_paid') {
    $payoutId = intval($_POST['payout_id'] ?? 0);
    $paidAt = $_POST['paid_at'] ?? date('Y-m-d H:i:s');

    if ($payoutId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID Payout tidak valid.']);
        exit;
    }

    try {
        $stmt = $db->prepare("UPDATE freelancer_payouts SET status = 'Paid', paid_at = ? WHERE id = ?");
        $stmt->execute([$paidAt, $payoutId]);

        // Get details for activity log
        $payout = $db->query("SELECT * FROM freelancer_payouts WHERE id = $payoutId")->fetch();
        if ($payout) {
            log_activity('payout', "Pelunasan Fee Freelancer #{$payout['id']}", "Dibayarkan ke {$payout['freelancer_name']} - " . format_rupiah($payout['amount']), $payout['amount']);
        }

        echo json_encode(['success' => true, 'message' => 'Status fee freelancer diubah menjadi Lunas (Paid)!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 3. Record Ads Top-Up
if ($action === 'create_ads') {
    $clientId = intval($_POST['client_id'] ?? 0);
    $projectId = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $platform = $_POST['platform'] ?? 'Meta Ads';
    $accountId = trim($_POST['account_id'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $spentDate = $_POST['spent_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');

    if ($clientId <= 0 || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Klien dan nominal top-up iklan wajib diisi.']);
        exit;
    }

    // Auto-resolve project if not selected
    if (empty($projectId)) {
        $pStmt = $db->prepare("SELECT id FROM projects WHERE client_id = ? ORDER BY id DESC LIMIT 1");
        $pStmt->execute([$clientId]);
        $foundProjId = $pStmt->fetchColumn();
        if ($foundProjId) {
            $projectId = intval($foundProjId);
        } else {
            $cName = $db->query("SELECT company FROM clients WHERE id = $clientId")->fetchColumn() ?: 'Klien';
            $db->prepare("INSERT INTO projects (client_id, name, contract_value, target_margin_percent, status, start_date, end_date) VALUES (?, ?, 0, 30, 'In Progress', date('now'), date('now', '+30 days'))")
               ->execute([$clientId, "Campaign $platform - $cName"]);
            $projectId = intval($db->lastInsertId());
        }
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO ads_spend (
                client_id, project_id, platform, account_id, amount, spent_date, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$clientId, $projectId, $platform, $accountId, $amount, $spentDate, $notes]);
        $adsId = $db->lastInsertId();

        log_activity('ads', "Catat Top-Up $platform", "Nominal: " . format_rupiah($amount) . " ($notes)", $amount);

        echo json_encode([
            'success' => true,
            'message' => "Pengeluaran Ads $platform berhasil dicatat!",
            'ads_id' => $adsId
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 4. Delete Payout (Owner only)
if ($action === 'delete_payout') {
    if (!is_owner()) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya Owner yang dapat menghapus data.']);
        exit;
    }
    $id = intval($_POST['id'] ?? 0);
    try {
        $stmt = $db->prepare("UPDATE freelancer_payouts SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Data fee freelancer berhasil dihapus!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 5. Delete Ads Top-Up (Owner only)
if ($action === 'delete_ads') {
    if (!is_owner()) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya Owner yang dapat menghapus data.']);
        exit;
    }
    $id = intval($_POST['id'] ?? 0);
    try {
        $stmt = $db->prepare("UPDATE ads_spend SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Data top-up ads berhasil dihapus!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);

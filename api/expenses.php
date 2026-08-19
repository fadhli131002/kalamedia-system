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
    $freelancerPhone = trim($_POST['freelancer_phone'] ?? '');
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
                freelancer_name, freelancer_phone, freelancer_bank, freelancer_account,
                project_id, task_description, amount, status, paid_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $freelancerName, $freelancerPhone, $freelancerBank, $freelancerAccount,
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

// 1b. Update Freelancer Payout
if ($action === 'update_payout') {
    $id = intval($_POST['id'] ?? 0);
    $freelancerName = trim($_POST['freelancer_name'] ?? '');
    $freelancerPhone = trim($_POST['freelancer_phone'] ?? '');
    $freelancerBank = trim($_POST['freelancer_bank'] ?? 'BCA');
    $freelancerAccount = trim($_POST['freelancer_account'] ?? '');
    $clientId = intval($_POST['client_id'] ?? 0);
    $projectId = intval($_POST['project_id'] ?? 0);
    $taskDescription = trim($_POST['task_description'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $status = $_POST['status'] ?? 'Pending';

    if ($id <= 0 || empty($freelancerName) || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID, nama freelancer, dan nominal fee wajib diisi.']);
        exit;
    }

    if ($projectId <= 0 && $clientId > 0) {
        $pStmt = $db->prepare("SELECT id FROM projects WHERE client_id = ? ORDER BY id DESC LIMIT 1");
        $pStmt->execute([$clientId]);
        $foundProjId = $pStmt->fetchColumn();
        if ($foundProjId) {
            $projectId = intval($foundProjId);
        }
    }

    try {
        $existing = $db->query("SELECT * FROM freelancer_payouts WHERE id = $id")->fetch();
        if (!$existing) {
            echo json_encode(['success' => false, 'message' => 'Data fee freelancer tidak ditemukan.']);
            exit;
        }

        $paidAt = $existing['paid_at'];
        if ($status === 'Paid' && empty($paidAt)) {
            $paidAt = date('Y-m-d H:i:s');
        } elseif ($status === 'Pending') {
            $paidAt = null;
        }

        if ($projectId > 0) {
            $stmt = $db->prepare("
                UPDATE freelancer_payouts SET
                    freelancer_name = ?,
                    freelancer_phone = ?,
                    freelancer_bank = ?,
                    freelancer_account = ?,
                    project_id = ?,
                    task_description = ?,
                    amount = ?,
                    status = ?,
                    paid_at = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $freelancerName, $freelancerPhone, $freelancerBank, $freelancerAccount,
                $projectId, $taskDescription, $amount, $status, $paidAt, $id
            ]);
        } else {
            $stmt = $db->prepare("
                UPDATE freelancer_payouts SET
                    freelancer_name = ?,
                    freelancer_phone = ?,
                    freelancer_bank = ?,
                    freelancer_account = ?,
                    task_description = ?,
                    amount = ?,
                    status = ?,
                    paid_at = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $freelancerName, $freelancerPhone, $freelancerBank, $freelancerAccount,
                $taskDescription, $amount, $status, $paidAt, $id
            ]);
        }

        log_activity('payout', "Edit Fee Freelancer: $freelancerName", "Nominal: " . format_rupiah($amount) . " | Status: $status", $amount);

        echo json_encode([
            'success' => true,
            'message' => 'Data fee freelancer berhasil diperbarui!'
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

// 3b. Update Ads Top-Up
if ($action === 'update_ads') {
    $id = intval($_POST['id'] ?? 0);
    $clientId = intval($_POST['client_id'] ?? 0);
    $projectId = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $platform = $_POST['platform'] ?? 'Meta Ads';
    $accountId = trim($_POST['account_id'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $spentDate = $_POST['spent_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');

    if ($id <= 0 || $clientId <= 0 || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID, klien, dan nominal top-up iklan wajib diisi.']);
        exit;
    }

    if (empty($projectId)) {
        $pStmt = $db->prepare("SELECT id FROM projects WHERE client_id = ? ORDER BY id DESC LIMIT 1");
        $pStmt->execute([$clientId]);
        $foundProjId = $pStmt->fetchColumn();
        if ($foundProjId) {
            $projectId = intval($foundProjId);
        }
    }

    try {
        $stmt = $db->prepare("
            UPDATE ads_spend SET
                client_id = ?,
                project_id = ?,
                platform = ?,
                account_id = ?,
                amount = ?,
                spent_date = ?,
                notes = ?
            WHERE id = ?
        ");
        $stmt->execute([$clientId, $projectId, $platform, $accountId, $amount, $spentDate, $notes, $id]);

        log_activity('ads', "Edit Top-Up $platform", "Nominal: " . format_rupiah($amount) . " ($notes)", $amount);

        echo json_encode([
            'success' => true,
            'message' => "Data Top-Up Ads $platform berhasil diperbarui!"
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 3c. Get Single Ads Spend Data
if ($action === 'get_ads') {
    $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID top-up ads tidak valid.']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            SELECT a.*, c.company as client_company, pr.name as project_name
            FROM ads_spend a
            JOIN clients c ON a.client_id = c.id
            LEFT JOIN projects pr ON a.project_id = pr.id
            WHERE a.id = ? AND COALESCE(a.is_deleted, 0) = 0
        ");
        $stmt->execute([$id]);
        $ad = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ad) {
            echo json_encode(['success' => false, 'message' => 'Data pengeluaran iklan tidak ditemukan.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'ads' => $ad,
            'formatted_amount' => format_rupiah($ad['amount'])
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

// 6. Get Freelancer Payout Voucher / Invoice Data
if ($action === 'get_payout_voucher' || $action === 'get_payout') {
    $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID Payout tidak valid.']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            SELECT p.*, pr.name as project_name, pr.client_id, c.company as client_company, c.name as client_pic
            FROM freelancer_payouts p
            JOIN projects pr ON p.project_id = pr.id
            JOIN clients c ON pr.client_id = c.id
            WHERE p.id = ? AND COALESCE(p.is_deleted, 0) = 0
        ");
        $stmt->execute([$id]);
        $payout = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payout) {
            echo json_encode(['success' => false, 'message' => 'Data fee freelancer tidak ditemukan.']);
            exit;
        }

        $paidDate = $payout['paid_at'] ?: $payout['created_at'];
        $voucherNumber = 'VCH-FL-' . date('ym', strtotime($paidDate)) . str_pad($payout['id'], 3, '0', STR_PAD_LEFT);

        $formatted = [
            'voucher_number' => $voucherNumber,
            'amount' => format_rupiah($payout['amount']),
            'payment_date' => format_date($paidDate, true),
            'created_date' => format_date($payout['created_at'])
        ];

        echo json_encode([
            'success' => true,
            'payout' => $payout,
            'formatted' => $formatted
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 7. Get Top-Up Ads Voucher / Invoice Data
if ($action === 'get_ads_voucher') {
    $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID Ads tidak valid.']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            SELECT a.*, c.company as client_company, c.name as client_pic, c.phone as client_phone,
                   pr.name as project_name
            FROM ads_spend a
            JOIN clients c ON a.client_id = c.id
            LEFT JOIN projects pr ON a.project_id = pr.id
            WHERE a.id = ? AND COALESCE(a.is_deleted, 0) = 0
        ");
        $stmt->execute([$id]);
        $ad = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ad) {
            echo json_encode(['success' => false, 'message' => 'Data top-up ads tidak ditemukan.']);
            exit;
        }

        $spentDate = $ad['spent_date'];
        $voucherNumber = 'VCH-ADS-' . date('ym', strtotime($spentDate)) . str_pad($ad['id'], 3, '0', STR_PAD_LEFT);

        $formatted = [
            'voucher_number' => $voucherNumber,
            'amount' => format_rupiah($ad['amount']),
            'spent_date' => format_date($spentDate),
            'created_date' => format_date($ad['created_at'])
        ];

        echo json_encode([
            'success' => true,
            'ads' => $ad,
            'formatted' => $formatted
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);

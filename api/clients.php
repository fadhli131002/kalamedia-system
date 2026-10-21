<?php
/**
 * Clients & Projects API Handler
 * Full CRUD (Create, Read, Update, Soft-Delete) for Clients and Projects
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_auth();
release_session();

$db = Database::getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function parse_client_amount($val): float {
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

// 1. CREATE CLIENT & PORTAL LOGIN ACCESS
if ($action === 'create_client') {
    if (!is_admin()) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya Owner dan Admin yang dapat menambahkan klien.']);
        exit;
    }
    $compSelect = trim($_POST['company_select'] ?? '');
    $compNew = trim($_POST['company_new'] ?? '');
    $company = ($compSelect === '__new__' || empty($compSelect)) ? $compNew : $compSelect;
    if (empty($company) && !empty($_POST['company'])) {
        $company = trim($_POST['company']);
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Service & Contract parameters
    $services = $_POST['service_type'] ?? ($_POST['services'] ?? 'Kelola Sosmed');
    if (is_array($services)) {
        $serviceType = implode(', ', array_filter(array_map('trim', $services)));
    } else {
        $serviceType = trim(strval($services));
    }
    if (empty($serviceType)) {
        $serviceType = 'Kelola Sosmed';
    }

    $contractStartDate = !empty($_POST['contract_start_date']) ? trim($_POST['contract_start_date']) : date('Y-m-d');
    $contractEndDate = !empty($_POST['contract_end_date']) ? trim($_POST['contract_end_date']) : date('Y-m-d', strtotime('+30 days'));

    $today = date('Y-m-d');
    $contractStatus = ($today > $contractEndDate) ? 'expired' : 'active';
    $clientStatus = ($contractStatus === 'expired') ? 'expired' : 'Active';
    $userActive = ($contractStatus === 'expired') ? 0 : 1;

    if (empty($name) || empty($company) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Nama, nama perusahaan/PT, dan email login wajib diisi.']);
        exit;
    }

    if (empty($password)) {
        $password = 'kalamedia123';
    }

    try {
        $db->beginTransaction();

        // 1. Check if client with this company name already exists
        $stmtChk = $db->prepare("SELECT id FROM clients WHERE company = ? AND COALESCE(is_deleted, 0) = 0 LIMIT 1");
        $stmtChk->execute([$company]);
        $existingClientId = $stmtChk->fetchColumn();

        if ($existingClientId) {
            $clientId = (int)$existingClientId;
            $db->prepare("UPDATE clients SET name = ?, email = ?, phone = ?, company_name = ?, pic_name = ?, service_type = ?, contract_start_date = ?, contract_end_date = ?, contract_status = ?, status = ? WHERE id = ?")
               ->execute([$name, $email, $phone, $company, $name, $serviceType, $contractStartDate, $contractEndDate, $contractStatus, $clientStatus, $clientId]);
        } else {
            $stmt = $db->prepare("INSERT INTO clients (name, company, company_name, pic_name, email, phone, service_type, contract_start_date, contract_end_date, contract_status, status, is_deleted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
            $stmt->execute([$name, $company, $company, $name, $email, $phone, $serviceType, $contractStartDate, $contractEndDate, $contractStatus, $clientStatus]);
            $clientId = (int)$db->lastInsertId();
        }

        // 2. Ensure at least one project is linked to this client
        $chkProj = $db->prepare("SELECT id FROM projects WHERE client_id = ? AND COALESCE(is_deleted, 0) = 0 LIMIT 1");
        $chkProj->execute([$clientId]);
        $hasProject = $chkProj->fetchColumn();
        if (!$hasProject) {
            $projectName = 'Project ' . $company;
            $db->prepare("INSERT INTO projects (client_id, name, project_name, service_type, contract_start_date, contract_end_date, contract_status, contract_value, target_margin_percent, status, start_date) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 30, 'In Progress', ?)")
               ->execute([$clientId, $projectName, $projectName, $serviceType, $contractStartDate, $contractEndDate, $contractStatus, $contractStartDate]);
        } else {
            $db->prepare("UPDATE projects SET service_type = ?, contract_start_date = ?, contract_end_date = ?, contract_status = ? WHERE id = ?")
               ->execute([$serviceType, $contractStartDate, $contractEndDate, $contractStatus, $hasProject]);
        }

        // 3. Create or Update Client User Account for Portal Access
        $hashedPw = password_hash($password, PASSWORD_BCRYPT);
        $userStmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $userStmt->execute([$email]);
        $existingUserId = $userStmt->fetchColumn();

        if ($existingUserId) {
            $db->prepare("
                UPDATE users SET name = ?, password = ?, role = 'client', client_id = ?, phone_wa = ?, is_active = ?, is_deleted = 0
                WHERE id = ?
            ")->execute([$name, $hashedPw, $clientId, $phone, $userActive, $existingUserId]);
        } else {
            $db->prepare("
                INSERT INTO users (name, email, password, role, employment_type, client_id, phone_wa, is_active, is_deleted)
                VALUES (?, ?, ?, 'client', 'client', ?, ?, ?, 0)
            ")->execute([$name, $email, $hashedPw, $clientId, $phone, $userActive]);
        }

        $db->commit();
        log_activity('client', "Klien Baru & Akses Portal Dibuat: $company", "Layanan: $serviceType, Kontrak: $contractStartDate s/d $contractEndDate");

        echo json_encode([
            'success' => true,
            'message' => 'Data klien dan akses login portal berhasil dibuat!',
            'client_id' => $clientId
        ]);
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 2. GET CLIENT DETAILS
if ($action === 'get_client') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM clients WHERE id = ? AND COALESCE(is_deleted, 0) = 0");
    $stmt->execute([$id]);
    $client = $stmt->fetch();

    if (!$client) {
        echo json_encode(['success' => false, 'message' => 'Data klien tidak ditemukan.']);
        exit;
    }

    echo json_encode(['success' => true, 'client' => $client]);
    exit;
}

// 3. UPDATE CLIENT
if ($action === 'update_client') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $services = $_POST['service_type'] ?? ($_POST['services'] ?? 'Kelola Sosmed');
    if (is_array($services)) {
        $serviceType = implode(', ', array_filter(array_map('trim', $services)));
    } else {
        $serviceType = trim(strval($services));
    }
    if (empty($serviceType)) {
        $serviceType = 'Kelola Sosmed';
    }

    $contractStartDate = !empty($_POST['contract_start_date']) ? trim($_POST['contract_start_date']) : date('Y-m-d');
    $contractEndDate = !empty($_POST['contract_end_date']) ? trim($_POST['contract_end_date']) : date('Y-m-d', strtotime('+30 days'));

    $today = date('Y-m-d');
    $contractStatus = ($today > $contractEndDate) ? 'expired' : 'active';
    $clientStatus = ($contractStatus === 'expired') ? 'expired' : 'Active';
    $userActive = ($contractStatus === 'expired') ? 0 : 1;

    if ($id <= 0 || empty($name) || empty($company)) {
        echo json_encode(['success' => false, 'message' => 'Nama dan nama perusahaan wajib diisi.']);
        exit;
    }

    $password = trim($_POST['password'] ?? '');

    try {
        $stmt = $db->prepare("
            UPDATE clients 
            SET name = ?, company = ?, company_name = ?, pic_name = ?, email = ?, phone = ?,
                service_type = ?, contract_start_date = ?, contract_end_date = ?, contract_status = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $company, $company, $name, $email, $phone, $serviceType, $contractStartDate, $contractEndDate, $contractStatus, $clientStatus, $id]);

        // Sync to project
        $db->prepare("UPDATE projects SET service_type = ?, contract_start_date = ?, contract_end_date = ?, contract_status = ? WHERE client_id = ?")
           ->execute([$serviceType, $contractStartDate, $contractEndDate, $contractStatus, $id]);

        // Sync to users table for Client Portal access
        $userStmt = $db->prepare("SELECT id FROM users WHERE client_id = ? OR email = ? LIMIT 1");
        $userStmt->execute([$id, $email]);
        $existingUserId = $userStmt->fetchColumn();

        if ($existingUserId) {
            if (!empty($password)) {
                $hashedPw = password_hash($password, PASSWORD_BCRYPT);
                $db->prepare("UPDATE users SET name = ?, email = ?, password = ?, role = 'client', client_id = ?, phone_wa = ?, is_active = ?, is_deleted = 0 WHERE id = ?")
                   ->execute([$name, $email, $hashedPw, $id, $phone, $userActive, $existingUserId]);
            } else {
                $db->prepare("UPDATE users SET name = ?, email = ?, role = 'client', client_id = ?, phone_wa = ?, is_active = ?, is_deleted = 0 WHERE id = ?")
                   ->execute([$name, $email, $id, $phone, $userActive, $existingUserId]);
            }
        } elseif (!empty($password)) {
            $hashedPw = password_hash($password, PASSWORD_BCRYPT);
            $db->prepare("INSERT INTO users (name, email, password, role, employment_type, client_id, phone_wa, is_active, is_deleted) VALUES (?, ?, ?, 'client', 'client', ?, ?, ?, 0)")
               ->execute([$name, $email, $hashedPw, $id, $phone, $userActive]);
        }

        log_activity('client', "Data Klien Diperbarui: $company", "Layanan: $serviceType, Masa Kontrak: $contractStartDate s/d $contractEndDate");

        echo json_encode(['success' => true, 'message' => 'Data klien dan masa kontrak berhasil diperbarui!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 3b. RENEW CLIENT CONTRACT
if ($action === 'renew_contract') {
    if (!is_admin()) {
        echo json_encode(['success' => false, 'message' => 'Hanya Owner dan Admin yang dapat memperpanjang kontrak klien.']);
        exit;
    }
    $id = intval($_POST['client_id'] ?? ($_POST['id'] ?? 0));
    $startDate = !empty($_POST['contract_start_date']) ? trim($_POST['contract_start_date']) : date('Y-m-d');
    $endDate = !empty($_POST['contract_end_date']) ? trim($_POST['contract_end_date']) : '';

    if ($id <= 0 || empty($endDate)) {
        echo json_encode(['success' => false, 'message' => 'Tanggal akhir perpanjangan kontrak wajib diisi.']);
        exit;
    }

    try {
        $stmt = $db->prepare("SELECT company, email FROM clients WHERE id = ?");
        $stmt->execute([$id]);
        $client = $stmt->fetch();
        if (!$client) {
            echo json_encode(['success' => false, 'message' => 'Klien tidak ditemukan.']);
            exit;
        }

        $today = date('Y-m-d');
        $contractStatus = ($today > $endDate) ? 'expired' : 'active';
        $clientStatus = ($contractStatus === 'expired') ? 'expired' : 'Active';
        $userActive = ($contractStatus === 'expired') ? 0 : 1;

        $db->prepare("
            UPDATE clients 
            SET contract_start_date = ?, contract_end_date = ?, contract_status = ?, status = ?
            WHERE id = ?
        ")->execute([$startDate, $endDate, $contractStatus, $clientStatus, $id]);

        $db->prepare("
            UPDATE projects 
            SET contract_start_date = ?, contract_end_date = ?, contract_status = ?
            WHERE client_id = ?
        ")->execute([$startDate, $endDate, $contractStatus, $id]);

        // Reactivate user portal login
        $db->prepare("
            UPDATE users SET is_active = ?, is_deleted = 0 WHERE client_id = ? OR email = ?
        ")->execute([$userActive, $id, $client['email']]);

        log_activity('client', "Kontrak Klien Diperpanjang: {$client['company']}", "Masa baru: $startDate s/d $endDate (Status: $contractStatus)");

        $formattedEnd = date('d M Y', strtotime($endDate));
        echo json_encode([
            'success' => true,
            'message' => "Masa kontrak {$client['company']} berhasil diperpanjang hingga {$formattedEnd} dan akun portal aktif kembali!"
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 4. DELETE CLIENT (SOFT DELETE)
if ($action === 'delete_client') {
    if (!is_owner()) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya Owner yang dapat menghapus data klien.']);
        exit;
    }

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID klien tidak valid.']);
        exit;
    }

    try {
        $stmt = $db->prepare("SELECT company FROM clients WHERE id = ?");
        $stmt->execute([$id]);
        $client = $stmt->fetch();

        $delStmt = $db->prepare("UPDATE clients SET is_deleted = 1 WHERE id = ?");
        $delStmt->execute([$id]);

        if ($client) {
            log_activity('client', "Data Klien Dihapus: {$client['company']}", "Owner menghapus klien dari sistem.");
        }

        echo json_encode(['success' => true, 'message' => 'Data klien berhasil dihapus!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 5. CREATE PROJECT (With Service Fee vs Ads Budget Separation & Inline Client Creation)
if ($action === 'create_project') {
    if (!is_admin()) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya Owner dan Admin yang dapat menambahkan proyek.']);
        exit;
    }

    $rawClientId = $_POST['client_id'] ?? '';
    $clientId = ($rawClientId === '__new__') ? 0 : intval($rawClientId);

    // Support Inline Client Creation if client doesn't exist yet or user chose '+ Buat Klien Baru Langsung'
    if ($clientId <= 0) {
        $newCompany = trim($_POST['new_client_company'] ?? ($_POST['company'] ?? ''));
        $newName = trim($_POST['new_client_name'] ?? ($_POST['pic_name'] ?? ''));
        $newEmail = trim($_POST['new_client_email'] ?? ($_POST['email'] ?? ''));
        $newPhone = trim($_POST['new_client_phone'] ?? ($_POST['phone'] ?? ''));
        $newPassword = trim($_POST['new_client_password'] ?? 'kalamedia123');

        if (!empty($newCompany)) {
            if (empty($newName)) $newName = $newCompany;
            if (empty($newEmail)) {
                $cleanSlug = preg_replace('/[^a-z0-9]/', '', strtolower($newCompany));
                $newEmail = $cleanSlug . '@klien.kalamedia.id';
            }

            try {
                // Check if already exists
                $stmtChkC = $db->prepare("SELECT id FROM clients WHERE company = ? AND COALESCE(is_deleted, 0) = 0 LIMIT 1");
                $stmtChkC->execute([$newCompany]);
                $existingCId = $stmtChkC->fetchColumn();

                if ($existingCId) {
                    $clientId = (int)$existingCId;
                } else {
                    $insC = $db->prepare("
                        INSERT INTO clients (name, company, company_name, pic_name, email, phone, contract_start_date, contract_end_date, contract_status, status, is_deleted)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 'Active', 0)
                    ");
                    $startDateVal = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
                    $endDateVal = !empty($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d', strtotime('+3 months'));
                    $insC->execute([$newName, $newCompany, $newCompany, $newName, $newEmail, $newPhone, $startDateVal, $endDateVal]);
                    $clientId = (int)$db->lastInsertId();

                    // Create user login for this client
                    $hashedPw = password_hash($newPassword ?: 'kalamedia123', PASSWORD_BCRYPT);
                    $db->prepare("
                        INSERT INTO users (name, email, password, role, employment_type, client_id, phone_wa, is_active, is_deleted)
                        VALUES (?, ?, ?, 'client', 'client', ?, ?, 1, 0)
                    ")->execute([$newName, $newEmail, $hashedPw, $clientId, $newPhone]);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Gagal membuat profil klien baru: ' . $e->getMessage()]);
                exit;
            }
        }
    }

    $name = trim($_POST['name'] ?? '');
    $serviceFee = parse_client_amount($_POST['service_fee_amount'] ?? 0);
    $adsBudget = parse_client_amount($_POST['ads_budget_pool'] ?? 0);
    $passedTotal = parse_client_amount($_POST['total_contract_value'] ?? ($_POST['contract_value'] ?? 0));

    // Calculate total contract value
    $totalContract = $serviceFee + $adsBudget;
    if ($totalContract <= 0 && $passedTotal > 0) {
        $totalContract = $passedTotal;
        $serviceFee = $passedTotal;
    }

    $targetMargin = floatval($_POST['target_margin_percent'] ?? 30.00);
    $status = $_POST['status'] ?? 'In Progress';
    $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
    $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d', strtotime('+3 months'));

    // Service Breakdown JSON
    $services = $_POST['service_breakdown'] ?? [];
    $serviceBreakdownJson = is_array($services) ? json_encode(array_values($services)) : null;

    if ($clientId <= 0 || empty($name) || $totalContract <= 0) {
        echo json_encode(['success' => false, 'message' => 'Klien, nama project, dan nominal nilai jasa / tagihan wajib diisi.']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO projects (
                client_id, name, project_name, service_fee_amount, ads_budget_pool,
                total_contract_value, contract_value, service_breakdown_json,
                target_margin_percent, status, start_date, end_date, contract_start_date, contract_end_date, is_deleted
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([
            $clientId, $name, $name, $serviceFee, $adsBudget,
            $totalContract, $totalContract, $serviceBreakdownJson,
            $targetMargin, $status, $startDate, $endDate, $startDate, $endDate
        ]);
        $projectId = (int)$db->lastInsertId();

        // If ads budget allocated, log initial pool in ads_budget_logs
        if ($adsBudget > 0) {
            $db->prepare("
                INSERT INTO ads_budget_logs (
                    project_id, client_id, platform, allocated_budget, spend_installed, remaining_budget,
                    status, notes, updated_by_user_id, updated_by_name, is_deleted
                ) VALUES (?, ?, 'Meta Ads', ?, 0, ?, 'sedang_berjalan', 'Alokasi Saldo Ads Awal Kontrak Proyek', ?, ?, 0)
            ")->execute([$projectId, $clientId, $adsBudget, $adsBudget, current_user_id(), current_user()['name'] ?? 'Admin']);
        }

        log_activity(
            'project',
            "Proyek Baru Dibuat: $name",
            "Jasa Kalamedia: " . format_rupiah($serviceFee) . " | Saldo Ads: " . format_rupiah($adsBudget) . " | Total Tagihan: " . format_rupiah($totalContract),
            $totalContract
        );

        echo json_encode([
            'success' => true,
            'message' => 'Data project dan alokasi saldo ads berhasil dibuat!',
            'project_id' => $projectId
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 6. GET PROJECT DETAILS
if ($action === 'get_project') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND COALESCE(is_deleted, 0) = 0");
    $stmt->execute([$id]);
    $project = $stmt->fetch();

    if (!$project) {
        echo json_encode(['success' => false, 'message' => 'Data proyek tidak ditemukan.']);
        exit;
    }

    // STRICT WORKER FINANCIAL PRIVACY: Karyawan / Freelancer dilarang keras melihat fee jasa agensi & margin profit!
    if (is_karyawan() || is_freelancer()) {
        unset(
            $project['contract_value'],
            $project['total_contract_value'],
            $project['service_fee_amount'],
            $project['target_margin_percent'],
            $project['service_breakdown_json']
        );
    }

    echo json_encode(['success' => true, 'project' => $project]);
    exit;
}

// 7. UPDATE PROJECT
if ($action === 'update_project') {
    if (!is_admin()) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya Owner dan Admin yang dapat memperbarui proyek.']);
        exit;
    }

    $id = intval($_POST['id'] ?? 0);
    $clientId = intval($_POST['client_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');

    $serviceFee = parse_client_amount($_POST['service_fee_amount'] ?? 0);
    $adsBudget = parse_client_amount($_POST['ads_budget_pool'] ?? 0);
    $passedTotal = parse_client_amount($_POST['total_contract_value'] ?? ($_POST['contract_value'] ?? 0));

    $totalContract = $serviceFee + $adsBudget;
    if ($totalContract <= 0 && $passedTotal > 0) {
        $totalContract = $passedTotal;
        $serviceFee = $passedTotal;
    }

    $targetMargin = floatval($_POST['target_margin_percent'] ?? 30.00);
    $status = $_POST['status'] ?? 'In Progress';
    $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

    $services = $_POST['service_breakdown'] ?? [];
    $serviceBreakdownJson = is_array($services) && !empty($services) ? json_encode(array_values($services)) : null;

    if ($id <= 0 || $clientId <= 0 || empty($name) || $totalContract <= 0) {
        echo json_encode(['success' => false, 'message' => 'Klien, nama project, dan nilai kontrak wajib diisi.']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            UPDATE projects 
            SET client_id = ?, name = ?, project_name = ?,
                service_fee_amount = ?, ads_budget_pool = ?,
                total_contract_value = ?, contract_value = ?,
                service_breakdown_json = COALESCE(?, service_breakdown_json),
                target_margin_percent = ?, status = ?, start_date = ?, end_date = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $clientId, $name, $name,
            $serviceFee, $adsBudget,
            $totalContract, $totalContract,
            $serviceBreakdownJson,
            $targetMargin, $status, $startDate, $endDate, $id
        ]);

        log_activity(
            'project',
            "Data Proyek Diperbarui: $name",
            "Jasa Agensi: " . format_rupiah($serviceFee) . " | Saldo Ads: " . format_rupiah($adsBudget) . " | Total: " . format_rupiah($totalContract),
            $totalContract
        );

        echo json_encode(['success' => true, 'message' => 'Data project berhasil diperbarui!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 7b. TOP-UP / TAMBAH SALDO ADS KLIEN SEWAKTU-WAKTU (Hanya jika kontrak aktif)
if ($action === 'topup_project_ads') {
    if (!is_admin()) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya Owner dan Admin yang dapat menambah saldo Ads.']);
        exit;
    }

    $projectId = intval($_POST['project_id'] ?? 0);
    $topupAmount = parse_client_amount($_POST['topup_amount'] ?? 0);
    $platform = trim($_POST['platform'] ?? 'Meta Ads');
    $notes = trim($_POST['notes'] ?? '');
    $transactionDate = !empty($_POST['transaction_date']) ? trim($_POST['transaction_date']) : date('Y-m-d');

    if ($projectId <= 0 || $topupAmount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Pilih proyek dan masukkan nominal penambahan saldo Ads yang valid (> Rp 0).']);
        exit;
    }

    try {
        // Fetch project and client contract
        $stmtP = $db->prepare("
            SELECT p.*, c.company as client_company, c.contract_end_date as client_contract_end, c.contract_status as client_contract_status
            FROM projects p
            JOIN clients c ON p.client_id = c.id
            WHERE p.id = ? AND COALESCE(p.is_deleted, 0) = 0
            LIMIT 1
        ");
        $stmtP->execute([$projectId]);
        $project = $stmtP->fetch();

        if (!$project) {
            echo json_encode(['success' => false, 'message' => 'Data proyek tidak ditemukan.']);
            exit;
        }

        // VALIDASI MASA KONTRAK KLIEN
        $today = date('Y-m-d');
        $contractEndDate = !empty($project['end_date']) ? $project['end_date'] : $project['client_contract_end'];
        $contractStatus = $project['contract_status'] ?: $project['client_contract_status'];

        if (($contractEndDate && $today > $contractEndDate) || $contractStatus === 'expired') {
            echo json_encode([
                'success' => false,
                'message' => 'Masa kontrak habis — perpanjang kontrak terlebih dahulu sebelum menambah saldo Ads!'
            ]);
            exit;
        }

        // Upload Bukti Transfer Top-Up Saldo Ads (jika ada)
        $receiptPath = null;
        if (!empty($_FILES['receipt_file']['name'])) {
            $file = $_FILES['receipt_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

            if (!in_array($ext, $allowed)) {
                echo json_encode(['success' => false, 'message' => 'Format file bukti transfer harus JPG, PNG, WEBP, atau PDF.']);
                exit;
            }

            if (!is_dir(UPLOAD_DIR)) {
                @mkdir(UPLOAD_DIR, 0777, true);
            }

            $fileName = 'topup_ads_' . $projectId . '_' . time() . '.' . $ext;
            $dest = UPLOAD_DIR . '/' . $fileName;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $receiptPath = UPLOAD_URL . '/' . $fileName;
            }
        }

        // UPDATE projects: Tambah ads_budget_pool dan total_contract_value TANPA MENGUBAH service_fee_amount!
        $db->beginTransaction();

        $db->prepare("
            UPDATE projects 
            SET ads_budget_pool = COALESCE(ads_budget_pool, 0) + ?,
                total_contract_value = COALESCE(total_contract_value, contract_value, 0) + ?,
                contract_value = COALESCE(contract_value, 0) + ?
            WHERE id = ?
        ")->execute([$topupAmount, $topupAmount, $topupAmount, $projectId]);

        // Insert log in ads_budget_logs
        $db->prepare("
            INSERT INTO ads_budget_logs (
                project_id, client_id, platform, allocated_budget, spend_installed, remaining_budget,
                status, notes, attachment_proof, updated_by_user_id, updated_by_name, is_deleted
            ) VALUES (?, ?, ?, ?, 0, ?, 'sedang_berjalan', ?, ?, ?, ?, 0)
        ")->execute([
            $projectId,
            (int)$project['client_id'],
            $platform,
            $topupAmount,
            $topupAmount,
            $notes ?: 'Top-up saldo iklan klien masa kontrak aktif',
            $receiptPath,
            current_user_id(),
            current_user()['name'] ?? 'Admin'
        ]);

        // Insert into ads_topup_transactions if receipt exists
        if ($receiptPath) {
            $db->prepare("
                INSERT INTO ads_topup_transactions (
                    client_id, project_id, uploaded_by_user_id, platform, topup_amount,
                    spend_installed, transaction_date, receipt_file, notes, status, is_deleted
                ) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, 'recorded', 0)
            ")->execute([
                (int)$project['client_id'],
                $projectId,
                current_user_id(),
                $platform,
                $topupAmount,
                $transactionDate,
                $receiptPath,
                $notes
            ]);
        }

        $db->commit();

        log_activity(
            'ads',
            "Top-Up Saldo Ads Proyek: {$project['name']}",
            "Nominal: " . format_rupiah($topupAmount) . " ({$platform}) untuk {$project['client_company']}",
            $topupAmount
        );

        echo json_encode([
            'success' => true,
            'message' => 'Top-up saldo Ads sebesar ' . format_rupiah($topupAmount) . ' berhasil ditambahkan ke proyek!',
            'topup_amount' => $topupAmount,
            'receipt_path' => $receiptPath ? url($receiptPath) : null
        ]);
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        exit;
    }
}

// 8. DELETE PROJECT (SOFT DELETE)
if ($action === 'delete_project') {
    if (!is_owner()) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya Owner yang dapat menghapus proyek.']);
        exit;
    }

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID proyek tidak valid.']);
        exit;
    }

    try {
        $stmt = $db->prepare("SELECT name FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $proj = $stmt->fetch();

        $delStmt = $db->prepare("UPDATE projects SET is_deleted = 1 WHERE id = ?");
        $delStmt->execute([$id]);

        if ($proj) {
            log_activity('project', "Proyek Dihapus: {$proj['name']}", "Owner menghapus proyek dari sistem.");
        }

        echo json_encode(['success' => true, 'message' => 'Data proyek berhasil dihapus!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 9. GET CLIENT PROJECTS
if ($action === 'get_client_projects') {
    $clientId = intval($_GET['client_id'] ?? 0);
    // STRICT WORKER FINANCIAL PRIVACY: Jangan sertakan contract_value untuk staf/freelancer
    if (is_karyawan() || is_freelancer()) {
        $stmt = $db->prepare("SELECT id, name FROM projects WHERE client_id = ? AND COALESCE(is_deleted, 0) = 0 ORDER BY id DESC");
    } else {
        $stmt = $db->prepare("SELECT id, name, contract_value FROM projects WHERE client_id = ? AND COALESCE(is_deleted, 0) = 0 ORDER BY id DESC");
    }
    $stmt->execute([$clientId]);
    $projects = $stmt->fetchAll();
    echo json_encode(['success' => true, 'projects' => $projects]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid clients action']);

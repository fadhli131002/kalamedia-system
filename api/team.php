<?php
/**
 * Kalamedia Agency - Team & Employees Management API
 * Handles CRUD for In-House Staff and Freelancers, Jobdesk, Client Assignments & Login Credentials
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_auth();

$user = current_user();
$db = Database::getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Only Owner & Admin can manage team
if (!is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya Owner dan Admin yang dapat mengelola data tim.']);
    exit;
}

// Release session lock immediately to prevent concurrent request blocking
release_session();

// 1. LIST TEAM MEMBERS
if ($action === 'list') {
    try {
        $stmt = $db->query("
            SELECT u.id, u.name, u.email, u.role, u.employment_type, u.position, u.jobdesk, u.phone_wa, u.avatar, u.is_active, u.created_at,
                   u.service_specialty, u.contract_start_date, u.contract_end_date
            FROM users u
            WHERE u.role IN ('karyawan', 'freelancer') AND COALESCE(u.is_deleted, 0) = 0
            ORDER BY 
                CASE WHEN u.employment_type = 'karyawan' THEN 1 ELSE 2 END,
                u.name ASC
        ");
        $members = $stmt->fetchAll();

        // Single batch query: Fetch all employee-client assignments at once (No N+1)
        $assignedRows = $db->query("
            SELECT ec.user_id, c.id, c.company, c.name as pic_name
            FROM employee_clients ec
            JOIN clients c ON ec.client_id = c.id
            WHERE COALESCE(c.is_deleted, 0) = 0
            ORDER BY c.company ASC
        ")->fetchAll();

        $assignmentsByUser = [];
        foreach ($assignedRows as $row) {
            $uid = (int)$row['user_id'];
            $assignmentsByUser[$uid][] = [
                'id' => $row['id'],
                'company' => $row['company'],
                'pic_name' => $row['pic_name']
            ];
        }

        foreach ($members as &$m) {
            $uid = (int)$m['id'];
            $m['clients'] = $assignmentsByUser[$uid] ?? [];
            $m['client_ids'] = array_column($m['clients'], 'id');
        }
        unset($m);

        echo json_encode(['success' => true, 'members' => $members]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 2. GET SINGLE TEAM MEMBER
if ($action === 'get_member') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID karyawan tidak valid']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            SELECT id, name, email, role, employment_type, position, jobdesk, phone_wa, avatar, is_active,
                   service_specialty, contract_start_date, contract_end_date
            FROM users
            WHERE id = ? AND COALESCE(is_deleted, 0) = 0
        ");
        $stmt->execute([$id]);
        $member = $stmt->fetch();

        if (!$member) {
            echo json_encode(['success' => false, 'message' => 'Karyawan tidak ditemukan']);
            exit;
        }

        $stmtClients = $db->prepare("
            SELECT client_id FROM employee_clients WHERE user_id = ?
        ");
        $stmtClients->execute([$id]);
        $member['client_ids'] = $stmtClients->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode(['success' => true, 'member' => $member]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 3. CREATE / SAVE TEAM MEMBER
if ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $employmentType = trim($_POST['employment_type'] ?? 'karyawan');
    if (!in_array($employmentType, ['karyawan', 'freelancer'])) {
        $employmentType = 'karyawan';
    }
    $role = $employmentType; // 'karyawan' or 'freelancer'
    $position = trim($_POST['position'] ?? '');
    $jobdesk = trim($_POST['jobdesk'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $phoneWa = trim($_POST['phone_wa'] ?? '');
    $serviceSpecialty = trim($_POST['service_specialty'] ?? '');
    $contractStartDate = trim($_POST['contract_start_date'] ?? '');
    $contractEndDate = trim($_POST['contract_end_date'] ?? '');

    $clientIds = $_POST['client_ids'] ?? [];
    if (!is_array($clientIds)) {
        $clientIds = array_filter(array_map('intval', explode(',', strval($clientIds))));
    }

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Nama karyawan wajib diisi']);
        exit;
    }

    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email login wajib diisi']);
        exit;
    }

    if (empty($contractStartDate) || empty($contractEndDate)) {
        echo json_encode(['success' => false, 'message' => 'Masa kontrak kerja (Tanggal Mulai dan Akhir Kontrak) wajib diisi']);
        exit;
    }

    if ($contractEndDate < $contractStartDate) {
        echo json_encode(['success' => false, 'message' => 'Tanggal akhir kontrak tidak boleh sebelum tanggal mulai']);
        exit;
    }

    try {
        $db->beginTransaction();

        if ($id <= 0) {
            // Check unique email
            $chk = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $chk->execute([$email]);
            if ($chk->fetchColumn()) {
                echo json_encode(['success' => false, 'message' => 'Email ini sudah terdaftar di sistem. Gunakan email lain.']);
                $db->rollBack();
                exit;
            }

            if (empty($password)) {
                $password = 'kalamedia123';
            }
            $hashedPw = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $db->prepare("
                INSERT INTO users (
                    name, email, password, role, employment_type, position, jobdesk, phone_wa,
                    service_specialty, contract_start_date, contract_end_date, is_active, is_deleted
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0)
            ");
            $stmt->execute([
                $name, $email, $hashedPw, $role, $employmentType, $position, $jobdesk, $phoneWa,
                $serviceSpecialty, $contractStartDate, $contractEndDate
            ]);
            $userId = (int)$db->lastInsertId();

            // Also sync to employees table for salary slips if not exists
            $chkEmp = $db->prepare("SELECT id FROM employees WHERE email = ? LIMIT 1");
            $chkEmp->execute([$email]);
            if (!$chkEmp->fetchColumn()) {
                $db->prepare("
                    INSERT INTO employees (name, position, department, employment_type, email, phone, status, service_specialty, contract_start_date, contract_end_date)
                    VALUES (?, ?, 'Creative & Operational', ?, ?, ?, 'Active', ?, ?, ?)
                ")->execute([
                    $name, $position ?: 'Team Specialist', ($employmentType === 'freelancer' ? 'Freelance' : 'Full-time'),
                    $email, $phoneWa, $serviceSpecialty, $contractStartDate, $contractEndDate
                ]);
            }

            $actionMsg = "Karyawan / Tim Baru Ditambahkan: $name ($employmentType)";
        } else {
            // Update existing member
            $chk = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $chk->execute([$email, $id]);
            if ($chk->fetchColumn()) {
                echo json_encode(['success' => false, 'message' => 'Email ini sudah digunakan oleh akun lain.']);
                $db->rollBack();
                exit;
            }

            if (!empty($password)) {
                $hashedPw = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("
                    UPDATE users 
                    SET name = ?, email = ?, password = ?, role = ?, employment_type = ?, position = ?, jobdesk = ?, phone_wa = ?,
                        service_specialty = ?, contract_start_date = ?, contract_end_date = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name, $email, $hashedPw, $role, $employmentType, $position, $jobdesk, $phoneWa,
                    $serviceSpecialty, $contractStartDate, $contractEndDate, $id
                ]);
            } else {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET name = ?, email = ?, role = ?, employment_type = ?, position = ?, jobdesk = ?, phone_wa = ?,
                        service_specialty = ?, contract_start_date = ?, contract_end_date = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name, $email, $role, $employmentType, $position, $jobdesk, $phoneWa,
                    $serviceSpecialty, $contractStartDate, $contractEndDate, $id
                ]);
            }

            $userId = $id;

            // Sync to employees table
            $db->prepare("
                UPDATE employees 
                SET name = ?, position = ?, phone = ?, employment_type = ?,
                    service_specialty = ?, contract_start_date = ?, contract_end_date = ?
                WHERE email = ?
            ")->execute([
                $name, $position ?: 'Team Specialist', $phoneWa, ($employmentType === 'freelancer' ? 'Freelance' : 'Full-time'),
                $serviceSpecialty, $contractStartDate, $contractEndDate, $email
            ]);

            $actionMsg = "Data Karyawan Diperbarui: $name";
        }

        // Sync employee_clients mapping
        $db->prepare("DELETE FROM employee_clients WHERE user_id = ?")->execute([$userId]);
        if (!empty($clientIds)) {
            $insEc = $db->prepare("INSERT INTO employee_clients (user_id, client_id) VALUES (?, ?)");
            foreach ($clientIds as $cId) {
                $cId = (int)$cId;
                if ($cId > 0) {
                    $insEc->execute([$userId, $cId]);
                }
            }
        }

        $db->commit();
        log_activity('team', $actionMsg, "Jabatan: $position, Klien dipegang: " . count($clientIds));

        echo json_encode([
            'success' => true,
            'message' => ($id <= 0) ? 'Anggota tim baru berhasil ditambahkan beserta akses login!' : 'Data tim berhasil diperbarui!',
            'user_id' => $userId
        ]);
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()]);
        exit;
    }
}

// 4. DELETE TEAM MEMBER
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit;
    }

    try {
        $stmt = $db->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $target = $stmt->fetch();

        // Prevent deleting Owner / Admin
        $chkRole = $db->prepare("SELECT role FROM users WHERE id = ?");
        $chkRole->execute([$id]);
        $r = $chkRole->fetchColumn();
        if ($r === 'owner' || $r === 'admin') {
            echo json_encode(['success' => false, 'message' => 'Tidak dapat menghapus akun Owner atau Admin']);
            exit;
        }

        $db->beginTransaction();

        // Remove mapping
        $db->prepare("DELETE FROM employee_clients WHERE user_id = ?")->execute([$id]);

        // Soft delete user
        $db->prepare("UPDATE users SET is_deleted = 1, is_active = 0 WHERE id = ?")->execute([$id]);

        // Inactivate in employees table
        if ($target) {
            $db->prepare("UPDATE employees SET status = 'Inactive' WHERE email = ?")->execute([$target['email']]);
        }

        $db->commit();
        log_activity('team', "Karyawan Dihapus: " . ($target['name'] ?? "ID $id"), "Dihapus oleh " . $user['name']);

        echo json_encode(['success' => true, 'message' => 'Anggota tim berhasil dihapus dari sistem']);
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Aksi API tidak valid']);

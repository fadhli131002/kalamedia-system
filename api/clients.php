<?php
/**
 * Clients & Projects API Handler
 * Full CRUD (Create, Read, Update, Soft-Delete) for Clients and Projects
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_auth();

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

// 1. CREATE CLIENT
if ($action === 'create_client') {
    $name = trim($_POST['name'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($name) || empty($company) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Nama kontak, nama perusahaan, dan email wajib diisi.']);
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO clients (name, company, email, phone, address, is_deleted) VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->execute([$name, $company, $email, $phone, $address]);
        $clientId = $db->lastInsertId();

        log_activity('client', "Klien Baru Ditambahkan: $company", "Kontak: $name ($email)");

        echo json_encode([
            'success' => true,
            'message' => 'Data klien berhasil ditambahkan!',
            'client_id' => $clientId
        ]);
        exit;
    } catch (Exception $e) {
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
    $address = trim($_POST['address'] ?? '');

    if ($id <= 0 || empty($name) || empty($company)) {
        echo json_encode(['success' => false, 'message' => 'Nama PIC dan nama perusahaan wajib diisi.']);
        exit;
    }

    try {
        $stmt = $db->prepare("UPDATE clients SET name = ?, company = ?, email = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->execute([$name, $company, $email, $phone, $address, $id]);

        log_activity('client', "Data Klien Diperbarui: $company", "Nomor Kontak: $phone, PIC: $name");

        echo json_encode(['success' => true, 'message' => 'Data klien berhasil diperbarui!']);
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

// 5. CREATE PROJECT
if ($action === 'create_project') {
    $clientId = intval($_POST['client_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $contractValue = parse_client_amount($_POST['contract_value'] ?? 0);
    $targetMargin = floatval($_POST['target_margin_percent'] ?? 30.00);
    $status = $_POST['status'] ?? 'In Progress';
    $startDate = $_POST['start_date'] ?? date('Y-m-d');
    $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

    if ($clientId <= 0 || empty($name) || $contractValue <= 0) {
        echo json_encode(['success' => false, 'message' => 'Klien, nama project, dan nilai kontrak wajib diisi.']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO projects (client_id, name, contract_value, target_margin_percent, status, start_date, end_date, is_deleted)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([$clientId, $name, $contractValue, $targetMargin, $status, $startDate, $endDate]);
        $projectId = $db->lastInsertId();

        log_activity('project', "Proyek Baru Dimulai: $name", "Nilai Kontrak: " . format_rupiah($contractValue), $contractValue);

        echo json_encode([
            'success' => true,
            'message' => 'Data project berhasil dibuat!',
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

    echo json_encode(['success' => true, 'project' => $project]);
    exit;
}

// 7. UPDATE PROJECT
if ($action === 'update_project') {
    $id = intval($_POST['id'] ?? 0);
    $clientId = intval($_POST['client_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $contractValue = parse_client_amount($_POST['contract_value'] ?? 0);
    $targetMargin = floatval($_POST['target_margin_percent'] ?? 30.00);
    $status = $_POST['status'] ?? 'In Progress';
    $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

    if ($id <= 0 || $clientId <= 0 || empty($name) || $contractValue <= 0) {
        echo json_encode(['success' => false, 'message' => 'Klien, nama project, dan nilai kontrak wajib diisi.']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            UPDATE projects 
            SET client_id = ?, name = ?, contract_value = ?, target_margin_percent = ?, status = ?, start_date = ?, end_date = ?
            WHERE id = ?
        ");
        $stmt->execute([$clientId, $name, $contractValue, $targetMargin, $status, $startDate, $endDate, $id]);

        log_activity('project', "Data Proyek Diperbarui: $name", "Nilai Kontrak: " . format_rupiah($contractValue), $contractValue);

        echo json_encode(['success' => true, 'message' => 'Data project berhasil diperbarui!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
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
    $stmt = $db->prepare("SELECT id, name, contract_value FROM projects WHERE client_id = ? AND COALESCE(is_deleted, 0) = 0 ORDER BY id DESC");
    $stmt->execute([$clientId]);
    $projects = $stmt->fetchAll();
    echo json_encode(['success' => true, 'projects' => $projects]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid clients action']);

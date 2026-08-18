<?php
/**
 * Clients & Projects API Handler
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_auth();

$db = Database::getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

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
        $stmt = $db->prepare("INSERT INTO clients (name, company, email, phone, address) VALUES (?, ?, ?, ?, ?)");
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

if ($action === 'create_project') {
    $clientId = intval($_POST['client_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $contractValue = floatval($_POST['contract_value'] ?? 0);
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
            INSERT INTO projects (client_id, name, contract_value, target_margin_percent, status, start_date, end_date)
            VALUES (?, ?, ?, ?, ?, ?, ?)
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

if ($action === 'update_client') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($id <= 0 || empty($name) || empty($company)) {
        echo json_encode(['success' => false, 'message' => 'Data klien tidak valid.']);
        exit;
    }

    try {
        $stmt = $db->prepare("UPDATE clients SET name = ?, company = ?, email = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->execute([$name, $company, $email, $phone, $address, $id]);

        log_activity('client', "Data Klien Diperbarui: $company", "Nomor Kontak: $phone");

        echo json_encode(['success' => true, 'message' => 'Data klien berhasil diperbarui!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

if ($action === 'get_client_projects') {
    $clientId = intval($_GET['client_id'] ?? 0);
    $stmt = $db->prepare("SELECT id, name, contract_value FROM projects WHERE client_id = ? ORDER BY id DESC");
    $stmt->execute([$clientId]);
    $projects = $stmt->fetchAll();
    echo json_encode(['success' => true, 'projects' => $projects]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid clients action']);

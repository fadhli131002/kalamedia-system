<?php
/**
 * Users Management API (Owner Only)
 * Endpoints for creating and managing Karyawan, Freelancer, Client, and Admin accounts.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_owner();

$db = Database::getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    if ($action === 'list') {
        $stmt = $db->query("
            SELECT u.id, u.name, u.email, u.role, u.employment_type, u.phone_wa, u.client_id, u.is_active, u.created_at,
                   c.company as client_company
            FROM users u
            LEFT JOIN clients c ON u.client_id = c.id
            ORDER BY u.id DESC
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'users' => $users
        ]);
        exit;
    }

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = trim($_POST['password'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $phone_wa = trim($_POST['phone_wa'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $client_id = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;

        // Validation
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Nama lengkap wajib diisi.']);
            exit;
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Alamat email tidak valid.']);
            exit;
        }

        if (empty($password) || strlen($password) < 4) {
            echo json_encode(['success' => false, 'message' => 'Kata sandi minimal 4 karakter.']);
            exit;
        }

        $allowedRoles = ['karyawan', 'freelancer', 'client', 'admin'];
        if (!in_array($role, $allowedRoles)) {
            echo json_encode(['success' => false, 'message' => 'Peran akun tidak valid. Pilih: Karyawan, Freelance, Client, atau Admin.']);
            exit;
        }

        if ($role === 'client' && empty($client_id)) {
            echo json_encode(['success' => false, 'message' => 'Untuk akun Klien, pilih perusahaan/brand yang terhubung.']);
            exit;
        }

        // Check duplicate email
        $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email ' . $email . ' sudah digunakan oleh akun lain.']);
            exit;
        }

        $employment_type = ($role === 'freelancer') ? 'freelance' : 'internal';
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // Insert into users
        $insertStmt = $db->prepare("
            INSERT INTO users (name, email, password, role, employment_type, phone_wa, client_id, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, datetime('now'))
        ");
        $insertStmt->execute([$name, $email, $hashedPassword, $role, $employment_type, $phone_wa ?: null, $client_id]);
        $newUserId = $db->lastInsertId();

        // If role is karyawan, sync to employees table if position or salary needed
        if ($role === 'karyawan') {
            $empCheck = $db->prepare("SELECT id FROM employees WHERE email = ?");
            $empCheck->execute([$email]);
            if (!$empCheck->fetch()) {
                $empStmt = $db->prepare("
                    INSERT INTO employees (name, position, department, employment_type, email, phone, base_salary, status, created_at)
                    VALUES (?, ?, 'Creative & Tech', 'Full-time', ?, ?, 0, 'Active', datetime('now'))
                ");
                $empStmt->execute([
                    $name,
                    $position ?: 'Staff Kreatif',
                    $email,
                    $phone_wa ?: null
                ]);
            }
        }

        // Log activity
        try {
            $roleLabel = ucfirst($role);
            $logStmt = $db->prepare("INSERT INTO activities (type, title, description) VALUES ('user_create', ?, ?)");
            $logStmt->execute([
                "Akun $roleLabel Baru Dibuat",
                "Owner membuat akun $roleLabel untuk $name ($email)"
            ]);
        } catch (Exception $e) {}

        echo json_encode([
            'success' => true,
            'message' => "Akun {$name} (Peran: " . ucfirst($role) . ") berhasil dibuat!",
            'user_id' => $newUserId
        ]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $currentUserId = current_user()['id'] ?? 0;

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID pengguna tidak valid.']);
            exit;
        }

        if ($id == $currentUserId) {
            echo json_encode(['success' => false, 'message' => 'Anda tidak dapat menghapus akun Anda sendiri.']);
            exit;
        }

        // Check if user is owner
        $uStmt = $db->prepare("SELECT role, name FROM users WHERE id = ?");
        $uStmt->execute([$id]);
        $u = $uStmt->fetch();

        if (!$u) {
            echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan.']);
            exit;
        }

        if ($u['role'] === 'owner') {
            echo json_encode(['success' => false, 'message' => 'Akun Owner utama tidak dapat dihapus.']);
            exit;
        }

        $delStmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $delStmt->execute([$id]);

        echo json_encode([
            'success' => true,
            'message' => "Akun {$u['name']} berhasil dihapus."
        ]);
        exit;
    }

    if ($action === 'reset_password') {
        $id = (int)($_POST['id'] ?? 0);
        $newPassword = trim($_POST['password'] ?? '');

        if ($id <= 0 || empty($newPassword) || strlen($newPassword) < 4) {
            echo json_encode(['success' => false, 'message' => 'Password minimal 4 karakter.']);
            exit;
        }

        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
        $upStmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upStmt->execute([$hashed, $id]);

        echo json_encode([
            'success' => true,
            'message' => 'Kata sandi pengguna berhasil diubah.'
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal.']);
    exit;

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Kesalahan server: ' . $e->getMessage()]);
    exit;
}

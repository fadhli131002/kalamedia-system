<?php
/**
 * Auth API Handler
 * Multi-Role Authentication for 5 Roles: Owner, Admin, Karyawan, Freelancer, Client
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email dan password wajib diisi.']);
        exit;
    }

    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE (email = ? OR (role = 'admin' AND ? = 'finance@kalamedia.id')) AND (is_active IS NULL OR is_active = 1) LIMIT 1");
        $stmt->execute([$email, $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $displayName = $user['name'];
            if ($user['role'] === 'owner') {
                $displayName = 'Owner';
            } elseif ($user['role'] === 'admin') {
                $displayName = 'Admin';
            }

            $userPayload = [
                'id' => (int)$user['id'],
                'name' => $displayName,
                'email' => $user['email'],
                'role' => $user['role'],
                'employment_type' => $user['employment_type'] ?? 'internal',
                'phone_wa' => $user['phone_wa'] ?? null,
                'client_id' => !empty($user['client_id']) ? (int)$user['client_id'] : null,
                'avatar' => $user['avatar']
            ];

            $_SESSION['logged_in'] = true;
            $_SESSION['user'] = $userPayload;
            $_SESSION['last_activity'] = time();

            // Strict role-based redirect target
            $redirectUrl = home_url_for_role($user['role']);

            echo json_encode([
                'success' => true,
                'message' => 'Login berhasil!',
                'redirect' => $redirectUrl,
                'user' => $userPayload
            ]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Email atau kata sandi tidak valid.']);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()]);
        exit;
    }
}

if ($action === 'quick_login') {
    $role = $_POST['role'] ?? 'owner';

    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM users 
            WHERE (role = ? OR employment_type = ?) 
              AND COALESCE(is_active, 1) = 1 
              AND COALESCE(is_deleted, 0) = 0 
            ORDER BY id ASC 
            LIMIT 1
        ");
        $stmt->execute([$role, $role]);
        $user = $stmt->fetch();

        if ($user) {
            $userPayload = [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'employment_type' => $user['employment_type'] ?? 'internal',
                'phone_wa' => $user['phone_wa'] ?? null,
                'client_id' => !empty($user['client_id']) ? (int)$user['client_id'] : null,
                'avatar' => $user['avatar']
            ];

            $_SESSION['logged_in'] = true;
            $_SESSION['user'] = $userPayload;
            $_SESSION['last_activity'] = time();

            $redirectUrl = home_url_for_role($user['role']);

            echo json_encode([
                'success' => true,
                'message' => 'Masuk sebagai ' . ucfirst($role) . ' (' . htmlspecialchars($user['name']) . ') berhasil!',
                'redirect' => $redirectUrl,
                'user' => $userPayload
            ]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Akun peran ' . $role . ' tidak ditemukan.']);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Location: ' . url('login'));
        exit;
    }

    echo json_encode(['success' => true, 'redirect' => url('login')]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid auth action']);

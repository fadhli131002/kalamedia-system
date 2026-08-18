<?php
/**
 * Auth API Handler
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
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $userPayload = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'avatar' => $user['avatar']
            ];

            $_SESSION['logged_in'] = true;
            $_SESSION['user'] = $userPayload;
            $_SESSION['last_activity'] = time();

            // Strict role-based redirect target
            $redirectUrl = ($user['role'] === 'owner') ? url('owner-dashboard') : url('admin-dashboard');

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
    $email = ($role === 'owner') ? 'owner@kalamedia.id' : 'finance@kalamedia.id';

    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? OR (role = 'admin' AND email = 'admin@kalamedia.id') OR (role = ?)");
        $stmt->execute([$email, $role]);
        $user = $stmt->fetch();

        if ($user) {
            $userPayload = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'avatar' => $user['avatar']
            ];

            $_SESSION['logged_in'] = true;
            $_SESSION['user'] = $userPayload;
            $_SESSION['last_activity'] = time();

            $redirectUrl = ($user['role'] === 'owner') ? url('owner-dashboard') : url('admin-dashboard');
            echo json_encode([
                'success' => true,
                'message' => 'Masuk sebagai ' . ucfirst($role) . ' berhasil!',
                'redirect' => $redirectUrl
            ]);
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
    
    // If called directly via browser GET or regular form
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Location: ' . url('login'));
        exit;
    }

    echo json_encode(['success' => true, 'redirect' => url('login')]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid auth action']);

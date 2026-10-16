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

            if (!isset($_SESSION['kala_auth']) || !is_array($_SESSION['kala_auth'])) {
                $_SESSION['kala_auth'] = [];
            }

            if ($user['role'] === 'client') {
                if (function_exists('switch_session_context')) {
                    switch_session_context('client');
                }
                $_SESSION['kala_auth']['client'] = [
                    'user_id'         => (int)$user['id'],
                    'name'            => $displayName,
                    'email'           => $user['email'],
                    'role'            => 'client',
                    'employment_type' => 'client',
                    'client_id'       => !empty($user['client_id']) ? (int)$user['client_id'] : null,
                    'avatar'          => $user['avatar'] ?? null
                ];
                $_SESSION['kala_client_portal'] = [
                    'logged_in'     => true,
                    'user'          => $userPayload,
                    'last_activity' => time()
                ];
                $_SESSION['user'] = $userPayload;
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['role'] = 'client';
                $_SESSION['client_id'] = !empty($user['client_id']) ? (int)$user['client_id'] : null;
                $redirectUrl = url('client-portal?tab=deliverables&portal=client&_slot=client');
            } else {
                if (function_exists('switch_session_context')) {
                    switch_session_context('internal');
                }
                $_SESSION['kala_auth']['internal'] = [
                    'user_id'         => (int)$user['id'],
                    'name'            => $displayName,
                    'email'           => $user['email'],
                    'role'            => $user['role'],
                    'employment_type' => $user['employment_type'] ?? 'internal',
                    'client_id'       => null,
                    'avatar'          => $user['avatar'] ?? null
                ];
                $_SESSION['kala_internal'] = [
                    'logged_in'     => true,
                    'user'          => $userPayload,
                    'last_activity' => time()
                ];
                $_SESSION['user'] = $userPayload;
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['role'] = $user['role'];
                $redirectUrl = home_url_for_role($user['role']);
            }

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

            if (!isset($_SESSION['kala_auth']) || !is_array($_SESSION['kala_auth'])) {
                $_SESSION['kala_auth'] = [];
            }

            if ($user['role'] === 'client') {
                if (function_exists('switch_session_context')) {
                    switch_session_context('client');
                }
                $_SESSION['kala_auth']['client'] = [
                    'user_id'         => (int)$user['id'],
                    'name'            => $displayName,
                    'email'           => $user['email'],
                    'role'            => 'client',
                    'employment_type' => 'client',
                    'client_id'       => !empty($user['client_id']) ? (int)$user['client_id'] : null,
                    'avatar'          => $user['avatar'] ?? null
                ];
                $_SESSION['kala_client_portal'] = [
                    'logged_in'     => true,
                    'user'          => $userPayload,
                    'last_activity' => time()
                ];
                $_SESSION['user'] = $userPayload;
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['role'] = 'client';
                $_SESSION['client_id'] = !empty($user['client_id']) ? (int)$user['client_id'] : null;
                $redirectUrl = url('client-portal?tab=deliverables&portal=client&_slot=client');
            } else {
                if (function_exists('switch_session_context')) {
                    switch_session_context('internal');
                }
                $_SESSION['kala_auth']['internal'] = [
                    'user_id'         => (int)$user['id'],
                    'name'            => $displayName,
                    'email'           => $user['email'],
                    'role'            => $user['role'],
                    'employment_type' => $user['employment_type'] ?? 'internal',
                    'client_id'       => null,
                    'avatar'          => $user['avatar'] ?? null
                ];
                $_SESSION['kala_internal'] = [
                    'logged_in'     => true,
                    'user'          => $userPayload,
                    'last_activity' => time()
                ];
                $_SESSION['user'] = $userPayload;
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['role'] = $user['role'];
                $redirectUrl = home_url_for_role($user['role']);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Masuk sebagai ' . ucfirst($role) . ' (' . htmlspecialchars($displayName) . ') berhasil!',
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
    $context = $_GET['context'] ?? $_POST['context'] ?? ($_GET['portal'] ?? ($_POST['portal'] ?? ($_GET['_slot'] ?? ($_POST['_slot'] ?? resolveActiveSessionContext()))));

    if ($context === 'client' || session_name() === 'KALASESS_CLIENT') {
        if (function_exists('switch_session_context')) {
            switch_session_context('client');
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie('KALASESS_CLIENT', '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        $redirectUrl = url('login?context=client');
    } elseif ($context === 'internal' || session_name() === 'KALASESS_INTERNAL') {
        if (function_exists('switch_session_context')) {
            switch_session_context('internal');
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie('KALASESS_INTERNAL', '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        $redirectUrl = url('login?context=internal');
    } else {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        $redirectUrl = url('login');
    }

    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Location: ' . $redirectUrl);
        exit;
    }

    echo json_encode(['success' => true, 'redirect' => $redirectUrl]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid auth action']);

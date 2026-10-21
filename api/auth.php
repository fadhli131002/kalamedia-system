<?php
/**
 * Auth API Handler
 * Multi-Role Authentication with Isolated Per-Tab Account Token (?_u=u_<id>)
 * 5 Roles: Owner, Admin, Karyawan, Freelancer, Client
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
                'id'              => (int)$user['id'],
                'name'            => $displayName,
                'email'           => $user['email'],
                'role'            => $user['role'],
                'employment_type' => $user['employment_type'] ?? ($user['role'] === 'client' ? 'client' : 'internal'),
                'phone_wa'        => $user['phone_wa'] ?? null,
                'client_id'       => !empty($user['client_id']) ? (int)$user['client_id'] : null,
                'avatar'          => $user['avatar'] ?? null
            ];

            if ($user['role'] === 'client') {
                // Strict validation: Check client contract expiration
                $clientId = !empty($user['client_id']) ? (int)$user['client_id'] : null;
                $cRow = null;
                if ($clientId) {
                    $stmtC = $db->prepare("SELECT id, company, contract_end_date, contract_status, is_deleted FROM clients WHERE id = ? LIMIT 1");
                    $stmtC->execute([$clientId]);
                    $cRow = $stmtC->fetch(PDO::FETCH_ASSOC);
                } else {
                    $stmtC = $db->prepare("SELECT id, company, contract_end_date, contract_status, is_deleted FROM clients WHERE email = ? LIMIT 1");
                    $stmtC->execute([$user['email']]);
                    $cRow = $stmtC->fetch(PDO::FETCH_ASSOC);
                }

                if ($cRow) {
                    $today = date('Y-m-d');
                    $isExpired = false;
                    if (!empty($cRow['contract_end_date']) && $today > $cRow['contract_end_date']) {
                        $isExpired = true;
                        $db->prepare("UPDATE clients SET contract_status = 'expired', status = 'expired' WHERE id = ?")->execute([$cRow['id']]);
                        $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$user['id']]);
                    } elseif (($cRow['contract_status'] ?? '') === 'expired' || ($cRow['is_deleted'] ?? 0) == 1) {
                        $isExpired = true;
                        $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$user['id']]);
                    }

                    if ($isExpired) {
                        $formattedDate = !empty($cRow['contract_end_date']) ? date('d F Y', strtotime($cRow['contract_end_date'])) : 'tanggal yang ditentukan';
                        echo json_encode([
                            'success' => false,
                            'message' => "Masa kontrak layanan Anda telah berakhir pada {$formattedDate}. Silakan hubungi tim Kalamedia untuk perpanjangan kontrak."
                        ]);
                        exit;
                    }
                }
            }

            // 1. Simpan di $_SESSION['kala_accounts'][$uid_key] (DILARANG KERAS MENGHAPUS AKUN LAIN!)
            $uidKey = 'u_' . (int)$user['id'];
            if (!isset($_SESSION['kala_accounts']) || !is_array($_SESSION['kala_accounts'])) {
                $_SESSION['kala_accounts'] = [];
            }
            $_SESSION['kala_accounts'][$uidKey] = [
                'user_id'         => (int)$user['id'],
                'id'              => (int)$user['id'],
                'name'            => $displayName,
                'email'           => $user['email'],
                'role'            => $user['role'],
                'employment_type' => $userPayload['employment_type'],
                'phone_wa'        => $user['phone_wa'] ?? null,
                'client_id'       => !empty($user['client_id']) ? (int)$user['client_id'] : null,
                'avatar'          => $user['avatar'] ?? null,
                'login_time'      => time()
            ];

            // 2. Set current active session for backwards compatibility
            $_SESSION['user'] = $userPayload;
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['client_id'] = !empty($user['client_id']) ? (int)$user['client_id'] : null;

            // 3. Tentukan Redirect URL dengan identitas tab ?_u=u_<id>
            if ($user['role'] === 'client') {
                $redirectUrl = url('client-portal?tab=deliverables&_u=' . $uidKey, false);
            } elseif ($user['role'] === 'owner') {
                $redirectUrl = url('owner-dashboard?_u=' . $uidKey, false);
            } elseif ($user['role'] === 'admin') {
                $redirectUrl = url('admin-dashboard?_u=' . $uidKey, false);
            } else {
                $redirectUrl = url('tasks?_u=' . $uidKey, false);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Login berhasil!',
                'redirect' => $redirectUrl,
                'uid_key' => $uidKey,
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
                'id'              => (int)$user['id'],
                'name'            => $displayName,
                'email'           => $user['email'],
                'role'            => $user['role'],
                'employment_type' => $user['employment_type'] ?? ($user['role'] === 'client' ? 'client' : 'internal'),
                'phone_wa'        => $user['phone_wa'] ?? null,
                'client_id'       => !empty($user['client_id']) ? (int)$user['client_id'] : null,
                'avatar'          => $user['avatar'] ?? null
            ];

            // 1. Simpan di $_SESSION['kala_accounts'][$uid_key] (DILARANG KERAS MENGHAPUS AKUN LAIN!)
            $uidKey = 'u_' . (int)$user['id'];
            if (!isset($_SESSION['kala_accounts']) || !is_array($_SESSION['kala_accounts'])) {
                $_SESSION['kala_accounts'] = [];
            }
            $_SESSION['kala_accounts'][$uidKey] = [
                'user_id'         => (int)$user['id'],
                'id'              => (int)$user['id'],
                'name'            => $displayName,
                'email'           => $user['email'],
                'role'            => $user['role'],
                'employment_type' => $userPayload['employment_type'],
                'phone_wa'        => $user['phone_wa'] ?? null,
                'client_id'       => !empty($user['client_id']) ? (int)$user['client_id'] : null,
                'avatar'          => $user['avatar'] ?? null,
                'login_time'      => time()
            ];

            // 2. Set current active session for backwards compatibility
            $_SESSION['user'] = $userPayload;
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['client_id'] = !empty($user['client_id']) ? (int)$user['client_id'] : null;

            // 3. Tentukan Redirect URL dengan identitas tab ?_u=u_<id>
            if ($user['role'] === 'client') {
                $redirectUrl = url('client-portal?tab=deliverables&_u=' . $uidKey, false);
            } elseif ($user['role'] === 'owner') {
                $redirectUrl = url('owner-dashboard?_u=' . $uidKey, false);
            } elseif ($user['role'] === 'admin') {
                $redirectUrl = url('admin-dashboard?_u=' . $uidKey, false);
            } else {
                $redirectUrl = url('tasks?_u=' . $uidKey, false);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Masuk sebagai ' . ucfirst($role) . ' (' . htmlspecialchars($displayName) . ') berhasil!',
                'redirect' => $redirectUrl,
                'uid_key' => $uidKey,
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
    // Isolated Logout: Hanya hapus $_SESSION['kala_accounts'][$uid_key] milik tab itu saja!
    $uidKey = $_GET['_u'] ?? $_POST['_u'] ?? ($_SERVER['HTTP_X_KALA_UID'] ?? '');
    if (!empty($uidKey) && is_numeric($uidKey)) {
        $uidKey = 'u_' . $uidKey;
    }

    $loggedOutRole = '';
    if (!empty($uidKey) && isset($_SESSION['kala_accounts'][$uidKey])) {
        $loggedOutRole = $_SESSION['kala_accounts'][$uidKey]['role'] ?? '';
        unset($_SESSION['kala_accounts'][$uidKey]);
    } else {
        $curr = current_user();
        if ($curr && !empty($curr['id'])) {
            $currKey = 'u_' . $curr['id'];
            $loggedOutRole = $curr['role'] ?? '';
            unset($_SESSION['kala_accounts'][$currKey]);
        }
    }

    if (empty($_SESSION['kala_accounts'])) {
        $_SESSION['kala_accounts'] = [];
        unset($_SESSION['user'], $_SESSION['user_id'], $_SESSION['role'], $_SESSION['client_id']);
    }

    $redirectUrl = url('login', false);
    if ($loggedOutRole === 'client' || (!empty($_GET['context']) && $_GET['context'] === 'client')) {
        $redirectUrl = url('login?context=client', false);
    }

    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Location: ' . $redirectUrl);
        exit;
    }

    echo json_encode([
        'success' => true,
        'redirect' => $redirectUrl,
        'logged_out_uid' => $uidKey
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Aksi auth tidak valid']);

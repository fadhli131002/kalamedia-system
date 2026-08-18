<?php
/**
 * Authentication & Strict Role-Based Access Control (RBAC)
 */

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/database.php';

function is_logged_in(): bool {
    return !empty($_SESSION['logged_in']) && !empty($_SESSION['user']);
}

function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return $_SESSION['user'] ?? null;
}

function is_owner(): bool {
    if (!is_logged_in()) {
        return false;
    }
    $role = $_SESSION['user']['role'] ?? '';
    return ($role === 'owner');
}

function is_admin(): bool {
    return is_logged_in(); // Both Owner and Finance have operational permissions
}

function current_portal(): string {
    return is_owner() ? 'owner' : 'finance';
}

function require_auth() {
    if (!is_logged_in()) {
        header('Location: ' . url('login'));
        exit;
    }
}

function require_owner() {
    require_auth();
    if (!is_owner()) {
        // Finance account is strictly forbidden from accessing Owner Executive views
        header('Location: ' . url('admin-dashboard'));
        exit;
    }
}

function require_admin() {
    require_auth();
}

function log_activity($type, $title, $description = null, $amount = null) {
    try {
        $db = Database::getConnection();
        $user = current_user();
        $userId = $user ? $user['id'] : null;
        $stmt = $db->prepare("INSERT INTO activities (user_id, type, title, description, amount) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $type, $title, $description, $amount]);
    } catch (Exception $e) {
        // silent fail
    }
}

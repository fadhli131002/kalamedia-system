<?php
/**
 * Kalamedia Agency Financial & Project Management System
 * Front Controller & Router
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

// Determine requested route
$route = $_GET['page'] ?? '';

if (empty($route)) {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $base = get_base_url();
    $sub = trim(str_replace($base, '', $uri), '/');
    if (!empty($sub) && $sub !== 'index.php') {
        $route = $sub;
    }
}

// Default route resolution
if (empty($route)) {
    if (is_logged_in()) {
        $route = is_owner() ? 'owner-dashboard' : 'admin-dashboard';
    } else {
        $route = 'login';
    }
}

// Expose current page globally
$currentPage = $route;
$GLOBALS['currentPage'] = $route;

// Router dispatch
switch ($route) {
    case 'login':
        require_once __DIR__ . '/views/login.php';
        break;

    case 'logout':
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        header('Location: ' . url('login'));
        exit;

    case 'owner-dashboard':
        require_owner();
        require_once __DIR__ . '/views/owner_dashboard.php';
        break;

    case 'admin-dashboard':
        require_admin();
        require_once __DIR__ . '/views/admin_dashboard.php';
        break;

    case 'invoices':
        require_once __DIR__ . '/views/invoices.php';
        break;

    case 'invoice-view':
        require_once __DIR__ . '/views/invoice_view.php';
        break;

    case 'expenses':
        require_once __DIR__ . '/views/expenses.php';
        break;

    case 'salaries':
        require_once __DIR__ . '/views/salaries.php';
        break;

    case 'clients':
        require_once __DIR__ . '/views/clients.php';
        break;

    case 'content-calendar':
    case 'content-planner':
        require_once __DIR__ . '/views/content_dashboard.php';
        break;

    case 'settings':
        require_owner();
        require_once __DIR__ . '/views/settings.php';
        break;

    default:
        // If unknown route, redirect based on login status
        if (is_logged_in()) {
            header('Location: ' . (is_owner() ? url('owner-dashboard') : url('admin-dashboard')));
        } else {
            header('Location: ' . url('login'));
        }
        exit;
}

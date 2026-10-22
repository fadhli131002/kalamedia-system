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
        header('Location: ' . home_url_for_role());
        exit;
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
        // Isolated Logout: Only removes this specific tab's account from $_SESSION['kala_accounts']
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
        header('Location: ' . $redirectUrl);
        exit;

    case 'client-portal':
        require_client();
        require_once __DIR__ . '/views/client_portal.php';
        break;

    case 'tasks':
    case 'team-workspace':
        require_worker();
        require_once __DIR__ . '/views/tasks.php';
        break;

    case 'dashboard':
        $context = resolveActiveSessionContext();
        if (is_owner($context)) {
            require_once __DIR__ . '/views/owner_dashboard.php';
        } else {
            require_once __DIR__ . '/views/admin_dashboard.php';
        }
        break;

    case 'owner-dashboard':
        require_owner();
        require_once __DIR__ . '/views/owner_dashboard.php';
        break;

    case 'admin-dashboard':
        require_admin();
        require_once __DIR__ . '/views/admin_dashboard.php';
        break;

    case 'invoices':
        require_auth('internal');
        $invRole = current_user_role('internal');
        if ($invRole === 'client') {
            header('Location: ' . url('client-portal?tab=invoices&portal=client&_slot=client'));
            exit;
        }
        if ($invRole === 'karyawan' || $invRole === 'freelancer') {
            // Workers forbidden from viewing invoice amounts
            header('Location: ' . url('tasks'));
            exit;
        }
        require_once __DIR__ . '/views/invoices.php';
        break;

    case 'invoice-view':
        require_auth('internal');
        $invRole = current_user_role('internal');
        if ($invRole === 'client') {
            header('Location: ' . url('client-portal?tab=invoices&portal=client&_slot=client'));
            exit;
        }
        if ($invRole === 'karyawan' || $invRole === 'freelancer') {
            // Workers forbidden from viewing invoice amounts
            header('Location: ' . url('tasks'));
            exit;
        }
        require_once __DIR__ . '/views/invoice_view.php';
        break;

    case 'expenses':
        require_admin();
        require_once __DIR__ . '/views/expenses.php';
        break;

    case 'payroll':
    case 'salaries':
        require_admin();
        require_once __DIR__ . '/views/salaries.php';
        break;

    case 'salary-view':
        require_admin();
        require_once __DIR__ . '/views/salary_view.php';
        break;

    case 'voucher-view':
        require_admin();
        require_once __DIR__ . '/views/voucher_view.php';
        break;

    case 'ads-voucher-view':
        require_admin();
        require_once __DIR__ . '/views/ads_voucher_view.php';
        break;

    case 'clients':
        require_admin();
        require_once __DIR__ . '/views/clients.php';
        break;

    case 'team':
    case 'employees-management':
        require_admin();
        require_once __DIR__ . '/views/team.php';
        break;

    case 'calendar':
    case 'content-calendar':
    case 'content-planner':
        require_auth('internal');
        if (is_ads_specialist()) {
            flash_error('Menu Kalender Konten tidak digunakan untuk Spesialis Iklan. Silakan gunakan menu Report Ads.');
            header('Location: ' . url('reports?tab=ads_report&_slot=internal'));
            exit;
        }
        require_once __DIR__ . '/views/content_dashboard.php';
        break;

    case 'reports':
        require_auth('internal');
        require_once __DIR__ . '/views/reports.php';
        break;

    case 'reports-form':
        require_admin();
        require_once __DIR__ . '/views/reports_form.php';
        break;

    case 'report-view':
    case 'report-deck':
        require_auth('internal');
        require_once __DIR__ . '/views/report_deck.php';
        break;

    case 'settings':
        require_owner();
        require_once __DIR__ . '/views/settings.php';
        break;

    default:
        // If unknown route, redirect based on login status
        if (is_logged_in('internal')) {
            header('Location: ' . home_url_for_role(current_user_role('internal')));
        } elseif (is_logged_in('client')) {
            header('Location: ' . url('client-portal?portal=client&_slot=client'));
        } else {
            header('Location: ' . url('login'));
        }
        exit;
}

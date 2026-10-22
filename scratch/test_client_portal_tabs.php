<?php
/**
 * Test All Tabs in Client Portal
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$db = Database::getConnection();
$client = $db->query("SELECT * FROM clients WHERE COALESCE(is_deleted,0)=0 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$clientId = (int)($client['id'] ?? 1);

// Ensure client user exists
$clientUser = $db->query("SELECT * FROM users WHERE role = 'client' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$clientUserId = (int)($clientUser['id'] ?? 5);

$tabs = ['deliverables', 'calendar', 'invoices', 'reports', 'finance'];

echo "=== TESTING ALL TABS IN CLIENT PORTAL ===\n\n";

foreach ($tabs as $tab) {
    $code = '<?php
    error_reporting(E_ALL);
    ini_set("display_errors", "1");
    $_SERVER["DOCUMENT_ROOT"] = "c:/xampp/htdocs/Kalamedia";
    $_SERVER["SCRIPT_NAME"] = "/index.php";
    $_SERVER["REQUEST_URI"] = "/client-portal?tab=' . $tab . '&portal=client&_slot=client";
    $_SERVER["REQUEST_METHOD"] = "GET";
    $_SERVER["HTTP_HOST"] = "localhost";
    
    require_once "config/app.php";
    require_once "config/database.php";
    require_once "config/auth.php";

    $_SESSION["kala_accounts"] = [
        "u_client" => [
            "user_id" => ' . $clientUserId . ',
            "role" => "client",
            "name" => "Client User",
            "email" => "client@kalamedia.id",
            "client_id" => ' . $clientId . '
        ]
    ];
    $_SESSION["kala_auth"] = [
        "client" => [
            "user_id" => ' . $clientUserId . ',
            "role" => "client",
            "name" => "Client User",
            "email" => "client@kalamedia.id",
            "client_id" => ' . $clientId . '
        ]
    ];
    $_SESSION["logged_in"] = true;
    $_SESSION["user"] = [
        "id" => ' . $clientUserId . ',
        "role" => "client",
        "name" => "Client User",
        "email" => "client@kalamedia.id",
        "client_id" => ' . $clientId . '
    ];
    $_GET = ["tab" => "' . $tab . '", "_slot" => "client", "_u" => "u_client"];

    $notices = [];
    set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$notices) {
        if (error_reporting() === 0) return false;
        $notices[] = "Line $errline: $errstr in " . basename($errfile);
        return true;
    });

    ob_start();
    try {
        include "views/client_portal.php";
        $out = ob_get_clean();
        restore_error_handler();
        if (!empty($notices)) {
            echo "NOTICES: " . implode("; ", $notices) . "\n";
        }
        echo "SUCCESS: " . strlen($out) . " bytes\n";
    } catch (\Throwable $e) {
        ob_end_clean();
        restore_error_handler();
        echo "EXCEPTION: " . $e->getMessage() . " at " . basename($e->getFile()) . ":" . $e->getLine() . "\n";
    }
    ';

    $tmp = sys_get_temp_dir() . '/test_cp_' . $tab . '.php';
    file_put_contents($tmp, $code);

    $out = shell_exec('php "' . $tmp . '" 2>&1');
    @unlink($tmp);

    $outTrim = trim($out);
    if (strpos($outTrim, 'SUCCESS:') !== false && strpos($outTrim, 'NOTICES:') === false && strpos($outTrim, 'Fatal error') === false) {
        preg_match('/SUCCESS:\s*(\d+)/', $outTrim, $m);
        echo " [PASS] Tab `$tab`: {$m[1]} bytes rendered with 0 notices\n";
    } else {
        echo " [FAIL] Tab `$tab`:\n $outTrim\n";
    }
}

<?php
/**
 * Test All API Endpoints & Actions
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$db = Database::getConnection();

// Get valid test IDs
$clientId = (int)$db->query("SELECT id FROM clients WHERE COALESCE(is_deleted,0)=0 LIMIT 1")->fetchColumn() ?: 1;
$projId = (int)$db->query("SELECT id FROM projects WHERE COALESCE(is_deleted,0)=0 LIMIT 1")->fetchColumn() ?: 1;
$invId = (int)$db->query("SELECT id FROM invoices WHERE COALESCE(is_deleted,0)=0 LIMIT 1")->fetchColumn() ?: 1;
$salaryId = (int)$db->query("SELECT id FROM salaries WHERE COALESCE(is_deleted,0)=0 LIMIT 1")->fetchColumn() ?: 1;
$payoutId = (int)$db->query("SELECT id FROM freelancer_payouts WHERE COALESCE(is_deleted,0)=0 LIMIT 1")->fetchColumn() ?: 1;
$spendId = (int)$db->query("SELECT id FROM ads_spend WHERE COALESCE(is_deleted,0)=0 LIMIT 1")->fetchColumn() ?: 1;
$taskId = (int)$db->query("SELECT id FROM tasks WHERE COALESCE(is_deleted,0)=0 LIMIT 1")->fetchColumn() ?: 1;
$reportId = (int)$db->query("SELECT id FROM performance_reports WHERE COALESCE(is_deleted,0)=0 LIMIT 1")->fetchColumn() ?: 1;

$endpoints = [
    'analytics.php' => [
        ['action' => 'kpi_cards'],
        ['action' => 'revenue_chart', 'filter' => 'monthly'],
        ['action' => 'profit_margin'],
        ['action' => 'ar_tracker']
    ],
    'invoices.php' => [
        ['action' => 'list'],
        ['action' => 'get_details', 'id' => $invId]
    ],
    'clients.php' => [
        ['action' => 'list'],
        ['action' => 'get_client', 'id' => $clientId]
    ],
    'expenses.php' => [
        ['action' => 'get_payout', 'id' => $payoutId],
        ['action' => 'get_spend', 'id' => $spendId]
    ],
    'salaries.php' => [
        ['action' => 'list'],
        ['action' => 'get_slip_data', 'id' => $salaryId]
    ],
    'team.php' => [
        ['action' => 'list'],
        ['action' => 'get_member', 'id' => 1]
    ],
    'content.php' => [
        ['action' => 'get_events', 'client_id' => $clientId],
        ['action' => 'get_clients']
    ],
    'portal_tasks.php' => [
        ['action' => 'list_tasks', 'client_id' => $clientId],
        ['action' => 'get_task', 'id' => $taskId]
    ],
    'ads_tracker.php' => [
        ['action' => 'list'],
        ['action' => 'get_client_summary', 'client_id' => $clientId]
    ],
    'reports.php' => [
        ['action' => 'list'],
        ['action' => 'get_report', 'id' => $reportId]
    ],
    'users.php' => [
        ['action' => 'get_profile'],
        ['action' => 'list']
    ]
];

echo "=====================================================\n";
echo "           API ENDPOINT & ACTION AUDIT               \n";
echo "=====================================================\n\n";

$pass = 0;
$fail = 0;

foreach ($endpoints as $file => $actionList) {
    foreach ($actionList as $params) {
        $actionName = $params['action'] ?? 'default';
        $label = "$file ?action=$actionName";

        $code = '<?php
        error_reporting(E_ALL);
        ini_set("display_errors", "1");
        $_SERVER["DOCUMENT_ROOT"] = "c:/xampp/htdocs/Kalamedia";
        $_SERVER["SCRIPT_NAME"] = "/api/' . $file . '";
        $_SERVER["REQUEST_URI"] = "/api/' . $file . '";
        $_SERVER["REQUEST_METHOD"] = "GET";
        $_SERVER["HTTP_HOST"] = "localhost";
        $_SERVER["HTTP_ACCEPT"] = "application/json";

        require_once "config/app.php";
        require_once "config/database.php";
        require_once "config/auth.php";

        $isOwnerRole = ("' . $file . '" === "users.php");
        $sessionRole = $isOwnerRole ? "owner" : "admin";
        $sessionUserId = $isOwnerRole ? 1 : 2;
        $_SESSION["kala_accounts"] = [
            "u_user" => [
                "user_id" => $sessionUserId,
                "role" => $sessionRole,
                "name" => ucfirst($sessionRole) . " User",
                "email" => $sessionRole . "@kalamedia.id",
                "employment_type" => "internal"
            ]
        ];
        $_SESSION["kala_auth"] = [
            "internal" => [
                "user_id" => $sessionUserId,
                "role" => $sessionRole,
                "name" => ucfirst($sessionRole) . " User",
                "email" => $sessionRole . "@kalamedia.id"
            ]
        ];
        $_SESSION["logged_in"] = true;
        $_SESSION["user"] = [
            "id" => $sessionUserId,
            "role" => $sessionRole,
            "name" => ucfirst($sessionRole) . " User",
            "email" => $sessionRole . "@kalamedia.id"
        ];
        $_GET = ' . var_export($params, true) . ';
        $_GET["_u"] = "u_user";

        ob_start();
        try {
            require "api/' . $file . '";
            $out = ob_get_clean();
            echo $out;
        } catch (\Throwable $e) {
            ob_end_clean();
            echo "API_EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
        }
        ';

        $tmp = sys_get_temp_dir() . '/audit_api_' . md5($label) . '.php';
        file_put_contents($tmp, $code);

        $cmd = 'php "' . $tmp . '" 2>&1';
        $output = shell_exec($cmd);
        @unlink($tmp);

        $outputTrim = trim($output);
        $json = json_decode($outputTrim, true);

        if ($json !== null) {
            if (isset($json['success']) && $json['success'] === false && strpos($json['message'] ?? '', 'Unknown') !== false) {
                echo " [FAIL] $label: {$json['message']}\n";
                $fail++;
            } else {
                echo " [PASS] $label (Valid JSON)\n";
                $pass++;
            }
        } elseif (strpos($outputTrim, 'API_EXCEPTION:') !== false || strpos($outputTrim, 'Fatal error') !== false || strpos($outputTrim, 'Parse error') !== false) {
            echo " [FAIL] $label\n";
            echo "        Output: " . substr($outputTrim, 0, 300) . "\n";
            $fail++;
        } else {
            echo " [PASS] $label (Response size: " . strlen($outputTrim) . " bytes)\n";
            $pass++;
        }
    }
}

echo "\nSummary: $pass PASSED, $fail FAILED\n";

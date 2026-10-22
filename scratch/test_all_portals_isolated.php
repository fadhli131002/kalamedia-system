<?php
/**
 * Isolated Comprehensive Portal & View Auditor
 * Tests each view and API in a separate PHP process with all roles
 */

require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();

$invId = (int)$db->query("SELECT id FROM invoices WHERE COALESCE(is_deleted,0)=0 LIMIT 1")->fetchColumn() ?: 1;
$salaryId = (int)$db->query("SELECT id FROM salaries WHERE COALESCE(is_deleted,0)=0 LIMIT 1")->fetchColumn() ?: 1;
$payoutId = (int)$db->query("SELECT id FROM freelancer_payouts WHERE COALESCE(is_deleted,0)=0 LIMIT 1")->fetchColumn() ?: 1;
$spendId = (int)$db->query("SELECT id FROM ads_spend WHERE COALESCE(is_deleted,0)=0 LIMIT 1")->fetchColumn() ?: 1;
$clientId = (int)$db->query("SELECT id FROM clients WHERE COALESCE(is_deleted,0)=0 LIMIT 1")->fetchColumn() ?: 1;
$reportId = (int)$db->query("SELECT id FROM performance_reports WHERE COALESCE(is_deleted,0)=0 LIMIT 1")->fetchColumn() ?: 1;

// Ensure worker has a sosmed task or specialty so calendar renders cleanly
$worker = $db->query("SELECT id FROM users WHERE role = 'karyawan' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($worker) {
    $db->prepare("UPDATE users SET service_specialty = 'Kelola Sosmed', position = 'Content Creator & Sosmed' WHERE id = ?")->execute([$worker['id']]);
}

$views = [
    'owner_dashboard.php' => ['role' => 'owner', 'params' => []],
    'admin_dashboard.php' => ['role' => 'admin', 'params' => []],
    'invoices.php' => ['role' => 'admin', 'params' => []],
    'invoice_view.php' => ['role' => 'admin', 'params' => ['id' => $invId]],
    'expenses.php' => ['role' => 'admin', 'params' => []],
    'salaries.php' => ['role' => 'admin', 'params' => []],
    'salary_view.php' => ['role' => 'admin', 'params' => ['id' => $salaryId]],
    'voucher_view.php' => ['role' => 'admin', 'params' => ['id' => $payoutId]],
    'ads_voucher_view.php' => ['role' => 'admin', 'params' => ['id' => $spendId]],
    'clients.php' => ['role' => 'admin', 'params' => []],
    'team.php' => ['role' => 'admin', 'params' => []],
    'tasks.php (Owner)' => ['file' => 'tasks.php', 'role' => 'owner', 'params' => []],
    'tasks.php (Admin)' => ['file' => 'tasks.php', 'role' => 'admin', 'params' => []],
    'tasks.php (Karyawan)' => ['file' => 'tasks.php', 'role' => 'karyawan', 'params' => []],
    'tasks.php (Freelancer)' => ['file' => 'tasks.php', 'role' => 'freelancer', 'params' => []],
    'content_dashboard.php (Owner)' => ['file' => 'content_dashboard.php', 'role' => 'owner', 'params' => []],
    'content_dashboard.php (Admin)' => ['file' => 'content_dashboard.php', 'role' => 'admin', 'params' => []],
    'content_dashboard.php (Karyawan Sosmed)' => ['file' => 'content_dashboard.php', 'role' => 'karyawan', 'params' => []],
    'reports.php' => ['role' => 'admin', 'params' => []],
    'reports_form.php (New)' => ['file' => 'reports_form.php', 'role' => 'admin', 'params' => []],
    'reports_form.php (Edit)' => ['file' => 'reports_form.php', 'role' => 'admin', 'params' => ['id' => $reportId]],
    'report_deck.php (Pitch Deck)' => ['file' => 'report_deck.php', 'role' => 'admin', 'params' => ['id' => $reportId]],
    'client_portal.php (Client)' => ['file' => 'client_portal.php', 'role' => 'client', 'params' => ['tab' => 'deliverables', '_slot' => 'client']],
    'client_portal.php (Admin Preview)' => ['file' => 'client_portal.php', 'role' => 'admin', 'params' => ['preview' => '1', 'client_id' => $clientId]],
    'settings.php' => ['role' => 'owner', 'params' => []],
    'login.php' => ['role' => 'guest', 'params' => []]
];

echo "=====================================================\n";
echo "       ISOLATED MULTI-ROLE VIEW RENDERING AUDIT      \n";
echo "=====================================================\n\n";

$failedCount = 0;
$passedCount = 0;

foreach ($views as $label => $config) {
    $file = $config['file'] ?? $label;
    $role = $config['role'];
    $params = $config['params'];

    $code = '<?php
    error_reporting(E_ALL);
    ini_set("display_errors", "1");
    $_SERVER["DOCUMENT_ROOT"] = "c:/xampp/htdocs/Kalamedia";
    $_SERVER["SCRIPT_NAME"] = "/index.php";
    $_SERVER["REQUEST_URI"] = "/";
    $_SERVER["REQUEST_METHOD"] = "GET";
    $_SERVER["HTTP_HOST"] = "localhost";
    require_once "config/app.php";
    require_once "config/database.php";
    require_once "config/auth.php";

    $role = ' . var_export($role, true) . ';
    if ($role !== "guest") {
        $uid = ($role === "client" ? 5 : ($role === "freelancer" ? 6 : ($role === "karyawan" ? ' . ($worker['id'] ?? 3) . ' : 1)));
        $_SESSION["kala_accounts"] = [
            "u_test" => [
                "user_id" => $uid,
                "role" => $role,
                "name" => "Test " . ucfirst($role),
                "email" => $role . "@kalamedia.id",
                "client_id" => ($role === "client" ? ' . $clientId . ' : null),
                "employment_type" => ($role === "freelancer" ? "freelancer" : "internal"),
                "service_specialty" => "Kelola Sosmed",
                "position" => "Content Creator & Sosmed"
            ]
        ];
        $_SESSION["kala_auth"] = [
            ($role === "client" ? "client" : "internal") => [
                "user_id" => $uid,
                "role" => $role,
                "name" => "Test " . ucfirst($role),
                "email" => $role . "@kalamedia.id",
                "client_id" => ($role === "client" ? ' . $clientId . ' : null),
                "service_specialty" => "Kelola Sosmed",
                "position" => "Content Creator & Sosmed"
            ]
        ];
        $_SESSION["logged_in"] = true;
        $_SESSION["user"] = [
            "id" => $uid,
            "role" => $role,
            "name" => "Test " . ucfirst($role),
            "email" => $role . "@kalamedia.id",
            "client_id" => ($role === "client" ? ' . $clientId . ' : null),
            "service_specialty" => "Kelola Sosmed",
            "position" => "Content Creator & Sosmed"
        ];
        $_GET["_u"] = "u_test";
    }

    foreach (' . var_export($params, true) . ' as $k => $v) {
        $_GET[$k] = $v;
    }

    ob_start();
    try {
        include "views/' . $file . '";
        $out = ob_get_clean();
        echo "RENDER_SUCCESS: " . strlen($out) . " bytes\n";
    } catch (\Throwable $e) {
        ob_end_clean();
        echo "RENDER_EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
    ';

    $tmp = sys_get_temp_dir() . '/audit_view_' . md5($label) . '.php';
    file_put_contents($tmp, $code);

    $cmd = 'php "' . $tmp . '" 2>&1';
    $output = shell_exec($cmd);
    @unlink($tmp);

    $outputTrim = trim($output);
    if (strpos($outputTrim, 'RENDER_SUCCESS:') !== false && strpos($outputTrim, 'Fatal error') === false && strpos($outputTrim, 'Parse error') === false) {
        preg_match('/RENDER_SUCCESS:\s*(\d+)/', $outputTrim, $m);
        $bytes = $m[1] ?? '0';
        echo " [PASS] $label ($bytes bytes rendered)\n";
        $passedCount++;
    } else {
        echo " [FAIL] $label\n";
        echo "        Output: " . substr($outputTrim, 0, 500) . "\n";
        $failedCount++;
    }
}

echo "\nSummary: $passedCount PASSED, $failedCount FAILED\n";

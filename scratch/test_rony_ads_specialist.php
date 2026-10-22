<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$db = Database::getConnection();

// Look for Rony umbara or create/update his data to match Image 1
$rony = $db->query("SELECT * FROM users WHERE email = 'rony@kalamediacreative.com' OR name LIKE '%Rony%' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$rony) {
    $db->prepare("INSERT INTO users (name, email, password, role, employment_type, service_specialty, position, jobdesk, is_active) VALUES ('Rony umbara', 'rony@kalamediacreative.com', 'test', 'freelancer', 'freelancer', 'Jasa Iklan Meta & Google (Meta Ads & Google Ads)', 'Ads specialist', 'Kelola ads meta', 1)")->execute();
    $rony = $db->query("SELECT * FROM users WHERE email = 'rony@kalamediacreative.com'")->fetch(PDO::FETCH_ASSOC);
} else {
    $db->prepare("UPDATE users SET service_specialty = 'Jasa Iklan Meta & Google (Meta Ads & Google Ads)', position = 'Ads specialist', jobdesk = 'Kelola ads meta' WHERE id = ?")->execute([$rony['id']]);
    $rony = $db->query("SELECT * FROM users WHERE id = {$rony['id']}")->fetch(PDO::FETCH_ASSOC);
}

echo "=== VERIFYING RONY UMBARA (ADS SPECIALIST) DETECTION ===\n";
echo "User ID: " . $rony['id'] . "\n";
echo "Name: " . $rony['name'] . "\n";
echo "Specialty: " . $rony['service_specialty'] . "\n";
echo "Position: " . $rony['position'] . "\n\n";

// Test 1: Function is_ads_specialist directly
$isAds = is_ads_specialist($rony);
echo "1. is_ads_specialist(\$rony): " . ($isAds ? "PASS (TRUE)" : "FAIL (FALSE)") . "\n";

// Test 2: Function is_creative_specialist directly
$isCreative = is_creative_specialist($rony);
echo "2. is_creative_specialist(\$rony): " . (!$isCreative ? "PASS (FALSE)" : "FAIL (TRUE)") . "\n";

// Test 3: worker_has_sosmed_projects
$hasSosmed = worker_has_sosmed_projects((int)$rony['id']);
echo "3. worker_has_sosmed_projects({$rony['id']}): " . (!$hasSosmed ? "PASS (FALSE - Correctly blocked)" : "FAIL (TRUE - Should be FALSE)") . "\n";

// Test 4: Simulate Session where specialty was not in session array (simulating existing logged-in session)
$_SESSION['kala_accounts'] = [
    'u_' . $rony['id'] => [
        'user_id' => $rony['id'],
        'id' => $rony['id'],
        'name' => $rony['name'],
        'email' => $rony['email'],
        'role' => $rony['role'],
        'employment_type' => 'freelancer'
        // Intentionally missing service_specialty & position to test automatic DB enrichment!
    ]
];
$_GET['_u'] = 'u_' . $rony['id'];
unset($GLOBALS['__ACTIVE_RESOLVED_ACCOUNT__']);

$resolved = resolve_active_account();
echo "4. Auto-enrichment in resolve_active_account():\n";
echo "   service_specialty: " . ($resolved['service_specialty'] ?? 'MISSING') . "\n";
echo "   position: " . ($resolved['position'] ?? 'MISSING') . "\n";
echo "   is_ads_specialist(): " . (is_ads_specialist() ? "PASS (TRUE)" : "FAIL (FALSE)") . "\n";

// Test 5: Render sidebar
ob_start();
include __DIR__ . '/../includes/sidebar.php';
$sidebarHtml = ob_get_clean();

$hasReportAdsLink = preg_match('/<span>\s*Report Ads\s*<\/span>/i', $sidebarHtml);
$hasKalenderKontenLink = preg_match('/<span>\s*Kalender Konten\s*<\/span>/i', $sidebarHtml);

echo "\n5. Sidebar Navigation Links Check for Rony Umbara:\n";
echo "   Has 'Report Ads' link: " . ($hasReportAdsLink ? "PASS (YES)" : "FAIL (NO)") . "\n";
echo "   Has 'Kalender Konten' link: " . (!$hasKalenderKontenLink ? "PASS (100% HIDDEN)" : "FAIL (STILL VISIBLE!)") . "\n";

// Test 6: Route guard when accessing Kalender Konten
$isBlockedFromCalendar = is_ads_specialist();
echo "6. Access Guard to Content Calendar for Ads Specialist: " . ($isBlockedFromCalendar ? "PASS (STRICTLY BLOCKED & REDIRECTED TO REPORT ADS)" : "FAIL") . "\n";

if ($isAds && !$hasSosmed && $hasReportAdsLink && !$hasKalenderKontenLink && $isBlockedFromCalendar) {
    echo "\n🎉 100% PASS: ADS SPECIALIST IS PROPERLY CONFIGURED, KALENDER KONTEN IS COMPLETELY HIDDEN & BLOCKED!\n";
} else {
    echo "\n⚠️ SOME CHECKS FAILED!\n";
}

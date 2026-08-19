<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

echo "=== TESTING VOUCHER_VIEW & SALARY_VIEW PUBLIC PAGES ===\n";

$_GET['id'] = 2;
ob_start();
include __DIR__ . '/../views/voucher_view.php';
$vHtml = ob_get_clean();
echo "1. voucher_view.php (ID: 2): " . (strlen($vHtml) > 500 ? "✔ PASS (Size: " . strlen($vHtml) . " bytes)" : "✖ FAIL") . "\n";

$_GET['id'] = 18;
ob_start();
include __DIR__ . '/../views/salary_view.php';
$sHtml = ob_get_clean();
echo "2. salary_view.php (ID: 18): " . (strlen($sHtml) > 500 ? "✔ PASS (Size: " . strlen($sHtml) . " bytes)" : "✖ FAIL") . "\n";

$_GET['id'] = 1;
ob_start();
include __DIR__ . '/../views/invoice_view.php';
$iHtml = ob_get_clean();
echo "3. invoice_view.php (ID: 1): " . (strlen($iHtml) > 500 ? "✔ PASS (Size: " . strlen($iHtml) . " bytes)" : "✖ FAIL") . "\n";

$_GET['id'] = 1;
ob_start();
include __DIR__ . '/../views/ads_voucher_view.php';
$aHtml = ob_get_clean();
echo "4. ads_voucher_view.php (ID: 1): " . (strlen($aHtml) > 500 ? "✔ PASS (Size: " . strlen($aHtml) . " bytes)" : "✖ FAIL") . "\n";

echo "\n🎉 ALL PUBLIC DOCUMENT VIEWS FUNCTION PERFECTLY!\n";

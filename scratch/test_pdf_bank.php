<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/auth.php';

$_SESSION['logged_in'] = true;
$_SESSION['user'] = ['id' => 1, 'name' => 'Rangga Pratama', 'email' => 'rangga@kalamedia.id', 'role' => 'owner'];

$_GET['action'] = 'download_pdf';
$_GET['id'] = 1;

ob_start();
require __DIR__ . '/../api/invoices.php';
$html = ob_get_clean();

echo "PDF HTML length: " . strlen($html) . "\n";
echo "PDF HTML has Bank Jago: " . (strpos($html, 'Bank Jago') !== false ? 'YES' : 'NO') . "\n";
echo "PDF HTML has 107577583322: " . (strpos($html, '107577583322') !== false ? 'YES' : 'NO') . "\n";
echo "PDF HTML has ILHAM LANANG: " . (strpos($html, 'ILHAM LANANG') !== false ? 'YES' : 'NO') . "\n";

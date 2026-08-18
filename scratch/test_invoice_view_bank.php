<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/auth.php';

$_SESSION['logged_in'] = true;
$_SESSION['user'] = ['id' => 1, 'name' => 'Rangga Pratama', 'email' => 'rangga@kalamedia.id', 'role' => 'owner'];

$_GET['id'] = 1;
ob_start();
require __DIR__ . '/../views/invoice_view.php';
$out = ob_get_clean();

echo "Has Bank Jago: " . (strpos($out, 'Bank Jago') !== false ? 'YES' : 'NO') . "\n";
echo "Has 107577583322: " . (strpos($out, '107577583322') !== false ? 'YES' : 'NO') . "\n";
echo "Has ILHAM LANANG: " . (strpos($out, 'ILHAM LANANG') !== false ? 'YES' : 'NO') . "\n";
echo "Has inv-status-pill: " . (strpos($out, 'inv-status-pill') !== false ? 'YES' : 'NO') . "\n";
echo "Has inv-status-paid: " . (strpos($out, 'inv-status-paid') !== false ? 'YES' : 'NO') . "\n";

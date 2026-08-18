<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/auth.php';

$_SESSION['logged_in'] = true;
$_SESSION['user'] = ['id' => 1, 'name' => 'Rangga Pratama', 'email' => 'rangga@kalamedia.id', 'role' => 'owner'];

ob_start();
require __DIR__ . '/../views/clients.php';
$out = ob_get_clean();

echo "Has 0895361622252: " . (strpos($out, '0895361622252') !== false ? 'YES' : 'NO') . "\n";
echo "Has Prima Pasir Mandiri: " . (strpos($out, 'Prima Pasir Mandiri') !== false ? 'YES' : 'NO') . "\n";

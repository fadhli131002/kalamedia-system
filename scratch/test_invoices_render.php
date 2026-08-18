<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/auth.php';
$_SESSION['logged_in'] = true;
$_SESSION['active_role'] = 'owner';
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Rangga Pratama',
    'email' => 'rangga@kalamedia.id',
    'role' => 'owner'
];

ob_start();
require __DIR__ . '/../views/invoices.php';
$out = ob_get_clean();

echo "Rendered length: " . strlen($out) . " bytes\n";
echo "Has confirmDeleteInvoice: " . (strpos($out, 'confirmDeleteInvoice') !== false ? 'YES' : 'NO') . "\n";
echo "Has modal-confirm-delete: " . (strpos($out, 'modal-confirm-delete') !== false ? 'YES' : 'NO') . "\n";
echo "Has btn-delete-ghost: " . (strpos($out, 'btn-delete-ghost') !== false ? 'YES' : 'NO') . "\n";

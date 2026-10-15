<?php
require_once __DIR__ . '/../config/app.php';
$_POST = [
    'email' => 'admin@kalamedia.id',
    'password' => 'password123'
];
$_GET = [
    'action' => 'login'
];

ob_start();
include __DIR__ . '/../api/auth.php';
$res = ob_get_clean();
echo "ADMIN AUTH RESULT:\n" . $res . "\n";

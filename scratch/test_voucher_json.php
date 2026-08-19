<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$_SESSION['logged_in'] = true;
$_SESSION['user'] = ['id' => 1, 'role' => 'owner'];

$_GET = ['action' => 'get_payout_voucher', 'id' => 2];
include __DIR__ . '/../api/expenses.php';

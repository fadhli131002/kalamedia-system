<?php
require_once __DIR__ . '/../config/app.php';
$_SESSION['logged_in'] = true;
$_SESSION['user'] = ['id' => 1, 'role' => 'owner', 'name' => 'Owner Kala'];

$_GET['action'] = 'update_employee';
$_POST['id'] = 5;
$_POST['name'] = 'Muhammad Fadhli';
$_POST['position'] = 'Creative Lead';
$_POST['department'] = 'Creative & Production';
$_POST['employment_type'] = 'Full-time';
$_POST['email'] = 'mha.fadhli@gmail.com';
$_POST['phone'] = '08881124215';
$_POST['bank_name'] = 'Seabank';
$_POST['bank_account'] = '901700766017';
$_POST['base_salary'] = '250000';
$_POST['status'] = 'Active';

include __DIR__ . '/../api/salaries.php';

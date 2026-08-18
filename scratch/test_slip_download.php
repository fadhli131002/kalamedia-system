<?php
session_start();
$_SESSION['logged_in'] = true;
$_SESSION['user'] = ['id' => 1, 'name' => 'Ilham Lanang', 'role' => 'owner'];

require_once __DIR__ . '/../config/database.php';
$_GET['action'] = 'download_slip_pdf';
$_GET['id'] = 17;
include __DIR__ . '/../api/salaries.php';

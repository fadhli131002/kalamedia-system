<?php
session_start();
$_SESSION['logged_in'] = true;
$_SESSION['user'] = ['id' => 1, 'name' => 'Ilham Lanang', 'role' => 'owner'];

require_once __DIR__ . '/../config/database.php';
$_GET['action'] = 'export_pdf';
$_GET['range_type'] = 'custom';
$_GET['start_date'] = '2026-08-20';
$_GET['end_date'] = '2026-08-30';
$_GET['client_id'] = '1';
$_GET['platform'] = 'all';
$_GET['status'] = 'all';

include __DIR__ . '/../api/content.php';

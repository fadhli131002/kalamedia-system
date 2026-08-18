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
require __DIR__ . '/../views/content_dashboard.php';
$out = ob_get_clean();

echo "Content Calendar HTML size: " . strlen($out) . " bytes\n";
echo "Has Content Calendar & Planner: " . (strpos($out, 'Content Calendar &amp; Planner') !== false || strpos($out, 'Content Calendar & Planner') !== false ? 'YES' : 'NO') . "\n";
echo "Has FullCalendar: " . (strpos($out, 'fullcalendar') !== false ? 'YES' : 'NO') . "\n";
echo "Has modal-content-planner: " . (strpos($out, 'modal-content-planner') !== false ? 'YES' : 'NO') . "\n";
echo "Has client-filter-list: " . (strpos($out, 'client-filter-list') !== false ? 'YES' : 'NO') . "\n";

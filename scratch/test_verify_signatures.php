<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$_SESSION['logged_in'] = true;
$_SESSION['user'] = ['id' => 1, 'role' => 'owner', 'name' => 'Muhammad Fadhli'];

$_GET['id'] = 6;
ob_start();
require __DIR__ . '/../views/report_deck.php';
$html = ob_get_clean();

echo "=== VERIFY DIGITAL SIGNATURES & NEW JOB TITLES ===\n";
if (strpos($html, 'ttd-fadhli.png') !== false && strpos($html, 'Creative Manager') !== false) {
    echo "✔ PASS: Muhammad Fadhli is 'Creative Manager' with ttd-fadhli.png\n";
} else {
    echo "✖ FAIL: Muhammad Fadhli signature or title mismatch\n";
}

if (strpos($html, 'ttd-ilham.png') !== false && strpos($html, 'Marketing Manager') !== false) {
    echo "✔ PASS: Ilham Lanang is 'Marketing Manager' with ttd-ilham.png\n";
} else {
    echo "✖ FAIL: Ilham Lanang signature or title mismatch\n";
}

echo "\nTotal Deck HTML Size: " . strlen($html) . " bytes\n";

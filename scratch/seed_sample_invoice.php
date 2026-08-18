<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';

$db = Database::getConnection();

// Check if client Prima Pasir Mandiri exists
$client = $db->query("SELECT id FROM clients WHERE company = 'Prima Pasir Mandiri'")->fetch();
if (!$client) {
    $db->exec("INSERT INTO clients (name, company, email, phone, address) VALUES ('Prima Pasir Mandiri', 'Prima Pasir Mandiri', 'contact@primapasir.com', '08123456789', 'Jl. Raya Serpong No. 10, Tangerang')");
    $clientId = $db->lastInsertId();
} else {
    $clientId = $client['id'];
}

// Check if invoice INV-KMC-0020526 exists
$inv = $db->query("SELECT id FROM invoices WHERE invoice_number = 'INV-KMC-0020526'")->fetch();
if (!$inv) {
    $stmt = $db->prepare("
        INSERT INTO invoices (
            invoice_number, client_id, issue_date, due_date,
            subtotal, discount_percent, discount_amount, tax_percent, tax_amount,
            total_amount, status, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'INV-KMC-0020526', $clientId, '2026-08-16', '2026-08-16',
        1500000, 0, 0, 0, 0,
        1500000, 'Paid',
        "Lingkup pekerjaan meliputi manajemen & monitoring konten untuk periode 1 bulan (30 hari). Pekerjaan akan mulai berjalan setelah pembayaran down payment (DP) sebesar 50% berhasil dikonfirmasi."
    ]);
    $newInvId = $db->lastInsertId();

    $itemStmt = $db->prepare("INSERT INTO invoice_items (invoice_id, service_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
    $itemStmt->execute([$newInvId, 'Pengelolaan Google Ads', 1, 350000, 350000]);
    $itemStmt->execute([$newInvId, 'Pembuatan Konten (Video dan Foto)', 1, 300000, 300000]);
    $itemStmt->execute([$newInvId, 'Pembuatan Konten iklan dan untuk google Ads', 1, 200000, 200000]);
    $itemStmt->execute([$newInvId, 'Saldo Ads (minimal Rp. 1.000.000,-) untuk 1 area (contoh: Area Jabodetabek)', 1, 650000, 650000]);

    echo "SEEDED_SAMPLE_INVOICE_ID: " . $newInvId . "\n";
} else {
    echo "EXISTS_SAMPLE_INVOICE_ID: " . $inv['id'] . "\n";
}

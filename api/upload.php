<?php
/**
 * Receipt & Attachment Upload API Handler
 * Enforces PRD Max 5MB Limit & Format Validation
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_auth();

$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak didukung']);
    exit;
}

if (!isset($_FILES['receipt_file']) || $_FILES['receipt_file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['receipt_file']['error'] ?? 'UNKNOWN';
    echo json_encode(['success' => false, 'message' => 'Gagal mengunggah file. Error code: ' . $errCode]);
    exit;
}

$file = $_FILES['receipt_file'];
$maxSize = 5 * 1024 * 1024; // 5MB

if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'Ukuran file melebihi batas maksimal 5MB!']);
    exit;
}

// Allowed MIME types and extensions
$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
$fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($fileExt, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => 'Format file tidak didukung. Harap upload format JPG, PNG, atau PDF.']);
    exit;
}

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// Unique filename
$newFileName = 'receipt_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
$destination = UPLOAD_DIR . '/' . $newFileName;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file ke direktori server.']);
    exit;
}

// Target entity linking
$targetType = $_POST['target_type'] ?? ''; // 'invoice', 'payout', 'ads'
$targetId = intval($_POST['target_id'] ?? 0);

if ($targetType === 'invoice' && $targetId > 0) {
    $stmt = $db->prepare("UPDATE invoices SET receipt_file = ?, status = 'Paid', paid_at = datetime('now', 'localtime') WHERE id = ?");
    $stmt->execute([$newFileName, $targetId]);
    
    // Get invoice info for activity log
    $inv = $db->query("SELECT invoice_number, total_amount FROM invoices WHERE id = $targetId")->fetch();
    if ($inv) {
        log_activity('invoice', "Bukti Transfer Invoice #{$inv['invoice_number']} Diunggah", "Pembayaran klien sebesar " . format_rupiah($inv['total_amount']) . " telah diverifikasi.", $inv['total_amount']);
    }
} elseif ($targetType === 'payout' && $targetId > 0) {
    $stmt = $db->prepare("UPDATE freelancer_payouts SET receipt_file = ?, status = 'Paid', paid_at = datetime('now', 'localtime') WHERE id = ?");
    $stmt->execute([$newFileName, $targetId]);

    $payout = $db->query("SELECT freelancer_name, amount FROM freelancer_payouts WHERE id = $targetId")->fetch();
    if ($payout) {
        log_activity('payout', "Bukti Payout {$payout['freelancer_name']} Diunggah", "Fee freelancer sebesar " . format_rupiah($payout['amount']) . " berhasil ditransfer.", $payout['amount']);
    }
} elseif ($targetType === 'ads' && $targetId > 0) {
    $stmt = $db->prepare("UPDATE ads_spend SET receipt_file = ? WHERE id = ?");
    $stmt->execute([$newFileName, $targetId]);

    $ads = $db->query("SELECT platform, amount FROM ads_spend WHERE id = $targetId")->fetch();
    if ($ads) {
        log_activity('ads', "Struk Top Up {$ads['platform']} Diunggah", "Struk top up " . format_rupiah($ads['amount']) . " tersimpan.", $ads['amount']);
    }
} elseif ($targetType === 'salary' && $targetId > 0) {
    $stmt = $db->prepare("UPDATE salaries SET receipt_file = ?, status = 'Paid', paid_at = datetime('now', 'localtime') WHERE id = ?");
    $stmt->execute([$newFileName, $targetId]);

    $sal = $db->query("SELECT employee_name, net_salary, month_period FROM salaries WHERE id = $targetId")->fetch();
    if ($sal) {
        log_activity('salary', "Bukti Transfer Gaji {$sal['employee_name']} Diunggah", "Pembayaran gaji periode {$sal['month_period']} sebesar " . format_rupiah($sal['net_salary']) . " berhasil diverifikasi.", $sal['net_salary']);
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Bukti transaksi berhasil diunggah!',
    'file_name' => $newFileName,
    'file_url' => url(UPLOAD_URL . '/' . $newFileName)
]);

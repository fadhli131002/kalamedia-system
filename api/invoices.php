<?php
/**
 * Invoices API Handler
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_auth();
release_session();

// STRICT FINANCIAL PRIVACY: Karyawan dan Freelancer dilarang keras mengakses invoice
if (is_karyawan() || is_freelancer()) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak: Karyawan dan freelancer dilarang melihat data nominal invoice klien.']);
    exit;
}

// CLIENT MUTATION RESTRICTION: Client is read-only
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if (is_client() && in_array($action, ['create', 'update', 'delete', 'mark_paid', 'update_status', 'upload_receipt', 'confirm_payment', 'project_detail', 'add_payment', 'delete_payment'])) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak: Klien tidak memiliki izin mengubah data invoice.']);
    exit;
}

$db = Database::getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'create') {
    $rawClientId = $_POST['client_id'] ?? '';
    $clientId = ($rawClientId !== '__new__') ? intval($rawClientId) : 0;
    $projectId = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $issueDate = $_POST['issue_date'] ?? date('Y-m-d');
    $dueDate = $_POST['due_date'] ?? date('Y-m-d', strtotime('+14 days'));
    $discountPercent = floatval($_POST['discount_percent'] ?? 0);
    $taxPercent = floatval($_POST['tax_percent'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $status = $_POST['status'] ?? 'Draft';
    if ($status === 'Sent' || empty($status)) {
        $status = 'Draft';
    }

    $items = $_POST['items'] ?? [];
    if (is_string($items)) {
        $items = json_decode($items, true) ?: [];
    }

    // Auto-create client if client_id is '__new__' or empty, and new client info is provided
    if ($clientId <= 0) {
        $newCompany = trim($_POST['new_client_company'] ?? $_POST['new_client_name'] ?? '');
        if (!empty($newCompany)) {
            $newPic = trim($_POST['new_client_pic'] ?? $newCompany);
            $newPhone = trim($_POST['new_client_phone'] ?? '-');
            $newEmail = trim($_POST['new_client_email'] ?? (strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $newCompany)) . '@kalamedia.client'));

            $stmtNewClient = $db->prepare("
                INSERT INTO clients (name, company, company_name, pic_name, email, phone, status)
                VALUES (?, ?, ?, ?, ?, ?, 'Active')
            ");
            $stmtNewClient->execute([$newPic, $newCompany, $newCompany, $newPic, $newEmail, $newPhone]);
            $clientId = (int)$db->lastInsertId();
        }
    }

    if ($clientId <= 0 || empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Klien dan minimal 1 item jasa wajib diisi.']);
        exit;
    }

    // Calculate subtotal
    $subtotal = 0;
    $validItems = [];
    foreach ($items as $item) {
        $serviceName = trim($item['service_name'] ?? '');
        $quantity = floatval($item['quantity'] ?? 1);
        $unitPrice = floatval($item['unit_price'] ?? 0);
        if (!empty($serviceName) && $quantity > 0 && $unitPrice >= 0) {
            $lineTotal = $quantity * $unitPrice;
            $subtotal += $lineTotal;
            $validItems[] = [
                'service_name' => $serviceName,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $lineTotal
            ];
        }
    }

    if (empty($validItems)) {
        echo json_encode(['success' => false, 'message' => 'Layanan jasa tidak valid.']);
        exit;
    }

    $discountAmount = ($subtotal * $discountPercent) / 100;
    $afterDiscount = $subtotal - $discountAmount;
    $taxAmount = ($afterDiscount * $taxPercent) / 100;
    $totalAmount = $afterDiscount + $taxAmount;

    // Generate Invoice Number: INV-KMC-YYMMXXX (collision-free)
    $prefix = 'INV-KMC-' . date('ym');
    $countStmt = $db->query("SELECT COUNT(*) FROM invoices WHERE invoice_number LIKE '$prefix%' OR invoice_number LIKE 'INV%'");
    $counter = intval($countStmt->fetchColumn()) + 1;
    $invoiceNumber = '';
    do {
        $seq = str_pad($counter, 3, '0', STR_PAD_LEFT);
        $candidate = $prefix . $seq;
        $check = $db->prepare("SELECT COUNT(*) FROM invoices WHERE invoice_number = ?");
        $check->execute([$candidate]);
        if ($check->fetchColumn() == 0) {
            $invoiceNumber = $candidate;
            break;
        }
        $counter++;
    } while ($counter < 9999);

    try {
        $db->beginTransaction();

        // If no project selected, auto-create a project for this client to ensure margin and expense tracking works seamlessly
        if (empty($projectId) || $projectId <= 0) {
            $clientStmt = $db->prepare("SELECT company, company_name FROM clients WHERE id = ?");
            $clientStmt->execute([$clientId]);
            $clientRow = $clientStmt->fetch();
            $clientCompany = !empty($clientRow['company']) ? $clientRow['company'] : (!empty($clientRow['company_name']) ? $clientRow['company_name'] : 'Klien #' . $clientId);

            $primaryService = !empty($validItems[0]['service_name']) ? $validItems[0]['service_name'] : 'Creative & Media Service';
            $projectName = $primaryService . ' - ' . $clientCompany;

            $projStmt = $db->prepare("
                INSERT INTO projects (
                    client_id, name, project_name, contract_value, target_margin_percent, status, start_date, end_date
                ) VALUES (?, ?, ?, ?, 30, 'In Progress', ?, ?)
            ");
            $projStmt->execute([$clientId, $projectName, $projectName, $totalAmount, $issueDate, $dueDate]);
            $projectId = (int)$db->lastInsertId();
        }

        $stmt = $db->prepare("
            INSERT INTO invoices (
                invoice_number, client_id, project_id, issue_date, due_date,
                subtotal, discount_percent, discount_amount, tax_percent, tax_amount,
                total_amount, status, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $invoiceNumber, $clientId, $projectId, $issueDate, $dueDate,
            $subtotal, $discountPercent, $discountAmount, $taxPercent, $taxAmount,
            $totalAmount, $status, $notes
        ]);
        $invoiceId = $db->lastInsertId();

        // Insert items
        $itemStmt = $db->prepare("
            INSERT INTO invoice_items (invoice_id, service_name, quantity, unit_price, total_price)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($validItems as $vItem) {
            $itemStmt->execute([
                $invoiceId, $vItem['service_name'], $vItem['quantity'], $vItem['unit_price'], $vItem['total_price']
            ]);
        }

        $db->commit();

        log_activity('invoice', "Invoice Baru Dibuat: #$invoiceNumber", "Total tagihan: " . format_rupiah($totalAmount), $totalAmount);

        echo json_encode([
            'success' => true,
            'message' => "Invoice #$invoiceNumber berhasil dibuat!",
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber
        ]);
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal membuat invoice: ' . $e->getMessage()]);
        exit;
    }
}

if ($action === 'update') {
    $invoiceId = intval($_POST['invoice_id'] ?? 0);
    if ($invoiceId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invoice ID tidak valid.']);
        exit;
    }

    // Verify invoice exists and is Draft
    $invStmt = $db->prepare("SELECT * FROM invoices WHERE id = ? AND COALESCE(is_deleted, 0) = 0");
    $invStmt->execute([$invoiceId]);
    $currentInv = $invStmt->fetch();

    if (!$currentInv) {
        echo json_encode(['success' => false, 'message' => 'Invoice tidak ditemukan atau telah dihapus.']);
        exit;
    }

    if ($currentInv['status'] === 'Paid') {
        echo json_encode(['success' => false, 'message' => 'Invoice yang sudah LUNAS (Paid) tidak dapat diedit.']);
        exit;
    }

    $clientId = intval($_POST['client_id'] ?? 0);
    $projectId = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $issueDate = $_POST['issue_date'] ?? date('Y-m-d');
    $dueDate = $_POST['due_date'] ?? date('Y-m-d', strtotime('+14 days'));
    $discountPercent = floatval($_POST['discount_percent'] ?? 0);
    $taxPercent = floatval($_POST['tax_percent'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $status = $_POST['status'] ?? 'Draft';
    if ($status === 'Sent' || empty($status)) {
        $status = 'Draft';
    }

    $items = $_POST['items'] ?? [];
    if (is_string($items)) {
        $items = json_decode($items, true) ?: [];
    }

    if ($clientId <= 0 || empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Klien dan minimal 1 item jasa wajib diisi.']);
        exit;
    }

    // Calculate subtotal
    $subtotal = 0;
    $validItems = [];
    foreach ($items as $item) {
        $serviceName = trim($item['service_name'] ?? '');
        $quantity = floatval($item['quantity'] ?? 1);
        $unitPrice = floatval($item['unit_price'] ?? 0);
        if (!empty($serviceName) && $quantity > 0 && $unitPrice >= 0) {
            $lineTotal = $quantity * $unitPrice;
            $subtotal += $lineTotal;
            $validItems[] = [
                'service_name' => $serviceName,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $lineTotal
            ];
        }
    }

    if (empty($validItems)) {
        echo json_encode(['success' => false, 'message' => 'Layanan jasa tidak valid.']);
        exit;
    }

    $discountAmount = ($subtotal * $discountPercent) / 100;
    $afterDiscount = $subtotal - $discountAmount;
    $taxAmount = ($afterDiscount * $taxPercent) / 100;
    $totalAmount = $afterDiscount + $taxAmount;

    try {
        $db->beginTransaction();

        // If no project selected, check if client has project or create one
        if (empty($projectId) || $projectId <= 0) {
            $clientStmt = $db->prepare("SELECT company FROM clients WHERE id = ?");
            $clientStmt->execute([$clientId]);
            $clientCompany = $clientStmt->fetchColumn() ?: 'Klien #' . $clientId;

            $primaryService = !empty($validItems[0]['service_name']) ? $validItems[0]['service_name'] : 'Creative & Media Service';
            $projectName = $primaryService . ' - ' . $clientCompany;

            $projStmt = $db->prepare("
                INSERT INTO projects (
                    client_id, name, contract_value, target_margin_percent, status, start_date, end_date
                ) VALUES (?, ?, ?, 30, 'In Progress', ?, ?)
            ");
            $projStmt->execute([$clientId, $projectName, $totalAmount, $issueDate, $dueDate]);
            $projectId = $db->lastInsertId();
        }

        // Update invoices
        $stmt = $db->prepare("
            UPDATE invoices SET
                client_id = ?,
                project_id = ?,
                issue_date = ?,
                due_date = ?,
                subtotal = ?,
                discount_percent = ?,
                discount_amount = ?,
                tax_percent = ?,
                tax_amount = ?,
                total_amount = ?,
                status = ?,
                notes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $clientId, $projectId, $issueDate, $dueDate,
            $subtotal, $discountPercent, $discountAmount, $taxPercent, $taxAmount,
            $totalAmount, $status, $notes, $invoiceId
        ]);

        // Delete existing items & re-insert
        $delItemStmt = $db->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
        $delItemStmt->execute([$invoiceId]);

        $itemStmt = $db->prepare("
            INSERT INTO invoice_items (invoice_id, service_name, quantity, unit_price, total_price)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($validItems as $vItem) {
            $itemStmt->execute([
                $invoiceId, $vItem['service_name'], $vItem['quantity'], $vItem['unit_price'], $vItem['total_price']
            ]);
        }

        $db->commit();

        $invoiceNumber = $currentInv['invoice_number'];
        log_activity('invoice', "Draft Invoice Diperbarui: #$invoiceNumber", "Total tagihan: " . format_rupiah($totalAmount), $totalAmount);

        echo json_encode([
            'success' => true,
            'message' => "Draft Invoice #$invoiceNumber berhasil diperbarui!",
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber
        ]);
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui invoice: ' . $e->getMessage()]);
        exit;
    }
}

if ($action === 'mark_paid') {
    $invoiceId = intval($_POST['invoice_id'] ?? 0);
    $paymentMethod = trim($_POST['payment_method'] ?? 'Bank Transfer');
    $paidAt = $_POST['paid_at'] ?? date('Y-m-d H:i:s');

    if ($invoiceId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invoice ID tidak valid.']);
        exit;
    }

    try {
        $stmt = $db->prepare("UPDATE invoices SET status = 'Paid', payment_method = ?, paid_at = ? WHERE id = ?");
        $stmt->execute([$paymentMethod, $paidAt, $invoiceId]);

        $inv = $db->query("SELECT invoice_number, total_amount FROM invoices WHERE id = $invoiceId")->fetch();
        if ($inv) {
            log_activity('invoice', "Invoice #{$inv['invoice_number']} Ditandai Lunas", "Pembayaran via $paymentMethod dicatat.", $inv['total_amount']);
        }

        echo json_encode(['success' => true, 'message' => 'Invoice berhasil ditandai Lunas!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

if ($action === 'update_status') {
    $invoiceId = intval($_POST['invoice_id'] ?? 0);
    $status = $_POST['status'] ?? 'Draft';
    if ($status === 'Sent') {
        $status = 'Draft';
    }

    try {
        $stmt = $db->prepare("UPDATE invoices SET status = ? WHERE id = ?");
        $stmt->execute([$status, $invoiceId]);
        echo json_encode(['success' => true, 'message' => "Status invoice diubah ke $status!"]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

if ($action === 'delete') {
    // Only Owner can delete
    if (!is_owner()) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya Owner yang dapat menghapus invoice.']);
        exit;
    }

    $invoiceId = intval($_POST['invoice_id'] ?? 0);
    try {
        $inv = $db->query("SELECT invoice_number FROM invoices WHERE id = $invoiceId")->fetch();
        // Soft delete
        $stmt = $db->prepare("UPDATE invoices SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$invoiceId]);
        
        if ($inv) {
            log_activity('invoice', "Invoice #{$inv['invoice_number']} Dihapus (Soft Delete)", "Owner menghapus invoice dari sistem.");
        }
        echo json_encode(['success' => true, 'message' => 'Invoice berhasil dihapus!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

if ($action === 'get_details') {
    $invoiceId = intval($_GET['id'] ?? 0);
    $stmt = $db->prepare("
        SELECT i.*, c.name as client_name, c.company as client_company, c.email as client_email, c.phone as client_phone, c.address as client_address,
               p.name as project_name
        FROM invoices i
        JOIN clients c ON i.client_id = c.id
        LEFT JOIN projects p ON i.project_id = p.id
        WHERE i.id = ? AND COALESCE(i.is_deleted, 0) = 0
    ");
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        echo json_encode(['success' => false, 'message' => 'Invoice tidak ditemukan atau telah dihapus.']);
        exit;
    }

    if (is_client()) {
        $clientAuthId = current_user_client_id();
        if ($clientAuthId && (int)$invoice['client_id'] !== (int)$clientAuthId) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak: Invoice ini bukan milik perusahaan Anda.']);
            exit;
        }
    }

    $itemsStmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
    $itemsStmt->execute([$invoiceId]);
    $invoice['items'] = $itemsStmt->fetchAll();

    $payStmt = $db->prepare("
        SELECT id, term_name, amount, payment_date, payment_method, receipt_file, notes, created_at
        FROM invoice_payments
        WHERE invoice_id = ? AND COALESCE(is_deleted, 0) = 0
        ORDER BY payment_date ASC, id ASC
    ");
    $payStmt->execute([$invoiceId]);
    $payments = $payStmt->fetchAll();

    $totalPaidCalc = 0;
    foreach ($payments as $p) {
        $totalPaidCalc += floatval($p['amount']);
    }
    $invoice['payments'] = $payments;
    $invoice['total_paid'] = $totalPaidCalc;
    $invoice['remaining_balance'] = max(0, floatval($invoice['total_amount']) - $totalPaidCalc);

    echo json_encode(['success' => true, 'invoice' => $invoice]);
    exit;
}

if ($action === 'list_payments') {
    $invoiceId = intval($_GET['invoice_id'] ?? 0);
    $stmt = $db->prepare("
        SELECT i.id, i.invoice_number, i.total_amount, i.status, c.company as client_company
        FROM invoices i
        JOIN clients c ON i.client_id = c.id
        WHERE i.id = ? AND COALESCE(i.is_deleted, 0) = 0
    ");
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        echo json_encode(['success' => false, 'message' => 'Invoice tidak ditemukan.']);
        exit;
    }

    $payStmt = $db->prepare("
        SELECT p.*, u.name as recorded_by_name
        FROM invoice_payments p
        LEFT JOIN users u ON p.recorded_by = u.id
        WHERE p.invoice_id = ? AND COALESCE(p.is_deleted, 0) = 0
        ORDER BY p.payment_date ASC, p.id ASC
    ");
    $payStmt->execute([$invoiceId]);
    $payments = $payStmt->fetchAll();

    $totalPaid = 0;
    foreach ($payments as &$p) {
        $p['amount_formatted'] = format_rupiah($p['amount']);
        $p['receipt_url'] = !empty($p['receipt_file']) ? url(UPLOAD_URL . '/' . $p['receipt_file']) : null;
        $totalPaid += floatval($p['amount']);
    }

    $totalAmount = floatval($invoice['total_amount']);
    $remaining = max(0, $totalAmount - $totalPaid);
    $percent = $totalAmount > 0 ? min(100, round(($totalPaid / $totalAmount) * 100, 1)) : 0;

    echo json_encode([
        'success' => true,
        'invoice' => [
            'id' => $invoice['id'],
            'invoice_number' => $invoice['invoice_number'],
            'total_amount' => $totalAmount,
            'total_amount_formatted' => format_rupiah($totalAmount),
            'total_paid' => $totalPaid,
            'total_paid_formatted' => format_rupiah($totalPaid),
            'remaining_balance' => $remaining,
            'remaining_balance_formatted' => format_rupiah($remaining),
            'progress_percent' => $percent,
            'status' => $invoice['status']
        ],
        'payments' => $payments
    ]);
    exit;
}

if ($action === 'add_payment') {
    $invoiceId = intval($_POST['invoice_id'] ?? 0);
    $termName = trim($_POST['term_name'] ?? '');
    if (empty($termName)) {
        $termName = 'Pembayaran Termin';
    }

    // Parse amount
    $rawAmount = $_POST['amount'] ?? 0;
    if (is_string($rawAmount)) {
        $cleanAmount = preg_replace('/[^\d.,]/', '', $rawAmount);
        if (strpos($cleanAmount, ',') !== false && strpos($cleanAmount, '.') !== false) {
            $cleanAmount = str_replace('.', '', $cleanAmount);
            $cleanAmount = str_replace(',', '.', $cleanAmount);
        } elseif (strpos($cleanAmount, '.') !== false && substr_count($cleanAmount, '.') > 1) {
            $cleanAmount = str_replace('.', '', $cleanAmount);
        } elseif (strpos($cleanAmount, ',') !== false) {
            $cleanAmount = str_replace(',', '.', $cleanAmount);
        }
        $amount = floatval($cleanAmount);
    } else {
        $amount = floatval($rawAmount);
    }

    if ($invoiceId <= 0 || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invoice ID atau nominal pembayaran tidak valid.']);
        exit;
    }

    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
    $paymentMethod = trim($_POST['payment_method'] ?? 'Bank Transfer');
    $notes = trim($_POST['notes'] ?? '');
    $currentUser = current_user();
    $recordedBy = !empty($currentUser['id']) ? (int)$currentUser['id'] : null;

    // Handle receipt file upload if provided
    $receiptFile = null;
    if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['receipt_file'];
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            echo json_encode(['success' => false, 'message' => 'Ukuran file bukti transfer melebihi 5MB!']);
            exit;
        }
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) {
            echo json_encode(['success' => false, 'message' => 'Format file tidak didukung. Harap upload JPG, PNG, atau PDF.']);
            exit;
        }
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0777, true);
        }
        $receiptFile = 'receipt_inv_' . $invoiceId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $receiptFile)) {
            $receiptFile = null;
        }
    }

    try {
        $db->beginTransaction();

        $invStmt = $db->prepare("SELECT invoice_number, total_amount, status FROM invoices WHERE id = ? AND COALESCE(is_deleted,0)=0");
        $invStmt->execute([$invoiceId]);
        $inv = $invStmt->fetch();
        if (!$inv) {
            throw new Exception("Invoice tidak ditemukan.");
        }

        $insStmt = $db->prepare("
            INSERT INTO invoice_payments (invoice_id, term_name, amount, payment_date, payment_method, receipt_file, notes, recorded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insStmt->execute([$invoiceId, $termName, $amount, $paymentDate, $paymentMethod, $receiptFile, $notes, $recordedBy]);
        $paymentId = $db->lastInsertId();

        // Calculate total payments so far
        $sumStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM invoice_payments WHERE invoice_id = ? AND COALESCE(is_deleted, 0) = 0");
        $sumStmt->execute([$invoiceId]);
        $totalPaidNow = floatval($sumStmt->fetchColumn());

        $totalInv = floatval($inv['total_amount']);
        $newStatus = $inv['status'];
        $paidAtUpdate = null;

        if ($totalPaidNow >= $totalInv) {
            $newStatus = 'Paid';
            $paidAtUpdate = date('Y-m-d H:i:s');
        } elseif ($totalPaidNow > 0) {
            $newStatus = 'Partially Paid';
        }

        // Update invoice status & latest receipt file if provided
        if ($receiptFile) {
            $upd = $db->prepare("UPDATE invoices SET status = ?, receipt_file = ?, paid_at = COALESCE(paid_at, ?) WHERE id = ?");
            $upd->execute([$newStatus, $receiptFile, $paidAtUpdate, $invoiceId]);
        } else {
            $upd = $db->prepare("UPDATE invoices SET status = ?, paid_at = COALESCE(paid_at, ?) WHERE id = ?");
            $upd->execute([$newStatus, $paidAtUpdate, $invoiceId]);
        }

        $db->commit();

        log_activity(
            'invoice',
            "Pembayaran Termin Dicatat: #{$inv['invoice_number']}",
            "Tahap '$termName' sebesar " . format_rupiah($amount) . " dicatat via $paymentMethod. Status invoice kini: $newStatus.",
            $amount
        );

        echo json_encode([
            'success' => true,
            'message' => "Pembayaran termin sebesar " . format_rupiah($amount) . " berhasil dicatat!",
            'payment_id' => $paymentId,
            'new_status' => $newStatus,
            'total_paid' => $totalPaidNow,
            'remaining_balance' => max(0, $totalInv - $totalPaidNow)
        ]);
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal mencatat pembayaran: ' . $e->getMessage()]);
        exit;
    }
}

if ($action === 'delete_payment') {
    if (!is_owner() && !is_admin()) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
        exit;
    }

    $paymentId = intval($_POST['payment_id'] ?? 0);
    if ($paymentId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID pembayaran tidak valid.']);
        exit;
    }

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT * FROM invoice_payments WHERE id = ? AND COALESCE(is_deleted, 0) = 0");
        $stmt->execute([$paymentId]);
        $pay = $stmt->fetch();
        if (!$pay) {
            throw new Exception("Riwayat pembayaran tidak ditemukan.");
        }

        $invoiceId = (int)$pay['invoice_id'];

        // Soft delete payment
        $del = $db->prepare("UPDATE invoice_payments SET is_deleted = 1 WHERE id = ?");
        $del->execute([$paymentId]);

        // Recalculate remaining
        $sumStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM invoice_payments WHERE invoice_id = ? AND COALESCE(is_deleted, 0) = 0");
        $sumStmt->execute([$invoiceId]);
        $totalPaidNow = floatval($sumStmt->fetchColumn());

        $invStmt = $db->prepare("SELECT invoice_number, total_amount, due_date FROM invoices WHERE id = ?");
        $invStmt->execute([$invoiceId]);
        $inv = $invStmt->fetch();

        $totalInv = floatval($inv['total_amount']);
        if ($totalPaidNow >= $totalInv) {
            $newStatus = 'Paid';
        } elseif ($totalPaidNow > 0) {
            $newStatus = 'Partially Paid';
        } else {
            // Check overdue or draft/sent
            $today = date('Y-m-d');
            $newStatus = ($inv['due_date'] < $today) ? 'Overdue' : 'Draft';
        }

        $upd = $db->prepare("UPDATE invoices SET status = ? WHERE id = ?");
        $upd->execute([$newStatus, $invoiceId]);

        $db->commit();

        log_activity(
            'invoice',
            "Pembayaran Termin Dihapus: #{$inv['invoice_number']}",
            "Tahap '{$pay['term_name']}' (" . format_rupiah($pay['amount']) . ") dihapus. Status invoice disesuaikan menjadi: $newStatus."
        );

        echo json_encode([
            'success' => true,
            'message' => 'Riwayat pembayaran berhasil dihapus.',
            'new_status' => $newStatus,
            'total_paid' => $totalPaidNow,
            'remaining_balance' => max(0, $totalInv - $totalPaidNow)
        ]);
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus riwayat pembayaran: ' . $e->getMessage()]);
        exit;
    }
}

if ($action === 'get_payment_detail') {
    $paymentId = intval($_GET['payment_id'] ?? 0);
    $stmt = $db->prepare("
        SELECT p.*, i.invoice_number, i.total_amount
        FROM invoice_payments p
        JOIN invoices i ON p.invoice_id = i.id
        WHERE p.id = ? AND COALESCE(p.is_deleted, 0) = 0
    ");
    $stmt->execute([$paymentId]);
    $pay = $stmt->fetch();

    if (!$pay) {
        echo json_encode(['success' => false, 'message' => 'Data pembayaran tidak ditemukan.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'payment' => [
            'id' => $pay['id'],
            'invoice_id' => $pay['invoice_id'],
            'invoice_number' => $pay['invoice_number'],
            'term_name' => $pay['term_name'],
            'amount' => floatval($pay['amount']),
            'amount_formatted' => format_rupiah($pay['amount']),
            'payment_date' => $pay['payment_date'],
            'payment_method' => $pay['payment_method'],
            'receipt_file' => $pay['receipt_file'],
            'notes' => $pay['notes']
        ]
    ]);
    exit;
}

if ($action === 'update_payment') {
    if (!is_owner() && !is_admin()) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
        exit;
    }

    $paymentId = intval($_POST['payment_id'] ?? 0);
    $termName = trim($_POST['term_name'] ?? '');
    if (empty($termName)) {
        $termName = 'Pembayaran Termin';
    }

    // Parse amount
    $rawAmount = $_POST['amount'] ?? 0;
    if (is_string($rawAmount)) {
        $cleanAmount = preg_replace('/[^\d.,]/', '', $rawAmount);
        if (strpos($cleanAmount, ',') !== false && strpos($cleanAmount, '.') !== false) {
            $cleanAmount = str_replace('.', '', $cleanAmount);
            $cleanAmount = str_replace(',', '.', $cleanAmount);
        } elseif (strpos($cleanAmount, '.') !== false && substr_count($cleanAmount, '.') > 1) {
            $cleanAmount = str_replace('.', '', $cleanAmount);
        } elseif (strpos($cleanAmount, ',') !== false) {
            $cleanAmount = str_replace(',', '.', $cleanAmount);
        }
        $amount = floatval($cleanAmount);
    } else {
        $amount = floatval($rawAmount);
    }

    if ($paymentId <= 0 || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Data input pembayaran tidak valid.']);
        exit;
    }

    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
    $paymentMethod = trim($_POST['payment_method'] ?? 'Bank Transfer');
    $notes = trim($_POST['notes'] ?? '');

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT * FROM invoice_payments WHERE id = ? AND COALESCE(is_deleted, 0) = 0");
        $stmt->execute([$paymentId]);
        $currentPay = $stmt->fetch();
        if (!$currentPay) {
            throw new Exception("Data pembayaran tidak ditemukan.");
        }

        $invoiceId = (int)$currentPay['invoice_id'];
        $receiptFile = $currentPay['receipt_file'];

        // Handle updated receipt file if uploaded
        if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['receipt_file'];
            $maxSize = 5 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                echo json_encode(['success' => false, 'message' => 'Ukuran file bukti transfer melebihi 5MB!']);
                exit;
            }
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts)) {
                echo json_encode(['success' => false, 'message' => 'Format file tidak didukung. Harap upload JPG, PNG, atau PDF.']);
                exit;
            }
            if (!is_dir(UPLOAD_DIR)) {
                mkdir(UPLOAD_DIR, 0777, true);
            }
            $newReceiptFile = 'receipt_inv_' . $invoiceId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $newReceiptFile)) {
                $receiptFile = $newReceiptFile;
            }
        }

        $updPay = $db->prepare("
            UPDATE invoice_payments 
            SET term_name = ?, amount = ?, payment_date = ?, payment_method = ?, receipt_file = ?, notes = ?
            WHERE id = ?
        ");
        $updPay->execute([$termName, $amount, $paymentDate, $paymentMethod, $receiptFile, $notes, $paymentId]);

        // Recalculate invoice status
        $sumStmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM invoice_payments WHERE invoice_id = ? AND COALESCE(is_deleted, 0) = 0");
        $sumStmt->execute([$invoiceId]);
        $totalPaidNow = floatval($sumStmt->fetchColumn());

        $invStmt = $db->prepare("SELECT invoice_number, total_amount, due_date FROM invoices WHERE id = ?");
        $invStmt->execute([$invoiceId]);
        $inv = $invStmt->fetch();

        $totalInv = floatval($inv['total_amount']);
        $newStatus = $inv['status'];
        if ($totalPaidNow >= $totalInv) {
            $newStatus = 'Paid';
        } elseif ($totalPaidNow > 0) {
            $newStatus = 'Partially Paid';
        } else {
            $today = date('Y-m-d');
            $newStatus = ($inv['due_date'] < $today) ? 'Overdue' : 'Draft';
        }

        $updInv = $db->prepare("UPDATE invoices SET status = ? WHERE id = ?");
        $updInv->execute([$newStatus, $invoiceId]);

        $db->commit();

        log_activity(
            'invoice',
            "Pembayaran Termin Diperbarui: #{$inv['invoice_number']}",
            "Tahap '$termName' diperbarui menjadi " . format_rupiah($amount) . " via $paymentMethod. Status invoice kini: $newStatus.",
            $amount
        );

        echo json_encode([
            'success' => true,
            'message' => "Riwayat pembayaran termin berhasil diperbarui!",
            'new_status' => $newStatus,
            'total_paid' => $totalPaidNow,
            'remaining_balance' => max(0, $totalInv - $totalPaidNow)
        ]);
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui pembayaran: ' . $e->getMessage()]);
        exit;
    }
}

if ($action === 'download_pdf') {
    $invoiceId = intval($_GET['id'] ?? 0);
    $stmt = $db->prepare("
        SELECT i.*, c.name as client_name, c.company as client_company, c.email as client_email, c.phone as client_phone, c.address as client_address,
               p.name as project_name
        FROM invoices i
        JOIN clients c ON i.client_id = c.id
        LEFT JOIN projects p ON i.project_id = p.id
        WHERE i.id = ? AND COALESCE(i.is_deleted, 0) = 0
    ");
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        header('Content-Type: text/plain');
        die("Invoice tidak ditemukan atau telah dihapus.");
    }

    if (is_client()) {
        $clientAuthId = current_user_client_id();
        if ($clientAuthId && (int)$invoice['client_id'] !== (int)$clientAuthId) {
            header('Content-Type: text/plain');
            die("Akses ditolak: Invoice ini bukan milik perusahaan Anda.");
        }
    }

    $itemsStmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
    $itemsStmt->execute([$invoiceId]);
    $items = $itemsStmt->fetchAll();

    $issueDateFormatted = date('j/n/Y', strtotime($invoice['issue_date']));
    $dueDateFormatted = date('j/n/Y', strtotime($invoice['due_date']));
    $defaultNote = "Lingkup pekerjaan meliputi manajemen & monitoring konten untuk periode 1 bulan (30 hari). Pekerjaan akan mulai berjalan setelah pembayaran down payment (DP) sebesar 50% berhasil dikonfirmasi.";
    $displayNote = !empty(trim($invoice['notes'] ?? '')) ? trim($invoice['notes']) : $defaultNote;
    $filename = "Invoice_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $invoice['invoice_number']) . ".pdf";

    // Set headers for clean direct HTML-to-print or standalone document
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
      <meta charset="UTF-8">
      <title><?= htmlspecialchars($invoice['invoice_number']) ?> - PT Kala Kausa Prima</title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="../assets/css/invoice.css">
      <script src="../assets/js/html2pdf.bundle.min.js"></script>
      <style>
        body { margin: 0; background: #F1F5F9; font-family: 'Inter', sans-serif; }
        .auto-download-notice {
          position: fixed; top: 15px; left: 50%; transform: translateX(-50%);
          background: #0F172A; color: #FFF; padding: 10px 20px; border-radius: 8px; font-size: 13px; z-index: 999;
          box-shadow: 0 4px 15px rgba(0,0,0,0.3); display: flex; align-items: center; gap: 10px;
        }
      </style>
    </head>
    <body>
      <div class="auto-download-notice" id="dl-notice">
        <span>Menyiapkan dan mengunduh file PDF...</span>
      </div>

      <div class="invoice-wrapper" style="padding: 20px 0;">
        <div class="invoice-paper" id="invoice-canvas">
          <!-- 1. Top To / Date Bar -->
          <div class="km-top-bar">
            <div class="km-to-box">
              <div class="label">To:</div>
              <div class="client-name"><?= htmlspecialchars($invoice['client_company'] ?: $invoice['client_name']) ?></div>
              <?php if (!empty($invoice['client_name']) && $invoice['client_company'] !== $invoice['client_name']): ?>
                <div class="client-sub">Up: <?= htmlspecialchars($invoice['client_name']) ?></div>
              <?php endif; ?>
            </div>
            <div class="km-date-inv-box">
              <div class="date-line">Date: <?= $issueDateFormatted ?></div>
              <div class="inv-num"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
            </div>
          </div>

          <!-- 2. Main Header: Logo, Address, INVOICE & Dates -->
          <div class="km-main-header">
            <div class="km-logo-col">
              <img src="../assets/img/Logo-1.webp" alt="PT Kala Kausa Prima" class="km-logo-img">
              <div class="km-agency-address">
                Jl. Padat Karya, Cisauk Girang, RT 001/RW 005,<br>
                Kel. Cisauk, Tangerang,<br>
                Banten 15341
              </div>
            </div>

            <div class="km-invoice-title-col">
              <div class="km-title-large">INVOICE</div>
              <div class="km-dates-row">
                <div>Date : <span class="val"><?= $issueDateFormatted ?></span></div>
                <div>Due date: <span class="val"><?= $dueDateFormatted ?></span></div>
              </div>
            </div>
          </div>

          <!-- 3. Description & Price Table -->
          <div class="km-table-wrapper">
            <table class="km-invoice-table">
              <thead>
                <tr>
                  <th class="col-desc">Description</th>
                  <th class="col-price">Price</th>
                  <th class="col-qty">Qty</th>
                  <th class="col-total">Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $item): ?>
                  <tr>
                    <td class="col-desc"><?= htmlspecialchars($item['service_name']) ?></td>
                    <td class="col-price"><?= format_rupiah($item['unit_price']) ?></td>
                    <td class="col-qty"><?= intval($item['quantity']) == $item['quantity'] ? intval($item['quantity']) : $item['quantity'] ?></td>
                    <td class="col-total"><?= format_rupiah($item['total_price']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- 4. Notes, Account Info & Subtotal / Grand Total -->
          <div class="km-middle-section">
            <div class="km-notes-col">
              <div class="km-note-block">
                <div class="km-note-title">Note:</div>
                <div class="km-note-text"><?= nl2br(htmlspecialchars($displayNote)) ?></div>
              </div>

              <div class="km-bank-info">
                <div class="km-bank-title">Informasi Rekening</div>
                <div class="km-bank-detail">(<?= AGENCY_BANK_NAME ?> a.n <?= AGENCY_BANK_HOLDER ?> - <?= AGENCY_BANK_ACCOUNT ?>)</div>
              </div>
            </div>

            <div class="km-totals-col">
              <div class="km-total-line">
                <span>Sub Total</span>
                <span><?= format_rupiah($invoice['subtotal']) ?></span>
              </div>

              <?php if ($invoice['discount_amount'] > 0): ?>
                <div class="km-total-line discount">
                  <span>Diskon (<?= $invoice['discount_percent'] ?>%)</span>
                  <span>- <?= format_rupiah($invoice['discount_amount']) ?></span>
                </div>
              <?php else: ?>
                <div class="km-total-line discount">
                  <span>-</span>
                  <span>Rp0</span>
                </div>
              <?php endif; ?>

              <?php if ($invoice['tax_amount'] > 0): ?>
                <div class="km-total-line">
                  <span>PPN (<?= $invoice['tax_percent'] ?>%)</span>
                  <span>+ <?= format_rupiah($invoice['tax_amount']) ?></span>
                </div>
              <?php endif; ?>

              <div class="km-total-line grand">
                <span>GRAND TOTAL</span>
                <span><?= format_rupiah($invoice['total_amount']) ?></span>
              </div>
            </div>
          </div>

          <!-- 5. Footer: QR Code, Agency Pitch & Social Links -->
          <div class="km-footer-section">
            <div class="km-footer-brand-box">
              <div class="km-qr-box">
                <img src="../assets/img/qr_code.svg" alt="QR Code" class="km-qr-img">
                <div class="km-qr-text">
                  SCAN ME<br>
                  TO MORE<br>
                  INFO
                </div>
              </div>

              <div class="km-about-text-box">
                <div class="km-about-title">PT Kala Kausa Prima</div>
                <div class="km-about-desc">
                  PT Kala Kausa Prima represents a modern creative agency focused on visibility, clarity, and impactful storytelling. The brand combines bold creativity with professional execution to create strong and meaningful brand communication.
                </div>
              </div>
            </div>

            <div class="km-bottom-bar">
              <div class="km-tagline">Built for What’s Next</div>
              <div class="km-social-links">
                <span class="km-social-item"><?= AGENCY_INSTAGRAM ?></span>
                <span class="km-social-item"><?= AGENCY_EMAIL ?></span>
                <span class="km-social-item"><?= AGENCY_WEBSITE ?></span>
              </div>
            </div>
          </div>

        </div>
      </div>

      <script>
        window.addEventListener('DOMContentLoaded', async () => {
          const element = document.getElementById('invoice-canvas');
          const filename = <?= json_encode($filename) ?>;
          const opt = {
            margin: [8, 8, 8, 8],
            filename: filename,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, letterRendering: true, scrollY: 0 },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
          };

          try {
            if (typeof html2pdf !== 'undefined') {
              await html2pdf().set(opt).from(element).save();
              document.getElementById('dl-notice').innerHTML = '<span>PDF Berhasil Diunduh!</span>';
              setTimeout(() => { window.close(); }, 1500);
            } else {
              window.print();
            }
          } catch(e) {
            console.error(e);
            window.print();
          }
        });
      </script>
    </body>
    </html>
    <?php
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid invoice action']);

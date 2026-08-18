<?php
/**
 * Invoices API Handler
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_auth();

$db = Database::getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'create') {
    $clientId = intval($_POST['client_id'] ?? 0);
    $projectId = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $issueDate = $_POST['issue_date'] ?? date('Y-m-d');
    $dueDate = $_POST['due_date'] ?? date('Y-m-d', strtotime('+14 days'));
    $discountPercent = floatval($_POST['discount_percent'] ?? 0);
    $taxPercent = floatval($_POST['tax_percent'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $status = $_POST['status'] ?? 'Draft';

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

    // Generate Invoice Number: INV-KMC-YYMMXXX
    $prefix = 'INV-KMC-' . date('ym');
    $countStmt = $db->query("SELECT COUNT(*) FROM invoices WHERE invoice_number LIKE '$prefix%' OR invoice_number LIKE 'INV%'");
    $nextSeq = str_pad(intval($countStmt->fetchColumn()) + 1, 3, '0', STR_PAD_LEFT);
    $invoiceNumber = $prefix . $nextSeq;

    try {
        $db->beginTransaction();

        // If no project selected, auto-create a project for this client to ensure margin and expense tracking works seamlessly
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

    $itemsStmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
    $itemsStmt->execute([$invoiceId]);
    $invoice['items'] = $itemsStmt->fetchAll();

    echo json_encode(['success' => true, 'invoice' => $invoice]);
    exit;
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
      <title><?= htmlspecialchars($invoice['invoice_number']) ?> - Kala Media</title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="../assets/css/invoice.css">
      <script src="../assets/js/html2pdf.bundle.min.js"></script>
      <style>
        body { margin: 0; background: #F1F5F9; font-family: 'Plus Jakarta Sans', sans-serif; }
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
              <img src="../assets/Jpg/Asset 3.png" alt="Kala Media Creative Agency" class="km-logo-img">
              <div class="km-agency-address">
                Jl. BSD Raya Utama,<br>
                Pagedangan,<br>
                Kec. Pagedangan, Kabupaten Tangerang,<br>
                Banten 15339
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
                <div class="km-bank-detail">(<?= AGENCY_BANK_NAME ?>) <?= AGENCY_BANK_ACCOUNT ?> a.n <?= AGENCY_BANK_HOLDER ?></div>
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
                <div class="km-about-title">Kala Media Creative</div>
                <div class="km-about-desc">
                  Kala Media represents a creative agency focused on visibility, clarity, and impactful storytelling. The brand combines bold creativity with professional execution to create strong and meaningful brand communication.
                </div>
              </div>
            </div>

            <div class="km-bottom-bar">
              <div class="km-tagline">Built to Be Seen.</div>
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

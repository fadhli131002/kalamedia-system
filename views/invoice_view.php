<?php
/**
 * PT Kala Kausa Prima - Official Invoice Template View (1:1 Match)
 */
$isLoggedIn = function_exists('is_logged_in') && is_logged_in();
$db = Database::getConnection();

$invoiceId = intval($_GET['id'] ?? 0);
if ($invoiceId <= 0) {
    if (!$isLoggedIn) {
        header('Location: ' . url('login'));
        exit;
    }
    die("ID Invoice tidak valid.");
}

$stmt = $db->prepare("
    SELECT i.*, c.name as client_name, c.company as client_company, c.email as client_email, c.phone as client_phone, c.address as client_address,
           p.name as project_name
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    LEFT JOIN projects p ON i.project_id = p.id
    WHERE i.id = ?
");
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die("Invoice tidak ditemukan.");
}

// Client Role Security Check: Strict Data Isolation
if ($isLoggedIn && is_client()) {
    $clientUserId = current_user_client_id();
    if ($clientUserId && (int)$invoice['client_id'] !== $clientUserId) {
        die("Anda tidak memiliki akses ke tagihan invoice ini.");
    }
}

$itemsStmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$itemsStmt->execute([$invoiceId]);
$items = $itemsStmt->fetchAll();

// Date formatting for template (d/m/Y or j/n/Y like in sample 16/8/2026)
$issueDateFormatted = date('j/n/Y', strtotime($invoice['issue_date']));
$dueDateFormatted = date('j/n/Y', strtotime($invoice['due_date']));

// Default Note if empty
$defaultNote = "Lingkup pekerjaan meliputi manajemen & monitoring konten untuk periode 1 bulan (30 hari). Pekerjaan akan mulai berjalan setelah pembayaran down payment (DP) sebesar 50% berhasil dikonfirmasi.";
$displayNote = !empty(trim($invoice['notes'] ?? '')) ? trim($invoice['notes']) : $defaultNote;

// Format WhatsApp Message & Phone
$rawPhone = preg_replace('/[^0-9]/', '', $invoice['client_phone'] ?? '');
if (str_starts_with($rawPhone, '0')) {
    $waPhone = '62' . substr($rawPhone, 1);
} elseif (str_starts_with($rawPhone, '62')) {
    $waPhone = $rawPhone;
} else {
    $waPhone = '';
}

$clientPicName = !empty($invoice['client_name']) ? $invoice['client_name'] : ($invoice['client_company'] ?: 'Bapak/Ibu');
$clientCompany = $invoice['client_company'] ?: $invoice['client_name'];
$projectName = $invoice['project_name'] ?: 'Layanan Kreatif & Media';
$totalFormatted = format_rupiah($invoice['total_amount']);

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? '') == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$invoiceUrl = $protocol . $host . url("invoice-view?id={$invoice['id']}");

$waText = "*TAGIHAN INVOICE RESMI*\n";
$waText .= "*PT Kala Kausa Prima*\n\n";
$waText .= "Halo Bapak/Ibu *$clientPicName* ($clientCompany),\n";
$waText .= "Berikut rincian tagihan invoice resmi Anda:\n\n";
$waText .= "- No. Invoice: #{$invoice['invoice_number']}\n";
$waText .= "- Proyek / Layanan: $projectName\n";
$waText .= "- Total Tagihan: *$totalFormatted*\n";
$waText .= "- Jatuh Tempo: $dueDateFormatted\n";
$waText .= "- Status: " . ($invoice['status'] === 'Paid' ? 'LUNAS (PAID)' : 'MENUNGGU PEMBAYARAN') . "\n\n";
$waText .= "Informasi Rekening Pembayaran:\n";
$waText .= "Bank: *" . AGENCY_BANK_NAME . "*\n";
$waText .= "No. Rek: *" . AGENCY_BANK_ACCOUNT . "*\n";
$waText .= "A.N: *" . AGENCY_BANK_HOLDER . "*\n\n";
$waText .= "Lihat & Unduh Invoice Resmi:\n";
$waText .= "$invoiceUrl\n\n";
$waText .= "Terima kasih atas kerja samanya.\n";
$waText .= "_PT Kala Kausa Prima • Built for What’s Next_";

$waUrl = !empty($waPhone) 
    ? "https://api.whatsapp.com/send?phone=$waPhone&text=" . urlencode($waText) 
    : "https://api.whatsapp.com/send?text=" . urlencode($waText);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?> - PT Kala Kausa Prima</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=saas-linear-1.0">
  <link rel="stylesheet" href="assets/css/invoice.css?v=saas-linear-1.0">
</head>
<body class="invoice-wrapper">

  <!-- Invoice Top Action Controls (Hidden on print) -->
  <div class="invoice-actions-bar">
    <a href="<?= url('invoices') ?>" class="btn-action-back">
      <span>Kembali ke Daftar Invoice</span>
    </a>
    
    <div class="invoice-actions-right">
      <!-- Status Pill Badge -->
      <span class="inv-status-pill inv-status-<?= strtolower($invoice['status']) ?>">
        <span class="inv-status-dot"></span>
        <span><?= $invoice['status'] === 'Paid' ? 'LUNAS (PAID)' : 'DRAFT' ?></span>
      </span>

      <?php if ($invoice['status'] === 'Draft' && !is_client()): ?>
        <button type="button" class="btn-action-secondary" style="color: #1570EF; border-color: #B2DDFF; background: #EFF8FF;" onclick="openEditInvoiceModal(<?= $invoice['id'] ?>)">
          <span>Edit Invoice</span>
        </button>
      <?php endif; ?>

      <?php if (!is_client()): ?>
        <!-- Share via WhatsApp Button -->
        <a href="<?= $waUrl ?>" target="_blank" class="btn-action-wa" title="Kirim rincian invoice ke WhatsApp Klien">
          <span>Share via WhatsApp</span>
        </a>
      <?php endif; ?>

      <?php if (!empty($invoice['receipt_file'])): ?>
        <button type="button" class="btn-action-secondary" onclick="viewReceiptImage('<?= UPLOAD_URL . '/' . htmlspecialchars($invoice['receipt_file']) ?>', 'Bukti Pelunasan #<?= htmlspecialchars($invoice['invoice_number']) ?>')">
          <span>Lihat Bukti Transfer</span>
        </button>
      <?php elseif ($invoice['status'] !== 'Paid'): ?>
        <button type="button" class="btn-action-secondary" style="color: #027A48; border-color: #A6F4C7; background: #F6FEF9;" onclick="triggerUploadModal('invoice', <?= $invoice['id'] ?>, 'Upload Bukti Pelunasan #<?= htmlspecialchars($invoice['invoice_number']) ?>')">
          <span>+ Upload Bukti Transfer</span>
        </button>
      <?php endif; ?>

      <button type="button" class="btn-action-primary" id="btn-download-inv-pdf" onclick="downloadInvoicePdf()">
        <span>Download PDF</span>
      </button>

      <button type="button" class="btn-action-secondary" onclick="window.print()">
        <span>Cetak</span>
      </button>
    </div>
  </div>

  <!-- 1:1 Matching Printable Invoice Canvas -->
  <div class="invoice-paper">

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
        <img src="assets/img/Logo-1.webp" alt="PT Kala Kausa Prima" class="km-logo-img">
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
          <img src="assets/img/qr_code.svg" alt="QR Code" class="km-qr-img">
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
          <span class="km-social-item">
            <?= AGENCY_INSTAGRAM ?>
          </span>
          <span class="km-social-item">
            <?= AGENCY_EMAIL ?>
          </span>
          <span class="km-social-item">
            <?= AGENCY_WEBSITE ?>
          </span>
        </div>
      </div>
    </div>

  </div>

  <?php require_once BASE_PATH . '/includes/modals.php'; ?>

  <!-- Scripts -->
  <script src="assets/js/html2pdf.bundle.min.js"></script>
  <script src="assets/js/app.js?v=<?= time() ?>"></script>
  <script src="assets/js/invoice.js?v=<?= time() ?>"></script>
  <script>
    async function downloadInvoicePdf() {
      const element = document.querySelector('.invoice-paper');
      const invoiceNumber = <?= json_encode($invoice['invoice_number']) ?>;
      const filename = `Invoice_${invoiceNumber.replace(/[^a-zA-Z0-9_-]/g, '_')}.pdf`;

      const btn = document.getElementById('btn-download-inv-pdf');
      const origHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = `<span>Menyiapkan PDF...</span>`;

      const opt = {
        margin: [0, 0, 0, 0],
        filename: filename,
        image: { type: 'jpeg', quality: 1 },
        html2canvas: { scale: 2.5, useCORS: true, letterRendering: true, scrollY: 0, windowWidth: 800 },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
      };

      try {
        if (typeof html2pdf !== 'undefined') {
          await html2pdf().set(opt).from(element).save();
          showToast('Invoice berhasil diunduh dalam format PDF!', 'success');
        } else {
          window.location.href = `api/invoices.php?action=download_pdf&id=<?= $invoice['id'] ?>`;
        }
      } catch (err) {
        console.error('Error generating PDF:', err);
        window.location.href = `api/invoices.php?action=download_pdf&id=<?= $invoice['id'] ?>`;
      } finally {
        btn.disabled = false;
        btn.innerHTML = origHtml;
      }
    }
  </script>
</body>
</html>

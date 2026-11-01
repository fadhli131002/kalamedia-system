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

// Fetch installment payments
$payStmt = $db->prepare("
    SELECT * FROM invoice_payments 
    WHERE invoice_id = ? AND COALESCE(is_deleted, 0) = 0 
    ORDER BY payment_date ASC, id ASC
");
$payStmt->execute([$invoiceId]);
$payments = $payStmt->fetchAll();

$totalPaid = 0;
foreach ($payments as $p) {
    $totalPaid += floatval($p['amount']);
}
$remainingBalance = max(0, floatval($invoice['total_amount']) - $totalPaid);
$isFullyPaid = ($invoice['status'] === 'Paid') || ($totalPaid >= floatval($invoice['total_amount']));
$isPartiallyPaid = (!$isFullyPaid && $totalPaid > 0);

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
$clientPortalUrl = $protocol . $host . url("invoice-view?id={$invoice['id']}&portal=client&_slot=client");

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
  <?php render_tab_uid_script(); ?>
</head>
<body class="invoice-wrapper">

  <!-- Invoice Top Action Controls (Hidden on print) -->
  <div class="invoice-actions-bar">
    <a href="<?= url('invoices') ?>" class="btn-action-back">
      <span>Kembali ke Daftar Invoice</span>
    </a>
    
    <div class="invoice-actions-right">
      <!-- Nebula Dark/Light Mode Toggle -->
      <button type="button" class="theme-toggle-compact" onclick="toggleTheme()" aria-label="Toggle Mode Nebula/Terang" title="Beralih Tema (Dark/Light)">
        <svg class="icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
        <svg class="icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
      </button>

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

        <!-- Share via Portal Button -->
        <button type="button" class="btn-action-secondary" onclick="sharePortalLink('<?= htmlspecialchars($clientPortalUrl, ENT_QUOTES) ?>')" title="Salin Link Invoice Portal Klien" style="display: inline-flex; align-items: center; gap: 6px; color: #1D4ED8; border-color: #BFDBFE; background: #EFF6FF;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
          <span>Share via Portal</span>
        </button>
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

        <?php if (!empty($payments)): ?>
          <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #E5E7EB;">
            <div class="km-total-line" style="font-weight: 600; color: #027A48;">
              <span>Telah Dibayar (<?= count($payments) ?>x)</span>
              <span><?= format_rupiah($totalPaid) ?></span>
            </div>
            <div class="km-total-line" style="font-weight: 800; font-size: 14px; color: <?= $remainingBalance <= 0 ? '#027A48' : '#B42318' ?>;">
              <span>Sisa Tagihan</span>
              <span><?= format_rupiah($remainingBalance) ?></span>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Riwayat Termin Pembayaran (Jika ada pembayaran tercatat) -->
    <?php if (!empty($payments)): ?>
      <div style="margin: 20px 0 28px 0; background: #FAFAFC; border: 1px solid #ECEEF5; border-radius: 8px; padding: 14px 18px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
          <div style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #1D2939;">
            Riwayat Pembayaran Termin (Payment History)
          </div>
          <span style="font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 4px; background: <?= $isFullyPaid ? '#ECFDF3' : '#FEF0C7' ?>; color: <?= $isFullyPaid ? '#027A48' : '#B54708' ?>;">
            <?= $isFullyPaid ? 'LUNAS (PAID)' : 'DIBAYAR SEBAGIAN (PARTIALLY PAID)' ?>
          </span>
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 11.5px;">
          <thead>
            <tr style="border-bottom: 1px solid #E4E7EC; color: #667085; text-align: left; font-size: 10.5px; text-transform: uppercase;">
              <th style="padding: 6px 8px; width: 30px;">#</th>
              <th style="padding: 6px 8px;">Keterangan / Tahap</th>
              <th style="padding: 6px 8px;">Tanggal</th>
              <th style="padding: 6px 8px;">Metode</th>
              <th style="padding: 6px 8px; text-align: right;">Nominal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payments as $idx => $p): ?>
              <tr style="border-bottom: 1px solid #F2F4F7;">
                <td style="padding: 7px 8px; color: #98A2B3;"><?= $idx + 1 ?></td>
                <td style="padding: 7px 8px; font-weight: 700; color: #101828;"><?= htmlspecialchars($p['term_name']) ?></td>
                <td style="padding: 7px 8px; color: #475467;"><?= date('d/m/Y', strtotime($p['payment_date'])) ?></td>
                <td style="padding: 7px 8px; color: #475467;"><?= htmlspecialchars($p['payment_method'] ?: 'Transfer') ?></td>
                <td style="padding: 7px 8px; text-align: right; font-weight: 700; color: #027A48;"><?= format_rupiah($p['amount']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

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

    async function sharePortalLink(url) {
      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(url);
          showToast('Link Invoice Portal Klien berhasil disalin ke clipboard!', 'success');
        } else {
          const temp = document.createElement('input');
          temp.value = url;
          document.body.appendChild(temp);
          temp.select();
          document.execCommand('copy');
          document.body.removeChild(temp);
          showToast('Link Invoice Portal Klien disalin!', 'success');
        }
      } catch (e) {
        prompt('Salin link portal klien berikut:', url);
      }
    }
  </script>
  <script src="assets/js/app.js?v=<?= time() ?>"></script>
</body>
</html>

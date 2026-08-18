<?php
/**
 * Kala Media Creative Agency - Official Invoice Template View (1:1 Match)
 */
require_auth();
$db = Database::getConnection();

$invoiceId = intval($_GET['id'] ?? 0);
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

$waText = "Halo Bapak/Ibu *$clientPicName* ($clientCompany),\n\n";
$waText .= "Semoga dalam keadaan baik. Kami dari *Kala Media Creative Agency* ingin menyampaikan rincian tagihan Invoice berikut:\n\n";
$waText .= "📄 *No. Invoice:* #{$invoice['invoice_number']}\n";
$waText .= "💼 *Proyek / Layanan:* $projectName\n";
$waText .= "💰 *Total Tagihan:* *$totalFormatted*\n";
$waText .= "📅 *Jatuh Tempo:* $dueDateFormatted\n";
$waText .= "📌 *Status:* " . ($invoice['status'] === 'Paid' ? '✅ LUNAS (PAID)' : '⏳ MENUNGGU PEMBAYARAN') . "\n\n";
$waText .= "💳 *Informasi Rekening Pembayaran:*\n";
$waText .= "Bank: *" . AGENCY_BANK_NAME . "*\n";
$waText .= "No. Rek: *" . AGENCY_BANK_ACCOUNT . "*\n";
$waText .= "A.N: *" . AGENCY_BANK_HOLDER . "*\n\n";
$waText .= "Terima kasih atas kerja samanya! 🙏\n";
$waText .= "— *Kala Media Creative*";

$waUrl = !empty($waPhone) 
    ? "https://api.whatsapp.com/send?phone=$waPhone&text=" . urlencode($waText) 
    : "https://api.whatsapp.com/send?text=" . urlencode($waText);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?> - Kala Media</title>
  <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
  <link rel="stylesheet" href="assets/css/invoice.css?v=<?= time() ?>">
</head>
<body class="invoice-wrapper">

  <!-- Invoice Top Action Controls (Hidden on print) -->
  <div class="invoice-actions-bar">
    <a href="<?= url('invoices') ?>" class="btn-action-back">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
      <span>Kembali ke Daftar Invoice</span>
    </a>
    
    <div class="invoice-actions-right">
      <!-- Status Pill Badge -->
      <span class="inv-status-pill inv-status-<?= strtolower($invoice['status']) ?>">
        <span class="inv-status-dot"></span>
        <span><?= $invoice['status'] === 'Paid' ? 'LUNAS (PAID)' : ($invoice['status'] === 'Sent' ? 'TERKIRIM (SENT)' : 'DRAFT') ?></span>
      </span>

      <!-- Share via WhatsApp Button -->
      <a href="<?= $waUrl ?>" target="_blank" class="btn-action-wa" title="Kirim rincian invoice ke WhatsApp Klien">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
        <span>Share via WhatsApp</span>
      </a>

      <?php if (!empty($invoice['receipt_file'])): ?>
        <button type="button" class="btn-action-secondary" onclick="viewReceiptImage('<?= UPLOAD_URL . '/' . htmlspecialchars($invoice['receipt_file']) ?>', 'Bukti Pelunasan #<?= htmlspecialchars($invoice['invoice_number']) ?>')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
          <span>Lihat Bukti Transfer</span>
        </button>
      <?php elseif ($invoice['status'] !== 'Paid'): ?>
        <button type="button" class="btn-action-secondary" style="color: #027A48; border-color: #A6F4C7; background: #F6FEF9;" onclick="triggerUploadModal('invoice', <?= $invoice['id'] ?>, 'Upload Bukti Pelunasan #<?= htmlspecialchars($invoice['invoice_number']) ?>')">
          <span>+ Upload Bukti Transfer</span>
        </button>
      <?php endif; ?>

      <button type="button" class="btn-action-primary" id="btn-download-inv-pdf" onclick="downloadInvoicePdf()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        <span>Download PDF</span>
      </button>

      <button type="button" class="btn-action-secondary" onclick="window.print()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
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
        <img src="assets/Jpg/Asset 3.png" alt="Kala Media Creative Agency" class="km-logo-img">
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
          <img src="assets/img/qr_code.svg" alt="QR Code" class="km-qr-img">
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
          <span class="km-social-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
            <?= AGENCY_INSTAGRAM ?>
          </span>
          <span class="km-social-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
            <?= AGENCY_EMAIL ?>
          </span>
          <span class="km-social-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
            <?= AGENCY_WEBSITE ?>
          </span>
        </div>
      </div>
    </div>

  </div>

  <?php require_once BASE_PATH . '/includes/modals.php'; ?>

  <!-- Scripts -->
  <script src="assets/js/html2pdf.bundle.min.js"></script>
  <script src="assets/js/app.js"></script>
  <script>
    async function downloadInvoicePdf() {
      const element = document.querySelector('.invoice-paper');
      const invoiceNumber = <?= json_encode($invoice['invoice_number']) ?>;
      const filename = `Invoice_${invoiceNumber.replace(/[^a-zA-Z0-9_-]/g, '_')}.pdf`;

      const btn = document.getElementById('btn-download-inv-pdf');
      const origHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = `<svg style="animation: spin 1s linear infinite; width:14px; height:14px; display:inline-block; margin-right:4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"></path></svg> <span>Menyiapkan PDF...</span>`;

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

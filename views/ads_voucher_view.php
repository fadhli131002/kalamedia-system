<?php
/**
 * Standalone Ads Top-Up Voucher & Outflow Invoice View
 * Kala Media Creative Agency
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$adsId = intval($_GET['id'] ?? 0);

if ($adsId <= 0) {
    die("ID Transaksi Ads tidak valid.");
}

$stmt = $db->prepare("
    SELECT a.*, c.company as client_company, c.name as client_pic, c.phone as client_phone,
           pr.name as project_name
    FROM ads_spend a
    JOIN clients c ON a.client_id = c.id
    LEFT JOIN projects pr ON a.project_id = pr.id
    WHERE a.id = ? AND COALESCE(a.is_deleted, 0) = 0
");
$stmt->execute([$adsId]);
$ad = $stmt->fetch();

if (!$ad) {
    die("Data pengeluaran iklan tidak ditemukan atau telah dihapus.");
}

$spentDate = $ad['spent_date'];
$voucherNumber = 'VCH-ADS-' . date('ym', strtotime($spentDate)) . str_pad($ad['id'], 3, '0', STR_PAD_LEFT);
$amountFormatted = format_rupiah($ad['amount']);
$spentDateFormatted = format_date($spentDate);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ads Top-Up Voucher #<?= htmlspecialchars($voucherNumber) ?> - Kala Media</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <style>
    body {
      background: #0B0F17;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 30px 16px;
      font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .top-actions-bar {
      width: 100%;
      max-width: 800px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      flex-wrap: wrap;
      gap: 12px;
    }
    .voucher-sheet {
      background: #FFFFFF;
      color: #000000;
      width: 100%;
      max-width: 800px;
      border-radius: 12px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.3);
      padding: 40px 44px;
      box-sizing: border-box;
    }
    @media print {
      body { background: #fff !important; padding: 0 !important; }
      .top-actions-bar { display: none !important; }
      .voucher-sheet { box-shadow: none !important; border-radius: 0 !important; padding: 20px !important; }
    }
  </style>
</head>
<body>

  <!-- Action Bar -->
  <div class="top-actions-bar">
    <div>
      <?php if (function_exists('is_logged_in') && is_logged_in()): ?>
        <a href="<?= url('expenses') ?>" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
          <span>Kembali ke Pengeluaran</span>
        </a>
      <?php else: ?>
        <span style="color: #94A3B8; font-size: 13px; font-weight: 600;">Kala Media Creative &bull; Official Ads Top-Up Voucher</span>
      <?php endif; ?>
    </div>

    <div style="display: flex; gap: 10px; align-items: center;">
      <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()" style="display: inline-flex; align-items: center; gap: 6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        <span>Cetak</span>
      </button>
      <button type="button" class="btn btn-primary btn-sm" id="btn-download-pdf" onclick="downloadPdf()" style="display: inline-flex; align-items: center; gap: 6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        <span>Unduh PDF</span>
      </button>
    </div>
  </div>

  <!-- Document Sheet -->
  <div class="voucher-sheet" id="print-area">
    
    <!-- Top Client & Date Bar -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 12px; border-bottom: 1.5px solid #000000; margin-bottom: 24px;">
      <div>
        <div style="font-size: 13px; font-weight: 500; color: #000000; margin-bottom: 3px;">Klien / Brand Pengiklan:</div>
        <div style="font-size: 17px; font-weight: 700; color: #000000;"><?= htmlspecialchars($ad['client_company']) ?></div>
        <div style="font-size: 12px; color: #4B5563; margin-top: 2px;">
          PIC: <?= htmlspecialchars($ad['client_pic'] ?: '-') ?> &bull; <?= htmlspecialchars($ad['project_name'] ?: 'Ads Management Campaign') ?>
        </div>
      </div>
      <div style="text-align: right;">
        <div style="font-size: 13px; font-weight: 800; color: #000000; margin-bottom: 3px;">VOUCHER: #<?= htmlspecialchars($voucherNumber) ?></div>
        <div style="font-size: 12.5px; color: #4B5563;">Tanggal Top-Up: <?= htmlspecialchars($spentDateFormatted) ?></div>
      </div>
    </div>

    <!-- Main Header: Logo & Title -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px;">
      <div style="width: 260px;">
        <img src="assets/Jpg/Asset 3.png" alt="Kala Media Creative Agency" style="height: 52px; width: auto; object-fit: contain; margin-bottom: 10px; display: block;">
        <div style="font-size: 10.5px; line-height: 1.35; color: #6B7280;">
          Jl. BSD Raya Utama, Pagedangan,<br>
          Kec. Pagedangan, Kabupaten Tangerang,<br>
          Banten 15339 &bull; <?= AGENCY_EMAIL ?>
        </div>
      </div>

      <div style="text-align: right;">
        <div style="font-size: 28px; font-weight: 900; color: #000000; letter-spacing: 1.5px; line-height: 1.1; margin-bottom: 6px; text-transform: uppercase;">
          ADS TOP-UP VOUCHER
        </div>
        <div style="font-size: 12px; font-weight: 800; color: #2563EB; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 4px;">
          BUKTI TRANSAKSI PENGELUARAN IKLAN DIGITAL
        </div>
        <div style="font-size: 12px; color: #000000;">
          Status Transaksi: <span style="font-weight: 700; color: #10B981;">BERHASIL (PAID / SUCCESS)</span>
        </div>
      </div>
    </div>

    <!-- Ads Metadata Grid -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 14px 18px; margin-bottom: 24px; font-size: 12px;">
      <div>
        <div style="margin-bottom: 6px;"><span style="color: #64748B; width: 110px; display: inline-block;">Platform Iklan:</span> <strong style="color: #0F172A;"><?= htmlspecialchars($ad['platform']) ?></strong></div>
        <div><span style="color: #64748B; width: 110px; display: inline-block;">ID Akun Iklan:</span> <span style="font-weight: 600; color: #0F172A;"><?= htmlspecialchars($ad['account_id'] ?: '-') ?></span></div>
      </div>
      <div>
        <div style="margin-bottom: 6px;"><span style="color: #64748B; width: 110px; display: inline-block;">Kategori Biaya:</span> <strong style="color: #0F172A;">Media Spend Outflow</strong></div>
        <div><span style="color: #64748B; width: 110px; display: inline-block;">Akun Pengiklan:</span> <span style="font-weight: 600; color: #0F172A;">Kala Media Business Manager</span></div>
      </div>
    </div>

    <!-- Deliverables & Notes Table -->
    <div style="border: 1px solid #E5E7EB; border-radius: 6px; overflow: hidden; margin-bottom: 24px;">
      <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
        <thead>
          <tr style="background: #F9FAFB; border-bottom: 1px solid #E5E7EB; text-align: left;">
            <th style="padding: 10px 14px; font-weight: 700; color: #000000; width: 65%;">RINCIAN PENGALOKASIAN ANGGARAN IKLAN</th>
            <th style="padding: 10px 14px; font-weight: 700; color: #000000; text-align: right; width: 35%;">NOMINAL TOP-UP (RP)</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="padding: 16px 14px; color: #374151; vertical-align: top;">
              <div style="font-weight: 700; color: #101828; margin-bottom: 4px;">Top-Up Saldo Iklan <?= htmlspecialchars($ad['platform']) ?></div>
              <div style="font-size: 11.5px; color: #6B7280; line-height: 1.4;">
                <?= htmlspecialchars($ad['notes'] ?: 'Alokasi anggaran iklan digital untuk proyek ' . ($ad['project_name'] ?: $ad['client_company'])) ?>
              </div>
            </td>
            <td style="padding: 16px 14px; font-weight: 800; font-size: 14px; color: #101828; text-align: right; vertical-align: top;">
              <?= $amountFormatted ?>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Total Paid Highlight -->
    <div style="background: #F9FAFB; border: 1.5px solid #000000; border-radius: 6px; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
      <div>
        <div style="font-size: 13px; font-weight: 800; color: #000000; letter-spacing: 0.5px; text-transform: uppercase;">
          TOTAL TOP-UP ANGGARAN IKLAN
        </div>
        <div style="font-size: 11px; color: #4B5563; margin-top: 3px;">
          Saldo iklan telah berhasil dialokasikan langsung ke akun iklan resmi.
        </div>
      </div>
      <div style="font-size: 24px; font-weight: 900; color: #000000; letter-spacing: 0.5px;">
        <?= $amountFormatted ?>
      </div>
    </div>

    <!-- Signatures Footer -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 36px; padding-top: 10px; font-size: 12px; text-align: center;">
      <div style="width: 190px;">
        <div style="color: #4B5563; margin-bottom: 4px;">Disiapkan oleh,</div>
        <div style="height: 48px; display: flex; align-items: center; justify-content: center; margin-bottom: 2px;">
          <img src="assets/Jpg/ttd-fadhli.png" alt="TTD Muhammad Fadhli" style="height: 42px; max-width: 120px; object-fit: contain;">
        </div>
        <div style="font-weight: 700; color: #000000; border-top: 1px solid #000000; padding-top: 4px;">Muhammad Fadhli</div>
        <div style="font-size: 10px; color: #6B7280;">Creative Manager</div>
      </div>
      <div style="width: 190px;">
        <div style="color: #4B5563; margin-bottom: 4px;">Disetujui oleh,</div>
        <div style="height: 48px; display: flex; align-items: center; justify-content: center; margin-bottom: 2px;">
          <img src="assets/Jpg/ttd-ilham.png" alt="TTD Ilham Lanang" style="height: 42px; max-width: 120px; object-fit: contain;">
        </div>
        <div style="font-weight: 700; color: #000000; border-top: 1px solid #000000; padding-top: 4px;">Ilham Lanang</div>
        <div style="font-size: 10px; color: #6B7280;">Marketing Manager</div>
      </div>
    </div>

    <!-- Bottom Tagline & Brand Links -->
    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #E5E7EB; padding-top: 12px; margin-top: 28px; font-size: 10.5px; color: #6B7280;">
      <div style="font-weight: 700; color: #000000;">Built to Be Seen.</div>
      <div style="display: flex; gap: 16px;">
        <span><?= AGENCY_INSTAGRAM ?></span>
        <span><?= AGENCY_EMAIL ?></span>
        <span><?= AGENCY_WEBSITE ?></span>
      </div>
    </div>

  </div>

  <script>
  function downloadPdf() {
    const btn = document.getElementById('btn-download-pdf');
    btn.innerHTML = '<span>Membuat PDF...</span>';
    btn.disabled = true;

    const element = document.getElementById('print-area');
    const opt = {
      margin:       [8, 8, 8, 8],
      filename:     'Ads_TopUp_<?= $voucherNumber ?>.pdf',
      image:        { type: 'jpeg', quality: 0.98 },
      html2canvas:  { scale: 2, useCORS: true, letterRendering: true },
      jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    html2pdf().set(opt).from(element).save().then(() => {
      btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg><span>Unduh PDF</span>';
      btn.disabled = false;
    }).catch(err => {
      console.error(err);
      alert('Gagal mengunduh PDF');
      btn.innerHTML = '<span>Unduh PDF</span>';
      btn.disabled = false;
    });
  }
  </script>
</body>
</html>

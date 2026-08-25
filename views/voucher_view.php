<?php
/**
 * Standalone Freelancer Payment Voucher & Fee Invoice View
 * Kala Media Creative Agency
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$payoutId = intval($_GET['id'] ?? 0);

if ($payoutId <= 0) {
    die("ID Voucher Pembayaran tidak valid.");
}

$stmt = $db->prepare("
    SELECT p.*, pr.name as project_name, c.company as client_company, c.name as client_pic
    FROM freelancer_payouts p
    JOIN projects pr ON p.project_id = pr.id
    JOIN clients c ON pr.client_id = c.id
    WHERE p.id = ? AND COALESCE(p.is_deleted, 0) = 0
");
$stmt->execute([$payoutId]);
$payout = $stmt->fetch();

if (!$payout) {
    die("Data voucher tidak ditemukan atau telah dihapus.");
}

$paidDate = $payout['paid_at'] ?: $payout['created_at'];
$voucherNumber = 'VCH-FL-' . date('ym', strtotime($paidDate)) . str_pad($payout['id'], 3, '0', STR_PAD_LEFT);
$amountFormatted = format_rupiah($payout['amount']);
$paymentDateFormatted = format_date($paidDate, true);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Voucher #<?= htmlspecialchars($voucherNumber) ?> - Kala Media</title>
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
    @media (max-width: 768px) {
      body {
        padding: 12px 8px !important;
      }
      .top-actions-bar {
        flex-direction: column;
        align-items: stretch !important;
        gap: 10px;
      }
      .voucher-sheet {
        padding: 20px 16px !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box;
        overflow-x: auto;
      }
      .voucher-header-row {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 14px;
        margin-bottom: 20px !important;
      }
      .voucher-header-col-right {
        text-align: left !important;
      }
      .voucher-title-large {
        font-size: 22px !important;
        letter-spacing: 1px !important;
      }
      .voucher-grid-2 {
        grid-template-columns: 1fr !important;
        gap: 12px !important;
      }
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
        <span style="color: #94A3B8; font-size: 13px; font-weight: 600;">Kala Media Creative &bull; Payment Voucher</span>
      <?php endif; ?>
    </div>

    <div style="display: flex; gap: 10px; align-items: center;">
      <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()" style="display: inline-flex; align-items: center; gap: 6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        <span>Cetak</span>
      </button>
      <button type="button" class="btn btn-primary btn-sm" onclick="downloadVoucherPdf()" style="display: inline-flex; align-items: center; gap: 6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        <span>Unduh PDF</span>
      </button>
    </div>
  </div>

  <!-- Voucher Sheet -->
  <div class="voucher-sheet" id="voucher-print-area">
    <!-- Top To / Date Bar -->
    <div class="voucher-header-row" style="display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 12px; border-bottom: 1.5px solid #000000; margin-bottom: 24px;">
      <div>
        <div style="font-size: 13px; font-weight: 500; color: #000000; margin-bottom: 3px;">Penerima Honor (Talenta / Freelancer):</div>
        <div style="font-size: 17px; font-weight: 700; color: #000000;"><?= htmlspecialchars($payout['freelancer_name']) ?></div>
        <div style="font-size: 12px; color: #4B5563; margin-top: 2px;">
          <?= htmlspecialchars($payout['freelancer_bank'] ?: 'Bank') ?> - <?= htmlspecialchars($payout['freelancer_account'] ?: '-') ?>
        </div>
      </div>
      <div class="voucher-header-col-right" style="text-align: right;">
        <div style="font-size: 13px; font-weight: 800; color: #000000; margin-bottom: 3px;">VOUCHER: #<?= htmlspecialchars($voucherNumber) ?></div>
        <div style="font-size: 12.5px; color: #4B5563;">Tanggal Bayar: <?= htmlspecialchars($paymentDateFormatted) ?></div>
      </div>
    </div>

    <!-- Main Header: Logo & Title -->
    <div class="voucher-header-row" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px;">
      <div style="width: 260px; max-width: 100%;">
        <img src="assets/Jpg/Asset 3.png" alt="Kala Media Creative Agency" style="height: 52px; width: auto; object-fit: contain; margin-bottom: 10px; display: block;">
        <div style="font-size: 10.5px; line-height: 1.35; color: #6B7280;">
          Jl. BSD Raya Utama, Pagedangan,<br>
          Kec. Pagedangan, Kabupaten Tangerang,<br>
          Banten 15339 &bull; <?= AGENCY_EMAIL ?>
        </div>
      </div>

      <div class="voucher-header-col-right" style="text-align: right;">
        <div class="voucher-title-large" style="font-size: 32px; font-weight: 900; color: #000000; letter-spacing: 1.5px; line-height: 1.1; margin-bottom: 8px; text-transform: uppercase;">
          PAYMENT VOUCHER
        </div>
        <div style="font-size: 12px; font-weight: 800; color: #4F46E5; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 4px;">
          BUKTI PEMBAYARAN FEE FREELANCER
        </div>
        <div style="font-size: 12px; color: #000000;">
          Status: <span style="font-weight: 700; color: #10B981;"><?= $payout['status'] === 'Paid' ? 'LUNAS (PAID)' : 'PENDING' ?></span>
        </div>
      </div>
    </div>

    <!-- Project & Client Metadata -->
    <div class="voucher-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 14px 18px; margin-bottom: 24px; font-size: 12px;">
      <div>
        <div style="margin-bottom: 6px;"><span style="color: #64748B; width: 110px; display: inline-block;">Proyek Terkait:</span> <strong style="color: #0F172A;"><?= htmlspecialchars($payout['project_name']) ?></strong></div>
        <div><span style="color: #64748B; width: 110px; display: inline-block;">Klien / Brand:</span> <span style="font-weight: 600; color: #0F172A;"><?= htmlspecialchars($payout['client_company']) ?></span></div>
      </div>
      <div>
        <div style="margin-bottom: 6px;"><span style="color: #64748B; width: 110px; display: inline-block;">Metode Bayar:</span> <strong style="color: #0F172A;">Bank Transfer</strong></div>
        <div><span style="color: #64748B; width: 110px; display: inline-block;">Akun Rekening:</span> <span style="font-weight: 600; color: #0F172A;"><?= htmlspecialchars($payout['freelancer_bank'] ?: 'BCA') ?> - <?= htmlspecialchars($payout['freelancer_account'] ?: '-') ?></span></div>
      </div>
    </div>

    <!-- Deliverables & Task Description Table -->
    <div style="border: 1px solid #E5E7EB; border-radius: 6px; overflow: hidden; margin-bottom: 24px;">
      <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
        <thead>
          <tr style="background: #F9FAFB; border-bottom: 1px solid #E5E7EB; text-align: left;">
            <th style="padding: 10px 14px; font-weight: 700; color: #000000; width: 65%;">URAIAN JASA / TUGAS PEKERJAAN</th>
            <th style="padding: 10px 14px; font-weight: 700; color: #000000; text-align: right; width: 35%;">NOMINAL HONOR (RP)</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="padding: 16px 14px; color: #374151; vertical-align: top;">
              <div style="font-weight: 700; color: #101828; margin-bottom: 4px;"><?= htmlspecialchars($payout['task_description'] ?: 'Jasa Pelaksanaan Deliverable') ?></div>
              <div style="font-size: 11.5px; color: #6B7280; line-height: 1.4;">
                Pelaksanaan deliverable untuk proyek <?= htmlspecialchars($payout['project_name']) ?> (Klien: <?= htmlspecialchars($payout['client_company']) ?>).
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
          TOTAL DIBAYARKAN (NET PAYOUT)
        </div>
        <div style="font-size: 11px; color: #4B5563; margin-top: 3px;">
          Pembayaran telah ditransfer penuh ke rekening talenta terkait.
        </div>
      </div>
      <div style="font-size: 26px; font-weight: 900; color: #000000; letter-spacing: 0.5px;">
        <?= $amountFormatted ?>
      </div>
    </div>

    <!-- Signatures Footer -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 36px; padding-top: 10px; font-size: 12px; text-align: center;">
      <div style="width: 180px;">
        <div style="color: #4B5563; margin-bottom: 50px;">Penerima Honor,</div>
        <div style="font-weight: 700; color: #000000; border-top: 1px solid #000000; padding-top: 4px;"><?= htmlspecialchars($payout['freelancer_name']) ?></div>
      </div>
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
    function downloadVoucherPdf() {
      const element = document.getElementById('voucher-print-area');
      const opt = {
        margin: [8, 8, 8, 8],
        filename: 'Voucher_Fee_<?= preg_replace('/[^a-zA-Z0-9_-]/', '_', $payout['freelancer_name']) ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, logging: false },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
      };
      html2pdf().set(opt).from(element).save();
    }
  </script>
</body>
</html>

<?php
/**
 * Standalone Employee Salary Slip View
 * PT Kala Kausa Prima
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$salaryId = intval($_GET['id'] ?? 0);

if ($salaryId <= 0) {
    die("ID Slip Gaji tidak valid.");
}

$stmt = $db->prepare("
    SELECT s.*, e.email as emp_email, e.phone as emp_phone, e.department as emp_dept, e.employment_type
    FROM salaries s
    LEFT JOIN employees e ON s.employee_id = e.id
    WHERE s.id = ? AND COALESCE(s.is_deleted, 0) = 0
");
$stmt->execute([$salaryId]);
$sal = $stmt->fetch();

if (!$sal) {
    die("Data slip gaji tidak ditemukan atau telah dihapus.");
}

$baseSalaryFormatted = format_rupiah($sal['base_salary']);
$allowanceFormatted = format_rupiah($sal['allowance']);
$deductionFormatted = format_rupiah($sal['deduction']);
$netSalaryFormatted = format_rupiah($sal['net_salary']);
$totalEarningsFormatted = format_rupiah($sal['base_salary'] + $sal['allowance']);
$paymentDateFormatted = format_date($sal['payment_date']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Slip Gaji - <?= htmlspecialchars($sal['employee_name']) ?> (<?= htmlspecialchars($sal['month_period']) ?>)</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=responsive-mobile-1.0">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <style>
    body {
      background: #F5F5F7;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 30px 16px;
      font-family: var(--font-sans);
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
    .slip-sheet {
      background: #FFFFFF;
      color: #000000;
      width: 100%;
      max-width: 800px;
      border-radius: 22px;
      border: 1px solid #ECEEF5;
      box-shadow: 0 10px 30px -10px rgba(18, 24, 48, 0.05), 0 2px 6px -1px rgba(18, 24, 48, 0.02);
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
      .slip-sheet {
        padding: 20px 16px !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box;
        overflow-x: auto;
      }
      .slip-header-row {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 14px;
        margin-bottom: 20px !important;
      }
      .slip-header-col-right {
        text-align: left !important;
      }
      .slip-title-large {
        font-size: 26px !important;
        letter-spacing: 1px !important;
      }
      .slip-grid-2 {
        grid-template-columns: 1fr !important;
        gap: 12px !important;
      }
    }
    @media print {
      body { background: #fff !important; padding: 0 !important; }
      .top-actions-bar { display: none !important; }
      .slip-sheet { box-shadow: none !important; border-radius: 0 !important; padding: 20px !important; }
    }
  </style>
</head>
<body>

  <!-- Action Bar -->
  <div class="top-actions-bar">
    <div>
      <?php if (function_exists('is_logged_in') && is_logged_in()): ?>
        <a href="<?= url('salaries') ?>" class="btn btn-secondary btn-sm">
          <span>Kembali ke Dashboard</span>
        </a>
      <?php else: ?>
        <span style="color: #94A3B8; font-size: 13px; font-weight: 600;">PT Kala Kausa Prima &bull; Official Salary Slip</span>
      <?php endif; ?>
    </div>

    <div style="display: flex; gap: 10px; align-items: center;">
      <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">
        <span>Cetak</span>
      </button>
      <button type="button" class="btn btn-primary btn-sm" onclick="downloadSlipPdf()">
        <span>Unduh PDF</span>
      </button>
    </div>
  </div>

  <!-- Salary Sheet -->
  <div class="slip-sheet" id="slip-print-area">
    <!-- Top To / Date Bar -->
    <div class="slip-header-row" style="display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 12px; border-bottom: 1.5px solid #000000; margin-bottom: 24px;">
      <div>
        <div style="font-size: 13px; font-weight: 500; color: #000000; margin-bottom: 3px;">Karyawan:</div>
        <div style="font-size: 17px; font-weight: 700; color: #000000;"><?= htmlspecialchars($sal['employee_name']) ?></div>
        <div style="font-size: 12px; color: #4B5563; margin-top: 2px;">
          <?= htmlspecialchars($sal['employee_position'] ?: '-') ?> &bull; <?= htmlspecialchars($sal['emp_dept'] ?: 'Creative & Production') ?>
        </div>
      </div>
      <div class="slip-header-col-right" style="text-align: right;">
        <div style="font-size: 13px; color: #000000; margin-bottom: 3px;">Periode: <?= htmlspecialchars($sal['month_period'] ?: '-') ?></div>
        <div style="font-size: 13px; font-weight: 700; color: #000000; letter-spacing: 0.5px;">Tanggal Bayar: <?= htmlspecialchars($paymentDateFormatted) ?></div>
      </div>
    </div>

    <!-- Main Header: Logo & Title -->
    <div class="slip-header-row" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px;">
      <div style="width: 250px; max-width: 100%;">
        <img src="assets/img/Logo-1.webp" alt="PT Kala Kausa Prima" style="height: 85px; width: auto; max-width: 130px; object-fit: contain; margin-bottom: 12px; display: block;">
        <div style="font-size: 10.5px; line-height: 1.35; color: #6B7280;">
          Jl. Padat Karya, Cisauk Girang, RT 001/RW 005,<br>
          Kel. Cisauk, Tangerang,<br>
          Banten 15341
        </div>
      </div>

      <div class="slip-header-col-right" style="text-align: right;">
        <div class="slip-title-large" style="font-size: 44px; font-weight: 900; color: #000000; letter-spacing: 2px; line-height: 1; margin-bottom: 8px; text-transform: uppercase;">
          SLIP GAJI
        </div>
        <div style="font-size: 12.5px; color: #000000;">
          Status: <span style="font-weight: 700; color: #10B981;"><?= $sal['status'] === 'Paid' ? 'LUNAS (PAID)' : 'PENDING' ?></span>
        </div>
      </div>
    </div>

    <!-- Employee Info Summary Grid -->
    <div class="slip-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; background: #F8F9FC; border: 1px solid #ECEEF5; border-radius: 14px; padding: 16px 20px; margin-bottom: 24px; font-size: 12px;">
      <div>
        <div style="margin-bottom: 6px;"><span style="color: #6E7485; width: 120px; display: inline-block;">Status Kepegawaian:</span> <strong style="color: #000000;"><?= htmlspecialchars($sal['employment_type'] ?: 'Full-time') ?></strong></div>
        <div><span style="color: #6E7485; width: 120px; display: inline-block;">Departemen:</span> <span><?= htmlspecialchars($sal['emp_dept'] ?: 'Creative & Production') ?></span></div>
      </div>
      <div>
        <div style="margin-bottom: 6px;"><span style="color: #6E7485; width: 120px; display: inline-block;">Rekening Payroll:</span> <strong style="color: #000000;"><?= htmlspecialchars($sal['bank_name'] ?: 'BCA') ?> - <?= htmlspecialchars($sal['bank_account'] ?: '-') ?></strong></div>
        <div><span style="color: #6E7485; width: 120px; display: inline-block;">Email / Kontak:</span> <span><?= htmlspecialchars($sal['emp_email'] ?: '-') ?></span></div>
      </div>
    </div>

    <!-- 2 Columns: Penghasilan vs Potongan -->
    <div class="slip-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
      <!-- Column A: Penghasilan -->
      <div style="border: 1px solid #E5E7EB; border-radius: 6px; overflow: hidden;">
        <div style="background: #F9FAFB; padding: 10px 14px; font-weight: 700; font-size: 13px; color: #000000; border-bottom: 1px solid #E5E7EB; letter-spacing: 0.3px;">
          A. PENGHASILAN (EARNINGS)
        </div>
        <div style="padding: 14px; font-size: 12.5px;">
          <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #F3F4F6;">
            <span style="color: #374151;">Gaji Pokok:</span>
            <strong style="color: #111827;"><?= $baseSalaryFormatted ?></strong>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #F3F4F6;">
            <span style="color: #374151;">Tunjangan & Bonus:</span>
            <strong style="color: #111827;"><?= $allowanceFormatted ?></strong>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 10px 0 4px; font-weight: 800; font-size: 13px; color: #10B981;">
            <span>Total Penghasilan Kotor:</span>
            <span><?= $totalEarningsFormatted ?></span>
          </div>
        </div>
      </div>

      <!-- Column B: Potongan -->
      <div style="border: 1px solid #E5E7EB; border-radius: 6px; overflow: hidden;">
        <div style="background: #F9FAFB; padding: 10px 14px; font-weight: 700; font-size: 13px; color: #000000; border-bottom: 1px solid #E5E7EB; letter-spacing: 0.3px;">
          B. POTONGAN (DEDUCTIONS)
        </div>
        <div style="padding: 14px; font-size: 12.5px;">
          <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #F3F4F6;">
            <span style="color: #374151;">Potongan Kasbon & BPJS:</span>
            <strong style="color: #EF4444;"><?= $deductionFormatted ?></strong>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #F3F4F6;">
            <span style="color: #374151;">PPh 21:</span>
            <span style="color: #6B7280;">Rp 0 (Ditanggung Agensi)</span>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 10px 0 4px; font-weight: 800; font-size: 13px; color: #EF4444;">
            <span>Total Potongan:</span>
            <span><?= $deductionFormatted ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Take Home Pay Highlight -->
    <div style="background: #F9FAFB; border: 1.5px solid #000000; border-radius: 6px; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
      <div>
        <div style="font-size: 13px; font-weight: 800; color: #000000; letter-spacing: 0.5px; text-transform: uppercase;">
          GAJI BERSIH (TAKE-HOME PAY)
        </div>
        <div style="font-size: 11px; color: #4B5563; margin-top: 3px;">
          Catatan: <?= htmlspecialchars($sal['notes'] ?: 'Pembayaran gaji bulanan PT Kala Kausa Prima.') ?>
        </div>
      </div>
      <div style="font-size: 26px; font-weight: 900; color: #000000; letter-spacing: 0.5px;">
        <?= $netSalaryFormatted ?>
      </div>
    </div>

    <!-- Signatures Footer -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 36px; padding-top: 10px; font-size: 12px; text-align: center;">
      <div style="width: 200px;">
        <div style="color: #4B5563; margin-bottom: 50px;">Penerima,</div>
        <div style="font-weight: 700; color: #000000; border-top: 1px solid #000000; padding-top: 4px;"><?= htmlspecialchars($sal['employee_name']) ?></div>
      </div>
      <div style="width: 220px;">
        <div style="color: #4B5563; margin-bottom: 4px;">Marketing Manager,</div>
        <div style="height: 48px; display: flex; align-items: center; justify-content: center; margin-bottom: 2px;">
          <img src="assets/Jpg/ttd-ilham.png" alt="TTD Ilham Lanang" style="height: 44px; max-width: 140px; object-fit: contain;">
        </div>
        <div style="font-weight: 700; color: #000000; border-top: 1px solid #000000; padding-top: 4px;">Ilham Lanang</div>
      </div>
    </div>

    <!-- Bottom Tagline & Brand Links -->
    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #E5E7EB; padding-top: 12px; margin-top: 28px; font-size: 10.5px; color: #6B7280;">
      <div style="font-weight: 700; color: #000000;">Built for What’s Next</div>
      <div style="display: flex; gap: 16px;">
        <span><?= AGENCY_INSTAGRAM ?></span>
        <span><?= AGENCY_EMAIL ?></span>
        <span><?= AGENCY_WEBSITE ?></span>
      </div>
    </div>
  </div>

  <script>
    function downloadSlipPdf() {
      const element = document.getElementById('slip-print-area');
      const opt = {
        margin: [8, 8, 8, 8],
        filename: 'Slip_Gaji_<?= preg_replace('/[^a-zA-Z0-9_-]/', '_', $sal['employee_name']) ?>_<?= htmlspecialchars($sal['month_period']) ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, logging: false },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
      };
      html2pdf().set(opt).from(element).save();
    }
  </script>
</body>
</html>

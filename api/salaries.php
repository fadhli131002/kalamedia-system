<?php
/**
 * Salaries & Employee Payroll API Handler
 * Kalamedia Agency Financial System
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_auth();

$db = Database::getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. Create Salary / Payroll Record
if ($action === 'create_salary') {
    $employeeId = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
    $employeeName = trim($_POST['employee_name'] ?? '');
    $employeePosition = trim($_POST['employee_position'] ?? '');
    $bankName = trim($_POST['bank_name'] ?? 'BCA');
    $bankAccount = trim($_POST['bank_account'] ?? '');
    $monthPeriod = trim($_POST['month_period'] ?? date('Y-m'));
    $baseSalary = floatval($_POST['base_salary'] ?? 0);
    $allowance = floatval($_POST['allowance'] ?? 0);
    $deduction = floatval($_POST['deduction'] ?? 0);
    $netSalary = $baseSalary + $allowance - $deduction;
    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
    $status = $_POST['status'] ?? 'Pending'; // 'Pending' or 'Paid'
    $paidAt = ($status === 'Paid') ? date('Y-m-d H:i:s') : null;
    $notes = trim($_POST['notes'] ?? '');

    // If employee_id is given, populate or verify employee details
    if ($employeeId && empty($employeeName)) {
        $emp = $db->query("SELECT * FROM employees WHERE id = $employeeId")->fetch();
        if ($emp) {
            $employeeName = $emp['name'];
            if (empty($employeePosition)) $employeePosition = $emp['position'];
            if (empty($bankName)) $bankName = $emp['bank_name'];
            if (empty($bankAccount)) $bankAccount = $emp['bank_account'];
            if ($baseSalary <= 0) $baseSalary = floatval($emp['base_salary']);
            $netSalary = $baseSalary + $allowance - $deduction;
        }
    }

    if (empty($employeeName) || $baseSalary <= 0) {
        echo json_encode(['success' => false, 'message' => 'Nama karyawan dan gaji pokok wajib diisi.']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO salaries (
                employee_id, employee_name, employee_position, bank_name, bank_account,
                month_period, base_salary, allowance, deduction, net_salary,
                payment_date, status, paid_at, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $employeeId, $employeeName, $employeePosition, $bankName, $bankAccount,
            $monthPeriod, $baseSalary, $allowance, $deduction, $netSalary,
            $paymentDate, $status, $paidAt, $notes
        ]);
        $salaryId = $db->lastInsertId();

        log_activity(
            'salary',
            "Penggajian Karyawan: $employeeName",
            "Periode $monthPeriod - Take Home Pay: " . format_rupiah($netSalary) . " ($status)",
            $netSalary
        );

        echo json_encode([
            'success' => true,
            'message' => 'Data gaji karyawan berhasil disimpan!',
            'salary_id' => $salaryId
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 2. Mark Salary as Paid
if ($action === 'mark_salary_paid') {
    $salaryId = intval($_POST['salary_id'] ?? 0);
    $paidAt = $_POST['paid_at'] ?? date('Y-m-d H:i:s');

    if ($salaryId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID Gaji tidak valid.']);
        exit;
    }

    try {
        $stmt = $db->prepare("UPDATE salaries SET status = 'Paid', paid_at = ? WHERE id = ?");
        $stmt->execute([$paidAt, $salaryId]);

        $sal = $db->query("SELECT * FROM salaries WHERE id = $salaryId")->fetch();
        if ($sal) {
            log_activity(
                'salary',
                "Pembayaran Gaji #{$sal['id']} Terverifikasi",
                "Gaji {$sal['employee_name']} periode {$sal['month_period']} sebesar " . format_rupiah($sal['net_salary']) . " telah ditransfer.",
                $sal['net_salary']
            );
        }

        echo json_encode(['success' => true, 'message' => 'Gaji karyawan berhasil ditandai Lunas (Paid)!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 3. Create Employee
if ($action === 'create_employee') {
    $name = trim($_POST['name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $department = trim($_POST['department'] ?? 'Creative & Tech');
    $employmentType = $_POST['employment_type'] ?? 'Full-time';
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $bankName = trim($_POST['bank_name'] ?? 'BCA');
    $bankAccount = trim($_POST['bank_account'] ?? '');
    $baseSalary = floatval($_POST['base_salary'] ?? 0);
    $status = $_POST['status'] ?? 'Active';

    if (empty($name) || empty($position)) {
        echo json_encode(['success' => false, 'message' => 'Nama karyawan dan posisi/jabatan wajib diisi.']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO employees (
                name, position, department, employment_type, email, phone,
                bank_name, bank_account, base_salary, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $name, $position, $department, $employmentType, $email, $phone,
            $bankName, $bankAccount, $baseSalary, $status
        ]);
        $empId = $db->lastInsertId();

        log_activity('client', "Karyawan Baru Ditambahkan: $name", "Posisi: $position - Gaji Pokok: " . format_rupiah($baseSalary), $baseSalary);

        echo json_encode([
            'success' => true,
            'message' => 'Data karyawan baru berhasil ditambahkan!',
            'employee_id' => $empId
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 3b. Get Single Employee Data
if ($action === 'get_employee') {
    $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID karyawan tidak valid.']);
        exit;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$id]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$emp) {
            echo json_encode(['success' => false, 'message' => 'Data karyawan tidak ditemukan.']);
            exit;
        }

        $emp['formatted_base_salary'] = format_rupiah($emp['base_salary']);
        echo json_encode(['success' => true, 'employee' => $emp]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 3c. Update Employee Data
if ($action === 'update_employee') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $department = trim($_POST['department'] ?? 'Creative & Production');
    $employmentType = $_POST['employment_type'] ?? 'Full-time';
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $bankName = trim($_POST['bank_name'] ?? 'BCA');
    $bankAccount = trim($_POST['bank_account'] ?? '');
    $baseSalary = floatval($_POST['base_salary'] ?? 0);
    $status = $_POST['status'] ?? 'Active';

    if ($id <= 0 || empty($name) || empty($position)) {
        echo json_encode(['success' => false, 'message' => 'Nama karyawan dan posisi/jabatan wajib diisi.']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            UPDATE employees SET
                name = ?,
                position = ?,
                department = ?,
                employment_type = ?,
                email = ?,
                phone = ?,
                bank_name = ?,
                bank_account = ?,
                base_salary = ?,
                status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $name, $position, $department, $employmentType,
            $email, $phone, $bankName, $bankAccount,
            $baseSalary, $status,
            $id
        ]);

        log_activity('client', "Data Karyawan Diperbarui: $name", "Jabatan: $position | Gaji Pokok: " . format_rupiah($baseSalary), $baseSalary);

        echo json_encode([
            'success' => true,
            'message' => 'Data karyawan berhasil diperbarui!'
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui data karyawan: ' . $e->getMessage()]);
        exit;
    }
}

// 4. Delete Salary Record
if ($action === 'delete_salary') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid.']);
        exit;
    }

    try {
        $stmt = $db->prepare("UPDATE salaries SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Data penggajian berhasil dihapus!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 5. Delete Employee
if ($action === 'delete_employee') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid.']);
        exit;
    }

    try {
        $db->exec("DELETE FROM employees WHERE id = $id");
        echo json_encode(['success' => true, 'message' => 'Data karyawan berhasil dihapus!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 6. Get Salary Slip Data
if ($action === 'get_slip_data' || $action === 'get_salary') {
    $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID slip gaji tidak valid.']);
        exit;
    }

    try {
        $stmt = $db->prepare("
            SELECT s.*, 
                   e.email as emp_email, e.phone as emp_phone, 
                   e.department as emp_dept, e.employment_type
            FROM salaries s
            LEFT JOIN employees e ON s.employee_id = e.id
            WHERE s.id = ? AND COALESCE(s.is_deleted, 0) = 0
        ");
        $stmt->execute([$id]);
        $salary = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$salary) {
            echo json_encode(['success' => false, 'message' => 'Data slip gaji tidak ditemukan.']);
            exit;
        }

        $formatted = [
            'base_salary' => format_rupiah($salary['base_salary']),
            'allowance' => format_rupiah($salary['allowance']),
            'deduction' => format_rupiah($salary['deduction']),
            'net_salary' => format_rupiah($salary['net_salary']),
            'payment_date' => format_date($salary['payment_date'])
        ];

        echo json_encode([
            'success' => true,
            'salary' => $salary,
            'formatted' => $formatted
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 7. Download Slip Gaji PDF (Standalone Document)
if ($action === 'download_slip_pdf') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        die("ID Slip Gaji tidak valid.");
    }

    $sal = $db->query("
        SELECT s.*, e.email as emp_email, e.phone as emp_phone, e.department as emp_dept, e.employment_type
        FROM salaries s
        LEFT JOIN employees e ON s.employee_id = e.id
        WHERE s.id = $id AND COALESCE(s.is_deleted, 0) = 0
    ")->fetch();

    if (!$sal) {
        die("Data slip gaji tidak ditemukan atau telah dihapus.");
    }

    $empName = $sal['employee_name'] ?: 'Karyawan';
    $rawPeriod = $sal['month_period'] ?: date('Y-m');
    $mmyy = date('my');
    if (preg_match('/^\d{4}-\d{2}$/', $rawPeriod)) {
        $parts = explode('-', $rawPeriod);
        $mmyy = $parts[1] . substr($parts[0], 2);
    }
    $filename = "SLP-{$empName}-{$mmyy}.pdf";
    $autoDownload = ($_GET['auto_download'] ?? '0') === '1';

    $totalEarnings = floatval($sal['base_salary']) + floatval($sal['allowance']);

    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
      <meta charset="UTF-8">
      <title><?= htmlspecialchars($filename) ?></title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
      <script src="../assets/js/html2pdf.bundle.min.js"></script>
      <style>
        * { box-sizing: border-box; }
        body {
          margin: 0;
          padding: 24px;
          background: #F8FAFC;
          font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, Arial, sans-serif;
          color: #000000;
        }
        .actions-bar {
          max-width: 800px;
          margin: 0 auto 16px;
          display: flex;
          justify-content: space-between;
          align-items: center;
        }
        .btn-back {
          background: #FFFFFF;
          color: #344054;
          border: 1px solid #D0D5DD;
          font-weight: 600;
          font-size: 13px;
          height: 36px;
          padding: 0 14px;
          border-radius: 8px;
          display: inline-flex;
          align-items: center;
          gap: 6px;
          text-decoration: none;
        }
        .btn-download {
          background: #101828;
          color: #FFFFFF;
          border: none;
          font-weight: 600;
          font-size: 13px;
          height: 36px;
          padding: 0 16px;
          border-radius: 8px;
          display: inline-flex;
          align-items: center;
          gap: 6px;
          cursor: pointer;
        }
        .slip-canvas {
          background: #FFFFFF;
          max-width: 800px;
          margin: 0 auto;
          padding: 36px 40px;
          border: 1px solid #EAECF0;
          border-radius: 8px;
          box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        }
        @media print {
          @page { size: A4 portrait; margin: 10mm; }
          body { background: #FFFFFF; padding: 0; }
          .actions-bar { display: none !important; }
          .slip-canvas { border: none; box-shadow: none; padding: 0; max-width: 100%; }
        }
      </style>
    </head>
    <body>

      <div class="actions-bar">
        <a href="../index.php?page=salaries" class="btn-back">&larr; Kembali ke Payroll</a>
        <button type="button" class="btn-download" id="btn-dl-slip" onclick="downloadThisSlipPdf()">
          📥 Download PDF (<?= htmlspecialchars($filename) ?>)
        </button>
      </div>

      <div class="slip-canvas" id="slip-print-area">
        <!-- Top To / Date Bar -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 12px; border-bottom: 1.5px solid #000000; margin-bottom: 24px;">
          <div>
            <div style="font-size: 13px; font-weight: 500; color: #000000; margin-bottom: 3px;">Karyawan:</div>
            <div style="font-size: 16px; font-weight: 700; color: #000000;"><?= htmlspecialchars($sal['employee_name']) ?></div>
            <div style="font-size: 12px; color: #4B5563; margin-top: 2px;">
              <span><?= htmlspecialchars($sal['employee_position'] ?: '-') ?></span> &bull; <span><?= htmlspecialchars($sal['emp_dept'] ?: 'Creative & Production') ?></span>
            </div>
          </div>
          <div style="text-align: right;">
            <div style="font-size: 13px; color: #000000; margin-bottom: 3px;">Periode: <?= htmlspecialchars($sal['month_period']) ?></div>
            <div style="font-size: 13px; font-weight: 700; color: #000000; letter-spacing: 0.5px;">Tanggal Bayar: <?= format_date($sal['payment_date']) ?></div>
          </div>
        </div>

        <!-- Main Header: Logo & Title -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px;">
          <div style="width: 250px;">
            <img src="../assets/Jpg/Asset 3.png" alt="Kala Media Creative Agency" style="height: 52px; width: auto; object-fit: contain; margin-bottom: 10px; display: block;">
            <div style="font-size: 10.5px; line-height: 1.35; color: #6B7280;">
              <?= AGENCY_ADDRESS ?>
            </div>
          </div>

          <div style="text-align: right;">
            <div style="font-size: 48px; font-weight: 900; color: #000000; letter-spacing: 3px; line-height: 1; margin-bottom: 10px; text-transform: uppercase;">
              SLIP GAJI
            </div>
            <div style="font-size: 12.5px; color: #000000;">
              Status: <span style="font-weight: 700; color: <?= $sal['status'] === 'Paid' ? '#10B981' : '#F59E0B' ?>;"><?= $sal['status'] === 'Paid' ? 'LUNAS (PAID)' : 'PENDING' ?></span>
            </div>
          </div>
        </div>

        <!-- Employee Info Summary Grid -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 14px 18px; margin-bottom: 24px; font-size: 12px;">
          <div>
            <div style="margin-bottom: 6px;"><span style="color: #64748B; width: 120px; display: inline-block;">Status Hubungan:</span> <strong style="color: #0F172A;"><?= htmlspecialchars($sal['employment_type'] ?: 'Full-time') ?></strong></div>
            <div><span style="color: #64748B; width: 120px; display: inline-block;">Departemen:</span> <span><?= htmlspecialchars($sal['emp_dept'] ?: 'Creative & Production') ?></span></div>
          </div>
          <div>
            <div style="margin-bottom: 6px;"><span style="color: #64748B; width: 120px; display: inline-block;">Rekening Transfer:</span> <strong style="color: #0F172A;"><?= htmlspecialchars(($sal['bank_name'] ?: 'BCA') . ' - ' . ($sal['bank_account'] ?: '-')) ?></strong></div>
            <div><span style="color: #64748B; width: 120px; display: inline-block;">Metode:</span> <span>Bank Transfer Otomatis</span></div>
          </div>
        </div>

        <!-- Rincian Penerimaan & Potongan -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
          <div style="border: 1px solid #E2E8F0; border-radius: 6px; overflow: hidden;">
            <div style="background: #F1F5F9; padding: 8px 12px; font-size: 11px; font-weight: 700; color: #334155; text-transform: uppercase; border-bottom: 1px solid #E2E8F0;">
              A. PENERIMAAN (EARNINGS)
            </div>
            <div style="padding: 12px; font-size: 12px;">
              <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span style="color: #64748B;">Gaji Pokok:</span>
                <span style="font-weight: 600; color: #0F172A;"><?= format_rupiah($sal['base_salary']) ?></span>
              </div>
              <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span style="color: #64748B;">Tunjangan & Bonus:</span>
                <span style="font-weight: 600; color: #0F172A;"><?= format_rupiah($sal['allowance']) ?></span>
              </div>
              <div style="display: flex; justify-content: space-between; padding-top: 8px; border-top: 1px dashed #CBD5E1; font-weight: 700;">
                <span style="color: #0F172A;">Total Penerimaan:</span>
                <span style="color: #0F172A;"><?= format_rupiah($totalEarnings) ?></span>
              </div>
            </div>
          </div>

          <div style="border: 1px solid #E2E8F0; border-radius: 6px; overflow: hidden;">
            <div style="background: #F1F5F9; padding: 8px 12px; font-size: 11px; font-weight: 700; color: #334155; text-transform: uppercase; border-bottom: 1px solid #E2E8F0;">
              B. POTONGAN (DEDUCTIONS)
            </div>
            <div style="padding: 12px; font-size: 12px;">
              <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span style="color: #64748B;">Potongan / Kasbon / BPJS:</span>
                <span style="font-weight: 600; color: #DC2626;"><?= format_rupiah($sal['deduction']) ?></span>
              </div>
              <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span style="color: #64748B;">PPh 21:</span>
                <span style="font-weight: 500; color: #16A34A; font-size: 11px;">Rp 0 (Ditanggung Agensi)</span>
              </div>
              <div style="display: flex; justify-content: space-between; padding-top: 8px; border-top: 1px dashed #CBD5E1; font-weight: 700;">
                <span style="color: #DC2626;">Total Potongan:</span>
                <span style="color: #DC2626;"><?= format_rupiah($sal['deduction']) ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Take Home Pay Box -->
        <div style="border: 2px solid #000000; border-radius: 6px; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; background: #FAFAFA;">
          <div>
            <div style="font-size: 11px; font-weight: 800; color: #000000; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">
              GAJI BERSIH DITERIMA (TAKE HOME PAY)
            </div>
            <div style="font-size: 11px; color: #64748B;">
              Catatan: <?= htmlspecialchars($sal['notes'] ?: 'Pembayaran gaji bulanan Kala Media Creative.') ?>
            </div>
          </div>
          <div style="font-size: 26px; font-weight: 900; color: #000000; letter-spacing: -0.5px;">
            <?= format_rupiah($sal['net_salary']) ?>
          </div>
        </div>

        <!-- Signatures -->
        <div style="display: flex; justify-content: space-between; padding: 0 20px; margin-bottom: 28px;">
          <div style="text-align: center; width: 220px;">
            <div style="font-size: 12px; color: #4B5563; margin-bottom: 50px;">Penerima,</div>
            <div style="font-size: 13px; font-weight: 700; color: #000000; border-top: 1px solid #000000; padding-top: 6px;"><?= htmlspecialchars($sal['employee_name']) ?></div>
          </div>
          <div style="text-align: center; width: 220px;">
            <div style="font-size: 12px; color: #4B5563; margin-bottom: 4px;">Head of Finance & Operations,</div>
            <div style="height: 52px; display: flex; align-items: center; justify-content: center; margin-bottom: 2px;">
              <img src="assets/Jpg/ttd-ilham.png" alt="TTD Ilham Lanang" style="height: 48px; max-width: 140px; object-fit: contain;">
            </div>
            <div style="font-size: 13px; font-weight: 700; color: #000000; border-top: 1px solid #000000; padding-top: 6px;">Ilham Lanang</div>
          </div>
        </div>

        <!-- Footer Contact -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 1px solid #E5E7EB; font-size: 10px; color: #6B7280;">
          <div style="font-weight: 700; color: #111827; letter-spacing: 0.5px;">Built to Be Seen.</div>
          <div><?= AGENCY_EMAIL ?> &bull; <?= AGENCY_WEBSITE ?></div>
        </div>
      </div>

      <script>
      async function downloadThisSlipPdf() {
        const element = document.getElementById('slip-print-area');
        const filename = <?= json_encode($filename) ?>;
        const btn = document.getElementById('btn-dl-slip');
        const origText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<span>Menyiapkan PDF...</span>';

        const opt = {
          margin:       [0, 0, 0, 0],
          filename:     filename,
          image:        { type: 'jpeg', quality: 0.98 },
          html2canvas:  { scale: 2, useCORS: true, letterRendering: true, scrollY: 0 },
          jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
          pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
        };

        try {
          if (typeof html2pdf !== 'undefined') {
            await html2pdf().set(opt).from(element).save();
          } else {
            window.print();
          }
        } catch (err) {
          console.error('PDF Error:', err);
          window.print();
        } finally {
          btn.disabled = false;
          btn.innerHTML = origText;
        }
      }

      <?php if ($autoDownload): ?>
      window.addEventListener('DOMContentLoaded', () => {
        setTimeout(downloadThisSlipPdf, 300);
      });
      <?php endif; ?>
      </script>
    </body>
    </html>
    <?php
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);


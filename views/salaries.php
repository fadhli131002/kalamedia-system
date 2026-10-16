<?php
/**
 * Kalamedia Employee Salaries & Payroll Management View (Untitled UI Design System)
 */
require_auth();
$db = Database::getConnection();

$tab = $_GET['tab'] ?? 'payroll'; // 'payroll' or 'employees'
$filterPeriod = $_GET['period'] ?? '';
$filterStatus = $_GET['status'] ?? '';

// Build Query for Salaries
$whereSal = ["COALESCE(s.is_deleted, 0) = 0"];
$paramsSal = [];

if (!empty($filterPeriod)) {
    $whereSal[] = "s.month_period = ?";
    $paramsSal[] = $filterPeriod;
}
if (!empty($filterStatus)) {
    $whereSal[] = "s.status = ?";
    $paramsSal[] = $filterStatus;
}

$salQuerySql = "
    SELECT s.*, e.department, e.employment_type
    FROM salaries s
    LEFT JOIN employees e ON s.employee_id = e.id
    WHERE " . implode(" AND ", $whereSal) . "
    ORDER BY s.payment_date DESC, s.id DESC
";

$salStmt = $db->prepare($salQuerySql);
$salStmt->execute($paramsSal);
$salariesList = $salStmt->fetchAll();

// Fetch Employees Master
$employeesList = $db->query("SELECT * FROM employees ORDER BY status ASC, name ASC")->fetchAll();

// Metrics & KPIs
$currentMonth = date('Y-m');
$paidSalaryMonth = floatval($db->query("SELECT COALESCE(SUM(net_salary), 0) FROM salaries WHERE status = 'Paid' AND COALESCE(is_deleted, 0) = 0 AND (month_period = '$currentMonth' OR strftime('%Y-%m', paid_at) = '$currentMonth')")->fetchColumn());
$totalPendingSalary = floatval($db->query("SELECT COALESCE(SUM(net_salary), 0) FROM salaries WHERE status = 'Pending' AND COALESCE(is_deleted, 0) = 0")->fetchColumn());
$pendingCount = intval($db->query("SELECT COUNT(*) FROM salaries WHERE status = 'Pending' AND COALESCE(is_deleted, 0) = 0")->fetchColumn());
$activeEmployeeCount = intval($db->query("SELECT COUNT(*) FROM employees WHERE status = 'Active'")->fetchColumn());
$totalSalaryAllTime = floatval($db->query("SELECT COALESCE(SUM(net_salary), 0) FROM salaries WHERE status = 'Paid' AND COALESCE(is_deleted, 0) = 0")->fetchColumn());

// Available Month Periods for Filter
$availablePeriods = $db->query("SELECT DISTINCT month_period FROM salaries WHERE COALESCE(is_deleted, 0) = 0 ORDER BY month_period DESC")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manajemen Gaji Karyawan (Payroll) - PT Kala Kausa Prima</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=responsive-mobile-1.0">
  <script src="assets/js/html2pdf.bundle.min.js"></script>
</head>
<body>
  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body">

        <!-- 1. KPI Breakdown Cards -->
        <div class="kpi-grid">
          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Gaji Terbayar (Bulan Ini)</span>
            </div>
            <div class="kpi-value" style="color: var(--success-text);"><?= format_rupiah($paidSalaryMonth) ?></div>
            <div class="kpi-meta" style="color: var(--success-text); font-weight: 600;">&bull; Sudah ditransfer ke rekening tim</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Pending Gaji (Antrean)</span>
            </div>
            <div class="kpi-value" style="color: var(--warning-text);"><?= format_rupiah($totalPendingSalary) ?></div>
            <div class="kpi-meta" style="color: var(--warning-text); font-weight: 600;">&bull; <?= $pendingCount ?> karyawan menunggu transfer</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Total Karyawan Aktif</span>
            </div>
            <div class="kpi-value"><?= $activeEmployeeCount ?> Orang</div>
            <div class="kpi-meta">Tim internal creative & tech</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Total Payroll All-Time</span>
            </div>
            <div class="kpi-value"><?= format_rupiah($totalSalaryAllTime) ?></div>
            <div class="kpi-meta">Akumulasi pengeluaran gaji</div>
          </div>
        </div>

        <!-- 2. Main Payroll Container -->
        <div class="glass-panel">
          <div class="panel-header" style="flex-wrap: wrap; gap: 14px;">
            <div>
              <h3 class="panel-title">
                Manajemen Penggajian Karyawan & Slip Gaji
              </h3>
              <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Kelola pembayaran take-home pay, cetak slip gaji resmi, dan bukti transfer</p>
            </div>

            <!-- Tab Switchers & Action Buttons -->
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
              <div class="date-filter-group">
                <a href="<?= url('salaries?tab=payroll') ?>" class="filter-btn <?= $tab === 'payroll' ? 'active' : '' ?>">Daftar Payroll Gaji</a>
                <a href="<?= url('salaries?tab=employees') ?>" class="filter-btn <?= $tab === 'employees' ? 'active' : '' ?>">Master Karyawan</a>
              </div>

              <?php if ($tab === 'payroll'): ?>
                <button class="btn btn-primary btn-sm" onclick="openModal('modal-input-salary')">
                  <span>Input Gaji Karyawan</span>
                </button>
              <?php else: ?>
                <button class="btn btn-primary btn-sm" onclick="openModal('modal-create-employee')">
                  <span>Tambah Karyawan</span>
                </button>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($tab === 'payroll'): ?>
            <!-- Filter & Search Bar for Payroll -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; background: #F9FAFB; padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
              <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <label style="font-size: 12px; color: var(--text-secondary); font-weight: 600;">Filter Periode:</label>
                <select class="form-select" style="width: 150px; padding: 5px 8px; font-size: 12px;" onchange="location.href='<?= url('salaries?tab=payroll&status=' . urlencode($filterStatus)) ?>&period=' + this.value">
                  <option value="">Semua Periode</option>
                  <?php foreach ($availablePeriods as $p): ?>
                    <option value="<?= $p ?>" <?= $filterPeriod === $p ? 'selected' : '' ?>><?= $p ?></option>
                  <?php endforeach; ?>
                </select>

                <label style="font-size: 12px; color: var(--text-secondary); font-weight: 600; margin-left: 8px;">Status:</label>
                <select class="form-select" style="width: 130px; padding: 5px 8px; font-size: 12px;" onchange="location.href='<?= url('salaries?tab=payroll&period=' . urlencode($filterPeriod)) ?>&status=' + this.value">
                  <option value="">Semua Status</option>
                  <option value="Paid" <?= $filterStatus === 'Paid' ? 'selected' : '' ?>>Paid (Lunas)</option>
                  <option value="Pending" <?= $filterStatus === 'Pending' ? 'selected' : '' ?>>Pending</option>
                </select>
              </div>

              <div>
                <input type="text" id="salary-search-input" class="form-control" style="width: 240px; padding: 6px 12px; font-size: 12px;" placeholder="Cari nama atau jabatan...">
              </div>
            </div>

            <!-- Payroll Table -->
            <div class="table-responsive">
              <table class="table-custom" id="salaries-table">
                <thead>
                  <tr>
                    <th>Periode & Tanggal</th>
                    <th>Nama Karyawan & Rekening</th>
                    <th>Komponen Gaji</th>
                    <th>Take-Home Pay</th>
                    <th>Status</th>
                    <th style="text-align: right;">Slip Gaji & Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($salariesList)): ?>
                    <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 28px;">Belum ada data pembayaran gaji pada filter ini.</td></tr>
                  <?php else: ?>
                    <?php foreach ($salariesList as $s): ?>
                      <tr>
                        <td>
                          <div style="font-weight: 700; color: #101828;"><?= htmlspecialchars($s['month_period']) ?></div>
                          <div style="font-size: 11px; color: var(--text-secondary);">Bayar: <?= format_date($s['payment_date']) ?></div>
                        </td>
                        <td>
                          <div style="font-weight: 700; color: #101828;"><?= htmlspecialchars($s['employee_name']) ?></div>
                          <div style="font-size: 11px; color: var(--text-secondary);">
                            <?= htmlspecialchars($s['employee_position'] ?: 'Staff') ?> &bull; <?= htmlspecialchars($s['bank_name'] ?: 'BCA') ?> (<?= htmlspecialchars($s['bank_account'] ?: '-') ?>)
                          </div>
                        </td>
                        <td>
                          <div style="font-size: 12px; color: #101828;">
                            Pokok: <span style="font-weight: 600;"><?= format_rupiah($s['base_salary']) ?></span>
                          </div>
                          <div style="font-size: 11px; color: var(--text-secondary); margin-top: 2px;">
                            <span style="color: var(--success-text); font-weight: 600;">+ <?= format_rupiah($s['allowance']) ?></span> | 
                            <span style="color: var(--danger-text); font-weight: 600;">- <?= format_rupiah($s['deduction']) ?></span>
                          </div>
                        </td>
                        <td>
                          <div style="font-size: 15px; font-weight: 800; color: #101828;">
                            <?= format_rupiah($s['net_salary']) ?>
                          </div>
                          <?php if (!empty($s['notes'])): ?>
                            <div style="font-size: 10.5px; color: var(--text-muted); max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                              <?= htmlspecialchars($s['notes']) ?>
                            </div>
                          <?php endif; ?>
                        </td>
                        <td>
                          <span class="badge-status badge-<?= strtolower($s['status']) ?>">
                            <?= $s['status'] ?>
                          </span>
                        </td>
                        <td style="text-align: right;">
                          <div style="display: inline-flex; gap: 6px; align-items: center;">
                            <!-- Slip Gaji Modal Trigger -->
                            <button type="button" class="btn btn-secondary btn-sm" onclick="openSlipGajiModal(<?= $s['id'] ?>)" title="Lihat & Cetak Slip Gaji">
                              <span>Slip Gaji</span>
                            </button>

                            <!-- Receipt Actions -->
                            <?php if (!empty($s['receipt_file'])): ?>
                              <button type="button" class="btn btn-secondary btn-sm" onclick="viewReceiptImage('<?= UPLOAD_URL . '/' . htmlspecialchars($s['receipt_file']) ?>', 'Bukti Transfer Gaji <?= htmlspecialchars($s['employee_name']) ?>')" title="Lihat Bukti Transfer">
                                <span>Struk</span>
                              </button>
                            <?php else: ?>
                              <button type="button" class="btn btn-primary btn-sm" onclick="triggerUploadModal('salary', <?= $s['id'] ?>, 'Upload Bukti Transfer Gaji <?= htmlspecialchars($s['employee_name']) ?>')" title="Upload Bukti Transfer">
                                <span>+ Bukti</span>
                              </button>
                            <?php endif; ?>

                            <!-- Mark Paid Shortcut -->
                            <?php if ($s['status'] === 'Pending'): ?>
                              <button type="button" class="btn btn-success btn-sm" onclick="markSalaryPaid(<?= $s['id'] ?>)" title="Tandai Sudah Ditransfer">
                                &#10003; Lunas
                              </button>
                            <?php endif; ?>

                            <!-- Delete Option -->
                            <button type="button" class="btn-delete-ghost" onclick="confirmDeleteSalary(<?= $s['id'] ?>, 'Gaji <?= htmlspecialchars($s['employee_name']) ?> (<?= htmlspecialchars($s['month_period']) ?>)')" title="Hapus Data Gaji" style="padding: 4px 8px; font-size: 12px; font-weight: 600;">
                              Hapus
                            </button>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

          <?php else: ?>
            <!-- Master Karyawan Table -->
            <div class="table-responsive">
              <table class="table-custom" id="employees-table">
                <thead>
                  <tr>
                    <th>Nama Karyawan</th>
                    <th>Jabatan & Departemen</th>
                    <th>Status Kerja</th>
                    <th>Kontak</th>
                    <th>Rekening Pembayaran</th>
                    <th>Gaji Pokok Default</th>
                    <th style="text-align: right;">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($employeesList)): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 28px;">Belum ada master data karyawan.</td></tr>
                  <?php else: ?>
                    <?php foreach ($employeesList as $emp): ?>
                      <tr>
                        <td>
                          <div style="font-weight: 700; color: #101828;"><?= htmlspecialchars($emp['name']) ?></div>
                          <span class="badge-status badge-<?= $emp['status'] === 'Active' ? 'paid' : 'draft' ?>" style="font-size: 10px; padding: 2px 6px; margin-top: 2px;">
                            <?= $emp['status'] ?>
                          </span>
                        </td>
                        <td>
                          <div style="font-weight: 600; color: #101828;"><?= htmlspecialchars($emp['position']) ?></div>
                          <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($emp['department'] ?: '-') ?></div>
                        </td>
                        <td>
                          <span style="font-size: 12px; color: var(--text-secondary); background: #F2F4F7; padding: 3px 8px; border-radius: 4px; font-weight: 500;">
                            <?= htmlspecialchars($emp['employment_type'] ?: 'Full-time') ?>
                          </span>
                        </td>
                        <td>
                          <div style="font-size: 12px; color: #101828;"><?= htmlspecialchars($emp['email'] ?: '-') ?></div>
                          <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($emp['phone'] ?: '-') ?></div>
                        </td>
                        <td>
                          <div style="font-weight: 600; color: #101828;"><?= htmlspecialchars($emp['bank_name'] ?: 'BCA') ?></div>
                          <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($emp['bank_account'] ?: '-') ?></div>
                        </td>
                        <td style="font-weight: 700; color: #101828;">
                          <?= format_rupiah($emp['base_salary']) ?>
                        </td>
                        <td style="text-align: right;">
                          <div style="display: inline-flex; gap: 6px; align-items: center;">
                            <!-- Edit Employee Button -->
                            <button type="button" class="btn btn-secondary btn-sm" onclick='openEditEmployeeModal(<?= json_encode($emp, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Data Karyawan" style="padding: 5px 12px; font-size: 12px; border-radius: 6px; font-weight: 600;">
                              <span>Edit</span>
                            </button>

                            <!-- Quick Create Salary -->
                            <button type="button" class="btn btn-primary btn-sm" onclick="quickCreateSalaryForEmp(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($emp['position'], ENT_QUOTES) ?>', '<?= htmlspecialchars($emp['bank_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($emp['bank_account'], ENT_QUOTES) ?>', <?= $emp['base_salary'] ?>)">
                              + Buat Gaji
                            </button>

                            <!-- Delete Employee -->
                            <button type="button" class="btn-delete-ghost" onclick="confirmDeleteEmployee(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['name'], ENT_QUOTES) ?>')" title="Hapus Karyawan" style="padding: 4px 8px; font-size: 12px; font-weight: 600;">
                              Hapus
                            </button>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

        </div>

  <?php require_once BASE_PATH . '/includes/footer.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      initTableSearch('salary-search-input', 'salaries-table');
    });

    // Quick open salary modal for specific employee
    function quickCreateSalaryForEmp(id, name, position, bank, account, salary) {
      const select = document.getElementById('salary-employee-select');
      if (select) {
        select.value = id;
      }
      document.getElementById('salary-emp-name').value = name;
      document.getElementById('salary-emp-position').value = position;
      document.getElementById('salary-emp-bank').value = bank || 'BCA';
      document.getElementById('salary-emp-account').value = account || '';
      document.getElementById('salary-base-input').value = formatRupiahDisplay(salary);
      calculateSalaryNet();
      openModal('modal-input-salary');
    }

    // Mark salary as paid
    async function markSalaryPaid(id) {
      showConfirmDeleteModal({
        title: 'Konfirmasi Pelunasan Gaji',
        descriptionHtml: 'Apakah Anda yakin ingin menandai pembayaran gaji ini sebagai <strong>Lunas (Paid)</strong>?',
        confirmBtnText: 'Tandai Lunas',
        onConfirm: async () => {
          const formData = new FormData();
          formData.append('salary_id', id);

          try {
            const res = await fetch('api/salaries.php?action=mark_salary_paid', {
              method: 'POST',
              body: formData
            });
            const data = await res.json();
            if (data.success) {
              showToast(data.message, 'success');
              setTimeout(() => window.location.reload(), 600);
            } else {
              showToast(data.message || 'Gagal mengubah status', 'danger');
            }
          } catch (err) {
            showToast('Terjadi kesalahan koneksi', 'danger');
          }
        }
      });
    }

    // Delete salary
    function confirmDeleteSalary(id, name) {
      showConfirmDeleteModal({
        title: 'Hapus Data Gaji?',
        descriptionHtml: `Apakah Anda yakin ingin menghapus data transaksi <strong style="color: #101828;">${name}</strong>? Tindakan ini bersifat permanen dan data yang dihapus tidak dapat dipulihkan.`,
        confirmBtnText: 'Hapus Data Gaji',
        onConfirm: async () => {
          const formData = new FormData();
          formData.append('id', id);

          try {
            const res = await fetch('api/salaries.php?action=delete_salary', {
              method: 'POST',
              body: formData
            });
            const data = await res.json();
            if (data.success) {
              showToast(data.message, 'success');
              setTimeout(() => window.location.reload(), 600);
            } else {
              showToast(data.message || 'Gagal menghapus data gaji', 'danger');
            }
          } catch (err) {
            showToast('Terjadi kesalahan koneksi', 'danger');
          }
        }
      });
    }

    // Delete employee
    function confirmDeleteEmployee(id, name) {
      showConfirmDeleteModal({
        title: 'Hapus Karyawan?',
        descriptionHtml: `Apakah Anda yakin ingin menghapus data karyawan <strong style="color: #101828;">${name}</strong> dari sistem? Tindakan ini bersifat permanen dan data yang dihapus tidak dapat dipulihkan.`,
        confirmBtnText: 'Hapus Karyawan',
        onConfirm: async () => {
          const formData = new FormData();
          formData.append('id', id);

          try {
            const res = await fetch('api/salaries.php?action=delete_employee', {
              method: 'POST',
              body: formData
            });
            const data = await res.json();
            if (data.success) {
              showToast(data.message, 'success');
              setTimeout(() => window.location.reload(), 600);
            } else {
              showToast(data.message || 'Gagal menghapus karyawan', 'danger');
            }
          } catch (err) {
            showToast('Terjadi kesalahan koneksi', 'danger');
          }
        }
      });
    }
  </script>
</body>
</html>

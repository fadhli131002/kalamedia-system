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

// Atur Periode for Total Payroll KPI
$payrollPeriod = $_GET['payroll_period'] ?? 'all';
$payrollPeriodLabel = 'Semua Waktu';
$payrollWhere = "status = 'Paid' AND COALESCE(is_deleted, 0) = 0";

if ($payrollPeriod === 'this_month') {
    $curM = date('Y-m');
    $payrollWhere .= " AND (month_period = '$curM' OR strftime('%Y-%m', paid_at) = '$curM')";
    $payrollPeriodLabel = 'Bulan Ini (' . date('M Y') . ')';
} elseif ($payrollPeriod === 'last_month') {
    $lastM = date('Y-m', strtotime('-1 month'));
    $payrollWhere .= " AND (month_period = '$lastM' OR strftime('%Y-%m', paid_at) = '$lastM')";
    $payrollPeriodLabel = 'Bulan Lalu (' . date('M Y', strtotime('-1 month')) . ')';
} elseif ($payrollPeriod === 'this_quarter') {
    $curY = date('Y');
    $curQ = ceil(intval(date('n')) / 3);
    $startM = str_pad(($curQ - 1) * 3 + 1, 2, '0', STR_PAD_LEFT);
    $endM = str_pad($curQ * 3, 2, '0', STR_PAD_LEFT);
    $payrollWhere .= " AND (date(COALESCE(paid_at, payment_date)) BETWEEN '$curY-$startM-01' AND '$curY-$endM-31')";
    $payrollPeriodLabel = "Kuartal $curQ ($curY)";
} elseif ($payrollPeriod === 'this_year') {
    $curY = date('Y');
    $payrollWhere .= " AND (strftime('%Y', COALESCE(paid_at, payment_date)) = '$curY' OR month_period LIKE '$curY%')";
    $payrollPeriodLabel = "Tahun $curY";
} elseif (preg_match('/^\d{4}-\d{2}$/', $payrollPeriod)) {
    $payrollWhere .= " AND (month_period = '$payrollPeriod' OR strftime('%Y-%m', paid_at) = '$payrollPeriod')";
    $payrollPeriodLabel = date('M Y', strtotime($payrollPeriod . '-01'));
} else {
    $payrollPeriod = 'all';
    $payrollPeriodLabel = 'Semua Waktu';
}

$totalSalarySelected = floatval($db->query("SELECT COALESCE(SUM(net_salary), 0) FROM salaries WHERE $payrollWhere")->fetchColumn());
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manajemen Gaji Karyawan (Payroll) - PT Kala Kausa Prima</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=saas-linear-2.5">
  <?php render_tab_uid_script(); ?>
  <script src="assets/js/html2pdf.bundle.min.js"></script>
  <style>
    .payroll-filter-field {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .payroll-filter-field label {
      font-size: 12px;
      color: var(--text-secondary);
      font-weight: 600;
      white-space: nowrap;
      margin: 0;
    }
    .payroll-filter-bar .form-select {
      height: 38px !important;
      padding: 0 30px 0 12px !important;
      border-radius: 10px !important;
      font-size: 12.5px !important;
      font-weight: 500 !important;
      background-color: #FFFFFF !important;
      border: 1px solid #D0D5DD !important;
      color: #101828 !important;
      box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04) !important;
    }
    .payroll-search-wrapper {
      position: relative;
      width: 260px;
      max-width: 100%;
    }
    .payroll-search-wrapper svg {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #667085;
      pointer-events: none;
    }
    .payroll-search-wrapper .form-control {
      width: 100% !important;
      height: 38px !important;
      padding: 0 12px 0 36px !important;
      border-radius: 10px !important;
      font-size: 12.5px !important;
      background-color: #FFFFFF !important;
      border: 1px solid #D0D5DD !important;
      color: #101828 !important;
      box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04) !important;
      box-sizing: border-box !important;
    }
    html[data-theme="dark"] .payroll-filter-bar .form-select,
    html[data-theme="dark"] .payroll-search-wrapper .form-control,
    body.dark-mode .payroll-filter-bar .form-select,
    body.dark-mode .payroll-search-wrapper .form-control {
      background-color: #0E121D !important;
      border: 1px solid rgba(255, 255, 255, 0.12) !important;
      color: #F8FAFC !important;
    }
    html[data-theme="dark"] .payroll-search-wrapper svg,
    body.dark-mode .payroll-search-wrapper svg {
      color: #94A3B8 !important;
    }

    @media (max-width: 768px) {
      .kpi-grid.payroll-kpi-grid {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 10px !important;
      }
      .payroll-header-panel {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 12px !important;
      }
      .payroll-header-actions {
        flex-direction: column !important;
        align-items: stretch !important;
        width: 100% !important;
        gap: 10px !important;
      }
      .payroll-header-actions .date-filter-group {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        width: 100% !important;
        padding: 4px !important;
        border-radius: 12px !important;
        gap: 4px !important;
        overflow: visible !important;
      }
      .payroll-header-actions .date-filter-group .filter-btn {
        width: 100% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        text-align: center !important;
        padding: 8px 10px !important;
        border-radius: 9px !important;
        font-size: 12.5px !important;
      }
      .payroll-header-actions .btn {
        width: 100% !important;
        height: 38px !important;
        align-self: stretch !important;
        justify-content: center !important;
        border-radius: 10px !important;
        font-weight: 700 !important;
        font-size: 13px !important;
      }
      .payroll-filter-bar {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 10px !important;
        padding: 12px !important;
        border-radius: 14px !important;
      }
      .payroll-filter-bar .payroll-filter-controls {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 10px !important;
        width: 100% !important;
      }
      .payroll-filter-field {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 5px !important;
        min-width: 0 !important;
      }
      .payroll-filter-field label {
        font-size: 11px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.03em !important;
      }
      .payroll-filter-bar select,
      .payroll-filter-bar input {
        width: 100% !important;
      }
      .payroll-search-wrapper {
        width: 100% !important;
      }
    }

    /* Salaries View NebulaPay Dark Mode Overrides */
    html[data-theme="dark"] .quick-subtab-bar > div,
    body.dark-mode .quick-subtab-bar > div {
      background: #161D2F !important;
      border: 1px solid rgba(255, 255, 255, 0.08) !important;
    }
    html[data-theme="dark"] .quick-subtab-btn,
    body.dark-mode .quick-subtab-btn {
      color: #94A3B8 !important;
    }
    html[data-theme="dark"] .quick-subtab-btn:hover:not(.active),
    body.dark-mode .quick-subtab-btn:hover:not(.active) {
      color: #F8FAFC !important;
      background: rgba(255, 255, 255, 0.06) !important;
    }
    html[data-theme="dark"] .quick-subtab-btn.active,
    body.dark-mode .quick-subtab-btn.active {
      background: #5B3DF5 !important;
      color: #FFFFFF !important;
      box-shadow: 0 2px 8px rgba(99, 102, 241, 0.35) !important;
    }
    html[data-theme="dark"] .date-filter-group,
    body.dark-mode .date-filter-group {
      background: #161D2F !important;
      border: 1px solid rgba(255, 255, 255, 0.08) !important;
    }
    html[data-theme="dark"] .date-filter-group .filter-btn,
    body.dark-mode .date-filter-group .filter-btn {
      color: #94A3B8 !important;
      background: transparent !important;
    }
    html[data-theme="dark"] .date-filter-group .filter-btn:hover:not(.active),
    body.dark-mode .date-filter-group .filter-btn:hover:not(.active) {
      color: #F8FAFC !important;
      background: rgba(255, 255, 255, 0.06) !important;
    }
    html[data-theme="dark"] .date-filter-group .filter-btn.active,
    body.dark-mode .date-filter-group .filter-btn.active {
      background: #5B3DF5 !important;
      color: #FFFFFF !important;
      box-shadow: 0 2px 8px rgba(91, 61, 245, 0.35) !important;
    }
    html[data-theme="dark"] .payroll-filter-bar,
    body.dark-mode .payroll-filter-bar {
      background: #161D2F !important;
      border: 1px solid rgba(255, 255, 255, 0.08) !important;
      color: #F8FAFC !important;
    }
    html[data-theme="dark"] .kala-table-scroll,
    body.dark-mode .kala-table-scroll {
      background: #12151F !important;
      border: 1px solid rgba(255, 255, 255, 0.08) !important;
    }
    html[data-theme="dark"] .table-custom td,
    body.dark-mode .table-custom td {
      background: transparent !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
      color: #F8FAFC !important;
    }
    html[data-theme="dark"] .table-custom th,
    body.dark-mode .table-custom th {
      background: #0E121D !important;
      color: #94A3B8 !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
    }
    .payroll-kpi-grid {
      position: relative !important;
      z-index: 40 !important;
      overflow: visible !important;
    }
    .kpi-card.kpi-card-dropdown,
    .payroll-kpi-grid .kpi-card:last-child {
      position: relative !important;
      overflow: visible !important;
      z-index: 50 !important;
    }
    #payroll-period-menu {
      position: absolute;
      right: 8px;
      top: 38px;
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 10px;
      box-shadow: 0 14px 30px -4px rgba(15, 23, 42, 0.2), 0 8px 12px -4px rgba(15, 23, 42, 0.12);
      z-index: 9999 !important;
      width: 220px;
      padding: 6px;
    }
    .payroll-period-link {
      display: block;
      padding: 6px 10px;
      font-size: 12px;
      border-radius: 6px;
      color: #334155;
      text-decoration: none;
      transition: background 0.15s ease, color 0.15s ease;
    }
    .payroll-period-link:hover {
      background: #F1F5F9;
      color: #0F172A;
    }
    .payroll-period-link.active {
      color: #2563EB !important;
      font-weight: 600 !important;
      background: #EFF6FF !important;
    }
    html[data-theme="dark"] #payroll-period-menu,
    body.dark-mode #payroll-period-menu {
      background: #161D2F !important;
      border: 1px solid rgba(255, 255, 255, 0.14) !important;
      box-shadow: 0 16px 36px -4px rgba(0, 0, 0, 0.7) !important;
    }
    html[data-theme="dark"] .payroll-period-link,
    body.dark-mode .payroll-period-link {
      color: #94A3B8 !important;
    }
    html[data-theme="dark"] .payroll-period-link:hover,
    body.dark-mode .payroll-period-link:hover {
      background: rgba(255, 255, 255, 0.08) !important;
      color: #FFFFFF !important;
    }
    html[data-theme="dark"] .payroll-period-link.active,
    body.dark-mode .payroll-period-link.active {
      background: rgba(37, 99, 235, 0.22) !important;
      color: #60A5FA !important;
    }
    html[data-theme="dark"] .btn-period-toggle,
    body.dark-mode .btn-period-toggle {
      background: #1E293B !important;
      border-color: rgba(255, 255, 255, 0.15) !important;
      color: #F8FAFC !important;
    }
  </style>
</head>
<body>
  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body">
        
        <?php if (is_owner() || is_admin()): ?>
        <!-- Quick Sub-Tab Switcher: Invoice Klien | Pengeluaran | Penggajian -->
        <div class="quick-subtab-bar" style="margin-bottom: 14px;">
          <div style="display: grid; grid-template-columns: repeat(3, 1fr); padding: 4px; border-radius: 12px; gap: 4px; box-sizing: border-box; width: 100%;">
            <a href="<?= url('invoices?_slot=internal') ?>" class="quick-subtab-btn" style="display: flex; align-items: center; justify-content: center; padding: 8px 6px; border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none; text-align: center; white-space: nowrap;">
              Invoice Klien
            </a>
            <a href="<?= url('expenses?_slot=internal') ?>" class="quick-subtab-btn" style="display: flex; align-items: center; justify-content: center; padding: 8px 6px; border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none; text-align: center; white-space: nowrap;">
              Pengeluaran
            </a>
            <a href="<?= url('salaries?_slot=internal') ?>" class="quick-subtab-btn active" style="display: flex; align-items: center; justify-content: center; padding: 8px 6px; border-radius: 8px; font-size: 12px; font-weight: 700; text-decoration: none; text-align: center; white-space: nowrap; background: #5B3DF5; color: #FFFFFF; box-shadow: 0 2px 6px rgba(91,61,245,0.25);">
              Penggajian
            </a>
          </div>
        </div>
        <?php endif; ?>

        <!-- 1. KPI Breakdown Cards (Shopeers SaaS Style) -->
        <div class="kpi-grid payroll-kpi-grid" style="position: relative; z-index: 40; overflow: visible;">
          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Gaji Terbayar (Bulan Ini)</span>
              <span class="kpi-icon">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="#059669" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              </span>
            </div>
            <div class="kpi-value" style="color: #059669;"><?= format_rupiah($paidSalaryMonth) ?></div>
            <div class="kpi-meta" style="color: #059669;">Sudah ditransfer ke rekening tim</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Pending Gaji (Antrean)</span>
              <span class="kpi-icon">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="#B45309" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              </span>
            </div>
            <div class="kpi-value" style="color: #B45309;"><?= format_rupiah($totalPendingSalary) ?></div>
            <div class="kpi-meta" style="color: #B45309;"><?= $pendingCount ?> staf menunggu transfer</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Total Karyawan Aktif</span>
              <span class="kpi-icon">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="#2563EB" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </span>
            </div>
            <div class="kpi-value"><?= $activeEmployeeCount ?></div>
            <div class="kpi-meta">Tim internal creative &amp; tech</div>
          </div>

          <div class="kpi-card kpi-card-dropdown" style="position: relative; overflow: visible !important; z-index: 50;">
            <div class="kpi-header" style="display: flex; justify-content: space-between; align-items: center;">
              <span class="kpi-title">Total Payroll</span>
              <div style="display: flex; align-items: center; gap: 6px;">
                <button type="button" class="btn btn-secondary btn-xs btn-period-toggle" onclick="togglePayrollPeriodDropdown(event)" style="font-size: 11px; padding: 3px 8px; height: 24px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px; background: #EEF2F6; border: 1px solid #CBD5E1; color: #1E293B; cursor: pointer; font-weight: 600;" title="Atur Periode Total Payroll">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                  <span>Atur Periode</span>
                  <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </button>
                <span class="kpi-icon">
                  <svg viewBox="0 0 24 24" width="16" height="16" stroke="#2563EB" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><line x1="6" y1="12" x2="6.01" y2="12"/><line x1="18" y1="12" x2="18.01" y2="12"/></svg>
                </span>
              </div>
            </div>
            <div class="kpi-value"><?= format_rupiah($totalSalarySelected) ?></div>
            <div class="kpi-meta" style="display: flex; justify-content: space-between; align-items: center;">
              <span>Periode: <strong style="color: #0F172A;"><?= htmlspecialchars($payrollPeriodLabel) ?></strong></span>
            </div>

            <!-- Dropdown Menu Atur Periode -->
            <div id="payroll-period-menu" style="display: none; position: absolute; right: 8px; top: 38px; z-index: 9999 !important;">
              <div style="font-size: 10.5px; font-weight: 700; color: #64748B; padding: 5px 8px; text-transform: uppercase; letter-spacing: 0.5px;">Atur Periode Payroll</div>
              <?php
                $makePeriodUrl = function($p) {
                    $q = array_merge($_GET, ['payroll_period' => $p]);
                    return url('salaries?' . http_build_query($q));
                };
              ?>
              <a href="<?= $makePeriodUrl('all') ?>" class="payroll-period-link <?= $payrollPeriod === 'all' ? 'active' : '' ?>">Semua Waktu (All-Time)</a>
              <a href="<?= $makePeriodUrl('this_month') ?>" class="payroll-period-link <?= $payrollPeriod === 'this_month' ? 'active' : '' ?>">Bulan Ini (<?= date('M Y') ?>)</a>
              <a href="<?= $makePeriodUrl('last_month') ?>" class="payroll-period-link <?= $payrollPeriod === 'last_month' ? 'active' : '' ?>">Bulan Lalu (<?= date('M Y', strtotime('-1 month')) ?>)</a>
              <a href="<?= $makePeriodUrl('this_quarter') ?>" class="payroll-period-link <?= $payrollPeriod === 'this_quarter' ? 'active' : '' ?>">Kuartal Ini (Q<?= ceil(intval(date('n')) / 3) ?>)</a>
              <a href="<?= $makePeriodUrl('this_year') ?>" class="payroll-period-link <?= $payrollPeriod === 'this_year' ? 'active' : '' ?>">Tahun Ini (<?= date('Y') ?>)</a>
              <?php if (!empty($availablePeriods)): ?>
                <div style="border-top: 1px solid #E2E8F0; margin: 4px 0;"></div>
                <div style="font-size: 10px; font-weight: 600; color: #94A3B8; padding: 3px 8px;">BULAN SPESIFIK:</div>
                <div style="max-height: 120px; overflow-y: auto;">
                  <?php foreach ($availablePeriods as $ap): ?>
                    <a href="<?= $makePeriodUrl($ap) ?>" class="payroll-period-link <?= $payrollPeriod === $ap ? 'active' : '' ?>" style="font-size: 11.5px; padding: 4px 8px;"><?= date('F Y', strtotime($ap . '-01')) ?></a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- 2. Main Payroll Container -->
        <div class="glass-panel" style="overflow: visible; min-width: 0; max-width: 100%;">
          <div class="panel-header payroll-header-panel" style="flex-wrap: wrap; gap: 14px;">
            <div>
              <h3 class="panel-title">
                Manajemen Penggajian Karyawan &amp; Slip Gaji
              </h3>
              <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Kelola pembayaran take-home pay, cetak slip gaji resmi, dan bukti transfer</p>
            </div>

            <!-- Tab Switchers & Action Buttons -->
            <div class="payroll-header-actions" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
              <div class="date-filter-group">
                <a href="<?= url('salaries?tab=payroll') ?>" class="filter-btn <?= $tab === 'payroll' ? 'active' : '' ?>">Daftar Payroll Gaji</a>
                <a href="<?= url('salaries?tab=employees') ?>" class="filter-btn <?= $tab === 'employees' ? 'active' : '' ?>">Master Karyawan</a>
              </div>

              <?php if ($tab === 'payroll'): ?>
                <button class="btn btn-primary btn-sm" onclick="openModal('modal-input-salary')" style="display: inline-flex; align-items: center; gap: 6px;">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                  <span>Input Gaji Karyawan</span>
                </button>
              <?php else: ?>
                <button class="btn btn-primary btn-sm" onclick="openModal('modal-create-employee')" style="display: inline-flex; align-items: center; gap: 6px;">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                  <span>Tambah Karyawan</span>
                </button>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($tab === 'payroll'): ?>
            <!-- Filter & Search Bar for Payroll -->
            <div class="payroll-filter-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; background: #F9FAFB; padding: 12px 14px; border-radius: 12px; border: 1px solid var(--border-color);">
              <div class="payroll-filter-controls" style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
                <div class="payroll-filter-field">
                  <label>Filter Periode:</label>
                  <select class="form-select" style="width: 160px;" onchange="location.href='<?= url('salaries?tab=payroll&status=' . urlencode($filterStatus)) ?>&period=' + this.value">
                    <option value="">Semua Periode</option>
                    <?php foreach ($availablePeriods as $p): ?>
                      <option value="<?= $p ?>" <?= $filterPeriod === $p ? 'selected' : '' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="payroll-filter-field">
                  <label>Status:</label>
                  <select class="form-select" style="width: 140px;" onchange="location.href='<?= url('salaries?tab=payroll&period=' . urlencode($filterPeriod)) ?>&status=' + this.value">
                    <option value="">Semua Status</option>
                    <option value="Paid" <?= $filterStatus === 'Paid' ? 'selected' : '' ?>>Paid (Lunas)</option>
                    <option value="Pending" <?= $filterStatus === 'Pending' ? 'selected' : '' ?>>Pending</option>
                  </select>
                </div>
              </div>

              <div class="payroll-search-wrapper">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="salary-search-input" class="form-control" placeholder="Cari nama atau jabatan...">
              </div>
            </div>

            <!-- Payroll Table -->
            <div class="kala-table-scroll table-responsive" style="width: 100%; max-width: 100%; overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; display: block !important; padding-bottom: 0;">
              <table class="table-custom" id="salaries-table" style="min-width: 950px !important; width: 100%;">
                <thead>
                  <tr>
                    <th style="white-space: nowrap !important;">Periode &amp; Tanggal</th>
                    <th style="white-space: nowrap !important;">Nama Karyawan &amp; Rekening</th>
                    <th style="white-space: nowrap !important;">Komponen Gaji</th>
                    <th style="white-space: nowrap !important;">Take-Home Pay</th>
                    <th style="white-space: nowrap !important;">Status</th>
                    <th class="th-actions" style="text-align: right; white-space: nowrap !important; min-width: 250px;">Slip Gaji &amp; Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($salariesList)): ?>
                    <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 28px;">Belum ada data pembayaran gaji pada filter ini.</td></tr>
                  <?php else: ?>
                    <?php foreach ($salariesList as $s): ?>
                      <tr>
                        <td style="white-space: nowrap;">
                          <div style="font-weight: 700; color: var(--text-primary);"><?= htmlspecialchars($s['month_period']) ?></div>
                          <div style="font-size: 11px; color: var(--text-secondary);">Bayar: <?= format_date($s['payment_date']) ?></div>
                        </td>
                        <td style="white-space: nowrap;">
                          <div style="font-weight: 700; color: var(--text-primary);"><?= htmlspecialchars($s['employee_name']) ?></div>
                          <div style="font-size: 11px; color: var(--text-secondary);">
                            <?= htmlspecialchars($s['employee_position'] ?: 'Staff') ?> &bull; <?= htmlspecialchars($s['bank_name'] ?: 'BCA') ?> (<?= htmlspecialchars($s['bank_account'] ?: '-') ?>)
                          </div>
                        </td>
                        <td style="white-space: nowrap;">
                          <div style="font-size: 12px; color: var(--text-primary);">
                            Pokok: <span style="font-weight: 600;"><?= format_rupiah($s['base_salary']) ?></span>
                          </div>
                          <div style="font-size: 11px; color: var(--text-secondary); margin-top: 2px;">
                            <span style="color: var(--success-text); font-weight: 600;">+ <?= format_rupiah($s['allowance']) ?></span> | 
                            <span style="color: var(--danger-text); font-weight: 600;">- <?= format_rupiah($s['deduction']) ?></span>
                          </div>
                        </td>
                        <td style="white-space: nowrap;">
                          <div style="font-size: 15px; font-weight: 800; color: var(--text-primary);">
                            <?= format_rupiah($s['net_salary']) ?>
                          </div>
                          <?php if (!empty($s['notes'])): ?>
                            <div style="font-size: 10.5px; color: var(--text-muted); max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                              <?= htmlspecialchars($s['notes']) ?>
                            </div>
                          <?php endif; ?>
                        </td>
                        <td style="white-space: nowrap;">
                          <span class="badge-status badge-<?= strtolower($s['status']) ?>">
                            <?= $s['status'] ?>
                          </span>
                        </td>
                        <td class="td-actions" style="text-align: right; white-space: nowrap;">
                          <div class="table-actions">
                            <!-- Slip Gaji Modal Trigger -->
                            <button type="button" class="btn-action-view" onclick="openSlipGajiModal(<?= $s['id'] ?>)" title="Lihat & Cetak Slip Gaji">
                              Slip Gaji
                            </button>

                            <!-- Receipt Actions -->
                            <?php if (!empty($s['receipt_file'])): ?>
                              <button type="button" class="btn-action-outline" onclick="viewReceiptImage('<?= UPLOAD_URL . '/' . htmlspecialchars($s['receipt_file']) ?>', 'Bukti Transfer Gaji <?= htmlspecialchars($s['employee_name']) ?>')" title="Lihat Bukti Transfer">
                                Struk
                              </button>
                            <?php else: ?>
                              <button type="button" class="btn-action-pay" onclick="triggerUploadModal('salary', <?= $s['id'] ?>, 'Upload Bukti Transfer Gaji <?= htmlspecialchars($s['employee_name']) ?>')" title="Upload Bukti Transfer">
                                + Bukti
                              </button>
                            <?php endif; ?>

                            <!-- Mark Paid Shortcut -->
                            <?php if ($s['status'] === 'Pending'): ?>
                              <button type="button" class="btn-action-success" onclick="markSalaryPaid(<?= $s['id'] ?>)" title="Tandai Sudah Ditransfer">
                                &#10003; Lunas
                              </button>
                            <?php endif; ?>

                            <!-- Delete Option -->
                            <button type="button" class="btn-action-delete btn-delete-ghost" onclick="confirmDeleteSalary(<?= $s['id'] ?>, 'Gaji <?= htmlspecialchars($s['employee_name']) ?> (<?= htmlspecialchars($s['month_period']) ?>)')" title="Hapus Data Gaji">
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
          <?php elseif ($tab === 'employees'): ?>
            <!-- Master Karyawan Table -->
            <div class="kala-table-scroll table-responsive" style="width: 100%; max-width: 100%; overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; display: block !important; padding-bottom: 0;">
              <table class="table-custom" id="employees-table" style="min-width: 950px !important; width: 100%;">
                <thead>
                  <tr>
                    <th style="white-space: nowrap !important;">Nama Karyawan</th>
                    <th style="white-space: nowrap !important;">Jabatan &amp; Departemen</th>
                    <th style="white-space: nowrap !important;">Status Kerja</th>
                    <th style="white-space: nowrap !important;">Kontak</th>
                    <th style="white-space: nowrap !important;">Rekening Pembayaran</th>
                    <th style="white-space: nowrap !important;">Gaji Pokok Default</th>
                    <th class="th-actions" style="text-align: right; white-space: nowrap !important; min-width: 220px;">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($employeesList)): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 28px;">Belum ada master data karyawan.</td></tr>
                  <?php else: ?>
                    <?php foreach ($employeesList as $emp): ?>
                      <tr>
                        <td style="white-space: nowrap;">
                          <div style="font-weight: 700; color: var(--text-primary);"><?= htmlspecialchars($emp['name']) ?></div>
                          <span class="badge-status badge-<?= $emp['status'] === 'Active' ? 'paid' : 'draft' ?>" style="font-size: 10px; padding: 2px 6px; margin-top: 2px;">
                            <?= $emp['status'] ?>
                          </span>
                        </td>
                        <td style="white-space: nowrap;">
                          <div style="font-weight: 600; color: var(--text-primary);"><?= htmlspecialchars($emp['position']) ?></div>
                          <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($emp['department'] ?: '-') ?></div>
                        </td>
                        <td style="white-space: nowrap;">
                          <span style="font-size: 12px; color: var(--text-secondary); background: var(--bg-surface-secondary, #F2F4F7); padding: 3px 8px; border-radius: 4px; font-weight: 500;">
                            <?= htmlspecialchars($emp['employment_type'] ?: 'Full-time') ?>
                          </span>
                        </td>
                        <td style="white-space: nowrap;">
                          <div style="font-size: 12px; color: var(--text-primary);"><?= htmlspecialchars($emp['email'] ?: '-') ?></div>
                          <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($emp['phone'] ?: '-') ?></div>
                        </td>
                        <td style="white-space: nowrap;">
                          <div style="font-weight: 600; color: var(--text-primary);"><?= htmlspecialchars($emp['bank_name'] ?: 'BCA') ?></div>
                          <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($emp['bank_account'] ?: '-') ?></div>
                        </td>
                        <td style="font-weight: 700; color: var(--text-primary); white-space: nowrap;">
                          <?= format_rupiah($emp['base_salary']) ?>
                        </td>
                        <td class="td-actions" style="text-align: right; white-space: nowrap;">
                          <div class="table-actions">
                            <!-- Edit Employee Button -->
                            <button type="button" class="btn-action-edit" onclick='openEditEmployeeModal(<?= json_encode($emp, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Data Karyawan">
                              Edit
                            </button>

                            <!-- Quick Create Salary -->
                            <button type="button" class="btn-action-pay" onclick="quickCreateSalaryForEmp(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($emp['position'], ENT_QUOTES) ?>', '<?= htmlspecialchars($emp['bank_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($emp['bank_account'], ENT_QUOTES) ?>', <?= $emp['base_salary'] ?>)" title="Buat Gaji">
                              + Buat Gaji
                            </button>

                            <!-- Delete Employee -->
                            <button type="button" class="btn-action-delete btn-delete-ghost" onclick="confirmDeleteEmployee(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['name'], ENT_QUOTES) ?>')" title="Hapus Karyawan">
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

    // Toggle dropdown Atur Periode Payroll
    function togglePayrollPeriodDropdown(e) {
      if (e) {
        e.preventDefault();
        e.stopPropagation();
      }
      const menu = document.getElementById('payroll-period-menu');
      if (menu) {
        const isShown = (menu.style.display === 'block');
        menu.style.display = isShown ? 'none' : 'block';
        const card = menu.closest('.kpi-card');
        const grid = menu.closest('.payroll-kpi-grid');
        if (card) {
          card.style.zIndex = isShown ? '50' : '200';
          card.style.overflow = 'visible';
        }
        if (grid) {
          grid.style.zIndex = isShown ? '40' : '150';
          grid.style.overflow = 'visible';
        }
      }
    }

    document.addEventListener('click', function(e) {
      const menu = document.getElementById('payroll-period-menu');
      if (menu && !e.target.closest('#payroll-period-menu') && !e.target.closest('button[onclick*="togglePayrollPeriodDropdown"]')) {
        menu.style.display = 'none';
        const card = menu.closest('.kpi-card');
        const grid = menu.closest('.payroll-kpi-grid');
        if (card) {
          card.style.zIndex = '50';
        }
        if (grid) {
          grid.style.zIndex = '40';
        }
      }
    });
  </script>
</body>
</html>

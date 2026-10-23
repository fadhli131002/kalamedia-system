<?php
/**
 * Kalamedia Owner Dashboard (Executive View - Untitled UI Design System)
 * Focused on Monthly Net Profit, Margins, Cashflow Trends, Profitability & Internal Team
 */
require_owner();
$db = Database::getConnection();

// Selected Month (Defaults to current month)
$selectedMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}
$currentMonth = $selectedMonth;

$isMysql = (Database::getDriver() === 'mysql');
$invDateExpr = $isMysql ? "DATE_FORMAT(COALESCE(NULLIF(paid_at, ''), issue_date), '%Y-%m')" : "strftime('%Y-%m', COALESCE(NULLIF(paid_at, ''), issue_date))";
$payDateExpr = $isMysql ? "DATE_FORMAT(COALESCE(NULLIF(paid_at, ''), created_at), '%Y-%m')" : "strftime('%Y-%m', COALESCE(NULLIF(paid_at, ''), created_at))";
$adsDateExpr = $isMysql ? "DATE_FORMAT(spent_date, '%Y-%m')" : "strftime('%Y-%m', spent_date)";
$salDateExpr = $isMysql ? "DATE_FORMAT(COALESCE(NULLIF(paid_at, ''), payment_date), '%Y-%m')" : "strftime('%Y-%m', COALESCE(NULLIF(paid_at, ''), payment_date))";

// 1. Total Monthly Revenue
$totalRevenue = floatval($db->query("SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE status = 'Paid' AND COALESCE(is_deleted, 0) = 0 AND {$invDateExpr} = '$currentMonth'")->fetchColumn());

// 2. Total Monthly Expense (Freelancers, Ads Spend, Salaries this month)
$totalPayouts = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM freelancer_payouts WHERE status = 'Paid' AND COALESCE(is_deleted, 0) = 0 AND {$payDateExpr} = '$currentMonth'")->fetchColumn());
$totalAds = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM ads_spend WHERE COALESCE(is_deleted, 0) = 0 AND {$adsDateExpr} = '$currentMonth'")->fetchColumn());
$totalSalaries = floatval($db->query("SELECT COALESCE(SUM(net_salary), 0) FROM salaries WHERE status = 'Paid' AND COALESCE(is_deleted, 0) = 0 AND ({$salDateExpr} = '$currentMonth' OR month_period = '$currentMonth')")->fetchColumn());
$totalExpense = $totalPayouts + $totalAds + $totalSalaries;

// 3. Monthly Net Profit & Margin
$netProfit = $totalRevenue - $totalExpense;
$profitMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;

// 4. Outstanding Receivables
$outstandingReceivables = floatval($db->query("SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE status IN ('Sent', 'Draft', 'Overdue') AND COALESCE(is_deleted, 0) = 0")->fetchColumn());
$unpaidInvoicesList = $db->query("
    SELECT i.*, c.company as client_company, c.phone as client_phone
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    WHERE i.status IN ('Sent', 'Draft', 'Overdue') AND COALESCE(i.is_deleted, 0) = 0
    ORDER BY i.due_date ASC LIMIT 5
")->fetchAll();

// 5. Project Profitability Leaderboard (Ranked by Profit Margin %)
$rawProjects = $db->query("
    SELECT p.*, c.company as client_company,
           (SELECT COALESCE(SUM(amount), 0) FROM freelancer_payouts WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0) as total_freelancer_cost,
           (SELECT COALESCE(SUM(amount), 0) FROM ads_spend WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0) as total_ads_cost
    FROM projects p
    JOIN clients c ON p.client_id = c.id
    WHERE COALESCE(p.is_deleted, 0) = 0 AND COALESCE(c.is_deleted, 0) = 0
")->fetchAll();

$leaderboard = [];
foreach ($rawProjects as $p) {
    $serviceFee = floatval($p['service_fee_amount'] > 0 ? $p['service_fee_amount'] : $p['contract_value']);
    $adsBudget = floatval($p['ads_budget_pool']);
    $totalContract = floatval($p['total_contract_value'] > 0 ? $p['total_contract_value'] : ($serviceFee + $adsBudget));
    $freelancerCost = floatval($p['total_freelancer_cost']);
    $prodCost = $freelancerCost + floatval($p['total_ads_cost']);
    $profit = $serviceFee - $freelancerCost;
    $margin = $serviceFee > 0 ? ($profit / $serviceFee) * 100 : 0;
    $targetMargin = floatval($p['target_margin_percent']);
    $isOverBudget = $margin < $targetMargin;

    $leaderboard[] = array_merge($p, [
        'service_fee' => $serviceFee,
        'ads_budget' => $adsBudget,
        'total_contract' => $totalContract,
        'prod_cost' => $prodCost,
        'profit' => $profit,
        'margin' => $margin,
        'target_margin' => $targetMargin,
        'is_over_budget' => $isOverBudget
    ]);
}

// Sort leaderboard by profit DESC
usort($leaderboard, function($a, $b) {
    return $b['profit'] <=> $a['profit'];
});

// 6. Internal Team & Payroll Summary
$activeEmployees = $db->query("SELECT * FROM employees WHERE status = 'Active' ORDER BY name ASC LIMIT 5")->fetchAll();
$activeEmployeeCount = intval($db->query("SELECT COUNT(*) FROM employees WHERE status = 'Active'")->fetchColumn());
$thisMonthSalaryPaid = floatval($db->query("SELECT COALESCE(SUM(net_salary), 0) FROM salaries WHERE status = 'Paid' AND COALESCE(is_deleted, 0) = 0 AND (month_period = '$currentMonth' OR strftime('%Y-%m', paid_at) = '$currentMonth')")->fetchColumn());
$pendingSalaryTotal = floatval($db->query("SELECT COALESCE(SUM(net_salary), 0) FROM salaries WHERE status = 'Pending' AND COALESCE(is_deleted, 0) = 0")->fetchColumn());
$pendingSalaryCount = intval($db->query("SELECT COUNT(*) FROM salaries WHERE status = 'Pending' AND COALESCE(is_deleted, 0) = 0")->fetchColumn());

// 7. Recent High-Level Activities
$activities = $db->query("SELECT * FROM activities ORDER BY id DESC LIMIT 6")->fetchAll();

// 8. System Users for Owner User Management (Karyawan, Freelancer, Client, Admin)
$systemUsers = $db->query("
    SELECT u.id, u.name, u.email, u.role, u.employment_type, u.phone_wa, u.client_id, u.created_at,
           c.company as client_company
    FROM users u
    LEFT JOIN clients c ON u.client_id = c.id
    ORDER BY CASE u.role 
        WHEN 'owner' THEN 1 
        WHEN 'admin' THEN 2 
        WHEN 'karyawan' THEN 3 
        WHEN 'freelancer' THEN 4 
        WHEN 'client' THEN 5 
        ELSE 6 END, u.id ASC
")->fetchAll();

$userCounts = [
    'karyawan' => 0,
    'freelancer' => 0,
    'client' => 0,
    'admin' => 0,
    'owner' => 0
];
foreach ($systemUsers as $su) {
    if (isset($userCounts[$su['role']])) {
        $userCounts[$su['role']]++;
    }
}

// 9. Ads Top-Up Transactions & Budget Monitoring for Owner (100% Dynamic Aggregations)
$adsProjectsSummary = $db->query("
    SELECT p.id, p.name as project_name, c.company as client_company,
           COALESCE(NULLIF(p.contract_value, 0), p.total_contract_value, 0) as total_kontrak_klien,
           COALESCE(NULLIF(p.ads_budget_pool, 0), p.allocated_ads_budget, 0) as total_pagu_ads_klien,
           COALESCE(NULLIF(p.ads_budget_pool, 0), p.allocated_ads_budget, 0) as allocated_ads_budget,
           COALESCE((
               SELECT SUM(COALESCE(NULLIF(transfer_amount_owner, 0), topup_amount, 0))
               FROM ads_topup_transactions
               WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0
           ), 0) as total_ditransfer_ke_tim_ads,
           COALESCE((
               SELECT SUM(COALESCE(NULLIF(platform_topup_amount, 0), topup_amount, 0))
               FROM ads_topup_transactions
               WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0 AND (platform_topup_status IS NULL OR platform_topup_status = 'completed')
           ), 0) as total_spend_installed,
           COALESCE((
               SELECT SUM(COALESCE(NULLIF(ad_spend_amount, 0), spend_installed, 0))
               FROM ads_topup_transactions
               WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0
           ), 0) as total_spend_terpakai,
           (
               COALESCE(NULLIF(p.ads_budget_pool, 0), p.allocated_ads_budget, 0) -
               COALESCE((
                   SELECT SUM(COALESCE(NULLIF(transfer_amount_owner, 0), topup_amount, 0))
                   FROM ads_topup_transactions
                   WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0
               ), 0)
           ) as remaining_ads_budget,
           (
               COALESCE(NULLIF(p.contract_value, 0), p.total_contract_value, 0) -
               COALESCE(NULLIF(p.ads_budget_pool, 0), p.allocated_ads_budget, 0)
           ) as margin_bersih_agensi
    FROM projects p
    JOIN clients c ON p.client_id = c.id
    WHERE COALESCE(p.is_deleted, 0) = 0 
      AND (COALESCE(p.ads_budget_pool, 0) > 0 OR COALESCE(p.allocated_ads_budget, 0) > 0)
    ORDER BY p.id DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$recentAdsTopups = $db->query("
    SELECT t.*, 
           COALESCE(NULLIF(t.transfer_amount_owner, 0), t.topup_amount, 0) as transfer_amount_owner,
           COALESCE(t.transfer_proof_owner, t.receipt_file) as transfer_proof_owner,
           COALESCE(NULLIF(t.platform_topup_amount, 0), t.topup_amount, 0) as platform_topup_amount,
           COALESCE(t.platform_topup_proof, t.receipt_file) as platform_topup_proof,
           COALESCE(NULLIF(t.ad_spend_amount, 0), t.spend_installed, 0) as ad_spend_amount,
           COALESCE(t.stage_label, t.notes, 'Tahap 1') as stage_label,
           COALESCE(t.assigned_user_name, u_assigned.name, 'Tim Ads Kalamedia') as assigned_user_name,
           u_up.name as uploader_name, p.name as project_name, c.company as client_company
    FROM ads_topup_transactions t
    LEFT JOIN users u_up ON t.uploaded_by_user_id = u_up.id
    LEFT JOIN users u_assigned ON t.assigned_user_id = u_assigned.id
    LEFT JOIN projects p ON t.project_id = p.id
    LEFT JOIN clients c ON t.client_id = c.id
    WHERE COALESCE(t.is_deleted, 0) = 0
    ORDER BY t.transaction_date DESC, t.id DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// Pending balance requests from Tim Ads to Owner
$pendingWorkerRequests = $db->query("
    SELECT r.*, p.name as project_name, c.company as client_company
    FROM ads_balance_requests r
    JOIN projects p ON r.project_id = p.id
    JOIN clients c ON r.client_id = c.id
    WHERE r.request_type = 'worker_to_owner' AND r.status = 'pending'
    ORDER BY r.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// 10. Initial Cashflow Chart Data (6 Months History) for Instant Zero-Lag Rendering
$isMysql = (Database::getDriver() === 'mysql');
$invDateExpr = $isMysql ? "DATE_FORMAT(COALESCE(NULLIF(paid_at, ''), issue_date), '%Y-%m')" : "strftime('%Y-%m', COALESCE(NULLIF(paid_at, ''), issue_date))";
$payDateExpr = $isMysql ? "DATE_FORMAT(COALESCE(NULLIF(paid_at, ''), created_at), '%Y-%m')" : "strftime('%Y-%m', COALESCE(NULLIF(paid_at, ''), created_at))";
$adsDateExpr = $isMysql ? "DATE_FORMAT(spent_date, '%Y-%m')" : "strftime('%Y-%m', spent_date)";
$salDateExpr = $isMysql ? "DATE_FORMAT(COALESCE(NULLIF(paid_at, ''), payment_date), '%Y-%m')" : "strftime('%Y-%m', COALESCE(NULLIF(paid_at, ''), payment_date))";

$monthNamesIndo = [
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
    7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
];

$initChartMonths = [];
$initChartRevenue = [];
$initChartExpense = [];

for ($i = 5; $i >= 0; $i--) {
    $mTime = strtotime("-$i months");
    $mKey = date('Y-m', $mTime);
    $mNum = intval(date('n', $mTime));
    $mYear = date('Y', $mTime);
    $initChartMonths[] = $monthNamesIndo[$mNum] . ' ' . $mYear;

    $mRev = floatval($db->query("SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE status = 'Paid' AND COALESCE(is_deleted, 0) = 0 AND {$invDateExpr} = '$mKey'")->fetchColumn());
    $mPay = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM freelancer_payouts WHERE status = 'Paid' AND COALESCE(is_deleted, 0) = 0 AND {$payDateExpr} = '$mKey'")->fetchColumn());
    $mAds = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM ads_spend WHERE COALESCE(is_deleted, 0) = 0 AND {$adsDateExpr} = '$mKey'")->fetchColumn());
    $mSal = floatval($db->query("SELECT COALESCE(SUM(net_salary), 0) FROM salaries WHERE status = 'Paid' AND COALESCE(is_deleted, 0) = 0 AND ({$salDateExpr} = '$mKey' OR month_period = '$mKey')")->fetchColumn());

    $initChartRevenue[] = $mRev;
    $initChartExpense[] = $mPay + $mAds + $mSal;
}

$initialChartData = [
    'labels' => $initChartMonths,
    'revenue' => $initChartRevenue,
    'expense' => $initChartExpense
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Executive Dashboard - PT Kala Kausa Prima</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=saas-linear-1.0">
  <?php render_tab_uid_script(); ?>
</head>
<body>
  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body">
        
        <!-- Owner Executive Control Bar: Time Range Filter & Actions -->
        <div class="owner-control-bar" style="margin-top: 0; margin-bottom: 14px;">
          <!-- Baris 1: Segmented Filter (Bulan Ini | Kuartal Ini | Tahun Ini | Semua) -->
          <div class="owner-period-filter-segmented" style="width: 100%; display: grid; grid-template-columns: repeat(4, 1fr); background: #F0F1F7; padding: 4px; border-radius: 12px; gap: 4px; box-sizing: border-box;">
            <button type="button" class="filter-btn active" onclick="changeTimeRange('month', this)">Bulan Ini</button>
            <button type="button" class="filter-btn" onclick="changeTimeRange('quarter', this)">Kuartal Ini</button>
            <button type="button" class="filter-btn" onclick="changeTimeRange('year', this)">Tahun Ini</button>
            <button type="button" class="filter-btn" onclick="changeTimeRange('all', this)">Semua</button>
          </div>

          <!-- Baris 2: Subtitle / Title & + Buat Invoice Baru -->
          <div class="owner-action-bar-row" style="display: flex; align-items: center; justify-content: space-between; margin-top: 10px; gap: 10px; flex-wrap: wrap;">
            <div>
              <h2 style="font-size: 15px; font-weight: 700; color: #16141F; margin: 0; letter-spacing: -0.2px;">Ringkasan Finansial Agensi</h2>
              <p style="font-size: 12px; color: #6E6D7A; margin: 2px 0 0 0;">Monitoring arus kas, margin laba &amp; piutang klien</p>
            </div>
            <button type="button" class="btn btn-primary btn-sm btn-create-invoice" onclick="openModal('modal-create-invoice')" style="background: #5B3DF5; color: #FFFFFF; font-weight: 600; font-size: 12.5px; border-radius: 10px; padding: 8px 16px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(91, 61, 245, 0.25); white-space: nowrap; flex-shrink: 0;">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
              <span>+ Buat Invoice Baru</span>
            </button>
          </div>
        </div>
        
        <!-- Key Performance Indicators (KPI) Cards - Shopeers Format -->
        <div class="kpi-grid">
          <!-- Total Revenue -->
          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Total Revenue</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
            <div class="kpi-value" id="kpi-revenue"><?= format_rupiah($totalRevenue) ?></div>
            <div class="kpi-meta">Terverifikasi dari invoice lunas</div>
          </div>

          <!-- Total Expense -->
          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Total Expense</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="kpi-value" id="kpi-expense"><?= format_rupiah($totalExpense) ?></div>
            <div class="kpi-meta">Freelancer, Ads &amp; Gaji</div>
          </div>

          <!-- Net Profit -->
          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Monthly Net Profit</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            </div>
            <div class="kpi-value" id="kpi-profit"><?= format_rupiah($netProfit) ?></div>
            <div class="kpi-meta">
              <span id="kpi-margin" class="badge-margin <?= $profitMargin >= 30 ? 'good' : ($profitMargin >= 15 ? 'warning' : 'bad') ?>">
                Margin <?= round($profitMargin, 1) ?>%
              </span>
              <span>Target &ge; 30%</span>
            </div>
          </div>

          <!-- Outstanding Receivables / Piutang Klien -->
          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Piutang Klien</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            </div>
            <div class="kpi-value" id="kpi-receivables"><?= format_rupiah($outstandingReceivables) ?></div>
            <div class="kpi-meta">Piutang klien belum lunas</div>
          </div>
        </div>

        <!-- Cashflow Chart Section -->
        <div class="glass-panel" style="position: relative;">
          <div class="panel-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
            <div>
              <h3 class="panel-title" style="display: flex; align-items: center; gap: 8px;">
                <span>Grafik Cashflow Tren Agensi (Inflow vs Outflow)</span>
              </h3>
              <p style="font-size: 12px; color: var(--text-secondary); margin-top: 3px;">Tren riwayat arus kas masuk dan biaya operasional 6 bulan terakhir</p>
            </div>
            <!-- Interactive Legend Indicators matching Slide 2 reference -->
            <div style="display: flex; align-items: center; gap: 12px; font-size: 12px; font-weight: 600;">
              <div onclick="toggleChartDataset(0, this)" style="display: flex; align-items: center; gap: 6px; color: #0284C7; cursor: pointer; padding: 4px 10px; background: #F0F9FF; border-radius: 6px; border: 1px solid #BAE6FD; transition: all 0.2s ease;" title="Klik untuk sembunyikan/tampilkan Inflow">
                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #06B6D4; box-shadow: 0 0 0 2px rgba(6,182,212,0.25);"></span>
                <span>Total Inflow (Revenue)</span>
              </div>
              <div onclick="toggleChartDataset(1, this)" style="display: flex; align-items: center; gap: 6px; color: #7C3AED; cursor: pointer; padding: 4px 10px; background: #F5F3FF; border-radius: 6px; border: 1px solid #DDD6FE; transition: all 0.2s ease;" title="Klik untuk sembunyikan/tampilkan Outflow">
                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #8B5CF6; box-shadow: 0 0 0 2px rgba(139,92,246,0.25);"></span>
                <span>Total Outflow (Expense)</span>
              </div>
            </div>
          </div>
          <div class="cashflow-chart-container" style="position: relative; height: 280px; width: 100%;">
            <canvas id="cashflowChart"></canvas>
          </div>
        </div>

        <!-- Two Column Layout: Project Profitability Leaderboard & Outstanding Receivables -->
        <div class="owner-dashboard-grid kala-dashboard-bottom-grid">
          
          <!-- Project Profitability Leaderboard -->
          <div class="glass-panel" style="margin-bottom: 0; overflow: visible;">
            <div class="panel-header kala-leaderboard-header">
              <div>
                <h3 class="panel-title">
                  Project Profitability Leaderboard
                </h3>
                <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Peringkat proyek berdasarkan profit margin & efisiensi biaya produksi</p>
              </div>
              <button class="btn btn-secondary btn-sm kala-btn-new-project" onclick="openModal('modal-create-project')">+ Proyek Baru</button>
            </div>

            <div class="kala-table-scroll table-responsive" style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch;">
              <table class="table-custom" style="min-width: 640px; width: 100%;">
                <thead>
                  <tr>
                    <th>Rank & Nama Proyek</th>
                    <th>Nilai Kontrak</th>
                    <th>Biaya Produksi</th>
                    <th>Net Profit</th>
                    <th>Margin (%)</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($leaderboard)): ?>
                    <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">Belum ada proyek berjalan.</td></tr>
                  <?php else: ?>
                    <?php foreach ($leaderboard as $idx => $p): ?>
                      <tr>
                        <td>
                          <div style="display: flex; align-items: center; gap: 12px;">
                            <?php
                              $rankClass = $idx === 0 ? 'rank-badge-1' : ($idx === 1 ? 'rank-badge-2' : ($idx === 2 ? 'rank-badge-3' : 'rank-badge-other'));
                            ?>
                            <span class="rank-badge <?= $rankClass ?>" title="Peringkat <?= $idx + 1 ?>">
                              #<?= $idx + 1 ?>
                            </span>
                            <div>
                              <div class="clickable-client-link" onclick="openProjectFinancialDetailModal(<?= $p['id'] ?>, <?= $p['client_id'] ?>)" title="Lihat Rincian Alokasi Keuangan">
                                <span style="font-size: 13.5px;"><?= htmlspecialchars($p['name']) ?></span>
                              </div>
                              <div style="font-size: 11.5px; color: var(--text-secondary); margin-top: 1px;">
                                <span style="font-weight: 600;"><?= htmlspecialchars($p['client_company']) ?></span> &bull; 
                                <span style="color: #2563EB; font-weight: 600; cursor: pointer;" onclick="openProjectFinancialDetailModal(<?= $p['id'] ?>, <?= $p['client_id'] ?>)">Rincian Biaya</span>
                              </div>
                            </div>
                          </div>
                        </td>
                        <td style="font-weight: 600; color: #101828;"><?= format_rupiah($p['contract_value']) ?></td>
                        <td>
                          <div style="color: var(--danger-text); font-weight: 600;"><?= format_rupiah($p['prod_cost']) ?></div>
                          <div style="font-size: 10.5px; color: var(--text-muted);">Fee: <?= format_rupiah($p['total_freelancer_cost']) ?> | Ads: <?= format_rupiah($p['total_ads_cost']) ?></div>
                        </td>
                        <td style="font-weight: 700; color: <?= $p['profit'] >= 0 ? 'var(--success-text)' : 'var(--danger-text)' ?>;">
                          <?= format_rupiah($p['profit']) ?>
                        </td>
                        <td>
                          <span class="badge-margin <?= $p['margin'] >= $p['target_margin'] ? 'good' : ($p['margin'] >= 15 ? 'warning' : 'bad') ?>">
                            <?= round($p['margin'], 1) ?>%
                          </span>
                          <?php if ($p['is_over_budget']): ?>
                            <div style="font-size: 10px; color: var(--danger-text); font-weight: 700; margin-top: 2px;">Over-Budget!</div>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Outstanding Receivables (Aging Widget) -->
          <div class="glass-panel" style="margin-bottom: 0;">
            <div class="panel-header">
              <div>
                <h3 class="panel-title">
                  Outstanding Receivables
                </h3>
                <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Tagihan klien yang belum masuk</p>
              </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 10px;">
              <?php if (empty($unpaidInvoicesList)): ?>
                <div style="text-align: center; padding: 24px; color: var(--text-muted); font-size: 13px; background: #F9FAFB; border-radius: var(--radius-sm); border: 1px dashed var(--border-color);">
                  Semua invoice klien telah dilunasi!
                </div>
              <?php else: ?>
                <?php foreach ($unpaidInvoicesList as $u):
                  $isOverdue = (strtotime($u['due_date']) < time() && $u['status'] !== 'Paid');
                ?>
                  <div style="background: #FFFFFF; border: 1px solid <?= $isOverdue ? 'var(--danger-border)' : 'var(--border-color)' ?>; border-radius: 8px; padding: 12px 14px; box-shadow: var(--shadow-xs);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                      <div>
                        <div class="clickable-client-link" onclick="openProjectFinancialDetailModal(<?= !empty($u['project_id']) ? $u['project_id'] : 0 ?>, <?= $u['client_id'] ?>)" title="Lihat Rincian Keuangan Klien">
                          <span style="font-size: 13px; font-weight: 700;"><?= htmlspecialchars($u['client_company']) ?></span>
                        </div>
                        <div style="font-size: 11px; color: var(--text-secondary);">#<?= htmlspecialchars($u['invoice_number']) ?></div>
                      </div>
                      <span class="badge-status badge-<?= $isOverdue ? 'overdue' : strtolower($u['status']) ?>">
                        <?= $isOverdue ? 'Overdue' : $u['status'] ?>
                      </span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--border-light);">
                      <div style="font-size: 11px; color: var(--text-muted);">
                        Tempo: <?= format_date($u['due_date']) ?>
                      </div>
                      <div style="font-size: 13px; font-weight: 800; color: #101828;">
                        <?= format_rupiah($u['total_amount']) ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- Internal Team & Payroll Summary Widget -->
          <div class="glass-panel owner-full-width-panel">
            <div class="panel-header" style="margin-bottom: 16px;">
              <div>
                <h3 class="panel-title">
                  Tim Internal & Ringkasan Payroll (Gaji Karyawan)
                </h3>
                <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Pemantauan struktur tim agensi, biaya gaji bulanan, dan status slip gaji</p>
              </div>
              <a href="<?= url('salaries') ?>" class="btn btn-secondary btn-sm" style="font-weight: 700;">
                <span>Kelola Gaji & Slip &rarr;</span>
              </a>
            </div>

            <div class="payroll-cards-grid">
              <div style="background: #F9FAFB; padding: 14px 18px; border-radius: 14px; border: 1px solid var(--border-color); border-left: 4px solid #5D76F5;">
                <div style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase; font-weight: 700;">Total Karyawan Aktif</div>
                <div style="font-size: 20px; font-weight: 800; color: #000000; margin-top: 4px;"><?= $activeEmployeeCount ?> Orang</div>
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Tim internal creative & tech</div>
              </div>

              <div style="background: #F9FAFB; padding: 14px 18px; border-radius: 8px; border: 1px solid var(--border-color); border-left: 4px solid var(--success);">
                <div style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase; font-weight: 700;">Gaji Terbayar Bulan Ini</div>
                <div style="font-size: 20px; font-weight: 800; color: var(--success-text); margin-top: 4px;"><?= format_rupiah($thisMonthSalaryPaid) ?></div>
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Periode <?= date('F Y') ?></div>
              </div>

              <div style="background: #F9FAFB; padding: 14px 18px; border-radius: 8px; border: 1px solid var(--border-color); border-left: 4px solid <?= $pendingSalaryCount > 0 ? 'var(--warning)' : '#D0D5DD' ?>;">
                <div style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase; font-weight: 700;">Antrean Gaji Pending</div>
                <div style="font-size: 20px; font-weight: 800; color: <?= $pendingSalaryCount > 0 ? 'var(--warning-text)' : '#101828' ?>; margin-top: 4px;"><?= format_rupiah($pendingSalaryTotal) ?></div>
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;"><?= $pendingSalaryCount ?> slip menunggu transfer</div>
              </div>
            </div>

            <?php if (!empty($activeEmployees)): ?>
              <div class="touch-swipe-x">
                <div style="display: flex; gap: 12px; min-width: 500px;">
                  <?php foreach ($activeEmployees as $emp): ?>
                    <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 14px; display: flex; align-items: center; gap: 12px; flex: 1; min-width: 220px; box-shadow: var(--shadow-xs);">
                      <div style="width: 36px; height: 36px; border-radius: 50%; background: #F2F4F7; color: #101828; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; border: 1px solid var(--border-color);">
                        <?= strtoupper(substr($emp['name'], 0, 2)) ?>
                      </div>
                      <div style="flex: 1; min-width: 0;">
                        <div style="font-size: 13px; font-weight: 700; color: #101828; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($emp['name']) ?></div>
                        <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($emp['position'] ?: 'Karyawan') ?> &bull; <span style="color: var(--success-text); font-weight: 600;"><?= format_rupiah($emp['base_salary']) ?></span></div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <!-- Notification Banner if Tim Ads Requests Balance -->
          <?php if (!empty($pendingWorkerRequests)): ?>
            <div style="margin-top: 24px;">
              <?php foreach ($pendingWorkerRequests as $pwr): ?>
                <div style="background: #FFF9F2; border: 1.5px solid #FDB022; border-radius: 12px; padding: 16px 20px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.12);">
                  <div style="display: flex; align-items: flex-start; gap: 14px;">
                    <span style="font-size: 28px; line-height: 1;">🔔</span>
                    <div>
                      <div style="font-weight: 800; font-size: 14px; color: #B54708;">
                        Permintaan Pencairan Saldo Ads dari Tim Ads (<?= htmlspecialchars($pwr['requester_name']) ?>)
                      </div>
                      <div style="font-size: 13px; color: #475467; margin-top: 3px;">
                        Klien: <b><?= htmlspecialchars($pwr['client_company']) ?></b> &bull; Proyek: <b><?= htmlspecialchars($pwr['project_name']) ?></b> &bull; Kebutuhan: <b style="color: #B42318; font-size: 14px;"><?= format_rupiah($pwr['amount_requested']) ?></b>
                      </div>
                      <div style="font-size: 12px; color: #344054; margin-top: 4px; background: #FFFFFF; border: 1px dashed #FDB022; padding: 6px 12px; border-radius: 6px;">
                        <b>Catatan Tim Ads:</b> "<?= htmlspecialchars($pwr['notes'] ?: 'Saldo iklan telah menipis/habis.') ?>"
                      </div>
                    </div>
                  </div>
                  <div style="display: flex; gap: 10px;">
                    <a href="<?= url('tasks') ?>?tab=ads" class="btn btn-primary btn-sm" style="font-weight: 700; background: #175CD3; border-color: #175CD3; font-size: 12.5px; padding: 8px 16px;">
                      + Buka Pelacakan &amp; Transfer Saldo
                    </a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <!-- Monitoring Saldo Iklan & Riwayat Top-Up Tim Ads -->
          <div class="glass-panel owner-full-width-panel" style="margin-top: <?= !empty($pendingWorkerRequests) ? '12px' : '24px' ?>;">
            <div class="panel-header" style="margin-bottom: 16px; flex-wrap: wrap; gap: 12px; display: flex; justify-content: space-between; align-items: center;">
              <div>
                <h3 class="panel-title" style="display: flex; align-items: center; flex-wrap: wrap; gap: 8px;">
                  <span>Monitoring Saldo Iklan &amp; Riwayat Top-Up Tim Ads</span>
                  <span class="badge" style="background: #EFF8FF; color: #175CD3; font-weight: 700; font-size: 11px;">Transparansi Kampanye</span>
                </h3>
                <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">
                  Pantau sisa pagu saldo iklan per proyek dan verifikasi struk transfer top-up yang di-upload tim Ads
                </p>
              </div>
              <div style="display: flex; gap: 8px;">
                <a href="<?= url('tasks') ?>?tab=ads" class="btn btn-secondary btn-sm" style="font-weight: 700;">
                  Buka Pelacakan Ads &rarr;
                </a>
              </div>
            </div>

            <!-- Project Ads Balance Cards -->
            <?php if (!empty($adsProjectsSummary)): ?>
              <div class="ads-projects-grid">
                <?php foreach ($adsProjectsSummary as $ap):
                  $alloc = floatval($ap['allocated_ads_budget']);
                  $spend = floatval($ap['total_spend_installed']);
                  $rem = floatval($ap['remaining_ads_budget']);
                  $pct = $alloc > 0 ? min(100, round(($spend / $alloc) * 100)) : 0;
                  $pctColor = ($pct >= 90) ? '#B42318' : (($pct >= 70) ? '#B54708' : '#027A48');
                ?>
                  <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: 10px; padding: 14px 16px; box-shadow: var(--shadow-xs);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                      <div>
                        <div style="font-size: 13.5px; font-weight: 800; color: #101828; line-height: 1.3;"><?= htmlspecialchars($ap['project_name']) ?></div>
                        <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($ap['client_company']) ?></div>
                      </div>
                      <span class="badge" style="background: <?= ($pct >= 90) ? '#FEF3F2' : '#F2F4F7' ?>; color: <?= $pctColor ?>; font-weight: 800; font-size: 11px;">
                        <?= $pct ?>% Terpakai
                      </span>
                    </div>

                    <div style="display: flex; justify-content: space-between; font-size: 11.5px; margin-bottom: 4px;">
                      <span style="color: var(--text-secondary);">Total Pagu Ads:</span>
                      <span style="font-weight: 700; color: #5925DC;"><?= format_rupiah($alloc) ?></span>
                    </div>

                    <div style="display: flex; justify-content: space-between; font-size: 11.5px; margin-bottom: 6px;">
                      <span style="color: var(--text-secondary);">Dicairkan ke Tim:</span>
                      <span style="font-weight: 700; color: #175CD3;"><?= format_rupiah($ap['total_ditransfer_ke_tim_ads']) ?></span>
                    </div>

                    <div style="background: #F2F4F7; height: 6px; border-radius: 3px; overflow: hidden; margin-bottom: 8px;">
                      <div style="background: <?= $pctColor ?>; height: 100%; width: <?= $pct ?>%;"></div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11.5px; border-top: 1px dashed var(--border-color); padding-top: 6px;">
                      <span style="color: var(--text-secondary);">Sisa Pagu di Owner:</span>
                      <span style="font-weight: 800; color: <?= $rem <= 0 ? 'var(--danger-text)' : '#027A48' ?>;"><?= format_rupiah($rem) ?></span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <!-- Table Riwayat Top-up Terbaru dengan 2 Tahap Bukti Transaksi -->
            <div class="kala-table-scroll table-responsive" style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; display:block;">
              <table class="table-custom" style="min-width: 950px; width: 100%;">
                <thead>
                  <tr>
                    <th style="min-width: 180px; white-space: nowrap;">TANGGAL &amp; PETUGAS ADS</th>
                    <th style="white-space: nowrap;">KLIEN &amp; PROYEK</th>
                    <th style="white-space: nowrap;">PLATFORM &amp; TAHAP</th>
                    <th style="white-space: nowrap;">1. TRANSFER OWNER &rarr; TIM ADS</th>
                    <th style="white-space: nowrap;">2. TOP-UP TIM ADS &rarr; META/GOOGLE</th>
                    <th style="white-space: nowrap;">SPEND &amp; STATUS KAMPANYE</th>
                    <th style="white-space: nowrap;">STATUS LAPORAN</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($recentAdsTopups)): ?>
                    <tr>
                      <td colspan="7" style="text-align: center; padding: 28px; color: var(--text-muted);">
                        Belum ada riwayat transaksi isi saldo Ads yang dicatat.
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($recentAdsTopups as $rt): 
                      $tfOwnerProof = !empty($rt['transfer_proof_owner']) ? $rt['transfer_proof_owner'] : (!empty($rt['receipt_file']) ? $rt['receipt_file'] : '');
                      $topupPlatformProof = !empty($rt['platform_topup_proof']) ? $rt['platform_topup_proof'] : (!empty($rt['receipt_file']) ? $rt['receipt_file'] : '');
                      $tfAmount = floatval($rt['transfer_amount_owner']);
                      $platformAmount = floatval($rt['platform_topup_amount']);
                      $spendAmount = floatval($rt['ad_spend_amount']);
                    ?>
                      <tr>
                        <td style="min-width: 180px; white-space: nowrap;">
                          <div style="font-weight: 700; color: #101828;"><?= date('d M Y', strtotime($rt['transaction_date'])) ?></div>
                          <div style="font-size: 11px; color: var(--text-secondary);">Petugas: <b><?= htmlspecialchars($rt['assigned_user_name'] ?? 'Tim Ads') ?></b></div>
                        </td>
                        <td>
                          <div style="font-weight: 700; color: #101828;"><?= htmlspecialchars($rt['client_company'] ?? '-') ?></div>
                          <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($rt['project_name'] ?? '-') ?></div>
                        </td>
                        <td>
                          <span class="badge" style="background: #F4F3FF; color: #5925DC; font-weight: 700; font-size: 11px;">
                            <?= htmlspecialchars($rt['platform']) ?>
                          </span>
                          <span style="font-size: 11px; color: var(--text-secondary); display: block; margin-top: 2px;">
                            <?= htmlspecialchars($rt['stage_label'] ?? 'Tahap Berjalan') ?>
                          </span>
                        </td>
                        <td style="white-space: nowrap;">
                          <div style="font-weight: 800; color: #175CD3; font-size: 13px;">
                            <?= format_rupiah($tfAmount) ?>
                          </div>
                          <?php if (!empty($tfOwnerProof)): ?>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="previewTransactionReceipt('<?= htmlspecialchars(addslashes($tfOwnerProof)) ?>', 'Bukti Transfer Manajemen ke Tim Ads - <?= htmlspecialchars(addslashes($rt['client_company'] ?? '')) ?>')" style="font-size: 10.5px; font-weight: 700; padding: 3px 8px; margin-top: 3px; display: inline-flex; align-items: center; gap: 4px; color: #175CD3; border-color: #B2CCFF; background: #EFF8FF;">
                              📄 Bukti Transfer Manajemen
                            </button>
                          <?php else: ?>
                            <span style="font-size: 10.5px; color: var(--text-muted); font-style: italic;">Belum ada lampiran</span>
                          <?php endif; ?>
                        </td>
                        <td style="white-space: nowrap;">
                          <div style="font-weight: 800; color: #027A48; font-size: 13px;">
                            <?= format_rupiah($platformAmount) ?>
                          </div>
                          <?php if (!empty($topupPlatformProof)): ?>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="previewTransactionReceipt('<?= htmlspecialchars(addslashes($topupPlatformProof)) ?>', 'Bukti Top-Up Platform <?= htmlspecialchars(addslashes($rt['platform'])) ?>')" style="font-size: 10.5px; font-weight: 700; padding: 3px 8px; margin-top: 3px; display: inline-flex; align-items: center; gap: 4px; color: #027A48; border-color: #A6F4C5; background: #ECFDF3;">
                              📈 Bukti Top-Up Ads
                            </button>
                          <?php else: ?>
                            <span class="badge" style="background: #FEF3F2; color: #B42318; font-size: 10px; font-weight: 700; margin-top: 3px;">⏳ Menunggu Top-Up</span>
                          <?php endif; ?>
                        </td>
                        <td style="white-space: nowrap;">
                          <div style="font-size: 12px; color: #101828;">Spend: <b><?= format_rupiah($spendAmount) ?></b></div>
                          <span class="badge" style="background: <?= ($rt['campaign_status'] === 'running' || empty($rt['campaign_status'])) ? '#ECFDF3' : '#F2F4F7' ?>; color: <?= ($rt['campaign_status'] === 'running' || empty($rt['campaign_status'])) ? '#027A48' : '#344054' ?>; font-size: 10px; font-weight: 700; margin-top: 2px;">
                            <?= ($rt['campaign_status'] === 'running' || empty($rt['campaign_status'])) ? '🟢 Iklan Jalan' : '⏸️ Dijeda' ?>
                          </span>
                        </td>
                        <td>
                          <span class="badge" style="background: <?= ($rt['status'] === 'verified') ? '#ECFDF3' : '#EFF8FF' ?>; color: <?= ($rt['status'] === 'verified') ? '#027A48' : '#175CD3' ?>; font-size: 10.5px; font-weight: 700;">
                            <?= ($rt['status'] === 'verified') ? '✓ Terverifikasi' : 'Terekam' ?>
                          </span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- 7. Manajemen Akun Pengguna (Karyawan, Freelance, Client & Admin) -->
          <div class="glass-panel owner-full-width-panel" style="margin-top: 24px;">
            <div class="panel-header" style="margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
              <div>
                <h3 class="panel-title">
                  Manajemen Akun Pengguna (User Access Control)
                </h3>
                <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">
                  Kelola hak akses dan tambahkan akun baru untuk Karyawan, Freelancer, dan Klien agensi
                </p>
              </div>
              <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="openCreateUserModal('karyawan')">
                  + Akun Karyawan
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="openCreateUserModal('freelancer')">
                  + Akun Freelance
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="openCreateUserModal('client')">
                  + Akun Klien
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="openCreateUserModal()">
                  + Tambah Akun
                </button>
              </div>
            </div>

            <!-- Role Summary Badges -->
            <div style="display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap;">
              <div style="background: #ECFDF3; border: 1px solid #A6F4C5; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 700; color: #027A48;">
                Karyawan: <?= $userCounts['karyawan'] ?> Akun
              </div>
              <div style="background: #F4F3FF; border: 1px solid #D9D6FE; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 700; color: #5925DC;">
                Freelancer: <?= $userCounts['freelancer'] ?> Akun
              </div>
              <div style="background: #EFF8FF; border: 1px solid #B2DDFF; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 700; color: #175CD3;">
                Client Portal: <?= $userCounts['client'] ?> Akun
              </div>
              <div style="background: #F8F9FC; border: 1px solid #EAECF0; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 700; color: #344054;">
                Admin &amp; Owner: <?= $userCounts['admin'] + $userCounts['owner'] ?> Akun
              </div>
            </div>

            <!-- Users Table -->
            <div class="table-responsive">
              <table class="table-custom">
                <thead>
                  <tr>
                    <th>Nama &amp; Email</th>
                    <th>Peran (Role)</th>
                    <th>Afiliasi / Kontak</th>
                    <th>Status</th>
                    <th>Tgl Dibuat</th>
                    <th style="text-align: right;">Aksi</th>
                  </tr>
                </thead>
                <tbody id="user-management-tbody">
                  <?php foreach ($systemUsers as $u): ?>
                    <tr id="user-row-<?= $u['id'] ?>">
                      <td>
                        <div style="font-weight: 700; color: #101828;"><?= htmlspecialchars($u['name']) ?></div>
                        <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($u['email']) ?></div>
                      </td>
                      <td>
                        <?php
                          $roleBadgeStyle = [
                            'owner' => 'background: #FEF3F2; color: #B42318; border: 1px solid #FECDCA;',
                            'admin' => 'background: #EFF8FF; color: #175CD3; border: 1px solid #B2DDFF;',
                            'karyawan' => 'background: #ECFDF3; color: #027A48; border: 1px solid #A6F4C5;',
                            'freelancer' => 'background: #F4F3FF; color: #5925DC; border: 1px solid #D9D6FE;',
                            'client' => 'background: #F8F9FC; color: #344054; border: 1px solid #D0D5DD;'
                          ][$u['role']] ?? 'background: #F2F4F7; color: #344054;';

                          $roleText = [
                            'owner' => 'Owner',
                            'admin' => 'Admin / PM',
                            'karyawan' => 'Karyawan',
                            'freelancer' => 'Freelancer',
                            'client' => 'Client Portal'
                          ][$u['role']] ?? ucfirst($u['role']);
                        ?>
                        <span style="font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 4px; <?= $roleBadgeStyle ?>">
                          <?= $roleText ?>
                        </span>
                      </td>
                      <td>
                        <?php if ($u['role'] === 'client'): ?>
                          <div style="font-size: 12px; font-weight: 600; color: #101828;">
                            <?= htmlspecialchars($u['client_company'] ?: 'Belum terhubung') ?>
                          </div>
                          <div style="font-size: 10.5px; color: var(--text-secondary);">Portal Klien</div>
                        <?php else: ?>
                          <div style="font-size: 12px; color: #101828;">
                            <?= htmlspecialchars($u['phone_wa'] ?: '-') ?>
                          </div>
                          <div style="font-size: 10.5px; color: var(--text-secondary);">
                            <?= $u['employment_type'] === 'freelance' ? 'Talenta Eksternal' : 'Internal Staff' ?>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <span style="font-size: 11px; font-weight: 700; color: #027A48; background: #ECFDF3; padding: 2px 8px; border-radius: 12px;">
                          Aktif
                        </span>
                      </td>
                      <td style="font-size: 12px; color: var(--text-secondary);">
                        <?= !empty($u['created_at']) ? date('d M Y', strtotime($u['created_at'])) : '-' ?>
                      </td>
                      <td style="text-align: right;">
                        <div style="display: inline-flex; gap: 6px;">
                          <button type="button" class="btn btn-secondary btn-sm" onclick="openResetPasswordModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')" style="font-size: 11px; padding: 4px 8px;">
                            Reset Sandi
                          </button>
                          <?php if ($u['role'] !== 'owner' && $u['id'] != ($currentUser['id'] ?? 0)): ?>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="deleteUserAccount(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')" style="color: var(--danger-text); border-color: #FECDCA; font-size: 11px; padding: 4px 8px;">
                              Hapus
                            </button>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

        </div>

  <script>
    window.__INITIAL_CHART_DATA__ = <?= json_encode($initialChartData) ?>;

    document.addEventListener('DOMContentLoaded', () => {
      // 1. Instant zero-lag rendering from server-side prepared data
      if (window.__INITIAL_CHART_DATA__ && typeof initCashflowChart === 'function') {
        initCashflowChart(window.__INITIAL_CHART_DATA__);
      }

      // 2. Sync with live analytics API
      const baseUrl = window.__KALA_BASE_URL__ || '';
      fetch(baseUrl + '/api/analytics.php?range=month')
        .then(res => res.json())
        .then(data => {
          if (data.chart && typeof initCashflowChart === 'function') {
            initCashflowChart(data.chart);
          }
        })
        .catch(err => console.log('Analytics sync:', err));
    });
  </script>

  <?php require_once BASE_PATH . '/includes/footer.php'; ?>

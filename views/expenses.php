<?php
/**
 * Kalamedia Expenses Management View (Untitled UI Design System)
 * Clean Monochrome Light Theme, 1px Subtle Borders, High Ergonomics & Readability
 * Covers Freelancer Payouts & Ads Spend
 */
require_auth();
$db = Database::getConnection();

$tab = $_GET['tab'] ?? 'freelancers'; // 'freelancers' or 'ads'

// Fetch Freelancer Payouts
$payouts = $db->query("
    SELECT p.*, pr.name as project_name, c.company as client_company
    FROM freelancer_payouts p
    JOIN projects pr ON p.project_id = pr.id
    JOIN clients c ON pr.client_id = c.id
    WHERE COALESCE(p.is_deleted, 0) = 0
    ORDER BY p.id DESC
")->fetchAll();

// Fetch Ads Spend
$adsList = $db->query("
    SELECT a.*, c.company as client_company, pr.name as project_name
    FROM ads_spend a
    JOIN clients c ON a.client_id = c.id
    LEFT JOIN projects pr ON a.project_id = pr.id
    WHERE COALESCE(a.is_deleted, 0) = 0
    ORDER BY a.id DESC
")->fetchAll();

// Expense Totals
$totalFreelancer = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM freelancer_payouts WHERE status = 'Paid' AND COALESCE(is_deleted, 0) = 0")->fetchColumn());
$totalAds = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM ads_spend WHERE COALESCE(is_deleted, 0) = 0")->fetchColumn());
$totalPendingFreelancer = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM freelancer_payouts WHERE status = 'Pending' AND COALESCE(is_deleted, 0) = 0")->fetchColumn());
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pengeluaran Operasional (Outflow) - PT Kala Kausa Prima</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=bankio-typography-3.0">
  <style>
    /* Bankio Minimal Finance Design System Extensions */
    .expenses-header-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      gap: 16px;
      flex-wrap: wrap;
    }
    .expenses-page-title {
      font-size: 20px;
      font-weight: 800;
      color: #000000;
      margin: 0;
      line-height: 1.25;
      letter-spacing: -0.02em;
    }
    .expenses-page-subtitle {
      font-size: 13px;
      color: #6E7485;
      margin-top: 4px;
      margin-bottom: 0;
    }
    .btn-solid-dark {
      background: #5D76F5;
      color: #FFFFFF;
      border: 1px solid #5D76F5;
      font-weight: 600;
      font-size: 13.5px;
      padding: 10px 18px;
      border-radius: 14px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      box-shadow: 0 4px 14px rgba(93, 118, 245, 0.25);
      transition: transform 200ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 200ms cubic-bezier(0.22, 1, 0.36, 1), background-color 200ms ease;
      text-decoration: none;
      will-change: transform;
    }
    .btn-solid-dark:hover {
      background: #4A63E2;
      border-color: #4A63E2;
      color: #FFFFFF;
      box-shadow: 0 8px 20px -4px rgba(93, 118, 245, 0.38);
      transform: translate3d(0, -1.5px, 0);
    }
    .btn-solid-dark:active {
      transform: translate3d(0, 0.5px, 0) scale(0.96);
      transition-duration: 90ms;
    }
    
    /* Level 1 Filter: Segmented Control Tabs */
    .segmented-control-container {
      background: #EEF0F6;
      border: 1px solid #ECEEF5;
      border-radius: 999px;
      padding: 5px;
      display: inline-flex;
      gap: 4px;
      margin-bottom: 24px;
    }
    .segmented-tab-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 18px;
      border-radius: 999px;
      font-size: 13px;
      font-weight: 600;
      color: #6E7485;
      text-decoration: none;
      transition: background-color 200ms cubic-bezier(0.22, 1, 0.36, 1), color 200ms cubic-bezier(0.22, 1, 0.36, 1), transform 150ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 200ms ease;
      font-family: inherit;
      will-change: transform;
    }
    .segmented-tab-btn:hover:not(.active) {
      color: #000000;
      transform: translate3d(0, -1px, 0);
    }
    .segmented-tab-btn:active {
      transform: scale(0.96);
      transition-duration: 90ms;
    }
    .segmented-tab-btn.active {
      background: #5D76F5;
      color: #FFFFFF;
      font-weight: 700;
      box-shadow: 0 4px 12px rgba(93, 118, 245, 0.28);
    }

    /* Level 2: Integrated Table Toolbar & Container */
    .untitled-card {
      background: #FFFFFF;
      border: 1px solid #ECEEF5;
      border-radius: 22px;
      box-shadow: 0 10px 30px -10px rgba(18, 24, 48, 0.05), 0 2px 6px -1px rgba(18, 24, 48, 0.02);
      overflow: hidden;
    }
    .table-toolbar-row {
      padding: 16px 20px;
      border-bottom: 1px solid #ECEEF5;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
      background: #FFFFFF;
    }
    .toolbar-filters-group {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      flex: 1;
    }
    .search-input-wrapper {
      position: relative;
      width: 320px;
      max-width: 100%;
    }
    .search-input-wrapper svg {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #667085;
      pointer-events: none;
    }
    .search-input-control {
      width: 100%;
      height: 40px;
      padding: 8px 12px 8px 38px;
      border: 1px solid #D0D5DD;
      border-radius: 8px;
      font-size: 13.5px;
      color: #101828;
      background: #FFFFFF;
      box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
      transition: border-color 200ms ease, box-shadow 200ms ease;
      outline: none;
      box-sizing: border-box;
    }
    .search-input-control:focus {
      border-color: #2563EB;
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }
    .select-filter-control {
      height: 40px;
      padding: 0 32px 0 12px;
      border: 1px solid #D0D5DD;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 500;
      color: #344054;
      background: #FFFFFF url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23667085' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 10px center;
      box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
      cursor: pointer;
      outline: none;
      appearance: none;
      -webkit-appearance: none;
    }
    .select-filter-control:focus {
      border-color: #2563EB;
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }
    .btn-ghost-outlined {
      height: 40px;
      padding: 0 14px;
      border: 1px solid #D0D5DD;
      background: #FFFFFF;
      color: #344054;
      font-size: 13px;
      font-weight: 600;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      cursor: pointer;
      box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
      transition: transform 200ms cubic-bezier(0.22, 1, 0.36, 1), background-color 200ms ease, color 200ms ease, border-color 200ms ease;
      text-decoration: none;
      will-change: transform;
    }
    .btn-ghost-outlined:hover {
      background: #F9FAFB;
      border-color: #B2BAC6;
      color: #1D2939;
      transform: translate3d(0, -1.5px, 0);
    }
    .btn-ghost-outlined:active {
      transform: translate3d(0, 0.5px, 0) scale(0.96);
      transition-duration: 90ms;
    }

    /* Table Typography & Rows */
    .untitled-table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
    }
    .untitled-table th {
      background: #F9FAFB;
      padding: 12px 20px;
      font-size: 11.5px;
      font-weight: 600;
      color: #475467;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      border-bottom: 1px solid #EAECF0;
      white-space: nowrap;
    }
    .untitled-table td {
      padding: 16px 20px;
      border-bottom: 1px solid #EAECF0;
      font-size: 13px;
      vertical-align: middle;
      color: #475467;
      background: #FFFFFF;
      transition: background 0.15s ease;
    }
    .untitled-table tbody tr:hover td {
      background: #F8FAFC;
    }
    .untitled-table tbody tr:last-child td {
      border-bottom: none;
    }

    /* Status Pills */
    .pill-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 3px 10px;
      border-radius: 16px;
      font-size: 12px;
      font-weight: 600;
      white-space: nowrap;
    }
    .pill-paid {
      background: #ECFDF3;
      color: #027A48;
      border: 1px solid #A6F4C5;
    }
    .pill-pending {
      background: #FFFAEB;
      color: #B54708;
      border: 1px solid #FEDF89;
    }
    .dot-indicator {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      display: inline-block;
    }
    .dot-paid { background: #12B76A; }
    .dot-pending { background: #F79009; }

    /* Action Buttons */
    .action-btn-group {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      justify-content: flex-end;
    }
    .btn-action-outline {
      height: 34px;
      padding: 0 10px;
      border: 1px solid #D0D5DD;
      background: #FFFFFF;
      color: #344054;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      cursor: pointer;
      box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
      transition: transform 200ms cubic-bezier(0.22, 1, 0.36, 1), background-color 200ms ease, color 200ms ease, border-color 200ms ease;
      white-space: nowrap;
      will-change: transform;
    }
    .btn-action-outline:hover {
      background: #F9FAFB;
      border-color: #B2BAC6;
      color: #101828;
      transform: translate3d(0, -1px, 0);
    }
    .btn-action-outline:active {
      transform: scale(0.96);
      transition-duration: 90ms;
    }
    .btn-action-upload {
      height: 34px;
      padding: 0 10px;
      border: 1px dashed #D0D5DD;
      background: #F9FAFB;
      color: #475467;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      cursor: pointer;
      transition: transform 200ms cubic-bezier(0.22, 1, 0.36, 1), background-color 200ms ease, border-color 200ms ease;
      white-space: nowrap;
      will-change: transform;
    }
    .btn-action-upload:hover {
      background: #F2F4F7;
      border-color: #98A2B3;
      color: #101828;
      transform: translate3d(0, -1px, 0);
    }
    .btn-action-upload:active {
      transform: scale(0.96);
      transition-duration: 90ms;
    }
    .btn-icon-square {
      width: 34px;
      height: 34px;
      border: 1px solid #D0D5DD;
      background: #FFFFFF;
      color: #344054;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
      transition: transform 200ms cubic-bezier(0.22, 1, 0.36, 1), background-color 200ms ease, color 200ms ease, border-color 200ms ease;
      will-change: transform;
    }
    .btn-icon-square:hover {
      background: #F9FAFB;
      border-color: #B2BAC6;
      color: #101828;
      transform: translate3d(0, -1px, 0);
    }
    .btn-icon-square:active {
      transform: scale(0.96);
      transition-duration: 90ms;
    }
    .btn-icon-delete {
      width: 34px;
      height: 34px;
      border: 1px solid #FECDCA;
      background: #FEF3F2;
      color: #D92D20;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
      transition: transform 200ms cubic-bezier(0.22, 1, 0.36, 1), background-color 200ms ease, border-color 200ms ease;
      will-change: transform;
    }
    .btn-icon-delete:hover {
      background: #FEE4E2;
      border-color: #FDA29B;
      transform: translate3d(0, -1px, 0);
    }
    .btn-icon-delete:active {
      transform: scale(0.96);
      transition-duration: 90ms;
    }
  </style>
</head>
<body>
  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body">

        <!-- 1. Page Header & Dynamic Primary CTA -->
        <div class="expenses-header-row">
          <div>
            <h1 class="expenses-page-title">Pengeluaran Operasional (Outflow)</h1>
            <p class="expenses-page-subtitle">Kelola pembayaran fee freelancer dan top-up saldo iklan</p>
          </div>

          <div>
            <?php if ($tab === 'freelancers'): ?>
              <button type="button" class="btn-solid-dark" onclick="openModal('modal-input-payout')">
                <span>+ Input Fee Freelancer</span>
              </button>
            <?php else: ?>
              <button type="button" class="btn-solid-dark" onclick="openModal('modal-catat-ads')">
                <span>+ Catat Top-Up Ads</span>
              </button>
            <?php endif; ?>
          </div>
        </div>

        <!-- KPI Breakdown Cards -->
        <div class="kpi-grid" style="margin-bottom: 24px;">
          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Fee Freelancer (Paid)</span>
            </div>
            <div class="kpi-value" style="color: var(--danger-text);"><?= format_rupiah($totalFreelancer) ?></div>
            <div class="kpi-meta" style="color: var(--danger-text); font-weight: 600;">&bull; Sudah ditransfer ke talenta</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Pending Freelancer Fee</span>
            </div>
            <div class="kpi-value" style="color: var(--warning-text);"><?= format_rupiah($totalPendingFreelancer) ?></div>
            <div class="kpi-meta" style="color: var(--warning-text); font-weight: 600;">&bull; Antrean transfer belum dibayar</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Total Ads Spend (Top-Up)</span>
            </div>
            <div class="kpi-value"><?= format_rupiah($totalAds) ?></div>
            <div class="kpi-meta">Meta, Google, TikTok Ads</div>
          </div>
        </div>

        <!-- 2. Main Category Tabs (Level 1 Filter: Segmented Control) -->
        <div class="segmented-control-container">
          <a href="<?= url('expenses?tab=freelancers') ?>" class="segmented-tab-btn <?= $tab === 'freelancers' ? 'active' : '' ?>">
            <span>Fee Freelancer</span>
            <span style="font-size: 11px; padding: 1px 7px; border-radius: 10px; background: <?= $tab === 'freelancers' ? '#F2F4F7' : 'rgba(0,0,0,0.06)' ?>; font-weight: 600; color: #344054;"><?= count($payouts) ?></span>
          </a>
          <a href="<?= url('expenses?tab=ads') ?>" class="segmented-tab-btn <?= $tab === 'ads' ? 'active' : '' ?>">
            <span>Top-Up Ads</span>
            <span style="font-size: 11px; padding: 1px 7px; border-radius: 10px; background: <?= $tab === 'ads' ? '#F2F4F7' : 'rgba(0,0,0,0.06)' ?>; font-weight: 600; color: #344054;"><?= count($adsList) ?></span>
          </a>
        </div>

        <!-- 3. Integrated Table Toolbar & Table Container (Untitled UI Card) -->
        <div class="untitled-card">
          
          <!-- Toolbar Row (Level 2 Multi-Filter Bar) -->
          <div class="table-toolbar-row">
            <div class="toolbar-filters-group">
              
              <!-- Search Input -->
              <div class="search-input-wrapper">
                <input 
                  type="text" 
                  id="expenses-search-input" 
                  class="search-input-control" 
                  placeholder="<?= $tab === 'freelancers' ? 'Cari nama freelancer, proyek, atau rekening...' : 'Cari platform, ID akun, klien, atau catatan...' ?>"
                  onkeyup="applyExpensesFilters()"
                >
              </div>

              <?php if ($tab === 'freelancers'): ?>
                <!-- Status Filter Dropdown -->
                <select id="filter-status" class="select-filter-control" onchange="applyExpensesFilters()">
                  <option value="">Status: Semua Status</option>
                  <option value="Paid">Status: Lunas (Paid)</option>
                  <option value="Pending">Status: Pending</option>
                </select>
              <?php else: ?>
                <!-- Platform Filter Dropdown for Ads -->
                <select id="filter-platform" class="select-filter-control" onchange="applyExpensesFilters()">
                  <option value="">Platform: Semua Platform</option>
                  <option value="Meta Ads">Meta Ads</option>
                  <option value="Google Ads">Google Ads</option>
                  <option value="TikTok Ads">TikTok Ads</option>
                  <option value="LinkedIn Ads">LinkedIn Ads</option>
                  <option value="Twitter/X Ads">X (Twitter) Ads</option>
                </select>
              <?php endif; ?>

              <!-- Period Filter Dropdown -->
              <select id="filter-period" class="select-filter-control" onchange="applyExpensesFilters()">
                <option value="all">Periode: Semua Waktu</option>
                <option value="this_month">Periode: Bulan Ini</option>
                <option value="this_quarter">Periode: Kuartal Ini</option>
                <option value="this_year">Periode: Tahun Ini</option>
              </select>

            </div>

            <!-- Export Button -->
            <div>
              <button type="button" class="btn-ghost-outlined" onclick="exportExpensesCsv()" title="Export data tabel ke file CSV / Spreadsheet">
                <span>Export CSV</span>
              </button>
            </div>
          </div>

          <?php if ($tab === 'freelancers'): ?>
            <!-- 4. Freelancer Payouts Table -->
            <div class="table-responsive" style="margin: 0;">
              <table class="untitled-table" id="payout-table">
                <thead>
                  <tr>
                    <th>NAMA FREELANCER &amp; REKENING</th>
                    <th>PROYEK &amp; KLIEN</th>
                    <th>URAIAN PEKERJAAN</th>
                    <th>NOMINAL FEE</th>
                    <th>STATUS</th>
                    <th style="text-align: right;">BUKTI &amp; AKSI</th>
                  </tr>
                </thead>
                <tbody id="payout-tbody">
                  <?php if (empty($payouts)): ?>
                    <tr id="empty-row"><td colspan="6" style="text-align: center; color: #667085; padding: 32px 20px;">Belum ada data pembayaran freelancer.</td></tr>
                  <?php else: ?>
                    <?php foreach ($payouts as $p): ?>
                      <?php
                        $rawDate = substr($p['paid_at'] ?: $p['created_at'], 0, 10);
                        $searchCorpus = strtolower($p['freelancer_name'] . ' ' . ($p['freelancer_phone'] ?? '') . ' ' . $p['freelancer_bank'] . ' ' . $p['freelancer_account'] . ' ' . $p['project_name'] . ' ' . $p['client_company'] . ' ' . $p['task_description']);
                      ?>
                      <tr 
                        class="expense-row" 
                        data-search="<?= htmlspecialchars($searchCorpus) ?>"
                        data-status="<?= $p['status'] ?>"
                        data-date="<?= $rawDate ?>"
                        data-amount="<?= $p['amount'] ?>"
                        data-name="<?= htmlspecialchars($p['freelancer_name']) ?>"
                        data-project="<?= htmlspecialchars($p['project_name']) ?>"
                        data-client="<?= htmlspecialchars($p['client_company']) ?>"
                      >
                        <td>
                          <div style="font-weight: 700; color: #101828; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                            <span><?= htmlspecialchars($p['freelancer_name']) ?></span>
                            <?php if (!empty($p['freelancer_phone'])): ?>
                              <span style="font-size: 10.5px; padding: 2px 6px; border-radius: 4px; background: rgba(37, 211, 102, 0.12); color: #15803d; font-weight: 600;">WA: <?= htmlspecialchars($p['freelancer_phone']) ?></span>
                            <?php endif; ?>
                          </div>
                          <div style="font-size: 11.5px; color: #667085; margin-top: 2px;">
                            <?= htmlspecialchars($p['freelancer_bank'] ?: 'Bank') ?> &bull; <?= htmlspecialchars($p['freelancer_account'] ?: 'No Rekening -') ?>
                          </div>
                        </td>
                        <td>
                          <div style="font-weight: 600; color: #101828;"><?= htmlspecialchars($p['project_name']) ?></div>
                          <div style="font-size: 11.5px; color: #667085; margin-top: 2px;"><?= htmlspecialchars($p['client_company']) ?></div>
                        </td>
                        <td style="color: #475467; max-width: 240px; line-height: 1.4;">
                          <?= htmlspecialchars($p['task_description']) ?>
                        </td>
                        <td style="font-weight: 700; color: #D92D20; font-size: 13.5px; white-space: nowrap;">
                          <?= format_rupiah($p['amount']) ?>
                        </td>
                        <td>
                          <?php if ($p['status'] === 'Paid'): ?>
                            <span class="pill-badge pill-paid">
                              <span class="dot-indicator dot-paid"></span>
                              <span>PAID</span>
                            </span>
                          <?php else: ?>
                            <span class="pill-badge pill-pending">
                              <span class="dot-indicator dot-pending"></span>
                              <span>PENDING</span>
                            </span>
                          <?php endif; ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                          <div class="action-btn-group">
                            
                            <!-- Invoice Button -->
                            <button type="button" class="btn-action-outline" onclick="openFreelancerVoucherModal(<?= $p['id'] ?>)" title="Lihat &amp; Cetak Invoice / Voucher Fee Freelancer">
                              <span>Invoice Fee</span>
                            </button>

                            <!-- Struk / Upload Button -->
                            <?php if (!empty($p['receipt_file'])): ?>
                              <button type="button" class="btn-action-outline" onclick="viewReceiptImage('<?= UPLOAD_URL . '/' . htmlspecialchars($p['receipt_file']) ?>', 'Bukti Transfer <?= htmlspecialchars($p['freelancer_name']) ?>')" title="Lihat Bukti Transfer">
                                <span>Lihat Struk</span>
                              </button>
                            <?php else: ?>
                              <button type="button" class="btn-action-upload" onclick="triggerUploadModal('payout', <?= $p['id'] ?>, 'Upload Bukti Fee <?= htmlspecialchars($p['freelancer_name']) ?>')" title="Upload Struk Transfer">
                                <span>+ Struk</span>
                              </button>
                            <?php endif; ?>

                            <!-- Edit Button -->
                            <button type="button" class="btn-action-outline" onclick="openEditPayoutModal(<?= $p['id'] ?>)" title="Edit Pembayaran Freelancer" style="font-weight: 600; padding: 4px 8px; font-size: 11.5px;">
                              <span>Edit</span>
                            </button>

                            <!-- Delete Button (Owner Only) -->
                            <?php if (is_owner()): ?>
                              <button type="button" class="btn-icon-delete" onclick="confirmDeleteExpense('payout', <?= $p['id'] ?>, '<?= htmlspecialchars($p['freelancer_name']) ?>')" title="Hapus Fee Freelancer" style="font-size: 11.5px; font-weight: 600; padding: 4px 8px; width: auto; height: auto;">
                                <span>Hapus</span>
                              </button>
                            <?php endif; ?>

                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

          <?php else: ?>
            <!-- 4b. Ads Top-Up Table -->
            <div class="table-responsive" style="margin: 0;">
              <table class="untitled-table" id="ads-table">
                <thead>
                  <tr>
                    <th>PLATFORM &amp; ID AKUN</th>
                    <th>KLIEN &amp; PROYEK</th>
                    <th>TANGGAL TOP-UP</th>
                    <th>KETERANGAN CAMPAIGN</th>
                    <th>NOMINAL TOP-UP</th>
                    <th style="text-align: right;">BUKTI &amp; AKSI</th>
                  </tr>
                </thead>
                <tbody id="ads-tbody">
                  <?php if (empty($adsList)): ?>
                    <tr id="empty-row"><td colspan="6" style="text-align: center; color: #667085; padding: 32px 20px;">Belum ada data pengeluaran iklan.</td></tr>
                  <?php else: ?>
                    <?php foreach ($adsList as $ad): ?>
                      <?php
                        $rawDate = substr($ad['spent_date'], 0, 10);
                        $searchCorpus = strtolower($ad['platform'] . ' ' . ($ad['account_id'] ?? '') . ' ' . $ad['client_company'] . ' ' . ($ad['project_name'] ?? '') . ' ' . ($ad['notes'] ?? ''));
                      ?>
                      <tr 
                        class="expense-row"
                        data-search="<?= htmlspecialchars($searchCorpus) ?>"
                        data-platform="<?= htmlspecialchars($ad['platform']) ?>"
                        data-date="<?= $rawDate ?>"
                        data-amount="<?= $ad['amount'] ?>"
                        data-client="<?= htmlspecialchars($ad['client_company']) ?>"
                        data-project="<?= htmlspecialchars($ad['project_name'] ?: '-') ?>"
                      >
                        <td>
                          <div style="font-weight: 700; color: #101828;"><?= htmlspecialchars($ad['platform']) ?></div>
                          <div style="font-size: 11.5px; color: #667085; margin-top: 2px;">ID: <?= htmlspecialchars($ad['account_id'] ?: '-') ?></div>
                        </td>
                        <td>
                          <div style="font-weight: 600; color: #101828;"><?= htmlspecialchars($ad['client_company']) ?></div>
                          <div style="font-size: 11.5px; color: #667085; margin-top: 2px;"><?= htmlspecialchars($ad['project_name'] ?: '-') ?></div>
                        </td>
                        <td style="color: #475467;"><?= format_date($ad['spent_date']) ?></td>
                        <td style="color: #475467; max-width: 240px; line-height: 1.4;"><?= htmlspecialchars($ad['notes'] ?: '-') ?></td>
                        <td style="font-weight: 700; color: #D92D20; font-size: 13.5px; white-space: nowrap;">
                          <?= format_rupiah($ad['amount']) ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                          <div class="action-btn-group">
                            
                            <!-- Invoice Ads Button -->
                            <button type="button" class="btn-action-outline" onclick="openAdsVoucherModal(<?= $ad['id'] ?>)" title="Lihat &amp; Cetak Invoice / Voucher Top-Up Ads">
                              <span>Invoice Ads</span>
                            </button>

                            <!-- Struk Button -->
                            <?php if (!empty($ad['receipt_file'])): ?>
                              <button type="button" class="btn-action-outline" onclick="viewReceiptImage('<?= UPLOAD_URL . '/' . htmlspecialchars($ad['receipt_file']) ?>', 'Struk Top-Up <?= htmlspecialchars($ad['platform']) ?>')" title="Lihat Struk Top-Up">
                                <span>Lihat Struk</span>
                              </button>
                            <?php else: ?>
                              <button type="button" class="btn-action-upload" onclick="triggerUploadModal('ads', <?= $ad['id'] ?>, 'Upload Struk <?= htmlspecialchars($ad['platform']) ?>')" title="Upload Struk Top-Up">
                                <span>+ Struk</span>
                              </button>
                            <?php endif; ?>

                            <!-- Edit Button -->
                            <button type="button" class="btn-action-outline" onclick="openEditAdsModal(<?= $ad['id'] ?>)" title="Edit Top-Up Ads" style="font-weight: 600; padding: 4px 8px; font-size: 11.5px;">
                              <span>Edit</span>
                            </button>

                            <!-- Delete Button (Owner Only) -->
                            <?php if (is_owner()): ?>
                              <button type="button" class="btn-icon-delete" onclick="confirmDeleteExpense('ads', <?= $ad['id'] ?>, 'Top-Up <?= htmlspecialchars($ad['platform']) ?>')" title="Hapus Top-Up Ads" style="font-size: 11.5px; font-weight: 600; padding: 4px 8px; width: auto; height: auto;">
                                <span>Hapus</span>
                              </button>
                            <?php endif; ?>

                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

        </div> <!-- /.untitled-card -->

      </div> <!-- /.content-body -->
    </main>
  </div>

  <?php require_once BASE_PATH . '/includes/footer.php'; ?>

  <script>
    // Multi-Filter Engine
    function applyExpensesFilters() {
      const query = (document.getElementById('expenses-search-input')?.value || '').toLowerCase().trim();
      const statusFilter = document.getElementById('filter-status')?.value || '';
      const platformFilter = document.getElementById('filter-platform')?.value || '';
      const periodFilter = document.getElementById('filter-period')?.value || 'all';

      const now = new Date();
      const curYear = now.getFullYear();
      const curMonth = String(now.getMonth() + 1).padStart(2, '0');
      const curQuarterStartMonth = Math.floor(now.getMonth() / 3) * 3 + 1;

      const rows = document.querySelectorAll('.expense-row');
      let visibleCount = 0;

      rows.forEach(row => {
        const searchCorpus = row.dataset.search || '';
        const rowStatus = row.dataset.status || '';
        const rowPlatform = row.dataset.platform || '';
        const rowDate = row.dataset.date || ''; // YYYY-MM-DD

        let matchSearch = !query || searchCorpus.includes(query);
        let matchStatus = !statusFilter || (rowStatus.toLowerCase() === statusFilter.toLowerCase());
        let matchPlatform = !platformFilter || (rowPlatform.toLowerCase() === platformFilter.toLowerCase());
        
        let matchPeriod = true;
        if (periodFilter !== 'all' && rowDate) {
          const rowY = parseInt(rowDate.substring(0, 4));
          const rowM = parseInt(rowDate.substring(5, 7));

          if (periodFilter === 'this_month') {
            matchPeriod = (rowY === curYear && rowM === parseInt(curMonth));
          } else if (periodFilter === 'this_quarter') {
            matchPeriod = (rowY === curYear && rowM >= curQuarterStartMonth && rowM <= (curQuarterStartMonth + 2));
          } else if (periodFilter === 'this_year') {
            matchPeriod = (rowY === curYear);
          }
        }

        if (matchSearch && matchStatus && matchPlatform && matchPeriod) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });

      // Handle Empty State dynamically
      let emptyMsgRow = document.getElementById('filter-empty-row');
      const tbody = document.getElementById('payout-tbody') || document.getElementById('ads-tbody');

      if (visibleCount === 0 && rows.length > 0) {
        if (!emptyMsgRow && tbody) {
          emptyMsgRow = document.createElement('tr');
          emptyMsgRow.id = 'filter-empty-row';
          emptyMsgRow.innerHTML = '<td colspan="6" style="text-align: center; color: #667085; padding: 36px 20px;">Tidak ada data pengeluaran yang cocok dengan filter pencarian saat ini.</td>';
          tbody.appendChild(emptyMsgRow);
        }
      } else if (emptyMsgRow) {
        emptyMsgRow.remove();
      }
    }

    // CSV / Spreadsheet Export Engine
    function exportExpensesCsv() {
      const activeTab = "<?= $tab ?>";
      const rows = document.querySelectorAll('.expense-row');
      let csvContent = "data:text/csv;charset=utf-8,";

      if (activeTab === 'freelancers') {
        csvContent += "Nama Freelancer,Nomor WhatsApp,Bank,Nomor Rekening,Proyek,Klien,Uraian Pekerjaan,Nominal Fee,Status,Tanggal\r\n";
        rows.forEach(r => {
          if (r.style.display !== 'none') {
            const name = `"${(r.dataset.name || '').replace(/"/g, '""')}"`;
            const phone = `"${(r.querySelector('span[style*="background: rgba(37, 211, 102"]')?.innerText.replace('WA: ', '') || '')}"`;
            const bankAcc = `"${(r.querySelector('td:first-child div:last-child')?.innerText || '').replace(/"/g, '""')}"`;
            const proj = `"${(r.dataset.project || '').replace(/"/g, '""')}"`;
            const client = `"${(r.dataset.client || '').replace(/"/g, '""')}"`;
            const task = `"${(r.querySelector('td:nth-child(3)')?.innerText || '').replace(/"/g, '""')}"`;
            const amt = r.dataset.amount || 0;
            const status = r.dataset.status || '';
            const date = r.dataset.date || '';
            csvContent += `${name},${phone},${bankAcc},${proj},${client},${task},${amt},${status},${date}\r\n`;
          }
        });
      } else {
        csvContent += "Platform,ID Akun,Klien,Proyek,Tanggal Top-Up,Keterangan,Nominal Top-Up\r\n";
        rows.forEach(r => {
          if (r.style.display !== 'none') {
            const plat = `"${(r.dataset.platform || '').replace(/"/g, '""')}"`;
            const acc = `"${(r.querySelector('td:first-child div:last-child')?.innerText.replace('ID: ', '') || '').replace(/"/g, '""')}"`;
            const client = `"${(r.dataset.client || '').replace(/"/g, '""')}"`;
            const proj = `"${(r.dataset.project || '').replace(/"/g, '""')}"`;
            const date = r.dataset.date || '';
            const notes = `"${(r.querySelector('td:nth-child(4)')?.innerText || '').replace(/"/g, '""')}"`;
            const amt = r.dataset.amount || 0;
            csvContent += `${plat},${acc},${client},${proj},${date},${notes},${amt}\r\n`;
          }
        });
      }

      const encodedUri = encodeURI(csvContent);
      const link = document.createElement("a");
      link.setAttribute("href", encodedUri);
      link.setAttribute("download", `Pengeluaran_${activeTab}_${new Date().toISOString().slice(0,10)}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      showToast('Data pengeluaran berhasil diexport ke CSV!', 'success');
    }

    // Delete Confirmation
    function confirmDeleteExpense(type, id, name) {
      const typeLabel = (type === 'payout') ? 'Fee Freelancer' : 'Top-Up Iklan';
      showConfirmDeleteModal({
        title: `Hapus ${typeLabel}?`,
        descriptionHtml: `Apakah Anda yakin ingin menghapus data pengeluaran <strong style="color: #101828;">${name}</strong>? Tindakan ini bersifat permanen dan data yang dihapus tidak dapat dipulihkan.`,
        confirmBtnText: 'Hapus Pengeluaran',
        onConfirm: async () => {
          const formData = new FormData();
          formData.append('id', id);

          const action = (type === 'payout') ? 'delete_payout' : 'delete_ads';
          try {
            const res = await fetch(`api/expenses.php?action=${action}`, {
              method: 'POST',
              body: formData
            });
            const data = await res.json();
            if (data.success) {
              showToast(data.message, 'success');
              setTimeout(() => window.location.reload(), 600);
            } else {
              showToast(data.message || 'Gagal menghapus data', 'danger');
            }
          } catch (err) {
            showToast('Gagal menghapus data', 'danger');
          }
        }
      });
    }
  </script>
</body>
</html>

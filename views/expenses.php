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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=saas-linear-2.5">
  <?php render_tab_uid_script(); ?>
  <style>
    /* Bankio Minimal Finance Design System Extensions */
    .expenses-header-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      gap: 12px;
      flex-wrap: wrap;
    }
    .expenses-page-title {
      font-size: 18px;
      font-weight: 600;
      color: #111827;
      margin: 0;
      line-height: 1.25;
      letter-spacing: -0.02em;
    }
    .expenses-page-subtitle {
      font-size: 12.5px;
      color: #6B7280;
      margin-top: 2px;
      margin-bottom: 0;
    }
    .btn-solid-dark {
      background: #2563EB;
      color: #FFFFFF;
      border: none;
      font-weight: 500;
      font-size: 13px;
      height: 34px;
      padding: 0 14px;
      border-radius: 8px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      cursor: pointer;
      box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
      transition: all 0.15s ease;
      text-decoration: none;
    }
    .btn-solid-dark:hover {
      background: #1D4ED8;
      color: #FFFFFF;
    }
    .btn-solid-dark:active {
      transform: scale(0.98);
    }
    
    /* Level 1 Filter: Segmented Control Tabs */
    .segmented-control-container {
      background: #F3F4F6;
      border: none;
      border-radius: 8px;
      padding: 3px;
      display: inline-flex;
      gap: 2px;
      margin-bottom: 20px;
      max-width: 100%;
      overflow-x: auto;
      white-space: nowrap;
      scrollbar-width: none;
    }
    .segmented-control-container::-webkit-scrollbar {
      display: none;
    }
    .segmented-tab-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 14px;
      border-radius: 6px;
      font-size: 12.5px;
      font-weight: 500;
      color: #6B7280;
      text-decoration: none;
      transition: all 0.15s ease;
      font-family: inherit;
    }
    .segmented-tab-btn:hover:not(.active) {
      color: #111827;
      background: rgba(0, 0, 0, 0.04);
    }
    .segmented-tab-btn.active {
      background: #FFFFFF;
      color: #111827;
      font-weight: 600;
      box-shadow: 0 1px 2px rgba(16, 24, 40, 0.08);
    }

    /* Level 2: Integrated Table Toolbar & Container */
    .untitled-card {
      background: #FFFFFF;
      border: 1px solid #ECEEF5;
      border-radius: 22px;
      box-shadow: 0 10px 30px -10px rgba(18, 24, 48, 0.05), 0 2px 6px -1px rgba(18, 24, 48, 0.02);
      overflow: visible;
    }
    .expenses-select-row {
      display: contents;
    }
    @media (max-width: 768px) {
      .kpi-grid.expenses-kpi-grid {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 10px !important;
      }
      .expenses-header-row {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 10px !important;
      }
      .expenses-header-row .btn-solid-dark {
        width: 100% !important;
        height: 38px !important;
        justify-content: center !important;
        border-radius: 10px !important;
        font-weight: 700 !important;
      }
      .segmented-control-container {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        width: 100% !important;
        padding: 4px !important;
        border-radius: 12px !important;
        gap: 4px !important;
        margin-bottom: 14px !important;
      }
      .segmented-control-container .segmented-tab-btn {
        width: 100% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 8px 10px !important;
        border-radius: 9px !important;
      }
      .table-toolbar-row {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 10px !important;
        padding: 14px !important;
      }
      .toolbar-filters-group {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 10px !important;
        width: 100% !important;
      }
      .expenses-select-row {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 8px !important;
        width: 100% !important;
      }
      .search-input-wrapper {
        width: 100% !important;
        max-width: 100% !important;
      }
      .select-filter-control {
        width: 100% !important;
        min-width: 0 !important;
        font-size: 12px !important;
      }
      .btn-ghost-outlined {
        width: 100% !important;
        justify-content: center !important;
      }
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

    /* Expenses View NebulaPay Dark Mode Overrides */
    html[data-theme="dark"] .untitled-card,
    body.dark-mode .untitled-card {
      background: linear-gradient(180deg, rgba(22, 29, 47, 0.85) 0%, rgba(15, 20, 32, 0.95) 100%) !important;
      border: 1px solid rgba(255, 255, 255, 0.07) !important;
      color: #F8FAFC !important;
      box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.6) !important;
    }
    html[data-theme="dark"] .table-toolbar-row,
    body.dark-mode .table-toolbar-row {
      background: transparent !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
    }
    html[data-theme="dark"] .expenses-page-title,
    body.dark-mode .expenses-page-title {
      color: #F8FAFC !important;
    }
    html[data-theme="dark"] .expenses-page-subtitle,
    body.dark-mode .expenses-page-subtitle {
      color: #94A3B8 !important;
    }
    html[data-theme="dark"] .segmented-control-container,
    body.dark-mode .segmented-control-container {
      background: #161D2F !important;
      border: 1px solid rgba(255, 255, 255, 0.08) !important;
    }
    html[data-theme="dark"] .segmented-tab-btn,
    body.dark-mode .segmented-tab-btn {
      color: #94A3B8 !important;
    }
    html[data-theme="dark"] .segmented-tab-btn:hover:not(.active),
    body.dark-mode .segmented-tab-btn:hover:not(.active) {
      color: #F8FAFC !important;
      background: rgba(255, 255, 255, 0.06) !important;
    }
    html[data-theme="dark"] .segmented-tab-btn.active,
    body.dark-mode .segmented-tab-btn.active {
      background: #6366F1 !important;
      color: #FFFFFF !important;
      box-shadow: 0 2px 8px rgba(99, 102, 241, 0.35) !important;
    }
    html[data-theme="dark"] .search-input-control,
    html[data-theme="dark"] .select-filter-control,
    body.dark-mode .search-input-control,
    body.dark-mode .select-filter-control {
      background: #161D2F !important;
      border: 1px solid rgba(255, 255, 255, 0.12) !important;
      color: #F8FAFC !important;
    }
    html[data-theme="dark"] .btn-ghost-outlined,
    html[data-theme="dark"] .btn-action-outline,
    html[data-theme="dark"] .btn-icon-square,
    body.dark-mode .btn-ghost-outlined,
    body.dark-mode .btn-action-outline,
    body.dark-mode .btn-icon-square {
      background: #161A26 !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      color: #94A3B8 !important;
    }
    html[data-theme="dark"] .untitled-table th,
    body.dark-mode .untitled-table th {
      background: #0E121D !important;
      color: #94A3B8 !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
    }
    html[data-theme="dark"] .untitled-table td,
    body.dark-mode .untitled-table td {
      background: transparent !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
      color: #F8FAFC !important;
    }
    html[data-theme="dark"] .untitled-table tbody tr:hover td,
    body.dark-mode .untitled-table tbody tr:hover td {
      background: rgba(255, 255, 255, 0.04) !important;
    }
    html[data-theme="dark"] #empty-row td,
    body.dark-mode #empty-row td {
      background: transparent !important;
      color: #94A3B8 !important;
    }
    html[data-theme="dark"] .kala-table-scroll,
    body.dark-mode .kala-table-scroll {
      background: #12151F !important;
      border: 1px solid rgba(255, 255, 255, 0.08) !important;
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
            <a href="<?= url('expenses?_slot=internal') ?>" class="quick-subtab-btn active" style="display: flex; align-items: center; justify-content: center; padding: 8px 6px; border-radius: 8px; font-size: 12px; font-weight: 700; text-decoration: none; text-align: center; white-space: nowrap; background: #5B3DF5; color: #FFFFFF; box-shadow: 0 2px 6px rgba(91,61,245,0.25);">
              Pengeluaran
            </a>
            <a href="<?= url('salaries?_slot=internal') ?>" class="quick-subtab-btn" style="display: flex; align-items: center; justify-content: center; padding: 8px 6px; border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none; text-align: center; white-space: nowrap;">
              Penggajian
            </a>
          </div>
        </div>
        <?php endif; ?>

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

        <!-- KPI Breakdown Cards (Shopeers Style) -->
        <div class="kpi-grid expenses-kpi-grid" style="margin-bottom: 24px;">
          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Fee Freelancer (Paid)</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="kpi-value"><?= format_rupiah($totalFreelancer) ?></div>
            <div class="kpi-meta">Sudah ditransfer ke talenta</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Pending Freelancer Fee</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="kpi-value"><?= format_rupiah($totalPendingFreelancer) ?></div>
            <div class="kpi-meta">Antrean transfer belum dibayar</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Total Ads Spend (Top-Up)</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
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
        <div class="untitled-card" style="overflow: visible; min-width: 0; max-width: 100%;">
          
          <!-- Toolbar Row (Level 2 Multi-Filter Bar) -->
          <div class="table-toolbar-row">
            <div class="toolbar-filters-group">
              
              <!-- Search Input -->
              <div class="search-input-wrapper">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input 
                  type="text" 
                  id="expenses-search-input" 
                  class="search-input-control" 
                  placeholder="<?= $tab === 'freelancers' ? 'Cari nama freelancer, proyek, atau rekening...' : 'Cari platform, ID akun, klien, atau catatan...' ?>"
                  onkeyup="applyExpensesFilters()"
                >
              </div>

              <div class="expenses-select-row">
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
            <div class="kala-table-scroll table-responsive" style="width: 100%; max-width: 100%; overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; display: block !important; margin: 0; padding-bottom: 6px;">
              <table class="untitled-table" id="payout-table" style="min-width: 950px !important; width: 100%;">
                <thead>
                  <tr>
                    <th style="white-space: nowrap !important;">NAMA FREELANCER &amp; REKENING</th>
                    <th style="white-space: nowrap !important;">PROYEK &amp; KLIEN</th>
                    <th style="white-space: nowrap !important;">URAIAN PEKERJAAN</th>
                    <th style="white-space: nowrap !important;">NOMINAL FEE</th>
                    <th style="white-space: nowrap !important;">STATUS</th>
                    <th class="th-actions" style="text-align: right; white-space: nowrap !important; min-width: 250px;">BUKTI &amp; AKSI</th>
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
                        <td style="white-space: nowrap;">
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
                        <td style="white-space: nowrap;">
                          <div style="font-weight: 600; color: #101828;"><?= htmlspecialchars($p['project_name']) ?></div>
                          <div style="font-size: 11.5px; color: #667085; margin-top: 2px;"><?= htmlspecialchars($p['client_company']) ?></div>
                        </td>
                        <td style="color: #475467; max-width: 240px; line-height: 1.4; white-space: normal;">
                          <?= htmlspecialchars($p['task_description']) ?>
                        </td>
                        <td style="font-weight: 700; color: #D92D20; font-size: 13.5px; white-space: nowrap;">
                          <?= format_rupiah($p['amount']) ?>
                        </td>
                        <td style="white-space: nowrap;">
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
                        <td class="td-actions" style="text-align: right; white-space: nowrap;">
                          <div class="table-actions">
                            <!-- Invoice Button -->
                            <button type="button" class="btn-action-view" onclick="openFreelancerVoucherModal(<?= $p['id'] ?>)" title="Lihat &amp; Cetak Invoice / Voucher Fee Freelancer">
                              Invoice Fee
                            </button>

                            <!-- Struk / Upload Button -->
                            <?php if (!empty($p['receipt_file'])): ?>
                              <button type="button" class="btn-action-outline" onclick="viewReceiptImage('<?= UPLOAD_URL . '/' . htmlspecialchars($p['receipt_file']) ?>', 'Bukti Transfer <?= htmlspecialchars($p['freelancer_name']) ?>')" title="Lihat Bukti Transfer">
                                Lihat Struk
                              </button>
                            <?php else: ?>
                              <button type="button" class="btn-action-pay" onclick="triggerUploadModal('payout', <?= $p['id'] ?>, 'Upload Bukti Fee <?= htmlspecialchars($p['freelancer_name']) ?>')" title="Upload Struk Transfer">
                                + Struk
                              </button>
                            <?php endif; ?>

                            <!-- Edit Button -->
                            <button type="button" class="btn-action-edit" onclick="openEditPayoutModal(<?= $p['id'] ?>)" title="Edit Pembayaran Freelancer">
                              Edit
                            </button>

                            <!-- Delete Button (Owner Only) -->
                            <?php if (is_owner()): ?>
                              <button type="button" class="btn-action-delete btn-delete-ghost" onclick="confirmDeleteExpense('payout', <?= $p['id'] ?>, '<?= htmlspecialchars($p['freelancer_name']) ?>')" title="Hapus Fee Freelancer">
                                Hapus
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
            <div class="kala-table-scroll table-responsive" style="width: 100%; max-width: 100%; overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; display: block !important; margin: 0; padding-bottom: 6px;">
              <table class="untitled-table" id="ads-table" style="min-width: 950px !important; width: 100%;">
                <thead>
                  <tr>
                    <th style="white-space: nowrap !important;">PLATFORM &amp; ID AKUN</th>
                    <th style="white-space: nowrap !important;">KLIEN &amp; PROYEK</th>
                    <th style="white-space: nowrap !important;">TANGGAL TOP-UP</th>
                    <th style="white-space: nowrap !important;">KETERANGAN CAMPAIGN</th>
                    <th style="white-space: nowrap !important;">NOMINAL TOP-UP</th>
                    <th class="th-actions" style="text-align: right; white-space: nowrap !important; min-width: 250px;">BUKTI &amp; AKSI</th>
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
                        <td style="white-space: nowrap;">
                          <div style="font-weight: 700; color: #101828;"><?= htmlspecialchars($ad['platform']) ?></div>
                          <div style="font-size: 11.5px; color: #667085; margin-top: 2px;">ID: <?= htmlspecialchars($ad['account_id'] ?: '-') ?></div>
                        </td>
                        <td style="white-space: nowrap;">
                          <div style="font-weight: 600; color: #101828;"><?= htmlspecialchars($ad['client_company']) ?></div>
                          <div style="font-size: 11.5px; color: #667085; margin-top: 2px;"><?= htmlspecialchars($ad['project_name'] ?: '-') ?></div>
                        </td>
                        <td style="color: #475467; white-space: nowrap;"><?= format_date($ad['spent_date']) ?></td>
                        <td style="color: #475467; max-width: 240px; line-height: 1.4; white-space: normal;"><?= htmlspecialchars($ad['notes'] ?: '-') ?></td>
                        <td style="font-weight: 700; color: #D92D20; font-size: 13.5px; white-space: nowrap;">
                          <?= format_rupiah($ad['amount']) ?>
                        </td>
                        <td class="td-actions" style="text-align: right; white-space: nowrap;">
                          <div class="table-actions">
                            <!-- Invoice Ads Button -->
                            <button type="button" class="btn-action-view" onclick="openAdsVoucherModal(<?= $ad['id'] ?>)" title="Lihat &amp; Cetak Invoice / Voucher Top-Up Ads">
                              Invoice Ads
                            </button>

                            <!-- Struk Button -->
                            <?php if (!empty($ad['receipt_file'])): ?>
                              <button type="button" class="btn-action-outline" onclick="viewReceiptImage('<?= UPLOAD_URL . '/' . htmlspecialchars($ad['receipt_file']) ?>', 'Struk Top-Up <?= htmlspecialchars($ad['platform']) ?>')" title="Lihat Struk Top-Up">
                                Lihat Struk
                              </button>
                            <?php else: ?>
                              <button type="button" class="btn-action-pay" onclick="triggerUploadModal('ads', <?= $ad['id'] ?>, 'Upload Struk <?= htmlspecialchars($ad['platform']) ?>')" title="Upload Struk Top-Up">
                                + Struk
                              </button>
                            <?php endif; ?>

                            <!-- Edit Button -->
                            <button type="button" class="btn-action-edit" onclick="openEditAdsModal(<?= $ad['id'] ?>)" title="Edit Top-Up Ads">
                              Edit
                            </button>

                            <!-- Delete Button (Owner Only) -->
                            <?php if (is_owner()): ?>
                              <button type="button" class="btn-action-delete btn-delete-ghost" onclick="confirmDeleteExpense('ads', <?= $ad['id'] ?>, 'Top-Up <?= htmlspecialchars($ad['platform']) ?>')" title="Hapus Top-Up Ads">
                                Hapus
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

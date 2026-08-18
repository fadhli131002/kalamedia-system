<?php
/**
 * Kalamedia Admin & Finance Dashboard (Operational View - Untitled UI Design System)
 * Focused on Accounts Receivable (A/R), Accounts Payable (A/P) Queue & Missing Receipts Audit
 */
require_auth();
$db = Database::getConnection();

// 1. Operational Counts for Top Alert Cards
// A/R: Unpaid Invoices
$unpaidInv = $db->query("SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount), 0) as total FROM invoices WHERE status != 'Paid' AND COALESCE(is_deleted, 0) = 0")->fetch();
$unpaidCount = intval($unpaidInv['cnt'] ?? 0);
$unpaidSum = floatval($unpaidInv['total'] ?? 0);

// A/P: Pending Freelancer Payouts
$pendingPay = $db->query("SELECT COUNT(*) as cnt, COALESCE(SUM(amount), 0) as total FROM freelancer_payouts WHERE status = 'Pending' AND COALESCE(is_deleted, 0) = 0")->fetch();
$pendingPayoutCount = intval($pendingPay['cnt'] ?? 0);
$pendingPayoutSum = floatval($pendingPay['total'] ?? 0);

// A/P: Pending Employee Salaries
$pendingSal = $db->query("SELECT COUNT(*) as cnt, COALESCE(SUM(net_salary), 0) as total FROM salaries WHERE status = 'Pending' AND COALESCE(is_deleted, 0) = 0")->fetch();
$pendingSalaryCount = intval($pendingSal['cnt'] ?? 0);
$pendingSalarySum = floatval($pendingSal['total'] ?? 0);

// Missing Receipts (Paid Invoices, Payouts, Salaries, Ads missing receipt_file)
$missingInv = intval($db->query("SELECT COUNT(*) FROM invoices WHERE status = 'Paid' AND (receipt_file IS NULL OR receipt_file = '') AND COALESCE(is_deleted, 0) = 0")->fetchColumn());
$missingPay = intval($db->query("SELECT COUNT(*) FROM freelancer_payouts WHERE status = 'Paid' AND (receipt_file IS NULL OR receipt_file = '') AND COALESCE(is_deleted, 0) = 0")->fetchColumn());
$missingSal = intval($db->query("SELECT COUNT(*) FROM salaries WHERE status = 'Paid' AND (receipt_file IS NULL OR receipt_file = '') AND COALESCE(is_deleted, 0) = 0")->fetchColumn());
$missingAds = intval($db->query("SELECT COUNT(*) FROM ads_spend WHERE (receipt_file IS NULL OR receipt_file = '') AND COALESCE(is_deleted, 0) = 0")->fetchColumn());
$totalMissingReceipts = $missingInv + $missingPay + $missingSal + $missingAds;

// 2. Fetch Active Queue (Combined recent active transactions)
$transactions = [];

// Invoices
$invoices = $db->query("
    SELECT i.id, i.invoice_number, i.total_amount, i.status, i.issue_date, i.due_date, i.receipt_file,
           c.company as client_company, 'Inflow (Invoice)' as trans_type, 'invoice' as target_type
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    WHERE COALESCE(i.is_deleted, 0) = 0
    ORDER BY i.id DESC LIMIT 15
")->fetchAll();
foreach ($invoices as $inv) {
    $transactions[] = [
        'id' => $inv['id'],
        'target_type' => 'invoice',
        'trans_type' => 'Inflow',
        'date' => $inv['issue_date'],
        'due_date' => $inv['due_date'],
        'title' => "Invoice #" . $inv['invoice_number'],
        'subtitle' => $inv['client_company'],
        'amount' => floatval($inv['total_amount']),
        'status' => $inv['status'],
        'receipt_file' => $inv['receipt_file']
    ];
}

// Freelancer Payouts
$payouts = $db->query("
    SELECT p.id, p.freelancer_name, p.freelancer_bank, p.freelancer_account, p.amount, p.status, 
           p.task_description, p.created_at as trans_date, p.receipt_file,
           pr.name as project_name, 'Outflow (Freelancer)' as trans_type, 'payout' as target_type
    FROM freelancer_payouts p
    LEFT JOIN projects pr ON p.project_id = pr.id
    WHERE COALESCE(p.is_deleted, 0) = 0
    ORDER BY p.id DESC LIMIT 15
")->fetchAll();
foreach ($payouts as $pay) {
    $transactions[] = [
        'id' => $pay['id'],
        'target_type' => 'payout',
        'trans_type' => 'Outflow',
        'date' => date('Y-m-d', strtotime($pay['trans_date'])),
        'title' => "Fee: " . $pay['freelancer_name'],
        'subtitle' => $pay['task_description'] . " (" . $pay['project_name'] . ")",
        'bank_info' => ($pay['freelancer_bank'] ? $pay['freelancer_bank'] . " - " . $pay['freelancer_account'] : ""),
        'amount' => floatval($pay['amount']),
        'status' => $pay['status'],
        'receipt_file' => $pay['receipt_file']
    ];
}

// Employee Salaries (Payroll)
$sals = $db->query("
    SELECT s.id, s.employee_name, s.employee_position, s.net_salary, s.status, s.payment_date as trans_date, s.receipt_file, s.month_period, s.bank_name, s.bank_account,
           'Outflow (Gaji Karyawan)' as trans_type, 'salary' as target_type
    FROM salaries s
    WHERE COALESCE(s.is_deleted, 0) = 0
    ORDER BY s.id DESC LIMIT 15
")->fetchAll();
foreach ($sals as $sal) {
    $transactions[] = [
        'id' => $sal['id'],
        'target_type' => 'salary',
        'trans_type' => 'Outflow',
        'date' => $sal['trans_date'],
        'title' => "Gaji: " . $sal['employee_name'],
        'subtitle' => ($sal['employee_position'] ?: 'Karyawan') . " &bull; Periode " . $sal['month_period'],
        'bank_info' => ($sal['bank_name'] ? $sal['bank_name'] . " - " . $sal['bank_account'] : ""),
        'amount' => floatval($sal['net_salary']),
        'status' => $sal['status'],
        'receipt_file' => $sal['receipt_file']
    ];
}

// Ads Spend
$adsList = $db->query("
    SELECT a.id, a.platform, a.amount, a.spent_date, a.receipt_file, a.account_id, a.notes,
           c.company as client_company, 'Outflow (Ads)' as trans_type, 'ads' as target_type
    FROM ads_spend a
    JOIN clients c ON a.client_id = c.id
    WHERE COALESCE(a.is_deleted, 0) = 0
    ORDER BY a.id DESC LIMIT 15
")->fetchAll();
foreach ($adsList as $ad) {
    $transactions[] = [
        'id' => $ad['id'],
        'target_type' => 'ads',
        'trans_type' => 'Outflow',
        'date' => $ad['spent_date'],
        'title' => "Top-Up " . $ad['platform'],
        'subtitle' => $ad['client_company'] . ($ad['account_id'] ? " ({$ad['account_id']})" : "") . ($ad['notes'] ? " - {$ad['notes']}" : ""),
        'amount' => floatval($ad['amount']),
        'status' => 'Paid',
        'receipt_file' => $ad['receipt_file']
    ];
}

// Sort combined transactions by date DESC
usort($transactions, function ($a, $b) {
    return strcmp($b['date'], $a['date']);
});

// 3. Filter specific lists for AR, AP, and Missing Receipts
$arInvoices = array_filter($transactions, fn($t) => $t['target_type'] === 'invoice' && $t['status'] !== 'Paid');
$apOutflows = array_filter($transactions, fn($t) => $t['trans_type'] === 'Outflow' && $t['status'] === 'Pending');
$missingReceiptItems = array_filter($transactions, fn($t) => empty($t['receipt_file']) && $t['status'] === 'Paid');
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin & Finance Dashboard - Kalamedia</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body">

        <!-- Header Actions: Quick Actions + Excel/CSV/PDF Export -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
          <div>
            <h2 style="font-size: 18px; font-weight: 800; color: #101828; margin: 0; letter-spacing: -0.2px;">Operational Reports & Antrean Transaksi</h2>
            <p style="font-size: 12px; color: var(--text-secondary); margin: 3px 0 0 0;">Kelola tagihan piutang, antrean hutang operasional, dan rekonsiliasi kas</p>
          </div>
          <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="api/analytics.php?action=export_excel" class="btn btn-secondary btn-sm" style="font-weight: 700;" title="Unduh spreadsheet Excel dengan format tabel & border rapi">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="8" y1="13" x2="16" y2="13"></line><line x1="8" y1="17" x2="16" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
              <span>Export Excel (.xls)</span>
            </a>
            <a href="api/analytics.php?action=export_csv" class="btn btn-secondary btn-sm" style="font-weight: 700;" title="Unduh spreadsheet CSV dengan pemisah kolom otomatis">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
              <span>Export CSV</span>
            </a>
            <a href="api/analytics.php?action=export_pdf" target="_blank" class="btn btn-primary btn-sm" style="font-weight: 700;" title="Buka & cetak laporan rekonsiliasi bulanan format PDF">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
              <span>Cetak Laporan PDF</span>
            </a>
          </div>
        </div>

        <!-- 1. Quick Action Cards (PRD 3.3 + Gaji Karyawan) -->
        <div class="quick-actions-bar">
          <div class="quick-action-card" onclick="openModal('modal-create-invoice')">
            <div class="action-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>
            </div>
            <div>
              <div class="action-title">+ Buat Invoice</div>
              <div class="action-desc">Penagihan baru ke klien</div>
            </div>
          </div>

          <div class="quick-action-card" onclick="openModal('modal-record-payment')">
            <div class="action-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            </div>
            <div>
              <div class="action-title">+ Catat Uang Masuk</div>
              <div class="action-desc">Pelunasan invoice klien</div>
            </div>
          </div>

          <div class="quick-action-card" onclick="openModal('modal-input-payout')">
            <div class="action-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" y1="8" x2="19" y2="14"></line><line x1="22" y1="11" x2="16" y2="11"></line></svg>
            </div>
            <div>
              <div class="action-title">+ Fee Freelancer</div>
              <div class="action-desc">Input pembayaran talenta</div>
            </div>
          </div>

          <div class="quick-action-card" onclick="openModal('modal-input-salary')">
            <div class="action-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="M7 15h0M2 9.5h20"></path><circle cx="16" cy="14" r="2"></circle></svg>
            </div>
            <div>
              <div class="action-title">+ Gaji Karyawan</div>
              <div class="action-desc">Payroll & slip gaji tim</div>
            </div>
          </div>

          <div class="quick-action-card" onclick="openModal('modal-catat-ads')">
            <div class="action-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            </div>
            <div>
              <div class="action-title">+ Top-Up Ads</div>
              <div class="action-desc">Meta, Google & TikTok Ads</div>
            </div>
          </div>
        </div>

        <!-- 2. Attention Required (Alert Cards - PRD 3.3) -->
        <div class="alert-cards-grid">
          <!-- Unpaid Invoices -->
          <div class="alert-card danger-border" onclick="switchOpTab('tab-ar')" style="cursor: pointer;">
            <div style="width: 36px; height: 36px; border-radius: 8px; background: var(--danger-bg); color: var(--danger-text); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            </div>
            <div class="alert-card-info">
              <h5>Accounts Receivable (A/R)</h5>
              <div class="count"><?= $unpaidCount ?> Invoice Belum Lunas</div>
              <div class="subtext">Total: <?= format_rupiah($unpaidSum) ?></div>
            </div>
          </div>

          <!-- Pending Freelancer Payouts -->
          <div class="alert-card warning-border" onclick="switchOpTab('tab-ap')" style="cursor: pointer;">
            <div style="width: 36px; height: 36px; border-radius: 8px; background: var(--warning-bg); color: var(--warning-text); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            </div>
            <div class="alert-card-info">
              <h5>Pending Freelancers (A/P)</h5>
              <div class="count"><?= $pendingPayoutCount ?> Talenta</div>
              <div class="subtext">Total: <?= format_rupiah($pendingPayoutSum) ?></div>
            </div>
          </div>

          <!-- Pending Employee Salaries -->
          <div class="alert-card" onclick="switchOpTab('tab-ap')" style="cursor: pointer; border-left: 4px solid #101828;">
            <div style="width: 36px; height: 36px; border-radius: 8px; background: #F2F4F7; color: #101828; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="M7 15h0M2 9.5h20"></path><circle cx="16" cy="14" r="2"></circle></svg>
            </div>
            <div class="alert-card-info">
              <h5>Pending Gaji Tim (A/P)</h5>
              <div class="count"><?= $pendingSalaryCount ?> Karyawan</div>
              <div class="subtext">Total: <?= format_rupiah($pendingSalarySum) ?></div>
            </div>
          </div>

          <!-- Missing Receipts -->
          <div class="alert-card info-border" onclick="switchOpTab('tab-missing')" style="cursor: pointer;">
            <div style="width: 36px; height: 36px; border-radius: 8px; background: var(--info-bg); color: var(--info-text); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
            </div>
            <div class="alert-card-info">
              <h5>Missing Receipt Audit</h5>
              <div class="count"><?= $totalMissingReceipts ?> Transaksi</div>
              <div class="subtext">Perlu unggah bukti transfer</div>
            </div>
          </div>
        </div>

        <!-- 3. Tab Navigation for Operational Reporting -->
        <div style="display: flex; gap: 8px; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; flex-wrap: wrap;">
          <button type="button" class="tab-btn active" id="btn-tab-all" onclick="switchOpTab('tab-all')">Semua Transaksi (Active Queue)</button>
          <button type="button" class="tab-btn" id="btn-tab-ar" onclick="switchOpTab('tab-ar')">Accounts Receivable (Invoice Belum Lunas)</button>
          <button type="button" class="tab-btn" id="btn-tab-ap" onclick="switchOpTab('tab-ap')">Accounts Payable (Antrean Pembayaran)</button>
          <button type="button" class="tab-btn" id="btn-tab-missing" onclick="switchOpTab('tab-missing')">Audit Bukti Transfer (Missing Receipts)</button>
        </div>

        <!-- 4. Active Queue & Tabbed Tables Container -->
        <div class="glass-panel">
          <div class="panel-header">
            <div>
              <h3 class="panel-title" id="tab-panel-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                Tabel Antrean Transaksi Berjalan (Active Queue)
              </h3>
              <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;" id="tab-panel-desc">Daftar transaksi mutasi operasional real-time</p>
            </div>

            <!-- Fast Search & Filter Bar -->
            <div style="display: flex; gap: 10px; align-items: center;">
              <input type="text" id="queue-search-input" class="form-control" style="width: 260px; padding: 7px 12px; font-size: 12.5px;" placeholder="Cari invoice, klien, freelancer...">
            </div>
          </div>

          <!-- Tab 1: All Active Queue -->
          <div id="section-tab-all" class="table-responsive">
            <table class="table-custom" id="queue-table">
              <thead>
                <tr>
                  <th>Tanggal</th>
                  <th>Tipe</th>
                  <th>Deskripsi Transaksi & Entity</th>
                  <th>Nominal (Rp)</th>
                  <th>Status</th>
                  <th style="text-align: right;">Aksi Bukti Transfer</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($transactions)): ?>
                  <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">Belum ada transaksi dalam antrean.</td></tr>
                <?php else: ?>
                  <?php foreach ($transactions as $t): ?>
                    <tr>
                      <td style="color: var(--text-secondary);"><?= format_date($t['date']) ?></td>
                      <td>
                        <span class="badge-type-<?= $t['trans_type'] === 'Inflow' ? 'in' : 'out' ?>">
                          <?= $t['trans_type'] === 'Inflow' ? '+ Inflow' : '- Outflow' ?>
                        </span>
                      </td>
                      <td>
                        <div style="font-weight: 700; color: #101828;"><?= htmlspecialchars($t['title']) ?></div>
                        <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($t['subtitle']) ?></div>
                      </td>
                      <td style="font-weight: 700; color: <?= $t['trans_type'] === 'Inflow' ? 'var(--success-text)' : 'var(--danger-text)' ?>;">
                        <?= format_rupiah($t['amount']) ?>
                      </td>
                      <td>
                        <span class="badge-status badge-<?= strtolower($t['status']) ?>">
                          <?= $t['status'] ?>
                        </span>
                      </td>
                      <td style="text-align: right;">
                        <?php if (!empty($t['receipt_file'])): ?>
                          <button type="button" class="btn btn-secondary btn-sm" onclick="viewReceiptImage('<?= UPLOAD_URL . '/' . htmlspecialchars($t['receipt_file']) ?>', '<?= htmlspecialchars($t['title']) ?>')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="3"></circle></svg>
                            <span>Lihat Bukti</span>
                          </button>
                        <?php else: ?>
                          <button type="button" class="btn btn-secondary btn-sm" onclick="triggerUploadModal('<?= $t['target_type'] ?>', <?= $t['id'] ?>, 'Upload Bukti <?= htmlspecialchars($t['title']) ?>')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                            <span>Upload Bukti</span>
                          </button>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Tab 2: Accounts Receivable (Unpaid Invoices) -->
          <div id="section-tab-ar" class="table-responsive" style="display: none;">
            <table class="table-custom">
              <thead>
                <tr>
                  <th>No. Invoice & Klien</th>
                  <th>Tgl Tagihan</th>
                  <th>Jatuh Tempo</th>
                  <th>Nominal Tagihan</th>
                  <th>Status Penagihan</th>
                  <th style="text-align: right;">Aksi Pelunasan</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($arInvoices)): ?>
                  <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">Tidak ada tagihan tertunda. Semua invoice telah dilunasi! 🎉</td></tr>
                <?php else: ?>
                  <?php foreach ($arInvoices as $ar): 
                    $isOverdue = (strtotime($ar['due_date']) < time() && $ar['status'] !== 'Paid');
                  ?>
                    <tr>
                      <td>
                        <div style="font-weight: 700; color: #101828;"><?= htmlspecialchars($ar['title']) ?></div>
                        <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($ar['subtitle']) ?></div>
                      </td>
                      <td style="color: var(--text-secondary);"><?= format_date($ar['date']) ?></td>
                      <td style="color: <?= $isOverdue ? 'var(--danger-text)' : 'var(--text-secondary)' ?>; font-weight: <?= $isOverdue ? '700' : 'normal' ?>;">
                        <?= format_date($ar['due_date']) ?>
                        <?php if ($isOverdue): ?>
                          <span style="font-size: 10px; color: var(--danger-text); display: block;">Jatuh Tempo!</span>
                        <?php endif; ?>
                      </td>
                      <td style="font-weight: 800; color: #101828;"><?= format_rupiah($ar['amount']) ?></td>
                      <td>
                        <span class="badge-status badge-<?= $isOverdue ? 'overdue' : strtolower($ar['status']) ?>">
                          <?= $isOverdue ? 'Overdue' : $ar['status'] ?>
                        </span>
                      </td>
                      <td style="text-align: right; display: flex; gap: 6px; justify-content: flex-end;">
                        <a href="index.php?view=invoice_view&id=<?= $ar['id'] ?>" class="btn btn-secondary btn-sm" title="Buka invoice dan share WA">
                          <span>Buka Invoice</span>
                        </a>
                        <button type="button" class="btn btn-primary btn-sm" onclick="triggerUploadModal('invoice', <?= $ar['id'] ?>, 'Pelunasan <?= htmlspecialchars($ar['title']) ?>')">
                          <span>+ Catat Lunas</span>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Tab 3: Accounts Payable (Pending Outflow) -->
          <div id="section-tab-ap" class="table-responsive" style="display: none;">
            <table class="table-custom">
              <thead>
                <tr>
                  <th>Nama Penerima</th>
                  <th>Kategori Pengeluaran</th>
                  <th>Keterangan Proyek</th>
                  <th>Info Rekening Bank</th>
                  <th>Nominal Bayar</th>
                  <th style="text-align: right;">Aksi Pembayaran</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($apOutflows)): ?>
                  <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">Tidak ada antrean pembayaran hutang operasional!</td></tr>
                <?php else: ?>
                  <?php foreach ($apOutflows as $ap): ?>
                    <tr>
                      <td>
                        <div style="font-weight: 700; color: #101828;"><?= htmlspecialchars($ap['title']) ?></div>
                      </td>
                      <td>
                        <span class="badge-type-out"><?= htmlspecialchars($ap['target_type'] === 'payout' ? 'Fee Freelancer' : 'Gaji Karyawan') ?></span>
                      </td>
                      <td>
                        <div style="font-size: 12px; color: var(--text-secondary);"><?= htmlspecialchars($ap['subtitle']) ?></div>
                      </td>
                      <td style="font-size: 12px; color: var(--text-secondary); font-weight: 600;">
                        <?= htmlspecialchars($ap['bank_info'] ?: '-') ?>
                      </td>
                      <td style="font-weight: 800; color: var(--danger-text);"><?= format_rupiah($ap['amount']) ?></td>
                      <td style="text-align: right;">
                        <button type="button" class="btn btn-primary btn-sm" onclick="triggerUploadModal('<?= $ap['target_type'] ?>', <?= $ap['id'] ?>, 'Upload Bukti Transfer <?= htmlspecialchars($ap['title']) ?>')">
                          <span>+ Upload Bukti Transfer</span>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Tab 4: Missing Receipt Audit -->
          <div id="section-tab-missing" class="table-responsive" style="display: none;">
            <table class="table-custom">
              <thead>
                <tr>
                  <th>Tanggal</th>
                  <th>Tipe Mutasi</th>
                  <th>Deskripsi & Entitas</th>
                  <th>Nominal</th>
                  <th>Status Audit Bukti</th>
                  <th style="text-align: right;">Tindakan</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($missingReceiptItems)): ?>
                  <tr><td colspan="6" style="text-align: center; color: var(--success-text); font-weight: 600; padding: 24px;">Audit Sempurna! Semua transaksi lunas memiliki bukti transfer. 🎉</td></tr>
                <?php else: ?>
                  <?php foreach ($missingReceiptItems as $m): ?>
                    <tr>
                      <td style="color: var(--text-secondary);"><?= format_date($m['date']) ?></td>
                      <td>
                        <span class="badge-type-<?= $m['trans_type'] === 'Inflow' ? 'in' : 'out' ?>">
                          <?= $m['trans_type'] ?>
                        </span>
                      </td>
                      <td>
                        <div style="font-weight: 700; color: #101828;"><?= htmlspecialchars($m['title']) ?></div>
                        <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($m['subtitle']) ?></div>
                      </td>
                      <td style="font-weight: 700; color: #101828;"><?= format_rupiah($m['amount']) ?></td>
                      <td>
                        <span class="badge-status badge-overdue">
                          Missing Receipt
                        </span>
                      </td>
                      <td style="text-align: right;">
                        <button type="button" class="btn btn-primary btn-sm" onclick="triggerUploadModal('<?= $m['target_type'] ?>', <?= $m['id'] ?>, 'Upload Bukti <?= htmlspecialchars($m['title']) ?>')">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                          <span>Upload Bukti Struk</span>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

        </div>

  <?php require_once BASE_PATH . '/includes/footer.php'; ?>

  <script>
    function switchOpTab(tabId) {
      // Toggle button classes
      document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
      const activeBtn = document.getElementById('btn-' + tabId);
      if (activeBtn) activeBtn.classList.add('active');

      // Hide all sections
      ['tab-all', 'tab-ar', 'tab-ap', 'tab-missing'].forEach(id => {
        const sec = document.getElementById('section-' + id);
        if (sec) sec.style.display = 'none';
      });

      // Show target section & update title
      const targetSec = document.getElementById('section-' + tabId);
      if (targetSec) targetSec.style.display = 'block';

      const titleEl = document.getElementById('tab-panel-title');
      const descEl = document.getElementById('tab-panel-desc');

      if (tabId === 'tab-all') {
        titleEl.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg> Tabel Antrean Transaksi Berjalan (Active Queue)`;
        descEl.textContent = 'Daftar transaksi mutasi operasional real-time';
      } else if (tabId === 'tab-ar') {
        titleEl.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Accounts Receivable (Daftar Invoice Belum Lunas)`;
        descEl.textContent = 'Monitoring invoice terkirim dan jatuh tempo yang menunggu pembayaran klien';
      } else if (tabId === 'tab-ap') {
        titleEl.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg> Accounts Payable (Antrean Pembayaran Outflow)`;
        descEl.textContent = 'Daftar fee freelancer dan gaji tim yang menunggu transfer kas keluar';
      } else if (tabId === 'tab-missing') {
        titleEl.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle></svg> Audit Bukti Transfer (Missing Receipts)`;
        descEl.textContent = 'Daftar transaksi lunas yang belum memiliki lampiran struk/bukti transfer';
      }
    }

    document.addEventListener('DOMContentLoaded', () => {
      initTableSearch('queue-search-input', 'queue-table');
    });
  </script>
</body>
</html>

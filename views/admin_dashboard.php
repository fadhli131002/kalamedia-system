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
  <title>Admin & Finance Dashboard - PT Kala Kausa Prima</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=saas-linear-1.0">
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
            <h2 style="font-size: 18px; font-weight: 600; color: #111827; margin: 0; letter-spacing: -0.02em;">Operational Reports & Antrean Transaksi</h2>
            <p style="font-size: 12.5px; color: #6B7280; margin: 3px 0 0 0;">Kelola tagihan piutang, antrean hutang operasional, dan rekonsiliasi kas</p>
          </div>
          <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="api/analytics.php?action=export_excel" class="btn btn-secondary btn-sm" style="font-weight: 500; height: 34px; padding: 0 12px; border-radius: 8px;" title="Unduh spreadsheet Excel dengan format tabel & border rapi">
              <span>Export Excel (.xls)</span>
            </a>
            <a href="api/analytics.php?action=export_csv" class="btn btn-secondary btn-sm" style="font-weight: 500; height: 34px; padding: 0 12px; border-radius: 8px;" title="Unduh spreadsheet CSV dengan pemisah kolom otomatis">
              <span>Export CSV</span>
            </a>
            <a href="api/analytics.php?action=export_pdf" target="_blank" class="btn btn-primary btn-sm" style="font-weight: 500; height: 34px; padding: 0 14px; border-radius: 8px;" title="Buka & cetak laporan rekonsiliasi bulanan format PDF">
              <span>Cetak Laporan PDF</span>
            </a>
          </div>
        </div>

        <!-- 1. Quick Action Cards -->
        <div class="quick-actions-bar">
          <div class="quick-action-card" onclick="openModal('modal-create-invoice')">
            <div>
              <div class="action-title">+ Buat Invoice</div>
              <div class="action-desc">Penagihan baru ke klien</div>
            </div>
          </div>

          <div class="quick-action-card" onclick="openModal('modal-record-payment')">
            <div>
              <div class="action-title">+ Catat Uang Masuk</div>
              <div class="action-desc">Pelunasan invoice klien</div>
            </div>
          </div>

          <div class="quick-action-card" onclick="openModal('modal-input-payout')">
            <div>
              <div class="action-title">+ Fee Freelancer</div>
              <div class="action-desc">Input pembayaran talenta</div>
            </div>
          </div>

          <div class="quick-action-card" onclick="openModal('modal-input-salary')">
            <div>
              <div class="action-title">+ Gaji Karyawan</div>
              <div class="action-desc">Payroll & slip gaji tim</div>
            </div>
          </div>

          <div class="quick-action-card" onclick="openModal('modal-catat-ads')">
            <div>
              <div class="action-title">+ Top-Up Ads</div>
              <div class="action-desc">Meta, Google & TikTok Ads</div>
            </div>
          </div>
        </div>

        <!-- 2. Operational Metrics Cards (Shopeers Style) -->
        <div class="kpi-grid">
          <!-- Unpaid Invoices -->
          <div class="kpi-card" onclick="switchOpTab('tab-ar')" style="cursor: pointer;">
            <div class="kpi-header">
              <span class="kpi-title">Accounts Receivable (A/R)</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            </div>
            <div class="kpi-value"><?= $unpaidCount ?></div>
            <div class="kpi-meta">Total: <?= format_rupiah($unpaidSum) ?></div>
          </div>

          <!-- Pending Freelancer Payouts -->
          <div class="kpi-card" onclick="switchOpTab('tab-ap')" style="cursor: pointer;">
            <div class="kpi-header">
              <span class="kpi-title">Pending Freelancers (A/P)</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="kpi-value"><?= $pendingPayoutCount ?></div>
            <div class="kpi-meta">Total: <?= format_rupiah($pendingPayoutSum) ?></div>
          </div>

          <!-- Pending Employee Salaries -->
          <div class="kpi-card" onclick="switchOpTab('tab-ap')" style="cursor: pointer;">
            <div class="kpi-header">
              <span class="kpi-title">Pending Gaji Tim (A/P)</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="kpi-value"><?= $pendingSalaryCount ?></div>
            <div class="kpi-meta">Total: <?= format_rupiah($pendingSalarySum) ?></div>
          </div>

          <!-- Missing Receipts -->
          <div class="kpi-card" onclick="switchOpTab('tab-missing')" style="cursor: pointer;">
            <div class="kpi-header">
              <span class="kpi-title">Missing Receipt Audit</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <div class="kpi-value"><?= $totalMissingReceipts ?></div>
            <div class="kpi-meta">Perlu unggah bukti transfer</div>
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
                            <span>Lihat Bukti</span>
                          </button>
                        <?php else: ?>
                          <button type="button" class="btn btn-secondary btn-sm" onclick="triggerUploadModal('<?= $t['target_type'] ?>', <?= $t['id'] ?>, 'Upload Bukti <?= htmlspecialchars($t['title']) ?>')">
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
                  <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">Tidak ada tagihan tertunda. Semua invoice telah dilunasi!</td></tr>
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
                  <tr><td colspan="6" style="text-align: center; color: var(--success-text); font-weight: 600; padding: 24px;">Audit Sempurna! Semua transaksi lunas memiliki bukti transfer.</td></tr>
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
        titleEl.textContent = 'Tabel Antrean Transaksi Berjalan (Active Queue)';
        descEl.textContent = 'Daftar transaksi mutasi operasional real-time';
      } else if (tabId === 'tab-ar') {
        titleEl.textContent = 'Accounts Receivable (Daftar Invoice Belum Lunas)';
        descEl.textContent = 'Monitoring invoice terkirim dan jatuh tempo yang menunggu pembayaran klien';
      } else if (tabId === 'tab-ap') {
        titleEl.textContent = 'Accounts Payable (Antrean Pembayaran Outflow)';
        descEl.textContent = 'Daftar fee freelancer dan gaji tim yang menunggu transfer kas keluar';
      } else if (tabId === 'tab-missing') {
        titleEl.textContent = 'Audit Bukti Transfer (Missing Receipts)';
        descEl.textContent = 'Daftar transaksi lunas yang belum memiliki lampiran struk/bukti transfer';
      }
    }

    document.addEventListener('DOMContentLoaded', () => {
      initTableSearch('queue-search-input', 'queue-table');
    });
  </script>
</body>
</html>

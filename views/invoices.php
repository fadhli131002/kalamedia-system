<?php
/**
 * Kalamedia Invoices List View (Untitled UI Design System)
 */
require_auth();
$db = Database::getConnection();

$filterStatus = $_GET['status'] ?? 'all';
$isClientUser = is_client();
$clientId = current_user_client_id();

$whereClause = "COALESCE(i.is_deleted, 0) = 0";
if ($isClientUser && $clientId) {
    $whereClause .= " AND i.client_id = " . intval($clientId);
}
if ($filterStatus !== 'all') {
    $whereClause .= " AND i.status = " . $db->quote($filterStatus);
}

$invoices = $db->query("
    SELECT i.*, c.name as client_name, c.company as client_company, c.phone as client_phone, p.name as project_name,
           COALESCE(p_sum.total_paid, 0) as paid_amount,
           COALESCE(p_sum.payments_count, 0) as payments_count
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    LEFT JOIN projects p ON i.project_id = p.id
    LEFT JOIN (
        SELECT invoice_id, SUM(amount) as total_paid, COUNT(id) as payments_count
        FROM invoice_payments
        WHERE COALESCE(is_deleted, 0) = 0
        GROUP BY invoice_id
    ) p_sum ON p_sum.invoice_id = i.id
    WHERE $whereClause
    ORDER BY i.id DESC
")->fetchAll();

// Statistics (Scoped if client)
$statClientWhere = ($isClientUser && $clientId) ? " AND client_id = " . intval($clientId) : "";
$totalBilled = floatval($db->query("SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE COALESCE(is_deleted, 0) = 0" . $statClientWhere)->fetchColumn());

// Calculate total actual cash paid (including partial installments)
$paySumQuery = "
    SELECT COALESCE(SUM(p.amount), 0) 
    FROM invoice_payments p 
    JOIN invoices i ON p.invoice_id = i.id 
    WHERE COALESCE(p.is_deleted, 0) = 0 AND COALESCE(i.is_deleted, 0) = 0
" . (($isClientUser && $clientId) ? " AND i.client_id = " . intval($clientId) : "");
$totalPaid = floatval($db->query($paySumQuery)->fetchColumn());
$totalUnpaid = max(0, $totalBilled - $totalPaid);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manajemen Invoice - PT Kala Kausa Prima</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=saas-linear-2.5">
  <?php render_tab_uid_script(); ?>
  <style>
    .invoices-search-wrap {
      position: relative;
      width: 300px;
      max-width: 100%;
    }
    .invoices-search-wrap svg {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #667085;
      pointer-events: none;
    }
    .invoices-search-wrap .form-control {
      width: 100% !important;
      padding-left: 36px !important;
      height: 38px !important;
      font-size: 13px !important;
      border-radius: 10px !important;
      box-sizing: border-box !important;
    }
    @media (max-width: 768px) {
      .invoices-toolbar-row {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 10px !important;
        margin-bottom: 14px !important;
      }
      .invoices-toolbar-row .date-filter-group {
        display: grid !important;
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        width: 100% !important;
        padding: 4px !important;
        border-radius: 12px !important;
        gap: 4px !important;
      }
      .invoices-toolbar-row .date-filter-group .filter-btn {
        width: 100% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        text-align: center !important;
        padding: 8px 6px !important;
        border-radius: 9px !important;
        font-size: 12.5px !important;
      }
      .invoices-search-wrap {
        width: 100% !important;
      }
    }

    /* Accordion Payments Panel Dark Mode & Light Mode Theme Support */
    .inv-terms-card {
      background: #FFFFFF;
      border: 1px solid #E4E7EC;
      border-radius: 12px;
      padding: 18px 20px;
      box-shadow: 0 1px 3px rgba(16,24,40,0.05);
    }
    .inv-terms-header-border {
      border-bottom: 1px solid #F2F4F7;
    }
    .inv-terms-title {
      color: #101828;
    }
    .inv-terms-subtitle {
      color: #667085;
    }
    .inv-terms-thead {
      background: #F9FAFB;
      border-bottom: 1px solid #EAECF0;
      color: #475467;
    }
    .inv-terms-row-border {
      border-bottom: 1px solid #F2F4F7;
    }
    .inv-terms-num-badge {
      background: #F2F4F7;
      color: #475467;
    }
    .inv-terms-name {
      color: #101828;
    }
    .inv-terms-text {
      color: #475467;
    }
    .inv-terms-method-badge {
      background: #F1F5F9;
      color: #334155;
      border: 1px solid #CBD5E1;
      border-radius: 6px;
      padding: 3px 8px;
      font-size: 11.5px;
      font-weight: 600;
      display: inline-block;
    }
    .inv-terms-stat-received {
      background: #ECFDF3;
      border: 1px solid #A6F4C5;
      color: #027A48;
      padding: 4px 10px;
      border-radius: 6px;
      font-size: 11.5px;
    }
    .inv-terms-stat-remaining-due {
      background: #FEF3F2;
      border: 1px solid #FECDCA;
      color: #B42318;
      padding: 4px 10px;
      border-radius: 6px;
      font-size: 11.5px;
    }
    .inv-terms-stat-remaining-zero {
      background: #F2F4F7;
      border: 1px solid #E4E7EC;
      color: #344054;
      padding: 4px 10px;
      border-radius: 6px;
      font-size: 11.5px;
    }
    .inv-terms-btn-receipt {
      font-size: 11px;
      padding: 3px 8px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      color: #1D4ED8;
      border: 1px solid #BFDBFE;
      background: #EFF6FF;
      border-radius: 6px;
      cursor: pointer;
    }

    /* DARK MODE OVERRIDES */
    html[data-theme="dark"] .invoice-terms-row,
    body.dark-mode .invoice-terms-row,
    html.dark-mode .invoice-terms-row {
      background: #0B0E14 !important;
    }
    html[data-theme="dark"] .inv-terms-card,
    body.dark-mode .inv-terms-card,
    html.dark-mode .inv-terms-card {
      background: #111622 !important;
      border-color: rgba(255, 255, 255, 0.08) !important;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4) !important;
    }
    html[data-theme="dark"] .inv-terms-header-border,
    body.dark-mode .inv-terms-header-border,
    html.dark-mode .inv-terms-header-border {
      border-bottom-color: rgba(255, 255, 255, 0.08) !important;
    }
    html[data-theme="dark"] .inv-terms-title,
    body.dark-mode .inv-terms-title,
    html.dark-mode .inv-terms-title {
      color: #F8FAFC !important;
    }
    html[data-theme="dark"] .inv-terms-subtitle,
    body.dark-mode .inv-terms-subtitle,
    html.dark-mode .inv-terms-subtitle {
      color: #94A3B8 !important;
    }
    html[data-theme="dark"] .inv-terms-thead,
    body.dark-mode .inv-terms-thead,
    html.dark-mode .inv-terms-thead {
      background: #161D2C !important;
      border-bottom-color: rgba(255, 255, 255, 0.08) !important;
      color: #94A3B8 !important;
    }
    html[data-theme="dark"] .inv-terms-row-border,
    body.dark-mode .inv-terms-row-border,
    html.dark-mode .inv-terms-row-border {
      border-bottom-color: rgba(255, 255, 255, 0.06) !important;
    }
    html[data-theme="dark"] .inv-terms-num-badge,
    body.dark-mode .inv-terms-num-badge,
    html.dark-mode .inv-terms-num-badge {
      background: #1E293B !important;
      color: #CBD5E1 !important;
    }
    html[data-theme="dark"] .inv-terms-name,
    body.dark-mode .inv-terms-name,
    html.dark-mode .inv-terms-name {
      color: #F8FAFC !important;
    }
    html[data-theme="dark"] .inv-terms-text,
    body.dark-mode .inv-terms-text,
    html.dark-mode .inv-terms-text {
      color: #CBD5E1 !important;
    }
    html[data-theme="dark"] .inv-terms-method-badge,
    body.dark-mode .inv-terms-method-badge,
    html.dark-mode .inv-terms-method-badge {
      background: #1E293B !important;
      color: #E2E8F0 !important;
      border-color: #334155 !important;
    }
    html[data-theme="dark"] .inv-terms-stat-received,
    body.dark-mode .inv-terms-stat-received,
    html.dark-mode .inv-terms-stat-received {
      background: rgba(18, 183, 106, 0.15) !important;
      border-color: rgba(18, 183, 106, 0.35) !important;
      color: #34D399 !important;
    }
    html[data-theme="dark"] .inv-terms-stat-received span,
    html[data-theme="dark"] .inv-terms-stat-received strong,
    body.dark-mode .inv-terms-stat-received span,
    body.dark-mode .inv-terms-stat-received strong,
    html.dark-mode .inv-terms-stat-received span,
    html.dark-mode .inv-terms-stat-received strong {
      color: #34D399 !important;
    }
    html[data-theme="dark"] .inv-terms-stat-remaining-due,
    body.dark-mode .inv-terms-stat-remaining-due,
    html.dark-mode .inv-terms-stat-remaining-due {
      background: rgba(239, 68, 68, 0.15) !important;
      border-color: rgba(239, 68, 68, 0.35) !important;
      color: #F87171 !important;
    }
    html[data-theme="dark"] .inv-terms-stat-remaining-due span,
    html[data-theme="dark"] .inv-terms-stat-remaining-due strong,
    body.dark-mode .inv-terms-stat-remaining-due span,
    body.dark-mode .inv-terms-stat-remaining-due strong,
    html.dark-mode .inv-terms-stat-remaining-due span,
    html.dark-mode .inv-terms-stat-remaining-due strong {
      color: #F87171 !important;
    }
    html[data-theme="dark"] .inv-terms-stat-remaining-zero,
    body.dark-mode .inv-terms-stat-remaining-zero,
    html.dark-mode .inv-terms-stat-remaining-zero {
      background: #1E293B !important;
      border-color: #334155 !important;
      color: #94A3B8 !important;
    }
    html[data-theme="dark"] .inv-terms-stat-remaining-zero span,
    html[data-theme="dark"] .inv-terms-stat-remaining-zero strong,
    body.dark-mode .inv-terms-stat-remaining-zero span,
    body.dark-mode .inv-terms-stat-remaining-zero strong,
    html.dark-mode .inv-terms-stat-remaining-zero span,
    html.dark-mode .inv-terms-stat-remaining-zero strong {
      color: #94A3B8 !important;
    }
    html[data-theme="dark"] .inv-terms-btn-receipt,
    body.dark-mode .inv-terms-btn-receipt,
    html.dark-mode .inv-terms-btn-receipt {
      color: #93C5FD !important;
      border-color: rgba(59, 130, 246, 0.4) !important;
      background: rgba(37, 99, 235, 0.18) !important;
    }
  </style>
</head>
<body>
  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body">
        
        <?php if (!$isClientUser && (is_owner() || is_admin())): ?>
        <!-- Quick Sub-Tab Switcher: Invoice Klien | Pengeluaran | Penggajian -->
        <div class="quick-subtab-bar" style="margin-bottom: 14px;">
          <div style="display: grid; grid-template-columns: repeat(3, 1fr); padding: 4px; border-radius: 12px; gap: 4px; box-sizing: border-box; width: 100%;">
            <a href="<?= url('invoices?_slot=internal') ?>" class="quick-subtab-btn active" style="display: flex; align-items: center; justify-content: center; padding: 8px 6px; border-radius: 8px; font-size: 12px; font-weight: 700; text-decoration: none; text-align: center; white-space: nowrap; background: #5B3DF5; color: #FFFFFF; box-shadow: 0 2px 6px rgba(91,61,245,0.25);">
              Invoice Klien
            </a>
            <a href="<?= url('expenses?_slot=internal') ?>" class="quick-subtab-btn" style="display: flex; align-items: center; justify-content: center; padding: 8px 6px; border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none; text-align: center; white-space: nowrap;">
              Pengeluaran
            </a>
            <a href="<?= url('salaries?_slot=internal') ?>" class="quick-subtab-btn" style="display: flex; align-items: center; justify-content: center; padding: 8px 6px; border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none; text-align: center; white-space: nowrap;">
              Penggajian
            </a>
          </div>
        </div>
        <?php endif; ?>

        <!-- Summary Cards (Shopeers SaaS Style) -->
        <div class="kpi-grid">
          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title"><?= $isClientUser ? 'Total Tagihan Saya' : 'Total Ditagihkan' ?></span>
              <span class="kpi-icon">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="#2563EB" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              </span>
            </div>
            <div class="kpi-value"><?= format_rupiah($totalBilled) ?></div>
            <div class="kpi-meta"><?= $isClientUser ? 'Akumulasi seluruh tagihan proyek' : 'Akumulasi invoice aktif' ?></div>
          </div>

          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title"><?= $isClientUser ? 'Tagihan Lunas' : 'Sudah Dilunasi' ?></span>
              <span class="kpi-icon">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="#059669" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              </span>
            </div>
            <div class="kpi-value" style="color: #059669;"><?= format_rupiah($totalPaid) ?></div>
            <div class="kpi-meta" style="color: #059669;"><?= $isClientUser ? 'Pembayaran terverifikasi' : 'Dana telah diterima' ?></div>
          </div>

          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title"><?= $isClientUser ? 'Belum Dibayar' : 'Menunggu Pelunasan' ?></span>
              <span class="kpi-icon">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="#B45309" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              </span>
            </div>
            <div class="kpi-value" style="color: #B45309;"><?= format_rupiah($totalUnpaid) ?></div>
            <div class="kpi-meta" style="color: #B45309;"><?= $isClientUser ? 'Menunggu pembayaran' : 'Piutang belum dibayar' ?></div>
          </div>
        </div>

        <!-- Invoices List Panel -->
        <div class="glass-panel" style="overflow: visible; min-width: 0; max-width: 100%;">
          <!-- Section Title & Subtitle -->
          <div style="margin-bottom: 16px;">
            <h2 class="panel-title" style="font-size: 15px; font-weight: 600; color: #111827; margin: 0 0 2px 0; letter-spacing: -0.015em;">
              <?= $isClientUser ? 'Riwayat Tagihan &amp; Invoice Saya' : 'Daftar Tagihan &amp; Penagihan (Invoices)' ?>
            </h2>
            <p style="font-size: 12.5px; color: #6B7280; margin: 0;">
              <?= $isClientUser ? 'Pantau status pembayaran invoice dan unduh dokumen invoice resmi PT Kala Kausa Prima' : 'Kelola invoice klien, status pembayaran & generate PDF' ?>
            </p>
          </div>

          <!-- Unified Toolbar Row -->
          <div class="invoices-toolbar-row" style="display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 20px; flex-wrap: wrap;">
            <!-- Left Side: Status Filter Tabs -->
            <div class="date-filter-group">
              <a href="<?= url('invoices?status=all' . ($isClientUser ? '&portal=client&_slot=client' : '')) ?>" class="filter-btn <?= $filterStatus === 'all' ? 'active' : '' ?>">Semua</a>
              <a href="<?= url('invoices?status=Paid' . ($isClientUser ? '&portal=client&_slot=client' : '')) ?>" class="filter-btn <?= $filterStatus === 'Paid' ? 'active' : '' ?>">Paid</a>
              <a href="<?= url('invoices?status=Partially Paid' . ($isClientUser ? '&portal=client&_slot=client' : '')) ?>" class="filter-btn <?= $filterStatus === 'Partially Paid' ? 'active' : '' ?>">Partially Paid</a>
              <a href="<?= url('invoices?status=Draft' . ($isClientUser ? '&portal=client&_slot=client' : '')) ?>" class="filter-btn <?= $filterStatus === 'Draft' ? 'active' : '' ?>">Draft</a>
            </div>

            <!-- Right Side: Search Bar -->
            <div class="invoices-search-wrap">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input type="text" id="invoice-search-input" class="form-control" placeholder="Cari invoice, klien, atau proyek...">
            </div>
          </div>

          <!-- Invoices Data Table -->
          <div class="kala-table-scroll table-responsive" style="width: 100%; max-width: 100%; overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; display: block !important; padding-bottom: 0;">
            <table class="table-custom" id="invoice-table" style="min-width: 980px !important; width: 100%;">
              <thead>
                <tr>
                  <th style="white-space: nowrap !important;">NO. INVOICE</th>
                  <th style="white-space: nowrap !important;">KLIEN &amp; PERUSAHAAN</th>
                  <th style="white-space: nowrap !important;">PROYEK TERKAIT</th>
                  <th style="white-space: nowrap !important;">TANGGAL TERBIT</th>
                  <th style="white-space: nowrap !important;">JATUH TEMPO</th>
                  <th style="white-space: nowrap !important;">TOTAL TAGIHAN</th>
                  <th style="white-space: nowrap !important;">STATUS</th>
                  <th class="th-actions" style="white-space: nowrap !important; text-align: right; min-width: 200px;">AKSI</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($invoices)): ?>
                  <tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 32px; white-space: nowrap !important;">Tidak ada invoice ditemukan.</td></tr>
                <?php else: ?>
                  <?php foreach ($invoices as $inv): ?>
                    <?php
                      // Build WA and Portal share links
                      $rawPhone = preg_replace('/[^0-9]/', '', $inv['client_phone'] ?? '');
                      if (str_starts_with($rawPhone, '0')) {
                          $waPhone = '62' . substr($rawPhone, 1);
                      } elseif (str_starts_with($rawPhone, '62')) {
                          $waPhone = $rawPhone;
                      } else {
                          $waPhone = '';
                      }
                      $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? '') == 443) ? "https://" : "http://";
                      $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                      $portalLink = $protocol . $host . url("invoice-view?id={$inv['id']}&portal=client&_slot=client");

                      $waText = "*TAGIHAN INVOICE RESMI*\n*PT Kala Kausa Prima*\n\n"
                              . "Halo Bapak/Ibu *" . ($inv['client_name'] ?: $inv['client_company']) . "* (" . $inv['client_company'] . "),\n"
                              . "Berikut rincian tagihan invoice resmi Anda:\n\n"
                              . "- No. Invoice: #" . $inv['invoice_number'] . "\n"
                              . "- Proyek: " . ($inv['project_name'] ?: 'Layanan Kreatif & Media') . "\n"
                              . "- Total Tagihan: *" . format_rupiah($inv['total_amount']) . "*\n"
                              . "- Jatuh Tempo: " . format_date($inv['due_date']) . "\n"
                              . "- Status: " . ($inv['status'] === 'Paid' ? 'LUNAS (PAID)' : 'MENUNGGU PEMBAYARAN') . "\n\n"
                              . "Akses Portal Invoice Klien:\n" . $portalLink . "\n\n"
                              . "Terima kasih atas kerja samanya.";

                      $rowWaUrl = !empty($waPhone) 
                          ? "https://api.whatsapp.com/send?phone=$waPhone&text=" . urlencode($waText) 
                          : "https://api.whatsapp.com/send?text=" . urlencode($waText);
                    ?>
                    <?php
                      $totalInvAmt = floatval($inv['total_amount']);
                      $paidInvAmt = floatval($inv['paid_amount']);
                      $remainingAmt = max(0, $totalInvAmt - $paidInvAmt);
                      $payCount = intval($inv['payments_count']);
                      $percentPaid = $totalInvAmt > 0 ? min(100, round(($paidInvAmt / $totalInvAmt) * 100, 1)) : 0;
                    ?>
                    <tr id="inv-row-<?= $inv['id'] ?>" class="invoice-main-row">
                      <td style="font-weight: 700; white-space: nowrap !important;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                          <button type="button" class="btn-toggle-terms" onclick="toggleInvoicePayments(<?= $inv['id'] ?>)" id="btn-toggle-<?= $inv['id'] ?>" title="Lihat Riwayat Termin Pembayaran" style="background: #F2F4F7; border: 1px solid #D0D5DD; border-radius: 6px; width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; padding: 0;">
                            <svg id="arrow-icon-<?= $inv['id'] ?>" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#344054" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="transition: transform 0.2s ease;"><polyline points="6 9 12 15 18 9"></polyline></svg>
                          </button>
                          <a href="<?= url('invoice-view?id=' . $inv['id'] . ($isClientUser ? '&portal=client&_slot=client' : '')) ?>" style="color: #101828; text-decoration: none; font-weight: 700; font-family: inherit;">
                            #<?= htmlspecialchars($inv['invoice_number']) ?>
                          </a>
                        </div>
                      </td>
                      <td style="white-space: nowrap !important;">
                        <?php if ($isClientUser): ?>
                          <div style="font-weight: 700; color: var(--text-primary);">
                            <span><?= htmlspecialchars($inv['client_company']) ?></span>
                          </div>
                        <?php else: ?>
                          <div class="clickable-client-link" onclick="openProjectFinancialDetailModal(<?= $inv['project_id'] ? $inv['project_id'] : 0 ?>, <?= $inv['client_id'] ?>)" title="Lihat Rincian Alokasi Keuangan">
                            <span><?= htmlspecialchars($inv['client_company']) ?></span>
                          </div>
                        <?php endif; ?>
                        <div style="font-size: 11.5px; color: var(--text-secondary); margin-top: 2px;"><?= htmlspecialchars($inv['client_name']) ?></div>
                      </td>
                      <td style="color: var(--text-secondary); font-size: 13px; white-space: nowrap !important;">
                        <?= $inv['project_name'] ? htmlspecialchars($inv['project_name']) : '<span style="color:var(--text-muted);">-</span>' ?>
                      </td>
                      <td style="color: var(--text-secondary); white-space: nowrap !important;"><?= format_date($inv['issue_date']) ?></td>
                      <td style="color: var(--text-secondary); white-space: nowrap !important;"><?= format_date($inv['due_date']) ?></td>
                      <td style="font-weight: 800; color: var(--text-primary); white-space: nowrap !important; font-size: 13.5px;">
                        <div><?= format_rupiah($inv['total_amount']) ?></div>
                        <?php if ($paidInvAmt > 0 && $remainingAmt > 0): ?>
                          <div style="font-size: 11px; font-weight: 600; color: #B54708; margin-top: 2px;">
                            Sisa: <?= format_rupiah($remainingAmt) ?>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td style="white-space: nowrap !important;">
                        <?php
                          $badgeClass = strtolower(str_replace(' ', '-', $inv['status']));
                          $badgeBg = '#F2F4F7';
                          $badgeColor = '#344054';
                          if ($inv['status'] === 'Paid') {
                              $badgeBg = '#ECFDF3';
                              $badgeColor = '#027A48';
                          } elseif ($inv['status'] === 'Partially Paid') {
                              $badgeBg = '#FEF0C7';
                              $badgeColor = '#B54708';
                          } elseif ($inv['status'] === 'Overdue') {
                              $badgeBg = '#FEF3F2';
                              $badgeColor = '#B42318';
                          }
                        ?>
                        <span class="badge-status" style="background: <?= $badgeBg ?>; color: <?= $badgeColor ?>; font-weight: 700; border-radius: 6px; padding: 3px 8px; font-size: 11px;">
                          <?= strtoupper($inv['status']) ?>
                        </span>
                        <?php if ($payCount > 0): ?>
                          <div style="font-size: 10.5px; color: #667085; margin-top: 3px; font-weight: 600;">
                            <?= $payCount ?>x termin (<?= $percentPaid ?>%)
                          </div>
                        <?php endif; ?>
                      </td>
                      <td class="td-actions" style="text-align: right; white-space: nowrap !important;">
                        <div class="table-actions">
                          <!-- Lihat Invoice (Icon Mata) -->
                          <a href="<?= url('invoice-view?id=' . $inv['id'] . ($isClientUser ? '&portal=client&_slot=client' : '')) ?>" class="btn-action-view" title="Lihat Invoice & Unduh PDF" style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; padding: 0; border-radius: 6px;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                          </a>

                          <?php if (!$isClientUser): ?>
                            <!-- Share WA Button (Icon WA Asli) -->
                            <a href="<?= $rowWaUrl ?>" target="_blank" class="btn-action-share-wa" title="Share Invoice ke WhatsApp Klien" style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; padding: 0; border-radius: 6px; background: #25D366; color: #FFFFFF; border: none; cursor: pointer;">
                              <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                            </a>

                            <!-- Share Portal Button (Icon Link) -->
                            <button type="button" class="btn-action-share-portal" onclick="shareInvoicePortal('<?= htmlspecialchars($portalLink, ENT_QUOTES) ?>', '<?= htmlspecialchars($inv['invoice_number'], ENT_QUOTES) ?>')" title="Salin / Bagikan Link Portal Klien" style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; padding: 0; border-radius: 6px; background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; cursor: pointer;">
                              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                            </button>

                            <?php if ($inv['status'] === 'Draft'): ?>
                              <button type="button" class="btn-action-edit" onclick="openEditInvoiceModal(<?= $inv['id'] ?>)" title="Edit Draft Invoice" style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; padding: 0; border-radius: 6px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                              </button>
                            <?php endif; ?>

                            <?php if ($inv['status'] !== 'Paid'): ?>
                              <!-- Tombol Bayar (Single Icon +) -->
                              <button type="button" class="btn-action-pay" onclick="openAddPaymentModal(<?= $inv['id'] ?>, '<?= htmlspecialchars($inv['invoice_number'], ENT_QUOTES) ?>', <?= $remainingAmt ?>)" title="Catat Termin Pembayaran" style="background: #12B76A; color: #FFFFFF; border: none; font-weight: 700; border-radius: 6px; padding: 0 10px; height: 32px; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                <span>Bayar</span>
                              </button>
                            <?php endif; ?>

                            <?php if (is_owner()): ?>
                              <!-- Tombol Hapus (Icon Tong Sampah) -->
                              <button type="button" class="btn-action-delete btn-delete-ghost" onclick="confirmDeleteInvoice(<?= $inv['id'] ?>, '<?= htmlspecialchars($inv['invoice_number'], ENT_QUOTES) ?>')" data-invoice-id="<?= $inv['id'] ?>" data-invoice-number="<?= htmlspecialchars($inv['invoice_number'], ENT_QUOTES) ?>" title="Hapus Invoice" style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; padding: 0; border-radius: 6px;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                              </button>
                            <?php endif; ?>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>

                    <!-- ACCORDION EXPANDABLE ROW (RIWAYAT TERMIN PEMBAYARAN) -->
                    <tr id="inv-terms-row-<?= $inv['id'] ?>" class="invoice-terms-row" style="display: none; background: #F8F9FC;">
                      <td colspan="8" style="padding: 16px 20px; border-top: 1px dashed #D0D5DD; border-bottom: 2px solid #EAECF0;">
                        <div id="inv-terms-container-<?= $inv['id'] ?>">
                          <div style="display: flex; align-items: center; justify-content: center; padding: 20px; color: #667085; font-size: 12.5px;">
                            <span class="spinner-border spinner-border-sm" style="margin-right: 8px;"></span> Memuat riwayat termin pembayaran...
                          </div>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

  <script>
    window.shareInvoicePortal = async function(url, invoiceNum) {
      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(url);
          showToast(`Link portal Invoice #${invoiceNum} berhasil disalin ke clipboard!`, 'success');
        } else {
          const temp = document.createElement('input');
          temp.value = url;
          document.body.appendChild(temp);
          temp.select();
          document.execCommand('copy');
          document.body.removeChild(temp);
          showToast(`Link portal Invoice #${invoiceNum} berhasil disalin!`, 'success');
        }
      } catch (e) {
        prompt(`Salin link portal invoice #${invoiceNum}:`, url);
      }
    };

    window.confirmDeleteInvoice = function(id, invoiceNumber) {
      if (typeof window.showConfirmDeleteModal === 'function') {
        window.showConfirmDeleteModal({
          title: 'Hapus Invoice?',
          descriptionHtml: `Apakah Anda yakin ingin menghapus invoice <strong style="color: #101828;">#${invoiceNumber}</strong>? Tindakan ini bersifat permanen dan data yang dihapus tidak dapat dipulihkan.`,
          confirmBtnText: 'Hapus Invoice',
          onConfirm: async () => {
            const formData = new FormData();
            formData.append('invoice_id', id);

            try {
              const res = await fetch('api/invoices.php?action=delete', {
                method: 'POST',
                body: formData
              });
              const data = await res.json();
              if (data.success) {
                showToast(data.message || 'Invoice berhasil dihapus!', 'success');
                setTimeout(() => window.location.reload(), 600);
              } else {
                showToast(data.message || 'Gagal menghapus invoice', 'danger');
              }
            } catch (err) {
              showToast('Gagal menghapus invoice', 'danger');
            }
          }
        });
      } else {
        if (confirm(`Apakah Anda yakin ingin menghapus invoice #${invoiceNumber}?`)) {
          const formData = new FormData();
          formData.append('invoice_id', id);
          fetch('api/invoices.php?action=delete', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
              if (data.success) {
                showToast(data.message || 'Invoice berhasil dihapus!', 'success');
                setTimeout(() => window.location.reload(), 600);
              } else {
                showToast(data.message || 'Gagal menghapus invoice', 'danger');
              }
            });
        }
      }
    };

    document.addEventListener('DOMContentLoaded', () => {
      if (typeof initTableSearch === 'function') {
        initTableSearch('invoice-search-input', 'invoice-table');
      }
    });

    // Toggle and Fetch Invoice Payment Milestones
    window.toggleInvoicePayments = async function(invoiceId) {
      const row = document.getElementById(`inv-terms-row-${invoiceId}`);
      const btn = document.getElementById(`btn-toggle-${invoiceId}`);
      const arrow = document.getElementById(`arrow-icon-${invoiceId}`);
      const container = document.getElementById(`inv-terms-container-${invoiceId}`);
      if (!row) return;

      const isHidden = (row.style.display === 'none' || !row.style.display);
      if (isHidden) {
        row.style.display = 'table-row';
        if (arrow) arrow.style.transform = 'rotate(180deg)';
        if (btn) btn.style.background = '#E0EAFF';
        await loadInvoicePaymentsData(invoiceId);
      } else {
        row.style.display = 'none';
        if (arrow) arrow.style.transform = 'rotate(0deg)';
        if (btn) btn.style.background = '#F2F4F7';
      }
    };

    window.loadInvoicePaymentsData = async function(invoiceId) {
      const container = document.getElementById(`inv-terms-container-${invoiceId}`);
      if (!container) return;

      try {
        const res = await fetch(`api/invoices.php?action=list_payments&invoice_id=${invoiceId}`);
        const data = await res.json();
        if (!data.success) {
          container.innerHTML = `<div style="color: #B42318; font-size: 12.5px; padding: 12px;">Gagal memuat data termin: ${data.message}</div>`;
          return;
        }

        const inv = data.invoice;
        const payments = data.payments || [];
        const isClient = <?= $isClientUser ? 'true' : 'false' ?>;

        let html = `
          <div class="inv-terms-card">
            <!-- Header Summary Row -->
            <div class="inv-terms-header-border" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; padding-bottom: 14px;">
              <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 32px; height: 32px; border-radius: 8px; background: #EEF4FF; color: #3538CD; display: flex; align-items: center; justify-content: center;">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                </div>
                <div>
                  <div class="inv-terms-title" style="font-size: 14px; font-weight: 700;">Riwayat Termin &amp; Realisasi Pembayaran</div>
                  <div class="inv-terms-subtitle" style="font-size: 12px;">Tagihan #${inv.invoice_number} &bull; Total: <strong class="inv-terms-title">${inv.total_amount_formatted}</strong></div>
                </div>
              </div>

              <!-- Quick Stats Pill & Add Button -->
              <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <div style="display: flex; gap: 8px;">
                  <div class="inv-terms-stat-received">
                    <span>Diterima:</span> <strong>${inv.total_paid_formatted}</strong>
                  </div>
                  <div class="${inv.remaining_balance > 0 ? 'inv-terms-stat-remaining-due' : 'inv-terms-stat-remaining-zero'}">
                    <span>Sisa:</span> <strong>${inv.remaining_balance_formatted}</strong>
                  </div>
                </div>

                ${!isClient && inv.remaining_balance > 0 ? `
                  <button type="button" class="btn btn-primary btn-sm" onclick="openAddPaymentModal(${inv.id}, '${inv.invoice_number}', ${inv.remaining_balance})" style="background: #12B76A; border-color: #12B76A; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    <span>Catat Pembayaran</span>
                  </button>
                ` : ''}
              </div>
            </div>

            <!-- Progress Bar -->
            <div style="margin-bottom: 16px;">
              <div style="display: flex; justify-content: space-between; font-size: 11.5px; margin-bottom: 5px; font-weight: 600;">
                <span class="inv-terms-subtitle">Progres Pelunasan</span>
                <span class="inv-terms-title">${inv.progress_percent}% Lunas</span>
              </div>
              <div style="width: 100%; height: 8px; background: rgba(148, 163, 184, 0.2); border-radius: 999px; overflow: hidden;">
                <div style="width: ${inv.progress_percent}%; height: 100%; background: ${inv.progress_percent >= 100 ? '#12B76A' : '#5B3DF5'}; border-radius: 999px; transition: width 0.4s ease;"></div>
              </div>
            </div>

            <!-- Payments Sub-Table -->
            <div style="overflow-x: auto;">
              <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                <thead>
                  <tr class="inv-terms-thead" style="text-transform: uppercase; font-size: 10.5px; letter-spacing: 0.04em;">
                    <th style="padding: 8px 12px; text-align: left;">Termin / Keterangan</th>
                    <th style="padding: 8px 12px; text-align: left;">Tanggal Bayar</th>
                    <th style="padding: 8px 12px; text-align: left;">Metode</th>
                    <th style="padding: 8px 12px; text-align: left;">Nominal Masuk</th>
                    <th style="padding: 8px 12px; text-align: left;">Bukti Transfer</th>
                    <th style="padding: 8px 12px; text-align: left;">Catatan</th>
                    ${!isClient ? '<th style="padding: 8px 12px; text-align: right;">Aksi</th>' : ''}
                  </tr>
                </thead>
                <tbody>
        `;

        if (payments.length === 0) {
          html += `
            <tr>
              <td colspan="${!isClient ? 7 : 6}" style="text-align: center; padding: 20px; color: #98A2B3; font-style: italic;">
                Belum ada pembayaran yang dicatat untuk invoice ini.
                ${!isClient ? ` <a href="javascript:void(0)" onclick="openAddPaymentModal(${inv.id}, '${inv.invoice_number}', ${inv.remaining_balance})" style="color: #3538CD; font-weight: 600; text-decoration: underline; margin-left: 4px;">Catat Sekarang</a>` : ''}
              </td>
            </tr>
          `;
        } else {
          payments.forEach((p, idx) => {
            const hasReceipt = Boolean(p.receipt_file);
            html += `
              <tr class="inv-terms-row-border">
                <td style="padding: 10px 12px; font-weight: 700;">
                  <span class="inv-terms-num-badge" style="display: inline-block; width: 18px; height: 18px; border-radius: 50%; text-align: center; line-height: 18px; font-size: 10px; margin-right: 6px;">${idx + 1}</span>
                  <span class="inv-terms-name">${escapeHtml(p.term_name || 'Pembayaran Termin')}</span>
                </td>
                <td class="inv-terms-text" style="padding: 10px 12px;">${escapeHtml(p.payment_date)}</td>
                <td style="padding: 10px 12px;">
                  <span class="inv-terms-method-badge">${escapeHtml(p.payment_method || 'Transfer')}</span>
                </td>
                <td style="padding: 10px 12px; font-weight: 800; color: #12B76A; font-size: 13px;">${p.amount_formatted}</td>
                <td style="padding: 10px 12px;">
                  ${hasReceipt ? `
                    <button type="button" class="inv-terms-btn-receipt" onclick="viewReceiptImage('${escapeHtml(p.receipt_file)}', 'Bukti ${escapeHtml(p.term_name)} - #${inv.invoice_number}')">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                      <span>Lihat Bukti</span>
                    </button>
                  ` : `
                    <span style="color: #98A2B3; font-size: 11.5px; font-style: italic;">Tanpa Bukti</span>
                  `}
                </td>
                <td class="inv-terms-subtitle" style="padding: 10px 12px; font-size: 12px;">${escapeHtml(p.notes || '-')}</td>
                ${!isClient ? `
                  <td style="padding: 10px 12px; text-align: right; white-space: nowrap;">
                    <!-- Edit Button -->
                    <button type="button" onclick="openEditPaymentModal(${p.id})" title="Edit Riwayat Pembayaran Ini" style="background: transparent; border: none; color: #1570EF; cursor: pointer; padding: 4px 6px; border-radius: 4px; font-size: 11.5px; display: inline-flex; align-items: center; margin-right: 4px;">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    </button>
                    <!-- Delete Button -->
                    <button type="button" onclick="confirmDeletePayment(${p.id}, ${inv.id}, '${escapeHtml(p.term_name)}', '${p.amount_formatted}')" title="Hapus Riwayat Pembayaran Ini" style="background: transparent; border: none; color: #D92D20; cursor: pointer; padding: 4px 6px; border-radius: 4px; font-size: 11.5px; display: inline-flex; align-items: center;">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    </button>
                  </td>
                ` : ''}
              </tr>
            `;
          });
        }

        html += `
                </tbody>
              </table>
            </div>
          </div>
        `;

        container.innerHTML = html;
      } catch (err) {
        container.innerHTML = `<div style="color: #B42318; font-size: 12.5px; padding: 12px;">Terjadi kesalahan saat memuat termin: ${err.message}</div>`;
      }
    };

    // Modal Catat Pembayaran Termin Baru
    window.openAddPaymentModal = function(invoiceId, invoiceNumber, remainingBalance) {
      const modal = document.getElementById('modal-add-payment-term');
      if (!modal) return;

      document.getElementById('pay-term-invoice-id').value = invoiceId;
      document.getElementById('pay-term-inv-num').innerText = invoiceNumber;
      document.getElementById('pay-term-remaining-label').innerText = 'Rp ' + Number(remainingBalance).toLocaleString('id-ID');
      document.getElementById('pay-term-amount').value = remainingBalance > 0 ? Number(remainingBalance).toLocaleString('id-ID') : '';
      document.getElementById('pay-term-name').value = 'Termin Pembayaran';
      document.getElementById('pay-term-date').value = new Date().toISOString().split('T')[0];
      document.getElementById('pay-term-method').value = 'Bank Transfer Mandiri';
      document.getElementById('pay-term-notes').value = '';
      document.getElementById('pay-term-receipt').value = '';

      openModal('modal-add-payment-term');
    };

    window.confirmDeletePayment = function(paymentId, invoiceId, termName, amountFormatted) {
      if (!confirm(`Hapus data pembayaran "${termName}" senilai ${amountFormatted}?`)) return;

      const fd = new FormData();
      fd.append('payment_id', paymentId);

      fetch('api/invoices.php?action=delete_payment', {
        method: 'POST',
        body: fd
      })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          showToast(res.message, 'success');
          loadInvoicePaymentsData(invoiceId);
          setTimeout(() => window.location.reload(), 1200);
        } else {
          showToast(res.message || 'Gagal menghapus pembayaran', 'danger');
        }
      })
      .catch(() => showToast('Kesalahan koneksi ke server', 'danger'));
    };

    window.openEditPaymentModal = async function(paymentId) {
      try {
        const res = await fetch(`api/invoices.php?action=get_payment_detail&payment_id=${paymentId}`);
        const data = await res.json();
        if (!data.success) {
          showToast(data.message || 'Gagal mengambil detail pembayaran', 'danger');
          return;
        }

        const p = data.payment;
        document.getElementById('edit-pay-id').value = p.id;
        document.getElementById('edit-pay-invoice-id').value = p.invoice_id;
        document.getElementById('edit-pay-inv-num').innerText = p.invoice_number;
        document.getElementById('edit-pay-name').value = p.term_name || '';
        document.getElementById('edit-pay-amount').value = Number(p.amount).toLocaleString('id-ID');
        document.getElementById('edit-pay-date').value = p.payment_date || '';
        document.getElementById('edit-pay-method').value = p.payment_method || 'Bank Transfer Mandiri';
        document.getElementById('edit-pay-notes').value = p.notes || '';
        document.getElementById('edit-pay-receipt').value = '';

        const previewContainer = document.getElementById('edit-pay-receipt-current');
        if (p.receipt_file) {
          previewContainer.style.display = 'block';
          document.getElementById('edit-pay-receipt-link').onclick = () => viewReceiptImage(p.receipt_file, `Bukti ${p.term_name}`);
        } else {
          previewContainer.style.display = 'none';
        }

        openModal('modal-edit-payment-term');
      } catch (e) {
        showToast('Terjadi kesalahan saat memuat data', 'danger');
      }
    };
  </script>

  <?php require_once BASE_PATH . '/includes/footer.php'; ?>

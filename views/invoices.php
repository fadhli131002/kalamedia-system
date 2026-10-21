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
    SELECT i.*, c.name as client_name, c.company as client_company, p.name as project_name
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    LEFT JOIN projects p ON i.project_id = p.id
    WHERE $whereClause
    ORDER BY i.id DESC
")->fetchAll();

// Statistics (Scoped if client)
$statClientWhere = ($isClientUser && $clientId) ? " AND client_id = " . intval($clientId) : "";
$totalBilled = floatval($db->query("SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE COALESCE(is_deleted, 0) = 0" . $statClientWhere)->fetchColumn());
$totalPaid = floatval($db->query("SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE status = 'Paid' AND COALESCE(is_deleted, 0) = 0" . $statClientWhere)->fetchColumn());
$totalUnpaid = floatval($db->query("SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE status != 'Paid' AND COALESCE(is_deleted, 0) = 0" . $statClientWhere)->fetchColumn());
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
  <link rel="stylesheet" href="assets/css/style.css?v=saas-linear-1.0">
  <?php render_tab_uid_script(); ?>
</head>
<body>
  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body">

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
          <div style="display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 20px; flex-wrap: wrap;">
            <!-- Left Side: Status Filter Tabs -->
            <div class="date-filter-group">
              <a href="<?= url('invoices?status=all' . ($isClientUser ? '&portal=client&_slot=client' : '')) ?>" class="filter-btn <?= $filterStatus === 'all' ? 'active' : '' ?>">Semua</a>
              <a href="<?= url('invoices?status=Paid' . ($isClientUser ? '&portal=client&_slot=client' : '')) ?>" class="filter-btn <?= $filterStatus === 'Paid' ? 'active' : '' ?>">Paid</a>
              <a href="<?= url('invoices?status=Draft' . ($isClientUser ? '&portal=client&_slot=client' : '')) ?>" class="filter-btn <?= $filterStatus === 'Draft' ? 'active' : '' ?>">Draft</a>
            </div>

            <!-- Right Side: Search Bar -->
            <div style="position: relative; width: 300px; max-width: 100%;">
              <input type="text" id="invoice-search-input" class="form-control" style="width: 100%; padding-left: 14px; height: 38px; font-size: 13px; border-color: #D0D5DD; border-radius: 8px;" placeholder="Cari invoice, klien, atau proyek...">
            </div>
          </div>

          <!-- Invoices Data Table -->
          <div class="kala-table-scroll table-responsive" style="width: 100%; max-width: 100%; overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; display: block !important; padding-bottom: 8px;">
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
                    <tr>
                      <td style="font-weight: 700; white-space: nowrap !important;">
                        <a href="<?= url('invoice-view?id=' . $inv['id'] . ($isClientUser ? '&portal=client&_slot=client' : '')) ?>" style="color: #101828; text-decoration: none; font-weight: 700; font-family: inherit;">
                          #<?= htmlspecialchars($inv['invoice_number']) ?>
                        </a>
                      </td>
                      <td style="white-space: nowrap !important;">
                        <?php if ($isClientUser): ?>
                          <div style="font-weight: 700; color: #101828;">
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
                      <td style="font-weight: 800; color: #101828; white-space: nowrap !important; font-size: 13.5px;"><?= format_rupiah($inv['total_amount']) ?></td>
                      <td style="white-space: nowrap !important;">
                        <span class="badge-status badge-<?= strtolower($inv['status']) ?>">
                          <?= strtoupper($inv['status']) ?>
                        </span>
                      </td>
                      <td class="td-actions" style="text-align: right; white-space: nowrap !important;">
                        <div class="table-actions">
                          <a href="<?= url('invoice-view?id=' . $inv['id'] . ($isClientUser ? '&portal=client&_slot=client' : '')) ?>" class="btn-action-view" title="Lihat & Unduh PDF">
                            <span>Lihat Invoice</span>
                          </a>

                          <?php if (!$isClientUser): ?>
                            <?php if ($inv['status'] === 'Draft'): ?>
                              <button type="button" class="btn-action-edit" onclick="openEditInvoiceModal(<?= $inv['id'] ?>)" title="Edit Draft Invoice">
                                <span>Edit</span>
                              </button>
                            <?php endif; ?>

                            <?php if ($inv['status'] !== 'Paid'): ?>
                              <button type="button" class="btn-action-pay" onclick="triggerUploadModal('invoice', <?= $inv['id'] ?>, 'Upload Bukti Pelunasan Invoice #<?= $inv['invoice_number'] ?>')" title="Upload Bukti Pembayaran">
                                <span>+ Bayar</span>
                              </button>
                            <?php endif; ?>

                            <?php if (is_owner()): ?>
                              <button type="button" class="btn-action-delete btn-delete-ghost" onclick="confirmDeleteInvoice(<?= $inv['id'] ?>, '<?= htmlspecialchars($inv['invoice_number'], ENT_QUOTES) ?>')" data-invoice-id="<?= $inv['id'] ?>" data-invoice-number="<?= htmlspecialchars($inv['invoice_number'], ENT_QUOTES) ?>" title="Hapus Invoice">
                                <span>Hapus</span>
                              </button>
                            <?php endif; ?>
                          <?php endif; ?>
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
  </script>

  <?php require_once BASE_PATH . '/includes/footer.php'; ?>

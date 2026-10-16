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
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=bankio-1.0">
</head>
<body>
  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body">

        <!-- Summary Cards -->
        <div class="kpi-grid">
          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Total Ditagihkan</span>
            </div>
            <div class="kpi-value"><?= format_rupiah($totalBilled) ?></div>
            <div class="kpi-meta">Akumulasi seluruh invoice aktif</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Sudah Dilunasi</span>
            </div>
            <div class="kpi-value" style="color: var(--success-text);"><?= format_rupiah($totalPaid) ?></div>
            <div class="kpi-meta" style="color: var(--success-text); font-weight: 600;">&bull; Dana telah diterima</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Menunggu Pelunasan</span>
            </div>
            <div class="kpi-value" style="color: var(--warning-text);"><?= format_rupiah($totalUnpaid) ?></div>
            <div class="kpi-meta" style="color: var(--warning-text); font-weight: 600;">&bull; Piutang belum dibayar</div>
          </div>
        </div>

        <!-- Invoices List Panel -->
        <div class="glass-panel">
          <!-- Section Title & Subtitle -->
          <div style="margin-bottom: 18px;">
            <h3 style="font-size: 18px; font-weight: 700; color: #101828; margin: 0 0 4px 0; letter-spacing: -0.2px;">Daftar Tagihan & Penagihan (Invoices)</h3>
            <p style="font-size: 13px; color: var(--text-secondary); margin: 0;">Kelola invoice klien, status pembayaran & generate PDF</p>
          </div>

          <!-- Unified Toolbar Row -->
          <div style="display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 20px; flex-wrap: wrap;">
            <!-- Left Side: Status Filter Tabs -->
            <div class="date-filter-group">
              <a href="<?= url('invoices?status=all') ?>" class="filter-btn <?= $filterStatus === 'all' ? 'active' : '' ?>">Semua</a>
              <a href="<?= url('invoices?status=Paid') ?>" class="filter-btn <?= $filterStatus === 'Paid' ? 'active' : '' ?>">Paid</a>
              <a href="<?= url('invoices?status=Draft') ?>" class="filter-btn <?= $filterStatus === 'Draft' ? 'active' : '' ?>">Draft</a>
            </div>

            <!-- Right Side: Search Bar -->
            <div style="position: relative; width: 300px; max-width: 100%;">
              <input type="text" id="invoice-search-input" class="form-control" style="width: 100%; padding-left: 14px; height: 38px; font-size: 13px; border-color: #D0D5DD; border-radius: 8px;" placeholder="Cari invoice, klien, atau proyek...">
            </div>
          </div>

          <!-- Invoices Data Table -->
          <div class="table-responsive">
            <table class="table-custom" id="invoice-table">
              <thead>
                <tr>
                  <th style="white-space: nowrap;">NO. INVOICE</th>
                  <th style="white-space: nowrap;">KLIEN & PERUSAHAAN</th>
                  <th style="white-space: nowrap;">PROYEK TERKAIT</th>
                  <th style="white-space: nowrap;">TANGGAL TERBIT</th>
                  <th style="white-space: nowrap;">JATUH TEMPO</th>
                  <th style="white-space: nowrap;">TOTAL TAGIHAN</th>
                  <th style="white-space: nowrap;">STATUS</th>
                  <th style="white-space: nowrap; text-align: right;">AKSI</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($invoices)): ?>
                  <tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 32px;">Tidak ada invoice ditemukan.</td></tr>
                <?php else: ?>
                  <?php foreach ($invoices as $inv): ?>
                    <tr>
                      <td style="font-weight: 700; white-space: nowrap;">
                        <a href="<?= url('invoice-view?id=' . $inv['id']) ?>" style="color: #101828; text-decoration: none; font-weight: 700; font-family: inherit;">
                          #<?= htmlspecialchars($inv['invoice_number']) ?>
                        </a>
                      </td>
                      <td>
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
                      <td style="color: var(--text-secondary); font-size: 13px;">
                        <?= $inv['project_name'] ? htmlspecialchars($inv['project_name']) : '<span style="color:var(--text-muted);">-</span>' ?>
                      </td>
                      <td style="color: var(--text-secondary); white-space: nowrap;"><?= format_date($inv['issue_date']) ?></td>
                      <td style="color: var(--text-secondary); white-space: nowrap;"><?= format_date($inv['due_date']) ?></td>
                      <td style="font-weight: 800; color: #101828; white-space: nowrap; font-size: 13.5px;"><?= format_rupiah($inv['total_amount']) ?></td>
                      <td>
                        <span class="badge-status badge-<?= strtolower($inv['status']) ?>">
                          <?= strtoupper($inv['status']) ?>
                        </span>
                      </td>
                      <td style="text-align: right; white-space: nowrap;">
                        <div style="display: inline-flex; gap: 8px; justify-content: flex-end; align-items: center;">
                          <a href="<?= url('invoice-view?id=' . $inv['id']) ?>" class="btn btn-secondary btn-sm" style="padding: 6px 12px; font-size: 12px; font-weight: 600; color: #344054; border: 1px solid #D0D5DD; background: #FFFFFF; border-radius: 8px; text-decoration: none;" title="Lihat & Unduh PDF">
                            <span>Lihat Invoice</span>
                          </a>

                          <?php if (!$isClientUser): ?>
                            <?php if ($inv['status'] === 'Draft'): ?>
                              <button type="button" class="btn btn-secondary btn-sm" onclick="openEditInvoiceModal(<?= $inv['id'] ?>)" style="padding: 6px 12px; font-size: 12px; font-weight: 600; color: #1570EF; border: 1px solid #B2DDFF; background: #EFF8FF; border-radius: 8px;" title="Edit Draft Invoice">
                                <span>Edit</span>
                              </button>
                            <?php endif; ?>

                            <?php if ($inv['status'] !== 'Paid'): ?>
                              <button type="button" class="btn btn-primary btn-sm" style="padding: 6px 12px; font-size: 12px;" onclick="triggerUploadModal('invoice', <?= $inv['id'] ?>, 'Upload Bukti Pelunasan Invoice #<?= $inv['invoice_number'] ?>')" title="Upload Bukti Pembayaran">
                                <span>+ Bayar</span>
                              </button>
                            <?php endif; ?>

                            <?php if (is_owner()): ?>
                              <button type="button" class="btn-delete-ghost" onclick="confirmDeleteInvoice(<?= $inv['id'] ?>, '<?= htmlspecialchars($inv['invoice_number'], ENT_QUOTES) ?>')" data-invoice-id="<?= $inv['id'] ?>" data-invoice-number="<?= htmlspecialchars($inv['invoice_number'], ENT_QUOTES) ?>" title="Hapus Invoice" style="padding: 4px 8px; font-size: 12px; font-weight: 600;">
                                Hapus
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

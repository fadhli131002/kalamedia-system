<?php
/**
 * Kalamedia Expenses Management View (Untitled UI Design System)
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
  <title>Manajemen Pengeluaran (Expenses) - Kalamedia</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body">

        <!-- KPI Breakdown Cards -->
        <div class="kpi-grid">
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

        <!-- Main Expense Container -->
        <div class="glass-panel">
          <div class="panel-header">
            <div>
              <h3 class="panel-title">Pengeluaran Operasional (Outflow)</h3>
              <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Kelola pembayaran fee freelancer dan top-up saldo iklan</p>
            </div>

            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
              <!-- Tab Switchers -->
              <div class="date-filter-group">
                <a href="<?= url('expenses?tab=freelancers') ?>" class="filter-btn <?= $tab === 'freelancers' ? 'active' : '' ?>">Fee Freelancer</a>
                <a href="<?= url('expenses?tab=ads') ?>" class="filter-btn <?= $tab === 'ads' ? 'active' : '' ?>">Top-Up Ads</a>
              </div>

              <?php if ($tab === 'freelancers'): ?>
                <button class="btn btn-primary btn-sm" onclick="openModal('modal-input-payout')">
                  + Input Fee Freelancer
                </button>
              <?php else: ?>
                <button class="btn btn-primary btn-sm" onclick="openModal('modal-catat-ads')">
                  + Catat Top-Up Ads
                </button>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($tab === 'freelancers'): ?>
            <!-- Freelancer Payouts Table -->
            <div class="table-responsive">
              <table class="table-custom" id="payout-table">
                <thead>
                  <tr>
                    <th>Nama Freelancer & Rekening</th>
                    <th>Proyek & Klien</th>
                    <th>Uraian Pekerjaan</th>
                    <th>Nominal Fee</th>
                    <th>Status</th>
                    <th style="text-align: right;">Bukti & Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($payouts)): ?>
                    <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">Belum ada data pembayaran freelancer.</td></tr>
                  <?php else: ?>
                    <?php foreach ($payouts as $p): ?>
                      <tr>
                        <td>
                          <div style="font-weight: 700; color: #101828; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                            <span><?= htmlspecialchars($p['freelancer_name']) ?></span>
                            <?php if (!empty($p['freelancer_phone'])): ?>
                              <span style="font-size: 10px; padding: 2px 6px; border-radius: 4px; background: rgba(37, 211, 102, 0.12); color: #15803d; font-weight: 600;">WA: <?= htmlspecialchars($p['freelancer_phone']) ?></span>
                            <?php endif; ?>
                          </div>
                          <div style="font-size: 11px; color: var(--text-secondary);">
                            <?= htmlspecialchars($p['freelancer_bank'] ?: 'Bank') ?> - <?= htmlspecialchars($p['freelancer_account'] ?: 'No Rekening -') ?>
                          </div>
                        </td>
                        <td>
                          <div style="font-weight: 600; color: #101828;"><?= htmlspecialchars($p['project_name']) ?></div>
                          <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($p['client_company']) ?></div>
                        </td>
                        <td style="color: var(--text-secondary); max-width: 250px;">
                          <?= htmlspecialchars($p['task_description']) ?>
                        </td>
                        <td style="font-weight: 800; color: var(--danger-text);">
                          <?= format_rupiah($p['amount']) ?>
                        </td>
                        <td>
                          <span class="badge-status badge-<?= strtolower($p['status']) ?>">
                            <?= $p['status'] ?>
                          </span>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                          <div style="display: inline-flex; gap: 6px; justify-content: flex-end; align-items: center;">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="openFreelancerVoucherModal(<?= $p['id'] ?>)" title="Lihat &amp; Cetak Invoice / Voucher Fee Freelancer" style="display: inline-flex; align-items: center; gap: 4px; font-weight: 600;">
                              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                              <span>Invoice Fee</span>
                            </button>

                            <button type="button" class="btn btn-secondary btn-sm" onclick="openEditPayoutModal(<?= $p['id'] ?>)" title="Edit Pembayaran Freelancer" style="display: inline-flex; align-items: center; gap: 4px;">
                              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                              <span>Edit</span>
                            </button>

                            <?php if (!empty($p['receipt_file'])): ?>
                              <button type="button" class="btn btn-secondary btn-sm" onclick="viewReceiptImage('<?= UPLOAD_URL . '/' . htmlspecialchars($p['receipt_file']) ?>', 'Bukti Transfer <?= htmlspecialchars($p['freelancer_name']) ?>')">
                                <span>Lihat Struk</span>
                              </button>
                            <?php else: ?>
                              <button type="button" class="btn btn-primary btn-sm" onclick="triggerUploadModal('payout', <?= $p['id'] ?>, 'Upload Bukti Fee <?= htmlspecialchars($p['freelancer_name']) ?>')">
                                <span>+ Upload Bukti</span>
                              </button>
                            <?php endif; ?>

                            <?php if (is_owner()): ?>
                              <button type="button" class="btn-delete-ghost" onclick="confirmDeleteExpense('payout', <?= $p['id'] ?>, '<?= htmlspecialchars($p['freelancer_name']) ?>')" title="Hapus Fee Freelancer">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                  <polyline points="3 6 5 6 21 6"></polyline>
                                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
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
            <!-- Ads Top-Up Table -->
            <div class="table-responsive">
              <table class="table-custom" id="ads-table">
                <thead>
                  <tr>
                    <th>Platform & ID Akun</th>
                    <th>Klien & Proyek</th>
                    <th>Tanggal Top-Up</th>
                    <th>Keterangan Campaign</th>
                    <th>Nominal</th>
                    <th style="text-align: right;">Bukti & Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($adsList)): ?>
                    <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">Belum ada data pengeluaran iklan.</td></tr>
                  <?php else: ?>
                    <?php foreach ($adsList as $ad): ?>
                      <tr>
                        <td>
                          <div style="font-weight: 700; color: #101828;"><?= htmlspecialchars($ad['platform']) ?></div>
                          <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($ad['account_id'] ?: 'ID Akun -') ?></div>
                        </td>
                        <td>
                          <div style="font-weight: 600; color: #101828;"><?= htmlspecialchars($ad['client_company']) ?></div>
                          <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($ad['project_name'] ?: '-') ?></div>
                        </td>
                        <td style="color: var(--text-secondary);"><?= format_date($ad['spent_date']) ?></td>
                        <td style="color: var(--text-secondary);"><?= htmlspecialchars($ad['notes'] ?: '-') ?></td>
                        <td style="font-weight: 800; color: var(--danger-text);"><?= format_rupiah($ad['amount']) ?></td>
                        <td style="text-align: right; white-space: nowrap;">
                          <div style="display: inline-flex; gap: 6px; justify-content: flex-end; align-items: center;">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="openAdsVoucherModal(<?= $ad['id'] ?>)" title="Lihat &amp; Cetak Invoice / Voucher Top-Up Ads" style="display: inline-flex; align-items: center; gap: 4px; font-weight: 600;">
                              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                              <span>Invoice Ads</span>
                            </button>

                            <button type="button" class="btn btn-secondary btn-sm" onclick="openEditAdsModal(<?= $ad['id'] ?>)" title="Edit Top-Up Ads" style="display: inline-flex; align-items: center; gap: 4px;">
                              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                              <span>Edit</span>
                            </button>

                            <?php if (!empty($ad['receipt_file'])): ?>
                              <button type="button" class="btn btn-secondary btn-sm" onclick="viewReceiptImage('<?= UPLOAD_URL . '/' . htmlspecialchars($ad['receipt_file']) ?>', 'Struk Top-Up <?= htmlspecialchars($ad['platform']) ?>')">
                                <span>Lihat Struk</span>
                              </button>
                            <?php else: ?>
                              <button type="button" class="btn btn-primary btn-sm" onclick="triggerUploadModal('ads', <?= $ad['id'] ?>, 'Upload Struk <?= htmlspecialchars($ad['platform']) ?>')">
                                <span>+ Upload Struk</span>
                              </button>
                            <?php endif; ?>

                            <?php if (is_owner()): ?>
                              <button type="button" class="btn-delete-ghost" onclick="confirmDeleteExpense('ads', <?= $ad['id'] ?>, 'Top-Up <?= htmlspecialchars($ad['platform']) ?>')" title="Hapus Top-Up Ads">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                  <polyline points="3 6 5 6 21 6"></polyline>
                                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
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

        </div>

  <?php require_once BASE_PATH . '/includes/footer.php'; ?>

  <script>
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

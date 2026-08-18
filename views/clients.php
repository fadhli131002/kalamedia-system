<?php
/**
 * Kalamedia Clients & Projects Database View (Untitled UI Design System)
 */
require_auth();
$db = Database::getConnection();

// Fetch all clients with project count and total invoiced
$clients = $db->query("
    SELECT c.*,
           (SELECT COUNT(*) FROM projects WHERE client_id = c.id) as total_projects,
           (SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE client_id = c.id AND COALESCE(is_deleted, 0) = 0) as total_invoiced,
           (SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE client_id = c.id AND status = 'Paid' AND COALESCE(is_deleted, 0) = 0) as total_paid
    FROM clients c
    ORDER BY c.id DESC
")->fetchAll();

// Fetch all projects with calculated costs & margins
$projects = $db->query("
    SELECT p.*, c.company as client_company,
           (SELECT COALESCE(SUM(amount), 0) FROM freelancer_payouts WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0) as total_freelancer_cost,
           (SELECT COALESCE(SUM(amount), 0) FROM ads_spend WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0) as total_ads_cost
    FROM projects p
    JOIN clients c ON p.client_id = c.id
    ORDER BY p.id DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Database Klien & Proyek - Kalamedia</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body">

        <!-- 1. Clients Database Panel -->
        <div class="glass-panel">
          <div class="panel-header">
            <div>
              <h3 class="panel-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                Database Klien & Brand Agensi
              </h3>
              <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Daftar seluruh klien dan riwayat penagihan</p>
            </div>

            <button class="btn btn-primary btn-sm" onclick="openModal('modal-create-client')">
              + Tambah Klien Baru
            </button>
          </div>

          <div class="table-responsive">
            <table class="table-custom">
              <thead>
                <tr>
                  <th>Nama Perusahaan</th>
                  <th>Kontak PIC</th>
                  <th>Email & Telepon</th>
                  <th>Total Proyek</th>
                  <th>Total Ditagihkan</th>
                  <th>Status Pembayaran</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($clients as $c): ?>
                  <tr>
                    <td>
                      <div class="clickable-client-link" onclick="openProjectFinancialDetailModal(0, <?= $c['id'] ?>)" title="Lihat Rincian Alokasi Keuangan Klien">
                        <span style="font-size: 14px; font-weight: 700;"><?= htmlspecialchars($c['company']) ?></span>
                        <svg class="icon-open" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                      </div>
                      <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($c['address'] ?: 'Alamat belum diatur') ?></div>
                    </td>
                    <td style="font-weight: 600; color: #101828;"><?= htmlspecialchars($c['name']) ?></td>
                    <td>
                      <div style="color: #101828; font-size: 12px;"><?= htmlspecialchars($c['email']) ?></div>
                      <div style="color: var(--text-secondary); font-size: 11px;"><?= htmlspecialchars($c['phone']) ?></div>
                    </td>
                    <td>
                      <span style="font-weight: 700; color: #101828; background: #F2F4F7; padding: 3px 8px; border-radius: 4px; font-size: 11.5px;">
                        <?= $c['total_projects'] ?> Proyek
                      </span>
                    </td>
                    <td style="font-weight: 700; color: #101828;"><?= format_rupiah($c['total_invoiced']) ?></td>
                    <td>
                      <div style="font-size: 11px; color: var(--success-text); font-weight: 700;">Lunas: <?= format_rupiah($c['total_paid']) ?></div>
                      <?php if ($c['total_invoiced'] > $c['total_paid']): ?>
                        <div style="font-size: 10.5px; color: var(--warning-text); font-weight: 600;">Sisa: <?= format_rupiah($c['total_invoiced'] - $c['total_paid']) ?></div>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- 2. Projects Pipeline & Margin Tracker -->
        <div class="glass-panel">
          <div class="panel-header">
            <div>
              <h3 class="panel-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                Daftar Proyek & Kontrol Margin
              </h3>
              <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Pantau progres nilai kontrak vs biaya produksi aktual</p>
            </div>

            <button class="btn btn-primary btn-sm" onclick="openModal('modal-create-project')">
              + Proyek Baru
            </button>
          </div>

          <div class="table-responsive">
            <table class="table-custom">
              <thead>
                <tr>
                  <th>Nama Proyek & Klien</th>
                  <th>Nilai Kontrak</th>
                  <th>Biaya Produksi (Outflow)</th>
                  <th>Estimasi Profit</th>
                  <th>Margin Aktual</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($projects as $p):
                  $prodCost = floatval($p['total_freelancer_cost']) + floatval($p['total_ads_cost']);
                  $contract = floatval($p['contract_value']);
                  $profit = $contract - $prodCost;
                  $margin = $contract > 0 ? ($profit / $contract) * 100 : 0;
                  $targetMargin = floatval($p['target_margin_percent']);
                  $isOverBudget = $margin < $targetMargin;
                ?>
                  <tr>
                    <td>
                      <div class="clickable-client-link" onclick="openProjectFinancialDetailModal(<?= $p['id'] ?>, <?= $p['client_id'] ?>)" title="Lihat Rincian Alokasi Keuangan Proyek">
                        <span style="font-weight: 700; color: #101828;"><?= htmlspecialchars($p['name']) ?></span>
                        <svg class="icon-open" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                      </div>
                      <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($p['client_company']) ?></div>
                    </td>
                    <td style="font-weight: 700; color: #101828;"><?= format_rupiah($contract) ?></td>
                    <td>
                      <div style="color: var(--danger-text); font-weight: 700;"><?= format_rupiah($prodCost) ?></div>
                      <div style="font-size: 10px; color: var(--text-muted);">Fee Freelancer: <?= format_rupiah($p['total_freelancer_cost']) ?></div>
                      <div style="font-size: 10px; color: var(--text-muted);">Ads Spend: <?= format_rupiah($p['total_ads_cost']) ?></div>
                    </td>
                    <td style="font-weight: 700; color: <?= $profit >= 0 ? 'var(--success-text)' : 'var(--danger-text)' ?>;">
                      <?= format_rupiah($profit) ?>
                    </td>
                    <td>
                      <span class="badge-margin <?= $margin >= $targetMargin ? 'good' : 'bad' ?>">
                        <?= round($margin, 1) ?>%
                      </span>
                      <div style="font-size: 10px; color: var(--text-muted); margin-top: 2px;">Target: <?= $targetMargin ?>%</div>
                    </td>
                    <td>
                      <span class="badge-status badge-<?= strtolower(str_replace(' ', '', $p['status'])) ?>">
                        <?= $p['status'] ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

  <?php require_once BASE_PATH . '/includes/footer.php'; ?>
</body>
</html>

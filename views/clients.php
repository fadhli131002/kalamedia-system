<?php
/**
 * Kalamedia Clients & Projects Database View (Untitled UI Design System)
 * Full Management: View, Add, Edit & Delete for Clients and Projects
 */
require_admin();
$db = Database::getConnection();

// Fetch all active clients with project count and total invoiced
// Fetch all active clients with project count and total invoiced
$clients = $db->query("
    SELECT c.*,
           (SELECT COUNT(*) FROM projects WHERE client_id = c.id AND COALESCE(is_deleted, 0) = 0) as total_projects,
           (SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE client_id = c.id AND COALESCE(is_deleted, 0) = 0) as total_invoiced,
           (SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE client_id = c.id AND status = 'Paid' AND COALESCE(is_deleted, 0) = 0) as total_paid
    FROM clients c
    WHERE COALESCE(c.is_deleted, 0) = 0
    ORDER BY c.id DESC
")->fetchAll();

// Batch query: Fetch assigned team members per client
$assignedTeamRows = $db->query("
    SELECT ec.client_id, u.id, u.name, u.role, u.employment_type, u.position
    FROM employee_clients ec
    JOIN users u ON ec.user_id = u.id
    WHERE COALESCE(u.is_deleted, 0) = 0
    ORDER BY u.name ASC
")->fetchAll();

$teamByClient = [];
foreach ($assignedTeamRows as $r) {
    $teamByClient[(int)$r['client_id']][] = $r;
}

// Fetch all active projects with calculated costs & margins
$projects = $db->query("
    SELECT p.*, c.company as client_company,
           (SELECT COALESCE(SUM(amount), 0) FROM freelancer_payouts WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0) as total_freelancer_cost,
           (SELECT COALESCE(SUM(amount), 0) FROM ads_spend WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0) as total_ads_cost
    FROM projects p
    JOIN clients c ON p.client_id = c.id
    WHERE COALESCE(p.is_deleted, 0) = 0 AND COALESCE(c.is_deleted, 0) = 0
    ORDER BY p.id DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Database Klien & Proyek - PT Kala Kausa Prima</title>
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

        <!-- 1. Clients Database Panel -->
        <div class="glass-panel" style="overflow: visible; min-width: 0; max-width: 100%;">
          <div class="card-toolbar-saas">
            <div class="card-toolbar-header-row">
              <div>
                <h2 class="panel-title" style="margin: 0; font-size: 15px; font-weight: 600; color: #111827; letter-spacing: -0.015em;">
                  Database Klien &amp; Brand Agensi
                </h2>
                <p class="panel-subtitle" style="font-size: 12.5px; color: #6B7280; margin: 2px 0 0;">Daftar seluruh klien, masa kontrak layanan, dan alokasi tim PIC</p>
              </div>

              <button type="button" class="btn btn-primary btn-sm mobile-only-btn" onclick="openModal('modal-create-client')">
                + Tambah Klien Baru
              </button>
            </div>

            <div class="card-toolbar-filter-row">
              <!-- Filter Pills Berdasarkan 4 Jasa Kalamedia (Clean Segmented Control 1 Baris) -->
              <div class="client-filter-pill-bar">
                <button type="button" class="client-filter-pill active" onclick="filterClientService('all', this)">Semua Jasa</button>
                <button type="button" class="client-filter-pill" onclick="filterClientService('Kelola Sosmed', this)">Kelola Sosmed</button>
                <button type="button" class="client-filter-pill" onclick="filterClientService('Desain Eceran', this)">Desain Eceran / Satuan</button>
                <button type="button" class="client-filter-pill" onclick="filterClientService('Jasa Buat Web', this)">Jasa Buat Web</button>
                <button type="button" class="client-filter-pill" onclick="filterClientService('Jasa Iklan', this)">Jasa Iklan Meta &amp; Google</button>
              </div>

              <button type="button" class="btn btn-primary btn-sm desktop-only-btn" onclick="openModal('modal-create-client')">
                + Tambah Klien Baru
              </button>
            </div>
          </div>

          <div class="kala-table-scroll table-responsive" style="width: 100%; max-width: 100%; overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; display: block !important; padding-bottom: 6px;">
            <table class="table-custom" style="min-width: 920px !important; width: 100%;">
              <thead>
                <tr>
                  <th style="white-space: nowrap !important;">NAMA PERUSAHAAN &amp; PIC</th>
                  <th style="white-space: nowrap !important;">LAYANAN / JASA</th>
                  <th style="white-space: nowrap !important;">TIM PIC (PENANGGUNG JAWAB)</th>
                  <th style="white-space: nowrap !important;">MASA KONTRAK &amp; STATUS</th>
                  <th style="white-space: nowrap !important;">TOTAL PROYEK &amp; BIAYA</th>
                  <th class="th-actions" style="text-align: right; white-space: nowrap !important; min-width: 160px;">AKSI</th>
                </tr>
              </thead>
              <tbody id="clients-table-body">
                <tr id="empty-filtered-clients" style="display: none;">
                  <td colspan="6" style="text-align: center; color: #949AB1; padding: 40px;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 8px auto; display: block;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <div style="font-weight: 600; color: #111827; font-size: 13px;">Tidak Ada Klien pada Kategori Jasa Ini</div>
                    <div style="font-size: 12px; color: #6B7280; margin-top: 2px;">Pilih "Semua Jasa" untuk melihat seluruh klien.</div>
                  </td>
                </tr>
                <?php if (empty($clients)): ?>
                  <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 32px;">Belum ada data klien terdaftar.</td></tr>
                <?php else: ?>
                  <?php 
                  $today = date('Y-m-d');
                  foreach ($clients as $c): 
                    $cServices = !empty($c['service_type']) ? explode(',', $c['service_type']) : [];
                    $assignedTeam = $teamByClient[(int)$c['id']] ?? [];

                    $endDate = !empty($c['contract_end_date']) ? $c['contract_end_date'] : null;
                    $startDate = !empty($c['contract_start_date']) ? $c['contract_start_date'] : null;
                    $isExpired = false;
                    $daysRemaining = null;

                    if ($endDate) {
                        $diffDays = (int)ceil((strtotime($endDate) - strtotime($today)) / 86400);
                        if ($diffDays < 0 || ($c['contract_status'] ?? '') === 'expired') {
                            $isExpired = true;
                        } else {
                            $daysRemaining = $diffDays;
                        }
                    }
                  ?>
                    <tr class="client-data-row" data-services="<?= htmlspecialchars(strtolower($c['service_type'] ?? '')) ?>">
                      <td style="white-space: nowrap;">
                        <div class="clickable-client-link" onclick="openProjectFinancialDetailModal(0, <?= $c['id'] ?>)" title="Lihat Rincian Alokasi Keuangan Klien">
                          <span style="font-size: 14px; font-weight: 800; color: #101828;"><?= htmlspecialchars($c['company']) ?></span>
                        </div>
                        <div style="font-weight: 600; color: #344054; font-size: 12px; margin-top: 2px;">PIC: <?= htmlspecialchars($c['name'] ?: '-') ?></div>
                        <div style="color: var(--text-secondary); font-size: 11px;"><?= htmlspecialchars($c['email'] ?: '') ?> <?= !empty($c['phone']) ? '• ' . htmlspecialchars($c['phone']) : '' ?></div>
                      </td>

                      <td>
                        <?php if (empty($cServices)): ?>
                          <span style="font-size: 11px; color: var(--text-muted); font-style: italic;">Belum diatur</span>
                        <?php else: ?>
                          <div style="display: flex; flex-wrap: wrap; gap: 4px; max-width: 220px;">
                            <?php foreach ($cServices as $s): 
                              $s = trim($s);
                              $tagClass = 'service-tag-default';
                              if (stripos($s, 'sosmed') !== false) $tagClass = 'service-tag-sosmed';
                              elseif (stripos($s, 'desain') !== false) $tagClass = 'service-tag-desain';
                              elseif (stripos($s, 'web') !== false) $tagClass = 'service-tag-web';
                              elseif (stripos($s, 'iklan') !== false) $tagClass = 'service-tag-ads';
                            ?>
                              <span class="service-tag <?= $tagClass ?>"><?= htmlspecialchars($s) ?></span>
                            <?php endforeach; ?>
                          </div>
                        <?php endif; ?>
                      </td>

                      <td style="white-space: nowrap;">
                        <?php if (empty($assignedTeam)): ?>
                          <span style="font-size: 11.5px; color: var(--text-tertiary); font-style: italic;">Belum ada PIC teralokasi</span>
                        <?php else: ?>
                          <div style="display: flex; flex-direction: column; gap: 4px;">
                            <?php foreach ($assignedTeam as $atm): ?>
                              <div style="font-size: 12px; font-weight: 700; color: #101828; display: inline-flex; align-items: center; gap: 4px;">
                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #5D76F5; display: inline-block;"></span>
                                <?= htmlspecialchars($atm['name']) ?>
                                <span style="font-size: 10px; color: #6E7485; font-weight: 500;">(<?= htmlspecialchars($atm['position'] ?: 'Staff') ?>)</span>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        <?php endif; ?>
                      </td>

                      <td style="white-space: nowrap;">
                        <?php if ($startDate && $endDate): ?>
                          <div style="font-size: 11.5px; color: #344054; font-weight: 600; margin-bottom: 4px;">
                            <?= date('d M Y', strtotime($startDate)) ?> &ndash; <?= date('d M Y', strtotime($endDate)) ?>
                          </div>
                        <?php else: ?>
                          <div style="font-size: 11px; color: var(--text-muted); font-style: italic; margin-bottom: 4px;">Tanggal belum diset</div>
                        <?php endif; ?>

                        <?php if ($isExpired): ?>
                          <div style="display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                            <span style="background: #FEF3F2; color: #B42318; border: none; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center;">
                              <span class="status-dot dot-red"></span> Kontrak Berakhir
                            </span>
                            <button type="button" class="btn btn-sm" onclick='openRenewClientModal(<?= json_encode($c, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' style="background: #027A48; color: #FFF; border: none; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; cursor: pointer;">
                              + Perpanjang Kontrak
                            </button>
                          </div>
                        <?php else: ?>
                          <div style="display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                            <span style="background: #ECFDF3; color: #027A48; border: none; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center;">
                              <span class="status-dot dot-green"></span> Aktif <?= $daysRemaining !== null ? "• Sisa {$daysRemaining} Hari" : '' ?>
                            </span>
                            <button type="button" class="btn btn-secondary btn-sm" onclick='openRenewClientModal(<?= json_encode($c, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' style="font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px;">
                              Perpanjang
                            </button>
                          </div>
                        <?php endif; ?>
                      </td>

                      <td style="white-space: nowrap;">
                        <span style="font-weight: 700; color: #101828; background: #F2F4F7; padding: 2px 6px; border-radius: 4px; font-size: 11px;">
                          <?= $c['total_projects'] ?> Proyek
                        </span>
                        <div style="font-weight: 700; color: #101828; font-size: 12px; margin-top: 3px;"><?= format_rupiah($c['total_invoiced']) ?></div>
                        <div style="font-size: 10.5px; color: var(--success-text); font-weight: 700;">Lunas: <?= format_rupiah($c['total_paid']) ?></div>
                      </td>

                      <td class="td-actions" style="text-align: right; white-space: nowrap;">
                        <div class="table-actions">
                          <button type="button" class="btn-action-edit" onclick='openEditClientModal(<?= json_encode($c, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Data Klien">
                            Edit
                          </button>
                          <?php if (is_owner()): ?>
                            <button type="button" class="btn-action-delete btn-delete-ghost" onclick="confirmDeleteClient(<?= $c['id'] ?>, '<?= htmlspecialchars($c['company'], ENT_QUOTES) ?>')" title="Hapus Klien">
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
        </div>

        <!-- 2. Projects Pipeline & Margin Tracker -->
        <div class="glass-panel" style="overflow: visible; min-width: 0; max-width: 100%;">
          <div class="panel-header">
            <div>
              <h3 class="panel-title">
                Daftar Proyek &amp; Kontrol Margin
              </h3>
              <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Pantau progres nilai kontrak vs biaya produksi aktual</p>
            </div>

            <button class="btn btn-primary btn-sm" onclick="openModal('modal-create-project')">
              + Proyek Baru
            </button>
          </div>

          <div class="kala-table-scroll table-responsive" style="width: 100%; max-width: 100%; overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; display: block !important; padding-bottom: 6px;">
            <table class="table-custom" style="min-width: 900px !important; width: 100%;">
              <thead>
                <tr>
                  <th style="white-space: nowrap !important;">NAMA PROYEK &amp; KLIEN</th>
                  <th style="white-space: nowrap !important;">NILAI KONTRAK</th>
                  <th style="white-space: nowrap !important;">BIAYA PRODUKSI (OUTFLOW)</th>
                  <th style="white-space: nowrap !important;">ESTIMASI PROFIT</th>
                  <th style="white-space: nowrap !important;">MARGIN AKTUAL</th>
                  <th style="white-space: nowrap !important;">STATUS</th>
                  <th class="th-actions" style="text-align: right; white-space: nowrap !important; min-width: 160px;">AKSI</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($projects)): ?>
                  <tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px;">Belum ada data proyek terdaftar.</td></tr>
                <?php else: ?>
                  <?php foreach ($projects as $p):
                    $prodCost = floatval($p['total_freelancer_cost']) + floatval($p['total_ads_cost']);
                    $contract = floatval($p['contract_value']);
                    $profit = $contract - $prodCost;
                    $margin = $contract > 0 ? ($profit / $contract) * 100 : 0;
                    $targetMargin = floatval($p['target_margin_percent']);
                    $isOverBudget = $margin < $targetMargin;
                  ?>
                    <tr>
                      <td style="white-space: nowrap;">
                        <div class="clickable-client-link" onclick="openProjectFinancialDetailModal(<?= $p['id'] ?>, <?= $p['client_id'] ?>)" title="Lihat Rincian Alokasi Keuangan Proyek">
                          <span style="font-weight: 700; color: #101828;"><?= htmlspecialchars($p['name']) ?></span>
                        </div>
                        <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($p['client_company']) ?></div>
                      </td>
                      <td style="font-weight: 700; color: #101828; white-space: nowrap;"><?= format_rupiah($contract) ?></td>
                      <td style="white-space: nowrap;">
                        <div style="color: var(--danger-text); font-weight: 700;"><?= format_rupiah($prodCost) ?></div>
                        <div style="font-size: 10px; color: var(--text-muted);">Fee Freelancer: <?= format_rupiah($p['total_freelancer_cost']) ?></div>
                        <div style="font-size: 10px; color: var(--text-muted);">Ads Spend: <?= format_rupiah($p['total_ads_cost']) ?></div>
                      </td>
                      <td style="font-weight: 700; color: <?= $profit >= 0 ? 'var(--success-text)' : 'var(--danger-text)' ?>; white-space: nowrap;">
                        <?= format_rupiah($profit) ?>
                      </td>
                      <td style="white-space: nowrap;">
                        <span class="badge-margin <?= $margin >= $targetMargin ? 'good' : 'bad' ?>">
                          <?= round($margin, 1) ?>%
                        </span>
                        <div style="font-size: 10px; color: var(--text-muted); margin-top: 2px;">Target: <?= $targetMargin ?>%</div>
                      </td>
                      <td style="white-space: nowrap;">
                        <span class="badge-status badge-<?= strtolower(str_replace(' ', '', $p['status'])) ?>">
                          <?= $p['status'] ?>
                        </span>
                      </td>
                      <td class="td-actions" style="text-align: right; white-space: nowrap;">
                        <div class="table-actions">
                          <button type="button" class="btn-action-edit" onclick='openEditProjectModal(<?= json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Proyek">
                            Edit
                          </button>
                          <?php if (is_owner()): ?>
                            <button type="button" class="btn-action-delete btn-delete-ghost" onclick="confirmDeleteProject(<?= $p['id'] ?>, '<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>')" title="Hapus Proyek">
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
        </div>

      </div>
    </main>
  </div>

  <?php require_once BASE_PATH . '/includes/modals.php'; ?>
  <?php require_once BASE_PATH . '/includes/footer.php'; ?>

  <script>
    function filterClientService(serviceKey, btn) {
      document.querySelectorAll('.client-filter-pill').forEach(b => b.classList.remove('active'));
      if (btn) btn.classList.add('active');

      const rows = document.querySelectorAll('.client-data-row');
      let visibleCount = 0;
      const key = (serviceKey || '').toLowerCase();

      rows.forEach(r => {
        const rowServices = r.getAttribute('data-services') || '';
        if (key === 'all' || rowServices.includes(key)) {
          r.style.display = '';
          visibleCount++;
        } else {
          r.style.display = 'none';
        }
      });

      const emptyEl = document.getElementById('empty-filtered-clients');
      if (emptyEl) {
        emptyEl.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
      }
    }
  </script>
</body>
</html>

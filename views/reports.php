<?php
/**
 * Kalamedia Full-Funnel Performance Marketing Reports - Index & Management Dashboard
 * Untitled UI Minimalist Design System - Real-Time Blended Economics & Pitch Deck Launcher
 */

require_auth();
$db = Database::getConnection();

// 1. Fetch Summary Aggregate Metrics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_reports,
        COALESCE(SUM(total_ad_spend), 0) as total_spend,
        COALESCE(SUM(revenue), 0) as total_revenue,
        COALESCE(SUM(total_conversions), 0) as total_conversions,
        COALESCE(SUM(ads_reach), 0) as total_reach,
        COALESCE(SUM(ads_impressions), 0) as total_impressions,
        COALESCE(AVG(roas), 0) as avg_roas
    FROM performance_reports 
    WHERE COALESCE(is_deleted, 0) = 0
")->fetch(PDO::FETCH_ASSOC);

$totalSpend = floatval($stats['total_spend'] ?? 0);
$totalRevenue = floatval($stats['total_revenue'] ?? 0);
$blendedRoas = $totalSpend > 0 ? round($totalRevenue / $totalSpend, 2) : 0;
$totalConversions = intval($stats['total_conversions'] ?? 0);
$avgCplCpa = $totalConversions > 0 ? round($totalSpend / $totalConversions, 2) : 0;

// 2. Fetch Clients for Filter Dropdown
$allClients = $db->query("SELECT id, name, company FROM clients ORDER BY company ASC")->fetchAll(PDO::FETCH_ASSOC);

// 3. Filter Parameters
$selectedClient = intval($_GET['client_id'] ?? 0);
$selectedPeriod = trim($_GET['period'] ?? $_GET['month'] ?? '');
$searchQuery = trim($_GET['search'] ?? '');

$where = ["COALESCE(r.is_deleted, 0) = 0"];
$params = [];

if ($selectedClient > 0) {
    $where[] = "r.client_id = ?";
    $params[] = $selectedClient;
}
if (!empty($selectedPeriod)) {
    $where[] = "r.report_period LIKE ?";
    $params[] = "%$selectedPeriod%";
}
if (!empty($searchQuery)) {
    $where[] = "(c.company LIKE ? OR c.name LIKE ? OR r.report_period LIKE ? OR r.objective LIKE ? OR r.content_identity LIKE ? OR r.what_worked LIKE ?)";
    $s = "%$searchQuery%";
    for ($i = 0; $i < 6; $i++) {
        $params[] = $s;
    }
}

$whereSql = implode(' AND ', $where);

// 4. Fetch Performance Reports List
$stmt = $db->prepare("
    SELECT r.*, 
           c.name as client_name, c.company as client_company, 
           c.email as client_email, c.phone as client_phone, c.logo as client_logo
    FROM performance_reports r
    JOIN clients c ON r.client_id = c.id
    WHERE $whereSql
    ORDER BY r.id DESC
");
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Laporan Performance Marketing Klien - Kalamedia</title>
  <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body>
  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body">

        <!-- 1. Top Summary KPI Grid: Full-Funnel Performance -->
        <div class="kpi-grid">
          <!-- Total Managed Spend -->
          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Total Ad Spend Terkelola</span>
            </div>
            <div class="kpi-value" style="color: #0F172A;">
              Rp <?= number_format($totalSpend, 0, ',', '.') ?>
            </div>
            <div class="kpi-meta" style="color: var(--text-secondary);">
              &bull; <?= number_format($stats['total_reports'] ?? 0) ?> Dokumen Deck Terbit
            </div>
          </div>

          <!-- Total Gross Revenue / Omset -->
          <div class="kpi-card" style="border-left: 3px solid #10B981;">
            <div class="kpi-header">
              <span class="kpi-title">Total Gross Revenue Dihasilkan</span>
            </div>
            <div class="kpi-value" style="color: #047857; font-weight: 900;">
              Rp <?= number_format($totalRevenue, 0, ',', '.') ?>
            </div>
            <div class="kpi-meta" style="color: var(--success-text); font-weight: 700;">
              &bull; Closing &amp; E-Commerce Sales
            </div>
          </div>

          <!-- Blended ROAS Index -->
          <div class="kpi-card" style="border-left: 3px solid #4F46E5;">
            <div class="kpi-header">
              <span class="kpi-title">Blended ROAS Index</span>
            </div>
            <div class="kpi-value" style="color: #4F46E5; font-weight: 900;">
              <?= number_format($blendedRoas, 2) ?>x
            </div>
            <div class="kpi-meta" style="color: #4F46E5; font-weight: 600;">
              &bull; Average Return on Ad Spend
            </div>
          </div>

          <!-- Total Conversions & CPA -->
          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Total Conversions &amp; CPA</span>
            </div>
            <div class="kpi-value" style="color: var(--text-primary);">
              <?= number_format($totalConversions) ?> <span style="font-size: 13px; font-weight: 600; color: #64748B;">Conv</span>
            </div>
            <div class="kpi-meta" style="color: var(--text-secondary);">
              &bull; Avg CPA: Rp <?= number_format($avgCplCpa, 0, ',', '.') ?>
            </div>
          </div>
        </div>

        <!-- 2. Main Performance Reports Panel -->
        <div class="glass-panel">
          <div class="panel-header">
            <div>
              <h3 class="panel-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                Laporan Kinerja Full-Funnel Performance Marketing
              </h3>
              <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">
                Daftar pitch deck presentasi performa klien dengan visualisasi ROAS, CPA, creative showdown, dan roadmap aksi.
              </p>
            </div>

            <a href="<?= url('reports-form') ?>" class="btn btn-primary btn-sm" style="gap: 6px;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
              <span>+ Buat Laporan Performance Baru</span>
            </a>
          </div>

          <!-- Filters Row -->
          <form method="GET" action="<?= url('reports') ?>" style="display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 220px;">
              <input type="text" name="search" class="form-control" placeholder="Cari klien, objektif kampanye, atau konsep materi..." value="<?= htmlspecialchars($searchQuery) ?>">
            </div>

            <div style="width: 200px;">
              <select name="client_id" class="form-select" onchange="this.form.submit()">
                <option value="">Semua Klien</option>
                <?php foreach ($allClients as $cl): ?>
                  <option value="<?= $cl['id'] ?>" <?= $selectedClient == $cl['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cl['company']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <button type="submit" class="btn btn-secondary btn-sm" style="padding: 9px 14px;">
              Filter
            </button>
            <?php if ($selectedClient > 0 || !empty($searchQuery)): ?>
              <a href="<?= url('reports') ?>" class="btn btn-secondary btn-sm" style="padding: 9px 12px; color: var(--danger-text);">
                Reset
              </a>
            <?php endif; ?>
          </form>

          <!-- Performance Reports Table -->
          <div class="table-responsive">
            <table class="table-custom">
              <thead>
                <tr>
                  <th>Klien &amp; Brand</th>
                  <th>Objektif &amp; Periode</th>
                  <th>Spend &amp; Revenue</th>
                  <th>ROAS &amp; Net ROI</th>
                  <th>Conversions &amp; CPL/CPA</th>
                  <th style="text-align: right;">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($reports)): ?>
                  <tr>
                    <td colspan="6" style="text-align: center; padding: 48px 16px; color: var(--text-secondary);">
                      <div style="font-size: 32px; margin-bottom: 8px;">📊</div>
                      <div style="font-weight: 700; font-size: 15px; color: #101828;">Belum Ada Laporan Performance Marketing</div>
                      <p style="font-size: 12.5px; margin-top: 4px;">Mulai dengan menerbitkan laporan deck performa pertama untuk klien Anda.</p>
                      <a href="<?= url('reports-form') ?>" class="btn btn-primary btn-sm" style="margin-top: 14px; display: inline-flex;">
                        + Buat Laporan Performance Sekarang
                      </a>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($reports as $r): ?>
                    <tr>
                      <!-- Client Brand -->
                      <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                          <div style="width: 36px; height: 36px; border-radius: 8px; background: #0F172A; color: #FFFFFF; font-weight: 900; font-size: 13px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <?= strtoupper(substr($r['client_company'] ?: $r['client_name'], 0, 2)) ?>
                          </div>
                          <div>
                            <a href="<?= url("report-deck?id={$r['id']}") ?>" style="font-weight: 700; color: #101828; text-decoration: none; font-size: 13.5px;" class="hover-underline">
                              <?= htmlspecialchars($r['client_company']) ?>
                            </a>
                            <div style="font-size: 11px; color: var(--text-muted);">
                              PIC: <?= htmlspecialchars($r['client_name']) ?>
                            </div>
                          </div>
                        </div>
                      </td>

                      <!-- Objective & Period -->
                      <td>
                        <div style="font-weight: 700; color: #0F172A; font-size: 12.5px; margin-bottom: 2px;">
                          <?= htmlspecialchars($r['objective'] ?: 'Performance Campaign') ?>
                        </div>
                        <span class="badge" style="background: #EEF2FF; color: #4F46E5; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px;">
                          <?= htmlspecialchars($r['report_period']) ?>
                        </span>
                      </td>

                      <!-- Spend vs Revenue -->
                      <td>
                        <div style="font-weight: 800; color: #047857; font-size: 13px;">
                          Rp <?= number_format($r['revenue'], 0, ',', '.') ?>
                        </div>
                        <div style="font-size: 11px; color: var(--text-secondary);">
                          Spend: Rp <?= number_format($r['total_ad_spend'], 0, ',', '.') ?>
                        </div>
                      </td>

                      <!-- ROAS & ROI -->
                      <td>
                        <div style="display: inline-flex; align-items: baseline; gap: 4px;">
                          <span style="font-weight: 900; color: #4F46E5; font-size: 15px;">
                            <?= number_format($r['roas'], 2) ?>x
                          </span>
                          <span style="font-size: 11px; font-weight: 700; color: #059669;">
                            (+<?= number_format($r['roi'], 0) ?>% ROI)
                          </span>
                        </div>
                        <div style="font-size: 10.5px; color: var(--text-muted); margin-top: 1px;">
                          CTR: <?= number_format($r['ads_ctr'], 2) ?>%
                        </div>
                      </td>

                      <!-- Conversions & CPL/CPA -->
                      <td>
                        <span style="display: inline-flex; align-items: center; gap: 5px; background: #0F172A; color: #FFFFFF; font-weight: 800; font-size: 11.5px; padding: 3px 9px; border-radius: 16px;">
                          <span style="color: #FCD34D;">★</span>
                          <?= number_format($r['total_conversions']) ?> Conv
                        </span>
                        <div style="font-size: 10.5px; color: var(--text-muted); margin-top: 2px;">
                          Rp <?= number_format($r['cpl_cpa'], 0, ',', '.') ?> / unit
                        </div>
                      </td>

                      <!-- Actions -->
                      <td style="text-align: right;">
                        <div style="display: inline-flex; align-items: center; gap: 6px;">
                          <a href="<?= url("report-deck?id={$r['id']}") ?>" class="btn btn-primary btn-sm" style="padding: 5px 10px; gap: 5px;" title="Buka Pitch Deck">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <span>Deck</span>
                          </a>

                          <a href="<?= url("reports-form?id={$r['id']}") ?>" class="btn btn-secondary btn-sm" style="padding: 5px 9px;" title="Edit Data Laporan">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                          </a>

                          <button type="button" class="btn btn-secondary btn-sm" style="padding: 5px 9px; color: var(--danger-text);" onclick="deleteReport(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['client_company'])) ?>', '<?= htmlspecialchars($r['report_period']) ?>')" title="Hapus Laporan">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                          </button>
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

  <script src="assets/js/app.js"></script>
  <script>
    async function deleteReport(id, company, period) {
      if (!confirm(`Apakah Anda yakin ingin menghapus laporan performa ${company} periode ${period}?`)) {
        return;
      }

      try {
        const res = await fetch(`api/reports.php?action=delete&id=${id}`, {
          method: 'POST'
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          setTimeout(() => {
            window.location.reload();
          }, 600);
        } else {
          showToast(data.message || 'Gagal menghapus laporan.', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal.', 'danger');
      }
    }
  </script>
</body>
</html>

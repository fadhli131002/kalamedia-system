<?php
/**
 * Kalamedia Full-Funnel Performance Marketing Reports - Index & Management Dashboard
 * Untitled UI Minimalist Design System - Real-Time Blended Economics & Pitch Deck Launcher
 */

require_auth();
$db = Database::getConnection();

$isClientUser = is_client();
$clientId = current_user_client_id();

$statClientWhere = ($isClientUser && $clientId) ? " AND client_id = " . intval($clientId) : "";

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
    WHERE COALESCE(is_deleted, 0) = 0 $statClientWhere
")->fetch(PDO::FETCH_ASSOC);

$totalSpend = floatval($stats['total_spend'] ?? 0);
$totalRevenue = floatval($stats['total_revenue'] ?? 0);
$blendedRoas = $totalSpend > 0 ? round($totalRevenue / $totalSpend, 2) : 0;
$totalConversions = intval($stats['total_conversions'] ?? 0);
$avgCplCpa = $totalConversions > 0 ? round($totalSpend / $totalConversions, 2) : 0;

// 2. Fetch Clients for Filter Dropdown
if ($isClientUser && $clientId) {
    $stmtC = $db->prepare("SELECT id, name, company FROM clients WHERE id = ?");
    $stmtC->execute([$clientId]);
    $allClients = $stmtC->fetchAll(PDO::FETCH_ASSOC);
    $selectedClient = $clientId;
} else {
    $allClients = $db->query("SELECT id, name, company FROM clients ORDER BY company ASC")->fetchAll(PDO::FETCH_ASSOC);
    $selectedClient = intval($_GET['client_id'] ?? 0);
}

// 3. Filter Parameters
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
  <title>Laporan Performance Marketing Klien - PT Kala Kausa Prima</title>
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

        <!-- 1. Top Summary KPI Grid: Shopeers Architecture -->
        <div class="kpi-grid">
          <!-- Total Managed Spend -->
          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Total Ad Spend</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
            <div class="kpi-value">
              Rp <?= number_format($totalSpend, 0, ',', '.') ?>
            </div>
            <div class="kpi-meta">
              <?= number_format($stats['total_reports'] ?? 0) ?> Dokumen Deck Terbit
            </div>
          </div>

          <!-- Total Gross Revenue / Omset -->
          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Total Gross Revenue</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            </div>
            <div class="kpi-value">
              Rp <?= number_format($totalRevenue, 0, ',', '.') ?>
            </div>
            <div class="kpi-meta">
              Closing &amp; E-Commerce Sales
            </div>
          </div>

          <!-- Blended ROAS Index -->
          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Blended ROAS Index</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
            <div class="kpi-value">
              <?= number_format($blendedRoas, 2) ?>x
            </div>
            <div class="kpi-meta">
              Average Return on Ad Spend
            </div>
          </div>

          <!-- Total Conversions & CPA -->
          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Total Conversions</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="kpi-value">
              <?= number_format($totalConversions) ?>
            </div>
            <div class="kpi-meta">
              Avg CPA: Rp <?= number_format($avgCplCpa, 0, ',', '.') ?>
            </div>
          </div>
        </div>

        <!-- 2. Main Performance Reports Panel -->
        <div class="glass-panel">
          <div class="panel-header">
            <div>
              <h3 class="panel-title">
                Laporan Kinerja Full-Funnel Performance Marketing
              </h3>
              <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">
                Daftar pitch deck presentasi performa klien dengan visualisasi ROAS, CPA, creative showdown, dan roadmap aksi.
              </p>
            </div>

            <?php if (!$isClientUser && (is_owner() || is_admin())): ?>
              <a href="<?= url('reports-form') ?>" class="btn btn-primary btn-sm">
                <span>+ Buat Laporan Performance Baru</span>
              </a>
            <?php endif; ?>
          </div>

          <!-- Filters Row -->
          <form method="GET" action="<?= url('reports') ?>" style="display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 220px;">
              <input type="text" name="search" class="form-control" placeholder="Cari klien, objektif kampanye, atau konsep materi..." value="<?= htmlspecialchars($searchQuery) ?>">
            </div>

            <?php if (!$isClientUser): ?>
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
            <?php endif; ?>

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
          <div class="kala-table-scroll table-responsive" style="width: 100%; max-width: 100%; overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; display: block !important; padding-bottom: 6px;">
            <table class="table-custom" style="min-width: 880px !important; width: 100%;">
              <thead>
                <tr>
                  <th style="white-space: nowrap !important;">Klien &amp; Brand</th>
                  <th style="white-space: nowrap !important;">Objektif &amp; Periode</th>
                  <th style="white-space: nowrap !important;">Spend &amp; Revenue</th>
                  <th style="white-space: nowrap !important;">ROAS &amp; Net ROI</th>
                  <th style="white-space: nowrap !important;">Conversions &amp; CPL/CPA</th>
                  <th class="th-actions" style="text-align: right; min-width: 180px; white-space: nowrap !important;">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($reports)): ?>
                  <tr>
                    <td colspan="6" style="text-align: center; padding: 48px 16px; color: var(--text-secondary);">
                      <div style="display: flex; justify-content: center; margin-bottom: 12px;">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                      </div>
                      <div style="font-weight: 600; font-size: 14px; color: #111827;">Belum Ada Laporan Performance Marketing</div>
                      <p style="font-size: 12.5px; color: #6B7280; margin-top: 4px;">
                        <?= $isClientUser ? 'Belum ada dokumen laporan performa yang diterbitkan untuk akun Anda.' : 'Mulai dengan menerbitkan laporan deck performa pertama untuk klien Anda.' ?>
                      </p>
                      <?php if (!$isClientUser && (is_owner() || is_admin())): ?>
                        <a href="<?= url('reports-form') ?>" class="btn btn-primary btn-sm" style="margin-top: 14px; display: inline-flex; height: 34px; padding: 0 14px; border-radius: 8px;">
                          + Buat Laporan Performance Sekarang
                        </a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($reports as $r): ?>
                    <tr>
                      <!-- Client Brand -->
                      <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                          <div style="width: 34px; height: 34px; border-radius: 8px; background: #EFF6FF; border: 1px solid #DBEAFE; color: #2563EB; font-weight: 600; font-size: 12.5px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <?= strtoupper(substr($r['client_company'] ?: $r['client_name'], 0, 2)) ?>
                          </div>
                          <div>
                            <a href="<?= url("report-deck?id={$r['id']}") ?>" style="font-weight: 600; color: #111827; text-decoration: none; font-size: 13px;" class="hover-underline">
                              <?= htmlspecialchars($r['client_company']) ?>
                            </a>
                            <div style="font-size: 11.5px; color: #6B7280;">
                              PIC: <?= htmlspecialchars($r['client_name']) ?>
                            </div>
                          </div>
                        </div>
                      </td>

                      <!-- Objective & Period -->
                      <td>
                        <div style="font-weight: 600; color: #111827; font-size: 12.5px; margin-bottom: 3px;">
                          <?= htmlspecialchars($r['objective'] ?: 'Performance Campaign') ?>
                        </div>
                        <span style="background: #F3F4F6; color: #374151; font-size: 11px; font-weight: 500; padding: 2px 7px; border-radius: 5px;">
                          <?= htmlspecialchars($r['report_period']) ?>
                        </span>
                      </td>

                      <!-- Spend vs Revenue -->
                      <td>
                        <div style="font-weight: 600; color: #047857; font-size: 13px;">
                          Rp <?= number_format($r['revenue'], 0, ',', '.') ?>
                        </div>
                        <div style="font-size: 11.5px; color: #6B7280;">
                          Spend: Rp <?= number_format($r['total_ad_spend'], 0, ',', '.') ?>
                        </div>
                      </td>

                      <!-- ROAS & ROI -->
                      <td>
                        <div style="display: inline-flex; align-items: baseline; gap: 4px;">
                          <span style="font-weight: 700; color: #2563EB; font-size: 14px;">
                            <?= number_format($r['roas'], 2) ?>x
                          </span>
                          <span style="font-size: 11px; font-weight: 600; color: #059669;">
                            (+<?= number_format($r['roi'], 0) ?>% ROI)
                          </span>
                        </div>
                        <div style="font-size: 11px; color: #9CA3AF; margin-top: 1px;">
                          CTR: <?= number_format($r['ads_ctr'], 2) ?>%
                        </div>
                      </td>

                      <!-- Conversions & CPL/CPA -->
                      <td>
                        <span style="display: inline-flex; align-items: center; background: #EFF6FF; color: #2563EB; font-weight: 600; font-size: 11.5px; padding: 3px 8px; border-radius: 6px;">
                          <?= number_format($r['total_conversions']) ?> Conv
                        </span>
                        <div style="font-size: 11px; color: #9CA3AF; margin-top: 2px;">
                          Rp <?= number_format($r['cpl_cpa'], 0, ',', '.') ?> / unit
                        </div>
                      </td>

                      <!-- Actions -->
                      <td class="td-actions" style="text-align: right; white-space: nowrap;">
                        <div class="table-actions">
                          <a href="<?= url("report-deck?id={$r['id']}&portal=client&_slot=client") ?>" class="btn-action-view" title="Buka Pitch Deck">
                            Deck
                          </a>

                          <?php if (!$isClientUser && (is_owner() || is_admin())): ?>
                            <a href="<?= url("reports-form?id={$r['id']}") ?>" class="btn-action-edit" title="Edit Data Laporan">
                              Edit
                            </a>

                            <button type="button" class="btn-action-delete btn-delete-ghost" onclick="deleteReport(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['client_company'])) ?>', '<?= htmlspecialchars($r['report_period']) ?>')" title="Hapus Laporan">
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

  <script src="assets/js/app.js?v=bankio-typography-3.0"></script>
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

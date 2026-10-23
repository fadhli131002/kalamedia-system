<?php
/**
 * Kalamedia Full-Funnel Performance Marketing Reports & Standard Ads Reports Dashboard
 * Multi-Role Adaptive: Tim Ads, Tim Kreatif, Owner & Admin
 */

require_auth();
$db = Database::getConnection();

$currUser = current_user();
$userId = (int)($currUser['id'] ?? 0);
$role = current_user_role();
$isClientUser = is_client();
$clientId = current_user_client_id();
$isOwnerOrAdmin = is_owner() || is_admin();
$isAdsSpecialistUser = is_ads_specialist($currUser);
$isCreativeSpecialistUser = is_creative_specialist($currUser);

// Determine active sub-tab
$activeTab = $_GET['tab'] ?? ($isAdsSpecialistUser ? 'ads_report' : 'decks');

// Filter Parameters
$selectedClient = intval($_GET['client_id'] ?? ($isClientUser ? $clientId : 0));
$selectedPeriod = trim($_GET['period'] ?? $_GET['month'] ?? '');
$searchQuery = trim($_GET['search'] ?? '');
$selectedPlatform = trim($_GET['platform'] ?? 'all');

// 1. Fetch Summary Aggregate Metrics
$statClientWhere = ($isClientUser && $clientId) ? " AND client_id = " . intval($clientId) : "";
if ($isAdsSpecialistUser && !$isOwnerOrAdmin) {
    $statClientWhere .= " AND (created_by_user_id = $userId OR client_id IN (
        SELECT client_id FROM projects WHERE id IN (
            SELECT project_id FROM tasks WHERE assigned_to_user_id = $userId AND is_deleted = 0
            UNION 
            SELECT project_id FROM ads_topup_transactions WHERE assigned_user_id = $userId AND is_deleted = 0
        )
    ))";
}

$stats = $db->query("
    SELECT 
        COUNT(*) as total_reports,
        COALESCE(SUM(total_ad_spend), 0) as total_spend,
        COALESCE(SUM(revenue), 0) as total_revenue,
        COALESCE(SUM(total_conversions), 0) as total_conversions,
        COALESCE(SUM(ads_reach), 0) as total_reach,
        COALESCE(SUM(ads_impressions), 0) as total_impressions,
        COALESCE(SUM(total_clicks), 0) as total_clicks,
        COALESCE(AVG(roas), 0) as avg_roas
    FROM performance_reports 
    WHERE COALESCE(is_deleted, 0) = 0 $statClientWhere
")->fetch(PDO::FETCH_ASSOC);

$totalSpend = floatval($stats['total_spend'] ?? 0);
$totalRevenue = floatval($stats['total_revenue'] ?? 0);
$blendedRoas = $totalSpend > 0 ? round($totalRevenue / $totalSpend, 2) : 0;
$totalConversions = intval($stats['total_conversions'] ?? 0);
$avgCplCpa = $totalConversions > 0 ? round($totalSpend / $totalConversions, 2) : 0;
$totalImpressions = intval($stats['total_impressions'] ?? 0);
$totalClicks = intval($stats['total_clicks'] ?? 0);
$avgCtr = $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : 0;

// 2. Fetch Clients & Projects for Dropdowns
if ($isClientUser && $clientId) {
    $stmtC = $db->prepare("SELECT id, name, company FROM clients WHERE id = ?");
    $stmtC->execute([$clientId]);
    $allClients = $stmtC->fetchAll(PDO::FETCH_ASSOC);
    $formProjects = $db->prepare("SELECT id, name, client_id, service_type FROM projects WHERE client_id = ? AND is_deleted = 0 ORDER BY name ASC");
    $formProjects->execute([$clientId]);
    $formProjects = $formProjects->fetchAll(PDO::FETCH_ASSOC);
} elseif ($isAdsSpecialistUser && !$isOwnerOrAdmin) {
    $stmtUserProjects = $db->prepare("
        SELECT DISTINCT p.id, p.name, p.client_id, p.service_type,
               c.company as client_company, c.name as client_name
        FROM projects p
        JOIN clients c ON p.client_id = c.id
        WHERE p.is_deleted = 0 AND c.is_deleted = 0
          AND (
              p.id IN (SELECT project_id FROM tasks WHERE assigned_to_user_id = ? AND is_deleted = 0)
              OR p.id IN (SELECT project_id FROM ads_topup_transactions WHERE assigned_user_id = ? AND is_deleted = 0)
          )
        ORDER BY c.company ASC, p.name ASC
    ");
    $stmtUserProjects->execute([$userId, $userId]);
    $formProjects = $stmtUserProjects->fetchAll(PDO::FETCH_ASSOC);

    // Clients scoped to this specialist
    $allClients = $db->prepare("
        SELECT DISTINCT c.id, c.name, c.company 
        FROM clients c
        JOIN projects p ON p.client_id = c.id
        WHERE c.is_deleted = 0 AND p.is_deleted = 0
          AND (
              p.id IN (SELECT project_id FROM tasks WHERE assigned_to_user_id = ? AND is_deleted = 0)
              OR p.id IN (SELECT project_id FROM ads_topup_transactions WHERE assigned_user_id = ? AND is_deleted = 0)
          )
        ORDER BY c.company ASC
    ");
    $allClients->execute([$userId, $userId]);
    $allClients = $allClients->fetchAll(PDO::FETCH_ASSOC);
    if (empty($allClients)) {
        $allClients = $db->query("SELECT id, name, company FROM clients WHERE is_deleted = 0 ORDER BY company ASC")->fetchAll(PDO::FETCH_ASSOC);
        $formProjects = $db->query("SELECT p.id, p.name, p.client_id, p.service_type, c.company as client_company, c.name as client_name FROM projects p JOIN clients c ON p.client_id = c.id WHERE p.is_deleted = 0 ORDER BY c.company ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    $allClients = $db->query("SELECT id, name, company FROM clients WHERE is_deleted = 0 ORDER BY company ASC")->fetchAll(PDO::FETCH_ASSOC);
    $formProjects = $db->query("
        SELECT p.id, p.name, p.client_id, p.service_type,
               c.company as client_company, c.name as client_name
        FROM projects p
        JOIN clients c ON p.client_id = c.id
        WHERE p.is_deleted = 0 AND c.is_deleted = 0
        ORDER BY c.company ASC, p.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// 3. Query Performance Reports
$where = ["COALESCE(r.is_deleted, 0) = 0"];
$params = [];

if ($selectedClient > 0) {
    $where[] = "r.client_id = ?";
    $params[] = $selectedClient;
} elseif ($isAdsSpecialistUser && !$isOwnerOrAdmin && !empty($allClients)) {
    $cIds = array_column($allClients, 'id');
    if (!empty($cIds)) {
        $inCls = implode(',', array_fill(0, count($cIds), '?'));
        $where[] = "(r.created_by_user_id = $userId OR r.client_id IN ($inCls))";
        foreach ($cIds as $cid) {
            $params[] = $cid;
        }
    }
}

if (!empty($selectedPeriod)) {
    $where[] = "r.report_period LIKE ?";
    $params[] = "%$selectedPeriod%";
}

if ($selectedPlatform !== 'all' && !empty($selectedPlatform)) {
    $where[] = "r.platform = ?";
    $params[] = $selectedPlatform;
}

if (!empty($searchQuery)) {
    $where[] = "(c.company LIKE ? OR c.name LIKE ? OR r.report_period LIKE ? OR r.objective LIKE ? OR r.winning_creative_notes LIKE ? OR r.optimizing_plan_notes LIKE ?)";
    $s = "%$searchQuery%";
    for ($i = 0; $i < 6; $i++) {
        $params[] = $s;
    }
}

$whereSql = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT r.*, 
           c.name as client_name, c.company as client_company, 
           c.email as client_email, c.phone as client_phone, c.logo as client_logo,
           p.name as project_name
    FROM performance_reports r
    JOIN clients c ON r.client_id = c.id
    LEFT JOIN projects p ON r.project_id = p.id
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
  <title>Laporan Performance &amp; Report Ads - PT Kala Kausa Prima</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=saas-linear-1.0">
  <?php render_tab_uid_script(); ?>
  <style>
    .report-tab-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 18px;
      border-radius: 12px;
      font-size: 13px;
      font-weight: 700;
      color: #4B5563;
      background: #FFFFFF;
      border: 1px solid #E5E7EB;
      text-decoration: none;
      transition: all 0.2s ease;
      cursor: pointer;
    }
    .report-tab-pill:hover {
      background: #F9FAFB;
      color: #111827;
    }
    .report-tab-pill.active {
      background: #5B3DF5;
      color: #FFFFFF;
      border-color: #5B3DF5;
      box-shadow: 0 4px 12px rgba(91, 61, 245, 0.25);
    }
    .metric-chip {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 11.5px;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 6px;
    }
    .chip-platform-meta { background: #EBF5FF; color: #1E429F; border: 1px solid #C3DDFD; }
    .chip-platform-google { background: #FEF08A; color: #854D0E; border: 1px solid #FDE047; }
    .chip-platform-tiktok { background: #F3E8FF; color: #6B21A8; border: 1px solid #E9D5FF; }

    /* Form Modal Styles */
    .kala-modal-backdrop {
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(17, 24, 39, 0.6);
      backdrop-filter: blur(4px);
      z-index: 1050;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    .kala-modal-dialog {
      background: #FFFFFF;
      border-radius: 20px;
      width: 100%;
      max-width: 820px;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
      border: 1px solid #E5E7EB;
    }
    .kala-modal-header {
      padding: 20px 24px;
      border-bottom: 1px solid #F3F4F6;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .kala-modal-body {
      padding: 24px;
    }
    .kala-modal-footer {
      padding: 16px 24px;
      background: #F9FAFB;
      border-top: 1px solid #F3F4F6;
      border-bottom-left-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 10px;
    }
    @media (max-width: 768px) {
      .content-body {
        padding-top: 14px !important;
        padding-bottom: calc(96px + env(safe-area-inset-bottom, 0px)) !important;
        padding-left: 16px !important;
        padding-right: 16px !important;
      }
      .glass-panel {
        padding: 14px !important;
        border-radius: 18px !important;
        width: 100% !important;
        box-sizing: border-box !important;
      }
    }
    @media print {
      body * { visibility: hidden !important; }
      #printable-report-area, #printable-report-area * { visibility: visible !important; }
      #printable-report-area { position: absolute; left: 0; top: 0; width: 100% !important; margin: 0 !important; padding: 20px !important; }
      .no-print { display: none !important; }
    }
  </style>
</head>
<body>
  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body">

        <!-- Role Badge Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
          <div style="min-width: 0;">
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
              <h2 style="font-size: 18px; font-weight: 800; color: #0F172A; margin: 0; line-height: 1.25;">
                <?= $isAdsSpecialistUser ? 'Workspace Report Ads Kalamedia' : 'Pusat Laporan & Performa Klien' ?>
              </h2>
              <?php if ($isAdsSpecialistUser): ?>
                <span style="background: #EEF2FF; color: #4F46E5; font-weight: 700; font-size: 11px; padding: 3px 10px; border-radius: 999px; border: 1px solid #E0E7FF;">
                  Mode Spesialis Ads
                </span>
              <?php endif; ?>
            </div>
            <p style="font-size: 12.5px; color: #64748B; margin: 3px 0 0; line-height: 1.4;">
              <?= $isAdsSpecialistUser 
                ? 'Template laporan iklan standar Kalamedia. Laporan otomatis terkirim ke Owner & Portal Klien.' 
                : 'Pusat laporan performa iklan berbayar (Meta, Google, TikTok Ads) dan rekap pekerjaan tim kreatif.' ?>
            </p>
          </div>

          <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <button type="button" onclick="openNewAdsReportModal()" style="background: #5B3DF5; color: #FFFFFF; font-weight: 700; font-size: 12.5px; border-radius: 10px; height: 36px; padding: 0 14px; border: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(91, 61, 245, 0.25); cursor: pointer; transition: all 0.15s ease;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              <span>+ Buat Report Ads</span>
            </button>
            <?php if ($isOwnerOrAdmin): ?>
              <a href="<?= url('reports-form') ?>" class="btn btn-secondary btn-sm" style="border-radius: 10px; height: 36px; display: inline-flex; align-items: center;">
                <span>+ Buat Pitch Deck</span>
              </a>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($isOwnerOrAdmin || $isCreativeSpecialistUser): ?>
        <!-- Modern Segmented Tabs Bar (Only when multiple views exist, hidden on mobile for workers) -->
        <div class="worker-tabs-bar" style="display: flex; gap: 8px; margin-bottom: 22px; flex-wrap: wrap;">
          <a href="<?= url('reports?tab=ads_report') ?>" class="report-tab-pill <?= $activeTab === 'ads_report' ? 'active' : '' ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            <span>Report Ads (Standar Kalamedia)</span>
            <span style="font-size: 11px; padding: 2px 7px; border-radius: 10px; background: <?= $activeTab === 'ads_report' ? 'rgba(255,255,255,0.2)' : '#F3F4F6' ?>;"><?= count($reports) ?></span>
          </a>

          <?php if (!$isAdsSpecialistUser || $isOwnerOrAdmin): ?>
            <a href="<?= url('reports?tab=decks') ?>" class="report-tab-pill <?= $activeTab === 'decks' ? 'active' : '' ?>">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
              <span>Deck Pitch &amp; Performance</span>
            </a>
          <?php endif; ?>

          <?php if ($isOwnerOrAdmin || $isCreativeSpecialistUser): ?>
            <a href="<?= url('reports?tab=work_recap') ?>" class="report-tab-pill <?= $activeTab === 'work_recap' ? 'active' : '' ?>">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              <span>Rekap Pekerjaan &amp; Produktivitas Tim</span>
            </a>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($activeTab === 'work_recap' && ($isOwnerOrAdmin || $isCreativeSpecialistUser)): ?>
          <!-- ================================================================= -->
          <!-- SUB-PANEL: REKAP PEKERJAAN & PRODUKTIVITAS TIM KREATIF -->
          <!-- ================================================================= -->
          <div class="glass-panel" style="padding: 24px; margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px;">
              <div>
                <h3 style="font-size: 16px; font-weight: 800; color: #111827; margin: 0;">
                  Rekap Produktivitas Desain, Video &amp; Tim Kreatif
                </h3>
                <p style="font-size: 12.5px; color: #6B7280; margin: 4px 0 0;">
                  100% data riil dihitung otomatis dari penugasan tugas (`tasks`), approval klien, dan deliverables tanpa angka hardcode.
                </p>
              </div>

              <button type="button" class="btn btn-secondary btn-sm" onclick="exportWorkRecapPdf()" style="font-weight: 700; border-radius: 8px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                <span>Cetak / Ekspor PDF Report</span>
              </button>
            </div>

            <!-- Filter Controls for Work Recap -->
            <div style="background: #F9FAFB; border: 1px solid #ECEEF5; border-radius: 14px; padding: 14px 16px; margin-bottom: 20px; display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
              <!-- Month Filter -->
              <div style="min-width: 150px;">
                <label style="font-size: 11px; font-weight: 700; color: #4B5563; text-transform: uppercase; display: block; margin-bottom: 4px;">Bulan</label>
                <select id="recap-filter-month" class="form-select" onchange="fetchAdminWorkRecap()">
                  <option value="">Semua Bulan</option>
                  <option value="<?= date('Y-m') ?>" selected>Bulan Ini (<?= date('M Y') ?>)</option>
                  <option value="<?= date('Y-m', strtotime('-1 month')) ?>">Bulan Lalu (<?= date('M Y', strtotime('-1 month')) ?>)</option>
                </select>
              </div>

              <!-- Client / Project Filter -->
              <div style="min-width: 180px;">
                <label style="font-size: 11px; font-weight: 700; color: #4B5563; text-transform: uppercase; display: block; margin-bottom: 4px;">Klien / Proyek</label>
                <select id="recap-filter-project" class="form-select" onchange="fetchAdminWorkRecap()">
                  <option value="">Semua Proyek PT</option>
                  <?php foreach ($formProjects as $p): ?>
                    <option value="<?= $p['id'] ?>">
                      <?= htmlspecialchars($p['client_company']) ?> &bull; <?= htmlspecialchars($p['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- Service Type Filter -->
              <div style="min-width: 160px;">
                <label style="font-size: 11px; font-weight: 700; color: #4B5563; text-transform: uppercase; display: block; margin-bottom: 4px;">Jenis Layanan</label>
                <select id="recap-filter-service" class="form-select" onchange="fetchAdminWorkRecap()">
                  <option value="all">Semua Layanan</option>
                  <option value="sosmed">Kelola Sosmed (Reguler)</option>
                  <option value="eceran">Desain Eceran / Satuan</option>
                  <option value="web">Jasa Buat Website</option>
                  <option value="ads">Jasa Iklan Meta &amp; Google</option>
                </select>
              </div>

              <?php if ($isOwnerOrAdmin): ?>
                <!-- Worker Filter for Owner -->
                <div style="min-width: 180px;">
                  <label style="font-size: 11px; font-weight: 700; color: #4B5563; text-transform: uppercase; display: block; margin-bottom: 4px;">Karyawan / Freelancer</label>
                  <select id="recap-filter-worker" class="form-select" onchange="fetchAdminWorkRecap()">
                    <option value="">Semua Tim Kalamedia</option>
                  </select>
                </div>
              <?php endif; ?>

              <div style="margin-top: 18px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="fetchAdminWorkRecap()">Segarkan</button>
              </div>
            </div>

            <!-- Dynamic 4 KPI Summary Cards -->
            <div class="kpi-grid" style="grid-template-columns: repeat(4, 1fr) !important; gap: 14px; margin-bottom: 22px;">
              <div class="kpi-card" style="border-top: 3px solid #2563EB;">
                <div class="kpi-header">
                  <span class="kpi-title" style="color: #2563EB; font-weight: 700;">Total Pekerjaan Dikerjakan</span>
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
                </div>
                <div class="kpi-value" id="recap-stat-total" style="font-size: 24px; color: #111827;">0</div>
                <div class="kpi-meta">Seluruh item kerjaan periode ini</div>
              </div>

              <div class="kpi-card" style="border-top: 3px solid #059669;">
                <div class="kpi-header">
                  <span class="kpi-title" style="color: #059669; font-weight: 700;">Selesai Disetujui (Approved)</span>
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <div class="kpi-value" id="recap-stat-approved" style="font-size: 24px; color: #059669;">0</div>
                <div class="kpi-meta">Diterima &amp; divalidasi klien</div>
              </div>

              <div class="kpi-card" style="border-top: 3px solid #F59E0B;">
                <div class="kpi-header">
                  <span class="kpi-title" style="color: #D97706; font-weight: 700;">Sedang Dikerjakan / Review</span>
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="kpi-value" id="recap-stat-progress" style="font-size: 24px; color: #D97706;">0</div>
                <div class="kpi-meta">Proses kreatif &amp; review klien</div>
              </div>

              <div class="kpi-card" style="border-top: 3px solid #DC2626;">
                <div class="kpi-header">
                  <span class="kpi-title" style="color: #DC2626; font-weight: 700;">Revisi (Revision)</span>
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <div class="kpi-value" id="recap-stat-revision" style="font-size: 24px; color: #DC2626;">0</div>
                <div class="kpi-meta">Perbaikan masukan klien</div>
              </div>
            </div>

            <!-- Detailed Work Log Table -->
            <div id="printable-work-recap-table" class="kala-table-scroll table-responsive" style="width: 100%; overflow-x: auto;">
              <table class="table-custom" style="min-width: 900px; width: 100%;">
                <thead>
                  <tr>
                    <th>TANGGAL</th>
                    <th>KLIEN &amp; PROYEK</th>
                    <th>JUDUL &amp; RINCIAN TUGAS</th>
                    <th>KATEGORI OUTPUT</th>
                    <th>TALENT / WORKER</th>
                    <th>LINK HASIL KERJA</th>
                    <th>STATUS &amp; APPROVAL</th>
                  </tr>
                </thead>
                <tbody id="recap-tasks-tbody">
                  <tr>
                    <td colspan="7" style="text-align: center; padding: 32px; color: #6B7280;">
                      Memuat data rekap pekerjaan...
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

        <?php else: ?>
          <!-- ================================================================= -->
          <!-- SUB-PANEL: REPORT ADS & DECK PERFORMANCE MARKETING -->
          <!-- ================================================================= -->

          <!-- 4 KPI Summary Cards for Performance Ads (Appify 2x2 Grid on Mobile, 4-Cols on Desktop) -->
          <div class="report-ads-kpi-grid">
            <!-- 1. Total Spend -->
            <div style="background: #FFFFFF; border-radius: 18px; border: 1px solid #F0F1F7; padding: 14px 16px; box-shadow: 0 4px 20px rgba(26, 22, 43, 0.04); display: flex; flex-direction: column; justify-content: space-between;">
              <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                <span style="font-size: 12px; font-weight: 700; color: #7B7890; white-space: nowrap;">Total Spend</span>
                <div style="width: 28px; height: 28px; border-radius: 8px; background: #F2EFFF; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#5B3DF5" stroke-width="2.2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
              </div>
              <div style="font-size: 17px; font-weight: 800; color: #1A162B; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                Rp <?= number_format($totalSpend, 0, ',', '.') ?>
              </div>
              <div style="font-size: 11px; color: #7B7890; margin-top: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <?= count($reports) ?> Dokumen Laporan Terbit
              </div>
            </div>

            <!-- 2. Impressions -->
            <div style="background: #FFFFFF; border-radius: 18px; border: 1px solid #F0F1F7; padding: 14px 16px; box-shadow: 0 4px 20px rgba(26, 22, 43, 0.04); display: flex; flex-direction: column; justify-content: space-between;">
              <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                <span style="font-size: 12px; font-weight: 700; color: #7B7890; white-space: nowrap;">Impressions</span>
                <div style="width: 28px; height: 28px; border-radius: 8px; background: #EEF2FF; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2.2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </div>
              </div>
              <div style="font-size: 17px; font-weight: 800; color: #1A162B; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <?= number_format($totalImpressions, 0, ',', '.') ?>
              </div>
              <div style="font-size: 11px; color: #7B7890; margin-top: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                Reach: <?= number_format($stats['total_reach'] ?? 0) ?> orang
              </div>
            </div>

            <!-- 3. Total Klik -->
            <div style="background: #FFFFFF; border-radius: 18px; border: 1px solid #F0F1F7; padding: 14px 16px; box-shadow: 0 4px 20px rgba(26, 22, 43, 0.04); display: flex; flex-direction: column; justify-content: space-between;">
              <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                <span style="font-size: 12px; font-weight: 700; color: #7B7890; white-space: nowrap;">Total Klik</span>
                <div style="width: 28px; height: 28px; border-radius: 8px; background: #E8F8F2; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2.2"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
                </div>
              </div>
              <div style="font-size: 17px; font-weight: 800; color: #1A162B; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <?= number_format($totalClicks) ?> Klik
              </div>
              <div style="font-size: 11px; color: #7B7890; margin-top: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                CTR: <b><?= number_format($avgCtr, 2) ?>%</b>
              </div>
            </div>

            <!-- 4. Total Leads -->
            <div style="background: #FFFFFF; border-radius: 18px; border: 1px solid #F0F1F7; padding: 14px 16px; box-shadow: 0 4px 20px rgba(26, 22, 43, 0.04); display: flex; flex-direction: column; justify-content: space-between;">
              <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                <span style="font-size: 12px; font-weight: 700; color: #7B7890; white-space: nowrap;">Total Leads</span>
                <div style="width: 28px; height: 28px; border-radius: 8px; background: #FFF4E5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                </div>
              </div>
              <div style="font-size: 17px; font-weight: 800; color: #1A162B; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <?= number_format($totalConversions) ?> Leads
              </div>
              <div style="font-size: 11px; color: #7B7890; margin-top: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                Avg CPA: Rp <?= number_format($avgCplCpa, 0, ',', '.') ?>
              </div>
            </div>
          </div>

          <!-- Main Table / Card Container -->
          <div class="glass-panel" style="padding: 20px; background: #FFFFFF; border-radius: 20px; border: 1px solid #F0F1F7; box-shadow: 0 4px 20px rgba(26, 22, 43, 0.04);">
            <!-- Filter Bar Form -->
            <form method="GET" action="<?= url('reports') ?>" class="report-filter-form" style="margin-bottom: 18px;">
              <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">

              <div style="flex: 1; width: 100%;">
                <input type="text" name="search" class="form-control" placeholder="Cari brand, objektif, atau ide materi..." value="<?= htmlspecialchars($searchQuery) ?>" style="border-radius: 12px; border: 1px solid #F0F1F7; background: #F7F8FC; padding: 10px 14px; font-size: 13px;">
              </div>

              <div class="report-filter-row-2col">
                <div style="flex: 1; min-width: 0;">
                  <select name="client_id" class="form-select" onchange="this.form.submit()" style="border-radius: 12px; border: 1px solid #F0F1F7; background: #F7F8FC; padding: 9px 12px; font-size: 12.5px; width: 100%;">
                    <option value="">Semua Klien PT</option>
                    <?php foreach ($allClients as $cl): ?>
                      <option value="<?= $cl['id'] ?>" <?= $selectedClient == $cl['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cl['company']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div style="flex: 1; min-width: 0;">
                  <select name="platform" class="form-select" onchange="this.form.submit()" style="border-radius: 12px; border: 1px solid #F0F1F7; background: #F7F8FC; padding: 9px 12px; font-size: 12.5px; width: 100%;">
                    <option value="all">Semua Platform</option>
                    <option value="Meta Ads" <?= $selectedPlatform === 'Meta Ads' ? 'selected' : '' ?>>Meta Ads</option>
                    <option value="Google Ads" <?= $selectedPlatform === 'Google Ads' ? 'selected' : '' ?>>Google Ads</option>
                    <option value="TikTok Ads" <?= $selectedPlatform === 'TikTok Ads' ? 'selected' : '' ?>>TikTok Ads</option>
                  </select>
                </div>
              </div>

              <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-secondary btn-sm" style="border-radius: 10px; padding: 9px 16px; font-weight: 600;">Filter</button>
                <?php if ($selectedClient > 0 || !empty($searchQuery) || $selectedPlatform !== 'all'): ?>
                  <a href="<?= url('reports?tab=' . $activeTab) ?>" class="btn btn-secondary btn-sm" style="border-radius: 10px; color: var(--danger-text); padding: 9px 14px;">Reset</a>
                <?php endif; ?>
              </div>
            </form>

            <!-- 1. Mobile Cards List (Shown on Mobile, Hidden on Desktop) -->
            <div class="report-ads-mobile-list" style="width: 100%; box-sizing: border-box;">
              <?php if (empty($reports)): ?>
                <div style="text-align: center; padding: 36px 16px; color: #64748B; background: #FFFFFF; border-radius: 18px; border: 1px solid #F1F5F9;">
                  <div style="font-weight: 700; font-size: 15px; color: #0F172A; margin-bottom: 6px;">
                    Belum Ada Laporan Report Ads
                  </div>
                  <p style="font-size: 13px; margin: 0 0 16px;">
                    Mulai buat laporan performa iklan untuk klien Anda.
                  </p>
                  <button type="button" class="btn btn-primary btn-sm" onclick="openNewAdsReportModal()" style="border-radius: 10px; background: #5B3DF5; border: none; padding: 8px 16px;">
                    + Buat Report Ads Pertama
                  </button>
                </div>
              <?php else: ?>
                <?php foreach ($reports as $r): 
                  $pName = $r['platform'] ?: 'Meta Ads';
                  $chipBg = (stripos($pName, 'google') !== false) ? '#FEF9C3' : ((stripos($pName, 'tiktok') !== false) ? '#F3E8FF' : '#EFF6FF');
                  $chipColor = (stripos($pName, 'google') !== false) ? '#854D0E' : ((stripos($pName, 'tiktok') !== false) ? '#6B21A8' : '#1D4ED8');
                ?>
                  <div class="appify-tx-card" style="background: #FFFFFF; border-radius: 18px; border: 1px solid #F1F5F9; padding: 14px; box-shadow: 0 1px 3px rgba(15,23,42,0.03); width: 100%; max-width: 100%; box-sizing: border-box; overflow: hidden; margin-bottom: 10px;">
                    <!-- Baris Atas: Avatar PR + Nama Klien + Badge Meta Ads & Oktober 2026 -->
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; width: 100%; box-sizing: border-box;">
                      <div style="display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1;">
                        <div style="width: 38px; height: 38px; border-radius: 10px; background: #EEF2FF; color: #4F46E5; font-weight: 800; font-size: 13px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                          <?= strtoupper(substr($r['client_company'] ?: $r['client_name'], 0, 2)) ?>
                        </div>
                        <div style="min-width: 0; flex: 1;">
                          <div style="font-weight: 700; color: #0F172A; font-size: 13.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?= htmlspecialchars($r['client_company']) ?>
                          </div>
                          <div style="display: flex; align-items: center; gap: 6px; margin-top: 2px; flex-wrap: wrap;">
                            <span style="font-size: 10.5px; font-weight: 700; padding: 2px 7px; border-radius: 6px; background: <?= $chipBg ?>; color: <?= $chipColor ?>;">
                              <?= htmlspecialchars($pName) ?>
                            </span>
                            <span style="font-size: 11px; color: #64748B;">
                              &bull; <?= htmlspecialchars($r['report_period']) ?>
                            </span>
                            <?php if (!empty($r['objective'])): ?>
                              <span style="font-size: 10px; font-weight: 600; padding: 1px 6px; border-radius: 4px; background: #F1F5F9; color: #475569;">
                                <?= htmlspecialchars($r['objective']) ?>
                              </span>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Baris Tengah: Grid 2x2 (Total Spend | Impressions | Clicks (CTR) | Results / Leads) -->
                    <div style="background: #F8FAFC; border: 1px solid #F1F5F9; border-radius: 12px; padding: 10px 12px; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 10px; margin-bottom: 10px; width: 100%; box-sizing: border-box; overflow: hidden;">
                      <div style="min-width: 0;">
                        <div style="font-size: 10px; font-weight: 600; color: #64748B; text-transform: uppercase;">Total Spend</div>
                        <div style="font-size: 13px; font-weight: 800; color: #0F172A; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                          Rp <?= number_format($r['total_ad_spend'], 0, ',', '.') ?>
                        </div>
                      </div>
                      <div style="min-width: 0;">
                        <div style="font-size: 10px; font-weight: 600; color: #64748B; text-transform: uppercase;">Impressions</div>
                        <div style="font-size: 12px; font-weight: 700; color: #0F172A; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                          <?= number_format($r['ads_impressions']) ?> views
                        </div>
                      </div>
                      <div style="min-width: 0;">
                        <div style="font-size: 10px; font-weight: 600; color: #64748B; text-transform: uppercase;">Clicks (CTR)</div>
                        <div style="font-size: 12px; font-weight: 700; color: #4F46E5; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                          <?= number_format($r['total_clicks']) ?> (<?= number_format($r['ads_ctr'], 2) ?>%)
                        </div>
                      </div>
                      <div style="min-width: 0;">
                        <div style="font-size: 10px; font-weight: 600; color: #64748B; text-transform: uppercase;">Results / Leads</div>
                        <div style="font-size: 13px; font-weight: 800; color: #10B981; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                          <?= number_format($r['total_conversions']) ?> <span style="font-size: 10.5px; font-weight: 500; color: #64748B;">(Rp <?= number_format($r['cpl_cpa'], 0, ',', '.') ?>)</span>
                        </div>
                      </div>
                    </div>

                    <!-- Baris Bawah: Tombol Aksi (Lihat Detail / PDF & Edit) -->
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; border-top: 1px solid #F1F5F9; padding-top: 10px; width: 100%; box-sizing: border-box;">
                      <div style="font-size: 11px; color: #64748B; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0;">
                        Oleh: <b><?= htmlspecialchars($r['author_name'] ?: 'Tim Ads') ?></b>
                      </div>
                      <div style="display: flex; gap: 6px; flex-shrink: 0;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick='viewAdsReportDetail(<?= json_encode($r) ?>)' style="height: 32px; padding: 0 10px; border-radius: 8px; font-size: 11.5px; font-weight: 600; background: #EEF2FF; color: #4F46E5; border: 1px solid #E0E7FF; cursor: pointer;">
                          Detail / PDF
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick='openEditAdsReportModal(<?= json_encode($r) ?>)' style="height: 32px; padding: 0 10px; border-radius: 8px; font-size: 11.5px; font-weight: 600; background: #FFFFFF; color: #334155; border: 1px solid #E2E8F0; cursor: pointer;">
                          Edit
                        </button>
                        <?php if ($isOwnerOrAdmin || ($isAdsSpecialistUser && (int)$r['created_by_user_id'] === $userId)): ?>
                          <button type="button" class="btn btn-secondary btn-sm" onclick="deleteReport(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['client_company'])) ?>', '<?= htmlspecialchars($r['report_period']) ?>')" style="height: 32px; padding: 0 8px; border-radius: 8px; font-size: 11.5px; font-weight: 600; background: #FEF2F2; color: #EF4444; border: 1px solid #FEE2E2; cursor: pointer;">
                            Hapus
                          </button>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <!-- 2. Desktop Table of Ads Reports (Shown on Desktop, Hidden on Mobile) -->
            <div class="report-ads-desktop-table kala-table-scroll table-responsive" style="width: 100%; overflow-x: auto;">
              <table class="table-custom" style="min-width: 950px; width: 100%;">
                <thead>
                  <tr>
                    <th>KLIEN &amp; PLATFORM</th>
                    <th>PERIODE &amp; OBJEKTIF</th>
                    <th>SPEND &amp; HASIL / CPA</th>
                    <th>IMPRESSIONS, CLICKS &amp; CTR</th>
                    <th>WINNING CREATIVE &amp; CATATAN</th>
                    <th>AUTHOR / SPESIALIS</th>
                    <th style="text-align: right; min-width: 160px;">AKSI</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($reports)): ?>
                    <tr>
                      <td colspan="7" style="text-align: center; padding: 48px 16px; color: #6B7280;">
                        <div style="font-weight: 700; font-size: 15px; color: #111827; margin-bottom: 6px;">
                          Belum Ada Laporan Report Ads
                        </div>
                        <p style="font-size: 13px; max-width: 480px; margin: 0 auto 16px;">
                          Mulai buat laporan performa iklan untuk klien Anda. Laporan akan otomatis tampil di Dashboard Owner dan Portal Klien.
                        </p>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openNewAdsReportModal()" style="border-radius: 10px; background: #5B3DF5; border: none;">
                          + Buat Report Ads Pertama
                        </button>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($reports as $r): 
                      $pName = $r['platform'] ?: 'Meta Ads';
                      $chipClass = (stripos($pName, 'google') !== false) ? 'chip-platform-google' : ((stripos($pName, 'tiktok') !== false) ? 'chip-platform-tiktok' : 'chip-platform-meta');
                    ?>
                      <tr>
                        <!-- Client Brand & Platform -->
                        <td>
                          <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 36px; height: 36px; border-radius: 10px; background: #EFF6FF; border: 1px solid #DBEAFE; color: #2563EB; font-weight: 800; font-size: 13px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                              <?= strtoupper(substr($r['client_company'] ?: $r['client_name'], 0, 2)) ?>
                            </div>
                            <div>
                              <div style="font-weight: 700; color: #111827; font-size: 13.5px;">
                                <?= htmlspecialchars($r['client_company']) ?>
                              </div>
                              <div style="margin-top: 3px;">
                                <span class="metric-chip <?= $chipClass ?>"><?= htmlspecialchars($pName) ?></span>
                              </div>
                            </div>
                          </div>
                        </td>

                        <!-- Periode & Objektif -->
                        <td>
                          <div style="font-weight: 700; color: #111827; font-size: 12.5px;">
                            <?= htmlspecialchars($r['report_period']) ?>
                          </div>
                          <span style="display: inline-block; background: #F3F4F6; color: #374151; font-size: 11px; font-weight: 600; padding: 2px 7px; border-radius: 5px; margin-top: 3px;">
                            <?= htmlspecialchars($r['objective'] ?: 'Leads / Sales') ?>
                          </span>
                        </td>

                        <!-- Spend & Conversions / CPA -->
                        <td>
                          <div style="font-weight: 800; color: #111827; font-size: 13px;">
                            Rp <?= number_format($r['total_ad_spend'], 0, ',', '.') ?>
                          </div>
                          <div style="font-size: 11.5px; color: #059669; font-weight: 700; margin-top: 2px;">
                            <?= number_format($r['total_conversions']) ?> Results (CPA: Rp <?= number_format($r['cpl_cpa'], 0, ',', '.') ?>)
                          </div>
                        </td>

                        <!-- Impressions, Clicks & CTR -->
                        <td>
                          <div style="font-size: 12.5px; color: #374151; font-weight: 600;">
                            <?= number_format($r['ads_impressions']) ?> views &bull; <?= number_format($r['total_clicks']) ?> klik
                          </div>
                          <div style="font-size: 11px; color: #2563EB; font-weight: 700; margin-top: 2px;">
                            CTR: <?= number_format($r['ads_ctr'], 2) ?>%
                            <?php if ($r['roas'] > 0): ?>
                              &bull; ROAS: <?= number_format($r['roas'], 2) ?>x
                            <?php endif; ?>
                          </div>
                        </td>

                        <!-- Winning Creative & Screenshot -->
                        <td>
                          <?php if (!empty($r['winning_creative_notes']) || !empty($r['what_worked'])): ?>
                            <div style="font-size: 12px; color: #111827; max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($r['winning_creative_notes'] ?: $r['what_worked']) ?>">
                              🌟 <b>Winning:</b> <?= htmlspecialchars($r['winning_creative_notes'] ?: $r['what_worked']) ?>
                            </div>
                          <?php endif; ?>
                          <?php if (!empty($r['dashboard_screenshot'])): ?>
                            <a href="<?= url($r['dashboard_screenshot']) ?>" target="_blank" style="font-size: 11px; color: #2563EB; font-weight: 600; text-decoration: underline; display: inline-flex; align-items: center; gap: 3px; margin-top: 2px;">
                              🖼️ Lihat Bukti Dashboard
                            </a>
                          <?php endif; ?>
                        </td>

                        <!-- Author -->
                        <td>
                          <div style="font-size: 12px; font-weight: 700; color: #374151;">
                            <?= htmlspecialchars($r['author_name'] ?: 'Tim Ads') ?>
                          </div>
                          <div style="font-size: 11px; color: #9CA3AF;">
                            <?= date('d M Y', strtotime($r['created_at'])) ?>
                          </div>
                        </td>

                        <!-- Actions -->
                        <td style="text-align: right; white-space: nowrap;">
                          <div class="table-actions" style="justify-content: flex-end;">
                            <button type="button" class="btn-action-view" onclick='viewAdsReportDetail(<?= json_encode($r) ?>)' title="Lihat Detail & Cetak PDF">
                              Lihat Detail
                            </button>

                            <a href="<?= url("report-deck?id={$r['id']}") ?>" class="btn-action-view" title="Buka Pitch Deck" style="background: #F3F4F6; color: #374151;">
                              Deck
                            </a>

                            <?php if ($isOwnerOrAdmin || ($isAdsSpecialistUser && (int)$r['created_by_user_id'] === $userId)): ?>
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
        <?php endif; ?>

      </div>
    </main>
  </div>

  <!-- ========================================================================= -->
  <!-- MODAL 1: FORM & TEMPLATE STANDAR REPORT ADS KALAMEDIA -->
  <!-- ========================================================================= -->
  <div id="modal-ads-report-form" class="kala-modal-backdrop" style="display: none;">
    <div class="kala-modal-dialog">
      <div class="kala-modal-header">
        <div>
          <h3 style="font-size: 17px; font-weight: 800; color: #111827; margin: 0;">
            Form &amp; Template Standar Report Ads Kalamedia
          </h3>
          <p style="font-size: 12.5px; color: #6B7280; margin: 2px 0 0;">
            Laporan otomatis tersinkronisasi ke Dashboard Owner dan Portal Klien PT terkait.
          </p>
        </div>
        <button type="button" class="pwa-banner-close" onclick="closeAdsReportModal()" style="font-size: 22px; cursor: pointer; border: none; background: none;">&times;</button>
      </div>

      <form id="form-create-ads-report" onsubmit="submitAdsReport(event)">
        <input type="hidden" name="id" id="form-ads-id" value="0">
        <input type="hidden" name="client_id" id="form-ads-client-id" value="0">

        <div class="kala-modal-body">
          <!-- 1. Pilih Proyek & Klien PT -->
          <div style="background: #F8FAFC; border: 1.5px solid #E2E8F0; border-radius: 12px; padding: 14px 16px; margin-bottom: 18px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
              <label style="font-size: 12.5px; font-weight: 800; color: #1E293B;">
                1. Pilih Klien / Proyek Kampanye PT <span style="color: #DC2626;">*</span>
              </label>
              <button type="button" class="btn btn-secondary btn-sm" onclick="autoPullProjectAdsSpend()" style="font-size: 11px; padding: 3px 8px; border-radius: 6px;">
                ⚡ Tarik Realisasi Spend Proyek
              </button>
            </div>
            <select name="project_id" id="form-ads-project-id" class="form-select" onchange="onReportProjectChange(this.value)" required style="font-weight: 600;">
              <option value="">-- Pilih Proyek Iklan Klien --</option>
              <?php foreach ($formProjects as $pr): ?>
                <option value="<?= $pr['id'] ?>" data-client-id="<?= $pr['client_id'] ?>" data-client-company="<?= htmlspecialchars($pr['client_company']) ?>">
                  <?= htmlspecialchars($pr['client_company']) ?> &bull; <?= htmlspecialchars($pr['name']) ?> (<?= htmlspecialchars($pr['service_type'] ?: 'Iklan') ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- 2. Periode & Platform & Objektif -->
          <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 18px;">
            <div>
              <label style="font-size: 12px; font-weight: 700; color: #374151; display: block; margin-bottom: 5px;">
                Periode Laporan <span style="color: #DC2626;">*</span>
              </label>
              <input type="text" name="report_period" id="form-ads-period" class="form-control" placeholder="Contoh: 1 - 31 Oktober 2026" required value="<?= date('1 - t M Y') ?>">
            </div>

            <div>
              <label style="font-size: 12px; font-weight: 700; color: #374151; display: block; margin-bottom: 5px;">
                Platform Iklan <span style="color: #DC2626;">*</span>
              </label>
              <select name="platform" id="form-ads-platform" class="form-select" required>
                <option value="Meta Ads">Meta Ads (Instagram &amp; FB)</option>
                <option value="Google Ads">Google Ads (Search &amp; YT)</option>
                <option value="TikTok Ads">TikTok Ads</option>
              </select>
            </div>

            <div>
              <label style="font-size: 12px; font-weight: 700; color: #374151; display: block; margin-bottom: 5px;">
                Objektif Kampanye <span style="color: #DC2626;">*</span>
              </label>
              <select name="objective" id="form-ads-objective" class="form-select" required>
                <option value="Leads / WhatsApp">Leads / WhatsApp (Pesan Masuk)</option>
                <option value="Sales / Konversi Web">Sales / Konversi Website (Purchase)</option>
                <option value="Traffic Website">Traffic / Kunjungan Website (LP)</option>
                <option value="Brand Awareness / Reach">Brand Awareness &amp; Reach</option>
              </select>
            </div>
          </div>

          <!-- 3. Metrik Template Report Ads (Dihitung Otomatis) -->
          <div style="border: 1px solid #E5E7EB; border-radius: 14px; padding: 16px; margin-bottom: 18px; background: #FFFFFF;">
            <div style="font-size: 12.5px; font-weight: 800; color: #111827; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
              <span>📊 Metrik Performa Iklan (Otomatis Menghitung CTR &amp; CPA/CPL)</span>
            </div>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 12px;">
              <!-- 1. Total Spend -->
              <div>
                <label style="font-size: 12px; font-weight: 700; color: #374151; display: block; margin-bottom: 4px;">
                  1. Total Spend (Rp) <span style="color: #DC2626;">*</span>
                </label>
                <input type="number" step="1" name="total_ad_spend" id="form-ads-spend" class="form-control" placeholder="Rp 0" required oninput="calculateAdsMetrics()">
              </div>

              <!-- 2. Impressions -->
              <div>
                <label style="font-size: 12px; font-weight: 700; color: #374151; display: block; margin-bottom: 4px;">
                  2. Impressions (Tayangan)
                </label>
                <input type="number" name="ads_impressions" id="form-ads-impressions" class="form-control" placeholder="0" oninput="calculateAdsMetrics()">
              </div>

              <!-- 3. Reach -->
              <div>
                <label style="font-size: 12px; font-weight: 700; color: #374151; display: block; margin-bottom: 4px;">
                  3. Reach (Jangkauan Orang)
                </label>
                <input type="number" name="ads_reach" id="form-ads-reach" class="form-control" placeholder="0">
              </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 12px;">
              <!-- 4. Clicks -->
              <div>
                <label style="font-size: 12px; font-weight: 700; color: #374151; display: block; margin-bottom: 4px;">
                  4. Clicks (Jumlah Klik)
                </label>
                <input type="number" name="total_clicks" id="form-ads-clicks" class="form-control" placeholder="0" oninput="calculateAdsMetrics()">
              </div>

              <!-- 5. CTR Auto-calculated -->
              <div>
                <label style="font-size: 12px; font-weight: 700; color: #1D4ED8; display: block; margin-bottom: 4px;">
                  5. CTR (%) &bull; Otomatis
                </label>
                <input type="text" name="ads_ctr" id="form-ads-ctr" class="form-control" placeholder="0.00 %" readonly style="background: #EFF6FF; font-weight: 800; color: #1D4ED8;">
              </div>

              <!-- 6. Total Results -->
              <div>
                <label style="font-size: 12px; font-weight: 700; color: #374151; display: block; margin-bottom: 4px;">
                  6. Total Results / Leads <span style="color: #DC2626;">*</span>
                </label>
                <input type="number" name="total_conversions" id="form-ads-results" class="form-control" placeholder="0" required oninput="calculateAdsMetrics()">
              </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
              <!-- 7. Cost Per Result / CPA / CPL -->
              <div>
                <label style="font-size: 12px; font-weight: 700; color: #047857; display: block; margin-bottom: 4px;">
                  7. Cost Per Result / CPA (Rp) &bull; Otomatis
                </label>
                <input type="text" name="cpl_cpa" id="form-ads-cpa" class="form-control" placeholder="Rp 0 / result" readonly style="background: #ECFDF5; font-weight: 800; color: #047857;">
              </div>

              <!-- 8. ROAS -->
              <div>
                <label style="font-size: 12px; font-weight: 700; color: #374151; display: block; margin-bottom: 4px;">
                  8. ROAS / Nilai Balik (Opsional)
                </label>
                <input type="number" step="0.01" name="roas" id="form-ads-roas" class="form-control" placeholder="Contoh: 3.5">
              </div>
            </div>
          </div>

          <!-- 4. Winning Creative & Optimasi -->
          <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; margin-bottom: 18px;">
            <div>
              <label style="font-size: 12px; font-weight: 700; color: #374151; display: block; margin-bottom: 4px;">
                🌟 Winning Creative / Iklan Terbaik <span style="color: #DC2626;">*</span>
              </label>
              <textarea name="winning_creative_notes" id="form-ads-winning" class="form-control" rows="3" placeholder="Tuliskan format iklan, materi hook, atau sudut pandang yang menghasilkan performa tertinggi..." required></textarea>
            </div>

            <div>
              <label style="font-size: 12px; font-weight: 700; color: #374151; display: block; margin-bottom: 4px;">
                📈 Evaluasi &amp; Rencana Optimasi Selanjutnya <span style="color: #DC2626;">*</span>
              </label>
              <textarea name="optimizing_plan_notes" id="form-ads-optimizing" class="form-control" rows="3" placeholder="Rencana testing audience baru, scaling budget, penyesuaian copywriting, dsb..." required></textarea>
            </div>
          </div>

          <!-- 5. Upload Screenshot Bukti Dashboard Ads Manager -->
          <div>
            <label style="font-size: 12px; font-weight: 700; color: #374151; display: block; margin-bottom: 4px;">
              📸 Upload Screenshot Bukti Dashboard Ads Manager (Meta / Google / TikTok)
            </label>
            <input type="file" name="dashboard_screenshot" id="form-ads-file" class="form-control" accept="image/*" onchange="previewAdsScreenshot(this)">
            <input type="hidden" name="existing_dashboard_screenshot" id="form-ads-existing-screenshot" value="">
            <div id="form-ads-screenshot-preview" style="margin-top: 8px; display: none;">
              <img src="" alt="Screenshot Ads" style="max-height: 120px; border-radius: 8px; border: 1px solid #E5E7EB;">
            </div>
          </div>
        </div>

        <div class="kala-modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeAdsReportModal()">Batal</button>
          <button type="submit" id="btn-submit-ads-report" class="btn btn-primary" style="font-weight: 800;">
            <span>🚀 Simpan &amp; Terbitkan Laporan</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ========================================================================= -->
  <!-- MODAL 2: DETAIL REPORT ADS VIEW (PRINTABLE & VISUAL CARD DECK) -->
  <!-- ========================================================================= -->
  <div id="modal-ads-report-detail" class="kala-modal-backdrop" style="display: none;">
    <div class="kala-modal-dialog" style="max-width: 780px;">
      <div class="kala-modal-header no-print">
        <div style="display: flex; align-items: center; gap: 8px;">
          <span style="font-size: 20px;">📄</span>
          <div>
            <h3 style="font-size: 16px; font-weight: 800; color: #111827; margin: 0;">Detail Dokumen Report Ads Kalamedia</h3>
            <span id="detail-modal-subtitle" style="font-size: 12px; color: #6B7280;"></span>
          </div>
        </div>
        <button type="button" class="pwa-banner-close" onclick="closeAdsDetailModal()" style="font-size: 22px; cursor: pointer; border: none; background: none;">&times;</button>
      </div>

      <div class="kala-modal-body" id="printable-report-area">
        <!-- Visual Presentation of Ads Report -->
        <div style="border-bottom: 2px solid #F3F4F6; padding-bottom: 16px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px;">
          <div>
            <div style="font-size: 11px; font-weight: 800; color: #2563EB; text-transform: uppercase; letter-spacing: 0.5px;">LAPORAN PERFORMA ADS RESMI</div>
            <h2 id="view-detail-company" style="font-size: 20px; font-weight: 800; color: #111827; margin: 4px 0 2px;"></h2>
            <div id="view-detail-period-badge" style="font-size: 12.5px; color: #4B5563; font-weight: 600;"></div>
          </div>
          <div style="text-align: right;">
            <div style="font-size: 12px; color: #9CA3AF;">Agensi Pelaksana</div>
            <div style="font-size: 13px; font-weight: 800; color: #111827;">PT Kala Kausa Prima</div>
          </div>
        </div>

        <!-- Metric Grid -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px;">
          <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 12px;">
            <div style="font-size: 11px; font-weight: 700; color: #64748B;">TOTAL AD SPEND</div>
            <div id="view-detail-spend" style="font-size: 18px; font-weight: 800; color: #0F172A; margin-top: 2px;"></div>
          </div>
          <div style="background: #F0FDF4; border: 1px solid #DCFCE7; border-radius: 10px; padding: 12px;">
            <div style="font-size: 11px; font-weight: 700; color: #166534;">TOTAL RESULTS / LEADS</div>
            <div id="view-detail-results" style="font-size: 18px; font-weight: 800; color: #15803D; margin-top: 2px;"></div>
          </div>
          <div style="background: #EFF6FF; border: 1px solid #DBEAFE; border-radius: 10px; padding: 12px;">
            <div style="font-size: 11px; font-weight: 700; color: #1E40AF;">COST PER RESULT (CPA)</div>
            <div id="view-detail-cpa" style="font-size: 18px; font-weight: 800; color: #1D4ED8; margin-top: 2px;"></div>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 18px;">
          <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 10px; padding: 12px;">
            <div style="font-size: 11px; font-weight: 700; color: #6B7280;">IMPRESSIONS</div>
            <div id="view-detail-impressions" style="font-size: 15px; font-weight: 700; color: #111827; margin-top: 2px;"></div>
          </div>
          <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 10px; padding: 12px;">
            <div style="font-size: 11px; font-weight: 700; color: #6B7280;">CLICKS &amp; CTR</div>
            <div id="view-detail-clicks-ctr" style="font-size: 15px; font-weight: 700; color: #111827; margin-top: 2px;"></div>
          </div>
          <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 10px; padding: 12px;">
            <div style="font-size: 11px; font-weight: 700; color: #6B7280;">REACH (JANGKAUAN)</div>
            <div id="view-detail-reach" style="font-size: 15px; font-weight: 700; color: #111827; margin-top: 2px;"></div>
          </div>
        </div>

        <!-- Winning Creative Notes -->
        <div style="background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 12px; padding: 14px; margin-bottom: 14px;">
          <div style="font-size: 12px; font-weight: 800; color: #92400E; margin-bottom: 4px;">🌟 WINNING CREATIVE / IKLAN TERBAIK</div>
          <p id="view-detail-winning" style="font-size: 13px; color: #78350F; margin: 0; line-height: 1.5; white-space: pre-wrap;"></p>
        </div>

        <!-- Optimization Plan Notes -->
        <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 12px; padding: 14px; margin-bottom: 16px;">
          <div style="font-size: 12px; font-weight: 800; color: #166534; margin-bottom: 4px;">📈 EVALUASI &amp; RENCANA OPTIMASI SELANJUTNYA</div>
          <p id="view-detail-optimizing" style="font-size: 13px; color: #14532D; margin: 0; line-height: 1.5; white-space: pre-wrap;"></p>
        </div>

        <!-- Screenshot Container -->
        <div id="view-detail-screenshot-box" style="display: none; margin-bottom: 16px;">
          <div style="font-size: 12px; font-weight: 800; color: #374151; margin-bottom: 6px;">📸 BUKTI SCREENSHOT DASHBOARD ADS MANAGER</div>
          <div style="text-align: center; border: 1px solid #E5E7EB; border-radius: 12px; padding: 10px; background: #F8FAFC;">
            <img id="view-detail-screenshot-img" src="" alt="Screenshot" style="max-width: 100%; max-height: 420px; border-radius: 8px;">
          </div>
        </div>

        <div style="font-size: 11.5px; color: #9CA3AF; text-align: right; border-top: 1px solid #F3F4F6; padding-top: 10px;">
          Diterbitkan oleh <b id="view-detail-author"></b> &bull; Kalamedia System Verified
        </div>
      </div>

      <div class="kala-modal-footer no-print">
        <button type="button" class="btn btn-secondary" onclick="closeAdsDetailModal()">Tutup</button>
        <button type="button" class="btn btn-primary" onclick="window.print()" style="font-weight: 700;">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
          <span>Cetak / Download PDF</span>
        </button>
      </div>
    </div>
  </div>

  <script src="assets/js/app.js?v=bankio-typography-3.0"></script>
  <script>
    // -------------------------------------------------------------
    // JS HANDLERS: REPORT ADS TEMPLATE & MODAL
    // -------------------------------------------------------------
    function openNewAdsReportModal() {
      document.getElementById('form-create-ads-report').reset();
      document.getElementById('form-ads-id').value = '0';
      document.getElementById('form-ads-client-id').value = '0';
      document.getElementById('form-ads-existing-screenshot').value = '';
      document.getElementById('form-ads-screenshot-preview').style.display = 'none';
      const titleEl = document.querySelector('#modal-ads-report-form h3');
      if (titleEl) titleEl.innerText = 'Form & Template Standar Report Ads Kalamedia';
      document.getElementById('modal-ads-report-form').style.display = 'flex';
    }

    function openEditAdsReportModal(r) {
      document.getElementById('form-create-ads-report').reset();
      document.getElementById('form-ads-id').value = r.id || '0';
      document.getElementById('form-ads-client-id').value = r.client_id || '0';
      
      const pSel = document.getElementById('form-ads-project-id');
      if (pSel) pSel.value = r.project_id || '';
      
      if (document.getElementById('form-ads-period')) document.getElementById('form-ads-period').value = r.report_period || '';
      if (document.getElementById('form-ads-platform')) document.getElementById('form-ads-platform').value = r.platform || 'Meta Ads';
      if (document.getElementById('form-ads-objective')) document.getElementById('form-ads-objective').value = r.objective || 'Leads / WhatsApp';
      if (document.getElementById('form-ads-spend')) document.getElementById('form-ads-spend').value = r.total_ad_spend || 0;
      if (document.getElementById('form-ads-impressions')) document.getElementById('form-ads-impressions').value = r.ads_impressions || 0;
      if (document.getElementById('form-ads-reach')) document.getElementById('form-ads-reach').value = r.ads_reach || 0;
      if (document.getElementById('form-ads-clicks')) document.getElementById('form-ads-clicks').value = r.total_clicks || 0;
      if (document.getElementById('form-ads-results')) document.getElementById('form-ads-results').value = r.total_conversions || 0;
      if (document.getElementById('form-ads-roas')) document.getElementById('form-ads-roas').value = r.roas || 0;
      if (document.getElementById('form-ads-revenue')) document.getElementById('form-ads-revenue').value = r.revenue || 0;
      if (document.getElementById('form-ads-winning')) document.getElementById('form-ads-winning').value = r.winning_creative_notes || r.what_worked || '';
      if (document.getElementById('form-ads-optimizing')) document.getElementById('form-ads-optimizing').value = r.optimizing_plan_notes || r.next_action_plan || '';
      
      if (r.dashboard_screenshot) {
        document.getElementById('form-ads-existing-screenshot').value = r.dashboard_screenshot;
        const previewBox = document.getElementById('form-ads-screenshot-preview');
        previewBox.querySelector('img').src = r.dashboard_screenshot;
        previewBox.style.display = 'block';
      } else {
        document.getElementById('form-ads-existing-screenshot').value = '';
        document.getElementById('form-ads-screenshot-preview').style.display = 'none';
      }

      calculateAdsMetrics();
      const titleEl = document.querySelector('#modal-ads-report-form h3');
      if (titleEl) titleEl.innerText = 'Edit Laporan Report Ads';
      document.getElementById('modal-ads-report-form').style.display = 'flex';
    }

    function closeAdsReportModal() {
      document.getElementById('modal-ads-report-form').style.display = 'none';
    }

    function onReportProjectChange(projectId) {
      const select = document.getElementById('form-ads-project-id');
      const selectedOption = select.options[select.selectedIndex];
      if (selectedOption && selectedOption.dataset.clientId) {
        document.getElementById('form-ads-client-id').value = selectedOption.dataset.clientId;
      }
    }

    async function autoPullProjectAdsSpend() {
      const projectId = document.getElementById('form-ads-project-id').value;
      if (!projectId) {
        showToast('Pilih Proyek Klien terlebih dahulu!', 'info');
        return;
      }

      try {
        const res = await fetch(`api/reports.php?action=get_project_ads_data&project_id=${projectId}`);
        const data = await res.json();
        if (data.success && data.data) {
          const spend = parseFloat(data.data.total_ad_spend || 0);
          document.getElementById('form-ads-spend').value = spend;
          if (data.data.latest_platform) {
            document.getElementById('form-ads-platform').value = data.data.latest_platform;
          }
          calculateAdsMetrics();
          showToast(`Berhasil menarik total spend proyek: Rp ${spend.toLocaleString('id-ID')}`, 'success');
        } else {
          showToast(data.message || 'Gagal menarik data spend proyek.', 'warning');
        }
      } catch (err) {
        showToast('Koneksi server gagal saat menarik spend.', 'danger');
      }
    }

    function calculateAdsMetrics() {
      const spend = parseFloat(document.getElementById('form-ads-spend').value || 0);
      const impressions = parseInt(document.getElementById('form-ads-impressions').value || 0);
      const clicks = parseInt(document.getElementById('form-ads-clicks').value || 0);
      const results = parseInt(document.getElementById('form-ads-results').value || 0);

      // 1. CTR (%) = (Clicks / Impressions) * 100
      let ctr = 0;
      if (impressions > 0 && clicks > 0) {
        ctr = (clicks / impressions) * 100;
      }
      document.getElementById('form-ads-ctr').value = ctr.toFixed(2) + ' %';

      // 2. Cost Per Result / CPA (Rp) = Spend / Results
      let cpa = 0;
      if (results > 0 && spend > 0) {
        cpa = Math.round(spend / results);
      }
      document.getElementById('form-ads-cpa').value = 'Rp ' + cpa.toLocaleString('id-ID') + ' / result';
    }

    function previewAdsScreenshot(input) {
      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
          const previewBox = document.getElementById('form-ads-screenshot-preview');
          previewBox.querySelector('img').src = e.target.result;
          previewBox.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
      }
    }

    async function submitAdsReport(e) {
      e.preventDefault();
      const btn = document.getElementById('btn-submit-ads-report');
      btn.disabled = true;
      btn.innerHTML = '<span>⏳ Menyimpan & Menerbitkan...</span>';

      try {
        const form = document.getElementById('form-create-ads-report');
        const formData = new FormData(form);

        const res = await fetch('api/reports.php?action=save_ads_report', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          closeAdsReportModal();
          setTimeout(() => {
            window.location.href = data.redirect || window.location.href;
          }, 800);
        } else {
          showToast(data.message || 'Gagal menyimpan Report Ads.', 'danger');
          btn.disabled = false;
          btn.innerHTML = '<span>🚀 Simpan &amp; Terbitkan Laporan</span>';
        }
      } catch (err) {
        showToast('Koneksi server gagal saat menyimpan laporan.', 'danger');
        btn.disabled = false;
        btn.innerHTML = '<span>🚀 Simpan &amp; Terbitkan Laporan</span>';
      }
    }

    function viewAdsReportDetail(rep) {
      document.getElementById('view-detail-company').innerText = rep.client_company || rep.client_name;
      document.getElementById('view-detail-period-badge').innerText = `${rep.platform || 'Meta Ads'} &bull; Periode ${rep.report_period || ''} &bull; ${rep.objective || 'Leads'}`;
      document.getElementById('view-detail-spend').innerText = 'Rp ' + (parseFloat(rep.total_ad_spend || 0)).toLocaleString('id-ID');
      document.getElementById('view-detail-results').innerText = (parseInt(rep.total_conversions || 0)).toLocaleString('id-ID') + ' Leads / Results';
      document.getElementById('view-detail-cpa').innerText = 'Rp ' + (parseFloat(rep.cpl_cpa || 0)).toLocaleString('id-ID');
      document.getElementById('view-detail-impressions').innerText = (parseInt(rep.ads_impressions || 0)).toLocaleString('id-ID') + ' Views';
      document.getElementById('view-detail-clicks-ctr').innerText = `${(parseInt(rep.total_clicks || 0)).toLocaleString('id-ID')} Klik (CTR: ${(parseFloat(rep.ads_ctr || 0)).toFixed(2)}%)`;
      document.getElementById('view-detail-reach').innerText = (parseInt(rep.ads_reach || 0)).toLocaleString('id-ID') + ' Orang';
      document.getElementById('view-detail-winning').innerText = rep.winning_creative_notes || rep.what_worked || 'Belum ada catatan iklan terbaik.';
      document.getElementById('view-detail-optimizing').innerText = rep.optimizing_plan_notes || rep.next_action_plan || 'Belum ada catatan rencana optimasi.';
      document.getElementById('view-detail-author').innerText = rep.author_name || 'Tim Ads Kalamedia';

      const sBox = document.getElementById('view-detail-screenshot-box');
      if (rep.dashboard_screenshot) {
        document.getElementById('view-detail-screenshot-img').src = rep.dashboard_screenshot;
        sBox.style.display = 'block';
      } else {
        sBox.style.display = 'none';
      }

      document.getElementById('modal-ads-report-detail').style.display = 'flex';
    }

    function closeAdsDetailModal() {
      document.getElementById('modal-ads-report-detail').style.display = 'none';
    }

    async function deleteReport(id, company, period) {
      if (!confirm(`Hapus dokumen laporan performa ${company} periode ${period}?`)) return;
      try {
        const res = await fetch(`api/reports.php?action=delete&id=${id}`, { method: 'POST' });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          setTimeout(() => window.location.reload(), 500);
        } else {
          showToast(data.message || 'Gagal menghapus laporan.', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal.', 'danger');
      }
    }

    // -------------------------------------------------------------
    // JS HANDLERS: REKAP PEKERJAAN TIM KREATIF
    // -------------------------------------------------------------
    async function fetchAdminWorkRecap() {
      const month = document.getElementById('recap-filter-month') ? document.getElementById('recap-filter-month').value : '';
      const projectId = document.getElementById('recap-filter-project') ? document.getElementById('recap-filter-project').value : '';
      const serviceType = document.getElementById('recap-filter-service') ? document.getElementById('recap-filter-service').value : 'all';
      const workerSelect = document.getElementById('recap-filter-worker');
      const workerId = workerSelect ? workerSelect.value : '';

      try {
        let url = `api/portal_tasks.php?action=work_recap&month=${encodeURIComponent(month)}&project_id=${projectId}&service_type=${encodeURIComponent(serviceType)}`;
        if (workerId) url += `&worker_user_id=${workerId}`;

        const res = await fetch(url);
        const data = await res.json();
        if (!data.success) {
          showToast(data.message || 'Gagal memuat rekap pekerjaan', 'danger');
          return;
        }

        // Render Summary
        document.getElementById('recap-stat-total').innerText = data.summary.total_tasks || 0;
        document.getElementById('recap-stat-approved').innerText = data.summary.approved_tasks || 0;
        document.getElementById('recap-stat-progress').innerText = data.summary.in_progress_tasks || 0;
        document.getElementById('recap-stat-revision').innerText = data.summary.revision_tasks || 0;

        // Populate Workers Dropdown if exists and empty
        if (workerSelect && workerSelect.options.length <= 1 && data.filters.workers) {
          data.filters.workers.forEach(w => {
            const opt = document.createElement('option');
            opt.value = w.id;
            opt.textContent = `${w.name} (${w.position || w.role})`;
            workerSelect.appendChild(opt);
          });
        }

        // Render Table Rows
        const tbody = document.getElementById('recap-tasks-tbody');
        tbody.innerHTML = '';

        if (!data.tasks || data.tasks.length === 0) {
          tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 32px; color: #6B7280;">Tidak ada pekerjaan ditemukan pada filter yang dipilih.</td></tr>`;
          return;
        }

        data.tasks.forEach(t => {
          const tr = document.createElement('tr');
          const statusBg = (t.status === 'approved') ? '#ECFDF5' : ((t.status === 'revision') ? '#FEF2F2' : '#EFF6FF');
          const statusColor = (t.status === 'approved') ? '#047857' : ((t.status === 'revision') ? '#B91C1C' : '#1D4ED8');
          const serviceBadgeBg = t.is_eceran ? '#FEF3C7' : '#F1F5F9';
          const serviceBadgeColor = t.is_eceran ? '#92400E' : '#334155';

          tr.innerHTML = `
            <td style="white-space: nowrap;">
              <div style="font-weight: 700; color: #111827; font-size: 12px;">${t.due_date ? t.due_date : (t.created_at ? t.created_at.substring(0, 10) : '-')}</div>
              <div style="font-size: 10.5px; color: #9CA3AF;">Mulai: ${t.start_date || '-'}</div>
            </td>
            <td>
              <div style="font-weight: 700; color: #111827; font-size: 12.5px;">${escapeHtml(t.client_company || t.client_name)}</div>
              <div style="font-size: 11px; color: #6B7280;">${escapeHtml(t.project_name)}</div>
              <span style="display: inline-block; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; background: ${serviceBadgeBg}; color: ${serviceBadgeColor}; margin-top: 2px;">
                ${escapeHtml(t.service_badge)}
              </span>
            </td>
            <td>
              <div style="font-weight: 700; color: #111827; font-size: 13px;">${escapeHtml(t.title)}</div>
              ${t.description ? `<div style="font-size: 11.5px; color: #6B7280; margin-top: 2px; max-width: 260px;">${escapeHtml(t.description)}</div>` : ''}
              ${t.latest_feedback_message ? `<div style="font-size: 11px; color: #D97706; margin-top: 4px; background: #FFFBEB; padding: 3px 6px; border-radius: 4px;">💬 Catatan Klien: "${escapeHtml(t.latest_feedback_message)}"</div>` : ''}
            </td>
            <td>
              <span style="font-size: 11px; font-weight: 700; background: #F3F4F6; color: #374151; padding: 3px 8px; border-radius: 6px;">
                ${escapeHtml(t.category_label)}
              </span>
            </td>
            <td>
              <div style="font-size: 12px; font-weight: 700; color: #111827;">${escapeHtml(t.worker_name || 'Belum di-assign')}</div>
              <div style="font-size: 10.5px; color: #6B7280;">${escapeHtml(t.worker_position || '')}</div>
            </td>
            <td>
              ${t.deliverable_link ? `<a href="${escapeHtml(t.deliverable_link)}" target="_blank" style="font-size: 11.5px; font-weight: 700; color: #2563EB; text-decoration: underline; display: inline-flex; align-items: center; gap: 4px;">🔗 Preview Hasil</a>` : '<span style="font-size: 11px; color: #9CA3AF;">Belum ada link</span>'}
            </td>
            <td>
              <span style="display: inline-block; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 6px; background: ${statusBg}; color: ${statusColor};">
                ${escapeHtml(t.status_label)}
              </span>
            </td>
          `;
          tbody.appendChild(tr);
        });
      } catch (err) {
        console.error(err);
      }
    }

    function exportWorkRecapPdf() {
      window.print();
    }

    function escapeHtml(text) {
      if (!text) return '';
      return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Auto load recap if on tab
    <?php if ($activeTab === 'work_recap'): ?>
      document.addEventListener('DOMContentLoaded', fetchAdminWorkRecap);
    <?php endif; ?>
  </script>

  <?php require_once BASE_PATH . '/includes/bottom_nav.php'; ?>
  <?php require_once BASE_PATH . '/includes/room_chat_modal.php'; ?>
</body>
</html>

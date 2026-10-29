<?php
/**
 * Kalamedia Clients & Projects Database View (Untitled UI Design System)
 * Full Management: View, Add, Edit & Delete for Clients and Projects
 */
require_admin();
$db = Database::getConnection();

// Fetch all active clients with project count and total invoiced
$clients = $db->query("
    SELECT c.*,
           (SELECT COUNT(*) FROM projects WHERE client_id = c.id AND COALESCE(is_deleted, 0) = 0) as total_projects,
           (SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE client_id = c.id AND COALESCE(is_deleted, 0) = 0) as total_invoiced,
           (SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE client_id = c.id AND status = 'Paid' AND COALESCE(is_deleted, 0) = 0) as total_paid,
           (SELECT COALESCE(SUM(amount), 0) FROM freelancer_payouts WHERE project_id IN (SELECT id FROM projects WHERE client_id = c.id) AND status = 'Paid' AND COALESCE(is_deleted, 0) = 0) as total_freelancer_paid
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

// Batch query: Fetch Kala AI Brand Audits per client
$auditsByClient = [];
try {
    $auditRows = $db->query("
        SELECT id, client_id, model_used, brand_stage, health_score, recommended_scheme_name, is_published_to_client,
               CASE WHEN questionnaire_json IS NOT NULL AND questionnaire_json != '' AND questionnaire_json != '{}' THEN 1 ELSE 0 END as has_draft,
               CASE WHEN ai_analysis_json IS NOT NULL AND ai_analysis_json != '' AND ai_analysis_json != '{}' THEN 1 ELSE 0 END as has_analysis,
               updated_at
        FROM brand_ai_audits
        ORDER BY id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($auditRows as $ar) {
        $auditsByClient[(int)$ar['client_id']] = $ar;
    }
} catch (Throwable $e) {
    // Table will be created automatically by Database::runMigrations()
}

// Fetch all active projects with calculated costs & margins and separated fees
$projects = $db->query("
    SELECT p.*, c.company as client_company, c.name as client_pic,
           c.contract_start_date as client_contract_start, c.contract_end_date as client_contract_end, c.contract_status as client_contract_status,
           (SELECT COALESCE(SUM(amount), 0) FROM freelancer_payouts WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0) as total_freelancer_cost,
           (SELECT COALESCE(SUM(amount), 0) FROM freelancer_payouts WHERE project_id = p.id AND status = 'Paid' AND COALESCE(is_deleted, 0) = 0) as total_freelancer_paid,
           (SELECT COALESCE(SUM(amount), 0) FROM freelancer_payouts WHERE project_id = p.id AND status = 'Pending' AND COALESCE(is_deleted, 0) = 0) as total_freelancer_pending,
           (SELECT COALESCE(SUM(amount), 0) FROM ads_spend WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0) as total_ads_cost,
           (SELECT COALESCE(SUM(assigned_ads_budget), 0) FROM tasks WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0) as total_assigned_ads_budget
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
  <title>Database Klien &amp; Proyek - PT Kala Kausa Prima</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=saas-linear-2.7">
  <?php render_tab_uid_script(); ?>
  <style>
    /* Clean Client & Project Table Refinements (Light & NebulaPay Dark Mode) */
    .kala-clients-toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      flex-wrap: wrap;
      margin-bottom: 16px;
      width: 100%;
      max-width: 100%;
      min-width: 0;
      box-sizing: border-box;
    }
    .kala-clients-toolbar-left {
      min-width: 0;
    }
    .kala-clients-toolbar-right {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      justify-content: flex-end;
      min-width: 0;
      max-width: 100%;
      box-sizing: border-box;
    }
    .client-filter-scroll-track {
      width: auto;
      max-width: 100%;
      min-width: 0;
      overflow-x: auto !important;
      -webkit-overflow-scrolling: touch !important;
      touch-action: pan-x !important;
      padding-bottom: 4px;
      scrollbar-width: thin !important;
      scrollbar-color: rgba(91, 61, 245, 0.35) transparent !important;
    }
    .client-filter-scroll-track::-webkit-scrollbar {
      height: 4px !important;
    }
    .client-filter-scroll-track::-webkit-scrollbar-track {
      background: transparent !important;
    }
    .client-filter-scroll-track::-webkit-scrollbar-thumb {
      background: #CBD5E1 !important;
      border-radius: 999px !important;
    }
    html[data-theme="dark"] .client-filter-scroll-track::-webkit-scrollbar-thumb,
    body.dark-mode .client-filter-scroll-track::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.22) !important;
    }
    .client-filter-scroll-track .client-filter-pill-bar {
      display: inline-flex !important;
      width: max-content !important;
      min-width: max-content !important;
      background: #F3F4F6 !important;
      padding: 4px !important;
      border-radius: 10px !important;
      gap: 4px !important;
      box-sizing: border-box !important;
      white-space: nowrap !important;
    }
    html[data-theme="dark"] .client-filter-scroll-track .client-filter-pill-bar,
    body.dark-mode .client-filter-scroll-track .client-filter-pill-bar {
      background: #161D2F !important;
      border: 1px solid rgba(255, 255, 255, 0.08) !important;
    }
    .client-filter-scroll-track .client-filter-pill {
      flex-shrink: 0 !important;
      padding: 6px 14px !important;
      border-radius: 7px !important;
      font-size: 12px !important;
      font-weight: 600 !important;
      white-space: nowrap !important;
      cursor: pointer !important;
    }
    @media (max-width: 768px) {
      .kala-clients-toolbar {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 12px !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
      }
      .kala-clients-toolbar-left {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
      }
      .kala-clients-toolbar-right {
        flex-direction: column !important;
        align-items: stretch !important;
        justify-content: flex-start !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        gap: 10px !important;
      }
      .client-filter-scroll-track {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        padding-bottom: 6px !important;
      }
      .kala-clients-toolbar-right > .btn,
      .panel-header > .btn {
        width: 100% !important;
        height: 38px !important;
        justify-content: center !important;
        border-radius: 10px !important;
        font-weight: 700 !important;
        font-size: 13px !important;
      }
    }
    .kala-service-stack {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 5px;
    }
    .kala-contract-meta {
      font-size: 12px;
      font-weight: 600;
      color: #344054;
      margin-bottom: 6px;
      white-space: nowrap;
    }
    .kala-contract-row {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      white-space: nowrap;
    }
    .kala-contract-pill {
      display: inline-flex;
      align-items: center;
      height: 26px;
      padding: 0 9px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 700;
      white-space: nowrap;
      line-height: 1;
    }
    .kala-contract-pill.active {
      background: #ECFDF3;
      color: #027A48;
      border: 1px solid #A6F4C5;
    }
    .kala-contract-pill.expired {
      background: #FEF3F2;
      color: #B42318;
      border: 1px solid #FECDCA;
    }
    .kala-btn-renew {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      height: 26px !important;
      padding: 0 10px !important;
      border-radius: 6px !important;
      font-size: 11px !important;
      font-weight: 700 !important;
      background: #F8FAFC;
      color: #344054;
      border: 1px solid #D0D5DD;
      cursor: pointer;
      white-space: nowrap;
      transition: all 0.15s ease;
    }
    .kala-btn-renew:hover {
      background: #EEF2FF;
      color: #4F46E5;
      border-color: #C7D2FE;
    }
    .kala-btn-renew.expired-cta {
      background: #027A48;
      color: #FFFFFF;
      border-color: #027A48;
    }
    .kala-project-count-pill {
      display: inline-flex;
      align-items: center;
      padding: 2px 8px;
      border-radius: 5px;
      font-size: 11px;
      font-weight: 700;
      background: #F2F4F7;
      color: #101828;
      border: 1px solid #E4E7EC;
      margin-bottom: 4px;
    }
    .kala-comp-badge {
      display: inline-flex;
      align-items: center;
      padding: 2px 7px;
      border-radius: 5px;
      font-size: 10.5px;
      font-weight: 700;
      white-space: nowrap;
    }
    .kala-comp-badge.jasa {
      background: #EFF8FF;
      color: #175CD3;
      border: 1px solid #B2DDFF;
    }
    .kala-comp-badge.ads {
      background: #ECFDF3;
      color: #027A48;
      border: 1px solid #A6F4C5;
    }
    .kala-client-actions-bar {
      display: inline-flex;
      align-items: center;
      justify-content: flex-end;
      gap: 6px;
      white-space: nowrap;
    }
    .kala-audit-pill {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      height: 28px;
      padding: 0 10px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 700;
      white-space: nowrap;
      line-height: 1;
      box-sizing: border-box;
    }
    .kala-audit-pill .kala-audit-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      display: inline-block;
      flex-shrink: 0;
    }
    .kala-audit-pill.unstarted {
      background: #F2F4F7;
      color: #475467;
      border: 1px solid #E4E7EC;
    }
    .kala-audit-pill.unstarted .kala-audit-dot {
      background: #98A2B3;
    }
    .kala-audit-pill.draft {
      background: #FFFBEB;
      color: #B45309;
      border: 1px solid #FDE68A;
    }
    .kala-audit-pill.draft .kala-audit-dot {
      background: #F59E0B;
    }
    .kala-audit-pill.published {
      background: #ECFDF3;
      color: #027A48;
      border: 1px solid #A6F4C5;
    }
    .kala-audit-pill.published .kala-audit-dot {
      background: #10B981;
    }
    .kala-btn-ai-audit {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 5px !important;
      height: 32px !important;
      padding: 0 12px !important;
      border-radius: 8px !important;
      font-size: 12px !important;
      font-weight: 700 !important;
      background: linear-gradient(135deg, #4F46E5 0%, #6366F1 100%) !important;
      color: #FFFFFF !important;
      border: 1px solid rgba(99, 102, 241, 0.4) !important;
      cursor: pointer !important;
      white-space: nowrap !important;
      box-shadow: 0 2px 8px rgba(79, 70, 229, 0.25) !important;
      transition: all 0.16s ease !important;
    }
    .kala-btn-ai-audit:hover {
      transform: translateY(-1px) !important;
      box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4) !important;
    }

    /* NebulaPay Dark Mode Overrides for Client & Project Tables */
    html[data-theme="dark"] .kala-contract-meta,
    body.dark-mode .kala-contract-meta {
      color: #E2E8F0 !important;
    }
    html[data-theme="dark"] .kala-contract-pill.active,
    body.dark-mode .kala-contract-pill.active {
      background: rgba(16, 185, 129, 0.14) !important;
      color: #34D399 !important;
      border-color: rgba(52, 211, 153, 0.3) !important;
    }
    html[data-theme="dark"] .kala-contract-pill.expired,
    body.dark-mode .kala-contract-pill.expired {
      background: rgba(239, 68, 68, 0.14) !important;
      color: #F87171 !important;
      border-color: rgba(248, 113, 113, 0.3) !important;
    }
    html[data-theme="dark"] .kala-btn-renew,
    body.dark-mode .kala-btn-renew {
      background: #161F33 !important;
      color: #CBD5E1 !important;
      border-color: rgba(255, 255, 255, 0.12) !important;
    }
    html[data-theme="dark"] .kala-btn-renew:hover,
    body.dark-mode .kala-btn-renew:hover {
      background: rgba(99, 102, 241, 0.18) !important;
      color: #FFFFFF !important;
      border-color: rgba(129, 140, 248, 0.45) !important;
    }
    html[data-theme="dark"] .kala-project-count-pill,
    body.dark-mode .kala-project-count-pill {
      background: #161F33 !important;
      color: #F1F5F9 !important;
      border-color: rgba(255, 255, 255, 0.1) !important;
    }
    html[data-theme="dark"] .kala-comp-badge.jasa,
    body.dark-mode .kala-comp-badge.jasa {
      background: rgba(59, 130, 246, 0.15) !important;
      color: #60A5FA !important;
      border-color: rgba(59, 130, 246, 0.3) !important;
    }
    html[data-theme="dark"] .kala-comp-badge.ads,
    body.dark-mode .kala-comp-badge.ads {
      background: rgba(16, 185, 129, 0.15) !important;
      color: #34D399 !important;
      border-color: rgba(16, 185, 129, 0.3) !important;
    }
    html[data-theme="dark"] .kala-audit-pill.unstarted,
    body.dark-mode .kala-audit-pill.unstarted {
      background: rgba(148, 163, 184, 0.12) !important;
      color: #94A3B8 !important;
      border-color: rgba(148, 163, 184, 0.24) !important;
    }
    html[data-theme="dark"] .kala-audit-pill.unstarted .kala-audit-dot,
    body.dark-mode .kala-audit-pill.unstarted .kala-audit-dot {
      background: #64748B !important;
    }
    html[data-theme="dark"] .kala-audit-pill.draft,
    body.dark-mode .kala-audit-pill.draft {
      background: rgba(245, 158, 11, 0.14) !important;
      color: #FBBF24 !important;
      border-color: rgba(251, 191, 36, 0.32) !important;
    }
    html[data-theme="dark"] .kala-audit-pill.published,
    body.dark-mode .kala-audit-pill.published {
      background: rgba(16, 185, 129, 0.14) !important;
      color: #34D399 !important;
      border-color: rgba(52, 211, 153, 0.32) !important;
    }
  </style>
</head>
<body>
  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body">
        
        <?php if (is_owner() || is_admin()): ?>
        <!-- Quick Sub-Tab Switcher: Database Klien & Proyek | Tim & Karyawan -->
        <div class="quick-subtab-bar" style="margin-bottom: 14px;">
          <div style="display: grid; grid-template-columns: repeat(2, 1fr); background: #F0F1F7; padding: 4px; border-radius: 12px; gap: 4px; box-sizing: border-box; width: 100%;">
            <a href="<?= url('clients?_slot=internal') ?>" class="quick-subtab-btn active" style="display: flex; align-items: center; justify-content: center; padding: 8px 12px; border-radius: 8px; font-size: 12.5px; font-weight: 700; text-decoration: none; text-align: center; white-space: nowrap; background: #5B3DF5; color: #FFFFFF; box-shadow: 0 2px 6px rgba(91,61,245,0.25);">
              Database Klien &amp; Proyek
            </a>
            <a href="<?= url('team?_slot=internal') ?>" class="quick-subtab-btn" style="display: flex; align-items: center; justify-content: center; padding: 8px 12px; border-radius: 8px; font-size: 12.5px; font-weight: 600; text-decoration: none; text-align: center; white-space: nowrap; color: #64748B;">
              Tim &amp; Karyawan
            </a>
          </div>
        </div>
        <?php endif; ?>

        <!-- 1. Clients Database Panel -->
        <div class="glass-panel" style="overflow: visible; min-width: 0; max-width: 100%;">
          <div class="kala-clients-toolbar">
            <div class="kala-clients-toolbar-left">
              <h2 class="panel-title" style="margin: 0; font-size: 15.5px; font-weight: 700; color: #111827; letter-spacing: -0.015em;">
                Database Klien &amp; Brand Agensi
              </h2>
              <p class="panel-subtitle" style="font-size: 12.5px; color: #6B7280; margin: 3px 0 0;">
                Daftar seluruh klien, masa kontrak layanan, dan alokasi tim PIC
              </p>
            </div>

            <div class="kala-clients-toolbar-right">
              <div class="client-filter-scroll-track" id="client-filter-scroll-track">
                <div class="client-filter-pill-bar">
                  <button type="button" class="client-filter-pill active" onclick="filterClientService('all', this)">Semua Jasa</button>
                  <button type="button" class="client-filter-pill" onclick="filterClientService('Kelola Sosmed', this)">Kelola Sosmed</button>
                  <button type="button" class="client-filter-pill" onclick="filterClientService('Desain Eceran', this)">Desain Eceran / Satuan</button>
                  <button type="button" class="client-filter-pill" onclick="filterClientService('Jasa Buat Web', this)">Jasa Buat Web</button>
                  <button type="button" class="client-filter-pill" onclick="filterClientService('Jasa Iklan', this)">Jasa Iklan Meta &amp; Google</button>
                </div>
              </div>

              <button type="button" class="btn btn-primary btn-sm" onclick="openModal('modal-create-client')" style="height: 36px; padding: 0 14px; font-weight: 700; white-space: nowrap;">
                + Tambah Klien Baru
              </button>
            </div>
          </div>

          <div class="kala-table-scroll table-responsive" style="width: 100%; max-width: 100%; overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; display: block !important; padding-bottom: 6px;">
            <table class="table-custom" style="min-width: 960px !important; width: 100%;">
              <thead>
                <tr>
                  <th style="white-space: nowrap !important;">NAMA PERUSAHAAN &amp; PIC</th>
                  <th style="white-space: nowrap !important;">LAYANAN / JASA</th>
                  <th style="white-space: nowrap !important;">TIM PIC (PENANGGUNG JAWAB)</th>
                  <th style="white-space: nowrap !important;">MASA KONTRAK &amp; STATUS</th>
                  <th style="white-space: nowrap !important;">TOTAL PROYEK &amp; BIAYA</th>
                  <th class="th-actions" style="text-align: right; white-space: nowrap !important; min-width: 280px;">STATUS AUDIT &amp; AKSI</th>
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

                      <td style="white-space: nowrap;">
                        <?php if (empty($cServices)): ?>
                          <span style="font-size: 11px; color: var(--text-muted); font-style: italic;">Belum diatur</span>
                        <?php else: ?>
                          <div class="kala-service-stack">
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
                              <div style="font-size: 12px; font-weight: 700; color: #101828; display: inline-flex; align-items: center; gap: 5px;">
                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #5D76F5; display: inline-block;"></span>
                                <?= htmlspecialchars($atm['name']) ?>
                                <span style="font-size: 10.5px; color: #6E7485; font-weight: 500;">(<?= htmlspecialchars($atm['position'] ?: 'Staff') ?>)</span>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        <?php endif; ?>
                      </td>

                      <td style="white-space: nowrap;">
                        <?php if ($startDate && $endDate): ?>
                          <div class="kala-contract-meta">
                            <?= date('d M Y', strtotime($startDate)) ?> &ndash; <?= date('d M Y', strtotime($endDate)) ?>
                          </div>
                        <?php else: ?>
                          <div style="font-size: 11px; color: var(--text-muted); font-style: italic; margin-bottom: 6px;">Tanggal belum diset</div>
                        <?php endif; ?>

                        <?php if ($isExpired): ?>
                          <div class="kala-contract-row">
                            <span class="kala-contract-pill expired">
                              <span class="status-dot dot-red"></span> Kontrak Berakhir
                            </span>
                            <button type="button" class="kala-btn-renew expired-cta" onclick='openRenewClientModal(<?= json_encode($c, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                              + Perpanjang
                            </button>
                          </div>
                        <?php else: ?>
                          <div class="kala-contract-row">
                            <span class="kala-contract-pill active">
                              <span class="status-dot dot-green"></span> Aktif <?= $daysRemaining !== null ? "&bull; Sisa {$daysRemaining} Hari" : '' ?>
                            </span>
                            <button type="button" class="kala-btn-renew" onclick='openRenewClientModal(<?= json_encode($c, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                              Perpanjang
                            </button>
                          </div>
                        <?php endif; ?>
                      </td>

                      <td style="white-space: nowrap;">
                        <span class="kala-project-count-pill">
                          <?= $c['total_projects'] ?> Proyek
                        </span>
                        <div style="font-weight: 800; color: var(--text-primary); font-size: 12.5px;"><?= format_rupiah($c['total_invoiced']) ?></div>
                        <div style="font-size: 11px; color: var(--success-text); font-weight: 700;">Lunas: <?= format_rupiah($c['total_paid']) ?></div>
                        <?php if (!empty($c['total_freelancer_paid']) && $c['total_freelancer_paid'] > 0): ?>
                          <div style="font-size: 10.5px; color: #027A48; font-weight: 700; margin-top: 2px;">Freelancer Terbayar: <?= format_rupiah($c['total_freelancer_paid']) ?></div>
                        <?php endif; ?>
                      </td>

                      <td class="td-actions" style="text-align: right; white-space: nowrap;">
                        <?php
                          $cAudit = $auditsByClient[(int)$c['id']] ?? null;
                          $auditBadgeText = 'Belum Dianalisis';
                          $auditBadgeClass = 'unstarted';
                          if ($cAudit) {
                              if ((int)($cAudit['is_published_to_client'] ?? 0) === 1) {
                                  $auditBadgeText = 'Terkirim ke Klien';
                                  $auditBadgeClass = 'published';
                              } elseif (!empty($cAudit['has_analysis']) || !empty($cAudit['has_draft'])) {
                                  $auditBadgeText = 'Draft Analisis';
                                  $auditBadgeClass = 'draft';
                              }
                          }
                        ?>
                        <div class="kala-client-actions-bar">
                          <span id="client-audit-badge-<?= (int)$c['id'] ?>" class="kala-audit-pill <?= $auditBadgeClass ?>">
                            <span class="kala-audit-dot"></span>
                            <span><?= $auditBadgeText ?></span>
                          </span>

                          <button type="button" class="kala-btn-ai-audit" onclick="openBrandAiAuditModal(<?= (int)$c['id'] ?>, '<?= htmlspecialchars($c['company'], ENT_QUOTES) ?>')" title="Kala AI Brand Diagnostic &amp; Strategy Blueprint (Gemini Pro)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.912 5.813a2 2 0 001.275 1.275L21 12l-5.813 1.912a2 2 0 00-1.275 1.275L12 21l-1.912-5.813a2 2 0 00-1.275-1.275L3 12l5.813-1.912a2 2 0 001.275-1.275L12 3z"/></svg>
                            <span>AI Brand Audit</span>
                          </button>

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
            <table class="table-custom" style="min-width: 960px !important; width: 100%;">
              <thead>
                <tr>
                  <th style="white-space: nowrap !important;">NAMA PROYEK &amp; KLIEN</th>
                  <th style="white-space: nowrap !important;">TOTAL KONTRAK &amp; KOMPONEN</th>
                  <th style="white-space: nowrap !important;">FEE FREELANCER (PRODUKSI)</th>
                  <th style="white-space: nowrap !important;">ESTIMASI PROFIT JASA</th>
                  <th style="white-space: nowrap !important;">MARGIN JASA</th>
                  <th style="white-space: nowrap !important;">STATUS &amp; SALDO ADS</th>
                  <th class="th-actions" style="text-align: right; white-space: nowrap !important; min-width: 190px;">AKSI</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($projects)): ?>
                  <tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px;">Belum ada data proyek terdaftar.</td></tr>
                <?php else: ?>
                  <?php foreach ($projects as $p):
                    $serviceFee = floatval($p['service_fee_amount'] > 0 ? $p['service_fee_amount'] : $p['contract_value']);
                    $adsBudget = floatval($p['ads_budget_pool']);
                    $totalContract = floatval($p['total_contract_value'] > 0 ? $p['total_contract_value'] : ($serviceFee + $adsBudget));
                    $freelancerCost = floatval($p['total_freelancer_cost']);
                    $freelancerPaid = floatval($p['total_freelancer_paid'] ?? 0);
                    $freelancerPending = floatval($p['total_freelancer_pending'] ?? 0);
                    $profit = $serviceFee - $freelancerCost;
                    $margin = $serviceFee > 0 ? ($profit / $serviceFee) * 100 : 0;
                    $targetMargin = floatval($p['target_margin_percent']);

                    $today = date('Y-m-d');
                    $contractEnd = !empty($p['end_date']) ? $p['end_date'] : ($p['client_contract_end'] ?? '');
                    $contractStatus = $p['contract_status'] ?: ($p['client_contract_status'] ?? 'active');
                    $isContractExpired = ($contractEnd && $today > $contractEnd) || ($contractStatus === 'expired');

                    $totalAssigned = floatval($p['total_assigned_ads_budget']);
                    $availablePool = max(0, $adsBudget - $totalAssigned);
                  ?>
                    <tr>
                      <td style="white-space: nowrap;">
                        <div class="clickable-client-link" onclick="openProjectFinancialDetailModal(<?= $p['id'] ?>, <?= $p['client_id'] ?>)" title="Lihat Rincian Alokasi Keuangan Proyek">
                          <span style="font-weight: 700; color: var(--text-primary);"><?= htmlspecialchars($p['name']) ?></span>
                        </div>
                        <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($p['client_company']) ?></div>
                      </td>
                      <td style="white-space: nowrap;">
                        <div style="font-weight: 800; color: var(--text-primary); font-size: 13.5px;"><?= format_rupiah($totalContract) ?></div>
                        <div style="display: flex; gap: 5px; margin-top: 4px; flex-wrap: wrap;">
                          <span class="kala-comp-badge jasa">
                            Jasa: <?= format_rupiah($serviceFee) ?>
                          </span>
                          <span class="kala-comp-badge ads">
                            Ads: <?= format_rupiah($adsBudget) ?>
                          </span>
                        </div>
                      </td>
                      <td style="white-space: nowrap;">
                        <div style="color: #10B981; font-weight: 800; font-size: 13px; display: inline-flex; align-items: center; gap: 5px;">
                          <span><?= format_rupiah($freelancerPaid) ?></span>
                          <span class="kala-comp-badge ads" style="font-size: 9.5px; padding: 1px 6px;">Lunas</span>
                        </div>
                        <?php if ($freelancerPending > 0): ?>
                          <div style="font-size: 10.5px; color: #B54708; font-weight: 600; margin-top: 2px;">Pending: <?= format_rupiah($freelancerPending) ?></div>
                        <?php endif; ?>
                        <div style="font-size: 10px; color: var(--text-muted); margin-top: 2px;">Biaya Talent Freelancer</div>
                      </td>
                      <td style="font-weight: 700; color: <?= $profit > 0 ? 'var(--success-text)' : ($profit < 0 ? 'var(--danger-text)' : 'var(--text-primary)') ?>; white-space: nowrap;">
                        <?= format_rupiah($profit) ?>
                        <div style="font-size: 10px; color: var(--text-muted);">(Laba Bersih Agensi)</div>
                      </td>
                      <td style="white-space: nowrap;">
                        <?php if ($totalContract <= 0 && $freelancerCost <= 0): ?>
                          <span style="display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 700; background: rgba(148, 163, 184, 0.14); color: var(--text-secondary); border: 1px solid rgba(148, 163, 184, 0.25);">
                            0%
                          </span>
                          <div style="font-size: 10px; color: var(--text-muted); margin-top: 2px;">Target: <?= $targetMargin ?>%</div>
                        <?php else: ?>
                          <span class="badge-margin <?= $margin >= $targetMargin ? 'good' : 'bad' ?>">
                            <?= round($margin, 1) ?>%
                          </span>
                          <div style="font-size: 10px; color: var(--text-muted); margin-top: 2px;">Target: <?= $targetMargin ?>%</div>
                        <?php endif; ?>
                      </td>
                      <td style="white-space: nowrap;">
                        <span class="badge-status badge-<?= strtolower(str_replace(' ', '', $p['status'])) ?>">
                          <?= $p['status'] ?>
                        </span>
                        <?php if ($adsBudget > 0): ?>
                          <div style="font-size: 10.5px; color: #027A48; font-weight: 600; margin-top: 3px;">
                            Sisa Pool Ads: <?= format_rupiah($availablePool) ?>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td class="td-actions" style="text-align: right; white-space: nowrap;">
                        <div class="table-actions" style="justify-content: flex-end; gap: 6px;">
                          <?php if (!$isContractExpired): ?>
                            <button type="button" class="kala-btn-renew" onclick='openTopupAdsModal(<?= json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' style="height: 32px !important; padding: 0 10px !important;" title="Tambah / Top-Up Saldo Ads Klien">
                              + Saldo Ads
                            </button>
                          <?php else: ?>
                            <button type="button" class="kala-btn-renew" disabled style="height: 32px !important; padding: 0 10px !important; opacity: 0.5; cursor: not-allowed;" title="Masa kontrak habis — perpanjang kontrak terlebih dahulu">
                              + Saldo Ads (Terkunci)
                            </button>
                          <?php endif; ?>

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
  <?php require_once BASE_PATH . '/includes/brand_ai_audit_modal.php'; ?>
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

    function openEditProjectModal(project) {
      if (!project) return;
      document.getElementById('edit-project-id').value = project.id || '';
      document.getElementById('edit-project-client-id').value = project.client_id || '';
      document.getElementById('edit-project-name').value = project.name || '';

      const serviceFee = project.service_fee_amount > 0 ? project.service_fee_amount : (project.contract_value || 0);
      const adsBudget = project.ads_budget_pool || 0;

      const feeInput = document.getElementById('edit-service-fee');
      const adsInput = document.getElementById('edit-ads-budget');

      if (feeInput) feeInput.value = Math.round(serviceFee).toLocaleString('id-ID');
      if (adsInput) adsInput.value = Math.round(adsBudget).toLocaleString('id-ID');

      document.getElementById('edit-project-target-margin').value = project.target_margin_percent || '30.00';
      document.getElementById('edit-project-status').value = project.status || 'Planning';
      document.getElementById('edit-project-start-date').value = project.start_date || '';
      document.getElementById('edit-project-end-date').value = project.end_date || '';

      recalcProjectSummary('edit');
      openModal('modal-edit-project');
    }

    function openTopupAdsModal(project) {
      if (!project) return;
      document.getElementById('topup-ads-project-id').value = project.id;
      document.getElementById('topup-ads-project-name').innerText = project.name;
      document.getElementById('topup-ads-client-company').innerText = project.client_company || 'Klien';

      const poolVal = parseFloat(project.ads_budget_pool) || 0;
      document.getElementById('topup-ads-current-pool').innerText = 'Rp ' + Math.round(poolVal).toLocaleString('id-ID');

      const startDate = project.start_date || project.client_contract_start || '-';
      const endDate = project.end_date || project.client_contract_end || '-';
      document.getElementById('topup-ads-contract-dates').innerText = `${startDate} s/d ${endDate}`;

      const amountInput = document.getElementById('topup-ads-amount');
      if (amountInput) amountInput.value = '';

      openModal('modal-topup-project-ads');
    }

    // Smooth drag-to-scroll for horizontal filter track
    document.addEventListener('DOMContentLoaded', function() {
      const slider = document.getElementById('client-filter-scroll-track');
      if (!slider) return;
      let isDown = false;
      let startX;
      let scrollLeft;

      slider.addEventListener('mousedown', (e) => {
        isDown = true;
        startX = e.pageX - slider.offsetLeft;
        scrollLeft = slider.scrollLeft;
      });
      slider.addEventListener('mouseleave', () => { isDown = false; });
      slider.addEventListener('mouseup', () => { isDown = false; });
      slider.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - slider.offsetLeft;
        const walk = (x - startX) * 1.5;
        slider.scrollLeft = scrollLeft - walk;
      });
    });
  </script>
</body>
</html>

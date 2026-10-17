<?php
/**
 * Kalamedia Agency - Manajemen Tim & Karyawan (Untitled UI Design System)
 * Full Management: In-House Employees & Freelancers, Jobdesk from Owner, Client Allocation & Credentials
 */
require_admin();

$user = current_user();
$role = current_user_role();
$isOwner = is_owner();
$db = Database::getConnection();

// Fetch active clients for assignment
$allClients = $db->query("
    SELECT id, company, name as pic_name 
    FROM clients 
    WHERE COALESCE(is_deleted, 0) = 0 
    ORDER BY company ASC
")->fetchAll();

// Fetch active team members (karyawan and freelancer)
$teamMembers = $db->query("
    SELECT u.id, u.name, u.email, u.role, u.employment_type, u.position, u.jobdesk, u.phone_wa, u.avatar, u.is_active, u.created_at,
           u.service_specialty, u.contract_start_date, u.contract_end_date
    FROM users u
    WHERE u.role IN ('karyawan', 'freelancer') AND COALESCE(u.is_deleted, 0) = 0
    ORDER BY 
        CASE WHEN u.employment_type = 'karyawan' THEN 1 ELSE 2 END,
        u.name ASC
")->fetchAll();

// Batch query: Fetch all employee-client assignments in 1 query (No N+1)
$assignedRows = $db->query("
    SELECT ec.user_id, c.id, c.company, c.name as pic_name
    FROM employee_clients ec
    JOIN clients c ON ec.client_id = c.id
    WHERE COALESCE(c.is_deleted, 0) = 0
    ORDER BY c.company ASC
")->fetchAll();

$assignmentsByUser = [];
foreach ($assignedRows as $row) {
    $uid = (int)$row['user_id'];
    $assignmentsByUser[$uid][] = [
        'id' => $row['id'],
        'company' => $row['company'],
        'pic_name' => $row['pic_name']
    ];
}

foreach ($teamMembers as &$m) {
    $uid = (int)$m['id'];
    $m['assigned_clients'] = $assignmentsByUser[$uid] ?? [];
    $m['client_ids'] = array_column($m['assigned_clients'], 'id');
}
unset($m);

// Metrics
$totalMembers = count($teamMembers);
$karyawanCount = 0;
$freelancerCount = 0;
foreach ($teamMembers as $tm) {
    if ($tm['employment_type'] === 'freelancer') $freelancerCount++;
    else $karyawanCount++;
}
$totalClientsCount = count($allClients);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tim & Karyawan - PT Kala Kausa Prima</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=responsive-mobile-2.1">
  <style>
    .team-badge-karyawan {
      background: rgba(93, 118, 245, 0.12);
      color: #5D76F5;
      border: 1px solid rgba(93, 118, 245, 0.2);
      font-size: 11px;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .team-badge-freelancer {
      background: rgba(137, 50, 224, 0.12);
      color: #8932E0;
      border: 1px solid rgba(137, 50, 224, 0.2);
      font-size: 11px;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .client-tag-pill {
      background: #F8F9FC;
      color: #6E7485;
      font-size: 11px;
      font-weight: 600;
      padding: 3px 8px;
      border-radius: 8px;
      display: inline-block;
      margin: 2px 2px 2px 0;
      border: 1px solid #ECEEF5;
    }
    .service-tag {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 11px;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 6px;
      margin-bottom: 4px;
    }
    .service-tag-sosmed { background: #EFF8FF; color: #175CD3; border: 1px solid #B2DDFF; }
    .service-tag-desain { background: #FDF2FA; color: #C11574; border: 1px solid #FCCEEE; }
    .service-tag-web { background: #F4F3FF; color: #5925DC; border: 1px solid #D9D6FE; }
    .service-tag-ads { background: #ECFDF3; color: #027A48; border: 1px solid #A6F4C5; }
    .service-tag-default { background: #F2F4F7; color: #344054; border: 1px solid #EAECF0; }
    .checkbox-clients-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 8px;
      max-height: 160px;
      overflow-y: auto;
      padding: 10px;
      background: #F8F9FC;
      border: 1px solid #ECEEF5;
      border-radius: 14px;
    }
    .checkbox-client-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12.5px;
      color: #000000;
      cursor: pointer;
    }

    /* Bento Box Design System - Bankio Minimal Finance */
    .bento-metrics-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 20px;
      margin-bottom: 28px;
    }
    @media (max-width: 1024px) {
      .bento-metrics-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
      }
    }
    @media (max-width: 768px) {
      .bento-metrics-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 10px !important;
        margin-bottom: 16px !important;
      }
    }

    .bento-metric-card {
      background-color: #FFFFFF;
      border-radius: 20px;
      padding: 18px 20px;
      border: 1px solid #ECEEF5;
      box-shadow: 0 10px 30px -10px rgba(18, 24, 48, 0.05), 0 2px 6px -1px rgba(18, 24, 48, 0.02);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      min-height: 110px;
      transition: transform 220ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 220ms cubic-bezier(0.22, 1, 0.36, 1), border-color 220ms ease;
      will-change: transform;
    }
    .bento-metric-card:hover {
      box-shadow: 0 14px 34px -8px rgba(18, 24, 48, 0.08);
      border-color: #DDE1EE;
      transform: translate3d(0, -2px, 0);
    }

    .metric-label {
      font-size: 11px;
      font-weight: 700;
      color: #949AB1;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      margin-bottom: 6px;
    }
    .metric-value {
      font-size: 22px;
      font-weight: 800;
      color: #101828;
      line-height: 1.2;
      letter-spacing: -0.02em;
      margin-bottom: 6px;
      display: flex;
      align-items: baseline;
      gap: 5px;
    }
    .metric-num {
      font-size: 24px;
      font-weight: 800;
      color: #101828;
      line-height: 1.15;
    }
    .metric-unit {
      font-size: 13px;
      font-weight: 600;
      color: #6E7485;
      letter-spacing: normal;
    }
    .metric-subtitle {
      font-size: 12px;
      color: #6E7485;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    /* Bento Table Card Wrapper */
    .bento-table-card {
      background-color: #FFFFFF;
      border-radius: 22px;
      border: 1px solid #ECEEF5;
      box-shadow: 0 10px 30px -10px rgba(18, 24, 48, 0.05), 0 2px 6px -1px rgba(18, 24, 48, 0.02);
      padding: 24px;
      overflow: visible;
      margin-bottom: 28px;
    }

    /* Bento Toolbar */
    .bento-toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 100%;
      margin-bottom: 24px;
      flex-wrap: wrap;
      gap: 16px;
    }

    @media (max-width: 768px) {
      .bento-table-card {
        padding: 16px 14px !important;
        border-radius: 18px !important;
        margin-bottom: 18px !important;
      }
      .team-top-header-card {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 12px !important;
      }
      .team-top-header-card .btn-primary {
        width: 100% !important;
        justify-content: center !important;
      }
      .bento-toolbar {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 10px !important;
        margin-bottom: 16px !important;
      }
      .bento-tabs-group {
        width: 100% !important;
        display: flex !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
        white-space: nowrap !important;
        scrollbar-width: none !important;
        gap: 4px !important;
        padding: 4px !important;
      }
      .bento-tabs-group::-webkit-scrollbar {
        display: none !important;
      }
      .bento-tab-btn {
        flex-shrink: 0 !important;
        white-space: nowrap !important;
        padding: 7px 14px !important;
        font-size: 12.5px !important;
        border-radius: 999px !important;
      }
      .bento-search-box {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 100% !important;
      }
      .bento-search-input {
        width: 100% !important;
        height: 40px !important;
        font-size: 13px !important;
      }
    }

    .bento-tabs-group {
      display: inline-flex;
      background: #EEF0F6;
      padding: 5px;
      border-radius: 999px;
      gap: 4px;
      border: 1px solid #ECEEF5;
    }

    .bento-tab-btn {
      background: transparent;
      border: none;
      color: #6E7485;
      font-size: 13px;
      font-weight: 600;
      padding: 8px 18px;
      border-radius: 999px;
      cursor: pointer;
      transition: background-color 200ms cubic-bezier(0.22, 1, 0.36, 1), color 200ms cubic-bezier(0.22, 1, 0.36, 1), transform 150ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 200ms ease;
      font-family: inherit;
      will-change: transform;
    }
    .bento-tab-btn:hover:not(.active) {
      color: #000000;
      transform: translate3d(0, -1px, 0);
    }
    .bento-tab-btn:active {
      transform: scale(0.96);
      transition-duration: 90ms;
    }
    .bento-tab-btn.active {
      background: #5D76F5;
      color: #FFFFFF;
      font-weight: 700;
      box-shadow: 0 4px 12px rgba(93, 118, 245, 0.28);
    }

    .bento-search-box {
      position: relative;
      min-width: 280px;
      max-width: 360px;
      flex: 1;
    }
    .bento-search-icon {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 14px;
      color: #949AB1;
      pointer-events: none;
      line-height: 1;
    }
    .bento-search-input {
      width: 100%;
      padding: 10px 14px 10px 38px;
      border-radius: 14px;
      border: 1px solid #ECEEF5;
      background: #FFFFFF;
      font-size: 13px;
      color: #000000;
      outline: none;
      font-family: inherit;
      transition: border-color 200ms ease, box-shadow 200ms ease;
    }
    .bento-search-input:focus {
      background: #FFFFFF;
      border-color: #5D76F5;
      box-shadow: 0 0 0 4px rgba(93, 118, 245, 0.15);
    }
    .bento-search-input::placeholder {
      color: #949AB1;
    }
  </style>
</head>
<body>
  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body">

        <!-- Top Header Card -->
        <div class="team-top-header-card" style="background: #FFFFFF; border: 1px solid #ECEEF5; border-radius: 22px; padding: 24px; margin-bottom: 22px; box-shadow: 0 10px 30px -10px rgba(18, 24, 48, 0.05), 0 2px 6px -1px rgba(18, 24, 48, 0.02); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
          <div>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
              <span class="badge" style="background: rgba(93, 118, 245, 0.12); color: #5D76F5; font-weight: 700; font-size: 11px; border-radius: 999px; padding: 3px 10px; border: 1px solid rgba(93, 118, 245, 0.2);">
                MANAJEMEN SDM & TALENTA
              </span>
              <span style="font-size: 12px; color: #949AB1;">&bull;</span>
              <span style="font-size: 12px; color: #6E7485; font-weight: 600;">
                Kalamedia Internal
              </span>
            </div>
            <h2 style="font-size: 20px; font-weight: 800; color: #000000; margin: 0; letter-spacing: -0.3px;">
              Tim & Karyawan Kalamedia
            </h2>
            <p style="font-size: 13px; color: #6E7485; margin: 4px 0 0;">
              Kelola data staf in-house dan freelancer, rincian jobdesk dari Owner, penugasan klien/PT, serta akun akses login ke sistem.
            </p>
          </div>

          <div>
            <button type="button" class="btn btn-primary" onclick="openModal('modal-create-team-member')">
              <span>+ Tambah Karyawan / Tim</span>
            </button>
          </div>
        </div>

        <!-- Top Metrics Summary (4-Column Bento Grid) -->
        <div class="bento-metrics-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
          <!-- Card 1: Total Tim Aktif -->
          <div class="bento-metric-card">
            <div>
              <div class="metric-label">TOTAL TIM AKTIF</div>
              <div class="metric-value">
                <span class="metric-num"><?= $totalMembers ?></span>
                <span class="metric-unit">Orang</span>
              </div>
            </div>
            <div class="metric-subtitle">
              <span>👥 Tim Internal & Eksternal</span>
            </div>
          </div>

          <!-- Card 2: Staf In-House -->
          <div class="bento-metric-card">
            <div>
              <div class="metric-label">STAF IN-HOUSE</div>
              <div class="metric-value">
                <span class="metric-num"><?= $karyawanCount ?></span>
                <span class="metric-unit">Staf</span>
              </div>
            </div>
            <div class="metric-subtitle">
              <span>💼 Karyawan Akses Portal</span>
            </div>
          </div>

          <!-- Card 3: Freelancer -->
          <div class="bento-metric-card">
            <div>
              <div class="metric-label">FREELANCER</div>
              <div class="metric-value">
                <span class="metric-num"><?= $freelancerCount ?></span>
                <span class="metric-unit">Talenta</span>
              </div>
            </div>
            <div class="metric-subtitle">
              <span>⚡ Talenta Eksternal</span>
            </div>
          </div>

          <!-- Card 4: Klien Terdaftar -->
          <div class="bento-metric-card">
            <div>
              <div class="metric-label">KLIEN TERDAFTAR</div>
              <div class="metric-value">
                <span class="metric-num"><?= $totalClientsCount ?></span>
                <span class="metric-unit">Perusahaan / PT</span>
              </div>
            </div>
            <div class="metric-subtitle">
              <span>🏢 Klien Aktif</span>
            </div>
          </div>
        </div>

        <!-- Unified Table Toolbar & Card Wrapper (Bento Container) -->
        <div class="bento-table-card" style="overflow: visible; min-width: 0; max-width: 100%;">
          <div style="margin-bottom: 20px;">
            <h3 style="font-size: 18px; font-weight: 800; color: #000000; margin: 0; letter-spacing: -0.3px;">
              Daftar Anggota Tim & Alokasi Klien
            </h3>
            <p style="font-size: 13px; color: #6E7485; margin: 4px 0 0;">
              Daftar seluruh tim yang memiliki hak akses pengerjaan deliverables dan workspace.
            </p>
          </div>

          <!-- Unified Toolbar: Filter Tabs on LEFT, Search Input on RIGHT -->
          <div class="bento-toolbar">
            <!-- Left: Filter Tabs -->
            <div class="bento-tabs-group">
              <button type="button" class="bento-tab-btn active" onclick="filterTeamRole('all', this)">Semua</button>
              <button type="button" class="bento-tab-btn" onclick="filterTeamRole('karyawan', this)">Karyawan In-House</button>
              <button type="button" class="bento-tab-btn" onclick="filterTeamRole('freelancer', this)">Freelancer</button>
            </div>

            <!-- Right: Search Input -->
            <div class="bento-search-box">
              <span class="bento-search-icon">🔍</span>
              <input 
                type="text" 
                id="team-search-input" 
                class="bento-search-input" 
                placeholder="Cari nama, email, atau posisi..." 
                oninput="handleTeamSearch(this.value)"
                autocomplete="off"
              >
            </div>
          </div>

          <div class="kala-table-scroll table-responsive" style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; display:block;">
            <table class="table-custom" style="min-width: 860px; width: 100%;">
              <thead>
                <tr>
                  <th style="min-width: 220px; white-space: nowrap;">NAMA &amp; KONTAK</th>
                  <th style="min-width: 130px; white-space: nowrap;">STATUS SDM</th>
                  <th style="min-width: 200px; white-space: nowrap;">SPESIALISASI JASA &amp; JOBDESK</th>
                  <th style="min-width: 150px; white-space: nowrap;">MASA KONTRAK KERJA</th>
                  <th style="min-width: 180px; white-space: nowrap;">KLIEN / PT YANG DIPEGANG</th>
                  <th style="min-width: 160px; white-space: nowrap;">EMAIL LOGIN PORTAL</th>
                  <th style="min-width: 120px; text-align: right; white-space: nowrap;">AKSI</th>
                </tr>
              </thead>
              <tbody id="team-table-body">
                <tr id="search-empty-team-row" style="display: none;">
                  <td colspan="7" style="text-align: center; color: #949AB1; padding: 48px;">
                    <div style="font-size: 26px; margin-bottom: 6px;">🔍</div>
                    <div style="font-weight: 700; color: #000000; margin-bottom: 4px; font-size: 14px;">Tidak Ada Anggota Tim yang Cocok</div>
                    <p style="font-size: 12.5px; color: #6E7485; margin: 0;">Silakan coba kata kunci pencarian atau filter tab lainnya.</p>
                  </td>
                </tr>
                <?php if (empty($teamMembers)): ?>
                  <tr id="empty-team-row">
                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 48px;">
                      <div style="font-size: 28px; margin-bottom: 8px;">👥</div>
                      <div style="font-weight: 700; color: #101828; margin-bottom: 4px;">Belum Ada Anggota Tim</div>
                      <p style="font-size: 13px; color: var(--text-secondary); margin: 0 auto 16px; max-width: 400px;">
                        Klik tombol "+ Tambah Karyawan / Tim" di atas untuk mendaftarkan karyawan atau freelancer pertama Anda.
                      </p>
                      <button type="button" class="btn btn-primary btn-sm" onclick="openModal('modal-create-team-member')">
                        + Tambah Karyawan / Tim
                      </button>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php 
                  $today = date('Y-m-d');
                  foreach ($teamMembers as $m): 
                    $spec = $m['service_specialty'] ?? '';
                    $specClass = 'service-tag-default';
                    if (stripos($spec, 'sosmed') !== false) $specClass = 'service-tag-sosmed';
                    elseif (stripos($spec, 'desain') !== false) $specClass = 'service-tag-desain';
                    elseif (stripos($spec, 'web') !== false) $specClass = 'service-tag-web';
                    elseif (stripos($spec, 'iklan') !== false) $specClass = 'service-tag-ads';

                    $wStart = $m['contract_start_date'] ?? null;
                    $wEnd = $m['contract_end_date'] ?? null;
                    $wExpired = false;
                    $wDaysLeft = null;

                    if ($wEnd) {
                        $diff = (int)ceil((strtotime($wEnd) - strtotime($today)) / 86400);
                        if ($diff < 0) {
                            $wExpired = true;
                        } else {
                            $wDaysLeft = $diff;
                        }
                    }
                  ?>
                    <tr class="team-row" data-role="<?= htmlspecialchars($m['employment_type']) ?>" data-specialty="<?= htmlspecialchars(strtolower($spec)) ?>">
                      <td style="min-width: 220px; white-space: nowrap;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                          <div style="width: 36px; height: 36px; border-radius: 50%; background: #EFF8FF; color: #175CD3; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; flex-shrink: 0; border: 1px solid #B2DDFF;">
                            <?= strtoupper(substr($m['name'], 0, 2)) ?>
                          </div>
                          <div>
                            <div style="font-weight: 800; color: #101828; font-size: 13.5px;">
                              <?= htmlspecialchars($m['name']) ?>
                            </div>
                            <?php if (!empty($m['phone_wa'])): ?>
                              <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $m['phone_wa']) ?>" target="_blank" style="font-size: 11px; color: #027A48; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                <span>WhatsApp: <?= htmlspecialchars($m['phone_wa']) ?></span>
                              </a>
                            <?php endif; ?>
                          </div>
                        </div>
                      </td>

                      <td style="min-width: 130px; white-space: nowrap;">
                        <?php if ($m['employment_type'] === 'freelancer'): ?>
                          <span class="team-badge-freelancer">Freelancer</span>
                        <?php else: ?>
                          <span class="team-badge-karyawan">Karyawan Kalamedia</span>
                        <?php endif; ?>
                      </td>

                      <td style="min-width: 200px;">
                        <?php if (!empty($spec)): ?>
                          <div>
                            <span class="service-tag <?= $specClass ?>"><?= htmlspecialchars($spec) ?></span>
                          </div>
                        <?php endif; ?>
                        <div style="font-weight: 700; color: #101828; font-size: 12.5px; margin-bottom: 2px;">
                          <?= htmlspecialchars($m['position'] ?: 'Spesialis Tim') ?>
                        </div>
                        <?php if (!empty($m['jobdesk'])): ?>
                          <div style="font-size: 11.5px; color: var(--text-secondary); max-width: 280px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" title="<?= htmlspecialchars($m['jobdesk']) ?>">
                            <?= nl2br(htmlspecialchars($m['jobdesk'])) ?>
                          </div>
                        <?php else: ?>
                          <span style="font-size: 11px; color: var(--text-muted); font-style: italic;">Belum ada rincian jobdesk</span>
                        <?php endif; ?>
                      </td>

                      <td style="min-width: 150px; white-space: nowrap;">
                        <?php if ($wStart && $wEnd): ?>
                          <div style="font-size: 11.5px; color: #344054; font-weight: 600; margin-bottom: 4px;">
                            <?= date('d M Y', strtotime($wStart)) ?> &ndash; <?= date('d M Y', strtotime($wEnd)) ?>
                          </div>
                        <?php else: ?>
                          <div style="font-size: 11px; color: var(--text-muted); font-style: italic; margin-bottom: 4px;">Belum diset</div>
                        <?php endif; ?>

                        <?php if ($wExpired): ?>
                          <span style="background: #FEF3F2; color: #B42318; border: 1px solid #FECDCA; font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 999px;">
                            🔴 Kontrak Berakhir
                          </span>
                        <?php elseif ($wDaysLeft !== null): ?>
                          <span style="background: #ECFDF3; color: #027A48; border: 1px solid #A6F4C5; font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 999px;">
                            🟢 Aktif • Sisa <?= $wDaysLeft ?> Hari
                          </span>
                        <?php endif; ?>
                      </td>

                      <td style="min-width: 180px;">
                        <?php if (!empty($m['assigned_clients'])): ?>
                          <div style="display: flex; flex-wrap: wrap; max-width: 260px; gap: 4px;">
                            <?php foreach ($m['assigned_clients'] as $ac): ?>
                              <span class="client-tag-pill"><?= htmlspecialchars($ac['company']) ?></span>
                            <?php endforeach; ?>
                          </div>
                        <?php else: ?>
                          <span style="font-size: 11px; color: var(--text-tertiary); font-style: italic;">Semua Proyek / Belum dialokasikan</span>
                        <?php endif; ?>
                      </td>

                      <td style="min-width: 160px; white-space: nowrap;">
                        <div style="font-size: 12.5px; color: #101828; font-weight: 600;">
                          <?= htmlspecialchars($m['email']) ?>
                        </div>
                        <span style="font-size: 10.5px; color: #027A48; font-weight: 700; background: #ECFDF3; padding: 1px 6px; border-radius: 4px;">
                          Akses Aktif
                        </span>
                      </td>

                      <td style="min-width: 120px; text-align: right; white-space: nowrap;">
                        <div style="display: inline-flex; gap: 6px; justify-content: flex-end; align-items: center;">
                          <button type="button" class="btn btn-secondary btn-sm" onclick='openEditTeamModal(<?= json_encode($m, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' style="padding: 5px 12px; font-size: 12px; font-weight: 600;">
                            Edit
                          </button>
                          <button type="button" class="btn-delete-ghost" onclick="confirmDeleteTeamMember(<?= $m['id'] ?>, '<?= htmlspecialchars($m['name'], ENT_QUOTES) ?>')" style="padding: 5px 10px; font-size: 12px; font-weight: 600;">
                            Hapus
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

  <!-- Modal 1: Tambah Karyawan / Tim Baru -->
  <div id="modal-create-team-member" class="modal-backdrop">
    <div class="modal-dialog modal-lg">
      <div class="modal-header">
        <h3 class="modal-title">+ Tambah Karyawan / Tim Baru</h3>
        <button type="button" class="modal-close" onclick="closeModal('modal-create-team-member')">&times;</button>
      </div>
      <form id="form-create-team-member" action="api/team.php?action=save" method="POST">
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Nama Lengkap Karyawan / Tim <span style="color:red;">*</span></label>
              <input type="text" name="name" class="form-control" placeholder="Contoh: Budi Santoso" required>
            </div>
            <div class="form-group">
              <label class="form-label">Status SDM <span style="color:red;">*</span></label>
              <select name="employment_type" class="form-select" required>
                <option value="karyawan">Karyawan Kalamedia (In-House)</option>
                <option value="freelancer">Freelancer (Talenta Eksternal)</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Bidang Jasa / Spesialisasi Kalamedia <span style="color:red;">*</span></label>
              <select name="service_specialty" class="form-select" required>
                <option value="Kelola Sosmed">Kelola Sosmed (Social Media Management)</option>
                <option value="Desain Eceran / Satuan">Desain Eceran / Satuan (Desain 1-an)</option>
                <option value="Jasa Buat Web">Jasa Buat Web (Website &amp; Landing Page)</option>
                <option value="Jasa Iklan Meta & Google">Jasa Iklan Meta &amp; Google (Meta Ads &amp; Google Ads)</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Posisi / Jabatan Spesifik <span style="color:red;">*</span></label>
              <input type="text" name="position" class="form-control" placeholder="Contoh: Meta & Google Ads Specialist, Graphic Designer" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Mulai Kontrak Kerja <span style="color:red;">*</span></label>
              <input type="date" name="contract_start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Akhir Kontrak Kerja <span style="color:red;">*</span></label>
              <input type="date" name="contract_end_date" class="form-control" value="<?= date('Y-m-d', strtotime('+6 months')) ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Detail Jobdesk dari Owner <span style="color:red;">*</span></label>
            <textarea name="jobdesk" class="form-control" rows="3" placeholder="Tuliskan rincian tanggung jawab dan tugas utama yang diberikan oleh Owner kepada dia..." required></textarea>
          </div>

          <!-- Pegangan Klien / Proyek PT -->
          <div class="form-group">
            <label class="form-label">Pegangan Klien / Proyek PT (Pilih Perusahaan yang Dipegang)</label>
            <div class="checkbox-clients-grid">
              <?php if (empty($allClients)): ?>
                <div style="font-size: 12px; color: var(--text-muted); grid-column: 1 / -1;">Belum ada data klien terdaftar. Anda dapat menambahkannya nanti.</div>
              <?php else: ?>
                <?php foreach ($allClients as $cl): ?>
                  <label class="checkbox-client-item">
                    <input type="checkbox" name="client_ids[]" value="<?= $cl['id'] ?>">
                    <span><b><?= htmlspecialchars($cl['company']) ?></b> (PIC: <?= htmlspecialchars($cl['pic_name']) ?>)</span>
                  </label>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 16px 0;">
          <div style="font-size: 12px; font-weight: 800; color: #101828; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">
            Kredensial Akses Login Sistem Kalamedia
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Email / Username Login <span style="color:red;">*</span></label>
              <input type="email" name="email" class="form-control" placeholder="karyawan@kalamedia.id" required>
            </div>
            <div class="form-group">
              <label class="form-label">Password Akses Sistem <span style="color:red;">*</span></label>
              <div style="position: relative; display: flex; align-items: center;">
                <input type="password" name="password" id="create-team-password" class="form-control" placeholder="Password untuk login" required style="padding-right: 55px;">
                <button type="button" onclick="togglePasswordVisibility('create-team-password', this)" style="position: absolute; right: 10px; background: none; border: none; font-size: 11px; font-weight: 700; color: var(--primary); cursor: pointer; padding: 4px;">Lihat</button>
              </div>
            </div>
          </div>

          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Nomor WhatsApp (Untuk Notifikasi Tugas & Revisi)</label>
            <input type="text" name="phone_wa" class="form-control" placeholder="Contoh: 081234567890">
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-team-member')">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan & Buat Akun Karyawan</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal 2: Edit Karyawan / Tim -->
  <div id="modal-edit-team-member" class="modal-backdrop">
    <div class="modal-dialog modal-lg">
      <div class="modal-header">
        <h3 class="modal-title">Edit Data Tim & Jobdesk</h3>
        <button type="button" class="modal-close" onclick="closeModal('modal-edit-team-member')">&times;</button>
      </div>
      <form id="form-edit-team-member" action="api/team.php?action=save" method="POST">
        <input type="hidden" name="id" id="edit-team-id">
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Nama Lengkap Karyawan / Tim <span style="color:red;">*</span></label>
              <input type="text" name="name" id="edit-team-name" class="form-control" required>
            </div>
            <div class="form-group">
              <label class="form-label">Status SDM <span style="color:red;">*</span></label>
              <select name="employment_type" id="edit-team-emp-type" class="form-select" required>
                <option value="karyawan">Karyawan Kalamedia (In-House)</option>
                <option value="freelancer">Freelancer (Talenta Eksternal)</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Bidang Jasa / Spesialisasi Kalamedia <span style="color:red;">*</span></label>
              <select name="service_specialty" id="edit-team-specialty" class="form-select" required>
                <option value="Kelola Sosmed">Kelola Sosmed (Social Media Management)</option>
                <option value="Desain Eceran / Satuan">Desain Eceran / Satuan (Desain 1-an)</option>
                <option value="Jasa Buat Web">Jasa Buat Web (Website &amp; Landing Page)</option>
                <option value="Jasa Iklan Meta & Google">Jasa Iklan Meta &amp; Google (Meta Ads &amp; Google Ads)</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Posisi / Jabatan Spesifik <span style="color:red;">*</span></label>
              <input type="text" name="position" id="edit-team-position" class="form-control" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Mulai Kontrak Kerja <span style="color:red;">*</span></label>
              <input type="date" name="contract_start_date" id="edit-team-start-date" class="form-control" required>
            </div>
            <div class="form-group">
              <label class="form-label">Akhir Kontrak Kerja <span style="color:red;">*</span></label>
              <input type="date" name="contract_end_date" id="edit-team-end-date" class="form-control" required>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Detail Jobdesk dari Owner <span style="color:red;">*</span></label>
            <textarea name="jobdesk" id="edit-team-jobdesk" class="form-control" rows="3" required></textarea>
          </div>

          <!-- Pegangan Klien / Proyek PT -->
          <div class="form-group">
            <label class="form-label">Pegangan Klien / Proyek PT</label>
            <div class="checkbox-clients-grid" id="edit-team-clients-container">
              <?php foreach ($allClients as $cl): ?>
                <label class="checkbox-client-item">
                  <input type="checkbox" name="client_ids[]" value="<?= $cl['id'] ?>" class="edit-client-checkbox">
                  <span><b><?= htmlspecialchars($cl['company']) ?></b></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 16px 0;">
          <div style="font-size: 12px; font-weight: 800; color: #101828; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">
            Kredensial Akses Login
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Email / Username Login <span style="color:red;">*</span></label>
              <input type="email" name="email" id="edit-team-email" class="form-control" required>
            </div>
            <div class="form-group">
              <label class="form-label">Reset Password (Kosongkan jika tidak diubah)</label>
              <div style="position: relative; display: flex; align-items: center;">
                <input type="password" name="password" id="edit-team-password" class="form-control" placeholder="Masukkan password baru jika ingin reset" style="padding-right: 55px;">
                <button type="button" onclick="togglePasswordVisibility('edit-team-password', this)" style="position: absolute; right: 10px; background: none; border: none; font-size: 11px; font-weight: 700; color: var(--primary); cursor: pointer; padding: 4px;">Lihat</button>
              </div>
            </div>
          </div>

          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Nomor WhatsApp</label>
            <input type="text" name="phone_wa" id="edit-team-phone" class="form-control">
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-team-member')">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function togglePasswordVisibility(inputId, btn) {
      const input = document.getElementById(inputId);
      if (!input) return;
      if (input.type === 'password') {
        input.type = 'text';
        if (btn) btn.innerText = 'Tutup';
      } else {
        input.type = 'password';
        if (btn) btn.innerText = 'Lihat';
      }
    }

    let currentRoleFilter = 'all';
    let currentSearchQuery = '';

    function filterTeamRole(role, btn) {
      document.querySelectorAll('.bento-tab-btn').forEach(b => b.classList.remove('active'));
      if (btn) btn.classList.add('active');
      currentRoleFilter = role;
      applyTeamFilters();
    }

    function handleTeamSearch(val) {
      currentSearchQuery = (val || '').trim().toLowerCase();
      applyTeamFilters();
    }

    function applyTeamFilters() {
      const rows = document.querySelectorAll('.team-row');
      let matchCount = 0;

      rows.forEach(r => {
        const role = r.getAttribute('data-role') || '';
        const rowText = r.textContent.toLowerCase();

        const roleMatch = (currentRoleFilter === 'all' || role === currentRoleFilter);
        const searchMatch = !currentSearchQuery || rowText.includes(currentSearchQuery);

        if (roleMatch && searchMatch) {
          r.style.display = '';
          matchCount++;
        } else {
          r.style.display = 'none';
        }
      });

      const searchEmpty = document.getElementById('search-empty-team-row');
      const emptyRow = document.getElementById('empty-team-row');
      if (searchEmpty) {
        if (matchCount === 0 && rows.length > 0) {
          searchEmpty.style.display = '';
        } else {
          searchEmpty.style.display = 'none';
        }
      }
    }

    function openEditTeamModal(m) {
      document.getElementById('edit-team-id').value = m.id || '';
      document.getElementById('edit-team-name').value = m.name || '';
      document.getElementById('edit-team-emp-type').value = m.employment_type || 'karyawan';
      document.getElementById('edit-team-specialty').value = m.service_specialty || 'Kelola Sosmed';
      document.getElementById('edit-team-position').value = m.position || '';
      document.getElementById('edit-team-start-date').value = m.contract_start_date || '';
      document.getElementById('edit-team-end-date').value = m.contract_end_date || '';
      document.getElementById('edit-team-jobdesk').value = m.jobdesk || '';
      document.getElementById('edit-team-email').value = m.email || '';
      document.getElementById('edit-team-phone').value = m.phone_wa || '';
      document.getElementById('edit-team-password').value = '';

      // Checkboxes
      const clientIds = (m.client_ids || []).map(Number);
      document.querySelectorAll('.edit-client-checkbox').forEach(cb => {
        cb.checked = clientIds.includes(parseInt(cb.value));
      });

      openModal('modal-edit-team-member');
    }

    async function confirmDeleteTeamMember(id, name) {
      if (!confirm(`Apakah Anda yakin ingin menghapus anggota tim ${name}? Akses login akan dinonaktifkan.`)) {
        return;
      }

      const formData = new FormData();
      formData.append('id', id);

      try {
        const res = await fetch('api/team.php?action=delete', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          setTimeout(() => window.location.reload(), 600);
        } else {
          showToast(data.message || 'Gagal menghapus anggota tim', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      }
    }

    document.addEventListener('DOMContentLoaded', () => {
      handleAjaxForm('form-create-team-member', () => {
        closeModal('modal-create-team-member');
        setTimeout(() => window.location.reload(), 600);
      });

      handleAjaxForm('form-edit-team-member', () => {
        closeModal('modal-edit-team-member');
        setTimeout(() => window.location.reload(), 600);
      });
    });
  </script>
  <?php require_once BASE_PATH . '/includes/footer.php'; ?>

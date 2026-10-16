<?php
/**
 * Kalamedia Agency - Task Management & Workspace Tim View (Untitled UI Design System)
 * Unified interface for Owner, Admin, Karyawan (In-House), and Freelancer
 */
require_auth();

$user = current_user();
$role = current_user_role();
$userId = (int)$user['id'];
$isAdminUser = is_admin();
$isWorkerUser = ($role === 'karyawan' || $role === 'freelancer');
$db = Database::getConnection();

$workerProfile = null;
$assignedClients = [];
if ($isWorkerUser) {
    $stmtW = $db->prepare("SELECT position, jobdesk, employment_type FROM users WHERE id = ?");
    $stmtW->execute([$userId]);
    $workerProfile = $stmtW->fetch();

    $stmtAc = $db->prepare("
        SELECT c.id, c.company, c.name as pic_name, c.package_name,
               (SELECT COUNT(*) FROM tasks WHERE client_id = c.id AND assigned_to_user_id = ? AND COALESCE(is_deleted,0) = 0) as my_task_count
        FROM clients c
        JOIN employee_clients ec ON c.id = ec.client_id
        WHERE ec.user_id = ? AND COALESCE(c.is_deleted,0) = 0
        ORDER BY c.company ASC
    ");
    $stmtAc->execute([$userId, $userId]);
    $assignedClients = $stmtAc->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manajemen Tugas Tim - PT Kala Kausa Prima</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=bankio-typography-3.0">
  <style>
    /* Task Management & Workspace Custom Styles - Bankio Minimal Finance */
    .tasks-header-card {
      background: #FFFFFF;
      border: 1px solid #ECEEF5;
      border-radius: 22px;
      padding: 24px 26px;
      margin-bottom: 22px;
      box-shadow: 0 10px 30px -10px rgba(18, 24, 48, 0.05), 0 2px 6px -1px rgba(18, 24, 48, 0.02);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 16px;
    }

    .workspace-tabs-bar {
      display: inline-flex;
      gap: 6px;
      margin-bottom: 24px;
      background: #EEF0F6;
      border: 1px solid #ECEEF5;
      padding: 5px;
      border-radius: 999px;
    }
    .workspace-tab-btn {
      border: none;
      background: transparent;
      padding: 9px 18px;
      border-radius: 999px;
      font-size: 13px;
      font-weight: 600;
      color: #6E7485;
      cursor: pointer;
      transition: background-color 200ms cubic-bezier(0.22, 1, 0.36, 1), color 200ms cubic-bezier(0.22, 1, 0.36, 1), transform 150ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 200ms ease;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-family: inherit;
      will-change: transform;
    }
    .workspace-tab-btn:hover:not(.active) {
      color: #000000;
      transform: translate3d(0, -1px, 0);
    }
    .workspace-tab-btn:active {
      transform: scale(0.96);
      transition-duration: 90ms;
    }
    .workspace-tab-btn.active {
      background: #5D76F5;
      color: #FFFFFF;
      font-weight: 700;
      box-shadow: 0 4px 14px rgba(93, 118, 245, 0.3);
    }

    .kpi-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 18px;
      margin-bottom: 24px;
    }
    .kpi-tile {
      background: #FFFFFF;
      border: 1px solid #ECEEF5;
      border-radius: 22px;
      padding: 22px 24px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      box-shadow: 0 10px 30px -10px rgba(18, 24, 48, 0.05), 0 2px 6px -1px rgba(18, 24, 48, 0.02);
      transition: transform 220ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 220ms cubic-bezier(0.22, 1, 0.36, 1), border-color 220ms ease;
      will-change: transform;
    }
    .kpi-tile:hover {
      transform: translate3d(0, -2px, 0);
      box-shadow: 0 14px 34px -8px rgba(18, 24, 48, 0.08);
      border-color: #DDE1EE;
    }
    .kpi-tile:nth-child(1) { animation: kalaCardEnter 280ms cubic-bezier(0.22, 1, 0.36, 1) 0ms both; }
    .kpi-tile:nth-child(2) { animation: kalaCardEnter 280ms cubic-bezier(0.22, 1, 0.36, 1) 35ms both; }
    .kpi-tile:nth-child(3) { animation: kalaCardEnter 280ms cubic-bezier(0.22, 1, 0.36, 1) 70ms both; }
    .kpi-tile:nth-child(4) { animation: kalaCardEnter 280ms cubic-bezier(0.22, 1, 0.36, 1) 105ms both; }
    .kpi-tile:nth-child(5) { animation: kalaCardEnter 280ms cubic-bezier(0.22, 1, 0.36, 1) 140ms both; }
    .kpi-tile.urgent {
      border-color: #FECDCA;
      background: #FEF3F2;
    }
    .kpi-tile.review {
      border-color: #FEDF89;
      background: #FFFAEB;
    }

    /* Filter Bar */
    .filter-panel {
      background: #FFFFFF;
      border: 1px solid #ECEEF5;
      border-radius: 20px;
      padding: 16px 20px;
      margin-bottom: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
      box-shadow: 0 10px 30px -10px rgba(18, 24, 48, 0.04);
    }

    /* Tasks Grid / Cards */
    .tasks-grid-list {
      display: grid;
      grid-template-columns: 1fr;
      gap: 16px;
    }
    .task-item-card {
      background: #FFFFFF;
      border: 1px solid #ECEEF5;
      border-radius: 20px;
      padding: 20px 22px;
      transition: transform 220ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 220ms cubic-bezier(0.22, 1, 0.36, 1), border-color 220ms ease;
      display: flex;
      flex-direction: column;
      gap: 12px;
      box-shadow: 0 10px 30px -10px rgba(18, 24, 48, 0.05), 0 2px 6px -1px rgba(18, 24, 48, 0.02);
      will-change: transform;
    }
    .task-item-card:hover {
      border-color: #DDE1EE;
      box-shadow: 0 14px 34px -8px rgba(18, 24, 48, 0.08);
      transform: translate3d(0, -2px, 0);
    }
    .task-item-card.has-revision {
      border-left: 4px solid #EF4444;
      background: #FFFDFD;
    }
    .task-item-card.has-review {
      border-left: 4px solid #F59E0B;
      background: #FFFEF9;
    }
    .task-item-card.is-approved {
      border-left: 4px solid #10B981;
    }

    .badge-worker {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-size: 11px;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 12px;
    }
    .badge-worker.karyawan {
      background: #EFF8FF;
      color: #175CD3;
      border: 1px solid #B2DDFF;
    }
    .badge-worker.freelancer {
      background: #F4F3FF;
      color: #5925DC;
      border: 1px solid #D9D6FE;
    }

    .badge-priority {
      font-size: 10.5px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      padding: 2px 7px;
      border-radius: 4px;
    }
    .badge-priority.urgent { background: #FEF3F2; color: #B42318; border: 1px solid #FECDCA; }
    .badge-priority.high { background: #FFFAEB; color: #B54708; border: 1px solid #FEDF89; }
    .badge-priority.medium { background: #F8F9FC; color: #344054; border: 1px solid #EAECF0; }
    .badge-priority.low { background: #F2F4F7; color: #667085; }

    .status-pill {
      font-size: 11px;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 12px;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }
    .status-pill.backlog { background: #F2F4F7; color: #475467; }
    .status-pill.in_progress { background: #EFF8FF; color: #175CD3; }
    .status-pill.internal_review { background: #EEF4FF; color: #3538CD; }
    .status-pill.client_review { background: #FEF0C7; color: #B54708; }
    .status-pill.revision { background: #FEE4E2; color: #B42318; }
    .status-pill.approved { background: #D1FADF; color: #027A48; }

    /* Alert Revision Box */
    .alert-revision-box {
      background: #FEF3F2;
      border: 1px solid #FECDCA;
      border-radius: var(--radius-md);
      padding: 16px 20px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }

    /* Discussion Thread */
    .discussion-thread {
      max-height: 380px;
      overflow-y: auto;
      padding: 16px;
      background: #F8FAFC;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-sm);
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-bottom: 18px;
    }
    .bubble {
      max-width: 85%;
      padding: 10px 14px;
      border-radius: 12px;
      font-size: 12.5px;
      line-height: 1.5;
    }
    .bubble.client-bubble {
      align-self: flex-start;
      background: #5D76F5;
      color: #FFFFFF;
      border-bottom-left-radius: 4px;
    }
    .bubble.team-bubble {
      align-self: flex-end;
      background: #FFFFFF;
      color: #000000;
      border: 1px solid #ECEEF5;
      border-bottom-right-radius: 4px;
    }
    .bubble-meta {
      font-size: 10.5px;
      margin-bottom: 4px;
      opacity: 0.8;
      display: flex;
      align-items: center;
      gap: 6px;
    }
  </style>
</head>
<body>

  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body">

        <!-- Header Card -->
        <div class="tasks-header-card">
          <div>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
              <span class="badge" style="background: #F2F4F7; color: #101828; font-weight: 800; font-size: 11px;">
                <?= $isAdminUser ? 'ADMIN & PM WORKSPACE' : 'TIM PRODUKSI' ?>
              </span>
              <span style="font-size: 12px; color: var(--text-muted);">&bull;</span>
              <span style="font-size: 12px; color: var(--text-secondary); font-weight: 600;">
                Login sebagai: <b><?= htmlspecialchars($user['name']) ?></b> (<?= ucfirst($role) ?>)
              </span>
            </div>
            <h2 style="font-size: 20px; font-weight: 800; color: #101828; margin: 0; letter-spacing: -0.3px;">
              <?= $isAdminUser ? 'Manajemen Tugas & Pembagian Kerja SDM' : 'Workspace Tugas Saya & Pelacakan Progres' ?>
            </h2>
            <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0;">
              <?= $isAdminUser 
                ? 'Distribusikan deliverables proyek ke Karyawan In-House dan Freelancer, serta pantau progres dan revisi klien.' 
                : 'Daftar deliverables yang ditugaskan kepada Anda. Perbarui link hasil kerja dan tindak lanjuti masukan klien.' ?>
            </p>
          </div>

          <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <?php if ($isAdminUser): ?>
              <button class="btn btn-primary btn-sm" onclick="openAssignTaskModal()">
                <span>+ Bagi Tugas Baru (Assign)</span>
              </button>
              <button class="btn btn-secondary btn-sm" onclick="deleteAllTasks()" style="color: #B42318; border-color: #FECDCA; background: #FEF3F2; font-weight: 700;" title="Hapus Semua Tugas">
                <span>Hapus Semua Tugas</span>
              </button>
            <?php endif; ?>
            <button class="btn btn-secondary btn-sm" onclick="loadTasksData()">
              <span>Segarkan</span>
            </button>
          </div>
        </div>

        <!-- Section Switcher Tabs -->
        <div class="workspace-tabs-bar">
          <button type="button" class="workspace-tab-btn active" id="tab-btn-tasks" onclick="switchTaskWorkspaceTab('tasks')">
            Deliverables & Penugasan Tim
          </button>
          <button type="button" class="workspace-tab-btn" id="tab-btn-ads" onclick="switchTaskWorkspaceTab('ads')">
            Pelacakan & Koordinasi Anggaran Ads
          </button>
        </div>

        <!-- SECTION 1: Tasks & Deliverables -->
        <div id="section-tasks-content">
          <!-- Alert Banner for Workers if Revision / Client Feedback Arrived -->
          <div id="revision-alert-container" style="display: none;"></div>

          <?php if ($isWorkerUser): ?>
            <!-- Worker Info Section: Jobdesk & Assigned Clients -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; margin-bottom: 20px;">
              <!-- 1. Jobdesk Saya dari Owner -->
              <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 20px; box-shadow: 0 1px 3px rgba(16,24,40,0.05);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                  <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #175CD3; background: #EFF8FF; padding: 2px 8px; border-radius: 4px; border: 1px solid #B2DDFF;">
                    JOBDESK DARI OWNER
                  </span>
                  <span style="font-size: 12px; font-weight: 700; color: #101828;">
                    <?= htmlspecialchars($workerProfile['position'] ?? 'Spesialis Tim') ?>
                  </span>
                </div>
                <h4 style="font-size: 14px; font-weight: 800; color: #101828; margin: 0 0 6px;">Tanggung Jawab Utama Anda:</h4>
                <p style="font-size: 13px; color: var(--text-secondary); margin: 0; line-height: 1.5; white-space: pre-line;">
                  <?= !empty($workerProfile['jobdesk']) ? htmlspecialchars($workerProfile['jobdesk']) : 'Menangani pengerjaan deliverables kreatif, kampanye, dan revisi klien sesuai arahan Owner Kalamedia.' ?>
                </p>
              </div>

              <!-- 2. Klien & Proyek yang Saya Pegang -->
              <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 20px; box-shadow: 0 1px 3px rgba(16,24,40,0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                  <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #027A48; background: #ECFDF3; padding: 2px 8px; border-radius: 4px; border: 1px solid #A6F4C5;">
                    KLIEN & PT YANG SAYA PEGANG
                  </span>
                  <span style="font-size: 12px; font-weight: 700; color: var(--text-muted);">
                    <?= count($assignedClients) ?> Perusahaan
                  </span>
                </div>
                <?php if (empty($assignedClients)): ?>
                  <p style="font-size: 12.5px; color: var(--text-muted); font-style: italic; margin: 0;">
                    Belum ada klien khusus yang dialokasikan oleh Owner ke Anda. Anda dapat mengerjakan seluruh penugasan aktif di bawah.
                  </p>
                <?php else: ?>
                  <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <?php foreach ($assignedClients as $ac): ?>
                      <div style="background: #F9FAFB; border: 1px solid var(--border-color); border-radius: 8px; padding: 8px 12px; display: inline-flex; flex-direction: column; gap: 2px;">
                        <span style="font-size: 13px; font-weight: 800; color: #101828;"><?= htmlspecialchars($ac['company']) ?></span>
                        <span style="font-size: 11px; color: var(--text-secondary);">PIC: <?= htmlspecialchars($ac['pic_name']) ?> &bull; <b><?= (int)$ac['my_task_count'] ?> Tugas</b></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- KPI Metrics Row -->
          <div class="kpi-row">
            <div class="kpi-tile">
              <span style="font-size: 11px; color: #949AB1; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;">TOTAL DELIVERABLES</span>
              <div style="font-size: 24px; font-weight: 800; color: #101828; letter-spacing: -0.02em; line-height: 1.2;" id="kpi-total">0</div>
              <span style="font-size: 12px; color: #6E7485;">Pekerjaan aktif</span>
            </div>

            <div class="kpi-tile urgent">
              <span style="font-size: 11px; color: #B42318; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;">BUTUH REVISI</span>
              <div style="font-size: 24px; font-weight: 800; color: #B42318; letter-spacing: -0.02em; line-height: 1.2;" id="kpi-revision">0</div>
              <span style="font-size: 12px; color: #B42318; font-weight: 600;">Permintaan klien</span>
            </div>

            <div class="kpi-tile review">
              <span style="font-size: 11px; color: #B54708; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;">REVIEW KLIEN</span>
              <div style="font-size: 24px; font-weight: 800; color: #B54708; letter-spacing: -0.02em; line-height: 1.2;" id="kpi-review">0</div>
              <span style="font-size: 12px; color: #B54708; font-weight: 600;">Menunggu approval</span>
            </div>

            <div class="kpi-tile">
              <span style="font-size: 11px; color: #949AB1; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;">SEDANG DIKERJAKAN</span>
              <div style="font-size: 24px; font-weight: 800; color: #101828; letter-spacing: -0.02em; line-height: 1.2;" id="kpi-progress">0</div>
              <span style="font-size: 12px; color: #6E7485;">Tahap produksi</span>
            </div>

            <div class="kpi-tile">
              <span style="font-size: 11px; color: #949AB1; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;">SELESAI & DISETUJUI</span>
              <div style="font-size: 24px; font-weight: 800; color: #101828; letter-spacing: -0.02em; line-height: 1.2;" id="kpi-approved">0</div>
              <span style="font-size: 12px; color: #6E7485;">Hasil final</span>
            </div>
          </div>

          <!-- Filter Panel -->
          <div class="filter-panel">
            <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
              <div class="date-filter-group" id="status-filter-group">
                <button class="filter-btn active" onclick="filterByStatus('all', this)">Semua</button>
                <button class="filter-btn" onclick="filterByStatus('revision', this)">Revisi</button>
                <button class="filter-btn" onclick="filterByStatus('client_review', this)">Review Klien</button>
                <button class="filter-btn" onclick="filterByStatus('in_progress', this)">In Progress</button>
                <button class="filter-btn" onclick="filterByStatus('approved', this)">Selesai</button>
              </div>

              <?php if ($isAdminUser): ?>
                <!-- SDM Type Filter -->
                <select id="filter-worker-type" class="form-control" style="width: auto; padding: 6px 12px; font-size: 12px; font-weight: 600;" onchange="applyFilters()">
                  <option value="">Semua SDM</option>
                  <option value="karyawan">Karyawan (In-House)</option>
                  <option value="freelancer">Freelancer</option>
                </select>
              <?php endif; ?>

              <!-- Category Filter -->
              <select id="filter-category" class="form-control" style="width: auto; padding: 6px 12px; font-size: 12px; font-weight: 600;" onchange="applyFilters()">
                <option value="">Semua Kategori</option>
                <option value="design">Desain Grafis</option>
                <option value="video">Produksi Video</option>
                <option value="copywriting">Copywriting</option>
                <option value="ads">Meta / TikTok Ads</option>
                <option value="web">Web Development</option>
              </select>
            </div>

            <div style="font-size: 12px; color: var(--text-muted);" id="tasks-count-label">
              Menampilkan 0 tugas
            </div>
          </div>

          <!-- Tasks Grid / List Container -->
          <div class="tasks-grid-list" id="tasks-list-container">
            <div style="text-align: center; padding: 48px; background: #FFF; border-radius: var(--radius-md); border: 1px solid var(--border-color); color: var(--text-muted);">
              Memuat data tugas...
            </div>
          </div>
        </div>

        <!-- SECTION 2: Ads Budget & Spend Tracker -->
        <div id="section-ads-content" style="display: none;">
          <!-- Ads Tracker Header & Controls -->
          <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 22px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; box-shadow: 0 1px 3px rgba(16,24,40,0.05);">
            <div>
              <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                <span class="badge" style="background: #F4F3FF; color: #5925DC; border: 1px solid #D9D6FE; font-weight: 800; font-size: 11px;">
                  ADS BUDGET & SPEND TRACKER
                </span>
                <span style="font-size: 12px; color: var(--text-muted);">&bull;</span>
                <span style="font-size: 12px; color: var(--text-secondary); font-weight: 600;">
                  Meta Ads &bull; Google Ads &bull; TikTok Ads
                </span>
              </div>
              <h3 style="font-size: 18px; font-weight: 800; color: #101828; margin: 0;">
                Pelacakan Dana & Koordinasi Anggaran Iklan
              </h3>
              <p style="font-size: 13px; color: var(--text-secondary); margin: 3px 0 0;">
                <?= $isAdminUser 
                  ? 'Kelola pagu saldo ads proyek, pantau realisasi belanja iklan oleh tim Ads, dan kawal margin agensi secara presisi.' 
                  : 'Lihat pagu saldo ads yang dialokasikan Owner untuk setiap proyek dan laporkan nominal anggaran yang sudah terpasang di dashboard.' ?>
              </p>
            </div>

            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
              <button class="btn btn-primary btn-sm" onclick="openUploadAdsTopupModal()" style="font-weight: 700; background: #027A48; border-color: #027A48;">
                <span>+ Upload Bukti Isi Saldo / Transaksi Ads</span>
              </button>
              <?php if ($isAdminUser): ?>
                <button class="btn btn-secondary btn-sm" onclick="openAllocateAdsModal()" style="font-weight: 700;">
                  <span>+ Atur Pagu Saldo Ads</span>
                </button>
              <?php endif; ?>
              <button class="btn btn-secondary btn-sm" onclick="openLogAdsSpendModal()" style="font-weight: 700;">
                <span>+ Update Anggaran Terpasang & Bukti</span>
              </button>
              <button class="btn btn-secondary btn-sm" onclick="loadAdsTrackerData()">
                <span>Segarkan</span>
              </button>
            </div>
          </div>

          <!-- Summary KPI Cards -->
          <div class="kpi-row" id="ads-kpi-container">
            <div class="kpi-tile">
              <span style="font-size: 11.5px; color: var(--text-secondary); font-weight: 600;">Memuat Ringkasan...</span>
            </div>
          </div>

          <!-- Top-Up Transactions & Proof Table Card (Automatic Sync to Client Portal) -->
          <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(16,24,40,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
              <div>
                <div style="display: flex; align-items: center; gap: 8px;">
                  <h4 style="font-size: 15px; font-weight: 800; color: #101828; margin: 0;">Riwayat & Bukti Transaksi Saldo Iklan (Ads Top-Up & Spend)</h4>
                  <span class="badge" style="background: #ECFDF3; color: #027A48; font-weight: 700; font-size: 10.5px;">Tampil di Portal Klien</span>
                </div>
                <span style="font-size: 12px; color: var(--text-secondary);">
                  Data pengisian saldo (Top-Up) yang diunggah oleh Tim Ads dan otomatis dapat ditinjau oleh Klien di Client Portal.
                </span>
              </div>
              <button type="button" class="btn btn-secondary btn-sm" onclick="openUploadAdsTopupModal()" style="font-size: 12px; font-weight: 700; color: #027A48; border-color: #A6F4C5; background: #ECFDF3;">
                + Upload Bukti Baru
              </button>
            </div>

            <div class="table-responsive">
              <table class="table-custom" id="table-ads-topups">
                <thead>
                  <tr>
                    <th>TANGGAL & PETUGAS ADS</th>
                    <th>PROYEK & KLIEN PT</th>
                    <th>PLATFORM</th>
                    <th>NOMINAL SALDO DIISI</th>
                    <th>ANGGARAN TERPASANG</th>
                    <th>CATATAN TIM ADS</th>
                    <th>BUKTI TRANSAKSI</th>
                    <th>STATUS</th>
                    <?php if ($isAdminUser): ?>
                      <th style="text-align: right;">AKSI</th>
                    <?php endif; ?>
                  </tr>
                </thead>
                <tbody id="tbody-ads-topups">
                  <tr><td colspan="<?= $isAdminUser ? 9 : 8 ?>" style="text-align: center; padding: 24px; color: var(--text-muted);">Memuat riwayat isi saldo ads...</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Projects Ads Budget Table Card -->
          <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(16,24,40,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
              <div>
                <h4 style="font-size: 15px; font-weight: 800; color: #101828; margin: 0;">Status Alokasi & Anggaran Terpasang per Proyek</h4>
                <span style="font-size: 12px; color: var(--text-secondary);">
                  <?= $isAdminUser ? 'Perbandingan Kontrak, Pagu Saldo Ads, Spend Terpasang, dan Sisa Saldo' : 'Alokasi Pagu Saldo Ads yang Ditugaskan Owner & Realisasi Belanja Iklan' ?>
                </span>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table-custom" id="table-ads-projects">
                <thead>
                  <tr>
                    <th>PROYEK & KLIEN</th>
                    <?php if ($isAdminUser): ?>
                      <th>NILAI KONTRAK (RAHASIA)</th>
                    <?php endif; ?>
                    <th>PAGU SALDO ADS</th>
                    <th>ANGGARAN TERPASANG (SPEND)</th>
                    <th>SISA SALDO ADS</th>
                    <?php if ($isAdminUser): ?>
                      <th>MARGIN BERSIH AGENSI</th>
                    <?php endif; ?>
                    <th>STATUS KAMPANYE</th>
                    <th style="text-align: right;">AKSI</th>
                  </tr>
                </thead>
                <tbody id="tbody-ads-projects">
                  <tr><td colspan="<?= $isAdminUser ? 8 : 6 ?>" style="text-align: center; padding: 28px; color: var(--text-muted);">Memuat data anggaran ads...</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Coordination Logs & Screenshot Proof Feed -->
          <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px; box-shadow: 0 1px 3px rgba(16,24,40,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
              <div>
                <h4 style="font-size: 15px; font-weight: 800; color: #101828; margin: 0;">Riwayat Update & Bukti Screenshot Dashboard Ads</h4>
                <span style="font-size: 12px; color: var(--text-secondary);">Catatan koordinasi langsung antara Tim Ads dan Owner beserta screenshot dashboard</span>
              </div>
            </div>

            <div id="ads-logs-feed-container">
              <div style="text-align: center; padding: 28px; color: var(--text-muted);">Memuat riwayat koordinasi...</div>
            </div>
          </div>
        </div>

      </div>
    </main>
  </div>

  <!-- MODAL: Assign New Task (Owner / Admin) -->
  <?php if ($isAdminUser): ?>
    <div class="modal" id="modal-assign-task">
      <div class="modal-dialog" style="max-width: 620px;">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <h4 class="modal-title">Bagi Tugas Baru (Assign Task)</h4>
              <span style="font-size: 12px; color: var(--text-secondary);">Tugaskan pekerjaan proyek ke Karyawan Internal atau Freelancer</span>
            </div>
            <button type="button" class="close-btn" onclick="closeModal('modal-assign-task')">&times;</button>
          </div>

          <form id="form-assign-task" onsubmit="handleAssignTask(event)">
            <div class="modal-body">
              <div class="form-group">
                <label class="form-label">Pilih Proyek & Klien <span style="color:red;">*</span></label>
                <select name="project_id" id="assign-project-id" class="form-control" required>
                  <option value="">-- Pilih Proyek Klien --</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Judul Pekerjaan / Deliverable <span style="color:red;">*</span></label>
                <input type="text" name="title" class="form-control" placeholder="Contoh: Desain Carousel Feed Promo Merdeka" required>
              </div>

              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                  <label class="form-label">Kategori Pekerjaan <span style="color:red;">*</span></label>
                  <select name="category" class="form-control" required>
                    <option value="design">Desain Grafis (Feed / Story / Banner)</option>
                    <option value="video">Produksi Video (Reels / TikTok / Motion)</option>
                    <option value="copywriting">Copywriting & Naskah Konten</option>
                    <option value="ads">Setup & Optimasi Ads (Meta/TikTok/Google)</option>
                    <option value="web">Web & Landing Page Development</option>
                    <option value="other">Deliverables Lainnya</option>
                  </select>
                </div>

                <div class="form-group">
                  <label class="form-label">Tugaskan Kepada (PIC) <span style="color:red;">*</span></label>
                  <select name="assigned_to_user_id" id="assign-member-id" class="form-control" required>
                    <option value="">-- Pilih Anggota Tim / Freelancer --</option>
                  </select>
                </div>
              </div>

              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                  <label class="form-label">Prioritas Tugas</label>
                  <select name="priority" class="form-control">
                    <option value="medium" selected>Medium</option>
                    <option value="high">High (Tinggi)</option>
                    <option value="urgent">Urgent (Segera)</option>
                    <option value="low">Low (Santai)</option>
                  </select>
                </div>

                <div class="form-group">
                  <label class="form-label">Tenggat Waktu (Deadline) <span style="color:red;">*</span></label>
                  <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+3 days')) ?>" required>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Deskripsi Brief / Detail Pengerjaan</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Tuliskan spesifikasi teknis, dimensi, referensi hook, atau instruksi khusus untuk PIC..."></textarea>
              </div>

              <div class="form-group">
                <label class="form-label">Link Dokumen / Referensi Awal (Opsional)</label>
                <input type="url" name="deliverable_link" class="form-control" placeholder="https://drive.google.com/... atau https://figma.com/...">
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" onclick="closeModal('modal-assign-task')">Batal</button>
              <button type="submit" class="btn btn-primary" id="btn-submit-assign">
                Bagikan Tugas & Notifikasi Tim
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- MODAL: Update Progress (Worker / Admin) -->
  <div class="modal" id="modal-update-progress">
    <div class="modal-dialog" style="max-width: 540px;">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h4 class="modal-title">Perbarui Status Progres & Hasil Kerja</h4>
            <span style="font-size: 12px; color: var(--text-secondary);" id="progress-modal-subtitle">Update deliverable link</span>
          </div>
          <button type="button" class="close-btn" onclick="closeModal('modal-update-progress')">&times;</button>
        </div>

        <form id="form-update-progress" onsubmit="handleProgressSubmit(event)">
          <input type="hidden" name="task_id" id="progress-task-id">

          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Status Alur Pengerjaan <span style="color:red;">*</span></label>
              <select name="status" id="progress-status-select" class="form-control" required>
                <option value="in_progress">Sedang Dikerjakan (In Progress)</option>
                <option value="internal_review">Review Internal Tim (Internal Review)</option>
                <option value="client_review">Menunggu Review Klien (Client Review)</option>
                <option value="revision">Dalam Perbaikan (Revision)</option>
                <option value="approved">Disetujui (Approved)</option>
                <option value="backlog">Antrean (Backlog)</option>
              </select>
              <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 4px;">
                Pilih "Menunggu Review Klien" jika pekerjaan sudah siap ditinjau oleh pihak klien.
              </small>
            </div>

            <div class="form-group">
              <label class="form-label">Tautan Hasil Kerja / Deliverable Preview</label>
              <input type="url" name="deliverable_link" id="progress-link-input" class="form-control" placeholder="https://drive.google.com/... atau https://figma.com/... atau Loom">
            </div>

            <div class="form-group">
              <label class="form-label">Catatan Tambahan (Opsional)</label>
              <textarea name="notes" class="form-control" rows="2" placeholder="Tuliskan catatan progres atau perubahan yang dilakukan..."></textarea>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-update-progress')">Batal</button>
            <button type="submit" class="btn btn-primary" id="btn-submit-progress">
              Simpan Progres
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL: Discussion & Feedback Thread (Worker / Admin View) -->
  <div class="modal" id="modal-task-discussion">
    <div class="modal-dialog" style="max-width: 620px;">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h4 class="modal-title" id="disc-task-title">Kotak Diskusi Masukan Klien</h4>
            <span style="font-size: 12px; color: var(--text-secondary);" id="disc-task-meta">Memuat rincian...</span>
          </div>
          <button type="button" class="close-btn" onclick="closeModal('modal-task-discussion')">&times;</button>
        </div>

        <div class="modal-body">
          <div id="disc-preview-box" style="background: #F8FAFC; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 12px; color: var(--text-secondary); font-weight: 600;">Link Preview Hasil Kerja:</span>
            <a id="disc-preview-btn" href="#" target="_blank" class="btn btn-secondary btn-sm" style="font-size: 11.5px; font-weight: 700;">
              Buka Preview Link
            </a>
          </div>

          <!-- Discussion History -->
          <div class="discussion-thread" id="disc-thread-container">
            <div style="text-align: center; color: var(--text-muted); font-size: 12px; padding: 16px;">
              Memuat diskusi...
            </div>
          </div>

          <!-- Reply Form -->
          <form id="form-reply-feedback" onsubmit="handleReplyFeedback(event)">
            <input type="hidden" name="task_id" id="reply-task-id">
            <input type="hidden" name="feedback_type" value="comment">

            <div class="form-group" style="margin-bottom: 10px;">
              <label class="form-label" style="font-size: 12px; font-weight: 700;">Kirim Tanggapan / Jawaban Masukan</label>
              <textarea name="message" id="reply-message" class="form-control" rows="2" placeholder="Tulis tanggapan atau konfirmasi perbaikan untuk klien..." required></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
              <label class="form-label" style="font-size: 11px; color: var(--text-secondary);">Unggah Hasil Revisi / Screenshot (Opsional)</label>
              <input type="file" name="attachment" id="reply-attachment" class="form-control">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
              <button type="button" class="btn btn-secondary" onclick="closeModal('modal-task-discussion')">Tutup</button>
              <button type="submit" class="btn btn-primary" id="btn-submit-reply">
                Kirim Balasan
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL: Update Anggaran Terpasang & Bukti Ads (Tim Ads / Workers / Admin) -->
  <div class="modal" id="modal-update-ads-spend">
    <div class="modal-dialog" style="max-width: 580px;">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h4 class="modal-title">Update Anggaran Terpasang & Bukti Dashboard</h4>
            <span style="font-size: 12px; color: var(--text-secondary);">Laporkan nominal belanja iklan yang sudah dipasang di platform</span>
          </div>
          <button type="button" class="close-btn" onclick="closeModal('modal-update-ads-spend')">&times;</button>
        </div>

        <form id="form-update-ads-spend" onsubmit="handleLogAdsSpend(event)" enctype="multipart/form-data">
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Proyek & PT Klien <span style="color:red;">*</span></label>
              <select name="project_id" id="ads-spend-project-id" class="form-control" required onchange="onAdsProjectSelectChange(this)">
                <option value="">-- Pilih Proyek Klien --</option>
              </select>
            </div>

            <!-- Info Pagu Saldo Ditugaskan Owner -->
            <div id="box-allocated-info" style="display: none; background: #F4F3FF; border: 1px solid #D9D6FE; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px;">
              <div style="font-size: 11.5px; color: #5925DC; font-weight: 700; text-transform: uppercase;">Pagu Saldo Ads Ditugaskan Owner</div>
              <div style="font-size: 18px; font-weight: 800; color: #101828;" id="txt-allocated-budget">Rp 0</div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
              <div class="form-group">
                <label class="form-label">Platform Iklan <span style="color:red;">*</span></label>
                <select name="platform" id="ads-spend-platform" class="form-control" required>
                  <option value="Meta Ads">Meta Ads (Instagram & FB)</option>
                  <option value="Google Ads">Google Ads / YouTube</option>
                  <option value="TikTok Ads">TikTok Ads</option>
                  <option value="Multi-Platform">Multi-Platform</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Status Kampanye <span style="color:red;">*</span></label>
                <select name="status" id="ads-spend-status" class="form-control" required>
                  <option value="sedang_berjalan">Sedang Berjalan</option>
                  <option value="menunggu_saldo">Menunggu Saldo</option>
                  <option value="saldo_hampir_habis">Saldo Hampir Habis</option>
                  <option value="selesai">Selesai</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Anggaran yang Sudah Terpasang / Terpakai (Rp) <span style="color:red;">*</span></label>
              <input type="text" name="spend_installed" id="ads-spend-amount" class="form-control" placeholder="Contoh: 5.000.000" required oninput="formatRupiahInput(this)">
              <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                Nominal akumulatif atau spend saat ini yang terserap di dashboard platform ads.
              </small>
            </div>

            <div class="form-group">
              <label class="form-label">Catatan Koordinasi untuk Owner</label>
              <textarea name="notes" id="ads-spend-notes" class="form-control" rows="2" placeholder="Contoh: Saldo Rp 5.000.000 sudah masuk ke Meta Ads Manager. Campaign Advantage+ Shopping aktif, CTR 2.3%"></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">Bukti Screenshot Dashboard Ads (Opsional)</label>
              <input type="file" name="attachment_proof" id="ads-spend-attachment" class="form-control" accept="image/*,.pdf">
              <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                Unggah tangkapan layar (screenshot) billing / dashboard platform iklan sebagai bukti fisik.
              </small>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-update-ads-spend')">Batal</button>
            <button type="submit" class="btn btn-primary" id="btn-submit-ads-spend">
              Simpan & Koordinasikan ke Owner
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL: Upload Bukti Transaksi Isi Saldo Ads (Tim Ads / Workers / Admin / Owner) -->
  <div class="modal" id="modal-upload-ads-topup">
    <div class="modal-dialog" style="max-width: 620px;">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h4 class="modal-title">+ Upload Bukti Transaksi Isi Saldo Ads</h4>
            <span style="font-size: 12px; color: var(--text-secondary);">Upload bukti pengisian saldo iklan klien. Otomatis tampil di Portal Klien.</span>
          </div>
          <button type="button" class="close-btn" onclick="closeModal('modal-upload-ads-topup')">&times;</button>
        </div>

        <form id="form-upload-ads-topup" onsubmit="handleUploadAdsTopup(event)" enctype="multipart/form-data">
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Pilih Klien / Proyek PT <span style="color:red;">*</span></label>
              <select name="project_id" id="ads-topup-project-id" class="form-control" required onchange="onTopupProjectChange(this)">
                <option value="">-- Pilih Proyek Klien --</option>
              </select>
            </div>

            <!-- Pagu Saldo Info Card -->
            <div id="topup-allocated-info" style="display: none; background: #F4F3FF; border: 1px solid #D9D6FE; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px;">
              <div style="font-size: 11px; color: #5925DC; font-weight: 700; text-transform: uppercase;">Pagu Saldo Ads Ditugaskan Owner</div>
              <div style="font-size: 17px; font-weight: 800; color: #101828;" id="txt-topup-allocated-budget">Rp 0</div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
              <div class="form-group">
                <label class="form-label">Platform Iklan <span style="color:red;">*</span></label>
                <select name="platform" id="ads-topup-platform" class="form-control" required>
                  <option value="Meta Ads">Meta Ads (Instagram & FB)</option>
                  <option value="Google Ads">Google Ads / YouTube</option>
                  <option value="TikTok Ads">TikTok Ads</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Tanggal Transaksi <span style="color:red;">*</span></label>
                <input type="date" name="transaction_date" id="ads-topup-date" class="form-control" value="<?= date('Y-m-d') ?>" required>
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
              <div class="form-group">
                <label class="form-label">Nominal Isi Saldo / Top-Up (Rp) <span style="color:red;">*</span></label>
                <input type="text" name="topup_amount" id="ads-topup-amount" class="form-control" placeholder="Contoh: 2.000.000" required oninput="formatRupiahInput(this)">
                <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 2px;">
                  Jumlah saldo iklan yang baru saja dibayarkan.
                </small>
              </div>

              <div class="form-group">
                <label class="form-label">Nominal Anggaran Terpasang (Rp) <span style="color:red;">*</span></label>
                <input type="text" name="spend_installed" id="ads-topup-spend" class="form-control" placeholder="Contoh: 5.000.000" required oninput="formatRupiahInput(this)">
                <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 2px;">
                  Akumulasi anggaran iklan yang sedang jalan saat ini.
                </small>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Upload Bukti Transaksi / Struk Top-Up (Wajib) <span style="color:red;">*</span></label>
              <input type="file" name="receipt_file" id="ads-topup-file" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf" required>
              <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 2px;">
                Format yang didukung: JPG, PNG, WEBP, PDF (Maks. 10MB). File otomatis tersimpan di <code>assets/uploads/receipts/</code>.
              </small>
            </div>

            <div class="form-group">
              <label class="form-label">Catatan untuk Klien & Owner</label>
              <textarea name="notes" id="ads-topup-notes" class="form-control" rows="2" placeholder="Contoh: Top-up saldo Meta Ads kampanye Oktober sudah masuk"></textarea>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-upload-ads-topup')">Batal</button>
            <button type="submit" class="btn btn-primary" id="btn-submit-ads-topup" style="background: #027A48; border-color: #027A48;">
              Simpan & Sinkronkan ke Portal Klien
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL: Atur Pagu Saldo Ads ke Proyek (Owner / Admin Only) -->
  <?php if ($isAdminUser): ?>
    <div class="modal" id="modal-allocate-ads-budget">
      <div class="modal-dialog" style="max-width: 520px;">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <h4 class="modal-title">Atur Pagu Saldo Ads ke Proyek</h4>
              <span style="font-size: 12px; color: var(--text-secondary);">Tentukan anggaran murni yang dialokasikan khusus untuk belanja platform iklan</span>
            </div>
            <button type="button" class="close-btn" onclick="closeModal('modal-allocate-ads-budget')">&times;</button>
          </div>

          <form id="form-allocate-ads-budget" onsubmit="handleAllocateAdsBudget(event)">
            <div class="modal-body">
              <div class="form-group">
                <label class="form-label">Pilih Proyek Klien <span style="color:red;">*</span></label>
                <select name="project_id" id="ads-alloc-project-id" class="form-control" required>
                  <option value="">-- Pilih Proyek Klien --</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Pagu Saldo Iklan yang Dialokasikan (Rp) <span style="color:red;">*</span></label>
                <input type="text" name="allocated_budget" id="ads-alloc-amount" class="form-control" placeholder="Contoh: 10.000.000" required oninput="formatRupiahInput(this)">
                <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                  Pagu ini yang akan menjadi batas penyerapan saldo bagi Tim Ads di dashboard iklan.
                </small>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" onclick="closeModal('modal-allocate-ads-budget')">Batal</button>
              <button type="submit" class="btn btn-primary" id="btn-submit-ads-alloc">
                Simpan Alokasi Saldo
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- MODAL: Lightbox Preview Bukti Screenshot Dashboard Ads -->
  <div class="modal" id="modal-preview-ads-proof">
    <div class="modal-dialog" style="max-width: 800px;">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h4 class="modal-title">Bukti Screenshot Dashboard Ads</h4>
            <span style="font-size: 12px; color: var(--text-secondary);">Tangkapan layar otentik platform periklanan</span>
          </div>
          <button type="button" class="close-btn" onclick="closeModal('modal-preview-ads-proof')">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center; padding: 16px;">
          <img id="img-proof-preview" src="" alt="Bukti Dashboard Ads" style="max-width: 100%; max-height: 520px; object-fit: contain; border-radius: 8px; border: 1px solid var(--border-color); display: none;">
          <iframe id="iframe-proof-preview" src="" style="width: 100%; height: 500px; border: none; display: none;"></iframe>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/js/app.js?v=bankio-typography-3.0"></script>
  <script>
    let allLoadedTasks = [];
    let currentFilterStatus = 'all';

    document.addEventListener('DOMContentLoaded', () => {
      loadTasksData();
      <?php if ($isAdminUser): ?>
        loadDropdowns();
      <?php endif; ?>
    });

    async function loadTasksData() {
      try {
        const res = await fetch('api/portal_tasks.php?action=list_tasks');
        const data = await res.json();

        if (data.success) {
          allLoadedTasks = data.tasks || [];
          renderKPIs(data.metrics);
          applyFilters();
          checkRevisionAlerts();
        } else {
          showToast(data.message || 'Gagal memuat daftar tugas', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      }
    }

    function renderKPIs(m) {
      if (!m) return;
      document.getElementById('kpi-total').innerText = m.total_tasks || 0;
      document.getElementById('kpi-revision').innerText = m.revision_count || 0;
      document.getElementById('kpi-review').innerText = m.client_review_count || 0;
      document.getElementById('kpi-progress').innerText = (m.in_progress_count || 0) + (m.internal_review_count || 0);
      document.getElementById('kpi-approved').innerText = m.approved_count || 0;
    }

    function checkRevisionAlerts() {
      const alertContainer = document.getElementById('revision-alert-container');
      const revisions = allLoadedTasks.filter(t => t.status === 'revision');
      const unreadCount = allLoadedTasks.reduce((acc, t) => acc + (parseInt(t.unread_feedbacks_count) || 0), 0);

      if (revisions.length > 0 || unreadCount > 0) {
        alertContainer.style.display = 'block';
        alertContainer.innerHTML = `
          <div class="alert-revision-box">
            <div style="display: flex; align-items: center; gap: 10px;">
              <div>
                <strong style="color: #B42318; font-size: 13.5px;">Tindakan Diperlukan: Masukan dari Klien</strong>
                <div style="font-size: 12.5px; color: #7A271A; margin-top: 2px;">
                  Terdapat <b>${revisions.length} tugas</b> yang berstatus REVISI dan <b>${unreadCount} pesan masukan baru</b> dari klien.
                </div>
              </div>
            </div>
            <button class="btn btn-sm" onclick="filterByStatus('revision', document.querySelector('#status-filter-group button:nth-child(2)'))" style="background: #B42318; color: #FFF; font-weight: 700; flex-shrink: 0; font-size: 11.5px;">
              Tinjau Revisi Sekarang
            </button>
          </div>
        `;
      } else {
        alertContainer.style.display = 'none';
      }
    }

    function filterByStatus(status, btn) {
      currentFilterStatus = status;
      document.querySelectorAll('#status-filter-group .filter-btn').forEach(b => b.classList.remove('active'));
      if (btn) btn.classList.add('active');
      applyFilters();
    }

    function applyFilters() {
      const workerType = document.getElementById('filter-worker-type') ? document.getElementById('filter-worker-type').value : '';
      const category = document.getElementById('filter-category') ? document.getElementById('filter-category').value : '';

      let filtered = allLoadedTasks;

      // Status filter
      if (currentFilterStatus !== 'all') {
        filtered = filtered.filter(t => t.status === currentFilterStatus);
      }
      // Worker type filter
      if (workerType) {
        filtered = filtered.filter(t => t.worker_type === workerType);
      }
      // Category filter
      if (category) {
        filtered = filtered.filter(t => t.category === category);
      }

      document.getElementById('tasks-count-label').innerText = `Menampilkan ${filtered.length} tugas`;
      renderTasksList(filtered);
    }

    function renderTasksList(tasks) {
      const container = document.getElementById('tasks-list-container');
      if (!tasks || tasks.length === 0) {
        container.innerHTML = `
          <div style="text-align: center; padding: 48px; background: #FFF; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
            <div style="font-size: 32px; margin-bottom: 6px;">📋</div>
            <h4 style="font-size: 15px; font-weight: 800; color: #101828;">Tidak Ada Tugas Sesuai Filter</h4>
            <p style="font-size: 12.5px; color: var(--text-muted); margin: 0;">Silakan ubah filter atau tambahkan tugas baru.</p>
          </div>
        `;
        return;
      }

      const statusMap = {
        'backlog': { label: 'Antrean', class: 'backlog' },
        'in_progress': { label: 'Sedang Dikerjakan', class: 'in_progress' },
        'internal_review': { label: 'Review Internal', class: 'internal_review' },
        'client_review': { label: 'Review Klien', class: 'client_review' },
        'revision': { label: 'Perlu Revisi', class: 'revision' },
        'approved': { label: 'Disetujui', class: 'approved' }
      };

      const categoryMap = {
        'design': 'Desain Grafis',
        'video': 'Produksi Video',
        'copywriting': 'Copywriting',
        'ads': 'Meta / Ads Campaign',
        'web': 'Web Development',
        'other': 'Deliverable'
      };

      let html = '';
      tasks.forEach(t => {
        const sInfo = statusMap[t.status] || { label: t.status, class: 'backlog' };
        const isFreelance = (t.worker_type === 'freelancer');
        const workerBadge = isFreelance 
          ? `<span class="badge-worker freelancer">Freelancer: <b>${escapeHtml(t.worker_name || 'Talent')}</b></span>`
          : `<span class="badge-worker karyawan">In-House: <b>${escapeHtml(t.worker_name || 'Staff')}</b></span>`;

        const hasRevision = (t.status === 'revision');
        const hasReview = (t.status === 'client_review');
        const isApproved = (t.status === 'approved');

        const cardExtraClass = hasRevision ? 'has-revision' : (hasReview ? 'has-review' : (isApproved ? 'is-approved' : ''));
        const unreadFeedbacks = parseInt(t.unread_feedbacks_count) || 0;

        html += `
          <div class="task-item-card ${cardExtraClass}">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap;">
              <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <span class="status-pill ${sInfo.class}">
                  <span>${sInfo.label}</span>
                </span>
                <span class="badge" style="background: #F2F4F7; color: var(--text-secondary); font-size: 10.5px; font-weight: 700;">
                  ${categoryMap[t.category] || t.category}
                </span>
                <span class="badge-priority ${t.priority || 'medium'}">
                  ${t.priority ? t.priority.toUpperCase() : 'MEDIUM'}
                </span>
                ${workerBadge}
              </div>

              <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 11.5px; color: var(--text-muted);">
                  <span>Deadline: <b>${t.due_date ? formatDate(t.due_date) : '-'}</b></span>
                </span>
                <?php if ($isAdminUser): ?>
                  <button type="button" class="btn btn-secondary btn-sm" onclick="deleteTask(${t.id})" style="padding: 3px 8px; font-size: 11px; font-weight: 600; color: #B42318;" title="Hapus Tugas">
                    Hapus
                  </button>
                <?php endif; ?>
              </div>
            </div>

            <div>
              <div style="font-size: 11.5px; color: var(--text-muted); font-weight: 600; margin-bottom: 2px;">
                ${escapeHtml(t.client_company || 'Client')} &bull; Proyek: ${escapeHtml(t.project_name || 'Kalamedia')}
              </div>
              <h3 style="font-size: 16px; font-weight: 800; color: #101828; margin: 0 0 4px;">
                ${escapeHtml(t.title)}
              </h3>
              <p style="font-size: 13px; color: var(--text-secondary); margin: 0; line-height: 1.5;">
                ${escapeHtml(t.description || 'Tidak ada deskripsi singkat.')}
              </p>
            </div>

            <!-- Deliverable Preview Link & Actions -->
            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 12px; flex-wrap: wrap; gap: 10px;">
              <div style="display: flex; align-items: center; gap: 10px;">
                ${t.deliverable_link ? `
                  <a href="${escapeHtml(t.deliverable_link)}" target="_blank" class="btn btn-secondary btn-sm" style="font-size: 11.5px; font-weight: 700;">
                    <span>Lihat Preview Hasil</span>
                  </a>
                ` : `
                  <span style="font-size: 11.5px; color: var(--text-tertiary); font-style: italic;">Belum ada link hasil</span>
                `}

                ${t.latest_feedback ? `
                  <span style="font-size: 11.5px; color: var(--text-secondary); max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: inline-block;">
                    "${escapeHtml(t.latest_feedback)}"
                  </span>
                ` : ''}
              </div>

              <div style="display: flex; gap: 8px; align-items: center;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="openDiscussionModal(${t.id})" style="font-size: 12px; font-weight: 700; position: relative;">
                  <span>Diskusi Klien</span>
                  ${unreadFeedbacks > 0 ? `
                    <span style="background: #EF4444; color: #FFF; font-size: 9.5px; font-weight: 800; padding: 1px 6px; border-radius: 10px; margin-left: 4px;">
                      ${unreadFeedbacks} Baru
                    </span>
                  ` : `
                    <span style="color: var(--text-muted); font-size: 11px; margin-left: 4px;">(${t.feedbacks_count || 0})</span>
                  `}
                </button>

                <button type="button" class="btn btn-primary btn-sm" onclick="openUpdateProgressModal(${t.id}, '${t.status}', '${escapeHtml(t.deliverable_link || '')}')" style="font-size: 12px;">
                  Perbarui Progres
                </button>
              </div>
            </div>
          </div>
        `;
      });

      container.innerHTML = html;
    }

    <?php if ($isAdminUser): ?>
      async function loadDropdowns() {
        try {
          // 1. Projects Dropdown
          const resP = await fetch('api/portal_tasks.php?action=projects_dropdown');
          const dataP = await resP.json();
          if (dataP.success) {
            const selectP = document.getElementById('assign-project-id');
            let pHtml = '<option value="">-- Pilih Proyek Klien --</option>';
            dataP.projects.forEach(p => {
              pHtml += `<option value="${p.project_id}">${escapeHtml(p.client_company)} - ${escapeHtml(p.project_name)}</option>`;
            });
            selectP.innerHTML = pHtml;
          }

          // 2. Members Dropdown
          const resM = await fetch('api/portal_tasks.php?action=team_members');
          const dataM = await resM.json();
          if (dataM.success) {
            const selectM = document.getElementById('assign-member-id');
            let mHtml = '<option value="">-- Pilih Anggota Tim / Freelancer --</option>';
            dataM.members.forEach(m => {
              const label = (m.role === 'freelancer' || m.employment_type === 'freelance') ? 'Freelancer' : 'Karyawan In-House';
              mHtml += `<option value="${m.id}">[${label}] ${escapeHtml(m.name)}</option>`;
            });
            selectM.innerHTML = mHtml;
          }
        } catch (err) {
          console.error(err);
        }
      }

      function openAssignTaskModal() {
        openModal('modal-assign-task');
      }

      async function handleAssignTask(e) {
        e.preventDefault();
        const form = document.getElementById('form-assign-task');
        const submitBtn = document.getElementById('btn-submit-assign');
        const origText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Menyimpan...';

        const formData = new FormData(form);
        try {
          const res = await fetch('api/portal_tasks.php?action=assign_task', {
            method: 'POST',
            body: formData
          });
          const data = await res.json();
          if (data.success) {
            showToast(data.message, 'success');
            closeModal('modal-assign-task');
            form.reset();
            loadTasksData();
          } else {
            showToast(data.message || 'Gagal membagi tugas', 'danger');
          }
        } catch (err) {
          showToast('Koneksi server gagal', 'danger');
        } finally {
          submitBtn.disabled = false;
          submitBtn.innerHTML = origText;
        }
      }

      async function deleteTask(taskId) {
        if (!confirm('Apakah Anda yakin ingin menghapus tugas ini?')) return;
        const formData = new FormData();
        formData.append('task_id', taskId);
        try {
          const res = await fetch('api/portal_tasks.php?action=delete_task', {
            method: 'POST',
            body: formData
          });
          const data = await res.json();
          if (data.success) {
            showToast(data.message, 'success');
            loadTasksData();
          } else {
            showToast(data.message || 'Gagal menghapus', 'danger');
          }
        } catch (err) {
          showToast('Koneksi server gagal', 'danger');
        }
      }

      async function deleteAllTasks() {
        if (!confirm('Apakah Anda yakin ingin menghapus SEMUA tugas yang ada di sistem? Tindakan ini tidak dapat dibatalkan.')) return;
        try {
          const res = await fetch('api/portal_tasks.php?action=delete_all_tasks', {
            method: 'POST'
          });
          const data = await res.json();
          if (data.success) {
            showToast(data.message, 'success');
            loadTasksData();
          } else {
            showToast(data.message || 'Gagal menghapus semua tugas', 'danger');
          }
        } catch (err) {
          showToast('Koneksi server gagal', 'danger');
        }
      }
    <?php endif; ?>

    function openUpdateProgressModal(taskId, currentStatus, currentLink) {
      document.getElementById('progress-task-id').value = taskId;
      document.getElementById('progress-status-select').value = currentStatus || 'in_progress';
      document.getElementById('progress-link-input').value = currentLink || '';
      openModal('modal-update-progress');
    }

    async function handleProgressSubmit(e) {
      e.preventDefault();
      const form = document.getElementById('form-update-progress');
      const submitBtn = document.getElementById('btn-submit-progress');
      const origText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Menyimpan...';

      const formData = new FormData(form);
      try {
        const res = await fetch('api/portal_tasks.php?action=update_progress', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          closeModal('modal-update-progress');
          loadTasksData();
        } else {
          showToast(data.message || 'Gagal menyimpan progres', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origText;
      }
    }

    async function openDiscussionModal(taskId) {
      try {
        const res = await fetch(`api/portal_tasks.php?action=get_task_details&id=${taskId}`);
        const data = await res.json();

        if (data.success) {
          const t = data.task;
          document.getElementById('reply-task-id').value = t.id;
          document.getElementById('disc-task-title').innerText = t.title;
          document.getElementById('disc-task-meta').innerText = 
            `Klien: ${t.client_company} • PIC: ${t.worker_name || 'Staff'} • Status: [${t.status.toUpperCase()}]`;

          const previewBox = document.getElementById('disc-preview-box');
          const previewBtn = document.getElementById('disc-preview-btn');
          if (t.deliverable_link) {
            previewBox.style.display = 'flex';
            previewBtn.href = t.deliverable_link;
          } else {
            previewBox.style.display = 'none';
          }

          // Render Discussion Thread
          const threadContainer = document.getElementById('disc-thread-container');
          const feedbacks = data.feedbacks || [];

          if (feedbacks.length === 0) {
            threadContainer.innerHTML = `
              <div style="text-align: center; color: var(--text-muted); font-size: 12px; padding: 18px;">
                Belum ada pesan masukan atau diskusi pada tugas ini.
              </div>
            `;
          } else {
            let threadHtml = '';
            feedbacks.forEach(f => {
              const isClient = (f.sender_role === 'client');
              const bubbleClass = isClient ? 'client-bubble' : 'team-bubble';
              const typeBadge = (f.feedback_type === 'revision_request') 
                ? '<span style="background: #FEE4E2; color: #B42318; padding: 1px 6px; border-radius: 4px; font-weight: 700; font-size: 10px;">Permintaan Revisi</span>'
                : ((f.feedback_type === 'approval') 
                  ? '<span style="background: #D1FADF; color: #027A48; padding: 1px 6px; border-radius: 4px; font-weight: 700; font-size: 10px;">Persetujuan</span>'
                  : '');

              threadHtml += `
                <div class="bubble ${bubbleClass}">
                  <div class="bubble-meta">
                    <b>${escapeHtml(f.sender_name || 'Pengguna')}</b> (${isClient ? 'Klien' : 'Tim Agensi'})
                    <span>&bull;</span>
                    <span>${formatTime(f.created_at)}</span>
                    ${typeBadge}
                  </div>
                  <div>${escapeHtml(f.message)}</div>
                  ${f.attachment_path ? `
                    <div style="margin-top: 6px;">
                      <a href="${escapeHtml(f.attachment_path)}" target="_blank" style="color: ${isClient ? '#93C5FD' : '#175CD3'}; font-size: 11.5px; text-decoration: underline;">
                        Buka Lampiran File
                      </a>
                    </div>
                  ` : ''}
                </div>
              `;
            });
            threadContainer.innerHTML = threadHtml;
            threadContainer.scrollTop = threadContainer.scrollHeight;
          }

          document.getElementById('reply-message').value = '';
          openModal('modal-task-discussion');
        } else {
          showToast(data.message || 'Gagal memuat rincian diskusi', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      }
    }

    async function handleReplyFeedback(e) {
      e.preventDefault();
      const form = document.getElementById('form-reply-feedback');
      const submitBtn = document.getElementById('btn-submit-reply');
      const origText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Mengirim...';

      const formData = new FormData(form);
      try {
        const res = await fetch('api/portal_tasks.php?action=submit_feedback', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast('Balasan Anda berhasil dikirim ke klien!', 'success');
          closeModal('modal-task-discussion');
          loadTasksData();
        } else {
          showToast(data.message || 'Gagal mengirim balasan', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origText;
      }
    }

    function formatDate(str) {
      if (!str) return '-';
      const d = new Date(str);
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
      return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
    }

    function formatTime(str) {
      if (!str) return '';
      const d = new Date(str);
      return `${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}`;
    }

    function escapeHtml(text) {
      if (!text) return '';
      const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      };
      return text.replace(/[&<>"']/g, m => map[m]);
    }

    // -----------------------------------------------------------
    // ADS BUDGET & SPEND TRACKER MODULE JS
    // -----------------------------------------------------------
    let allLoadedAdsData = null;

    function switchTaskWorkspaceTab(tab) {
      const btnTasks = document.getElementById('tab-btn-tasks');
      const btnAds = document.getElementById('tab-btn-ads');
      const secTasks = document.getElementById('section-tasks-content');
      const secAds = document.getElementById('section-ads-content');

      if (tab === 'ads') {
        btnAds.classList.add('active');
        btnTasks.classList.remove('active');
        secTasks.style.display = 'none';
        secAds.style.display = 'block';
        loadAdsTrackerData();
      } else {
        btnTasks.classList.add('active');
        btnAds.classList.remove('active');
        secAds.style.display = 'none';
        secTasks.style.display = 'block';
      }
    }

    async function loadAdsTrackerData() {
      try {
        const res = await fetch('api/ads_tracker.php?action=list');
        const data = await res.json();
        if (!data.success) {
          showToast(data.message || 'Gagal memuat data iklan', 'danger');
          return;
        }
        allLoadedAdsData = data;
        renderAdsSummary(data);
        renderAdsTopupTable(data);
        renderAdsProjectsTable(data);
        renderAdsLogsFeed(data);
        populateAdsProjectDropdowns(data.projects || []);
      } catch (err) {
        console.error(err);
        showToast('Koneksi server gagal memuat data ads', 'danger');
      }
    }

    function renderAdsSummary(data) {
      const container = document.getElementById('ads-kpi-container');
      const isPrivileged = (data.role === 'owner' || data.role === 'admin');

      if (isPrivileged && data.summary) {
        const s = data.summary;
        container.innerHTML = `
          <div class="kpi-tile">
            <span style="font-size: 11.5px; color: var(--text-secondary); font-weight: 700; text-transform: uppercase;">Total Kontrak Klien (Rahasia)</span>
            <div style="font-size: 20px; font-weight: 800; color: #101828;">Rp ${Number(s.total_contract_value || 0).toLocaleString('id-ID')}</div>
            <span style="font-size: 11px; color: var(--text-muted);">Hanya tampak di internal Owner</span>
          </div>

          <div class="kpi-tile">
            <span style="font-size: 11.5px; color: #5925DC; font-weight: 700; text-transform: uppercase;">Pagu Saldo Ads Dialokasikan</span>
            <div style="font-size: 20px; font-weight: 800; color: #5925DC;">Rp ${Number(s.total_allocated_ads || 0).toLocaleString('id-ID')}</div>
            <span style="font-size: 11px; color: var(--text-muted);">Pagu belanja platform ads</span>
          </div>

          <div class="kpi-tile">
            <span style="font-size: 11.5px; color: #175CD3; font-weight: 700; text-transform: uppercase;">Anggaran Terpasang (Spend)</span>
            <div style="font-size: 20px; font-weight: 800; color: #175CD3;">Rp ${Number(s.total_spend_installed || 0).toLocaleString('id-ID')}</div>
            <span style="font-size: 11px; color: var(--text-muted);">Laporan realisasi Tim Ads</span>
          </div>

          <div class="kpi-tile ${s.total_remaining_ads <= 0 ? 'urgent' : ''}">
            <span style="font-size: 11.5px; color: ${s.total_remaining_ads <= 0 ? '#B42318' : '#027A48'}; font-weight: 700; text-transform: uppercase;">Sisa Saldo Ads</span>
            <div style="font-size: 20px; font-weight: 800; color: ${s.total_remaining_ads <= 0 ? '#B42318' : '#027A48'};">Rp ${Number(s.total_remaining_ads || 0).toLocaleString('id-ID')}</div>
            <span style="font-size: 11px; color: var(--text-muted);">Saldo pagu yang tersisa</span>
          </div>

          <div class="kpi-tile">
            <span style="font-size: 11.5px; color: #027A48; font-weight: 700; text-transform: uppercase;">Margin Bersih Agensi</span>
            <div style="font-size: 20px; font-weight: 800; color: #027A48;">Rp ${Number(s.total_agency_retained || 0).toLocaleString('id-ID')}</div>
            <span style="font-size: 11px; color: var(--text-muted);">Kontrak - Pagu Saldo Ads</span>
          </div>
        `;
      } else {
        // STRICT WORKER VIEW: Calculate sums without any contract or agency margin values
        let totalAlloc = 0;
        let totalInstalled = 0;
        let totalRemaining = 0;
        const projects = data.projects || [];

        projects.forEach(p => {
          totalAlloc += Number(p.allocated_ads_budget || 0);
          totalInstalled += Number(p.total_spend_installed || 0);
          totalRemaining += Number(p.remaining_ads_budget || 0);
        });

        container.innerHTML = `
          <div class="kpi-tile">
            <span style="font-size: 11.5px; color: #5925DC; font-weight: 700; text-transform: uppercase;">Pagu Saldo Ads Ditugaskan Owner</span>
            <div style="font-size: 20px; font-weight: 800; color: #5925DC;">Rp ${totalAlloc.toLocaleString('id-ID')}</div>
            <span style="font-size: 11px; color: var(--text-muted);">Alokasi dana belanja iklan</span>
          </div>

          <div class="kpi-tile">
            <span style="font-size: 11.5px; color: #175CD3; font-weight: 700; text-transform: uppercase;">Anggaran Terpasang di Dashboard</span>
            <div style="font-size: 20px; font-weight: 800; color: #175CD3;">Rp ${totalInstalled.toLocaleString('id-ID')}</div>
            <span style="font-size: 11px; color: var(--text-muted);">Total spend yang Anda pasang</span>
          </div>

          <div class="kpi-tile ${totalRemaining <= 0 ? 'urgent' : ''}">
            <span style="font-size: 11.5px; color: ${totalRemaining <= 0 ? '#B42318' : '#027A48'}; font-weight: 700; text-transform: uppercase;">Sisa Saldo Iklan</span>
            <div style="font-size: 20px; font-weight: 800; color: ${totalRemaining <= 0 ? '#B42318' : '#027A48'};">Rp ${totalRemaining.toLocaleString('id-ID')}</div>
            <span style="font-size: 11px; color: var(--text-muted);">Batas penyerapan tersisa</span>
          </div>

          <div class="kpi-tile">
            <span style="font-size: 11.5px; color: var(--text-secondary); font-weight: 700; text-transform: uppercase;">Proyek Kampanye</span>
            <div style="font-size: 20px; font-weight: 800; color: #101828;">${projects.length} Proyek</div>
            <span style="font-size: 11px; color: var(--text-muted);">Klien aktif Kalamedia</span>
          </div>
        `;
      }
    }

    function renderAdsProjectsTable(data) {
      const tbody = document.getElementById('tbody-ads-projects');
      const isPrivileged = (data.role === 'owner' || data.role === 'admin');
      const projects = data.projects || [];

      if (projects.length === 0) {
        tbody.innerHTML = `<tr><td colspan="${isPrivileged ? 8 : 6}" style="text-align: center; padding: 32px; color: var(--text-muted);">Belum ada data proyek atau alokasi saldo iklan.</td></tr>`;
        return;
      }

      let html = '';
      projects.forEach(p => {
        const alloc = Number(p.allocated_ads_budget || 0);
        const installed = Number(p.total_spend_installed || 0);
        const remaining = Number(p.remaining_ads_budget || 0);
        const contract = Number(p.contract_value || 0);
        const margin = Number(p.agency_retained_balance || (contract - alloc));

        let statusBadge = '<span class="badge" style="background: #F2F4F7; color: #344054; font-weight: 700;">Belum Ada Log</span>';
        if (p.logs && p.logs.length > 0) {
          const latestLog = p.logs[0];
          const stMap = {
            'sedang_berjalan': { label: 'Sedang Berjalan', bg: '#ECFDF3', color: '#027A48' },
            'menunggu_saldo': { label: 'Menunggu Saldo', bg: '#EFF8FF', color: '#175CD3' },
            'saldo_hampir_habis': { label: 'Saldo Menipis', bg: '#FEF3F2', color: '#B42318' },
            'selesai': { label: 'Selesai', bg: '#F2F4F7', color: '#344054' }
          };
          const st = stMap[latestLog.status] || { label: latestLog.status, bg: '#F2F4F7', color: '#344054' };
          statusBadge = `<span class="badge" style="background: ${st.bg}; color: ${st.color}; font-weight: 700;">${st.label}</span>`;
        }

        html += `
          <tr>
            <td>
              <div style="font-weight: 800; color: #101828;">${escapeHtml(p.client_company)}</div>
              <span style="font-size: 11.5px; color: var(--text-secondary);">${escapeHtml(p.project_name)}</span>
            </td>
            ${isPrivileged ? `
              <td style="font-weight: 800; color: #101828;">
                Rp ${contract.toLocaleString('id-ID')}
              </td>
            ` : ''}
            <td style="font-weight: 800; color: #5925DC;">
              Rp ${alloc.toLocaleString('id-ID')}
            </td>
            <td style="font-weight: 800; color: #175CD3;">
              Rp ${installed.toLocaleString('id-ID')}
            </td>
            <td style="font-weight: 800; color: ${remaining <= 0 && alloc > 0 ? '#B42318' : '#027A48'};">
              Rp ${remaining.toLocaleString('id-ID')}
            </td>
            ${isPrivileged ? `
              <td style="font-weight: 800; color: #027A48;">
                Rp ${margin.toLocaleString('id-ID')}
              </td>
            ` : ''}
            <td>
              ${statusBadge}
            </td>
            <td style="text-align: right; white-space: nowrap;">
              <button type="button" class="btn btn-primary btn-sm" onclick="openLogAdsSpendModal(${p.project_id})" style="font-size: 11px; padding: 4px 10px;">
                + Input Spend
              </button>
              ${isPrivileged ? `
                <button type="button" class="btn btn-secondary btn-sm" onclick="openAllocateAdsModal(${p.project_id}, ${alloc})" style="font-size: 11px; padding: 4px 8px; margin-left: 4px;">
                  Atur Pagu
                </button>
              ` : ''}
            </td>
          </tr>
        `;
      });

      tbody.innerHTML = html;
    }

    function renderAdsLogsFeed(data) {
      const container = document.getElementById('ads-logs-feed-container');
      const isPrivileged = (data.role === 'owner' || data.role === 'admin');
      const isOwner = (data.role === 'owner');

      // Aggregate all logs from all projects
      let allLogs = [];
      (data.projects || []).forEach(p => {
        (p.logs || []).forEach(l => {
          allLogs.push({
            ...l,
            client_company: p.client_company,
            project_name: p.project_name
          });
        });
      });

      allLogs.sort((a, b) => b.id - a.id);

      if (allLogs.length === 0) {
        container.innerHTML = `
          <div style="text-align: center; padding: 36px; background: #F9FAFB; border-radius: var(--radius-md); border: 1px dashed var(--border-color); color: var(--text-muted);">
            <div style="font-size: 28px; margin-bottom: 6px;">📈</div>
            <h4 style="font-size: 14px; font-weight: 700; color: #101828; margin-bottom: 2px;">Belum Ada Riwayat Update Anggaran Terpasang</h4>
            <p style="font-size: 12.5px; margin: 0;">Tim Ads dapat menekan tombol <b>+ Update Anggaran Terpasang & Bukti</b> untuk mencatat nominal belanja platform iklan.</p>
          </div>
        `;
        return;
      }

      let html = '<div style="display: flex; flex-direction: column; gap: 12px;">';
      allLogs.forEach(l => {
        const stMap = {
          'sedang_berjalan': { label: 'Sedang Berjalan', bg: '#ECFDF3', color: '#027A48' },
          'menunggu_saldo': { label: 'Menunggu Saldo', bg: '#EFF8FF', color: '#175CD3' },
          'saldo_hampir_habis': { label: 'Saldo Menipis', bg: '#FEF3F2', color: '#B42318' },
          'selesai': { label: 'Selesai', bg: '#F2F4F7', color: '#344054' }
        };
        const st = stMap[l.status] || { label: l.status, bg: '#F2F4F7', color: '#344054' };
        const isVerified = (l.is_verified_by_owner == 1);

        html += `
          <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 16px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 14px; box-shadow: 0 1px 2px rgba(16,24,40,0.04);">
            <div style="flex: 1; min-width: 260px;">
              <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 6px;">
                <span style="font-weight: 800; font-size: 13.5px; color: #101828;">${escapeHtml(l.client_company)}</span>
                <span class="badge" style="background: #F4F3FF; color: #5925DC; font-weight: 800; font-size: 11px;">${escapeHtml(l.platform)}</span>
                <span class="badge" style="background: ${st.bg}; color: ${st.color}; font-weight: 700; font-size: 11px;">${st.label}</span>
                <span style="font-size: 11.5px; color: var(--text-muted);">&bull; ${formatDate(l.created_at || l.updated_at)}</span>
              </div>

              <div style="display: flex; gap: 18px; margin-bottom: 8px; flex-wrap: wrap;">
                <div>
                  <span style="font-size: 11px; color: var(--text-muted); display: block;">Anggaran Terpasang:</span>
                  <span style="font-size: 14px; font-weight: 800; color: #175CD3;">Rp ${Number(l.spend_installed || 0).toLocaleString('id-ID')}</span>
                </div>
                <div>
                  <span style="font-size: 11px; color: var(--text-muted); display: block;">Pagu Saldo:</span>
                  <span style="font-size: 14px; font-weight: 700; color: #5925DC;">Rp ${Number(l.allocated_budget || 0).toLocaleString('id-ID')}</span>
                </div>
                <div>
                  <span style="font-size: 11px; color: var(--text-muted); display: block;">Sisa Saldo:</span>
                  <span style="font-size: 14px; font-weight: 800; color: ${Number(l.remaining_budget || 0) <= 0 ? '#B42318' : '#027A48'};">Rp ${Number(l.remaining_budget || 0).toLocaleString('id-ID')}</span>
                </div>
              </div>

              <div style="font-size: 13px; color: #344054; background: #F9FAFB; border-radius: 6px; padding: 8px 12px; margin-bottom: 6px; border: 1px solid #EAECF0;">
                <b>Catatan Koordinasi:</b> ${escapeHtml(l.notes || 'Tidak ada catatan khusus.')}
              </div>

              <div style="font-size: 11.5px; color: var(--text-secondary);">
                PIC Penginput: <b>${escapeHtml(l.updated_by_name || 'Tim Ads')}</b>
              </div>
            </div>

            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
              ${isVerified ? `
                <span class="badge" style="background: #ECFDF3; color: #027A48; font-weight: 700; font-size: 11px; border: 1px solid #A6F4C5;">
                  ✓ Terverifikasi Owner
                </span>
              ` : `
                ${isOwner ? `
                  <button type="button" class="btn btn-secondary btn-sm" onclick="verifyAdsLog(${l.id})" style="font-size: 11px; color: #027A48; border-color: #A6F4C5; background: #ECFDF3; font-weight: 700;">
                    ✓ Verifikasi Laporan
                  </button>
                ` : `
                  <span class="badge" style="background: #FEF0C7; color: #B54708; font-weight: 700; font-size: 11px;">
                    Menunggu Verifikasi
                  </span>
                `}
              `}

              ${l.attachment_proof ? `
                <button type="button" class="btn btn-secondary btn-sm" onclick="previewAdsProof('${escapeHtml(l.attachment_proof)}')" style="font-size: 11px; font-weight: 600;">
                  Lihat Screenshot Dashboard
                </button>
              ` : ''}

              ${isPrivileged ? `
                <button type="button" class="btn btn-secondary btn-sm" onclick="deleteAdsLog(${l.id})" style="font-size: 10.5px; color: #B42318; border-color: #FECDCA; padding: 2px 8px;" title="Hapus Riwayat">
                  Hapus
                </button>
              ` : ''}
            </div>
          </div>
        `;
      });
      html += '</div>';

      container.innerHTML = html;
    }

    function renderAdsTopupTable(data) {
      const tbody = document.getElementById('tbody-ads-topups');
      if (!tbody) return;
      const isPrivileged = (data.role === 'owner' || data.role === 'admin');
      const isOwner = (data.role === 'owner');
      const topups = data.topup_transactions || [];

      if (topups.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="${isPrivileged ? 9 : 8}" style="text-align: center; padding: 36px 20px; color: var(--text-muted); background: #FCFCFD;">
              <div style="font-size: 26px; margin-bottom: 6px;">💳</div>
              <div style="font-weight: 700; color: #101828; margin-bottom: 2px;">Belum Ada Bukti Transaksi Isi Saldo Ads</div>
              <div style="font-size: 12px; margin-bottom: 12px;">Tim Ads dapat menekan tombol <b>+ Upload Bukti Isi Saldo / Transaksi Ads</b> saat selesai top-up iklan klien.</div>
              <button type="button" class="btn btn-secondary btn-sm" onclick="openUploadAdsTopupModal()" style="font-size: 11.5px; font-weight: 700; color: #027A48;">
                + Upload Bukti Sekarang
              </button>
            </td>
          </tr>
        `;
        return;
      }

      let html = '';
      topups.forEach(t => {
        const isVerified = (t.status === 'verified');
        const fileUrl = t.receipt_file ? escapeHtml(t.receipt_file) : '';

        html += `
          <tr>
            <td>
              <div style="font-weight: 800; color: #101828;">${formatDate(t.transaction_date)}</div>
              <small style="color: var(--text-secondary); font-size: 11px;">Oleh: <b>${escapeHtml(t.uploader_name || 'Tim Ads')}</b></small>
            </td>
            <td>
              <div style="font-weight: 800; color: #101828;">${escapeHtml(t.client_company || 'Klien PT')}</div>
              <small style="color: var(--text-muted); font-size: 11px;">${escapeHtml(t.project_name || 'Proyek')}</small>
            </td>
            <td>
              <span class="badge" style="background: #F4F3FF; color: #5925DC; font-weight: 800; font-size: 11px;">
                ${escapeHtml(t.platform)}
              </span>
            </td>
            <td>
              <span style="font-weight: 800; color: #027A48; font-size: 13.5px;">
                Rp ${Number(t.topup_amount || 0).toLocaleString('id-ID')}
              </span>
            </td>
            <td>
              <span style="font-weight: 700; color: #101828;">
                Rp ${Number(t.spend_installed || 0).toLocaleString('id-ID')}
              </span>
            </td>
            <td>
              <div style="max-width: 200px; font-size: 12px; color: #344054; line-height: 1.4;">
                ${escapeHtml(t.notes || '-')}
              </div>
            </td>
            <td>
              ${fileUrl ? `
                <button type="button" class="btn btn-secondary btn-sm" onclick="previewTransactionReceipt('${fileUrl}', 'Bukti Isi Saldo ${escapeHtml(t.platform)} - ${escapeHtml(t.client_company || '')}')" style="font-size: 11px; font-weight: 700; padding: 4px 10px; display: inline-flex; align-items: center; gap: 4px;">
                  <span>Lihat Bukti</span>
                </button>
              ` : `
                <span style="font-size: 11px; color: var(--text-muted); font-style: italic;">Tidak ada file</span>
              `}
            </td>
            <td>
              ${isVerified ? `
                <span class="badge" style="background: #ECFDF3; color: #027A48; font-weight: 700; font-size: 10.5px; border: 1px solid #A6F4C5;">
                  ✓ Terverifikasi
                </span>
              ` : `
                <span class="badge" style="background: #EFF8FF; color: #175CD3; font-weight: 700; font-size: 10.5px;">
                  Terekam
                </span>
              `}
            </td>
            ${isPrivileged ? `
              <td style="text-align: right; white-space: nowrap;">
                ${!isVerified && isOwner ? `
                  <button type="button" class="btn btn-secondary btn-sm" onclick="verifyTopupTransaction(${t.id})" style="font-size: 10.5px; color: #027A48; border-color: #A6F4C5; background: #ECFDF3; font-weight: 700; margin-right: 4px;">
                    ✓ Verifikasi
                  </button>
                ` : ''}
                <button type="button" class="btn btn-secondary btn-sm" onclick="deleteTopupTransaction(${t.id})" style="font-size: 10.5px; color: #B42318; border-color: #FECDCA; padding: 2px 8px;" title="Hapus Transaksi">
                  Hapus
                </button>
              </td>
            ` : ''}
          </tr>
        `;
      });

      tbody.innerHTML = html;
    }

    function populateAdsProjectDropdowns(projects) {
      const selectSpend = document.getElementById('ads-spend-project-id');
      const selectAlloc = document.getElementById('ads-alloc-project-id');
      const selectTopup = document.getElementById('ads-topup-project-id');

      let optionsHtml = '<option value="">-- Pilih Proyek Klien --</option>';
      projects.forEach(p => {
        optionsHtml += `<option value="${p.project_id}" data-allocated="${p.allocated_ads_budget || 0}">[${escapeHtml(p.client_company)}] ${escapeHtml(p.project_name)}</option>`;
      });

      if (selectSpend) selectSpend.innerHTML = optionsHtml;
      if (selectAlloc) selectAlloc.innerHTML = optionsHtml;
      if (selectTopup) selectTopup.innerHTML = optionsHtml;
    }

    function onAdsProjectSelectChange(selectElem) {
      const selectedOption = selectElem.options[selectElem.selectedIndex];
      const box = document.getElementById('box-allocated-info');
      const txt = document.getElementById('txt-allocated-budget');
      if (!box || !txt) return;

      const alloc = Number(selectedOption ? selectedOption.getAttribute('data-allocated') || 0 : 0);
      if (selectElem.value && alloc > 0) {
        txt.innerText = 'Rp ' + alloc.toLocaleString('id-ID');
        box.style.display = 'block';
      } else if (selectElem.value) {
        txt.innerText = 'Rp 0 (Belum dialokasikan Owner)';
        box.style.display = 'block';
      } else {
        box.style.display = 'none';
      }
    }

    function openUploadAdsTopupModal(projectId = null, defaultPlatform = 'Meta Ads') {
      const form = document.getElementById('form-upload-ads-topup');
      if (form) form.reset();

      const dateInput = document.getElementById('ads-topup-date');
      if (dateInput) {
        dateInput.value = new Date().toISOString().split('T')[0];
      }

      const projSelect = document.getElementById('ads-topup-project-id');
      if (projSelect && projectId) {
        projSelect.value = projectId;
        onTopupProjectChange(projSelect);
      } else {
        const box = document.getElementById('topup-allocated-info');
        if (box) box.style.display = 'none';
      }

      const platSelect = document.getElementById('ads-topup-platform');
      if (platSelect && defaultPlatform) {
        platSelect.value = defaultPlatform;
      }

      openModal('modal-upload-ads-topup');
    }

    function onTopupProjectChange(selectElem) {
      const selectedOption = selectElem.options[selectElem.selectedIndex];
      const box = document.getElementById('topup-allocated-info');
      const txt = document.getElementById('txt-topup-allocated-budget');
      if (!box || !txt) return;

      const alloc = Number(selectedOption ? selectedOption.getAttribute('data-allocated') || 0 : 0);
      if (selectElem.value && alloc > 0) {
        txt.innerText = 'Rp ' + alloc.toLocaleString('id-ID');
        box.style.display = 'block';
      } else if (selectElem.value) {
        txt.innerText = 'Rp 0 (Belum dialokasikan Owner)';
        box.style.display = 'block';
      } else {
        box.style.display = 'none';
      }
    }

    async function handleUploadAdsTopup(e) {
      e.preventDefault();
      const form = document.getElementById('form-upload-ads-topup');
      const submitBtn = document.getElementById('btn-submit-ads-topup');
      const origText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Mengupload Bukti...';

      const formData = new FormData(form);
      try {
        const res = await fetch('api/ads_tracker.php?action=upload_topup', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          closeModal('modal-upload-ads-topup');
          form.reset();
          loadAdsTrackerData();
        } else {
          showToast(data.message || 'Gagal mengupload bukti isi saldo', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origText;
      }
    }

    async function verifyTopupTransaction(transId) {
      const formData = new FormData();
      formData.append('transaction_id', transId);
      try {
        const res = await fetch('api/ads_tracker.php?action=verify_topup', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          loadAdsTrackerData();
        } else {
          showToast(data.message || 'Gagal memverifikasi', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      }
    }

    async function deleteTopupTransaction(transId) {
      if (!confirm('Apakah Anda yakin ingin menghapus transaksi isi saldo ini?')) return;
      const formData = new FormData();
      formData.append('transaction_id', transId);
      try {
        const res = await fetch('api/ads_tracker.php?action=delete_topup', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          loadAdsTrackerData();
        } else {
          showToast(data.message || 'Gagal menghapus transaksi', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      }
    }

    function openLogAdsSpendModal(projectId = null, defaultPlatform = 'Meta Ads') {
      const form = document.getElementById('form-update-ads-spend');
      if (form) form.reset();

      const projSelect = document.getElementById('ads-spend-project-id');
      if (projSelect && projectId) {
        projSelect.value = projectId;
        onAdsProjectSelectChange(projSelect);
      } else {
        const box = document.getElementById('box-allocated-info');
        if (box) box.style.display = 'none';
      }

      const platSelect = document.getElementById('ads-spend-platform');
      if (platSelect && defaultPlatform) {
        platSelect.value = defaultPlatform;
      }

      openModal('modal-update-ads-spend');
    }

    function openAllocateAdsModal(projectId = null, currentBudget = 0) {
      const form = document.getElementById('form-allocate-ads-budget');
      if (form) form.reset();

      const projSelect = document.getElementById('ads-alloc-project-id');
      if (projSelect && projectId) {
        projSelect.value = projectId;
      }

      const amountInput = document.getElementById('ads-alloc-amount');
      if (amountInput && currentBudget > 0) {
        amountInput.value = Number(currentBudget).toLocaleString('id-ID');
      }

      openModal('modal-allocate-ads-budget');
    }

    async function handleLogAdsSpend(e) {
      e.preventDefault();
      const form = document.getElementById('form-update-ads-spend');
      const submitBtn = document.getElementById('btn-submit-ads-spend');
      const origText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Menyimpan...';

      const formData = new FormData(form);
      try {
        const res = await fetch('api/ads_tracker.php?action=log_spend', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          closeModal('modal-update-ads-spend');
          form.reset();
          loadAdsTrackerData();
        } else {
          showToast(data.message || 'Gagal menyimpan anggaran iklan', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origText;
      }
    }

    async function handleAllocateAdsBudget(e) {
      e.preventDefault();
      const form = document.getElementById('form-allocate-ads-budget');
      const submitBtn = document.getElementById('btn-submit-ads-alloc');
      const origText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Menyimpan...';

      const formData = new FormData(form);
      try {
        const res = await fetch('api/ads_tracker.php?action=allocate_budget', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          closeModal('modal-allocate-ads-budget');
          form.reset();
          loadAdsTrackerData();
        } else {
          showToast(data.message || 'Gagal menyimpan alokasi pagu saldo', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origText;
      }
    }

    async function verifyAdsLog(logId) {
      const formData = new FormData();
      formData.append('log_id', logId);
      try {
        const res = await fetch('api/ads_tracker.php?action=verify_log', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          loadAdsTrackerData();
        } else {
          showToast(data.message || 'Gagal memverifikasi', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      }
    }

    async function deleteAdsLog(logId) {
      if (!confirm('Hapus riwayat anggaran iklan ini?')) return;
      const formData = new FormData();
      formData.append('log_id', logId);
      try {
        const res = await fetch('api/ads_tracker.php?action=delete_log', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          loadAdsTrackerData();
        } else {
          showToast(data.message || 'Gagal menghapus', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      }
    }

    function previewAdsProof(url) {
      const img = document.getElementById('img-proof-preview');
      const iframe = document.getElementById('iframe-proof-preview');

      if (url.toLowerCase().endsWith('.pdf')) {
        img.style.display = 'none';
        iframe.src = url;
        iframe.style.display = 'block';
      } else {
        iframe.style.display = 'none';
        img.src = url;
        img.style.display = 'inline-block';
      }
      openModal('modal-preview-ads-proof');
    }

    function formatRupiahInput(input) {
      let val = input.value.replace(/[^0-9]/g, '');
      if (val) {
        input.value = Number(val).toLocaleString('id-ID');
      } else {
        input.value = '';
      }
    }
  </script>

  <?php require_once BASE_PATH . '/includes/modals.php'; ?>
  <script src="assets/js/invoice.js?v=<?= time() ?>"></script>
</body>
</html>

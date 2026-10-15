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
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    /* Task Management & Workspace Custom Styles */
    .tasks-header-card {
      background: #FFFFFF;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-lg);
      padding: 24px;
      margin-bottom: 20px;
      box-shadow: 0 1px 3px rgba(16, 24, 40, 0.05);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 16px;
    }

    .kpi-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 14px;
      margin-bottom: 20px;
    }
    .kpi-tile {
      background: #FFFFFF;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md);
      padding: 16px;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
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
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md);
      padding: 14px 18px;
      margin-bottom: 18px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }

    /* Tasks Grid / Cards */
    .tasks-grid-list {
      display: grid;
      grid-template-columns: 1fr;
      gap: 12px;
    }
    .task-item-card {
      background: #FFFFFF;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md);
      padding: 18px 20px;
      transition: all 0.15s ease;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .task-item-card:hover {
      border-color: #98A2B3;
      box-shadow: 0 4px 12px -2px rgba(16, 24, 40, 0.06);
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
      background: #101828;
      color: #FFFFFF;
      border-bottom-left-radius: 2px;
    }
    .bubble.team-bubble {
      align-self: flex-end;
      background: #FFFFFF;
      color: #101828;
      border: 1px solid var(--border-color);
      border-bottom-right-radius: 2px;
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
            <span style="font-size: 11.5px; color: var(--text-secondary); font-weight: 600;">Total Deliverables</span>
            <div style="font-size: 22px; font-weight: 800; color: #101828;" id="kpi-total">0</div>
            <span style="font-size: 11px; color: var(--text-muted);">Pekerjaan aktif</span>
          </div>

          <div class="kpi-tile urgent">
            <span style="font-size: 11.5px; color: #B42318; font-weight: 700;">Butuh Revisi</span>
            <div style="font-size: 22px; font-weight: 800; color: #B42318;" id="kpi-revision">0</div>
            <span style="font-size: 11px; color: #B42318;">Permintaan klien</span>
          </div>

          <div class="kpi-tile review">
            <span style="font-size: 11.5px; color: #B54708; font-weight: 700;">Menunggu Review Klien</span>
            <div style="font-size: 22px; font-weight: 800; color: #B54708;" id="kpi-review">0</div>
            <span style="font-size: 11px; color: #B54708;">Menunggu approval</span>
          </div>

          <div class="kpi-tile">
            <span style="font-size: 11.5px; color: #175CD3; font-weight: 600;">Sedang Dikerjakan</span>
            <div style="font-size: 22px; font-weight: 800; color: #175CD3;" id="kpi-progress">0</div>
            <span style="font-size: 11px; color: var(--text-muted);">Tahap produksi</span>
          </div>

          <div class="kpi-tile">
            <span style="font-size: 11.5px; color: #027A48; font-weight: 600;">Selesai & Disetujui</span>
            <div style="font-size: 22px; font-weight: 800; color: #027A48;" id="kpi-approved">0</div>
            <span style="font-size: 11px; color: var(--text-muted);">Hasil final</span>
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

  <script src="assets/js/app.js"></script>
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
  </script>

  <?php require_once BASE_PATH . '/includes/modals.php'; ?>
  <script src="assets/js/invoice.js?v=<?= time() ?>"></script>
</body>
</html>

<?php
/**
 * Kalamedia Client Portal View
 * High-Aesthetic Untitled UI Modular Theme
 * Exclusively Scoped to Current Client's Projects, Deliverables, Content & Invoices
 */
require_auth();

$user = current_user();
$role = current_user_role();
$db = Database::getConnection();

// Determine Client ID
$clientId = current_user_client_id();
$allActiveClients = [];
if ($role === 'owner' || $role === 'admin') {
    $requestedClientId = intval($_GET['client_id'] ?? 0);
    if ($requestedClientId > 0) {
        $clientId = $requestedClientId;
    } else {
        $clientId = (int)$db->query("SELECT id FROM clients WHERE COALESCE(is_deleted,0)=0 ORDER BY id ASC LIMIT 1")->fetchColumn();
    }
    $allActiveClients = $db->query("SELECT id, name, company FROM clients WHERE COALESCE(is_deleted,0)=0 ORDER BY company ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Client Profile
$client = null;
if ($clientId > 0) {
    $stmtClient = $db->prepare("SELECT * FROM clients WHERE id = ?");
    $stmtClient->execute([$clientId]);
    $client = $stmtClient->fetch();
}

if (!$client) {
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Client Portal - Kalamedia</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=bankio-typography-3.0">
</head>
<body>
  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>
    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>
      <div class="content-body">
        <div style="background: #FFFFFF; border: 1px solid #ECEEF5; border-radius: 24px; padding: 50px 30px; text-align: center; margin-top: 20px; box-shadow: 0 10px 30px -10px rgba(18, 24, 48, 0.05), 0 2px 6px -1px rgba(18, 24, 48, 0.02);">
          <div style="display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; background: rgba(93, 118, 245, 0.12); color: #5D76F5; border-radius: 20px; font-size: 28px; margin-bottom: 16px;">
            ✦
          </div>
          <h2 style="font-size: 20px; font-weight: 800; color: #000000; margin-bottom: 8px;">Belum Ada Klien Terdaftar</h2>
          <p style="font-size: 14px; color: #6E7485; max-width: 520px; margin: 0 auto 24px; line-height: 1.5;">
            Saat ini belum ada data klien aktif di sistem Kalamedia. Anda dapat langsung membuat invoice baru untuk klien pertama atau menambahkan klien melalui menu Klien.
          </p>
          <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
            <?php if ($role === 'owner' || $role === 'admin'): ?>
              <button type="button" class="btn btn-primary" onclick="openModal('modal-create-invoice')">
                + Buat Invoice Baru
              </button>
              <a href="<?= url('clients') ?>" class="btn btn-secondary">
                Kelola Data Klien
              </a>
            <?php else: ?>
              <a href="<?= url('client-portal?tab=deliverables&portal=client&_slot=client') ?>" class="btn btn-primary">
                Segarkan Portal
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </main>
  </div>
  <?php require_once BASE_PATH . '/includes/modals.php'; ?>
  <script src="assets/js/app.js?v=<?= time() ?>"></script>
  <script src="assets/js/invoice.js?v=<?= time() ?>"></script>
</body>
</html>
<?php
    exit;
}

// Fetch Active Project for this client
$stmtProj = $db->prepare("SELECT * FROM projects WHERE client_id = ? AND COALESCE(is_deleted,0)=0 ORDER BY id DESC LIMIT 1");
$stmtProj->execute([$clientId]);
$project = $stmtProj->fetch();

// Fetch Content Calendar items for this client
$contentItems = $db->prepare("
    SELECT * FROM content_planner 
    WHERE client_id = ? AND COALESCE(is_deleted,0)=0 
    ORDER BY publish_date ASC, publish_time ASC 
    LIMIT 12
");
$contentItems->execute([$clientId]);
$contents = $contentItems->fetchAll();

// Fetch Invoices for this client (STRICT: Financial privacy - only client-facing invoice totals)
$stmtInvoices = $db->prepare("
    SELECT id, invoice_number, issue_date, due_date, total_amount, status
    FROM invoices
    WHERE client_id = ? AND COALESCE(is_deleted,0)=0
    ORDER BY id DESC
");
$stmtInvoices->execute([$clientId]);
$invoices = $stmtInvoices->fetchAll();

// Fetch Performance Marketing Reports for this client
$stmtReports = $db->prepare("
    SELECT * FROM performance_reports
    WHERE client_id = ? AND COALESCE(is_deleted,0)=0
    ORDER BY id DESC
");
$stmtReports->execute([$clientId]);
$clientReports = $stmtReports->fetchAll(PDO::FETCH_ASSOC);

// Fetch Ads Top-Up Transactions & Proof Receipts for this client
$stmtAdsTopup = $db->prepare("
    SELECT t.*, u.name as uploader_name, p.name as project_name
    FROM ads_topup_transactions t
    LEFT JOIN users u ON t.uploaded_by_user_id = u.id
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE t.client_id = ? AND COALESCE(t.is_deleted, 0) = 0
    ORDER BY t.transaction_date DESC, t.id DESC
");
$stmtAdsTopup->execute([$clientId]);
$clientAdsTopups = $stmtAdsTopup->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Client Portal - <?= htmlspecialchars($client['company'] ?? 'Kalamedia') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=bankio-typography-3.0">
  <style>
    /* Client Portal Custom Styles - Minimal Finance (Bankio) */
    .portal-hero {
      background: linear-gradient(135deg, #5D76F5 0%, #8932E0 100%);
      border-radius: 20px;
      padding: 26px 26px;
      color: #FFFFFF !important;
      margin-bottom: 22px;
      box-shadow: 0 14px 34px -8px rgba(93, 118, 245, 0.35);
      position: relative;
      overflow: hidden;
      border: 1px solid rgba(255, 255, 255, 0.18);
    }
    .portal-hero::after {
      content: '';
      position: absolute;
      top: -60px;
      right: -60px;
      width: 240px;
      height: 240px;
      background: radial-gradient(circle, rgba(255, 255, 255, 0.22) 0%, rgba(255, 255, 255, 0) 70%);
      border-radius: 50%;
      pointer-events: none;
    }
    .portal-hero * {
      color: #FFFFFF;
    }
    .portal-hero h1,
    .portal-hero .portal-hero-title {
      color: #FFFFFF !important;
      font-size: 20px !important;
      font-weight: 800 !important;
      margin: 10px 0 6px !important;
      letter-spacing: -0.3px !important;
      line-height: 1.25 !important;
    }
    .portal-hero p,
    .portal-hero .portal-hero-desc {
      color: rgba(255, 255, 255, 0.92) !important;
      font-size: 13px !important;
      line-height: 1.5 !important;
    }
    .portal-hero-top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 16px;
      flex-wrap: wrap;
      margin-bottom: 20px;
    }
    .portal-brand-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 5px 14px;
      background: rgba(255, 255, 255, 0.18);
      border: 1px solid rgba(255, 255, 255, 0.28);
      border-radius: 999px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      color: #FFFFFF !important;
      backdrop-filter: blur(4px);
    }
    .portal-brand-badge span {
      color: #FFFFFF !important;
    }
    .portal-progress-wrapper {
      background: rgba(255, 255, 255, 0.14);
      border: 1px solid rgba(255, 255, 255, 0.22);
      border-radius: 16px;
      padding: 16px 20px;
      margin-top: 16px;
      backdrop-filter: blur(4px);
    }
    .portal-progress-wrapper span,
    .portal-progress-wrapper div {
      color: #FFFFFF !important;
    }
    #portal-progress-text {
      color: rgba(255, 255, 255, 0.92) !important;
      font-size: 12.5px !important;
      margin-top: 2px !important;
    }
    #portal-progress-percent {
      color: #FFFFFF !important;
      font-size: 22px !important;
      font-weight: 800 !important;
      letter-spacing: -0.3px !important;
      line-height: 1.2 !important;
    }
    .portal-progress-bar-bg {
      height: 10px;
      background: rgba(255, 255, 255, 0.22);
      border-radius: 999px;
      overflow: hidden;
      margin: 10px 0 6px;
      position: relative;
    }
    .portal-progress-bar-fill {
      height: 100%;
      background: linear-gradient(90deg, #FFFFFF 0%, #E0EAFF 100%);
      border-radius: 999px;
      box-shadow: 0 0 12px rgba(255, 255, 255, 0.5);
      transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Portal Stats Grid */
    .portal-stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 22px;
    }
    .portal-stat-card {
      background: #FFFFFF;
      border: 1px solid #ECEEF5;
      border-radius: 18px;
      padding: 16px 18px;
      min-height: 105px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      box-shadow: 0 10px 30px -10px rgba(18, 24, 48, 0.05), 0 2px 6px -1px rgba(18, 24, 48, 0.02);
      transition: transform 220ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 220ms cubic-bezier(0.22, 1, 0.36, 1), border-color 220ms ease;
      will-change: transform;
    }
    .portal-stat-card:hover {
      transform: translate3d(0, -2px, 0);
      box-shadow: 0 14px 34px -8px rgba(18, 24, 48, 0.08);
      border-color: #DDE1EE;
    }
    .portal-stat-card.highlight {
      border-color: #FEDF89;
      background: #FFFAEB;
    }

    /* Portal Tabs - Bankio Pill Style */
    .portal-nav-tabs {
      display: inline-flex;
      gap: 6px;
      background: #EEF0F6;
      border: 1px solid #ECEEF5;
      border-radius: 999px;
      padding: 5px;
      margin-bottom: 24px;
      max-width: 100%;
      overflow-x: auto;
    }
    .portal-tab-btn {
      padding: 9px 18px;
      font-size: 13px;
      font-weight: 600;
      color: #6E7485;
      background: transparent;
      border: none;
      border-radius: 999px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      white-space: nowrap;
      transition: background-color 200ms cubic-bezier(0.22, 1, 0.36, 1), color 200ms cubic-bezier(0.22, 1, 0.36, 1), transform 150ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 200ms ease;
      font-family: inherit;
      will-change: transform;
    }
    .portal-tab-btn:hover:not(.active) {
      color: #000000;
      transform: translate3d(0, -1px, 0);
    }
    .portal-tab-btn:active {
      transform: scale(0.96);
      transition-duration: 90ms;
    }
    .portal-tab-btn.active {
      background: #5D76F5;
      color: #FFFFFF;
      box-shadow: 0 4px 14px rgba(93, 118, 245, 0.3);
      font-weight: 700;
    }

    /* Deliverable Task Cards */
    .tasks-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 16px;
    }
    .task-card {
      background: #FFFFFF;
      border: 1px solid #ECEEF5;
      border-radius: 20px;
      padding: 22px;
      transition: transform 220ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 220ms cubic-bezier(0.22, 1, 0.36, 1), border-color 220ms ease;
      box-shadow: 0 10px 30px -10px rgba(18, 24, 48, 0.05), 0 2px 6px -1px rgba(18, 24, 48, 0.02);
      position: relative;
      will-change: transform;
    }
    .task-card:hover {
      border-color: #DDE1EE;
      box-shadow: 0 14px 34px -8px rgba(18, 24, 48, 0.08);
      transform: translate3d(0, -2px, 0);
    }
    .task-card.status-client_review {
      border-left: 4px solid #F59E0B;
      background: #FFFDF5;
    }
    .task-card.status-revision {
      border-left: 4px solid #EF4444;
      background: #FEF2F2;
    }
    .task-card.status-approved {
      border-left: 4px solid #10B981;
    }
    .task-card-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
      margin-bottom: 12px;
      flex-wrap: wrap;
    }
    .badge-worker {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 11px;
      font-weight: 700;
      padding: 4px 12px;
      border-radius: 999px;
    }
    .badge-worker.karyawan {
      background: rgba(93, 118, 245, 0.12);
      color: #5D76F5;
      border: 1px solid rgba(93, 118, 245, 0.2);
    }
    .badge-worker.freelancer {
      background: rgba(137, 50, 224, 0.12);
      color: #8932E0;
      border: 1px solid rgba(137, 50, 224, 0.2);
    }

    /* Workflow Status Badge */
    .status-pill {
      font-size: 11px;
      font-weight: 700;
      padding: 4px 12px;
      border-radius: 999px;
      text-transform: capitalize;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }
    .status-pill.backlog { background: #F2F4F7; color: #475467; }
    .status-pill.in_progress { background: rgba(93, 118, 245, 0.12); color: #5D76F5; }
    .status-pill.internal_review { background: rgba(137, 50, 224, 0.12); color: #8932E0; }
    .status-pill.client_review { background: #FEF0C7; color: #B54708; }
    .status-pill.revision { background: #FEE4E2; color: #B42318; }
    .status-pill.approved { background: #D1FADF; color: #027A48; }

    /* Discussion Thread Modal */
    .discussion-thread {
      max-height: 380px;
      overflow-y: auto;
      padding: 16px;
      background: #F8F9FC;
      border: 1px solid #ECEEF5;
      border-radius: 16px;
      display: flex;
      flex-direction: column;
      gap: 14px;
      margin-bottom: 20px;
    }
    .bubble {
      max-width: 85%;
      padding: 12px 16px;
      border-radius: 16px;
      font-size: 13px;
      line-height: 1.5;
      position: relative;
    }
    .bubble.client-bubble {
      align-self: flex-end;
      background: #5D76F5;
      color: #FFFFFF;
      border-bottom-right-radius: 4px;
    }
    .bubble.team-bubble {
      align-self: flex-start;
      background: #FFFFFF;
      color: #000000;
      border: 1px solid #ECEEF5;
      border-bottom-left-radius: 4px;
    }
    .bubble-meta {
      font-size: 10.5px;
      margin-bottom: 4px;
      display: flex;
      align-items: center;
      gap: 6px;
      opacity: 0.85;
    }
  </style>
</head>
<body>

  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body">

        <!-- Preview Notice if Admin/Owner is looking at portal -->
        <?php if ($role !== 'client'): ?>
          <div style="background: #F0F4FF; border: 1px solid #D0DDFE; border-radius: 14px; padding: 12px 18px; margin-bottom: 20px; font-size: 13px; color: #1D2939; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; box-shadow: 0 2px 6px -1px rgba(93, 118, 245, 0.08);">
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
              <span style="background: #5D76F5; color: #FFFFFF; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 999px; letter-spacing: 0.3px;">
                MODE PREVIEW <?= strtoupper($role) ?>
              </span>
              <span style="color: #344054; font-weight: 600;">
                Pratinjau Portal Klien:
              </span>
              <?php if (!empty($allActiveClients)): ?>
                <select onchange="window.location.href='client-portal?preview=1&portal=internal&_slot=internal&client_id=' + this.value" style="font-size: 12.5px; font-weight: 700; color: #101828; padding: 5px 12px; border-radius: 8px; border: 1.5px solid #5D76F5; background: #FFFFFF; cursor: pointer;">
                  <?php foreach ($allActiveClients as $ac): ?>
                    <option value="<?= $ac['id'] ?>" <?= $clientId == $ac['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($ac['company']) ?> (PIC: <?= htmlspecialchars($ac['name']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php else: ?>
                <strong><?= htmlspecialchars($client['company'] ?? 'Klien') ?></strong>
              <?php endif; ?>
            </div>
            <div>
              <a href="<?= home_url_for_role($role) ?>" class="btn btn-secondary btn-sm" style="font-size: 12px; font-weight: 700; background: #FFFFFF; border: 1px solid #D0D5DD; color: #344054;">
                &larr; Kembali ke Dashboard
              </a>
            </div>
          </div>
        <?php endif; ?>

        <!-- 1. Client Project Hero Card -->
        <div class="portal-hero">
          <div class="portal-hero-top">
            <div>
              <div class="portal-brand-badge">
                <span style="color: #FFFFFF !important;"><?= htmlspecialchars($client['company']) ?></span>
                <span style="color: rgba(255, 255, 255, 0.6) !important;">&bull;</span>
                <span style="color: #FFFFFF !important;"><?= htmlspecialchars($client['package_name'] ?? 'Social Media & Performance Ads') ?></span>
              </div>
              <h1 class="portal-hero-title" style="font-size: 20px; font-weight: 800; margin: 10px 0 6px; letter-spacing: -0.3px; color: #FFFFFF !important;">
                <?= htmlspecialchars($project['name'] ?? 'Kampanye Digital & Produksi Konten') ?>
              </h1>
              <p class="portal-hero-desc" style="font-size: 13px; color: rgba(255, 255, 255, 0.92) !important; margin: 0; max-width: 640px; line-height: 1.5;">
                Selamat datang di Client Portal Kalamedia. Di sini Anda dapat memantau pengerjaan seluruh deliverable, meninjau hasil karya tim, dan memberikan revisi atau persetujuan secara transparan.
              </p>
            </div>

            <div style="text-align: right;">
              <span style="font-size: 11px; color: rgba(255, 255, 255, 0.8) !important; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">PIC Kalamedia Agensi</span>
              <div style="font-size: 14px; font-weight: 800; margin-top: 2px; color: #FFFFFF !important;">Tim Kalamedia</div>
              <a href="https://wa.me/628881124215" target="_blank" class="btn btn-sm" style="margin-top: 8px; background: rgba(255,255,255,0.18); color: #fff; border: 1px solid rgba(255,255,255,0.3); font-size: 11.5px; font-weight: 700;">
                Hubungi via WhatsApp
              </a>
            </div>
          </div>

          <!-- Progress Bar Section -->
          <div class="portal-progress-wrapper">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <div>
                <span style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #FFFFFF !important;">
                  Progres Pengerjaan Keseluruhan Deliverables
                </span>
                <div style="font-size: 12.5px; color: rgba(255, 255, 255, 0.92) !important; margin-top: 2px;" id="portal-progress-text">
                  Memuat data pengerjaan...
                </div>
              </div>
              <div style="font-size: 22px; font-weight: 800; letter-spacing: -0.3px; color: #FFFFFF !important; line-height: 1.2;" id="portal-progress-percent">
                0%
              </div>
            </div>

            <div class="portal-progress-bar-bg">
              <div class="portal-progress-bar-fill" id="portal-progress-fill" style="width: 0%;"></div>
            </div>
          </div>
        </div>

        <!-- 2. Portal Statistics Counters -->
        <div class="portal-stats-grid">
          <div class="portal-stat-card">
            <div>
              <span style="font-size: 11px; color: #949AB1; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;">TOTAL DELIVERABLES</span>
              <div style="font-size: 22px; font-weight: 800; color: #101828; letter-spacing: -0.02em; line-height: 1.2; margin: 4px 0;" id="stat-total-tasks">0</div>
            </div>
            <span style="font-size: 12px; color: #6E7485;">Pekerjaan proyek aktif</span>
          </div>

          <div class="portal-stat-card highlight">
            <div>
              <span style="font-size: 11px; color: #B54708; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; display: flex; align-items: center; gap: 4px;">
                BUTUH REVIEW KLIEN
              </span>
              <div style="font-size: 22px; font-weight: 800; color: #B54708; letter-spacing: -0.02em; line-height: 1.2; margin: 4px 0;" id="stat-review-tasks">0</div>
            </div>
            <span style="font-size: 12px; color: #B54708; font-weight: 600;">Menunggu persetujuan Anda</span>
          </div>

          <div class="portal-stat-card">
            <div>
              <span style="font-size: 11px; color: #949AB1; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;">SEDANG DIKERJAKAN</span>
              <div style="font-size: 22px; font-weight: 800; color: #101828; letter-spacing: -0.02em; line-height: 1.2; margin: 4px 0;" id="stat-progress-tasks">0</div>
            </div>
            <span style="font-size: 12px; color: #6E7485;">Proses kreatif & revisi tim</span>
          </div>

          <div class="portal-stat-card">
            <div>
              <span style="font-size: 11px; color: #949AB1; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;">SELESAI & DISETUJUI</span>
              <div style="font-size: 22px; font-weight: 800; color: #101828; letter-spacing: -0.02em; line-height: 1.2; margin: 4px 0;" id="stat-approved-tasks">0</div>
            </div>
            <span style="font-size: 12px; color: #6E7485;">Deliverables telah final</span>
          </div>
        </div>

        <!-- 3. Navigation Tabs -->
        <div class="portal-nav-tabs">
          <button class="portal-tab-btn active" onclick="switchPortalTab('deliverables', this)">
            <span>Progress & Deliverables</span>
          </button>
          <button class="portal-tab-btn" onclick="switchPortalTab('calendar', this)">
            <span>Kalender Konten (<?= count($contents) ?>)</span>
          </button>
          <button class="portal-tab-btn" onclick="switchPortalTab('invoices', this)">
            <span>Tagihan / Invoices (<?= count($invoices) ?>)</span>
          </button>
          <button class="portal-tab-btn" onclick="switchPortalTab('reports', this)">
            <span>Laporan Performa (<?= count($clientReports) ?>)</span>
          </button>
          <button class="portal-tab-btn" onclick="switchPortalTab('ads', this)">
            <span>Transparansi &amp; Bukti Saldo Ads (<?= count($clientAdsTopups) ?>)</span>
          </button>
        </div>

        <!-- TAB 1: Deliverables & Team Tasks -->
        <div id="tab-pane-deliverables">
          <!-- Filter Controls -->
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; gap: 8px; flex-wrap: wrap;" id="task-filter-chips">
              <button class="filter-btn active" onclick="filterClientTasks('all', this)">Semua Pekerjaan</button>
              <button class="filter-btn" onclick="filterClientTasks('client_review', this)">Butuh Review Klien</button>
              <button class="filter-btn" onclick="filterClientTasks('in_progress', this)">Sedang Dikerjakan</button>
              <button class="filter-btn" onclick="filterClientTasks('approved', this)">Disetujui</button>
            </div>

            <div style="font-size: 12px; color: var(--text-muted);">
              Klik pada pekerjaan untuk melihat rincian & memberi masukan.
            </div>
          </div>

          <!-- Tasks List Container -->
          <div class="tasks-grid" id="client-tasks-container">
            <div style="text-align: center; padding: 40px; color: var(--text-muted); background: #FFF; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
              Memuat daftar pekerjaan...
            </div>
          </div>

          <!-- Riwayat & Bukti Transaksi Saldo Iklan (Ads Top-Up & Spend) -->
          <div class="glass-panel" style="margin-top: 28px;">
            <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
              <div>
                <h3 class="panel-title" style="display: flex; align-items: center; gap: 8px;">
                  <span>Riwayat &amp; Bukti Transaksi Saldo Iklan (Ads Top-Up &amp; Spend)</span>
                  <span class="badge" style="background: #ECFDF3; color: #027A48; font-weight: 700; font-size: 11px;">Transparan &amp; Terverifikasi</span>
                </h3>
                <p style="font-size: 12.5px; color: var(--text-secondary); margin-top: 2px;">
                  Bukti transparan pengisian saldo iklan (Meta Ads, Google Ads, TikTok Ads) oleh Tim Ads Kalamedia beserta lampiran struk/billing resmi.
                </p>
              </div>
              <button type="button" class="btn btn-secondary btn-sm" onclick="switchPortalTab('ads', document.querySelector('.portal-tab-btn[onclick*=\'ads\']'))">
                Buka Live Tracker Ads &rarr;
              </button>
            </div>

            <div class="table-responsive">
              <table class="table-custom">
                <thead>
                  <tr>
                    <th>TANGGAL &amp; PETUGAS ADS</th>
                    <th>PROYEK &amp; PLATFORM</th>
                    <th>NOMINAL TOP-UP</th>
                    <th>ANGGARAN TERPASANG</th>
                    <th>CATATAN TIM ADS</th>
                    <th style="text-align: right;">BUKTI TRANSAKSI</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($clientAdsTopups)): ?>
                    <tr>
                      <td colspan="6" style="text-align: center; padding: 36px; color: var(--text-muted);">
                        <div style="font-size: 24px; margin-bottom: 6px;">💳</div>
                        <b>Belum ada riwayat pengisian saldo iklan</b>
                        <p style="font-size: 12px; margin: 4px 0 0;">Bukti transfer saldo iklan yang di-upload tim Ads akan otomatis muncul di sini.</p>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($clientAdsTopups as $topup): ?>
                      <tr>
                        <td>
                          <div style="font-weight: 700; color: #101828;">
                            <?= date('d M Y', strtotime($topup['transaction_date'])) ?>
                          </div>
                          <div style="font-size: 11px; color: var(--text-secondary);">
                            Petugas: <b><?= htmlspecialchars($topup['uploader_name'] ?? 'Tim Ads Kalamedia') ?></b>
                          </div>
                        </td>
                        <td>
                          <div style="font-weight: 700; color: #101828;">
                            <?= htmlspecialchars($topup['project_name'] ?? 'Kampanye Iklan') ?>
                          </div>
                          <?php
                            $pBg = '#F4F3FF'; $pCol = '#5925DC';
                            if (stripos($topup['platform'], 'Google') !== false) { $pBg = '#EFF8FF'; $pCol = '#175CD3'; }
                            elseif (stripos($topup['platform'], 'TikTok') !== false) { $pBg = '#F8F9FC'; $pCol = '#0C111D'; }
                          ?>
                          <span class="badge" style="background: <?= $pBg ?>; color: <?= $pCol ?>; font-weight: 700; font-size: 10.5px;">
                            <?= htmlspecialchars($topup['platform']) ?>
                          </span>
                        </td>
                        <td style="font-weight: 800; color: #027A48;">
                          + Rp <?= number_format($topup['topup_amount'], 0, ',', '.') ?>
                        </td>
                        <td style="font-weight: 700; color: #101828;">
                          Rp <?= number_format($topup['spend_installed'], 0, ',', '.') ?>
                        </td>
                        <td style="font-size: 12.5px; color: var(--text-secondary); max-width: 260px;">
                          <?= htmlspecialchars($topup['notes'] ?: 'Pengisian saldo iklan kampanye berjalan lancar.') ?>
                        </td>
                        <td style="text-align: right;">
                          <?php if (!empty($topup['receipt_file'])): ?>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="previewTransactionReceipt('<?= htmlspecialchars(addslashes($topup['receipt_file'])) ?>', 'Bukti Transaksi Top-Up Saldo <?= htmlspecialchars(addslashes($topup['platform'])) ?>')" style="font-size: 11.5px; font-weight: 700; gap: 4px; display: inline-flex; align-items: center;">
                              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                              Lihat Bukti Transaksi
                            </button>
                          <?php else: ?>
                            <span style="font-size: 11.5px; color: var(--text-muted); font-style: italic;">Tidak ada lampiran</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- TAB 2: Content Calendar -->
        <div id="tab-pane-calendar" style="display: none;">
          <div class="glass-panel">
            <div class="panel-header">
              <div>
                <h3 class="panel-title">Jadwal Penayangan Konten Klien</h3>
                <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Daftar jadwal konten media sosial yang telah direncanakan oleh tim kreatif Kalamedia.</p>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table-custom">
                <thead>
                  <tr>
                    <th>TANGGAL & WAKTU</th>
                    <th>PLATFORM</th>
                    <th>FORMAT KONTEN</th>
                    <th>JUDUL KONTEN</th>
                    <th>STATUS TAYANG</th>
                    <th>PREVIEW LINK</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($contents)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 32px; color: var(--text-muted);">Belum ada jadwal konten untuk bulan ini.</td></tr>
                  <?php else: ?>
                    <?php foreach ($contents as $c): ?>
                      <tr>
                        <td style="font-weight: 700; white-space: nowrap;">
                          <?= date('d M Y', strtotime($c['publish_date'])) ?>
                          <span style="font-size: 11px; color: var(--text-muted); display: block; font-weight: 500;"><?= substr($c['publish_time'] ?? '10:00', 0, 5) ?> WIB</span>
                        </td>
                        <td>
                          <span class="badge" style="background: #F2F4F7; color: #101828; font-weight: 700;">
                            <?= htmlspecialchars($c['platform']) ?>
                          </span>
                        </td>
                        <td style="font-size: 12.5px;"><?= htmlspecialchars($c['content_type']) ?></td>
                        <td style="font-weight: 700; color: #101828;"><?= htmlspecialchars($c['title']) ?></td>
                        <td>
                          <span class="badge" style="background: <?= $c['status'] === 'Published' ? '#ECFDF5' : '#F2F4F7' ?>; color: <?= $c['status'] === 'Published' ? '#027A48' : '#344054' ?>; font-weight: 700;">
                            <?= htmlspecialchars($c['status']) ?>
                          </span>
                        </td>
                        <td>
                          <?php if (!empty($c['asset_url'])): ?>
                            <a href="<?= htmlspecialchars($c['asset_url']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 4px 8px;">
                              Lihat Aset
                            </a>
                          <?php else: ?>
                            <span style="font-size: 11px; color: var(--text-tertiary);">-</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- TAB 3: Client Invoices (Client Privacy Assured) -->
        <div id="tab-pane-invoices" style="display: none;">
          <div class="glass-panel">
            <div class="panel-header">
              <div>
                <h3 class="panel-title">Riwayat Tagihan & Invoice Klien</h3>
                <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Daftar tagihan resmi untuk layanan agensi Kalamedia Creative.</p>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table-custom">
                <thead>
                  <tr>
                    <th>NO. INVOICE</th>
                    <th>TANGGAL TERBIT</th>
                    <th>JATUH TEMPO</th>
                    <th>TOTAL TAGIHAN</th>
                    <th>STATUS</th>
                    <th style="text-align: right;">AKSI</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($invoices)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 32px; color: var(--text-muted);">Belum ada riwayat tagihan invoice.</td></tr>
                  <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                      <tr>
                        <td style="font-weight: 800; color: #101828;"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                        <td><?= date('d M Y', strtotime($inv['issue_date'])) ?></td>
                        <td><?= date('d M Y', strtotime($inv['due_date'])) ?></td>
                        <td style="font-weight: 800; color: #101828;">Rp <?= number_format($inv['total_amount'], 0, ',', '.') ?></td>
                        <td>
                          <?php
                            $status = $inv['status'];
                            $bg = ($status === 'Paid') ? '#ECFDF5' : (($status === 'Overdue') ? '#FEF2F2' : '#F2F4F7');
                            $col = ($status === 'Paid') ? '#027A48' : (($status === 'Overdue') ? '#B42318' : '#344054');
                          ?>
                          <span class="badge" style="background: <?= $bg ?>; color: <?= $col ?>; font-weight: 700;">
                            <?= htmlspecialchars($status) ?>
                          </span>
                        </td>
                        <td style="text-align: right;">
                          <a href="<?= url('invoice-view') ?>?id=<?= $inv['id'] ?>&portal=client&_slot=client" class="btn btn-secondary btn-sm" style="font-size: 11.5px; font-weight: 700;">
                            Lihat / Cetak PDF
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- TAB 4: Performance Reports (Pitch Deck & Blended Economics) -->
        <div id="tab-pane-reports" style="display: none;">
          <div class="glass-panel">
            <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
              <div>
                <h3 class="panel-title">Laporan Kinerja Full-Funnel Performance Marketing</h3>
                <p style="font-size: 12.5px; color: var(--text-secondary); margin-top: 2px;">
                  Dokumen laporan hasil kampanye paid ads dan strategi performa resmi agensi Kalamedia Creative.
                </p>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table-custom">
                <thead>
                  <tr>
                    <th>PERIODE LAPORAN</th>
                    <th>OBJEKTIF KAMPANYE</th>
                    <th>TOTAL AD SPEND</th>
                    <th>GROSS REVENUE</th>
                    <th>BLENDED ROAS</th>
                    <th style="text-align: right;">AKSI</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($clientReports)): ?>
                    <tr>
                      <td colspan="6" style="text-align: center; padding: 32px; color: var(--text-muted);">
                        Belum ada dokumen laporan performa untuk periode aktif.
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($clientReports as $rep): ?>
                      <tr>
                        <td style="font-weight: 800; color: #101828;">
                          <?= htmlspecialchars($rep['report_period'] ?? 'Periode Berjalan') ?>
                        </td>
                        <td>
                          <span class="badge" style="background: #F2F4F7; color: #101828; font-weight: 700;">
                            <?= htmlspecialchars($rep['objective'] ?? 'Sales & Leads') ?>
                          </span>
                        </td>
                        <td style="font-weight: 700;">
                          Rp <?= number_format($rep['total_ad_spend'] ?? 0, 0, ',', '.') ?>
                        </td>
                        <td style="font-weight: 700; color: #027A48;">
                          Rp <?= number_format($rep['revenue'] ?? 0, 0, ',', '.') ?>
                        </td>
                        <td>
                          <span class="badge" style="background: #EEF2FF; color: #4338CA; font-weight: 800;">
                            <?= number_format($rep['roas'] ?? 0, 2) ?>x
                          </span>
                        </td>
                        <td style="text-align: right;">
                          <a href="<?= url('report-deck?id=' . $rep['id'] . '&portal=client&_slot=client') ?>" class="btn btn-secondary btn-sm" style="font-size: 11.5px; font-weight: 700;">
                            <span>Lihat Deck Laporan</span>
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- TAB 5: Client Live Ads Transparency -->
        <div id="tab-pane-ads" style="display: none;">
          <div class="glass-panel">
            <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
              <div>
                <h3 class="panel-title">Transparansi Kampanye & Anggaran Iklan (Live Ads Tracker)</h3>
                <p style="font-size: 12.5px; color: var(--text-secondary); margin-top: 2px;">
                  Informasi real-time penayangan iklan berbayar (Meta, Google, TikTok Ads) yang dijalankan dan diverifikasi agensi Kalamedia.
                </p>
              </div>
              <button type="button" class="btn btn-secondary btn-sm" onclick="loadClientAdsData()">
                Segarkan Data
              </button>
            </div>

            <div id="client-ads-container" style="padding: 24px;">
              <div style="text-align: center; padding: 32px; color: var(--text-muted);">
                Memuat data kampanye ads...
              </div>
            </div>
          </div>
        </div>

      </div>
    </main>
  </div>

  <!-- MODAL: Feedback & Approval Dialog -->
  <div class="modal" id="modal-task-feedback">
    <div class="modal-dialog" style="max-width: 640px;">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h4 class="modal-title" id="modal-task-title">Detail Pekerjaan & Masukan Klien</h4>
            <span style="font-size: 12px; color: var(--text-secondary);" id="modal-task-meta">Memuat data...</span>
          </div>
          <button type="button" class="close-btn" onclick="closeModal('modal-task-feedback')">&times;</button>
        </div>

        <div class="modal-body">
          <!-- Deliverable Preview Link Section -->
          <div id="modal-preview-box" style="background: #F8FAFC; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 14px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; gap: 10px;">
            <div>
              <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary);">Tautan Hasil Karya / Preview</div>
              <div style="font-size: 12.5px; color: var(--text-muted); margin-top: 2px;" id="modal-preview-url-text">Klik tombol untuk meninjau preview di tab baru.</div>
            </div>
            <a id="modal-preview-btn" href="#" target="_blank" class="btn btn-primary btn-sm" style="flex-shrink: 0; font-weight: 700;">
              <span>Buka Preview</span>
            </a>
          </div>

          <!-- Brief / Description -->
          <div style="margin-bottom: 16px;">
            <label class="form-label" style="font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.5px;">Brief / Penjelasan Pekerjaan</label>
            <div id="modal-task-desc" style="font-size: 13px; color: var(--text-secondary); line-height: 1.5; background: #FFF; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-sm);">
              -
            </div>
          </div>

          <!-- Discussion & Feedback History -->
          <div style="margin-bottom: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
              <label class="form-label" style="margin-bottom: 0; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.5px;">
                Riwayat Masukan & Diskusi Revisi
              </label>
              <span style="font-size: 11px; color: var(--text-muted);" id="thread-count">0 pesan</span>
            </div>
            <div class="discussion-thread" id="discussion-thread-container">
              <div style="text-align: center; color: var(--text-muted); font-size: 12px; padding: 16px;">
                Belum ada masukan sebelumnya.
              </div>
            </div>
          </div>

          <!-- Input New Feedback Form -->
          <form id="form-submit-feedback" onsubmit="handleFeedbackSubmit(event)">
            <input type="hidden" name="task_id" id="feedback-task-id">

            <div class="form-group" style="margin-bottom: 14px;">
              <label class="form-label" style="font-size: 12px; font-weight: 700;">Tindakan / Jenis Masukan Anda</label>
              <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                <label style="display: flex; align-items: center; gap: 6px; padding: 8px 10px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: 12px; cursor: pointer; background: #FFF;">
                  <input type="radio" name="feedback_type" value="comment" checked>
                  <span>Catatan</span>
                </label>
                <label style="display: flex; align-items: center; gap: 6px; padding: 8px 10px; border: 1px solid #FEC84B; border-radius: var(--radius-sm); font-size: 12px; cursor: pointer; background: #FFFAEB;">
                  <input type="radio" name="feedback_type" value="revision_request">
                  <span style="color: #B54708; font-weight: 700;">Minta Revisi</span>
                </label>
                <label style="display: flex; align-items: center; gap: 6px; padding: 8px 10px; border: 1px solid #A6F4C5; border-radius: var(--radius-sm); font-size: 12px; cursor: pointer; background: #ECFDF3;">
                  <input type="radio" name="feedback_type" value="approval">
                  <span style="color: #027A48; font-weight: 700;">Setujui Hasil</span>
                </label>
              </div>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
              <label class="form-label" style="font-size: 12px; font-weight: 700;">Catatan / Instruksi Masukan</label>
              <textarea name="message" id="feedback-message" class="form-control" rows="3" placeholder="Tuliskan catatan perbaikan atau masukan spesifik untuk tim Kalamedia..."></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 18px;">
              <label class="form-label" style="font-size: 11.5px; color: var(--text-secondary);">Lampiran Referensi / Screenshot Coretan (Opsional)</label>
              <input type="file" name="attachment" id="feedback-attachment" class="form-control" accept="image/*,.pdf,.mp4">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
              <button type="button" class="btn btn-secondary" onclick="closeModal('modal-task-feedback')">Tutup</button>
              <button type="submit" class="btn btn-primary" id="btn-submit-feedback">
                Kirim Masukan ke Tim
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/js/app.js?v=bankio-typography-3.0"></script>
  <script>
    let currentTasksList = [];

    // Initialize Client Portal Data
    document.addEventListener('DOMContentLoaded', () => {
      loadClientTasks();

      const urlParams = new URLSearchParams(window.location.search);
      const activeTab = urlParams.get('tab');
      if (activeTab && ['deliverables', 'calendar', 'invoices', 'reports', 'ads'].includes(activeTab)) {
        const targetBtn = document.querySelector(`.portal-tab-btn[onclick*="'${activeTab}'"]`);
        if (targetBtn) {
          switchPortalTab(activeTab, targetBtn);
        }
      }
    });

    async function loadClientTasks() {
      try {
        const slot = '<?= ($role === "client") ? "client" : "internal" ?>';
        const clientId = '<?= (int)$clientId ?>';
        const res = await fetch(`api/portal_tasks.php?action=list_tasks&_slot=${slot}&portal=${slot}&client_id=${clientId}`);
        const data = await res.json();

        if (data.success) {
          currentTasksList = data.tasks || [];
          renderProgress(data.metrics);
          renderTasks(currentTasksList);
        } else {
          showToast(data.message || 'Gagal memuat data portal', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal memuat data', 'danger');
      }
    }

    function renderProgress(m) {
      if (!m) return;
      document.getElementById('stat-total-tasks').innerText = m.total_tasks || 0;
      document.getElementById('stat-review-tasks').innerText = m.client_review_count || 0;
      document.getElementById('stat-progress-tasks').innerText = (m.in_progress_count || 0) + (m.revision_count || 0);
      document.getElementById('stat-approved-tasks').innerText = m.approved_count || 0;

      const pct = m.overall_progress || 0;
      document.getElementById('portal-progress-percent').innerText = pct + '%';
      document.getElementById('portal-progress-fill').style.width = pct + '%';
      document.getElementById('portal-progress-text').innerText = 
        `${m.approved_count || 0} dari ${m.total_tasks || 0} Deliverables telah disetujui (Approved).`;
    }

    function renderTasks(tasks) {
      const container = document.getElementById('client-tasks-container');
      if (!tasks || tasks.length === 0) {
        container.innerHTML = `
          <div style="text-align: center; padding: 48px 24px; background: #FFF; border: 1px solid var(--border-color); border-radius: var(--radius-md);">
            <div style="font-size: 32px; margin-bottom: 8px;">📋</div>
            <h4 style="font-size: 15px; font-weight: 800; color: #101828; margin-bottom: 4px;">Tidak Ada Pekerjaan pada Kategori Ini</h4>
            <p style="font-size: 12.5px; color: var(--text-secondary); margin: 0;">Semua deliverable pada filter ini telah terselesaikan atau belum dibuat.</p>
          </div>
        `;
        return;
      }

      const statusMap = {
        'backlog': { label: 'Antrean', class: 'backlog' },
        'in_progress': { label: 'Sedang Dikerjakan', class: 'in_progress' },
        'internal_review': { label: 'Review Internal Tim', class: 'internal_review' },
        'client_review': { label: 'Menunggu Review Anda', class: 'client_review' },
        'revision': { label: 'Dalam Perbaikan', class: 'revision' },
        'approved': { label: 'Selesai & Disetujui', class: 'approved' }
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
        const workerPos = t.worker_position ? ` (${escapeHtml(t.worker_position)})` : '';
        const workerBadge = isFreelance 
          ? `<span class="badge-worker freelancer">Freelancer: ${escapeHtml(t.worker_name || 'Talent')}${workerPos}</span>`
          : `<span class="badge-worker karyawan">Tim Kalamedia: ${escapeHtml(t.worker_name || 'Staff')}${workerPos}</span>`;

        const hasPreview = !!t.deliverable_link;
        const feedbacksCount = parseInt(t.feedbacks_count) || 0;

        html += `
          <div class="task-card status-${t.status}">
            <div class="task-card-header">
              <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <span class="status-pill ${sInfo.class}">
                  <span>${sInfo.label}</span>
                </span>
                <span class="badge" style="background: #F2F4F7; color: var(--text-secondary); font-weight: 700; font-size: 10.5px;">
                  ${categoryMap[t.category] || t.category}
                </span>
                ${workerBadge}
              </div>

              <div style="font-size: 11.5px; color: var(--text-muted);">
                <span>Target: <b>${t.due_date ? formatDate(t.due_date) : 'Segera'}</b></span>
              </div>
            </div>

            <h3 style="font-size: 15.5px; font-weight: 800; color: #101828; margin: 0 0 6px;">
              ${escapeHtml(t.title)}
            </h3>

            <p style="font-size: 13px; color: var(--text-secondary); margin: 0 0 14px; line-height: 1.5;">
              ${escapeHtml(t.description || 'Tidak ada deskripsi singkat.')}
            </p>

            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 12px; flex-wrap: wrap; gap: 10px;">
              <div style="display: flex; align-items: center; gap: 12px;">
                ${hasPreview ? `
                  <a href="${escapeHtml(t.deliverable_link)}" target="_blank" class="btn btn-secondary btn-sm" style="font-size: 11.5px; font-weight: 700;">
                    <span>Lihat Preview Hasil</span>
                  </a>
                ` : `
                  <span style="font-size: 11.5px; color: var(--text-tertiary); font-style: italic;">Preview belum tersedia</span>
                `}

                <span style="font-size: 11.5px; color: var(--text-muted);">
                  <b>${feedbacksCount}</b> masukan
                </span>
              </div>

              <div style="display: flex; gap: 8px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="openFeedbackModal(${t.id})" style="font-size: 12px; font-weight: 700;">
                  Beri Masukan / Revisi
                </button>

                ${t.status !== 'approved' ? `
                  <button type="button" class="btn btn-primary btn-sm" onclick="quickApproveTask(${t.id})" style="font-size: 12px; background: #027A48; border-color: #027A48;">
                    ✓ Setujui (Approve)
                  </button>
                ` : `
                  <span style="font-size: 12px; color: #027A48; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px;">
                    ✓ Telah Disetujui
                  </span>
                `}
              </div>
            </div>
          </div>
        `;
      });

      container.innerHTML = html;
    }

    function filterClientTasks(filter, btn) {
      document.querySelectorAll('#task-filter-chips .filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      if (filter === 'all') {
        renderTasks(currentTasksList);
      } else if (filter === 'client_review') {
        renderTasks(currentTasksList.filter(t => t.status === 'client_review' || t.status === 'revision'));
      } else {
        renderTasks(currentTasksList.filter(t => t.status === filter));
      }
    }

    function switchPortalTab(tabName, btn) {
      document.querySelectorAll('.portal-tab-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      ['deliverables', 'calendar', 'invoices', 'reports', 'ads'].forEach(t => {
        const pane = document.getElementById('tab-pane-' + t);
        if (pane) {
          if (t === tabName) {
            pane.style.display = 'block';
            pane.style.animation = 'none';
            void pane.offsetWidth; // Trigger reflow for smooth re-animation
            pane.style.animation = 'kalaPageEnter 260ms cubic-bezier(0.22, 1, 0.36, 1) both';
          } else {
            pane.style.display = 'none';
          }
        }
      });

      if (tabName === 'ads') {
        requestAnimationFrame(() => setTimeout(loadClientAdsData, 90));
      }
    }

    async function loadClientAdsData() {
      const container = document.getElementById('client-ads-container');
      try {
        const slot = '<?= ($role === "client") ? "client" : "internal" ?>';
        const clientId = '<?= (int)$clientId ?>';
        const res = await fetch(`api/ads_tracker.php?action=list&_slot=${slot}&portal=${slot}&client_id=${clientId}`);
        const data = await res.json();

        if (!data.success || !data.projects || data.projects.length === 0) {
          container.innerHTML = `
            <div style="text-align: center; padding: 40px; color: var(--text-muted); background: #F9FAFB; border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
              <div style="font-size: 28px; margin-bottom: 8px;">📊</div>
              <h4 style="font-size: 15px; font-weight: 700; color: #101828; margin-bottom: 4px;">Belum Ada Kampanye Iklan Berbayar</h4>
              <p style="font-size: 13px; margin: 0;">Saat ini belum ada kampanye ads berbayar yang aktif pada paket Anda atau data masih dalam proses setup.</p>
            </div>
          `;
          return;
        }

        let html = '';
        data.projects.forEach(p => {
          const logs = p.logs || [];
          const totalSpend = Number(p.total_spend_installed || 0);

          html += `
            <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(16,24,40,0.05);">
              <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 14px;">
                <div>
                  <span class="badge" style="background: #ECFDF3; color: #027A48; font-weight: 700; font-size: 11px; margin-bottom: 4px;">KAMPANYE IKLAN AKTIF</span>
                  <h4 style="font-size: 16px; font-weight: 800; color: #101828; margin: 2px 0;">${escapeHtml(p.project_name)}</h4>
                  <span style="font-size: 12.5px; color: var(--text-secondary);">${escapeHtml(p.client_company)} &bull; ${escapeHtml(p.package_name || 'Performance Marketing')}</span>
                </div>
                <div style="text-align: right;">
                  <span style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase; font-weight: 700;">Anggaran Iklan Terpasang</span>
                  <div style="font-size: 20px; font-weight: 800; color: #101828;">Rp ${totalSpend.toLocaleString('id-ID')}</div>
                </div>
              </div>

              <h5 style="font-size: 13px; font-weight: 700; color: #344054; margin: 0 0 10px;">Aktivitas & Riwayat Penayangan Iklan:</h5>
          `;

          if (logs.length === 0) {
            html += `
              <div style="padding: 16px; background: #F9FAFB; border-radius: 8px; font-size: 12.5px; color: var(--text-muted); font-style: italic;">
                Tim Ads Kalamedia sedang menyiapkan setup materi iklan dan penargetan audiens. Laporan penayangan berkala akan tampil di sini.
              </div>
            `;
          } else {
            html += `
              <div style="display: flex; flex-direction: column; gap: 10px;">
            `;
            logs.forEach(l => {
              const statusMap = {
                'sedang_berjalan': { label: 'Sedang Berjalan', bg: '#ECFDF3', color: '#027A48' },
                'menunggu_saldo': { label: 'Menunggu Saldo', bg: '#EFF8FF', color: '#175CD3' },
                'saldo_hampir_habis': { label: 'Saldo Menipis', bg: '#FEF3F2', color: '#B42318' },
                'selesai': { label: 'Selesai', bg: '#F2F4F7', color: '#344054' }
              };
              const st = statusMap[l.status] || { label: l.status, bg: '#F2F4F7', color: '#344054' };

              html += `
                <div style="background: #F9FAFB; border: 1px solid var(--border-color); border-radius: 8px; padding: 14px 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                  <div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                      <span class="badge" style="background: #F4F3FF; color: #5925DC; font-weight: 800; font-size: 11px;">${escapeHtml(l.platform)}</span>
                      <span class="badge" style="background: ${st.bg}; color: ${st.color}; font-weight: 700; font-size: 11px;">${st.label}</span>
                      <span style="font-size: 11px; color: var(--text-muted);">${formatDate(l.created_at || l.updated_at)}</span>
                    </div>
                    <div style="font-size: 13px; color: #101828; margin-top: 4px;">
                      ${escapeHtml(l.notes || 'Kampanye performa berjalan optimal sesuai target KPI.')}
                    </div>
                  </div>
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="text-align: right;">
                      <span style="font-size: 10.5px; color: var(--text-muted); display: block;">Realisasi Belanja</span>
                      <span style="font-size: 14px; font-weight: 800; color: #101828;">Rp ${Number(l.spend_installed || 0).toLocaleString('id-ID')}</span>
                    </div>
                    ${l.attachment_proof ? `
                      <a href="${escapeHtml(l.attachment_proof)}" target="_blank" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 5px 10px;">
                        Bukti Dashboard
                      </a>
                    ` : ''}
                  </div>
                </div>
              `;
            });
            html += `</div>`;
          }

          html += `</div>`;
        });

        // Render Top-Up Transactions in Tab Ads
        const topups = data.topup_transactions || [];
        if (topups.length > 0) {
          html += `
            <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 20px; margin-top: 24px; box-shadow: 0 1px 3px rgba(16,24,40,0.05);">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
                <div>
                  <h4 style="font-size: 16px; font-weight: 800; color: #101828; margin: 0 0 2px;">
                    Riwayat &amp; Bukti Transaksi Saldo Iklan (Top-Up Real-Time)
                  </h4>
                  <span style="font-size: 12px; color: var(--text-secondary);">
                    Catatan pengisian saldo iklan resmi dari Tim Ads Kalamedia beserta verifikasi struk transfer.
                  </span>
                </div>
                <span class="badge" style="background: #ECFDF3; color: #027A48; font-weight: 700;">
                  ${topups.length} Transaksi Terverifikasi
                </span>
              </div>

              <div class="table-responsive">
                <table class="table-custom">
                  <thead>
                    <tr>
                      <th>TANGGAL &amp; PETUGAS ADS</th>
                      <th>PLATFORM</th>
                      <th>SALDO DIISI (TOP-UP)</th>
                      <th>ANGGARAN TERPASANG</th>
                      <th>CATATAN KETERANGAN</th>
                      <th style="text-align: right;">BUKTI STRUK</th>
                    </tr>
                  </thead>
                  <tbody>
          `;
          topups.forEach(t => {
            const platformBadge = (t.platform || '').toLowerCase().includes('google')
              ? 'background: #EFF8FF; color: #175CD3;'
              : ((t.platform || '').toLowerCase().includes('tiktok') ? 'background: #F8F9FC; color: #0C111D;' : 'background: #F4F3FF; color: #5925DC;');

            html += `
              <tr>
                <td>
                  <div style="font-weight: 700; color: #101828;">${formatDate(t.transaction_date)}</div>
                  <div style="font-size: 11px; color: var(--text-secondary);">Oleh: ${escapeHtml(t.uploader_name || 'Tim Ads Kalamedia')}</div>
                </td>
                <td>
                  <span class="badge" style="${platformBadge} font-weight: 700; font-size: 11px;">${escapeHtml(t.platform)}</span>
                </td>
                <td style="font-weight: 800; color: #027A48;">
                  + Rp ${Number(t.topup_amount || 0).toLocaleString('id-ID')}
                </td>
                <td style="font-weight: 700; color: #101828;">
                  Rp ${Number(t.spend_installed || 0).toLocaleString('id-ID')}
                </td>
                <td style="font-size: 12px; color: var(--text-secondary); max-width: 250px;">
                  ${escapeHtml(t.notes || 'Pengisian saldo Ads.')}
                </td>
                <td style="text-align: right;">
                  ${t.receipt_file ? `
                    <button type="button" class="btn btn-secondary btn-sm" onclick="previewTransactionReceipt('${escapeHtml(t.receipt_file)}', 'Bukti Transaksi Saldo Iklan')" style="font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                      Lihat Bukti
                    </button>
                  ` : '<span style="font-size: 11px; color: var(--text-muted);">-</span>'}
                </td>
              </tr>
            `;
          });
          html += `
                  </tbody>
                </table>
              </div>
            </div>
          `;
        }

        container.innerHTML = html;
      } catch (err) {
        console.error(err);
        container.innerHTML = `
          <div style="text-align: center; padding: 32px; color: #B42318;">
            Gagal memuat transparansi iklan. Silakan coba kembali nanti.
          </div>
        `;
      }
    }

    async function openFeedbackModal(taskId) {
      try {
        const res = await fetch(`api/portal_tasks.php?action=get_task_details&id=${taskId}&_slot=client`);
        const data = await res.json();

        if (data.success) {
          const t = data.task;
          document.getElementById('feedback-task-id').value = t.id;
          document.getElementById('modal-task-title').innerText = t.title;
          document.getElementById('modal-task-meta').innerText = 
            `Kategori: ${t.category.toUpperCase()} • PIC: ${t.worker_name || 'Staff'} (${t.worker_type === 'freelancer' ? 'Freelancer' : 'Karyawan In-House'})`;
          document.getElementById('modal-task-desc').innerText = t.description || 'Tidak ada deskripsi rinci.';

          // Preview link setup
          const previewBox = document.getElementById('modal-preview-box');
          const previewBtn = document.getElementById('modal-preview-btn');
          if (t.deliverable_link) {
            previewBox.style.display = 'flex';
            previewBtn.href = t.deliverable_link;
          } else {
            previewBox.style.display = 'none';
          }

          // Render Discussion Thread
          const threadContainer = document.getElementById('discussion-thread-container');
          const feedbacks = data.feedbacks || [];
          document.getElementById('thread-count').innerText = `${feedbacks.length} pesan`;

          if (feedbacks.length === 0) {
            threadContainer.innerHTML = `
              <div style="text-align: center; color: var(--text-muted); font-size: 12px; padding: 18px;">
                Belum ada catatan atau masukan pada tugas ini. Tulis masukan pertama Anda di bawah!
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
                    <b>${escapeHtml(f.sender_name || 'Pengguna')}</b> (${isClient ? 'Klien' : 'Tim Kalamedia'})
                    <span>&bull;</span>
                    <span>${formatTime(f.created_at)}</span>
                    ${typeBadge}
                  </div>
                  <div>${escapeHtml(f.message)}</div>
                  ${f.attachment_path ? `
                    <div style="margin-top: 6px;">
                      <a href="${escapeHtml(f.attachment_path)}" target="_blank" style="color: ${isClient ? '#93C5FD' : '#175CD3'}; font-size: 11.5px; text-decoration: underline;">
                        Buka Lampiran Bukti / File
                      </a>
                    </div>
                  ` : ''}
                </div>
              `;
            });
            threadContainer.innerHTML = threadHtml;
            threadContainer.scrollTop = threadContainer.scrollHeight;
          }

          // Reset Form
          document.getElementById('feedback-message').value = '';
          document.getElementById('feedback-attachment').value = '';
          openModal('modal-task-feedback');
        } else {
          showToast(data.message || 'Gagal membuka rincian tugas', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      }
    }

    async function handleFeedbackSubmit(e) {
      e.preventDefault();
      const form = document.getElementById('form-submit-feedback');
      const submitBtn = document.getElementById('btn-submit-feedback');
      const origText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Mengirim...';

      const formData = new FormData(form);
      formData.append('_slot', 'client');

      try {
        const res = await fetch('api/portal_tasks.php?action=submit_feedback', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();

        if (data.success) {
          showToast(data.message, 'success');
          closeModal('modal-task-feedback');
          loadClientTasks(); // Reload tasks & progress
        } else {
          showToast(data.message || 'Gagal mengirim masukan', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origText;
      }
    }

    async function quickApproveTask(taskId) {
      if (!confirm('Apakah Anda yakin ingin menyetujui (Approve) hasil kerja ini?')) {
        return;
      }

      const formData = new FormData();
      formData.append('task_id', taskId);
      formData.append('feedback_type', 'approval');
      formData.append('message', 'Hasil kerja telah disetujui (Approved) secara resmi oleh Klien. Pekerjaan siap dipublikasikan!');
      formData.append('_slot', 'client');

      try {
        const res = await fetch('api/portal_tasks.php?action=submit_feedback', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          loadClientTasks();
        } else {
          showToast(data.message || 'Gagal menyetujui', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
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

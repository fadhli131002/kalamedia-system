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

// Strict Client Security: Block expired client from portal
if ($role === 'client' && $client) {
    $today = date('Y-m-d');
    $isContractExpired = false;
    if (!empty($client['contract_end_date']) && $today > $client['contract_end_date']) {
        $isContractExpired = true;
        $db->prepare("UPDATE clients SET contract_status = 'expired', status = 'expired' WHERE id = ?")->execute([$client['id']]);
        if (!empty($user['id'])) {
            $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$user['id']]);
        }
    } elseif (($client['contract_status'] ?? '') === 'expired') {
        $isContractExpired = true;
    }

    if ($isContractExpired) {
        $formattedEndDate = !empty($client['contract_end_date']) ? date('d F Y', strtotime($client['contract_end_date'])) : 'tanggal yang ditentukan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Masa Kontrak Berakhir - <?= htmlspecialchars($client['company'] ?? 'Kalamedia') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=saas-linear-1.0">
  <style>
    .expired-page {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      background: #F8F9FC;
      background-image: radial-gradient(circle at 10% 20%, rgba(239, 68, 68, 0.05) 0%, transparent 40%), radial-gradient(circle at 90% 80%, rgba(93, 118, 245, 0.05) 0%, transparent 40%);
      font-family: var(--font-sans);
    }
    .expired-card {
      max-width: 540px;
      width: 100%;
      background: #FFFFFF;
      border: 1px solid #ECEEF5;
      border-radius: 24px;
      padding: 44px 36px;
      text-align: center;
      box-shadow: 0 20px 40px -15px rgba(18, 24, 48, 0.08);
    }
  </style>
</head>
<body class="expired-page">
  <div class="expired-card">
    <div style="width: 72px; height: 72px; margin: 0 auto 20px; border-radius: 50%; background: #FEF3F2; color: #D92D20; display: flex; align-items: center; justify-content: center; font-size: 32px; border: 1px solid #FEE4E2;">
      ⏳
    </div>
    <span class="badge" style="background: #FEF3F2; color: #B42318; font-weight: 800; font-size: 11.5px; padding: 4px 12px; margin-bottom: 12px;">
      STATUS: KONTRAK SELESAI / EXPIRED
    </span>
    <h2 style="font-size: 22px; font-weight: 800; color: #101828; margin: 8px 0 12px;">
      Akses Layanan Ditangguhkan
    </h2>
    <p style="font-size: 14.5px; color: #475467; line-height: 1.6; margin-bottom: 24px;">
      Masa kontrak layanan Anda telah berakhir pada <b><?= $formattedEndDate ?></b>. Silakan hubungi tim Kalamedia untuk perpanjangan kontrak.
    </p>
    <div style="background: #F8F9FC; border: 1px solid #ECEEF5; border-radius: 12px; padding: 14px; margin-bottom: 24px; font-size: 13px; color: #344054; text-align: left;">
      <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
        <span style="color: #667085;">Klien / Perusahaan:</span>
        <b><?= htmlspecialchars($client['company'] ?? '-') ?></b>
      </div>
      <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
        <span style="color: #667085;">Layanan Terdaftar:</span>
        <b><?= htmlspecialchars($client['service_type'] ?? 'Kelola Sosmed') ?></b>
      </div>
      <div style="display: flex; justify-content: space-between;">
        <span style="color: #667085;">Akhir Kontrak:</span>
        <span style="color: #B42318; font-weight: 700;"><?= $formattedEndDate ?></span>
      </div>
    </div>
    <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
      <a href="https://wa.me/62895361622252?text=Halo%20Tim%20Kalamedia,%20saya%20ingin%20memperpanjang%20kontrak%20layanan%20<?= urlencode($client['company']) ?>" target="_blank" class="btn btn-primary" style="font-weight: 700; background: #027A48; border-color: #027A48;">
        Hubungi WhatsApp Tim Kalamedia
      </a>
      <a href="<?= url('logout') ?>?context=client" onclick="if(window.handleKalaLogout){handleKalaLogout(this.href);return false;}else{sessionStorage.removeItem('kala_tab_uid');}" class="btn btn-secondary" style="font-weight: 600;">
        Keluar (Logout)
      </a>
    </div>
  </div>
</body>
</html>
<?php
        exit;
    }
}

if (!$client) {
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Client Portal - Kalamedia</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=saas-linear-1.0">
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

// Fetch Ads Top-Up Transactions & Proof Receipts for this client (2-Stage Verification)
$stmtAdsTopup = $db->prepare("
    SELECT t.*, 
           COALESCE(NULLIF(t.transfer_amount_owner, 0), t.topup_amount, 0) as transfer_amount_owner,
           COALESCE(t.transfer_proof_owner, t.receipt_file) as transfer_proof_owner,
           COALESCE(NULLIF(t.platform_topup_amount, 0), t.topup_amount, 0) as platform_topup_amount,
           COALESCE(t.platform_topup_proof, t.receipt_file) as platform_topup_proof,
           COALESCE(t.platform_topup_status, 'completed') as platform_topup_status,
           COALESCE(NULLIF(t.ad_spend_amount, 0), t.spend_installed, 0) as ad_spend_amount,
           COALESCE(t.campaign_status, 'running') as campaign_status,
           COALESCE(t.stage_label, t.notes, 'Tahap 1') as stage_label,
           COALESCE(t.assigned_user_name, u.name, 'Tim Ads Kalamedia') as assigned_user_name,
           u.name as uploader_name, p.name as project_name
    FROM ads_topup_transactions t
    LEFT JOIN users u ON t.uploaded_by_user_id = u.id
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE t.client_id = ? AND COALESCE(t.is_deleted, 0) = 0
    ORDER BY t.transaction_date DESC, t.id DESC
");
$stmtAdsTopup->execute([$clientId]);
$clientAdsTopups = $stmtAdsTopup->fetchAll(PDO::FETCH_ASSOC);

// Dynamic Aggregation for Client Ad Funds (100% Dynamic from DB)
$stmtClientAdFunds = $db->prepare("
    SELECT 
        COALESCE(SUM(COALESCE(NULLIF(p.ads_budget_pool, 0), p.allocated_ads_budget, 0)), 0) as total_pagu_ads_klien,
        COALESCE((
            SELECT SUM(COALESCE(NULLIF(t.transfer_amount_owner, 0), t.topup_amount, 0))
            FROM ads_topup_transactions t
            WHERE t.client_id = ? AND COALESCE(t.is_deleted, 0) = 0
        ), 0) as total_ditransfer_ke_tim_ads,
        COALESCE((
            SELECT SUM(COALESCE(NULLIF(t.platform_topup_amount, 0), t.topup_amount, 0))
            FROM ads_topup_transactions t
            WHERE t.client_id = ? AND COALESCE(t.is_deleted, 0) = 0 AND (t.platform_topup_status IS NULL OR t.platform_topup_status = 'completed')
        ), 0) as total_topup_ke_platform,
        COALESCE((
            SELECT SUM(COALESCE(NULLIF(t.ad_spend_amount, 0), t.spend_installed, 0))
            FROM ads_topup_transactions t
            WHERE t.client_id = ? AND COALESCE(t.is_deleted, 0) = 0
        ), 0) as total_spend_terpakai
    FROM projects p
    WHERE p.client_id = ? AND COALESCE(p.is_deleted, 0) = 0
");
$stmtClientAdFunds->execute([$clientId, $clientId, $clientId, $clientId]);
$clientAdMetrics = $stmtClientAdFunds->fetch(PDO::FETCH_ASSOC) ?: [
    'total_pagu_ads_klien' => 0,
    'total_ditransfer_ke_tim_ads' => 0,
    'total_topup_ke_platform' => 0,
    'total_spend_terpakai' => 0
];

$clientPaguTotal = floatval($clientAdMetrics['total_pagu_ads_klien']);
$clientTransferredTotal = floatval($clientAdMetrics['total_ditransfer_ke_tim_ads']);
$clientTopupPlatformTotal = floatval($clientAdMetrics['total_topup_ke_platform']);
$clientSpendTotal = floatval($clientAdMetrics['total_spend_terpakai']);
$clientSisaPaguDiOwner = $clientPaguTotal - $clientTransferredTotal;
$clientSisaDiTimAds = $clientTransferredTotal - $clientSpendTotal;

// Check pending limit request from Owner to Client
$stmtPendingLimitReq = $db->prepare("
    SELECT r.*, p.name as project_name 
    FROM ads_balance_requests r
    LEFT JOIN projects p ON r.project_id = p.id
    WHERE r.client_id = ? AND r.request_type = 'owner_to_client' AND r.status = 'pending'
    ORDER BY r.id DESC LIMIT 1
");
$stmtPendingLimitReq->execute([$clientId]);
$pendingLimitRequest = $stmtPendingLimitReq->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Client Portal - <?= htmlspecialchars($client['company'] ?? 'Kalamedia') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=saas-linear-1.0">
  <?php render_tab_uid_script(); ?>
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
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 16px;
      margin-bottom: 22px;
    }
    @media (max-width: 1024px) {
      .portal-stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
      }
    }
    @media (max-width: 768px) {
      .portal-stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 10px !important;
      }
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

    /* Portal Tabs - SaaS Segmented Control Bar */
    .portal-nav-tabs {
      display: inline-flex;
      align-items: center;
      gap: 2px;
      background: #F3F4F6;
      border: none;
      border-radius: 8px;
      padding: 3px;
      margin-bottom: 20px;
      max-width: 100%;
      overflow-x: auto;
      white-space: nowrap;
      scrollbar-width: none;
      -webkit-overflow-scrolling: touch;
      box-shadow: none;
    }
    .portal-nav-tabs::-webkit-scrollbar {
      display: none;
    }
    .portal-tab-btn {
      padding: 6px 12px;
      font-size: 12.5px;
      font-weight: 500;
      color: #6B7280;
      background: transparent;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      white-space: nowrap;
      flex-shrink: 0;
      box-shadow: none;
      transition: all 150ms ease;
      font-family: inherit;
    }
    .portal-tab-btn:hover:not(.active) {
      color: #111827;
      background: rgba(0, 0, 0, 0.04);
    }
    .portal-tab-btn:active {
      transform: scale(0.98);
    }
    .portal-tab-btn.active {
      background: #FFFFFF !important;
      color: #111827 !important;
      border: none !important;
      box-shadow: 0 1px 2px rgba(16, 24, 40, 0.08) !important;
      font-weight: 600 !important;
    }
    .portal-tab-badge {
      font-size: 11px;
      font-weight: 600;
      padding: 1px 6px;
      border-radius: 4px;
      background: #E5E7EB;
      color: #4B5563;
      line-height: 1.2;
    }
    .portal-tab-btn.active .portal-tab-badge {
      background: #F3F4F6;
      color: #111827;
    }

    /* Filter & Search Toolbar (SaaS Clean Controls) */
    .portal-filter-toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      margin-bottom: 16px;
      flex-wrap: wrap;
    }
    .portal-filter-chips-track {
      display: inline-flex;
      align-items: center;
      gap: 2px;
      background: #F3F4F6;
      border-radius: 8px;
      padding: 3px;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      white-space: nowrap;
      scrollbar-width: none;
      max-width: 100%;
    }
    .portal-filter-chips-track::-webkit-scrollbar {
      display: none;
    }
    .portal-chip-btn,
    #task-filter-chips .filter-btn {
      background: transparent !important;
      border: none !important;
      color: #6B7280 !important;
      border-radius: 6px !important;
      padding: 6px 12px !important;
      font-size: 12.5px !important;
      font-weight: 500 !important;
      cursor: pointer !important;
      display: inline-flex !important;
      align-items: center !important;
      gap: 6px !important;
      white-space: nowrap !important;
      flex-shrink: 0 !important;
      box-shadow: none !important;
      transition: all 150ms ease !important;
      font-family: inherit !important;
      line-height: 1.2 !important;
      text-decoration: none !important;
    }
    .portal-chip-btn:hover:not(.active),
    #task-filter-chips .filter-btn:hover:not(.active) {
      background: rgba(0, 0, 0, 0.04) !important;
      color: #111827 !important;
    }
    .portal-chip-btn:active,
    #task-filter-chips .filter-btn:active {
      transform: scale(0.98) !important;
    }
    .portal-chip-btn.active,
    #task-filter-chips .filter-btn.active {
      background: #FFFFFF !important;
      border: none !important;
      color: #111827 !important;
      font-weight: 600 !important;
      box-shadow: 0 1px 2px rgba(16, 24, 40, 0.08) !important;
    }
    .portal-chip-badge {
      font-size: 11px;
      font-weight: 600;
      padding: 1px 6px;
      border-radius: 4px;
      background: #E5E7EB;
      color: #4B5563;
      line-height: 1.2;
    }
    .portal-chip-btn.active .portal-chip-badge,
    #task-filter-chips .filter-btn.active .portal-chip-badge {
      background: #F3F4F6;
      color: #111827;
    }

    /* Search Text Box */
    .portal-search-box {
      position: relative;
      display: flex;
      align-items: center;
      min-width: 240px;
      max-width: 320px;
      flex-grow: 1;
    }
    .portal-search-icon {
      position: absolute;
      left: 12px;
      font-size: 13px;
      pointer-events: none;
      opacity: 0.6;
    }
    .portal-search-input {
      width: 100%;
      height: 38px;
      padding: 6px 32px 6px 34px;
      border-radius: 10px;
      border: 1.5px solid #D0D5DD;
      background: #FFFFFF;
      color: #101828;
      font-size: 12.5px;
      font-family: inherit;
      box-shadow: 0 1px 3px rgba(16, 24, 40, 0.04);
      outline: none;
      transition: border-color 160ms ease, box-shadow 160ms ease;
    }
    .portal-search-input:focus {
      border-color: #5D76F5;
      box-shadow: 0 0 0 3.5px rgba(93, 118, 245, 0.15);
    }
    .portal-search-input::placeholder {
      color: #98A2B3;
      font-size: 12px;
    }
    .portal-search-clear {
      position: absolute;
      right: 10px;
      background: transparent;
      border: none;
      color: #98A2B3;
      font-size: 16px;
      font-weight: 700;
      cursor: pointer;
      padding: 0;
      line-height: 1;
    }
    .portal-search-clear:hover {
      color: #101828;
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

    @media (max-width: 768px) {
      aside.sidebar,
      .sidebar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        bottom: 0 !important;
        width: 280px !important;
        height: 100dvh !important;
        z-index: 9999 !important;
        background: #FFFFFF !important;
        transform: translate3d(-105%, 0, 0) !important;
        transition: transform 250ms cubic-bezier(0.22, 1, 0.36, 1) !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        padding: 20px 16px !important;
        box-shadow: 16px 0 40px rgba(0,0,0,0.15) !important;
      }
      aside.sidebar.open,
      aside.sidebar.sidebar-open,
      .sidebar.open,
      .sidebar.sidebar-open {
        transform: translate3d(0, 0, 0) !important;
      }
      .sidebar-overlay {
        z-index: 9998 !important;
      }

      /* Mobile Portal Preview Notice */
      .portal-preview-notice {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 10px !important;
        padding: 12px 14px !important;
      }
      .preview-notice-left {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 8px !important;
        width: 100% !important;
      }
      .preview-notice-header-row {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        flex-wrap: wrap !important;
      }
      .preview-client-select {
        width: 100% !important;
        height: 38px !important;
      }
      .preview-notice-right {
        width: 100% !important;
      }
      .preview-back-btn {
        width: 100% !important;
        justify-content: center !important;
        text-align: center !important;
        display: flex !important;
      }

      /* Mobile Portal Hero Card */
      .portal-hero {
        padding: 16px 14px !important;
        border-radius: 18px !important;
        margin-bottom: 16px !important;
      }
      .portal-hero-top {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 12px !important;
        margin-bottom: 12px !important;
      }
      .portal-brand-badge {
        font-size: 10px !important;
        padding: 4px 10px !important;
        max-width: 100% !important;
        white-space: normal !important;
        line-height: 1.35 !important;
        display: inline-flex !important;
        flex-wrap: wrap !important;
      }
      .portal-hero-title {
        font-size: 17px !important;
        margin: 8px 0 4px !important;
        line-height: 1.3 !important;
      }
      .portal-hero-desc {
        font-size: 12px !important;
        line-height: 1.45 !important;
      }
      .portal-hero-pic-block {
        text-align: left !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        background: rgba(255, 255, 255, 0.12) !important;
        padding: 10px 14px !important;
        border-radius: 14px !important;
        border: 1px solid rgba(255, 255, 255, 0.22) !important;
        margin-top: 4px !important;
        gap: 10px !important;
      }
      .portal-hero-pic-block .btn-whatsapp-pic {
        margin-top: 0 !important;
        padding: 6px 12px !important;
        white-space: nowrap !important;
        flex-shrink: 0 !important;
      }
      .portal-progress-wrapper {
        padding: 12px 14px !important;
        border-radius: 14px !important;
        margin-top: 10px !important;
      }
      .portal-progress-wrapper span {
        font-size: 10.5px !important;
      }
      #portal-progress-text {
        font-size: 11.5px !important;
      }
      #portal-progress-percent {
        font-size: 18px !important;
      }
      .portal-progress-bar-bg {
        height: 8px !important;
        margin: 8px 0 4px !important;
      }

      .portal-stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 8px !important;
        margin-bottom: 14px !important;
      }
      .portal-stat-card {
        padding: 12px !important;
        border-radius: 14px !important;
        min-height: auto !important;
      }
      .portal-stat-card div > span {
        font-size: 9.5px !important;
      }
      .portal-stat-card div > div {
        font-size: 18px !important;
        margin: 2px 0 !important;
      }
      .portal-stat-card > span {
        font-size: 10.5px !important;
      }

      .portal-nav-tabs {
        display: inline-flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        gap: 2px !important;
        background: #F3F4F6 !important;
        padding: 3px !important;
        border-radius: 8px !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        white-space: nowrap !important;
        scrollbar-width: none !important;
        -webkit-overflow-scrolling: touch !important;
        margin-bottom: 16px !important;
        box-sizing: border-box !important;
      }
      .portal-nav-tabs::-webkit-scrollbar {
        display: none !important;
      }
      .portal-nav-tabs .portal-tab-btn:first-child {
        grid-column: auto !important;
      }
      .portal-tab-btn {
        width: auto !important;
        white-space: nowrap !important;
        padding: 6px 12px !important;
        font-size: 12px !important;
        border-radius: 6px !important;
        justify-content: center !important;
        box-sizing: border-box !important;
        flex: 0 0 auto !important;
      }

      .portal-filter-toolbar {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 10px !important;
        margin-bottom: 14px !important;
      }
      .portal-filter-chips-track,
      #task-filter-chips {
        display: inline-flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        gap: 2px !important;
        background: #F3F4F6 !important;
        padding: 3px !important;
        border-radius: 8px !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        white-space: nowrap !important;
        scrollbar-width: none !important;
        -webkit-overflow-scrolling: touch !important;
        box-sizing: border-box !important;
      }
      .portal-filter-chips-track::-webkit-scrollbar,
      #task-filter-chips::-webkit-scrollbar {
        display: none !important;
      }
      .portal-chip-btn,
      #task-filter-chips .filter-btn {
        width: auto !important;
        padding: 6px 12px !important;
        font-size: 12px !important;
        border-radius: 6px !important;
        justify-content: center !important;
        box-sizing: border-box !important;
        flex: 0 0 auto !important;
      }
      .portal-search-box {
        min-width: 100% !important;
        max-width: 100% !important;
        width: 100% !important;
        box-sizing: border-box !important;
      }
      .portal-search-input {
        width: 100% !important;
        height: 38px !important;
        font-size: 12.5px !important;
        box-sizing: border-box !important;
      }
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
          <div class="portal-preview-notice" style="background: #F0F4FF; border: 1px solid #D0DDFE; border-radius: 14px; padding: 12px 18px; margin-bottom: 20px; font-size: 13px; color: #1D2939; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; box-shadow: 0 2px 6px -1px rgba(93, 118, 245, 0.08);">
            <div class="preview-notice-left" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
              <div class="preview-notice-header-row">
                <span class="preview-mode-badge" style="background: #5D76F5; color: #FFFFFF; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 999px; letter-spacing: 0.3px;">
                  MODE PREVIEW <?= strtoupper($role) ?>
                </span>
                <span class="preview-mode-label" style="color: #344054; font-weight: 600;">
                  Pratinjau Portal Klien:
                </span>
              </div>
              <?php if (!empty($allActiveClients)): ?>
                <select class="preview-client-select" onchange="window.location.href='client-portal?preview=1&portal=internal&_slot=internal&client_id=' + this.value" style="font-size: 12.5px; font-weight: 700; color: #101828; padding: 5px 12px; border-radius: 8px; border: 1.5px solid #5D76F5; background: #FFFFFF; cursor: pointer;">
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
            <div class="preview-notice-right">
              <a href="<?= home_url_for_role($role) ?>" class="btn btn-secondary btn-sm preview-back-btn" style="font-size: 12px; font-weight: 700; background: #FFFFFF; border: 1px solid #D0D5DD; color: #344054;">
                &larr; Kembali ke Dashboard
              </a>
            </div>
          </div>
        <?php endif; ?>

        <!-- 1. Client Project Hero Card -->
        <div class="portal-hero">
          <div class="portal-hero-top">
            <div class="portal-hero-main-info">
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

            <div class="portal-hero-pic-block" style="text-align: right;">
              <div class="portal-pic-text">
                <span class="portal-pic-label" style="font-size: 11px; color: rgba(255, 255, 255, 0.8) !important; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">PIC Kalamedia Agensi</span>
                <div class="portal-pic-name" style="font-size: 14px; font-weight: 800; margin-top: 2px; color: #FFFFFF !important;">Tim Kalamedia</div>
              </div>
              <a href="https://wa.me/628881124215" target="_blank" class="btn btn-sm btn-whatsapp-pic" style="margin-top: 8px; background: rgba(255,255,255,0.18); color: #fff; border: 1px solid rgba(255,255,255,0.3); font-size: 11.5px; font-weight: 700;">
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

        <!-- 2. Portal Statistics Counters (Shopeers Format) -->
        <div class="kpi-grid portal-stats-grid">
          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Total Deliverables</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            </div>
            <div class="kpi-value" id="stat-total-tasks">0</div>
            <div class="kpi-meta">Pekerjaan proyek aktif</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Butuh Review Klien</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <div class="kpi-value" id="stat-review-tasks">0</div>
            <div class="kpi-meta">Menunggu persetujuan Anda</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Sedang Dikerjakan</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="kpi-value" id="stat-progress-tasks">0</div>
            <div class="kpi-meta">Proses kreatif & revisi tim</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Selesai & Disetujui</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div class="kpi-value" id="stat-approved-tasks">0</div>
            <div class="kpi-meta">Deliverables telah final</div>
          </div>
        </div>

        <!-- 3. Navigation Tabs (Modern Segmented Boxed Bar) -->
        <div class="portal-nav-tabs">
          <button class="portal-tab-btn active" onclick="switchPortalTab('deliverables', this)">
            <span>Progress & Deliverables</span>
          </button>
          <button class="portal-tab-btn" onclick="switchPortalTab('calendar', this)">
            <span>Kalender Konten</span>
            <span class="portal-tab-badge"><?= count($contents) ?></span>
          </button>
          <button class="portal-tab-btn" onclick="switchPortalTab('invoices', this)">
            <span>Tagihan / Invoices</span>
            <span class="portal-tab-badge"><?= count($invoices) ?></span>
          </button>
          <button class="portal-tab-btn" onclick="switchPortalTab('reports', this)">
            <span>Laporan Performa</span>
            <span class="portal-tab-badge"><?= count($clientReports) ?></span>
          </button>
          <button class="portal-tab-btn" onclick="switchPortalTab('ads', this)">
            <span>Bukti Saldo Ads</span>
            <span class="portal-tab-badge"><?= count($clientAdsTopups) ?></span>
          </button>
        </div>

        <!-- TAB 1: Deliverables & Team Tasks -->
        <div id="tab-pane-deliverables">
          <!-- Filter Controls & Search Box (Modern Boxed Controls) -->
          <div class="portal-filter-toolbar">
            <div class="portal-filter-chips-track" id="task-filter-chips">
              <button type="button" class="portal-chip-btn filter-btn active" onclick="filterClientTasks('all', this)">
                <span>Semua Pekerjaan</span>
                <span class="portal-chip-badge" id="filter-count-all">0</span>
              </button>
              <button type="button" class="portal-chip-btn filter-btn" onclick="filterClientTasks('client_review', this)">
                <span>Butuh Review Klien</span>
                <span class="portal-chip-badge" id="filter-count-review">0</span>
              </button>
              <button type="button" class="portal-chip-btn filter-btn" onclick="filterClientTasks('in_progress', this)">
                <span>Sedang Dikerjakan</span>
                <span class="portal-chip-badge" id="filter-count-progress">0</span>
              </button>
              <button type="button" class="portal-chip-btn filter-btn" onclick="filterClientTasks('approved', this)">
                <span>Disetujui</span>
                <span class="portal-chip-badge" id="filter-count-approved">0</span>
              </button>
            </div>

            <div class="portal-search-box">
              <span class="portal-search-icon" style="display: flex; align-items: center;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              </span>
              <input 
                type="text" 
                id="client-task-search-input" 
                class="portal-search-input" 
                placeholder="Cari nama deliverable..." 
                oninput="handleClientTaskSearch(this.value)"
                autocomplete="off"
              >
              <button type="button" class="portal-search-clear" id="portal-search-clear-btn" onclick="clearClientTaskSearch()" style="display:none;" title="Bersihkan pencarian">&times;</button>
            </div>
          </div>

          <div style="font-size: 12px; color: #6E7485; margin: -6px 0 16px 2px; display: flex; align-items: center; gap: 6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-7 7c0 2.38 1.19 4.47 3 5.74V17a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-2.26c1.81-1.27 3-3.36 3-5.74a7 7 0 0 0-7-7z"/></svg>
            <span>Klik pada deliverable untuk melihat detail, riwayat feedback, dan persetujuan (approve).</span>
          </div>

          <!-- Tasks List Container -->
          <div class="tasks-grid" id="client-tasks-container">
            <div style="text-align: center; padding: 40px; color: var(--text-muted); background: #FFF; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
              Memuat daftar pekerjaan...
            </div>
          </div>

          <!-- Riwayat & Bukti Transaksi Saldo Iklan (Ads Top-Up & Spend) -->
          <div class="glass-panel" style="margin-top: 28px; overflow: visible; min-width: 0; max-width: 100%;">
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

            <div class="kala-table-scroll table-responsive" style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; display:block;">
              <table class="table-custom" style="min-width: 950px; width: 100%;">
                <thead>
                  <tr>
                    <th style="min-width: 190px; white-space: nowrap;">TANGGAL &amp; PETUGAS ADS</th>
                    <th style="white-space: nowrap;">PROYEK &amp; PLATFORM</th>
                    <th style="white-space: nowrap;">TAHAP 1: OWNER &rarr; TIM ADS</th>
                    <th style="white-space: nowrap;">TAHAP 2: TIM ADS &rarr; META/GOOGLE</th>
                    <th style="white-space: nowrap;">REALISASI SPEND &amp; SISA</th>
                    <th style="white-space: nowrap;">STATUS KAMPANYE</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($clientAdsTopups)): ?>
                    <tr>
                      <td colspan="6" style="text-align: center; padding: 36px; color: var(--text-muted);">
                        <div style="display: flex; justify-content: center; margin-bottom: 8px;">
                          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        </div>
                        <b>Belum ada riwayat pengisian saldo iklan</b>
                        <p style="font-size: 12px; margin: 4px 0 0;">Bukti transfer saldo iklan yang di-upload tim Ads akan otomatis muncul di sini.</p>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($clientAdsTopups as $topup): 
                      $tfOwnerAmount = floatval($topup['transfer_amount_owner']);
                      $tfOwnerProof = $topup['transfer_proof_owner'];
                      $topupPlatformAmount = floatval($topup['platform_topup_amount']);
                      $topupPlatformProof = $topup['platform_topup_proof'];
                      $topupPlatformStatus = $topup['platform_topup_status'];
                      $spendAktual = floatval($topup['ad_spend_amount']);
                      $sisaDiTim = max(0, $tfOwnerAmount - $spendAktual);
                      $campStatus = $topup['campaign_status'];
                    ?>
                      <tr>
                        <td style="min-width: 190px; white-space: nowrap;">
                          <div style="font-weight: 700; color: #101828;">
                            <?= date('d M Y', strtotime($topup['transaction_date'])) ?>
                          </div>
                          <div style="font-size: 11px; color: var(--text-secondary);">
                            Petugas: <b><?= htmlspecialchars($topup['assigned_user_name'] ?? $topup['uploader_name'] ?? 'Tim Ads') ?></b>
                          </div>
                        </td>
                        <td style="white-space: nowrap;">
                          <div style="font-weight: 700; color: #101828;">
                            <?= htmlspecialchars($topup['project_name'] ?? 'Kampanye Iklan') ?>
                          </div>
                          <?php
                            $pBg = '#F4F3FF'; $pCol = '#5925DC';
                            if (stripos($topup['platform'], 'Google') !== false) { $pBg = '#EFF8FF'; $pCol = '#175CD3'; }
                            elseif (stripos($topup['platform'], 'TikTok') !== false) { $pBg = '#F8F9FC'; $pCol = '#0C111D'; }
                          ?>
                          <div style="display: flex; gap: 4px; align-items: center; margin-top: 2px;">
                            <span class="badge" style="background: <?= $pBg ?>; color: <?= $pCol ?>; font-weight: 700; font-size: 10px;">
                              <?= htmlspecialchars($topup['platform']) ?>
                            </span>
                            <span style="font-size: 11px; color: var(--text-muted);">&bull; <?= htmlspecialchars($topup['stage_label'] ?? 'Tahap 1') ?></span>
                          </div>
                        </td>
                        <td style="white-space: nowrap;">
                          <div style="font-weight: 800; color: #175CD3; font-size: 13.5px;">
                            Rp <?= number_format($tfOwnerAmount, 0, ',', '.') ?>
                          </div>
                          <?php if (!empty($tfOwnerProof)): ?>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="previewTransactionReceipt('<?= htmlspecialchars(addslashes($tfOwnerProof)) ?>', 'Bukti Transfer Owner ke Tim Ads - <?= htmlspecialchars(addslashes($topup['project_name'] ?? '')) ?>')" style="font-size: 10.5px; font-weight: 700; padding: 3px 8px; margin-top: 4px; display: inline-flex; align-items: center; gap: 4px; color: #175CD3; border-color: #B2CCFF; background: #EFF8FF;">
                              📄 Bukti TF Owner
                            </button>
                          <?php else: ?>
                            <span style="font-size: 10.5px; color: var(--text-muted); font-style: italic; display: block; margin-top: 2px;">Belum ada lampiran</span>
                          <?php endif; ?>
                        </td>
                        <td style="white-space: nowrap;">
                          <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="font-weight: 800; color: #027A48; font-size: 13.5px;">
                              Rp <?= number_format($topupPlatformAmount, 0, ',', '.') ?>
                            </span>
                            <?php if ($topupPlatformStatus === 'completed'): ?>
                              <span class="badge" style="background: #ECFDF3; color: #027A48; font-weight: 700; font-size: 9.5px;">✓ Top-Up Selesai</span>
                            <?php else: ?>
                              <span class="badge" style="background: #EFF8FF; color: #175CD3; font-weight: 700; font-size: 9.5px;">Menunggu Top-Up</span>
                            <?php endif; ?>
                          </div>
                          <?php if (!empty($topupPlatformProof)): ?>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="previewTransactionReceipt('<?= htmlspecialchars(addslashes($topupPlatformProof)) ?>', 'Bukti Top-Up Platform <?= htmlspecialchars(addslashes($topup['platform'])) ?> - <?= htmlspecialchars(addslashes($topup['project_name'] ?? '')) ?>')" style="font-size: 10.5px; font-weight: 700; padding: 3px 8px; margin-top: 4px; display: inline-flex; align-items: center; gap: 4px; color: #027A48; border-color: #A6F4C5; background: #ECFDF3;">
                              📈 Bukti Top-Up Ads
                            </button>
                          <?php else: ?>
                            <span style="font-size: 10.5px; color: var(--text-muted); font-style: italic; display: block; margin-top: 2px;">Menunggu tim Ads</span>
                          <?php endif; ?>
                        </td>
                        <td style="white-space: nowrap;">
                          <div style="font-size: 11.5px; color: #475467;">
                            Spend: <b style="color: #101828;">Rp <?= number_format($spendAktual, 0, ',', '.') ?></b>
                          </div>
                          <div style="font-size: 11.5px; color: <?= $sisaDiTim <= 0 ? '#B42318' : '#027A48' ?>; font-weight: 700; margin-top: 2px;">
                            Sisa Saldo: Rp <?= number_format($sisaDiTim, 0, ',', '.') ?>
                          </div>
                        </td>
                        <td style="white-space: nowrap;">
                          <?php if ($campStatus === 'running' || $sisaDiTim > 0): ?>
                            <span class="badge" style="background: #ECFDF3; color: #027A48; font-weight: 700; font-size: 11px;">
                              ● Iklan Jalan
                            </span>
                          <?php elseif ($campStatus === 'paused'): ?>
                            <span class="badge" style="background: #FEF0C7; color: #B54708; font-weight: 700; font-size: 11px;">
                              ❚❚ Dijeda
                            </span>
                          <?php else: ?>
                            <span class="badge" style="background: #FEF3F2; color: #B42318; font-weight: 700; font-size: 11px;">
                              ✕ Saldo Habis
                            </span>
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

            <div class="kala-table-scroll table-responsive" style="width: 100%; max-width: 100%; overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; display: block !important; padding-bottom: 6px;">
              <table class="table-custom" style="min-width: 720px !important; width: 100%;">
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
          <div class="glass-panel" style="overflow: visible; min-width: 0; max-width: 100%;">
            <div class="panel-header">
              <div>
                <h3 class="panel-title">Riwayat Tagihan &amp; Invoice Klien</h3>
                <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Daftar tagihan resmi untuk layanan agensi Kalamedia Creative.</p>
              </div>
            </div>

            <div class="kala-table-scroll table-responsive" style="width: 100%; max-width: 100%; overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; display: block !important; padding-bottom: 8px;">
              <table class="table-custom" style="min-width: 820px !important; width: 100%;">
                <thead>
                  <tr>
                    <th style="white-space: nowrap !important;">NO. INVOICE</th>
                    <th style="white-space: nowrap !important;">TANGGAL TERBIT</th>
                    <th style="white-space: nowrap !important;">JATUH TEMPO</th>
                    <th style="white-space: nowrap !important;">TOTAL TAGIHAN</th>
                    <th style="white-space: nowrap !important;">STATUS</th>
                    <th style="text-align: right; white-space: nowrap !important;">AKSI</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($invoices)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 32px; color: var(--text-muted); white-space: nowrap !important;">Belum ada riwayat tagihan invoice.</td></tr>
                  <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                      <tr>
                        <td style="font-weight: 800; color: #101828; white-space: nowrap !important;"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                        <td style="white-space: nowrap !important;"><?= date('d M Y', strtotime($inv['issue_date'])) ?></td>
                        <td style="white-space: nowrap !important;"><?= date('d M Y', strtotime($inv['due_date'])) ?></td>
                        <td style="font-weight: 800; color: #101828; white-space: nowrap !important;">Rp <?= number_format($inv['total_amount'], 0, ',', '.') ?></td>
                        <td style="white-space: nowrap !important;">
                          <?php
                            $status = $inv['status'];
                            $bg = ($status === 'Paid') ? '#ECFDF5' : (($status === 'Overdue') ? '#FEF2F2' : '#F2F4F7');
                            $col = ($status === 'Paid') ? '#027A48' : (($status === 'Overdue') ? '#B42318' : '#344054');
                          ?>
                          <span class="badge" style="background: <?= $bg ?>; color: <?= $col ?>; font-weight: 700;">
                            <?= htmlspecialchars($status) ?>
                          </span>
                        </td>
                        <td style="text-align: right; white-space: nowrap !important;">
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

            <div class="kala-table-scroll table-responsive" style="width: 100%; max-width: 100%; overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; display: block !important; padding-bottom: 6px;">
              <table class="table-custom" style="min-width: 720px !important; width: 100%;">
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

        <!-- TAB 5: Client Live Ads Transparency (100% Dynamic Aggregation) -->
        <div id="tab-pane-ads" style="display: none;">
          <?php if (!empty($pendingLimitRequest)): ?>
            <!-- Prominent Banner Notifikasi Pengajuan Tambah Limit dari Owner -->
            <div id="client-banner-limit-request" style="background: linear-gradient(135deg, #FEF0C7 0%, #FEDF89 100%); border: 1.5px solid #FDB022; border-radius: 14px; padding: 18px 20px; margin-bottom: 22px; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.18); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
              <div style="display: flex; align-items: flex-start; gap: 14px; max-width: 760px;">
                <span style="font-size: 26px; line-height: 1;">🔔</span>
                <div>
                  <div style="font-size: 14.5px; font-weight: 800; color: #7A2E0E;">
                    Pengajuan Penambahan Limit Saldo Iklan dari Owner Kalamedia
                  </div>
                  <p style="font-size: 13px; color: #93370D; margin: 4px 0 0; line-height: 1.5;">
                    Owner mengajukan penambahan limit saldo iklan sebesar <b>Rp <?= number_format($pendingLimitRequest['amount_requested'], 0, ',', '.') ?></b> untuk proyek <b><?= htmlspecialchars($pendingLimitRequest['project_name'] ?? 'Kampanye Iklan') ?></b> agar penayangan iklan di platform tidak terhenti.
                    <?php if (!empty($pendingLimitRequest['notes'])): ?>
                      <br><i>Catatan Owner: "<?= htmlspecialchars($pendingLimitRequest['notes']) ?>"</i>
                    <?php endif; ?>
                  </p>
                </div>
              </div>
              <button type="button" class="btn btn-primary" onclick="openClientApproveLimitModal(<?= (int)$pendingLimitRequest['project_id'] ?>, <?= (int)$pendingLimitRequest['id'] ?>, <?= (float)$pendingLimitRequest['amount_requested'] ?>, '<?= htmlspecialchars(addslashes($pendingLimitRequest['notes'] ?? '')) ?>')" style="background: #027A48; border-color: #027A48; font-weight: 800; font-size: 12.5px; padding: 10px 18px; border-radius: 10px; box-shadow: 0 2px 6px rgba(2,122,72,0.3); display: inline-flex; align-items: center; gap: 6px;">
                <span>✅ Setuju Tambah Limit &amp; Upload Bukti Transfer</span>
              </button>
            </div>
          <?php endif; ?>

          <!-- 4 Kartu KPI Dana Iklan di Portal Klien -->
          <div class="kpi-grid portal-stats-grid" id="client-ads-kpi-row" style="grid-template-columns: repeat(4, 1fr) !important; gap: 14px; margin-bottom: 24px;">
            <!-- Kartu 1: Total Pagu Saldo Ads Anda -->
            <div class="kpi-card" style="border-top: 3px solid #5925DC;">
              <div class="kpi-header">
                <span class="kpi-title" style="color: #5925DC; font-weight: 700;">Total Pagu Saldo Ads Anda</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#5925DC" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
              </div>
              <div class="kpi-value" id="kpi-client-pagu" style="color: #101828; font-size: 22px;">
                Rp <?= number_format($clientPaguTotal, 0, ',', '.') ?>
              </div>
              <div class="kpi-meta">Pagu limit saldo iklan yang Anda alokasikan</div>
            </div>

            <!-- Kartu 2: Dicairkan ke Tim Ads -->
            <div class="kpi-card" style="border-top: 3px solid #175CD3;">
              <div class="kpi-header">
                <span class="kpi-title" style="color: #175CD3; font-weight: 700;">Dicairkan ke Tim Ads</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#175CD3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 9V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><rect x="9" y="9" width="12" height="10" rx="2"/></svg>
              </div>
              <div class="kpi-value" id="kpi-client-transferred" style="color: #175CD3; font-size: 22px;">
                Rp <?= number_format($clientTransferredTotal, 0, ',', '.') ?>
              </div>
              <div class="kpi-meta">Dana dicairkan Owner ke Tim Ads pelaksana</div>
            </div>

            <!-- Kartu 3: Sudah Top-Up & Jalan di Meta/Google -->
            <div class="kpi-card" style="border-top: 3px solid #027A48;">
              <div class="kpi-header">
                <span class="kpi-title" style="color: #027A48; font-weight: 700;">Top-Up &amp; Jalan di Platform</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#027A48" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
              </div>
              <div class="kpi-value" id="kpi-client-topup-platform" style="color: #027A48; font-size: 22px;">
                Rp <?= number_format($clientTopupPlatformTotal, 0, ',', '.') ?>
              </div>
              <div class="kpi-meta" id="kpi-client-spend-meta">
                Spend: <b>Rp <?= number_format($clientSpendTotal, 0, ',', '.') ?></b> &bull; <?= ($clientSisaDiTimAds > 0 || $clientTopupPlatformTotal > 0) ? 'Iklan Jalan' : 'Dijeda' ?>
              </div>
            </div>

            <!-- Kartu 4: Sisa Pagu Saldo Ads Anda -->
            <div class="kpi-card <?= $clientSisaPaguDiOwner <= 0 ? 'urgent' : '' ?>" style="border-top: 3px solid <?= $clientSisaPaguDiOwner <= 0 ? '#B42318' : '#059669' ?>;">
              <div class="kpi-header">
                <span class="kpi-title" style="color: <?= $clientSisaPaguDiOwner <= 0 ? '#B42318' : '#059669' ?>; font-weight: 700;">Sisa Pagu Saldo Ads Anda</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="<?= $clientSisaPaguDiOwner <= 0 ? '#B42318' : '#059669' ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              </div>
              <div class="kpi-value" id="kpi-client-remaining" style="color: <?= $clientSisaPaguDiOwner <= 0 ? '#B42318' : '#059669' ?>; font-size: 22px;">
                Rp <?= number_format($clientSisaPaguDiOwner, 0, ',', '.') ?>
              </div>
              <div class="kpi-meta" id="kpi-client-remaining-meta">
                Di Owner: Rp <?= number_format($clientSisaPaguDiOwner, 0, ',', '.') ?> | Di Iklan: Rp <?= number_format($clientSisaDiTimAds, 0, ',', '.') ?>
              </div>
            </div>
          </div>

          <div class="glass-panel">
            <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
              <div>
                <h3 class="panel-title">Transparansi Kampanye &amp; Anggaran Iklan (Live Ads Tracker)</h3>
                <p style="font-size: 12.5px; color: var(--text-secondary); margin-top: 2px;">
                  Informasi real-time penayangan iklan berbayar (Meta, Google, TikTok Ads) beserta transparansi 2 tahap verifikasi bukti transaksi.
                </p>
              </div>
              <button type="button" class="btn btn-secondary btn-sm" onclick="loadClientAdsData()">
                Segarkan Data
              </button>
            </div>

            <div id="client-ads-container" style="padding: 24px;">
              <!-- Container for Live Projects & 2-Stage Verification Table -->
              <div style="text-align: center; padding: 32px; color: var(--text-muted);">
                Memuat transparansi iklan...
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
              <div class="kala-feedback-type-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;">
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
    let currentTaskFilter = 'all';
    let currentTaskSearchQuery = '';

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
          applyClientTaskFilters();
        } else {
          renderTasks([]);
        }
      } catch (err) {
        renderTasks([]);
      }
    }

    function renderProgress(m) {
      if (!m) return;
      const total = m.total_tasks || 0;
      const review = m.client_review_count || 0;
      const progress = (m.in_progress_count || 0) + (m.revision_count || 0);
      const approved = m.approved_count || 0;

      document.getElementById('stat-total-tasks').innerText = total;
      document.getElementById('stat-review-tasks').innerText = review;
      document.getElementById('stat-progress-tasks').innerText = progress;
      document.getElementById('stat-approved-tasks').innerText = approved;

      // Update Boxed Filter Badges
      const bAll = document.getElementById('filter-count-all');
      if (bAll) bAll.innerText = total;
      const bReview = document.getElementById('filter-count-review');
      if (bReview) bReview.innerText = review;
      const bProgress = document.getElementById('filter-count-progress');
      if (bProgress) bProgress.innerText = progress;
      const bApproved = document.getElementById('filter-count-approved');
      if (bApproved) bApproved.innerText = approved;

      const pct = m.overall_progress || 0;
      document.getElementById('portal-progress-percent').innerText = pct + '%';
      document.getElementById('portal-progress-fill').style.width = pct + '%';
      document.getElementById('portal-progress-text').innerText = 
        `${approved} dari ${total} Deliverables telah disetujui (Approved).`;
    }

    function applyClientTaskFilters() {
      let filtered = currentTasksList.slice();

      if (currentTaskFilter === 'client_review') {
        filtered = filtered.filter(t => t.status === 'client_review' || t.status === 'revision');
      } else if (currentTaskFilter === 'in_progress') {
        filtered = filtered.filter(t => t.status === 'in_progress');
      } else if (currentTaskFilter === 'approved') {
        filtered = filtered.filter(t => t.status === 'approved');
      }

      if (currentTaskSearchQuery && currentTaskSearchQuery.trim() !== '') {
        const q = currentTaskSearchQuery.trim().toLowerCase();
        filtered = filtered.filter(t => {
          const title = (t.title || '').toLowerCase();
          const desc = (t.description || '').toLowerCase();
          const cat = (t.category || '').toLowerCase();
          const worker = (t.worker_name || '').toLowerCase();
          return title.includes(q) || desc.includes(q) || cat.includes(q) || worker.includes(q);
        });
      }

      renderTasks(filtered);
    }

    function filterClientTasks(filter, btn) {
      document.querySelectorAll('#task-filter-chips .filter-btn, #task-filter-chips .portal-chip-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentTaskFilter = filter;
      applyClientTaskFilters();
    }

    function handleClientTaskSearch(query) {
      currentTaskSearchQuery = query;
      const clearBtn = document.getElementById('portal-search-clear-btn');
      if (clearBtn) {
        clearBtn.style.display = (query && query.length > 0) ? 'inline-block' : 'none';
      }
      applyClientTaskFilters();
    }

    function clearClientTaskSearch() {
      const input = document.getElementById('client-task-search-input');
      if (input) {
        input.value = '';
        input.focus();
      }
      handleClientTaskSearch('');
    }

    function renderTasks(tasks) {
      const container = document.getElementById('client-tasks-container');
      if (!tasks || tasks.length === 0) {
        const isSearch = currentTaskSearchQuery && currentTaskSearchQuery.trim() !== '';
        container.innerHTML = `
          <div style="text-align: center; padding: 48px 24px; background: #FFF; border: 1.5px solid var(--border-color); border-radius: 16px; box-shadow: 0 1px 3px rgba(16,24,40,0.03);">
            <div style="display: flex; justify-content: center; margin-bottom: 12px;">${isSearch ? '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>' : '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>'}</div>
            <h4 style="font-size: 15px; font-weight: 800; color: #101828; margin-bottom: 4px;">
              ${isSearch ? 'Tidak Ada Deliverable yang Cocok' : 'Tidak Ada Pekerjaan pada Kategori Ini'}
            </h4>
            <p style="font-size: 12.5px; color: var(--text-secondary); margin: 0;">
              ${isSearch 
                ? `Tidak ditemukan deliverable dengan kata kunci "<b>${escapeHtml(currentTaskSearchQuery)}</b>". Coba gunakan kata kunci lain.` 
                : 'Semua deliverable pada filter ini telah terselesaikan atau belum dibuat.'}
            </p>
            ${isSearch ? `
              <button type="button" class="btn btn-secondary btn-sm" onclick="clearClientTaskSearch()" style="margin-top: 14px; font-weight: 700;">
                Bersihkan Filter Pencarian
              </button>
            ` : ''}
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
        const isFreelance = (t.worker_type === 'freelancer');
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

            ${(t.is_ads_task || parseFloat(t.assigned_ads_budget) > 0) ? `
              <!-- Real-Time Ads Monitoring Panel (Client View) -->
              <div style="background: #F8F9FC; border: 1.5px solid #EAECF0; border-radius: 12px; padding: 14px; margin: 12px 0;">
                ${t.is_depleted ? `
                  <div style="background: #FFEFEF; border: 1.5px solid #FECDCA; border-radius: 8px; padding: 10px 14px; margin-bottom: 12px; color: #B42318; font-weight: 700; font-size: 12.5px; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 18px;">⚠️</span>
                    <span><b>PEMBERITAHUAN: SALDO IKLAN TELAH HABIS (Rp 0)!</b> Kampanye dijeda otomatis. Harap hubungi Tim Kalamedia untuk perpanjangan / top-up saldo iklan Anda.</span>
                  </div>
                ` : (t.is_warning ? `
                  <div style="background: #FEF6E7; border: 1.5px solid #FEDF89; border-radius: 8px; padding: 10px 14px; margin-bottom: 12px; color: #B54708; font-weight: 700; font-size: 12.5px; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 18px;">⚠️</span>
                    <span><b>SALDO IKLAN HAMPIR HABIS (&le; 20%)!</b> Saldo iklan kampanye Anda menipis. Anda dapat meminta penambahan saldo ads ke tim Kalamedia.</span>
                  </div>
                ` : '')}

                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 12px;">
                  <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <span class="badge" style="background: #F4F3FF; color: #5925DC; font-weight: 800; font-size: 11px;">
                      ${escapeHtml(t.ads_platform || 'Meta Ads')}
                    </span>

                    <!-- Indikator 4: Status Tayang Iklan Jalan / Tidak -->
                    ${t.campaign_run_status === 'running' ? `
                      <span style="background: #E8F7F0; color: #30A46C; border: 1px solid #A6F4C5; font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 6px;">
                        <span style="width: 7px; height: 7px; border-radius: 50%; background: #30A46C; box-shadow: 0 0 6px #30A46C; display: inline-block;"></span>
                        Iklan Jalan (Running)
                      </span>
                    ` : (t.campaign_run_status === 'paused' ? `
                      <span style="background: #FEF6E7; color: #F5A524; border: 1px solid #FEDF89; font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 6px;">
                        <span style="width: 7px; height: 7px; border-radius: 50%; background: #F5A524; display: inline-block;"></span>
                        Iklan Dijeda (Paused)
                      </span>
                    ` : `
                      <span style="background: #FFEFEF; color: #E5484D; border: 1px solid #FECDCA; font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 6px;">
                        <span style="width: 7px; height: 7px; border-radius: 50%; background: #E5484D; display: inline-block;"></span>
                        Iklan Berhenti (Stopped)
                      </span>
                    `)}

                    <!-- Indikator 5: Status Saldo Masih Ada / Habis -->
                    ${t.balance_health_status === 'safe' ? `
                      <span class="badge" style="background: #E8F7F0; color: #30A46C; font-weight: 800; font-size: 11px; padding: 3px 8px; border-radius: 6px;">
                        Saldo Masih Tersedia (Aman)
                      </span>
                    ` : (t.balance_health_status === 'warning' ? `
                      <span class="badge" style="background: #FEF6E7; color: #F5A524; font-weight: 800; font-size: 11px; padding: 3px 8px; border-radius: 6px;">
                        Saldo Hampir Habis
                      </span>
                    ` : `
                      <span class="badge" style="background: #FFEFEF; color: #E5484D; font-weight: 800; font-size: 11px; padding: 3px 8px; border-radius: 6px;">
                        SALDO HABIS (Rp 0)
                      </span>
                    `)}
                  </div>

                  <div style="display: flex; gap: 6px; align-items: center;">
                    ${t.transfer_proof_url ? `
                      <button type="button" class="btn btn-secondary btn-sm" onclick="openUniversalTransferProofModal('${escapeHtml(t.transfer_proof_url)}', 'Bukti Transfer Saldo Iklan Klien', '${escapeHtml(t.title)}')" style="font-size: 11.5px; font-weight: 700; gap: 4px; display: inline-flex; align-items: center;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        Lihat Bukti Transfer
                      </button>
                    ` : ''}

                    ${t.ads_spent_proof_url ? `
                      <a href="${escapeHtml(t.ads_spent_proof_url)}" target="_blank" class="btn btn-secondary btn-sm" style="font-size: 11.5px; font-weight: 700;">
                        Lihat Dashboard Ads
                      </a>
                    ` : ''}
                  </div>
                </div>

                <!-- 3 Key Metric Columns: Budget, Spent, Remaining -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; background: #FFFFFF; border: 1px solid #EAECF0; border-radius: 8px; padding: 12px; margin-bottom: 10px; text-align: center;">
                  <div>
                    <span style="font-size: 10.5px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; display: block;">1. Total Budget Saldo Ads</span>
                    <span style="font-size: 14px; font-weight: 800; color: #101828;">${t.assigned_ads_budget_formatted}</span>
                  </div>
                  <div style="border-left: 1px solid #EAECF0; border-right: 1px solid #EAECF0;">
                    <span style="font-size: 10.5px; color: #B42318; font-weight: 600; text-transform: uppercase; display: block;">2. Sudah Dibelanjakan</span>
                    <span style="font-size: 14px; font-weight: 800; color: #B42318;">${t.ads_spent_amount_formatted} (${t.spent_percent}%)</span>
                  </div>
                  <div>
                    <span style="font-size: 10.5px; color: #027A48; font-weight: 600; text-transform: uppercase; display: block;">3. Sisa Saldo Ads</span>
                    <span style="font-size: 14px; font-weight: 800; color: #027A48;">${t.remaining_ads_balance_formatted}</span>
                  </div>
                </div>

                <!-- Real-time Progress Bar -->
                <div style="height: 8px; background: #E5E7EB; border-radius: 999px; overflow: hidden; margin-bottom: 6px;">
                  <div style="height: 100%; width: ${Math.min(100, t.spent_percent)}%; background: ${t.spent_percent >= 100 ? '#E5484D' : (t.spent_percent >= 80 ? '#F5A524' : '#30A46C')}; border-radius: 999px; transition: width 0.4s ease;"></div>
                </div>

                ${t.ads_spent_notes ? `
                  <div style="font-size: 12px; color: #475467; font-style: italic; margin-top: 6px;">
                    Catatan Tim Ads: "${escapeHtml(t.ads_spent_notes)}"
                  </div>
                ` : ''}
              </div>
            ` : ''}

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

        if (!data.success) {
          container.innerHTML = `
            <div style="text-align: center; padding: 32px; color: #B42318;">
              ${escapeHtml(data.message || 'Gagal memuat transparansi iklan.')}
            </div>
          `;
          return;
        }

        // 1. Update 4 KPI Cards Dynamically
        if (data.summary) {
          const s = data.summary;
          const elPagu = document.getElementById('kpi-client-pagu');
          if (elPagu) elPagu.innerText = 'Rp ' + Number(s.total_pagu_ads_klien || 0).toLocaleString('id-ID');

          const elTrans = document.getElementById('kpi-client-transferred');
          if (elTrans) elTrans.innerText = 'Rp ' + Number(s.total_ditransfer_ke_tim_ads || 0).toLocaleString('id-ID');

          const elTopup = document.getElementById('kpi-client-topup-platform');
          if (elTopup) elTopup.innerText = 'Rp ' + Number(s.total_topup_ke_platform || 0).toLocaleString('id-ID');

          const elSpendMeta = document.getElementById('kpi-client-spend-meta');
          if (elSpendMeta) {
            const isRun = (s.campaign_status === 'running' || s.sisa_saldo_di_tim_ads > 0);
            elSpendMeta.innerHTML = `Spend: <b>Rp ${Number(s.total_spend_terpakai || 0).toLocaleString('id-ID')}</b> &bull; ${isRun ? 'Iklan Jalan' : 'Dijeda'}`;
          }

          const elRem = document.getElementById('kpi-client-remaining');
          if (elRem) {
            const remVal = Number(s.sisa_pagu_di_owner || 0);
            elRem.innerText = 'Rp ' + remVal.toLocaleString('id-ID');
            elRem.style.color = remVal <= 0 ? '#B42318' : '#059669';
          }

          const elRemMeta = document.getElementById('kpi-client-remaining-meta');
          if (elRemMeta) {
            elRemMeta.innerText = `Di Owner: Rp ${Number(s.sisa_pagu_di_owner || 0).toLocaleString('id-ID')} | Di Iklan: Rp ${Number(s.sisa_saldo_di_tim_ads || 0).toLocaleString('id-ID')}`;
          }
        }

        // 2. Render Pending Limit Request Banner if exists
        let bannerHtml = '';
        if (data.pending_limit_request) {
          const req = data.pending_limit_request;
          bannerHtml = `
            <div id="client-banner-limit-request" style="background: linear-gradient(135deg, #FEF0C7 0%, #FEDF89 100%); border: 1.5px solid #FDB022; border-radius: 14px; padding: 18px 20px; margin-bottom: 22px; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.18); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
              <div style="display: flex; align-items: flex-start; gap: 14px; max-width: 760px;">
                <span style="font-size: 26px; line-height: 1;">🔔</span>
                <div>
                  <div style="font-size: 14.5px; font-weight: 800; color: #7A2E0E;">
                    Pengajuan Penambahan Limit Saldo Iklan dari Owner Kalamedia
                  </div>
                  <p style="font-size: 13px; color: #93370D; margin: 4px 0 0; line-height: 1.5;">
                    Owner mengajukan penambahan limit saldo iklan sebesar <b>Rp ${Number(req.amount_requested || 0).toLocaleString('id-ID')}</b> untuk proyek <b>${escapeHtml(req.project_name || 'Kampanye Iklan')}</b> agar penayangan iklan di platform tidak terhenti.
                    ${req.notes ? `<br><i>Catatan Owner: "${escapeHtml(req.notes)}"</i>` : ''}
                  </p>
                </div>
              </div>
              <button type="button" class="btn btn-primary" onclick="openClientApproveLimitModal(${req.project_id}, ${req.id}, ${req.amount_requested || 0}, '${escapeHtml(req.notes || '')}')" style="background: #027A48; border-color: #027A48; font-weight: 800; font-size: 12.5px; padding: 10px 18px; border-radius: 10px; box-shadow: 0 2px 6px rgba(2,122,72,0.3); display: inline-flex; align-items: center; gap: 6px;">
                <span>✅ Setuju Tambah Limit &amp; Upload Bukti Transfer</span>
              </button>
            </div>
          `;
        }

        // 3. Render 2-Stage Verification Table & Projects Summary
        const topups = data.topup_transactions || [];
        let tableHtml = '';

        if (topups.length === 0) {
          tableHtml = `
            <div style="text-align: center; padding: 40px; color: var(--text-muted); background: #F9FAFB; border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
              <div style="display: flex; justify-content: center; margin-bottom: 12px;">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
              </div>
              <h4 style="font-size: 15px; font-weight: 700; color: #101828; margin-bottom: 4px;">Belum Ada Transaksi Saldo Iklan</h4>
              <p style="font-size: 13px; margin: 0;">Bukti transfer saldo dan pengisian ke Meta/Google Ads dari agensi akan otomatis tampil transparan di sini.</p>
            </div>
          `;
        } else {
          tableHtml = `
            <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 20px; box-shadow: 0 1px 3px rgba(16,24,40,0.05);">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
                <div>
                  <h4 style="font-size: 16px; font-weight: 800; color: #101828; margin: 0 0 2px;">
                    Alur Perjalanan &amp; Bukti Transaksi Dana Iklan (2 Tahap Verifikasi)
                  </h4>
                  <span style="font-size: 12.5px; color: var(--text-secondary);">
                    Transparansi penuh mulai dari pencairan Owner ke Tim Ads hingga penyetoran ke akun platform iklan.
                  </span>
                </div>
                <span class="badge" style="background: #ECFDF3; color: #027A48; font-weight: 700;">
                  ${topups.length} Transaksi Terverifikasi
                </span>
              </div>

              <div class="kala-table-scroll table-responsive" style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; display:block;">
                <table class="table-custom" style="min-width: 950px; width: 100%;">
                  <thead>
                    <tr>
                      <th style="min-width: 190px; white-space: nowrap;">TANGGAL &amp; PETUGAS ADS</th>
                      <th style="white-space: nowrap;">PROYEK &amp; PLATFORM</th>
                      <th style="white-space: nowrap;">TAHAP 1: OWNER &rarr; TIM ADS</th>
                      <th style="white-space: nowrap;">TAHAP 2: TIM ADS &rarr; META/GOOGLE</th>
                      <th style="white-space: nowrap;">REALISASI SPEND &amp; SISA</th>
                      <th style="white-space: nowrap;">STATUS KAMPANYE</th>
                    </tr>
                  </thead>
                  <tbody>
          `;

          topups.forEach(t => {
            const pBg = (t.platform || '').toLowerCase().includes('google')
              ? '#EFF8FF'
              : ((t.platform || '').toLowerCase().includes('tiktok') ? '#F8F9FC' : '#F4F3FF');
            const pCol = (t.platform || '').toLowerCase().includes('google')
              ? '#175CD3'
              : ((t.platform || '').toLowerCase().includes('tiktok') ? '#0C111D' : '#5925DC');

            const tfOwnerAmount = Number(t.transfer_amount_owner || t.topup_amount || 0);
            const tfOwnerProof = t.transfer_proof_owner || t.receipt_file || '';
            const topupPlatformAmount = Number(t.platform_topup_amount || t.topup_amount || 0);
            const topupPlatformProof = t.platform_topup_proof || t.receipt_file || '';
            const topupPlatformStatus = t.platform_topup_status || 'completed';
            const spendAktual = Number(t.ad_spend_amount || t.spend_installed || 0);
            const sisaDiTim = Math.max(0, tfOwnerAmount - spendAktual);
            const campStatus = t.campaign_status || 'running';

            tableHtml += `
              <tr>
                <td style="min-width: 190px; white-space: nowrap;">
                  <div style="font-weight: 700; color: #101828;">${formatDate(t.transaction_date)}</div>
                  <div style="font-size: 11px; color: var(--text-secondary);">Petugas: <b>${escapeHtml(t.assigned_user_name || t.uploader_name || 'Tim Ads')}</b></div>
                </td>
                <td style="white-space: nowrap;">
                  <div style="font-weight: 700; color: #101828;">${escapeHtml(t.project_name || 'Kampanye Iklan')}</div>
                  <div style="display: flex; gap: 4px; align-items: center; margin-top: 2px;">
                    <span class="badge" style="background: ${pBg}; color: ${pCol}; font-weight: 700; font-size: 10px;">${escapeHtml(t.platform)}</span>
                    <span style="font-size: 11px; color: var(--text-muted);">&bull; ${escapeHtml(t.stage_label || 'Tahap 1')}</span>
                  </div>
                </td>
                <td style="white-space: nowrap;">
                  <div style="font-weight: 800; color: #175CD3; font-size: 13.5px;">
                    Rp ${tfOwnerAmount.toLocaleString('id-ID')}
                  </div>
                  ${tfOwnerProof ? `
                    <button type="button" class="btn btn-secondary btn-sm" onclick="previewTransactionReceipt('${escapeHtml(tfOwnerProof)}', 'Bukti Transfer Owner ke Tim Ads - ${escapeHtml(t.project_name || '')}')" style="font-size: 10.5px; font-weight: 700; padding: 3px 8px; margin-top: 4px; display: inline-flex; align-items: center; gap: 4px; color: #175CD3; border-color: #B2CCFF; background: #EFF8FF;">
                      📄 Bukti TF Owner
                    </button>
                  ` : '<span style="font-size: 10.5px; color: var(--text-muted); font-style: italic; display: block; margin-top: 2px;">Belum ada lampiran</span>'}
                </td>
                <td style="white-space: nowrap;">
                  <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="font-weight: 800; color: #027A48; font-size: 13.5px;">
                      Rp ${topupPlatformAmount.toLocaleString('id-ID')}
                    </span>
                    ${topupPlatformStatus === 'completed' ? `
                      <span class="badge" style="background: #ECFDF3; color: #027A48; font-weight: 700; font-size: 9.5px;">✓ Top-Up Selesai</span>
                    ` : `
                      <span class="badge" style="background: #EFF8FF; color: #175CD3; font-weight: 700; font-size: 9.5px;">Menunggu Top-Up</span>
                    `}
                  </div>
                  ${topupPlatformProof ? `
                    <button type="button" class="btn btn-secondary btn-sm" onclick="previewTransactionReceipt('${escapeHtml(topupPlatformProof)}', 'Bukti Top-Up Platform ${escapeHtml(t.platform)} - ${escapeHtml(t.project_name || '')}')" style="font-size: 10.5px; font-weight: 700; padding: 3px 8px; margin-top: 4px; display: inline-flex; align-items: center; gap: 4px; color: #027A48; border-color: #A6F4C5; background: #ECFDF3;">
                      📈 Bukti Top-Up Ads
                    </button>
                  ` : '<span style="font-size: 10.5px; color: var(--text-muted); font-style: italic; display: block; margin-top: 2px;">Menunggu tim Ads</span>'}
                </td>
                <td style="white-space: nowrap;">
                  <div style="font-size: 11.5px; color: #475467;">
                    Spend: <b style="color: #101828;">Rp ${spendAktual.toLocaleString('id-ID')}</b>
                  </div>
                  <div style="font-size: 11.5px; color: ${sisaDiTim <= 0 ? '#B42318' : '#027A48'}; font-weight: 700; margin-top: 2px;">
                    Sisa Saldo: Rp ${sisaDiTim.toLocaleString('id-ID')}
                  </div>
                </td>
                <td style="white-space: nowrap;">
                  ${(campStatus === 'running' || sisaDiTim > 0) ? `
                    <span class="badge" style="background: #ECFDF3; color: #027A48; font-weight: 700; font-size: 11px;">● Iklan Jalan</span>
                  ` : (campStatus === 'paused' ? `
                    <span class="badge" style="background: #FEF0C7; color: #B54708; font-weight: 700; font-size: 11px;">❚❚ Dijeda</span>
                  ` : `
                    <span class="badge" style="background: #FEF3F2; color: #B42318; font-weight: 700; font-size: 11px;">✕ Saldo Habis</span>
                  `)}
                </td>
              </tr>
            `;
          });

          tableHtml += `
                  </tbody>
                </table>
              </div>
            </div>
          `;
        }

        container.innerHTML = bannerHtml + tableHtml;
      } catch (err) {
        console.error(err);
        container.innerHTML = `
          <div style="text-align: center; padding: 32px; color: #B42318;">
            Gagal memuat transparansi iklan. Silakan coba kembali nanti.
          </div>
        `;
      }
    }

    function openClientApproveLimitModal(projectId, requestId = 0, amountRequested = 0, notes = '') {
      document.getElementById('client-limit-project-id').value = projectId || '';
      document.getElementById('client-limit-request-id').value = requestId || 0;
      
      const reqEl = document.getElementById('client-limit-requested-amount');
      if (reqEl) reqEl.innerText = 'Rp ' + Number(amountRequested || 0).toLocaleString('id-ID');

      const notesEl = document.getElementById('client-limit-notes-text');
      if (notesEl) {
        notesEl.innerText = notes ? `Alasan Owner: "${notes}"` : 'Pengajuan saldo untuk kelancaran kampanye.';
      }

      const amountInput = document.getElementById('client-limit-amount-input');
      if (amountInput && amountRequested > 0) {
        amountInput.value = Number(amountRequested).toLocaleString('id-ID');
      }

      openModal('modal-client-approve-limit');
    }

    async function handleClientApproveLimit(e) {
      e.preventDefault();
      const form = document.getElementById('form-client-approve-limit');
      const submitBtn = document.getElementById('btn-submit-client-limit');
      const origText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Memproses Persetujuan...';

      const formData = new FormData(form);

      try {
        const res = await fetch('api/ads_tracker.php?action=approve_client_limit', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          closeModal('modal-client-approve-limit');
          form.reset();
          loadClientAdsData();
          // Hide top banner if exists
          const banner = document.getElementById('client-banner-limit-request');
          if (banner) banner.style.display = 'none';
        } else {
          showToast(data.message || 'Gagal memproses persetujuan', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origText;
      }
    }

    function formatRupiahInput(input) {
      let val = input.value.replace(/[^0-9]/g, '');
      if (val) {
        input.value = Number(val).toLocaleString('id-ID');
      } else {
        input.value = '';
      }
    }

    function previewTransactionReceipt(url, title = 'Bukti Transaksi Saldo Iklan') {
      const modal = document.getElementById('modal-view-receipt');
      if (!modal) return;

      const img = document.getElementById('view-receipt-img');
      const iframe = document.getElementById('view-receipt-iframe');
      const titleEl = document.getElementById('view-receipt-title');
      const downloadLink = document.getElementById('view-receipt-download');

      if (titleEl) titleEl.innerText = title;
      if (downloadLink) downloadLink.href = url;

      if (url.toLowerCase().endsWith('.pdf')) {
        if (img) img.style.display = 'none';
        if (iframe) {
          iframe.src = url;
          iframe.style.display = 'block';
        }
      } else {
        if (iframe) {
          iframe.style.display = 'none';
          iframe.src = '';
        }
        if (img) {
          img.src = url;
          img.style.display = 'inline-block';
        }
      }
      openModal('modal-view-receipt');
    }
    window.previewTransactionReceipt = previewTransactionReceipt;

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

  <!-- MODAL: Klien Setujui Tambah Limit Pagu Saldo Ads -->
  <div class="modal" id="modal-client-approve-limit">
    <div class="modal-dialog" style="max-width: 540px;">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h4 class="modal-title">Persetujuan Tambah Limit Saldo Iklan</h4>
            <span style="font-size: 12px; color: var(--text-secondary);">Tambahkan limit pagu saldo ads &amp; upload bukti transfer pembayaran ke agensi</span>
          </div>
          <button type="button" class="close-btn" onclick="closeModal('modal-client-approve-limit')">&times;</button>
        </div>
        <form id="form-client-approve-limit" onsubmit="handleClientApproveLimit(event)" enctype="multipart/form-data">
          <input type="hidden" name="project_id" id="client-limit-project-id" value="<?= $project['id'] ?? 0 ?>">
          <input type="hidden" name="request_id" id="client-limit-request-id" value="0">
          
          <div class="modal-body">
            <div id="client-limit-info-box" style="background: #F4F3FF; border: 1px solid #D9D6FE; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px;">
              <div style="font-size: 11.5px; color: #5925DC; font-weight: 700; text-transform: uppercase;">Pengajuan dari Owner</div>
              <div style="font-size: 18px; font-weight: 800; color: #101828; margin-top: 2px;" id="client-limit-requested-amount">Rp 0</div>
              <div style="font-size: 12px; color: #475467; margin-top: 4px;" id="client-limit-notes-text"></div>
            </div>

            <div class="form-group">
              <label class="form-label">Nominal Tambah Saldo (Rp) <span style="color:red;">*</span></label>
              <input type="text" name="amount_added" id="client-limit-amount-input" class="form-control" placeholder="Contoh: 1.000.000" required oninput="formatRupiahInput(this)">
            </div>

            <div class="form-group">
              <label class="form-label">Upload Bukti Transfer Bank (JPG / PNG / PDF) <span style="color:red;">*</span></label>
              <input type="file" name="client_proof_file" class="form-control" accept="image/*,.pdf" required>
              <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                Lampirkan struk transfer dana penambahan saldo iklan ke rekening Kalamedia.
              </small>
            </div>

            <div class="form-group">
              <label class="form-label">Catatan Tambahan (Opsional)</label>
              <textarea name="notes" class="form-control" rows="2" placeholder="Contoh: Dana penambahan saldo ads untuk kampanye promo bulan ini"></textarea>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-client-approve-limit')">Batal</button>
            <button type="submit" class="btn btn-primary" id="btn-submit-client-limit" style="background: #027A48; border-color: #027A48; font-weight: 700;">
              Konfirmasi &amp; Tambah Pagu Saldo
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php require_once BASE_PATH . '/includes/bottom_nav.php'; ?>
  <?php require_once BASE_PATH . '/includes/modals.php'; ?>
  <script src="assets/js/app.js?v=<?= time() ?>"></script>
  <script src="assets/js/invoice.js?v=<?= time() ?>"></script>
</body>
</html>

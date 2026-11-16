<?php
/**
 * Portal Kala - Mobile Bottom Navigation & Install App Banner (PWA)
 * Multi-Role Aware: Owner, Admin, Karyawan, Freelancer, Client
 */

if (!function_exists('current_user_role')) {
    return;
}

$currentUser = current_user();
$role = current_user_role();
$currentPage = $GLOBALS['currentPage'] ?? $_GET['page'] ?? '';
$isClient = is_client();
$isWorker = ($role === 'karyawan' || $role === 'freelancer');
$isOwner = is_owner();
$isAdmin = is_admin();
?>

<!-- PWA Mobile Install Banner Modal / Snack Notification -->
<div id="pwa-mobile-install-banner" class="pwa-install-banner" style="display: none;">
  <div class="pwa-banner-card">
    <button type="button" class="pwa-banner-close" onclick="dismissPwaBanner()" aria-label="Tutup Banner">&times;</button>
    <div class="pwa-banner-content">
      <img src="assets/img/icon-192.png" alt="Portal Kala Icon" class="pwa-banner-icon">
      <div class="pwa-banner-text">
        <h4 class="pwa-banner-title">Pasang Aplikasi Portal Kala</h4>
        <p class="pwa-banner-desc">Akses cepat, hemat kuota &amp; notifikasi instan langsung dari layar HP Anda.</p>
      </div>
    </div>
    <div class="pwa-banner-actions">
      <button type="button" class="btn-pwa-dismiss" onclick="dismissPwaBanner()">Nanti Saja</button>
      <button type="button" id="btn-pwa-install-banner" class="btn-pwa-install-action" onclick="triggerPwaInstall()">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
          <polyline points="7 10 12 15 17 10"></polyline>
          <line x1="12" y1="15" x2="12" y2="3"></line>
        </svg>
        <span>Instal Sekarang</span>
      </button>
    </div>
  </div>
</div>

<!-- iOS Safari Manual Install Modal Instruction (If opened in iPhone) -->
<div id="pwa-ios-instructions-modal" class="pwa-ios-modal" style="display: none;">
  <div class="pwa-ios-card">
    <div class="pwa-ios-header">
      <div style="display: flex; align-items: center; gap: 8px;">
        <img src="assets/img/icon-192.png" alt="Portal Kala" style="width: 28px; height: 28px; border-radius: 6px;">
        <span style="font-weight: 700; font-size: 14px; color: #111827;">Instal di iPhone (iOS)</span>
      </div>
      <button type="button" class="pwa-banner-close" onclick="closeIosInstallModal()">&times;</button>
    </div>
    <div class="pwa-ios-body">
      <p style="font-size: 12.5px; color: #4B5563; margin-bottom: 12px; line-height: 1.4;">
        Untuk memasang <b>Portal Kala</b> di layar utama iPhone:
      </p>
      <ol style="font-size: 12.5px; color: #111827; padding-left: 18px; margin: 0; line-height: 1.6;">
        <li>Ketuk ikon <b>Bagikan (Share)</b> <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2" style="vertical-align: middle;"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path><polyline points="16 6 12 2 8 6"></polyline><line x1="12" y1="2" x2="12" y2="15"></line></svg> di bar bawah Safari.</li>
        <li>Gulir ke bawah lalu pilih <b>"Tambah ke Layar Utama" (Add to Home Screen)</b>.</li>
        <li>Ketuk <b>Tambah</b> di pojok kanan atas. Selesai!</li>
      </ol>
    </div>
    <div style="margin-top: 14px; text-align: right;">
      <button type="button" class="btn btn-primary btn-sm" onclick="closeIosInstallModal()" style="font-weight: 600; font-size: 12px; border-radius: 8px;">Mengerti</button>
    </div>
  </div>
</div>

<!-- Mobile Bottom Navigation Bar (Active on Mobile Screen <= 768px) -->
<nav class="mobile-bottom-nav <?= $isWorker ? 'worker-slide2-nav' : ($isOwner ? 'owner-bottom-nav' : '') ?>" id="mobile-bottom-nav" aria-label="Navigasi Bawah Mobile">
  <div class="bottom-nav-sliding-pill" id="bottom-nav-sliding-pill" aria-hidden="true"></div>
  <?php if ($isClient): 
    $clientProfileId = (int)($currentUser['client_id'] ?? current_user_client_id());
    $clientHasSosmed = $clientProfileId > 0 ? client_has_sosmed_service($clientProfileId) : true;
  ?>
    <!-- CLIENT PORTAL BOTTOM NAV -->
    <a href="<?= url('client-portal?tab=deliverables&portal=client&_slot=client') ?>" class="mob-nav-item <?= ($currentPage === 'client-portal' && (!isset($_GET['tab']) || $_GET['tab'] === 'deliverables')) ? 'active' : '' ?>">
      <div class="mob-nav-icon nav-icon-wrap">
        <?= renderItshoverIcon('deliverables', 22) ?>
      </div>
      <span class="mob-nav-label">Deliverable</span>
    </a>

    <?php if ($clientHasSosmed): ?>
      <a href="<?= url('client-portal?tab=calendar&portal=client&_slot=client') ?>" class="mob-nav-item <?= ($currentPage === 'client-portal' && ($_GET['tab'] ?? '') === 'calendar') || in_array($currentPage, ['content-calendar', 'content-planner']) ? 'active' : '' ?>">
        <div class="mob-nav-icon nav-icon-wrap">
          <?= renderItshoverIcon('calendar', 22) ?>
        </div>
        <span class="mob-nav-label">Kalender</span>
      </a>
    <?php endif; ?>

    <a href="<?= url('client-portal?tab=invoices&portal=client&_slot=client') ?>" class="mob-nav-item <?= ($currentPage === 'client-portal' && ($_GET['tab'] ?? '') === 'invoices') || in_array($currentPage, ['invoices', 'invoice-view']) ? 'active' : '' ?>">
      <div class="mob-nav-icon nav-icon-wrap">
        <?= renderItshoverIcon('invoices', 22) ?>
      </div>
      <span class="mob-nav-label">Tagihan</span>
    </a>

    <a href="<?= url('client-portal?tab=reports&portal=client&_slot=client') ?>" class="mob-nav-item <?= ($currentPage === 'client-portal' && in_array(($_GET['tab'] ?? ''), ['reports', 'ads'])) || in_array($currentPage, ['reports', 'report-deck']) ? 'active' : '' ?>">
      <div class="mob-nav-icon nav-icon-wrap">
        <?= renderItshoverIcon('reports', 22) ?>
      </div>
      <span class="mob-nav-label">Laporan</span>
    </a>

    <button type="button" class="mob-nav-item" onclick="toggleMobileSidebar()" aria-label="Buka Menu Lainnya">
      <div class="mob-nav-icon nav-icon-wrap">
        <?= renderItshoverIcon('menu', 22) ?>
      </div>
      <span class="mob-nav-label">Menu</span>
    </button>

  <?php elseif ($isWorker): 
    $isAdsSpecialistUser = is_ads_specialist($currentUser);
    $workerHasSosmed = worker_has_sosmed_projects((int)($currentUser['id'] ?? 0));
    $workerCurrentTab = $_GET['tab'] ?? '';
  ?>
    <!-- TIM ADS / FREELANCER / KARYAWAN BOTTOM NAV (SLIDE 2 DARK PILL STYLE) -->
    <a href="<?= url('tasks?_slot=internal') ?>" class="mob-nav-item <?= in_array($currentPage, ['tasks', 'team-workspace']) && (empty($workerCurrentTab) || $workerCurrentTab === 'tasks') ? 'active' : '' ?>" id="mob-nav-tasks-item" onclick="handleWorkerBottomNavTasks(event)">
      <div class="mob-nav-icon nav-icon-wrap">
        <?= renderItshoverIcon('tasks', 22) ?>
      </div>
      <span class="mob-nav-label">Tugas Saya</span>
    </a>

    <?php if ($isAdsSpecialistUser): ?>
      <!-- ORANG ADS: Saldo Ads + Report Ads -->
      <a href="<?= url('tasks?tab=ads_tracker&_slot=internal') ?>" class="mob-nav-item <?= ($currentPage === 'tasks' && in_array($workerCurrentTab, ['ads', 'ads_tracker'])) ? 'active' : '' ?>" id="mob-nav-ads-item" onclick="handleWorkerBottomNavAds(event)">
        <div class="mob-nav-icon nav-icon-wrap">
          <?= renderItshoverIcon('ads', 22) ?>
        </div>
        <span class="mob-nav-label">Saldo Ads</span>
      </a>

      <a href="<?= url('reports?tab=ads_report&_slot=internal') ?>" class="mob-nav-item <?= in_array($currentPage, ['reports', 'reports-form', 'report-view', 'report-deck']) ? 'active' : '' ?>">
        <div class="mob-nav-icon nav-icon-wrap">
          <?= renderItshoverIcon('reports', 22) ?>
        </div>
        <span class="mob-nav-label">Report Ads</span>
      </a>
    <?php else: ?>
      <!-- TIM KREATIF: Rekap Kerja Bulanan / Per Proyek + Kalender (Jika ada Sosmed) -->
      <a href="<?= url('tasks?tab=recap&_slot=internal') ?>" class="mob-nav-item <?= ($currentPage === 'tasks' && $workerCurrentTab === 'recap') ? 'active' : '' ?>" id="mob-nav-recap-item" onclick="handleWorkerBottomNavRecap(event)">
        <div class="mob-nav-icon nav-icon-wrap">
          <?= renderItshoverIcon('invoices', 22) ?>
        </div>
        <span class="mob-nav-label">Rekap Kerja</span>
      </a>

      <?php if ($workerHasSosmed): ?>
        <a href="<?= url('content-calendar?_slot=internal') ?>" class="mob-nav-item <?= in_array($currentPage, ['content-calendar', 'content-planner']) ? 'active' : '' ?>">
          <div class="mob-nav-icon nav-icon-wrap">
            <?= renderItshoverIcon('calendar', 22) ?>
          </div>
          <span class="mob-nav-label">Kalender</span>
        </a>
      <?php endif; ?>
    <?php endif; ?>

    <!-- ITEM 4: PROFILE KARYAWAN (Menggantikan Menu) -->
    <a href="<?= url('tasks?tab=profile&_slot=internal') ?>" class="mob-nav-item <?= ($currentPage === 'tasks' && $workerCurrentTab === 'profile') ? 'active' : '' ?>" id="mob-nav-profile-item" onclick="handleWorkerBottomNavProfile(event)" aria-label="Buka Profile Karyawan">
      <div class="mob-nav-icon nav-icon-wrap">
        <?= renderItshoverIcon('profile', 22) ?>
      </div>
      <span class="mob-nav-label">Profile</span>
    </a>

  <?php elseif ($isOwner): ?>
    <!-- OWNER BOTTOM NAV: 4 TAB PILAR UTAMA + 1 TOMBOL SEMUA MENU (LAUNCHER) -->
    <!-- Tab 1: Eksekutif -->
    <a href="<?= url('owner-dashboard?_slot=internal') ?>" class="mob-nav-item <?= in_array($currentPage, ['owner-dashboard', 'dashboard']) ? 'active' : '' ?>" title="Executive Dashboard">
      <div class="mob-nav-icon nav-icon-wrap">
        <?= renderItshoverIcon('dashboard', 22) ?>
      </div>
      <span class="mob-nav-label">Eksekutif</span>
    </a>

    <!-- Tab 2: Operasional -->
    <a href="<?= url('tasks?_slot=internal') ?>" class="mob-nav-item <?= in_array($currentPage, ['tasks', 'team-workspace']) ? 'active' : '' ?>" title="Pembagian Tugas Tim & Saldo Ads">
      <div class="mob-nav-icon nav-icon-wrap">
        <?= renderItshoverIcon('tasks', 22) ?>
      </div>
      <span class="mob-nav-label">Operasional</span>
    </a>

    <!-- Tab 3: Keuangan -->
    <a href="<?= url('invoices?_slot=internal') ?>" class="mob-nav-item <?= in_array($currentPage, ['invoices', 'invoice-view', 'expenses', 'salaries', 'salary-view', 'payroll']) ? 'active' : '' ?>" title="Invoice, Pengeluaran & Penggajian">
      <div class="mob-nav-icon nav-icon-wrap">
        <?= renderItshoverIcon('wallet', 22) ?>
      </div>
      <span class="mob-nav-label">Keuangan</span>
    </a>

    <!-- Tab 4: Klien & Tim -->
    <a href="<?= url('clients?_slot=internal') ?>" class="mob-nav-item <?= in_array($currentPage, ['clients', 'team', 'employees-management']) ? 'active' : '' ?>" title="Database Klien, Proyek & Tim">
      <div class="mob-nav-icon nav-icon-wrap">
        <?= renderItshoverIcon('team', 22) ?>
      </div>
      <span class="mob-nav-label">Klien &amp; Tim</span>
    </a>

    <!-- Tab 5: Semua (Buka Bottom Sheet Launcher) -->
    <button type="button" class="mob-nav-item" onclick="openOwnerBottomSheet()" aria-label="Buka Semua Menu Owner" title="Pusat Seluruh Menu Eksekutif">
      <div class="mob-nav-icon nav-icon-wrap">
        <?= renderItshoverIcon('all-menu', 22) ?>
      </div>
      <span class="mob-nav-label">Semua</span>
    </button>

  <?php else: ?>
    <!-- ADMIN BOTTOM NAV -->
    <a href="<?= url('admin-dashboard?_slot=internal') ?>" class="mob-nav-item <?= in_array($currentPage, ['admin-dashboard']) ? 'active' : '' ?>">
      <div class="mob-nav-icon nav-icon-wrap">
        <?= renderItshoverIcon('dashboard', 22) ?>
      </div>
      <span class="mob-nav-label">Dashboard</span>
    </a>

    <a href="<?= url('tasks?_slot=internal') ?>" class="mob-nav-item <?= in_array($currentPage, ['tasks', 'team-workspace']) ? 'active' : '' ?>">
      <div class="mob-nav-icon nav-icon-wrap">
        <?= renderItshoverIcon('tasks', 22) ?>
      </div>
      <span class="mob-nav-label">Tugas Tim</span>
    </a>

    <a href="<?= url('invoices?_slot=internal') ?>" class="mob-nav-item <?= in_array($currentPage, ['invoices', 'invoice-view']) ? 'active' : '' ?>">
      <div class="mob-nav-icon nav-icon-wrap">
        <?= renderItshoverIcon('invoices', 22) ?>
      </div>
      <span class="mob-nav-label">Invoice</span>
    </a>

    <a href="<?= url('clients?_slot=internal') ?>" class="mob-nav-item <?= $currentPage === 'clients' ? 'active' : '' ?>">
      <div class="mob-nav-icon nav-icon-wrap">
        <?= renderItshoverIcon('team', 22) ?>
      </div>
      <span class="mob-nav-label">Klien</span>
    </a>

    <button type="button" class="mob-nav-item" onclick="toggleMobileSidebar()" aria-label="Buka Menu Lainnya">
      <div class="mob-nav-icon nav-icon-wrap">
        <?= renderItshoverIcon('menu', 22) ?>
      </div>
      <span class="mob-nav-label">Menu</span>
    </button>
  <?php endif; ?>
</nav>

<?php if ($isOwner): ?>
<!-- Dark Blurred Backdrop for Owner Menu Bottom Sheet (iOS Spring Curve) -->
<div id="owner-menu-backdrop" class="owner-sheet-backdrop" onclick="closeOwnerBottomSheet()" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); z-index: 1099; opacity: 0; transition: opacity 0.36s cubic-bezier(0.32, 0.72, 0, 1); will-change: opacity;"></div>

<!-- Slide-Up Bottom Sheet: Pusat Menu Eksekutif Owner (iOS Spring Curve) -->
<div id="owner-menu-bottom-sheet" class="owner-bottom-sheet" style="display: none; position: fixed; left: 0; right: 0; bottom: 0; max-height: 82dvh; background: rgba(255, 255, 255, 0.92); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border-radius: 24px 24px 0 0; box-shadow: 0 -10px 40px rgba(15, 23, 42, 0.18); padding: 18px 16px 36px; z-index: 1100; transform: translate3d(0, 100%, 0); transition: transform 0.4s cubic-bezier(0.32, 0.72, 0, 1); will-change: transform; overflow-y: auto; -webkit-overflow-scrolling: touch; box-sizing: border-box;">
  
  <!-- Drag Handle Bar -->
  <div style="width: 40px; height: 4px; background: #CBD5E1; border-radius: 999px; margin: 0 auto 16px; cursor: pointer;" onclick="closeOwnerBottomSheet()"></div>

  <!-- Header Sheet -->
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; padding: 0 4px;">
    <div>
      <div style="display: flex; align-items: center; gap: 8px;">
        <h3 style="font-size: 16px; font-weight: 700; color: #16141F; margin: 0; letter-spacing: -0.2px;">Pusat Menu Eksekutif Owner</h3>
        <span class="badge-version" style="font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 5px;">
          <?= defined('APP_VERSION') ? APP_VERSION : 'v3.0' ?>
        </span>
      </div>
      <p style="font-size: 12px; color: #6E6D7A; margin: 2px 0 0 0;">Navigasi lengkap 12 menu divisi &amp; kontrol sistem agensi</p>
    </div>
    <button type="button" onclick="closeOwnerBottomSheet()" aria-label="Tutup" style="background: #F1F5F9; border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #64748B; cursor: pointer; transition: all 0.15s ease;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
    </button>
  </div>

  <!-- Bagian 1 — Pintasan Aksi Cepat (Quick Actions 4 Tombol Pill) -->
  <div style="margin-bottom: 20px;">
    <div style="font-size: 11px; font-weight: 700; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; padding-left: 4px;">Pintasan Aksi Cepat</div>
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
      <a href="<?= url('invoices?action=new&_slot=internal') ?>" onclick="handleQuickActionInvoice(event)" style="display: flex; align-items: center; gap: 8px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 9px 12px; text-decoration: none; color: #1E293B; font-size: 12px; font-weight: 600; transition: all 0.15s ease;">
        <span style="display: flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 8px; background: #EEF2FF; color: #5B3DF5; flex-shrink: 0;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        </span>
        <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">+ Buat Invoice</span>
      </a>

      <a href="<?= url('tasks?action=new&_slot=internal') ?>" onclick="handleQuickActionTask(event)" style="display: flex; align-items: center; gap: 8px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 9px 12px; text-decoration: none; color: #1E293B; font-size: 12px; font-weight: 600; transition: all 0.15s ease;">
        <span style="display: flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 8px; background: #EEF2FF; color: #5B3DF5; flex-shrink: 0;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </span>
        <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">+ Bagi Tugas</span>
      </a>

      <a href="<?= url('clients?action=new&_slot=internal') ?>" onclick="handleQuickActionClient(event)" style="display: flex; align-items: center; gap: 8px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 9px 12px; text-decoration: none; color: #1E293B; font-size: 12px; font-weight: 600; transition: all 0.15s ease;">
        <span style="display: flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 8px; background: #EEF2FF; color: #5B3DF5; flex-shrink: 0;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
        </span>
        <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">+ Tambah Klien</span>
      </a>

      <a href="<?= url('team?action=new&_slot=internal') ?>" onclick="handleQuickActionTeam(event)" style="display: flex; align-items: center; gap: 8px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 9px 12px; text-decoration: none; color: #1E293B; font-size: 12px; font-weight: 600; transition: all 0.15s ease;">
        <span style="display: flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 8px; background: #EEF2FF; color: #5B3DF5; flex-shrink: 0;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </span>
        <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">+ Tambah Tim</span>
      </a>
    </div>
  </div>

  <!-- Bagian 2 — Grid Menu Lengkap Owner (Grid 3 Kolom) -->
  <div style="margin-bottom: 22px;">
    <div style="font-size: 11px; font-weight: 700; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; padding-left: 4px;">Seluruh Menu Agensi (12 Menu)</div>
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
      
      <!-- 1. Dashboard Eksekutif -->
      <a href="<?= url('owner-dashboard?_slot=internal') ?>" class="owner-menu-grid-card" style="display: flex; flex-direction: column; align-items: center; justify-content: center; background: #FFFFFF; border: 1px solid #ECEEF5; border-radius: 16px; padding: 14px 6px 12px; text-decoration: none; text-align: center; gap: 8px; transition: all 0.15s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="width: 44px; height: 44px; border-radius: 14px; background: #EEF2FF; color: #5B3DF5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <?= renderItshoverIcon('dashboard', 22) ?>
        </div>
        <span style="font-size: 11px; font-weight: 600; color: #1E293B; line-height: 1.25; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">Dashboard Eksekutif</span>
      </a>

      <!-- 2. Tugas & Saldo Ads -->
      <a href="<?= url('tasks?_slot=internal') ?>" class="owner-menu-grid-card" style="display: flex; flex-direction: column; align-items: center; justify-content: center; background: #FFFFFF; border: 1px solid #ECEEF5; border-radius: 16px; padding: 14px 6px 12px; text-decoration: none; text-align: center; gap: 8px; transition: all 0.15s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="width: 44px; height: 44px; border-radius: 14px; background: #EEF2FF; color: #5B3DF5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <?= renderItshoverIcon('tasks', 22) ?>
        </div>
        <span style="font-size: 11px; font-weight: 600; color: #1E293B; line-height: 1.25; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">Tugas &amp; Saldo Ads</span>
      </a>

      <!-- 3. Tim & Karyawan -->
      <a href="<?= url('team?_slot=internal') ?>" class="owner-menu-grid-card" style="display: flex; flex-direction: column; align-items: center; justify-content: center; background: #FFFFFF; border: 1px solid #ECEEF5; border-radius: 16px; padding: 14px 6px 12px; text-decoration: none; text-align: center; gap: 8px; transition: all 0.15s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="width: 44px; height: 44px; border-radius: 14px; background: #EEF2FF; color: #5B3DF5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <?= renderItshoverIcon('team', 22) ?>
        </div>
        <span style="font-size: 11px; font-weight: 600; color: #1E293B; line-height: 1.25; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">Tim &amp; Karyawan</span>
      </a>

      <!-- 4. Klien & Proyek -->
      <a href="<?= url('clients?_slot=internal') ?>" class="owner-menu-grid-card" style="display: flex; flex-direction: column; align-items: center; justify-content: center; background: #FFFFFF; border: 1px solid #ECEEF5; border-radius: 16px; padding: 14px 6px 12px; text-decoration: none; text-align: center; gap: 8px; transition: all 0.15s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="width: 44px; height: 44px; border-radius: 14px; background: #EEF2FF; color: #5B3DF5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <?= renderItshoverIcon('briefcase', 22) ?>
        </div>
        <span style="font-size: 11px; font-weight: 600; color: #1E293B; line-height: 1.25; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">Klien &amp; Proyek</span>
      </a>

      <!-- 5. Invoice & Tagihan -->
      <a href="<?= url('invoices?_slot=internal') ?>" class="owner-menu-grid-card" style="display: flex; flex-direction: column; align-items: center; justify-content: center; background: #FFFFFF; border: 1px solid #ECEEF5; border-radius: 16px; padding: 14px 6px 12px; text-decoration: none; text-align: center; gap: 8px; transition: all 0.15s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="width: 44px; height: 44px; border-radius: 14px; background: #EEF2FF; color: #5B3DF5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <?= renderItshoverIcon('invoices', 22) ?>
        </div>
        <span style="font-size: 11px; font-weight: 600; color: #1E293B; line-height: 1.25; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">Invoice &amp; Tagihan</span>
      </a>

      <!-- 6. Pengeluaran & Fee -->
      <a href="<?= url('expenses?_slot=internal') ?>" class="owner-menu-grid-card" style="display: flex; flex-direction: column; align-items: center; justify-content: center; background: #FFFFFF; border: 1px solid #ECEEF5; border-radius: 16px; padding: 14px 6px 12px; text-decoration: none; text-align: center; gap: 8px; transition: all 0.15s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="width: 44px; height: 44px; border-radius: 14px; background: #EEF2FF; color: #5B3DF5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <?= renderItshoverIcon('expenses', 22) ?>
        </div>
        <span style="font-size: 11px; font-weight: 600; color: #1E293B; line-height: 1.25; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">Pengeluaran &amp; Fee</span>
      </a>

      <!-- 7. Penggajian & Slip -->
      <a href="<?= url('salaries?_slot=internal') ?>" class="owner-menu-grid-card" style="display: flex; flex-direction: column; align-items: center; justify-content: center; background: #FFFFFF; border: 1px solid #ECEEF5; border-radius: 16px; padding: 14px 6px 12px; text-decoration: none; text-align: center; gap: 8px; transition: all 0.15s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="width: 44px; height: 44px; border-radius: 14px; background: #EEF2FF; color: #5B3DF5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <?= renderItshoverIcon('payroll', 22) ?>
        </div>
        <span style="font-size: 11px; font-weight: 600; color: #1E293B; line-height: 1.25; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">Penggajian &amp; Slip</span>
      </a>

      <!-- 8. Laporan Performa -->
      <a href="<?= url('reports?_slot=internal') ?>" class="owner-menu-grid-card" style="display: flex; flex-direction: column; align-items: center; justify-content: center; background: #FFFFFF; border: 1px solid #ECEEF5; border-radius: 16px; padding: 14px 6px 12px; text-decoration: none; text-align: center; gap: 8px; transition: all 0.15s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="width: 44px; height: 44px; border-radius: 14px; background: #EEF2FF; color: #5B3DF5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <?= renderItshoverIcon('reports', 22) ?>
        </div>
        <span style="font-size: 11px; font-weight: 600; color: #1E293B; line-height: 1.25; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">Laporan Performa</span>
      </a>

      <!-- 9. Kalender Konten -->
      <a href="<?= url('content-calendar?_slot=internal') ?>" class="owner-menu-grid-card" style="display: flex; flex-direction: column; align-items: center; justify-content: center; background: #FFFFFF; border: 1px solid #ECEEF5; border-radius: 16px; padding: 14px 6px 12px; text-decoration: none; text-align: center; gap: 8px; transition: all 0.15s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="width: 44px; height: 44px; border-radius: 14px; background: #EEF2FF; color: #5B3DF5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <?= renderItshoverIcon('calendar', 22) ?>
        </div>
        <span style="font-size: 11px; font-weight: 600; color: #1E293B; line-height: 1.25; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">Kalender Konten</span>
      </a>

      <!-- 10. Portal Klien (Preview) -->
      <a href="<?= url('client-portal?preview=1&portal=internal&_slot=internal') ?>" class="owner-menu-grid-card" style="display: flex; flex-direction: column; align-items: center; justify-content: center; background: #FFFFFF; border: 1px solid #ECEEF5; border-radius: 16px; padding: 14px 6px 12px; text-decoration: none; text-align: center; gap: 8px; transition: all 0.15s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="width: 44px; height: 44px; border-radius: 14px; background: #EEF2FF; color: #5B3DF5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <?= renderItshoverIcon('portal', 22) ?>
        </div>
        <span style="font-size: 11px; font-weight: 600; color: #1E293B; line-height: 1.25; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">Portal Klien (Preview)</span>
      </a>

      <!-- 11. Pengaturan Agensi -->
      <a href="<?= url('settings?_slot=internal') ?>" class="owner-menu-grid-card" style="display: flex; flex-direction: column; align-items: center; justify-content: center; background: #FFFFFF; border: 1px solid #ECEEF5; border-radius: 16px; padding: 14px 6px 12px; text-decoration: none; text-align: center; gap: 8px; transition: all 0.15s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="width: 44px; height: 44px; border-radius: 14px; background: #EEF2FF; color: #5B3DF5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <?= renderItshoverIcon('settings', 22) ?>
        </div>
        <span style="font-size: 11px; font-weight: 600; color: #1E293B; line-height: 1.25; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">Pengaturan Agensi</span>
      </a>

      <!-- 12. Rekap Pekerjaan Tim -->
      <a href="<?= url('tasks?tab=recap&_slot=internal') ?>" class="owner-menu-grid-card" style="display: flex; flex-direction: column; align-items: center; justify-content: center; background: #FFFFFF; border: 1px solid #ECEEF5; border-radius: 16px; padding: 14px 6px 12px; text-decoration: none; text-align: center; gap: 8px; transition: all 0.15s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="width: 44px; height: 44px; border-radius: 14px; background: #EEF2FF; color: #5B3DF5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <?= renderItshoverIcon('tasks', 22) ?>
        </div>
        <span style="font-size: 11px; font-weight: 600; color: #1E293B; line-height: 1.25; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">Rekap Kerja Tim</span>
      </a>

    </div>
  </div>

  <!-- Bagian 3 — Kartu Profil Owner & Tombol Keluar (Logout) -->
  <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 16px; padding: 14px; margin-top: 6px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; gap: 10px;">
      <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
        <div style="width: 38px; height: 38px; border-radius: 50%; background: #EEF2FF; color: #5B3DF5; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; flex-shrink: 0; border: 1px solid #E0E7FF;">
          OW
        </div>
        <div style="min-width: 0;">
          <div style="font-size: 13.5px; font-weight: 700; color: #0F172A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
            <?= htmlspecialchars($currentUser['name'] ?? 'Owner Kalamedia') ?>
          </div>
          <div style="font-size: 11px; color: #64748B; font-weight: 500;">
            PT Kala Kausa Prima • Akun Eksekutif Owner
          </div>
        </div>
      </div>
      <span style="background: #EEF2FF; color: #5B3DF5; font-size: 10.5px; font-weight: 700; padding: 3px 8px; border-radius: 999px; border: 1px solid #E0E7FF; flex-shrink: 0;">
        OWNER
      </span>
    </div>

    <a href="<?= url('logout') ?>" style="display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; background: #FFEFEF; color: #E5484D; font-size: 12.5px; font-weight: 700; padding: 11px 16px; border-radius: 12px; text-decoration: none; border: 1px solid #FFD8D8; box-sizing: border-box; transition: all 0.15s ease;">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
        <polyline points="16 17 21 12 16 7"></polyline>
        <line x1="21" y1="12" x2="9" y2="12"></line>
      </svg>
      <span>Keluar Sesi (Logout)</span>
    </a>
  </div>
</div>
<?php endif; ?>

<script>
// PWA Mobile Install Banner Handling
(function() {
  const isIos = /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase());
  const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

  window.triggerPwaInstall = function() {
    if (window.deferredPwaPrompt) {
      window.deferredPwaPrompt.prompt();
      window.deferredPwaPrompt.userChoice.then(function(choice) {
        if (choice.outcome === 'accepted') {
          dismissPwaBanner();
        }
        window.deferredPwaPrompt = null;
      });
    } else if (isIos) {
      dismissPwaBanner();
      const modal = document.getElementById('pwa-ios-instructions-modal');
      if (modal) modal.style.display = 'flex';
    } else {
      // Fallback instruction
      showToast('Ketuk menu titik tiga (⋮) di browser lalu pilih "Instal Aplikasi" / "Tambahkan ke Layar Utama"', 'info');
      dismissPwaBanner();
    }
  };

  window.dismissPwaBanner = function() {
    const banner = document.getElementById('pwa-mobile-install-banner');
    if (banner) banner.style.display = 'none';
    sessionStorage.setItem('portal_kala_pwa_dismissed', '1');
  };

  window.closeIosInstallModal = function() {
    const modal = document.getElementById('pwa-ios-instructions-modal');
    if (modal) modal.style.display = 'none';
  };

  // Show banner on mobile devices if not already running as standalone PWA
  window.addEventListener('load', function() {
    if (isStandalone) return; // Already installed, do not show
    if (sessionStorage.getItem('portal_kala_pwa_dismissed') === '1') return;

    const isMobile = window.innerWidth <= 768 || /android|iphone|ipad|ipod/i.test(navigator.userAgent);
    if (isMobile) {
      setTimeout(function() {
        const banner = document.getElementById('pwa-mobile-install-banner');
        if (banner) banner.style.display = 'block';
      }, 1800); // Gentle 1.8s delay after page load
    }
  });

  window.addEventListener('appinstalled', function() {
    dismissPwaBanner();
  });

  // Mobile Bottom Nav Sliding Active Pill Manager (60fps Horizontal FLIP)
  window.updateBottomNavPill = function(targetItem, useSpring) {
    const nav = document.getElementById('mobile-bottom-nav');
    const pill = document.getElementById('bottom-nav-sliding-pill');
    if (!nav || !pill) return;
    const activeItem = targetItem || nav.querySelector('.mob-nav-item.active');
    if (activeItem && activeItem.offsetParent !== null) {
      const targetLeft = activeItem.offsetLeft + (activeItem.offsetWidth - 32) / 2;
      const savedLeft = parseFloat(sessionStorage.getItem('kala_bottom_nav_left') || 'NaN');

      if (!targetItem && !isNaN(savedLeft) && Math.abs(savedLeft - targetLeft) > 2) {
        pill.style.transition = 'none';
        pill.style.transform = 'translate3d(' + savedLeft + 'px, 0, 0)';
        pill.style.width = '32px';
        pill.style.opacity = '1';
        void pill.offsetWidth;
        requestAnimationFrame(function() {
          pill.style.transition = 'transform 0.38s cubic-bezier(0.22, 1, 0.36, 1), width 0.3s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.2s ease';
          pill.style.transform = 'translate3d(' + targetLeft + 'px, 0, 0)';
        });
      } else {
        if (useSpring !== false) {
          pill.style.transition = 'transform 0.38s cubic-bezier(0.22, 1, 0.36, 1), width 0.3s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.2s ease';
        }
        pill.style.transform = 'translate3d(' + targetLeft + 'px, 0, 0)';
        pill.style.width = '32px';
        pill.style.opacity = '1';
      }
      sessionStorage.setItem('kala_bottom_nav_left', String(targetLeft));
    } else if (!targetItem) {
      pill.style.opacity = '0';
    }
  };

  document.addEventListener('DOMContentLoaded', function() {
    const nav = document.getElementById('mobile-bottom-nav');
    if (nav) {
      nav.querySelectorAll('a.mob-nav-item').forEach(function(item) {
        item.addEventListener('click', function() {
          const curActive = nav.querySelector('.mob-nav-item.active') || item;
          if (curActive && curActive.offsetParent !== null) {
            const curLeft = curActive.offsetLeft + (curActive.offsetWidth - 32) / 2;
            sessionStorage.setItem('kala_bottom_nav_left', String(curLeft));
          }
          window.updateBottomNavPill(item, true);
        });
      });
    }
    requestAnimationFrame(function() {
      window.updateBottomNavPill(null, true);
    });
  });

  window.addEventListener('resize', function() {
    if (window.updateBottomNavPill) window.updateBottomNavPill(null, false);
  });

  window.addEventListener('load', function() {
    setTimeout(function() { window.updateBottomNavPill(null, true); }, 60);
  });

  // Worker workspace seamless tab switching handlers
  window.handleWorkerBottomNavProfile = function(e) {
    if (typeof switchTaskWorkspaceTab === 'function') {
      if (e) e.preventDefault();
      switchTaskWorkspaceTab('profile');
    }
  };
  window.handleWorkerBottomNavTasks = function(e) {
    if (typeof switchTaskWorkspaceTab === 'function') {
      if (e) e.preventDefault();
      switchTaskWorkspaceTab('tasks');
    }
  };
  window.handleWorkerBottomNavAds = function(e) {
    if (typeof switchTaskWorkspaceTab === 'function') {
      if (e) e.preventDefault();
      switchTaskWorkspaceTab('ads');
    }
  };
  window.handleWorkerBottomNavRecap = function(e) {
    if (typeof switchTaskWorkspaceTab === 'function') {
      if (e) e.preventDefault();
      switchTaskWorkspaceTab('recap');
    }
  };

  // Owner Bottom Sheet Launcher Controls
  window.openOwnerBottomSheet = function() {
    const sheet = document.getElementById('owner-menu-bottom-sheet');
    const backdrop = document.getElementById('owner-menu-backdrop');
    if (!sheet || !backdrop) return;
    backdrop.style.display = 'block';
    sheet.style.display = 'block';
    requestAnimationFrame(function() {
      backdrop.style.opacity = '1';
      sheet.style.transform = 'translate3d(0, 0, 0)';
    });
    document.body.style.overflow = 'hidden';
  };

  window.closeOwnerBottomSheet = function() {
    const sheet = document.getElementById('owner-menu-bottom-sheet');
    const backdrop = document.getElementById('owner-menu-backdrop');
    if (!sheet || !backdrop) return;
    backdrop.style.opacity = '0';
    sheet.style.transform = 'translate3d(0, 100%, 0)';
    setTimeout(function() {
      backdrop.style.display = 'none';
      sheet.style.display = 'none';
      document.body.style.overflow = '';
    }, 280);
  };

  // Quick Action Modal Triggers from Bottom Sheet
  window.handleQuickActionInvoice = function(e) {
    if (typeof openModal === 'function' && document.getElementById('modal-create-invoice')) {
      if (e) e.preventDefault();
      closeOwnerBottomSheet();
      openModal('modal-create-invoice');
    }
  };

  window.handleQuickActionTask = function(e) {
    if (typeof openModal === 'function' && document.getElementById('modal-create-task')) {
      if (e) e.preventDefault();
      closeOwnerBottomSheet();
      openModal('modal-create-task');
    }
  };

  window.handleQuickActionClient = function(e) {
    if (typeof openModal === 'function' && document.getElementById('modal-create-client')) {
      if (e) e.preventDefault();
      closeOwnerBottomSheet();
      openModal('modal-create-client');
    }
  };

  window.handleQuickActionTeam = function(e) {
    if (typeof openModal === 'function' && document.getElementById('modal-create-employee')) {
      if (e) e.preventDefault();
      closeOwnerBottomSheet();
      openModal('modal-create-employee');
    }
  };

  // Close sheet on Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      if (typeof closeOwnerBottomSheet === 'function') {
        closeOwnerBottomSheet();
      }
    }
  });
})();
</script>

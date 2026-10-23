<?php
/**
 * Kalamedia Sidebar Navigation - Bankio Minimal Finance UI Kit
 * Multi-Role Support: Owner, Admin, Karyawan, Freelancer, Client
 */
$currentUser = current_user();
$role = current_user_role();
$isOwner = is_owner();
$isAdmin = is_admin();
$isClient = is_client();
$isWorker = ($role === 'karyawan' || $role === 'freelancer');

$currentPage = $GLOBALS['currentPage'] ?? $_GET['page'] ?? '';
if (empty($currentPage)) {
    $currentPage = home_url_for_role();
}
$homeUrl = home_url_for_role();

$roleLabelMap = [
    'owner' => 'OWNER',
    'admin' => 'ADMIN',
    'karyawan' => 'KARYAWAN',
    'freelancer' => 'FREELANCER',
    'client' => 'CLIENT'
];

$activeName = $currentUser['name'] ?? '';
if ($role === 'owner') {
    $sidebarProfileName = 'Owner';
    $sidebarAvatar = 'OW';
    $sidebarBadge = 'OWNER';
} elseif ($role === 'admin') {
    $sidebarProfileName = 'Admin';
    $sidebarAvatar = 'AD';
    $sidebarBadge = 'ADMIN';
} elseif ($role === 'client') {
    $sidebarProfileName = htmlspecialchars($activeName ?: 'Client');
    $cleanName = trim(preg_replace('/[^a-zA-Z0-9]/', '', $activeName));
    $sidebarAvatar = strtoupper(substr($cleanName ?: 'CL', 0, 2));
    $sidebarBadge = 'CLIENT';
} elseif ($role === 'karyawan') {
    $sidebarProfileName = htmlspecialchars($activeName ?: 'Karyawan');
    $cleanName = trim(preg_replace('/[^a-zA-Z0-9]/', '', $activeName));
    $sidebarAvatar = strtoupper(substr($cleanName ?: 'KA', 0, 2));
    $sidebarBadge = 'KARYAWAN';
} elseif ($role === 'freelancer') {
    $sidebarProfileName = htmlspecialchars($activeName ?: 'Freelancer');
    $cleanName = trim(preg_replace('/[^a-zA-Z0-9]/', '', $activeName));
    $sidebarAvatar = strtoupper(substr($cleanName ?: 'FR', 0, 2));
    $sidebarBadge = 'FREELANCER';
} else {
    $sidebarProfileName = htmlspecialchars($activeName ?: 'User');
    $cleanName = trim(preg_replace('/[^a-zA-Z0-9]/', '', $activeName));
    $sidebarAvatar = strtoupper(substr($cleanName ?: 'US', 0, 2));
    $sidebarBadge = strtoupper($role ?: 'USER');
}
$badgeLabel = $sidebarBadge;
?>

<style>
/* 1. View Transition Otomatis Antar Halaman */
@view-transition {
  navigation: auto;
}
::view-transition-old(root) {
  animation: kalaFadeOut 140ms cubic-bezier(0.4, 0, 1, 1) both;
}
::view-transition-new(root) {
  animation: kalaSlideUpIn 280ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

/* 2. Keyframes Masuk & Keluar Halaman/Tab */
@keyframes kalaFadeOut {
  from { opacity: 1; transform: translate3d(0, 0, 0) scale(1); }
  to   { opacity: 0; transform: translate3d(0, -6px, 0) scale(0.99); }
}

@keyframes kalaSlideUpIn {
  0%   { opacity: 0; transform: translate3d(0, 18px, 0) scale(0.985); }
  100% { opacity: 1; transform: translate3d(0, 0, 0) scale(1); }
}

@keyframes kalaCardCascade {
  0%   { opacity: 0; transform: translate3d(0, 16px, 0); }
  100% { opacity: 1; transform: translate3d(0, 0, 0); }
}

/* 3. Terapkan Animasi pada Konten Utama & Setiap Tab Saat Aktif */
.main-content, main, .tab-pane, .kala-page-view {
  animation: kalaSlideUpIn 320ms cubic-bezier(0.22, 1, 0.36, 1) both;
  will-change: transform, opacity;
}

.kala-page-leaving {
  animation: kalaFadeOut 150ms cubic-bezier(0.4, 0, 1, 1) forwards !important;
  pointer-events: none;
}

/* 4. Efek Muncul Berurutan (Staggered Cascade) pada Kartu di Dalam Halaman */
.main-content .card,
.tab-pane .card,
.kala-stagger-item {
  animation: kalaCardCascade 360ms cubic-bezier(0.22, 1, 0.36, 1) both;
}
.main-content .card:nth-child(1), .tab-pane > *:nth-child(1) { animation-delay: 20ms; }
.main-content .card:nth-child(2), .tab-pane > *:nth-child(2) { animation-delay: 65ms; }
.main-content .card:nth-child(3), .tab-pane > *:nth-child(3) { animation-delay: 110ms; }
.main-content .card:nth-child(4), .tab-pane > *:nth-child(4) { animation-delay: 155ms; }
.main-content .card:nth-child(5), .tab-pane > *:nth-child(5) { animation-delay: 200ms; }

/* 5. Animasi Item Menu Sidebar & Hover Elastis */
.sidebar a, .sidebar .nav-item, .sidebar-link, .sidebar-nav .nav-link {
  position: relative;
  transition: transform 240ms cubic-bezier(0.22, 1, 0.36, 1),
              background-color 240ms cubic-bezier(0.22, 1, 0.36, 1),
              color 200ms ease,
              box-shadow 240ms ease !important;
}
.sidebar a:hover:not(.active), .sidebar .nav-item:hover:not(.active), .sidebar-nav .nav-link:hover:not(.active) {
  transform: translate3d(5px, 0, 0) !important;
  background-color: #EEF2FF !important;
  color: #5B3DF5 !important;
}
.sidebar a:active, .sidebar .nav-item:active, .bottom-nav a:active, .bottom-nav button:active, .mobile-bottom-nav a:active, .mobile-bottom-nav button:active, .mob-nav-item:active {
  transform: scale(0.94) !important;
}

/* 6. Perbaiki Drawer Sidebar Mobile: JANGAN PAKAI display:none AGAR ANIMASI SLIDE JALAN! */
@media (max-width: 1024px) {
  aside.sidebar, #sidebar, #app-sidebar, .sidebar {
    display: flex !important;
    visibility: hidden;
    pointer-events: none;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 280px !important;
    max-width: 85vw !important;
    height: 100dvh !important;
    z-index: 99999 !important;
    transform: translate3d(-104%, 0, 0) !important;
    transition: transform 320ms cubic-bezier(0.22, 1, 0.36, 1),
                visibility 0ms linear 320ms !important;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25) !important;
  }
  aside.sidebar.sidebar-open, aside.sidebar.active, aside.sidebar.open,
  #sidebar.active, #sidebar.open, #app-sidebar.open, #app-sidebar.active, #app-sidebar.sidebar-open,
  .sidebar.open, .sidebar.active, .sidebar.sidebar-open,
  body.sidebar-open aside.sidebar, body.sidebar-open #app-sidebar, body.sidebar-open .sidebar {
    visibility: visible !important;
    pointer-events: auto !important;
    transform: translate3d(0, 0, 0) !important;
    transition: transform 320ms cubic-bezier(0.22, 1, 0.36, 1),
                visibility 0ms linear 0ms !important;
  }
}

/* 7. Sliding Active Pill Motion System */
.sidebar-nav {
  position: relative;
}
.sidebar-sliding-pill {
  position: absolute;
  left: 10px;
  right: 10px;
  height: 36px;
  background: #5B3DF5;
  border-radius: 12px;
  box-shadow: 0 4px 14px rgba(91, 61, 245, 0.28);
  pointer-events: none;
  z-index: 1;
  opacity: 0;
  transform: translate3d(0, 0, 0);
  transition: transform 260ms cubic-bezier(0.22, 1, 0.36, 1),
              height 200ms cubic-bezier(0.22, 1, 0.36, 1),
              opacity 200ms ease;
}
.sidebar-nav .nav-link {
  position: relative;
  z-index: 2;
}
</style>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeMobileSidebar()"></div>

<aside class="sidebar" id="app-sidebar">
  <div class="sidebar-header">
    <a href="<?= $homeUrl ?>" style="display: flex; align-items: center; justify-content: space-between; width: 100%; text-decoration: none; color: inherit; gap: 8px;">
      <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
        <img src="assets/img/Logo-1.webp" alt="PT Kala Kausa Prima" class="sidebar-brand-img" style="height: 32px; width: 32px; object-fit: contain; flex-shrink: 0;">
        <span style="font-weight: 800; font-size: 13.5px; color: #000000; letter-spacing: -0.2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Kala Kausa Prima</span>
      </div>
      <span class="role-badge-sidebar">
        <?= $badgeLabel ?>
      </span>
    </a>
    <button class="sidebar-close-btn" onclick="closeMobileSidebar()" aria-label="Close Sidebar">&times;</button>
  </div>

  <nav class="sidebar-nav" id="app-sidebar-nav">
    <div class="sidebar-sliding-pill" id="sidebar-sliding-pill" aria-hidden="true"></div>

    <?php if ($isClient): ?>
      <!-- CLIENT PORTAL NAVIGATION -->
      <div class="nav-section-title">Client Portal</div>

      <a href="<?= url('client-portal?tab=deliverables&portal=client&_slot=client') ?>" class="nav-link <?= ($currentPage === 'client-portal' && (!isset($_GET['tab']) || $_GET['tab'] === 'deliverables')) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
        <span>Progress & Deliverables</span>
      </a>

      <?php 
        $clientCId = current_user_client_id();
        $clientHasSosmed = $clientCId ? client_has_sosmed_service($clientCId) : true;
        if ($clientHasSosmed): 
      ?>
      <a href="<?= url('client-portal?tab=calendar&portal=client&_slot=client') ?>" class="nav-link <?= ($currentPage === 'client-portal' && ($_GET['tab'] ?? '') === 'calendar') || in_array($currentPage, ['content-calendar', 'content-planner']) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span>Kalender Konten</span>
      </a>
      <?php endif; ?>

      <a href="<?= url('client-portal?tab=invoices&portal=client&_slot=client') ?>" class="nav-link <?= ($currentPage === 'client-portal' && ($_GET['tab'] ?? '') === 'invoices') || in_array($currentPage, ['invoices', 'invoice-view']) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        <span>Tagihan / Invoices</span>
      </a>

      <a href="<?= url('client-portal?tab=reports&portal=client&_slot=client') ?>" class="nav-link <?= ($currentPage === 'client-portal' && in_array(($_GET['tab'] ?? ''), ['reports', 'ads'])) || in_array($currentPage, ['reports', 'report-deck']) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        <span>Laporan Performa</span>
      </a>

    <?php elseif ($isWorker): ?>
      <!-- WORKER & FREELANCER NAVIGATION (SMART SPECIALIZATION ADAPTIVE) -->
      <div class="nav-section-title">Workspace Tim</div>

      <a href="<?= url('tasks?_slot=internal') ?>" class="nav-link <?= (in_array($currentPage, ['tasks', 'team-workspace']) && (empty($_GET['tab']) || $_GET['tab'] === 'tasks')) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <span>Tugas Saya & Progres</span>
      </a>

      <?php if (is_ads_specialist($currentUser)): ?>
        <!-- Menu Saldo Ads untuk Tim Ads (Sinkron dengan Bottom Navigation Bar) -->
        <a href="<?= url('tasks?tab=ads&_slot=internal') ?>" class="nav-link <?= (in_array($currentPage, ['tasks', 'team-workspace']) && in_array(($_GET['tab'] ?? ''), ['ads', 'ads_tracker'])) ? 'active' : '' ?>">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="12 6 12 12 16 14"/></svg>
          <span>Saldo Ads</span>
        </a>

        <!-- ATURAN ORANG ADS: Hapus Kalender Konten -> Ganti Jadi Report Ads -->
        <a href="<?= url('reports?tab=ads_report&_slot=internal') ?>" class="nav-link <?= in_array($currentPage, ['reports', 'reports-form', 'report-view', 'report-deck']) ? 'active' : '' ?>">
          <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
          <span>Report Ads</span>
        </a>
      <?php else: ?>
        <!-- ATURAN TIM KREATIF: Desain Grafis, Video Editor, Copywriter, Kelola Sosmed -->
        <a href="<?= url('tasks?tab=work_recap&_slot=internal') ?>" class="nav-link <?= ($currentPage === 'tasks' && ($_GET['tab'] ?? '') === 'work_recap') ? 'active' : '' ?>">
          <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          <span>Report Pekerjaan</span>
        </a>

        <?php 
          $workerUid = (int)($currentUser['id'] ?? 0);
          $hasSosmedProj = worker_has_sosmed_projects($workerUid);
          if ($hasSosmedProj): 
        ?>
        <a href="<?= url('content-calendar?_slot=internal') ?>" class="nav-link <?= in_array($currentPage, ['content-calendar', 'content-planner']) ? 'active' : '' ?>">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <span>Kalender Konten</span>
        </a>
        <?php endif; ?>
      <?php endif; ?>

      <a href="<?= url('tasks?tab=profile&_slot=internal') ?>" class="nav-link <?= (in_array($currentPage, ['tasks', 'team-workspace']) && ($_GET['tab'] ?? '') === 'profile') ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <span>Profil Saya</span>
      </a>

    <?php else: ?>
      <!-- OWNER & ADMIN NAVIGATION -->
      <div class="nav-section-title">Menu Utama</div>

      <?php if ($isOwner): ?>
        <a href="<?= url('owner-dashboard?_slot=internal') ?>" class="nav-link <?= $currentPage === 'owner-dashboard' ? 'active' : '' ?>">
          <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
          <span>Executive Dashboard</span>
        </a>
      <?php else: ?>
        <a href="<?= url('admin-dashboard?_slot=internal') ?>" class="nav-link <?= $currentPage === 'admin-dashboard' ? 'active' : '' ?>">
          <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
          <span>Admin Dashboard</span>
        </a>
      <?php endif; ?>

      <div class="nav-section-title">Manajemen Operasional</div>

      <!-- Task Assignment & SDM Management -->
      <a href="<?= url('tasks?_slot=internal') ?>" class="nav-link <?= in_array($currentPage, ['tasks', 'team-workspace']) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <span>Pembagian Tugas Tim</span>
      </a>

      <!-- Team & Employees Management -->
      <a href="<?= url('team?_slot=internal') ?>" class="nav-link <?= in_array($currentPage, ['team', 'employees-management']) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span>Tim & Karyawan</span>
      </a>

      <!-- Client Portal Preview for Admin/Owner -->
      <a href="<?= url('client-portal?preview=1&portal=internal&_slot=internal') ?>" class="nav-link <?= $currentPage === 'client-portal' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
        <span>Portal Klien (Preview)</span>
      </a>

      <a href="<?= url('invoices?_slot=internal') ?>" class="nav-link <?= in_array($currentPage, ['invoices', 'invoice-view']) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        <span>Invoice & Penagihan</span>
      </a>

      <a href="<?= url('expenses?_slot=internal') ?>" class="nav-link <?= $currentPage === 'expenses' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><circle cx="17" cy="15" r="1.5"/></svg>
        <span>Pengeluaran Operasional</span>
      </a>

      <a href="<?= url('salaries?_slot=internal') ?>" class="nav-link <?= in_array($currentPage, ['salaries', 'salary-view']) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><line x1="6" y1="12" x2="6.01" y2="12"/><line x1="18" y1="12" x2="18.01" y2="12"/></svg>
        <span>Penggajian Karyawan</span>
      </a>

      <a href="<?= url('clients?_slot=internal') ?>" class="nav-link <?= $currentPage === 'clients' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/></svg>
        <span>Klien & Proyek</span>
      </a>

      <a href="<?= url('reports?_slot=internal') ?>" class="nav-link <?= in_array($currentPage, ['reports', 'reports-form', 'report-view', 'report-deck']) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        <span>Laporan Klien</span>
      </a>

      <a href="<?= url('content-calendar?_slot=internal') ?>" class="nav-link <?= in_array($currentPage, ['content-calendar', 'content-planner']) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span>Kalender Konten</span>
      </a>

      <?php if ($isOwner): ?>
        <div class="nav-section-title">Konfigurasi</div>
        <a href="<?= url('settings?_slot=internal') ?>" class="nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          <span>Pengaturan Agensi</span>
        </a>
      <?php endif; ?>
    <?php endif; ?>

    <!-- Dedicated Logout Menu Link -->
    <?php
      $sbLogoutContext = ($currentPage === 'client-portal' || is_client()) ? 'client' : 'internal';
    ?>
    <!-- PWA Install Action Button (Only visible on supporting mobile/desktop browsers) -->
    <div style="padding: 0 12px; margin-top: 10px; margin-bottom: 8px;">
      <button type="button" id="btn-pwa-install" class="btn btn-secondary btn-sm" style="display: none; width: 100%; align-items: center; justify-content: center; gap: 6px; font-weight: 600; font-size: 11.5px; border-color: #2563EB; color: #2563EB; background: #EFF6FF; padding: 6px 10px; border-radius: 8px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        <span>Pasang Portal Kala</span>
      </button>
    </div>

    <div class="nav-section-title">Sesi Akun</div>
    <a href="<?= url('logout') ?>?context=<?= $sbLogoutContext ?>" onclick="if(window.handleKalaLogout){handleKalaLogout(this.href);return false;}else{sessionStorage.removeItem('kala_tab_uid');}" class="nav-link nav-link-logout">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      <span>Keluar (Logout)</span>
    </a>

  </nav>

  <div class="sidebar-user">
    <div class="user-profile">
      <div class="avatar">
        <?= $sidebarAvatar ?>
      </div>
      <div class="user-details">
        <h4><?= $sidebarProfileName ?></h4>
        <span class="role-badge">
          <?= $sidebarBadge ?>
        </span>
      </div>
    </div>
  </div>

  <script>
    window.updateSidebarSlidingPill = function(targetLink) {
      var nav = document.getElementById('app-sidebar-nav');
      var pill = document.getElementById('sidebar-sliding-pill');
      if (!nav || !pill) return;
      var activeLink = targetLink || nav.querySelector('.nav-link.active');
      if (activeLink && activeLink.offsetParent !== null) {
        var topPos = activeLink.offsetTop;
        var h = activeLink.offsetHeight;
        pill.style.transform = 'translate3d(0, ' + topPos + 'px, 0)';
        pill.style.height = h + 'px';
        pill.style.opacity = '1';
      } else if (!targetLink) {
        pill.style.opacity = '0';
      }
    };

    window.toggleMobileSidebar = function() {
      var sidebar = document.getElementById('app-sidebar') || document.querySelector('.sidebar');
      var overlay = document.getElementById('sidebar-overlay');
      var isOpen = document.body.classList.toggle('sidebar-open');
      if (sidebar) {
        sidebar.classList.toggle('sidebar-open', isOpen);
        sidebar.classList.toggle('active', isOpen);
        sidebar.classList.toggle('open', isOpen);
      }
      if (overlay) overlay.classList.toggle('active', isOpen);
      if (isOpen) {
        setTimeout(window.updateSidebarSlidingPill, 120);
      }
    };

    window.closeMobileSidebar = function() {
      var sidebar = document.getElementById('app-sidebar') || document.querySelector('.sidebar');
      var overlay = document.getElementById('sidebar-overlay');
      document.body.classList.remove('sidebar-open');
      if (sidebar) {
        sidebar.classList.remove('sidebar-open');
        sidebar.classList.remove('active');
        sidebar.classList.remove('open');
      }
      if (overlay) overlay.classList.remove('active');
    };

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        window.closeMobileSidebar();
      }
    });

    (function() {
      try {
        var nav = document.getElementById('app-sidebar-nav') || document.querySelector('.sidebar-nav');
        var savedPos = sessionStorage.getItem('kala_sidebar_scroll');
        if (nav && savedPos !== null) {
          nav.scrollTop = parseInt(savedPos, 10);
        }

        // Initialize Sliding Active Pill
        requestAnimationFrame(function() {
          setTimeout(window.updateSidebarSlidingPill, 60);
        });

        if (nav) {
          nav.addEventListener('scroll', function() {
            sessionStorage.setItem('kala_sidebar_scroll', nav.scrollTop);
          }, { passive: true });

          nav.addEventListener('mouseleave', function() {
            window.updateSidebarSlidingPill();
          });

          nav.querySelectorAll('.nav-link').forEach(function(link) {
            link.addEventListener('mouseenter', function() {
              window.updateSidebarSlidingPill(link);
            });
            link.addEventListener('click', function(e) {
              window.updateSidebarSlidingPill(link);
            });
          });
        }
      } catch(e) {}
    })();
  </script>
</aside>

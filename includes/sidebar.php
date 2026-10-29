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
      <div style="display: flex; align-items: center; gap: 9px; min-width: 0; flex: 1;">
        <img src="assets/img/Logo-1.webp" alt="PT Kala Kausa Prima" class="sidebar-brand-img" style="height: 32px; width: 32px; object-fit: contain; flex-shrink: 0;">
        <span class="sidebar-brand-name" style="font-weight: 800; font-size: 13.5px; letter-spacing: -0.2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Kala Kausa Prima</span>
      </div>
      <div style="display: flex; align-items: center; gap: 4px; flex-shrink: 0;">
        <span class="role-badge-sidebar">
          <?= $badgeLabel ?>
        </span>
      </div>
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

      <?php if ($isOwner || $isAdmin): ?>
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
    <div class="nav-section-title">Sesi Akun</div>
    <a href="<?= url('logout') ?>?context=<?= $sbLogoutContext ?>" onclick="if(window.handleKalaLogout){handleKalaLogout(this.href);return false;}else{sessionStorage.removeItem('kala_tab_uid');}" class="nav-link nav-link-logout">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      <span>Keluar (Logout)</span>
    </a>
    <!-- Dedicated App Version Display under Logout -->
    <div class="sidebar-version-wrap" style="padding: 6px 12px 10px; margin-top: 2px; display: flex; align-items: center; justify-content: space-between;">
      <span style="font-size: 11px; font-weight: 500; color: var(--text-muted, #94A3B8); letter-spacing: 0.1px;">Versi Aplikasi</span>
      <span class="badge-version" title="Versi Rilis Sistem"><?= defined('APP_VERSION') ? APP_VERSION : 'v3.0' ?></span>
    </div>
  </nav>

  <!-- Sidebar Footer: PWA Button + Nebula Theme Switcher + Profile Card in 1 Flex Column -->
  <div class="sidebar-footer" id="app-sidebar-footer">
    <!-- PWA Install Action Button (Only visible on supporting mobile/desktop browsers) -->
    <div id="pwa-install-wrap" style="width: 100%;">
      <button type="button" id="btn-pwa-install" class="btn btn-secondary btn-sm" style="display: none; width: 100%; align-items: center; justify-content: center; gap: 6px; font-weight: 600; font-size: 11.5px; border-color: #2563EB; color: #2563EB; background: #EFF6FF; padding: 6px 10px; border-radius: 8px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        <span>Pasang Portal Kala</span>
      </button>
    </div>

    <!-- Theme Toggle Switch (Neumorphic 3D Switch matching Image 2 - Full Sidebar Width) -->
    <style>
      .sidebar-theme-toggle-wrap { width: 100%; display: flex; padding: 4px 0; margin: 4px 0; box-sizing: border-box; }
      .sidebar-theme-toggle { width: 100% !important; background: transparent !important; border: none !important; padding: 0 !important; box-shadow: none !important; cursor: pointer; display: block !important; outline: none !important; }
      .sidebar-theme-toggle:hover { border-color: transparent !important; box-shadow: none !important; }
      .theme-switch-pill {
        width: 100% !important;
        height: 40px !important;
        border-radius: 9999px !important;
        background: #E2E5EB !important;
        position: relative !important;
        box-sizing: border-box !important;
        box-shadow: inset 0 3px 6px rgba(0,0,0,0.20), inset 0 1px 2px rgba(0,0,0,0.15), inset 0 -1px 2px rgba(255,255,255,0.9), 0 1px 1px rgba(255,255,255,0.6) !important;
        border: 1px solid rgba(0,0,0,0.08) !important;
        display: block !important;
        cursor: pointer !important;
        transition: background 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease !important;
        overflow: hidden !important;
      }
      html[data-theme="dark"] .theme-switch-pill,
      body.dark-mode .theme-switch-pill {
        background: #252830 !important;
        box-shadow: inset 0 4px 7px rgba(0,0,0,0.75), inset 0 1.5px 3px rgba(0,0,0,0.6), inset 0 -1px 1px rgba(255,255,255,0.08), 0 1px 2px rgba(0,0,0,0.35) !important;
        border: 1px solid rgba(0,0,0,0.5) !important;
      }
      /* Ambient track destination indicators */
      .track-icon-ambient {
        position: absolute !important;
        top: 11px !important;
        width: 16px !important;
        height: 16px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        z-index: 1 !important;
        pointer-events: none !important;
        opacity: 0.65;
        transition: opacity 0.25s ease;
      }
      .track-icon-ambient-left {
        left: 12px !important;
        color: #F59E0B !important;
      }
      .track-icon-ambient-right {
        right: 12px !important;
        color: #94A3B8 !important;
      }
      html[data-theme="dark"] .track-icon-ambient-left {
        opacity: 0.75 !important;
        color: #FBBF24 !important;
      }
      html[data-theme="dark"] .track-icon-ambient-right {
        opacity: 0.35 !important;
      }
      /* 3D Tactile Circular Knob */
      .theme-switch-knob {
        width: 32px !important;
        height: 32px !important;
        border-radius: 50% !important;
        position: absolute !important;
        top: 3px !important;
        left: 4px !important;
        transform: none !important;
        background: linear-gradient(180deg, #FFFFFF 0%, #F5F7FA 70%, #E3E7EE 100%) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-sizing: border-box !important;
        box-shadow: 0 3px 6px rgba(0,0,0,0.22), 0 1px 2px rgba(0,0,0,0.15), inset 0 1.5px 1.5px #FFFFFF, inset 0 -1.5px 1.5px rgba(0,0,0,0.12) !important;
        border: 1px solid rgba(0,0,0,0.06) !important;
        transition: left 0.34s cubic-bezier(0.34, 1.25, 0.64, 1), background 0.25s ease, box-shadow 0.25s ease !important;
        pointer-events: none !important;
        z-index: 2 !important;
      }
      html[data-theme="dark"] .theme-switch-knob,
      body.dark-mode .theme-switch-knob {
        left: calc(100% - 36px) !important;
        transform: none !important;
        background: linear-gradient(180deg, #FFFFFF 0%, #F3F4F8 70%, #DFE3EB 100%) !important;
        box-shadow: 0 3px 8px rgba(0,0,0,0.5), 0 1px 3px rgba(0,0,0,0.35), inset 0 1.5px 1.5px #FFFFFF, inset 0 -1.5px 1.5px rgba(0,0,0,0.15) !important;
      }
      .theme-switch-knob .knob-icon {
        flex-shrink: 0;
        display: block;
      }
      /* Light mode (default): show sun, hide moon */
      .theme-switch-knob .knob-icon-sun {
        display: block !important;
        stroke: #EA580C !important;
      }
      .theme-switch-knob .knob-icon-moon {
        display: none !important;
      }
      /* Dark mode: hide sun, show moon with stars */
      html[data-theme="dark"] .theme-switch-knob .knob-icon-sun,
      body.dark-mode .theme-switch-knob .knob-icon-sun {
        display: none !important;
      }
      html[data-theme="dark"] .theme-switch-knob .knob-icon-moon,
      body.dark-mode .theme-switch-knob .knob-icon-moon {
        display: block !important;
        stroke: #4B5563 !important;
      }
    </style>
    <div class="sidebar-theme-toggle-wrap">
      <button type="button" class="sidebar-theme-toggle" onclick="toggleTheme()" aria-label="Beralih Mode Tema (Terang / Gelap)" title="Beralih Mode Tema (Terang / Gelap)">
        <div class="theme-switch-pill" aria-hidden="true">
          <!-- Ambient Destination Indicator: Sun on Left -->
          <div class="track-icon-ambient track-icon-ambient-left">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="4.3"></circle>
              <line x1="12" y1="2" x2="12" y2="4.5"></line>
              <line x1="12" y1="19.5" x2="12" y2="22"></line>
              <line x1="2" y1="12" x2="4.5" y2="12"></line>
              <line x1="19.5" y1="12" x2="22" y2="12"></line>
            </svg>
          </div>
          <!-- Ambient Destination Indicator: Moon on Right -->
          <div class="track-icon-ambient track-icon-ambient-right">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14.5 3.5A8.5 8.5 0 1 0 20.5 14.5 7.2 7.2 0 0 1 14.5 3.5z"></path>
              <path d="M18.5 3.5v3.2m-1.6-1.6h3.2" stroke-width="1.8"></path>
            </svg>
          </div>
          <!-- Active 3D Porcelain Tactile Knob -->
          <div class="theme-switch-knob">
            <!-- Sun icon for light mode (Knob state: Left, warm amber rays) -->
            <svg class="knob-icon knob-icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#EA580C" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="4.3"></circle>
              <line x1="12" y1="2" x2="12" y2="4.5"></line>
              <line x1="12" y1="19.5" x2="12" y2="22"></line>
              <line x1="2" y1="12" x2="4.5" y2="12"></line>
              <line x1="19.5" y1="12" x2="22" y2="12"></line>
              <line x1="4.93" y1="4.93" x2="6.7" y2="6.7"></line>
              <line x1="17.3" y1="17.3" x2="19.07" y2="19.07"></line>
              <line x1="4.93" y1="19.07" x2="6.7" y2="17.3"></line>
              <line x1="17.3" y1="6.7" x2="19.07" y2="4.93"></line>
            </svg>
            <!-- Moon + stars icon for dark mode (Knob state: Right, charcoal moon with sparkle stars) -->
            <svg class="knob-icon knob-icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4B5563" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14.5 3.5A8.5 8.5 0 1 0 20.5 14.5 7.2 7.2 0 0 1 14.5 3.5z"></path>
              <path d="M18.5 3.5v3.2m-1.6-1.6h3.2" stroke-width="2"></path>
              <path d="M21 8.5v2.2m-1.1-1.1h2.2" stroke-width="1.8"></path>
            </svg>
          </div>
        </div>
      </button>
    </div>

    <!-- User Profile Card -->
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

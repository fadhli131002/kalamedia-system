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
/* 1. Native View Transitions API: Isolate Sidebar & Topbar so only Main Content morphs */
@view-transition {
  navigation: auto;
}
.sidebar, #app-sidebar {
  view-transition-name: kala-sidebar;
}
.topbar, .appify-topbar, .owner-topbar {
  view-transition-name: kala-topbar;
}
.content-body, .main-content {
  view-transition-name: kala-main;
}
::view-transition-old(kala-sidebar),
::view-transition-new(kala-sidebar),
::view-transition-old(kala-topbar),
::view-transition-new(kala-topbar) {
  animation: none !important;
  mix-blend-mode: normal !important;
}
::view-transition-old(kala-main) {
  animation: kalaFadeOut 150ms cubic-bezier(0.4, 0, 1, 1) both;
}
::view-transition-new(kala-main) {
  animation: kalaContentEnter 420ms cubic-bezier(0.16, 1, 0.3, 1) both;
}

/* 2. Keyframes Masuk & Keluar Halaman + Staggered Content Morph */
@keyframes kalaFadeOut {
  from { opacity: 1; transform: translate3d(0, 0, 0) scale(1); }
  to   { opacity: 0; transform: translate3d(0, -6px, 0) scale(0.99); }
}

@keyframes kalaContentEnter {
  0% {
    opacity: 0;
    transform: translate3d(0, 14px, 0) scale(0.99);
    filter: blur(4px);
  }
  100% {
    opacity: 1;
    transform: translate3d(0, 0, 0) scale(1);
    filter: blur(0);
  }
}

/* 3. Staggered Content Entrance on Direct Children of .content-body / .main-content / .tab-pane */
.content-body > *,
.main-content > *,
.tab-pane > * {
  animation: kalaContentEnter 0.45s cubic-bezier(0.16, 1, 0.3, 1) both;
  will-change: transform, opacity, filter;
}
.content-body > *:nth-child(1), .main-content > *:nth-child(1), .tab-pane > *:nth-child(1) { animation-delay: 0.03s; }
.content-body > *:nth-child(2), .main-content > *:nth-child(2), .tab-pane > *:nth-child(2) { animation-delay: 0.07s; }
.content-body > *:nth-child(3), .main-content > *:nth-child(3), .tab-pane > *:nth-child(3) { animation-delay: 0.11s; }
.content-body > *:nth-child(4), .main-content > *:nth-child(4), .tab-pane > *:nth-child(4) { animation-delay: 0.15s; }
.content-body > *:nth-child(5), .main-content > *:nth-child(5), .tab-pane > *:nth-child(5) { animation-delay: 0.19s; }
.content-body > *:nth-child(6), .main-content > *:nth-child(6), .tab-pane > *:nth-child(6) { animation-delay: 0.23s; }

.kala-page-leaving {
  animation: kalaFadeOut 140ms cubic-bezier(0.4, 0, 1, 1) forwards !important;
  pointer-events: none;
}

@media (prefers-reduced-motion: reduce) {
  .content-body > *, .main-content > *, .tab-pane > * {
    animation: none !important;
    filter: none !important;
  }
}

/* 4. Transisi Lebar & Geser Mulus Sidebar + Efek Tekan Tombol Active (transform: scale) */
.sidebar,
aside.sidebar,
#app-sidebar {
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1),
              width 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
}

@media (min-width: 1025px) {
  html,
  body,
  .app-container {
    transform: none !important;
    filter: none !important;
    perspective: none !important;
    backdrop-filter: none !important;
    overflow-x: clip !important;
  }
  .sidebar,
  aside.sidebar,
  #app-sidebar {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    bottom: 0 !important;
    width: 250px !important;
    height: 100vh !important;
    height: 100dvh !important;
    max-height: 100dvh !important;
    z-index: 200 !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
  }
  .main-wrapper {
    margin-left: 250px !important;
    width: calc(100% - 250px) !important;
    max-width: calc(100% - 250px) !important;
  }
}

.sidebar-nav {
  position: relative;
}
.sidebar-menu-item,
.sidebar-nav .nav-link,
.sidebar-nav .sidebar-link {
  position: relative;
  z-index: 2;
  transition: transform 0.1s ease,
              color 0.2s ease,
              background-color 0.2s ease !important;
}
.sidebar-menu-item:active,
.sidebar-menu-item.active,
.sidebar-nav .nav-link:active,
.sidebar-nav .nav-link.active,
.sidebar-nav .sidebar-link:active,
.sidebar-nav .sidebar-link.active,
.mobile-bottom-nav .mob-nav-item:active {
  transform: scale(0.98) !important;
  transition: transform 0.1s ease !important;
}
.sidebar-nav .nav-link .nav-icon-wrap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1),
              filter 0.2s ease;
}
.sidebar-nav .nav-link:hover:not(.active) {
  color: #5B3DF5 !important;
  background-color: transparent !important;
}
html[data-theme="dark"] .sidebar-nav .nav-link:hover:not(.active),
body.dark-mode .sidebar-nav .nav-link:hover:not(.active) {
  color: #A5B4FC !important;
}
.sidebar-nav .nav-link.active .nav-icon-wrap svg {
  filter: drop-shadow(0 2px 8px rgba(99, 102, 241, 0.45));
}

/* When FLIP Gliding Pill is active, let the pill render the active background */
.sidebar-nav.has-gliding-pill .nav-link.active {
  background: transparent !important;
  box-shadow: none !important;
  color: #FFFFFF !important;
}
.sidebar-nav.has-gliding-pill .nav-link.active svg {
  stroke: #FFFFFF !important;
  color: #FFFFFF !important;
}

/* 5. FLIP Gliding Active Pill & Soft Hover Ghost Pill */
.sidebar-active-pill {
  position: absolute;
  left: 10px;
  right: 10px;
  top: 0;
  height: 40px;
  background: linear-gradient(135deg, #5B3DF5 0%, #4F46E5 100%);
  border-radius: 12px;
  box-shadow: 0 6px 16px -3px rgba(91, 61, 245, 0.42);
  pointer-events: none;
  z-index: 1;
  opacity: 0;
  will-change: transform, height;
  transform: translate3d(0, 0, 0);
}
.sidebar-hover-pill {
  position: absolute;
  left: 10px;
  right: 10px;
  top: 0;
  height: 40px;
  background: rgba(91, 61, 245, 0.08);
  border-radius: 12px;
  pointer-events: none;
  z-index: 0;
  opacity: 0;
  will-change: transform, height, opacity;
  transform: translate3d(0, 0, 0);
  transition: transform 0.26s cubic-bezier(0.22, 1, 0.36, 1),
              height 0.22s cubic-bezier(0.22, 1, 0.36, 1),
              opacity 0.18s ease;
}
html[data-theme="dark"] .sidebar-hover-pill,
body.dark-mode .sidebar-hover-pill {
  background: rgba(148, 163, 184, 0.10);
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
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                width 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                visibility 0ms linear 0.3s !important;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25) !important;
  }
  aside.sidebar.sidebar-open, aside.sidebar.active, aside.sidebar.open,
  #sidebar.active, #sidebar.open, #app-sidebar.open, #app-sidebar.active, #app-sidebar.sidebar-open,
  .sidebar.open, .sidebar.active, .sidebar.sidebar-open,
  body.sidebar-open aside.sidebar, body.sidebar-open #app-sidebar, body.sidebar-open .sidebar {
    visibility: visible !important;
    pointer-events: auto !important;
    transform: translate3d(0, 0, 0) !important;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                width 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                visibility 0ms linear 0ms !important;
  }
}
</style>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeMobileSidebar()"></div>

<aside class="sidebar" id="app-sidebar">
  <div class="sidebar-header">
    <a href="<?= $homeUrl ?>" style="display: flex; align-items: center; justify-content: space-between; width: 100%; text-decoration: none; color: inherit; gap: 8px;">
      <div style="display: flex; align-items: center; gap: 9px; min-width: 0; flex: 1;">
        <img src="assets/img/Logo-1.webp" alt="PT Kala Kausa Prima" class="sidebar-brand-img" width="32" height="32" style="height: 32px; width: 32px; object-fit: contain; flex-shrink: 0;">
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
    <div id="sidebarHoverPill" class="sidebar-hover-pill" aria-hidden="true"></div>
    <div id="sidebarActivePill" class="sidebar-active-pill sidebar-sliding-pill" aria-hidden="true"></div>

    <?php if ($isClient): ?>
      <!-- CLIENT PORTAL NAVIGATION -->
      <div class="nav-section-title">Client Portal</div>

      <a href="<?= url('client-portal?tab=deliverables&portal=client&_slot=client') ?>" data-nav-key="client-deliverables" class="nav-link sidebar-link sidebar-menu-item <?= ($currentPage === 'client-portal' && (!isset($_GET['tab']) || $_GET['tab'] === 'deliverables')) ? 'active' : '' ?>">
        <span class="nav-icon-wrap"><?= renderItshoverIcon('deliverables', 20) ?></span>
        <span>Progress &amp; Deliverables</span>
      </a>

      <?php 
        $clientCId = current_user_client_id();
        $clientHasSosmed = $clientCId ? client_has_sosmed_service($clientCId) : true;
        if ($clientHasSosmed): 
      ?>
      <a href="<?= url('client-portal?tab=calendar&portal=client&_slot=client') ?>" data-nav-key="client-calendar" class="nav-link sidebar-link sidebar-menu-item <?= ($currentPage === 'client-portal' && ($_GET['tab'] ?? '') === 'calendar') || in_array($currentPage, ['content-calendar', 'content-planner']) ? 'active' : '' ?>">
        <span class="nav-icon-wrap"><?= renderItshoverIcon('calendar', 20) ?></span>
        <span>Kalender Konten</span>
      </a>
      <?php endif; ?>

      <a href="<?= url('client-portal?tab=invoices&portal=client&_slot=client') ?>" data-nav-key="client-invoices" class="nav-link sidebar-link sidebar-menu-item <?= ($currentPage === 'client-portal' && ($_GET['tab'] ?? '') === 'invoices') || in_array($currentPage, ['invoices', 'invoice-view']) ? 'active' : '' ?>">
        <span class="nav-icon-wrap"><?= renderItshoverIcon('invoices', 20) ?></span>
        <span>Tagihan / Invoices</span>
      </a>

      <a href="<?= url('client-portal?tab=reports&portal=client&_slot=client') ?>" data-nav-key="client-reports" class="nav-link sidebar-link sidebar-menu-item <?= ($currentPage === 'client-portal' && in_array(($_GET['tab'] ?? ''), ['reports', 'ads'])) || in_array($currentPage, ['reports', 'report-deck']) ? 'active' : '' ?>">
        <span class="nav-icon-wrap"><?= renderItshoverIcon('reports', 20) ?></span>
        <span>Laporan Performa</span>
      </a>

    <?php elseif ($isWorker): ?>
      <!-- WORKER & FREELANCER NAVIGATION (SMART SPECIALIZATION ADAPTIVE) -->
      <div class="nav-section-title">Workspace Tim</div>

      <a href="<?= url('tasks?_slot=internal') ?>" data-nav-key="worker-tasks" class="nav-link sidebar-link sidebar-menu-item <?= (in_array($currentPage, ['tasks', 'team-workspace']) && (empty($_GET['tab']) || $_GET['tab'] === 'tasks')) ? 'active' : '' ?>">
        <span class="nav-icon-wrap"><?= renderItshoverIcon('tasks', 20) ?></span>
        <span>Tugas Saya &amp; Progres</span>
      </a>

      <?php if (is_ads_specialist($currentUser)): ?>
        <a href="<?= url('tasks?tab=ads&_slot=internal') ?>" data-nav-key="worker-ads" class="nav-link sidebar-link sidebar-menu-item <?= (in_array($currentPage, ['tasks', 'team-workspace']) && in_array(($_GET['tab'] ?? ''), ['ads', 'ads_tracker'])) ? 'active' : '' ?>">
          <span class="nav-icon-wrap"><?= renderItshoverIcon('ads', 20) ?></span>
          <span>Saldo Ads</span>
        </a>

        <a href="<?= url('reports?tab=ads_report&_slot=internal') ?>" data-nav-key="worker-reports" class="nav-link sidebar-link sidebar-menu-item <?= in_array($currentPage, ['reports', 'reports-form', 'report-view', 'report-deck']) ? 'active' : '' ?>">
          <span class="nav-icon-wrap"><?= renderItshoverIcon('reports', 20) ?></span>
          <span>Report Ads</span>
        </a>
      <?php else: ?>
        <a href="<?= url('tasks?tab=work_recap&_slot=internal') ?>" data-nav-key="worker-recap" class="nav-link sidebar-link sidebar-menu-item <?= ($currentPage === 'tasks' && ($_GET['tab'] ?? '') === 'work_recap') ? 'active' : '' ?>">
          <span class="nav-icon-wrap"><?= renderItshoverIcon('invoices', 20) ?></span>
          <span>Report Pekerjaan</span>
        </a>

        <?php 
          $workerUid = (int)($currentUser['id'] ?? 0);
          $hasSosmedProj = worker_has_sosmed_projects($workerUid);
          if ($hasSosmedProj): 
        ?>
        <a href="<?= url('content-calendar?_slot=internal') ?>" data-nav-key="worker-calendar" class="nav-link sidebar-link sidebar-menu-item <?= in_array($currentPage, ['content-calendar', 'content-planner']) ? 'active' : '' ?>">
          <span class="nav-icon-wrap"><?= renderItshoverIcon('calendar', 20) ?></span>
          <span>Kalender Konten</span>
        </a>
        <?php endif; ?>
      <?php endif; ?>

      <a href="<?= url('tasks?tab=profile&_slot=internal') ?>" data-nav-key="worker-profile" class="nav-link sidebar-link sidebar-menu-item <?= (in_array($currentPage, ['tasks', 'team-workspace']) && ($_GET['tab'] ?? '') === 'profile') ? 'active' : '' ?>">
        <span class="nav-icon-wrap"><?= renderItshoverIcon('profile', 20) ?></span>
        <span>Profil Saya</span>
      </a>

    <?php else: ?>
      <!-- OWNER & ADMIN NAVIGATION (Minimalist 6-Pillar Architecture) -->
      <div class="nav-section-title">Menu Utama</div>

      <?php if ($isOwner): ?>
        <a href="<?= url('owner-dashboard?_slot=internal') ?>" data-nav-key="owner-dashboard" class="nav-link sidebar-link sidebar-menu-item <?= $currentPage === 'owner-dashboard' ? 'active' : '' ?>">
          <span class="nav-icon-wrap"><?= renderItshoverIcon('dashboard', 20) ?></span>
          <span>Executive Dashboard</span>
        </a>
      <?php else: ?>
        <a href="<?= url('admin-dashboard?_slot=internal') ?>" data-nav-key="admin-dashboard" class="nav-link sidebar-link sidebar-menu-item <?= $currentPage === 'admin-dashboard' ? 'active' : '' ?>">
          <span class="nav-icon-wrap"><?= renderItshoverIcon('dashboard', 20) ?></span>
          <span>Admin Dashboard</span>
        </a>
      <?php endif; ?>

      <div class="nav-section-title">Operasional &amp; Bisnis</div>

      <!-- 1. Tugas & Operasional -->
      <a href="<?= url('tasks?_slot=internal') ?>" data-nav-key="tasks-ops" class="nav-link sidebar-link sidebar-menu-item <?= in_array($currentPage, ['tasks', 'team-workspace', 'content-calendar', 'content-planner']) ? 'active' : '' ?>">
        <span class="nav-icon-wrap"><?= renderItshoverIcon('tasks', 20) ?></span>
        <span>Tugas &amp; Operasional</span>
      </a>

      <!-- 2. Klien & Tim -->
      <a href="<?= url('clients?_slot=internal') ?>" data-nav-key="clients-team" class="nav-link sidebar-link sidebar-menu-item <?= in_array($currentPage, ['clients', 'team', 'employees-management', 'client-portal']) ? 'active' : '' ?>">
        <span class="nav-icon-wrap"><?= renderItshoverIcon('clients', 20) ?></span>
        <span>Klien &amp; Tim</span>
      </a>

      <!-- 3. Keuangan & Invoice -->
      <a href="<?= url('invoices?_slot=internal') ?>" data-nav-key="finance-invoices" class="nav-link sidebar-link sidebar-menu-item <?= in_array($currentPage, ['invoices', 'invoice-view', 'expenses', 'salaries', 'salary-view']) ? 'active' : '' ?>">
        <span class="nav-icon-wrap"><?= renderItshoverIcon('invoices', 20) ?></span>
        <span>Keuangan &amp; Invoice</span>
      </a>

      <!-- 4. Laporan Klien -->
      <a href="<?= url('reports?_slot=internal') ?>" data-nav-key="reports" class="nav-link sidebar-link sidebar-menu-item <?= in_array($currentPage, ['reports', 'reports-form', 'report-view', 'report-deck']) ? 'active' : '' ?>">
        <span class="nav-icon-wrap"><?= renderItshoverIcon('reports', 20) ?></span>
        <span>Laporan Klien</span>
      </a>

      <?php if ($isOwner || $isAdmin): ?>
        <div class="nav-section-title">Sistem</div>
        <a href="<?= url('settings?_slot=internal') ?>" data-nav-key="settings" class="nav-link sidebar-link sidebar-menu-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
          <span class="nav-icon-wrap"><?= renderItshoverIcon('settings', 20) ?></span>
          <span>Pengaturan Agensi</span>
        </a>
      <?php endif; ?>
    <?php endif; ?>

    <!-- Dedicated Logout Menu Link -->
    <?php
      $sbLogoutContext = is_client() ? 'client' : 'internal';
    ?>
    <a href="<?= url('logout') ?>?context=<?= $sbLogoutContext ?>" data-nav-key="logout" onclick="if(window.handleKalaLogout){handleKalaLogout(this.href);return false;}else{sessionStorage.removeItem('kala_tab_uid');}" class="nav-link sidebar-link sidebar-menu-item nav-link-logout" style="margin-top: 6px;">
      <span class="nav-icon-wrap"><?= renderItshoverIcon('logout', 20) ?></span>
      <span>Keluar (Logout)</span>
    </a>
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
    window.updateSidebarSlidingPill = function(targetLink, useSpring) {
      var nav = document.getElementById('app-sidebar-nav');
      var pill = document.getElementById('sidebarActivePill') || document.getElementById('sidebar-sliding-pill');
      if (!nav || !pill) return;
      var activeLink = targetLink || nav.querySelector('.nav-link.active');
      if (activeLink && activeLink.offsetParent !== null) {
        nav.classList.add('has-gliding-pill');
        var topPos = activeLink.offsetTop;
        var h = activeLink.offsetHeight;
        if (useSpring !== false) {
          pill.style.transition = 'transform 0.42s cubic-bezier(0.22, 1, 0.36, 1), height 0.32s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.2s ease';
        }
        pill.style.transform = 'translate3d(0, ' + topPos + 'px, 0)';
        pill.style.height = h + 'px';
        pill.style.opacity = '1';
      } else if (!targetLink) {
        nav.classList.remove('has-gliding-pill');
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
        setTimeout(function() { window.updateSidebarSlidingPill(null, true); }, 80);
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
        var pill = document.getElementById('sidebarActivePill') || document.getElementById('sidebar-sliding-pill');
        var hoverPill = document.getElementById('sidebarHoverPill');
        if (!nav) return;

        // 1. Read saved FLIP state from previous page click
        var rawMotion = sessionStorage.getItem('kala_sidebar_motion');
        var savedMotion = null;
        if (rawMotion) {
          try { savedMotion = JSON.parse(rawMotion); } catch(err) {}
        }

        var savedScroll = (savedMotion && typeof savedMotion.scroll === 'number')
          ? savedMotion.scroll
          : parseInt(sessionStorage.getItem('kala_sidebar_scroll') || '0', 10);
        if (!isNaN(savedScroll)) {
          nav.scrollTop = savedScroll;
        }

        // 2. FLIP (First -> Last -> Invert -> Play) Gliding Active Pill
        var activeLink = nav.querySelector('.nav-link.active');
        if (pill && activeLink && activeLink.offsetParent !== null) {
          nav.classList.add('has-gliding-pill');
          var newTop = activeLink.offsetTop;
          var newHeight = activeLink.offsetHeight;

          if (savedMotion && typeof savedMotion.top === 'number' && savedMotion.top !== newTop) {
            // Invert: place pill at old position with zero transition
            pill.style.transition = 'none';
            pill.style.transform = 'translate3d(0, ' + savedMotion.top + 'px, 0)';
            pill.style.height = (savedMotion.height || newHeight) + 'px';
            pill.style.opacity = '1';
            void pill.offsetHeight; // Force reflow

            // Play: glide to new active menu position at 60fps
            requestAnimationFrame(function() {
              pill.style.transition = 'transform 0.42s cubic-bezier(0.22, 1, 0.36, 1), height 0.32s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.2s ease';
              pill.style.transform = 'translate3d(0, ' + newTop + 'px, 0)';
              pill.style.height = newHeight + 'px';
            });
          } else {
            pill.style.transition = 'none';
            pill.style.transform = 'translate3d(0, ' + newTop + 'px, 0)';
            pill.style.height = newHeight + 'px';
            pill.style.opacity = '1';
            requestAnimationFrame(function() {
              pill.style.transition = 'transform 0.42s cubic-bezier(0.22, 1, 0.36, 1), height 0.32s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.2s ease';
            });
          }
          // Update stored position to current active item
          sessionStorage.setItem('kala_sidebar_motion', JSON.stringify({
            top: newTop,
            height: newHeight,
            scroll: nav.scrollTop,
            key: activeLink.getAttribute('data-nav-key') || ''
          }));
        }

        // 3. Persist scroll & attach Hover Ghost Pill + Click FLIP state capture
        nav.addEventListener('scroll', function() {
          sessionStorage.setItem('kala_sidebar_scroll', nav.scrollTop);
        }, { passive: true });

        nav.querySelectorAll('.nav-link').forEach(function(link) {
          link.addEventListener('mouseenter', function() {
            if (!hoverPill || link.classList.contains('active')) {
              if (hoverPill) hoverPill.style.opacity = '0';
              return;
            }
            hoverPill.style.transform = 'translate3d(0, ' + link.offsetTop + 'px, 0)';
            hoverPill.style.height = link.offsetHeight + 'px';
            hoverPill.style.opacity = '1';
          });

          link.addEventListener('click', function() {
            var currentActive = nav.querySelector('.nav-link.active') || link;
            sessionStorage.setItem('kala_sidebar_motion', JSON.stringify({
              top: currentActive.offsetTop,
              height: currentActive.offsetHeight,
              scroll: nav.scrollTop,
              key: currentActive.getAttribute('data-nav-key') || ''
            }));
            window.updateSidebarSlidingPill(link, true);
          });
        });

        nav.addEventListener('mouseleave', function() {
          if (hoverPill) {
            hoverPill.style.opacity = '0';
          }
        });
      } catch(e) {}
    })();
  </script>
</aside>

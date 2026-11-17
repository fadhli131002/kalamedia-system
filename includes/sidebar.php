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

<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeMobileSidebar()"></div>

<aside class="sidebar" id="app-sidebar">
  <div class="sidebar-header">
    <a href="<?= $homeUrl ?>" class="sidebar-brand-link">
      <div class="sidebar-brand-left">
        <img src="assets/img/Logo-1.webp" alt="PT Kala Kausa Prima" class="sidebar-brand-img" width="32" height="32">
        <span class="sidebar-brand-name">Kala Kausa Prima</span>
      </div>
      <div class="sidebar-brand-right">
        <span class="role-badge-sidebar">
          <?= $badgeLabel ?>
        </span>
      </div>
    </a>
    <button class="sidebar-close-btn" onclick="closeMobileSidebar()" aria-label="Close Sidebar">&times;</button>
  </div>

  <nav class="sidebar-nav" id="app-sidebar-nav">
    <?php if ($isClient): ?>
      <!-- CLIENT PORTAL NAVIGATION -->
      <div class="nav-section-title">Client Portal</div>

      <a href="<?= url('client-portal?tab=deliverables&portal=client&_slot=client') ?>" class="nav-link sidebar-link sidebar-menu-item <?= ($currentPage === 'client-portal' && (!isset($_GET['tab']) || $_GET['tab'] === 'deliverables')) ? 'active' : '' ?>">
        <span class="nav-icon-wrap"><?= renderItshoverIcon('deliverables', 24) ?></span>
        <span class="nav-label-text">Progress &amp; Deliverables</span>
      </a>

      <?php 
        $clientCId = current_user_client_id();
        $clientHasSosmed = $clientCId ? client_has_sosmed_service($clientCId) : true;
        if ($clientHasSosmed): 
      ?>
      <a href="<?= url('client-portal?tab=calendar&portal=client&_slot=client') ?>" class="nav-link sidebar-link sidebar-menu-item <?= ($currentPage === 'client-portal' && ($_GET['tab'] ?? '') === 'calendar') || in_array($currentPage, ['content-calendar', 'content-planner']) ? 'active' : '' ?>">
        <span class="nav-icon-wrap"><?= renderItshoverIcon('calendar', 24) ?></span>
        <span class="nav-label-text">Kalender Konten</span>
      </a>
      <?php endif; ?>

      <a href="<?= url('client-portal?tab=invoices&portal=client&_slot=client') ?>" class="nav-link sidebar-link sidebar-menu-item <?= ($currentPage === 'client-portal' && ($_GET['tab'] ?? '') === 'invoices') || in_array($currentPage, ['invoices', 'invoice-view']) ? 'active' : '' ?>">
        <span class="nav-icon-wrap"><?= renderItshoverIcon('invoices', 24) ?></span>
        <span class="nav-label-text">Tagihan / Invoices</span>
      </a>

      <a href="<?= url('client-portal?tab=reports&portal=client&_slot=client') ?>" class="nav-link sidebar-link sidebar-menu-item <?= ($currentPage === 'client-portal' && in_array(($_GET['tab'] ?? ''), ['reports', 'ads'])) || in_array($currentPage, ['reports', 'report-deck']) ? 'active' : '' ?>">
        <span class="nav-icon-wrap"><?= renderItshoverIcon('reports', 24) ?></span>
        <span class="nav-label-text">Laporan Performa</span>
      </a>

    <?php elseif ($isWorker): ?>
      <!-- WORKER & FREELANCER NAVIGATION (SMART SPECIALIZATION ADAPTIVE) -->
      <div class="nav-section-title">Workspace Tim</div>

      <a href="<?= url('tasks?_slot=internal') ?>" class="nav-link sidebar-link sidebar-menu-item <?= (in_array($currentPage, ['tasks', 'team-workspace']) && (empty($_GET['tab']) || $_GET['tab'] === 'tasks')) ? 'active' : '' ?>">
        <span class="nav-icon-wrap"><?= renderItshoverIcon('tasks', 24) ?></span>
        <span class="nav-label-text">Tugas Saya &amp; Progres</span>
      </a>

      <?php if (is_ads_specialist($currentUser)): ?>
        <a href="<?= url('tasks?tab=ads&_slot=internal') ?>" class="nav-link sidebar-link sidebar-menu-item <?= (in_array($currentPage, ['tasks', 'team-workspace']) && in_array(($_GET['tab'] ?? ''), ['ads', 'ads_tracker'])) ? 'active' : '' ?>">
          <span class="nav-icon-wrap"><?= renderItshoverIcon('ads', 24) ?></span>
          <span class="nav-label-text">Saldo Ads</span>
        </a>

        <a href="<?= url('reports?tab=ads_report&_slot=internal') ?>" class="nav-link sidebar-link sidebar-menu-item <?= in_array($currentPage, ['reports', 'reports-form', 'report-view', 'report-deck']) ? 'active' : '' ?>">
          <span class="nav-icon-wrap"><?= renderItshoverIcon('reports', 24) ?></span>
          <span class="nav-label-text">Report Ads</span>
        </a>
      <?php else: ?>
        <a href="<?= url('tasks?tab=work_recap&_slot=internal') ?>" class="nav-link sidebar-link sidebar-menu-item <?= ($currentPage === 'tasks' && ($_GET['tab'] ?? '') === 'work_recap') ? 'active' : '' ?>">
          <span class="nav-icon-wrap"><?= renderItshoverIcon('invoices', 24) ?></span>
          <span class="nav-label-text">Report Pekerjaan</span>
        </a>

        <?php 
          $workerUid = (int)($currentUser['id'] ?? 0);
          $hasSosmedProj = worker_has_sosmed_projects($workerUid);
          if ($hasSosmedProj): 
        ?>
        <a href="<?= url('content-calendar?_slot=internal') ?>" class="nav-link sidebar-link sidebar-menu-item <?= in_array($currentPage, ['content-calendar', 'content-planner']) ? 'active' : '' ?>">
          <span class="nav-icon-wrap"><?= renderItshoverIcon('calendar', 24) ?></span>
          <span class="nav-label-text">Kalender Konten</span>
        </a>
        <?php endif; ?>
      <?php endif; ?>

      <a href="<?= url('tasks?tab=profile&_slot=internal') ?>" class="nav-link sidebar-link sidebar-menu-item <?= (in_array($currentPage, ['tasks', 'team-workspace']) && ($_GET['tab'] ?? '') === 'profile') ? 'active' : '' ?>">
        <span class="nav-icon-wrap"><?= renderItshoverIcon('profile', 24) ?></span>
        <span class="nav-label-text">Profil Saya</span>
      </a>

    <?php else: ?>
      <!-- OWNER & ADMIN NAVIGATION (Minimalist 6-Pillar Architecture) -->
      <div class="nav-section-title">Menu Utama</div>

      <?php if ($isOwner): ?>
        <a href="<?= url('owner-dashboard?_slot=internal') ?>" class="nav-link sidebar-link sidebar-menu-item <?= $currentPage === 'owner-dashboard' ? 'active' : '' ?>">
          <span class="nav-icon-wrap"><?= renderItshoverIcon('dashboard', 24) ?></span>
          <span class="nav-label-text">Executive Dashboard</span>
        </a>
      <?php else: ?>
        <a href="<?= url('admin-dashboard?_slot=internal') ?>" class="nav-link sidebar-link sidebar-menu-item <?= $currentPage === 'admin-dashboard' ? 'active' : '' ?>">
          <span class="nav-icon-wrap"><?= renderItshoverIcon('dashboard', 24) ?></span>
          <span class="nav-label-text">Admin Dashboard</span>
        </a>
      <?php endif; ?>

      <div class="nav-section-title">Operasional &amp; Bisnis</div>

      <!-- 1. Tugas & Operasional -->
      <a href="<?= url('tasks?_slot=internal') ?>" class="nav-link sidebar-link sidebar-menu-item <?= in_array($currentPage, ['tasks', 'team-workspace', 'content-calendar', 'content-planner']) ? 'active' : '' ?>">
        <span class="nav-icon-wrap"><?= renderItshoverIcon('tasks', 24) ?></span>
        <span class="nav-label-text">Tugas &amp; Operasional</span>
      </a>

      <!-- 2. Klien & Tim -->
      <a href="<?= url('clients?_slot=internal') ?>" class="nav-link sidebar-link sidebar-menu-item <?= in_array($currentPage, ['clients', 'team', 'employees-management', 'client-portal']) ? 'active' : '' ?>">
        <span class="nav-icon-wrap"><?= renderItshoverIcon('clients', 24) ?></span>
        <span class="nav-label-text">Klien &amp; Tim</span>
      </a>

      <!-- 3. Keuangan & Invoice -->
      <a href="<?= url('invoices?_slot=internal') ?>" class="nav-link sidebar-link sidebar-menu-item <?= in_array($currentPage, ['invoices', 'invoice-view', 'expenses', 'salaries', 'salary-view']) ? 'active' : '' ?>">
        <span class="nav-icon-wrap"><?= renderItshoverIcon('invoices', 24) ?></span>
        <span class="nav-label-text">Keuangan &amp; Invoice</span>
      </a>

      <!-- 4. Laporan Klien -->
      <a href="<?= url('reports?_slot=internal') ?>" class="nav-link sidebar-link sidebar-menu-item <?= in_array($currentPage, ['reports', 'reports-form', 'report-view', 'report-deck']) ? 'active' : '' ?>">
        <span class="nav-icon-wrap"><?= renderItshoverIcon('reports', 24) ?></span>
        <span class="nav-label-text">Laporan Klien</span>
      </a>

      <?php if ($isOwner || $isAdmin): ?>
        <div class="nav-section-title">Sistem</div>
        <a href="<?= url('settings?_slot=internal') ?>" class="nav-link sidebar-link sidebar-menu-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
          <span class="nav-icon-wrap"><?= renderItshoverIcon('settings', 24) ?></span>
          <span class="nav-label-text">Pengaturan Agensi</span>
        </a>
      <?php endif; ?>
    <?php endif; ?>

    <!-- Dedicated Logout Menu Link -->
    <?php
      $sbLogoutContext = is_client() ? 'client' : 'internal';
    ?>
    <a href="<?= url('logout') ?>?context=<?= $sbLogoutContext ?>" onclick="if(window.handleKalaLogout){handleKalaLogout(this.href);return false;}else{sessionStorage.removeItem('kala_tab_uid');}" class="nav-link sidebar-link sidebar-menu-item nav-link-logout">
      <span class="nav-icon-wrap"><?= renderItshoverIcon('logout', 24) ?></span>
      <span class="nav-label-text">Keluar (Logout)</span>
    </a>
  </nav>

  <!-- Sidebar Footer: PWA Button + Nebula Theme Switcher + Profile Card in 1 Flex Column -->
  <div class="sidebar-footer" id="app-sidebar-footer">
    <!-- PWA Install Action Button (Only visible on supporting mobile/desktop browsers) -->
    <div id="pwa-install-wrap" class="pwa-install-wrap">
      <button type="button" id="btn-pwa-install" class="btn btn-secondary btn-sm btn-pwa-install">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        <span>Pasang Portal Kala</span>
      </button>
    </div>

    <div class="sidebar-theme-toggle-wrap">
      <button type="button" class="sidebar-theme-toggle" onclick="toggleTheme()" aria-label="Beralih Mode Tema (Terang / Gelap)" title="Beralih Mode Tema (Terang / Gelap)">
        <div class="theme-switch-pill" aria-hidden="true">
          <div class="track-icon-ambient track-icon-ambient-left">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="4.3"></circle>
              <line x1="12" y1="2" x2="12" y2="4.5"></line>
              <line x1="12" y1="19.5" x2="12" y2="22"></line>
              <line x1="2" y1="12" x2="4.5" y2="12"></line>
              <line x1="19.5" y1="12" x2="22" y2="12"></line>
            </svg>
          </div>
          <div class="track-icon-ambient track-icon-ambient-right">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14.5 3.5A8.5 8.5 0 1 0 20.5 14.5 7.2 7.2 0 0 1 14.5 3.5z"></path>
              <path d="M18.5 3.5v3.2m-1.6-1.6h3.2" stroke-width="1.8"></path>
            </svg>
          </div>
          <div class="theme-switch-knob">
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

    document.body.classList.remove('sidebar-collapsed');
    try { localStorage.removeItem('kala_sidebar_collapsed'); } catch(e) {}

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        window.closeMobileSidebar();
      }
    });
  </script>
</aside>

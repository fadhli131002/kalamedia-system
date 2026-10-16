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

  <nav class="sidebar-nav">

    <?php if ($isClient): ?>
      <!-- CLIENT PORTAL NAVIGATION -->
      <div class="nav-section-title">Client Portal</div>

      <a href="<?= url('client-portal?tab=deliverables&portal=client&_slot=client') ?>" class="nav-link <?= ($currentPage === 'client-portal' && (!isset($_GET['tab']) || $_GET['tab'] === 'deliverables')) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
        <span>Progress & Deliverables</span>
      </a>

      <a href="<?= url('client-portal?tab=calendar&portal=client&_slot=client') ?>" class="nav-link <?= ($currentPage === 'client-portal' && ($_GET['tab'] ?? '') === 'calendar') || in_array($currentPage, ['content-calendar', 'content-planner']) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span>Kalender Konten</span>
      </a>

      <a href="<?= url('client-portal?tab=invoices&portal=client&_slot=client') ?>" class="nav-link <?= ($currentPage === 'client-portal' && ($_GET['tab'] ?? '') === 'invoices') || in_array($currentPage, ['invoices', 'invoice-view']) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        <span>Tagihan / Invoices</span>
      </a>

      <a href="<?= url('client-portal?tab=reports&portal=client&_slot=client') ?>" class="nav-link <?= ($currentPage === 'client-portal' && in_array(($_GET['tab'] ?? ''), ['reports', 'ads'])) || in_array($currentPage, ['reports', 'report-deck']) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        <span>Laporan Performa</span>
      </a>

    <?php elseif ($isWorker): ?>
      <!-- WORKER & FREELANCER NAVIGATION -->
      <div class="nav-section-title">Workspace Tim</div>

      <a href="<?= url('tasks') ?>" class="nav-link <?= in_array($currentPage, ['tasks', 'team-workspace']) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <span>Tugas Saya & Progres</span>
      </a>

      <a href="<?= url('content-calendar') ?>" class="nav-link <?= in_array($currentPage, ['content-calendar', 'content-planner']) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span>Kalender Konten</span>
      </a>

    <?php else: ?>
      <!-- OWNER & ADMIN NAVIGATION -->
      <div class="nav-section-title">Menu Utama</div>

      <?php if ($isOwner): ?>
        <a href="<?= url('owner-dashboard') ?>" class="nav-link <?= $currentPage === 'owner-dashboard' ? 'active' : '' ?>">
          <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
          <span>Executive Dashboard</span>
        </a>
      <?php else: ?>
        <a href="<?= url('admin-dashboard') ?>" class="nav-link <?= $currentPage === 'admin-dashboard' ? 'active' : '' ?>">
          <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
          <span>Admin Dashboard</span>
        </a>
      <?php endif; ?>

      <div class="nav-section-title">Manajemen Operasional</div>

      <!-- Task Assignment & SDM Management -->
      <a href="<?= url('tasks') ?>" class="nav-link <?= in_array($currentPage, ['tasks', 'team-workspace']) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <span>Pembagian Tugas Tim</span>
      </a>

      <!-- Team & Employees Management -->
      <a href="<?= url('team') ?>" class="nav-link <?= in_array($currentPage, ['team', 'employees-management']) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span>Tim & Karyawan</span>
      </a>

      <!-- Client Portal Preview for Admin/Owner -->
      <a href="<?= url('client-portal') ?>" class="nav-link <?= $currentPage === 'client-portal' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
        <span>Portal Klien (Preview)</span>
      </a>

      <a href="<?= url('invoices') ?>" class="nav-link <?= in_array($currentPage, ['invoices', 'invoice-view']) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        <span>Invoice & Penagihan</span>
      </a>

      <a href="<?= url('expenses') ?>" class="nav-link <?= $currentPage === 'expenses' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><circle cx="17" cy="15" r="1.5"/></svg>
        <span>Pengeluaran Operasional</span>
      </a>

      <a href="<?= url('salaries') ?>" class="nav-link <?= in_array($currentPage, ['salaries', 'salary-view']) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><line x1="6" y1="12" x2="6.01" y2="12"/><line x1="18" y1="12" x2="18.01" y2="12"/></svg>
        <span>Penggajian Karyawan</span>
      </a>

      <a href="<?= url('clients') ?>" class="nav-link <?= $currentPage === 'clients' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/></svg>
        <span>Klien & Proyek</span>
      </a>

      <a href="<?= url('reports') ?>" class="nav-link <?= in_array($currentPage, ['reports', 'reports-form', 'report-view', 'report-deck']) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        <span>Laporan Klien</span>
      </a>

      <a href="<?= url('content-calendar') ?>" class="nav-link <?= in_array($currentPage, ['content-calendar', 'content-planner']) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span>Kalender Konten</span>
      </a>

      <?php if ($isOwner): ?>
        <div class="nav-section-title">Konfigurasi</div>
        <a href="<?= url('settings') ?>" class="nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
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
    <a href="<?= url('logout') ?>?context=<?= $sbLogoutContext ?>&portal=<?= $sbLogoutContext ?>&_slot=<?= $sbLogoutContext ?>" class="nav-link nav-link-logout">
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
</aside>

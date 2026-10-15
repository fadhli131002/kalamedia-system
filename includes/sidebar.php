<?php
/**
 * Kalamedia Sidebar Navigation - Clean Minimalist Text Architecture (No Icons)
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
    'admin' => 'ADMIN / PM',
    'karyawan' => 'KARYAWAN',
    'freelancer' => 'FREELANCER',
    'client' => 'CLIENT'
];
$badgeLabel = $roleLabelMap[$role] ?? strtoupper($role);

$badgeColors = [
    'owner' => ['bg' => '#FEF3F2', 'text' => '#B42318'],
    'admin' => ['bg' => '#EFF8FF', 'text' => '#175CD3'],
    'karyawan' => ['bg' => '#ECFDF3', 'text' => '#027A48'],
    'freelancer' => ['bg' => '#F4F3FF', 'text' => '#5925DC'],
    'client' => ['bg' => '#F8F9FC', 'text' => '#344054']
];
$currentBadge = $badgeColors[$role] ?? ['bg' => '#F2F4F7', 'text' => '#344054'];
?>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeMobileSidebar()"></div>

<aside class="sidebar" id="app-sidebar">
  <div class="sidebar-header">
    <a href="<?= $homeUrl ?>" style="display: flex; align-items: center; justify-content: space-between; width: 100%; text-decoration: none; color: inherit; gap: 8px;">
      <div style="display: flex; align-items: center; gap: 9px; min-width: 0;">
        <img src="assets/img/Logo-1.webp" alt="PT Kala Kausa Prima" class="sidebar-brand-img" style="height: 30px; width: 30px; object-fit: contain; flex-shrink: 0;">
        <span style="font-weight: 800; font-size: 13px; color: #101828; letter-spacing: -0.2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Kala Kausa Prima</span>
      </div>
      <span class="role-badge-sidebar" style="font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 2px 6px; border-radius: 4px; background: <?= $currentBadge['bg'] ?>; color: <?= $currentBadge['text'] ?>; white-space: nowrap; flex-shrink: 0;">
        <?= $badgeLabel ?>
      </span>
    </a>
    <button class="sidebar-close-btn" onclick="closeMobileSidebar()" aria-label="Close Sidebar">&times;</button>
  </div>

  <nav class="sidebar-nav">

    <?php if ($isClient): ?>
      <!-- CLIENT PORTAL NAVIGATION -->
      <div class="nav-section-title">Client Portal</div>

      <a href="<?= url('client-portal') ?>" class="nav-link <?= $currentPage === 'client-portal' ? 'active' : '' ?>">
        <span>Progress & Deliverables</span>
      </a>

      <a href="<?= url('content-calendar') ?>" class="nav-link <?= in_array($currentPage, ['content-calendar', 'content-planner']) ? 'active' : '' ?>">
        <span>Kalender Konten</span>
      </a>

      <a href="<?= url('invoices') ?>" class="nav-link <?= in_array($currentPage, ['invoices', 'invoice-view']) ? 'active' : '' ?>">
        <span>Tagihan / Invoices</span>
      </a>

      <a href="<?= url('reports') ?>" class="nav-link <?= in_array($currentPage, ['reports', 'report-deck']) ? 'active' : '' ?>">
        <span>Laporan Performa</span>
      </a>

    <?php elseif ($isWorker): ?>
      <!-- WORKER & FREELANCER NAVIGATION -->
      <div class="nav-section-title">Workspace Tim</div>

      <a href="<?= url('tasks') ?>" class="nav-link <?= in_array($currentPage, ['tasks', 'team-workspace']) ? 'active' : '' ?>">
        <span>Tugas Saya & Progres</span>
      </a>

      <a href="<?= url('content-calendar') ?>" class="nav-link <?= in_array($currentPage, ['content-calendar', 'content-planner']) ? 'active' : '' ?>">
        <span>Kalender Konten</span>
      </a>

    <?php else: ?>
      <!-- OWNER & ADMIN NAVIGATION -->
      <div class="nav-section-title">Menu Utama</div>

      <?php if ($isOwner): ?>
        <a href="<?= url('owner-dashboard') ?>" class="nav-link <?= $currentPage === 'owner-dashboard' ? 'active' : '' ?>">
          <span>Executive Dashboard</span>
        </a>
      <?php else: ?>
        <a href="<?= url('admin-dashboard') ?>" class="nav-link <?= $currentPage === 'admin-dashboard' ? 'active' : '' ?>">
          <span>Admin Dashboard</span>
        </a>
      <?php endif; ?>

      <div class="nav-section-title">Manajemen Operasional</div>

      <!-- Task Assignment & SDM Management -->
      <a href="<?= url('tasks') ?>" class="nav-link <?= in_array($currentPage, ['tasks', 'team-workspace']) ? 'active' : '' ?>">
        <span>Pembagian Tugas Tim</span>
      </a>

      <!-- Client Portal Preview for Admin/Owner -->
      <a href="<?= url('client-portal') ?>" class="nav-link <?= $currentPage === 'client-portal' ? 'active' : '' ?>">
        <span>Portal Klien (Preview)</span>
      </a>

      <a href="<?= url('invoices') ?>" class="nav-link <?= in_array($currentPage, ['invoices', 'invoice-view']) ? 'active' : '' ?>">
        <span>Invoice & Penagihan</span>
      </a>

      <a href="<?= url('expenses') ?>" class="nav-link <?= $currentPage === 'expenses' ? 'active' : '' ?>">
        <span>Pengeluaran Operasional</span>
      </a>

      <a href="<?= url('salaries') ?>" class="nav-link <?= in_array($currentPage, ['salaries', 'salary-view']) ? 'active' : '' ?>">
        <span>Penggajian Karyawan</span>
      </a>

      <a href="<?= url('clients') ?>" class="nav-link <?= $currentPage === 'clients' ? 'active' : '' ?>">
        <span>Klien & Proyek</span>
      </a>

      <a href="<?= url('reports') ?>" class="nav-link <?= in_array($currentPage, ['reports', 'reports-form', 'report-view', 'report-deck']) ? 'active' : '' ?>">
        <span>Laporan Klien</span>
      </a>

      <a href="<?= url('content-calendar') ?>" class="nav-link <?= in_array($currentPage, ['content-calendar', 'content-planner']) ? 'active' : '' ?>">
        <span>Kalender Konten</span>
      </a>

      <?php if ($isOwner): ?>
        <div class="nav-section-title">Konfigurasi</div>
        <a href="<?= url('settings') ?>" class="nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
          <span>Pengaturan Agensi</span>
        </a>
      <?php endif; ?>
    <?php endif; ?>

    <!-- Dedicated Logout Menu Link -->
    <div class="nav-section-title">Sesi Akun</div>
    <a href="<?= url('logout') ?>" class="nav-link" style="color: #B42318; font-weight: 700;">
      <span>Keluar (Logout)</span>
    </a>

  </nav>

  <div class="sidebar-user">
    <div class="user-profile">
      <?php
        if ($isOwner) {
            $sidebarProfileName = 'Owner';
            $sidebarAvatar = 'OW';
        } elseif ($role === 'admin') {
            $sidebarProfileName = 'Admin';
            $sidebarAvatar = 'AD';
        } else {
            $sidebarProfileName = htmlspecialchars($currentUser['name'] ?? 'User Kalamedia');
            $sidebarAvatar = strtoupper(substr($currentUser['name'] ?? 'User', 0, 2));
        }
      ?>
      <div class="avatar">
        <?= $sidebarAvatar ?>
      </div>
      <div class="user-details">
        <h4><?= $sidebarProfileName ?></h4>
        <span class="role-badge" style="background: <?= $currentBadge['bg'] ?>; color: <?= $currentBadge['text'] ?>;">
          <?= $badgeLabel ?>
        </span>
      </div>
    </div>
    <a href="<?= url('logout') ?>" class="btn btn-secondary btn-sm" style="color: #B42318; border-color: #FECDCA; background: #FEF3F2; font-size: 11px; font-weight: 700; text-decoration: none; padding: 5px 12px; border-radius: 6px;">
      Logout
    </a>
  </div>
</aside>

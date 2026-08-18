<?php
/**
 * Kalamedia Sidebar Navigation - Clean Role-Based Architecture
 */
$currentUser = current_user();
$isOwner = is_owner();
$currentPage = $GLOBALS['currentPage'] ?? $_GET['page'] ?? ($isOwner ? 'owner-dashboard' : 'admin-dashboard');
$homeUrl = $isOwner ? url('owner-dashboard') : url('admin-dashboard');
?>

<aside class="sidebar">
  <div class="sidebar-header">
    <a href="<?= $homeUrl ?>" style="display: flex; align-items: center; justify-content: space-between; width: 100%; text-decoration: none; color: inherit; gap: 8px;">
      <img src="assets/Jpg/Asset 3.png" alt="Kala Media Creative Agency" class="sidebar-brand-img" style="height: 23px; width: auto; max-width: 120px; object-fit: contain;">
      <span class="role-badge-sidebar" style="font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 2px 6px; border-radius: 4px; background: #F2F4F7; color: <?= $isOwner ? '#B45309' : '#101828' ?>; white-space: nowrap;">
        <?= $isOwner ? 'OWNER' : 'FINANCE' ?>
      </span>
    </a>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-title">Menu Utama</div>

    <?php if ($isOwner): ?>
      <!-- Owner Dashboard -->
      <a href="<?= url('owner-dashboard') ?>" class="nav-link <?= $currentPage === 'owner-dashboard' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="7" height="9"></rect>
          <rect x="14" y="3" width="7" height="5"></rect>
          <rect x="14" y="12" width="7" height="9"></rect>
          <rect x="3" y="16" width="7" height="5"></rect>
        </svg>
        <span>Dashboard</span>
      </a>
    <?php else: ?>
      <!-- Finance Dashboard -->
      <a href="<?= url('admin-dashboard') ?>" class="nav-link <?= $currentPage === 'admin-dashboard' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="7" height="9"></rect>
          <rect x="14" y="3" width="7" height="5"></rect>
          <rect x="14" y="12" width="7" height="9"></rect>
          <rect x="3" y="16" width="7" height="5"></rect>
        </svg>
        <span>Dashboard</span>
      </a>
    <?php endif; ?>

    <div class="nav-section-title">Manajemen Operasional</div>

    <a href="<?= url('invoices') ?>" class="nav-link <?= in_array($currentPage, ['invoices', 'invoice-view']) ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
        <polyline points="14 2 14 8 20 8"></polyline>
        <line x1="16" y1="13" x2="8" y2="13"></line>
        <line x1="16" y1="17" x2="8" y2="17"></line>
        <polyline points="10 9 9 9 8 9"></polyline>
      </svg>
      <span>Invoice & Penagihan</span>
    </a>

    <a href="<?= url('expenses') ?>" class="nav-link <?= $currentPage === 'expenses' ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="12" y1="1" x2="12" y2="23"></line>
        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
      </svg>
      <span>Pengeluaran Operasional</span>
    </a>

    <a href="<?= url('salaries') ?>" class="nav-link <?= in_array($currentPage, ['salaries', 'salary-view']) ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="2" y="4" width="20" height="16" rx="2"></rect>
        <path d="M7 15h0M2 9.5h20"></path>
        <circle cx="16" cy="14" r="2"></circle>
      </svg>
      <span>Penggajian Karyawan</span>
    </a>

    <a href="<?= url('clients') ?>" class="nav-link <?= $currentPage === 'clients' ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
        <circle cx="9" cy="7" r="4"></circle>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
      </svg>
      <span>Klien & Proyek</span>
    </a>

    <a href="<?= url('content-calendar') ?>" class="nav-link <?= in_array($currentPage, ['content-calendar', 'content-planner']) ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
        <line x1="16" y1="2" x2="16" y2="6"></line>
        <line x1="8" y1="2" x2="8" y2="6"></line>
        <line x1="3" y1="10" x2="21" y2="10"></line>
      </svg>
      <span>Kalender Konten</span>
    </a>

    <?php if ($isOwner): ?>
      <div class="nav-section-title">Konfigurasi</div>
      <a href="<?= url('settings') ?>" class="nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="3"></circle>
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
        </svg>
        <span>Pengaturan Agensi</span>
      </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-user">
    <div class="user-profile">
      <div class="avatar">
        <?= strtoupper(substr($currentUser['name'] ?? ($isOwner ? 'Owner' : 'Finance'), 0, 2)) ?>
      </div>
      <div class="user-details">
        <h4><?= htmlspecialchars($currentUser['name'] ?? ($isOwner ? 'Owner Kala' : 'Finance Kala')) ?></h4>
        <span class="role-badge <?= $isOwner ? 'role-owner' : 'role-admin' ?>">
          <?= $isOwner ? 'Owner' : 'Finance' ?>
        </span>
      </div>
    </div>
    <a href="<?= url('logout') ?>" class="btn-logout" title="Keluar / Logout">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
        <polyline points="16 17 21 12 16 7"></polyline>
        <line x1="21" y1="12" x2="9" y2="12"></line>
      </svg>
    </a>
  </div>
</aside>

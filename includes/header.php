<?php
/**
 * Kalamedia Top Navigation Bar - Untitled UI Portal Architecture
 */
$currentUser = current_user();
$currentPage = $GLOBALS['currentPage'] ?? $_GET['page'] ?? '';
if (empty($currentPage)) {
    $currentPage = home_url_for_role();
}
$isOwnerPortal = is_owner();
$isAdminUser = is_admin();
$isClientUser = is_client();

$pageMeta = [
    'owner-dashboard' => ['name' => 'Dashboard Eksekutif', 'parent' => 'Kalamedia'],
    'admin-dashboard' => ['name' => 'Dashboard Operasional', 'parent' => 'Kalamedia'],
    'client-portal' => ['name' => 'Portal Klien & Progres Deliverables', 'parent' => 'Client Portal'],
    'tasks' => ['name' => 'Manajemen Tugas & Workspace Tim', 'parent' => 'Operasional'],
    'team-workspace' => ['name' => 'Workspace Tim', 'parent' => 'Tim'],
    'invoices' => ['name' => 'Invoice & Penagihan', 'parent' => 'Operasional'],
    'invoice-view' => ['name' => 'Detail Tagihan', 'parent' => 'Invoice'],
    'expenses' => ['name' => 'Pengeluaran Operasional', 'parent' => 'Operasional'],
    'salaries' => ['name' => 'Penggajian Karyawan', 'parent' => 'Operasional'],
    'salary-view' => ['name' => 'Slip Gaji Karyawan', 'parent' => 'Payroll'],
    'clients' => ['name' => 'Klien & Proyek', 'parent' => 'Operasional'],
    'reports' => ['name' => 'Laporan Klien', 'parent' => 'Operasional'],
    'reports-form' => ['name' => 'Form Laporan Klien', 'parent' => 'Laporan'],
    'report-view' => ['name' => 'Presentasi Laporan', 'parent' => 'Laporan'],
    'content-calendar' => ['name' => 'Kalender Konten', 'parent' => 'Produksi'],
    'content-planner' => ['name' => 'Kalender Konten', 'parent' => 'Produksi'],
    'settings' => ['name' => 'Pengaturan Agensi', 'parent' => 'Sistem'],
];
$currentPageInfo = $pageMeta[$currentPage] ?? ['name' => ucwords(str_replace('-', ' ', $currentPage)), 'parent' => 'Kalamedia'];
?>

<?php
  $userRole = current_user_role();
  $isWorker = ($userRole === 'karyawan' || $userRole === 'freelancer');
?>

<?php if ($isWorker): ?>
<header class="topbar appify-topbar" style="background: #FFFFFF; border-bottom: 1px solid #F0F1F7; padding: 12px 16px; min-height: 60px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 10px rgba(26,22,43,0.02);">
  <div style="display: flex; align-items: center; gap: 12px; min-width: 0;">
    <button class="sidebar-toggle-btn" id="sidebar-toggle" onclick="toggleMobileSidebar()" aria-label="Buka Menu Navigasi" title="Buka Menu" style="background: #FFFFFF; border: 1px solid #F0F1F7; border-radius: 12px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #1A162B; flex-shrink: 0; box-shadow: 0 2px 6px rgba(26,22,43,0.03);">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      </svg>
    </button>
    <div style="min-width: 0;">
      <h1 style="font-size: 18px; font-weight: 800; color: #1A162B; margin: 0; line-height: 1.2; letter-spacing: -0.2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
        Halo, <?= htmlspecialchars($currentUser['name'] ?? 'Tim Kalamedia') ?>
      </h1>
      <div style="font-size: 12px; color: #7B7890; margin-top: 2px;">
        <?= date('d M Y') ?>
      </div>
    </div>
  </div>

  <div style="flex-shrink: 0;">
    <span style="display: inline-flex; align-items: center; background: #F2EFFF; color: #5B3DF5; font-size: 11.5px; font-weight: 700; padding: 5px 12px; border-radius: 20px; border: 1px solid rgba(91, 61, 245, 0.15); letter-spacing: 0.2px;">
      <?= $userRole === 'freelancer' ? 'Freelancer' : 'Staff' ?>
    </span>
  </div>
</header>
<?php else: ?>
<header class="topbar">
  <div class="greeting-text">
    <button class="sidebar-toggle-btn" id="sidebar-toggle" onclick="toggleMobileSidebar()" aria-label="Buka Menu Navigasi" title="Buka Menu">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      </svg>
    </button>
    <div class="greeting-info">
      <div class="topbar-breadcrumb">
        <span class="breadcrumb-root"><?= htmlspecialchars($currentPageInfo['parent']) ?></span>
        <span class="breadcrumb-sep">/</span>
        <span class="breadcrumb-current"><?= htmlspecialchars($currentPageInfo['name']) ?></span>
      </div>
      <?php
        if ($userRole === 'owner') {
            $greetingDisplayName = 'Owner';
        } elseif ($userRole === 'admin') {
            $greetingDisplayName = 'Admin';
        } else {
            $greetingDisplayName = htmlspecialchars($currentUser['name'] ?? 'User Kalamedia');
        }
      ?>
      <h1 class="greeting-heading">
        <span class="greeting-name">Halo, <?= $greetingDisplayName ?></span>
        <span class="greeting-date">(<?= date('d M Y') ?>)</span>
      </h1>
    </div>
  </div>

  <div class="topbar-actions" style="display: flex; align-items: center; gap: 8px;">
    <!-- Date Filter on Owner Dashboard -->
    <?php if ($currentPage === 'owner-dashboard'): ?>
      <div class="date-filter-group">
        <button class="filter-btn active" onclick="changeTimeRange('month', this)">Bulan Ini</button>
        <button class="filter-btn" onclick="changeTimeRange('quarter', this)">Kuartal Ini</button>
        <button class="filter-btn" onclick="changeTimeRange('year', this)">Tahun Ini</button>
        <button class="filter-btn" onclick="changeTimeRange('all', this)">Semua</button>
      </div>
    <?php endif; ?>

    <!-- Role Context Actions -->
    <?php if (($userRole === 'owner' || $userRole === 'admin') && !$isClientUser && !in_array($userRole, ['client', 'karyawan', 'freelancer'])): ?>
      <button class="btn btn-primary btn-sm btn-create-invoice" onclick="openModal('modal-create-invoice')">
        <span>+ Buat Invoice</span>
      </button>
    <?php elseif ($isClientUser): ?>
      <span style="font-size: 11px; font-weight: 700; color: #101828; background: #F2F4F7; padding: 6px 12px; border-radius: 20px; border: 1px solid var(--border-color);">
        Portal Klien Terverifikasi
      </span>
    <?php endif; ?>

  </div>
</header>
<?php endif; ?>

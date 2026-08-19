<?php
/**
 * Kalamedia Top Navigation Bar - Untitled UI Portal Architecture
 */
$currentUser = current_user();
$currentPage = $GLOBALS['currentPage'] ?? $_GET['page'] ?? ($currentUser && $currentUser['role'] === 'owner' ? 'owner-dashboard' : 'admin-dashboard');
$isOwnerPortal = is_owner();

$pageMeta = [
    'owner-dashboard' => ['name' => 'Dashboard Eksekutif', 'parent' => 'Kalamedia'],
    'admin-dashboard' => ['name' => 'Dashboard Operasional', 'parent' => 'Kalamedia'],
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

<header class="topbar">
  <div class="greeting-text">
    <button class="btn btn-secondary btn-sm" id="sidebar-toggle" style="display:none; padding:6px 10px;" onclick="document.querySelector('.sidebar').classList.toggle('open')">
      &#9776;
    </button>
    <div style="display: flex; flex-direction: column; gap: 3px;">
      <div class="topbar-breadcrumb">
        <span class="breadcrumb-root"><?= htmlspecialchars($currentPageInfo['parent']) ?></span>
        <span class="breadcrumb-sep">/</span>
        <span class="breadcrumb-current"><?= htmlspecialchars($currentPageInfo['name']) ?></span>
      </div>
      <h1 style="font-size: 15px; font-weight: 700; color: #101828; margin: 0; display: flex; align-items: center; gap: 8px;">
        Halo, <?= htmlspecialchars($currentUser['name'] ?? ($isOwnerPortal ? 'Owner Kala' : 'Finance Kala')) ?> 👋
        <span style="font-size: 12px; font-weight: 500; color: var(--text-secondary);">
          (<?= date('l, d F Y') ?>)
        </span>
      </h1>
    </div>
  </div>

  <div class="topbar-actions">
    <!-- Date Filter on Owner Dashboard -->
    <?php if ($currentPage === 'owner-dashboard'): ?>
      <div class="date-filter-group">
        <button class="filter-btn active" onclick="changeTimeRange('month', this)">Bulan Ini</button>
        <button class="filter-btn" onclick="changeTimeRange('quarter', this)">Kuartal Ini</button>
        <button class="filter-btn" onclick="changeTimeRange('year', this)">Tahun Ini</button>
        <button class="filter-btn" onclick="changeTimeRange('all', this)">Semua</button>
      </div>
    <?php endif; ?>

    <!-- Create Invoice Button (Primary CTA) -->
    <button class="btn btn-primary btn-sm" onclick="openModal('modal-create-invoice')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <line x1="12" y1="5" x2="12" y2="19"></line>
        <line x1="5" y1="12" x2="19" y2="12"></line>
      </svg>
      <span>Buat Invoice</span>
    </button>
  </div>
</header>

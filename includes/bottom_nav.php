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
<nav class="mobile-bottom-nav" id="mobile-bottom-nav" aria-label="Navigasi Bawah Mobile">
  <?php if ($isClient): 
    $clientProfileId = (int)($currentUser['client_id'] ?? current_user_client_id());
    $clientHasSosmed = $clientProfileId > 0 ? client_has_sosmed_service($clientProfileId) : true;
  ?>
    <!-- CLIENT PORTAL BOTTOM NAV -->
    <a href="<?= url('client-portal?tab=deliverables&portal=client&_slot=client') ?>" class="mob-nav-item <?= ($currentPage === 'client-portal' && (!isset($_GET['tab']) || $_GET['tab'] === 'deliverables')) ? 'active' : '' ?>">
      <div class="mob-nav-icon">
        <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
      </div>
      <span class="mob-nav-label">Deliverable</span>
    </a>

    <?php if ($clientHasSosmed): ?>
      <a href="<?= url('client-portal?tab=calendar&portal=client&_slot=client') ?>" class="mob-nav-item <?= ($currentPage === 'client-portal' && ($_GET['tab'] ?? '') === 'calendar') || in_array($currentPage, ['content-calendar', 'content-planner']) ? 'active' : '' ?>">
        <div class="mob-nav-icon">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <span class="mob-nav-label">Kalender</span>
      </a>
    <?php endif; ?>

    <a href="<?= url('client-portal?tab=invoices&portal=client&_slot=client') ?>" class="mob-nav-item <?= ($currentPage === 'client-portal' && ($_GET['tab'] ?? '') === 'invoices') || in_array($currentPage, ['invoices', 'invoice-view']) ? 'active' : '' ?>">
      <div class="mob-nav-icon">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      </div>
      <span class="mob-nav-label">Tagihan</span>
    </a>

    <a href="<?= url('client-portal?tab=reports&portal=client&_slot=client') ?>" class="mob-nav-item <?= ($currentPage === 'client-portal' && in_array(($_GET['tab'] ?? ''), ['reports', 'ads'])) || in_array($currentPage, ['reports', 'report-deck']) ? 'active' : '' ?>">
      <div class="mob-nav-icon">
        <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      </div>
      <span class="mob-nav-label">Laporan</span>
    </a>

    <button type="button" class="mob-nav-item" onclick="toggleMobileSidebar()" aria-label="Buka Menu Lainnya">
      <div class="mob-nav-icon">
        <svg viewBox="0 0 24 24"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
      </div>
      <span class="mob-nav-label">Menu</span>
    </button>

  <?php elseif ($isWorker): 
    $isAdsSpecialistUser = is_ads_specialist($currentUser);
    $workerHasSosmed = worker_has_sosmed_projects((int)($currentUser['id'] ?? 0));
  ?>
    <!-- TIM ADS / FREELANCER / KARYAWAN BOTTOM NAV -->
    <a href="<?= url('tasks?_slot=internal') ?>" class="mob-nav-item <?= in_array($currentPage, ['tasks', 'team-workspace']) && (empty($_GET['tab']) || $_GET['tab'] === 'tasks') ? 'active' : '' ?>">
      <div class="mob-nav-icon">
        <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      </div>
      <span class="mob-nav-label">Tugas Saya</span>
    </a>

    <?php if ($isAdsSpecialistUser): ?>
      <!-- ORANG ADS: Saldo Ads + Report Ads (Kalender Konten Dihapus) -->
      <a href="<?= url('tasks?tab=ads_tracker&_slot=internal') ?>" class="mob-nav-item <?= ($currentPage === 'tasks' && ($_GET['tab'] ?? '') === 'ads_tracker') ? 'active' : '' ?>">
        <div class="mob-nav-icon">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="12 6 12 12 16 14"/></svg>
        </div>
        <span class="mob-nav-label">Saldo Ads</span>
      </a>

      <a href="<?= url('reports?tab=ads_report&_slot=internal') ?>" class="mob-nav-item <?= in_array($currentPage, ['reports', 'report-deck']) ? 'active' : '' ?>">
        <div class="mob-nav-icon">
          <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        </div>
        <span class="mob-nav-label">Report Ads</span>
      </a>
    <?php else: ?>
      <!-- TIM KREATIF: Rekap Kerja Bulanan / Per Proyek + Kalender (Jika ada Sosmed) -->
      <a href="<?= url('tasks?tab=recap&_slot=internal') ?>" class="mob-nav-item <?= ($currentPage === 'tasks' && ($_GET['tab'] ?? '') === 'recap') ? 'active' : '' ?>">
        <div class="mob-nav-icon">
          <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        </div>
        <span class="mob-nav-label">Rekap Kerja</span>
      </a>

      <?php if ($workerHasSosmed): ?>
        <a href="<?= url('content-calendar?_slot=internal') ?>" class="mob-nav-item <?= in_array($currentPage, ['content-calendar', 'content-planner']) ? 'active' : '' ?>">
          <div class="mob-nav-icon">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          </div>
          <span class="mob-nav-label">Kalender</span>
        </a>
      <?php endif; ?>
    <?php endif; ?>

    <button type="button" class="mob-nav-item" onclick="toggleMobileSidebar()" aria-label="Buka Menu Lainnya">
      <div class="mob-nav-icon">
        <svg viewBox="0 0 24 24"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
      </div>
      <span class="mob-nav-label">Menu</span>
    </button>

  <?php else: ?>
    <!-- OWNER & ADMIN BOTTOM NAV -->
    <a href="<?= $isOwner ? url('owner-dashboard?_slot=internal') : url('admin-dashboard?_slot=internal') ?>" class="mob-nav-item <?= in_array($currentPage, ['owner-dashboard', 'admin-dashboard']) ? 'active' : '' ?>">
      <div class="mob-nav-icon">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
      </div>
      <span class="mob-nav-label">Dashboard</span>
    </a>

    <a href="<?= url('tasks?_slot=internal') ?>" class="mob-nav-item <?= in_array($currentPage, ['tasks', 'team-workspace']) ? 'active' : '' ?>">
      <div class="mob-nav-icon">
        <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      </div>
      <span class="mob-nav-label">Tugas Tim</span>
    </a>

    <a href="<?= url('clients?_slot=internal') ?>" class="mob-nav-item <?= $currentPage === 'clients' ? 'active' : '' ?>">
      <div class="mob-nav-icon">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <span class="mob-nav-label">Klien</span>
    </a>

    <a href="<?= url('invoices?_slot=internal') ?>" class="mob-nav-item <?= in_array($currentPage, ['invoices', 'invoice-view']) ? 'active' : '' ?>">
      <div class="mob-nav-icon">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      </div>
      <span class="mob-nav-label">Invoice</span>
    </a>

    <button type="button" class="mob-nav-item" onclick="toggleMobileSidebar()" aria-label="Buka Menu Lainnya">
      <div class="mob-nav-icon">
        <svg viewBox="0 0 24 24"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
      </div>
      <span class="mob-nav-label">Menu</span>
    </button>
  <?php endif; ?>
</nav>

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
})();
</script>

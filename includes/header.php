<?php
/**
 * Kalamedia Top Navigation Bar - Light Frost Glassmorphism & Inter Typography
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

$userRole = current_user_role();
$isWorker = ($userRole === 'karyawan' || $userRole === 'freelancer');
?>
<!-- Global Inter Font & Viewport Meta Enforcement -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=20261117-static-sidebar">
<script>
  (function() {
    var vp = document.querySelector('meta[name="viewport"]');
    if (!vp) {
      vp = document.createElement('meta');
      vp.name = 'viewport';
      document.head.appendChild(vp);
    }
    vp.content = 'width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover';
  })();
</script>

<?php if ($isWorker): 
  $workerAvatarUrl = get_kala_cartoon_avatar_data_uri($currentUser['name'] ?? 'Worker', $userRole);
  $fallbackSvgData = $workerAvatarUrl;
?>
<header class="topbar appify-topbar">
  <div class="topbar-left-group">
    <button class="sidebar-toggle-btn" id="sidebar-toggle" onclick="toggleMobileSidebar()" aria-label="Buka Menu Navigasi" title="Buka Menu">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      </svg>
    </button>

    <div class="cartoon-avatar-wrapper size-40" onclick="navigateToWorkerProfile(event)" title="Buka Profil Saya">
      <img src="<?= $workerAvatarUrl ?>" 
           alt="<?= htmlspecialchars($currentUser['name'] ?? 'Worker') ?>" 
           width="40" height="40">
    </div>

    <div class="topbar-user-meta" onclick="navigateToWorkerProfile(event)" title="Buka Profil">
      <h1 class="greeting-h1">
        Halo, <?= htmlspecialchars($currentUser['name'] ?? 'Tim Kalamedia') ?>
      </h1>
      <div class="greeting-date-sub">
        <?= date('d M Y') ?>
      </div>
    </div>
  </div>

  <div class="topbar-right-group">
    <button type="button" class="theme-toggle-compact" onclick="toggleTheme()" aria-label="Toggle Mode Nebula/Terang" title="Beralih Tema (Dark/Light)">
      <svg class="icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
      <svg class="icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 3.5A8.5 8.5 0 1 0 20.5 14.5 7.2 7.2 0 0 1 14.5 3.5z"></path><path d="M18.5 3.5v3.2m-1.6-1.6h3.2" stroke-width="2"></path><path d="M21 8.5v2.2m-1.1-1.1h2.2" stroke-width="1.8"></path></svg>
    </button>
    <span class="badge-role-pill">
      <?= $userRole === 'freelancer' ? 'Freelancer' : 'Staff' ?>
    </span>
  </div>
</header>
<script>
  function navigateToWorkerProfile(e) {
    if (e) e.preventDefault();
    if (typeof switchTaskWorkspaceTab === 'function') {
      switchTaskWorkspaceTab('profile');
    } else {
      window.location.href = '<?= url('tasks?tab=profile&_slot=internal') ?>';
    }
  }
</script>
<?php elseif ($userRole === 'owner'): 
  $ownerAvatarUrl = get_kala_cartoon_avatar_data_uri('Owner', 'owner');
?>
<header class="topbar appify-topbar owner-topbar">
  <div class="topbar-left-group">
    <button class="sidebar-toggle-btn" id="sidebar-toggle" onclick="toggleMobileSidebar()" aria-label="Buka Menu Navigasi" title="Buka Menu">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      </svg>
    </button>

    <div class="cartoon-avatar-wrapper size-40" onclick="if(window.innerWidth<=768 && typeof openOwnerBottomSheet==='function'){openOwnerBottomSheet();}else{window.location.href='<?= url('owner-dashboard?_slot=internal') ?>';}" title="Pusat Menu Owner">
      <img src="<?= $ownerAvatarUrl ?>" 
           alt="Owner" 
           width="40" height="40">
    </div>

    <div class="topbar-user-meta">
      <h1 class="greeting-h1">
        Halo, Owner
      </h1>
      <div class="greeting-date-sub">
        <?= date('d M Y') ?>
      </div>
    </div>
  </div>

  <div class="owner-topbar-right topbar-right-group">
    <button type="button" class="kala-topbar-ai-btn" onclick="openKalaOwnerCopilot()" title="Tanya Kala AI (Asisten Owner)">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M12 2L14.8 8.5L22 9.3L16.5 14L18.2 21L12 17.3L5.8 21L7.5 14L2 9.3L9.2 8.5L12 2Z"/></svg>
      <span class="kala-ai-label-full">Tanya Kala AI</span>
      <span class="kala-ai-label-short">Kala AI</span>
    </button>
    <button type="button" class="theme-toggle-compact" onclick="toggleTheme()" aria-label="Toggle Mode Nebula/Terang" title="Beralih Tema (Dark/Light)">
      <svg class="icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
      <svg class="icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 3.5A8.5 8.5 0 1 0 20.5 14.5 7.2 7.2 0 0 1 14.5 3.5z"></path><path d="M18.5 3.5v3.2m-1.6-1.6h3.2" stroke-width="2"></path><path d="M21 8.5v2.2m-1.1-1.1h2.2" stroke-width="1.8"></path></svg>
    </button>
    <span class="badge-role-executive kala-exec-badge-top">
      Executive
    </span>
  </div>
</header>
<?php else: 
  if ($userRole === 'admin') {
      $greetingDisplayName = 'Admin';
  } else {
      $greetingDisplayName = htmlspecialchars($currentUser['name'] ?? 'User Kalamedia');
  }
  $generalAvatarUrl = get_kala_cartoon_avatar_data_uri($greetingDisplayName, $userRole);
?>
<header class="topbar">
  <div class="greeting-text topbar-left-group">
    <button class="sidebar-toggle-btn" id="sidebar-toggle" onclick="toggleMobileSidebar()" aria-label="Buka Menu Navigasi" title="Buka Menu">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      </svg>
    </button>
    <div class="cartoon-avatar-wrapper size-40" title="Profil <?= $greetingDisplayName ?>">
      <img src="<?= $generalAvatarUrl ?>" alt="<?= $greetingDisplayName ?>" width="40" height="40">
    </div>
    <div class="greeting-info topbar-user-meta">
      <div class="topbar-breadcrumb">
        <span class="breadcrumb-root"><?= htmlspecialchars($currentPageInfo['parent']) ?></span>
        <span class="breadcrumb-sep">/</span>
        <span class="breadcrumb-current"><?= htmlspecialchars($currentPageInfo['name']) ?></span>
      </div>
      <h1 class="greeting-heading">
        <span class="greeting-name">Halo, <?= $greetingDisplayName ?></span>
        <span class="greeting-date">(<?= date('d M Y') ?>)</span>
      </h1>
    </div>
  </div>

  <div class="topbar-actions topbar-right-group">
    <?php if (in_array($userRole, ['owner', 'admin'], true) && !$isClientUser): ?>
      <button type="button" class="kala-topbar-ai-btn" onclick="openKalaOwnerCopilot()" title="Tanya Kala AI (Asisten Owner)">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M12 2L14.8 8.5L22 9.3L16.5 14L18.2 21L12 17.3L5.8 21L7.5 14L2 9.3L9.2 8.5L12 2Z"/></svg>
        <span class="kala-ai-label-full">Tanya Kala AI</span>
        <span class="kala-ai-label-short">Kala AI</span>
      </button>
    <?php endif; ?>
    <button type="button" class="theme-toggle-compact" onclick="toggleTheme()" aria-label="Toggle Mode Nebula/Terang" title="Beralih Tema (Dark/Light)">
      <svg class="icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
      <svg class="icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 3.5A8.5 8.5 0 1 0 20.5 14.5 7.2 7.2 0 0 1 14.5 3.5z"></path><path d="M18.5 3.5v3.2m-1.6-1.6h3.2" stroke-width="2"></path><path d="M21 8.5v2.2m-1.1-1.1h2.2" stroke-width="1.8"></path></svg>
    </button>
    <?php if ($userRole === 'admin' && !$isClientUser): ?>
      <button class="btn btn-primary btn-sm btn-create-invoice" onclick="openModal('modal-create-invoice')">
        <span>+ Buat Invoice</span>
      </button>
    <?php elseif ($isClientUser): ?>
      <span class="badge-portal-klien">
        Portal Klien Terverifikasi
      </span>
    <?php endif; ?>
  </div>
</header>
<?php endif; ?>
<?php require_once __DIR__ . '/kala_ai_copilot_drawer.php'; ?>

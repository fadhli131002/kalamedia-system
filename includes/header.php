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

<?php if ($isWorker): 
  $workerAvatarSeed = urlencode($currentUser['name'] ?? 'Worker');
  $workerAvatarUrl = "https://api.dicebear.com/7.x/adventurer/svg?seed={$workerAvatarSeed}&backgroundColor=ffd5dc,c0aede,d1d4f9,b6e3f4";
  $fallbackSvgData = "data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2250%22 fill=%22%23FDE2E4%22/><circle cx=%2250%22 cy=%2244%22 r=%2222%22 fill=%22%23FFDFBA%22/><path d=%22M32 38 Q50 16 68 38 Q50 26 32 38 Z%22 fill=%22%233D2645%22/><circle cx=%2242%22 cy=%2242%22 r=%223%22 fill=%22%231F2937%22/><circle cx=%2258%22 cy=%2242%22 r=%223%22 fill=%22%231F2937%22/><circle cx=%2238%22 cy=%2247%22 r=%222.5%22 fill=%22%23FFB5A7%22 opacity=%220.6%22/><circle cx=%2262%22 cy=%2247%22 r=%222.5%22 fill=%22%23FFB5A7%22 opacity=%220.6%22/><path d=%22M45 50 Q50 54 55 50%22 stroke=%22%23D95D39%22 stroke-width=%222%22 fill=%22none%22 stroke-linecap=%22round%22/><path d=%22M25 94 C28 72 40 66 50 66 C60 66 72 72 75 94 Z%22 fill=%22%236C5CE7%22/><circle cx=%2250%22 cy=%2268%22 r=%225%22 fill=%22%23FFDFBA%22/></svg>";
?>
<header class="topbar appify-topbar" style="height: 64px !important; min-height: 64px !important; max-height: 64px !important; padding: 0 16px !important; margin: 0 !important; display: flex !important; align-items: center !important; justify-content: space-between !important; position: sticky !important; top: 0 !important; z-index: 100 !important; box-sizing: border-box !important;">
  <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
    <button class="sidebar-toggle-btn" id="sidebar-toggle" onclick="toggleMobileSidebar()" aria-label="Buka Menu Navigasi" title="Buka Menu">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      </svg>
    </button>

    <!-- Cartoon Avatar Round 40x40px next to greeting, clicks to Profile Tab! -->
    <div class="cartoon-avatar-wrapper size-40" onclick="navigateToWorkerProfile(event)" title="Buka Profil Saya" style="cursor: pointer; width: 40px; height: 40px; min-width: 40px; min-height: 40px; border-radius: 50%; overflow: hidden; flex-shrink: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
      <img src="<?= $workerAvatarUrl ?>" 
           alt="<?= htmlspecialchars($currentUser['name'] ?? 'Worker') ?>" 
           onerror="this.onerror=null; this.src='<?= $fallbackSvgData ?>';"
           style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; display: block;">
    </div>

    <div style="min-width: 0; cursor: pointer;" onclick="navigateToWorkerProfile(event)" title="Buka Profil">
      <h1 class="greeting-h1" style="font-size: 15px; font-weight: 700; margin: 0; line-height: 1.25; letter-spacing: -0.2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
        Halo, <?= htmlspecialchars($currentUser['name'] ?? 'Tim Kalamedia') ?>
      </h1>
      <div class="greeting-date-sub" style="font-size: 11px; font-weight: 500; margin-top: 2px;">
        <?= date('d M Y') ?>
      </div>
    </div>
  </div>

  <div style="flex-shrink: 0; display: flex; align-items: center; gap: 8px;">
    <!-- Nebula Dark/Light Mode Toggle -->
    <button type="button" class="theme-toggle-compact" onclick="toggleTheme()" aria-label="Toggle Mode Nebula/Terang" title="Beralih Tema (Dark/Light)">
      <svg class="icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
      <svg class="icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 3.5A8.5 8.5 0 1 0 20.5 14.5 7.2 7.2 0 0 1 14.5 3.5z"></path><path d="M18.5 3.5v3.2m-1.6-1.6h3.2" stroke-width="2"></path><path d="M21 8.5v2.2m-1.1-1.1h2.2" stroke-width="1.8"></path></svg>
    </button>
    <span class="badge-role-pill" style="display: inline-flex; align-items: center; font-size: 11px; font-weight: 600; padding: 4px 12px; border-radius: 999px; letter-spacing: 0.1px;">
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
  $ownerAvatarSeed = urlencode($currentUser['name'] ?? 'Owner');
  $ownerAvatarUrl = "https://api.dicebear.com/7.x/adventurer/svg?seed={$ownerAvatarSeed}&backgroundColor=d1d4f9,c0aede,b6e3f4";
  $ownerFallbackSvgData = "data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2250%22 fill=%22%23EEF2FF%22/><text x=%2250%22 y=%2258%22 font-size=%2234%22 font-weight=%22700%22 fill=%22%235B3DF5%22 text-anchor=%22middle%22 font-family=%22Inter, sans-serif%22>OW</text></svg>";
?>
<header class="topbar appify-topbar owner-topbar" style="height: 64px !important; min-height: 64px !important; max-height: 64px !important; padding: 0 16px !important; margin: 0 !important; display: flex !important; align-items: center !important; justify-content: space-between !important; position: sticky !important; top: 0 !important; z-index: 100 !important; box-sizing: border-box !important;">
  <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
    <button class="sidebar-toggle-btn" id="sidebar-toggle" onclick="toggleMobileSidebar()" aria-label="Buka Menu Navigasi" title="Buka Menu">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      </svg>
    </button>

    <!-- Cartoon/Initial Avatar Round 40x40px Owner -->
    <div class="cartoon-avatar-wrapper size-40" onclick="if(window.innerWidth<=768 && typeof openOwnerBottomSheet==='function'){openOwnerBottomSheet();}else{window.location.href='<?= url('owner-dashboard?_slot=internal') ?>';}" title="Pusat Menu Owner" style="cursor: pointer; width: 40px; height: 40px; min-width: 40px; min-height: 40px; border-radius: 50%; overflow: hidden; flex-shrink: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
      <img src="<?= $ownerAvatarUrl ?>" 
           alt="Owner" 
           onerror="this.onerror=null; this.src='<?= $ownerFallbackSvgData ?>';"
           style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; display: block;">
    </div>

    <div style="min-width: 0; flex: 1;">
      <h1 class="greeting-h1" style="font-size: 16px; font-weight: 700; margin: 0; line-height: 1.25; letter-spacing: -0.2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
        Halo, Owner
      </h1>
      <div class="greeting-date-sub" style="font-size: 11.5px; font-weight: 500; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
        <?= date('d M Y') ?>
      </div>
    </div>
  </div>

  <div class="owner-topbar-right" style="flex-shrink: 0; display: flex; align-items: center; gap: 6px;">
    <!-- Tanya Kala AI (Asisten Owner) -->
    <button type="button" class="kala-topbar-ai-btn" onclick="openKalaOwnerCopilot()" title="Tanya Kala AI (Asisten Owner)">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" style="flex-shrink:0;"><path d="M12 2L14.8 8.5L22 9.3L16.5 14L18.2 21L12 17.3L5.8 21L7.5 14L2 9.3L9.2 8.5L12 2Z"/></svg>
      <span class="kala-ai-label-full">Tanya Kala AI</span>
      <span class="kala-ai-label-short">Kala AI</span>
    </button>
    <!-- Nebula Dark/Light Mode Toggle -->
    <button type="button" class="theme-toggle-compact" onclick="toggleTheme()" aria-label="Toggle Mode Nebula/Terang" title="Beralih Tema (Dark/Light)">
      <svg class="icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
      <svg class="icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 3.5A8.5 8.5 0 1 0 20.5 14.5 7.2 7.2 0 0 1 14.5 3.5z"></path><path d="M18.5 3.5v3.2m-1.6-1.6h3.2" stroke-width="2"></path><path d="M21 8.5v2.2m-1.1-1.1h2.2" stroke-width="1.8"></path></svg>
    </button>
    <span class="badge-role-executive kala-exec-badge-top" style="border-radius: 999px; padding: 4px 12px; font-size: 11.5px; font-weight: 600; display: inline-flex; align-items: center; letter-spacing: 0.1px;">
      Executive
    </span>
  </div>
</header>
<?php else: ?>
<header class="topbar" style="height: 64px !important; min-height: 64px !important; max-height: 64px !important; padding: 0 16px !important; margin: 0 !important; display: flex !important; align-items: center !important; justify-content: space-between !important; position: sticky !important; top: 0 !important; z-index: 100 !important; box-sizing: border-box !important;">
  <div class="greeting-text" style="min-width: 0; flex: 1;">
    <button class="sidebar-toggle-btn" id="sidebar-toggle" onclick="toggleMobileSidebar()" aria-label="Buka Menu Navigasi" title="Buka Menu">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      </svg>
    </button>
    <div class="greeting-info" style="min-width: 0;">
      <div class="topbar-breadcrumb">
        <span class="breadcrumb-root"><?= htmlspecialchars($currentPageInfo['parent']) ?></span>
        <span class="breadcrumb-sep">/</span>
        <span class="breadcrumb-current"><?= htmlspecialchars($currentPageInfo['name']) ?></span>
      </div>
      <?php
        if ($userRole === 'admin') {
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

  <div class="topbar-actions" style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
    <?php if (in_array($userRole, ['owner', 'admin'], true) && !$isClientUser): ?>
      <button type="button" class="kala-topbar-ai-btn" onclick="openKalaOwnerCopilot()" title="Tanya Kala AI (Asisten Owner)">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" style="flex-shrink:0;"><path d="M12 2L14.8 8.5L22 9.3L16.5 14L18.2 21L12 17.3L5.8 21L7.5 14L2 9.3L9.2 8.5L12 2Z"/></svg>
        <span class="kala-ai-label-full">Tanya Kala AI</span>
        <span class="kala-ai-label-short">Kala AI</span>
      </button>
    <?php endif; ?>
    <!-- Nebula Dark/Light Mode Toggle -->
    <button type="button" class="theme-toggle-compact" onclick="toggleTheme()" aria-label="Toggle Mode Nebula/Terang" title="Beralih Tema (Dark/Light)">
      <svg class="icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
      <svg class="icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 3.5A8.5 8.5 0 1 0 20.5 14.5 7.2 7.2 0 0 1 14.5 3.5z"></path><path d="M18.5 3.5v3.2m-1.6-1.6h3.2" stroke-width="2"></path><path d="M21 8.5v2.2m-1.1-1.1h2.2" stroke-width="1.8"></path></svg>
    </button>
    <!-- Role Context Actions -->
    <?php if ($userRole === 'admin' && !$isClientUser): ?>
      <button class="btn btn-primary btn-sm btn-create-invoice" onclick="openModal('modal-create-invoice')">
        <span>+ Buat Invoice</span>
      </button>
    <?php elseif ($isClientUser): ?>
      <span class="badge-portal-klien" style="font-size: 11px; font-weight: 700; padding: 6px 12px; border-radius: 20px; border: 1px solid var(--border-color);">
        Portal Klien Terverifikasi
      </span>
    <?php endif; ?>

  </div>
</header>
<?php endif; ?>
<?php require_once __DIR__ . '/kala_ai_copilot_drawer.php'; ?>

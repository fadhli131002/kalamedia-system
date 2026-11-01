<?php
/**
 * Kalamedia Login View (Untitled UI Design System)
 * Multi-Slot Session Support: Allows concurrent logins across tabs for Owner and Client
 */

if (function_exists('check_and_expire_client_contracts')) {
    check_and_expire_client_contracts();
}

$requestedContext = $_GET['context'] ?? '';
$requestedUid = $_GET['_u'] ?? '';
$isSwitch = isset($_GET['switch']) || isset($_GET['new_tab']);

// Only auto-redirect away from login if a specific active _u token was passed and not switching
if (!empty($requestedUid) && is_logged_in() && !$isSwitch) {
    header('Location: ' . home_url_for_role());
    exit;
}

$activeContext = ($requestedContext === 'client') ? 'client' : 'internal';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - PT Kala Kausa Prima</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=saas-linear-1.0">
  <?php render_tab_uid_script(); ?>
  <style>
    .login-page {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      background: #F8F9FA;
      font-family: var(--font-sans);
      position: relative;
    }
    html[data-theme="dark"] .login-page,
    body.dark-mode.login-page {
      background: #07090E !important;
    }
    .login-card {
      width: 100%;
      max-width: 440px;
      background: #FFFFFF;
      border: 1px solid #EAECEF;
      border-radius: 12px;
      padding: 36px 32px;
      box-shadow: 0 1px 3px rgba(16, 24, 40, 0.05);
      position: relative;
    }
    html[data-theme="dark"] .login-card {
      background: #12151F !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.85), 0 0 1px 1px rgba(56, 189, 248, 0.25) !important;
      color: #FFFFFF !important;
    }
    .login-tabs-wrapper {
      background: #F3F4F6;
      padding: 3px;
      border-radius: 8px;
      display: flex;
      gap: 2px;
      margin-bottom: 24px;
    }
    html[data-theme="dark"] .login-tabs-wrapper {
      background: #0E121D !important;
      border: 1px solid rgba(255, 255, 255, 0.08) !important;
    }
    .login-tab-btn {
      flex: 1;
      border: none;
      padding: 7px 12px;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      background: transparent;
      color: #6B7280;
      transition: all 0.15s ease;
      text-align: center;
      font-family: inherit;
    }
    html[data-theme="dark"] .login-tab-btn {
      color: #94A3B8 !important;
    }
    .login-tab-btn:hover:not(.active) {
      color: #111827;
    }
    html[data-theme="dark"] .login-tab-btn:hover:not(.active) {
      color: #FFFFFF !important;
    }
    .login-tab-btn.active {
      background: #FFFFFF;
      color: #111827;
      box-shadow: 0 1px 2px rgba(16, 24, 40, 0.08);
      font-weight: 600;
    }
    html[data-theme="dark"] .login-tab-btn.active {
      background: #6366F1 !important;
      color: #FFFFFF !important;
      box-shadow: 0 2px 8px rgba(99, 102, 241, 0.45) !important;
    }
    .login-card .form-control {
      border-radius: 8px;
      border: 1px solid #D1D5DB;
      background: #FFFFFF;
      padding: 9px 12px;
      font-size: 13.5px;
      color: #111827;
      transition: border-color 150ms ease, box-shadow 150ms ease;
      font-family: inherit;
      width: 100%;
      box-sizing: border-box;
    }
    html[data-theme="dark"] .login-card .form-control {
      background: #0E121D !important;
      border: 1px solid rgba(255, 255, 255, 0.12) !important;
      color: #FFFFFF !important;
    }
    html[data-theme="dark"] .login-card .form-control:focus {
      border-color: #38BDF8 !important;
      box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.22) !important;
    }
    html[data-theme="dark"] .login-card .form-label {
      color: #94A3B8 !important;
    }
    html[data-theme="dark"] .login-card #login-title {
      color: #FFFFFF !important;
    }
    html[data-theme="dark"] .login-card #login-subtitle {
      color: #94A3B8 !important;
    }
    html[data-theme="dark"] .login-brand img,
    body.dark-mode .login-brand img,
    html[data-theme="dark"] .login-brand-logo,
    body.dark-mode .login-brand-logo {
      filter: brightness(0) invert(1) drop-shadow(0 0 1px rgba(255, 255, 255, 0.4)) !important;
      opacity: 0.95;
    }
    html[data-theme="dark"] .login-brand .login-brand-title,
    body.dark-mode .login-brand .login-brand-title {
      color: #FFFFFF !important;
    }
    .login-theme-floating-toggle {
      position: absolute;
      top: 20px;
      right: 20px;
      z-index: 100;
    }
    .login-card .btn-primary {
      background: #2563EB;
      color: #FFFFFF;
      border: none;
      border-radius: 8px;
      height: 38px;
      padding: 0 16px;
      font-size: 13.5px;
      font-weight: 500;
      box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
      transition: background-color 150ms ease;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .login-card .btn-primary:hover {
      background: #1D4ED8;
    }
    html[data-theme="dark"] .login-card .btn-primary {
      background: #6366F1 !important;
      box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4) !important;
    }
    html[data-theme="dark"] .login-card .btn-primary:hover {
      background: #4F46E5 !important;
    }
    @media (max-width: 480px) {
      .login-page {
        padding: 16px 12px;
      }
      .login-card {
        padding: 24px 18px;
        border-radius: 12px;
      }
      .login-tab-btn {
        padding: 6px 10px;
        font-size: 12px;
      }
    }
  </style>
</head>
<body class="login-page">

  <!-- Floating Nebula Dark/Light Mode Toggle -->
  <div class="login-theme-floating-toggle">
    <button type="button" class="theme-toggle-compact" onclick="toggleTheme()" aria-label="Toggle Mode Nebula/Terang" title="Beralih Tema (Dark/Light)">
      <svg class="icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
      <svg class="icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
    </button>
  </div>

  <div class="login-card">
    <div class="login-brand" style="display: flex; flex-direction: column; align-items: center; text-align: center; gap: 6px; margin-bottom: 22px;">
      <img src="assets/img/Logo-1.webp" alt="PT Kala Kausa Prima" class="login-brand-logo" style="height: 48px; width: 48px; object-fit: contain; margin-bottom: 2px;">
      <div class="login-brand-title" style="font-weight: 600; font-size: 16px; letter-spacing: -0.015em;">PT Kala Kausa Prima</div>
      <div style="display: flex; align-items: center; gap: 6px;">
        <span style="font-size: 11px; color: #2563EB; font-weight: 500; background: #EFF6FF; padding: 2px 8px; border-radius: 5px;">
          Agency System
        </span>
        <span class="badge-version" style="font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 5px;">
          <?= defined('APP_VERSION') ? APP_VERSION : 'v3.0' ?>
        </span>
      </div>
    </div>

    <!-- Multi-Slot Context Switcher Tabs -->
    <div class="login-tabs-wrapper">
      <button type="button" class="login-tab-btn <?= $activeContext === 'internal' ? 'active' : '' ?>" id="tab-btn-internal" onclick="setLoginContext('internal')">
        Staff Internal & Owner
      </button>
      <button type="button" class="login-tab-btn <?= $activeContext === 'client' ? 'active' : '' ?>" id="tab-btn-client" onclick="setLoginContext('client')">
        Portal Klien
      </button>
    </div>

    <div style="margin-bottom: 20px; text-align: center;">
      <h3 style="font-size: 16px; font-weight: 600; color: #111827; margin-bottom: 4px; letter-spacing: -0.015em;" id="login-title">
        <?= $activeContext === 'client' ? 'Portal Klien Kalamedia' : 'Selamat Datang Kembali' ?>
      </h3>
      <p style="font-size: 12.5px; color: #6B7280; margin: 0; line-height: 1.45;" id="login-subtitle">
        <?= $activeContext === 'client' ? 'Masuk untuk memantau progres proyek, kalender konten & laporan kampanye.' : 'Masuk ke ruang kerja internal & sistem manajemen Kalamedia Agency.' ?>
      </p>
    </div>

    <form id="form-login" action="api/auth.php?action=login" method="POST">
      <input type="hidden" name="auth_context" id="auth_context_input" value="<?= $activeContext ?>">
      <input type="hidden" name="_slot" id="auth_slot_input" value="<?= $activeContext ?>">
      <input type="hidden" name="portal" id="auth_portal_input" value="<?= $activeContext ?>">

      <div class="form-group" style="margin-bottom: 14px;">
        <label class="form-label" id="label-email" style="font-weight: 500; color: #374151; margin-bottom: 5px; font-size: 12.5px; display: block;">Alamat Email</label>
        <input type="email" name="email" id="login-email" class="form-control" placeholder="<?= $activeContext === 'client' ? 'pic@perusahaan.com' : 'nama@kalamedia.id' ?>" required autofocus>
      </div>

      <div class="form-group" style="margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
          <label class="form-label" style="margin-bottom: 0; font-weight: 500; color: #374151; font-size: 12.5px;">Kata Sandi</label>
          <a href="javascript:void(0)" onclick="showToast('Silakan hubungi Administrator Kalamedia untuk bantuan login.', 'info')" style="font-size: 12px; color: #2563EB; text-decoration: none; font-weight: 500;">Lupa Password?</a>
        </div>
        <div style="position: relative; display: flex; align-items: center;">
          <input type="password" name="password" id="login-password" class="form-control" placeholder="••••••••" required style="padding-right: 42px;">
          <button type="button" onclick="togglePasswordVisibility('login-password', this)" title="Lihat / Sembunyikan Password" style="position: absolute; right: 8px; background: transparent; border: none; border-radius: 6px; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; color: #64748B; cursor: pointer; padding: 0; transition: color 0.15s ease;">
            <svg class="icon-eye-open" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
            <svg class="icon-eye-closed" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
              <line x1="1" y1="1" x2="23" y2="23"></line>
            </svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; height: 38px; font-size: 13.5px; font-weight: 500;">
        <span id="btn-submit-text"><?= $activeContext === 'client' ? 'Masuk ke Portal Klien' : 'Masuk ke Sistem Internal' ?></span>
      </button>
    </form>
  </div>

  <script src="assets/js/app.js?v=bankio-typography-3.0"></script>
  <script>
    function setLoginContext(ctx) {
      document.getElementById('auth_context_input').value = ctx;
      if (document.getElementById('auth_slot_input')) document.getElementById('auth_slot_input').value = ctx;
      if (document.getElementById('auth_portal_input')) document.getElementById('auth_portal_input').value = ctx;
      const tabInternal = document.getElementById('tab-btn-internal');
      const tabClient = document.getElementById('tab-btn-client');
      const title = document.getElementById('login-title');
      const subtitle = document.getElementById('login-subtitle');
      const emailInput = document.getElementById('login-email');
      const submitText = document.getElementById('btn-submit-text');

      if (ctx === 'client') {
        tabClient.classList.add('active');
        tabInternal.classList.remove('active');
        title.innerText = 'Portal Klien Kalamedia';
        subtitle.innerText = 'Masuk untuk memantau progres proyek, kalender konten & laporan kampanye.';
        emailInput.placeholder = 'pic@perusahaan.com';
        submitText.innerText = 'Masuk ke Portal Klien';
      } else {
        tabInternal.classList.add('active');
        tabClient.classList.remove('active');
        title.innerText = 'Selamat Datang Kembali';
        subtitle.innerText = 'Masuk ke ruang kerja internal & sistem manajemen Kalamedia Agency.';
        emailInput.placeholder = 'nama@kalamedia.id';
        submitText.innerText = 'Masuk ke Sistem Internal';
      }
    }

    const loginForm = document.getElementById('form-login');
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submitBtn = loginForm.querySelector('button[type="submit"]');
      const originalHtml = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Memverifikasi...';

      const formData = new FormData(loginForm);
      try {
        const res = await fetch(loginForm.action, {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          if (data.uid_key) {
            sessionStorage.setItem('kala_tab_uid', data.uid_key);
          }
          showToast(data.message, 'success');
          setTimeout(() => {
            window.location.href = data.redirect || 'index.php';
          }, 400);
        } else {
          showToast(data.message || 'Login gagal', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHtml;
      }
    });
  </script>
</body>
</html>

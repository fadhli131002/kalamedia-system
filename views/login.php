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
  <?php render_pwa_meta_tags(); ?>
  <style>
    .login-page {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      background: #F8F9FA;
      font-family: var(--font-sans);
    }
    .login-card {
      width: 100%;
      max-width: 440px;
      background: #FFFFFF;
      border: 1px solid #EAECEF;
      border-radius: 12px;
      padding: 36px 32px;
      box-shadow: 0 1px 3px rgba(16, 24, 40, 0.05);
    }
    .login-tabs-wrapper {
      background: #F3F4F6;
      padding: 3px;
      border-radius: 8px;
      display: flex;
      gap: 2px;
      margin-bottom: 24px;
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
    .login-tab-btn:hover:not(.active) {
      color: #111827;
    }
    .login-tab-btn.active {
      background: #FFFFFF;
      color: #111827;
      box-shadow: 0 1px 2px rgba(16, 24, 40, 0.08);
      font-weight: 600;
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
    .login-card .form-control:focus {
      border-color: #2563EB;
      box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.12);
      outline: none;
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

  <div class="login-card">
    <div class="login-brand" style="display: flex; flex-direction: column; align-items: center; text-align: center; gap: 6px; margin-bottom: 22px;">
      <img src="assets/img/Logo-1.webp" alt="PT Kala Kausa Prima" style="height: 48px; width: 48px; object-fit: contain; margin-bottom: 2px;">
      <div style="font-weight: 600; font-size: 16px; color: #111827; letter-spacing: -0.015em;">PT Kala Kausa Prima</div>
      <span style="font-size: 11px; color: #2563EB; font-weight: 500; background: #EFF6FF; padding: 2px 8px; border-radius: 5px;">
        Agency System
      </span>
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
          <input type="password" name="password" id="login-password" class="form-control" placeholder="••••••••" required style="padding-right: 68px;">
          <button type="button" onclick="togglePasswordVisibility('login-password', this)" style="position: absolute; right: 8px; background: #F3F4F6; border: none; border-radius: 6px; padding: 4px 8px; font-size: 11px; font-weight: 500; color: #4B5563; cursor: pointer;">
            Lihat
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

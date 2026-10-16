<?php
/**
 * Kalamedia Login View (Untitled UI Design System)
 * Multi-Slot Session Support: Allows concurrent logins across tabs for Owner and Client
 */

$requestedContext = $_GET['context'] ?? '';
$isSwitch = isset($_GET['switch']);

// Check session based on requested context
if ($requestedContext === 'client') {
    if (!empty($_SESSION['kala_client_portal']['logged_in']) && !$isSwitch) {
        header('Location: ' . url('client-portal'));
        exit;
    }
    $activeContext = 'client';
} elseif ($requestedContext === 'internal') {
    if (!empty($_SESSION['kala_internal']['logged_in']) && !$isSwitch) {
        header('Location: ' . home_url_for_role(current_user_role('internal')));
        exit;
    }
    $activeContext = 'internal';
} else {
    // Default flow:
    if (!$isSwitch) {
        if (!empty($_SESSION['kala_internal']['logged_in'])) {
            header('Location: ' . home_url_for_role(current_user_role('internal')));
            exit;
        } elseif (!empty($_SESSION['kala_client_portal']['logged_in'])) {
            header('Location: ' . url('client-portal'));
            exit;
        }
    }
    $activeContext = 'internal';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - PT Kala Kausa Prima</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=bankio-1.0">
  <style>
    .login-page {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      background: #F5F5F7;
      background-image: 
        radial-gradient(circle at 15% 20%, rgba(93, 118, 245, 0.08) 0%, transparent 45%), 
        radial-gradient(circle at 85% 80%, rgba(137, 50, 224, 0.06) 0%, transparent 45%);
      font-family: 'SF Pro Rounded', 'Plus Jakarta Sans', 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }
    .login-card {
      width: 100%;
      max-width: 460px;
      background: #FFFFFF;
      border: 1px solid #ECEEF5;
      border-radius: 24px;
      padding: 38px 34px;
      box-shadow: 0 10px 30px -10px rgba(18, 24, 48, 0.05), 0 2px 6px -1px rgba(18, 24, 48, 0.02);
    }
    .login-tabs-wrapper {
      background: #EEF0F6;
      padding: 5px;
      border-radius: 999px;
      display: flex;
      gap: 4px;
      margin-bottom: 24px;
      border: 1px solid #ECEEF5;
    }
    .login-tab-btn {
      flex: 1;
      border: none;
      padding: 9px 14px;
      border-radius: 999px;
      font-size: 12.5px;
      font-weight: 600;
      cursor: pointer;
      background: transparent;
      color: #6E7485;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      text-align: center;
      font-family: inherit;
    }
    .login-tab-btn.active {
      background: #5D76F5;
      color: #FFFFFF;
      box-shadow: 0 4px 12px rgba(93, 118, 245, 0.28);
      font-weight: 700;
    }
    .login-card .form-control {
      border-radius: 14px;
      border: 1px solid #ECEEF5;
      background: #FFFFFF;
      padding: 12px 16px;
      font-size: 13.5px;
      color: #000000;
      transition: all 0.2s ease;
      font-family: inherit;
    }
    .login-card .form-control:focus {
      border-color: #5D76F5;
      background: #FFFFFF;
      box-shadow: 0 0 0 4px rgba(93, 118, 245, 0.15);
      outline: none;
    }
    .login-card .btn-primary {
      background: #5D76F5;
      color: #FFFFFF;
      border: 1px solid #5D76F5;
      border-radius: 14px;
      padding: 12px 18px;
      font-size: 14px;
      font-weight: 600;
      box-shadow: 0 8px 20px -4px rgba(93, 118, 245, 0.38);
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      cursor: pointer;
    }
    .login-card .btn-primary:hover {
      background: #4A63E2;
      border-color: #4A63E2;
      box-shadow: 0 10px 24px -4px rgba(93, 118, 245, 0.45);
      transform: translateY(-1px);
    }
    @media (max-width: 480px) {
      .login-page {
        padding: 16px;
      }
      .login-card {
        padding: 26px 20px;
        border-radius: 20px;
      }
    }
  </style>
</head>
<body class="login-page">

  <div class="login-card">
    <div class="login-brand" style="display: flex; flex-direction: column; align-items: center; text-align: center; gap: 8px; margin-bottom: 22px;">
      <img src="assets/img/Logo-1.webp" alt="PT Kala Kausa Prima" style="height: 56px; width: 56px; object-fit: contain; margin-bottom: 2px;">
      <div style="font-weight: 800; font-size: 18px; color: #000000; letter-spacing: -0.3px;">PT Kala Kausa Prima</div>
      <span style="font-size: 11px; color: #5D76F5; text-transform: uppercase; font-weight: 700; letter-spacing: 0.8px; background: rgba(93, 118, 245, 0.12); padding: 3px 12px; border-radius: 999px; border: 1px solid rgba(93, 118, 245, 0.2);">
        Built for What’s Next
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

    <div style="margin-bottom: 22px; text-align: center;">
      <h3 style="font-size: 19px; font-weight: 800; color: #000000; margin-bottom: 5px; letter-spacing: -0.3px;" id="login-title">
        <?= $activeContext === 'client' ? 'Portal Klien Kalamedia' : 'Selamat Datang Kembali' ?>
      </h3>
      <p style="font-size: 13px; color: #6E7485; margin: 0; line-height: 1.5;" id="login-subtitle">
        <?= $activeContext === 'client' ? 'Masuk untuk memantau progres proyek, kalender konten & laporan kampanye.' : 'Masuk ke ruang kerja internal & sistem manajemen Kalamedia Agency.' ?>
      </p>
    </div>

    <form id="form-login" action="api/auth.php?action=login" method="POST">
      <input type="hidden" name="auth_context" id="auth_context_input" value="<?= $activeContext ?>">

      <div class="form-group" style="margin-bottom: 16px;">
        <label class="form-label" id="label-email" style="font-weight: 600; color: #000000; margin-bottom: 6px; font-size: 12.5px; display: block;">Alamat Email</label>
        <input type="email" name="email" id="login-email" class="form-control" placeholder="<?= $activeContext === 'client' ? 'pic@perusahaan.com' : 'nama@kalamedia.id' ?>" required autofocus>
      </div>

      <div class="form-group" style="margin-bottom: 22px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
          <label class="form-label" style="margin-bottom: 0; font-weight: 600; color: #000000; font-size: 12.5px;">Kata Sandi</label>
          <a href="javascript:void(0)" onclick="showToast('Silakan hubungi Administrator Kalamedia untuk bantuan login.', 'info')" style="font-size: 12px; color: #5D76F5; text-decoration: none; font-weight: 600;">Lupa Password?</a>
        </div>
        <div style="position: relative; display: flex; align-items: center;">
          <input type="password" name="password" id="login-password" class="form-control" placeholder="••••••••" required style="padding-right: 68px;">
          <button type="button" onclick="togglePasswordVisibility('login-password', this)" style="position: absolute; right: 8px; background: #F8F9FC; border: 1px solid #ECEEF5; border-radius: 10px; padding: 5px 10px; font-size: 11px; font-weight: 700; color: #6E7485; cursor: pointer; transition: all 0.15s ease;">
            Lihat
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px 18px; font-size: 14px;">
        <span id="btn-submit-text"><?= $activeContext === 'client' ? 'Masuk ke Portal Klien' : 'Masuk ke Sistem Internal' ?></span>
      </button>
    </form>
  </div>
    </form>
  </div>

  <script src="assets/js/app.js"></script>
  <script>
    function setLoginContext(ctx) {
      document.getElementById('auth_context_input').value = ctx;
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
          showToast(data.message, 'success');
          setTimeout(() => {
            window.location.href = data.redirect || 'index.php';
          }, 500);
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

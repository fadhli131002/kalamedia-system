<?php
/**
 * Kalamedia Login View (Untitled UI Design System)
 */
if (is_logged_in()) {
    header('Location: ' . home_url_for_role());
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - PT Kala Kausa Prima</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .login-page {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      background: var(--bg-main);
    }
    .login-card {
      width: 100%;
      max-width: 480px;
      background: #FFFFFF;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-lg);
      padding: 36px 32px;
      box-shadow: 0 10px 25px -5px rgba(16, 24, 40, 0.08), 0 8px 10px -6px rgba(16, 24, 40, 0.03);
    }
    .login-brand {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 24px;
    }
    @media (max-width: 480px) {
      .login-page {
        padding: 14px;
      }
      .login-card {
        padding: 24px 18px;
      }
    }
  </style>
</head>
<body class="login-page">

  <div class="login-card">
    <div class="login-brand" style="display: flex; flex-direction: column; align-items: center; text-align: center; gap: 8px; margin-bottom: 24px;">
      <img src="assets/img/Logo-1.webp" alt="PT Kala Kausa Prima" style="height: 60px; width: 60px; object-fit: contain; margin-bottom: 2px;">
      <div style="font-weight: 800; font-size: 18px; color: #101828; letter-spacing: -0.3px;">PT Kala Kausa Prima</div>
      <span style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; letter-spacing: 0.8px; background: #F2F4F7; padding: 3px 12px; border-radius: 20px;">
        Built for What’s Next
      </span>
    </div>

    <div style="margin-bottom: 24px;">
      <h3 style="font-size: 18px; font-weight: 800; color: #101828; margin-bottom: 4px; letter-spacing: -0.2px;">Selamat Datang Kembali</h3>
      <p style="font-size: 13px; color: var(--text-secondary); margin: 0;">Masuk ke Portal & Sistem Manajemen Kalamedia Agency.</p>
    </div>

    <form id="form-login" action="api/auth.php?action=login" method="POST">
      <div class="form-group">
        <label class="form-label">Alamat Email</label>
        <input type="email" name="email" id="login-email" class="form-control" placeholder="nama@kalamedia.id" required autofocus>
      </div>

      <div class="form-group" style="margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
          <label class="form-label" style="margin-bottom: 0;">Kata Sandi</label>
          <a href="javascript:void(0)" onclick="showToast('Silakan hubungi Owner / Administrator untuk bantuan login.', 'info')" style="font-size: 11.5px; color: var(--text-secondary); text-decoration: none; font-weight: 600;">Lupa Password?</a>
        </div>
        <div style="position: relative; display: flex; align-items: center;">
          <input type="password" name="password" id="login-password" class="form-control" placeholder="••••••••" required style="padding-right: 64px;">
          <button type="button" onclick="togglePasswordVisibility('login-password', this)" style="position: absolute; right: 8px; background: transparent; border: 1px solid var(--border-color, #E4E7EC); border-radius: 6px; padding: 4px 8px; font-size: 11px; font-weight: 600; color: #475467; cursor: pointer;">
            Lihat
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 11px 16px; font-size: 14px;">
        <span>Masuk ke Sistem</span>
      </button>
    </form>
  </div>

  <script src="assets/js/app.js"></script>
  <script>
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

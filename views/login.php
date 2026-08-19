<?php
/**
 * Kalamedia Login View (Untitled UI Design System)
 */
if (is_logged_in()) {
    header('Location: ' . (is_owner() ? url('owner-dashboard') : url('admin-dashboard')));
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Kalamedia Agency System</title>
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
      max-width: 440px;
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
    .demo-accounts-box {
      margin-top: 24px;
      background: #F9FAFB;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-sm);
      padding: 16px;
    }
    .demo-btn-group {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-top: 10px;
    }
  </style>
</head>
<body class="login-page">

  <div class="login-card">
    <div class="login-brand" style="display: flex; flex-direction: column; align-items: center; text-align: center; gap: 8px; margin-bottom: 24px;">
      <img src="assets/Jpg/Asset 3.png" alt="Kala Media Creative Agency" style="height: 52px; width: auto; max-width: 220px; object-fit: contain; margin-bottom: 4px;">
      <span style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; letter-spacing: 0.8px; background: #F2F4F7; padding: 3px 12px; border-radius: 20px;">
        Financial & Project System
      </span>
    </div>

    <div style="margin-bottom: 24px;">
      <h3 style="font-size: 18px; font-weight: 800; color: #101828; margin-bottom: 4px; letter-spacing: -0.2px;">Selamat Datang Kembali</h3>
      <p style="font-size: 13px; color: var(--text-secondary); margin: 0;">Masuk ke akun internal agensi Kalamedia.</p>
    </div>

    <form id="form-login" action="api/auth.php?action=login" method="POST">
      <div class="form-group">
        <label class="form-label">Alamat Email</label>
        <input type="email" name="email" id="login-email" class="form-control" placeholder="nama@kalamedia.id" required autofocus>
      </div>

      <div class="form-group" style="margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
          <label class="form-label" style="margin-bottom: 0;">Kata Sandi</label>
          <a href="javascript:void(0)" onclick="showToast('Silakan hubungi Super Admin untuk reset password.', 'info')" style="font-size: 11.5px; color: var(--text-secondary); text-decoration: none; font-weight: 600;">Lupa Password?</a>
        </div>
        <input type="password" name="password" id="login-password" class="form-control" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 11px 16px; font-size: 14px;">
        <span>Masuk ke Sistem</span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
      </button>
    </form>

    <!-- Quick Demo Switcher -->
    <div class="demo-accounts-box">
      <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.5px; text-align: center;">
        Akses Cepat Demo Akun
      </div>
      <div class="demo-btn-group">
        <button type="button" class="btn btn-secondary btn-sm" onclick="quickLogin('owner')" style="justify-content: center; font-size: 11px; font-weight: 700;">
          <span style="color: #B45309;">👑</span> Muhammad Fadhli
        </button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="quickLogin('admin')" style="justify-content: center; font-size: 11px; font-weight: 700;">
          <span style="color: #101828;">💼</span> Ilham Lanang
        </button>
      </div>
      <div style="text-align: center; margin-top: 10px; font-size: 11px; color: var(--text-muted);">
        Owner (Creative Manager): <code>owner@kalamedia.id</code><br>
        Marketing Manager: <code>finance@kalamedia.id</code> &bull; Pass: <code>password123</code>
      </div>
    </div>
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
          }, 600);
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

    async function quickLogin(role) {
      const formData = new FormData();
      formData.append('role', role);
      try {
        const res = await fetch('api/auth.php?action=quick_login', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          setTimeout(() => {
            window.location.href = data.redirect;
          }, 600);
        } else {
          showToast('Login cepat gagal', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      }
    }
  </script>
</body>
</html>

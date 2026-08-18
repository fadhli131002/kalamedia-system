<?php
/**
 * Kalamedia Settings View (Restricted: Owner Only - Untitled UI Design System)
 */
require_owner();
$db = Database::getConnection();
$users = $db->query("SELECT id, name, email, role, created_at FROM users ORDER BY id ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pengaturan Agensi - Kalamedia</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body">

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
          
          <!-- Agency Profile & Invoice Bank Info -->
          <div class="glass-panel">
            <div class="panel-header">
              <div>
                <h3 class="panel-title">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                  Profil & Rekening Agensi
                </h3>
                <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Identitas resmi dan info rekening penerima pembayaran invoice</p>
              </div>
            </div>

            <form onsubmit="event.preventDefault(); showToast('Pengaturan profil agensi disimpan!', 'success');">
              <div class="form-group">
                <label class="form-label">Nama Agensi</label>
                <input type="text" class="form-control" value="<?= AGENCY_NAME ?>" readonly>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Email Finance</label>
                  <input type="email" class="form-control" value="<?= AGENCY_EMAIL ?>" readonly>
                </div>
                <div class="form-group">
                  <label class="form-label">Nomor Telepon</label>
                  <input type="text" class="form-control" value="<?= AGENCY_PHONE ?>" readonly>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Alamat Kantor</label>
                <textarea class="form-control" rows="2" readonly><?= AGENCY_ADDRESS ?></textarea>
              </div>
              <div class="form-group">
                <label class="form-label">Rekening Pembayaran Utama (Invoice)</label>
                <div style="background: #F9FAFB; padding: 14px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); font-size: 13px;">
                  <div style="font-weight: 700; color: #101828;"><?= AGENCY_BANK_NAME ?></div>
                  <div style="font-size: 17px; font-weight: 800; color: #101828; margin: 4px 0; letter-spacing: -0.2px;"><?= AGENCY_BANK_ACCOUNT ?></div>
                  <div style="color: var(--text-secondary); font-size: 12px;">a/n <?= AGENCY_BANK_HOLDER ?></div>
                </div>
              </div>
              <button type="submit" class="btn btn-primary btn-sm">Simpan Perubahan</button>
            </form>
          </div>

          <!-- User Management (RBAC) -->
          <div class="glass-panel">
            <div class="panel-header">
              <div>
                <h3 class="panel-title">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                  Manajemen Akun Pengguna (RBAC)
                </h3>
                <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Daftar akun dan hak akses pengguna sistem</p>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table-custom">
                <thead>
                  <tr>
                    <th>Nama & Email</th>
                    <th>Peran</th>
                    <th>Hak Akses</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($users as $u): ?>
                    <tr>
                      <td>
                        <div style="font-weight: 700; color: #101828;"><?= htmlspecialchars($u['name']) ?></div>
                        <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($u['email']) ?></div>
                      </td>
                      <td>
                        <span class="role-badge <?= $u['role'] === 'owner' ? 'role-owner' : 'role-admin' ?>">
                          <?= $u['role'] === 'owner' ? 'Owner Agensi' : 'Finance Agensi' ?>
                        </span>
                      </td>
                      <td style="font-size: 11px; color: var(--text-secondary);">
                        <?= $u['role'] === 'owner' ? 'Akses Eksekutif (Analisis Profit & Pengaturan Sistem)' : 'Operasional Harian (Pencatatan Keuangan & Kalender Konten)' ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div style="margin-top: 18px; background: #F8FAFC; border: 1px solid var(--border-color); border-left: 4px solid #101828; border-radius: var(--radius-sm); padding: 14px 16px; font-size: 12px; color: var(--text-secondary);">
              <strong style="color: #101828;">Keamanan Sistem:</strong> Admin Finance tidak memiliki wewenang untuk mengubah pengaturan akun Owner atau menghapus arsip keuangan secara permanen.
            </div>
          </div>

        </div>

  <?php require_once BASE_PATH . '/includes/footer.php'; ?>
</body>
</html>

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
  <title>Pengaturan Agensi - PT Kala Kausa Prima</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=saas-linear-1.0">
</head>
<body>
  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body">

  <style>
    @media (max-width: 768px) {
      .settings-grid,
      .kala-grid-2col {
        grid-template-columns: 1fr !important;
        gap: 16px !important;
      }
      .kala-settings-card-header {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 10px !important;
      }
      .kala-settings-card-header .kala-btn-add-account {
        width: 100% !important;
        text-align: center;
        justify-content: center;
      }
    }
  </style>

        <div class="settings-grid kala-grid-2col">
          
          <!-- Agency Profile & Invoice Bank Info -->
          <div class="glass-panel" style="overflow: visible; min-width: 0; max-width: 100%;">
            <div class="panel-header">
              <div>
                <h3 class="panel-title">
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
          <div class="glass-panel" style="overflow: visible; min-width: 0; max-width: 100%;">
            <div class="panel-header kala-settings-card-header">
              <div>
                <h3 class="panel-title">
                  Manajemen Akun Pengguna (RBAC)
                </h3>
                <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Daftar akun dan hak akses pengguna sistem</p>
              </div>
              <button type="button" class="btn btn-primary btn-sm kala-btn-add-account" onclick="openCreateUserModal()">
                + Tambah Akun
              </button>
            </div>

            <div class="kala-table-scroll table-responsive" style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; display:block;">
              <table class="table-custom" style="min-width: 580px; width: 100%;">
                <thead>
                  <tr>
                    <th style="white-space: nowrap;">Nama & Email</th>
                    <th style="white-space: nowrap;">Peran</th>
                    <th style="white-space: nowrap;">Hak Akses</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($users as $u): ?>
                    <tr>
                      <td style="white-space: nowrap;">
                        <div style="font-weight: 700; color: #101828;"><?= htmlspecialchars($u['name']) ?></div>
                        <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($u['email']) ?></div>
                      </td>
                      <td style="white-space: nowrap;">
                        <span class="role-badge" style="font-weight: 700; font-size: 11px; text-transform: uppercase;">
                          <?= htmlspecialchars($u['role']) ?>
                        </span>
                      </td>
                      <td style="font-size: 11px; color: var(--text-secondary); white-space: nowrap;">
                        <?php
                          $roleDesc = [
                            'owner' => 'Akses Penuh Eksekutif (Profit, User Management & Sistem)',
                            'admin' => 'Operasional Agensi (Invoice, Penugasan & Pengeluaran)',
                            'karyawan' => 'Workspace Karyawan (Tugas In-House & Kalender Konten)',
                            'freelancer' => 'Workspace Freelance (Tugas Talenta & Kalender Konten)',
                            'client' => 'Client Portal (Progress Proyek, Review & Invoice)'
                          ][$u['role']] ?? 'Akses Sistem';
                          echo $roleDesc;
                        ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div style="margin-top: 18px; background: #F8FAFC; border: 1px solid var(--border-color); border-left: 4px solid #5D76F5; border-radius: var(--radius-sm); padding: 14px 16px; font-size: 12px; color: var(--text-secondary);">
              <strong style="color: #000000;">Keamanan Sistem:</strong> Admin Finance tidak memiliki wewenang untuk mengubah pengaturan akun Owner atau menghapus arsip keuangan secara permanen.
            </div>
          </div>

        </div>

  <?php require_once BASE_PATH . '/includes/footer.php'; ?>
</body>
</html>

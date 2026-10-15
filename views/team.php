<?php
/**
 * Kalamedia Agency - Manajemen Tim & Karyawan (Untitled UI Design System)
 * Full Management: In-House Employees & Freelancers, Jobdesk from Owner, Client Allocation & Credentials
 */
require_admin();

$user = current_user();
$role = current_user_role();
$isOwner = is_owner();
$db = Database::getConnection();

// Fetch active clients for assignment
$allClients = $db->query("
    SELECT id, company, name as pic_name 
    FROM clients 
    WHERE COALESCE(is_deleted, 0) = 0 
    ORDER BY company ASC
")->fetchAll();

// Fetch active team members (karyawan and freelancer)
$teamMembers = $db->query("
    SELECT u.id, u.name, u.email, u.role, u.employment_type, u.position, u.jobdesk, u.phone_wa, u.avatar, u.is_active, u.created_at
    FROM users u
    WHERE u.role IN ('karyawan', 'freelancer') AND COALESCE(u.is_deleted, 0) = 0
    ORDER BY 
        CASE WHEN u.employment_type = 'karyawan' THEN 1 ELSE 2 END,
        u.name ASC
")->fetchAll();

// Attach assigned clients to each member
foreach ($teamMembers as &$m) {
    $stmtC = $db->prepare("
        SELECT c.id, c.company, c.name as pic_name
        FROM clients c
        JOIN employee_clients ec ON c.id = ec.client_id
        WHERE ec.user_id = ? AND COALESCE(c.is_deleted, 0) = 0
        ORDER BY c.company ASC
    ");
    $stmtC->execute([$m['id']]);
    $m['assigned_clients'] = $stmtC->fetchAll();
    $m['client_ids'] = array_column($m['assigned_clients'], 'id');
}
unset($m);

// Metrics
$totalMembers = count($teamMembers);
$karyawanCount = 0;
$freelancerCount = 0;
foreach ($teamMembers as $tm) {
    if ($tm['employment_type'] === 'freelancer') $freelancerCount++;
    else $karyawanCount++;
}
$totalClientsCount = count($allClients);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tim & Karyawan - PT Kala Kausa Prima</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .team-badge-karyawan {
      background: #EFF8FF;
      color: #175CD3;
      border: 1px solid #B2DDFF;
      font-size: 11px;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 12px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .team-badge-freelancer {
      background: #F4F3FF;
      color: #5925DC;
      border: 1px solid #D9D6FE;
      font-size: 11px;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 12px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .client-tag-pill {
      background: #F2F4F7;
      color: #344054;
      font-size: 11px;
      font-weight: 600;
      padding: 2px 7px;
      border-radius: 6px;
      display: inline-block;
      margin: 2px 2px 2px 0;
      border: 1px solid var(--border-color);
    }
    .checkbox-clients-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 8px;
      max-height: 160px;
      overflow-y: auto;
      padding: 10px;
      background: #F9FAFB;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-sm);
    }
    .checkbox-client-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12.5px;
      color: #344054;
      cursor: pointer;
    }
  </style>
</head>
<body>
  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body">

        <!-- Top Header Card -->
        <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 24px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(16,24,40,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
          <div>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
              <span class="badge" style="background: #EFF8FF; color: #175CD3; font-weight: 800; font-size: 11px;">
                MANAJEMEN SDM & TALENTA
              </span>
              <span style="font-size: 12px; color: var(--text-muted);">&bull;</span>
              <span style="font-size: 12px; color: var(--text-secondary); font-weight: 600;">
                Kalamedia Internal
              </span>
            </div>
            <h2 style="font-size: 20px; font-weight: 800; color: #101828; margin: 0; letter-spacing: -0.3px;">
              Tim & Karyawan Kalamedia
            </h2>
            <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0;">
              Kelola data staf in-house dan freelancer, rincian jobdesk dari Owner, penugasan klien/PT, serta akun akses login ke sistem.
            </p>
          </div>

          <div>
            <button type="button" class="btn btn-primary" onclick="openModal('modal-create-team-member')">
              <span>+ Tambah Karyawan / Tim</span>
            </button>
          </div>
        </div>

        <!-- KPI Metrics Grid -->
        <div class="kpi-row" style="margin-bottom: 20px;">
          <div class="kpi-tile">
            <span style="font-size: 11.5px; color: var(--text-secondary); font-weight: 600;">Total Tim Aktif</span>
            <div style="font-size: 24px; font-weight: 800; color: #101828;"><?= $totalMembers ?></div>
            <span style="font-size: 11px; color: var(--text-muted);">Karyawan & Freelancer</span>
          </div>

          <div class="kpi-tile">
            <span style="font-size: 11.5px; color: #175CD3; font-weight: 700;">Karyawan Kalamedia</span>
            <div style="font-size: 24px; font-weight: 800; color: #175CD3;"><?= $karyawanCount ?></div>
            <span style="font-size: 11px; color: var(--text-muted);">Staf In-House</span>
          </div>

          <div class="kpi-tile">
            <span style="font-size: 11.5px; color: #5925DC; font-weight: 700;">Freelancer</span>
            <div style="font-size: 24px; font-weight: 800; color: #5925DC;"><?= $freelancerCount ?></div>
            <span style="font-size: 11px; color: var(--text-muted);">Talenta Eksternal</span>
          </div>

          <div class="kpi-tile">
            <span style="font-size: 11.5px; color: #027A48; font-weight: 600;">Klien Terdaftar</span>
            <div style="font-size: 24px; font-weight: 800; color: #027A48;"><?= $totalClientsCount ?></div>
            <span style="font-size: 11px; color: var(--text-muted);">Perusahaan / PT</span>
          </div>
        </div>

        <!-- Team Members Table Panel -->
        <div class="glass-panel">
          <div class="panel-header" style="flex-wrap: wrap; gap: 12px;">
            <div>
              <h3 class="panel-title">Daftar Anggota Tim & Alokasi Klien</h3>
              <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">
                Daftar seluruh tim yang memiliki hak akses pengerjaan deliverables dan workspace.
              </p>
            </div>

            <div style="display: flex; gap: 8px;">
              <button class="filter-btn active" onclick="filterTeamRole('all', this)">Semua</button>
              <button class="filter-btn" onclick="filterTeamRole('karyawan', this)">Karyawan In-House</button>
              <button class="filter-btn" onclick="filterTeamRole('freelancer', this)">Freelancer</button>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table-custom">
              <thead>
                <tr>
                  <th>NAMA & KONTAK</th>
                  <th>STATUS SDM</th>
                  <th>POSISI & JOBDESK DARI OWNER</th>
                  <th>KLIEN / PT YANG DIPEGANG</th>
                  <th>EMAIL LOGIN PORTAL</th>
                  <th style="text-align: right; white-space: nowrap;">AKSI</th>
                </tr>
              </thead>
              <tbody id="team-table-body">
                <?php if (empty($teamMembers)): ?>
                  <tr id="empty-team-row">
                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 48px;">
                      <div style="font-size: 28px; margin-bottom: 8px;">👥</div>
                      <div style="font-weight: 700; color: #101828; margin-bottom: 4px;">Belum Ada Anggota Tim</div>
                      <p style="font-size: 13px; color: var(--text-secondary); margin: 0 auto 16px; max-width: 400px;">
                        Klik tombol "+ Tambah Karyawan / Tim" di atas untuk mendaftarkan karyawan atau freelancer pertama Anda.
                      </p>
                      <button type="button" class="btn btn-primary btn-sm" onclick="openModal('modal-create-team-member')">
                        + Tambah Karyawan / Tim
                      </button>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($teamMembers as $m): ?>
                    <tr class="team-row" data-role="<?= htmlspecialchars($m['employment_type']) ?>">
                      <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                          <div style="width: 36px; height: 36px; border-radius: 50%; background: #EFF8FF; color: #175CD3; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; flex-shrink: 0; border: 1px solid #B2DDFF;">
                            <?= strtoupper(substr($m['name'], 0, 2)) ?>
                          </div>
                          <div>
                            <div style="font-weight: 800; color: #101828; font-size: 13.5px;">
                              <?= htmlspecialchars($m['name']) ?>
                            </div>
                            <?php if (!empty($m['phone_wa'])): ?>
                              <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $m['phone_wa']) ?>" target="_blank" style="font-size: 11px; color: #027A48; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                <span>WhatsApp: <?= htmlspecialchars($m['phone_wa']) ?></span>
                              </a>
                            <?php endif; ?>
                          </div>
                        </div>
                      </td>

                      <td>
                        <?php if ($m['employment_type'] === 'freelancer'): ?>
                          <span class="team-badge-freelancer">Freelancer</span>
                        <?php else: ?>
                          <span class="team-badge-karyawan">Karyawan Kalamedia</span>
                        <?php endif; ?>
                      </td>

                      <td>
                        <div style="font-weight: 700; color: #101828; font-size: 12.5px; margin-bottom: 2px;">
                          <?= htmlspecialchars($m['position'] ?: 'Spesialis Tim') ?>
                        </div>
                        <?php if (!empty($m['jobdesk'])): ?>
                          <div style="font-size: 12px; color: var(--text-secondary); max-width: 320px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" title="<?= htmlspecialchars($m['jobdesk']) ?>">
                            <?= nl2br(htmlspecialchars($m['jobdesk'])) ?>
                          </div>
                        <?php else: ?>
                          <span style="font-size: 11px; color: var(--text-muted); font-style: italic;">Belum ada rincian jobdesk</span>
                        <?php endif; ?>
                      </td>

                      <td>
                        <?php if (!empty($m['assigned_clients'])): ?>
                          <div style="display: flex; flex-wrap: wrap; max-width: 280px;">
                            <?php foreach ($m['assigned_clients'] as $ac): ?>
                              <span class="client-tag-pill"><?= htmlspecialchars($ac['company']) ?></span>
                            <?php endforeach; ?>
                          </div>
                        <?php else: ?>
                          <span style="font-size: 11px; color: var(--text-tertiary); font-style: italic;">Semua Proyek / Belum dialokasikan</span>
                        <?php endif; ?>
                      </td>

                      <td>
                        <div style="font-size: 12.5px; color: #101828; font-weight: 600;">
                          <?= htmlspecialchars($m['email']) ?>
                        </div>
                        <span style="font-size: 10.5px; color: #027A48; font-weight: 700; background: #ECFDF3; padding: 1px 6px; border-radius: 4px;">
                          Akses Aktif
                        </span>
                      </td>

                      <td style="text-align: right; white-space: nowrap;">
                        <div style="display: inline-flex; gap: 6px; justify-content: flex-end; align-items: center;">
                          <button type="button" class="btn btn-secondary btn-sm" onclick='openEditTeamModal(<?= json_encode($m, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' style="padding: 5px 12px; font-size: 12px; font-weight: 600;">
                            Edit
                          </button>
                          <button type="button" class="btn-delete-ghost" onclick="confirmDeleteTeamMember(<?= $m['id'] ?>, '<?= htmlspecialchars($m['name'], ENT_QUOTES) ?>')" style="padding: 5px 10px; font-size: 12px; font-weight: 600;">
                            Hapus
                          </button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </main>
  </div>

  <!-- Modal 1: Tambah Karyawan / Tim Baru -->
  <div id="modal-create-team-member" class="modal-backdrop">
    <div class="modal-dialog modal-lg">
      <div class="modal-header">
        <h3 class="modal-title">+ Tambah Karyawan / Tim Baru</h3>
        <button type="button" class="modal-close" onclick="closeModal('modal-create-team-member')">&times;</button>
      </div>
      <form id="form-create-team-member" action="api/team.php?action=save" method="POST">
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Nama Lengkap Karyawan / Tim <span style="color:red;">*</span></label>
              <input type="text" name="name" class="form-control" placeholder="Contoh: Budi Santoso" required>
            </div>
            <div class="form-group">
              <label class="form-label">Status SDM <span style="color:red;">*</span></label>
              <select name="employment_type" class="form-select" required>
                <option value="karyawan">Karyawan Kalamedia (In-House)</option>
                <option value="freelancer">Freelancer (Talenta Eksternal)</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Posisi / Spesialisasi <span style="color:red;">*</span></label>
            <input type="text" name="position" class="form-control" placeholder="Contoh: Meta & Google Ads Specialist, Graphic Designer, Video Editor" required>
          </div>

          <div class="form-group">
            <label class="form-label">Detail Jobdesk dari Owner <span style="color:red;">*</span></label>
            <textarea name="jobdesk" class="form-control" rows="3" placeholder="Tuliskan rincian tanggung jawab dan tugas utama yang diberikan oleh Owner kepada dia..." required></textarea>
          </div>

          <!-- Pegangan Klien / Proyek PT -->
          <div class="form-group">
            <label class="form-label">Pegangan Klien / Proyek PT (Pilih Perusahaan yang Dipegang)</label>
            <div class="checkbox-clients-grid">
              <?php if (empty($allClients)): ?>
                <div style="font-size: 12px; color: var(--text-muted); grid-column: 1 / -1;">Belum ada data klien terdaftar. Anda dapat menambahkannya nanti.</div>
              <?php else: ?>
                <?php foreach ($allClients as $cl): ?>
                  <label class="checkbox-client-item">
                    <input type="checkbox" name="client_ids[]" value="<?= $cl['id'] ?>">
                    <span><b><?= htmlspecialchars($cl['company']) ?></b> (PIC: <?= htmlspecialchars($cl['pic_name']) ?>)</span>
                  </label>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 16px 0;">
          <div style="font-size: 12px; font-weight: 800; color: #101828; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">
            Kredensial Akses Login Sistem Kalamedia
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Email / Username Login <span style="color:red;">*</span></label>
              <input type="email" name="email" class="form-control" placeholder="karyawan@kalamedia.id" required>
            </div>
            <div class="form-group">
              <label class="form-label">Password Akses Sistem <span style="color:red;">*</span></label>
              <div style="position: relative; display: flex; align-items: center;">
                <input type="password" name="password" id="create-team-password" class="form-control" placeholder="Password untuk login" required style="padding-right: 55px;">
                <button type="button" onclick="togglePasswordVisibility('create-team-password', this)" style="position: absolute; right: 10px; background: none; border: none; font-size: 11px; font-weight: 700; color: var(--primary); cursor: pointer; padding: 4px;">Lihat</button>
              </div>
            </div>
          </div>

          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Nomor WhatsApp (Untuk Notifikasi Tugas & Revisi)</label>
            <input type="text" name="phone_wa" class="form-control" placeholder="Contoh: 081234567890">
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-team-member')">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan & Buat Akun Karyawan</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal 2: Edit Karyawan / Tim -->
  <div id="modal-edit-team-member" class="modal-backdrop">
    <div class="modal-dialog modal-lg">
      <div class="modal-header">
        <h3 class="modal-title">Edit Data Tim & Jobdesk</h3>
        <button type="button" class="modal-close" onclick="closeModal('modal-edit-team-member')">&times;</button>
      </div>
      <form id="form-edit-team-member" action="api/team.php?action=save" method="POST">
        <input type="hidden" name="id" id="edit-team-id">
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Nama Lengkap Karyawan / Tim <span style="color:red;">*</span></label>
              <input type="text" name="name" id="edit-team-name" class="form-control" required>
            </div>
            <div class="form-group">
              <label class="form-label">Status SDM <span style="color:red;">*</span></label>
              <select name="employment_type" id="edit-team-emp-type" class="form-select" required>
                <option value="karyawan">Karyawan Kalamedia (In-House)</option>
                <option value="freelancer">Freelancer (Talenta Eksternal)</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Posisi / Spesialisasi <span style="color:red;">*</span></label>
            <input type="text" name="position" id="edit-team-position" class="form-control" required>
          </div>

          <div class="form-group">
            <label class="form-label">Detail Jobdesk dari Owner <span style="color:red;">*</span></label>
            <textarea name="jobdesk" id="edit-team-jobdesk" class="form-control" rows="3" required></textarea>
          </div>

          <!-- Pegangan Klien / Proyek PT -->
          <div class="form-group">
            <label class="form-label">Pegangan Klien / Proyek PT</label>
            <div class="checkbox-clients-grid" id="edit-team-clients-container">
              <?php foreach ($allClients as $cl): ?>
                <label class="checkbox-client-item">
                  <input type="checkbox" name="client_ids[]" value="<?= $cl['id'] ?>" class="edit-client-checkbox">
                  <span><b><?= htmlspecialchars($cl['company']) ?></b></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 16px 0;">
          <div style="font-size: 12px; font-weight: 800; color: #101828; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">
            Kredensial Akses Login
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Email / Username Login <span style="color:red;">*</span></label>
              <input type="email" name="email" id="edit-team-email" class="form-control" required>
            </div>
            <div class="form-group">
              <label class="form-label">Reset Password (Kosongkan jika tidak diubah)</label>
              <div style="position: relative; display: flex; align-items: center;">
                <input type="password" name="password" id="edit-team-password" class="form-control" placeholder="Masukkan password baru jika ingin reset" style="padding-right: 55px;">
                <button type="button" onclick="togglePasswordVisibility('edit-team-password', this)" style="position: absolute; right: 10px; background: none; border: none; font-size: 11px; font-weight: 700; color: var(--primary); cursor: pointer; padding: 4px;">Lihat</button>
              </div>
            </div>
          </div>

          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Nomor WhatsApp</label>
            <input type="text" name="phone_wa" id="edit-team-phone" class="form-control">
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-team-member')">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>

  <?php require_once BASE_PATH . '/includes/modals.php'; ?>
  <?php require_once BASE_PATH . '/includes/footer.php'; ?>

  <script>
    function togglePasswordVisibility(inputId, btn) {
      const input = document.getElementById(inputId);
      if (!input) return;
      if (input.type === 'password') {
        input.type = 'text';
        if (btn) btn.innerText = 'Tutup';
      } else {
        input.type = 'password';
        if (btn) btn.innerText = 'Lihat';
      }
    }

    function filterTeamRole(role, btn) {
      document.querySelectorAll('.panel-header .filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const rows = document.querySelectorAll('.team-row');
      rows.forEach(r => {
        if (role === 'all' || r.getAttribute('data-role') === role) {
          r.style.display = '';
        } else {
          r.style.display = 'none';
        }
      });
    }

    function openEditTeamModal(m) {
      document.getElementById('edit-team-id').value = m.id || '';
      document.getElementById('edit-team-name').value = m.name || '';
      document.getElementById('edit-team-emp-type').value = m.employment_type || 'karyawan';
      document.getElementById('edit-team-position').value = m.position || '';
      document.getElementById('edit-team-jobdesk').value = m.jobdesk || '';
      document.getElementById('edit-team-email').value = m.email || '';
      document.getElementById('edit-team-phone').value = m.phone_wa || '';
      document.getElementById('edit-team-password').value = '';

      // Checkboxes
      const clientIds = (m.client_ids || []).map(Number);
      document.querySelectorAll('.edit-client-checkbox').forEach(cb => {
        cb.checked = clientIds.includes(parseInt(cb.value));
      });

      openModal('modal-edit-team-member');
    }

    async function confirmDeleteTeamMember(id, name) {
      if (!confirm(`Apakah Anda yakin ingin menghapus anggota tim ${name}? Akses login akan dinonaktifkan.`)) {
        return;
      }

      const formData = new FormData();
      formData.append('id', id);

      try {
        const res = await fetch('api/team.php?action=delete', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          setTimeout(() => window.location.reload(), 600);
        } else {
          showToast(data.message || 'Gagal menghapus anggota tim', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      }
    }

    document.addEventListener('DOMContentLoaded', () => {
      handleAjaxForm('form-create-team-member', () => {
        closeModal('modal-create-team-member');
        setTimeout(() => window.location.reload(), 600);
      });

      handleAjaxForm('form-edit-team-member', () => {
        closeModal('modal-edit-team-member');
        setTimeout(() => window.location.reload(), 600);
      });
    });
  </script>
</body>
</html>

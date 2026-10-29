<?php
/**
 * Kalamedia Settings View (Restricted: Owner & Admin - Untitled UI Design System)
 */
require_admin();
$db = Database::getConnection();
$users = $db->query("SELECT id, name, email, role, created_at FROM users ORDER BY id ASC")->fetchAll();
$savedGeminiKey = get_agency_setting('gemini_api_key', '');
$savedGeminiModel = get_agency_setting('gemini_model', 'gemini-2.5-pro') ?: 'gemini-2.5-pro';
$standardGeminiModels = [
    'gemini-2.5-pro',
    'gemini-3.1-pro-preview',
    'gemini-3.0-pro',
    'gemini-3.0-flash',
    'gemini-3-flash-preview',
    'gemini-2.5-pro-preview',
    'gemini-2.5-flash'
];
$isCustomModelSaved = !in_array($savedGeminiModel, $standardGeminiModels, true);
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
  <?php render_tab_uid_script(); ?>
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
            .kala-grid-2col,
            .kala-ai-form-grid {
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
                  Profil &amp; Rekening Agensi
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
                <div style="background: var(--bg-input, #F9FAFB); padding: 14px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); font-size: 13px;">
                  <div style="font-weight: 700; color: var(--text-primary, #101828);"><?= AGENCY_BANK_NAME ?></div>
                  <div style="font-size: 17px; font-weight: 800; color: var(--text-primary, #101828); margin: 4px 0; letter-spacing: -0.2px;"><?= AGENCY_BANK_ACCOUNT ?></div>
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
                    <th style="white-space: nowrap;">Nama &amp; Email</th>
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

            <div style="margin-top: 18px; display: flex; align-items: center; justify-content: space-between; gap: 12px; background: #F8FAFC; border: 1px solid var(--border-color); border-left: 4px solid #5D76F5; border-radius: var(--radius-sm); padding: 14px 16px; font-size: 12px; color: var(--text-secondary);">
              <div>
                <strong style="color: #000000;">Keamanan Sistem:</strong> Admin Finance tidak memiliki wewenang untuk mengubah pengaturan akun Owner atau menghapus arsip keuangan secara permanen.
              </div>
              <span class="badge-version" style="flex-shrink: 0;" title="Versi Rilis Sistem">
                <?= defined('APP_VERSION') ? APP_VERSION : 'v3.0' ?>
              </span>
            </div>
          </div>

          <!-- Integrasi Kala AI (Google Gemini Pro Engine) -->
          <div class="glass-panel kala-ai-settings-panel" style="grid-column: 1 / -1; overflow: visible; min-width: 0; max-width: 100%;">
            <style>
              .kala-ai-settings-panel {
                background: #FFFFFF;
                border: 1px solid #E2E8F0;
                border-radius: 18px;
                padding: 24px !important;
                box-shadow: 0 4px 20px -4px rgba(15, 23, 42, 0.05);
              }
              .kala-ai-header-bar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 16px;
                flex-wrap: wrap;
                padding-bottom: 18px;
                margin-bottom: 20px;
                border-bottom: 1px solid #EEF2F6;
              }
              .kala-ai-icon-box {
                width: 44px;
                height: 44px;
                border-radius: 12px;
                background: linear-gradient(135deg, #4F46E5 0%, #2563EB 100%);
                color: #FFFFFF;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                box-shadow: 0 6px 16px rgba(79, 70, 229, 0.25);
              }
              .kala-ai-pro-tag {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                background: rgba(79, 70, 229, 0.1);
                color: #4F46E5;
                border: 1px solid rgba(79, 70, 229, 0.25);
                font-size: 10.5px;
                font-weight: 800;
                padding: 2px 8px;
                border-radius: 6px;
                letter-spacing: 0.3px;
              }
              .kala-ai-badge-pill {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                padding: 6px 13px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 700;
                line-height: 1;
                white-space: nowrap;
              }
              .kala-ai-badge-pill .status-dot-ind {
                width: 7px;
                height: 7px;
                border-radius: 50%;
                display: inline-block;
              }
              .kala-ai-badge-pill.connected {
                background: #ECFDF3;
                color: #027A48;
                border: 1px solid #A6F4C5;
              }
              .kala-ai-badge-pill.connected .status-dot-ind {
                background: #10B981;
                box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
              }
              .kala-ai-badge-pill.unconfigured {
                background: #FFFBEB;
                color: #B45309;
                border: 1px solid #FDE68A;
              }
              .kala-ai-badge-pill.unconfigured .status-dot-ind {
                background: #F59E0B;
              }
              .kala-ai-form-grid {
                display: grid;
                grid-template-columns: 1.25fr 1.25fr;
                gap: 20px;
                align-items: start;
              }
              .kala-ai-field-head {
                display: flex;
                justify-content: space-between;
                align-items: center;
                min-height: 28px;
                margin-bottom: 8px;
                gap: 8px;
              }
              .kala-ai-field-label {
                font-size: 12.5px;
                font-weight: 700;
                color: #1E293B;
                margin: 0;
                display: inline-flex;
                align-items: center;
                gap: 4px;
              }
              .kala-ai-studio-link {
                font-size: 11.5px;
                font-weight: 600;
                color: #4F46E5;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 4px;
                transition: opacity 0.15s ease;
              }
              .kala-ai-studio-link:hover {
                opacity: 0.8;
                text-decoration: underline;
              }
              .kala-ai-sync-btn {
                background: rgba(79, 70, 229, 0.08);
                border: 1px solid rgba(79, 70, 229, 0.28);
                color: #4F46E5;
                border-radius: 7px;
                height: 26px;
                padding: 0 10px;
                font-size: 11.5px;
                font-weight: 700;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 5px;
                transition: all 0.15s ease;
                white-space: nowrap;
              }
              .kala-ai-sync-btn:hover:not(:disabled) {
                background: rgba(79, 70, 229, 0.15);
                border-color: #4F46E5;
              }
              .kala-ai-sync-btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
              }
              .kala-ai-input-wrap {
                position: relative;
                display: flex;
                align-items: center;
              }
              .kala-ai-control {
                height: 42px !important;
                border-radius: 10px !important;
                font-size: 13px !important;
                box-sizing: border-box !important;
              }
              .kala-ai-eye-btn {
                position: absolute;
                right: 8px;
                top: 50%;
                transform: translateY(-50%);
                background: transparent;
                border: none;
                color: #64748B;
                cursor: pointer;
                height: 28px;
                padding: 0 9px;
                border-radius: 6px;
                display: inline-flex;
                align-items: center;
                gap: 5px;
                font-size: 11.5px;
                font-weight: 600;
                transition: all 0.15s ease;
              }
              .kala-ai-eye-btn:hover {
                background: rgba(79, 70, 229, 0.08);
                color: #4F46E5;
              }
              .kala-ai-info-note {
                background: #F8FAFC;
                border: 1px solid #E2E8F0;
                border-radius: 12px;
                padding: 13px 16px;
                font-size: 12.5px;
                color: #475569;
                margin-top: 16px;
                display: flex;
                align-items: flex-start;
                gap: 12px;
                line-height: 1.55;
              }
              .kala-ai-info-icon {
                width: 30px;
                height: 30px;
                border-radius: 8px;
                background: rgba(79, 70, 229, 0.1);
                color: #4F46E5;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                margin-top: 1px;
              }
              .kala-ai-status-box {
                display: none;
                margin-top: 14px;
                padding: 13px 16px;
                border-radius: 12px;
                font-size: 12.5px;
                line-height: 1.5;
                align-items: flex-start;
                gap: 10px;
              }
              .kala-ai-status-box.success {
                display: flex;
                background: #ECFDF3;
                border: 1px solid #A6F4C5;
                color: #027A48;
              }
              .kala-ai-status-box.error {
                display: flex;
                background: #FEF3F2;
                border: 1px solid #FECDCA;
                color: #B42318;
              }
              .kala-ai-actions-footer {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                margin-top: 18px;
                padding-top: 16px;
                border-top: 1px solid #EEF2F6;
                flex-wrap: wrap;
              }
              .kala-ai-btn-primary {
                height: 38px;
                padding: 0 18px;
                border-radius: 10px;
                font-size: 12.5px;
                font-weight: 700;
                background: linear-gradient(135deg, #4F46E5 0%, #3B82F6 100%);
                color: #FFFFFF;
                border: none;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
                transition: all 0.15s ease;
              }
              .kala-ai-btn-primary:hover:not(:disabled) {
                transform: translateY(-1px);
                box-shadow: 0 6px 16px rgba(79, 70, 229, 0.35);
              }
              .kala-ai-btn-secondary {
                height: 38px;
                padding: 0 16px;
                border-radius: 10px;
                font-size: 12.5px;
                font-weight: 700;
                background: #FFFFFF;
                color: #1E293B;
                border: 1px solid #CBD5E1;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: all 0.15s ease;
              }
              .kala-ai-btn-secondary:hover:not(:disabled) {
                border-color: #4F46E5;
                color: #4F46E5;
                background: #F8FAFC;
              }

              /* NebulaPay Dark Mode Overrides */
              html[data-theme="dark"] .kala-ai-settings-panel,
              body.dark-mode .kala-ai-settings-panel {
                background: #0F1420 !important;
                border-color: rgba(255, 255, 255, 0.08) !important;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4) !important;
              }
              html[data-theme="dark"] .kala-ai-header-bar,
              body.dark-mode .kala-ai-header-bar,
              html[data-theme="dark"] .kala-ai-actions-footer,
              body.dark-mode .kala-ai-actions-footer {
                border-color: rgba(255, 255, 255, 0.07) !important;
              }
              html[data-theme="dark"] .kala-ai-pro-tag,
              body.dark-mode .kala-ai-pro-tag {
                background: rgba(99, 102, 241, 0.18) !important;
                color: #818CF8 !important;
                border-color: rgba(99, 102, 241, 0.35) !important;
              }
              html[data-theme="dark"] .kala-ai-badge-pill.connected,
              body.dark-mode .kala-ai-badge-pill.connected {
                background: rgba(16, 185, 129, 0.14) !important;
                color: #34D399 !important;
                border-color: rgba(52, 211, 153, 0.32) !important;
              }
              html[data-theme="dark"] .kala-ai-badge-pill.unconfigured,
              body.dark-mode .kala-ai-badge-pill.unconfigured {
                background: rgba(245, 158, 11, 0.14) !important;
                color: #FBBF24 !important;
                border-color: rgba(251, 191, 36, 0.32) !important;
              }
              html[data-theme="dark"] .kala-ai-field-label,
              body.dark-mode .kala-ai-field-label {
                color: #F1F5F9 !important;
              }
              html[data-theme="dark"] .kala-ai-studio-link,
              body.dark-mode .kala-ai-studio-link {
                color: #818CF8 !important;
              }
              html[data-theme="dark"] .kala-ai-sync-btn,
              body.dark-mode .kala-ai-sync-btn {
                background: rgba(99, 102, 241, 0.14) !important;
                border-color: rgba(99, 102, 241, 0.35) !important;
                color: #A5B4FC !important;
              }
              html[data-theme="dark"] .kala-ai-sync-btn:hover:not(:disabled),
              body.dark-mode .kala-ai-sync-btn:hover:not(:disabled) {
                background: rgba(99, 102, 241, 0.25) !important;
                color: #FFFFFF !important;
              }
              html[data-theme="dark"] .kala-ai-eye-btn,
              body.dark-mode .kala-ai-eye-btn {
                color: #94A3B8 !important;
              }
              html[data-theme="dark"] .kala-ai-eye-btn:hover,
              body.dark-mode .kala-ai-eye-btn:hover {
                color: #F8FAFC !important;
                background: rgba(255, 255, 255, 0.08) !important;
              }
              html[data-theme="dark"] .kala-ai-info-note,
              body.dark-mode .kala-ai-info-note {
                background: #141B2D !important;
                border-color: rgba(255, 255, 255, 0.07) !important;
                color: #CBD5E1 !important;
              }
              html[data-theme="dark"] .kala-ai-info-icon,
              body.dark-mode .kala-ai-info-icon {
                background: rgba(56, 189, 248, 0.14) !important;
                color: #38BDF8 !important;
              }
              html[data-theme="dark"] .kala-ai-status-box.success,
              body.dark-mode .kala-ai-status-box.success {
                background: rgba(6, 78, 59, 0.38) !important;
                border-color: rgba(52, 211, 153, 0.35) !important;
                color: #6EE7B7 !important;
              }
              html[data-theme="dark"] .kala-ai-status-box.error,
              body.dark-mode .kala-ai-status-box.error {
                background: rgba(127, 29, 29, 0.38) !important;
                border-color: rgba(248, 113, 113, 0.35) !important;
                color: #FCA5A5 !important;
              }
              html[data-theme="dark"] .kala-ai-btn-secondary,
              body.dark-mode .kala-ai-btn-secondary {
                background: #161F33 !important;
                border-color: rgba(255, 255, 255, 0.12) !important;
                color: #F1F5F9 !important;
              }
              html[data-theme="dark"] .kala-ai-btn-secondary:hover:not(:disabled),
              body.dark-mode .kala-ai-btn-secondary:hover:not(:disabled) {
                border-color: #818CF8 !important;
                color: #FFFFFF !important;
                background: #1E293B !important;
              }
            </style>

            <!-- Header -->
            <div class="kala-ai-header-bar">
              <div style="display: flex; align-items: center; gap: 14px;">
                <div class="kala-ai-icon-box">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3l1.912 5.813a2 2 0 001.275 1.275L21 12l-5.813 1.912a2 2 0 00-1.275 1.275L12 21l-1.912-5.813a2 2 0 00-1.275-1.275L3 12l5.813-1.912a2 2 0 001.275-1.275L12 3z"/>
                    <path d="M5 3v4"/><path d="M3 5h4"/>
                  </svg>
                </div>
                <div>
                  <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <h3 class="panel-title" style="margin: 0; font-size: 15.5px; font-weight: 800;">
                      Integrasi Kala AI (Google Gemini Pro Engine)
                    </h3>
                    <span class="kala-ai-pro-tag">PRO TIER</span>
                  </div>
                  <p style="font-size: 12.5px; color: var(--text-secondary); margin: 3px 0 0;">
                    Mesin kecerdasan buatan Deep Reasoning untuk <b>Kala AI Brand Diagnostic &amp; Strategy Blueprint</b> (Model Aktif: <code id="gemini-active-model-code"><?= htmlspecialchars($savedGeminiModel) ?></code>)
                  </p>
                </div>
              </div>

              <span id="gemini-key-status-badge" class="kala-ai-badge-pill <?= !empty($savedGeminiKey) ? 'connected' : 'unconfigured' ?>">
                <span class="status-dot-ind"></span>
                <span id="gemini-key-status-text"><?= !empty($savedGeminiKey) ? 'API Key Pro Tersimpan (' . htmlspecialchars($savedGeminiModel) . ')' : 'API Key Belum Diatur' ?></span>
              </span>
            </div>

            <!-- Form -->
            <form id="form-gemini-settings" onsubmit="handleSaveGeminiSettings(event)">
              <div class="kala-ai-form-grid">
                
                <!-- Left Column: API Key Input -->
                <div>
                  <div class="kala-ai-field-head">
                    <label class="kala-ai-field-label" for="input-gemini-api-key">
                      <span>Google Gemini Pro API Key</span>
                      <span style="color: #EF4444;">*</span>
                    </label>
                    <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener" class="kala-ai-studio-link">
                      <span>Buka Google AI Studio</span>
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    </a>
                  </div>
                  <div class="kala-ai-input-wrap">
                    <input
                      type="password"
                      id="input-gemini-api-key"
                      name="gemini_api_key"
                      class="form-control kala-ai-control"
                      style="padding-right: 84px; font-family: monospace; letter-spacing: 0.3px;"
                      placeholder="AIzaSyYourGoogleGeminiProApiKey..."
                      value="<?= htmlspecialchars($savedGeminiKey) ?>"
                      autocomplete="off"
                    >
                    <button type="button" class="kala-ai-eye-btn" onclick="toggleGeminiKeyVisibility(this)" title="Tampilkan / Sembunyikan API Key">
                      <svg id="gemini-eye-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                      <span id="gemini-eye-label">Show</span>
                    </button>
                  </div>
                </div>

                <!-- Right Column: Model Selector -->
                <div>
                  <div class="kala-ai-field-head">
                    <label class="kala-ai-field-label" for="select-gemini-model">
                      <span>Model AI Gemini Pro</span>
                    </label>
                    <button type="button" id="btn-fetch-gemini-models" class="kala-ai-sync-btn" onclick="handleFetchGeminiModels()">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                      <span>Muat Daftar Model Terbaru dari API Key</span>
                    </button>
                  </div>
                  <select id="select-gemini-model" name="gemini_model" class="form-control form-select kala-ai-control" onchange="handleGeminiModelSelectChange(this.value)">
                    <option value="gemini-2.5-pro" <?= $savedGeminiModel === 'gemini-2.5-pro' ? 'selected' : '' ?>>gemini-2.5-pro (Default — Deep Reasoning &amp; Brand Strategy Pro)</option>
                    <option value="gemini-3.1-pro-preview" <?= $savedGeminiModel === 'gemini-3.1-pro-preview' ? 'selected' : '' ?>>gemini-3.1-pro-preview (Frontier Pro Preview Terbaru)</option>
                    <option value="gemini-3.0-pro" <?= $savedGeminiModel === 'gemini-3.0-pro' ? 'selected' : '' ?>>gemini-3.0-pro (Gemini 3.0 Pro Reasoning)</option>
                    <option value="gemini-3.0-flash" <?= $savedGeminiModel === 'gemini-3.0-flash' ? 'selected' : '' ?>>gemini-3.0-flash (Gemini 3.0 Fast Hybrid)</option>
                    <option value="gemini-3-flash-preview" <?= $savedGeminiModel === 'gemini-3-flash-preview' ? 'selected' : '' ?>>gemini-3-flash-preview (Gemini 3 Flash Preview)</option>
                    <option value="gemini-2.5-pro-preview" <?= $savedGeminiModel === 'gemini-2.5-pro-preview' ? 'selected' : '' ?>>gemini-2.5-pro-preview (Frontier Pro Preview)</option>
                    <option value="gemini-2.5-flash" <?= $savedGeminiModel === 'gemini-2.5-flash' ? 'selected' : '' ?>>gemini-2.5-flash (Fast Hybrid Pro)</option>
                    <?php if ($isCustomModelSaved): ?>
                      <option value="<?= htmlspecialchars($savedGeminiModel) ?>" selected><?= htmlspecialchars($savedGeminiModel) ?> (Model Aktif Tersimpan)</option>
                    <?php endif; ?>
                    <option value="custom">Custom / Model Terbaru Lainnya...</option>
                  </select>

                  <div id="custom-gemini-model-wrap" style="display: none; margin-top: 8px;">
                    <input
                      type="text"
                      id="input-gemini-custom-model"
                      name="gemini_custom_model"
                      class="form-control kala-ai-control"
                      style="font-family: monospace; font-size: 12.5px;"
                      placeholder="Ketik ID model (contoh: gemini-3.1-pro-preview / gemini-3.0-flash)"
                      value="<?= $isCustomModelSaved ? htmlspecialchars($savedGeminiModel) : '' ?>"
                    >
                    <small style="font-size: 11px; color: var(--text-secondary); display: block; margin-top: 4px;">
                      Ketik ID model Google Gemini terbaru/eksperimental secara bebas.
                    </small>
                  </div>
                </div>

              </div>

              <!-- Smart Fallback Info Banner -->
              <div class="kala-ai-info-note">
                <div class="kala-ai-info-icon">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <div>
                  <b>Google Gemini Pro Engine + Live Auto-Discovery:</b> Sistem menguji model yang Anda pilih di layar secara langsung. Jika model mengalami batas kuota (<code>429</code>) atau tidak lagi tersedia di akun baru (<code>404</code>), sistem otomatis memanggil <code>GET /v1beta/models</code> untuk mendeteksi dan beralih ke model generasi terbaru yang aktif di akun Anda (Prioritas: <code>gemini-3.1-pro</code> &rarr; <code>gemini-3.0-flash</code> &rarr; <code>gemini-2.5-pro</code>).
                </div>
              </div>

              <!-- Dynamic Connection / Model Test Alert Box -->
              <div id="gemini-test-result-box" class="kala-ai-status-box"></div>

              <!-- Footer Buttons -->
              <div class="kala-ai-actions-footer">
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                  <button type="submit" id="btn-save-gemini" class="kala-ai-btn-primary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    <span>Simpan Konfigurasi AI</span>
                  </button>
                  <button type="button" id="btn-test-gemini" class="kala-ai-btn-secondary" onclick="handleTestGeminiConnection()">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    <span>Tes Koneksi Gemini Pro</span>
                  </button>
                </div>
                <span style="font-size: 11.5px; color: var(--text-secondary);">
                  Endpoint: <code>generativelanguage.googleapis.com/v1beta</code>
                </span>
              </div>
            </form>
          </div>

          <script>
            const KALA_ICON_ALERT = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
            const KALA_ICON_CHECK = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
            const KALA_ICON_ERROR = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
            const KALA_ICON_SPIN = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/></svg>';

            function escapeGeminiHtml(str) {
              return String(str || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
            }

            function syncGeminiModelDropdownToActive(modelUsed, discoveredModels) {
              const select = document.getElementById('select-gemini-model');
              const customWrap = document.getElementById('custom-gemini-model-wrap');
              const activeCodeEl = document.getElementById('gemini-active-model-code');
              if (activeCodeEl && modelUsed) {
                activeCodeEl.textContent = modelUsed;
              }
              if (!select) return;

              if (Array.isArray(discoveredModels) && discoveredModels.length > 0) {
                select.innerHTML = '';
                discoveredModels.forEach(m => {
                  const opt = document.createElement('option');
                  opt.value = m.id;
                  const proTag = m.is_pro ? '[PRO] ' : '';
                  opt.textContent = `${proTag}${m.id} — ${m.display_name || ''}`;
                  if (modelUsed && m.id === modelUsed) opt.selected = true;
                  select.appendChild(opt);
                });
                const customOpt = document.createElement('option');
                customOpt.value = 'custom';
                customOpt.textContent = 'Custom / Model Terbaru Lainnya...';
                select.appendChild(customOpt);
              }

              if (modelUsed) {
                let found = false;
                for (let i = 0; i < select.options.length; i++) {
                  if (select.options[i].value === modelUsed) {
                    select.selectedIndex = i;
                    found = true;
                    break;
                  }
                }
                if (!found) {
                  const newOpt = document.createElement('option');
                  newOpt.value = modelUsed;
                  newOpt.textContent = `${modelUsed} (Auto-Discovered Active Model)`;
                  newOpt.selected = true;
                  const customIndex = select.options.length - 1;
                  if (customIndex >= 0 && select.options[customIndex].value === 'custom') {
                    select.insertBefore(newOpt, select.options[customIndex]);
                  } else {
                    select.appendChild(newOpt);
                  }
                }
                if (customWrap) customWrap.style.display = 'none';
              }
            }

            function renderGeminiAttemptsLog(attempts) {
              if (!Array.isArray(attempts) || attempts.length === 0) return '';
              // Only show detailed log if more than 1 model was tried OR if the attempt failed
              const hasFailed = attempts.some(a => a.status === 'failed');
              if (attempts.length === 1 && !hasFailed) return '';

              const rowsHtml = attempts.map((att, idx) => {
                const isOk = att.status === 'success';
                const statusBadge = isOk
                  ? `<span style="display:inline-flex;align-items:center;gap:4px;font-weight:700;color:#10B981;">&#10003; TERHUBUNG</span>`
                  : `<span style="display:inline-flex;align-items:center;gap:4px;font-weight:700;color:#F87171;">&#10005; GAGAL</span>`;
                return `
                  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:5px 8px;border-radius:6px;background:rgba(15,23,42,0.06);font-size:11.5px;margin-top:4px;">
                    <div style="font-family:monospace;font-weight:700;">#${idx + 1} ${escapeGeminiHtml(att.model)}</div>
                    <div style="flex:1;opacity:0.92;">${escapeGeminiHtml(att.detail || '')}</div>
                    <div style="flex-shrink:0;">${statusBadge}</div>
                  </div>
                `;
              }).join('');

              return `
                <div style="margin-top:8px;padding-top:8px;border-top:1px dashed rgba(148,163,184,0.35);">
                  <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:0.4px;margin-bottom:4px;opacity:0.85;">
                    Log Diagnostik Model &amp; Auto-Discovery:
                  </div>
                  ${rowsHtml}
                </div>
              `;
            }

            function handleGeminiModelSelectChange(val) {
              const customWrap = document.getElementById('custom-gemini-model-wrap');
              const customInput = document.getElementById('input-gemini-custom-model');
              if (!customWrap) return;
              if (val === 'custom') {
                customWrap.style.display = 'block';
                if (customInput) customInput.focus();
              } else {
                customWrap.style.display = 'none';
              }
            }

            function getSelectedGeminiModelValue() {
              const select = document.getElementById('select-gemini-model');
              const customInput = document.getElementById('input-gemini-custom-model');
              if (!select) return 'gemini-2.5-pro';
              if (select.value === 'custom') {
                return (customInput && customInput.value.trim() !== '') ? customInput.value.trim() : 'gemini-2.5-pro';
              }
              return select.value;
            }

            async function handleFetchGeminiModels() {
              const btn = document.getElementById('btn-fetch-gemini-models');
              const apiKey = document.getElementById('input-gemini-api-key').value.trim();
              const select = document.getElementById('select-gemini-model');
              const resultBox = document.getElementById('gemini-test-result-box');

              if (!apiKey) {
                if (typeof showToast === 'function') showToast('Masukkan API Key terlebih dahulu untuk memuat daftar model.', 'warning');
                if (resultBox) {
                  resultBox.className = 'kala-ai-status-box error';
                  resultBox.style.display = 'flex';
                  resultBox.innerHTML = `${KALA_ICON_ALERT}<div><b>API Key Diperlukan:</b> Silakan isi Google Gemini API Key terlebih dahulu sebelum memuat daftar model.</div>`;
                }
                return;
              }

              const origHtml = btn.innerHTML;
              btn.disabled = true;
              btn.innerHTML = `${KALA_ICON_SPIN}<span>Memuat Model...</span>`;

              try {
                const formData = new FormData();
                formData.append('action', 'list_models');
                formData.append('gemini_api_key', apiKey);

                const res = await fetch('api/settings.php?action=list_models', {
                  method: 'POST',
                  body: formData
                });
                const data = await res.json();

                if (data.success && Array.isArray(data.models) && data.models.length > 0) {
                  const currentVal = getSelectedGeminiModelValue();
                  syncGeminiModelDropdownToActive(currentVal, data.models);
                  handleGeminiModelSelectChange(select.value);
                  if (typeof showToast === 'function') showToast(data.message, 'success');
                  if (resultBox) {
                    resultBox.className = 'kala-ai-status-box success';
                    resultBox.style.display = 'flex';
                    resultBox.innerHTML = `${KALA_ICON_CHECK}<div><b>${escapeGeminiHtml(data.message)}</b> Silakan pilih model dari dropdown di atas lalu klik Simpan atau Tes Koneksi.</div>`;
                  }
                } else {
                  if (typeof showToast === 'function') showToast(data.message || 'Gagal memuat daftar model', 'danger');
                  if (resultBox) {
                    resultBox.className = 'kala-ai-status-box error';
                    resultBox.style.display = 'flex';
                    resultBox.innerHTML = `${KALA_ICON_ERROR}<div><b>Gagal Memuat Model:</b> ${escapeGeminiHtml(data.message || 'Periksa kembali API Key Anda.')}</div>`;
                  }
                }
              } catch (err) {
                if (typeof showToast === 'function') showToast('Gagal menghubungi server untuk memuat model', 'danger');
              } finally {
                btn.disabled = false;
                btn.innerHTML = origHtml;
              }
            }

            function toggleGeminiKeyVisibility(btn) {
              const input = document.getElementById('input-gemini-api-key');
              const label = document.getElementById('gemini-eye-label');
              const icon = document.getElementById('gemini-eye-icon');
              if (!input) return;
              if (input.type === 'password') {
                input.type = 'text';
                if (label) label.textContent = 'Hide';
                if (icon) {
                  icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
                }
              } else {
                input.type = 'password';
                if (label) label.textContent = 'Show';
                if (icon) {
                  icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
                }
              }
            }

            async function handleSaveGeminiSettings(e) {
              e.preventDefault();
              const btn = document.getElementById('btn-save-gemini');
              const apiKey = document.getElementById('input-gemini-api-key').value.trim();
              const selectVal = document.getElementById('select-gemini-model').value;
              const customVal = (document.getElementById('input-gemini-custom-model')?.value || '').trim();
              const badge = document.getElementById('gemini-key-status-badge');
              const badgeText = document.getElementById('gemini-key-status-text');
              const activeCodeEl = document.getElementById('gemini-active-model-code');

              if (selectVal === 'custom' && !customVal) {
                if (typeof showToast === 'function') showToast('Silakan ketik ID model kustom terlebih dahulu.', 'warning');
                return;
              }

              const origHtml = btn.innerHTML;
              btn.disabled = true;
              btn.innerHTML = `${KALA_ICON_SPIN}<span>Menyimpan...</span>`;

              try {
                const formData = new FormData();
                formData.append('action', 'save_gemini');
                formData.append('gemini_api_key', apiKey);
                formData.append('gemini_model', selectVal);
                formData.append('gemini_custom_model', customVal);

                const res = await fetch('api/settings.php?action=save_gemini', {
                  method: 'POST',
                  body: formData
                });
                const data = await res.json();
                if (data.success) {
                  if (typeof showToast === 'function') showToast(data.message, 'success');
                  if (badge) {
                    badge.className = 'kala-ai-badge-pill ' + (data.has_key ? 'connected' : 'unconfigured');
                  }
                  if (badgeText) {
                    badgeText.textContent = data.has_key ? `API Key Pro Tersimpan (${data.gemini_model})` : 'API Key Belum Diatur';
                  }
                  if (activeCodeEl && data.gemini_model) {
                    activeCodeEl.textContent = data.gemini_model;
                  }
                } else {
                  if (typeof showToast === 'function') showToast(data.message || 'Gagal menyimpan pengaturan', 'danger');
                }
              } catch (err) {
                if (typeof showToast === 'function') showToast('Terjadi kesalahan koneksi server', 'danger');
              } finally {
                btn.disabled = false;
                btn.innerHTML = origHtml;
              }
            }

            async function handleTestGeminiConnection() {
              const btn = document.getElementById('btn-test-gemini');
              const apiKey = document.getElementById('input-gemini-api-key').value.trim();
              const selectEl = document.getElementById('select-gemini-model');
              const selectVal = selectEl ? selectEl.value : 'gemini-2.5-pro';
              const customVal = (document.getElementById('input-gemini-custom-model')?.value || '').trim();
              const activeModelInput = getSelectedGeminiModelValue();
              const resultBox = document.getElementById('gemini-test-result-box');
              const badge = document.getElementById('gemini-key-status-badge');
              const badgeText = document.getElementById('gemini-key-status-text');

              if (!apiKey) {
                if (typeof showToast === 'function') showToast('Silakan masukkan Gemini API Key terlebih dahulu.', 'warning');
                if (resultBox) {
                  resultBox.className = 'kala-ai-status-box error';
                  resultBox.style.display = 'flex';
                  resultBox.innerHTML = `${KALA_ICON_ALERT}<div><b>API Key Kosong:</b> Silakan isi kolom Google Gemini API Key sebelum melakukan tes koneksi.</div>`;
                }
                return;
              }

              const origHtml = btn.innerHTML;
              btn.disabled = true;
              btn.innerHTML = `${KALA_ICON_SPIN}<span>Menguji ${escapeGeminiHtml(activeModelInput)}...</span>`;
              if (resultBox) {
                resultBox.className = 'kala-ai-status-box';
                resultBox.style.display = 'none';
              }

              try {
                const formData = new FormData();
                formData.append('action', 'test_gemini');
                formData.append('gemini_api_key', apiKey);
                formData.append('gemini_model', selectVal);
                formData.append('gemini_custom_model', customVal);

                const res = await fetch('api/settings.php?action=test_gemini', {
                  method: 'POST',
                  body: formData
                });
                const data = await res.json();

                if (data.success) {
                  if (typeof showToast === 'function') showToast(data.message, 'success');

                  // Automatically sync dropdown & header badge to the working model (including auto-discovered model)
                  syncGeminiModelDropdownToActive(data.model_used || activeModelInput, data.discovered_models);

                  if (badge) {
                    badge.className = 'kala-ai-badge-pill connected';
                  }
                  if (badgeText) {
                    badgeText.textContent = 'Terhubung (' + (data.model_used || activeModelInput) + ')';
                  }
                  if (resultBox) {
                    resultBox.className = 'kala-ai-status-box success';
                    resultBox.style.display = 'flex';
                    const attemptsHtml = renderGeminiAttemptsLog(data.attempts);
                    resultBox.innerHTML = `
                      ${KALA_ICON_CHECK}
                      <div style="flex:1;min-width:0;">
                        <div style="font-weight: 800; margin-bottom: 3px;">${escapeGeminiHtml(data.message)}</div>
                        <div style="font-size: 12px; opacity: 0.92;">
                          <b>Model Aktif Tersimpan:</b> <code>${escapeGeminiHtml(data.model_used)}</code> &bull; <b>Respon:</b> "${escapeGeminiHtml(data.sample_reply || 'Koneksi Kala AI Pro aktif.')}"
                        </div>
                        ${attemptsHtml}
                      </div>
                    `;
                  }
                } else {
                  if (typeof showToast === 'function') showToast(data.message || 'Tes koneksi Gemini Pro gagal', 'danger');
                  if (Array.isArray(data.discovered_models) && data.discovered_models.length > 0) {
                    syncGeminiModelDropdownToActive(null, data.discovered_models);
                  }
                  if (resultBox) {
                    resultBox.className = 'kala-ai-status-box error';
                    resultBox.style.display = 'flex';
                    const attemptsHtml = renderGeminiAttemptsLog(data.attempts);
                    resultBox.innerHTML = `
                      ${KALA_ICON_ERROR}
                      <div style="flex:1;min-width:0;">
                        <div style="font-weight: 800; margin-bottom: 2px;">${escapeGeminiHtml(data.message || 'Gagal menghubungi Google Gemini Pro API')}</div>
                        <div style="font-size: 11.5px; opacity: 0.9;">Pastikan API Key Anda valid, memiliki kuota aktif, dan koneksi internet server berjalan normal.</div>
                        ${attemptsHtml}
                      </div>
                    `;
                  }
                }
              } catch (err) {
                if (typeof showToast === 'function') showToast('Gagal menghubungi endpoint tes Gemini Pro', 'danger');
                if (resultBox) {
                  resultBox.className = 'kala-ai-status-box error';
                  resultBox.style.display = 'flex';
                  resultBox.innerHTML = `${KALA_ICON_ERROR}<div><b>Gangguan Jaringan:</b> Tidak dapat menghubungi server API.</div>`;
                }
              } finally {
                btn.disabled = false;
                btn.innerHTML = origHtml;
              }
            }
          </script>

          <!-- NebulaPay Theme Appearance Settings Panel -->
          <div class="glass-panel" style="grid-column: 1 / -1; overflow: visible; min-width: 0; max-width: 100%;">
            <style>
              .kala-theme-option-card {
                cursor: pointer;
                border: 1.5px solid #E2E8F0;
                background: #FFFFFF;
                border-radius: 16px;
                padding: 20px;
                transition: all 0.2s ease;
                box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
                position: relative;
              }
              .kala-theme-option-card:hover {
                border-color: #94A3B8;
                transform: translateY(-1px);
              }
              .kala-theme-option-card.is-active {
                border: 2px solid #4F46E5 !important;
                background: #F8FAFF !important;
                box-shadow: 0 10px 26px -6px rgba(79, 70, 229, 0.16), 0 0 0 3px rgba(79, 70, 229, 0.1) !important;
              }
              .kala-theme-card-title {
                font-size: 14px;
                font-weight: 800;
                color: #0F172A;
              }
              .kala-theme-card-sub {
                font-size: 11.5px;
                color: #64748B;
                margin-top: 1px;
              }
              .kala-theme-spec-box {
                background: #F8FAFC;
                border: 1px solid #E2E8F0;
                border-radius: 12px;
                padding: 12px 14px;
                margin-bottom: 12px;
              }
              .kala-theme-spec-label {
                font-size: 11.5px;
                color: #64748B;
                font-weight: 500;
              }
              .kala-theme-card-desc {
                font-size: 12px;
                color: #475569;
                line-height: 1.5;
                margin: 0;
              }
              .kala-theme-icon-wrap.dark-icon {
                width: 38px;
                height: 38px;
                border-radius: 10px;
                background: #EEF2FF;
                color: #4F46E5;
                display: flex;
                align-items: center;
                justify-content: center;
                border: 1px solid #C7D2FE;
                flex-shrink: 0;
              }
              .kala-theme-icon-wrap.light-icon {
                width: 38px;
                height: 38px;
                border-radius: 10px;
                background: #FEF3C7;
                color: #D97706;
                display: flex;
                align-items: center;
                justify-content: center;
                border: 1px solid #FDE68A;
                flex-shrink: 0;
              }
              .kala-theme-status-badge {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 4px 10px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: 800;
                white-space: nowrap;
                transition: all 0.2s ease;
              }
              .kala-theme-status-badge.active {
                background: #ECFDF3;
                color: #027A48;
                border: 1px solid #6CE9A6;
              }
              .kala-theme-status-badge.inactive {
                background: #F1F5F9;
                color: #64748B;
                border: 1px solid #E2E8F0;
              }

              /* Dark Mode Overrides for Theme Selector Cards */
              html[data-theme="dark"] .kala-theme-option-card,
              body.dark-mode .kala-theme-option-card {
                background: #111726 !important;
                border: 1.5px solid rgba(255, 255, 255, 0.08) !important;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35) !important;
              }
              html[data-theme="dark"] .kala-theme-option-card:hover,
              body.dark-mode .kala-theme-option-card:hover {
                border-color: rgba(56, 189, 248, 0.35) !important;
              }
              html[data-theme="dark"] .kala-theme-option-card.is-active,
              body.dark-mode .kala-theme-option-card.is-active {
                background: #151E32 !important;
                border: 2px solid #38BDF8 !important;
                box-shadow: 0 12px 30px rgba(6, 182, 212, 0.18), 0 0 0 3px rgba(56, 189, 248, 0.14) !important;
              }
              html[data-theme="dark"] .kala-theme-card-title,
              body.dark-mode .kala-theme-card-title {
                color: #F8FAFC !important;
              }
              html[data-theme="dark"] .kala-theme-card-sub,
              body.dark-mode .kala-theme-card-sub,
              html[data-theme="dark"] .kala-theme-spec-label,
              body.dark-mode .kala-theme-spec-label,
              html[data-theme="dark"] .kala-theme-card-desc,
              body.dark-mode .kala-theme-card-desc {
                color: #94A3B8 !important;
              }
              html[data-theme="dark"] .kala-theme-spec-box,
              body.dark-mode .kala-theme-spec-box {
                background: #0B0F19 !important;
                border-color: rgba(255, 255, 255, 0.07) !important;
              }
              html[data-theme="dark"] .kala-theme-icon-wrap.dark-icon,
              body.dark-mode .kala-theme-icon-wrap.dark-icon {
                background: rgba(56, 189, 248, 0.14) !important;
                color: #38BDF8 !important;
                border-color: rgba(56, 189, 248, 0.35) !important;
              }
              html[data-theme="dark"] .kala-theme-icon-wrap.light-icon,
              body.dark-mode .kala-theme-icon-wrap.light-icon {
                background: rgba(251, 191, 36, 0.14) !important;
                color: #FBBF24 !important;
                border-color: rgba(251, 191, 36, 0.35) !important;
              }
              html[data-theme="dark"] .kala-theme-status-badge.active,
              body.dark-mode .kala-theme-status-badge.active {
                background: rgba(16, 185, 129, 0.18) !important;
                color: #34D399 !important;
                border-color: rgba(52, 211, 153, 0.4) !important;
              }
              html[data-theme="dark"] .kala-theme-status-badge.inactive,
              body.dark-mode .kala-theme-status-badge.inactive {
                background: rgba(255, 255, 255, 0.06) !important;
                color: #94A3B8 !important;
                border-color: rgba(255, 255, 255, 0.1) !important;
              }
            </style>

            <div class="panel-header">
              <div>
                <h3 class="panel-title">
                  Personalisasi Tema &amp; Antarmuka Sistem
                </h3>
                <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Pilih mode visual dashboard yang berlaku merata di seluruh halaman sistem Kalamedia</p>
              </div>
              <button type="button" id="btn-settings-toggle-theme" class="btn btn-secondary btn-sm" onclick="toggleTheme()" style="display: inline-flex; align-items: center; gap: 6px;">
                <svg id="icon-settings-toggle-theme" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                <span id="label-settings-toggle-theme">Ganti Tema Sekarang</span>
              </button>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-top: 8px;">
              <!-- Option 1: NebulaPay Enterprise Dark -->
              <div onclick="selectThemeMode('dark')" class="kala-theme-option-card" id="theme-card-dark">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 14px;">
                  <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="kala-theme-icon-wrap dark-icon">
                      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    </div>
                    <div>
                      <div class="kala-theme-card-title">NebulaPay Dark Mode</div>
                      <div class="kala-theme-card-sub">Enterprise Fintech Aesthetic</div>
                    </div>
                  </div>
                  <span id="theme-badge-dark" class="kala-theme-status-badge active">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>Mode Aktif Saat Ini</span>
                  </span>
                </div>
                <div class="kala-theme-spec-box">
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <span class="kala-theme-spec-label">Latar Utama</span>
                    <span style="font-size: 11.5px; font-weight: 700; color: #0284C7;">#07090E Deep Space</span>
                  </div>
                  <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span class="kala-theme-spec-label">Aksen Kartu</span>
                    <span style="font-size: 11.5px; font-weight: 700; color: #059669;">Aurora Cyan &amp; Mint</span>
                  </div>
                </div>
                <p class="kala-theme-card-desc">Kontras tinggi, nyaman di mata saat bekerja malam hari, dan tampilan berkelas ala NebulaPay.</p>
              </div>

              <!-- Option 2: Clean Light Mode -->
              <div onclick="selectThemeMode('light')" class="kala-theme-option-card" id="theme-card-light">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 14px;">
                  <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="kala-theme-icon-wrap light-icon">
                      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    </div>
                    <div>
                      <div class="kala-theme-card-title">Clean Light Mode</div>
                      <div class="kala-theme-card-sub">Modern SaaS Classic</div>
                    </div>
                  </div>
                  <span id="theme-badge-light" class="kala-theme-status-badge inactive">
                    <span>Klik untuk Aktifkan</span>
                  </span>
                </div>
                <div class="kala-theme-spec-box">
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <span class="kala-theme-spec-label">Latar Utama</span>
                    <span style="font-size: 11.5px; font-weight: 700; color: #2563EB;">#F8F9FA Pure Canvas</span>
                  </div>
                  <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span class="kala-theme-spec-label">Aksen Kartu</span>
                    <span style="font-size: 11.5px; font-weight: 700; color: #059669;">Emerald &amp; Royal Blue</span>
                  </div>
                </div>
                <p class="kala-theme-card-desc">Tampilan jernih standar pencetakan dokumen resmi dan presentasi dalam ruangan terang.</p>
              </div>
            </div>
          </div>

        </div>

  <?php require_once BASE_PATH . '/includes/footer.php'; ?>
</body>
</html>

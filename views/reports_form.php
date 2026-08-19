<?php
/**
 * Kalamedia Full-Funnel Performance Marketing Report - Tabbed Data Entry Form
 * Minimalist Untitled UI Design System with Real-Time Calculations & Creative Uploader
 */

require_auth();
$db = Database::getConnection();

$reportId = intval($_GET['id'] ?? 0);
$report = null;

if ($reportId > 0) {
    $stmt = $db->prepare("
        SELECT r.*, c.name as client_name, c.company as client_company
        FROM performance_reports r
        JOIN clients c ON r.client_id = c.id
        WHERE r.id = ? AND COALESCE(r.is_deleted, 0) = 0
    ");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$report) {
        set_flash('danger', 'Laporan kinerja performa tidak ditemukan atau telah dihapus.');
        header('Location: ' . url('reports'));
        exit;
    }
}

// Fetch all active clients for dropdown
$clients = $db->query("SELECT id, name, company FROM clients ORDER BY company ASC")->fetchAll(PDO::FETCH_ASSOC);

$isEdit = !empty($report);
$pageTitle = $isEdit ? 'Edit Laporan Performance Marketing' : 'Buat Laporan Performance Marketing Baru';
$defaultPeriod = date('F Y'); // e.g. August 2026
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> - Kalamedia</title>
  <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
  <style>
    /* Tabbed Interface Layout */
    .tab-navigation-bar {
      display: flex;
      gap: 8px;
      border-bottom: 2px solid #EAECF0;
      margin-bottom: 24px;
      overflow-x: auto;
      padding-bottom: 2px;
    }
    .tab-nav-btn {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 12px 18px;
      font-size: 13.5px;
      font-weight: 700;
      color: #667085;
      background: transparent;
      border: none;
      border-bottom: 3px solid transparent;
      margin-bottom: -2px;
      cursor: pointer;
      transition: all 0.2s ease;
      white-space: nowrap;
      border-radius: 8px 8px 0 0;
    }
    .tab-nav-btn:hover {
      color: #101828;
      background: #F9FAFB;
    }
    .tab-nav-btn.active {
      color: #101828;
      border-bottom-color: #101828;
      background: #F8FAFC;
    }
    .tab-nav-btn .tab-step-badge {
      width: 22px;
      height: 22px;
      border-radius: 50%;
      background: #EAECF0;
      color: #475467;
      font-size: 11px;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s ease;
    }
    .tab-nav-btn.active .tab-step-badge {
      background: #101828;
      color: #FFFFFF;
    }

    .tab-pane-content {
      display: none;
      animation: fadeIn 0.25s ease-in-out;
    }
    .tab-pane-content.active {
      display: block;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(4px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .form-section-card {
      background: #FFFFFF;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-lg);
      padding: 24px 28px;
      margin-bottom: 20px;
      box-shadow: var(--shadow-xs);
    }
    .form-section-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 20px;
      padding-bottom: 14px;
      border-bottom: 1px solid var(--border-light);
    }
    .section-icon-badge {
      width: 38px;
      height: 38px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #F2F4F7;
      color: #101828;
      font-size: 16px;
      font-weight: 800;
      flex-shrink: 0;
    }
    .section-icon-badge.indigo { background: #EEF2FF; color: #4F46E5; }
    .section-icon-badge.emerald { background: #ECFDF5; color: #059669; }
    .section-icon-badge.amber { background: #FFFBEB; color: #D97706; }
    .section-icon-badge.purple { background: #FAF5FF; color: #7C3AED; }
    .section-icon-badge.rose { background: #FFF1F2; color: #E11D48; }

    .form-section-title {
      font-size: 16px;
      font-weight: 800;
      color: #101828;
      margin: 0;
      letter-spacing: -0.2px;
    }
    .form-section-subtitle {
      font-size: 12.5px;
      color: var(--text-secondary);
      margin-top: 2px;
    }
    .input-hint {
      font-size: 11px;
      color: var(--text-tertiary);
      margin-top: 4px;
      line-height: 1.4;
    }

    /* Live Metric Calculation Cards */
    .live-calc-box {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      background: #F8FAFC;
      border: 1px solid #E2E8F0;
      border-radius: 12px;
      padding: 16px;
      margin-top: 16px;
    }
    .live-calc-item {
      background: #FFFFFF;
      border: 1px solid #EAECF0;
      border-radius: 8px;
      padding: 12px 14px;
    }
    .live-calc-label {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #64748B;
      margin-bottom: 4px;
    }
    .live-calc-value {
      font-size: 18px;
      font-weight: 900;
      color: #0F172A;
    }
    .live-calc-meta {
      font-size: 11px;
      color: #94A3B8;
      margin-top: 2px;
    }

    /* Creative Upload Preview Cards */
    .creative-upload-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-top: 10px;
    }
    .creative-upload-card {
      border: 2px dashed #D0D5DD;
      border-radius: 12px;
      padding: 20px;
      background: #FAFAFA;
      transition: all 0.2s ease;
      position: relative;
    }
    .creative-upload-card:hover {
      border-color: #4F46E5;
      background: #F8FAFC;
    }
    .creative-upload-card.winning {
      border-color: #86EFAC;
      background: #F0FDF4;
    }
    .creative-upload-card.losing {
      border-color: #FECDD3;
      background: #FFF1F2;
    }
    .creative-card-title {
      font-size: 13px;
      font-weight: 800;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .creative-thumb-preview {
      width: 100%;
      height: 180px;
      border-radius: 8px;
      background: #E2E8F0;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      margin-bottom: 12px;
      border: 1px solid #CBD5E1;
    }
    .creative-thumb-preview img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    @media (max-width: 768px) {
      .live-calc-box { grid-template-columns: 1fr; }
      .creative-upload-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body">

        <!-- Top Header & Breadcrumb Navigation -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
          <div>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
              <a href="<?= url('reports') ?>" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: var(--text-secondary); text-decoration: none;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Kembali ke Dashboard Laporan
              </a>
            </div>
            <h2 style="font-size: 20px; font-weight: 800; color: #101828; margin: 0; letter-spacing: -0.3px;">
              <?= htmlspecialchars($pageTitle) ?>
            </h2>
            <p style="font-size: 13px; color: var(--text-secondary); margin: 2px 0 0 0;">
              Input dan kelola metrik full-funnel performance marketing: Business Summary, Paid Ads, Creative Intelligence, dan Action Plan.
            </p>
          </div>

          <?php if ($isEdit): ?>
            <a href="<?= url("report-deck?id={$report['id']}") ?>" target="_blank" class="btn btn-secondary btn-sm" style="gap: 6px;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
              <span>Buka Pitch Deck Presentasi</span>
            </a>
          <?php endif; ?>
        </div>

        <!-- Tabbed Navigation Bar -->
        <div class="tab-navigation-bar">
          <button type="button" class="tab-nav-btn active" onclick="switchTab(1)" id="tab-btn-1">
            <span class="tab-step-badge">1</span>
            <span>1. Business Summary</span>
          </button>
          <button type="button" class="tab-nav-btn" onclick="switchTab(2)" id="tab-btn-2">
            <span class="tab-step-badge">2</span>
            <span>2. Paid Ads Performance</span>
          </button>
          <button type="button" class="tab-nav-btn" onclick="switchTab(3)" id="tab-btn-3">
            <span class="tab-step-badge">3</span>
            <span>3. Content & Retention</span>
          </button>
          <button type="button" class="tab-nav-btn" onclick="switchTab(4)" id="tab-btn-4">
            <span class="tab-step-badge">4</span>
            <span>4. Action Plan & Insights</span>
          </button>
        </div>

        <!-- Form with novalidate to prevent native HTML5 hidden input blocking -->
        <form id="form-report" novalidate action="<?= url('api/reports.php?action=save') ?>" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="id" value="<?= $report ? $report['id'] : '' ?>">
          <input type="hidden" name="existing_winning_content_url" value="<?= htmlspecialchars($report['winning_content_url'] ?? '') ?>">
          <input type="hidden" name="existing_underperforming_content_url" value="<?= htmlspecialchars($report['underperforming_content_url'] ?? '') ?>">

          <!-- =========================================================================
               TAB 1: BUSINESS SUMMARY (Spend, Omset, ROAS, Conversions, CPL/CPA)
               ========================================================================= -->
          <div class="tab-pane-content active" id="tab-pane-1">
            <div class="form-section-card">
              <div class="form-section-header">
                <div class="section-icon-badge indigo">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                </div>
                <div>
                  <h3 class="form-section-title">Business Summary & Full-Funnel Economics</h3>
                  <p class="form-section-subtitle">Tentukan klien, periode laporan, objektif campaign, dan efisiensi belanja iklan (ROAS/ROI).</p>
                </div>
              </div>

              <div class="form-row">
                <div class="form-group" style="flex: 1.2;">
                  <label class="form-label">Klien / Brand Terdaftar *</label>
                  <select name="client_id" id="report-client-id" class="form-select">
                    <option value="">-- Pilih Klien Agensi --</option>
                    <?php foreach ($clients as $c): ?>
                      <option value="<?= $c['id'] ?>" <?= ($report && $report['client_id'] == $c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['company']) ?> (PIC: <?= htmlspecialchars($c['name']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="input-hint">Klien penerima laporan performa agensi Kalamedia.</div>
                </div>

                <div class="form-group" style="flex: 0.8;">
                  <label class="form-label">Periode Laporan *</label>
                  <input type="text" name="report_period" id="report-period" class="form-control" placeholder="Contoh: August 2026" value="<?= htmlspecialchars($report['report_period'] ?? $defaultPeriod) ?>">
                  <div class="input-hint">Bulan atau kuartal kampanye (misal: August 2026, Q3 2026).</div>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Campaign Objective (Objektif Utama Kampanye) *</label>
                <input type="text" name="objective" id="report-objective" class="form-control" placeholder="Contoh: Lead Generation & Unit Closing / E-Commerce ROAS Scale-up" value="<?= htmlspecialchars($report['objective'] ?? '') ?>">
                <div class="input-hint">Tujuan strategis yang disepakati bersama klien untuk periode ini.</div>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Total Ad Spend (Total Belanja Iklan) *</label>
                  <div style="position: relative;">
                    <span style="position: absolute; left: 12px; top: 10px; font-weight: 700; color: var(--text-secondary); font-size: 13px;">Rp</span>
                    <input type="text" name="total_ad_spend" id="input-ad-spend" class="form-control" style="padding-left: 36px; font-weight: 700;" placeholder="0" value="<?= htmlspecialchars($report['total_ad_spend'] ?? '0') ?>" oninput="recalcBusinessSummary()">
                  </div>
                  <div class="input-hint">Total modal iklan (Meta Ads, TikTok Ads, Google Ads).</div>
                </div>

                <div class="form-group">
                  <label class="form-label">Revenue / Gross Omset (Total Pendapatan) *</label>
                  <div style="position: relative;">
                    <span style="position: absolute; left: 12px; top: 10px; font-weight: 700; color: var(--text-secondary); font-size: 13px;">Rp</span>
                    <input type="text" name="revenue" id="input-revenue" class="form-control" style="padding-left: 36px; font-weight: 700; color: #047857;" placeholder="0" value="<?= htmlspecialchars($report['revenue'] ?? '0') ?>" oninput="recalcBusinessSummary()">
                  </div>
                  <div class="input-hint">Nilai penjualan langsung / closing yang dihasilkan.</div>
                </div>

                <div class="form-group">
                  <label class="form-label">Total Conversions (Closing / Leads / Orders) *</label>
                  <input type="text" name="total_conversions" id="input-conversions" class="form-control" placeholder="Contoh: 60" value="<?= htmlspecialchars($report['total_conversions'] ?? '0') ?>" oninput="recalcBusinessSummary()">
                  <div class="input-hint">Jumlah unit closing, lead terverifikasi, atau order e-commerce.</div>
                </div>
              </div>

              <!-- Live Real-Time Calculated KPI Box -->
              <div class="live-calc-box">
                <div class="live-calc-item">
                  <div class="live-calc-label">Calculated ROAS</div>
                  <div class="live-calc-value" id="calc-roas" style="color: #4F46E5;">
                    <?= number_format($report['roas'] ?? 0, 2) ?>x
                  </div>
                  <div class="live-calc-meta">Revenue &divide; Ad Spend</div>
                  <input type="hidden" name="roas" id="hidden-roas" value="<?= htmlspecialchars($report['roas'] ?? '0') ?>">
                </div>

                <div class="live-calc-item">
                  <div class="live-calc-label">Calculated ROI</div>
                  <div class="live-calc-value" id="calc-roi" style="color: #059669;">
                    <?= number_format($report['roi'] ?? 0, 2) ?>%
                  </div>
                  <div class="live-calc-meta">Net Return on Investment</div>
                  <input type="hidden" name="roi" id="hidden-roi" value="<?= htmlspecialchars($report['roi'] ?? '0') ?>">
                </div>

                <div class="live-calc-item">
                  <div class="live-calc-label">CPL / CPA (Cost Per Action)</div>
                  <div class="live-calc-value" id="calc-cpl-cpa" style="color: #101828;">
                    Rp <?= number_format($report['cpl_cpa'] ?? 0, 0, ',', '.') ?>
                  </div>
                  <div class="live-calc-meta">Ad Spend &divide; Conversions</div>
                  <input type="hidden" name="cpl_cpa" id="hidden-cpl-cpa" value="<?= htmlspecialchars($report['cpl_cpa'] ?? '0') ?>">
                </div>
              </div>

              <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
                <button type="button" class="btn btn-primary" onclick="switchTab(2)" style="gap: 6px;">
                  <span>Lanjut ke Tab 2: Paid Ads</span>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </button>
              </div>
            </div>
          </div>

          <!-- =========================================================================
               TAB 2: PAID ADS PERFORMANCE (Reach, Impressions, CTR, CPC, Lost IS)
               ========================================================================= -->
          <div class="tab-pane-content" id="tab-pane-2">
            <div class="form-section-card">
              <div class="form-section-header">
                <div class="section-icon-badge emerald">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
                </div>
                <div>
                  <h3 class="form-section-title">Paid Ads Performance & Algorithmic Efficiency</h3>
                  <p class="form-section-subtitle">Distribusi traffic iklan, efisiensi bidding (CPC/CPM), dan Lost Impression Share.</p>
                </div>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Ads Reach (Akun Unik Terjangkau) *</label>
                  <input type="text" name="ads_reach" id="input-ads-reach" class="form-control" placeholder="Contoh: 345000" value="<?= htmlspecialchars($report['ads_reach'] ?? '0') ?>">
                  <div class="input-hint">Jumlah akun unik yang melihat iklan berbayar.</div>
                </div>

                <div class="form-group">
                  <label class="form-label">Ads Impressions (Total Impresi Iklan) *</label>
                  <input type="text" name="ads_impressions" id="input-ads-impressions" class="form-control" placeholder="Contoh: 890000" value="<?= htmlspecialchars($report['ads_impressions'] ?? '0') ?>">
                  <div class="input-hint">Frekuensi total iklan ditampilkan di feed/stories/reels.</div>
                </div>

                <div class="form-group">
                  <label class="form-label">Ads CTR (%) (Click-Through Rate) *</label>
                  <div style="position: relative;">
                    <input type="text" name="ads_ctr" id="input-ads-ctr" class="form-control" style="padding-right: 32px; font-weight: 700;" placeholder="Contoh: 3.85" value="<?= htmlspecialchars($report['ads_ctr'] ?? '0') ?>">
                    <span style="position: absolute; right: 12px; top: 10px; font-weight: 700; color: var(--text-secondary); font-size: 13px;">%</span>
                  </div>
                  <div class="input-hint">Benchmark industri sehat: &gt; 2.50%.</div>
                </div>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">CPC (Cost Per Click) (Rp)</label>
                  <div style="position: relative;">
                    <span style="position: absolute; left: 12px; top: 10px; font-weight: 700; color: var(--text-secondary); font-size: 13px;">Rp</span>
                    <input type="text" name="ads_cpc" class="form-control" style="padding-left: 36px;" placeholder="Contoh: 1312" value="<?= htmlspecialchars($report['ads_cpc'] ?? '0') ?>">
                  </div>
                  <div class="input-hint">Biaya rata-rata per klik tautan/form.</div>
                </div>

                <div class="form-group">
                  <label class="form-label">CPM (Cost Per Mille / 1.000 Tayangan) (Rp)</label>
                  <div style="position: relative;">
                    <span style="position: absolute; left: 12px; top: 10px; font-weight: 700; color: var(--text-secondary); font-size: 13px;">Rp</span>
                    <input type="text" name="ads_cpm" class="form-control" style="padding-left: 36px;" placeholder="Contoh: 50560" value="<?= htmlspecialchars($report['ads_cpm'] ?? '0') ?>">
                  </div>
                  <div class="input-hint">Biaya penayangan per 1.000 impresi.</div>
                </div>

                <div class="form-group">
                  <label class="form-label">Lost IS (Rank) (%)</label>
                  <div style="position: relative;">
                    <input type="text" name="lost_is_rank" class="form-control" style="padding-right: 32px;" placeholder="Contoh: 6.40" value="<?= htmlspecialchars($report['lost_is_rank'] ?? '0') ?>">
                    <span style="position: absolute; right: 12px; top: 10px; font-weight: 700; color: var(--text-secondary); font-size: 13px;">%</span>
                  </div>
                  <div class="input-hint">Pangsa tayang hilang karena skor relevansi/ad quality.</div>
                </div>

                <div class="form-group">
                  <label class="form-label">Lost IS (Budget) (%)</label>
                  <div style="position: relative;">
                    <input type="text" name="lost_is_budget" class="form-control" style="padding-right: 32px;" placeholder="Contoh: 12.80" value="<?= htmlspecialchars($report['lost_is_budget'] ?? '0') ?>">
                    <span style="position: absolute; right: 12px; top: 10px; font-weight: 700; color: var(--text-secondary); font-size: 13px;">%</span>
                  </div>
                  <div class="input-hint">Pangsa tayang hilang karena batas anggaran harian.</div>
                </div>
              </div>

              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Evaluasi Strategi Paid Ads *</label>
                <textarea name="ads_evaluation" id="input-ads-evaluation" class="form-control" rows="3" placeholder="Tuliskan evaluasi struktur campaign, targeting audience, performa lead form / katalog belanja, serta delivery algoritma..."><?= htmlspecialchars($report['ads_evaluation'] ?? '') ?></textarea>
                <div class="input-hint">Analisis teknis ads yang menjelaskan mengapa metrik tersebut tercapai.</div>
              </div>

              <div style="display: flex; justify-content: space-between; margin-top: 24px;">
                <button type="button" class="btn btn-secondary" onclick="switchTab(1)">
                  &larr; Kembali ke Business Summary
                </button>
                <button type="button" class="btn btn-primary" onclick="switchTab(3)" style="gap: 6px;">
                  <span>Lanjut ke Tab 3: Content & Retention</span>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </button>
              </div>
            </div>
          </div>

          <!-- =========================================================================
               TAB 3: CONTENT & RETENTION (Views, Retention %, ER, Creative Uploads)
               ========================================================================= -->
          <div class="tab-pane-content" id="tab-pane-3">
            <div class="form-section-card">
              <div class="form-section-header">
                <div class="section-icon-badge amber">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                </div>
                <div>
                  <h3 class="form-section-title">Content & Creative Intelligence</h3>
                  <p class="form-section-subtitle">Retensi video, pertumbuhan followers, serta evaluasi komparatif Winning vs Underperforming creatives.</p>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Konsep & Identitas Konten Utama (Content Identity) *</label>
                <input type="text" name="content_identity" id="input-content-identity" class="form-control" placeholder="Contoh: Virtual Room Tour 360° Unit Hook 2-Lantai & Testimoni Serah Terima Unit" value="<?= htmlspecialchars($report['content_identity'] ?? '') ?>">
                <div class="input-hint">Pilar tema konten atau format produksi yang mendominasi campaign.</div>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Total Video Views (Organik + Ads)</label>
                  <input type="text" name="total_views" class="form-control" placeholder="Contoh: 480000" value="<?= htmlspecialchars($report['total_views'] ?? '0') ?>">
                  <div class="input-hint">Total akumulasi penayangan konten video/reels.</div>
                </div>

                <div class="form-group">
                  <label class="form-label">Followers Gained (Net Growth)</label>
                  <input type="text" name="followers_gained" class="form-control" placeholder="Contoh: 2950" value="<?= htmlspecialchars($report['followers_gained'] ?? '0') ?>">
                  <div class="input-hint">Net penambahan followers baru akun klien.</div>
                </div>

                <div class="form-group">
                  <label class="form-label">Avg Video Retention (%) *</label>
                  <div style="position: relative;">
                    <input type="text" name="avg_video_retention" id="input-video-retention" class="form-control" style="padding-right: 32px; font-weight: 700; color: #4F46E5;" placeholder="Contoh: 52.40" value="<?= htmlspecialchars($report['avg_video_retention'] ?? '0') ?>">
                    <span style="position: absolute; right: 12px; top: 10px; font-weight: 700; color: var(--text-secondary); font-size: 13px;">%</span>
                  </div>
                  <div class="input-hint">Persentase rata-rata audiens menonton video sampai selesai.</div>
                </div>

                <div class="form-group">
                  <label class="form-label">Engagement Rate (%) *</label>
                  <div style="position: relative;">
                    <input type="text" name="engagement_rate" id="input-engagement-rate" class="form-control" style="padding-right: 32px; font-weight: 700;" placeholder="Contoh: 5.85" value="<?= htmlspecialchars($report['engagement_rate'] ?? '0') ?>">
                    <span style="position: absolute; right: 12px; top: 10px; font-weight: 700; color: var(--text-secondary); font-size: 13px;">%</span>
                  </div>
                  <div class="input-hint">Rasio interaksi aktif terhadap reach.</div>
                </div>
              </div>

              <!-- Creative Screenshots Showdown Upload Section -->
              <div style="margin-top: 20px;">
                <label class="form-label" style="font-size: 14px; font-weight: 800; color: #101828; margin-bottom: 8px; display: block;">
                  Tangkapan Layar Materi Kreatif (Creative Showdown)
                </label>
                <p style="font-size: 12px; color: var(--text-secondary); margin-top: -4px; margin-bottom: 14px;">
                  Unggah screenshot postingan / iklan juara dan iklan yang kurang perform untuk disandingkan di laporan deck pitch.
                </p>

                <div class="creative-upload-grid">
                  <!-- 1. Winning Creative Card -->
                  <div class="creative-upload-card winning">
                    <div class="creative-card-title" style="color: #047857;">
                      <span>🏆 Winning Creative (Top Performer)</span>
                    </div>

                    <div class="creative-thumb-preview" id="preview-winning-box">
                      <?php if (!empty($report['winning_content_url'])): ?>
                        <img src="<?= htmlspecialchars($report['winning_content_url']) ?>" alt="Winning Creative Preview" id="preview-winning-img">
                      <?php else: ?>
                        <div id="preview-winning-placeholder" style="text-align: center; color: #94A3B8; font-size: 12px;">
                          <div style="font-size: 28px; margin-bottom: 4px;">🎬</div>
                          <span>Belum ada screenshot winning creative</span>
                        </div>
                      <?php endif; ?>
                    </div>

                    <label class="btn btn-secondary btn-sm" style="width: 100%; justify-content: center; cursor: pointer; gap: 6px;">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                      <span>Upload Screenshot Winning (JPG/PNG/SVG)</span>
                      <input type="file" name="winning_content_file" id="file-winning" accept="image/*,.svg" style="display: none;" onchange="previewCreative(this, 'preview-winning-box')">
                    </label>
                    <div class="input-hint">Maksimal 5MB. Menampilkan konsep visual dengan konversi/retensi tertinggi.</div>
                  </div>

                  <!-- 2. Underperforming Creative Card -->
                  <div class="creative-upload-card losing">
                    <div class="creative-card-title" style="color: #B91C1C;">
                      <span>⚠️ Underperforming Creative (Low Retention / High CPL)</span>
                    </div>

                    <div class="creative-thumb-preview" id="preview-underperforming-box">
                      <?php if (!empty($report['underperforming_content_url'])): ?>
                        <img src="<?= htmlspecialchars($report['underperforming_content_url']) ?>" alt="Underperforming Creative Preview" id="preview-underperforming-img">
                      <?php else: ?>
                        <div id="preview-underperforming-placeholder" style="text-align: center; color: #94A3B8; font-size: 12px;">
                          <div style="font-size: 28px; margin-bottom: 4px;">📉</div>
                          <span>Belum ada screenshot underperforming creative</span>
                        </div>
                      <?php endif; ?>
                    </div>

                    <label class="btn btn-secondary btn-sm" style="width: 100%; justify-content: center; cursor: pointer; gap: 6px;">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                      <span>Upload Screenshot Underperforming</span>
                      <input type="file" name="underperforming_content_file" id="file-underperforming" accept="image/*,.svg" style="display: none;" onchange="previewCreative(this, 'preview-underperforming-box')">
                    </label>
                    <div class="input-hint">Maksimal 5MB. Materi yang dihentikan untuk dijadikan bahan evaluasi perbaikan.</div>
                  </div>
                </div>
              </div>

              <div style="display: flex; justify-content: space-between; margin-top: 24px;">
                <button type="button" class="btn btn-secondary" onclick="switchTab(2)">
                  &larr; Kembali ke Paid Ads
                </button>
                <button type="button" class="btn btn-primary" onclick="switchTab(4)" style="gap: 6px;">
                  <span>Lanjut ke Tab 4: Action Plan</span>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </button>
              </div>
            </div>
          </div>

          <!-- =========================================================================
               TAB 4: INSIGHTS & ACTION PLAN (What Worked, What Didn't, Next Action Plan)
               ========================================================================= -->
          <div class="tab-pane-content" id="tab-pane-4">
            <div class="form-section-card">
              <div class="form-section-header">
                <div class="section-icon-badge purple">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                </div>
                <div>
                  <h3 class="form-section-title">Strategic Insights & Executive Action Plan</h3>
                  <p class="form-section-subtitle">Sintesis temuan lapangan dan rencana scaling budget/konten untuk periode berikutnya.</p>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" style="color: #047857;">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                  What Worked (Faktor Keberhasilan & Winning Angle) *
                </label>
                <textarea name="what_worked" id="input-what-worked" class="form-control" rows="4" placeholder="• Hook 3 detik pertama dengan angle 'Beli Rumah Cicilan 3 Jutaan'...&#10;• Format video Reels POV walk-through show unit menghasilkan 68% total leads...&#10;• Custom Audience Lookalike 1% menghasilkan CPL paling efisien (Rp 28.500)..."><?= htmlspecialchars($report['what_worked'] ?? '') ?></textarea>
                <div class="input-hint">Gunakan poin-poin bullet (•) untuk mempermudah pembacaan oleh tim direksi klien.</div>
              </div>

              <div class="form-group">
                <label class="form-label" style="color: #B91C1C;">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                  What Didn't Work (Hambatan & Titik Inefisiensi) *
                </label>
                <textarea name="what_didnt_work" id="input-what-didnt-work" class="form-control" rows="4" placeholder="• Single Image banner statis memiliki bounce rate tinggi (CTR < 1.1%) dan CPL membengkak...&#10;• Penargetan audiens broad interest terlalu banyak mendatangkan leads luar Jabodetabek..."><?= htmlspecialchars($report['what_didnt_work'] ?? '') ?></textarea>
                <div class="input-hint">Jelaskan materi atau strategi yang underperform dan alasan mengapa tidak dilanjutkan.</div>
              </div>

              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="color: #4F46E5;">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                  Next Action Plan & Roadmap Scaling (Rencana Aksi Bulan Berikutnya) *
                </label>
                <textarea name="next_action_plan" id="input-next-action-plan" class="form-control" rows="4" placeholder="1. Scale budget 30% pada ad set winning creative 'Video Tour Hook 2 Lantai'.&#10;2. Produksi 4 variasi video UGC testimoni serah terima kunci cluster terbaru.&#10;3. Implementasi WhatsApp Business Automation CRM untuk fast response < 5 menit."><?= htmlspecialchars($report['next_action_plan'] ?? '') ?></textarea>
                <div class="input-hint">Langkah konkret nomor 1, 2, 3 yang akan dieksekusi agensi pada periode mendatang.</div>
              </div>

              <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 28px; padding-top: 16px; border-top: 1px solid var(--border-light);">
                <button type="button" class="btn btn-secondary" onclick="switchTab(3)">
                  &larr; Kembali ke Content & Retention
                </button>
                <div style="display: flex; gap: 10px;">
                  <a href="<?= url('reports') ?>" class="btn btn-secondary">Batal</a>
                  <button type="button" id="btn-submit-report" onclick="submitReportForm()" class="btn btn-primary" style="padding: 10px 24px; gap: 8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    <span><?= $isEdit ? 'Simpan Perubahan Laporan' : 'Terbitkan Laporan Performance Deck' ?></span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </form>

      </div>
    </main>
  </div>

  <script src="assets/js/app.js"></script>
  <script>
    // Tab switching logic
    function switchTab(tabIndex) {
      document.querySelectorAll('.tab-pane-content').forEach(p => p.classList.remove('active'));
      document.querySelectorAll('.tab-nav-btn').forEach(b => b.classList.remove('active'));

      const targetPane = document.getElementById(`tab-pane-${tabIndex}`);
      const targetBtn = document.getElementById(`tab-btn-${tabIndex}`);

      if (targetPane) targetPane.classList.add('active');
      if (targetBtn) targetBtn.classList.add('active');
      window.scrollTo({ top: 120, behavior: 'smooth' });
    }

    // Number parser helper in JS
    function parseJsNumber(val) {
      if (!val) return 0;
      let str = String(val).trim().replace(/[^\d.,]/g, '');
      if (!str) return 0;
      // If dot thousand separator like 45.000.000
      if ((str.match(/\./g) || []).length > 1) {
        str = str.replace(/\./g, '');
      } else if ((str.match(/,/g) || []).length > 1) {
        str = str.replace(/,/g, '');
      } else if (str.includes(',') && !str.includes('.')) {
        str = str.replace(',', '.');
      }
      return parseFloat(str) || 0;
    }

    // Live calculation for ROAS, ROI, and CPL/CPA
    function recalcBusinessSummary() {
      const spendInput = document.getElementById('input-ad-spend').value;
      const revenueInput = document.getElementById('input-revenue').value;
      const convInput = document.getElementById('input-conversions').value;

      const spend = parseJsNumber(spendInput);
      const revenue = parseJsNumber(revenueInput);
      const conversions = parseInt(String(convInput).replace(/[^\d]/g, '')) || 0;

      // 1. ROAS
      const roas = spend > 0 ? (revenue / spend) : 0;
      document.getElementById('calc-roas').innerText = roas.toFixed(2) + 'x';
      document.getElementById('hidden-roas').value = roas.toFixed(2);

      // 2. ROI
      const roi = spend > 0 ? (((revenue - spend) / spend) * 100) : 0;
      document.getElementById('calc-roi').innerText = (roi > 0 ? '+' : '') + roi.toLocaleString('id-ID', { maximumFractionDigits: 1 }) + '%';
      document.getElementById('hidden-roi').value = roi.toFixed(2);

      // 3. CPL / CPA
      const cplCpa = conversions > 0 ? (spend / conversions) : 0;
      document.getElementById('calc-cpl-cpa').innerText = 'Rp ' + Math.round(cplCpa).toLocaleString('id-ID');
      document.getElementById('hidden-cpl-cpa').value = cplCpa.toFixed(2);
    }

    // Instant Image Preview on File Selection
    function previewCreative(input, containerId) {
      if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
          const container = document.getElementById(containerId);
          container.innerHTML = `<img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover; border-radius:8px;" alt="Creative Preview">`;
        };
        reader.readAsDataURL(file);
      }
    }

    // Display toast helper
    function notify(message, type = 'info') {
      if (typeof showToast === 'function') {
        showToast(message, type);
      } else {
        alert(message);
      }
    }

    // Cross-tab validation & Submission
    async function submitReportForm() {
      const form = document.getElementById('form-report');
      const submitBtn = document.getElementById('btn-submit-report');
      const originalText = submitBtn ? submitBtn.innerHTML : 'Terbitkan Laporan Performance Deck';

      // 1. Validate Tab 1 (Business Summary)
      const clientId = document.getElementById('report-client-id').value;
      if (!clientId) {
        switchTab(1);
        document.getElementById('report-client-id').focus();
        notify('Silakan pilih Klien / Brand pada Tab 1 terlebih dahulu.', 'warning');
        return;
      }

      const reportPeriod = document.getElementById('report-period').value.trim();
      if (!reportPeriod) {
        switchTab(1);
        document.getElementById('report-period').focus();
        notify('Silakan isi Periode Laporan pada Tab 1 (misal: August 2026).', 'warning');
        return;
      }

      const objective = document.getElementById('report-objective').value.trim();
      if (!objective) {
        switchTab(1);
        document.getElementById('report-objective').focus();
        notify('Silakan isi Campaign Objective pada Tab 1 (misal: Lead Generation / ROAS Scale-up).', 'warning');
        return;
      }

      // Sync calculations
      recalcBusinessSummary();

      // 2. Validate Tab 2 (Paid Ads)
      const adsEvaluation = document.getElementById('input-ads-evaluation').value.trim();
      if (!adsEvaluation) {
        switchTab(2);
        document.getElementById('input-ads-evaluation').focus();
        notify('Silakan lengkapi Evaluasi Strategi Paid Ads pada Tab 2.', 'warning');
        return;
      }

      // 3. Validate Tab 3 (Content & Retention)
      const contentIdentity = document.getElementById('input-content-identity').value.trim();
      if (!contentIdentity) {
        switchTab(3);
        document.getElementById('input-content-identity').focus();
        notify('Silakan isi Konsep & Identitas Konten Utama pada Tab 3.', 'warning');
        return;
      }

      // 4. Validate Tab 4 (Action Plan)
      const whatWorked = document.getElementById('input-what-worked').value.trim();
      if (!whatWorked) {
        switchTab(4);
        document.getElementById('input-what-worked').focus();
        notify('Kolom "What Worked" wajib diisi pada Tab 4.', 'warning');
        return;
      }

      const whatDidntWork = document.getElementById('input-what-didnt-work').value.trim();
      if (!whatDidntWork) {
        switchTab(4);
        document.getElementById('input-what-didnt-work').focus();
        notify('Kolom "What Didn\'t Work" wajib diisi pada Tab 4.', 'warning');
        return;
      }

      const nextActionPlan = document.getElementById('input-next-action-plan').value.trim();
      if (!nextActionPlan) {
        switchTab(4);
        document.getElementById('input-next-action-plan').focus();
        notify('Kolom "Next Action Plan" wajib diisi pada Tab 4.', 'warning');
        return;
      }

      // All validations passed -> Prepare submission
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = `
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation: spin 1s linear infinite;"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg>
          <span>Memproses &amp; Menerbitkan Deck...</span>
        `;
      }

      const formData = new FormData(form);
      const targetUrl = form.getAttribute('action') || 'api/reports.php?action=save';

      try {
        const res = await fetch(targetUrl, {
          method: 'POST',
          body: formData
        });
        const data = await res.json();

        if (data && data.success) {
          try { localStorage.removeItem('kalamedia_perf_report_draft'); } catch(e) {}
          notify(data.message || 'Laporan Performance Marketing berhasil diterbitkan!', 'success');
          setTimeout(() => {
            window.location.href = data.redirect || 'reports';
          }, 600);
        } else {
          notify((data && data.message) ? data.message : 'Gagal menyimpan laporan.', 'danger');
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
          }
        }
      } catch (err) {
        console.error('Submit report error:', err);
        notify('Koneksi server gagal. Silakan periksa koneksi Anda dan coba lagi.', 'danger');
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;
        }
      }
    }

    // Auto-save draft to localStorage on input
    function autoSaveDraft() {
      if (<?= $isEdit ? 'true' : 'false' ?>) return;
      const form = document.getElementById('form-report');
      if (!form) return;
      const data = {};
      new FormData(form).forEach((val, key) => {
        if (typeof val === 'string' && key !== 'id' && !key.includes('file')) {
          data[key] = val;
        }
      });
      try {
        localStorage.setItem('kalamedia_perf_report_draft', JSON.stringify(data));
      } catch (e) {}
    }

    // Auto-restore draft from localStorage
    function restoreDraft() {
      if (<?= $isEdit ? 'true' : 'false' ?>) return;
      try {
        const draftStr = localStorage.getItem('kalamedia_perf_report_draft');
        if (!draftStr) return;
        const data = JSON.parse(draftStr);
        for (const [key, val] of Object.entries(data)) {
          const el = document.querySelector(`[name="${key}"]`);
          if (el && !el.value && val) {
            el.value = val;
          }
        }
      } catch (e) {}
    }

    // Run initial calculations on page load
    document.addEventListener('DOMContentLoaded', () => {
      restoreDraft();
      recalcBusinessSummary();

      // Listen for input changes to auto-save draft
      const form = document.getElementById('form-report');
      if (form) {
        form.addEventListener('input', autoSaveDraft);
      }
    });
  </script>
</body>
</html>

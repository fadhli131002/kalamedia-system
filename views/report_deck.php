<?php
/**
 * Kalamedia Full-Funnel Performance Marketing Report - Executive Presentation & Printable Pitch Deck View
 * High-End Pitch Deck Presentation with Strict 2-Page A4 Engine:
 * Page 1: Points 1, 2, 3 (Cover, Economics, Paid Ads & Creative Showdown)
 * Page 2: Point 4 (Strategic Synthesis Matrix & Digital Signatures Sign-Off)
 */

require_auth();
$db = Database::getConnection();

$reportId = intval($_GET['id'] ?? 0);
if ($reportId <= 0) {
    header('Location: ' . url('reports'));
    exit;
}

$stmt = $db->prepare("
    SELECT r.*, 
           c.name as client_name, c.company as client_company, 
           c.email as client_email, c.phone as client_phone,
           c.address as client_address, c.logo as client_logo
    FROM performance_reports r
    JOIN clients c ON r.client_id = c.id
    WHERE r.id = ? AND COALESCE(r.is_deleted, 0) = 0
");
$stmt->execute([$reportId]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    die("Laporan kinerja performa klien tidak ditemukan atau telah dihapus.");
}

$clientPic = !empty($report['client_name']) ? $report['client_name'] : $report['client_company'];
$clientCompany = $report['client_company'] ?: $report['client_name'];

// Derived calculation ratios
$frequency = ($report['ads_reach'] > 0) 
    ? round($report['ads_impressions'] / $report['ads_reach'], 2) 
    : 1;

$capturedIS = max(0, 100 - floatval($report['lost_is_budget']) - floatval($report['lost_is_rank']));
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Performance Deck - <?= htmlspecialchars($clientCompany) ?> (<?= htmlspecialchars($report['report_period']) ?>) - Kalamedia</title>
  <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
  <script src="assets/js/html2pdf.bundle.min.js"></script>

  <style>
    /* ==========================================================================
       PITCH DECK PRESENTATION & PRINT STYLES
       ========================================================================== */
    .deck-screen-wrapper {
      background: #0F172A;
      min-height: 100vh;
      padding-bottom: 50px;
      color: #0F172A;
    }

    /* Floating Navigation Action Bar */
    .deck-actions-bar {
      position: sticky;
      top: 0;
      z-index: 100;
      background: #1E293B;
      border-bottom: 1px solid #334155;
      padding: 10px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    .deck-actions-left {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .deck-actions-right {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* Pitch Deck Canvas Container */
    .deck-canvas-container {
      max-width: 920px;
      margin: 20px auto 0;
      padding: 0 12px;
    }

    .deck-paper {
      background: #FFFFFF;
      border-radius: 12px;
      padding: 32px 36px;
      box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.35);
      margin-bottom: 24px;
    }

    /* Page Break Utility */
    .html2pdf__page-break {
      page-break-before: always !important;
      break-before: page !important;
      display: block;
      height: 0;
      margin: 0;
      padding: 0;
      border: none;
    }
    .screen-page-divider {
      border-top: 2px dashed #CBD5E1;
      margin: 32px 0 24px 0;
      position: relative;
      text-align: center;
    }
    .screen-page-divider::after {
      content: 'HALAMAN 2 (STRATEGIC SYNTHESIS & SIGN-OFF)';
      position: absolute;
      top: -9px;
      left: 50%;
      transform: translateX(-50%);
      background: #E2E8F0;
      color: #64748B;
      font-size: 9.5px;
      font-weight: 800;
      padding: 2px 10px;
      border-radius: 10px;
      letter-spacing: 0.5px;
    }

    /* Page 2 Mini Running Header */
    .deck-page-2-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-bottom: 10px;
      border-bottom: 1.5px solid #0F172A;
      margin-bottom: 16px;
    }
    .deck-p2-brand {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .deck-p2-logo {
      height: 22px;
      width: auto;
      object-fit: contain;
    }
    .deck-p2-title {
      font-size: 10.5px;
      font-weight: 900;
      color: #0F172A;
      letter-spacing: 0.6px;
      text-transform: uppercase;
    }
    .deck-p2-meta {
      font-size: 10.5px;
      font-weight: 700;
      color: #64748B;
    }

    /* 1. Executive Deck Header */
    .deck-header-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-bottom: 12px;
      border-bottom: 2px solid #0F172A;
      margin-bottom: 12px;
      gap: 16px;
      flex-wrap: wrap;
    }
    .deck-agency-info {
      display: flex;
      flex-direction: column;
      gap: 1px;
    }
    .deck-agency-logo {
      height: 32px;
      width: auto;
      object-fit: contain;
      margin-bottom: 2px;
      align-self: flex-start;
    }
    .deck-tagline {
      font-size: 10px;
      font-weight: 800;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      color: #0F172A;
    }
    .deck-subtext {
      font-size: 10.5px;
      color: #64748B;
      line-height: 1.3;
    }

    .deck-meta-col {
      text-align: right;
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 2px;
    }
    .deck-badge-pill {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      background: #0F172A;
      color: #F8FAFC;
      font-size: 9px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      padding: 3px 8px;
      border-radius: 14px;
    }
    .deck-title-main {
      font-size: 17px;
      font-weight: 900;
      color: #0F172A;
      letter-spacing: -0.3px;
      margin-top: 1px;
    }
    .deck-period-pill {
      display: inline-block;
      font-size: 11px;
      font-weight: 800;
      color: #4F46E5;
      background: #EEF2FF;
      padding: 2px 7px;
      border-radius: 5px;
    }

    /* 2. Client Hero Bar & Objective */
    .deck-client-hero {
      background: #F8FAFC;
      border: 1px solid #E2E8F0;
      border-radius: 8px;
      padding: 10px 14px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 14px;
      flex-wrap: wrap;
      gap: 10px;
    }
    .deck-client-left {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .deck-client-avatar {
      width: 36px;
      height: 36px;
      border-radius: 8px;
      background: #0F172A;
      color: #FFFFFF;
      font-size: 14px;
      font-weight: 900;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .deck-client-name {
      font-size: 14.5px;
      font-weight: 900;
      color: #0F172A;
      margin: 0;
      letter-spacing: -0.2px;
    }
    .deck-client-sub {
      font-size: 11px;
      color: #64748B;
      margin: 1px 0 0 0;
    }
    .deck-client-right {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }
    .deck-objective-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      background: #F0FDF4;
      border: 1px solid #BBF7D0;
      color: #15803D;
      font-size: 11px;
      font-weight: 800;
      padding: 4px 8px;
      border-radius: 6px;
    }
    .deck-date-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      color: #64748B;
      font-size: 10.5px;
      font-weight: 700;
      padding: 4px 7px;
      border-radius: 6px;
    }

    /* Section Subheadings */
    .deck-section-title {
      font-size: 12px;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #0F172A;
      margin: 14px 0 8px 0;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .deck-section-title::before {
      content: '';
      display: inline-block;
      width: 3.5px;
      height: 13px;
      background: #4F46E5;
      border-radius: 2px;
    }

    /* 3. Top Line Economics Summary Grid */
    .deck-kpi-grid-4 {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 10px;
      margin-bottom: 14px;
    }
    .deck-kpi-card {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 8px;
      padding: 10px 12px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
    }
    .deck-kpi-card.highlight-spend {
      border-left: 3.5px solid #64748B;
      background: #F8FAFC;
    }
    .deck-kpi-card.highlight-rev {
      border-left: 3.5px solid #10B981;
      background: #F0FDF4;
    }
    .deck-kpi-card.highlight-roas {
      background: #0F172A;
      color: #FFFFFF;
      border-color: #0F172A;
    }
    .deck-kpi-card.highlight-conv {
      border-left: 3.5px solid #4F46E5;
      background: #EEF2FF;
    }
    .kpi-top-label {
      font-size: 9px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #64748B;
      margin-bottom: 2px;
    }
    .highlight-roas .kpi-top-label {
      color: #94A3B8;
    }
    .kpi-main-val {
      font-size: 17.5px;
      font-weight: 900;
      color: #0F172A;
      letter-spacing: -0.4px;
      line-height: 1.15;
    }
    .highlight-rev .kpi-main-val {
      color: #047857;
    }
    .highlight-roas .kpi-main-val {
      color: #38BDF8;
      font-size: 20px;
    }
    .highlight-conv .kpi-main-val {
      color: #4338CA;
    }
    .kpi-sub-label {
      font-size: 10px;
      color: #64748B;
      margin-top: 2px;
    }
    .highlight-roas .kpi-sub-label {
      color: #CBD5E1;
    }

    /* 4. Paid Ads Performance Grid */
    .paid-ads-deck-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-bottom: 14px;
    }
    .paid-metrics-box {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 8px;
      padding: 11px 13px;
    }
    .metrics-row-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 4px 0;
      border-bottom: 1px solid #F1F5F9;
      font-size: 11px;
    }
    .metrics-row-item:last-child {
      border-bottom: none;
      padding-bottom: 0;
    }
    .mri-label {
      color: #475467;
      font-weight: 600;
    }
    .mri-val {
      font-weight: 800;
      color: #0F172A;
    }

    /* Impression Share Bar */
    .is-progress-bar {
      display: flex;
      height: 8px;
      border-radius: 4px;
      overflow: hidden;
      margin: 6px 0 5px 0;
      background: #E2E8F0;
    }
    .is-bar-captured { background: #10B981; }
    .is-bar-rank { background: #F59E0B; }
    .is-bar-budget { background: #EF4444; }

    .is-legend {
      display: flex;
      gap: 8px;
      font-size: 9px;
      color: #64748B;
      flex-wrap: wrap;
    }
    .is-legend-item {
      display: flex;
      align-items: center;
      gap: 3px;
    }
    .is-dot {
      width: 5px;
      height: 5px;
      border-radius: 50%;
    }

    /* 5. Creative Showdown Teardown Grid */
    .creative-concept-bar {
      background: #F8FAFC;
      border: 1px solid #E2E8F0;
      border-radius: 7px;
      padding: 8px 12px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
      flex-wrap: wrap;
      gap: 6px;
    }
    .creative-concept-title {
      font-size: 11.5px;
      font-weight: 800;
      color: #0F172A;
      display: flex;
      align-items: center;
      gap: 4px;
    }
    .creative-stats-pills {
      display: flex;
      gap: 6px;
      font-size: 10.5px;
      flex-wrap: wrap;
    }
    .creative-stat-pill {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      padding: 2px 6px;
      border-radius: 4px;
      color: #334155;
    }

    .creative-showdown-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-bottom: 4px;
    }
    .showdown-card {
      border-radius: 8px;
      padding: 11px 12px;
      display: flex;
      flex-direction: column;
      background: #FFFFFF;
    }
    .showdown-card.winning {
      background: #F0FDF4;
      border: 1px solid #BBF7D0;
    }
    .showdown-card.losing {
      background: #FFF1F2;
      border: 1px solid #FECDD3;
    }
    .showdown-card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
      gap: 6px;
      flex-wrap: wrap;
    }
    .showdown-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 10px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      padding: 2.5px 7px;
      border-radius: 4px;
      white-space: nowrap;
    }
    .winning .showdown-badge {
      background: #15803D;
      color: #FFFFFF;
    }
    .losing .showdown-badge {
      background: #B91C1C;
      color: #FFFFFF;
    }
    .showdown-subbadge {
      font-size: 10px;
      font-weight: 800;
      white-space: nowrap;
    }
    .winning .showdown-subbadge {
      color: #15803D;
    }
    .losing .showdown-subbadge {
      color: #B91C1C;
    }

    .showdown-media-frame {
      width: 100%;
      height: 155px;
      border-radius: 6px;
      background: #F8FAFC;
      border: 1px solid #E2E8F0;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 8px;
      padding: 3px;
    }
    .showdown-media-frame img {
      max-width: 100%;
      max-height: 100%;
      width: auto;
      height: auto;
      object-fit: contain;
      border-radius: 4px;
    }

    .showdown-text-body {
      font-size: 11px;
      color: #334155;
      line-height: 1.4;
    }

    /* 6. Strategic Insights Matrix (3 Columns) */
    .strategy-matrix-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      margin-bottom: 20px;
    }
    .matrix-card {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 8px;
      padding: 14px 14px;
      display: flex;
      flex-direction: column;
    }
    .matrix-card.worked {
      border-top: 3.5px solid #10B981;
    }
    .matrix-card.didnt-work {
      border-top: 3.5px solid #EF4444;
    }
    .matrix-card.action-plan {
      border-top: 3.5px solid #4F46E5;
      background: #F8FAFC;
    }
    .matrix-title {
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .worked .matrix-title { color: #047857; }
    .didnt-work .matrix-title { color: #B91C1C; }
    .action-plan .matrix-title { color: #4338CA; }

    .matrix-content {
      font-size: 11px;
      color: #334155;
      line-height: 1.55;
      white-space: pre-line;
    }

    /* 7. Sign-off Row */
    .deck-signoff-row {
      margin-top: 20px;
      padding-top: 16px;
      border-top: 1px solid #E2E8F0;
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      gap: 16px;
    }
    .deck-confidential-note {
      font-size: 9.5px;
      color: #94A3B8;
      max-width: 440px;
      line-height: 1.35;
    }

    /* Prevent clipping inside cards */
    .deck-header-row,
    .deck-client-hero,
    .deck-kpi-grid-4,
    .paid-ads-deck-grid,
    .paid-metrics-box,
    .creative-concept-bar,
    .creative-showdown-grid,
    .showdown-card,
    .strategy-matrix-grid,
    .matrix-card,
    .deck-signoff-row {
      page-break-inside: avoid !important;
      break-inside: avoid !important;
    }

    /* ==========================================================================
       PRINT MEDIA QUERY (STRICT 2-PAGE SPLIT & ZERO CLIPPING)
       ========================================================================== */
    @media print {
      @page {
        size: A4 portrait;
        margin: 6mm 8mm 6mm 8mm;
      }
      *, *:before, *:after {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
      }
      body, html {
        background: #FFFFFF !important;
        margin: 0 !important;
        padding: 0 !important;
      }
      .sidebar,
      .topbar,
      .deck-actions-bar,
      .screen-page-divider,
      #sidebar-toggle,
      .btn,
      .modal-backdrop {
        display: none !important;
      }
      .app-container,
      .main-wrapper,
      .deck-screen-wrapper,
      .deck-canvas-container {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #FFFFFF !important;
        border: none !important;
        box-shadow: none !important;
      }
      .deck-paper {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        border-radius: 0 !important;
        margin-bottom: 0 !important;
      }
      .html2pdf__page-break {
        page-break-before: always !important;
        break-before: page !important;
        display: block !important;
        height: 0 !important;
      }
      .deck-kpi-card.highlight-roas {
        background: #0F172A !important;
        color: #FFFFFF !important;
      }
      .deck-kpi-card.highlight-roas .kpi-main-val {
        color: #38BDF8 !important;
      }
      .deck-kpi-card.highlight-roas .kpi-top-label {
        color: #94A3B8 !important;
      }
    }
  </style>
</head>
<body class="deck-screen-wrapper">

  <!-- Sticky Presentation Action Bar -->
  <div class="deck-actions-bar">
    <div class="deck-actions-left">
      <a href="<?= url('reports') ?>" class="btn btn-secondary btn-sm" style="background: #334155; color: #FFFFFF; border-color: #475467; gap: 6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        <span>Daftar Laporan</span>
      </a>
      <span style="font-size: 13px; font-weight: 800; color: #F8FAFC;">
        Deck #<?= $report['id'] ?> &bull; <?= htmlspecialchars($clientCompany) ?>
      </span>
      <span class="badge" style="background: #312E81; color: #C7D2FE; font-size: 11px; font-weight: 700;">
        <?= htmlspecialchars($report['report_period']) ?>
      </span>
    </div>

    <div class="deck-actions-right">
      <a href="<?= url("reports-form?id={$report['id']}") ?>" class="btn btn-secondary btn-sm" style="background: #334155; color: #FFFFFF; border-color: #475467; gap: 6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
        <span>Edit Data Laporan</span>
      </a>

      <button type="button" class="btn btn-primary btn-sm" onclick="exportDeckToPdf()" style="gap: 6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        <span>Save as PDF / Download</span>
      </button>

      <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()" style="background: #334155; color: #FFFFFF; border-color: #475467; gap: 6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        <span>Cetak Deck</span>
      </button>
    </div>
  </div>

  <!-- Printable Presentation Canvas -->
  <div class="deck-canvas-container">
    <div class="deck-paper" id="printable-deck-canvas">

      <!-- =====================================================================
           PAGE 1: COVER, ECONOMICS, PAID ADS & CREATIVE SHOWDOWN (POINTS 1, 2, 3)
           ===================================================================== -->
      <div>
        <!-- 1. Executive Deck Header -->
        <div class="deck-header-row">
          <div class="deck-agency-info">
            <img src="assets/Jpg/Asset 3.png" alt="Kala Media Creative Agency" class="deck-agency-logo">
            <div class="deck-tagline">KALA MEDIA CREATIVE &bull; BUILT TO BE SEEN.</div>
            <div class="deck-subtext">
              Full-Funnel Performance Marketing &bull; Digital Growth Advisory &bull; Tangerang, Banten 15339
            </div>
          </div>

          <div class="deck-meta-col">
            <div class="deck-badge-pill">CLIENT PERFORMANCE MARKETING DECK</div>
            <div class="deck-title-main">QBR PERFORMANCE REPORT</div>
            <div class="deck-period-pill">Periode: <?= htmlspecialchars($report['report_period']) ?></div>
          </div>
        </div>

        <!-- 2. Client Identity & Objective Bar -->
        <div class="deck-client-hero">
          <div class="deck-client-left">
            <div class="deck-client-avatar">
              <?= strtoupper(substr($clientCompany, 0, 2)) ?>
            </div>
            <div>
              <h2 class="deck-client-name"><?= htmlspecialchars($clientCompany) ?></h2>
              <p class="deck-client-sub">
                Attn: <strong><?= htmlspecialchars($clientPic) ?></strong> &bull; <?= htmlspecialchars($report['client_email'] ?: 'Verified Partner') ?>
              </p>
            </div>
          </div>

          <div class="deck-client-right">
            <div class="deck-objective-badge">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polygon points="12 8 8 12 12 16 12 8"></polygon></svg>
              <span><?= htmlspecialchars($report['objective'] ?: 'Full-Funnel Growth') ?></span>
            </div>
            <div class="deck-date-badge">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
              <span><?= date('d M Y', strtotime($report['created_at'])) ?></span>
            </div>
          </div>
        </div>

        <!-- 3. Section 1: Business Summary & Economics -->
        <div class="deck-section-title">
          1. Executive Economics & Business Summary
        </div>

        <div class="deck-kpi-grid-4">
          <!-- 1. Total Ad Spend -->
          <div class="deck-kpi-card highlight-spend">
            <div class="kpi-top-label">Total Ad Spend</div>
            <div class="kpi-main-val">Rp <?= number_format($report['total_ad_spend'], 0, ',', '.') ?></div>
            <div class="kpi-sub-label">Alokasi Paid Media</div>
          </div>

          <!-- 2. Revenue / Omset -->
          <div class="deck-kpi-card highlight-rev">
            <div class="kpi-top-label">Gross Revenue</div>
            <div class="kpi-main-val">Rp <?= number_format($report['revenue'], 0, ',', '.') ?></div>
            <div class="kpi-sub-label" style="color: #047857; font-weight: 700;">
              +<?= number_format($report['roi'], 1) ?>% ROI
            </div>
          </div>

          <!-- 3. ROAS (Highlight Card) -->
          <div class="deck-kpi-card highlight-roas">
            <div class="kpi-top-label">Return on Ad Spend</div>
            <div class="kpi-main-val"><?= number_format($report['roas'], 2) ?>x</div>
            <div class="kpi-sub-label">Blended ROAS Index</div>
          </div>

          <!-- 4. Conversions & CPA -->
          <div class="deck-kpi-card highlight-conv">
            <div class="kpi-top-label">Conversions & CPL</div>
            <div class="kpi-main-val"><?= number_format($report['total_conversions']) ?> <span style="font-size: 11.5px; font-weight: 700;">Conv</span></div>
            <div class="kpi-sub-label" style="font-weight: 700; color: #4338CA;">
              Rp <?= number_format($report['cpl_cpa'], 0, ',', '.') ?> / unit
            </div>
          </div>
        </div>

        <!-- 4. Section 2: Paid Ads Traffic & Share of Voice -->
        <div class="deck-section-title">
          2. Paid Ads Performance & Algorithmic Share
        </div>

        <div class="paid-ads-deck-grid">
          <!-- Left: Metrics Breakdown -->
          <div class="paid-metrics-box">
            <div class="metrics-row-item">
              <span class="mri-label">Ads Reach (Akun Unik)</span>
              <span class="mri-val"><?= number_format($report['ads_reach']) ?> akun</span>
            </div>
            <div class="metrics-row-item">
              <span class="mri-label">Total Impressions (Tayangan)</span>
              <span class="mri-val"><?= number_format($report['ads_impressions']) ?> (Frekuensi <?= $frequency ?>x)</span>
            </div>
            <div class="metrics-row-item">
              <span class="mri-label">Click-Through Rate (CTR)</span>
              <span class="mri-val" style="color: #047857;"><?= number_format($report['ads_ctr'], 2) ?>% High Intent</span>
            </div>
            <div class="metrics-row-item">
              <span class="mri-label">Cost Per Click (CPC)</span>
              <span class="mri-val">Rp <?= number_format($report['ads_cpc'], 0, ',', '.') ?></span>
            </div>
            <div class="metrics-row-item">
              <span class="mri-label">Cost Per Mille (CPM)</span>
              <span class="mri-val">Rp <?= number_format($report['ads_cpm'], 0, ',', '.') ?></span>
            </div>
          </div>

          <!-- Right: Lost Impression Share & Ads Evaluation -->
          <div class="paid-metrics-box">
            <div style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748B; margin-bottom: 2px;">
              Impression Share (Pangsa Tayang Iklan)
            </div>

            <div class="is-progress-bar">
              <div class="is-bar-captured" style="width: <?= $capturedIS ?>%;" title="Captured Share: <?= $capturedIS ?>%"></div>
              <div class="is-bar-rank" style="width: <?= floatval($report['lost_is_rank']) ?>%;" title="Lost IS Rank: <?= $report['lost_is_rank'] ?>%"></div>
              <div class="is-bar-budget" style="width: <?= floatval($report['lost_is_budget']) ?>%;" title="Lost IS Budget: <?= $report['lost_is_budget'] ?>%"></div>
            </div>

            <div class="is-legend">
              <div class="is-legend-item"><span class="is-dot" style="background: #10B981;"></span> Captured (<?= number_format($capturedIS, 1) ?>%)</div>
              <div class="is-legend-item"><span class="is-dot" style="background: #F59E0B;"></span> Lost IS Rank (<?= number_format($report['lost_is_rank'], 1) ?>%)</div>
              <div class="is-legend-item"><span class="is-dot" style="background: #EF4444;"></span> Lost IS Budget (<?= number_format($report['lost_is_budget'], 1) ?>%)</div>
            </div>

            <div style="margin-top: 8px; padding-top: 6px; border-top: 1px solid #F1F5F9; font-size: 10.5px; color: #334155; line-height: 1.4;">
              <strong>Algorithmic Evaluation:</strong><br>
              <?= nl2br(htmlspecialchars($report['ads_evaluation'])) ?>
            </div>
          </div>
        </div>

        <!-- 5. Section 3: Content Intelligence & Creative Showdown -->
        <div class="deck-section-title">
          3. Content & Creative Intelligence (Showdown Teardown)
        </div>

        <!-- Top Creative Health Bar -->
        <div class="creative-concept-bar">
          <div class="creative-concept-title">
            <span>💡</span>
            <span><strong>Creative Concept:</strong> <?= htmlspecialchars($report['content_identity'] ?: 'Performance Video Ads') ?></span>
          </div>
          <div class="creative-stats-pills">
            <span class="creative-stat-pill"><strong><?= number_format($report['total_views']) ?></strong> Views</span>
            <span class="creative-stat-pill"><strong>+<?= number_format($report['followers_gained']) ?></strong> Followers</span>
            <span class="creative-stat-pill" style="color: #4F46E5; border-color: #C7D2FE; background: #EEF2FF;">
              <strong><?= number_format($report['avg_video_retention'], 1) ?>%</strong> Avg Retention
            </span>
            <span class="creative-stat-pill" style="color: #047857; border-color: #BBF7D0; background: #F0FDF4;">
              <strong><?= number_format($report['engagement_rate'], 2) ?>%</strong> ER
            </span>
          </div>
        </div>

        <!-- Creative Showdown Side-by-Side Cards -->
        <div class="creative-showdown-grid">
          <!-- 🏆 Winning Creative -->
          <div class="showdown-card winning">
            <div class="showdown-card-header">
              <span class="showdown-badge">🏆 Winning Creative</span>
              <span class="showdown-subbadge">High Hook Rate</span>
            </div>

            <div class="showdown-media-frame">
              <?php if (!empty($report['winning_content_url'])): ?>
                <img src="<?= htmlspecialchars($report['winning_content_url']) ?>" alt="Winning Creative Screenshot">
              <?php else: ?>
                <div style="color: #94A3B8; font-size: 11px; text-align: center;">
                  <div style="font-size: 20px; margin-bottom: 2px;">🎬</div>
                  <span>Materi Winning Creative</span>
                </div>
              <?php endif; ?>
            </div>

            <div class="showdown-text-body">
              <strong>Key Driver:</strong> Hook 3 detik pertama dengan visual POV nyata memicu minat tinggi dan retensi video <?= number_format($report['avg_video_retention'], 1) ?>%.
            </div>
          </div>

          <!-- ⚠️ Underperforming Creative -->
          <div class="showdown-card losing">
            <div class="showdown-card-header">
              <span class="showdown-badge">⚠️ Underperforming</span>
              <span class="showdown-subbadge">Low Retention / High CPA</span>
            </div>

            <div class="showdown-media-frame">
              <?php if (!empty($report['underperforming_content_url'])): ?>
                <img src="<?= htmlspecialchars($report['underperforming_content_url']) ?>" alt="Underperforming Creative Screenshot">
              <?php else: ?>
                <div style="color: #94A3B8; font-size: 11px; text-align: center;">
                  <div style="font-size: 20px; margin-bottom: 2px;">📉</div>
                  <span>Materi Underperforming</span>
                </div>
              <?php endif; ?>
            </div>

            <div class="showdown-text-body">
              <strong>Diagnosis:</strong> Format statis / kurang elemen hook storytelling menghasilkan bounce rate tinggi dan dihentikan demi efisiensi budget.
            </div>
          </div>
        </div>
      </div>

      <!-- =====================================================================
           PAGE BREAK: SECTION 4 & SIGN-OFF MOVED CLEANLY TO PAGE 2
           ===================================================================== -->
      <div class="html2pdf__page-break"></div>
      <div class="screen-page-divider"></div>

      <!-- =====================================================================
           PAGE 2: STRATEGIC SYNTHESIS MATRIX & DIGITAL SIGNATURES
           ===================================================================== -->
      <div>
        <!-- Page 2 Mini Running Header -->
        <div class="deck-page-2-header">
          <div class="deck-p2-brand">
            <img src="assets/Jpg/Asset 3.png" alt="Kala Media" class="deck-p2-logo">
            <span class="deck-p2-title">KALA MEDIA &bull; EXECUTIVE PERFORMANCE REPORT</span>
          </div>
          <div class="deck-p2-meta">
            <?= htmlspecialchars($clientCompany) ?> &bull; <?= htmlspecialchars($report['report_period']) ?>
          </div>
        </div>

        <!-- 6. Section 4: Strategic Synthesis & Action Plan -->
        <div class="deck-section-title" style="margin-top: 4px;">
          4. Strategic Synthesis & Action Plan
        </div>

        <div class="strategy-matrix-grid">
          <!-- What Worked -->
          <div class="matrix-card worked">
            <div class="matrix-title">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
              What Worked (Faktor Kunci)
            </div>
            <div class="matrix-content">
              <?= nl2br(htmlspecialchars($report['what_worked'])) ?>
            </div>
          </div>

          <!-- What Didn't Work -->
          <div class="matrix-card didnt-work">
            <div class="matrix-title">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
              What Didn't Work (Evaluasi)
            </div>
            <div class="matrix-content">
              <?= nl2br(htmlspecialchars($report['what_didnt_work'])) ?>
            </div>
          </div>

          <!-- Next Action Plan -->
          <div class="matrix-card action-plan">
            <div class="matrix-title">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
              Next Action Plan (Roadmap)
            </div>
            <div class="matrix-content">
              <?= nl2br(htmlspecialchars($report['next_action_plan'])) ?>
            </div>
          </div>
        </div>

        <!-- 7. Deck Footer Sign-Off & Authenticity -->
        <div class="deck-signoff-row">
          <div class="deck-confidential-note">
            Dokumen laporan kinerja performa ini disusun secara objektif oleh Kalamedia Creative Agency untuk internal manajemen klien. Seluruh data metrik telah melalui rekonsiliasi analytics.
          </div>

          <div style="display: flex; gap: 32px; align-items: flex-end;">
            <div style="text-align: center; display: flex; flex-direction: column; align-items: center; min-width: 130px;">
              <div style="font-size: 10.5px; color: #64748B; margin-bottom: 2px;">Prepared by:</div>
              <div style="height: 48px; display: flex; align-items: center; justify-content: center; margin-bottom: 2px;">
                <img src="assets/Jpg/ttd-fadhli.png" alt="TTD Muhammad Fadhli" style="height: 42px; max-width: 120px; object-fit: contain;">
              </div>
              <div style="width: 130px; border-bottom: 1px solid #0F172A; margin-bottom: 3px;"></div>
              <div style="font-size: 11.5px; font-weight: 800; color: #0F172A;">Muhammad Fadhli</div>
              <div style="font-size: 10px; color: #64748B;">Creative Manager</div>
            </div>

            <div style="text-align: center; display: flex; flex-direction: column; align-items: center; min-width: 130px;">
              <div style="font-size: 10.5px; color: #64748B; margin-bottom: 2px;">Approved by:</div>
              <div style="height: 48px; display: flex; align-items: center; justify-content: center; margin-bottom: 2px;">
                <img src="assets/Jpg/ttd-ilham.png" alt="TTD Ilham Lanang" style="height: 42px; max-width: 120px; object-fit: contain;">
              </div>
              <div style="width: 130px; border-bottom: 1px solid #0F172A; margin-bottom: 3px;"></div>
              <div style="font-size: 11.5px; font-weight: 800; color: #0F172A;">Ilham Lanang</div>
              <div style="font-size: 10px; color: #64748B;">Marketing Manager</div>
            </div>
          </div>
        </div>

      </div>

    </div>
  </div>

  <script src="assets/js/app.js"></script>
  <script>
    function exportDeckToPdf() {
      const element = document.getElementById('printable-deck-canvas');
      const opt = {
        margin: [6, 8, 6, 8],
        filename: 'Performance_Deck_<?= preg_replace('/[^a-zA-Z0-9_-]/', '_', $clientCompany) ?>_<?= preg_replace('/[^a-zA-Z0-9_-]/', '_', $report['report_period']) ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, logging: false, scrollY: 0 },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak: { mode: ['css', 'legacy'], before: '.html2pdf__page-break' }
      };

      if (typeof html2pdf !== 'undefined') {
        showToast('Memproses ekspor Pitch Deck PDF (2 Halaman A4)...', 'info');
        html2pdf().set(opt).from(element).save().then(() => {
          showToast('Pitch Deck PDF berhasil diunduh!', 'success');
        });
      } else {
        window.print();
      }
    }
  </script>
</body>
</html>

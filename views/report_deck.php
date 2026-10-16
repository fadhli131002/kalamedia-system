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

// Client Role Security Check: Strict Data Isolation
if (is_client()) {
    $clientUserId = current_user_client_id();
    if ($clientUserId && (int)$report['client_id'] !== $clientUserId) {
        die("Akses ditolak: Anda tidak memiliki akses ke laporan kinerja performa ini.");
    }
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
  <title>Performance Deck - <?= htmlspecialchars($clientCompany) ?> (<?= htmlspecialchars($report['report_period']) ?>) - PT Kala Kausa Prima</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=bankio-typography-3.0">
  <script src="assets/js/html2pdf.bundle.min.js"></script>

  <style>
    /* ==========================================================================
       PITCH DECK PRESENTATION & PRINT STYLES - BANKIO MINIMAL FINANCE
       ========================================================================== */
    .deck-screen-wrapper {
      background: #F5F5F7;
      min-height: 100vh;
      padding-bottom: 50px;
      color: #000000;
      font-family: var(--font-sans);
    }

    /* Floating Navigation Action Bar */
    .deck-actions-bar {
      position: sticky;
      top: 0;
      z-index: 100;
      background: #FFFFFF;
      border-bottom: 1px solid #ECEEF5;
      padding: 12px 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 4px 14px rgba(18, 24, 48, 0.05);
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

    @media (max-width: 768px) {
      .deck-screen-wrapper {
        padding-bottom: 24px;
      }
      .deck-actions-bar {
        flex-direction: column;
        align-items: stretch !important;
        padding: 10px 12px;
        gap: 10px;
      }
      .deck-actions-left, .deck-actions-right {
        width: 100%;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 6px;
      }
      .deck-canvas-container {
        padding: 0 6px;
      }
      .deck-paper {
        padding: 18px 14px !important;
        border-radius: 8px;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box;
        overflow-x: auto;
      }
      .deck-kpi-grid-4 {
        grid-template-columns: 1fr 1fr !important;
        gap: 8px !important;
      }
      .paid-ads-deck-grid {
        grid-template-columns: 1fr !important;
        gap: 8px !important;
      }
      .creative-concept-bar {
        flex-direction: column;
        align-items: flex-start !important;
      }
      .deck-header-row {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 10px;
      }
      .deck-meta-col {
        text-align: left !important;
        align-items: flex-start !important;
      }
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
      height: 32px;
      width: 32px;
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
      gap: 3px;
    }
    .deck-agency-logo {
      height: 54px;
      width: 54px;
      object-fit: contain;
      margin-bottom: 4px;
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
      background: #5D76F5;
      color: #FFFFFF;
      font-size: 9px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      padding: 3px 8px;
      border-radius: 999px;
    }
    .deck-title-main {
      font-size: 17px;
      font-weight: 900;
      color: #000000;
      letter-spacing: -0.3px;
      margin-top: 1px;
    }
    .deck-period-pill {
      display: inline-block;
      font-size: 11px;
      font-weight: 800;
      color: #5D76F5;
      background: rgba(93, 118, 245, 0.12);
      padding: 2px 7px;
      border-radius: 999px;
    }

    /* 2. Client Hero Bar & Objective */
    .deck-client-hero {
      background: #F8F9FC;
      border: 1px solid #ECEEF5;
      border-radius: 14px;
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
      border-radius: 10px;
      background: linear-gradient(135deg, #5D76F5 0%, #8932E0 100%);
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
      color: #000000;
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
      background: linear-gradient(135deg, #5D76F5 0%, #8932E0 100%);
      color: #FFFFFF;
      border-color: #5D76F5;
    }
    .deck-kpi-card.highlight-conv {
      border-left: 3.5px solid #5D76F5;
      background: #EEF2FF;
    }
    .kpi-top-label {
      font-size: 9px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #6E7485;
      margin-bottom: 2px;
    }
    .highlight-roas .kpi-top-label {
      color: rgba(255, 255, 255, 0.85);
    }
    .kpi-main-val {
      font-size: 17.5px;
      font-weight: 900;
      color: #000000;
      letter-spacing: -0.4px;
      line-height: 1.15;
    }
    .highlight-rev .kpi-main-val {
      color: #047857;
    }
    .highlight-roas .kpi-main-val {
      color: #FFFFFF;
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
        background: linear-gradient(135deg, #5D76F5 0%, #8932E0 100%) !important;
        color: #FFFFFF !important;
      }
      .deck-kpi-card.highlight-roas .kpi-main-val {
        color: #FFFFFF !important;
      }
      .deck-kpi-card.highlight-roas .kpi-top-label {
        color: rgba(255, 255, 255, 0.85) !important;
      }
    }
  </style>
</head>
<body class="deck-screen-wrapper">

  <!-- Sticky Presentation Action Bar -->
  <div class="deck-actions-bar">
    <div class="deck-actions-left">
      <a href="<?= url('reports') ?>" class="btn btn-secondary btn-sm" style="background: #334155; color: #FFFFFF; border-color: #475467;">
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
      <a href="<?= url("reports-form?id={$report['id']}") ?>" class="btn btn-secondary btn-sm" style="background: #334155; color: #FFFFFF; border-color: #475467;">
        <span>Edit Data Laporan</span>
      </a>

      <button type="button" class="btn btn-primary btn-sm" onclick="exportDeckToPdf()">
        <span>Save as PDF / Download</span>
      </button>

      <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()" style="background: #334155; color: #FFFFFF; border-color: #475467;">
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
            <img src="assets/img/Logo-1.webp" alt="PT Kala Kausa Prima" class="deck-agency-logo">
            <div class="deck-tagline">PT KALA KAUSA PRIMA &bull; BUILT FOR WHAT’S NEXT</div>
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
              <span><?= htmlspecialchars($report['objective'] ?: 'Full-Funnel Growth') ?></span>
            </div>
            <div class="deck-date-badge">
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
          <!-- Winning Creative -->
          <div class="showdown-card winning">
            <div class="showdown-card-header">
              <span class="showdown-badge">Winning Creative</span>
              <span class="showdown-subbadge">High Hook Rate</span>
            </div>

            <div class="showdown-media-frame">
              <?php if (!empty($report['winning_content_url'])): ?>
                <img src="<?= htmlspecialchars($report['winning_content_url']) ?>" alt="Winning Creative Screenshot">
              <?php else: ?>
                <div style="color: #94A3B8; font-size: 11px; text-align: center;">
                  <span>Materi Winning Creative</span>
                </div>
              <?php endif; ?>
            </div>

            <div class="showdown-text-body">
              <strong>Key Driver:</strong> Hook 3 detik pertama dengan visual POV nyata memicu minat tinggi dan retensi video <?= number_format($report['avg_video_retention'], 1) ?>%.
            </div>
          </div>

          <!-- Underperforming Creative -->
          <div class="showdown-card losing">
            <div class="showdown-card-header">
              <span class="showdown-badge">Underperforming</span>
              <span class="showdown-subbadge">Low Retention / High CPA</span>
            </div>

            <div class="showdown-media-frame">
              <?php if (!empty($report['underperforming_content_url'])): ?>
                <img src="<?= htmlspecialchars($report['underperforming_content_url']) ?>" alt="Underperforming Creative Screenshot">
              <?php else: ?>
                <div style="color: #94A3B8; font-size: 11px; text-align: center;">
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
            <img src="assets/img/Logo-1.webp" alt="PT Kala Kausa Prima" class="deck-p2-logo">
            <span class="deck-p2-title">PT KALA KAUSA PRIMA &bull; EXECUTIVE PERFORMANCE REPORT</span>
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
              What Worked (Faktor Kunci)
            </div>
            <div class="matrix-content">
              <?= nl2br(htmlspecialchars($report['what_worked'])) ?>
            </div>
          </div>

          <!-- What Didn't Work -->
          <div class="matrix-card didnt-work">
            <div class="matrix-title">
              What Didn't Work (Evaluasi)
            </div>
            <div class="matrix-content">
              <?= nl2br(htmlspecialchars($report['what_didnt_work'])) ?>
            </div>
          </div>

          <!-- Next Action Plan -->
          <div class="matrix-card action-plan">
            <div class="matrix-title">
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
            Dokumen laporan kinerja performa ini disusun secara objektif oleh PT Kala Kausa Prima untuk internal manajemen klien. Seluruh data metrik telah melalui rekonsiliasi analytics.
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

  <script src="assets/js/app.js?v=bankio-typography-3.0"></script>
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

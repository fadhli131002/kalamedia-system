<?php
/**
 * Shared Styles & Executive Renderer for "Kala AI Brand Diagnostic & Strategy Blueprint"
 * Used by Admin/Owner Workspace (views/clients.php) and Client Portal Dashboard (views/client_portal.php)
 * 100% Crisp SVG Icons (Zero Emojis) & Full Light / NebulaPay Dark Mode Support
 */
?>
<style>
  /* ==========================================================================
     KALA AI BRAND DIAGNOSTIC & STRATEGY BLUEPRINT — LIGHT & NEBULAPAY DARK CSS
     ========================================================================== */
  .kala-ai-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    color: #0F172A;
  }
  .kala-ai-muted {
    color: #64748B;
  }
  .kala-ai-pill-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(79, 70, 229, 0.1);
    border: 1px solid rgba(79, 70, 229, 0.25);
    color: #4F46E5;
    font-size: 11px;
    font-weight: 800;
    padding: 4px 10px;
    border-radius: 999px;
    letter-spacing: 0.3px;
  }

  /* Blueprint Executive Output Cards */
  .kala-bp-hero {
    background: linear-gradient(135deg, #EEF2FF 0%, #F5F3FF 55%, #ECFDF5 100%);
    border-radius: 18px;
    padding: 24px;
    color: #0F172A;
    margin-bottom: 20px;
    box-shadow: 0 8px 24px -8px rgba(79, 70, 229, 0.12);
    border: 1.5px solid #C7D2FE;
  }
  .kala-bp-hero-grid {
    display: grid;
    grid-template-columns: 155px 1fr 1.15fr;
    gap: 20px;
    align-items: center;
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 18px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
  }
  .kala-bp-hero-scheme-box {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 14px 16px;
  }
  .kala-bp-ring-track {
    stroke: #E2E8F0;
  }
  .kala-bp-bar-track {
    height: 7px;
    background: #E2E8F0;
    border-radius: 999px;
    overflow: hidden;
  }
  .kala-bp-hero-tag-model {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(79, 70, 229, 0.1);
    border: 1px solid rgba(79, 70, 229, 0.25);
    color: #4F46E5;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.3px;
  }
  .kala-bp-hero-tag-stage {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #ECFDF3;
    border: 1px solid #6CE9A6;
    color: #027A48;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
  }
  .kala-bp-grid-2 {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 16px;
  }
  .kala-bp-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 16px;
  }
  .kala-bp-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 20px 22px;
    margin-bottom: 18px;
    box-shadow: 0 4px 16px -4px rgba(15, 23, 42, 0.04);
  }
  .kala-bp-card-title {
    font-size: 14.5px;
    font-weight: 800;
    color: #0F172A;
    margin: 0 0 14px 0;
    padding-bottom: 12px;
    border-bottom: 1px solid #F1F5F9;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
  }
  .kala-bp-title-left {
    display: inline-flex;
    align-items: center;
    gap: 10px;
  }
  .kala-bp-sec-icon {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: rgba(79, 70, 229, 0.1);
    color: #4F46E5;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .kala-bp-subbox {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 14px 16px;
    margin-bottom: 12px;
  }
  .kala-bp-subbox:last-child {
    margin-bottom: 0;
  }
  .kala-bp-subbox-title {
    font-size: 11.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #4338CA;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
  }
  .kala-bp-subbox-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .kala-bp-text {
    font-size: 13px;
    color: #1E293B;
    line-height: 1.6;
    margin: 0;
    white-space: pre-line;
  }
  .kala-bp-list {
    margin: 0;
    padding-left: 18px;
    font-size: 13px;
    color: #1E293B;
    line-height: 1.6;
  }
  .kala-bp-list li {
    margin-bottom: 6px;
  }
  .kala-bp-copy-btn {
    background: #EEF2FF;
    border: 1px solid #C7D2FE;
    color: #4338CA;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 7px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.15s ease;
  }
  .kala-bp-copy-btn:hover {
    background: #4338CA;
    color: #FFFFFF;
  }

  /* ==========================================================================
     NEBULAPAY DARK MODE OVERRIDES
     ========================================================================== */
  html[data-theme="dark"] .kala-ai-card,
  body.dark-mode .kala-ai-card,
  html[data-theme="dark"] .kala-bp-hero,
  body.dark-mode .kala-bp-hero {
    background: linear-gradient(135deg, #1E1B4B 0%, #312E81 55%, #0F172A 100%) !important;
    border-color: rgba(99, 102, 241, 0.35) !important;
    color: #F8FAFC !important;
  }
  html[data-theme="dark"] .kala-bp-hero-grid,
  body.dark-mode .kala-bp-hero-grid {
    background: rgba(15, 23, 42, 0.55) !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
    color: #F8FAFC !important;
  }
  html[data-theme="dark"] .kala-bp-hero-scheme-box,
  body.dark-mode .kala-bp-hero-scheme-box {
    background: rgba(255, 255, 255, 0.06) !important;
    border-color: rgba(255, 255, 255, 0.14) !important;
    color: #F8FAFC !important;
  }
  html[data-theme="dark"] .kala-bp-ring-track,
  body.dark-mode .kala-bp-ring-track {
    stroke: rgba(255, 255, 255, 0.15) !important;
  }
  html[data-theme="dark"] .kala-bp-bar-track,
  body.dark-mode .kala-bp-bar-track {
    background: rgba(255, 255, 255, 0.15) !important;
  }
  html[data-theme="dark"] .kala-bp-hero-tag-model,
  body.dark-mode .kala-bp-hero-tag-model {
    background: rgba(255, 255, 255, 0.14) !important;
    border-color: rgba(255, 255, 255, 0.25) !important;
    color: #E0E7FF !important;
  }
  html[data-theme="dark"] .kala-bp-hero-tag-stage,
  body.dark-mode .kala-bp-hero-tag-stage {
    background: rgba(52, 211, 153, 0.2) !important;
    border-color: rgba(52, 211, 153, 0.45) !important;
    color: #6EE7B7 !important;
  }
  html[data-theme="dark"] .kala-bp-card,
  body.dark-mode .kala-bp-card {
    background: #121826 !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
    color: #F8FAFC !important;
  }
  html[data-theme="dark"] .kala-ai-muted,
  body.dark-mode .kala-ai-muted {
    color: #94A3B8 !important;
  }
  html[data-theme="dark"] .kala-ai-pill-badge,
  body.dark-mode .kala-ai-pill-badge {
    background: rgba(99, 102, 241, 0.18) !important;
    border-color: rgba(99, 102, 241, 0.35) !important;
    color: #A5B4FC !important;
  }
  html[data-theme="dark"] .kala-bp-card-title,
  body.dark-mode .kala-bp-card-title {
    color: #F8FAFC !important;
    border-bottom-color: rgba(255, 255, 255, 0.07) !important;
  }
  html[data-theme="dark"] .kala-bp-sec-icon,
  body.dark-mode .kala-bp-sec-icon {
    background: rgba(56, 189, 248, 0.14) !important;
    color: #38BDF8 !important;
  }
  html[data-theme="dark"] .kala-bp-subbox,
  body.dark-mode .kala-bp-subbox {
    background: #0B0F19 !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
  }
  html[data-theme="dark"] .kala-bp-subbox-title,
  body.dark-mode .kala-bp-subbox-title {
    color: #38BDF8 !important;
  }
  html[data-theme="dark"] .kala-bp-text,
  body.dark-mode .kala-bp-text,
  html[data-theme="dark"] .kala-bp-list,
  body.dark-mode .kala-bp-list {
    color: #E2E8F0 !important;
  }
  html[data-theme="dark"] .kala-bp-copy-btn,
  body.dark-mode .kala-bp-copy-btn {
    background: rgba(56, 189, 248, 0.14) !important;
    border-color: rgba(56, 189, 248, 0.35) !important;
    color: #38BDF8 !important;
  }
  html[data-theme="dark"] .kala-bp-copy-btn:hover,
  body.dark-mode .kala-bp-copy-btn:hover {
    background: #38BDF8 !important;
    color: #0B0F19 !important;
  }

  @media (max-width: 820px) {
    .kala-bp-hero-grid,
    .kala-bp-grid-2,
    .kala-bp-grid-3 {
      grid-template-columns: 1fr !important;
    }
  }
</style>

<script>
  window.KALA_BP_ICONS = {
    sparkles: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.912 5.813a2 2 0 001.275 1.275L21 12l-5.813 1.912a2 2 0 00-1.275 1.275L12 21l-1.912-5.813a2 2 0 00-1.275-1.275L3 12l5.813-1.912a2 2 0 001.275-1.275L12 3z"/></svg>',
    layers: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>',
    target: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>',
    activity: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
    checkCircle: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
    alertTriangle: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    layout: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>',
    copy: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
    link: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
    bookmark: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>',
    video: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>',
    calendar: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
    zap: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
    messageSquare: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    shield: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
    trendingUp: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
    users: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    compass: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>'
  };

  window.escapeKalaHtml = function(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  };

  window.copyKalaAiText = function(btn, encodedText) {
    const text = decodeURIComponent(encodedText || '');
    if (!text) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = window.KALA_BP_ICONS.checkCircle + '<span>Tersalin</span>';
        if (typeof showToast === 'function') showToast('Teks berhasil disalin ke clipboard!', 'success');
        setTimeout(() => { btn.innerHTML = orig; }, 1800);
      });
    } else {
      const ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      if (typeof showToast === 'function') showToast('Teks berhasil disalin!', 'success');
    }
  };

  /**
   * Renders the full Executive Strategy Blueprint cards HTML (100% SVG Icons, Zero Emojis)
   */
  window.renderKalaAiBlueprintCards = function(analysis, client, auditMeta) {
    if (!analysis || typeof analysis !== 'object') {
      return '<div style="text-align:center; padding:40px; color:var(--text-muted);">Data analisis belum tersedia.</div>';
    }

    const esc = window.escapeKalaHtml;
    const IC = window.KALA_BP_ICONS;
    const companyName = (client && client.company) ? client.company : 'Brand Klien';
    const modelUsed = (auditMeta && auditMeta.model_used) ? auditMeta.model_used : (analysis._meta && analysis._meta.model_used ? analysis._meta.model_used : 'gemini-2.5-pro');
    const updatedAt = (auditMeta && auditMeta.updated_at) ? auditMeta.updated_at : (analysis._meta && analysis._meta.generated_at ? analysis._meta.generated_at : '');

    const rawStage = analysis.brand_stage;
    const brandStage = (typeof rawStage === 'object' && rawStage !== null) ? (rawStage.stage_name || 'Stage 1: Foundation') : (rawStage || 'Stage 1: Foundation & Storefront Optimization');
    const stageReason = (typeof rawStage === 'object' && rawStage !== null) ? (rawStage.explanation || '') : (analysis.brand_stage_reason || '');

    const hs = (typeof analysis.health_score === 'object' && analysis.health_score !== null) ? analysis.health_score : {};
    const overallScore = parseInt(hs.overall_score ?? hs.overall ?? analysis.health_score ?? (auditMeta ? auditMeta.health_score : 70)) || 0;
    const storefrontScore = parseInt(hs.storefront_score ?? overallScore) || 0;
    const contentScore = parseInt(hs.content_score ?? overallScore) || 0;
    const engagementScore = parseInt(hs.engagement_closing_score ?? overallScore) || 0;
    const prodAdsScore = parseInt(hs.production_ads_score ?? overallScore) || 0;
    const scoreVerdict = hs.score_verdict || 'Evaluasi menyeluruh dari profil, konten, interaksi, dan kesiapan konversi.';

    const scoreColor = overallScore >= 75 ? '#34D399' : (overallScore >= 55 ? '#FBBF24' : '#F87171');
    const dashOffset = Math.round(283 - (283 * Math.min(100, Math.max(0, overallScore))) / 100);

    // 3. Executive Diagnosis
    const diag = analysis.executive_diagnosis || {};
    const diagOverview = diag.summary || diag.overview || '';
    const strengths = Array.isArray(diag.strengths) ? diag.strengths : (diag.strengths ? [diag.strengths] : []);
    const bottlenecks = Array.isArray(diag.core_bottlenecks) ? diag.core_bottlenecks : (diag.core_bottlenecks ? [diag.core_bottlenecks] : []);

    // 4. Recommended Scheme
    const scheme = (typeof analysis.recommended_scheme === 'object' && analysis.recommended_scheme !== null) ? analysis.recommended_scheme : { scheme_name: analysis.recommended_scheme };
    const schemeName = scheme.scheme_name || (auditMeta && auditMeta.recommended_scheme_name) || 'Kala 90-Day Growth & Conversion Blueprint';
    const schemePhilosophy = scheme.philosophy || '';
    const northStar = scheme.north_star_metric || scheme.primary_kpi_target || '';

    // 5. Storefront Revamp
    const sf = analysis.storefront_revamp || {};
    const bioOpt1 = sf.bio_option_1 || sf.new_bio_draft || (Array.isArray(sf.new_bio_lines) ? sf.new_bio_lines.join('\n') : '');
    const bioOpt2 = sf.bio_option_2 || '';
    const linkFunnel = sf.link_in_bio_funnel || sf.link_and_funnel || '';
    const pinnedPosts = Array.isArray(sf.pinned_posts) ? sf.pinned_posts : [];
    const highlights = Array.isArray(sf.story_highlights) ? sf.story_highlights : [];

    // 6. Content Strategy
    const cs = analysis.content_strategy || {};
    const pillars = Array.isArray(cs.content_pillars) ? cs.content_pillars : [];
    const weekly = cs.weekly_schedule || {};
    const hp = cs.hook_and_persona || {};
    const hookPersonaText = cs.hook_formula_and_persona || hp.persona_and_tone || (typeof hp === 'string' ? hp : '');
    const hookFormulas = Array.isArray(hp.hook_formulas) ? hp.hook_formulas : (Array.isArray(cs.sample_hooks) ? cs.sample_hooks : []);

    // 7. Closing & Ads Blueprint
    const ca = analysis.closing_and_ads_blueprint || {};
    const dmSop = ca.dm_sop_script || ca.dm_anti_ghosting_sop || {};
    const adsKol = ca.ads_and_kol_strategy || ca.meta_ads_and_kol_strategy || {};

    // 8. 90-Day Roadmap
    const rm = analysis.roadmap_90_days || {};
    const m1 = rm.month_1 || {};
    const m2 = rm.month_2 || {};
    const m3 = rm.month_3 || {};

    const renderSubScoreBar = (label, val, color) => `
      <div style="margin-bottom: 10px;">
        <div style="display: flex; justify-content: space-between; font-size: 11.5px; font-weight: 700; margin-bottom: 4px;">
          <span>${esc(label)}</span>
          <span>${val}/100</span>
        </div>
        <div class="kala-bp-bar-track">
          <div style="height: 100%; width: ${Math.min(100, Math.max(0, val))}%; background: ${color}; border-radius: 999px;"></div>
        </div>
      </div>
    `;

    return `
      <!-- 1. EXECUTIVE HERO: SCORE RING, BRAND STAGE & RECOMMENDED SCHEME -->
      <div class="kala-bp-hero">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 18px;">
          <div>
            <div style="display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 10px;">
              <span class="kala-bp-hero-tag-model">
                ${IC.sparkles}
                <span>POWERED BY GEMINI PRO (${esc(modelUsed)})</span>
              </span>
              <span class="kala-bp-hero-tag-stage">
                ${IC.layers}
                <span>${esc(brandStage)}</span>
              </span>
            </div>
            <h2 style="font-size: 20px; font-weight: 800; margin: 0 0 6px 0; letter-spacing: -0.3px;">
              ${esc(companyName)} — Strategy Blueprint
            </h2>
            <p style="font-size: 13px; opacity: 0.9; margin: 0; max-width: 680px; line-height: 1.55;">
              ${esc(stageReason || scoreVerdict)}
            </p>
          </div>
          ${updatedAt ? `<div style="font-size: 11px; opacity: 0.75; font-weight: 600;">Diperbarui: ${esc(updatedAt)}</div>` : ''}
        </div>

        <div class="kala-bp-hero-grid">
          <!-- SVG Health Score Ring -->
          <div style="text-align: center;">
            <div style="position: relative; width: 110px; height: 110px; margin: 0 auto;">
              <svg width="110" height="110" viewBox="0 0 100 100" style="transform: rotate(-90deg);">
                <circle class="kala-bp-ring-track" cx="50" cy="50" r="45" fill="none" stroke-width="8"></circle>
                <circle cx="50" cy="50" r="45" fill="none" stroke="${scoreColor}" stroke-width="8" stroke-dasharray="283" stroke-dashoffset="${dashOffset}" stroke-linecap="round"></circle>
              </svg>
              <div style="position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                <span style="font-size: 25px; font-weight: 800; line-height: 1;">${overallScore}</span>
                <span style="font-size: 10px; font-weight: 700; opacity: 0.75; margin-top: 2px;">/ 100 SKOR</span>
              </div>
            </div>
            <div style="font-size: 11px; font-weight: 800; margin-top: 8px; text-transform: uppercase; letter-spacing: 0.4px;">Brand Health Score</div>
          </div>

          <!-- 4 Sub-Score Bars -->
          <div>
            ${renderSubScoreBar('1. Storefront, Bio & Funnel', storefrontScore, '#0284C7')}
            ${renderSubScoreBar('2. Performa & Strategi Konten', contentScore, '#6366F1')}
            ${renderSubScoreBar('3. Interaksi & Konversi DM', engagementScore, '#10B981')}
            ${renderSubScoreBar('4. Kapasitas Produksi & Ads', prodAdsScore, '#F59E0B')}
          </div>

          <!-- Recommended 90-Day Strategy Scheme Highlight -->
          <div class="kala-bp-hero-scheme-box">
            <div style="display: inline-flex; align-items: center; gap: 6px; font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #4F46E5;">
              ${IC.target}
              <span>REKOMENDASI SKEMA STRATEGI UTAMA</span>
            </div>
            <div style="font-size: 15.5px; font-weight: 800; margin: 5px 0 6px 0; line-height: 1.3;">${esc(schemeName)}</div>
            <div style="font-size: 12px; opacity: 0.88; line-height: 1.5;">${esc(schemePhilosophy)}</div>
            ${northStar ? `<div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(148,163,184,0.25); font-size: 11.5px; font-weight: 700;">North Star KPI: ${esc(northStar)}</div>` : ''}
          </div>
        </div>
      </div>

      <!-- 2. EXECUTIVE DIAGNOSIS: STRENGTHS VS CORE BOTTLENECKS -->
      <div class="kala-bp-card">
        <h3 class="kala-bp-card-title">
          <span class="kala-bp-title-left">
            <span class="kala-bp-sec-icon">${IC.activity}</span>
            <span>1. Executive Diagnosis (Bedah Kekuatan &amp; Titik Bocor Utama)</span>
          </span>
        </h3>
        ${diagOverview ? `<p class="kala-bp-text" style="margin-bottom: 14px;">${esc(diagOverview)}</p>` : ''}
        <div class="kala-bp-grid-2" style="margin-bottom: 0;">
          <div class="kala-bp-subbox" style="border-left: 4px solid #10B981; margin-bottom: 0;">
            <div class="kala-bp-subbox-title" style="color: #059669;">
              <span class="kala-bp-subbox-label">${IC.checkCircle}<span>Kekuatan &amp; Modal Utama Brand (Strengths)</span></span>
            </div>
            <ul class="kala-bp-list">
              ${strengths.map(s => `<li>${esc(s)}</li>`).join('') || '<li>Modal dasar produk & identitas bisnis siap dikembangkan.</li>'}
            </ul>
          </div>
          <div class="kala-bp-subbox" style="border-left: 4px solid #EF4444; margin-bottom: 0;">
            <div class="kala-bp-subbox-title" style="color: #DC2626;">
              <span class="kala-bp-subbox-label">${IC.alertTriangle}<span>Titik Bocor &amp; Bottleneck Utama (Core Bottlenecks)</span></span>
            </div>
            <ul class="kala-bp-list">
              ${bottlenecks.map(b => `<li>${esc(b)}</li>`).join('') || '<li>Perlu optimasi alur konten ke konversi DM.</li>'}
            </ul>
          </div>
        </div>
      </div>

      <!-- 3. STOREFRONT & PROFILE REVAMP -->
      <div class="kala-bp-card">
        <h3 class="kala-bp-card-title">
          <span class="kala-bp-title-left">
            <span class="kala-bp-sec-icon">${IC.layout}</span>
            <span>2. Revamp Profil &amp; Etalase Digital Instagram (Storefront &amp; Funnel)</span>
          </span>
        </h3>

        <div class="kala-bp-grid-2">
          <div class="kala-bp-subbox" style="margin-bottom: 0;">
            <div class="kala-bp-subbox-title">
              <span class="kala-bp-subbox-label">${IC.sparkles}<span>Opsi 1: Draft Bio Instagram Baru (Siap Copy-Paste)</span></span>
              ${bioOpt1 ? `<button type="button" class="kala-bp-copy-btn" onclick="copyKalaAiText(this, '${encodeURIComponent(bioOpt1)}')">${IC.copy}<span>Copy Bio 1</span></button>` : ''}
            </div>
            <div class="kala-bp-text" style="font-weight: 600;">${esc(bioOpt1 || '-')}</div>
          </div>

          <div class="kala-bp-subbox" style="margin-bottom: 0;">
            <div class="kala-bp-subbox-title">
              <span class="kala-bp-subbox-label">${IC.zap}<span>Opsi 2: Draft Bio Alternatif (Problem-Solution)</span></span>
              ${bioOpt2 ? `<button type="button" class="kala-bp-copy-btn" onclick="copyKalaAiText(this, '${encodeURIComponent(bioOpt2)}')">${IC.copy}<span>Copy Bio 2</span></button>` : ''}
            </div>
            <div class="kala-bp-text" style="font-weight: 600;">${esc(bioOpt2 || bioOpt1 || '-')}</div>
          </div>
        </div>

        <div class="kala-bp-subbox">
          <div class="kala-bp-subbox-title">
            <span class="kala-bp-subbox-label">${IC.link}<span>Rekomendasi Arsitektur Link in Bio &amp; Funnel</span></span>
          </div>
          <p class="kala-bp-text">${esc(linkFunnel || '-')}</p>
        </div>

        <div style="margin: 14px 0 8px; font-size: 12.5px; font-weight: 800; display: flex; align-items: center; gap: 6px;">
          ${IC.bookmark}
          <span>Rekomendasi 3 Pinned Posts Wajib di Baris Atas Feed:</span>
        </div>
        <div class="kala-bp-grid-3">
          ${pinnedPosts.map((pp, idx) => {
            if (typeof pp === 'string') {
              return `<div class="kala-bp-subbox" style="margin-bottom:0;"><div class="kala-bp-subbox-title">Pinned Post #${idx+1}</div><p class="kala-bp-text">${esc(pp)}</p></div>`;
            }
            return `
              <div class="kala-bp-subbox" style="margin-bottom: 0;">
                <div class="kala-bp-subbox-title">
                  <span>${esc(pp.slot || ('Pinned Post #' + (idx + 1)))}</span>
                  <span style="font-size: 10px; padding: 2px 6px; border-radius: 4px; background: rgba(91,61,245,0.12);">${esc(pp.format || 'Carousel/Reels')}</span>
                </div>
                <div style="font-size: 13px; font-weight: 800; margin-bottom: 4px;">${esc(pp.title || pp.headline || '')}</div>
                <p class="kala-bp-text" style="font-size: 12px;">${esc(pp.concept || pp.angle || '')}</p>
              </div>
            `;
          }).join('') || '<div class="kala-bp-subbox">3 Pinned Posts: (1) Brand Introduction & USP, (2) Best Seller & Testimoni Nyata, (3) Cara Order & Penawaran.</div>'}
        </div>

        <div class="kala-bp-subbox" style="margin-top: 12px;">
          <div class="kala-bp-subbox-title">
            <span class="kala-bp-subbox-label">${IC.layers}<span>Susunan Story Highlights Penumbuh Trust</span></span>
          </div>
          <ul class="kala-bp-list">
            ${highlights.map(h => `<li>${esc(typeof h === 'object' ? ((h.title || '') + (h.content ? ' — ' + h.content : '')) : h)}</li>`).join('') || '<li>About Us, Katalog/Pricelist, Testimoni, FAQ, Cara Order</li>'}
          </ul>
        </div>
      </div>

      <!-- 4. CONTENT STRATEGY & HOOK FORMULA -->
      <div class="kala-bp-card">
        <h3 class="kala-bp-card-title">
          <span class="kala-bp-title-left">
            <span class="kala-bp-sec-icon">${IC.video}</span>
            <span>3. Strategi Pilar Konten, Jadwal Format &amp; Formula Hook</span>
          </span>
        </h3>

        <div style="margin-bottom: 8px; font-size: 12.5px; font-weight: 800; display: flex; align-items: center; gap: 6px;">
          ${IC.layers}
          <span>Pilar Konten Utama (Content Pillars) &amp; Ide Topik Konkret:</span>
        </div>
        <div class="kala-bp-grid-3">
          ${pillars.map((p, idx) => `
            <div class="kala-bp-subbox" style="margin-bottom: 0; border-top: 3px solid #5B3DF5;">
              <div class="kala-bp-subbox-title">
                <span>Pilar #${idx + 1}: ${esc(p.pillar_name || p.name || '')}</span>
              </div>
              ${(p.objective || p.purpose) ? `<div style="font-size: 11.5px; font-weight: 700; opacity: 0.85; margin-bottom: 6px;">Objektif: ${esc(p.objective || p.purpose)}</div>` : ''}
              <ul class="kala-bp-list" style="font-size: 12px;">
                ${(Array.isArray(p.topic_ideas) ? p.topic_ideas : (Array.isArray(p.sample_topics) ? p.sample_topics : (Array.isArray(p.ideas) ? p.ideas : []))).map(idea => `<li>${esc(idea)}</li>`).join('')}
              </ul>
            </div>
          `).join('') || '<div class="kala-bp-subbox">Pilar Edukasi, Social Proof, dan Product Solution.</div>'}
        </div>

        <div class="kala-bp-grid-2" style="margin-top: 14px; margin-bottom: 0;">
          <div class="kala-bp-subbox" style="margin-bottom: 0;">
            <div class="kala-bp-subbox-title">
              <span class="kala-bp-subbox-label">${IC.calendar}<span>Komposisi &amp; Jadwal Format Mingguan</span></span>
            </div>
            <div style="font-size: 12.5px; font-weight: 800; margin-bottom: 6px;">${esc(weekly.composition_ratio || '')}</div>
            <p class="kala-bp-text">${esc(weekly.schedule_plan || (typeof weekly === 'string' ? weekly : ''))}</p>
          </div>

          <div class="kala-bp-subbox" style="margin-bottom: 0;">
            <div class="kala-bp-subbox-title">
              <span class="kala-bp-subbox-label">${IC.zap}<span>Formula Hook 3 Detik Pertama &amp; Arahan Talent</span></span>
            </div>
            <p class="kala-bp-text" style="margin-bottom: 8px;">${esc(hookPersonaText)}</p>
            ${hookFormulas.length > 0 ? `
              <div style="font-size: 11.5px; font-weight: 800; margin-bottom: 4px;">Contoh Kalimat Hook Siap Pakai:</div>
              <ul class="kala-bp-list" style="font-size: 12px;">
                ${hookFormulas.map(hf => `<li>${esc(hf)}</li>`).join('')}
              </ul>
            ` : ''}
          </div>
        </div>
      </div>

      <!-- 5. CLOSING DM SOP & META ADS / KOL BLUEPRINT -->
      <div class="kala-bp-card">
        <h3 class="kala-bp-card-title">
          <span class="kala-bp-title-left">
            <span class="kala-bp-sec-icon">${IC.messageSquare}</span>
            <span>4. SOP Closing DM Anti-Ghosting &amp; Skema Meta Ads / KOL</span>
          </span>
        </h3>

        <div class="kala-bp-subbox" style="border-left: 4px solid #0891B2;">
          <div class="kala-bp-subbox-title">
            <span class="kala-bp-subbox-label">${IC.shield}<span>SOP &amp; Skrip Balasan DM Anti-Ghosting (Saat Prospek Tanya Harga)</span></span>
          </div>
          ${dmSop.response_sla ? `<div style="font-size: 12px; font-weight: 700; margin-bottom: 10px;">Standar SLA Respon: ${esc(dmSop.response_sla)}</div>` : ''}
          ${(dmSop.step_1_greeting_diagnose || dmSop.step_2_value_and_price || dmSop.step_3_anti_ghosting_followup) ? `
            <div class="kala-bp-grid-3" style="margin-bottom: 0;">
              <div style="background: rgba(91, 61, 245, 0.05); border: 1px solid rgba(91, 61, 245, 0.2); border-radius: 10px; padding: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                  <span style="font-size: 11px; font-weight: 800;">Tahap 1: Sambutan &amp; Diagnosa</span>
                  ${dmSop.step_1_greeting_diagnose ? `<button type="button" class="kala-bp-copy-btn" onclick="copyKalaAiText(this, '${encodeURIComponent(dmSop.step_1_greeting_diagnose)}')">${IC.copy}<span>Copy</span></button>` : ''}
                </div>
                <p class="kala-bp-text" style="font-size: 12px;">${esc(dmSop.step_1_greeting_diagnose || '-')}</p>
              </div>
              <div style="background: rgba(91, 61, 245, 0.05); border: 1px solid rgba(91, 61, 245, 0.2); border-radius: 10px; padding: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                  <span style="font-size: 11px; font-weight: 800;">Tahap 2: Value Framing &amp; Harga</span>
                  ${dmSop.step_2_value_and_price ? `<button type="button" class="kala-bp-copy-btn" onclick="copyKalaAiText(this, '${encodeURIComponent(dmSop.step_2_value_and_price)}')">${IC.copy}<span>Copy</span></button>` : ''}
                </div>
                <p class="kala-bp-text" style="font-size: 12px;">${esc(dmSop.step_2_value_and_price || '-')}</p>
              </div>
              <div style="background: rgba(91, 61, 245, 0.05); border: 1px solid rgba(91, 61, 245, 0.2); border-radius: 10px; padding: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                  <span style="font-size: 11px; font-weight: 800;">Tahap 3: Follow-Up Anti-Ghosting</span>
                  ${dmSop.step_3_anti_ghosting_followup ? `<button type="button" class="kala-bp-copy-btn" onclick="copyKalaAiText(this, '${encodeURIComponent(dmSop.step_3_anti_ghosting_followup)}')">${IC.copy}<span>Copy</span></button>` : ''}
                </div>
                <p class="kala-bp-text" style="font-size: 12px;">${esc(dmSop.step_3_anti_ghosting_followup || '-')}</p>
              </div>
            </div>
          ` : `
            <p class="kala-bp-text">${esc(typeof dmSop === 'string' ? dmSop : (Array.isArray(dmSop.flow_steps) ? dmSop.flow_steps.join('\n') : '-'))}</p>
          `}
        </div>

        <div class="kala-bp-grid-2" style="margin-top: 12px; margin-bottom: 0;">
          <div class="kala-bp-subbox" style="margin-bottom: 0;">
            <div class="kala-bp-subbox-title">
              <span class="kala-bp-subbox-label">${IC.trendingUp}<span>Strategi Funneling Instagram / Meta Ads</span></span>
            </div>
            <p class="kala-bp-text">${esc(adsKol.meta_ads_funnel || (typeof adsKol === 'string' ? adsKol : '-'))}</p>
          </div>
          <div class="kala-bp-subbox" style="margin-bottom: 0;">
            <div class="kala-bp-subbox-title">
              <span class="kala-bp-subbox-label">${IC.users}<span>Strategi Kolaborasi KOL / Influencer</span></span>
            </div>
            <p class="kala-bp-text">${esc(adsKol.kol_collaboration || (typeof adsKol === 'string' ? adsKol : '-'))}</p>
          </div>
        </div>
      </div>

      <!-- 6. 90-DAY EXECUTION ROADMAP -->
      <div class="kala-bp-card" style="margin-bottom: 6px;">
        <h3 class="kala-bp-card-title">
          <span class="kala-bp-title-left">
            <span class="kala-bp-sec-icon">${IC.compass}</span>
            <span>5. Peta Jalan Eksekusi 90 Hari (3-Month Action Roadmap)</span>
          </span>
        </h3>
        <div class="kala-bp-grid-3" style="margin-bottom: 0;">
          ${[
            { data: m1 || rm.month_1_foundation, defaultTitle: 'Bulan 1 (Foundation & Quick Wins)', color: '#5B3DF5' },
            { data: m2 || rm.month_2_growth, defaultTitle: 'Bulan 2 (Growth & Engagement)', color: '#0891B2' },
            { data: m3 || rm.month_3_scale, defaultTitle: 'Bulan 3 (Conversion & Scale-Up)', color: '#10B981' }
          ].map(item => {
            const d = item.data || {};
            const title = (typeof d === 'object' && d !== null && d.phase_title) ? d.phase_title : item.defaultTitle;
            const focus = (typeof d === 'object' && d !== null && d.focus) ? d.focus : '';
            const actions = Array.isArray(d) ? d : ((typeof d === 'object' && d !== null && Array.isArray(d.action_items)) ? d.action_items : (typeof d === 'string' && d ? [d] : []));
            return `
              <div class="kala-bp-subbox" style="margin-bottom: 0; border-top: 4px solid ${item.color};">
                <div class="kala-bp-subbox-title" style="color: ${item.color};">
                  <span>${esc(title)}</span>
                </div>
                ${focus ? `<div style="font-size: 12px; font-weight: 700; margin-bottom: 8px;">Fokus: ${esc(focus)}</div>` : ''}
                <ul class="kala-bp-list" style="font-size: 12px;">
                  ${actions.map(a => `<li>${esc(a)}</li>`).join('') || '<li>Eksekusi bertahap sesuai KPI bulanan.</li>'}
                </ul>
              </div>
            `;
          }).join('')}
        </div>
      </div>
    `;
  };

  /**
   * Generates & Downloads a clean A4 Executive PDF Blueprint with PT Kala Kausa Prima header
   */
  window.downloadKalaAiBlueprintPdf = function(analysis, client, auditMeta) {
    if (!analysis) {
      if (typeof showToast === 'function') showToast('Belum ada hasil analisis untuk diunduh.', 'warning');
      return;
    }

    const companyName = (client && client.company) ? client.company : 'Klien_Kalamedia';
    const htmlCards = window.renderKalaAiBlueprintCards(analysis, client, auditMeta);

    const printWin = window.open('', '_blank', 'width=1020,height=900');
    if (!printWin) {
      window.print();
      return;
    }

    const baseUrl = window.location.origin + (window.__KALA_BASE_URL__ || '');
    const logoUrl = baseUrl + '/assets/img/Logo-1.webp';

    printWin.document.write(`
      <!DOCTYPE html>
      <html lang="id">
      <head>
        <meta charset="UTF-8">
        <title>Kala AI Brand Strategy Blueprint - ${window.escapeKalaHtml(companyName)}</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
          * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
          body { font-family: 'Inter', Arial, sans-serif; background: #FFFFFF; color: #0F172A; margin: 0; padding: 24px; }
          .pdf-doc-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #1E1B4B; padding-bottom: 14px; margin-bottom: 20px; }
          .pdf-brand-left { display: flex; align-items: center; gap: 12px; }
          .pdf-brand-left img { width: 42px; height: 42px; object-fit: contain; }
          .kala-bp-hero { background: linear-gradient(135deg, #1E1B4B 0%, #312E81 60%, #0F766E 100%); border-radius: 16px; padding: 20px; color: #FFFFFF !important; margin-bottom: 16px; page-break-inside: avoid; }
          .kala-bp-hero * { color: #FFFFFF !important; }
          .kala-bp-hero-grid { display: grid; grid-template-columns: 150px 1fr 1.1fr; gap: 16px; align-items: center; background: rgba(15, 23, 42, 0.32); border: 1px solid rgba(255,255,255,0.14); border-radius: 14px; padding: 16px; }
          .kala-bp-grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 12px; }
          .kala-bp-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 12px; }
          .kala-bp-card { background: #FFFFFF; border: 1px solid #CBD5E1; border-radius: 14px; padding: 16px; margin-bottom: 14px; page-break-inside: avoid; }
          .kala-bp-card-title { font-size: 14px; font-weight: 800; color: #0F172A; margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px; }
          .kala-bp-title-left { display: inline-flex; align-items: center; gap: 8px; }
          .kala-bp-sec-icon { width: 26px; height: 26px; border-radius: 6px; background: #EEF2FF; color: #4F46E5; display: inline-flex; align-items: center; justify-content: center; }
          .kala-bp-subbox { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 12px; margin-bottom: 10px; }
          .kala-bp-subbox-title { font-size: 11px; font-weight: 800; text-transform: uppercase; color: #4338CA; margin-bottom: 5px; display: flex; justify-content: space-between; align-items: center; }
          .kala-bp-subbox-label { display: inline-flex; align-items: center; gap: 5px; }
          .kala-bp-text { font-size: 12px; color: #1E293B; line-height: 1.55; margin: 0; white-space: pre-line; }
          .kala-bp-list { margin: 0; padding-left: 16px; font-size: 12px; color: #1E293B; line-height: 1.55; }
          .kala-bp-copy-btn { display: none !important; }
          @page { size: A4; margin: 12mm; }
        </style>
      </head>
      <body>
        <div class="pdf-doc-header">
          <div class="pdf-brand-left">
            <img src="${logoUrl}" alt="Kalamedia" onerror="this.style.display='none'">
            <div>
              <div style="font-size: 16px; font-weight: 800; color: #0F172A;">PT KALA KAUSA PRIMA (KALAMEDIA CREATIVE)</div>
              <div style="font-size: 11.5px; color: #475569;">Kala AI Brand Diagnostic &amp; 90-Day Strategy Blueprint Official Document</div>
            </div>
          </div>
          <div style="text-align: right; font-size: 11.5px; color: #475569;">
            <div style="font-weight: 800; color: #0F172A;">KLIEN: ${window.escapeKalaHtml(companyName)}</div>
            <div>Tanggal Cetak: ${new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })}</div>
          </div>
        </div>
        ${htmlCards}
        <div style="margin-top: 18px; border-top: 1px solid #E2E8F0; padding-top: 10px; display: flex; justify-content: space-between; font-size: 10.5px; color: #64748B;">
          <span>Dokumen Rahasia Klien &bull; Disusun oleh Tim Strategist PT Kala Kausa Prima &amp; Google Gemini Pro Engine</span>
          <span>kalamediacreative.com</span>
        </div>
      </body>
      </html>
    `);
    printWin.document.close();
    printWin.focus();
    setTimeout(() => {
      printWin.print();
    }, 550);
  };
</script>

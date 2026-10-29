<?php
/**
 * Kala AI Brand Diagnostic & Strategy Blueprint - Modal Workspace (Owner & Admin)
 * Powered by Google Gemini Pro Engine (gemini-2.5-pro default)
 * 100% Crisp SVG Icons (Zero Emojis) & Full Light / NebulaPay Dark Mode Support
 */
require_once BASE_PATH . '/includes/brand_ai_blueprint_shared.php';
?>
<style>
  #modal-brand-ai-audit {
    z-index: 10050;
  }
  #modal-brand-ai-audit .kala-ai-modal-dialog {
    max-width: 1140px;
    width: 95%;
    margin: 20px auto;
  }
  #modal-brand-ai-audit .kala-ai-modal-content {
    border-radius: 20px;
    overflow: hidden;
    background: #FFFFFF;
    border: 1px solid #E4E7EC;
    box-shadow: 0 24px 60px -12px rgba(16, 24, 40, 0.28);
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 40px);
  }
  .kala-ai-ws-header {
    padding: 18px 24px;
    background: #FFFFFF;
    color: #0F172A;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    border-bottom: 1px solid #E2E8F0;
  }
  .kala-ai-ws-header-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: linear-gradient(135deg, #4F46E5 0%, #2563EB 100%);
    color: #FFFFFF;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 6px 14px rgba(79, 70, 229, 0.25);
  }
  .kala-ai-ws-header-title {
    margin: 0;
    font-size: 16px;
    font-weight: 800;
    color: #0F172A;
    letter-spacing: -0.01em;
  }
  .kala-ai-ws-header-sub {
    font-size: 12.5px;
    color: #64748B;
    margin-top: 2px;
  }
  .kala-ai-ws-header-sub b {
    color: #0F172A;
  }
  .kala-ai-ws-model-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: rgba(79, 70, 229, 0.1);
    border: 1px solid rgba(79, 70, 229, 0.28);
    color: #4F46E5;
    font-size: 10.5px;
    font-weight: 800;
    padding: 3px 9px;
    border-radius: 999px;
  }
  .kala-ai-ws-close-btn {
    background: #F1F5F9;
    border: 1px solid #E2E8F0;
    color: #475569;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    font-size: 18px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
  }
  .kala-ai-ws-close-btn:hover {
    background: #E2E8F0;
    color: #0F172A;
  }
  .kala-ai-ws-tabs-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 24px;
    background: #F8FAFC;
    border-bottom: 1px solid #E2E8F0;
    flex-wrap: wrap;
  }
  .kala-ai-ws-tab-group {
    display: inline-flex;
    background: #E2E8F0;
    padding: 4px;
    border-radius: 11px;
    gap: 4px;
  }
  .kala-ai-ws-tab-btn {
    border: none;
    background: transparent;
    color: #475569;
    font-size: 12.5px;
    font-weight: 700;
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.18s ease;
  }
  .kala-ai-ws-tab-btn.active {
    background: #4F46E5;
    color: #FFFFFF;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
  }
  .kala-ai-ws-body {
    padding: 20px 24px;
    overflow-y: auto;
    flex: 1;
    background: #F8FAFC;
  }
  .kala-ai-intro-banner {
    background: linear-gradient(135deg, #EEF2FF 0%, #F5F3FF 100%);
    border: 1px solid #C7D2FE;
    border-radius: 12px;
    padding: 14px 18px;
    margin-bottom: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
  }
  .kala-ai-intro-text {
    font-size: 13px;
    color: #1E1B4B;
    line-height: 1.55;
    font-weight: 500;
    margin: 0;
    flex: 1;
    min-width: 260px;
  }
  .kala-ai-acc-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    margin-bottom: 14px;
    overflow: hidden;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
    transition: border-color 0.2s ease;
  }
  .kala-ai-acc-card:hover {
    border-color: #CBD5E1;
  }
  .kala-ai-acc-header {
    width: 100%;
    background: #FFFFFF;
    border: none;
    padding: 14px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    text-align: left;
    border-bottom: 1px solid transparent;
    transition: background 0.15s ease;
  }
  .kala-ai-acc-card.open .kala-ai-acc-header {
    background: #F8FAFC;
    border-bottom-color: #E2E8F0;
  }
  .kala-ai-acc-chevron {
    transition: transform 0.2s ease;
  }
  .kala-ai-acc-card.open .kala-ai-acc-chevron {
    transform: rotate(180deg);
  }
  .kala-ai-acc-body {
    padding: 18px;
    display: none;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
  }
  .kala-ai-acc-card.open .kala-ai-acc-body {
    display: grid;
  }
  @media (max-width: 820px) {
    .kala-ai-acc-card.open .kala-ai-acc-body {
      grid-template-columns: 1fr;
    }
  }
  .kala-ai-q-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 13px 14px;
  }
  .kala-ai-q-item.full-width {
    grid-column: 1 / -1;
  }
  .kala-ai-q-label {
    font-size: 12.5px;
    font-weight: 800;
    color: #0F172A;
    display: flex;
    align-items: center;
    gap: 8px;
    line-height: 1.4;
  }
  .kala-ai-q-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 22px;
    padding: 0 6px;
    border-radius: 6px;
    background: #4F46E5;
    color: #FFFFFF;
    font-size: 11px;
    font-weight: 800;
    flex-shrink: 0;
  }
  .kala-ai-q-desc {
    font-size: 12px;
    color: #475569;
    line-height: 1.5;
    margin-bottom: 4px;
  }
  .kala-ai-q-textarea {
    width: 100%;
    min-height: 74px;
    border: 1.5px solid #CBD5E1;
    border-radius: 10px;
    padding: 9px 12px;
    font-size: 12.5px;
    font-family: inherit;
    color: #0F172A;
    background: #FFFFFF;
    resize: vertical;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
    box-sizing: border-box;
  }
  .kala-ai-q-textarea:focus {
    outline: none;
    border-color: #4F46E5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
  }
  .kala-ai-ws-footer {
    padding: 14px 24px;
    background: #FFFFFF;
    border-top: 1px solid #E2E8F0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }
  .kala-ai-edit-drawer {
    background: #FFFFFF;
    border: 2px solid #6366F1;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 12px 32px -8px rgba(79, 70, 229, 0.18);
  }

  /* ==========================================================================
     NEBULAPAY DARK MODE OVERRIDES FOR KALA AI MODAL WORKSPACE
     ========================================================================== */
  html[data-theme="dark"] #modal-brand-ai-audit .kala-ai-modal-content,
  body.dark-mode #modal-brand-ai-audit .kala-ai-modal-content {
    background: #0B0F19 !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
  }
  html[data-theme="dark"] .kala-ai-ws-header,
  body.dark-mode .kala-ai-ws-header {
    background: #111827 !important;
    border-bottom-color: rgba(255, 255, 255, 0.08) !important;
    color: #F8FAFC !important;
  }
  html[data-theme="dark"] .kala-ai-ws-header-title,
  body.dark-mode .kala-ai-ws-header-title,
  html[data-theme="dark"] .kala-ai-ws-header-sub b,
  body.dark-mode .kala-ai-ws-header-sub b {
    color: #F8FAFC !important;
  }
  html[data-theme="dark"] .kala-ai-ws-header-sub,
  body.dark-mode .kala-ai-ws-header-sub {
    color: #94A3B8 !important;
  }
  html[data-theme="dark"] .kala-ai-ws-model-pill,
  body.dark-mode .kala-ai-ws-model-pill {
    background: rgba(99, 102, 241, 0.22) !important;
    border-color: rgba(129, 140, 248, 0.4) !important;
    color: #A5B4FC !important;
  }
  html[data-theme="dark"] .kala-ai-ws-close-btn,
  body.dark-mode .kala-ai-ws-close-btn {
    background: #1E293B !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
    color: #CBD5E1 !important;
  }
  html[data-theme="dark"] .kala-ai-ws-close-btn:hover,
  body.dark-mode .kala-ai-ws-close-btn:hover {
    background: #334155 !important;
    color: #FFFFFF !important;
  }
  html[data-theme="dark"] .kala-ai-ws-tabs-bar,
  body.dark-mode .kala-ai-ws-tabs-bar {
    background: #111827 !important;
    border-bottom-color: rgba(255, 255, 255, 0.08) !important;
  }
  html[data-theme="dark"] .kala-ai-ws-tab-group,
  body.dark-mode .kala-ai-ws-tab-group {
    background: #1E293B !important;
  }
  html[data-theme="dark"] .kala-ai-ws-tab-btn,
  body.dark-mode .kala-ai-ws-tab-btn {
    color: #94A3B8 !important;
  }
  html[data-theme="dark"] .kala-ai-ws-tab-btn.active,
  body.dark-mode .kala-ai-ws-tab-btn.active {
    background: #6366F1 !important;
    color: #FFFFFF !important;
  }
  html[data-theme="dark"] .kala-ai-ws-body,
  body.dark-mode .kala-ai-ws-body {
    background: #07090E !important;
  }
  html[data-theme="dark"] .kala-ai-intro-banner,
  body.dark-mode .kala-ai-intro-banner {
    background: linear-gradient(135deg, rgba(49, 46, 129, 0.4) 0%, rgba(30, 27, 75, 0.55) 100%) !important;
    border-color: rgba(99, 102, 241, 0.35) !important;
  }
  html[data-theme="dark"] .kala-ai-intro-text,
  body.dark-mode .kala-ai-intro-text {
    color: #E0E7FF !important;
  }
  html[data-theme="dark"] .kala-ai-acc-card,
  body.dark-mode .kala-ai-acc-card {
    background: #121826 !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
  }
  html[data-theme="dark"] .kala-ai-acc-header,
  body.dark-mode .kala-ai-acc-header {
    background: #121826 !important;
    color: #F8FAFC !important;
  }
  html[data-theme="dark"] .kala-ai-acc-card.open .kala-ai-acc-header,
  body.dark-mode .kala-ai-acc-card.open .kala-ai-acc-header {
    background: #161D2F !important;
    border-bottom-color: rgba(255, 255, 255, 0.08) !important;
  }
  html[data-theme="dark"] .kala-ai-q-item,
  body.dark-mode .kala-ai-q-item {
    background: #0D1322 !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
  }
  html[data-theme="dark"] .kala-ai-q-label,
  body.dark-mode .kala-ai-q-label {
    color: #F8FAFC !important;
  }
  html[data-theme="dark"] .kala-ai-q-desc,
  body.dark-mode .kala-ai-q-desc {
    color: #94A3B8 !important;
  }
  html[data-theme="dark"] .kala-ai-q-textarea,
  body.dark-mode .kala-ai-q-textarea {
    background: #161D2F !important;
    border-color: rgba(255, 255, 255, 0.14) !important;
    color: #F8FAFC !important;
  }
  html[data-theme="dark"] .kala-ai-q-textarea:focus,
  body.dark-mode .kala-ai-q-textarea:focus {
    border-color: #818CF8 !important;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25) !important;
  }
  html[data-theme="dark"] .kala-ai-ws-footer,
  body.dark-mode .kala-ai-ws-footer {
    background: #111827 !important;
    border-top-color: rgba(255, 255, 255, 0.08) !important;
  }
  html[data-theme="dark"] .kala-ai-edit-drawer,
  body.dark-mode .kala-ai-edit-drawer {
    background: #121826 !important;
    border-color: #6366F1 !important;
    color: #F8FAFC !important;
  }

  /* ==========================================================================
     MOBILE RESPONSIVENESS (<= 768px) FOR KALA AI AUDIT MODAL
     ========================================================================== */
  @media (max-width: 768px) {
    #modal-brand-ai-audit .kala-ai-modal-dialog {
      width: 98%;
      margin: 8px auto;
    }
    #modal-brand-ai-audit .kala-ai-modal-content {
      max-height: calc(100vh - 16px);
      border-radius: 16px;
    }
    .kala-ai-ws-header {
      padding: 13px 15px;
      gap: 10px;
    }
    .kala-ai-ws-header-title {
      font-size: 14.5px;
    }
    .kala-ai-ws-tabs-bar {
      padding: 10px 14px;
      gap: 8px;
    }
    .kala-ai-ws-tab-group {
      width: 100%;
      display: grid;
      grid-template-columns: 1fr 1fr;
    }
    .kala-ai-ws-tab-btn {
      font-size: 11.5px;
      padding: 8px 10px;
      justify-content: center;
      text-align: center;
    }
    .kala-ai-ws-body {
      padding: 14px 14px;
    }
    .kala-ai-acc-header {
      padding: 12px 14px;
    }
    .kala-ai-acc-body {
      padding: 13px;
      gap: 12px;
    }
    #kala-audit-edit-panel > div[style*="grid-template-columns"] {
      grid-template-columns: 1fr !important;
    }
    .kala-ai-ws-footer {
      padding: 12px 14px;
      flex-direction: column;
      align-items: stretch;
    }
    .kala-ai-ws-footer > div:last-child {
      width: 100%;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .kala-ai-ws-footer > div:last-child button {
      width: 100%;
      justify-content: center;
    }
  }
</style>

<div class="modal" id="modal-brand-ai-audit" style="display: none;">
  <div class="modal-dialog kala-ai-modal-dialog">
    <div class="modal-content kala-ai-modal-content">
      
      <!-- Top Executive Header -->
      <div class="kala-ai-ws-header">
        <div style="display: flex; align-items: center; gap: 12px;">
          <div class="kala-ai-ws-header-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.912 5.813a2 2 0 001.275 1.275L21 12l-5.813 1.912a2 2 0 00-1.275 1.275L12 21l-1.912-5.813a2 2 0 00-1.275-1.275L3 12l5.813-1.912a2 2 0 001.275-1.275L12 3z"/></svg>
          </div>
          <div>
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
              <h3 class="kala-ai-ws-header-title">
                Kala AI Brand Diagnostic &amp; Strategy Blueprint
              </h3>
              <span id="kala-audit-model-badge" class="kala-ai-ws-model-pill">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                <span id="kala-audit-model-badge-text">Powered by Gemini Pro</span>
              </span>
            </div>
            <div class="kala-ai-ws-header-sub">
              Klien: <b id="kala-audit-client-title">-</b>
            </div>
          </div>
        </div>

        <div style="display: flex; align-items: center; gap: 10px;">
          <span id="kala-audit-status-pill" style="background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; font-size: 11px; font-weight: 800; padding: 4px 12px; border-radius: 999px;">
            Belum Dianalisis
          </span>
          <button type="button" class="kala-ai-ws-close-btn" onclick="closeModal('modal-brand-ai-audit')">
            &times;
          </button>
        </div>
      </div>

      <!-- 2 Main Workspace Tabs -->
      <div class="kala-ai-ws-tabs-bar">
        <div class="kala-ai-ws-tab-group">
          <button type="button" class="kala-ai-ws-tab-btn active" id="kala-audit-tab-btn-1" onclick="switchKalaAuditWorkspaceTab(1)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            <span>Tab 1: Kuesioner Diagnostik (17 Poin)</span>
            <span id="kala-audit-filled-counter" style="background: rgba(255,255,255,0.2); padding: 1px 7px; border-radius: 999px; font-size: 11px;">0/17</span>
          </button>
          <button type="button" class="kala-ai-ws-tab-btn" id="kala-audit-tab-btn-2" onclick="switchKalaAuditWorkspaceTab(2)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.912 5.813a2 2 0 001.275 1.275L21 12l-5.813 1.912a2 2 0 00-1.275 1.275L12 21l-1.912-5.813a2 2 0 00-1.275-1.275L3 12l5.813-1.912a2 2 0 001.275-1.275L12 3z"/></svg>
            <span>Tab 2: Hasil Analisis &amp; Skema Strategi AI</span>
          </button>
        </div>

        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;" id="kala-audit-tab1-quick-tools">
          <button type="button" class="btn btn-secondary btn-sm" onclick="toggleAllKalaAuditSections(true)" style="font-size: 11.5px; font-weight: 700; padding: 5px 10px;">
            Buka Semua Bagian
          </button>
          <button type="button" class="btn btn-secondary btn-sm" onclick="toggleAllKalaAuditSections(false)" style="font-size: 11.5px; font-weight: 700; padding: 5px 10px;">
            Tutup Semua
          </button>
        </div>
      </div>

      <!-- Workspace Body -->
      <div class="kala-ai-ws-body">
        <input type="hidden" id="kala-audit-client-id" value="0">

        <!-- ==============================================================
             TAB 1: KUESIONER DIAGNOSTIK (17 POIN DALAM 6 BAGIAN)
             ============================================================== -->
        <div id="kala-audit-tab-pane-1">
          <div class="kala-ai-intro-banner">
            <p class="kala-ai-intro-text">
              <i>"Silakan isi poin-poin diagnostik dari hulu ke hilir di bawah ini. Anda dapat mengisi semuanya sekaligus atau mencicil per bagian (Simpan Draft), dan boleh melewati poin yang datanya belum dipegang."</i>
            </p>
            <span id="kala-audit-last-saved-info" style="font-size: 11.5px; font-weight: 700; color: #4F46E5; white-space: nowrap;"></span>
          </div>

          <form id="form-kala-brand-audit-questions" onsubmit="return false;">
            
            <!-- BAGIAN 1: Identitas Bisnis & Target Utama (Business & Goal) -->
            <div class="kala-ai-acc-card open">
              <button type="button" class="kala-ai-acc-header" onclick="toggleKalaAuditSection(this)">
                <div style="display: flex; align-items: center; gap: 10px;">
                  <span style="background: #EEF2FF; color: #4F46E5; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 7px;">BAGIAN 1</span>
                  <span style="font-size: 13.5px; font-weight: 800;">Bagian 1: Identitas Bisnis &amp; Target Utama (Business &amp; Goal)</span>
                </div>
                <span style="font-size: 12px; color: #64748B; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
                  <span>Poin 1 – 4</span>
                  <svg class="kala-ai-acc-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
              </button>
              <div class="kala-ai-acc-body">
                <div class="kala-ai-q-item">
                  <label class="kala-ai-q-label" for="kala-q-1">
                    <span class="kala-ai-q-num">1</span>
                    <span>Nama Akun &amp; Niche</span>
                  </label>
                  <div class="kala-ai-q-desc">Apa username Instagram klien, bergerak di bidang apa, dan produk atau jasa apa yang menjadi ujung tombak penjualan?</div>
                  <textarea id="kala-q-1" data-q-key="q1" class="kala-ai-q-textarea" placeholder="Contoh: @brandklien.id — Bergerak di bidang F&B / Skincare / Fashion, produk utama..." oninput="updateKalaAuditFilledCounter()"></textarea>
                </div>

                <div class="kala-ai-q-item">
                  <label class="kala-ai-q-label" for="kala-q-2">
                    <span class="kala-ai-q-num">2</span>
                    <span>Harga &amp; Model Bisnis</span>
                  </label>
                  <div class="kala-ai-q-desc">Berapa kisaran harga produk, dan bagaimana cara pelanggan membeli (lewat DM/WhatsApp, Shopee/Tokopedia, website, atau datang ke toko fisik)?</div>
                  <textarea id="kala-q-2" data-q-key="q2" class="kala-ai-q-textarea" placeholder="Contoh: Kisaran Rp 85.000 - Rp 250.000, pembelian mayoritas via WhatsApp Admin & Shopee..." oninput="updateKalaAuditFilledCounter()"></textarea>
                </div>

                <div class="kala-ai-q-item">
                  <label class="kala-ai-q-label" for="kala-q-3">
                    <span class="kala-ai-q-num">3</span>
                    <span>Target Utama 3 Bulan ke Depan</span>
                  </label>
                  <div class="kala-ai-q-desc">Apa 1 gol paling mendesak yang mau dicapai dari Instagram saat ini—apakah menaikkan penjualan langsung, mencari leads/klien, membangun awareness dari nol, atau memperbaiki citra brand?</div>
                  <textarea id="kala-q-3" data-q-key="q3" class="kala-ai-q-textarea" placeholder="Contoh: Menaikkan konversi leads yang masuk ke WhatsApp dan meningkatkan repeat order..." oninput="updateKalaAuditFilledCounter()"></textarea>
                </div>

                <div class="kala-ai-q-item">
                  <label class="kala-ai-q-label" for="kala-q-4">
                    <span class="kala-ai-q-num">4</span>
                    <span>Unique Selling Proposition (USP)</span>
                  </label>
                  <div class="kala-ai-q-desc">Apa alasan paling kuat kenapa orang harus beli di tempat klien ini dibanding pindah ke kompetitor sebelah?</div>
                  <textarea id="kala-q-4" data-q-key="q4" class="kala-ai-q-textarea" placeholder="Contoh: Bahan baku 100% organik bersertifikat, garansi tukar baru, pengiriman sameday..." oninput="updateKalaAuditFilledCounter()"></textarea>
                </div>
              </div>
            </div>

            <!-- BAGIAN 2: Target Audiens & Kompetitor (Market & Positioning) -->
            <div class="kala-ai-acc-card open">
              <button type="button" class="kala-ai-acc-header" onclick="toggleKalaAuditSection(this)">
                <div style="display: flex; align-items: center; gap: 10px;">
                  <span style="background: #EFF6FF; color: #1D4ED8; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 7px;">BAGIAN 2</span>
                  <span style="font-size: 13.5px; font-weight: 800;">Bagian 2: Target Audiens &amp; Kompetitor (Market &amp; Positioning)</span>
                </div>
                <span style="font-size: 12px; color: #64748B; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
                  <span>Poin 5 – 6</span>
                  <svg class="kala-ai-acc-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
              </button>
              <div class="kala-ai-acc-body">
                <div class="kala-ai-q-item">
                  <label class="kala-ai-q-label" for="kala-q-5">
                    <span class="kala-ai-q-num">5</span>
                    <span>Profil Pembeli Ideal</span>
                  </label>
                  <div class="kala-ai-q-desc">Siapa pembeli utama saat ini (rentang usia, gender, domisili, dan daya beli), serta masalah atau kebutuhan apa yang membuat mereka mencari produk ini?</div>
                  <textarea id="kala-q-5" data-q-key="q5" class="kala-ai-q-textarea" placeholder="Contoh: Wanita usia 23-38 tahun di Jabodetabek & Surabaya, SES B/A, butuh solusi..." oninput="updateKalaAuditFilledCounter()"></textarea>
                </div>

                <div class="kala-ai-q-item">
                  <label class="kala-ai-q-label" for="kala-q-6">
                    <span class="kala-ai-q-num">6</span>
                    <span>Akun Kompetitor &amp; Referensi</span>
                  </label>
                  <div class="kala-ai-q-desc">Siapa 2–3 akun kompetitor langsung di niche ini, dan 1–2 akun Instagram (boleh dari luar negeri atau niche lain) yang gaya kontennya paling ingin ditiru?</div>
                  <textarea id="kala-q-6" data-q-key="q6" class="kala-ai-q-textarea" placeholder="Contoh: Kompetitor: @kompetitorA, @kompetitorB. Referensi visual/storytelling: @referensiC..." oninput="updateKalaAuditFilledCounter()"></textarea>
                </div>
              </div>
            </div>

            <!-- BAGIAN 3: Kondisi Profil & Toko Digital (Storefront & Funnel) -->
            <div class="kala-ai-acc-card open">
              <button type="button" class="kala-ai-acc-header" onclick="toggleKalaAuditSection(this)">
                <div style="display: flex; align-items: center; gap: 10px;">
                  <span style="background: #ECFDF5; color: #047857; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 7px;">BAGIAN 3</span>
                  <span style="font-size: 13.5px; font-weight: 800;">Bagian 3: Kondisi Profil &amp; Toko Digital (Storefront &amp; Funnel)</span>
                </div>
                <span style="font-size: 12px; color: #64748B; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
                  <span>Poin 7 – 8</span>
                  <svg class="kala-ai-acc-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
              </button>
              <div class="kala-ai-acc-body">
                <div class="kala-ai-q-item">
                  <label class="kala-ai-q-label" for="kala-q-7">
                    <span class="kala-ai-q-num">7</span>
                    <span>Isi Bio &amp; Link Saat Ini</span>
                  </label>
                  <div class="kala-ai-q-desc">Apa kalimat yang tertulis di bio Instagram sekarang, dan ke mana arah link in bio saat diklik oleh pengunjung?</div>
                  <textarea id="kala-q-7" data-q-key="q7" class="kala-ai-q-textarea" placeholder="Contoh: Bio saat ini masih singkat, link in bio mengarah ke Linktree (WA & Shopee)..." oninput="updateKalaAuditFilledCounter()"></textarea>
                </div>

                <div class="kala-ai-q-item">
                  <label class="kala-ai-q-label" for="kala-q-8">
                    <span class="kala-ai-q-num">8</span>
                    <span>Pinned Posts &amp; Highlight</span>
                  </label>
                  <div class="kala-ai-q-desc">Konten apa yang sedang disematkan (pin) di baris paling atas feed, dan informasi apa saja yang ada di Story Highlights?</div>
                  <textarea id="kala-q-8" data-q-key="q8" class="kala-ai-q-textarea" placeholder="Contoh: Belum ada Pinned Post / Pin berisi katalog lama. Highlight: Testimoni, Pricelist, Lokasi..." oninput="updateKalaAuditFilledCounter()"></textarea>
                </div>
              </div>
            </div>

            <!-- BAGIAN 4: Performa & Strategi Konten (Content & Analytics) -->
            <div class="kala-ai-acc-card open">
              <button type="button" class="kala-ai-acc-header" onclick="toggleKalaAuditSection(this)">
                <div style="display: flex; align-items: center; gap: 10px;">
                  <span style="background: #FEF3C7; color: #B45309; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 7px;">BAGIAN 4</span>
                  <span style="font-size: 13.5px; font-weight: 800;">Bagian 4: Performa &amp; Strategi Konten (Content &amp; Analytics)</span>
                </div>
                <span style="font-size: 12px; color: #64748B; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
                  <span>Poin 9 – 12</span>
                  <svg class="kala-ai-acc-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
              </button>
              <div class="kala-ai-acc-body">
                <div class="kala-ai-q-item">
                  <label class="kala-ai-q-label" for="kala-q-9">
                    <span class="kala-ai-q-num">9</span>
                    <span>Statistik Saat Ini</span>
                  </label>
                  <div class="kala-ai-q-desc">Berapa jumlah followers saat ini, rata-rata views Reels, serta rata-rata reach dan interaksi (likes, comments, saves, shares) per postingan dalam 30 hari terakhir?</div>
                  <textarea id="kala-q-9" data-q-key="q9" class="kala-ai-q-textarea" placeholder="Contoh: 4.200 followers, rata-rata views Reels 600-1.500, likes 25-40, saves 5-10 per post..." oninput="updateKalaAuditFilledCounter()"></textarea>
                </div>

                <div class="kala-ai-q-item">
                  <label class="kala-ai-q-label" for="kala-q-10">
                    <span class="kala-ai-q-num">10</span>
                    <span>Frekuensi &amp; Format Posting</span>
                  </label>
                  <div class="kala-ai-q-desc">Seberapa sering posting dalam seminggu (Reels, Carousel, Single Image, dan IG Story), dan format mana yang paling sering digunakan?</div>
                  <textarea id="kala-q-10" data-q-key="q10" class="kala-ai-q-textarea" placeholder="Contoh: 2-3x seminggu kebanyakan Single Image katalog, Reels jarang (1x seminggu), Story harian..." oninput="updateKalaAuditFilledCounter()"></textarea>
                </div>

                <div class="kala-ai-q-item">
                  <label class="kala-ai-q-label" for="kala-q-11">
                    <span class="kala-ai-q-num">11</span>
                    <span>Konten Terbaik &amp; Terburuk</span>
                  </label>
                  <div class="kala-ai-q-desc">Seperti apa topik dan format dari 2 konten yang paling ramai (views atau saves tertinggi), dan seperti apa konten yang paling sepi peminat?</div>
                  <textarea id="kala-q-11" data-q-key="q11" class="kala-ai-q-textarea" placeholder="Contoh: Paling ramai: Reels behind the scene & tips edukasi (15k views). Paling sepi: poster promo hard-selling..." oninput="updateKalaAuditFilledCounter()"></textarea>
                </div>

                <div class="kala-ai-q-item">
                  <label class="kala-ai-q-label" for="kala-q-12">
                    <span class="kala-ai-q-num">12</span>
                    <span>Gaya Bahasa &amp; Wajah Brand</span>
                  </label>
                  <div class="kala-ai-q-desc">Apakah sudah ada sosok tetap (founder atau talent) yang muncul di depan kamera, dan bagaimana gaya bahasa yang dipakai sekarang (formal, santai, edukatif, atau humoris)?</div>
                  <textarea id="kala-q-12" data-q-key="q12" class="kala-ai-q-textarea" placeholder="Contoh: Belum ada talent tetap (masih voice over), gaya bahasa santai & ramah..." oninput="updateKalaAuditFilledCounter()"></textarea>
                </div>
              </div>
            </div>

            <!-- BAGIAN 5: Interaksi & Konversi (Engagement & Closing) -->
            <div class="kala-ai-acc-card open">
              <button type="button" class="kala-ai-acc-header" onclick="toggleKalaAuditSection(this)">
                <div style="display: flex; align-items: center; gap: 10px;">
                  <span style="background: #FCE7F3; color: #BE185D; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 7px;">BAGIAN 5</span>
                  <span style="font-size: 13.5px; font-weight: 800;">Bagian 5: Interaksi &amp; Konversi (Engagement &amp; Closing)</span>
                </div>
                <span style="font-size: 12px; color: #64748B; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
                  <span>Poin 13 – 14</span>
                  <svg class="kala-ai-acc-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
              </button>
              <div class="kala-ai-acc-body">
                <div class="kala-ai-q-item">
                  <label class="kala-ai-q-label" for="kala-q-13">
                    <span class="kala-ai-q-num">13</span>
                    <span>Kualitas Interaksi</span>
                  </label>
                  <div class="kala-ai-q-desc">Apakah kolom komentar dan DM sering mendapat pertanyaan soal produk/diskusi, atau masih sepi dan hanya berisi komentar singkat?</div>
                  <textarea id="kala-q-13" data-q-key="q13" class="kala-ai-q-textarea" placeholder="Contoh: Komentar masih sepi, tetapi DM lumayan sering tanya harga saat ada Reels yang naik..." oninput="updateKalaAuditFilledCounter()"></textarea>
                </div>

                <div class="kala-ai-q-item">
                  <label class="kala-ai-q-label" for="kala-q-14">
                    <span class="kala-ai-q-num">14</span>
                    <span>Alur Penanganan DM</span>
                  </label>
                  <div class="kala-ai-q-desc">Siapa yang membalas DM/komentar yang masuk, berapa lama rata-rata waktu responsnya, dan apakah sering ada calon pembeli yang bertanya harga lalu menghilang (ghosting)?</div>
                  <textarea id="kala-q-14" data-q-key="q14" class="kala-ai-q-textarea" placeholder="Contoh: Dijawab admin CS, respon 15-30 menit. Sering tanya harga lalu langsung hilang (ghosting)..." oninput="updateKalaAuditFilledCounter()"></textarea>
                </div>
              </div>
            </div>

            <!-- BAGIAN 6: Kapasitas Produksi & Hambatan (Resources & Bottlenecks) -->
            <div class="kala-ai-acc-card open">
              <button type="button" class="kala-ai-acc-header" onclick="toggleKalaAuditSection(this)">
                <div style="display: flex; align-items: center; gap: 10px;">
                  <span style="background: #FEE2E2; color: #B91C1C; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 7px;">BAGIAN 6</span>
                  <span style="font-size: 13.5px; font-weight: 800;">Bagian 6: Kapasitas Produksi &amp; Hambatan (Resources &amp; Bottlenecks)</span>
                </div>
                <span style="font-size: 12px; color: #64748B; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
                  <span>Poin 15 – 17</span>
                  <svg class="kala-ai-acc-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
              </button>
              <div class="kala-ai-acc-body">
                <div class="kala-ai-q-item">
                  <label class="kala-ai-q-label" for="kala-q-15">
                    <span class="kala-ai-q-num">15</span>
                    <span>Tim &amp; Alat Produksi</span>
                  </label>
                  <div class="kala-ai-q-desc">Siapa saja yang mengerjakan akun ini (sendiri atau ada tim editor/talent/admin), dan alat apa yang dipakai untuk syuting serta edit?</div>
                  <textarea id="kala-q-15" data-q-key="q15" class="kala-ai-q-textarea" placeholder="Contoh: Dibantu tim Kalamedia + 1 staf internal klien untuk kirim stok foto/video via iPhone..." oninput="updateKalaAuditFilledCounter()"></textarea>
                </div>

                <div class="kala-ai-q-item">
                  <label class="kala-ai-q-label" for="kala-q-16">
                    <span class="kala-ai-q-num">16</span>
                    <span>Pengalaman Iklan &amp; Kolaborasi</span>
                  </label>
                  <div class="kala-ai-q-desc">Apakah pernah menggunakan Instagram Ads (Boost Post atau Meta Ads Manager) serta bekerja sama dengan influencer/KOL, dan bagaimana hasilnya sejauh ini?</div>
                  <textarea id="kala-q-16" data-q-key="q16" class="kala-ai-q-textarea" placeholder="Contoh: Pernah Boost Post Rp 50rb/hari dapat banyak likes tapi minim chat WA. Belum pernah pakai Meta Ads Manager..." oninput="updateKalaAuditFilledCounter()"></textarea>
                </div>

                <div class="kala-ai-q-item full-width">
                  <label class="kala-ai-q-label" for="kala-q-17">
                    <span class="kala-ai-q-num">17</span>
                    <span>Kendala Terbesar Saat Ini</span>
                  </label>
                  <div class="kala-ai-q-desc">Apa 1–2 hal yang paling bikin pusing atau mentok saat mengelola akun ini (misalnya kehabisan ide, tidak pede di depan kamera, views mentok di angka ratusan, atau followers banyak tapi tidak ada yang beli)?</div>
                  <textarea id="kala-q-17" data-q-key="q17" class="kala-ai-q-textarea" placeholder="Contoh: Views Reels mentok di angka 500-800 dan calon pembeli yang DM sering ghosting setelah dikasih tahu harga..." oninput="updateKalaAuditFilledCounter()"></textarea>
                </div>
              </div>
            </div>

          </form>
        </div>

        <!-- ==============================================================
             TAB 2: HASIL ANALISIS & SKEMA STRATEGI AI
             ============================================================== -->
        <div id="kala-audit-tab-pane-2" style="display: none;">
          
          <!-- Executive Action Toolbar inside Tab 2 -->
          <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px; padding: 14px 18px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;" class="kala-ai-card">
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
              <span style="font-size: 12.5px; font-weight: 800; color: #4F46E5;">Status Publikasi Portal Klien:</span>
              <span id="kala-audit-tab2-pub-badge" style="background: #FEF3C7; color: #B45309; font-size: 11.5px; font-weight: 800; padding: 4px 10px; border-radius: 999px;">
                Draft Internal Admin
              </span>
              <span id="kala-audit-tab2-updated-at" style="font-size: 11.5px; color: #64748B;"></span>
            </div>

            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
              <button type="button" class="btn btn-primary btn-sm" id="btn-kala-generate-cal-from-audit" onclick="openCalendarFromBrandAudit()" style="background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); border: none; font-weight: 800; font-size: 12px; border-radius: 9px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(79,70,229,0.28);">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M12 14l1 2 2 1-2 1-1 2-1-2-2-1 2-1 1-2z"/></svg>
                <span>Generate Rekomendasi Kalender Konten (Kala AI)</span>
              </button>
              <button type="button" class="btn btn-secondary btn-sm" id="btn-kala-edit-analysis" onclick="toggleEditBrandAiAnalysis()" style="font-weight: 700; font-size: 12px; border-radius: 9px; display: inline-flex; align-items: center; gap: 6px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                <span>Edit Hasil Analisis</span>
              </button>
              <button type="button" class="btn btn-secondary btn-sm" id="btn-kala-download-pdf" onclick="downloadCurrentBrandAiPdf()" style="font-weight: 700; font-size: 12px; border-radius: 9px; display: inline-flex; align-items: center; gap: 6px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 18 15 15"/></svg>
                <span>Download PDF Blueprint</span>
              </button>
              <button type="button" class="btn btn-primary btn-sm" id="btn-kala-publish-client" onclick="publishBrandAiToClient(1)" style="background: #027A48; border-color: #027A48; font-weight: 800; font-size: 12px; border-radius: 9px; display: inline-flex; align-items: center; gap: 6px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                <span>Kirim &amp; Tampilkan ke Dashboard Klien</span>
              </button>
              <button type="button" class="btn btn-secondary btn-sm" id="btn-kala-unpublish-client" onclick="publishBrandAiToClient(0)" style="display: none; background: #FEF2F2; color: #B42318; border-color: #FECACA; font-weight: 800; font-size: 12px; border-radius: 9px; align-items: center; gap: 6px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                <span>Tarik dari Dashboard Klien</span>
              </button>
            </div>
          </div>

          <!-- Inline Edit Drawer (Hidden unless "Edit Hasil Analisis" is clicked) -->
          <div id="kala-audit-edit-panel" class="kala-ai-edit-drawer" style="display: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-bottom: 1px solid #E2E8F0; padding-bottom: 10px;">
              <div>
                <h4 style="margin: 0; font-size: 15px; font-weight: 800; color: #4F46E5; display: flex; align-items: center; gap: 8px;">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  <span>Editor Penyempurnaan Analisis &amp; Skema Strategi AI</span>
                </h4>
                <span style="font-size: 12px; color: #64748B;">Sesuaikan teks hasil analisis Gemini Pro sebelum dipublikasikan ke Dashboard Portal Klien.</span>
              </div>
              <button type="button" class="btn btn-secondary btn-sm" onclick="toggleEditBrandAiAnalysis(false)">Tutup Editor</button>
            </div>

            <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px;">
              <div>
                <label class="form-label" style="font-size: 11.5px; font-weight: 700;">Level / Fase Brand (`brand_stage`)</label>
                <input type="text" id="edit-ai-brand-stage" class="form-control" placeholder="Contoh: Stage 2: High Reach, Low Conversion">
              </div>
              <div>
                <label class="form-label" style="font-size: 11.5px; font-weight: 700;">Nama Skema Strategi (`recommended_scheme`)</label>
                <input type="text" id="edit-ai-scheme-name" class="form-control" placeholder="Contoh: The Trust-to-Closing Funnel Engine">
              </div>
              <div>
                <label class="form-label" style="font-size: 11.5px; font-weight: 700;">Skor Kesehatan Akun (0–100)</label>
                <input type="number" min="0" max="100" id="edit-ai-health-score" class="form-control" placeholder="75">
              </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 14px;">
              <div>
                <label class="form-label" style="font-size: 11px; font-weight: 700;">Skor Storefront (0-100)</label>
                <input type="number" min="0" max="100" id="edit-ai-sub-storefront" class="form-control">
              </div>
              <div>
                <label class="form-label" style="font-size: 11px; font-weight: 700;">Skor Konten (0-100)</label>
                <input type="number" min="0" max="100" id="edit-ai-sub-content" class="form-control">
              </div>
              <div>
                <label class="form-label" style="font-size: 11px; font-weight: 700;">Skor Closing &amp; DM (0-100)</label>
                <input type="number" min="0" max="100" id="edit-ai-sub-closing" class="form-control">
              </div>
              <div>
                <label class="form-label" style="font-size: 11px; font-weight: 700;">Skor Produksi &amp; Ads (0-100)</label>
                <input type="number" min="0" max="100" id="edit-ai-sub-ads" class="form-control">
              </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px;">
              <div>
                <label class="form-label" style="font-size: 11.5px; font-weight: 700;">Penjelasan Fase Brand</label>
                <textarea id="edit-ai-stage-explanation" class="form-control" rows="2"></textarea>
              </div>
              <div>
                <label class="form-label" style="font-size: 11.5px; font-weight: 700;">Filosofi &amp; Fokus Skema Strategi 90 Hari</label>
                <textarea id="edit-ai-scheme-philosophy" class="form-control" rows="2"></textarea>
              </div>
            </div>

            <div style="margin-bottom: 14px;">
              <label class="form-label" style="font-size: 11.5px; font-weight: 700;">Ringkasan Eksekutif Diagnosis</label>
              <textarea id="edit-ai-exec-summary" class="form-control" rows="2"></textarea>
            </div>

            <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px;">
              <div>
                <label class="form-label" style="font-size: 11.5px; font-weight: 700; color: #047857;">Kekuatan Saat Ini (1 poin per baris)</label>
                <textarea id="edit-ai-strengths" class="form-control" rows="3"></textarea>
              </div>
              <div>
                <label class="form-label" style="font-size: 11.5px; font-weight: 700; color: #B91C1C;">Titik Bocor / Bottleneck Utama (1 poin per baris)</label>
                <textarea id="edit-ai-bottlenecks" class="form-control" rows="3"></textarea>
              </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px;">
              <div>
                <label class="form-label" style="font-size: 11.5px; font-weight: 700;">Draft Bio Instagram Baru - Opsi 1 (Authority)</label>
                <textarea id="edit-ai-bio-1" class="form-control" rows="3"></textarea>
              </div>
              <div>
                <label class="form-label" style="font-size: 11.5px; font-weight: 700;">Draft Bio Instagram Baru - Opsi 2 (Conversion)</label>
                <textarea id="edit-ai-bio-2" class="form-control" rows="3"></textarea>
              </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px;">
              <div>
                <label class="form-label" style="font-size: 11.5px; font-weight: 700;">Rekomendasi Link in Bio &amp; Funnel</label>
                <textarea id="edit-ai-link-funnel" class="form-control" rows="2"></textarea>
              </div>
              <div>
                <label class="form-label" style="font-size: 11.5px; font-weight: 700;">Formula Hook 3 Detik Pertama &amp; Gaya Talent</label>
                <textarea id="edit-ai-hook-formula" class="form-control" rows="2"></textarea>
              </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px;">
              <div>
                <label class="form-label" style="font-size: 11.5px; font-weight: 700;">SOP Penanganan DM Anti-Ghosting</label>
                <textarea id="edit-ai-dm-sop" class="form-control" rows="3"></textarea>
              </div>
              <div>
                <label class="form-label" style="font-size: 11.5px; font-weight: 700;">Strategi Meta Ads &amp; KOL</label>
                <textarea id="edit-ai-ads-kol" class="form-control" rows="3"></textarea>
              </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #E2E8F0; padding-top: 12px;">
              <button type="button" class="btn btn-secondary btn-sm" onclick="toggleEditBrandAiAnalysis(false)">Batal</button>
              <button type="button" class="btn btn-primary btn-sm" id="btn-save-edited-ai-analysis" onclick="saveEditedBrandAiAnalysis()" style="background: #4F46E5; border-color: #4F46E5; font-weight: 800; display: inline-flex; align-items: center; gap: 6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                <span>Simpan Perubahan Analisis</span>
              </button>
            </div>
          </div>

          <!-- Blueprint Cards Output Container -->
          <div id="kala-audit-blueprint-container"></div>

        </div>

      </div>

      <!-- Sticky Workspace Footer -->
      <div class="kala-ai-ws-footer">
        <div style="font-size: 12px; color: #64748B; display: flex; align-items: center; gap: 8px;">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          <span>Tips: Anda bisa mencicil pengisian dan klik <b>Simpan Draft Jawaban</b> kapan saja.</span>
        </div>

        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
          <button type="button" class="btn btn-secondary" id="btn-kala-save-draft" onclick="saveBrandAiDraft()" style="font-weight: 700; font-size: 12.5px; border-radius: 10px; display: inline-flex; align-items: center; gap: 7px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            <span>Simpan Draft Jawaban</span>
          </button>
          <button type="button" class="btn btn-primary" id="btn-kala-run-gemini" onclick="runGeminiProBrandAnalysis()" style="background: linear-gradient(135deg, #4F46E5 0%, #3B82F6 100%); border: none; font-weight: 800; font-size: 12.5px; padding: 9px 18px; border-radius: 10px; box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35); display: inline-flex; align-items: center; gap: 7px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 3l1.912 5.813a2 2 0 001.275 1.275L21 12l-5.813 1.912a2 2 0 00-1.275 1.275L12 21l-1.912-5.813a2 2 0 00-1.275-1.275L3 12l5.813-1.912a2 2 0 001.275-1.275L12 3z"/></svg>
            <span>Jalankan Analisis Gemini Pro &amp; Buat Skema</span>
          </button>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
  window.currentKalaAuditState = {
    clientId: 0,
    clientData: null,
    auditMeta: null,
    questionnaire: {},
    analysis: null
  };

  function toggleKalaAuditSection(headerBtn) {
    const card = headerBtn.closest('.kala-ai-acc-card');
    if (card) card.classList.toggle('open');
  }

  function toggleAllKalaAuditSections(openState) {
    document.querySelectorAll('#modal-brand-ai-audit .kala-ai-acc-card').forEach(card => {
      if (openState) card.classList.add('open');
      else card.classList.remove('open');
    });
  }

  function switchKalaAuditWorkspaceTab(tabNum) {
    const btn1 = document.getElementById('kala-audit-tab-btn-1');
    const btn2 = document.getElementById('kala-audit-tab-btn-2');
    const pane1 = document.getElementById('kala-audit-tab-pane-1');
    const pane2 = document.getElementById('kala-audit-tab-pane-2');
    const quickTools = document.getElementById('kala-audit-tab1-quick-tools');

    if (tabNum === 2) {
      btn1.classList.remove('active');
      btn2.classList.add('active');
      pane1.style.display = 'none';
      pane2.style.display = 'block';
      if (quickTools) quickTools.style.display = 'none';
      renderCurrentKalaAuditBlueprint();
    } else {
      btn2.classList.remove('active');
      btn1.classList.add('active');
      pane2.style.display = 'none';
      pane1.style.display = 'block';
      if (quickTools) quickTools.style.display = 'flex';
    }
  }

  function collectKalaAuditQuestions() {
    const out = {};
    for (let i = 1; i <= 17; i++) {
      const el = document.getElementById('kala-q-' + i);
      out['q' + i] = el ? el.value.trim() : '';
    }
    return out;
  }

  function populateKalaAuditQuestions(qData) {
    for (let i = 1; i <= 17; i++) {
      const el = document.getElementById('kala-q-' + i);
      if (el) {
        el.value = (qData && qData['q' + i]) ? qData['q' + i] : '';
      }
    }
    updateKalaAuditFilledCounter();
  }

  function updateKalaAuditFilledCounter() {
    let filled = 0;
    for (let i = 1; i <= 17; i++) {
      const el = document.getElementById('kala-q-' + i);
      if (el && el.value.trim() !== '') filled++;
    }
    const counterEl = document.getElementById('kala-audit-filled-counter');
    if (counterEl) counterEl.innerText = `${filled}/17`;
  }

  function updateKalaAuditModalBadges(audit) {
    const statusPill = document.getElementById('kala-audit-status-pill');
    const modelBadgeText = document.getElementById('kala-audit-model-badge-text');
    const pubBadge = document.getElementById('kala-audit-tab2-pub-badge');
    const btnPub = document.getElementById('btn-kala-publish-client');
    const btnUnpub = document.getElementById('btn-kala-unpublish-client');
    const updatedInfo = document.getElementById('kala-audit-tab2-updated-at');

    const hasAnalysis = !!(audit && audit.ai_analysis && Object.keys(audit.ai_analysis).length > 0);
    const hasDraft = !!(audit && audit.questionnaire && Object.values(audit.questionnaire).some(v => String(v || '').trim() !== ''));
    const isPub = !!(audit && parseInt(audit.is_published_to_client) === 1);

    if (modelBadgeText) {
      modelBadgeText.innerText = 'Powered by ' + ((audit && audit.model_used) ? audit.model_used : 'gemini-2.5-pro');
    }

    if (isPub) {
      if (statusPill) {
        statusPill.innerText = 'Terkirim ke Klien';
        statusPill.style.background = '#ECFDF3';
        statusPill.style.color = '#027A48';
      }
      if (pubBadge) {
        pubBadge.innerText = 'Terkirim & Tampil di Dashboard Klien';
        pubBadge.style.background = '#ECFDF3';
        pubBadge.style.color = '#027A48';
      }
      if (btnPub) btnPub.style.display = 'none';
      if (btnUnpub) btnUnpub.style.display = 'inline-flex';
    } else if (hasAnalysis || hasDraft) {
      if (statusPill) {
        statusPill.innerText = 'Draft Analisis';
        statusPill.style.background = '#FEF3C7';
        statusPill.style.color = '#B45309';
      }
      if (pubBadge) {
        pubBadge.innerText = 'Draft Analisis (Hanya Admin/Owner)';
        pubBadge.style.background = '#FEF3C7';
        pubBadge.style.color = '#B45309';
      }
      if (btnPub) btnPub.style.display = 'inline-flex';
      if (btnUnpub) btnUnpub.style.display = 'none';
    } else {
      if (statusPill) {
        statusPill.innerText = 'Belum Dianalisis';
        statusPill.style.background = '#F1F5F9';
        statusPill.style.color = '#475569';
      }
      if (pubBadge) {
        pubBadge.innerText = 'Belum Dianalisis';
        pubBadge.style.background = '#F1F5F9';
        pubBadge.style.color = '#475569';
      }
      if (btnPub) btnPub.style.display = 'inline-flex';
      if (btnUnpub) btnUnpub.style.display = 'none';
    }

    if (updatedInfo) {
      updatedInfo.innerText = (audit && audit.updated_at) ? ('Terakhir diperbarui: ' + audit.updated_at) : '';
    }

    // Also update row badge in clients.php table if present
    if (window.currentKalaAuditState.clientId) {
      const rowBadge = document.getElementById('client-audit-badge-' + window.currentKalaAuditState.clientId);
      if (rowBadge) {
        rowBadge.removeAttribute('style');
        if (isPub) {
          rowBadge.className = 'kala-audit-pill published';
          rowBadge.innerHTML = '<span class="kala-audit-dot"></span><span>Terkirim ke Klien</span>';
        } else if (hasAnalysis || hasDraft) {
          rowBadge.className = 'kala-audit-pill draft';
          rowBadge.innerHTML = '<span class="kala-audit-dot"></span><span>Draft Analisis</span>';
        } else {
          rowBadge.className = 'kala-audit-pill unstarted';
          rowBadge.innerHTML = '<span class="kala-audit-dot"></span><span>Belum Dianalisis</span>';
        }
      }
    }
  }

  function renderCurrentKalaAuditBlueprint() {
    const container = document.getElementById('kala-audit-blueprint-container');
    if (!container) return;

    const analysis = window.currentKalaAuditState.analysis;
    if (!analysis || Object.keys(analysis).length === 0) {
      container.innerHTML = `
        <div class="kala-ai-card" style="text-align: center; padding: 48px 24px;">
          <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(79,70,229,0.1); color: #4F46E5; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l1.912 5.813a2 2 0 001.275 1.275L21 12l-5.813 1.912a2 2 0 00-1.275 1.275L12 21l-1.912-5.813a2 2 0 00-1.275-1.275L3 12l5.813-1.912a2 2 0 001.275-1.275L12 3z"/></svg>
          </div>
          <h4 style="font-size: 16px; font-weight: 800; margin: 0 0 6px;">Belum Ada Hasil Analisis AI untuk Klien Ini</h4>
          <p class="kala-ai-muted" style="font-size: 13px; max-width: 520px; margin: 0 auto 18px; line-height: 1.55;">
            Silakan isi kuesioner diagnostik 17 poin pada <b>Tab 1</b> (boleh dicicil sebagian), lalu klik tombol <b>"Jalankan Analisis Gemini Pro &amp; Buat Skema"</b>.
          </p>
          <button type="button" class="btn btn-primary btn-sm" onclick="switchKalaAuditWorkspaceTab(1)" style="background: #4F46E5; border-color: #4F46E5; font-weight: 700;">
            &larr; Kembali ke Tab 1 (Kuesioner Diagnostik)
          </button>
        </div>
      `;
      return;
    }

    container.innerHTML = window.renderKalaAiBlueprintCards(
      analysis,
      window.currentKalaAuditState.clientData || {},
      window.currentKalaAuditState.auditMeta || {}
    );
  }

  async function openBrandAiAuditModal(clientId, companyName) {
    window.currentKalaAuditState = {
      clientId: clientId,
      clientData: { id: clientId, company: companyName || 'Klien' },
      auditMeta: null,
      questionnaire: {},
      analysis: null
    };

    document.getElementById('kala-audit-client-id').value = clientId;
    document.getElementById('kala-audit-client-title').innerText = companyName || 'Klien';
    document.getElementById('kala-audit-last-saved-info').innerText = 'Memuat data audit...';
    document.getElementById('kala-audit-edit-panel').style.display = 'none';

    populateKalaAuditQuestions({});
    openModal('modal-brand-ai-audit');

    try {
      const res = await fetch(`api/gemini_brand_audit.php?action=get_audit&client_id=${clientId}`);
      const data = await res.json();
      if (data.success) {
        window.currentKalaAuditState.clientData = data.client || { id: clientId, company: companyName };
        window.currentKalaAuditState.auditMeta = data.audit || null;
        window.currentKalaAuditState.questionnaire = (data.audit && data.audit.questionnaire) ? data.audit.questionnaire : {};
        window.currentKalaAuditState.analysis = (data.audit && data.audit.ai_analysis) ? data.audit.ai_analysis : null;

        populateKalaAuditQuestions(window.currentKalaAuditState.questionnaire);
        updateKalaAuditModalBadges(data.audit);

        const savedInfo = document.getElementById('kala-audit-last-saved-info');
        if (savedInfo) {
          savedInfo.innerText = (data.audit && data.audit.updated_at)
            ? ('Draft tersimpan: ' + data.audit.updated_at)
            : 'Siap diisi (Bisa dicicil per bagian)';
        }

        if (window.currentKalaAuditState.analysis && Object.keys(window.currentKalaAuditState.analysis).length > 0) {
          switchKalaAuditWorkspaceTab(2);
        } else {
          switchKalaAuditWorkspaceTab(1);
        }
      } else {
        showToast(data.message || 'Gagal memuat data audit klien', 'danger');
      }
    } catch (err) {
      showToast('Gagal menghubungi server audit AI', 'danger');
    }
  }

  async function saveBrandAiDraft() {
    const clientId = window.currentKalaAuditState.clientId;
    if (!clientId) return;

    const btn = document.getElementById('btn-kala-save-draft');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span>Menyimpan Draft...</span>';

    const questionnaire = collectKalaAuditQuestions();

    try {
      const res = await fetch('api/gemini_brand_audit.php?action=save_draft', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          client_id: clientId,
          questionnaire: questionnaire
        })
      });
      const data = await res.json();
      if (data.success) {
        window.currentKalaAuditState.questionnaire = questionnaire;
        if (!window.currentKalaAuditState.auditMeta) {
          window.currentKalaAuditState.auditMeta = { questionnaire: questionnaire, is_published_to_client: 0 };
        } else {
          window.currentKalaAuditState.auditMeta.questionnaire = questionnaire;
        }
        updateKalaAuditModalBadges(window.currentKalaAuditState.auditMeta);
        const savedInfo = document.getElementById('kala-audit-last-saved-info');
        if (savedInfo) savedInfo.innerText = 'Draft disimpan barusan';
        showToast(data.message || 'Draft jawaban berhasil disimpan!', 'success');
      } else {
        showToast(data.message || 'Gagal menyimpan draft', 'danger');
      }
    } catch (err) {
      showToast('Gagal menyimpan draft ke server', 'danger');
    } finally {
      btn.disabled = false;
      btn.innerHTML = origHtml;
    }
  }

  async function runGeminiProBrandAnalysis() {
    const clientId = window.currentKalaAuditState.clientId;
    if (!clientId) return;

    const questionnaire = collectKalaAuditQuestions();
    const filledCount = Object.values(questionnaire).filter(v => v !== '').length;
    if (filledCount === 0) {
      showToast('Silakan isi minimal beberapa poin diagnostik sebelum menjalankan analisis Gemini Pro.', 'warning');
      return;
    }

    const btn = document.getElementById('btn-kala-run-gemini');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span>Menganalisis Brand dengan Gemini Pro (15–40 dtk)...</span>';

    try {
      const res = await fetch('api/gemini_brand_audit.php?action=run_analysis', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          client_id: clientId,
          questionnaire: questionnaire
        })
      });
      const data = await res.json();
      if (data.success) {
        window.currentKalaAuditState.questionnaire = questionnaire;
        window.currentKalaAuditState.analysis = data.analysis;
        window.currentKalaAuditState.auditMeta = Object.assign({}, window.currentKalaAuditState.auditMeta || {}, {
          model_used: data.model_used || 'gemini-2.5-pro',
          brand_stage: data.brand_stage,
          health_score: data.health_score,
          recommended_scheme_name: data.recommended_scheme_name,
          ai_analysis: data.analysis,
          questionnaire: questionnaire,
          updated_at: 'Baru saja'
        });

        updateKalaAuditModalBadges(window.currentKalaAuditState.auditMeta);
        switchKalaAuditWorkspaceTab(2);
        showToast(data.message || 'Analisis Gemini Pro selesai!', 'success');
      } else {
        showToast(data.message || 'Gagal menjalankan analisis Gemini Pro', 'danger');
      }
    } catch (err) {
      showToast('Koneksi timeout atau gagal saat menghubungi Gemini Pro API', 'danger');
    } finally {
      btn.disabled = false;
      btn.innerHTML = origHtml;
    }
  }

  function toggleEditBrandAiAnalysis(forceState) {
    const panel = document.getElementById('kala-audit-edit-panel');
    if (!panel) return;

    const show = (typeof forceState === 'boolean') ? forceState : (panel.style.display === 'none');
    if (!show) {
      panel.style.display = 'none';
      return;
    }

    const a = window.currentKalaAuditState.analysis;
    if (!a) {
      showToast('Belum ada hasil analisis untuk diedit.', 'warning');
      return;
    }

    const stageObj = (typeof a.brand_stage === 'object' && a.brand_stage !== null) ? a.brand_stage : { stage_name: a.brand_stage || '', explanation: a.brand_stage_reason || '' };
    const hsObj = (typeof a.health_score === 'object' && a.health_score !== null) ? a.health_score : { overall_score: parseInt(a.health_score) || 0 };
    const schemeObj = (typeof a.recommended_scheme === 'object' && a.recommended_scheme !== null) ? a.recommended_scheme : { scheme_name: a.recommended_scheme || '', philosophy: '' };
    const diagObj = a.executive_diagnosis || {};
    const sfObj = a.storefront_revamp || {};
    const csObj = a.content_strategy || {};
    const closeObj = a.closing_and_ads_blueprint || {};

    document.getElementById('edit-ai-brand-stage').value = stageObj.stage_name || '';
    document.getElementById('edit-ai-stage-explanation').value = stageObj.explanation || '';
    document.getElementById('edit-ai-scheme-name').value = schemeObj.scheme_name || '';
    document.getElementById('edit-ai-scheme-philosophy').value = schemeObj.philosophy || '';
    document.getElementById('edit-ai-health-score').value = hsObj.overall_score ?? hsObj.overall ?? 70;
    document.getElementById('edit-ai-sub-storefront').value = hsObj.storefront_score ?? 70;
    document.getElementById('edit-ai-sub-content').value = hsObj.content_score ?? 70;
    document.getElementById('edit-ai-sub-closing').value = hsObj.engagement_closing_score ?? 70;
    document.getElementById('edit-ai-sub-ads').value = hsObj.production_ads_score ?? 70;

    document.getElementById('edit-ai-exec-summary').value = diagObj.summary || diagObj.overview || '';
    document.getElementById('edit-ai-strengths').value = Array.isArray(diagObj.strengths) ? diagObj.strengths.join('\n') : (diagObj.strengths || '');
    document.getElementById('edit-ai-bottlenecks').value = Array.isArray(diagObj.core_bottlenecks) ? diagObj.core_bottlenecks.join('\n') : (diagObj.core_bottlenecks || '');

    document.getElementById('edit-ai-bio-1').value = sfObj.bio_option_1 || sfObj.new_bio_draft || (Array.isArray(sfObj.new_bio_lines) ? sfObj.new_bio_lines.join('\n') : '');
    document.getElementById('edit-ai-bio-2').value = sfObj.bio_option_2 || '';
    document.getElementById('edit-ai-link-funnel').value = sfObj.link_in_bio_funnel || sfObj.link_and_funnel || '';
    document.getElementById('edit-ai-hook-formula').value = csObj.hook_formula_and_persona || (csObj.hook_and_persona && csObj.hook_and_persona.persona_and_tone) || '';

    const dmSop = closeObj.dm_anti_ghosting_sop || closeObj.dm_sop_script;
    document.getElementById('edit-ai-dm-sop').value = (typeof dmSop === 'object' && dmSop !== null)
      ? (dmSop.flow_steps ? (Array.isArray(dmSop.flow_steps) ? dmSop.flow_steps.join('\n') : dmSop.flow_steps) : (dmSop.step_1_greeting_diagnose || ''))
      : (dmSop || '');
    const adsKol = closeObj.meta_ads_and_kol_strategy || closeObj.ads_and_kol_strategy;
    document.getElementById('edit-ai-ads-kol').value = (typeof adsKol === 'object' && adsKol !== null)
      ? (adsKol.meta_ads_funnel || '')
      : (adsKol || '');

    panel.style.display = 'block';
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  async function saveEditedBrandAiAnalysis() {
    const clientId = window.currentKalaAuditState.clientId;
    const a = JSON.parse(JSON.stringify(window.currentKalaAuditState.analysis || {}));

    a.brand_stage = {
      stage_name: document.getElementById('edit-ai-brand-stage').value.trim(),
      explanation: document.getElementById('edit-ai-stage-explanation').value.trim()
    };
    a.recommended_scheme = {
      scheme_name: document.getElementById('edit-ai-scheme-name').value.trim(),
      philosophy: document.getElementById('edit-ai-scheme-philosophy').value.trim()
    };
    a.health_score = {
      overall_score: parseInt(document.getElementById('edit-ai-health-score').value) || 0,
      overall: parseInt(document.getElementById('edit-ai-health-score').value) || 0,
      storefront_score: parseInt(document.getElementById('edit-ai-sub-storefront').value) || 0,
      content_score: parseInt(document.getElementById('edit-ai-sub-content').value) || 0,
      engagement_closing_score: parseInt(document.getElementById('edit-ai-sub-closing').value) || 0,
      production_ads_score: parseInt(document.getElementById('edit-ai-sub-ads').value) || 0
    };

    a.executive_diagnosis = a.executive_diagnosis || {};
    a.executive_diagnosis.summary = document.getElementById('edit-ai-exec-summary').value.trim();
    a.executive_diagnosis.overview = a.executive_diagnosis.summary;
    a.executive_diagnosis.strengths = document.getElementById('edit-ai-strengths').value.split('\n').map(s => s.trim()).filter(Boolean);
    a.executive_diagnosis.core_bottlenecks = document.getElementById('edit-ai-bottlenecks').value.split('\n').map(s => s.trim()).filter(Boolean);

    a.storefront_revamp = a.storefront_revamp || {};
    a.storefront_revamp.bio_option_1 = document.getElementById('edit-ai-bio-1').value.trim();
    a.storefront_revamp.bio_option_2 = document.getElementById('edit-ai-bio-2').value.trim();
    a.storefront_revamp.link_in_bio_funnel = document.getElementById('edit-ai-link-funnel').value.trim();

    a.content_strategy = a.content_strategy || {};
    a.content_strategy.hook_formula_and_persona = document.getElementById('edit-ai-hook-formula').value.trim();

    a.closing_and_ads_blueprint = a.closing_and_ads_blueprint || {};
    if (typeof a.closing_and_ads_blueprint.dm_anti_ghosting_sop === 'object' && a.closing_and_ads_blueprint.dm_anti_ghosting_sop !== null) {
      a.closing_and_ads_blueprint.dm_anti_ghosting_sop.flow_steps = document.getElementById('edit-ai-dm-sop').value.split('\n').map(s => s.trim()).filter(Boolean);
    } else {
      a.closing_and_ads_blueprint.dm_anti_ghosting_sop = document.getElementById('edit-ai-dm-sop').value.trim();
    }
    a.closing_and_ads_blueprint.meta_ads_and_kol_strategy = document.getElementById('edit-ai-ads-kol').value.trim();

    const btn = document.getElementById('btn-save-edited-ai-analysis');
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span>Menyimpan...</span>';

    try {
      const res = await fetch('api/gemini_brand_audit.php?action=save_edited_analysis', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          client_id: clientId,
          analysis: a
        })
      });
      const data = await res.json();
      if (data.success) {
        window.currentKalaAuditState.analysis = a;
        toggleEditBrandAiAnalysis(false);
        renderCurrentKalaAuditBlueprint();
        showToast(data.message || 'Perubahan hasil analisis berhasil disimpan!', 'success');
      } else {
        showToast(data.message || 'Gagal menyimpan perubahan', 'danger');
      }
    } catch (err) {
      showToast('Gagal menyimpan perubahan ke server', 'danger');
    } finally {
      btn.disabled = false;
      btn.innerHTML = origText;
    }
  }

  async function publishBrandAiToClient(publishFlag) {
    const clientId = window.currentKalaAuditState.clientId;
    if (!clientId) return;

    if (!window.currentKalaAuditState.analysis || Object.keys(window.currentKalaAuditState.analysis).length === 0) {
      showToast('Jalankan analisis Gemini Pro terlebih dahulu sebelum mengirim ke Dashboard Klien.', 'warning');
      return;
    }

    try {
      const res = await fetch('api/gemini_brand_audit.php?action=toggle_publish', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          client_id: clientId,
          publish: publishFlag
        })
      });
      const data = await res.json();
      if (data.success) {
        if (!window.currentKalaAuditState.auditMeta) window.currentKalaAuditState.auditMeta = {};
        window.currentKalaAuditState.auditMeta.is_published_to_client = data.is_published_to_client;
        window.currentKalaAuditState.auditMeta.published_at = data.published_at;
        updateKalaAuditModalBadges(window.currentKalaAuditState.auditMeta);
        showToast(data.message, 'success');
      } else {
        showToast(data.message || 'Gagal memperbarui status publikasi', 'danger');
      }
    } catch (err) {
      showToast('Koneksi ke server gagal', 'danger');
    }
  }

  function downloadCurrentBrandAiPdf() {
    if (!window.currentKalaAuditState.analysis) {
      showToast('Belum ada hasil analisis untuk diunduh sebagai PDF.', 'warning');
      return;
    }
    window.downloadKalaAiBlueprintPdf(
      window.currentKalaAuditState.analysis,
      window.currentKalaAuditState.clientData || {},
      window.currentKalaAuditState.auditMeta || {}
    );
  }

  function openCalendarFromBrandAudit() {
    const clientId = document.getElementById('kala-audit-client-id')?.value || window.currentKalaAuditState?.clientId;
    const companyName = window.currentKalaAuditState?.clientData?.company || '';
    if (typeof openKalaAiCalendarModal === 'function') {
      openKalaAiCalendarModal(clientId, companyName);
    }
  }
</script>
<?php require_once __DIR__ . '/kala_ai_calendar_modal.php'; ?>

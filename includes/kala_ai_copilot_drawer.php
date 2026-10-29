<?php
/**
 * Kala AI Owner Executive Copilot — Slide-Over Chat Drawer & Floating Trigger
 * Exclusive to `owner` and `admin` roles.
 * Strict Rule: Zero Emojis (SVG icons only), Full Light Mode & NebulaPay Dark Mode support.
 */
$copilotCurrentRole = function_exists('getUserRole') ? getUserRole() : ($_SESSION['user_role'] ?? $_SESSION['role'] ?? '');
if (!in_array($copilotCurrentRole, ['owner', 'admin'], true)) {
    return;
}
?>
<style>
/* Topbar AI Trigger Button & Mobile Header Harmony */
.kala-topbar-ai-btn {
    height: 34px;
    padding: 0 13px;
    border-radius: 999px;
    background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
    color: #ffffff !important;
    font-size: 12px;
    font-weight: 700;
    border: 1px solid rgba(255, 255, 255, 0.18);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);
    white-space: nowrap;
    flex-shrink: 0;
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}
.kala-topbar-ai-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(79, 70, 229, 0.4);
}
.kala-ai-label-short {
    display: none;
}

/* Floating Trigger Button */
.kala-copilot-fab {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 1045;
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 11px 18px;
    border-radius: 999px;
    background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
    color: #ffffff !important;
    font-size: 13px;
    font-weight: 800;
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 12px 28px -6px rgba(79, 70, 229, 0.55);
    cursor: pointer;
    transition: transform 0.2s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.2s ease;
}
.kala-copilot-fab:hover {
    transform: translateY(-2px) scale(1.02);
    box-shadow: 0 16px 34px -6px rgba(79, 70, 229, 0.7);
}
@media (max-width: 768px) {
    .kala-ai-label-full {
        display: none !important;
    }
    .kala-ai-label-short {
        display: inline !important;
    }
    .kala-topbar-ai-btn {
        height: 32px !important;
        padding: 0 11px !important;
        font-size: 11.5px !important;
        gap: 5px !important;
    }
    .kala-exec-badge-top {
        display: none !important;
    }
    .kala-copilot-fab {
        display: none !important;
    }
}

/* Active Copilot Screen Lock & Clean Mobile Floating State */
body.kala-copilot-active {
    overflow: hidden !important;
}
body.kala-copilot-active #btn-floating-room-chat {
    display: none !important;
}
body.kala-copilot-active .mobile-bottom-nav {
    display: none !important;
}

/* Slide-Over Backdrop & Drawer */
.kala-copilot-backdrop {
    position: fixed !important;
    inset: 0 !important;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 100050 !important;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.24s ease;
}
.kala-copilot-backdrop.open {
    opacity: 1 !important;
    pointer-events: auto !important;
}
.kala-copilot-drawer {
    position: fixed !important;
    top: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    height: 100vh !important;
    height: 100dvh !important;
    max-height: 100dvh !important;
    width: 100% !important;
    max-width: 470px !important;
    z-index: 100060 !important;
    display: flex !important;
    flex-direction: column !important;
    background: var(--bg-card, #ffffff);
    border-left: 1px solid var(--border-light, #E2E8F0);
    box-shadow: -20px 0 50px rgba(15, 23, 42, 0.25);
    transform: translateX(102%);
    transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1);
    overflow: hidden !important;
}
@media (max-width: 768px) {
    .kala-copilot-drawer {
        left: 0 !important;
        right: 0 !important;
        top: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        max-width: 100vw !important;
        height: 100dvh !important;
        max-height: 100dvh !important;
        border-left: none !important;
        border-radius: 0 !important;
    }
}
html[data-theme="dark"] .kala-copilot-drawer,
body.dark-mode .kala-copilot-drawer {
    background: #0D111A;
    border-left-color: rgba(255, 255, 255, 0.1);
    box-shadow: -20px 0 60px rgba(0, 0, 0, 0.75);
}
.kala-copilot-drawer.open {
    transform: translateX(0) !important;
}

.kcp-header {
    flex-shrink: 0 !important;
    padding: 16px 20px;
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.09) 0%, rgba(124, 58, 237, 0.09) 100%);
    border-bottom: 1px solid var(--border-light, #E2E8F0);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
html[data-theme="dark"] .kcp-header,
body.dark-mode .kcp-header {
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.18) 0%, rgba(124, 58, 237, 0.14) 100%);
    border-bottom-color: rgba(255, 255, 255, 0.08);
}

.kcp-messages {
    flex: 1 1 0 !important;
    min-height: 0 !important;
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch !important;
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    background: var(--bg-main, #F8FAFC);
}
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    background: var(--bg-main, #F8FAFC);
}
html[data-theme="dark"] .kcp-messages,
body.dark-mode .kcp-messages {
    background: #080B12;
}

.kcp-bubble-ai {
    align-self: flex-start;
    max-width: 92%;
    background: var(--bg-card, #ffffff);
    border: 1px solid var(--border-light, #E2E8F0);
    border-radius: 14px 14px 14px 4px;
    padding: 13px 15px;
    font-size: 12.8px;
    line-height: 1.6;
    color: var(--text-primary, #0F172A);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}
html[data-theme="dark"] .kcp-bubble-ai,
body.dark-mode .kcp-bubble-ai {
    background: #131926;
    border-color: rgba(255, 255, 255, 0.08);
    color: #F8FAFC;
}

.kcp-bubble-user {
    align-self: flex-end;
    max-width: 85%;
    background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
    color: #ffffff;
    border-radius: 14px 14px 4px 14px;
    padding: 11px 15px;
    font-size: 12.8px;
    font-weight: 600;
    line-height: 1.5;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
}

.kcp-chips-wrap {
    flex-shrink: 0 !important;
    padding: 10px 16px;
    border-top: 1px solid var(--border-light, #E2E8F0);
    background: var(--bg-card, #ffffff);
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
html[data-theme="dark"] .kcp-chips-wrap,
body.dark-mode .kcp-chips-wrap {
    background: #0D111A;
    border-top-color: rgba(255, 255, 255, 0.08);
}

.kcp-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 11.2px;
    font-weight: 700;
    background: rgba(79, 70, 229, 0.08);
    color: #4F46E5;
    border: 1px solid rgba(79, 70, 229, 0.22);
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
    transition: all 0.15s ease;
}
@media (max-width: 768px) {
    .kcp-header {
        padding: 13px 16px !important;
        flex-shrink: 0 !important;
    }
    .kcp-messages {
        padding: 14px 14px !important;
        flex: 1 1 0 !important;
        min-height: 0 !important;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch !important;
    }
    .kcp-chips-wrap {
        flex-shrink: 0 !important;
        flex-wrap: nowrap !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
        scrollbar-width: none !important;
        padding: 8px 14px !important;
    }
    .kcp-chips-wrap::-webkit-scrollbar {
        display: none !important;
    }
    .kcp-input-bar {
        flex-shrink: 0 !important;
        padding: 10px 14px calc(12px + env(safe-area-inset-bottom, 0px)) 14px !important;
        box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.08) !important;
    }
    .kcp-input {
        height: 42px !important;
        font-size: 13px !important;
    }
}
html[data-theme="dark"] .kcp-chip,
body.dark-mode .kcp-chip {
    background: rgba(99, 102, 241, 0.14);
    color: #A5B4FC;
    border-color: rgba(99, 102, 241, 0.3);
}
.kcp-chip:hover {
    background: #4F46E5;
    color: #ffffff !important;
    border-color: #4F46E5;
}

.kcp-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 11px;
    border-radius: 8px;
    font-size: 11.5px;
    font-weight: 700;
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.28);
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s ease;
}
html[data-theme="dark"] .kcp-action-btn,
body.dark-mode .kcp-action-btn {
    background: rgba(16, 185, 129, 0.14);
    color: #34D399;
    border-color: rgba(16, 185, 129, 0.3);
}
.kcp-action-btn:hover {
    background: #059669;
    color: #ffffff !important;
}

.kcp-input-bar {
    flex-shrink: 0 !important;
    padding: 12px 16px 16px 16px;
    background: var(--bg-card, #ffffff);
    border-top: 1px solid var(--border-light, #E2E8F0);
    display: flex;
    align-items: center;
    gap: 8px;
}
html[data-theme="dark"] .kcp-input-bar,
body.dark-mode .kcp-input-bar {
    background: #0D111A;
    border-top-color: rgba(255, 255, 255, 0.08);
}
.kcp-input {
    flex: 1;
    height: 40px;
    padding: 0 13px;
    border-radius: 10px;
    border: 1px solid var(--border-light, #CBD5E1);
    background: var(--bg-main, #F8FAFC);
    color: var(--text-primary, #0F172A);
    font-size: 12.8px;
    font-family: inherit;
}
html[data-theme="dark"] .kcp-input,
body.dark-mode .kcp-input {
    background: #131926;
    border-color: rgba(255, 255, 255, 0.12);
    color: #F8FAFC;
}
.kcp-input:focus {
    outline: none;
    border-color: #6366F1;
}
</style>

<!-- Floating Bottom-Right Button -->
<button type="button" class="kala-copilot-fab" onclick="openKalaOwnerCopilot()" title="Tanya Kala AI (Asisten Eksekutif Owner)">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
        <path d="M12 2L14.8 8.5L22 9.3L16.5 14L18.2 21L12 17.3L5.8 21L7.5 14L2 9.3L9.2 8.5L12 2Z"></path>
    </svg>
    <span>Tanya Kala AI</span>
</button>

<!-- Slide-Over Backdrop -->
<div class="kala-copilot-backdrop" id="kala-copilot-backdrop" onclick="closeKalaOwnerCopilot()"></div>

<!-- Slide-Over Copilot Drawer -->
<aside class="kala-copilot-drawer" id="kala-copilot-drawer" aria-label="Asisten AI Pribadi Owner">
    <div class="kcp-header">
        <div style="display:flex; align-items:center; gap:10px;">
            <div style="width:38px; height:38px; border-radius:11px; background:linear-gradient(135deg, #4F46E5, #7C3AED); color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0; box-shadow:0 4px 12px rgba(79,70,229,0.35);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path d="M12 2L14.8 8.5L22 9.3L16.5 14L18.2 21L12 17.3L5.8 21L7.5 14L2 9.3L9.2 8.5L12 2Z"></path>
                </svg>
            </div>
            <div>
                <div style="display:flex; align-items:center; gap:6px;">
                    <h3 style="margin:0; font-size:14.5px; font-weight:800; color:var(--text-primary);">Kala AI Copilot</h3>
                    <span style="font-size:9.5px; font-weight:800; padding:2px 7px; border-radius:999px; background:rgba(16,185,129,0.14); color:#10B981;">LIVE DB SYNC</span>
                </div>
                <div style="font-size:11.5px; color:var(--text-muted);">Asisten Eksekutif Pribadi Owner &amp; Admin</div>
            </div>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeKalaOwnerCopilot()" style="padding:6px 10px; border-radius:8px; display:inline-flex; align-items:center; gap:4px; font-size:11.5px;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            Tutup
        </button>
    </div>

    <!-- Chat Messages Area -->
    <div class="kcp-messages" id="kcp-messages-list">
        <div class="kcp-bubble-ai">
            <div style="display:flex; align-items:center; gap:6px; font-size:11px; font-weight:800; color:#6366F1; margin-bottom:6px; text-transform:uppercase;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M12 2L14.8 8.5L22 9.3L16.5 14L18.2 21L12 17.3L5.8 21L7.5 14L2 9.3L9.2 8.5L12 2Z"/></svg>
                <span>Kala AI Executive Copilot</span>
            </div>
            Halo Owner, saya terhubung langsung secara real-time dengan database Keuangan, Invoice, Klien, Tugas Tim, Kalender Konten, dan Saldo Ads Kalamedia.<br><br>
            Silakan klik salah satu tombol pintasan di bawah atau ketik pertanyaan Anda:
            <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:10px;">
                <button type="button" class="kcp-action-btn" onclick="triggerCopilotSmartAction('create_invoice')">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                    <span>Buat Invoice Sekarang</span>
                </button>
                <button type="button" class="kcp-action-btn" onclick="triggerCopilotSmartAction('open_monthly_report')">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    <span>Buka &amp; Review Report Bulanan Klien</span>
                </button>
                <button type="button" class="kcp-action-btn" onclick="triggerCopilotSmartAction('open_ai_calendar')">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span>Buka Rekomendasi Kalender Konten</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Quick Prompt Chips -->
    <div class="kcp-chips-wrap">
        <button type="button" class="kcp-chip" onclick="sendCopilotQuickPrompt('Cek Status Invoice Klien: Klien mana saja yang belum dibuatkan invoice bulan ini atau masih berstatus Unpaid/Overdue?')">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
            <span>Cek Status Invoice Klien</span>
        </button>
        <button type="button" class="kcp-chip" onclick="sendCopilotQuickPrompt('Ringkasan Perkembangan Klien Bulan Ini: Bagaimana kondisi seluruh klien aktif, status Audit AI, Kalender Konten, dan Laporan Bulanan mereka?')">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            <span>Ringkasan Perkembangan Klien Bulan Ini</span>
        </button>
        <button type="button" class="kcp-chip" onclick="sendCopilotQuickPrompt('Cek Tugas Tim & Revisi: Tugas mana saja yang sedang berstatus revisi, macet, atau melewati tenggat waktu beserta nama PIC-nya?')">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            <span>Cek Tugas Tim &amp; Revisi</span>
        </button>
        <button type="button" class="kcp-chip" onclick="sendCopilotQuickPrompt('Cek Sisa Saldo Ads: Bagaimana rincian pagu anggaran iklan, spend terpakai, dan sisa saldo ads masing-masing klien saat ini?')">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            <span>Cek Sisa Saldo Ads</span>
        </button>
    </div>

    <!-- Input Bar -->
    <form class="kcp-input-bar" onsubmit="event.preventDefault(); submitKalaCopilotQuestion();">
        <input type="text" id="kcp-question-input" class="kcp-input" placeholder="Tanyakan status invoice, perkembangan klien, revisi tim, saldo ads..." autocomplete="off">
        <button type="submit" id="btn-kcp-send" class="btn btn-primary" style="height:40px; padding:0 15px; border-radius:10px; background:linear-gradient(135deg, #4F46E5, #7C3AED); border:none; display:inline-flex; align-items:center; justify-content:center; gap:6px; font-weight:700; font-size:12.5px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            <span>Kirim</span>
        </button>
    </form>
</aside>

<script>
window.kalaCopilotHistory = [];

function ensureCopilotMountedToBody() {
    try {
        const backdrop = document.getElementById('kala-copilot-backdrop');
        const drawer = document.getElementById('kala-copilot-drawer');
        if (backdrop && backdrop.parentElement !== document.body) {
            document.body.appendChild(backdrop);
        }
        if (drawer && drawer.parentElement !== document.body) {
            document.body.appendChild(drawer);
        }
    } catch (e) {
        // silent fail safe
    }
}
document.addEventListener('DOMContentLoaded', ensureCopilotMountedToBody);

function openKalaOwnerCopilot() {
    ensureCopilotMountedToBody();
    document.body.classList.add('kala-copilot-active');
    document.getElementById('kala-copilot-backdrop')?.classList.add('open');
    document.getElementById('kala-copilot-drawer')?.classList.add('open');
    setTimeout(() => {
        document.getElementById('kcp-question-input')?.focus();
    }, 180);
}

function closeKalaOwnerCopilot() {
    document.body.classList.remove('kala-copilot-active');
    document.getElementById('kala-copilot-backdrop')?.classList.remove('open');
    document.getElementById('kala-copilot-drawer')?.classList.remove('open');
}

function sendCopilotQuickPrompt(promptText) {
    const input = document.getElementById('kcp-question-input');
    if (input) input.value = promptText;
    submitKalaCopilotQuestion();
}

function escapeHtmlCopilot(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function formatCopilotReplyHtml(text) {
    return escapeHtmlCopilot(text)
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\n/g, '<br>');
}

function triggerCopilotSmartAction(actionType, clientId) {
    closeKalaOwnerCopilot();
    if (actionType === 'create_invoice') {
        const modalInv = document.getElementById('modal-create-invoice');
        if (modalInv && typeof openModal === 'function') {
            openModal('modal-create-invoice');
        } else {
            window.location.href = '<?= url("invoices") ?>';
        }
    } else if (actionType === 'open_monthly_report') {
        const panel = document.getElementById('kala-monthly-ai-panel');
        if (panel) {
            if (clientId) {
                const sel = document.getElementById('kamr-client-select');
                if (sel) { sel.value = String(clientId); loadMonthlyAiReportsList(); }
            }
            panel.scrollIntoView({ behavior: 'smooth' });
        } else {
            window.location.href = '<?= url("reports") ?>' + (clientId ? ('?client_id=' + encodeURIComponent(clientId)) : '');
        }
    } else if (actionType === 'open_ai_calendar') {
        if (typeof openKalaAiCalendarModal === 'function') {
            openKalaAiCalendarModal(clientId || '');
        } else {
            window.location.href = '<?= url("content-calendar") ?>';
        }
    } else if (actionType === 'open_brand_audit') {
        if (typeof openBrandAiAuditModal === 'function' && clientId) {
            openBrandAiAuditModal(clientId);
        } else {
            window.location.href = '<?= url("clients") ?>';
        }
    } else if (actionType === 'open_tasks') {
        window.location.href = '<?= url("tasks") ?>';
    }
}

async function submitKalaCopilotQuestion() {
    const input = document.getElementById('kcp-question-input');
    const msgList = document.getElementById('kcp-messages-list');
    const btnSend = document.getElementById('btn-kcp-send');
    if (!input || !msgList) return;

    const question = input.value.trim();
    if (!question) return;
    input.value = '';

    // Render User Bubble
    const userDiv = document.createElement('div');
    userDiv.className = 'kcp-bubble-user';
    userDiv.textContent = question;
    msgList.appendChild(userDiv);

    // Render Typing Bubble
    const loadingDiv = document.createElement('div');
    loadingDiv.className = 'kcp-bubble-ai';
    loadingDiv.innerHTML = `<span style="color:var(--text-muted); font-size:12px;">Menganalisis data real-time agensi...</span>`;
    msgList.appendChild(loadingDiv);
    msgList.scrollTop = msgList.scrollHeight;

    btnSend.disabled = true;

    try {
        const res = await fetch('<?= BASE_URL ?>/api/gemini_owner_assistant.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'ask',
                question: question,
                history: window.kalaCopilotHistory
            })
        });
        const json = await res.json();
        btnSend.disabled = false;

        if (!json.success) {
            loadingDiv.innerHTML = `<span style="color:#EF4444; font-weight:600;">${escapeHtmlCopilot(json.message || 'Gagal memproses pertanyaan.')}</span>`;
            return;
        }

        window.kalaCopilotHistory.push({ role: 'user', content: question });
        window.kalaCopilotHistory.push({ role: 'assistant', content: json.reply });

        const highlightsHtml = (json.highlights || []).map(h => `
            <div style="font-size:11.5px; font-weight:700; padding:4px 9px; border-radius:6px; background:rgba(79,70,229,0.08); color:#6366F1; margin-top:6px;">
                • ${escapeHtmlCopilot(h)}
            </div>
        `).join('');

        const actions = (json.suggested_actions && json.suggested_actions.length) ? json.suggested_actions : [
            { action_type: 'create_invoice', label: 'Buat Invoice Sekarang', client_id: 0 },
            { action_type: 'open_monthly_report', label: 'Buka & Review Report Bulanan Klien', client_id: 0 },
            { action_type: 'open_ai_calendar', label: 'Buka Rekomendasi Kalender Konten', client_id: 0 }
        ];

        const actionsHtml = actions.map(act => `
            <button type="button" class="kcp-action-btn" onclick="triggerCopilotSmartAction('${escapeHtmlCopilot(act.action_type)}', ${parseInt(act.client_id || 0, 10)})">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><polyline points="9 18 15 12 9 6"/></svg>
                <span>${escapeHtmlCopilot(act.label)}</span>
            </button>
        `).join('');

        loadingDiv.innerHTML = `
            <div style="display:flex; align-items:center; justify-content:space-between; font-size:10.5px; font-weight:800; color:#6366F1; margin-bottom:6px; text-transform:uppercase;">
                <span>Kala AI Copilot</span>
                <span style="color:var(--text-muted); font-weight:600;">${escapeHtmlCopilot(json.model_used || 'Gemini Pro')}</span>
            </div>
            <div>${formatCopilotReplyHtml(json.reply)}</div>
            ${highlightsHtml ? `<div style="margin-top:8px;">${highlightsHtml}</div>` : ''}
            <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:12px; padding-top:10px; border-top:1px dashed var(--border-light, rgba(148,163,184,0.2));">
                ${actionsHtml}
            </div>
        `;
        msgList.scrollTop = msgList.scrollHeight;
    } catch (err) {
        btnSend.disabled = false;
        loadingDiv.innerHTML = `<span style="color:#EF4444;">Terjadi kesalahan koneksi saat menghubungi Kala AI Copilot.</span>`;
    }
}
</script>


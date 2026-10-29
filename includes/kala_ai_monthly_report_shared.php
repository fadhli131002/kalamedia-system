<?php
/**
 * Shared UI Renderer & PDF Exporter for Kala AI Monthly Client Reports
 * Used by `views/reports.php` (Owner Review & Approval) and `views/client_portal.php` (Client Portal).
 * Strict Rule: Zero Emojis (SVG icons only), Full Light Mode & NebulaPay Dark Mode Support, Client-Safe Metrics.
 */
?>
<style>
.kamr-card {
    background: var(--bg-card, #ffffff);
    border: 1px solid var(--border-light, #E2E8F0);
    border-radius: 16px;
    padding: 20px 22px;
    margin-bottom: 16px;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
}
html[data-theme="dark"] .kamr-card,
body.dark-mode .kamr-card {
    background: #121826;
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
}
.kamr-section-box {
    background: var(--bg-main, #F8FAFC);
    border: 1px solid var(--border-light, #E2E8F0);
    border-radius: 14px;
    padding: 16px 18px;
}
html[data-theme="dark"] .kamr-section-box,
body.dark-mode .kamr-section-box {
    background: #0D111A;
    border-color: rgba(255, 255, 255, 0.07);
}
.kamr-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 18px;
}
@media (max-width: 900px) {
    .kamr-kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
.kamr-kpi-tile {
    background: var(--bg-main, #F8FAFC);
    border: 1px solid var(--border-light, #E2E8F0);
    border-radius: 12px;
    padding: 13px 15px;
}
html[data-theme="dark"] .kamr-kpi-tile,
body.dark-mode .kamr-kpi-tile {
    background: #0D111A;
    border-color: rgba(255, 255, 255, 0.08);
}
.kamr-sec-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13.5px;
    font-weight: 800;
    color: var(--text-primary, #0F172A);
    margin: 0 0 10px 0;
}
html[data-theme="dark"] .kamr-sec-title,
body.dark-mode .kamr-sec-title {
    color: #F8FAFC;
}
.kamr-generator-grid {
    display: grid;
    grid-template-columns: 1.3fr 0.9fr 1.4fr auto;
    gap: 12px;
    align-items: end;
    padding: 15px 18px;
    border-radius: 14px;
    background: var(--bg-main, rgba(15, 23, 42, 0.03));
    border: 1px solid var(--border-light, rgba(148, 163, 184, 0.18));
    margin-bottom: 18px;
}
.kamr-two-sec-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 14px;
}
.kamr-two-sec-grid:last-child {
    margin-bottom: 0;
}
.kamr-edit-grid-2-1 {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 12px;
}
.kamr-edit-grid-1-1 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
@media (max-width: 960px) {
    .kamr-generator-grid {
        grid-template-columns: 1fr 1fr;
    }
    .kamr-two-sec-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 768px) {
    #kala-monthly-ai-panel {
        padding: 16px 15px !important;
    }
    .kamr-generator-grid {
        grid-template-columns: 1fr !important;
        padding: 13px 14px;
        gap: 10px;
    }
    #btn-kamr-generate {
        width: 100% !important;
        justify-content: center !important;
    }
    .kamr-card {
        padding: 15px 14px;
    }
    .kamr-section-box {
        padding: 13px 14px;
    }
    .kamr-kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }
    .kamr-kpi-tile {
        padding: 10px 11px;
    }
    .kamr-edit-grid-2-1,
    .kamr-edit-grid-1-1 {
        grid-template-columns: 1fr !important;
    }
}
@media (max-width: 420px) {
    .kamr-kpi-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function escapeHtmlKamr(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function formatRupiahKamr(num) {
    const n = parseFloat(num) || 0;
    return 'Rp ' + Math.round(n).toLocaleString('id-ID');
}

/**
 * Renders the 5-section Executive Monthly AI Report HTML inside a container
 */
window.renderKalaMonthlyAiReportHtml = function(reportRow, options = {}) {
    const isOwnerView = !!options.isOwnerView;
    const raw = reportRow.raw_metrics || {};
    const ai  = reportRow.ai_report || {};
    const execSum = ai.executive_summary || {};
    const contentAch = ai.content_and_task_achievements || {};
    const adsRev = ai.ads_performance_review || {};
    const wins = ai.key_wins_and_insights || {};
    const roadmap = ai.next_month_roadmap || {};

    const tasksMeta = raw.tasks || {};
    const contentMeta = raw.content || {};
    const adsMeta = raw.ads_budget_client_safe || {};

    const isPublished = reportRow.status === 'published_client';
    const statusBadge = isPublished
        ? `<span style="display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:800; padding:4px 10px; border-radius:999px; background:rgba(16,185,129,0.14); color:#10B981; border:1px solid rgba(16,185,129,0.3);">
             <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
             Approved & Tampil di Portal Klien
           </span>`
        : `<span style="display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:800; padding:4px 10px; border-radius:999px; background:rgba(245,158,11,0.15); color:#F59E0B; border:1px solid rgba(245,158,11,0.35);">
             <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
             Draft Review Owner (Belum Dikirim ke Klien)
           </span>`;

    const deliverablesList = (contentAch.key_deliverables_completed || []).map(item => `
        <li style="margin-bottom:6px; display:flex; align-items:flex-start; gap:7px; font-size:12.5px; color:var(--text-secondary);">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2.4" style="flex-shrink:0; margin-top:2px;"><polyline points="20 6 9 17 4 12"/></svg>
            <span>${escapeHtmlKamr(item)}</span>
        </li>
    `).join('');

    const keyWinsList = (wins.key_wins || []).map(item => `
        <li style="margin-bottom:6px; display:flex; align-items:flex-start; gap:7px; font-size:12.5px; color:var(--text-secondary);">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6366F1" stroke-width="2.3" style="flex-shrink:0; margin-top:2px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <span>${escapeHtmlKamr(item)}</span>
        </li>
    `).join('');

    const challengesList = (wins.challenges_and_solutions || []).map(item => `
        <li style="margin-bottom:6px; display:flex; align-items:flex-start; gap:7px; font-size:12.5px; color:var(--text-secondary);">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2.3" style="flex-shrink:0; margin-top:2px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span>${escapeHtmlKamr(item)}</span>
        </li>
    `).join('');

    const nextActionsList = (roadmap.action_plan_items || []).map((item, idx) => `
        <div style="display:flex; align-items:flex-start; gap:10px; padding:9px 12px; border-radius:10px; background:var(--bg-card, rgba(255,255,255,0.03)); border:1px solid var(--border-light, rgba(148,163,184,0.15));">
            <span style="width:22px; height:22px; border-radius:6px; background:rgba(79,70,229,0.14); color:#6366F1; font-size:11px; font-weight:800; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;">0${idx + 1}</span>
            <span style="font-size:12.5px; color:var(--text-primary); font-weight:600; line-height:1.45;">${escapeHtmlKamr(item)}</span>
        </div>
    `).join('');

    const progressPct = Math.min(100, Math.max(5, parseInt(execSum.progress_90_days_percentage || 65, 10)));

    let ownerActionButtons = '';
    if (isOwnerView) {
        ownerActionButtons = `
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="openEditMonthlyAiReportModal(${reportRow.id})" style="font-size:12px; font-weight:700; border-radius:9px; display:inline-flex; align-items:center; gap:6px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    <span>Edit Isi Laporan</span>
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="printMonthlyAiReportPdf(${reportRow.id})" style="font-size:12px; font-weight:700; border-radius:9px; display:inline-flex; align-items:center; gap:6px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>Cetak PDF</span>
                </button>
                ${!isPublished ? `
                <button type="button" class="btn btn-primary btn-sm" onclick="togglePublishMonthlyAiReport(${reportRow.id}, true)" style="background:linear-gradient(135deg, #059669 0%, #10B981 100%); border:none; font-size:12px; font-weight:800; border-radius:9px; padding:7px 14px; display:inline-flex; align-items:center; gap:6px; box-shadow:0 4px 12px rgba(16,185,129,0.28);">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>Approve & Kirim Report ke Portal Klien</span>
                </button>
                ` : `
                <button type="button" class="btn btn-secondary btn-sm" onclick="togglePublishMonthlyAiReport(${reportRow.id}, false)" style="color:#EF4444; border-color:rgba(239,68,68,0.3); font-size:12px; font-weight:700; border-radius:9px; display:inline-flex; align-items:center; gap:6px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    <span>Tarik ke Draft Owner</span>
                </button>
                `}
            </div>
        `;
    } else {
        ownerActionButtons = `
            <button type="button" class="btn btn-primary btn-sm" onclick="printMonthlyAiReportPdf(${reportRow.id})" style="background:linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); border:none; font-size:12px; font-weight:700; border-radius:9px; padding:8px 15px; display:inline-flex; align-items:center; gap:6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span>Download PDF Laporan Bulanan</span>
            </button>
        `;
    }

    return `
        <div class="kamr-card" id="kamr-report-card-${reportRow.id}">
            <!-- Top Header Bar -->
            <div style="display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:14px; padding-bottom:16px; margin-bottom:18px; border-bottom:1px solid var(--border-light, rgba(148,163,184,0.18));">
                <div>
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:6px;">
                        <span style="font-size:11px; font-weight:800; padding:3px 9px; border-radius:6px; background:rgba(79,70,229,0.12); color:#6366F1; text-transform:uppercase;">PERIODE ${escapeHtmlKamr(raw.month_label || reportRow.report_month)}</span>
                        ${statusBadge}
                        <span style="font-size:11px; font-weight:700; padding:3px 9px; border-radius:6px; background:rgba(16,185,129,0.1); color:#10B981;">Rating: ${escapeHtmlKamr(ai.overall_performance_rating || 'Optimal')}</span>
                    </div>
                    <h3 style="margin:0; font-size:17px; font-weight:800; color:var(--text-primary);">${escapeHtmlKamr(ai.report_title || ('Laporan Bulanan Klien — ' + (raw.client_company || reportRow.client_company || '')))}</h3>
                    <p style="margin:3px 0 0 0; font-size:12px; color:var(--text-muted);">
                        Brand: <strong>${escapeHtmlKamr(raw.client_company || reportRow.client_company || '-')}</strong> • Stage: ${escapeHtmlKamr(raw.brand_stage || '-')} • Skema: ${escapeHtmlKamr(raw.recommended_scheme || '-')}
                    </p>
                </div>
                ${ownerActionButtons}
            </div>

            <!-- Real-Time Aggregated KPI Strip (Client-Safe) -->
            <div class="kamr-kpi-grid">
                <div class="kamr-kpi-tile">
                    <div style="font-size:10.5px; font-weight:700; text-transform:uppercase; color:var(--text-muted); margin-bottom:4px;">Deliverables & Tugas Selesai</div>
                    <div style="font-size:18px; font-weight:800; color:#10B981;">${tasksMeta.approved_completed ?? 0} Selesai</div>
                    <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">${tasksMeta.in_progress ?? 0} Berjalan • ${tasksMeta.in_revision ?? 0} Revisi</div>
                </div>
                <div class="kamr-kpi-tile">
                    <div style="font-size:10.5px; font-weight:700; text-transform:uppercase; color:var(--text-muted); margin-bottom:4px;">Distribusi Kalender Konten</div>
                    <div style="font-size:18px; font-weight:800; color:#6366F1;">${contentMeta.total ?? 0} Konten</div>
                    <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">Reels: ${contentMeta.reels_video ?? 0} • Carousel: ${contentMeta.carousel ?? 0} • Feed: ${contentMeta.feed_single ?? 0}</div>
                </div>
                <div class="kamr-kpi-tile">
                    <div style="font-size:10.5px; font-weight:700; text-transform:uppercase; color:var(--text-muted); margin-bottom:4px;">Serapan Pagu Saldo Ads Klien</div>
                    <div style="font-size:16px; font-weight:800; color:var(--text-primary);">${formatRupiahKamr(adsMeta.spend_used || 0)}</div>
                    <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">Dari Total Pagu: ${formatRupiahKamr(adsMeta.total_pagu_ads || 0)}</div>
                </div>
                <div class="kamr-kpi-tile">
                    <div style="font-size:10.5px; font-weight:700; text-transform:uppercase; color:var(--text-muted); margin-bottom:4px;">Sisa Pagu Saldo Ads Klien</div>
                    <div style="font-size:16px; font-weight:800; color:#10B981;">${formatRupiahKamr(adsMeta.remaining_pagu_ads || 0)}</div>
                    <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">Reach: ${(adsMeta.reach || 0).toLocaleString('id-ID')} • Leads: ${adsMeta.conversions_leads || 0}</div>
                </div>
            </div>

            <!-- 1. Ringkasan Eksekutif Perkembangan Brand Bulan Ini -->
            <div class="kamr-section-box" style="margin-bottom:14px;">
                <h4 class="kamr-sec-title">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6366F1" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <span>1. Ringkasan Eksekutif Perkembangan Brand Bulan Ini</span>
                </h4>
                <div style="font-size:13.5px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">${escapeHtmlKamr(execSum.headline || '')}</div>
                <p style="margin:0 0 12px 0; font-size:12.5px; line-height:1.65; color:var(--text-secondary);">${escapeHtmlKamr(execSum.narrative || '')}</p>
                <div style="padding:10px 14px; border-radius:10px; background:rgba(79,70,229,0.06); border:1px solid rgba(79,70,229,0.18);">
                    <div style="display:flex; justify-content:space-between; font-size:11.5px; font-weight:700; margin-bottom:6px; color:var(--text-primary);">
                        <span>Progres Terhadap Target 90 Hari (Brand AI Blueprint)</span>
                        <span style="color:#6366F1;">${progressPct}% Tercapai</span>
                    </div>
                    <div style="height:7px; border-radius:999px; background:rgba(148,163,184,0.2); overflow:hidden; margin-bottom:6px;">
                        <div style="width:${progressPct}%; height:100%; background:linear-gradient(90deg, #4F46E5, #10B981); border-radius:999px;"></div>
                    </div>
                    <div style="font-size:11.5px; color:var(--text-muted);">${escapeHtmlKamr(execSum.progress_90_days_note || '')}</div>
                </div>
            </div>

            <!-- 2 & 3 Grid: Capaian Produksi Konten & Evaluasi Performa Ads -->
            <div class="kamr-two-sec-grid">
                <!-- 2. Capaian Produksi Konten & Deliverables -->
                <div class="kamr-section-box">
                    <h4 class="kamr-sec-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2.2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span>2. Capaian Produksi Konten & Deliverables</span>
                    </h4>
                    <p style="margin:0 0 10px 0; font-size:12.5px; line-height:1.6; color:var(--text-secondary);">${escapeHtmlKamr(contentAch.summary_narrative || '')}</p>
                    <ul style="list-style:none; padding:0; margin:0 0 10px 0;">
                        ${deliverablesList}
                    </ul>
                    <div style="font-size:11.5px; color:var(--text-muted); padding-top:8px; border-top:1px dashed var(--border-light, rgba(148,163,184,0.2));">
                        <strong>Evaluasi Bauran Format:</strong> ${escapeHtmlKamr(contentAch.content_mix_evaluation || '')}
                    </div>
                </div>

                <!-- 3. Evaluasi Performa Kampanye Ads & Efisiensi Anggaran -->
                <div class="kamr-section-box">
                    <h4 class="kamr-sec-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2.2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        <span>3. Evaluasi Performa Kampanye Ads & Efisiensi Anggaran</span>
                    </h4>
                    <p style="margin:0 0 8px 0; font-size:12.5px; line-height:1.6; color:var(--text-secondary);">
                        <strong>Efisiensi Pagu Anggaran:</strong> ${escapeHtmlKamr(adsRev.budget_efficiency_summary || '')}
                    </p>
                    <p style="margin:0 0 8px 0; font-size:12.5px; line-height:1.6; color:var(--text-secondary);">
                        <strong>Analisis Metrik Kampanye:</strong> ${escapeHtmlKamr(adsRev.campaign_metrics_analysis || '')}
                    </p>
                    <div style="font-size:11.5px; color:var(--text-muted); padding-top:8px; border-top:1px dashed var(--border-light, rgba(148,163,184,0.2));">
                        <strong>Catatan Optimasi Tim Ads:</strong> ${escapeHtmlKamr(adsRev.optimization_notes || '')}
                    </div>
                </div>
            </div>

            <!-- 4 & 5 Grid: Key Wins & Roadmap Bulan Depan -->
            <div class="kamr-two-sec-grid">
                <!-- 4. Highlight Keberhasilan (Key Wins) & Evaluasi Kendala -->
                <div class="kamr-section-box">
                    <h4 class="kamr-sec-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2.2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        <span>4. Highlight Keberhasilan (Key Wins) & Evaluasi Kendala</span>
                    </h4>
                    <div style="font-size:11.5px; font-weight:700; color:#6366F1; margin-bottom:6px;">Key Wins Bulan Ini:</div>
                    <ul style="list-style:none; padding:0; margin:0 0 10px 0;">
                        ${keyWinsList}
                    </ul>
                    <div style="font-size:11.5px; font-weight:700; color:#F59E0B; margin-bottom:6px;">Evaluasi Kendala & Solusi:</div>
                    <ul style="list-style:none; padding:0; margin:0;">
                        ${challengesList}
                    </ul>
                </div>

                <!-- 5. Rekomendasi & Rencana Fokus Bulan Depan -->
                <div class="kamr-section-box">
                    <h4 class="kamr-sec-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8B5CF6" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
                        <span>5. Rekomendasi & Rencana Fokus Bulan Depan</span>
                    </h4>
                    <div style="font-size:12.5px; font-weight:700; color:var(--text-primary); margin-bottom:10px;">
                        Fokus Utama: <span style="color:#6366F1;">${escapeHtmlKamr(roadmap.strategic_focus_theme || 'Akselerasi Pertumbuhan & Konversi')}</span>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:7px;">
                        ${nextActionsList}
                    </div>
                </div>
            </div>
        </div>
    `;
};

window.printMonthlyAiReportPdf = function(reportId) {
    const el = document.getElementById(`kamr-report-card-${reportId}`);
    if (!el) return;
    const win = window.open('', '_blank', 'width=1024,height=800');
    if (!win) {
        window.print();
        return;
    }
    win.document.write(`
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <title>Laporan Eksekutif Bulanan Klien - Kala Kausa Prima</title>
            <style>
                body { font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif; color: #0F172A; background: #FFFFFF; padding: 28px; margin: 0; }
                .kamr-card { border: 1px solid #CBD5E1; border-radius: 14px; padding: 24px; }
                .kamr-section-box { border: 1px solid #E2E8F0; border-radius: 12px; padding: 14px 16px; background: #F8FAFC; margin-bottom: 12px; }
                .kamr-kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 16px; }
                .kamr-kpi-tile { border: 1px solid #E2E8F0; border-radius: 10px; padding: 10px 12px; background: #F8FAFC; }
                .kamr-sec-title { display: flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: 800; margin: 0 0 8px 0; color: #0F172A; }
                button { display: none !important; }
            </style>
        </head>
        <body>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; padding-bottom:12px; border-bottom:2px solid #4F46E5;">
                <div>
                    <div style="font-size:18px; font-weight:800; color:#0F172A;">KALA KAUSA PRIMA (KALAMEDIA)</div>
                    <div style="font-size:12px; color:#64748B;">Executive Monthly Client Performance & Strategy Report</div>
                </div>
                <div style="font-size:11.5px; color:#475569; text-align:right;">Dicetak pada: ${new Date().toLocaleDateString('id-ID', { day:'2-digit', month:'long', year:'numeric' })}</div>
            </div>
            ${el.outerHTML}
            <script>window.onload = function() { setTimeout(function(){ window.print(); }, 300); };<\/script>
        </body>
        </html>
    `);
    win.document.close();
};
</script>


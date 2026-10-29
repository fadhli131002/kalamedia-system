<?php
/**
 * Reusable Modal: Kala AI Content Calendar Generator (7 / 14 / 30 Hari)
 * Pulls directly from Brand AI Strategy Blueprint (`brand_ai_audits`)
 * Supports inline editing & checkbox selection before syncing to Kalender Konten & Portal Klien.
 */
if (!isset($db)) {
    $db = Database::getConnection();
}
$kalaAiCalClients = $db->query("
    SELECT c.id, c.name, c.company, c.service_type,
           (SELECT b.recommended_scheme_name FROM brand_ai_audits b WHERE b.client_id = c.id ORDER BY b.id DESC LIMIT 1) as ai_scheme,
           (SELECT b.brand_stage FROM brand_ai_audits b WHERE b.client_id = c.id ORDER BY b.id DESC LIMIT 1) as ai_stage
    FROM clients c
    WHERE c.is_deleted = 0
    ORDER BY c.company ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<style>
#modal-kala-ai-calendar {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 100060;
    align-items: center;
    justify-content: center;
    padding: 16px;
    box-sizing: border-box;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity 180ms ease, visibility 180ms ease;
}
#modal-kala-ai-calendar.show,
#modal-kala-ai-calendar.active {
    display: flex !important;
    opacity: 1 !important;
    visibility: visible !important;
    pointer-events: auto !important;
}
#modal-kala-ai-calendar .kac-dialog {
    width: 96vw;
    max-width: 1280px;
    max-height: 92vh;
    display: flex;
    flex-direction: column;
    border-radius: 18px;
    overflow: hidden;
    background: var(--bg-card, #ffffff);
    border: 1px solid var(--border-light, #E2E8F0);
    box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.35);
}
html[data-theme="dark"] #modal-kala-ai-calendar .kac-dialog,
body.dark-mode #modal-kala-ai-calendar .kac-dialog {
    background: #0D111A;
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.75);
}
.kac-header {
    padding: 18px 24px;
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.08) 0%, rgba(124, 58, 237, 0.08) 100%);
    border-bottom: 1px solid var(--border-light, #E2E8F0);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}
html[data-theme="dark"] .kac-header,
body.dark-mode .kac-header {
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.16) 0%, rgba(124, 58, 237, 0.12) 100%);
    border-bottom-color: rgba(255, 255, 255, 0.08);
}
.kac-body {
    padding: 20px 24px;
    overflow-y: auto;
    flex: 1;
}
.kac-config-bar {
    background: var(--bg-main, #F8FAFC);
    border: 1px solid var(--border-light, #E2E8F0);
    border-radius: 14px;
    padding: 16px 18px;
    margin-bottom: 18px;
}
html[data-theme="dark"] .kac-config-bar,
body.dark-mode .kac-config-bar {
    background: #131926;
    border-color: rgba(255, 255, 255, 0.08);
}
.kac-duration-group {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}
.kac-dur-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 9px 12px;
    border-radius: 10px;
    font-size: 12.5px;
    font-weight: 700;
    cursor: pointer;
    border: 1px solid var(--border-light, #CBD5E1);
    background: var(--bg-card, #ffffff);
    color: var(--text-secondary, #475569);
    transition: all 0.18s ease;
}
html[data-theme="dark"] .kac-dur-btn,
body.dark-mode .kac-dur-btn {
    background: #0D111A;
    border-color: rgba(255, 255, 255, 0.12);
    color: #94A3B8;
}
.kac-dur-btn.active {
    background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
    color: #ffffff !important;
    border-color: transparent;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);
}
.kac-strategy-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 12px;
    margin-top: 14px;
    background: rgba(79, 70, 229, 0.06);
    border: 1px solid rgba(79, 70, 229, 0.2);
}
html[data-theme="dark"] .kac-strategy-banner,
body.dark-mode .kac-strategy-banner {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.25);
}
.kac-item-card {
    background: var(--bg-card, #ffffff);
    border: 1px solid var(--border-light, #E2E8F0);
    border-radius: 14px;
    padding: 16px;
    margin-bottom: 12px;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
html[data-theme="dark"] .kac-item-card,
body.dark-mode .kac-item-card {
    background: #131926;
    border-color: rgba(255, 255, 255, 0.08);
}
.kac-item-card.selected {
    border-color: rgba(79, 70, 229, 0.45);
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.08);
}
.kac-input {
    width: 100%;
    padding: 8px 11px;
    font-size: 12.5px;
    border-radius: 8px;
    border: 1px solid var(--border-light, #CBD5E1);
    background: var(--bg-main, #F8FAFC);
    color: var(--text-primary, #0F172A);
    font-family: inherit;
}
html[data-theme="dark"] .kac-input,
body.dark-mode .kac-input {
    background: #0D111A;
    border-color: rgba(255, 255, 255, 0.12);
    color: #F8FAFC;
}
.kac-input:focus {
    outline: none;
    border-color: #6366F1;
}
.kac-label {
    display: block;
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted, #64748B);
    margin-bottom: 4px;
}
.kac-footer {
    padding: 14px 24px;
    border-top: 1px solid var(--border-light, #E2E8F0);
    background: var(--bg-main, #F8FAFC);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
html[data-theme="dark"] .kac-footer,
body.dark-mode .kac-footer {
    background: #0B0F17;
    border-top-color: rgba(255, 255, 255, 0.08);
}
.kac-config-grid {
    display: grid;
    grid-template-columns: 1.3fr 1.3fr 0.9fr 1.2fr auto;
    gap: 12px;
    align-items: end;
}
.kac-meta-grid {
    display: grid;
    grid-template-columns: 140px 100px 140px 190px 1fr;
    gap: 10px;
    margin-bottom: 10px;
}
.kac-two-col-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 10px;
}
.kac-two-col-grid:last-child {
    margin-bottom: 0;
}
@media (max-width: 960px) {
    .kac-config-grid {
        grid-template-columns: 1fr 1fr;
    }
    .kac-meta-grid {
        grid-template-columns: 1fr 1fr;
    }
}
@media (max-width: 768px) {
    #modal-kala-ai-calendar .kac-dialog {
        width: 98vw;
        max-height: 94vh;
        border-radius: 16px;
    }
    .kac-header {
        padding: 14px 16px;
        align-items: flex-start;
    }
    .kac-header h3 {
        font-size: 14.5px !important;
    }
    .kac-body {
        padding: 14px 16px;
    }
    .kac-config-bar {
        padding: 13px 14px;
    }
    .kac-config-grid {
        grid-template-columns: 1fr !important;
        gap: 10px;
    }
    #btn-kac-generate {
        width: 100% !important;
        justify-content: center !important;
    }
    .kac-meta-grid {
        grid-template-columns: 1fr 1fr !important;
    }
    .kac-meta-grid > div:last-child {
        grid-column: 1 / -1;
    }
    .kac-two-col-grid {
        grid-template-columns: 1fr !important;
    }
    .kac-footer {
        padding: 12px 16px;
        flex-direction: column;
        align-items: stretch;
    }
    .kac-footer > div:last-child {
        width: 100%;
        display: flex;
        flex-direction: column-reverse;
        gap: 8px;
    }
    .kac-footer > div:last-child button {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="modal modal-backdrop" id="modal-kala-ai-calendar" style="display: none; z-index: 100060;">
    <div class="kac-dialog">
        <!-- Header -->
        <div class="kac-header">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:42px; height:42px; border-radius:12px; background:linear-gradient(135deg, #4F46E5, #7C3AED); display:flex; align-items:center; justify-content:center; color:#fff; flex-shrink:0; box-shadow:0 6px 16px rgba(79,70,229,0.3);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                        <path d="M12 14l1.5 2.5L16 18l-2.5 1.5L12 22l-1.5-2.5L8 18l2.5-1.5L12 14z"></path>
                    </svg>
                </div>
                <div>
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <h3 style="margin:0; font-size:16px; font-weight:800; color:var(--text-primary);">Rekomendasi Kalender Konten Otomatis (Kala AI)</h3>
                        <span style="font-size:10.5px; font-weight:800; padding:3px 9px; border-radius:999px; background:rgba(79,70,229,0.14); color:#6366F1; border:1px solid rgba(99,102,241,0.3);">GEMINI STRATEGY SYNC</span>
                    </div>
                    <p style="margin:2px 0 0 0; font-size:12px; color:var(--text-muted);">Disusun otomatis dari Brand Stage, USP, Pilar Konten, Rasio Format, dan Formula Hook klien</p>
                </div>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('modal-kala-ai-calendar')" style="padding:7px 12px; border-radius:8px; display:inline-flex; align-items:center; gap:5px; flex-shrink:0;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                Tutup
            </button>
        </div>

        <!-- Body -->
        <div class="kac-body">
            <!-- Configuration Bar -->
            <div class="kac-config-bar">
                <div class="kac-config-grid">
                    <div>
                        <label class="kac-label">Pilih Brand / Klien</label>
                        <select id="kac-client-select" class="kac-input" onchange="onKacClientChange(this.value)" style="height:38px; font-weight:600;">
                            <option value="">-- Pilih Klien --</option>
                            <?php foreach ($kalaAiCalClients as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" data-company="<?= e($c['company'] ?: $c['name']) ?>">
                                <?= e($c['company'] ?: $c['name']) ?><?= !empty($c['ai_scheme']) ? ' — [AI Audited]' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="kac-label">Rentang Rencana Konten</label>
                        <input type="hidden" id="kac-duration-input" value="7">
                        <div class="kac-duration-group">
                            <button type="button" class="kac-dur-btn active" data-days="7" onclick="selectKacDuration(7)">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                7 Hari
                            </button>
                            <button type="button" class="kac-dur-btn" data-days="14" onclick="selectKacDuration(14)">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                14 Hari
                            </button>
                            <button type="button" class="kac-dur-btn" data-days="30" onclick="selectKacDuration(30)">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                30 Hari
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="kac-label">Tanggal Mulai</label>
                        <input type="date" id="kac-start-date" class="kac-input" value="<?= date('Y-m-d') ?>" style="height:38px;">
                    </div>

                    <div>
                        <label class="kac-label">Fokus Kampanye (Opsional)</label>
                        <input type="text" id="kac-custom-focus" class="kac-input" placeholder="Misal: Edukasi produk, Promo..." style="height:38px;">
                    </div>

                    <div>
                        <button type="button" id="btn-kac-generate" onclick="generateKalaAiCalendar()" class="btn btn-primary" style="height:38px; padding:0 18px; font-size:12.5px; font-weight:700; background:linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); border:none; border-radius:10px; display:inline-flex; align-items:center; gap:7px; white-space:nowrap; box-shadow:0 4px 14px rgba(79,70,229,0.35);">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 2L14.8 8.5L22 9.3L16.5 14L18.2 21L12 17.3L5.8 21L7.5 14L2 9.3L9.2 8.5L12 2Z"/></svg>
                            <span>Susun Kalender AI</span>
                        </button>
                    </div>
                </div>

                <!-- Strategy Source Indicator -->
                <div id="kac-strategy-info" class="kac-strategy-banner" style="display:none;"></div>
            </div>

            <!-- Loading State -->
            <div id="kac-loading-state" style="display:none; text-align:center; padding:48px 20px;">
                <div style="width:52px; height:52px; border-radius:16px; background:rgba(79,70,229,0.12); color:#6366F1; display:inline-flex; align-items:center; justify-content:center; margin-bottom:14px;">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                </div>
                <h4 style="margin:0 0 6px 0; font-size:15px; font-weight:800; color:var(--text-primary);">Kala AI Sedang Merancang Jadwal, Hook & Brief Konten...</h4>
                <p style="margin:0; font-size:12.5px; color:var(--text-muted); max-width:520px; margin-inline:auto;">Menyelaraskan Pilar Konten, Rasio Reels/Carousel/Feed/Story, Visual Brief, dan Draft Caption sesuai Skema Strategi AI klien.</p>
            </div>

            <!-- Empty Initial State -->
            <div id="kac-empty-state" style="text-align:center; padding:42px 20px; border:1px dashed var(--border-light, #CBD5E1); border-radius:14px;">
                <div style="width:48px; height:48px; border-radius:14px; background:rgba(79,70,229,0.08); color:#6366F1; display:inline-flex; align-items:center; justify-content:center; margin-bottom:12px;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                <h4 style="margin:0 0 6px 0; font-size:14.5px; font-weight:800; color:var(--text-primary);">Pilih Klien & Durasi, Lalu Klik "Susun Kalender AI"</h4>
                <p style="margin:0; font-size:12.5px; color:var(--text-muted); max-width:560px; margin-inline:auto;">
                    Anda dapat mengedit setiap topik, kalimat Hook 3 detik pertama, Visual Brief untuk tim kreatif, dan Draft Caption sebelum menerapkannya ke Kalender Konten & Portal Klien.
                </p>
            </div>

            <!-- Preview Container -->
            <div id="kac-preview-container" style="display:none;">
                <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <label style="display:inline-flex; align-items:center; gap:8px; font-size:13px; font-weight:700; cursor:pointer; color:var(--text-primary);">
                            <input type="checkbox" id="kac-select-all" checked onchange="toggleAllKacItems(this.checked)" style="width:16px; height:16px; accent-color:#4F46E5;">
                            <span>Pilih Semua Rencana Konten</span>
                        </label>
                        <span id="kac-summary-badge" style="font-size:11.5px; font-weight:700; padding:3px 10px; border-radius:999px; background:rgba(16,185,129,0.12); color:#10B981;">0 Item Siap Dijadwalkan</span>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addBlankKacItem()" style="font-size:12px; font-weight:700; display:inline-flex; align-items:center; gap:6px; border-radius:8px;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        Tambah Baris Manual
                    </button>
                </div>

                <div id="kac-calendar-summary-box" style="margin-bottom:14px; padding:11px 15px; border-radius:10px; background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.22); font-size:12.5px; color:var(--text-primary); display:none;"></div>

                <div id="kac-items-list"></div>
            </div>
        </div>

        <!-- Footer -->
        <div class="kac-footer">
            <div style="font-size:12px; color:var(--text-muted); display:flex; align-items:center; gap:7px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6366F1" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span>Item yang dicentang akan otomatis masuk ke <strong>Kalender Konten</strong> & tampil di tab <strong>Kalender Konten Portal Klien</strong>.</span>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-kala-ai-calendar')" style="border-radius:10px; font-size:12.5px;">Batal</button>
                <button type="button" id="btn-kac-apply" onclick="applyKalaAiCalendarToSystem()" class="btn btn-primary" disabled style="background:linear-gradient(135deg, #059669 0%, #10B981 100%); border:none; border-radius:10px; font-size:12.5px; font-weight:700; padding:9px 18px; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 14px rgba(16,185,129,0.3);">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span>Terapkan ke Kalender Konten & Tampilkan di Portal Klien</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.kacGeneratedItems = [];

function openKalaAiCalendarModal(clientId, clientCompany) {
    const modal = document.getElementById('modal-kala-ai-calendar');
    if (!modal) return;

    const selectEl = document.getElementById('kac-client-select');
    if (clientId && selectEl) {
        selectEl.value = String(clientId);
        onKacClientChange(clientId);
    } else if (selectEl && selectEl.value) {
        onKacClientChange(selectEl.value);
    }

    if (typeof openModal === 'function') {
        openModal('modal-kala-ai-calendar');
    } else {
        modal.classList.add('active');
    }
}

function selectKacDuration(days) {
    document.getElementById('kac-duration-input').value = String(days);
    document.querySelectorAll('#modal-kala-ai-calendar .kac-dur-btn').forEach(btn => {
        btn.classList.toggle('active', parseInt(btn.getAttribute('data-days'), 10) === days);
    });
}

async function onKacClientChange(clientId) {
    const infoBox = document.getElementById('kac-strategy-info');
    if (!infoBox) return;
    if (!clientId) {
        infoBox.style.display = 'none';
        return;
    }

    infoBox.style.display = 'flex';
    infoBox.innerHTML = `<span style="font-size:12px; color:var(--text-muted);">Memuat data Skema Strategi AI klien...</span>`;

    try {
        const res = await fetch(`<?= BASE_URL ?>/api/gemini_content_calendar.php?action=get_client_strategy&client_id=${encodeURIComponent(clientId)}`);
        const json = await res.json();
        if (json.success && json.has_strategy && json.strategy) {
            const st = json.strategy;
            const pillarsBadges = (st.pillars_list || []).map(p =>
                `<span style="font-size:11px; font-weight:600; padding:2px 8px; border-radius:6px; background:rgba(79,70,229,0.12); color:#6366F1;">${escapeHtmlKac(p)}</span>`
            ).join(' ');
            infoBox.innerHTML = `
                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <span style="display:inline-flex; align-items:center; gap:5px; font-size:11.5px; font-weight:800; color:#10B981;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        Terhubung ke Skema Strategi AI:
                    </span>
                    <strong style="font-size:12.5px; color:var(--text-primary);">${escapeHtmlKac(st.recommended_scheme_name)}</strong>
                    <span style="font-size:11px; padding:2px 8px; border-radius:999px; background:rgba(16,185,129,0.14); color:#10B981; font-weight:700;">Stage: ${escapeHtmlKac(st.brand_stage)} (Skor ${st.health_score}/100)</span>
                </div>
                <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                    <span style="font-size:11px; color:var(--text-muted);">Pilar Konten:</span>
                    ${pillarsBadges || '<span style="font-size:11px; color:var(--text-muted);">4 Pilar Default</span>'}
                </div>
            `;
        } else {
            infoBox.innerHTML = `
                <div style="display:flex; align-items:center; justify-content:space-between; width:100%; flex-wrap:wrap; gap:8px;">
                    <span style="font-size:12px; color:var(--text-secondary); display:inline-flex; align-items:center; gap:6px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        Klien ini belum memiliki dokumen Audit 17 Pertanyaan. Kalender AI tetap dapat disusun otomatis berdasarkan profil layanan klien.
                    </span>
                </div>
            `;
        }
    } catch (err) {
        infoBox.style.display = 'none';
    }
}

function escapeHtmlKac(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

async function generateKalaAiCalendar() {
    const clientId = document.getElementById('kac-client-select').value;
    const durationDays = parseInt(document.getElementById('kac-duration-input').value || '7', 10);
    const startDate = document.getElementById('kac-start-date').value;
    const customFocus = document.getElementById('kac-custom-focus').value;

    if (!clientId) {
        if (typeof showToast === 'function') showToast('Silakan pilih Brand / Klien terlebih dahulu.', 'error');
        else alert('Silakan pilih Brand / Klien terlebih dahulu.');
        return;
    }

    const btnGen = document.getElementById('btn-kac-generate');
    const loadingEl = document.getElementById('kac-loading-state');
    const emptyEl = document.getElementById('kac-empty-state');
    const previewEl = document.getElementById('kac-preview-container');

    btnGen.disabled = true;
    loadingEl.style.display = 'block';
    emptyEl.style.display = 'none';
    previewEl.style.display = 'none';

    try {
        const res = await fetch('<?= BASE_URL ?>/api/gemini_content_calendar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'generate_calendar',
                client_id: clientId,
                duration_days: durationDays,
                start_date: startDate,
                custom_focus: customFocus
            })
        });
        const json = await res.json();
        loadingEl.style.display = 'none';
        btnGen.disabled = false;

        if (!json.success) {
            emptyEl.style.display = 'block';
            if (typeof showToast === 'function') showToast(json.message || 'Gagal menyusun kalender.', 'error');
            else alert(json.message || 'Gagal menyusun kalender.');
            return;
        }

        window.kacGeneratedItems = (json.items || []).map(it => ({
            selected: true,
            scheduled_date: it.scheduled_date || startDate,
            best_time: it.best_time || '19:00',
            platform: it.platform || 'Instagram',
            content_format: it.content_format || 'Instagram Reels',
            content_pillar: it.content_pillar || 'Edukasi & Authority',
            title: it.title || '',
            hook_text: it.hook_text || '',
            visual_brief: it.visual_brief || '',
            caption_draft: it.caption_draft || ''
        }));

        const sumBox = document.getElementById('kac-calendar-summary-box');
        if (sumBox && json.calendar_summary) {
            sumBox.style.display = 'block';
            sumBox.innerHTML = `<strong>Strategi Distribusi (${durationDays} Hari — Model: ${escapeHtmlKac(json.model_used || 'Gemini Pro')}):</strong> ${escapeHtmlKac(json.calendar_summary)}`;
        } else if (sumBox) {
            sumBox.style.display = 'none';
        }

        renderKacItemsList();
        previewEl.style.display = 'block';
        if (typeof showToast === 'function') showToast(json.message || 'Rekomendasi kalender berhasil disusun!', 'success');
    } catch (e) {
        loadingEl.style.display = 'none';
        emptyEl.style.display = 'block';
        btnGen.disabled = false;
        if (typeof showToast === 'function') showToast('Terjadi kesalahan jaringan saat menghubungi Gemini AI.', 'error');
    }
}

function renderKacItemsList() {
    const listEl = document.getElementById('kac-items-list');
    if (!listEl) return;

    if (!window.kacGeneratedItems.length) {
        listEl.innerHTML = `<div style="padding:24px; text-align:center; color:var(--text-muted);">Belum ada item konten.</div>`;
        updateKacSelectionSummary();
        return;
    }

    listEl.innerHTML = window.kacGeneratedItems.map((item, idx) => `
        <div class="kac-item-card ${item.selected ? 'selected' : ''}" id="kac-card-${idx}">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px; padding-bottom:10px; border-bottom:1px solid var(--border-light, rgba(148,163,184,0.18));">
                <div style="display:flex; align-items:center; gap:10px;">
                    <input type="checkbox" ${item.selected ? 'checked' : ''} onchange="updateKacItemField(${idx}, 'selected', this.checked)" style="width:17px; height:17px; accent-color:#4F46E5; cursor:pointer;">
                    <span style="font-size:12px; font-weight:800; padding:3px 9px; border-radius:6px; background:rgba(79,70,229,0.12); color:#6366F1;">Konten #${idx + 1}</span>
                    <span style="font-size:12px; font-weight:700; color:var(--text-secondary);">${escapeHtmlKac(item.scheduled_date)} • ${escapeHtmlKac(item.best_time)} WIB</span>
                </div>
                <button type="button" onclick="removeKacItem(${idx})" style="background:transparent; border:none; color:#EF4444; font-size:11.5px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:4px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    Hapus
                </button>
            </div>

            <div class="kac-meta-grid">
                <div>
                    <label class="kac-label">Tanggal Tayang</label>
                    <input type="date" class="kac-input" value="${escapeHtmlKac(item.scheduled_date)}" onchange="updateKacItemField(${idx}, 'scheduled_date', this.value)">
                </div>
                <div>
                    <label class="kac-label">Jam</label>
                    <input type="text" class="kac-input" value="${escapeHtmlKac(item.best_time)}" onchange="updateKacItemField(${idx}, 'best_time', this.value)" placeholder="19:00">
                </div>
                <div>
                    <label class="kac-label">Platform</label>
                    <input type="text" class="kac-input" value="${escapeHtmlKac(item.platform)}" onchange="updateKacItemField(${idx}, 'platform', this.value)">
                </div>
                <div>
                    <label class="kac-label">Format Konten</label>
                    <select class="kac-input" onchange="updateKacItemField(${idx}, 'content_format', this.value)">
                        ${['Instagram Reels', 'Carousel Edukasi', 'Single Image Promo', 'IG Story Interaktif', 'TikTok Video'].map(fmt =>
                            `<option value="${fmt}" ${item.content_format === fmt ? 'selected' : ''}>${fmt}</option>`
                        ).join('')}
                        ${!['Instagram Reels', 'Carousel Edukasi', 'Single Image Promo', 'IG Story Interaktif', 'TikTok Video'].includes(item.content_format)
                            ? `<option value="${escapeHtmlKac(item.content_format)}" selected>${escapeHtmlKac(item.content_format)}</option>` : ''}
                    </select>
                </div>
                <div>
                    <label class="kac-label">Pilar Konten (Sesuai Skema Strategi AI)</label>
                    <input type="text" class="kac-input" value="${escapeHtmlKac(item.content_pillar)}" onchange="updateKacItemField(${idx}, 'content_pillar', this.value)">
                </div>
            </div>

            <div class="kac-two-col-grid">
                <div>
                    <label class="kac-label">Judul / Topik Utama Konten</label>
                    <input type="text" class="kac-input" style="font-weight:700;" value="${escapeHtmlKac(item.title)}" onchange="updateKacItemField(${idx}, 'title', this.value)">
                </div>
                <div>
                    <label class="kac-label">Hook 3 Detik Pertama (Reels) / Judul Slide 1 (Carousel)</label>
                    <input type="text" class="kac-input" value="${escapeHtmlKac(item.hook_text)}" onchange="updateKacItemField(${idx}, 'hook_text', this.value)">
                </div>
            </div>

            <div class="kac-two-col-grid">
                <div>
                    <label class="kac-label">Visual Brief (Arahan Video / Desain untuk Tim Kreatif)</label>
                    <textarea class="kac-input" rows="2" onchange="updateKacItemField(${idx}, 'visual_brief', this.value)">${escapeHtmlKac(item.visual_brief)}</textarea>
                </div>
                <div>
                    <label class="kac-label">Draft Caption Lengkap + CTA + Hashtag</label>
                    <textarea class="kac-input" rows="2" onchange="updateKacItemField(${idx}, 'caption_draft', this.value)">${escapeHtmlKac(item.caption_draft)}</textarea>
                </div>
            </div>
        </div>
    `).join('');

    updateKacSelectionSummary();
}

function updateKacItemField(idx, field, val) {
    if (!window.kacGeneratedItems[idx]) return;
    window.kacGeneratedItems[idx][field] = val;
    if (field === 'selected') {
        const card = document.getElementById(`kac-card-${idx}`);
        if (card) card.classList.toggle('selected', !!val);
        updateKacSelectionSummary();
    }
}

function toggleAllKacItems(checked) {
    window.kacGeneratedItems.forEach((it, idx) => {
        it.selected = checked;
    });
    renderKacItemsList();
}

function removeKacItem(idx) {
    window.kacGeneratedItems.splice(idx, 1);
    renderKacItemsList();
}

function addBlankKacItem() {
    const startDate = document.getElementById('kac-start-date').value || new Date().toISOString().slice(0, 10);
    window.kacGeneratedItems.push({
        selected: true,
        scheduled_date: startDate,
        best_time: '19:00',
        platform: 'Instagram',
        content_format: 'Instagram Reels',
        content_pillar: 'Edukasi & Solusi Brand',
        title: 'Topik Konten Baru',
        hook_text: '',
        visual_brief: '',
        caption_draft: ''
    });
    renderKacItemsList();
}

function updateKacSelectionSummary() {
    const selectedCount = (window.kacGeneratedItems || []).filter(i => i.selected).length;
    const badge = document.getElementById('kac-summary-badge');
    const applyBtn = document.getElementById('btn-kac-apply');
    if (badge) {
        badge.textContent = `${selectedCount} dari ${window.kacGeneratedItems.length} Item Dipilih`;
    }
    if (applyBtn) {
        applyBtn.disabled = selectedCount === 0;
    }
}

async function applyKalaAiCalendarToSystem() {
    const clientId = document.getElementById('kac-client-select').value;
    const selectedItems = (window.kacGeneratedItems || []).filter(i => i.selected);

    if (!clientId || selectedItems.length === 0) {
        if (typeof showToast === 'function') showToast('Pilih minimal 1 item konten untuk disimpan.', 'error');
        return;
    }

    const applyBtn = document.getElementById('btn-kac-apply');
    const origHtml = applyBtn.innerHTML;
    applyBtn.disabled = true;
    applyBtn.innerHTML = `<span>Menyimpan ke Kalender & Portal Klien...</span>`;

    try {
        const res = await fetch('<?= BASE_URL ?>/api/gemini_content_calendar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'apply_to_calendar',
                client_id: clientId,
                items: selectedItems
            })
        });
        const json = await res.json();
        applyBtn.disabled = false;
        applyBtn.innerHTML = origHtml;

        if (!json.success) {
            if (typeof showToast === 'function') showToast(json.message || 'Gagal menerapkan kalender.', 'error');
            else alert(json.message || 'Gagal menerapkan kalender.');
            return;
        }

        if (typeof showToast === 'function') {
            showToast(json.message || 'Berhasil menerapkan ke Kalender Konten & Portal Klien!', 'success');
        }
        closeModal('modal-kala-ai-calendar');

        // Refresh FullCalendar if on Kalender Konten page
        if (typeof window.refreshContentCalendar === 'function') {
            window.refreshContentCalendar();
        }
    } catch (err) {
        applyBtn.disabled = false;
        applyBtn.innerHTML = origHtml;
        if (typeof showToast === 'function') showToast('Kesalahan jaringan saat menyimpan kalender.', 'error');
    }
}
</script>


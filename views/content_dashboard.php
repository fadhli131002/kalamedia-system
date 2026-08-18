<?php
/**
 * Kalamedia Content Management Dashboard (Content Calendar)
 * 1:1 Pixel-Accurate Implementation based on Slide 3 (supitarcalendar.com reference)
 * Includes Day/Week/Month views, Mini Calendar date picker, and PDF Export.
 */

require_auth();

$db = Database::getConnection();
$portal = current_portal();
$isOwner = ($portal === 'owner');

// Pre-defined client colors matching Slide 3
$clientColorPalette = ['#2563EB', '#EC4899', '#16A34A', '#8B5CF6', '#F59E0B', '#06B6D4', '#EF4444', '#6366F1'];

// Fetch all clients
$clients = $db->query("
    SELECT c.id, c.name, c.company,
           COUNT(cp.id) as total_contents
    FROM clients c
    LEFT JOIN content_planner cp ON c.id = cp.client_id AND COALESCE(cp.is_deleted, 0) = 0
    GROUP BY c.id
    ORDER BY c.company ASC
")->fetchAll();

foreach ($clients as $idx => &$c) {
    $c['color_hex'] = $clientColorPalette[$idx % count($clientColorPalette)];
}
unset($c);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Content Calendar - Kalamedia</title>
  <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
  <!-- FullCalendar 6.1.15 JS -->
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
  <!-- html2pdf for Export PDF -->
  <script src="assets/js/html2pdf.bundle.min.js"></script>
  <style>
    /* ==========================================================================
       SUPITAR CALENDAR THEME (1:1 with Slide 3 Reference)
       ========================================================================== */
    .supitar-calendar-card {
      background: #FFFFFF;
      border: 1px solid #EAECF0;
      border-radius: 16px;
      box-shadow: 0 1px 3px rgba(16, 24, 40, 0.06), 0 1px 2px rgba(16, 24, 40, 0.04);
      display: flex;
      min-height: 820px;
      overflow: hidden;
    }

    .supitar-sidebar {
      width: 260px;
      min-width: 260px;
      border-right: 1px solid #EAECF0;
      background: #FFFFFF;
      padding: 24px;
      display: flex;
      flex-direction: column;
      gap: 20px;
      box-sizing: border-box;
    }

    .supitar-sidebar-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .supitar-title {
      font-size: 16px;
      font-weight: 700;
      color: #101828;
      margin: 0;
    }

    .supitar-icon-btn {
      background: transparent;
      border: none;
      color: #667085;
      cursor: pointer;
      padding: 2px;
      display: flex;
      align-items: center;
      border-radius: 4px;
    }
    .supitar-icon-btn:hover { color: #101828; }

    .supitar-search-wrap {
      position: relative;
      width: 100%;
    }
    .supitar-search-wrap svg {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      pointer-events: none;
    }
    .supitar-search-wrap input {
      width: 100%;
      padding: 8px 12px 8px 32px;
      background: #FFFFFF;
      border: 1px solid #D0D5DD;
      border-radius: 8px;
      font-size: 13px;
      color: #101828;
      outline: none;
      box-sizing: border-box;
      transition: all 0.15s ease;
    }
    .supitar-search-wrap input:focus {
      border-color: #2563EB;
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    /* Mini Date Picker */
    .supitar-mini-cal {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .supitar-mini-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0 4px;
    }
    .supitar-mini-month {
      font-size: 13px;
      font-weight: 700;
      color: #101828;
    }
    .supitar-mini-nav {
      background: transparent;
      border: none;
      color: #667085;
      cursor: pointer;
      font-size: 16px;
      font-weight: 600;
      padding: 0 4px;
      line-height: 1;
    }
    .supitar-mini-nav:hover { color: #101828; }

    .supitar-mini-weekdays {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      text-align: center;
      font-size: 11px;
      font-weight: 600;
      color: #98A2B3;
      margin-bottom: 2px;
    }

    .supitar-mini-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 2px;
      text-align: center;
    }
    .supitar-mini-cell {
      font-size: 11.5px;
      font-weight: 500;
      color: #344054;
      height: 26px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      cursor: pointer;
      transition: all 0.15s ease;
      user-select: none;
    }
    .supitar-mini-cell:hover {
      background: #F2F4F7;
    }
    .supitar-mini-cell.other-month {
      color: #D0D5DD;
    }
    .supitar-mini-cell.is-today {
      background: #EFF6FF;
      color: #2563EB;
      font-weight: 700;
      border: 1px solid #BFDBFE;
    }
    .supitar-mini-cell.is-selected {
      background: #2563EB !important;
      color: #FFFFFF !important;
      font-weight: 700;
      border: none !important;
    }

    /* My Calendars */
    .supitar-my-calendars {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .supitar-section-title {
      font-size: 13px;
      font-weight: 700;
      color: #101828;
    }
    .supitar-client-list {
      display: flex;
      flex-direction: column;
      gap: 8px;
      max-height: 200px;
      overflow-y: auto;
    }
    .supitar-client-item {
      display: flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
      font-size: 13px;
      color: #344054;
      font-weight: 500;
      user-select: none;
    }
    .supitar-client-item input[type="checkbox"] {
      width: 16px;
      height: 16px;
      border-radius: 4px;
      cursor: pointer;
    }
    .supitar-client-label {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .supitar-btn-add-cal {
      width: 100%;
      height: 40px;
      background: #2563EB;
      color: #FFFFFF;
      border: none;
      border-radius: 8px;
      font-size: 13.5px;
      font-weight: 600;
      cursor: pointer;
      margin-top: auto;
      box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
      transition: background 0.15s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }
    .supitar-btn-add-cal:hover {
      background: #1D4ED8;
    }

    /* Main Panel */
    .supitar-main-panel {
      flex: 1;
      background: #FFFFFF;
      padding: 24px;
      display: flex;
      flex-direction: column;
      gap: 16px;
      min-width: 0;
    }

    .supitar-topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
    }
    .supitar-topbar-left {
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .supitar-month-heading {
      font-size: 20px;
      font-weight: 700;
      color: #101828;
      margin: 0;
      letter-spacing: -0.2px;
    }
    .supitar-btn-today {
      background: #FFFFFF;
      border: 1px solid #D0D5DD;
      border-radius: 8px;
      height: 32px;
      padding: 0 12px;
      font-size: 12.5px;
      font-weight: 600;
      color: #344054;
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .supitar-btn-today:hover {
      background: #F9FAFB;
      border-color: #98A2B3;
    }
    .supitar-arrow-group {
      display: flex;
      align-items: center;
      gap: 4px;
    }
    .supitar-arrow-btn {
      background: transparent;
      border: none;
      color: #667085;
      font-size: 18px;
      font-weight: 600;
      cursor: pointer;
      padding: 0 6px;
      line-height: 1;
      border-radius: 4px;
    }
    .supitar-arrow-btn:hover { color: #101828; background: #F2F4F7; }

    .supitar-topbar-right {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .supitar-view-switcher {
      display: flex;
      background: #F2F4F7;
      padding: 3px;
      border-radius: 8px;
      gap: 2px;
    }
    .supitar-view-btn {
      background: transparent;
      border: none;
      font-size: 12.5px;
      font-weight: 600;
      color: #667085;
      padding: 5px 12px;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .supitar-view-btn.active {
      background: #FFFFFF;
      color: #101828;
      font-weight: 700;
      box-shadow: 0 1px 2px rgba(16, 24, 40, 0.08);
    }

    /* FullCalendar Overrides for Slide 3 Look */
    .supitar-fc-container {
      flex: 1;
    }
    .fc {
      font-family: inherit !important;
      --fc-border-color: #F2F4F7 !important;
      --fc-page-bg-color: #FFFFFF !important;
    }
    .fc-theme-standard th {
      border: 1px solid #F2F4F7 !important;
      background: #FFFFFF !important;
      padding: 10px 0 !important;
    }
    .fc-col-header-cell-cushion {
      color: #667085 !important;
      font-size: 12px !important;
      font-weight: 600 !important;
      text-decoration: none !important;
    }
    .fc-theme-standard td {
      border: 1px solid #F2F4F7 !important;
    }
    .fc-daygrid-day-top {
      flex-direction: row !important;
      padding: 6px 8px !important;
    }
    .fc-daygrid-day-number {
      color: #667085 !important;
      font-size: 12px !important;
      font-weight: 500 !important;
      text-decoration: none !important;
    }
    .fc-day-other .fc-daygrid-day-number {
      color: #D0D5DD !important;
    }

    /* Today Circle Indicator in FullCalendar */
    .fc-day-today .fc-daygrid-day-number {
      background: #2563EB !important;
      color: #FFFFFF !important;
      border-radius: 50% !important;
      width: 22px !important;
      height: 22px !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      font-weight: 700 !important;
      margin: 0 !important;
      padding: 0 !important;
    }

    /* Month View Event Strip Cards matching Slide 3 */
    .fc-daygrid-event {
      margin: 2px 4px !important;
      border-radius: 4px !important;
      padding: 2px 6px !important;
      font-size: 11px !important;
      font-weight: 600 !important;
      line-height: 1.3 !important;
      border: none !important;
      cursor: pointer !important;
      transition: all 0.15s ease !important;
      white-space: nowrap !important;
      overflow: hidden !important;
      text-overflow: ellipsis !important;
      box-shadow: 0 1px 2px rgba(0,0,0,0.03) !important;
    }
    .fc-daygrid-event:hover {
      transform: translateY(-1px) !important;
      box-shadow: 0 3px 6px rgba(0,0,0,0.08) !important;
      filter: brightness(0.97);
    }

    .supitar-event-badge {
      display: flex;
      align-items: center;
      gap: 5px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .supitar-event-bar {
      width: 2.5px;
      height: 11px;
      border-radius: 2px;
      flex-shrink: 0;
    }
    .supitar-event-text {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    /* TimeGrid (Day & Week View) Polished Styling */
    .fc-timegrid-slot {
      height: 48px !important;
      border-bottom: 1px dashed #F2F4F7 !important;
    }
    .fc-timegrid-slot-label-cushion {
      font-size: 11.5px !important;
      font-weight: 600 !important;
      color: #667085 !important;
      padding: 0 8px !important;
    }
    .fc-timegrid-col-header-cell {
      background: #FFFFFF !important;
      padding: 12px 0 !important;
      border-bottom: 1px solid #EAECF0 !important;
    }
    .fc-timegrid-col-header-cell .fc-col-header-cell-cushion {
      font-size: 14px !important;
      font-weight: 700 !important;
      color: #101828 !important;
    }
    .fc-timegrid-event {
      border-radius: 8px !important;
      box-shadow: 0 2px 4px rgba(16, 24, 40, 0.08) !important;
      padding: 8px 10px !important;
      margin: 2px !important;
      border-left-width: 4px !important;
      border-top: none !important;
      border-right: none !important;
      border-bottom: none !important;
      cursor: pointer !important;
      transition: all 0.15s ease !important;
      box-sizing: border-box !important;
    }
    .fc-timegrid-event:hover {
      transform: translateY(-1px) scale(1.01) !important;
      box-shadow: 0 6px 12px rgba(16, 24, 40, 0.12) !important;
    }
    .supitar-event-timegrid-inner {
      display: flex;
      flex-direction: column;
      gap: 3px;
      height: 100%;
      overflow: hidden;
    }
  </style>
</head>
<body>
  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body" style="padding: 24px;">

        <!-- Supitar Calendar Frame (1:1 Match with Slide 3 Reference) -->
        <div class="supitar-calendar-card">
          
          <!-- LEFT SIDEBAR -->
          <div class="supitar-sidebar">
            <div class="supitar-sidebar-header">
              <h3 class="supitar-title">Kalender Konten</h3>
              <button type="button" class="supitar-icon-btn" title="Sembunyikan Sidebar">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
              </button>
            </div>

            <!-- Search Event -->
            <div class="supitar-search-wrap">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#98A2B3" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input type="text" id="cal-search-input" placeholder="Cari jadwal konten..." oninput="onSearchContentInput(this.value)">
            </div>

            <!-- Mini Month Calendar -->
            <div class="supitar-mini-cal">
              <div class="supitar-mini-header">
                <button type="button" class="supitar-mini-nav" onclick="prevMiniCalMonth()">‹</button>
                <span id="mini-cal-month-title" class="supitar-mini-month">Agustus 2026</span>
                <button type="button" class="supitar-mini-nav" onclick="nextMiniCalMonth()">›</button>
              </div>
              <div class="supitar-mini-weekdays">
                <span>M</span><span>S</span><span>S</span><span>R</span><span>K</span><span>J</span><span>S</span>
              </div>
              <div class="supitar-mini-grid" id="mini-cal-days-grid">
                <!-- Rendered dynamically -->
              </div>
            </div>

            <!-- My Calendars (Clients Checklist) -->
            <div class="supitar-my-calendars">
              <div class="supitar-section-title">Daftar Klien</div>
              <div class="supitar-client-list">
                <?php foreach ($clients as $c): ?>
                  <label class="supitar-client-item" data-client-id="<?= $c['id'] ?>">
                    <input type="checkbox" class="client-checkbox" value="<?= $c['id'] ?>" checked onchange="onClientCheckboxChange()" style="accent-color: <?= $c['color_hex'] ?>;">
                    <span class="supitar-client-label" title="<?= htmlspecialchars($c['company']) ?>"><?= htmlspecialchars($c['company']) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- + Add Event Button (Slide 3 Blue CTA) -->
            <button type="button" class="supitar-btn-add-cal" onclick="openCreateContentModal()">
              + Tambah Jadwal
            </button>
          </div>

          <!-- MAIN CALENDAR PANEL -->
          <div class="supitar-main-panel">
            
            <!-- Top Toolbar -->
            <div class="supitar-topbar">
              <div class="supitar-topbar-left">
                <h2 id="cal-header-title" class="supitar-month-heading">Agustus 2026</h2>
                <button type="button" class="supitar-btn-today" onclick="calGoToday()">Hari Ini</button>
                <div class="supitar-arrow-group">
                  <button type="button" class="supitar-arrow-btn" onclick="calPrev()">‹</button>
                  <button type="button" class="supitar-arrow-btn" onclick="calNext()">›</button>
                </div>
              </div>

              <div class="supitar-topbar-right">
                <!-- Export PDF Action Button with Filter Modal -->
                <button type="button" class="supitar-btn-today" onclick="openExportContentModal()" style="display: inline-flex; align-items: center; gap: 6px;" title="Ekspor Jadwal Konten ke PDF">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                  <span>Ekspor PDF</span>
                </button>

                <!-- Segmented View Switcher -->
                <div class="supitar-view-switcher">
                  <button type="button" class="supitar-view-btn" id="btn-view-day" onclick="calChangeView('timeGridDay', this)">Harian</button>
                  <button type="button" class="supitar-view-btn" id="btn-view-week" onclick="calChangeView('timeGridWeek', this)">Mingguan</button>
                  <button type="button" class="supitar-view-btn active" id="btn-view-month" onclick="calChangeView('dayGridMonth', this)">Bulanan</button>
                </div>
              </div>
            </div>

            <!-- FullCalendar Container -->
            <div id="calendar" class="supitar-fc-container"></div>

          </div>

        </div>

      </div>
    </main>
  </div>

  <?php require_once BASE_PATH . '/includes/modals.php'; ?>

  <!-- Scripts -->
  <script src="assets/js/app.js?v=<?= time() ?>"></script>
  <script>
  let fullCalInstance = null;
  let currentMiniDate = new Date();
  let searchDebounceTimer = null;

  document.addEventListener('DOMContentLoaded', function() {
    initFullCalendar();
    renderMiniCalendar(currentMiniDate);
  });

  function initFullCalendar() {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl || typeof FullCalendar === 'undefined') return;

    fullCalInstance = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      headerToolbar: false, // Use our custom Slide 3 topbar
      locale: 'en',
      firstDay: 0, // Sunday first day like Slide 3
      editable: true,
      droppable: true,
      selectable: true,
      dayMaxEvents: 4,
      height: 'auto',
      navLinks: true,

      // TimeGrid Day / Week settings
      slotMinTime: '07:00:00',
      slotMaxTime: '22:00:00',
      slotDuration: '01:00:00',
      slotLabelInterval: '01:00:00',
      allDaySlot: false,
      expandRows: true,
      dayHeaderFormat: { weekday: 'long', month: 'short', day: 'numeric', omitCommas: true },
      slotLabelFormat: {
        hour: 'numeric',
        minute: '2-digit',
        omitZeroMinute: true,
        meridiem: 'short',
        hour12: true
      },

      // Lazy load events with start and end
      events: function(info, successCallback, failureCallback) {
        const selectedClientIds = getSelectedClientIds();
        const search = document.getElementById('cal-search-input')?.value || '';

        const url = new URL('api/content.php', window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1));
        url.searchParams.append('action', 'get_events');
        url.searchParams.append('start', info.startStr);
        url.searchParams.append('end', info.endStr);
        if (selectedClientIds.length > 0) {
          url.searchParams.append('client_ids', selectedClientIds.join(','));
        } else {
          url.searchParams.append('client_ids', 'none');
        }
        if (search) url.searchParams.append('search', search);

        fetch(url)
          .then(res => res.json())
          .then(data => {
            successCallback(data);
          })
          .catch(err => {
            console.error('Error fetching calendar events:', err);
            failureCallback(err);
          });
      },

      // Event rendering (Adaptive for Month vs Day/Week View)
      eventContent: function(arg) {
        const p = arg.event.extendedProps;
        const barColor = p.theme ? p.theme.border : (p.color_hex || '#2563EB');
        const title = arg.event.title || 'Event';

        if (arg.view.type === 'dayGridMonth') {
          // Slide 3 Strip Pill Card
          const wrapper = document.createElement('div');
          wrapper.className = 'supitar-event-badge';
          wrapper.innerHTML = `
            <span class="supitar-event-bar" style="background: ${barColor};"></span>
            <span class="supitar-event-text" title="${escapeHtml(title)}">${escapeHtml(title)}</span>
          `;
          return { domNodes: [wrapper] };
        } else {
          // Day / Week TimeGrid Rich Card
          const wrapper = document.createElement('div');
          wrapper.className = 'supitar-event-timegrid-inner';
          wrapper.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <span style="font-size: 10px; font-weight: 700; background: ${barColor}; color: #FFFFFF; padding: 1px 5px; border-radius: 4px;">${escapeHtml(p.platform || 'Post')}</span>
              <span style="font-size: 10px; font-weight: 600; color: #475467;">${escapeHtml(p.publish_time || '')}</span>
            </div>
            <div style="font-size: 12px; font-weight: 700; color: #101828; line-height: 1.3; margin: 3px 0;">${escapeHtml(title)}</div>
            <div style="font-size: 11px; font-weight: 600; color: ${barColor};">${escapeHtml(p.client_company || p.client_name || '')}</div>
            <div style="font-size: 10px; color: #667085; margin-top: auto;">PIC: ${escapeHtml(p.assignee_name || '-')} • <strong style="color:#101828;">${escapeHtml(p.status || 'Draft')}</strong></div>
          `;
          return { domNodes: [wrapper] };
        }
      },

      // Drag & Drop Date Reschedule
      eventDrop: async function(info) {
        const eventId = info.event.id;
        const newDate = info.event.startStr.split('T')[0];
        const newTime = info.event.startStr.split('T')[1] ? info.event.startStr.split('T')[1].substring(0, 5) : '';

        const formData = new FormData();
        formData.append('id', eventId);
        formData.append('publish_date', newDate);
        if (newTime) formData.append('publish_time', newTime);

        try {
          const res = await fetch('api/content.php?action=update_date', {
            method: 'POST',
            body: formData
          });
          const result = await res.json();
          if (result.success) {
            showToast(result.message || 'Event rescheduled!', 'success');
          } else {
            showToast(result.message || 'Failed to reschedule', 'danger');
            info.revert();
          }
        } catch (err) {
          showToast('Connection error', 'danger');
          info.revert();
        }
      },

      // Click Date Cell -> Add Event on that date & time
      dateClick: function(info) {
        const dateStr = info.dateStr.split('T')[0];
        const timeStr = info.dateStr.split('T')[1] ? info.dateStr.split('T')[1].substring(0, 5) : '10:00';
        openCreateContentModal(dateStr, timeStr);
      },

      // Click Event -> Edit Modal
      eventClick: function(info) {
        info.jsEvent.preventDefault();
        openEditContentModal(info.event.id);
      },

      // Date / Month / View changes sync
      datesSet: function(info) {
        updateCustomHeaderTitle(info.view.currentStart, info.view.type);
        currentMiniDate = new Date(info.view.currentStart);
        renderMiniCalendar(currentMiniDate);
        
        // Sync active view switcher button
        document.querySelectorAll('.supitar-view-btn').forEach(b => b.classList.remove('active'));
        if (info.view.type === 'timeGridDay') {
          document.getElementById('btn-view-day')?.classList.add('active');
        } else if (info.view.type === 'timeGridWeek') {
          document.getElementById('btn-view-week')?.classList.add('active');
        } else {
          document.getElementById('btn-view-month')?.classList.add('active');
        }
      }
    });

    fullCalInstance.render();
  }

  function calGoToday() {
    if (fullCalInstance) {
      fullCalInstance.today();
      currentMiniDate = new Date();
      renderMiniCalendar(currentMiniDate);
    }
  }

  function calPrev() {
    if (fullCalInstance) fullCalInstance.prev();
  }

  function calNext() {
    if (fullCalInstance) fullCalInstance.next();
  }

  function calChangeView(viewName, btn) {
    if (!fullCalInstance) return;
    fullCalInstance.changeView(viewName);
    document.querySelectorAll('.supitar-view-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
  }

  function updateCustomHeaderTitle(dateObj, viewType = 'dayGridMonth') {
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const monthName = months[dateObj.getMonth()];
    const year = dateObj.getFullYear();
    const titleEl = document.getElementById('cal-header-title');
    if (titleEl) {
      if (viewType === 'timeGridDay') {
        titleEl.textContent = `${dateObj.getDate()} ${monthName} ${year}`;
      } else {
        titleEl.textContent = `${monthName} ${year}`;
      }
    }
  }

  window.refreshContentCalendar = function() {
    if (fullCalInstance) {
      fullCalInstance.refetchEvents();
    }
  };

  function onSearchContentInput(val) {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
      window.refreshContentCalendar();
    }, 300);
  }

  function getSelectedClientIds() {
    const checkboxes = document.querySelectorAll('.client-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
  }

  function onClientCheckboxChange() {
    window.refreshContentCalendar();
  }

  function renderMiniCalendar(baseDate) {
    const grid = document.getElementById('mini-cal-days-grid');
    const title = document.getElementById('mini-cal-month-title');
    if (!grid || !title) return;

    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    title.textContent = `${months[baseDate.getMonth()]} ${baseDate.getFullYear()}`;

    grid.innerHTML = '';

    const year = baseDate.getFullYear();
    const month = baseDate.getMonth();

    const firstDayIndex = new Date(year, month, 1).getDay();
    const lastDate = new Date(year, month + 1, 0).getDate();
    const prevLastDate = new Date(year, month, 0).getDate();

    const today = new Date();
    const isCurrentMonth = (today.getFullYear() === year && today.getMonth() === month);

    // Prev month days
    for (let x = firstDayIndex; x > 0; x--) {
      const cell = document.createElement('div');
      cell.className = 'supitar-mini-cell other-month';
      const dayNum = prevLastDate - x + 1;
      cell.textContent = dayNum;
      
      const prevDate = new Date(year, month - 1, dayNum);
      cell.addEventListener('click', () => {
        selectMiniCalendarDate(prevDate);
      });
      grid.appendChild(cell);
    }

    // Current month days
    for (let i = 1; i <= lastDate; i++) {
      const cell = document.createElement('div');
      cell.className = 'supitar-mini-cell';
      cell.textContent = i;

      if (isCurrentMonth && today.getDate() === i) {
        cell.classList.add('is-today');
      }

      const cellDate = new Date(year, month, i);
      cell.addEventListener('click', () => {
        document.querySelectorAll('.supitar-mini-cell').forEach(c => c.classList.remove('is-selected'));
        cell.classList.add('is-selected');
        selectMiniCalendarDate(cellDate);
      });

      grid.appendChild(cell);
    }
  }

  function selectMiniCalendarDate(targetDate) {
    if (!fullCalInstance) return;
    fullCalInstance.gotoDate(targetDate);
    // If in Day view, it directly navigates to that day; if in Month view, it shifts to that date
    showToast(`Memilih tanggal ${targetDate.getDate()} ${['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'][targetDate.getMonth()]} ${targetDate.getFullYear()}`, 'info');
  }

  function prevMiniCalMonth() {
    currentMiniDate.setMonth(currentMiniDate.getMonth() - 1);
    renderMiniCalendar(currentMiniDate);
    if (fullCalInstance) fullCalInstance.gotoDate(currentMiniDate);
  }

  function nextMiniCalMonth() {
    currentMiniDate.setMonth(currentMiniDate.getMonth() + 1);
    renderMiniCalendar(currentMiniDate);
    if (fullCalInstance) fullCalInstance.gotoDate(currentMiniDate);
  }

  // Open Export Modal with prefilled date/month
  window.openExportContentModal = function() {
    const monthInput = document.getElementById('export-month-input');
    const startInput = document.getElementById('export-start-date');
    const endInput = document.getElementById('export-end-date');

    if (fullCalInstance && fullCalInstance.view) {
      const currentStart = fullCalInstance.view.currentStart;
      const yyyy = currentStart.getFullYear();
      const mm = String(currentStart.getMonth() + 1).padStart(2, '0');
      if (monthInput) monthInput.value = `${yyyy}-${mm}`;

      if (startInput && endInput && fullCalInstance.view.activeStart && fullCalInstance.view.activeEnd) {
        startInput.value = fullCalInstance.view.activeStart.toISOString().split('T')[0];
        // activeEnd is exclusive in FullCalendar, so take day before or exact
        const endDateObj = new Date(fullCalInstance.view.activeEnd.getTime() - 86400000);
        endInput.value = endDateObj.toISOString().split('T')[0];
      }
    }
    openModal('modal-export-content-pdf');
  };

  function escapeHtml(text) {
    if (!text) return '';
    return String(text)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }
  </script>
</body>
</html>

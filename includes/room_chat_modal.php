<?php
/**
 * Room Chat Diskusi Per Pekerjaan (WhatsApp / Purple Chat App - 3 Pihak)
 * Client + Karyawan/Freelancer + Owner/Admin (Otomatis Masuk Memantau)
 * Sesuai Desain Modern Referensi Gambar 3
 */
$chatUser = current_user();
$chatRole = current_user_role();
$chatUserId = (int)($chatUser['id'] ?? 0);
$chatUserName = htmlspecialchars($chatUser['name'] ?? 'User');
?>

<!-- 1. FLOATING CHAT BUTTON (FAB) -->
<button type="button" id="btn-floating-room-chat" class="kala-floating-chat-fab" onclick="KalaRoomChat.openInbox()" aria-label="Buka Room Chat Diskusi">
  <div class="fab-icon-wrap">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
    </svg>
    <span id="kala-chat-fab-badge" class="fab-badge" style="display: none;">0</span>
  </div>
</button>

<!-- 2. ROOM CHAT MODAL OVERLAY & CONTAINER (DRAWER / FULL SCREEN MOBILE) -->
<div id="kala-room-chat-modal" class="kala-chat-overlay" style="display: none;">
  <div class="kala-chat-backdrop" onclick="KalaRoomChat.close()"></div>

  <div class="kala-chat-dialog" id="kala-chat-dialog">
    
    <!-- ========================================== -->
    <!-- VIEW A: DAFTAR ROOM CHAT / INBOX DISKUSI   -->
    <!-- (Persis Gambar 3 Layar Kiri: All Message)   -->
    <!-- ========================================== -->
    <div id="kala-chat-inbox-view" class="kala-chat-view-panel active">
      <!-- Inbox Header (Purple Modern) -->
      <div class="chat-inbox-header">
        <div class="chat-inbox-header-top">
          <div>
            <div class="chat-inbox-greeting">Halo, <?= $chatUserName ?>!</div>
            <h3 class="chat-inbox-title" id="chat-inbox-summary-title">Room Diskusi Pekerjaan</h3>
          </div>
          <div style="display: flex; align-items: center; gap: 8px;">
            <button type="button" class="chat-header-btn" onclick="KalaRoomChat.refreshInbox()" title="Segarkan Obrolan">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
            </button>
            <button type="button" class="chat-header-btn" onclick="KalaRoomChat.close()" title="Tutup Chat">
              &times;
            </button>
          </div>
        </div>

        <!-- Search Box In Inbox -->
        <div class="chat-search-bar">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="2.2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
          <input type="text" id="chat-inbox-search" placeholder="Cari nama tugas, PT klien, atau PIC..." oninput="KalaRoomChat.filterInbox()">
        </div>

        <!-- Segmented Tab: Semua | Belum Dibaca | Revisi -->
        <div class="chat-inbox-tabs">
          <button type="button" class="chat-tab-pill active" onclick="KalaRoomChat.switchInboxFilter('all', this)">
            Semua Room
          </button>
          <button type="button" class="chat-tab-pill" onclick="KalaRoomChat.switchInboxFilter('unread', this)">
            Belum Dibaca <span id="chat-tab-unread-count" class="chat-pill-badge" style="display: none;">0</span>
          </button>
          <button type="button" class="chat-tab-pill" onclick="KalaRoomChat.switchInboxFilter('revision', this)">
            Ada Revisi
          </button>
        </div>
      </div>

      <!-- Quick Contact Avatars Carousel (Gambar 3 Contact List) -->
      <div class="chat-contacts-strip" id="chat-contacts-strip">
        <!-- Injected via JS -->
      </div>

      <!-- Room Chat List (Scrollable) -->
      <div class="chat-inbox-list" id="chat-inbox-list">
        <div class="chat-loading-state">
          <div class="chat-spinner"></div>
          <span>Memuat daftar room chat...</span>
        </div>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- VIEW B: ROOM CHAT DISKUSI PER PEKERJAAN     -->
    <!-- (Persis Gambar 3 Layar Tengah & Kanan)     -->
    <!-- ========================================== -->
    <div id="kala-chat-room-view" class="kala-chat-view-panel">
      <!-- Header Room Chat Ungu -->
      <div class="chat-room-header">
        <div class="chat-header-left">
          <button type="button" class="chat-back-btn" onclick="KalaRoomChat.backToInbox()" title="Kembali ke Inbox">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
              <line x1="19" y1="12" x2="5" y2="12"></line>
              <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
          </button>
          <div class="chat-header-info">
            <h4 class="chat-task-title" id="room-header-title">Judul Pekerjaan</h4>
            <div class="chat-task-subtitle">
              <span id="room-header-company">PT Klien</span>
              <span class="chat-dot-sep">&bull;</span>
              <span id="room-header-members">Client, PIC &amp; Owner</span>
              <span class="chat-online-pill"><span class="chat-online-dot"></span> Online</span>
            </div>
          </div>
        </div>

        <div class="chat-header-actions">
          <a id="room-header-preview-btn" href="#" target="_blank" class="chat-preview-link" title="Buka Link Preview Hasil Kerja" style="display: none;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            <span>Preview</span>
          </a>
          <button type="button" class="chat-header-close-btn" onclick="KalaRoomChat.close()" title="Tutup Room">
            &times;
          </button>
        </div>
      </div>

      <!-- Scope / Member Monitoring Banner -->
      <div class="chat-monitor-banner">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#5B3DF5" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
        <span>Room ini dipantau langsung oleh <b>Owner Kalamedia</b>, <b>PIC Tim</b>, dan <b>Klien</b>.</span>
      </div>

      <!-- Area Bubble Percakapan -->
      <div class="chat-messages-container" id="chat-messages-container">
        <!-- Injected via JS -->
      </div>

      <!-- Input Bar Chat di Bawah -->
      <div class="chat-input-bar">
        <!-- Tag Selector (Optional) -->
        <div class="chat-tag-selector">
          <button type="button" class="chat-tag-btn active" data-type="comment" onclick="KalaRoomChat.selectTag('comment', this)">
            💬 Chat Diskusi
          </button>
          <button type="button" class="chat-tag-btn" data-type="revision_request" onclick="KalaRoomChat.selectTag('revision_request', this)">
            ⚠️ Request Revisi
          </button>
          <button type="button" class="chat-tag-btn" data-type="approval" onclick="KalaRoomChat.selectTag('approval', this)">
            ✓ Setujui (Approve)
          </button>
        </div>

        <!-- Attachment Preview Bar (Shown when a file is chosen) -->
        <div id="chat-attachment-preview-bar" class="chat-attachment-preview-bar" style="display: none;">
          <div class="att-preview-left">
            <span class="att-preview-icon">📎</span>
            <span id="att-preview-name" class="att-preview-name">foto_revisi.png</span>
          </div>
          <button type="button" class="att-preview-remove" onclick="KalaRoomChat.clearAttachment()">&times;</button>
        </div>

        <!-- Main Input Row -->
        <form id="form-chat-send" onsubmit="KalaRoomChat.sendMessage(event)" class="chat-input-row" enctype="multipart/form-data">
          <input type="hidden" id="chat-active-task-id" name="task_id" value="">
          <input type="hidden" id="chat-active-feedback-type" name="feedback_type" value="comment">
          
          <input type="file" id="chat-file-input" name="attachment" accept="image/*,application/pdf" style="display: none;" onchange="KalaRoomChat.handleFileChosen(event)">

          <button type="button" class="chat-attach-btn" onclick="document.getElementById('chat-file-input').click()" title="Lampirkan Gambar atau Bukti Revisi">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6D7185" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
          </button>

          <div class="chat-input-wrapper">
            <input type="text" id="chat-text-input" name="message" class="chat-text-input" placeholder="Ketik pesan diskusi kerjaan..." autocomplete="off">
          </div>

          <button type="submit" id="chat-send-btn" class="chat-send-btn" title="Kirim Pesan">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
              <line x1="22" y1="2" x2="11" y2="13"></line>
              <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
            </svg>
          </button>
        </form>
      </div>
    </div>

  </div>
</div>

<!-- 3. LIGHTBOX MODAL UNTUK PERBESAR GAMBAR -->
<div id="kala-chat-lightbox" class="kala-chat-lightbox" style="display: none;" onclick="KalaRoomChat.closeLightbox()">
  <button type="button" class="lightbox-close" onclick="KalaRoomChat.closeLightbox()">&times;</button>
  <img id="kala-chat-lightbox-img" src="" alt="Pratinjau Gambar" onclick="event.stopPropagation()">
</div>

<style>
/* ==========================================================================
   KALA ROOM CHAT SYSTEM (WHATSAPP PURPLE MODERN - GAMBAR 3)
   ========================================================================== */

/* Floating Action Button (FAB) */
.kala-floating-chat-fab {
  position: fixed;
  bottom: 84px;
  right: 18px;
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: linear-gradient(135deg, #5B3DF5 0%, #6D4AFF 100%);
  color: #FFFFFF;
  border: none;
  box-shadow: 0 8px 24px rgba(91, 61, 245, 0.38), 0 2px 6px rgba(91, 61, 245, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 9980;
  transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s ease;
  -webkit-tap-highlight-color: transparent;
}
.kala-floating-chat-fab:hover {
  transform: scale(1.06);
  box-shadow: 0 10px 28px rgba(91, 61, 245, 0.45);
}
.kala-floating-chat-fab:active {
  transform: scale(0.94);
}
.fab-icon-wrap {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
}
.fab-badge {
  position: absolute;
  top: -8px;
  right: -10px;
  background: #EF4444;
  color: #FFFFFF;
  font-size: 10px;
  font-weight: 800;
  padding: 1px 6px;
  border-radius: 999px;
  border: 2px solid #FFFFFF;
  line-height: 1.2;
}

/* Overlay & Backdrop */
.kala-chat-overlay {
  position: fixed;
  inset: 0;
  z-index: 10050;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: kalaChatFadeIn 0.22s ease-out;
}
.kala-chat-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(15, 13, 29, 0.55);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
}

/* Modal Dialog Box */
.kala-chat-dialog {
  position: relative;
  width: 940px;
  max-width: 95vw;
  height: 650px;
  max-height: 90vh;
  background: #FFFFFF;
  border-radius: 24px;
  box-shadow: 0 24px 60px rgba(18, 14, 46, 0.25), 0 8px 24px rgba(0, 0, 0, 0.08);
  display: flex;
  overflow: hidden;
  z-index: 10051;
  animation: kalaChatSlideUp 0.26s cubic-bezier(0.16, 1, 0.3, 1);
}

/* Split View on Desktop (WhatsApp Web layout) */
@media (min-width: 820px) {
  #kala-chat-inbox-view {
    width: 350px;
    min-width: 350px;
    max-width: 350px;
    border-right: 1px solid #ECEEF5;
    display: flex !important;
  }
  #kala-chat-room-view {
    flex: 1;
    min-width: 0;
    display: flex !important;
  }
  .chat-back-btn {
    display: none !important;
  }
}

/* Full-Screen Drawer on Mobile Screens (<= 819px) */
@media (max-width: 819px) {
  .kala-chat-overlay {
    padding: 0;
  }
  .kala-chat-dialog {
    width: 100vw !important;
    max-width: 100vw !important;
    height: 100% !important;
    max-height: 100% !important;
    border-radius: 0 !important;
  }
  .kala-chat-view-panel {
    display: none !important;
    width: 100% !important;
    height: 100% !important;
  }
  .kala-chat-view-panel.active {
    display: flex !important;
  }
  .kala-floating-chat-fab {
    bottom: 84px;
    right: 14px;
    width: 48px;
    height: 48px;
  }
}

.kala-chat-view-panel {
  display: flex;
  flex-direction: column;
  height: 100%;
  background: #FFFFFF;
}

/* ==========================================
   INBOX / ALL MESSAGE STYLES (GAMBAR 3 LAYAR 1)
   ========================================== */
.chat-inbox-header {
  background: #FFFFFF;
  border-bottom: 1px solid #ECEEF5;
  padding: 16px 18px 14px;
  color: #0F172A;
}
.chat-inbox-header-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}
.chat-inbox-greeting {
  font-size: 12px;
  font-weight: 500;
  color: #64748B;
}
.chat-inbox-title {
  font-size: 17px;
  font-weight: 800;
  color: #0F172A;
  margin: 2px 0 0;
  letter-spacing: -0.3px;
}
.chat-header-btn {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
  color: #334155;
  font-size: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}
.chat-header-btn:hover {
  background: #F1F5F9;
  color: #0F172A;
  border-color: #CBD5E1;
}

.chat-search-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
  border-radius: 12px;
  padding: 8px 14px;
  margin-bottom: 12px;
  transition: all 0.15s ease;
}
.chat-search-bar:focus-within {
  background: #FFFFFF;
  border-color: #5B3DF5;
  box-shadow: 0 0 0 3px rgba(91, 61, 245, 0.12);
}
.chat-search-bar input {
  background: transparent;
  border: none;
  color: #0F172A;
  font-size: 12.5px;
  width: 100%;
  outline: none;
  font-family: inherit;
}
.chat-search-bar input::placeholder {
  color: #94A3B8;
}

.chat-inbox-tabs {
  display: flex;
  gap: 4px;
  background: #F1F5F9;
  padding: 4px;
  border-radius: 12px;
  overflow-x: auto;
  scrollbar-width: none;
}
.chat-inbox-tabs::-webkit-scrollbar { display: none; }
.chat-tab-pill {
  background: transparent;
  border: none;
  color: #64748B;
  font-size: 12px;
  font-weight: 600;
  padding: 6px 14px;
  border-radius: 8px;
  cursor: pointer;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  transition: all 0.15s ease;
  font-family: inherit;
}
.chat-tab-pill:hover:not(.active) {
  color: #0F172A;
  background: rgba(255, 255, 255, 0.6);
}
.chat-tab-pill.active {
  background: #FFFFFF;
  color: #5B3DF5;
  font-weight: 700;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}
.chat-pill-badge {
  background: #EF4444;
  color: #FFFFFF;
  font-size: 9.5px;
  padding: 1px 5px;
  border-radius: 999px;
  font-weight: 700;
}

/* Contacts horizontal avatar strip */
.chat-contacts-strip {
  display: flex;
  gap: 12px;
  padding: 12px 16px;
  overflow-x: auto;
  scrollbar-width: none;
  border-bottom: 1px solid #F0F1F7;
  background: #FAFBFC;
}
.chat-contacts-strip::-webkit-scrollbar { display: none; }
.chat-contact-chip {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  cursor: pointer;
  flex-shrink: 0;
  max-width: 58px;
  text-align: center;
}
.chat-contact-avatar-wrap {
  position: relative;
  width: 40px;
  height: 40px;
}
.chat-contact-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #E0DBFD;
  color: #5B3DF5;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 800;
  border: 2px solid #FFFFFF;
  box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}
.chat-contact-name {
  font-size: 10.5px;
  color: #475467;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  width: 100%;
}

/* Room Items in Inbox */
.chat-inbox-list {
  flex: 1;
  overflow-y: auto;
  padding: 8px 10px;
}
.chat-room-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 12px;
  border-radius: 14px;
  cursor: pointer;
  transition: background 0.15s ease;
  position: relative;
  margin-bottom: 3px;
}
.chat-room-item:hover {
  background: #F7F8FC;
}
.chat-room-item.active {
  background: #F2EFFF;
}
.chat-room-avatar-wrap {
  position: relative;
  flex-shrink: 0;
}
.chat-room-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: linear-gradient(135deg, #E0DBFD 0%, #D1FADF 100%);
  color: #5B3DF5;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  font-weight: 800;
}
.chat-online-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: #10B981;
  border: 2px solid #FFFFFF;
  position: absolute;
  bottom: 0;
  right: 0;
}
.chat-room-content {
  flex: 1;
  min-width: 0;
}
.chat-room-row-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 2px;
}
.chat-room-title {
  font-size: 13px;
  font-weight: 700;
  color: #1A162B;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.chat-room-time {
  font-size: 10.5px;
  color: #98A2B3;
  flex-shrink: 0;
  margin-left: 6px;
}
.chat-room-client {
  font-size: 11px;
  color: #7B7890;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-bottom: 2px;
}
.chat-room-row-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.chat-room-snippet {
  font-size: 11.5px;
  color: #667085;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
}
.chat-room-badge {
  background: #5B3DF5;
  color: #FFFFFF;
  font-size: 10px;
  font-weight: 800;
  min-width: 18px;
  height: 18px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 5px;
  flex-shrink: 0;
}
.chat-room-badge.has-revision {
  background: #EF4444;
}

/* ==========================================
   ROOM CHAT CONVERSATION VIEW (GAMBAR 3 LAYAR 2)
   ========================================== */
.chat-room-header {
  background: #FFFFFF;
  border-bottom: 1px solid #ECEEF5;
  padding: 13px 16px;
  color: #0F172A;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
  border-top-left-radius: 20px;
  border-top-right-radius: 20px;
}
@media (max-width: 819px) {
  .chat-room-header {
    border-radius: 0;
  }
}
.chat-header-left {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  flex: 1;
}
.chat-back-btn {
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
  color: #334155;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
  transition: all 0.15s ease;
}
.chat-back-btn:hover {
  background: #F1F5F9;
  color: #0F172A;
  border-color: #CBD5E1;
}
.chat-header-info {
  min-width: 0;
  flex: 1;
}
.chat-task-title {
  font-size: 14.5px;
  font-weight: 800;
  color: #0F172A;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  letter-spacing: -0.2px;
}
.chat-task-subtitle {
  font-size: 11px;
  color: #64748B;
  margin-top: 2px;
  display: flex;
  align-items: center;
  gap: 5px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.chat-dot-sep { color: #CBD5E1; }
.chat-online-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #ECFDF5;
  color: #059669;
  font-weight: 700;
  font-size: 10.5px;
  padding: 1px 7px;
  border-radius: 999px;
  margin-left: 4px;
}
.chat-online-pill .chat-online-dot {
  position: static;
  width: 6px;
  height: 6px;
  background: #059669;
}

.chat-header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}
.chat-preview-link {
  height: 32px;
  padding: 0 10px;
  border-radius: 8px;
  background: #EEF2FF;
  border: 1px solid #E0E7FF;
  color: #4F46E5;
  font-size: 11.5px;
  font-weight: 700;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  transition: all 0.15s ease;
}
.chat-preview-link:hover {
  background: #E0E7FF;
  color: #4338CA;
}
.chat-header-close-btn {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
  color: #334155;
  font-size: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}
.chat-header-close-btn:hover {
  background: #F1F5F9;
  color: #0F172A;
  border-color: #CBD5E1;
}

.chat-monitor-banner {
  background: #F8FAFC;
  border-bottom: 1px solid #ECEEF5;
  padding: 7px 16px;
  font-size: 11.5px;
  color: #4F46E5;
  display: flex;
  align-items: center;
  gap: 7px;
  flex-shrink: 0;
}

/* Conversation Body */
.chat-messages-container {
  flex: 1;
  overflow-y: auto;
  background: #F7F8FC;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.chat-date-pill {
  align-self: center;
  background: #EBECEF;
  color: #64748B;
  font-size: 10.5px;
  font-weight: 700;
  padding: 3px 12px;
  border-radius: 999px;
  margin: 4px 0;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

/* Message Bubble Rows */
.chat-msg-row {
  display: flex;
  gap: 8px;
  max-width: 85%;
  position: relative;
}
.chat-msg-row.self {
  align-self: flex-end;
  justify-content: flex-end;
}
.chat-msg-row.other {
  align-self: flex-start;
  justify-content: flex-start;
}

.chat-msg-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #E0DBFD;
  color: #5B3DF5;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11.5px;
  font-weight: 800;
  flex-shrink: 0;
  margin-top: 2px;
}
.chat-msg-avatar.owner {
  background: #1E1B2E;
  color: #FFFFFF;
}
.chat-msg-avatar.client {
  background: #D1FADF;
  color: #027A48;
}

.chat-msg-bubble {
  padding: 10px 14px;
  font-size: 13.5px;
  line-height: 1.45;
  position: relative;
  word-break: break-word;
}

/* Self Bubble (Kanan - Ungu #5B3DF5) */
.chat-msg-row.self .chat-msg-bubble {
  background: #5B3DF5;
  color: #FFFFFF;
  border-radius: 16px 16px 4px 16px;
  box-shadow: 0 3px 10px rgba(91, 61, 245, 0.22);
}
.chat-msg-row.self .chat-msg-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 4px;
  margin-top: 4px;
  font-size: 10.5px;
  color: rgba(255, 255, 255, 0.75);
}

/* Other Bubble (Kiri - Putih #FFFFFF) */
.chat-msg-row.other .chat-msg-bubble {
  background: #FFFFFF;
  color: #1A162B;
  border-radius: 16px 16px 16px 4px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  border: 1px solid #ECEEF5;
}
.chat-msg-header {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
}
.chat-sender-name {
  font-size: 11.5px;
  font-weight: 800;
  color: #1A162B;
}
.chat-role-badge {
  font-size: 9.5px;
  font-weight: 800;
  padding: 2px 6px;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}
.chat-role-badge.owner {
  background: #16141F;
  color: #FFFFFF;
}
.chat-role-badge.client {
  background: #E8F8F2;
  color: #10B981;
}
.chat-role-badge.worker {
  background: #F2EFFF;
  color: #5B3DF5;
}

.chat-msg-row.other .chat-msg-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 4px;
  margin-top: 4px;
  font-size: 10.5px;
  color: #98A2B3;
}

/* Special Message Type Banners */
.chat-feedback-type-banner {
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  margin-bottom: 6px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.chat-feedback-type-banner.revision {
  background: #FEE4E2;
  color: #B42318;
}
.chat-feedback-type-banner.approval {
  background: #D1FADF;
  color: #027A48;
}

/* Image Attachment inside Bubble */
.chat-msg-image-wrap {
  margin: 6px 0;
  cursor: pointer;
}
.chat-msg-image-thumb {
  max-width: 240px;
  max-height: 200px;
  border-radius: 12px;
  display: block;
  object-fit: cover;
  transition: transform 0.15s ease;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.chat-msg-image-thumb:hover {
  transform: scale(1.02);
}
.chat-msg-doc-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border-radius: 8px;
  background: rgba(0,0,0,0.05);
  font-size: 12px;
  font-weight: 600;
  text-decoration: underline;
  margin-top: 4px;
}
.chat-msg-row.self .chat-msg-doc-link {
  background: rgba(255,255,255,0.15);
  color: #FFFFFF;
}

/* Empty Conversation State */
.chat-empty-state {
  text-align: center;
  margin: auto;
  max-width: 320px;
  padding: 24px;
}
.chat-empty-icon {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: #F2EFFF;
  color: #5B3DF5;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 12px;
}
.chat-empty-title {
  font-size: 15px;
  font-weight: 800;
  color: #1A162B;
  margin: 0 0 6px;
}
.chat-empty-desc {
  font-size: 12.5px;
  color: #7B7890;
  line-height: 1.5;
  margin: 0;
}

/* ==========================================
   INPUT BAR (PERSIS GAMBAR 3)
   ========================================== */
.chat-input-bar {
  background: #FFFFFF;
  border-top: 1px solid #ECEEF5;
  padding: 10px 14px;
  flex-shrink: 0;
}
.chat-tag-selector {
  display: flex;
  gap: 6px;
  margin-bottom: 8px;
  overflow-x: auto;
  scrollbar-width: none;
}
.chat-tag-selector::-webkit-scrollbar { display: none; }
.chat-tag-btn {
  background: #F7F8FC;
  border: 1px solid #ECEEF5;
  color: #475467;
  font-size: 11px;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 999px;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}
.chat-tag-btn.active {
  background: #F2EFFF;
  border-color: #5B3DF5;
  color: #5B3DF5;
  font-weight: 700;
}

.chat-attachment-preview-bar {
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
  border-radius: 8px;
  padding: 6px 10px;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.att-preview-left {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
}
.att-preview-name {
  font-size: 12px;
  color: #1E293B;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.att-preview-remove {
  background: transparent;
  border: none;
  color: #94A3B8;
  font-size: 16px;
  font-weight: 700;
  cursor: pointer;
  padding: 0 4px;
}
.att-preview-remove:hover { color: #EF4444; }

.chat-input-row {
  display: flex;
  align-items: center;
  gap: 8px;
}
.chat-attach-btn {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #F7F8FC;
  border: 1px solid #ECEEF5;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
  transition: background 0.15s;
}
.chat-attach-btn:hover {
  background: #EDE8FE;
  color: #5B3DF5;
}
.chat-input-wrapper {
  flex: 1;
  min-width: 0;
}
.chat-text-input {
  width: 100%;
  height: 42px;
  background: #F0F1F7;
  border-radius: 999px;
  border: 1.5px solid transparent;
  padding: 0 16px;
  font-size: 13.5px;
  color: #1A162B;
  outline: none;
  font-family: inherit;
  transition: border-color 0.15s, background 0.15s;
  box-sizing: border-box;
}
.chat-text-input:focus {
  background: #FFFFFF;
  border-color: #5B3DF5;
  box-shadow: 0 0 0 3px rgba(91, 61, 245, 0.12);
}
.chat-text-input::placeholder {
  color: #9CA3AF;
  font-size: 13px;
}

.chat-send-btn {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  background: #5B3DF5;
  color: #FFFFFF;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
  transition: transform 0.15s, background 0.15s;
  box-shadow: 0 4px 12px rgba(91, 61, 245, 0.28);
}
.chat-send-btn:hover {
  background: #4C2EE6;
  transform: scale(1.04);
}
.chat-send-btn:active {
  transform: scale(0.96);
}

/* Lightbox Modal */
.kala-chat-lightbox {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.88);
  z-index: 10100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.kala-chat-lightbox img {
  max-width: 90vw;
  max-height: 90vh;
  border-radius: 12px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
  object-fit: contain;
}
.lightbox-close {
  position: absolute;
  top: 20px;
  right: 24px;
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: #FFFFFF;
  font-size: 32px;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

/* Animations */
@keyframes kalaChatFadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
@keyframes kalaChatSlideUp {
  from { transform: translateY(20px) scale(0.98); opacity: 0; }
  to { transform: translateY(0) scale(1); opacity: 1; }
}
.chat-spinner {
  width: 24px;
  height: 24px;
  border: 3px solid #E0DBFD;
  border-top-color: #5B3DF5;
  border-radius: 50%;
  animation: kalaChatSpin 0.7s linear infinite;
  margin: 0 auto 8px;
}
@keyframes kalaChatSpin {
  to { transform: rotate(360deg); }
}
.chat-loading-state {
  text-align: center;
  padding: 40px 16px;
  color: #7B7890;
  font-size: 13px;
}
</style>

<script>
/**
 * Room Chat Controller Object (KalaRoomChat)
 * Connects to api/portal_tasks.php:
 * - list_chat_rooms
 * - get_task_details
 * - submit_feedback
 * - mark_feedbacks_read
 */
const KalaRoomChat = (function() {
  let activeTaskId = null;
  let allRooms = [];
  let currentFilter = 'all';
  let pollInterval = null;
  let isSending = false;

  const currentUserId = <?= json_encode($chatUserId) ?>;
  const currentRole = <?= json_encode($chatRole) ?>;

  function init() {
    // Initial fetch of unread count to populate FAB badge
    fetchUnreadSummary();
    // Poll unread counts every 12 seconds
    setInterval(fetchUnreadSummary, 12000);
  }

  async function fetchUnreadSummary() {
    try {
      const res = await fetch('api/portal_tasks.php?action=list_chat_rooms');
      const data = await res.json();
      if (data.success) {
        updateFabBadge(data.total_unread || 0);
      }
    } catch (e) {}
  }

  function updateFabBadge(count) {
    const badge = document.getElementById('kala-chat-fab-badge');
    const tabBadge = document.getElementById('chat-tab-unread-count');
    if (!badge) return;

    if (count > 0) {
      badge.innerText = count > 99 ? '99+' : count;
      badge.style.display = 'inline-block';
      if (tabBadge) {
        tabBadge.innerText = count;
        tabBadge.style.display = 'inline-block';
      }
    } else {
      badge.style.display = 'none';
      if (tabBadge) tabBadge.style.display = 'none';
    }
  }

  // Open Inbox (Gambar 3 Layar 1)
  async function openInbox() {
    const modal = document.getElementById('kala-room-chat-modal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    // On mobile, show Inbox panel
    const inboxView = document.getElementById('kala-chat-inbox-view');
    const roomView = document.getElementById('kala-chat-room-view');
    inboxView.classList.add('active');
    roomView.classList.remove('active');

    await loadRoomsList();

    // Start polling room if currently active
    startPolling();
  }

  // Open Specific Room Chat (Gambar 3 Layar 2)
  async function openRoom(taskId) {
    activeTaskId = taskId;
    const modal = document.getElementById('kala-room-chat-modal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    // On mobile, show Room View
    const inboxView = document.getElementById('kala-chat-inbox-view');
    const roomView = document.getElementById('kala-chat-room-view');
    inboxView.classList.remove('active');
    roomView.classList.add('active');

    // Also load inbox rooms in background for desktop split-view
    loadRoomsList();

    await loadRoomMessages(taskId);
    startPolling();
  }

  function backToInbox() {
    activeTaskId = null;
    const inboxView = document.getElementById('kala-chat-inbox-view');
    const roomView = document.getElementById('kala-chat-room-view');
    inboxView.classList.add('active');
    roomView.classList.remove('active');
    loadRoomsList();
  }

  function close() {
    const modal = document.getElementById('kala-room-chat-modal');
    modal.style.display = 'none';
    document.body.style.overflow = '';
    stopPolling();
    fetchUnreadSummary();
    if (typeof loadTasksData === 'function') {
      loadTasksData();
    }
    if (typeof loadDeliverables === 'function') {
      loadDeliverables();
    }
  }

  async function loadRoomsList() {
    const listEl = document.getElementById('chat-inbox-list');
    try {
      const res = await fetch('api/portal_tasks.php?action=list_chat_rooms');
      const data = await res.json();

      if (data.success) {
        allRooms = data.rooms || [];
        updateFabBadge(data.total_unread || 0);
        renderContactsStrip(allRooms);
        renderRoomsList();
      } else {
        listEl.innerHTML = `<div style="text-align: center; padding: 24px; color: #EF4444; font-size: 12.5px;">${data.message || 'Gagal memuat obrolan'}</div>`;
      }
    } catch (e) {
      listEl.innerHTML = `<div style="text-align: center; padding: 24px; color: #9CA3AF; font-size: 12.5px;">Gagal terhubung ke server</div>`;
    }
  }

  function renderContactsStrip(rooms) {
    const strip = document.getElementById('chat-contacts-strip');
    if (!strip) return;

    // Extract unique active PICs and clients
    const contacts = [];
    const seen = new Set();

    rooms.forEach(r => {
      const name = r.worker_name || r.client_company || 'Tim';
      if (!seen.has(name) && contacts.length < 8) {
        seen.add(name);
        contacts.push({
          name: name,
          taskId: r.id,
          initial: name.substring(0, 2).toUpperCase()
        });
      }
    });

    if (contacts.length === 0) {
      strip.style.display = 'none';
      return;
    }

    strip.style.display = 'flex';
    strip.innerHTML = contacts.map(c => `
      <div class="chat-contact-chip" onclick="KalaRoomChat.openRoom(${c.taskId})">
        <div class="chat-contact-avatar-wrap">
          <div class="chat-contact-avatar">${c.initial}</div>
          <span class="chat-online-dot"></span>
        </div>
        <span class="chat-contact-name">${escapeHtml(c.name)}</span>
      </div>
    `).join('');
  }

  function renderRoomsList() {
    const listEl = document.getElementById('chat-inbox-list');
    if (!listEl) return;

    const query = (document.getElementById('chat-inbox-search')?.value || '').toLowerCase().trim();

    const filtered = allRooms.filter(r => {
      if (currentFilter === 'unread' && Number(r.unread_count) <= 0) return false;
      if (currentFilter === 'revision' && r.status !== 'revision' && r.latest_feedback_type !== 'revision_request') return false;
      if (query) {
        const fullText = `${r.title} ${r.client_company} ${r.worker_name} ${r.latest_feedback || ''}`.toLowerCase();
        if (!fullText.includes(query)) return false;
      }
      return true;
    });

    if (filtered.length === 0) {
      listEl.innerHTML = `
        <div style="text-align: center; padding: 36px 16px; color: #7B7890;">
          <div style="font-size: 13.5px; font-weight: 700; color: #1A162B; margin-bottom: 4px;">Tidak Ada Room Chat</div>
          <div style="font-size: 12px;">Belum ada room diskusi yang sesuai dengan filter.</div>
        </div>
      `;
      return;
    }

    listEl.innerHTML = filtered.map(r => {
      const initial = (r.title || 'T').substring(0, 1).toUpperCase();
      const isActive = (Number(activeTaskId) === Number(r.id));
      const hasUnread = Number(r.unread_count) > 0;
      const isRevision = (r.status === 'revision' || r.latest_feedback_type === 'revision_request');

      return `
        <div class="chat-room-item ${isActive ? 'active' : ''}" onclick="KalaRoomChat.openRoom(${r.id})">
          <div class="chat-room-avatar-wrap">
            <div class="chat-room-avatar">${initial}</div>
            <span class="chat-online-dot"></span>
          </div>
          <div class="chat-room-content">
            <div class="chat-room-row-top">
              <span class="chat-room-title">${escapeHtml(r.title)}</span>
              <span class="chat-room-time">${r.latest_feedback_time_formatted || ''}</span>
            </div>
            <div class="chat-room-client">
              ${escapeHtml(r.client_company || 'Klien')} &bull; PIC: <b>${escapeHtml(r.worker_name || 'Staff')}</b>
            </div>
            <div class="chat-room-row-bottom">
              <span class="chat-room-snippet">
                ${isRevision ? '<b style="color: #EF4444;">[Revisi]</b> ' : ''}
                ${r.latest_sender_name ? `<b>${escapeHtml(r.latest_sender_name)}:</b> ` : ''}
                ${escapeHtml(r.latest_feedback_snippet || 'Belum ada obrolan')}
              </span>
              ${hasUnread ? `
                <span class="chat-room-badge ${isRevision ? 'has-revision' : ''}">${r.unread_count}</span>
              ` : ''}
            </div>
          </div>
        </div>
      `;
    }).join('');
  }

  function filterInbox() {
    renderRoomsList();
  }

  function switchInboxFilter(filter, btn) {
    currentFilter = filter;
    document.querySelectorAll('.chat-tab-pill').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    renderRoomsList();
  }

  async function refreshInbox() {
    await loadRoomsList();
    if (activeTaskId) {
      await loadRoomMessages(activeTaskId);
    }
  }

  // Load Conversation for Active Room
  async function loadRoomMessages(taskId) {
    const container = document.getElementById('chat-messages-container');
    document.getElementById('chat-active-task-id').value = taskId;

    try {
      const res = await fetch(`api/portal_tasks.php?action=get_task_details&id=${taskId}`);
      const data = await res.json();

      if (!data.success) {
        container.innerHTML = `<div style="text-align: center; padding: 24px; color: #EF4444;">${data.message || 'Gagal memuat tugas'}</div>`;
        return;
      }

      const t = data.task;
      const feedbacks = data.feedbacks || [];

      // Update Header Info
      document.getElementById('room-header-title').innerText = t.title;
      document.getElementById('room-header-company').innerText = t.client_company || 'Klien Kalamedia';
      document.getElementById('room-header-members').innerText = `Klien, ${t.worker_name || 'PIC Tim'}, & Owner (Memantau)`;

      const previewBtn = document.getElementById('room-header-preview-btn');
      if (t.deliverable_link) {
        previewBtn.style.display = 'inline-flex';
        previewBtn.href = t.deliverable_link;
      } else {
        previewBtn.style.display = 'none';
      }

      // Render Messages
      if (feedbacks.length === 0) {
        container.innerHTML = `
          <div class="chat-empty-state">
            <div class="chat-empty-icon">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
            </div>
            <h5 class="chat-empty-title">Selamat Datang di Room Diskusi!</h5>
            <p class="chat-empty-desc">
              Room ini otomatis menghubungkan <b>Klien</b>, <b>${escapeHtml(t.worker_name || 'PIC')}</b>, dan <b>Owner Kalamedia</b> untuk memantau progres, brief, dan revisi tugas ini.
            </p>
          </div>
        `;
      } else {
        let html = '';
        let lastDate = '';

        feedbacks.forEach(f => {
          const msgDate = f.date_formatted || '';
          if (msgDate && msgDate !== lastDate) {
            html += `<div class="chat-date-pill">${msgDate}</div>`;
            lastDate = msgDate;
          }

          const isSelf = Boolean(f.is_self);
          const senderInitial = (f.sender_name || 'U').substring(0, 2).toUpperCase();
          const senderRole = (f.sender_role || '').toLowerCase();
          
          let roleBadgeClass = 'worker';
          let roleBadgeLabel = 'TIM KALA';
          if (senderRole === 'owner' || senderRole === 'admin') {
            roleBadgeClass = 'owner';
            roleBadgeLabel = 'OWNER';
          } else if (senderRole === 'client') {
            roleBadgeClass = 'client';
            roleBadgeLabel = 'CLIENT';
          }

          const isRevision = (f.feedback_type === 'revision_request');
          const isApproval = (f.feedback_type === 'approval');

          html += `
            <div class="chat-msg-row ${isSelf ? 'self' : 'other'}">
              ${!isSelf ? `
                <div class="chat-msg-avatar ${roleBadgeClass}" title="${escapeHtml(f.sender_name)} (${roleBadgeLabel})">
                  ${senderInitial}
                </div>
              ` : ''}

              <div class="chat-msg-bubble">
                ${!isSelf ? `
                  <div class="chat-msg-header">
                    <span class="chat-sender-name">${escapeHtml(f.sender_name || 'Pengguna')}</span>
                    <span class="chat-role-badge ${roleBadgeClass}">${roleBadgeLabel}</span>
                  </div>
                ` : ''}

                ${isRevision ? `
                  <div class="chat-feedback-type-banner revision">
                    <span>⚠️ Permintaan Revisi Klien</span>
                  </div>
                ` : ''}

                ${isApproval ? `
                  <div class="chat-feedback-type-banner approval">
                    <span>✓ Hasil Kerja Disetujui</span>
                  </div>
                ` : ''}

                ${f.attachment_path ? `
                  ${isImage(f.attachment_path) ? `
                    <div class="chat-msg-image-wrap" onclick="KalaRoomChat.openLightbox('${escapeHtml(f.attachment_path)}')">
                      <img src="${escapeHtml(f.attachment_path)}" alt="Lampiran Foto" class="chat-msg-image-thumb">
                    </div>
                  ` : `
                    <div>
                      <a href="${escapeHtml(f.attachment_path)}" target="_blank" class="chat-msg-doc-link">
                        <span>📄 Buka File Lampiran</span>
                      </a>
                    </div>
                  `}
                ` : ''}

                <div>${escapeHtml(f.message)}</div>

                <div class="chat-msg-footer">
                  <span>${f.time_formatted || ''}</span>
                  ${isSelf ? '<span title="Terkirim">&#10003;&#10003;</span>' : ''}
                </div>
              </div>
            </div>
          `;
        });

        container.innerHTML = html;
        scrollToBottom();
      }
    } catch (e) {
      container.innerHTML = `<div style="text-align: center; padding: 24px; color: #9CA3AF;">Gagal terhubung ke server</div>`;
    }
  }

  function scrollToBottom() {
    const container = document.getElementById('chat-messages-container');
    if (container) {
      container.scrollTop = container.scrollHeight;
    }
  }

  function isImage(path) {
    if (!path) return false;
    return /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(path);
  }

  function selectTag(type, btn) {
    document.getElementById('chat-active-feedback-type').value = type;
    document.querySelectorAll('.chat-tag-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    const input = document.getElementById('chat-text-input');
    if (type === 'revision_request') {
      input.placeholder = 'Tulis rincian revisi yang perlu diperbaiki tim...';
    } else if (type === 'approval') {
      input.placeholder = 'Ketik catatan apresiasi & konfirmasi persetujuan...';
    } else {
      input.placeholder = 'Ketik pesan diskusi kerjaan...';
    }
    input.focus();
  }

  function handleFileChosen(e) {
    const file = e.target.files[0];
    const previewBar = document.getElementById('chat-attachment-preview-bar');
    const nameEl = document.getElementById('att-preview-name');
    if (file) {
      nameEl.innerText = file.name;
      previewBar.style.display = 'flex';
    } else {
      previewBar.style.display = 'none';
    }
  }

  function clearAttachment() {
    const fileInput = document.getElementById('chat-file-input');
    fileInput.value = '';
    document.getElementById('chat-attachment-preview-bar').style.display = 'none';
  }

  async function sendMessage(e) {
    e.preventDefault();
    if (isSending) return;

    const taskId = document.getElementById('chat-active-task-id').value;
    const input = document.getElementById('chat-text-input');
    const fileInput = document.getElementById('chat-file-input');
    const feedbackType = document.getElementById('chat-active-feedback-type').value || 'comment';

    const message = input.value.trim();
    const hasFile = fileInput.files && fileInput.files.length > 0;

    if (!message && !hasFile && feedbackType !== 'approval') {
      input.focus();
      return;
    }

    const sendBtn = document.getElementById('chat-send-btn');
    isSending = true;
    sendBtn.disabled = true;

    const formData = new FormData();
    formData.append('task_id', taskId);
    formData.append('feedback_type', feedbackType);
    formData.append('message', message);
    if (hasFile) {
      formData.append('attachment', fileInput.files[0]);
    }

    try {
      const res = await fetch('api/portal_tasks.php?action=submit_feedback', {
        method: 'POST',
        body: formData
      });
      const data = await res.json();

      if (data.success) {
        input.value = '';
        clearAttachment();
        selectTag('comment', document.querySelector('.chat-tag-btn[data-type="comment"]'));
        await loadRoomMessages(taskId);
        loadRoomsList();
      } else {
        alert(data.message || 'Gagal mengirim pesan');
      }
    } catch (err) {
      alert('Koneksi gagal. Silakan coba lagi.');
    } finally {
      isSending = false;
      sendBtn.disabled = false;
    }
  }

  function startPolling() {
    stopPolling();
    pollInterval = setInterval(() => {
      if (activeTaskId) {
        loadRoomMessages(activeTaskId);
      }
      loadRoomsList();
    }, 4500);
  }

  function stopPolling() {
    if (pollInterval) {
      clearInterval(pollInterval);
      pollInterval = null;
    }
  }

  function openLightbox(src) {
    const lb = document.getElementById('kala-chat-lightbox');
    const img = document.getElementById('kala-chat-lightbox-img');
    img.src = src;
    lb.style.display = 'flex';
  }

  function closeLightbox() {
    const lb = document.getElementById('kala-chat-lightbox');
    lb.style.display = 'none';
  }

  function escapeHtml(str) {
    if (!str) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(str).replace(/[&<>"']/g, m => map[m]);
  }

  // Auto initialize on DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  return {
    openInbox,
    openRoom,
    backToInbox,
    close,
    refreshInbox,
    filterInbox,
    switchInboxFilter,
    selectTag,
    handleFileChosen,
    clearAttachment,
    sendMessage,
    openLightbox,
    closeLightbox
  };
})();

// Global alias for compatibility
window.openRoomChat = function(taskId) {
  KalaRoomChat.openRoom(taskId);
};
window.openDiscussionModal = function(taskId) {
  KalaRoomChat.openRoom(taskId);
};
window.openFeedbackModal = function(taskId) {
  KalaRoomChat.openRoom(taskId);
};
</script>

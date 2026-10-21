<?php
/**
 * Kalamedia Agency - Task Management & Workspace Tim View (Untitled UI Design System)
 * Unified interface for Owner, Admin, Karyawan (In-House), and Freelancer
 */
require_auth();

$user = current_user();
$role = strtolower($_SESSION['role'] ?? ($user['role'] ?? current_user_role()));
$userId = (int)$user['id'];
$isOwnerOrAdmin = in_array($role, ['owner', 'admin']);
$isAdminUser = $isOwnerOrAdmin;
$isOwnerUser = ($role === 'owner');
$isWorkerUser = ($role === 'karyawan' || $role === 'freelancer');
$db = Database::getConnection();

// Calculate worker's dynamic ads metrics 100% from database (strictly 2 cards: Saldo & Sisa Saldo)
$total_ditransfer_owner = 0.0;
$total_dibelanjakan_ads = 0.0;
$sisa_saldo = 0.0;
$status_iklan = 'Belum Ada Saldo';

if ($isWorkerUser) {
    $stmtWorkerAds = $db->prepare("
        SELECT 
            COALESCE(SUM(COALESCE(NULLIF(t.transfer_amount_owner, 0), t.topup_amount, 0)), 0) as total_transfer,
            COALESCE(SUM(COALESCE(NULLIF(t.ad_spend_amount, 0), t.spend_installed, 0)), 0) as total_spent
        FROM ads_topup_transactions t
        WHERE COALESCE(t.is_deleted, 0) = 0
        AND (
            t.assigned_user_id = ? OR t.uploaded_by_user_id = ?
            OR t.project_id IN (SELECT DISTINCT project_id FROM tasks WHERE assigned_to_user_id = ? AND COALESCE(is_deleted,0)=0)
            OR t.client_id IN (SELECT client_id FROM employee_clients WHERE user_id = ?)
        )
    ");
    $stmtWorkerAds->execute([$userId, $userId, $userId, $userId]);
    $workerSummaryRow = $stmtWorkerAds->fetch(PDO::FETCH_ASSOC);
    $total_ditransfer_owner = floatval($workerSummaryRow['total_transfer'] ?? 0);
    $total_dibelanjakan_ads = floatval($workerSummaryRow['total_spent'] ?? 0);
    $sisa_saldo = $total_ditransfer_owner - $total_dibelanjakan_ads;
    $status_iklan = ($sisa_saldo > 0) ? 'Iklan Jalan' : ($total_ditransfer_owner > 0 ? 'Saldo Habis' : 'Belum Ada Saldo');
}

$allTeamWorkers = [];
if ($isAdminUser || $isOwnerUser) {
    $stmtWorkers = $db->query("SELECT id, name, role, employment_type FROM users WHERE (role IN ('karyawan', 'freelancer', 'admin', 'owner') OR employment_type IS NOT NULL) AND is_active = 1 ORDER BY name ASC");
    $allTeamWorkers = $stmtWorkers->fetchAll();
}

$workerProfile = null;
$assignedClients = [];
if ($isWorkerUser) {
    $stmtW = $db->prepare("SELECT position, jobdesk, employment_type FROM users WHERE id = ?");
    $stmtW->execute([$userId]);
    $workerProfile = $stmtW->fetch();

    $stmtAc = $db->prepare("
        SELECT c.id, c.company, c.name as pic_name, c.package_name,
               (SELECT COUNT(*) FROM tasks WHERE client_id = c.id AND assigned_to_user_id = ? AND COALESCE(is_deleted,0) = 0) as my_task_count
        FROM clients c
        JOIN employee_clients ec ON c.id = ec.client_id
        WHERE ec.user_id = ? AND COALESCE(c.is_deleted,0) = 0
        ORDER BY c.company ASC
    ");
    $stmtAc->execute([$userId, $userId]);
    $assignedClients = $stmtAc->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manajemen Tugas Tim - PT Kala Kausa Prima</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=saas-linear-1.0">
  <?php render_tab_uid_script(); ?>
  <style>
    /* Task Management & Workspace Custom Styles - Bankio Minimal Finance */
    .mobile-text { display: none; }
    .desktop-text { display: inline; }

    .tasks-header-card {
      background: #FFFFFF;
      border: 1px solid #ECEEF5;
      border-radius: 22px;
      padding: 24px 26px;
      margin-bottom: 22px;
      box-shadow: 0 10px 30px -10px rgba(18, 24, 48, 0.05), 0 2px 6px -1px rgba(18, 24, 48, 0.02);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 16px;
    }

    /* Workspace Tabs Bar - Modern Segmented Boxed Bar */
    .workspace-tabs-bar {
      display: inline-flex;
      gap: 2px;
      margin-bottom: 20px;
      background: #F3F4F6;
      border: none;
      padding: 3px;
      border-radius: 8px;
      max-width: 100%;
      overflow-x: auto;
      white-space: nowrap;
      scrollbar-width: none;
    }
    .workspace-tabs-bar::-webkit-scrollbar {
      display: none;
    }
    .workspace-tab-btn {
      padding: 6px 14px;
      font-size: 12.5px;
      font-weight: 500;
      color: #6B7280;
      background: transparent;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      white-space: nowrap;
      box-shadow: none;
      transition: all 0.15s ease;
      font-family: inherit;
    }
    .workspace-tab-btn:hover:not(.active) {
      color: #111827;
      background: rgba(0, 0, 0, 0.04);
      transform: none;
      box-shadow: none;
    }
    .workspace-tab-btn:active {
      transform: scale(0.98);
    }
    .workspace-tab-btn.active {
      background: #FFFFFF !important;
      color: #111827 !important;
      border: none !important;
      font-weight: 600;
      box-shadow: 0 1px 2px rgba(16, 24, 40, 0.08) !important;
    }

    .kpi-row {
      display: grid;
      grid-template-columns: repeat(5, minmax(0, 1fr));
      gap: 16px;
      margin-bottom: 22px;
    }
    @media (max-width: 1024px) {
      .kpi-row {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
      }
    }
    .kpi-tile {
      background: #FFFFFF;
      border: 1px solid #ECEEF5;
      border-radius: 20px;
      padding: 20px 22px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      min-height: 105px;
      box-shadow: 0 10px 30px -10px rgba(18, 24, 48, 0.05), 0 2px 6px -1px rgba(18, 24, 48, 0.02);
      transition: transform 220ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 220ms cubic-bezier(0.22, 1, 0.36, 1), border-color 220ms ease;
      will-change: transform;
    }
    .kpi-tile:hover {
      transform: translate3d(0, -2px, 0);
      box-shadow: 0 14px 34px -8px rgba(18, 24, 48, 0.08);
      border-color: #DDE1EE;
    }
    .kpi-tile:nth-child(1) { animation: kalaCardEnter 280ms cubic-bezier(0.22, 1, 0.36, 1) 0ms both; }
    .kpi-tile:nth-child(2) { animation: kalaCardEnter 280ms cubic-bezier(0.22, 1, 0.36, 1) 35ms both; }
    .kpi-tile:nth-child(3) { animation: kalaCardEnter 280ms cubic-bezier(0.22, 1, 0.36, 1) 70ms both; }
    .kpi-tile:nth-child(4) { animation: kalaCardEnter 280ms cubic-bezier(0.22, 1, 0.36, 1) 105ms both; }
    .kpi-tile:nth-child(5) { animation: kalaCardEnter 280ms cubic-bezier(0.22, 1, 0.36, 1) 140ms both; }
    .kpi-tile.urgent {
      border-color: #FECDCA;
      background: #FEF3F2;
    }
    .kpi-tile.review {
      border-color: #FEDF89;
      background: #FFFAEB;
    }

    /* Filter Panel - Modern Boxed Controls */
    .filter-panel {
      background: #FFFFFF;
      border: 1px solid #ECEEF5;
      border-radius: 20px;
      padding: 20px;
      margin-bottom: 22px;
      display: flex;
      flex-direction: column;
      gap: 16px;
      box-shadow: 0 10px 30px -10px rgba(18, 24, 48, 0.04);
    }
    .task-services-section,
    .task-status-section {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .task-filter-header-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .task-filter-title {
      font-size: 11.5px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      color: #475467;
    }
    .task-filter-hint {
      font-size: 11px;
      color: #98A2B3;
      font-weight: 500;
    }
    .task-services-track {
      display: inline-flex;
      gap: 2px;
      align-items: center;
      overflow-x: auto;
      flex-wrap: nowrap;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: none;
      background: #F3F4F6;
      padding: 3px;
      border-radius: 8px;
      max-width: 100%;
    }
    .task-services-track::-webkit-scrollbar {
      display: none;
    }
    .task-service-pill {
      background: transparent;
      border: none;
      color: #6B7280;
      border-radius: 6px;
      padding: 6px 12px;
      font-size: 12.5px;
      font-weight: 500;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      white-space: nowrap;
      flex-shrink: 0;
      box-shadow: none;
      transition: all 0.15s ease;
      font-family: inherit;
      line-height: 1.2;
    }
    .task-service-pill:hover:not(.active) {
      background: rgba(0, 0, 0, 0.04);
      color: #111827;
      transform: none;
      box-shadow: none;
    }
    .task-service-pill:active {
      transform: scale(0.98);
    }
    .task-service-pill.active {
      background: #FFFFFF !important;
      border: none !important;
      color: #111827 !important;
      font-weight: 600;
      box-shadow: 0 1px 2px rgba(16, 24, 40, 0.08) !important;
    }

    .task-status-grid {
      display: inline-flex;
      gap: 2px;
      align-items: center;
      overflow-x: auto;
      flex-wrap: nowrap;
      background: #F3F4F6;
      padding: 3px;
      border-radius: 8px;
      max-width: 100%;
      scrollbar-width: none;
    }
    .task-status-grid::-webkit-scrollbar {
      display: none;
    }
    .task-status-grid .filter-btn {
      background: transparent !important;
      border: none !important;
      color: #6B7280 !important;
      border-radius: 6px !important;
      padding: 6px 12px !important;
      font-size: 12.5px !important;
      font-weight: 500 !important;
      cursor: pointer !important;
      display: inline-flex !important;
      align-items: center !important;
      gap: 6px !important;
      white-space: nowrap !important;
      transition: all 0.15s ease !important;
    }
    .task-status-grid .filter-btn:hover:not(.active) {
      background: rgba(0, 0, 0, 0.04) !important;
      color: #111827 !important;
    }
    .task-status-grid .filter-btn.active {
      background: #FFFFFF !important;
      border: none !important;
      color: #111827 !important;
      font-weight: 600 !important;
      box-shadow: 0 1px 2px rgba(16, 24, 40, 0.08) !important;
    }

    .task-bottom-toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
      padding-top: 10px;
      border-top: 1px dashed #E2E8F0;
    }
    .task-bottom-left-group {
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
      flex-grow: 1;
    }
    .task-select-wrap {
      min-width: 200px;
    }
    .task-sdm-select {
      height: 38px;
      border-radius: 10px;
      border: 1.5px solid #D0D5DD;
      background: #FFFFFF;
      font-size: 12.5px;
      font-weight: 600;
      color: #344054;
      padding: 6px 12px;
    }
    .task-search-wrap {
      position: relative;
      display: flex;
      align-items: center;
      min-width: 240px;
      max-width: 320px;
      flex-grow: 1;
    }
    .task-search-icon {
      position: absolute;
      left: 12px;
      font-size: 13px;
      pointer-events: none;
      opacity: 0.6;
    }
    .task-search-input {
      width: 100%;
      height: 38px;
      padding: 6px 32px 6px 34px;
      border-radius: 10px;
      border: 1.5px solid #D0D5DD;
      background: #FFFFFF;
      color: #101828;
      font-size: 12.5px;
      font-family: inherit;
      box-shadow: 0 1px 3px rgba(16, 24, 40, 0.04);
      outline: none;
      transition: border-color 160ms ease, box-shadow 160ms ease;
    }
    .task-search-input:focus {
      border-color: #5D76F5;
      box-shadow: 0 0 0 3.5px rgba(93, 118, 245, 0.15);
    }
    .task-search-input::placeholder {
      color: #98A2B3;
      font-size: 12px;
    }
    .task-search-clear {
      position: absolute;
      right: 10px;
      background: transparent;
      border: none;
      color: #98A2B3;
      font-size: 16px;
      font-weight: 700;
      cursor: pointer;
      padding: 0;
      line-height: 1;
    }
    .task-search-clear:hover {
      color: #101828;
    }
    .task-count-pill {
      font-size: 12px;
      font-weight: 600;
      color: #64748B;
      background: #F8FAFC;
      border: 1px solid #E2E8F0;
      padding: 7px 14px;
      border-radius: 999px;
      white-space: nowrap;
    }

    /* Tasks Grid / Cards */
    .tasks-grid-list {
      display: grid;
      grid-template-columns: 1fr;
      gap: 16px;
    }
    .task-item-card {
      background: #FFFFFF;
      border: 1px solid #ECEEF5;
      border-radius: 20px;
      padding: 20px 22px;
      transition: transform 220ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 220ms cubic-bezier(0.22, 1, 0.36, 1), border-color 220ms ease;
      display: flex;
      flex-direction: column;
      gap: 12px;
      box-shadow: 0 10px 30px -10px rgba(18, 24, 48, 0.05), 0 2px 6px -1px rgba(18, 24, 48, 0.02);
      will-change: transform;
    }
    .task-item-card:hover {
      border-color: #DDE1EE;
      box-shadow: 0 14px 34px -8px rgba(18, 24, 48, 0.08);
      transform: translate3d(0, -2px, 0);
    }
    .task-item-card.has-revision {
      border-left: 4px solid #EF4444;
      background: #FFFDFD;
    }
    .task-item-card.has-review {
      border-left: 4px solid #F59E0B;
      background: #FFFEF9;
    }
    .task-item-card.is-approved {
      border-left: 4px solid #10B981;
    }

    .badge-worker {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-size: 11px;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 12px;
    }
    .badge-worker.karyawan {
      background: #EFF8FF;
      color: #175CD3;
      border: 1px solid #B2DDFF;
    }
    .badge-worker.freelancer {
      background: #F4F3FF;
      color: #5925DC;
      border: 1px solid #D9D6FE;
    }

    .badge-priority {
      font-size: 10.5px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      padding: 2px 7px;
      border-radius: 4px;
    }
    .badge-priority.urgent { background: #FEF3F2; color: #B42318; border: 1px solid #FECDCA; }
    .badge-priority.high { background: #FFFAEB; color: #B54708; border: 1px solid #FEDF89; }
    .badge-priority.medium { background: #F8F9FC; color: #344054; border: 1px solid #EAECF0; }
    .badge-priority.low { background: #F2F4F7; color: #667085; }

    .status-pill {
      font-size: 11px;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 12px;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }
    .status-pill.backlog { background: #F2F4F7; color: #475467; }
    .status-pill.in_progress { background: #EFF8FF; color: #175CD3; }
    .status-pill.internal_review { background: #EEF4FF; color: #3538CD; }
    .status-pill.client_review { background: #FEF0C7; color: #B54708; }
    .status-pill.revision { background: #FEE4E2; color: #B42318; }
    .status-pill.approved { background: #D1FADF; color: #027A48; }

    /* Alert Revision Box */
    .alert-revision-box {
      background: #FEF3F2;
      border: 1px solid #FECDCA;
      border-radius: var(--radius-md);
      padding: 16px 20px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }

    /* Discussion Thread */
    .discussion-thread {
      max-height: 380px;
      overflow-y: auto;
      padding: 16px;
      background: #F8FAFC;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-sm);
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-bottom: 18px;
    }
    .bubble {
      max-width: 85%;
      padding: 10px 14px;
      border-radius: 12px;
      font-size: 12.5px;
      line-height: 1.5;
    }
    .bubble.client-bubble {
      align-self: flex-start;
      background: #5D76F5;
      color: #FFFFFF;
      border-bottom-left-radius: 4px;
    }
    .bubble.team-bubble {
      align-self: flex-end;
      background: #FFFFFF;
      color: #000000;
      border: 1px solid #ECEEF5;
      border-bottom-right-radius: 4px;
    }
    .bubble-meta {
      font-size: 10.5px;
      margin-bottom: 4px;
      opacity: 0.8;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .client-filter-pill {
      background: #FFFFFF;
      border: 1px solid #D0D5DD;
      color: #344054;
      font-size: 12px;
      font-weight: 600;
      padding: 6px 14px;
      border-radius: 999px;
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .client-filter-pill:hover {
      background: #F9FAFB;
      border-color: #98A2B3;
    }
    .client-filter-pill.active {
      background: linear-gradient(135deg, #5D76F5 0%, #8932E0 100%);
      color: #FFFFFF;
      border-color: transparent;
      box-shadow: 0 2px 6px rgba(93, 118, 245, 0.35);
    }
    .service-tag {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 11px;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 6px;
    }
    .service-tag-sosmed { background: #EFF8FF; color: #175CD3; border: 1px solid #B2DDFF; }
    .service-tag-desain { background: #FDF2FA; color: #C11574; border: 1px solid #FCCEEE; }
    .service-tag-web { background: #F4F3FF; color: #5925DC; border: 1px solid #D9D6FE; }
    .service-tag-ads { background: #ECFDF3; color: #027A48; border: 1px solid #A6F4C5; }
    .service-tag-default { background: #F2F4F7; color: #344054; border: 1px solid #EAECF0; }

    @media (max-width: 768px) {
      .mobile-text { display: inline !important; }
      .desktop-text { display: none !important; }

      .tasks-header-card {
        padding: 16px 14px !important;
        border-radius: 18px !important;
        margin-bottom: 16px !important;
        gap: 12px !important;
      }
      .tasks-header-actions {
        width: 100% !important;
        display: grid !important;
        grid-template-columns: 1fr !important;
        gap: 8px !important;
      }
      .tasks-header-actions .btn {
        width: 100% !important;
        justify-content: center !important;
        text-align: center !important;
      }

      /* Workspace Tabs Bar: 2 Columns Side-by-Side */
      .workspace-tabs-bar {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        width: 100% !important;
        gap: 6px !important;
        padding: 5px !important;
        border-radius: 14px !important;
        box-sizing: border-box !important;
        margin-bottom: 16px !important;
      }
      .workspace-tab-btn {
        width: 100% !important;
        padding: 9px 6px !important;
        font-size: 11.5px !important;
        border-radius: 9px !important;
        justify-content: center !important;
        text-align: center !important;
        white-space: nowrap !important;
        box-sizing: border-box !important;
      }

      /* Balanced KPI Tiles Grid: 1 Hero card + 2x2 grid */
      .kpi-row {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 8px !important;
        margin-bottom: 16px !important;
      }
      .kpi-row > .kpi-tile:first-child {
        grid-column: 1 / -1 !important;
        background: linear-gradient(135deg, #F8FAFC 0%, #EEF2F6 100%) !important;
        border-color: #DDE1EE !important;
      }
      .kpi-row > .kpi-tile:last-child,
      .kpi-row > *:last-child:nth-child(odd) {
        grid-column: auto !important;
      }
      .kpi-tile {
        padding: 12px 14px !important;
        border-radius: 14px !important;
        min-height: auto !important;
      }
      .kpi-tile > span:first-child {
        font-size: 9.5px !important;
        letter-spacing: 0.04em !important;
      }
      .kpi-tile > div {
        font-size: 18px !important;
        margin: 2px 0 !important;
      }
      .kpi-tile > span:last-child {
        font-size: 10.5px !important;
      }

      /* Filter Panel */
      .filter-panel {
        padding: 14px !important;
        border-radius: 16px !important;
        gap: 12px !important;
        margin-bottom: 16px !important;
      }
      .task-filter-title {
        font-size: 11px !important;
      }
      .task-filter-hint {
        font-size: 10px !important;
      }
      .task-services-track {
        overflow-x: auto !important;
        flex-wrap: nowrap !important;
        padding-bottom: 4px !important;
        width: 100% !important;
      }
      .task-service-pill {
        padding: 7px 13px !important;
        font-size: 11.5px !important;
        border-radius: 9px !important;
      }
      #status-filter-group.task-status-grid,
      .task-status-grid {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 8px !important;
        width: 100% !important;
        box-sizing: border-box !important;
        overflow: visible !important;
        flex-wrap: unset !important;
      }
      #status-filter-group.task-status-grid .filter-btn:first-child,
      .task-status-grid .filter-btn:first-child {
        grid-column: 1 / -1 !important;
      }
      #status-filter-group.task-status-grid .filter-btn,
      .task-status-grid .filter-btn {
        width: 100% !important;
        padding: 8px 10px !important;
        font-size: 11.5px !important;
        border-radius: 9px !important;
        justify-content: center !important;
        text-align: center !important;
        white-space: nowrap !important;
        box-sizing: border-box !important;
        flex-shrink: unset !important;
      }
      .task-bottom-toolbar {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 10px !important;
        padding-top: 10px !important;
      }
      .task-bottom-left-group {
        flex-direction: column !important;
        align-items: stretch !important;
        width: 100% !important;
        gap: 8px !important;
      }
      .task-select-wrap,
      .task-sdm-select {
        width: 100% !important;
        min-width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
      }
      .task-search-wrap {
        width: 100% !important;
        min-width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
      }
      .task-search-input {
        width: 100% !important;
        height: 38px !important;
        font-size: 12.5px !important;
        box-sizing: border-box !important;
      }
      .task-count-pill {
        width: 100% !important;
        text-align: center !important;
        justify-content: center !important;
        box-sizing: border-box !important;
      }

      /* Section 2 Ads Tracker Header Actions */
      .ads-tracker-header-actions {
        width: 100% !important;
        display: flex !important;
        flex-direction: column !important;
        gap: 8px !important;
      }
      .ads-tracker-header-actions .btn {
        width: 100% !important;
        justify-content: center !important;
        text-align: center !important;
      }
    }
  </style>
</head>
<body>

  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body">

        <!-- Header Card -->
        <div class="tasks-header-card">
          <div>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
              <span class="badge" style="background: #F2F4F7; color: #101828; font-weight: 800; font-size: 11px;">
                <?= $isAdminUser ? 'ADMIN & PM WORKSPACE' : 'TIM PRODUKSI' ?>
              </span>
              <span style="font-size: 12px; color: var(--text-muted);">&bull;</span>
              <span style="font-size: 12px; color: var(--text-secondary); font-weight: 600;">
                Login sebagai: <b><?= htmlspecialchars($user['name']) ?></b> (<?= ucfirst($role) ?>)
              </span>
            </div>
            <h2 style="font-size: 20px; font-weight: 800; color: #101828; margin: 0; letter-spacing: -0.3px;">
              <?= $isAdminUser ? 'Manajemen Tugas & Pembagian Kerja SDM' : 'Workspace Tugas Saya & Pelacakan Progres' ?>
            </h2>
            <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0;">
              <?= $isAdminUser 
                ? 'Distribusikan deliverables proyek ke Karyawan In-House dan Freelancer, serta pantau progres dan revisi klien.' 
                : 'Daftar deliverables yang ditugaskan kepada Anda. Perbarui link hasil kerja dan tindak lanjuti masukan klien.' ?>
            </p>
          </div>

          <div class="tasks-header-actions" style="display: flex; gap: 8px; flex-wrap: wrap;">
            <?php if ($isAdminUser): ?>
              <button class="btn btn-primary btn-sm" onclick="openAssignTaskModal()">
                <span>+ Bagi Tugas Baru (Assign)</span>
              </button>
              <button class="btn btn-secondary btn-sm" onclick="deleteAllTasks()" style="color: #B42318; border-color: #FECDCA; background: #FEF3F2; font-weight: 700;" title="Hapus Semua Tugas">
                <span>Hapus Semua Tugas</span>
              </button>
            <?php endif; ?>
            <button class="btn btn-secondary btn-sm" onclick="loadTasksData()">
              <span>Segarkan</span>
            </button>
          </div>
        </div>

        <!-- Section Switcher Tabs -->
        <div class="workspace-tabs-bar">
          <button type="button" class="workspace-tab-btn active" id="tab-btn-tasks" onclick="switchTaskWorkspaceTab('tasks')">
            <span class="desktop-text">Deliverables &amp; Penugasan Tim</span>
            <span class="mobile-text">Deliverables Tim</span>
          </button>
          <button type="button" class="workspace-tab-btn" id="tab-btn-ads" onclick="switchTaskWorkspaceTab('ads')">
            <span class="desktop-text">Pelacakan &amp; Koordinasi Anggaran Ads</span>
            <span class="mobile-text">Pelacakan Ads</span>
          </button>
        </div>

        <!-- SECTION 1: Tasks & Deliverables -->
        <div id="section-tasks-content">
          <!-- Alert Banner for Workers if Revision / Client Feedback Arrived -->
          <div id="revision-alert-container" style="display: none;"></div>

          <?php if ($isWorkerUser): ?>
            <!-- Worker Info Section: Jobdesk & Assigned Clients -->
            <div class="kala-grid-2col" style="margin-bottom: 20px;">
              <!-- 1. Jobdesk Saya dari Owner -->
              <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 20px; box-shadow: 0 1px 3px rgba(16,24,40,0.05);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                  <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #175CD3; background: #EFF8FF; padding: 2px 8px; border-radius: 4px; border: 1px solid #B2DDFF;">
                    JOBDESK DARI OWNER
                  </span>
                  <span style="font-size: 12px; font-weight: 700; color: #101828;">
                    <?= htmlspecialchars($workerProfile['position'] ?? 'Spesialis Tim') ?>
                  </span>
                </div>
                <h4 style="font-size: 14px; font-weight: 800; color: #101828; margin: 0 0 6px;">Tanggung Jawab Utama Anda:</h4>
                <p style="font-size: 13px; color: var(--text-secondary); margin: 0; line-height: 1.5; white-space: pre-line;">
                  <?= !empty($workerProfile['jobdesk']) ? htmlspecialchars($workerProfile['jobdesk']) : 'Menangani pengerjaan deliverables kreatif, kampanye, dan revisi klien sesuai arahan Owner Kalamedia.' ?>
                </p>
              </div>

              <!-- 2. Klien & Proyek yang Saya Pegang -->
              <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 20px; box-shadow: 0 1px 3px rgba(16,24,40,0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                  <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #027A48; background: #ECFDF3; padding: 2px 8px; border-radius: 4px; border: 1px solid #A6F4C5;">
                    KLIEN & PT YANG SAYA PEGANG
                  </span>
                  <span style="font-size: 12px; font-weight: 700; color: var(--text-muted);">
                    <?= count($assignedClients) ?> Perusahaan
                  </span>
                </div>
                <?php if (empty($assignedClients)): ?>
                  <p style="font-size: 12.5px; color: var(--text-muted); font-style: italic; margin: 0;">
                    Belum ada klien khusus yang dialokasikan oleh Owner ke Anda. Anda dapat mengerjakan seluruh penugasan aktif di bawah.
                  </p>
                <?php else: ?>
                  <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <?php foreach ($assignedClients as $ac): ?>
                      <div style="background: #F9FAFB; border: 1px solid var(--border-color); border-radius: 8px; padding: 8px 12px; display: inline-flex; flex-direction: column; gap: 2px;">
                        <span style="font-size: 13px; font-weight: 800; color: #101828;"><?= htmlspecialchars($ac['company']) ?></span>
                        <span style="font-size: 11px; color: var(--text-secondary);">PIC: <?= htmlspecialchars($ac['pic_name']) ?> &bull; <b><?= (int)$ac['my_task_count'] ?> Tugas</b></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- KPI Metrics Row (Shopeers SaaS Style) -->
          <div class="kpi-row">
            <div class="kpi-tile">
              <div class="metric-header">
                <span class="metric-label">Total Deliverables</span>
                <span class="metric-icon">
                  <svg viewBox="0 0 24 24" width="16" height="16" stroke="#2563EB" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </span>
              </div>
              <div class="metric-value">
                <span class="metric-num" id="kpi-total">0</span>
              </div>
              <div class="metric-subtitle">
                <span>Pekerjaan aktif</span>
              </div>
            </div>

            <div class="kpi-tile urgent">
              <div class="metric-header">
                <span class="metric-label" style="color: #B42318;">Butuh Revisi</span>
                <span class="metric-icon">
                  <svg viewBox="0 0 24 24" width="16" height="16" stroke="#B42318" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </span>
              </div>
              <div class="metric-value">
                <span class="metric-num" id="kpi-revision" style="color: #B42318;">0</span>
              </div>
              <div class="metric-subtitle">
                <span style="color: #B42318;">Permintaan klien</span>
              </div>
            </div>

            <div class="kpi-tile review">
              <div class="metric-header">
                <span class="metric-label" style="color: #B54708;">Review Klien</span>
                <span class="metric-icon">
                  <svg viewBox="0 0 24 24" width="16" height="16" stroke="#B54708" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                </span>
              </div>
              <div class="metric-value">
                <span class="metric-num" id="kpi-review" style="color: #B54708;">0</span>
              </div>
              <div class="metric-subtitle">
                <span style="color: #B54708;">Menunggu approval</span>
              </div>
            </div>

            <div class="kpi-tile">
              <div class="metric-header">
                <span class="metric-label">Sedang Dikerjakan</span>
                <span class="metric-icon">
                  <svg viewBox="0 0 24 24" width="16" height="16" stroke="#2563EB" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                </span>
              </div>
              <div class="metric-value">
                <span class="metric-num" id="kpi-progress">0</span>
              </div>
              <div class="metric-subtitle">
                <span>Tahap produksi</span>
              </div>
            </div>

            <div class="kpi-tile">
              <div class="metric-header">
                <span class="metric-label">Selesai &amp; Disetujui</span>
                <span class="metric-icon">
                  <svg viewBox="0 0 24 24" width="16" height="16" stroke="#059669" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </span>
              </div>
              <div class="metric-value">
                <span class="metric-num" id="kpi-approved">0</span>
              </div>
              <div class="metric-subtitle">
                <span>Hasil final rampung</span>
              </div>
            </div>
          </div>

          <!-- Filter Panel -->
          <div class="filter-panel">
            <!-- Row 1: Kategori Jasa (Clean Segmented Control 1 Baris) -->
            <div class="task-services-section">
              <div class="task-filter-header-bar">
                <span class="task-filter-title">Kategori Layanan</span>
                <span class="task-filter-hint">Geser untuk memilih spesialisasi jasa</span>
              </div>
              <div class="task-services-track">
                <button type="button" class="task-service-pill active" onclick="filterTaskService('all', this)">
                  <span>Semua Jasa</span>
                </button>
                <button type="button" class="task-service-pill" onclick="filterTaskService('Kelola Sosmed', this)">
                  <span>Kelola Sosmed</span>
                </button>
                <button type="button" class="task-service-pill" onclick="filterTaskService('Desain Eceran', this)">
                  <span>Desain Eceran / Satuan</span>
                </button>
                <button type="button" class="task-service-pill" onclick="filterTaskService('Jasa Buat Web', this)">
                  <span>Jasa Buat Web</span>
                </button>
                <button type="button" class="task-service-pill" onclick="filterTaskService('Jasa Iklan', this)">
                  <span>Jasa Iklan Meta &amp; Google</span>
                </button>
              </div>
            </div>

            <!-- Row 2: Status Pekerjaan (Clean Segmented Control 1 Baris) -->
            <div class="task-status-section">
              <div class="task-filter-header-bar">
                <span class="task-filter-title">Status Pekerjaan</span>
              </div>
              <div class="task-status-grid" id="status-filter-group">
                <button type="button" class="filter-btn active" onclick="filterByStatus('all', this)">Semua Status</button>
                <button type="button" class="filter-btn" onclick="filterByStatus('revision', this)">Revisi</button>
                <button type="button" class="filter-btn" onclick="filterByStatus('client_review', this)">Review Klien</button>
                <button type="button" class="filter-btn" onclick="filterByStatus('in_progress', this)">In Progress</button>
                <button type="button" class="filter-btn" onclick="filterByStatus('approved', this)">Selesai</button>
              </div>
            </div>

            <!-- Row 3: SDM Dropdown, Search Box, & Task Counter -->
            <div class="task-bottom-toolbar">
              <div class="task-bottom-left-group">
                <?php if ($isAdminUser): ?>
                  <div class="task-select-wrap">
                    <select id="filter-worker-type" class="form-control task-sdm-select" onchange="applyFilters()">
                      <option value="">Semua SDM (Karyawan &amp; Freelancer)</option>
                      <option value="karyawan">Karyawan (In-House)</option>
                      <option value="freelancer">Freelancer</option>
                    </select>
                  </div>
                <?php endif; ?>

                <div class="task-search-wrap">
                  <span class="task-search-icon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                  </span>
                  <input type="text" id="task-search-input" class="form-control task-search-input" placeholder="Cari deliverables, klien, PIC..." oninput="handleTaskSearch()">
                  <button type="button" class="task-search-clear" id="task-search-clear-btn" style="display: none;" onclick="clearTaskSearch()">&times;</button>
                </div>
              </div>

              <div class="task-count-pill" id="tasks-count-label">
                Menampilkan 0 tugas
              </div>
            </div>
          </div>

          <!-- Tasks Grid / List Container -->
          <div class="tasks-grid-list" id="tasks-list-container">
            <div style="text-align: center; padding: 48px; background: #FFF; border-radius: var(--radius-md); border: 1px solid var(--border-color); color: var(--text-muted);">
              Memuat data tugas...
            </div>
          </div>
        </div>

        <!-- SECTION 2: Ads Budget & Spend Tracker -->
        <div id="section-ads-content" style="display: none;">
          <!-- Active Hierarchical Notifications Container (Tim Ads <-> Owner <-> Client) -->
          <div id="ads-notifications-container" style="margin-bottom: 16px;"></div>

          <!-- Ads Tracker Header & Controls (Clean SaaS Header) -->
          <div class="page-header-bar" style="background: #FFFFFF; border: 1px solid #EAECEF; border-radius: 12px; padding: 18px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; box-shadow: 0 1px 2px rgba(16,24,40,0.04);">
            <div>
              <h2 class="panel-title" style="font-size: 16px; font-weight: 600; color: #111827; margin: 0; letter-spacing: -0.015em;">
                Pelacakan Dana &amp; Anggaran Iklan (Ads Tracker)
              </h2>
              <p style="font-size: 12.5px; color: #6B7280; margin: 3px 0 0;">
                <?= ($isAdminUser || $isOwnerUser)
                  ? 'Kawal alur dana iklan 2 tahap (Owner &rarr; Tim Ads &rarr; Meta/Google), pantau sisa pagu, dan verifikasi bukti transaksi secara presisi.' 
                  : 'Pantau saldo dana iklan yang diterima dari Owner, laporkan bukti top-up ke Meta/Google Ads, dan catat realisasi spend berjalan.' ?>
              </p>
            </div>

            <div class="ads-tracker-header-actions" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
              <?php if ($isAdminUser || $isOwnerUser): ?>
                <button type="button" class="btn btn-primary btn-sm" onclick="openTransferToWorkerModal()" style="font-weight: 700; background: #175CD3; border-color: #175CD3;">
                  <span>+ Transfer Saldo ke Tim Ads</span>
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="openAllocateAdsModal()">
                  <span>+ Atur Pagu Saldo Ads</span>
                </button>
              <?php else: ?>
                <button type="button" class="btn btn-primary btn-sm" onclick="openConfirmPlatformTopupModal()" style="font-weight: 700; background: #027A48; border-color: #027A48;">
                  <span>+ Konfirmasi Top-Up &amp; Spend</span>
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="openRequestBalanceWorkerModal()" style="color: #B42318; border-color: #FDA29B;">
                  <span>🔔 Minta Saldo ke Owner</span>
                </button>
              <?php endif; ?>
              <button type="button" class="btn btn-secondary btn-sm" onclick="loadAdsTrackerData()">
                <span>Segarkan Data</span>
              </button>
            </div>
          </div>

          <!-- Summary KPI Cards -->
          <div class="kpi-row" id="ads-kpi-container">
            <?php if ($isOwnerOrAdmin): ?>
              <div class="kpi-tile">
                <span style="font-size: 11.5px; color: var(--text-secondary); font-weight: 600;">Memuat Ringkasan Owner...</span>
              </div>
            <?php else: ?>
              <!-- KARYAWAN / FREELANCER (TIM ADS): HANYA 2 KARTU (Saldo & Sisa Saldo) -->
              <div class="kpi-tile">
                <div class="metric-header">
                  <span class="metric-label">Saldo</span>
                  <span class="metric-icon">
                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="#175CD3" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                  </span>
                </div>
                <div class="metric-value">
                  <span class="metric-num" style="color: #175CD3;">Rp <?= number_format($total_ditransfer_owner, 0, ',', '.') ?></span>
                </div>
                <div class="metric-subtitle">
                  <span>Nominal saldo yang ditransfer oleh Owner</span>
                </div>
              </div>

              <div class="kpi-tile <?= $sisa_saldo <= 0 ? 'urgent' : '' ?>">
                <div class="metric-header">
                  <span class="metric-label" style="color: <?= $sisa_saldo <= 0 ? '#B42318' : '#027A48' ?>;">Sisa Saldo</span>
                  <span class="metric-icon">
                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="<?= $sisa_saldo <= 0 ? '#B42318' : '#027A48' ?>" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                  </span>
                </div>
                <div class="metric-value">
                  <span class="metric-num" style="color: <?= $sisa_saldo <= 0 ? '#B42318' : '#027A48' ?>;">Rp <?= number_format($sisa_saldo, 0, ',', '.') ?></span>
                </div>
                <div class="metric-subtitle">
                  <span>Sudah dibelanjakan: Rp <?= number_format($total_dibelanjakan_ads, 0, ',', '.') ?> &bull; Status: <?= $status_iklan ?></span>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <!-- Top-Up Transactions & Proof Table Card (Automatic Sync to Client Portal) -->
          <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(16,24,40,0.05); min-width: 0; max-width: 100%; overflow: visible;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
              <div>
                <div style="display: flex; align-items: center; gap: 8px;">
                  <h4 style="font-size: 15px; font-weight: 800; color: #101828; margin: 0;">
                    <?= $isOwnerOrAdmin ? 'Riwayat &amp; Bukti Transaksi Saldo Iklan (Alur 2 Tahap)' : 'Riwayat Saldo &amp; Bukti Belanja Iklan' ?>
                  </h4>
                  <span class="badge" style="background: #ECFDF3; color: #027A48; font-weight: 700; font-size: 10.5px;">Tampil di Portal Klien</span>
                </div>
                <span style="font-size: 12px; color: var(--text-secondary);">
                  <?= $isOwnerOrAdmin 
                    ? 'Catatan pencairan dari Owner ke Tim Ads dan penyetoran saldo ke platform Meta Ads / Google Ads beserta lampiran bukti fisik.'
                    : 'Catatan saldo yang diterima dari Owner dan bukti realisasi belanja iklan ke platform ads.' ?>
                </span>
              </div>
              <?php if ($isOwnerOrAdmin): ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="openTransferToWorkerModal()" style="font-size: 12px; font-weight: 700; color: #175CD3; border-color: #B2CCFF; background: #EFF8FF;">
                  + Transfer Saldo Baru
                </button>
              <?php endif; ?>
            </div>

            <div class="kala-table-scroll table-responsive" style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; display:block;">
              <table class="table-custom" id="table-ads-topups" style="min-width: 980px; width: 100%;">
                <thead>
                  <?php if ($isOwnerOrAdmin): ?>
                    <tr>
                      <th style="min-width: 190px; white-space: nowrap;">TANGGAL &amp; PETUGAS ADS</th>
                      <th style="white-space: nowrap;">PROYEK &amp; PLATFORM</th>
                      <th style="white-space: nowrap;">TAHAP 1: OWNER &rarr; TIM ADS</th>
                      <th style="white-space: nowrap;">TAHAP 2: TIM ADS &rarr; META/GOOGLE</th>
                      <th style="white-space: nowrap;">REALISASI SPEND &amp; SISA</th>
                      <th style="white-space: nowrap;">STATUS KAMPANYE</th>
                      <th class="th-actions" style="text-align: right; white-space: nowrap; min-width: 140px;">AKSI</th>
                    </tr>
                  <?php else: ?>
                    <tr>
                      <th style="white-space: nowrap;">TANGGAL &amp; PROYEK</th>
                      <th style="white-space: nowrap;">PLATFORM ADS</th>
                      <th style="white-space: nowrap;">SALDO (TRANSFER DARI OWNER + BUKTI TF OWNER)</th>
                      <th style="white-space: nowrap;">SUDAH DIBELANJAKAN (BUKTI TOP-UP/SPEND ADS)</th>
                      <th style="white-space: nowrap;">SISA SALDO</th>
                      <th style="white-space: nowrap;">STATUS KAMPANYE</th>
                      <th class="th-actions" style="text-align: right; white-space: nowrap; min-width: 140px;">AKSI</th>
                    </tr>
                  <?php endif; ?>
                </thead>
                <tbody id="tbody-ads-topups">
                  <tr><td colspan="7" style="text-align: center; padding: 24px; color: var(--text-muted);">Memuat riwayat isi saldo ads...</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Projects Ads Budget Table Card (HANYA UNTUK OWNER & ADMIN) -->
          <?php if ($isOwnerOrAdmin): ?>
          <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(16,24,40,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: gap: 8px;">
              <div>
                <h4 style="font-size: 15px; font-weight: 800; color: #101828; margin: 0;">Status Alokasi &amp; Anggaran Terpasang per Proyek</h4>
                <span style="font-size: 12px; color: var(--text-secondary);">
                  Sinkronisasi Kontrak, Margin Agensi, Pagu Saldo Ads Klien, Pencairan ke Tim Ads, dan Sisa Pagu di Tangan Owner
                </span>
              </div>
            </div>

            <div class="kala-table-scroll table-responsive" style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; display:block;">
              <table class="table-custom" id="table-ads-projects" style="min-width: 980px; width: 100%;">
                <thead>
                  <tr id="thead-ads-projects-row">
                    <th>PROYEK &amp; KLIEN</th>
                    <th>NILAI KONTRAK (RAHASIA)</th>
                    <th>MARGIN BERSIH AGENSI</th>
                    <th>TOTAL PAGU ADS KLIEN</th>
                    <th>DITRANSFER KE TIM ADS</th>
                    <th>SISA PAGU DI OWNER</th>
                    <th>STATUS KAMPANYE</th>
                    <th class="th-actions" style="text-align: right; white-space: nowrap; min-width: 200px;">AKSI</th>
                  </tr>
                </thead>
                <tbody id="tbody-ads-projects">
                  <tr><td colspan="8" style="text-align: center; padding: 28px; color: var(--text-muted);">Memuat data anggaran ads...</td></tr>
                </tbody>
              </table>
            </div>
          </div>
          <?php endif; ?>

          <!-- Coordination Logs & Screenshot Proof Feed -->
          <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px; box-shadow: 0 1px 3px rgba(16,24,40,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
              <div>
                <h4 style="font-size: 15px; font-weight: 800; color: #101828; margin: 0;">Riwayat Update & Bukti Screenshot Dashboard Ads</h4>
                <span style="font-size: 12px; color: var(--text-secondary);">Catatan koordinasi langsung antara Tim Ads dan Owner beserta screenshot dashboard</span>
              </div>
            </div>

            <div id="ads-logs-feed-container">
              <div style="text-align: center; padding: 28px; color: var(--text-muted);">Memuat riwayat koordinasi...</div>
            </div>
          </div>
        </div>

      </div>
    </main>
  </div>

  <!-- MODAL: Assign New Task (Owner / Admin) -->
  <?php if ($isAdminUser): ?>
    <div class="modal" id="modal-assign-task">
      <div class="modal-dialog" style="max-width: 620px;">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <h4 class="modal-title">Bagi Tugas Baru (Assign Task)</h4>
              <span style="font-size: 12px; color: var(--text-secondary);">Tugaskan pekerjaan proyek ke Karyawan Internal atau Freelancer</span>
            </div>
            <button type="button" class="close-btn" onclick="closeModal('modal-assign-task')">&times;</button>
          </div>

          <form id="form-assign-task" onsubmit="handleAssignTask(event)">
            <div class="modal-body">
              <div class="form-group">
                <label class="form-label">Pilih Proyek & Klien <span style="color:red;">*</span></label>
                <select name="project_id" id="assign-project-id" class="form-control" onchange="onCategoryOrProjectChange()" required>
                  <option value="">-- Pilih Proyek Klien --</option>
                </select>
                <div id="assign-project-contract-info" style="display:none; font-size: 11.5px; font-weight: 600; color: #475467; margin-top: 4px;"></div>
              </div>

              <div class="form-group">
                <label class="form-label">Judul Pekerjaan / Deliverable <span style="color:red;">*</span></label>
                <input type="text" name="title" class="form-control" placeholder="Contoh: Desain Carousel Feed Promo Merdeka" required>
              </div>

              <div class="form-row kala-form-row">
                <div class="form-group">
                  <label class="form-label">Kategori Pekerjaan (Jasa Kalamedia) <span style="color:red;">*</span></label>
                  <select name="category" id="assign-category" class="form-control" onchange="onCategoryOrProjectChange()" required>
                    <option value="Kelola Sosmed">Kelola Sosmed (Social Media Management)</option>
                    <option value="Desain Eceran / Satuan">Desain Eceran / Satuan (Desain 1-an)</option>
                    <option value="Jasa Buat Web">Jasa Buat Web (Website &amp; Landing Page)</option>
                    <option value="Jasa Iklan Meta & Google">Jasa Iklan Meta &amp; Google (Meta Ads &amp; Google Ads)</option>
                  </select>
                </div>

                <div class="form-group">
                  <label class="form-label">Tugaskan Kepada (PIC) <span style="color:red;">*</span></label>
                  <select name="assigned_to_user_id" id="assign-member-id" class="form-control" onchange="onAssignMemberChange()" required>
                    <option value="">-- Pilih Anggota Tim / Freelancer --</option>
                  </select>
                  <div id="assign-worker-contract-info" style="display:none; font-size: 11.5px; font-weight: 700; color: #175CD3; margin-top: 4px; padding: 4px 8px; background: #EFF8FF; border-radius: 4px; border: 1px solid #B2DDFF;"></div>
                </div>
              </div>

              <div class="form-row kala-form-row">
                <div class="form-group">
                  <label class="form-label">Mulai Pengerjaan (Start Date) <span style="color:red;">*</span></label>
                  <input type="date" name="start_date" id="assign-start-date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                  <label class="form-label">Tenggat Selesai (Deadline) <span style="color:red;">*</span></label>
                  <input type="date" name="due_date" id="assign-due-date" class="form-control" value="<?= date('Y-m-d', strtotime('+3 days')) ?>" required>
                </div>
              </div>

              <!-- Ads Budget Allocation Section (Visible when category is Ads or Ads project selected) -->
              <div id="assign-ads-budget-section" style="display:none; background: #F8F9FC; border: 1.5px solid #D0D5DD; border-radius: 12px; padding: 14px; margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                  <span style="font-size: 12px; font-weight: 700; color: #101828; display: flex; align-items: center; gap: 6px;">
                    🎯 Alokasi Saldo Ads &amp; Bukti Transfer ke Tim Ads
                  </span>
                  <span id="assign-available-pool-badge" class="badge" style="background: #ECFDF3; color: #027A48; font-weight: 700; font-size: 11px;">
                    Sisa Saldo Ads: Rp 0
                  </span>
                </div>

                <div class="form-row kala-form-row">
                  <div class="form-group" style="margin-bottom: 10px;">
                    <label class="form-label" style="font-size: 11.5px; font-weight: 700;">Platform Iklan</label>
                    <select name="ads_platform" id="assign-ads-platform" class="form-control">
                      <option value="Meta Ads">Meta Ads (Facebook &amp; Instagram)</option>
                      <option value="Google Ads">Google Ads (Search &amp; Youtube)</option>
                      <option value="TikTok Ads">TikTok Ads</option>
                    </select>
                  </div>

                  <div class="form-group" style="margin-bottom: 10px;">
                    <label class="form-label" style="font-size: 11.5px; font-weight: 700; color: #027A48;">
                      Budget Ads Diberikan ke PIC (Rp) *
                    </label>
                    <div class="input-group-currency">
                      <span class="currency-addon" style="background: #ECFDF3; color: #027A48; font-weight: 700;">Rp</span>
                      <input type="text" name="assigned_ads_budget" id="assign-ads-budget-input" class="form-control rupiah-input" placeholder="0">
                    </div>
                  </div>
                </div>

                <div class="form-group" style="margin-bottom: 10px;">
                  <label class="form-label" style="font-size: 11.5px; font-weight: 700;">Upload Bukti Transfer Dana Iklan (JPG/PNG/PDF)</label>
                  <input type="file" name="transfer_proof_file" class="form-control" accept="image/*,.pdf">
                  <span style="font-size: 11px; color: var(--text-muted); margin-top: 3px; display: block;">
                    Bukti transfer ini wajib terlihat oleh Orang Ads dan oleh Klien di Portal Klien.
                  </span>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                  <label class="form-label" style="font-size: 11.5px;">Status Tayang Awal</label>
                  <select name="campaign_run_status" class="form-control">
                    <option value="running" selected>Iklan Langsung Jalan (Running)</option>
                    <option value="paused">Iklan Dijeda Sementara (Paused / Menunggu Materi)</option>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Prioritas Tugas</label>
                <select name="priority" class="form-control">
                  <option value="medium" selected>Medium</option>
                  <option value="high">High (Tinggi)</option>
                  <option value="urgent">Urgent (Segera)</option>
                  <option value="low">Low (Santai)</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Deskripsi Brief / Detail Pengerjaan</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Tuliskan spesifikasi teknis, dimensi, referensi hook, atau instruksi khusus untuk PIC..."></textarea>
              </div>

              <div class="form-group">
                <label class="form-label">Link Dokumen / Referensi Awal (Opsional)</label>
                <input type="url" name="deliverable_link" class="form-control" placeholder="https://drive.google.com/... atau https://figma.com/...">
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" onclick="closeModal('modal-assign-task')">Batal</button>
              <button type="submit" class="btn btn-primary" id="btn-submit-assign">
                Bagikan Tugas & Notifikasi Tim
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- MODAL: Update Ads Spending & Campaign Status (Ads PIC / Worker / Admin) -->
  <div class="modal" id="modal-update-ads-spend-task">
    <div class="modal-dialog" style="max-width: 560px;">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h4 class="modal-title">Update Pemakaian &amp; Status Iklan</h4>
            <span style="font-size: 12px; color: var(--text-secondary);" id="ads-spend-modal-subtitle">Monitoring Real-Time Kampanye Ads</span>
          </div>
          <button type="button" class="close-btn" onclick="closeModal('modal-update-ads-spend-task')">&times;</button>
        </div>

        <form id="form-update-ads-spend" onsubmit="handleUpdateAdsSpend(event)">
          <input type="hidden" name="task_id" id="ads-spend-task-id">

          <div class="modal-body">
            <!-- Campaign Info & Budget Card -->
            <div style="background: #F8F9FC; border: 1px solid #EAECF0; border-radius: 12px; padding: 14px; margin-bottom: 16px;">
              <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                <div>
                  <span style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #6E7485;">Tugas / Kampanye Ads</span>
                  <div style="font-size: 15px; font-weight: 800; color: #101828; margin: 2px 0 2px;" id="ads-spend-task-title">-</div>
                  <div style="font-size: 12px; color: #475467;" id="ads-spend-client-company">-</div>
                </div>
                <div style="text-align: right;">
                  <span id="ads-spend-platform-badge" class="badge" style="background: #F4F3FF; color: #5925DC; font-weight: 700; font-size: 11px;">Meta Ads</span>
                </div>
              </div>

              <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; border-top: 1px dashed #D0D5DD; margin-top: 10px; padding-top: 10px; text-align: center;">
                <div>
                  <span style="font-size: 10.5px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; display: block;">Total Budget</span>
                  <span id="ads-spend-total-budget" style="font-size: 13.5px; font-weight: 800; color: #101828;">Rp 0</span>
                </div>
                <div>
                  <span style="font-size: 10.5px; color: #B42318; font-weight: 600; text-transform: uppercase; display: block;">Sudah Dibelanjakan</span>
                  <span id="ads-spend-current-spent" style="font-size: 13.5px; font-weight: 800; color: #B42318;">Rp 0</span>
                </div>
                <div>
                  <span style="font-size: 10.5px; color: #027A48; font-weight: 600; text-transform: uppercase; display: block;">Sisa Saldo Terhitung</span>
                  <span id="ads-spend-calc-remaining" style="font-size: 13.5px; font-weight: 800; color: #027A48;">Rp 0</span>
                </div>
              </div>
            </div>

            <div class="form-row kala-form-row">
              <div class="form-group">
                <label class="form-label">Saldo Sudah Dibelanjakan (Rp) <span style="color:red;">*</span></label>
                <div class="input-group-currency">
                  <span class="currency-addon" style="background: #FEF3F2; color: #B42318; font-weight: 700;">Rp</span>
                  <input type="text" name="ads_spent_amount" id="ads-spend-input-amount" class="form-control rupiah-input" placeholder="0" oninput="recalcModalAdsRemaining()" required>
                </div>
                <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                  Input total akumulasi saldo yang sudah dibelanjakan hari/minggu ini.
                </small>
              </div>

              <div class="form-group">
                <label class="form-label">Status Penayangan Iklan Real-Time <span style="color:red;">*</span></label>
                <select name="campaign_run_status" id="ads-spend-run-status" class="form-control" required>
                  <option value="running">🟢 Iklan Jalan (Running)</option>
                  <option value="paused">🟡 Iklan Dijeda (Paused)</option>
                  <option value="stopped">🔴 Iklan Berhenti / Selesai (Stopped)</option>
                </select>
                <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                  Status ini langsung tampil di dashboard Karyawan, Client, dan Owner.
                </small>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Upload Screenshot Ads Manager (Opsional)</label>
              <input type="file" name="ads_spent_proof" id="ads-spend-proof-file" class="form-control" accept="image/*,.pdf">
              <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                Lampirkan tangkapan layar dashboard Meta/Google Ads Manager sebagai bukti transparansi.
              </small>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label">Catatan Kinerja / Catatan PIC</label>
              <textarea name="ads_spent_notes" id="ads-spend-notes" class="form-control" rows="2" placeholder="Contoh: CTR 3.2%, CPC Rp 450, performa stabil, budget harian Rp 100.000..."></textarea>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-update-ads-spend-task')">Batal</button>
            <button type="submit" class="btn btn-primary" id="btn-submit-ads-spend" style="background: #175CD3; border-color: #175CD3;">
              Simpan &amp; Perbarui Metrik Iklan
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL: Update Progress (Worker / Admin) -->
  <div class="modal" id="modal-update-progress">
    <div class="modal-dialog" style="max-width: 540px;">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h4 class="modal-title">Perbarui Status Progres & Hasil Kerja</h4>
            <span style="font-size: 12px; color: var(--text-secondary);" id="progress-modal-subtitle">Update deliverable link</span>
          </div>
          <button type="button" class="close-btn" onclick="closeModal('modal-update-progress')">&times;</button>
        </div>

        <form id="form-update-progress" onsubmit="handleProgressSubmit(event)">
          <input type="hidden" name="task_id" id="progress-task-id">

          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Status Alur Pengerjaan <span style="color:red;">*</span></label>
              <select name="status" id="progress-status-select" class="form-control" required>
                <option value="in_progress">Sedang Dikerjakan (In Progress)</option>
                <option value="internal_review">Review Internal Tim (Internal Review)</option>
                <option value="client_review">Menunggu Review Klien (Client Review)</option>
                <option value="revision">Dalam Perbaikan (Revision)</option>
                <option value="approved">Disetujui (Approved)</option>
                <option value="backlog">Antrean (Backlog)</option>
              </select>
              <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 4px;">
                Pilih "Menunggu Review Klien" jika pekerjaan sudah siap ditinjau oleh pihak klien.
              </small>
            </div>

            <div class="form-group">
              <label class="form-label">Tautan Hasil Kerja / Deliverable Preview</label>
              <input type="url" name="deliverable_link" id="progress-link-input" class="form-control" placeholder="https://drive.google.com/... atau https://figma.com/... atau Loom">
            </div>

            <div class="form-group">
              <label class="form-label">Catatan Tambahan (Opsional)</label>
              <textarea name="notes" class="form-control" rows="2" placeholder="Tuliskan catatan progres atau perubahan yang dilakukan..."></textarea>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-update-progress')">Batal</button>
            <button type="submit" class="btn btn-primary" id="btn-submit-progress">
              Simpan Progres
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL: Discussion & Feedback Thread (Worker / Admin View) -->
  <div class="modal" id="modal-task-discussion">
    <div class="modal-dialog" style="max-width: 620px;">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h4 class="modal-title" id="disc-task-title">Kotak Diskusi Masukan Klien</h4>
            <span style="font-size: 12px; color: var(--text-secondary);" id="disc-task-meta">Memuat rincian...</span>
          </div>
          <button type="button" class="close-btn" onclick="closeModal('modal-task-discussion')">&times;</button>
        </div>

        <div class="modal-body">
          <div id="disc-preview-box" style="background: #F8FAFC; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 12px; color: var(--text-secondary); font-weight: 600;">Link Preview Hasil Kerja:</span>
            <a id="disc-preview-btn" href="#" target="_blank" class="btn btn-secondary btn-sm" style="font-size: 11.5px; font-weight: 700;">
              Buka Preview Link
            </a>
          </div>

          <!-- Discussion History -->
          <div class="discussion-thread" id="disc-thread-container">
            <div style="text-align: center; color: var(--text-muted); font-size: 12px; padding: 16px;">
              Memuat diskusi...
            </div>
          </div>

          <!-- Reply Form -->
          <form id="form-reply-feedback" onsubmit="handleReplyFeedback(event)">
            <input type="hidden" name="task_id" id="reply-task-id">
            <input type="hidden" name="feedback_type" value="comment">

            <div class="form-group" style="margin-bottom: 10px;">
              <label class="form-label" style="font-size: 12px; font-weight: 700;">Kirim Tanggapan / Jawaban Masukan</label>
              <textarea name="message" id="reply-message" class="form-control" rows="2" placeholder="Tulis tanggapan atau konfirmasi perbaikan untuk klien..." required></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
              <label class="form-label" style="font-size: 11px; color: var(--text-secondary);">Unggah Hasil Revisi / Screenshot (Opsional)</label>
              <input type="file" name="attachment" id="reply-attachment" class="form-control">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
              <button type="button" class="btn btn-secondary" onclick="closeModal('modal-task-discussion')">Tutup</button>
              <button type="submit" class="btn btn-primary" id="btn-submit-reply">
                Kirim Balasan
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL: Update Anggaran Terpasang & Bukti Ads (Tim Ads / Workers / Admin) -->
  <div class="modal" id="modal-update-ads-spend">
    <div class="modal-dialog" style="max-width: 580px;">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h4 class="modal-title">Update Anggaran Terpasang & Bukti Dashboard</h4>
            <span style="font-size: 12px; color: var(--text-secondary);">Laporkan nominal belanja iklan yang sudah dipasang di platform</span>
          </div>
          <button type="button" class="close-btn" onclick="closeModal('modal-update-ads-spend')">&times;</button>
        </div>

        <form id="form-update-ads-spend" onsubmit="handleLogAdsSpend(event)" enctype="multipart/form-data">
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Proyek & PT Klien <span style="color:red;">*</span></label>
              <select name="project_id" id="ads-spend-project-id" class="form-control" required onchange="onAdsProjectSelectChange(this)">
                <option value="">-- Pilih Proyek Klien --</option>
              </select>
            </div>

            <!-- Info Pagu Saldo Ditugaskan Owner -->
            <div id="box-allocated-info" style="display: none; background: #F4F3FF; border: 1px solid #D9D6FE; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px;">
              <div style="font-size: 11.5px; color: #5925DC; font-weight: 700; text-transform: uppercase;">Pagu Saldo Ads Ditugaskan Owner</div>
              <div style="font-size: 18px; font-weight: 800; color: #101828;" id="txt-allocated-budget">Rp 0</div>
            </div>

            <div class="form-row kala-form-row">
              <div class="form-group">
                <label class="form-label">Platform Iklan <span style="color:red;">*</span></label>
                <select name="platform" id="ads-spend-platform" class="form-control" required>
                  <option value="Meta Ads">Meta Ads (Instagram & FB)</option>
                  <option value="Google Ads">Google Ads / YouTube</option>
                  <option value="TikTok Ads">TikTok Ads</option>
                  <option value="Multi-Platform">Multi-Platform</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Status Kampanye <span style="color:red;">*</span></label>
                <select name="status" id="ads-spend-status" class="form-control" required>
                  <option value="sedang_berjalan">Sedang Berjalan</option>
                  <option value="menunggu_saldo">Menunggu Saldo</option>
                  <option value="saldo_hampir_habis">Saldo Hampir Habis</option>
                  <option value="selesai">Selesai</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Anggaran yang Sudah Terpasang / Terpakai (Rp) <span style="color:red;">*</span></label>
              <input type="text" name="spend_installed" id="ads-spend-amount" class="form-control" placeholder="Contoh: 5.000.000" required oninput="formatRupiahInput(this)">
              <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                Nominal akumulatif atau spend saat ini yang terserap di dashboard platform ads.
              </small>
            </div>

            <div class="form-group">
              <label class="form-label">Catatan Koordinasi untuk Owner</label>
              <textarea name="notes" id="ads-spend-notes" class="form-control" rows="2" placeholder="Contoh: Saldo Rp 5.000.000 sudah masuk ke Meta Ads Manager. Campaign Advantage+ Shopping aktif, CTR 2.3%"></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">Bukti Screenshot Dashboard Ads (Opsional)</label>
              <input type="file" name="attachment_proof" id="ads-spend-attachment" class="form-control" accept="image/*,.pdf">
              <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                Unggah tangkapan layar (screenshot) billing / dashboard platform iklan sebagai bukti fisik.
              </small>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-update-ads-spend')">Batal</button>
            <button type="submit" class="btn btn-primary" id="btn-submit-ads-spend">
              Simpan & Koordinasikan ke Owner
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL: Upload Bukti Transaksi Isi Saldo Ads (Tim Ads / Workers / Admin / Owner) -->
  <div class="modal" id="modal-upload-ads-topup">
    <div class="modal-dialog" style="max-width: 620px;">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h4 class="modal-title">+ Upload Bukti Transaksi Isi Saldo Ads</h4>
            <span style="font-size: 12px; color: var(--text-secondary);">Upload bukti pengisian saldo iklan klien. Otomatis tampil di Portal Klien.</span>
          </div>
          <button type="button" class="close-btn" onclick="closeModal('modal-upload-ads-topup')">&times;</button>
        </div>

        <form id="form-upload-ads-topup" onsubmit="handleUploadAdsTopup(event)" enctype="multipart/form-data">
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Pilih Klien / Proyek PT <span style="color:red;">*</span></label>
              <select name="project_id" id="ads-topup-project-id" class="form-control" required onchange="onTopupProjectChange(this)">
                <option value="">-- Pilih Proyek Klien --</option>
              </select>
            </div>

            <!-- Pagu Saldo Info Card -->
            <div id="topup-allocated-info" style="display: none; background: #F4F3FF; border: 1px solid #D9D6FE; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px;">
              <div style="font-size: 11px; color: #5925DC; font-weight: 700; text-transform: uppercase;">Pagu Saldo Ads Ditugaskan Owner</div>
              <div style="font-size: 17px; font-weight: 800; color: #101828;" id="txt-topup-allocated-budget">Rp 0</div>
            </div>

            <div class="form-row kala-form-row">
              <div class="form-group">
                <label class="form-label">Platform Iklan <span style="color:red;">*</span></label>
                <select name="platform" id="ads-topup-platform" class="form-control" required>
                  <option value="Meta Ads">Meta Ads (Instagram & FB)</option>
                  <option value="Google Ads">Google Ads / YouTube</option>
                  <option value="TikTok Ads">TikTok Ads</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Tanggal Transaksi <span style="color:red;">*</span></label>
                <input type="date" name="transaction_date" id="ads-topup-date" class="form-control" value="<?= date('Y-m-d') ?>" required>
              </div>
            </div>

            <div class="form-row kala-form-row">
              <div class="form-group">
                <label class="form-label">Nominal Isi Saldo / Top-Up (Rp) <span style="color:red;">*</span></label>
                <input type="text" name="topup_amount" id="ads-topup-amount" class="form-control" placeholder="Contoh: 2.000.000" required oninput="formatRupiahInput(this)">
                <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 2px;">
                  Jumlah saldo iklan yang baru saja dibayarkan.
                </small>
              </div>

              <div class="form-group">
                <label class="form-label">Nominal Anggaran Terpasang (Rp) <span style="color:red;">*</span></label>
                <input type="text" name="spend_installed" id="ads-topup-spend" class="form-control" placeholder="Contoh: 5.000.000" required oninput="formatRupiahInput(this)">
                <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 2px;">
                  Akumulasi anggaran iklan yang sedang jalan saat ini.
                </small>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Upload Bukti Transaksi / Struk Top-Up (Wajib) <span style="color:red;">*</span></label>
              <input type="file" name="receipt_file" id="ads-topup-file" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf" required>
              <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 2px;">
                Format yang didukung: JPG, PNG, WEBP, PDF (Maks. 10MB). File otomatis tersimpan di <code>assets/uploads/receipts/</code>.
              </small>
            </div>

            <div class="form-group">
              <label class="form-label">Catatan untuk Klien & Owner</label>
              <textarea name="notes" id="ads-topup-notes" class="form-control" rows="2" placeholder="Contoh: Top-up saldo Meta Ads kampanye Oktober sudah masuk"></textarea>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-upload-ads-topup')">Batal</button>
            <button type="submit" class="btn btn-primary" id="btn-submit-ads-topup" style="background: #027A48; border-color: #027A48;">
              Simpan & Sinkronkan ke Portal Klien
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL: Atur Pagu Saldo Ads ke Proyek (Owner / Admin Only) -->
  <?php if ($isAdminUser): ?>
    <div class="modal" id="modal-allocate-ads-budget">
      <div class="modal-dialog" style="max-width: 520px;">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <h4 class="modal-title">Atur Pagu Saldo Ads ke Proyek</h4>
              <span style="font-size: 12px; color: var(--text-secondary);">Tentukan anggaran murni yang dialokasikan khusus untuk belanja platform iklan</span>
            </div>
            <button type="button" class="close-btn" onclick="closeModal('modal-allocate-ads-budget')">&times;</button>
          </div>

          <form id="form-allocate-ads-budget" onsubmit="handleAllocateAdsBudget(event)">
            <div class="modal-body">
              <div class="form-group">
                <label class="form-label">Pilih Proyek Klien <span style="color:red;">*</span></label>
                <select name="project_id" id="ads-alloc-project-id" class="form-control" required>
                  <option value="">-- Pilih Proyek Klien --</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Pagu Saldo Iklan yang Dialokasikan (Rp) <span style="color:red;">*</span></label>
                <input type="text" name="allocated_budget" id="ads-alloc-amount" class="form-control" placeholder="Contoh: 10.000.000" required oninput="formatRupiahInput(this)">
                <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                  Pagu ini yang akan menjadi batas penyerapan saldo bagi Tim Ads di dashboard iklan.
                </small>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" onclick="closeModal('modal-allocate-ads-budget')">Batal</button>
              <button type="submit" class="btn btn-primary" id="btn-submit-ads-alloc">
                Simpan Alokasi Saldo
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- MODAL: Lightbox Preview Bukti Screenshot Dashboard Ads -->
  <div class="modal" id="modal-preview-ads-proof">
    <div class="modal-dialog" style="max-width: 800px;">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h4 class="modal-title">Bukti Screenshot Dashboard Ads</h4>
            <span style="font-size: 12px; color: var(--text-secondary);">Tangkapan layar otentik platform periklanan</span>
          </div>
          <button type="button" class="close-btn" onclick="closeModal('modal-preview-ads-proof')">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center; padding: 16px;">
          <img id="img-proof-preview" src="" alt="Bukti Dashboard Ads" style="max-width: 100%; max-height: 520px; object-fit: contain; border-radius: 8px; border: 1px solid var(--border-color); display: none;">
          <iframe id="iframe-proof-preview" src="" style="width: 100%; height: 500px; border: none; display: none;"></iframe>
        </div>
      </div>
    </div>
  </div>

  <?php if ($isOwnerOrAdmin): ?>
  <!-- MODAL: Transfer Saldo Ads Owner -> Tim Ads (Owner / Admin Only) -->
  <div class="modal" id="modal-transfer-to-worker">
    <div class="modal-dialog" style="max-width: 580px;">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h4 class="modal-title">+ Cairkan Saldo Ads ke Tim Ads</h4>
            <span style="font-size: 12px; color: var(--text-secondary);">Owner mentransfer dana dari sisa pagu ke Tim Ads pelaksana beserta bukti transfer</span>
          </div>
          <button type="button" class="close-btn" onclick="closeModal('modal-transfer-to-worker')">&times;</button>
        </div>

        <form id="form-transfer-to-worker" onsubmit="handleTransferToWorker(event)" enctype="multipart/form-data">
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Pilih Proyek Klien <span style="color:red;">*</span></label>
              <select name="project_id" id="transfer-worker-project-id" class="form-control" required onchange="onTransferProjectChange(this)">
                <option value="">-- Pilih Proyek Klien --</option>
              </select>
            </div>

            <!-- Sisa Pagu Saldo di Owner Info Box -->
            <div id="box-transfer-sisa-pagu" style="display: none; background: #EFF8FF; border: 1px solid #B2DDFF; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px;">
              <div style="font-size: 11px; color: #175CD3; font-weight: 700; text-transform: uppercase;">Sisa Pagu Saldo Ads di Tangan Owner</div>
              <div style="font-size: 18px; font-weight: 800; color: #101828; margin-top: 2px;" id="txt-transfer-sisa-pagu">Rp 0</div>
              <div style="font-size: 11.5px; color: #475467; margin-top: 2px;" id="txt-transfer-pagu-info">Batas dana iklan yang masih tersedia untuk dicairkan ke Tim Ads.</div>
            </div>

            <div class="form-row kala-form-row">
              <div class="form-group">
                <label class="form-label">Tugaskan ke Tim Ads (PIC) <span style="color:red;">*</span></label>
                <select name="assigned_user_id" id="transfer-worker-user-id" class="form-control" required>
                  <option value="">-- Pilih Tim Ads --</option>
                  <?php foreach ($allTeamWorkers as $w): ?>
                    <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?> (<?= htmlspecialchars($w['role'] ?? 'Tim') ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Platform Iklan <span style="color:red;">*</span></label>
                <select name="platform" id="transfer-worker-platform" class="form-control" required>
                  <option value="Meta Ads">Meta Ads (Instagram &amp; FB)</option>
                  <option value="Google Ads">Google Ads / YouTube</option>
                  <option value="TikTok Ads">TikTok Ads</option>
                </select>
              </div>
            </div>

            <div class="form-row kala-form-row">
              <div class="form-group">
                <label class="form-label">Nominal Transfer (Rp) <span style="color:red;">*</span></label>
                <input type="text" name="transfer_amount" id="transfer-worker-amount" class="form-control" placeholder="Contoh: 200.000" required oninput="formatRupiahInput(this)">
              </div>

              <div class="form-group">
                <label class="form-label">Keterangan Tahap <span style="color:red;">*</span></label>
                <input type="text" name="stage_label" id="transfer-worker-stage" class="form-control" value="Tahap 1" placeholder="Contoh: Tahap 1, Tahap 2, dll." required>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Upload Bukti Transfer Owner &rarr; Tim Ads (JPG / PNG / PDF) <span style="color:red;">*</span></label>
              <input type="file" name="transfer_proof_file" id="transfer-worker-proof" class="form-control" accept="image/*,.pdf" required>
              <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                Bukti transfer ini wajib terlihat oleh Tim Ads dan Klien di Portal Klien secara transparan.
              </small>
            </div>

            <div class="form-group">
              <label class="form-label">Catatan Tambahan untuk Tim Ads &amp; Klien</label>
              <textarea name="notes" id="transfer-worker-notes" class="form-control" rows="2" placeholder="Contoh: Dana iklan ditransfer via BCA untuk campaign testing 7 hari pertama."></textarea>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-transfer-to-worker')">Batal</button>
            <button type="submit" class="btn btn-primary" id="btn-submit-transfer-worker" style="background: #175CD3; border-color: #175CD3; font-weight: 700;">
              Konfirmasi &amp; Transfer Saldo
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- MODAL: Konfirmasi Top-Up Platform & Update Spend (Tim Ads) -->
  <div class="modal" id="modal-confirm-platform-topup">
    <div class="modal-dialog" style="max-width: 580px;">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h4 class="modal-title">Konfirmasi Top-Up ke Meta/Google &amp; Update Spend</h4>
            <span style="font-size: 12px; color: var(--text-secondary);">Laporkan bahwa dana dari Owner sudah disetor ke platform iklan beserta realisasi spend</span>
          </div>
          <button type="button" class="close-btn" onclick="closeModal('modal-confirm-platform-topup')">&times;</button>
        </div>

        <form id="form-confirm-platform-topup" onsubmit="handleConfirmPlatformTopup(event)" enctype="multipart/form-data">
          <input type="hidden" name="transaction_id" id="confirm-topup-trans-id">

          <div class="modal-body">
            <!-- Info Dana Diterima dari Owner -->
            <div id="box-confirm-topup-info" style="background: #F4F3FF; border: 1px solid #D9D6FE; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px;">
              <div style="font-size: 11px; color: #5925DC; font-weight: 700; text-transform: uppercase;">Dana Diterima dari Owner</div>
              <div style="font-size: 18px; font-weight: 800; color: #101828; margin-top: 2px;" id="txt-confirm-owner-transfer">Rp 0</div>
              <div style="font-size: 11.5px; color: #475467; margin-top: 2px;" id="txt-confirm-topup-proj-info">Proyek &amp; Platform</div>
            </div>

            <div class="form-row kala-form-row">
              <div class="form-group">
                <label class="form-label">Nominal Top-Up ke Platform Ads (Rp) <span style="color:red;">*</span></label>
                <input type="text" name="platform_topup_amount" id="confirm-platform-amount" class="form-control" placeholder="Contoh: 200.000" required oninput="formatRupiahInput(this)">
                <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                  Jumlah saldo yang berhasil masuk ke akun Meta/Google Ads.
                </small>
              </div>

              <div class="form-group">
                <label class="form-label">Realisasi Belanja / Spend Saat Ini (Rp) <span style="color:red;">*</span></label>
                <input type="text" name="ad_spend_amount" id="confirm-spend-amount" class="form-control" placeholder="Contoh: 150.000" required oninput="formatRupiahInput(this)">
                <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                  Total nominal saldo yang telah terserap/terbelanjakan.
                </small>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Status Kampanye Iklan <span style="color:red;">*</span></label>
              <select name="campaign_status" id="confirm-campaign-status" class="form-control" required>
                <option value="running">Sedang Berjalan (Iklan Aktif Tayang)</option>
                <option value="paused">Dijeda Sementara (Paused)</option>
                <option value="empty">Saldo Habis (Empty)</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Upload Bukti Struk Billing / Screenshot Ads Manager (Wajib) <span style="color:red;">*</span></label>
              <input type="file" name="platform_receipt_file" id="confirm-platform-receipt" class="form-control" accept="image/*,.pdf" required>
              <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                Lampirkan struk resmi platform Meta/Google Ads atau tangkapan layar dashboard billing.
              </small>
            </div>

            <div class="form-group">
              <label class="form-label">Catatan Koordinasi untuk Owner &amp; Klien</label>
              <textarea name="notes" id="confirm-topup-notes" class="form-control" rows="2" placeholder="Contoh: Saldo Rp 200.000 berhasil di-topup ke Meta Ads Manager. Campaign Advantage+ Shopping aktif."></textarea>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-confirm-platform-topup')">Batal</button>
            <button type="submit" class="btn btn-primary" id="btn-submit-confirm-topup" style="background: #027A48; border-color: #027A48; font-weight: 700;">
              Simpan &amp; Sinkronkan ke Portal Klien
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL: Tim Ads Minta Saldo Tambahan ke Owner -->
  <div class="modal" id="modal-request-balance-worker">
    <div class="modal-dialog" style="max-width: 520px;">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h4 class="modal-title">🔔 Permintaan Saldo Iklan ke Owner</h4>
            <span style="font-size: 12px; color: var(--text-secondary);">Ajukan pencairan saldo tahap berikutnya kepada Owner</span>
          </div>
          <button type="button" class="close-btn" onclick="closeModal('modal-request-balance-worker')">&times;</button>
        </div>

        <form id="form-request-balance-worker" onsubmit="handleRequestBalanceWorker(event)">
          <input type="hidden" name="request_type" value="worker_to_owner">

          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Pilih Proyek Klien <span style="color:red;">*</span></label>
              <select name="project_id" id="req-worker-project-id" class="form-control" required>
                <option value="">-- Pilih Proyek Klien --</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Nominal Kebutuhan Saldo (Rp) <span style="color:red;">*</span></label>
              <input type="text" name="amount_requested" id="req-worker-amount" class="form-control" placeholder="Contoh: 200.000" required oninput="formatRupiahInput(this)">
            </div>

            <div class="form-group">
              <label class="form-label">Alasan &amp; Laporan Singkat untuk Owner <span style="color:red;">*</span></label>
              <textarea name="notes" id="req-worker-notes" class="form-control" rows="3" placeholder="Contoh: Saldo tahap 1 sebesar Rp 200.000 sudah terserap habis di Meta Ads. Butuh pencairan tahap 2 sebesar Rp 200.000 agar kampanye tetap jalan optimal." required></textarea>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-request-balance-worker')">Batal</button>
            <button type="submit" class="btn btn-primary" id="btn-submit-req-worker" style="background: #175CD3; border-color: #175CD3; font-weight: 700;">
              Kirim Permintaan ke Owner
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php if ($isOwnerOrAdmin): ?>
  <!-- MODAL: Owner Ajukan Tambah Limit Saldo ke Klien -->
  <div class="modal" id="modal-request-limit-owner">
    <div class="modal-dialog" style="max-width: 540px;">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h4 class="modal-title">🔔 Ajukan Tambah Limit Saldo Ads ke Klien</h4>
            <span style="font-size: 12px; color: var(--text-secondary);">Kirim notifikasi pengajuan penambahan limit pagu ke Portal Klien</span>
          </div>
          <button type="button" class="close-btn" onclick="closeModal('modal-request-limit-owner')">&times;</button>
        </div>

        <form id="form-request-limit-owner" onsubmit="handleRequestLimitOwner(event)">
          <input type="hidden" name="request_type" value="owner_to_client">

          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Pilih Proyek Klien <span style="color:red;">*</span></label>
              <select name="project_id" id="req-owner-project-id" class="form-control" required>
                <option value="">-- Pilih Proyek Klien --</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Nominal Pengajuan Tambah Limit (Rp) <span style="color:red;">*</span></label>
              <input type="text" name="amount_requested" id="req-owner-amount" class="form-control" placeholder="Contoh: 1.000.000" required oninput="formatRupiahInput(this)">
            </div>

            <div class="form-group">
              <label class="form-label">Catatan &amp; Rekomendasi untuk Klien <span style="color:red;">*</span></label>
              <textarea name="notes" id="req-owner-notes" class="form-control" rows="3" placeholder="Contoh: Pagu saldo iklan awal sebesar Rp 650.000 telah terserap optimal dengan ROAS 3.2x. Kami menyarankan penambahan limit Rp 1.000.000 untuk memperluas jangkauan promosi bulan ini." required></textarea>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-request-limit-owner')">Batal</button>
            <button type="submit" class="btn btn-primary" id="btn-submit-req-owner" style="background: #027A48; border-color: #027A48; font-weight: 700;">
              Kirim Pengajuan ke Portal Klien
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <script src="assets/js/app.js?v=bankio-typography-3.0"></script>
  <script>
    let allLoadedTasks = [];
    let currentFilterStatus = 'all';

    document.addEventListener('DOMContentLoaded', () => {
      loadTasksData();
      <?php if ($isAdminUser): ?>
        loadDropdowns();
      <?php endif; ?>
    });

    async function loadTasksData() {
      try {
        const res = await fetch('api/portal_tasks.php?action=list_tasks');
        const data = await res.json();

        if (data.success) {
          allLoadedTasks = data.tasks || [];
          renderKPIs(data.metrics);
          applyFilters();
          checkRevisionAlerts();
        } else {
          showToast(data.message || 'Gagal memuat daftar tugas', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      }
    }

    function renderKPIs(m) {
      if (!m) return;
      document.getElementById('kpi-total').innerText = m.total_tasks || 0;
      document.getElementById('kpi-revision').innerText = m.revision_count || 0;
      document.getElementById('kpi-review').innerText = m.client_review_count || 0;
      document.getElementById('kpi-progress').innerText = (m.in_progress_count || 0) + (m.internal_review_count || 0);
      document.getElementById('kpi-approved').innerText = m.approved_count || 0;
    }

    function checkRevisionAlerts() {
      const alertContainer = document.getElementById('revision-alert-container');
      const revisions = allLoadedTasks.filter(t => t.status === 'revision');
      const unreadCount = allLoadedTasks.reduce((acc, t) => acc + (parseInt(t.unread_feedbacks_count) || 0), 0);

      if (revisions.length > 0 || unreadCount > 0) {
        alertContainer.style.display = 'block';
        alertContainer.innerHTML = `
          <div class="alert-revision-box">
            <div style="display: flex; align-items: center; gap: 10px;">
              <div>
                <strong style="color: #B42318; font-size: 13.5px;">Tindakan Diperlukan: Masukan dari Klien</strong>
                <div style="font-size: 12.5px; color: #7A271A; margin-top: 2px;">
                  Terdapat <b>${revisions.length} tugas</b> yang berstatus REVISI dan <b>${unreadCount} pesan masukan baru</b> dari klien.
                </div>
              </div>
            </div>
            <button class="btn btn-sm" onclick="filterByStatus('revision', document.querySelector('#status-filter-group button:nth-child(2)'))" style="background: #B42318; color: #FFF; font-weight: 700; flex-shrink: 0; font-size: 11.5px;">
              Tinjau Revisi Sekarang
            </button>
          </div>
        `;
      } else {
        alertContainer.style.display = 'none';
      }
    }

    let currentServiceFilter = 'all';

    function filterTaskService(serviceKey, btn) {
      currentServiceFilter = serviceKey;
      document.querySelectorAll('.task-service-pill').forEach(b => b.classList.remove('active'));
      if (btn) btn.classList.add('active');
      applyFilters();
    }

    function filterByStatus(status, btn) {
      currentFilterStatus = status;
      document.querySelectorAll('#status-filter-group .filter-btn').forEach(b => b.classList.remove('active'));
      if (btn) btn.classList.add('active');
      applyFilters();
    }

    function handleTaskSearch() {
      const searchInput = document.getElementById('task-search-input');
      const clearBtn = document.getElementById('task-search-clear-btn');
      if (clearBtn) {
        clearBtn.style.display = searchInput && searchInput.value ? 'block' : 'none';
      }
      applyFilters();
    }

    function clearTaskSearch() {
      const searchInput = document.getElementById('task-search-input');
      if (searchInput) {
        searchInput.value = '';
      }
      const clearBtn = document.getElementById('task-search-clear-btn');
      if (clearBtn) clearBtn.style.display = 'none';
      applyFilters();
    }

    function applyFilters() {
      const workerType = document.getElementById('filter-worker-type') ? document.getElementById('filter-worker-type').value : '';

      let filtered = allLoadedTasks;

      // Status filter
      if (currentFilterStatus !== 'all') {
        filtered = filtered.filter(t => t.status === currentFilterStatus);
      }
      // Worker type filter
      if (workerType) {
        filtered = filtered.filter(t => t.worker_type === workerType);
      }
      // Service filter
      if (currentServiceFilter !== 'all') {
        const key = currentServiceFilter.toLowerCase();
        filtered = filtered.filter(t => {
          const cat = (t.category || '').toLowerCase();
          const srv = (t.client_service_type || '').toLowerCase();
          return cat.includes(key) || srv.includes(key);
        });
      }
      // Search keyword filter
      const searchInput = document.getElementById('task-search-input');
      const searchKey = searchInput ? searchInput.value.trim().toLowerCase() : '';
      if (searchKey) {
        filtered = filtered.filter(t => {
          const title = (t.title || '').toLowerCase();
          const desc = (t.description || '').toLowerCase();
          const clientName = (t.client_company || t.company || '').toLowerCase();
          const picName = (t.pic_name || '').toLowerCase();
          const workerName = (t.worker_name || t.user_name || '').toLowerCase();
          const deliverable = (t.deliverable_type || '').toLowerCase();
          return title.includes(searchKey) || desc.includes(searchKey) || clientName.includes(searchKey) || picName.includes(searchKey) || workerName.includes(searchKey) || deliverable.includes(searchKey);
        });
      }

      document.getElementById('tasks-count-label').innerText = `Menampilkan ${filtered.length} tugas`;
      renderTasksList(filtered);
    }

    function renderTasksList(tasks) {
      const container = document.getElementById('tasks-list-container');
      if (!tasks || tasks.length === 0) {
        container.innerHTML = `
          <div style="text-align: center; padding: 48px; background: #FFF; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 8px; display: block;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            <h4 style="font-size: 15px; font-weight: 600; color: #111827; margin: 0 0 2px;">Tidak Ada Tugas Sesuai Filter</h4>
            <p style="font-size: 12.5px; color: #6B7280; margin: 0;">Silakan ubah filter atau tambahkan tugas baru.</p>
          </div>
        `;
        return;
      }

      const statusMap = {
        'backlog': { label: 'Antrean', class: 'backlog' },
        'in_progress': { label: 'Sedang Dikerjakan', class: 'in_progress' },
        'internal_review': { label: 'Review Internal', class: 'internal_review' },
        'client_review': { label: 'Review Klien', class: 'client_review' },
        'revision': { label: 'Perlu Revisi', class: 'revision' },
        'approved': { label: 'Disetujui', class: 'approved' }
      };

      let html = '';
      tasks.forEach(t => {
        const sInfo = statusMap[t.status] || { label: t.status, class: 'backlog' };
        const isFreelance = (t.worker_type === 'freelancer');
        const workerSpec = t.worker_specialty ? ` • ${escapeHtml(t.worker_specialty)}` : '';
        const workerBadge = isFreelance 
          ? `<span class="badge-worker freelancer">Freelancer: <b>${escapeHtml(t.worker_name || 'Talent')}</b>${workerSpec}</span>`
          : `<span class="badge-worker karyawan">In-House: <b>${escapeHtml(t.worker_name || 'Staff')}</b>${workerSpec}</span>`;

        let catLabel = t.category || 'Kelola Sosmed';
        let catClass = 'service-tag-default';
        if (catLabel.toLowerCase().includes('sosmed')) catClass = 'service-tag-sosmed';
        else if (catLabel.toLowerCase().includes('desain')) catClass = 'service-tag-desain';
        else if (catLabel.toLowerCase().includes('web')) catClass = 'service-tag-web';
        else if (catLabel.toLowerCase().includes('iklan') || catLabel.toLowerCase().includes('ads')) catClass = 'service-tag-ads';

        const hasRevision = (t.status === 'revision');
        const hasReview = (t.status === 'client_review');
        const isApproved = (t.status === 'approved');

        const cardExtraClass = hasRevision ? 'has-revision' : (hasReview ? 'has-review' : (isApproved ? 'is-approved' : ''));
        const unreadFeedbacks = parseInt(t.unread_feedbacks_count) || 0;
        const dateRangeText = (t.start_date ? formatDate(t.start_date) : '-') + ' s/d ' + (t.due_date ? formatDate(t.due_date) : '-');

        html += `
          <div class="task-item-card ${cardExtraClass}">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap;">
              <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <span class="status-pill ${sInfo.class}">
                  <span>${sInfo.label}</span>
                </span>
                <span class="service-tag ${catClass}">
                  ${escapeHtml(catLabel)}
                </span>
                <span class="badge-priority ${t.priority || 'medium'}">
                  ${t.priority ? t.priority.toUpperCase() : 'MEDIUM'}
                </span>
                ${workerBadge}
              </div>

              <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 11.5px; color: var(--text-muted);">
                  <span>Waktu Pengerjaan: <b>${dateRangeText}</b></span>
                </span>
                <?php if ($isAdminUser): ?>
                  <button type="button" class="btn btn-secondary btn-sm" onclick="deleteTask(${t.id})" style="padding: 3px 8px; font-size: 11px; font-weight: 600; color: #B42318;" title="Hapus Tugas">
                    Hapus
                  </button>
                <?php endif; ?>
              </div>
            </div>

            <div>
              <div style="font-size: 11.5px; color: var(--text-muted); font-weight: 600; margin-bottom: 2px;">
                ${escapeHtml(t.client_company || 'Client')} &bull; Proyek: ${escapeHtml(t.project_name || 'Kalamedia')}
              </div>
              <h3 style="font-size: 16px; font-weight: 800; color: #101828; margin: 0 0 4px;">
                ${escapeHtml(t.title)}
              </h3>
              <p style="font-size: 13px; color: var(--text-secondary); margin: 0; line-height: 1.5;">
                ${escapeHtml(t.description || 'Tidak ada deskripsi singkat.')}
              </p>
            </div>

            ${(t.is_ads_task || parseFloat(t.assigned_ads_budget) > 0) ? `
              <!-- Real-Time Ads Monitoring Panel (5 Required Indicators) -->
              <div style="background: #F8F9FC; border: 1.5px solid #EAECF0; border-radius: 12px; padding: 14px; margin: 12px 0;">
                ${t.is_depleted ? `
                  <div style="background: #FFEFEF; border: 1.5px solid #FECDCA; border-radius: 8px; padding: 10px 14px; margin-bottom: 12px; color: #B42318; font-weight: 700; font-size: 12.5px; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 18px;">⚠️</span>
                    <span><b>SALDO IKLAN HABIS (Rp 0)!</b> Kampanye dihentikan sementara. Harap segera koordinasi dengan Client / Owner untuk Top-Up Saldo Ads proyek.</span>
                  </div>
                ` : (t.is_warning ? `
                  <div style="background: #FEF6E7; border: 1.5px solid #FEDF89; border-radius: 8px; padding: 10px 14px; margin-bottom: 12px; color: #B54708; font-weight: 700; font-size: 12.5px; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 18px;">⚠️</span>
                    <span><b>SALDO IKLAN HAMPIR HABIS (&le; 20%)!</b> Sisa saldo menipis, segera siapkan top-up saldo agar iklan tidak berhenti.</span>
                  </div>
                ` : '')}

                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 12px;">
                  <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <span class="badge" style="background: #F4F3FF; color: #5925DC; font-weight: 800; font-size: 11px;">
                      ${escapeHtml(t.ads_platform || 'Meta Ads')}
                    </span>

                    <!-- Indikator 4: Status Tayang Iklan Jalan / Tidak -->
                    ${t.campaign_run_status === 'running' ? `
                      <span style="background: #E8F7F0; color: #30A46C; border: 1px solid #A6F4C5; font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 6px;">
                        <span style="width: 7px; height: 7px; border-radius: 50%; background: #30A46C; box-shadow: 0 0 6px #30A46C; display: inline-block;"></span>
                        Iklan Jalan (Running)
                      </span>
                    ` : (t.campaign_run_status === 'paused' ? `
                      <span style="background: #FEF6E7; color: #F5A524; border: 1px solid #FEDF89; font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 6px;">
                        <span style="width: 7px; height: 7px; border-radius: 50%; background: #F5A524; display: inline-block;"></span>
                        Iklan Dijeda (Paused)
                      </span>
                    ` : `
                      <span style="background: #FFEFEF; color: #E5484D; border: 1px solid #FECDCA; font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 6px;">
                        <span style="width: 7px; height: 7px; border-radius: 50%; background: #E5484D; display: inline-block;"></span>
                        Iklan Berhenti (Stopped)
                      </span>
                    `)}

                    <!-- Indikator 5: Status Saldo Masih Ada / Habis -->
                    ${t.balance_health_status === 'safe' ? `
                      <span class="badge" style="background: #E8F7F0; color: #30A46C; font-weight: 800; font-size: 11px; padding: 3px 8px; border-radius: 6px;">
                        Saldo Masih Tersedia (Aman)
                      </span>
                    ` : (t.balance_health_status === 'warning' ? `
                      <span class="badge" style="background: #FEF6E7; color: #F5A524; font-weight: 800; font-size: 11px; padding: 3px 8px; border-radius: 6px;">
                        Saldo Hampir Habis (Segera Top-Up)
                      </span>
                    ` : `
                      <span class="badge" style="background: #FFEFEF; color: #E5484D; font-weight: 800; font-size: 11px; padding: 3px 8px; border-radius: 6px;">
                        SALDO HABIS (Rp 0)
                      </span>
                    `)}
                  </div>

                  <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                    ${t.transfer_proof_url ? `
                      <button type="button" class="btn btn-secondary btn-sm" onclick="openUniversalTransferProofModal('${escapeHtml(t.transfer_proof_url)}', 'Bukti Transfer Saldo Ads', '${escapeHtml(t.title)}')" style="font-size: 11px; font-weight: 700; gap: 4px; display: inline-flex; align-items: center;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        Lihat Bukti Transfer
                      </button>
                    ` : ''}

                    <button type="button" class="btn btn-primary btn-sm" onclick='openUpdateAdsSpendModal(${JSON.stringify(t)})' style="font-size: 11px; font-weight: 700; background: #175CD3; border-color: #175CD3;">
                      ⚡ Update Pemakaian &amp; Status Iklan
                    </button>
                  </div>
                </div>

                <!-- 3 Key Metric Columns: Budget, Spent, Remaining -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; background: #FFFFFF; border: 1px solid #EAECF0; border-radius: 8px; padding: 12px; margin-bottom: 10px; text-align: center;">
                  <div>
                    <span style="font-size: 10.5px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; display: block;">1. Total Budget Saldo Ads</span>
                    <span style="font-size: 14px; font-weight: 800; color: #101828;">${t.assigned_ads_budget_formatted}</span>
                  </div>
                  <div style="border-left: 1px solid #EAECF0; border-right: 1px solid #EAECF0;">
                    <span style="font-size: 10.5px; color: #B42318; font-weight: 600; text-transform: uppercase; display: block;">2. Sudah Dibelanjakan</span>
                    <span style="font-size: 14px; font-weight: 800; color: #B42318;">${t.ads_spent_amount_formatted} (${t.spent_percent}%)</span>
                  </div>
                  <div>
                    <span style="font-size: 10.5px; color: #027A48; font-weight: 600; text-transform: uppercase; display: block;">3. Sisa Saldo Ads</span>
                    <span style="font-size: 14px; font-weight: 800; color: #027A48;">${t.remaining_ads_balance_formatted}</span>
                  </div>
                </div>

                <!-- Real-time Progress Bar -->
                <div style="height: 8px; background: #E5E7EB; border-radius: 999px; overflow: hidden; margin-bottom: 6px;">
                  <div style="height: 100%; width: ${Math.min(100, t.spent_percent)}%; background: ${t.spent_percent >= 100 ? '#E5484D' : (t.spent_percent >= 80 ? '#F5A524' : '#30A46C')}; border-radius: 999px; transition: width 0.4s ease;"></div>
                </div>

                ${t.ads_spent_notes ? `
                  <div style="font-size: 12px; color: #475467; font-style: italic; margin-top: 6px;">
                    Catatan Terakhir: "${escapeHtml(t.ads_spent_notes)}"
                    ${t.ads_spent_proof_url ? ` &bull; <a href="${escapeHtml(t.ads_spent_proof_url)}" target="_blank" style="color: #175CD3; font-weight: 700; text-decoration: none;">[Lihat Screenshot Ads Manager]</a>` : ''}
                  </div>
                ` : ''}
              </div>
            ` : ''}

            <!-- Deliverable Preview Link & Actions -->
            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 12px; flex-wrap: wrap; gap: 10px;">
              <div style="display: flex; align-items: center; gap: 10px;">
                ${t.deliverable_link ? `
                  <a href="${escapeHtml(t.deliverable_link)}" target="_blank" class="btn btn-secondary btn-sm" style="font-size: 11.5px; font-weight: 700;">
                    <span>Lihat Preview Hasil</span>
                  </a>
                ` : `
                  <span style="font-size: 11.5px; color: var(--text-tertiary); font-style: italic;">Belum ada link hasil</span>
                `}

                ${t.latest_feedback ? `
                  <span style="font-size: 11.5px; color: var(--text-secondary); max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: inline-block;">
                    "${escapeHtml(t.latest_feedback)}"
                  </span>
                ` : ''}
              </div>

              <div style="display: flex; gap: 8px; align-items: center;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="openDiscussionModal(${t.id})" style="font-size: 12px; font-weight: 700; position: relative;">
                  <span>Diskusi Klien</span>
                  ${unreadFeedbacks > 0 ? `
                    <span style="background: #EF4444; color: #FFF; font-size: 9.5px; font-weight: 800; padding: 1px 6px; border-radius: 10px; margin-left: 4px;">
                      ${unreadFeedbacks} Baru
                    </span>
                  ` : `
                    <span style="color: var(--text-muted); font-size: 11px; margin-left: 4px;">(${t.feedbacks_count || 0})</span>
                  `}
                </button>

                <button type="button" class="btn btn-primary btn-sm" onclick="openUpdateProgressModal(${t.id}, '${t.status}', '${escapeHtml(t.deliverable_link || '')}')" style="font-size: 12px;">
                  Perbarui Progres
                </button>
              </div>
            </div>
          </div>
        `;
      });

      container.innerHTML = html;
    }

    <?php if ($isAdminUser): ?>
      let globalProjects = [];
      let globalMembers = [];

      async function loadDropdowns() {
        try {
          // 1. Projects Dropdown
          const resP = await fetch('api/portal_tasks.php?action=projects_dropdown');
          const dataP = await resP.json();
          if (dataP.success) {
            globalProjects = dataP.projects || [];
            const selectP = document.getElementById('assign-project-id');
            let pHtml = '<option value="">-- Pilih Proyek Klien --</option>';
            globalProjects.forEach(p => {
              const srv = p.service_type ? ` [${p.service_type}]` : '';
              pHtml += `<option value="${p.project_id}">${escapeHtml(p.client_company)}${escapeHtml(srv)} - ${escapeHtml(p.project_name)}</option>`;
            });
            selectP.innerHTML = pHtml;
          }

          // 2. Members Dropdown
          const resM = await fetch('api/portal_tasks.php?action=team_members');
          const dataM = await resM.json();
          if (dataM.success) {
            globalMembers = dataM.members || [];
            renderAssignMembersDropdown();
          }
        } catch (err) {
          console.error(err);
        }
      }

      function renderAssignMembersDropdown() {
        const selectM = document.getElementById('assign-member-id');
        if (!selectM) return;

        const projId = document.getElementById('assign-project-id')?.value;
        const category = document.getElementById('assign-category')?.value || '';

        const selectedProj = globalProjects.find(p => String(p.project_id) === String(projId));
        const clientService = selectedProj?.service_type || '';

        // Show client contract info if project selected
        const projInfoEl = document.getElementById('assign-project-contract-info');
        if (projInfoEl) {
          if (selectedProj && selectedProj.contract_start_date && selectedProj.contract_end_date) {
            const isExp = selectedProj.contract_status === 'expired';
            projInfoEl.style.display = 'block';
            projInfoEl.innerHTML = `Masa Kontrak Klien: <b>${selectedProj.contract_start_date} s/d ${selectedProj.contract_end_date}</b> ${isExp ? '<span style="color:#B42318;">(KONTRAK KLIEN HABIS)</span>' : '<span style="color:#027A48;">(Aktif)</span>'}`;
          } else {
            projInfoEl.style.display = 'none';
          }
        }

        let targetKey = category;
        if (!targetKey && clientService) targetKey = clientService;

        const today = new Date().toISOString().slice(0, 10);
        const recommended = [];
        const others = [];

        globalMembers.forEach(m => {
          const isContractActive = !m.contract_end_date || m.contract_end_date >= today;
          const specialtyMatch = targetKey && m.service_specialty && (
            m.service_specialty.toLowerCase().includes(targetKey.toLowerCase()) ||
            targetKey.toLowerCase().includes(m.service_specialty.toLowerCase())
          );

          if (specialtyMatch && isContractActive) {
            recommended.push(m);
          } else {
            others.push(m);
          }
        });

        let mHtml = '<option value="">-- Pilih Anggota Tim / Freelancer --</option>';

        if (recommended.length > 0) {
          mHtml += `<optgroup label="⭐ Rekomendasi (Sesuai Jasa '${targetKey}' & Kontrak Aktif)">`;
          recommended.forEach(m => {
            const label = (m.role === 'freelancer' || m.employment_type === 'freelance') ? 'Freelancer' : 'Karyawan In-House';
            const spec = m.service_specialty ? ` (${m.service_specialty})` : '';
            mHtml += `<option value="${m.id}">⭐ [${label}] ${escapeHtml(m.name)}${escapeHtml(spec)}</option>`;
          });
          mHtml += `</optgroup>`;
        }

        mHtml += `<optgroup label="Anggota Tim Lainnya">`;
        others.forEach(m => {
          const label = (m.role === 'freelancer' || m.employment_type === 'freelance') ? 'Freelancer' : 'Karyawan In-House';
          const spec = m.service_specialty ? ` (${m.service_specialty})` : '';
          const isExpired = m.contract_end_date && m.contract_end_date < today;
          const expLabel = isExpired ? ' [KONTRAK HABIS]' : '';
          mHtml += `<option value="${m.id}">[${label}] ${escapeHtml(m.name)}${escapeHtml(spec)}${expLabel}</option>`;
        });
        mHtml += `</optgroup>`;

        selectM.innerHTML = mHtml;

        // Auto-select first recommended if available
        if (recommended.length > 0) {
          selectM.value = recommended[0].id;
        }
        onAssignMemberChange();
      }

      function onCategoryOrProjectChange() {
        renderAssignMembersDropdown();

        const cat = document.getElementById('assign-category')?.value || '';
        const projId = document.getElementById('assign-project-id')?.value;
        const selectedProj = globalProjects.find(p => String(p.project_id) === String(projId));
        const adsSection = document.getElementById('assign-ads-budget-section');
        const poolBadge = document.getElementById('assign-available-pool-badge');

        const isAdsCat = (cat.toLowerCase().includes('iklan') || cat.toLowerCase().includes('ads'));
        if (adsSection) {
          if (isAdsCat || (selectedProj && parseFloat(selectedProj.ads_budget_pool) > 0)) {
            adsSection.style.display = 'block';
            if (poolBadge && selectedProj) {
              const available = parseFloat(selectedProj.available_ads_pool) || 0;
              poolBadge.innerText = 'Sisa Saldo Ads Proyek: Rp ' + Math.round(available).toLocaleString('id-ID');
              if (available <= 0) {
                poolBadge.style.background = '#FEF3F2';
                poolBadge.style.color = '#B42318';
              } else {
                poolBadge.style.background = '#ECFDF3';
                poolBadge.style.color = '#027A48';
              }
            }
          } else {
            adsSection.style.display = 'none';
          }
        }
      }

      function onAssignMemberChange() {
        const memberId = document.getElementById('assign-member-id')?.value;
        const infoEl = document.getElementById('assign-worker-contract-info');
        const startInput = document.getElementById('assign-start-date');
        const dueInput = document.getElementById('assign-due-date');
        const projId = document.getElementById('assign-project-id')?.value;
        const selectedProj = globalProjects.find(p => String(p.project_id) === String(projId));

        if (!memberId) {
          if (infoEl) infoEl.style.display = 'none';
          if (startInput) { startInput.removeAttribute('min'); startInput.removeAttribute('max'); }
          if (dueInput) { dueInput.removeAttribute('min'); dueInput.removeAttribute('max'); }
          return;
        }

        const worker = globalMembers.find(m => String(m.id) === String(memberId));
        if (!worker) return;

        const wStart = worker.contract_start_date;
        const wEnd = worker.contract_end_date;

        if (wStart && wEnd) {
          if (infoEl) {
            infoEl.style.display = 'block';
            infoEl.innerHTML = `Masa kontrak kerja <b>${escapeHtml(worker.name)}</b>: <b>${wStart}</b> s/d <b>${wEnd}</b>`;
          }

          let minDate = wStart;
          let maxDate = wEnd;

          if (selectedProj && selectedProj.contract_end_date) {
            if (selectedProj.contract_end_date < maxDate) {
              maxDate = selectedProj.contract_end_date;
            }
          }

          if (startInput) {
            startInput.min = minDate;
            startInput.max = maxDate;
            if (startInput.value < minDate) startInput.value = minDate;
            if (startInput.value > maxDate) startInput.value = maxDate;
          }

          if (dueInput) {
            dueInput.min = minDate;
            dueInput.max = maxDate;
            if (dueInput.value < minDate) dueInput.value = minDate;
            if (dueInput.value > maxDate) dueInput.value = maxDate;
          }
        } else {
          if (infoEl) infoEl.style.display = 'none';
        }
      }

      function openAssignTaskModal() {
        openModal('modal-assign-task');
      }

      async function handleAssignTask(e) {
        e.preventDefault();
        const form = document.getElementById('form-assign-task');
        const submitBtn = document.getElementById('btn-submit-assign');
        const origText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Menyimpan...';

        const formData = new FormData(form);
        try {
          const res = await fetch('api/portal_tasks.php?action=assign_task', {
            method: 'POST',
            body: formData
          });
          const data = await res.json();
          if (data.success) {
            showToast(data.message, 'success');
            closeModal('modal-assign-task');
            form.reset();
            loadTasksData();
          } else {
            showToast(data.message || 'Gagal membagi tugas', 'danger');
          }
        } catch (err) {
          showToast('Koneksi server gagal', 'danger');
        } finally {
          submitBtn.disabled = false;
          submitBtn.innerHTML = origText;
        }
      }

      let currentEditingAdsBudget = 0;

      function openUpdateAdsSpendModal(task) {
        if (!task) return;
        document.getElementById('ads-spend-task-id').value = task.id;
        document.getElementById('ads-spend-task-title').innerText = task.title || '-';
        document.getElementById('ads-spend-client-company').innerText = (task.client_company || 'Klien') + ' • ' + (task.project_name || 'Proyek');
        document.getElementById('ads-spend-platform-badge').innerText = task.ads_platform || 'Meta Ads';

        const totalBudget = parseFloat(task.assigned_ads_budget) || 0;
        currentEditingAdsBudget = totalBudget;

        document.getElementById('ads-spend-total-budget').innerText = 'Rp ' + Math.round(totalBudget).toLocaleString('id-ID');
        
        const currentSpent = parseFloat(task.ads_spent_amount) || 0;
        document.getElementById('ads-spend-current-spent').innerText = 'Rp ' + Math.round(currentSpent).toLocaleString('id-ID');

        const amountInput = document.getElementById('ads-spend-input-amount');
        if (amountInput) amountInput.value = Math.round(currentSpent).toLocaleString('id-ID');

        document.getElementById('ads-spend-run-status').value = task.campaign_run_status || 'running';
        document.getElementById('ads-spend-notes').value = task.ads_spent_notes || '';

        recalcModalAdsRemaining();
        openModal('modal-update-ads-spend-task');
      }

      function recalcModalAdsRemaining() {
        const inputEl = document.getElementById('ads-spend-input-amount');
        const valStr = inputEl ? inputEl.value : '0';
        const spent = parseFloat(valStr.replace(/[^0-9]/g, '')) || 0;
        const remaining = Math.max(0, currentEditingAdsBudget - spent);
        const remainingEl = document.getElementById('ads-spend-calc-remaining');
        if (remainingEl) {
          remainingEl.innerText = 'Rp ' + Math.round(remaining).toLocaleString('id-ID');
          if (remaining <= 0) {
            remainingEl.style.color = '#B42318';
          } else {
            remainingEl.style.color = '#027A48';
          }
        }
      }

      async function handleUpdateAdsSpend(e) {
        e.preventDefault();
        const form = document.getElementById('form-update-ads-spend');
        const submitBtn = document.getElementById('btn-submit-ads-spend');
        const origText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Menyimpan...';

        const formData = new FormData(form);
        try {
          const res = await fetch('api/portal_tasks.php?action=update_ads_campaign_metrics', {
            method: 'POST',
            body: formData
          });
          const data = await res.json();
          if (data.success) {
            showToast(data.message, 'success');
            closeModal('modal-update-ads-spend-task');
            loadTasksData();
          } else {
            showToast(data.message || 'Gagal memperbarui metrik iklan', 'danger');
          }
        } catch (err) {
          showToast('Koneksi server gagal', 'danger');
        } finally {
          submitBtn.disabled = false;
          submitBtn.innerHTML = origText;
        }
      }

      async function deleteTask(taskId) {
        if (!confirm('Apakah Anda yakin ingin menghapus tugas ini?')) return;
        const formData = new FormData();
        formData.append('task_id', taskId);
        try {
          const res = await fetch('api/portal_tasks.php?action=delete_task', {
            method: 'POST',
            body: formData
          });
          const data = await res.json();
          if (data.success) {
            showToast(data.message, 'success');
            loadTasksData();
          } else {
            showToast(data.message || 'Gagal menghapus', 'danger');
          }
        } catch (err) {
          showToast('Koneksi server gagal', 'danger');
        }
      }

      async function deleteAllTasks() {
        if (!confirm('Apakah Anda yakin ingin menghapus SEMUA tugas yang ada di sistem? Tindakan ini tidak dapat dibatalkan.')) return;
        try {
          const res = await fetch('api/portal_tasks.php?action=delete_all_tasks', {
            method: 'POST'
          });
          const data = await res.json();
          if (data.success) {
            showToast(data.message, 'success');
            loadTasksData();
          } else {
            showToast(data.message || 'Gagal menghapus semua tugas', 'danger');
          }
        } catch (err) {
          showToast('Koneksi server gagal', 'danger');
        }
      }
    <?php endif; ?>

    function openUpdateProgressModal(taskId, currentStatus, currentLink) {
      document.getElementById('progress-task-id').value = taskId;
      document.getElementById('progress-status-select').value = currentStatus || 'in_progress';
      document.getElementById('progress-link-input').value = currentLink || '';
      openModal('modal-update-progress');
    }

    async function handleProgressSubmit(e) {
      e.preventDefault();
      const form = document.getElementById('form-update-progress');
      const submitBtn = document.getElementById('btn-submit-progress');
      const origText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Menyimpan...';

      const formData = new FormData(form);
      try {
        const res = await fetch('api/portal_tasks.php?action=update_progress', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          closeModal('modal-update-progress');
          loadTasksData();
        } else {
          showToast(data.message || 'Gagal menyimpan progres', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origText;
      }
    }

    async function openDiscussionModal(taskId) {
      try {
        const res = await fetch(`api/portal_tasks.php?action=get_task_details&id=${taskId}`);
        const data = await res.json();

        if (data.success) {
          const t = data.task;
          document.getElementById('reply-task-id').value = t.id;
          document.getElementById('disc-task-title').innerText = t.title;
          document.getElementById('disc-task-meta').innerText = 
            `Klien: ${t.client_company} • PIC: ${t.worker_name || 'Staff'} • Status: [${t.status.toUpperCase()}]`;

          const previewBox = document.getElementById('disc-preview-box');
          const previewBtn = document.getElementById('disc-preview-btn');
          if (t.deliverable_link) {
            previewBox.style.display = 'flex';
            previewBtn.href = t.deliverable_link;
          } else {
            previewBox.style.display = 'none';
          }

          // Render Discussion Thread
          const threadContainer = document.getElementById('disc-thread-container');
          const feedbacks = data.feedbacks || [];

          if (feedbacks.length === 0) {
            threadContainer.innerHTML = `
              <div style="text-align: center; color: var(--text-muted); font-size: 12px; padding: 18px;">
                Belum ada pesan masukan atau diskusi pada tugas ini.
              </div>
            `;
          } else {
            let threadHtml = '';
            feedbacks.forEach(f => {
              const isClient = (f.sender_role === 'client');
              const bubbleClass = isClient ? 'client-bubble' : 'team-bubble';
              const typeBadge = (f.feedback_type === 'revision_request') 
                ? '<span style="background: #FEE4E2; color: #B42318; padding: 1px 6px; border-radius: 4px; font-weight: 700; font-size: 10px;">Permintaan Revisi</span>'
                : ((f.feedback_type === 'approval') 
                  ? '<span style="background: #D1FADF; color: #027A48; padding: 1px 6px; border-radius: 4px; font-weight: 700; font-size: 10px;">Persetujuan</span>'
                  : '');

              threadHtml += `
                <div class="bubble ${bubbleClass}">
                  <div class="bubble-meta">
                    <b>${escapeHtml(f.sender_name || 'Pengguna')}</b> (${isClient ? 'Klien' : 'Tim Agensi'})
                    <span>&bull;</span>
                    <span>${formatTime(f.created_at)}</span>
                    ${typeBadge}
                  </div>
                  <div>${escapeHtml(f.message)}</div>
                  ${f.attachment_path ? `
                    <div style="margin-top: 6px;">
                      <a href="${escapeHtml(f.attachment_path)}" target="_blank" style="color: ${isClient ? '#93C5FD' : '#175CD3'}; font-size: 11.5px; text-decoration: underline;">
                        Buka Lampiran File
                      </a>
                    </div>
                  ` : ''}
                </div>
              `;
            });
            threadContainer.innerHTML = threadHtml;
            threadContainer.scrollTop = threadContainer.scrollHeight;
          }

          document.getElementById('reply-message').value = '';
          openModal('modal-task-discussion');
        } else {
          showToast(data.message || 'Gagal memuat rincian diskusi', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      }
    }

    async function handleReplyFeedback(e) {
      e.preventDefault();
      const form = document.getElementById('form-reply-feedback');
      const submitBtn = document.getElementById('btn-submit-reply');
      const origText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Mengirim...';

      const formData = new FormData(form);
      try {
        const res = await fetch('api/portal_tasks.php?action=submit_feedback', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast('Balasan Anda berhasil dikirim ke klien!', 'success');
          closeModal('modal-task-discussion');
          loadTasksData();
        } else {
          showToast(data.message || 'Gagal mengirim balasan', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origText;
      }
    }

    function formatDate(str) {
      if (!str) return '-';
      const d = new Date(str);
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
      return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
    }

    function formatTime(str) {
      if (!str) return '';
      const d = new Date(str);
      return `${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}`;
    }

    function escapeHtml(text) {
      if (!text) return '';
      const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      };
      return text.replace(/[&<>"']/g, m => map[m]);
    }

    // -----------------------------------------------------------
    // ADS BUDGET & SPEND TRACKER MODULE JS
    // -----------------------------------------------------------
    let allLoadedAdsData = null;

    function switchTaskWorkspaceTab(tab) {
      const btnTasks = document.getElementById('tab-btn-tasks');
      const btnAds = document.getElementById('tab-btn-ads');
      const secTasks = document.getElementById('section-tasks-content');
      const secAds = document.getElementById('section-ads-content');

      if (tab === 'ads') {
        btnAds.classList.add('active');
        btnTasks.classList.remove('active');
        secTasks.style.display = 'none';
        secAds.style.display = 'block';
        loadAdsTrackerData();
      } else {
        btnTasks.classList.add('active');
        btnAds.classList.remove('active');
        secAds.style.display = 'none';
        secTasks.style.display = 'block';
      }
    }

    async function loadAdsTrackerData() {
      try {
        const uid = '<?= $userId ?>';
        const res = await fetch(`api/ads_tracker.php?action=list&_u=u_${uid}`);
        const data = await res.json();
        if (!data.success) {
          showToast(data.message || 'Gagal memuat data iklan', 'danger');
          return;
        }
        allLoadedAdsData = data;
        renderAdsNotifications(data);
        renderAdsSummary(data);
        renderAdsTopupTable(data);
        renderAdsProjectsTable(data);
        renderAdsLogsFeed(data);
        populateAdsProjectDropdowns(data.projects || []);
      } catch (err) {
        console.error(err);
        showToast('Koneksi server gagal memuat data ads', 'danger');
      }
    }

    function renderAdsNotifications(data) {
      const container = document.getElementById('ads-notifications-container');
      if (!container) return;
      const isPrivileged = <?= json_encode($isOwnerOrAdmin) ?> && (data.role === 'owner' || data.role === 'admin');
      const pending = data.pending_requests || [];

      if (isPrivileged) {
        const workerReqs = pending.filter(r => r.request_type === 'worker_to_owner');
        if (workerReqs.length > 0) {
          let reqHtml = '';
          workerReqs.forEach(r => {
            const amount = Number(r.amount_requested || 0);
            reqHtml += `
              <div style="background: #FFF9F2; border: 1.5px solid #FDB022; border-radius: 10px; padding: 14px 18px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; box-shadow: 0 2px 4px rgba(245, 158, 11, 0.08);">
                <div style="display: flex; align-items: flex-start; gap: 12px;">
                  <span style="font-size: 26px; line-height: 1;">🔔</span>
                  <div>
                    <div style="font-weight: 800; font-size: 14px; color: #B54708;">
                      Permintaan Pencairan Saldo dari Tim Ads (${escapeHtml(r.requester_name || 'Tim Ads')})
                    </div>
                    <div style="font-size: 13px; color: #475467; margin-top: 2px;">
                      Proyek: <b>${escapeHtml(r.client_company || '')} - ${escapeHtml(r.project_name || '')}</b> &bull; Kebutuhan: <b style="color: #B42318; font-size: 14px;">Rp ${amount.toLocaleString('id-ID')}</b>
                    </div>
                    <div style="font-size: 12px; color: #344054; margin-top: 5px; background: #FFFFFF; border: 1px dashed #FDB022; padding: 6px 10px; border-radius: 6px;">
                      <b>Catatan Tim Ads:</b> "${escapeHtml(r.notes || 'Saldo iklan telah menipis/habis.')}"
                    </div>
                  </div>
                </div>
                <div style="display: flex; gap: 8px;">
                  <button type="button" class="btn btn-primary btn-sm" onclick="openTransferToWorkerModal(${r.project_id}, null, ${amount})" style="font-weight: 700; background: #175CD3; border-color: #175CD3; font-size: 12px;">
                    + Cairkan Saldo Sekarang
                  </button>
                </div>
              </div>
            `;
          });
          container.innerHTML = reqHtml;
          return;
        }
      } else {
        // Worker view: check if any assigned projects have low or 0 balance
        const projects = data.projects || [];
        const lowBalanceProjects = projects.filter(p => Number(p.sisa_saldo_di_tim_ads || 0) <= 0);
        if (lowBalanceProjects.length > 0) {
          let alerts = '';
          lowBalanceProjects.forEach(p => {
            const hasPending = p.has_pending_request;
            alerts += `
              <div style="background: #FEF3F2; border: 1px solid #FECDCA; border-radius: 8px; padding: 12px 16px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                  <span style="font-size: 20px;">⚠️</span>
                  <div style="font-size: 13px; color: #B42318; font-weight: 700;">
                    Saldo Iklan untuk [${escapeHtml(p.client_company)}] ${escapeHtml(p.project_name)} Habis (Rp 0)!
                    <span style="font-weight: 400; color: #475467; display: block; font-size: 11.5px; margin-top: 2px;">
                      ${hasPending ? 'Permintaan tambahan saldo sedang ditinjau oleh Owner.' : 'Segera ajukan pencairan saldo tahap berikutnya ke Owner agar kampanye iklan tidak terhenti.'}
                    </span>
                  </div>
                </div>
                <div>
                  ${hasPending ? `
                    <span class="badge" style="background: #FEF0C7; color: #B54708; font-weight: 800; padding: 6px 12px; font-size: 11px;">
                      ⏳ Menunggu Konfirmasi Owner
                    </span>
                  ` : `
                    <button type="button" class="btn btn-primary btn-sm" onclick="openRequestBalanceWorkerModal(${p.project_id})" style="background: #B42318; border-color: #B42318; font-weight: 700; font-size: 11px;">
                      🔔 Minta Saldo ke Owner
                    </button>
                  `}
                </div>
              </div>
            `;
          });
          container.innerHTML = alerts;
          return;
        }
      }
      container.innerHTML = '';
    }

    function renderAdsSummary(data) {
      const container = document.getElementById('ads-kpi-container');
      if (!container) return;

      <?php if ($isOwnerOrAdmin): ?>
      const isPrivileged = (data.role === 'owner' || data.role === 'admin');
      if (isPrivileged && data.summary) {
        const s = data.summary;
        container.innerHTML = `
          <div class="kpi-tile">
            <div class="metric-header">
              <span class="metric-label">Total Kontrak Klien</span>
              <span class="metric-icon">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="#2563EB" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              </span>
            </div>
            <div class="metric-value">
              <span class="metric-num">Rp ${Number(s.total_contract_value || 0).toLocaleString('id-ID')}</span>
            </div>
            <div class="metric-subtitle">
              <span>Rahasia internal Owner</span>
            </div>
          </div>

          <div class="kpi-tile">
            <div class="metric-header">
              <span class="metric-label">Margin Bersih Agensi</span>
              <span class="metric-icon">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="#059669" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
              </span>
            </div>
            <div class="metric-value">
              <span class="metric-num" style="color: #059669;">Rp ${Number(s.total_agency_retained || 0).toLocaleString('id-ID')}</span>
            </div>
            <div class="metric-subtitle">
              <span>Pendapatan jasa murni agensi</span>
            </div>
          </div>

          <div class="kpi-tile">
            <div class="metric-header">
              <span class="metric-label">Total Pagu Saldo Ads</span>
              <span class="metric-icon">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="#5925DC" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
              </span>
            </div>
            <div class="metric-value">
              <span class="metric-num" style="color: #5925DC;">Rp ${Number(s.total_allocated_ads || 0).toLocaleString('id-ID')}</span>
            </div>
            <div class="metric-subtitle">
              <span>Pagu belanja iklan klien</span>
            </div>
          </div>

          <div class="kpi-tile">
            <div class="metric-header">
              <span class="metric-label">Ditransfer ke Tim Ads</span>
              <span class="metric-icon">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="#175CD3" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
              </span>
            </div>
            <div class="metric-value">
              <span class="metric-num" style="color: #175CD3;">Rp ${Number(s.total_transferred_to_ads || 0).toLocaleString('id-ID')}</span>
            </div>
            <div class="metric-subtitle">
              <span>Top-Up: Rp ${Number(s.total_spend_installed || 0).toLocaleString('id-ID')}</span>
            </div>
          </div>

          <div class="kpi-tile ${Number(s.total_remaining_ads || 0) <= 0 ? 'urgent' : ''}">
            <div class="metric-header">
              <span class="metric-label" style="color: ${Number(s.total_remaining_ads || 0) <= 0 ? '#B42318' : '#027A48'};">Sisa Pagu di Owner</span>
              <span class="metric-icon">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="${Number(s.total_remaining_ads || 0) <= 0 ? '#B42318' : '#027A48'}" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              </span>
            </div>
            <div class="metric-value">
              <span class="metric-num" style="color: ${Number(s.total_remaining_ads || 0) <= 0 ? '#B42318' : '#027A48'};">Rp ${Number(s.total_remaining_ads || 0).toLocaleString('id-ID')}</span>
            </div>
            <div class="metric-subtitle">
              <span>Belum dicairkan ke Tim Ads</span>
            </div>
          </div>
        `;
      }
      <?php else: ?>
      // KARYAWAN / FREELANCER (TIM ADS) VIEW: EXACTLY 2 CARDS ONLY!
      const s = data.summary || {};
      const transferred = Number(s.total_ditransfer_owner !== undefined ? s.total_ditransfer_owner : (s.total_transferred_from_owner || <?= (float)$total_ditransfer_owner ?>));
      const spend = Number(s.total_dibelanjakan_ads !== undefined ? s.total_dibelanjakan_ads : (s.total_spend_terpakai || <?= (float)$total_dibelanjakan_ads ?>));
      const sisa = transferred - spend;
      const isRunning = (sisa > 0);
      const statusText = isRunning ? 'Iklan Jalan' : (transferred > 0 ? 'Saldo Habis' : 'Belum Ada Saldo');

      container.innerHTML = `
        <div class="kpi-tile">
          <div class="metric-header">
            <span class="metric-label">Saldo</span>
            <span class="metric-icon">
              <svg viewBox="0 0 24 24" width="16" height="16" stroke="#175CD3" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </span>
          </div>
          <div class="metric-value">
            <span class="metric-num" style="color: #175CD3;">Rp ${transferred.toLocaleString('id-ID')}</span>
          </div>
          <div class="metric-subtitle">
            <span>Nominal saldo yang ditransfer oleh Owner</span>
          </div>
        </div>

        <div class="kpi-tile ${sisa <= 0 ? 'urgent' : ''}">
          <div class="metric-header">
            <span class="metric-label" style="color: ${sisa <= 0 ? '#B42318' : '#027A48'};">Sisa Saldo</span>
            <span class="metric-icon">
              <svg viewBox="0 0 24 24" width="16" height="16" stroke="${sisa <= 0 ? '#B42318' : '#027A48'}" stroke-width="1.75" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </span>
          </div>
          <div class="metric-value">
            <span class="metric-num" style="color: ${sisa <= 0 ? '#B42318' : '#027A48'};">Rp ${sisa.toLocaleString('id-ID')}</span>
          </div>
          <div class="metric-subtitle">
            <span>Sudah dibelanjakan: Rp ${spend.toLocaleString('id-ID')} &bull; Status: ${statusText}</span>
          </div>
        </div>
      `;
      <?php endif; ?>
    }

    function renderAdsProjectsTable(data) {
      <?php if (!$isOwnerOrAdmin): ?>
      return;
      <?php else: ?>
      const tbody = document.getElementById('tbody-ads-projects');
      if (!tbody) return;
      const isPrivileged = (data.role === 'owner' || data.role === 'admin');
      if (!isPrivileged) return;

      const projects = data.projects || [];
      if (projects.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; padding: 32px; color: var(--text-muted);">Belum ada data proyek atau alokasi saldo iklan.</td></tr>`;
        return;
      }

      let html = '';
      projects.forEach(p => {
        const contract = Number(p.total_kontrak_klien || p.contract_value || 0);
        const margin = Number(p.margin_bersih_agensi || 0);
        const pagu = Number(p.total_pagu_ads_klien || p.allocated_ads_budget || 0);
        const transferred = Number(p.total_ditransfer_ke_tim_ads || 0);
        const sisaPaguOwner = Number(p.sisa_pagu_di_owner || 0);
        const sisaSaldoTimAds = Number(p.sisa_saldo_di_tim_ads || 0);

        let stBadge = '<span class="badge" style="background: #ECFDF3; color: #027A48; font-weight: 700;">🟢 Iklan Jalan</span>';
        if (sisaSaldoTimAds <= 0 && transferred > 0) {
          stBadge = '<span class="badge" style="background: #FEF3F2; color: #B42318; font-weight: 700;">🔴 Saldo Tim Habis</span>';
        } else if (transferred <= 0) {
          stBadge = '<span class="badge" style="background: #EFF8FF; color: #175CD3; font-weight: 700;">Menunggu Transfer</span>';
        }

        html += `
          <tr>
            <td>
              <div style="font-weight: 800; color: #101828;">${escapeHtml(p.client_company)}</div>
              <span style="font-size: 11.5px; color: var(--text-secondary);">${escapeHtml(p.project_name)}</span>
            </td>
            <td style="font-weight: 800; color: #101828; white-space: nowrap;">
              Rp ${contract.toLocaleString('id-ID')}
            </td>
            <td style="font-weight: 800; color: #027A48; white-space: nowrap;">
              Rp ${margin.toLocaleString('id-ID')}
            </td>
            <td style="font-weight: 800; color: #5925DC; white-space: nowrap;">
              Rp ${pagu.toLocaleString('id-ID')}
            </td>
            <td style="font-weight: 800; color: #175CD3; white-space: nowrap;">
              Rp ${transferred.toLocaleString('id-ID')}
            </td>
            <td style="font-weight: 800; color: ${sisaPaguOwner <= 0 ? '#B42318' : '#027A48'}; white-space: nowrap;">
              Rp ${sisaPaguOwner.toLocaleString('id-ID')}
            </td>
            <td>
              ${stBadge}
            </td>
            <td class="td-actions" style="text-align: right; white-space: nowrap;">
              <div class="table-actions" style="display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap;">
                <button type="button" class="btn btn-primary btn-sm" onclick="openTransferToWorkerModal(${p.project_id}, ${sisaPaguOwner})" style="font-size: 11px; font-weight: 700; background: #175CD3; border-color: #175CD3;">
                  + Transfer Saldo
                </button>
                ${sisaPaguOwner <= 0 ? `
                  <button type="button" class="btn btn-secondary btn-sm" onclick="openRequestLimitOwnerModal(${p.project_id})" style="font-size: 11px; font-weight: 700; color: #B54708; border-color: #FDB022; background: #FFF9F2;">
                    🔔 Ajukan Limit ke Klien
                  </button>
                ` : ''}
                <button type="button" class="btn btn-secondary btn-sm" onclick="openAllocateAdsModal(${p.project_id}, ${pagu})" style="font-size: 11px; font-weight: 600;">
                  Atur Pagu
                </button>
              </div>
            </td>
          </tr>
        `;
      });

      tbody.innerHTML = html;
      <?php endif; ?>
    }

    function renderAdsTopupTable(data) {
      const tbody = document.getElementById('tbody-ads-topups');
      if (!tbody) return;
      const isPrivileged = <?= json_encode($isOwnerOrAdmin) ?> && (data.role === 'owner' || data.role === 'admin');
      const isOwner = (data.role === 'owner');
      const topups = data.topup_transactions || [];

      if (topups.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="7" style="text-align: center; padding: 36px 20px; color: var(--text-muted); background: #FCFCFD;">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 8px; display: block;"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
              <div style="font-weight: 600; color: #111827; margin-bottom: 2px; font-size: 14px;">Belum Ada Riwayat Alur Transaksi Saldo Iklan</div>
              <div style="font-size: 12px; color: #6B7280; margin-bottom: 12px;">
                ${isPrivileged ? 'Owner dapat mencairkan saldo dengan menekan tombol <b>+ Transfer Saldo ke Tim Ads</b>.' : 'Owner belum mencairkan saldo iklan untuk tugas/proyek Anda.'}
              </div>
              ${isPrivileged ? `
                <button type="button" class="btn btn-primary btn-sm" onclick="openTransferToWorkerModal()" style="font-size: 12px; font-weight: 600; background: #175CD3; border-color: #175CD3;">
                  + Cairkan Saldo Sekarang
                </button>
              ` : ''}
            </td>
          </tr>
        `;
        return;
      }

      let html = '';
      topups.forEach(t => {
        const isVerified = (t.status === 'verified');
        const transferOwner = Number(t.transfer_amount_owner || t.topup_amount || 0);
        const topupPlatform = Number(t.platform_topup_amount || t.topup_amount || 0);
        const spend = Number(t.ad_spend_amount || t.spend_installed || 0);
        const sisa = transferOwner - spend;

        const isPlatformToppedUp = (t.platform_topup_status === 'completed' || topupPlatform > 0);

        if (isPrivileged) {
          // OWNER / ADMIN VIEW: 7 Columns with full internal metrics
          html += `
            <tr>
              <td style="min-width: 190px; white-space: nowrap;">
                <div style="font-weight: 800; color: #101828;">${formatDate(t.transaction_date)}</div>
                <div style="font-size: 11.5px; color: var(--text-secondary); margin-top: 1px;">
                  Oleh: <b>${escapeHtml(t.assigned_user_name || t.uploader_name || 'Tim Ads')}</b>
                </div>
                <span class="badge" style="background: #F2F4F7; color: #344054; font-size: 10px; font-weight: 700; margin-top: 3px;">
                  ${escapeHtml(t.stage_label || 'Tahap 1')}
                </span>
              </td>

              <td style="white-space: nowrap;">
                <div style="font-weight: 800; color: #101828;">${escapeHtml(t.client_company || 'Klien')}</div>
                <div style="color: var(--text-muted); font-size: 11px;">${escapeHtml(t.project_name || 'Proyek')}</div>
                <span class="badge" style="background: #F4F3FF; color: #5925DC; font-weight: 800; font-size: 10.5px; margin-top: 3px;">
                  ${escapeHtml(t.platform || 'Meta Ads')}
                </span>
              </td>

              <td style="white-space: nowrap;">
                <div style="font-weight: 800; color: #175CD3; font-size: 13.5px;">
                  Rp ${transferOwner.toLocaleString('id-ID')}
                </div>
                ${t.transfer_proof_owner ? `
                  <button type="button" class="btn btn-secondary btn-sm" onclick="previewAdsProof('${escapeHtml(t.transfer_proof_owner)}')" style="font-size: 10.5px; font-weight: 700; padding: 2px 8px; margin-top: 4px; display: inline-flex; align-items: center; gap: 4px;">
                    📄 Bukti TF Owner
                  </button>
                ` : `
                  <span style="font-size: 11px; color: var(--text-muted); font-style: italic;">Belum ada bukti</span>
                `}
              </td>

              <td style="white-space: nowrap;">
                ${isPlatformToppedUp ? `
                  <div style="font-weight: 800; color: #027A48; font-size: 13.5px;">
                    Rp ${topupPlatform.toLocaleString('id-ID')}
                  </div>
                  ${t.platform_topup_proof ? `
                    <button type="button" class="btn btn-secondary btn-sm" onclick="previewAdsProof('${escapeHtml(t.platform_topup_proof)}')" style="font-size: 10.5px; font-weight: 700; padding: 2px 8px; margin-top: 4px; color: #027A48; display: inline-flex; align-items: center; gap: 4px;">
                      📈 Bukti Top-Up Ads
                    </button>
                  ` : `
                    <span class="badge" style="background: #ECFDF3; color: #027A48; font-weight: 700; font-size: 10.5px; margin-top: 4px;">
                      ✓ Sudah Top-Up
                    </span>
                  `}
                ` : `
                  <span class="badge" style="background: #FEF0C7; color: #B54708; font-weight: 700; font-size: 11px;">
                    Menunggu Top-Up
                  </span>
                `}
              </td>

              <td style="white-space: nowrap;">
                <div style="font-size: 12px; color: #344054;">
                  Spend: <b style="color: #B42318;">Rp ${spend.toLocaleString('id-ID')}</b>
                </div>
                <div style="font-size: 12px; color: #344054; margin-top: 2px;">
                  Sisa: <b style="color: ${sisa <= 0 ? '#B42318' : '#027A48'};">Rp ${sisa.toLocaleString('id-ID')}</b>
                </div>
              </td>

              <td style="white-space: nowrap;">
                ${(t.campaign_status === 'running' || !t.campaign_status) ? `
                  <span class="badge" style="background: #ECFDF3; color: #027A48; font-weight: 700; font-size: 11px;">
                    🟢 Iklan Jalan
                  </span>
                ` : (t.campaign_status === 'paused' ? `
                  <span class="badge" style="background: #FEF0C7; color: #B54708; font-weight: 700; font-size: 11px;">
                    🟡 Dijeda
                  </span>
                ` : `
                  <span class="badge" style="background: #FEF3F2; color: #B42318; font-weight: 700; font-size: 11px;">
                    🔴 Saldo Habis
                  </span>
                `)}
              </td>

              <td class="td-actions" style="text-align: right; white-space: nowrap;">
                <div class="table-actions" style="display: flex; gap: 6px; justify-content: flex-end;">
                  ${!isVerified && isOwner ? `
                    <button type="button" class="btn-action-success" onclick="verifyTopupTransaction(${t.id})" title="Verifikasi Transaksi">
                      ✓ Verifikasi
                    </button>
                  ` : ''}
                  <button type="button" class="btn-action-delete btn-delete-ghost" onclick="deleteTopupTransaction(${t.id})" title="Hapus Transaksi">
                    Hapus
                  </button>
                </div>
              </td>
            </tr>
          `;
        } else {
          // KARYAWAN / FREELANCER (TIM ADS) VIEW: EXACTLY AS REQUESTED
          // Columns: Tanggal & Proyek, Platform Ads, Saldo (Transfer dari Owner + Bukti TF Owner), Sudah Dibelanjakan (Bukti Top-Up/Spend Ads), Sisa Saldo, Status Kampanye, AKSI
          html += `
            <tr>
              <td style="white-space: nowrap;">
                <div style="font-weight: 800; color: #101828;">${formatDate(t.transaction_date)}</div>
                <div style="font-size: 12px; color: #344054; margin-top: 2px;">
                  <b>${escapeHtml(t.client_company || 'Klien')}</b> &bull; ${escapeHtml(t.project_name || 'Proyek')}
                </div>
                <span class="badge" style="background: #F2F4F7; color: #344054; font-size: 10px; font-weight: 700; margin-top: 3px;">
                  ${escapeHtml(t.stage_label || 'Tahap 1')}
                </span>
              </td>

              <td style="white-space: nowrap;">
                <span class="badge" style="background: #F4F3FF; color: #5925DC; font-weight: 800; font-size: 11px;">
                  ${escapeHtml(t.platform || 'Meta Ads')}
                </span>
              </td>

              <td style="white-space: nowrap;">
                <div style="font-weight: 800; color: #175CD3; font-size: 14px;">
                  Rp ${transferOwner.toLocaleString('id-ID')}
                </div>
                ${t.transfer_proof_owner ? `
                  <button type="button" class="btn btn-secondary btn-sm" onclick="previewAdsProof('${escapeHtml(t.transfer_proof_owner)}')" style="font-size: 10.5px; font-weight: 700; padding: 2px 8px; margin-top: 4px; display: inline-flex; align-items: center; gap: 4px; color: #175CD3; border-color: #B2CCFF; background: #EFF8FF;">
                    📄 Bukti TF Owner
                  </button>
                ` : `
                  <span style="font-size: 11px; color: var(--text-muted); font-style: italic;">Belum ada bukti</span>
                `}
              </td>

              <td style="white-space: nowrap;">
                <div style="font-weight: 800; color: #027A48; font-size: 14px;">
                  Rp ${spend.toLocaleString('id-ID')}
                </div>
                ${t.platform_topup_proof ? `
                  <button type="button" class="btn btn-secondary btn-sm" onclick="previewAdsProof('${escapeHtml(t.platform_topup_proof)}')" style="font-size: 10.5px; font-weight: 700; padding: 2px 8px; margin-top: 4px; color: #027A48; border-color: #A6F4C5; background: #ECFDF3; display: inline-flex; align-items: center; gap: 4px;">
                    📈 Bukti Top-Up Ads
                  </button>
                ` : `
                  <span class="badge" style="background: #ECFDF3; color: #027A48; font-weight: 700; font-size: 10.5px; margin-top: 4px;">
                    ✓ Tercatat
                  </span>
                `}
              </td>

              <td style="white-space: nowrap;">
                <div style="font-weight: 800; font-size: 14px; color: ${sisa <= 0 ? '#B42318' : '#027A48'};">
                  Rp ${sisa.toLocaleString('id-ID')}
                </div>
              </td>

              <td style="white-space: nowrap;">
                ${(sisa > 0 && (t.campaign_status === 'running' || !t.campaign_status)) ? `
                  <span class="badge" style="background: #ECFDF3; color: #027A48; font-weight: 700; font-size: 11px;">
                    🟢 Iklan Jalan
                  </span>
                ` : (t.campaign_status === 'paused' ? `
                  <span class="badge" style="background: #FEF0C7; color: #B54708; font-weight: 700; font-size: 11px;">
                    🟡 Dijeda
                  </span>
                ` : `
                  <span class="badge" style="background: #FEF3F2; color: #B42318; font-weight: 700; font-size: 11px;">
                    🔴 Saldo Habis
                  </span>
                `)}
              </td>

              <td class="td-actions" style="text-align: right; white-space: nowrap;">
                <div class="table-actions" style="display: flex; gap: 6px; justify-content: flex-end;">
                  <button type="button" class="btn btn-primary btn-sm" onclick="openConfirmPlatformTopupModal(${t.id}, ${transferOwner})" style="font-size: 11px; font-weight: 700; background: #027A48; border-color: #027A48;" title="Update Realisasi Top-Up & Spend">
                    ⚡ Update Spend
                  </button>
                </div>
              </td>
            </tr>
          `;
        }
      });

      tbody.innerHTML = html;
    }

    function populateAdsProjectDropdowns(projects) {
      const selectSpend = document.getElementById('ads-spend-project-id');
      const selectAlloc = document.getElementById('ads-alloc-project-id');
      const selectTopup = document.getElementById('ads-topup-project-id');
      const selectTransferWorker = document.getElementById('transfer-worker-project-id');
      const selectReqWorker = document.getElementById('req-worker-project-id');
      const selectReqOwner = document.getElementById('req-owner-project-id');

      let optionsHtml = '<option value="">-- Pilih Proyek Klien --</option>';
      projects.forEach(p => {
        <?php if ($isOwnerOrAdmin): ?>
        const sisaPaguOwner = Number(p.sisa_pagu_di_owner || 0);
        const paguTotal = Number(p.total_pagu_ads_klien || p.allocated_ads_budget || 0);
        optionsHtml += `<option value="${p.project_id}" data-allocated="${paguTotal}" data-sisa-pagu="${sisaPaguOwner}">[${escapeHtml(p.client_company)}] ${escapeHtml(p.project_name)}</option>`;
        <?php else: ?>
        optionsHtml += `<option value="${p.project_id}">[${escapeHtml(p.client_company)}] ${escapeHtml(p.project_name)}</option>`;
        <?php endif; ?>
      });

      if (selectSpend) selectSpend.innerHTML = optionsHtml;
      if (selectAlloc) selectAlloc.innerHTML = optionsHtml;
      if (selectTopup) selectTopup.innerHTML = optionsHtml;
      if (selectTransferWorker) selectTransferWorker.innerHTML = optionsHtml;
      if (selectReqWorker) selectReqWorker.innerHTML = optionsHtml;
      if (selectReqOwner) selectReqOwner.innerHTML = optionsHtml;
    }

    function onAdsProjectSelectChange(selectElem) {
      const selectedOption = selectElem.options[selectElem.selectedIndex];
      const box = document.getElementById('box-allocated-info');
      const txt = document.getElementById('txt-allocated-budget');
      if (!box || !txt) return;

      const alloc = Number(selectedOption ? selectedOption.getAttribute('data-allocated') || 0 : 0);
      if (selectElem.value && alloc > 0) {
        txt.innerText = 'Rp ' + alloc.toLocaleString('id-ID');
        box.style.display = 'block';
      } else if (selectElem.value) {
        txt.innerText = 'Rp 0 (Belum dialokasikan Owner)';
        box.style.display = 'block';
      } else {
        box.style.display = 'none';
      }
    }

    function openUploadAdsTopupModal(projectId = null, defaultPlatform = 'Meta Ads') {
      const form = document.getElementById('form-upload-ads-topup');
      if (form) form.reset();

      const dateInput = document.getElementById('ads-topup-date');
      if (dateInput) {
        dateInput.value = new Date().toISOString().split('T')[0];
      }

      const projSelect = document.getElementById('ads-topup-project-id');
      if (projSelect && projectId) {
        projSelect.value = projectId;
        onTopupProjectChange(projSelect);
      } else {
        const box = document.getElementById('topup-allocated-info');
        if (box) box.style.display = 'none';
      }

      const platSelect = document.getElementById('ads-topup-platform');
      if (platSelect && defaultPlatform) {
        platSelect.value = defaultPlatform;
      }

      openModal('modal-upload-ads-topup');
    }

    function onTopupProjectChange(selectElem) {
      const selectedOption = selectElem.options[selectElem.selectedIndex];
      const box = document.getElementById('topup-allocated-info');
      const txt = document.getElementById('txt-topup-allocated-budget');
      if (!box || !txt) return;

      const alloc = Number(selectedOption ? selectedOption.getAttribute('data-allocated') || 0 : 0);
      if (selectElem.value && alloc > 0) {
        txt.innerText = 'Rp ' + alloc.toLocaleString('id-ID');
        box.style.display = 'block';
      } else if (selectElem.value) {
        txt.innerText = 'Rp 0 (Belum dialokasikan Owner)';
        box.style.display = 'block';
      } else {
        box.style.display = 'none';
      }
    }

    async function handleUploadAdsTopup(e) {
      e.preventDefault();
      const form = document.getElementById('form-upload-ads-topup');
      const submitBtn = document.getElementById('btn-submit-ads-topup');
      const origText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Mengupload Bukti...';

      const formData = new FormData(form);
      try {
        const res = await fetch('api/ads_tracker.php?action=upload_topup', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          closeModal('modal-upload-ads-topup');
          form.reset();
          loadAdsTrackerData();
        } else {
          showToast(data.message || 'Gagal mengupload bukti isi saldo', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origText;
      }
    }

    // -------------------------------------------------------------
    // MODAL HANDLERS: TRANSFER TO WORKER (OWNER)
    // -------------------------------------------------------------
    function openTransferToWorkerModal(projectId = null, currentSisaPagu = null, defaultAmount = null) {
      const form = document.getElementById('form-transfer-to-worker');
      if (form) form.reset();

      const projSelect = document.getElementById('transfer-worker-project-id');
      if (projSelect && projectId) {
        projSelect.value = projectId;
        onTransferProjectChange(projSelect);
      } else {
        const box = document.getElementById('box-transfer-sisa-pagu');
        if (box) box.style.display = 'none';
      }

      if (defaultAmount && defaultAmount > 0) {
        const amountInput = document.getElementById('transfer-worker-amount');
        if (amountInput) amountInput.value = Number(defaultAmount).toLocaleString('id-ID');
      }

      openModal('modal-transfer-to-worker');
    }

    function onTransferProjectChange(selectElem) {
      const selectedOption = selectElem.options[selectElem.selectedIndex];
      const box = document.getElementById('box-transfer-sisa-pagu');
      const txt = document.getElementById('txt-transfer-sisa-pagu');
      if (!box || !txt) return;

      const sisa = Number(selectedOption ? selectedOption.getAttribute('data-sisa-pagu') || 0 : 0);
      if (selectElem.value) {
        txt.innerText = 'Rp ' + sisa.toLocaleString('id-ID');
        txt.style.color = sisa <= 0 ? '#B42318' : '#027A48';
        box.style.display = 'block';
      } else {
        box.style.display = 'none';
      }
    }

    async function handleTransferToWorker(e) {
      e.preventDefault();
      const form = document.getElementById('form-transfer-to-worker');
      const submitBtn = document.getElementById('btn-submit-transfer-worker');
      const origText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Memproses Transfer...';

      const formData = new FormData(form);
      try {
        const res = await fetch('api/ads_tracker.php?action=transfer_to_worker', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          closeModal('modal-transfer-to-worker');
          form.reset();
          loadAdsTrackerData();
        } else {
          showToast(data.message || 'Gagal mencairkan saldo ads', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origText;
      }
    }

    // -------------------------------------------------------------
    // MODAL HANDLERS: KONFIRMASI TOP-UP PLATFORM & SPEND (TIM ADS)
    // -------------------------------------------------------------
    function openConfirmPlatformTopupModal(transId = null, ownerTransferAmount = 0, projectId = null) {
      const form = document.getElementById('form-confirm-platform-topup');
      if (form) form.reset();

      document.getElementById('confirm-topup-trans-id').value = transId || '';
      document.getElementById('txt-confirm-owner-transfer').innerText = 'Rp ' + Number(ownerTransferAmount || 0).toLocaleString('id-ID');

      if (allLoadedAdsData && allLoadedAdsData.topup_transactions) {
        const t = allLoadedAdsData.topup_transactions.find(x => String(x.id) === String(transId));
        if (t) {
          document.getElementById('txt-confirm-topup-proj-info').innerText = `Proyek: [${t.client_company}] ${t.project_name} (${t.platform})`;
          if (t.platform_topup_amount > 0) {
            document.getElementById('confirm-platform-amount').value = Number(t.platform_topup_amount).toLocaleString('id-ID');
          } else if (ownerTransferAmount > 0) {
            document.getElementById('confirm-platform-amount').value = Number(ownerTransferAmount).toLocaleString('id-ID');
          }
          if (t.ad_spend_amount > 0) {
            document.getElementById('confirm-spend-amount').value = Number(t.ad_spend_amount).toLocaleString('id-ID');
          }
          if (t.campaign_status) {
            document.getElementById('confirm-campaign-status').value = t.campaign_status;
          }
          if (t.notes) {
            document.getElementById('confirm-topup-notes').value = t.notes;
          }
        }
      }

      openModal('modal-confirm-platform-topup');
    }

    function openConfirmPlatformTopupModalForProject(projectId, ownerTransferAmount = 0) {
      let transId = null;
      if (allLoadedAdsData && allLoadedAdsData.topup_transactions) {
        const match = allLoadedAdsData.topup_transactions.find(t => String(t.project_id) === String(projectId));
        if (match) {
          transId = match.id;
          ownerTransferAmount = match.transfer_amount_owner || ownerTransferAmount;
        }
      }
      openConfirmPlatformTopupModal(transId, ownerTransferAmount, projectId);
    }

    async function handleConfirmPlatformTopup(e) {
      e.preventDefault();
      const form = document.getElementById('form-confirm-platform-topup');
      const submitBtn = document.getElementById('btn-submit-confirm-topup');
      const origText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Menyimpan & Menyinkronkan...';

      const formData = new FormData(form);
      try {
        const res = await fetch('api/ads_tracker.php?action=confirm_platform_topup', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          closeModal('modal-confirm-platform-topup');
          form.reset();
          loadAdsTrackerData();
        } else {
          showToast(data.message || 'Gagal menyimpan konfirmasi topup', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origText;
      }
    }

    // -------------------------------------------------------------
    // MODAL HANDLERS: MINTA SALDO KE OWNER (TIM ADS)
    // -------------------------------------------------------------
    function openRequestBalanceWorkerModal(projectId = null) {
      const form = document.getElementById('form-request-balance-worker');
      if (form) form.reset();

      const projSelect = document.getElementById('req-worker-project-id');
      if (projSelect && projectId) {
        projSelect.value = projectId;
      }

      openModal('modal-request-balance-worker');
    }

    async function handleRequestBalanceWorker(e) {
      e.preventDefault();
      const form = document.getElementById('form-request-balance-worker');
      const submitBtn = document.getElementById('btn-submit-req-worker');
      const origText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Mengirim Permintaan...';

      const formData = new FormData(form);
      try {
        const res = await fetch('api/ads_tracker.php?action=request_balance', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          closeModal('modal-request-balance-worker');
          form.reset();
          loadAdsTrackerData();
        } else {
          showToast(data.message || 'Gagal mengirim permintaan', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origText;
      }
    }

    // -------------------------------------------------------------
    // MODAL HANDLERS: AJUKAN LIMIT KE KLIEN (OWNER)
    // -------------------------------------------------------------
    function openRequestLimitOwnerModal(projectId = null) {
      const form = document.getElementById('form-request-limit-owner');
      if (form) form.reset();

      const projSelect = document.getElementById('req-owner-project-id');
      if (projSelect && projectId) {
        projSelect.value = projectId;
      }

      openModal('modal-request-limit-owner');
    }

    async function handleRequestLimitOwner(e) {
      e.preventDefault();
      const form = document.getElementById('form-request-limit-owner');
      const submitBtn = document.getElementById('btn-submit-req-owner');
      const origText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Mengirim Pengajuan...';

      const formData = new FormData(form);
      try {
        const res = await fetch('api/ads_tracker.php?action=request_balance', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          closeModal('modal-request-limit-owner');
          form.reset();
          loadAdsTrackerData();
        } else {
          showToast(data.message || 'Gagal mengirim pengajuan ke klien', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origText;
      }
    }

    async function verifyTopupTransaction(transId) {
      const formData = new FormData();
      formData.append('transaction_id', transId);
      try {
        const res = await fetch('api/ads_tracker.php?action=verify_topup', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          loadAdsTrackerData();
        } else {
          showToast(data.message || 'Gagal memverifikasi', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      }
    }

    async function deleteTopupTransaction(transId) {
      if (!confirm('Apakah Anda yakin ingin menghapus transaksi isi saldo ini?')) return;
      const formData = new FormData();
      formData.append('transaction_id', transId);
      try {
        const res = await fetch('api/ads_tracker.php?action=delete_topup', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          loadAdsTrackerData();
        } else {
          showToast(data.message || 'Gagal menghapus transaksi', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      }
    }

    function openLogAdsSpendModal(projectId = null, defaultPlatform = 'Meta Ads') {
      const form = document.getElementById('form-update-ads-spend');
      if (form) form.reset();

      const projSelect = document.getElementById('ads-spend-project-id');
      if (projSelect && projectId) {
        projSelect.value = projectId;
        onAdsProjectSelectChange(projSelect);
      } else {
        const box = document.getElementById('box-allocated-info');
        if (box) box.style.display = 'none';
      }

      const platSelect = document.getElementById('ads-spend-platform');
      if (platSelect && defaultPlatform) {
        platSelect.value = defaultPlatform;
      }

      openModal('modal-update-ads-spend');
    }

    function openAllocateAdsModal(projectId = null, currentBudget = 0) {
      const form = document.getElementById('form-allocate-ads-budget');
      if (form) form.reset();

      const projSelect = document.getElementById('ads-alloc-project-id');
      if (projSelect && projectId) {
        projSelect.value = projectId;
      }

      const amountInput = document.getElementById('ads-alloc-amount');
      if (amountInput && currentBudget > 0) {
        amountInput.value = Number(currentBudget).toLocaleString('id-ID');
      }

      openModal('modal-allocate-ads-budget');
    }

    async function handleLogAdsSpend(e) {
      e.preventDefault();
      const form = document.getElementById('form-update-ads-spend');
      const submitBtn = document.getElementById('btn-submit-ads-spend');
      const origText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Menyimpan...';

      const formData = new FormData(form);
      try {
        const res = await fetch('api/ads_tracker.php?action=log_spend', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          closeModal('modal-update-ads-spend');
          form.reset();
          loadAdsTrackerData();
        } else {
          showToast(data.message || 'Gagal menyimpan anggaran iklan', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origText;
      }
    }

    async function handleAllocateAdsBudget(e) {
      e.preventDefault();
      const form = document.getElementById('form-allocate-ads-budget');
      const submitBtn = document.getElementById('btn-submit-ads-alloc');
      const origText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Menyimpan...';

      const formData = new FormData(form);
      try {
        const res = await fetch('api/ads_tracker.php?action=allocate_budget', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          closeModal('modal-allocate-ads-budget');
          form.reset();
          loadAdsTrackerData();
        } else {
          showToast(data.message || 'Gagal menyimpan alokasi pagu saldo', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origText;
      }
    }

    async function verifyAdsLog(logId) {
      const formData = new FormData();
      formData.append('log_id', logId);
      try {
        const res = await fetch('api/ads_tracker.php?action=verify_log', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          loadAdsTrackerData();
        } else {
          showToast(data.message || 'Gagal memverifikasi', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      }
    }

    async function deleteAdsLog(logId) {
      if (!confirm('Hapus riwayat anggaran iklan ini?')) return;
      const formData = new FormData();
      formData.append('log_id', logId);
      try {
        const res = await fetch('api/ads_tracker.php?action=delete_log', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          loadAdsTrackerData();
        } else {
          showToast(data.message || 'Gagal menghapus', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      }
    }

    function previewAdsProof(url) {
      const img = document.getElementById('img-proof-preview');
      const iframe = document.getElementById('iframe-proof-preview');

      if (url.toLowerCase().endsWith('.pdf')) {
        img.style.display = 'none';
        iframe.src = url;
        iframe.style.display = 'block';
      } else {
        iframe.style.display = 'none';
        img.src = url;
        img.style.display = 'inline-block';
      }
      openModal('modal-preview-ads-proof');
    }

    function formatRupiahInput(input) {
      let val = input.value.replace(/[^0-9]/g, '');
      if (val) {
        input.value = Number(val).toLocaleString('id-ID');
      } else {
        input.value = '';
      }
    }
  </script>

  <?php require_once BASE_PATH . '/includes/modals.php'; ?>
  <script src="assets/js/app.js?v=<?= time() ?>"></script>
  <script src="assets/js/invoice.js?v=<?= time() ?>"></script>
</body>
</html>

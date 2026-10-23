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
            OR t.project_id IN (SELECT DISTINCT project_id FROM freelancer_payouts WHERE (freelancer_name = ? OR freelancer_name LIKE ?) AND COALESCE(is_deleted,0)=0)
        )
    ");
    $stmtWorkerAds->execute([$userId, $userId, $userId, $userId, $user['name'], '%' . $user['name'] . '%']);
    $workerSummaryRow = $stmtWorkerAds->fetch(PDO::FETCH_ASSOC);
    $total_ditransfer_owner = floatval($workerSummaryRow['total_transfer'] ?? 0);
    $total_dibelanjakan_ads = floatval($workerSummaryRow['total_spent'] ?? 0);
    $sisa_saldo = $total_ditransfer_owner - $total_dibelanjakan_ads;
    $status_iklan = ($sisa_saldo > 0) ? 'Iklan Jalan' : ($total_ditransfer_owner > 0 ? 'Saldo Habis' : 'Belum Ada Saldo');

    // Freelancer Saldo & Honorarium Payouts (Real-Time from freelancer_payouts)
    $freelancerPaid = 0.0;
    $freelancerPending = 0.0;
    if ($role === 'freelancer') {
        $stmtFp = $db->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN status = 'Paid' THEN amount ELSE 0 END), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN status = 'Pending' THEN amount ELSE 0 END), 0) as pending_amount
            FROM freelancer_payouts 
            WHERE COALESCE(is_deleted, 0) = 0 
            AND (freelancer_name = ? OR freelancer_name LIKE ? OR project_id IN (SELECT DISTINCT project_id FROM tasks WHERE assigned_to_user_id = ? AND COALESCE(is_deleted, 0) = 0))
        ");
        $stmtFp->execute([$user['name'], '%' . $user['name'] . '%', $userId]);
        $fpRow = $stmtFp->fetch(PDO::FETCH_ASSOC);
        $freelancerPaid = floatval($fpRow['paid_amount'] ?? 0);
        $freelancerPending = floatval($fpRow['pending_amount'] ?? 0);
    }
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

      /* Global Mobile Layout & Spacing (Bento Style) */
      body,
      .app-container,
      .main-wrapper {
        background: #F8FAFC !important;
      }

      .content-body {
        padding-top: 14px !important;
        padding-bottom: calc(96px + env(safe-area-inset-bottom, 0px)) !important;
        padding-left: 16px !important;
        padding-right: 16px !important;
      }

      /* Bento Metric Cards: 2x2 Grid with Minimalist Typography */
      .bento-deliverables-2x2-grid {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 10px !important;
        margin-bottom: 14px !important;
      }
      .bento-metric-card,
      .bento-total-card {
        background: #FFFFFF !important;
        border: 1px solid #F1F5F9 !important;
        border-radius: 20px !important;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03) !important;
      }

      /* Bento Filter & Search Panel */
      .bento-filter-panel {
        background: #FFFFFF !important;
        border: 1px solid #F1F5F9 !important;
        border-radius: 20px !important;
        padding: 16px !important;
        margin-bottom: 14px !important;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03) !important;
      }

      /* Segmented Controls Chips (bg-slate-100 p-1 rounded-xl) */
      .bento-segmented-control,
      .task-services-track,
      #status-filter-group.task-status-grid,
      .task-status-grid {
        display: flex !important;
        flex-direction: row !important;
        overflow-x: auto !important;
        white-space: nowrap !important;
        gap: 4px !important;
        width: 100% !important;
        box-sizing: border-box !important;
        -webkit-overflow-scrolling: touch !important;
        scrollbar-width: none !important;
        background: #F1F5F9 !important;
        padding: 4px !important;
        border-radius: 12px !important;
        flex-wrap: nowrap !important;
      }
      .bento-segmented-control::-webkit-scrollbar,
      .task-services-track::-webkit-scrollbar,
      .task-status-grid::-webkit-scrollbar {
        display: none !important;
      }

      .task-service-pill,
      .task-status-grid .filter-btn {
        width: auto !important;
        flex-shrink: 0 !important;
        padding: 7px 14px !important;
        font-size: 12px !important;
        font-weight: 500 !important;
        color: #64748B !important;
        background: transparent !important;
        border: none !important;
        border-radius: 8px !important;
        cursor: pointer !important;
        white-space: nowrap !important;
        transition: all 0.15s ease !important;
        box-sizing: border-box !important;
      }
      .task-service-pill.active,
      .task-status-grid .filter-btn.active {
        background: #FFFFFF !important;
        color: #0F172A !important;
        font-weight: 600 !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08) !important;
      }

      /* Quick Action Buttons in Tab Saldo Ads (Round 38px circle anti-gepeng) */
      .appify-action-grid {
        gap: 8px !important;
      }
      .appify-action-btn {
        padding: 12px 6px !important;
        height: auto !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px !important;
        border-radius: 16px !important;
        border: 1px solid #F1F5F9 !important;
        background: #FFFFFF !important;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03) !important;
        box-sizing: border-box !important;
      }
      .appify-action-icon-circle {
        width: 38px !important;
        height: 38px !important;
        min-width: 38px !important;
        min-height: 38px !important;
        border-radius: 50% !important;
        flex-shrink: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
      }

      /* Task Items Bento Card */
      .task-item-card {
        border-radius: 20px !important;
        border: 1px solid #F1F5F9 !important;
        padding: 16px 18px !important;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03) !important;
        margin-bottom: 12px !important;
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
        <!-- Header Card (Admin / PM Only) -->
        <?php if ($isAdminUser): ?>
        <div class="tasks-header-card">
          <div>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
              <span class="badge" style="background: #F2F4F7; color: #101828; font-weight: 800; font-size: 11px;">
                ADMIN &amp; PM WORKSPACE
              </span>
              <span style="font-size: 12px; color: var(--text-muted);">&bull;</span>
              <span style="font-size: 12px; color: var(--text-secondary); font-weight: 600;">
                Login sebagai: <b><?= htmlspecialchars($user['name']) ?></b> (Admin)
              </span>
            </div>
            <h2 style="font-size: 20px; font-weight: 800; color: #101828; margin: 0; letter-spacing: -0.3px;">
              Manajemen Tugas &amp; Pembagian Kerja SDM
            </h2>
            <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0;">
              Distribusikan deliverables proyek ke Karyawan In-House dan Freelancer, serta pantau progres dan revisi klien.
            </p>
          </div>

          <div class="tasks-header-actions" style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button class="btn btn-primary btn-sm" onclick="openAssignTaskModal()">
              <span>+ Bagi Tugas Baru (Assign)</span>
            </button>
            <button class="btn btn-secondary btn-sm" onclick="deleteAllTasks()" style="color: #B42318; border-color: #FECDCA; background: #FEF3F2; font-weight: 700;" title="Hapus Semua Tugas">
              <span>Hapus Semua Tugas</span>
            </button>
            <button class="btn btn-secondary btn-sm" onclick="loadTasksData()">
              <span>Segarkan</span>
            </button>
          </div>
        </div>
        <?php endif; ?>

        <!-- Section Switcher Tabs (Desktop Only on Mobile for Workers) -->
        <div class="workspace-tabs-bar <?= !$isAdminUser ? 'worker-tabs-bar' : '' ?>">
          <button type="button" class="workspace-tab-btn active" id="tab-btn-tasks" onclick="switchTaskWorkspaceTab('tasks')">
            <span class="desktop-text">Deliverables &amp; Penugasan Tim</span>
            <span class="mobile-text">Deliverables Tim</span>
          </button>
          <button type="button" class="workspace-tab-btn" id="tab-btn-ads" onclick="switchTaskWorkspaceTab('ads')">
            <span class="desktop-text">Pelacakan &amp; Koordinasi Anggaran Ads</span>
            <span class="mobile-text">Pelacakan Ads</span>
          </button>
          <button type="button" class="workspace-tab-btn" id="tab-btn-recap" onclick="switchTaskWorkspaceTab('recap')">
            <span class="desktop-text">Report &amp; Rekap Pekerjaan</span>
            <span class="mobile-text">Rekap Kerja</span>
          </button>
        </div>

        <!-- SECTION 1: Tasks & Deliverables -->
        <div id="section-tasks-content">
          <!-- Alert Banner for Workers if Revision / Client Feedback Arrived -->
          <div id="revision-alert-container" style="display: none;"></div>

          <?php if ($role === 'freelancer'): ?>
            <!-- Freelancer Saldo & Honorarium Payouts (Clean 2-Column Bento Grid) -->
            <div class="freelancer-bento-fee-grid" style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-bottom: 12px;">
              <!-- Card 1: Fee Diterima -->
              <div class="bento-metric-card" style="background: #FFFFFF; border: 1px solid #F1F5F9; border-radius: 16px; padding: 12px 14px; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03); display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                    <span style="font-size: 11.5px; font-weight: 600; color: #64748B;">Fee Diterima</span>
                    <span style="background: #ECFDF5; color: #059669; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 999px;">Lunas</span>
                  </div>
                  <div style="font-size: 17px; font-weight: 800; color: #0F172A; line-height: 1.2; letter-spacing: -0.3px;">
                    <span id="kpi-freelancer-paid"><?= format_rupiah($freelancerPaid) ?></span>
                  </div>
                </div>
                <div style="font-size: 10.5px; color: #059669; font-weight: 500; margin-top: 3px;">
                  ✓ Sudah ditransfer
                </div>
              </div>

              <!-- Card 2: Menunggu Transfer -->
              <div class="bento-metric-card" style="background: #FFFFFF; border: 1px solid #F1F5F9; border-radius: 16px; padding: 12px 14px; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03); display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                    <span style="font-size: 11.5px; font-weight: 600; color: #64748B;">Menunggu TF</span>
                    <span style="background: #FFFBEB; color: #D97706; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 999px;">Antrean</span>
                  </div>
                  <div style="font-size: 17px; font-weight: 800; color: #0F172A; line-height: 1.2; letter-spacing: -0.3px;">
                    <span id="kpi-freelancer-pending"><?= format_rupiah($freelancerPending) ?></span>
                  </div>
                </div>
                <div style="font-size: 10.5px; color: #D97706; font-weight: 500; margin-top: 3px;">
                  ⏳ Proses Owner
                </div>
              </div>

              <!-- Hidden Total Fee for data-binding compatibility -->
              <span id="kpi-freelancer-total" style="display: none;"><?= format_rupiah($freelancerPaid + $freelancerPending) ?></span>
            </div>
          <?php endif; ?>

          <?php if ($isWorkerUser): ?>
            <!-- Worker Info: Jobdesk & Klien (Compact Single Card) -->
            <div class="bento-worker-info-card" style="background: #FFFFFF; border: 1px solid #F1F5F9; border-radius: 16px; padding: 12px 14px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(15,23,42,0.03); display: flex; flex-direction: column; gap: 8px; width: 100%; box-sizing: border-box;">
              <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 12px;">
                <div style="display: flex; align-items: center; gap: 6px; min-width: 0; flex: 1;">
                  <span style="font-size: 10px; font-weight: 700; color: #4F46E5; background: #EEF2FF; padding: 2px 7px; border-radius: 6px; flex-shrink: 0;">JOBDESK</span>
                  <span style="font-weight: 600; color: #0F172A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <?= htmlspecialchars($workerProfile['position'] ?? 'Spesialis Tim') ?> &bull; <span style="font-weight: 400; color: #64748B;"><?= htmlspecialchars($workerProfile['jobdesk'] ?? 'Pengerjaan deliverables kreatif') ?></span>
                  </span>
                </div>
              </div>
              <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: 11.5px;">
                <span style="font-size: 10px; font-weight: 700; color: #059669; background: #ECFDF5; padding: 2px 7px; border-radius: 6px; flex-shrink: 0;">KLIEN</span>
                <?php if (empty($assignedClients)): ?>
                  <span style="color: #94A3B8; font-style: italic; font-size: 11px;">Semua penugasan aktif</span>
                <?php else: ?>
                  <?php foreach ($assignedClients as $ac): ?>
                    <span style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 2px 8px; border-radius: 6px; color: #334155; font-weight: 600; font-size: 11px;">
                      <?= htmlspecialchars($ac['company']) ?>
                    </span>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- 1 Baris Stat Strip Compact (4 Kolom Kecil: Total, Proses, Revisi, Selesai) -->
          <div class="bento-stat-strip" style="background: #FFFFFF; border: 1px solid #F1F5F9; border-radius: 16px; padding: 10px 12px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(15,23,42,0.03); display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; text-align: center; width: 100%; box-sizing: border-box;">
            <div style="padding: 2px;">
              <div style="font-size: 18px; font-weight: 800; color: #0F172A; line-height: 1.2;" id="kpi-total">0</div>
              <div style="font-size: 11px; font-weight: 600; color: #64748B; margin-top: 2px;">Total</div>
            </div>
            <div style="padding: 2px; border-left: 1px solid #F1F5F9;">
              <div style="font-size: 18px; font-weight: 800; color: #4F46E5; line-height: 1.2;" id="kpi-progress">0</div>
              <div style="font-size: 11px; font-weight: 600; color: #4F46E5; margin-top: 2px;">Proses</div>
            </div>
            <div style="padding: 2px; border-left: 1px solid #F1F5F9;">
              <div style="font-size: 18px; font-weight: 800; color: #EF4444; line-height: 1.2;" id="kpi-revision">0</div>
              <div style="font-size: 11px; font-weight: 600; color: #EF4444; margin-top: 2px;">Revisi</div>
            </div>
            <div style="padding: 2px; border-left: 1px solid #F1F5F9;">
              <div style="font-size: 18px; font-weight: 800; color: #10B981; line-height: 1.2;" id="kpi-approved">0</div>
              <div style="font-size: 11px; font-weight: 600; color: #10B981; margin-top: 2px;">Selesai</div>
            </div>
            <!-- Hidden Review count for complete JS binding safety -->
            <span id="kpi-review" style="display: none;">0</span>
          </div>

          <!-- Bento Filter & Search Panel (Compact & Streamlined) -->
          <div class="bento-filter-panel" style="background: #FFFFFF; border: 1px solid #F1F5F9; border-radius: 16px; padding: 10px 12px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03); width: 100%; box-sizing: border-box; display: flex; flex-direction: column; gap: 8px;">
            <!-- Row 1: Search Input (Clean Border with Left Icon) -->
            <div style="position: relative; display: flex; align-items: center; width: 100%;">
              <span style="position: absolute; left: 12px; display: flex; align-items: center; pointer-events: none; color: #94A3B8;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              </span>
              <input type="text" id="task-search-input" class="form-control" placeholder="Cari deliverables, klien, PIC..." oninput="handleTaskSearch()" style="width: 100%; height: 38px; border-radius: 10px; border: 1px solid #E2E8F0; background: #F8FAFC; padding: 6px 32px 6px 36px; font-size: 13px; color: #0F172A; outline: none; box-sizing: border-box; transition: all 0.15s ease;">
              <button type="button" id="task-search-clear-btn" style="position: absolute; right: 10px; background: transparent; border: none; color: #94A3B8; font-size: 18px; cursor: pointer; display: none; line-height: 1;" onclick="clearTaskSearch()">&times;</button>
            </div>

            <!-- Row 2: Status Pekerjaan (Clean Segmented Control) -->
            <div class="bento-segmented-control task-status-grid" id="status-filter-group" style="background: #F1F5F9; padding: 3px; border-radius: 10px; display: flex; gap: 3px; overflow-x: auto; scrollbar-width: none; -webkit-overflow-scrolling: touch; width: 100%; box-sizing: border-box;">
              <button type="button" class="filter-btn active" onclick="filterByStatus('all', this)" style="border: none; border-radius: 8px; padding: 6px 12px; font-size: 11.5px; font-weight: 500; color: #64748B; background: transparent; cursor: pointer; white-space: nowrap; transition: all 0.15s ease;">Semua</button>
              <button type="button" class="filter-btn" onclick="filterByStatus('in_progress', this)" style="border: none; border-radius: 8px; padding: 6px 12px; font-size: 11.5px; font-weight: 500; color: #64748B; background: transparent; cursor: pointer; white-space: nowrap; transition: all 0.15s ease;">In Progress</button>
              <button type="button" class="filter-btn" onclick="filterByStatus('client_review', this)" style="border: none; border-radius: 8px; padding: 6px 12px; font-size: 11.5px; font-weight: 500; color: #64748B; background: transparent; cursor: pointer; white-space: nowrap; transition: all 0.15s ease;">Review</button>
              <button type="button" class="filter-btn" onclick="filterByStatus('revision', this)" style="border: none; border-radius: 8px; padding: 6px 12px; font-size: 11.5px; font-weight: 500; color: #64748B; background: transparent; cursor: pointer; white-space: nowrap; transition: all 0.15s ease;">Revisi</button>
              <button type="button" class="filter-btn" onclick="filterByStatus('approved', this)" style="border: none; border-radius: 8px; padding: 6px 12px; font-size: 11.5px; font-weight: 500; color: #64748B; background: transparent; cursor: pointer; white-space: nowrap; transition: all 0.15s ease;">Selesai</button>
            </div>

            <!-- Hidden Category Track & Count Label to preserve full JS compatibility -->
            <div class="task-services-track" style="display: none !important;">
              <button type="button" class="task-service-pill active" onclick="filterTaskService('all', this)">Semua Jasa</button>
            </div>
            <span id="tasks-count-label" style="display: none;">Menampilkan 0 tugas</span>
            <?php if ($isAdminUser): ?>
              <div style="margin-top: 4px;">
                <select id="filter-worker-type" class="form-control" onchange="applyFilters()" style="height: 36px; border-radius: 10px; border: 1px solid #E2E8F0; background: #F8FAFC; font-size: 12.5px; font-weight: 600; color: #334155; padding: 0 10px; width: 100%;">
                  <option value="">Semua SDM (Karyawan &amp; Freelancer)</option>
                  <option value="karyawan">Karyawan (In-House)</option>
                  <option value="freelancer">Freelancer</option>
                </select>
              </div>
            <?php endif; ?>
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

          <?php if ($isOwnerOrAdmin): ?>
          <!-- Ads Tracker Header & Controls (Clean SaaS Header for Owner & Admin) -->
          <div class="page-header-bar" style="background: #FFFFFF; border: 1px solid #EAECEF; border-radius: 12px; padding: 18px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; box-shadow: 0 1px 2px rgba(16,24,40,0.04);">
            <div>
              <h2 class="panel-title" style="font-size: 16px; font-weight: 600; color: #111827; margin: 0; letter-spacing: -0.015em;">
                Pelacakan Dana &amp; Anggaran Iklan (Ads Tracker)
              </h2>
              <p style="font-size: 12.5px; color: #6B7280; margin: 3px 0 0;">
                Kawal alur dana iklan 2 tahap (Owner &rarr; Tim Ads &rarr; Meta/Google), pantau sisa pagu, dan verifikasi bukti transaksi secara presisi.
              </p>
            </div>

            <div class="ads-tracker-header-actions" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
              <button type="button" class="btn btn-primary btn-sm" onclick="openTransferToWorkerModal()" style="font-weight: 700; background: #175CD3; border-color: #175CD3;">
                <span>+ Transfer Saldo ke Tim Ads</span>
              </button>
              <button type="button" class="btn btn-secondary btn-sm" onclick="openAllocateAdsModal()">
                <span>+ Atur Pagu Saldo Ads</span>
              </button>
              <button type="button" class="btn btn-secondary btn-sm" onclick="loadAdsTrackerData()">
                <span>Segarkan Data</span>
              </button>
            </div>
          </div>

          <!-- Summary KPI Cards (Owner & Admin) -->
          <div class="kpi-row" id="ads-kpi-container">
            <div class="kpi-tile">
              <span style="font-size: 11.5px; color: var(--text-secondary); font-weight: 600;">Memuat Ringkasan Owner...</span>
            </div>
          </div>

          <!-- Top-Up Transactions & Proof Table Card (Owner & Admin) -->
          <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(16,24,40,0.05); min-width: 0; max-width: 100%; overflow: visible;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
              <div>
                <div style="display: flex; align-items: center; gap: 8px;">
                  <h4 style="font-size: 15px; font-weight: 800; color: #101828; margin: 0;">
                    Riwayat &amp; Bukti Transaksi Saldo Iklan (Alur 2 Tahap)
                  </h4>
                  <span class="badge" style="background: #ECFDF3; color: #027A48; font-weight: 700; font-size: 10.5px;">Tampil di Portal Klien</span>
                </div>
                <span style="font-size: 12px; color: var(--text-secondary);">
                  Catatan pencairan dari Owner ke Tim Ads dan penyetoran saldo ke platform Meta Ads / Google Ads beserta lampiran bukti fisik.
                </span>
              </div>
              <button type="button" class="btn btn-secondary btn-sm" onclick="openTransferToWorkerModal()" style="font-size: 12px; font-weight: 700; color: #175CD3; border-color: #B2CCFF; background: #EFF8FF;">
                + Transfer Saldo Baru
              </button>
            </div>

            <div class="kala-table-scroll table-responsive" style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; display:block;">
              <table class="table-custom" id="table-ads-topups" style="min-width: 980px; width: 100%;">
                <thead>
                  <tr>
                    <th style="min-width: 190px; white-space: nowrap;">TANGGAL &amp; PETUGAS ADS</th>
                    <th style="white-space: nowrap;">PROYEK &amp; PLATFORM</th>
                    <th style="white-space: nowrap;">TAHAP 1: OWNER &rarr; TIM ADS</th>
                    <th style="white-space: nowrap;">TAHAP 2: TIM ADS &rarr; META/GOOGLE</th>
                    <th style="white-space: nowrap;">REALISASI SPEND &amp; SISA</th>
                    <th style="white-space: nowrap;">STATUS KAMPANYE</th>
                    <th class="th-actions" style="text-align: right; white-space: nowrap; min-width: 140px;">AKSI</th>
                  </tr>
                </thead>
                <tbody id="tbody-ads-topups">
                  <tr><td colspan="7" style="text-align: center; padding: 24px; color: var(--text-muted);">Memuat riwayat isi saldo ads...</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Projects Ads Budget Table Card (Owner & Admin) -->
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

          <!-- Coordination Logs & Screenshot Proof Feed (Owner & Admin) -->
          <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px; box-shadow: 0 1px 3px rgba(16,24,40,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
              <div>
                <h4 style="font-size: 15px; font-weight: 800; color: #101828; margin: 0;">Riwayat Update &amp; Bukti Screenshot Dashboard Ads</h4>
                <span style="font-size: 12px; color: var(--text-secondary);">Catatan koordinasi langsung antara Tim Ads dan Owner beserta screenshot dashboard</span>
              </div>
            </div>

            <div id="ads-logs-feed-container">
              <div style="text-align: center; padding: 28px; color: var(--text-muted);">Memuat riwayat koordinasi...</div>
            </div>
          </div>

          <?php else: ?>
          <!-- WORKER / FREELANCER (TIM ADS): APPIFY "MY WALLET" WORKSPACE -->
          <div class="appify-wallet-container" style="max-width: 100%; margin: 0 auto 24px;">
            <!-- 1. Bento Card: Saldo Diterima dari Owner -->
            <div class="bento-saldo-owner-card" style="background: #FFFFFF; border: 1px solid #F1F5F9; border-radius: 20px; padding: 16px 18px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(15,23,42,0.03); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
              <div>
                <div style="font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">
                  Saldo Diterima dari Owner
                </div>
                <div id="appify-hero-transferred" style="font-size: 24px; font-weight: 800; color: #0F172A; letter-spacing: -0.5px; line-height: 1.2;">
                  Rp <?= number_format($total_ditransfer_owner, 0, ',', '.') ?>
                </div>
              </div>
              <span id="appify-hero-status-pill" style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; background: <?= $sisa_saldo > 0 ? '#ECFDF5' : '#FEF2F2' ?>; color: <?= $sisa_saldo > 0 ? '#059669' : '#EF4444' ?>; border: 1px solid <?= $sisa_saldo > 0 ? '#A7F3D0' : '#FECACA' ?>;">
                <span style="width: 6px; height: 6px; border-radius: 50%; background: currentColor;"></span>
                <span id="appify-hero-status-text"><?= $sisa_saldo > 0 ? 'Iklan Jalan' : ($total_ditransfer_owner > 0 ? 'Saldo Habis' : 'Belum Ada Saldo') ?></span>
              </span>
            </div>

            <!-- 2. Kartu Ungu Bergradien (My Wallet Hero) -->
            <?php
              $heroSpendPct = $total_ditransfer_owner > 0 ? round(($total_dibelanjakan_ads / $total_ditransfer_owner) * 100) : 0;
              $heroSpendPctClamped = min(100, max(0, $heroSpendPct));
            ?>
            <div class="appify-hero-card" style="background: linear-gradient(135deg, #5B3DF5 0%, #7C5CFA 100%); border-radius: 20px; padding: 22px; color: #FFFFFF; box-shadow: 0 10px 25px rgba(91, 61, 245, 0.22); position: relative; overflow: hidden; margin-bottom: 18px;">
              <div style="position: relative; z-index: 2;">
                <div style="font-size: 13px; font-weight: 500; color: rgba(255,255,255,0.85); margin-bottom: 4px;">
                  Sisa Saldo Saat Ini
                </div>
                <div id="appify-hero-remaining" style="font-size: 24px; font-weight: 800; color: #FFFFFF; letter-spacing: -0.3px; margin-bottom: 14px;">
                  Rp <?= number_format($sisa_saldo, 0, ',', '.') ?>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 12.5px; color: rgba(255,255,255,0.9);">
                  <span>Sudah Dibelanjakan</span>
                  <span id="appify-hero-spend" style="font-weight: 700;">Spend: Rp <?= number_format($total_dibelanjakan_ads, 0, ',', '.') ?></span>
                </div>
                <!-- Progress bar putih transparan elegan -->
                <div style="height: 8px; background: rgba(255, 255, 255, 0.25); border-radius: 999px; overflow: hidden;">
                  <div id="appify-hero-progress" style="height: 100%; width: <?= $heroSpendPctClamped ?>%; background: #FFFFFF; border-radius: 999px; transition: width 0.4s ease;"></div>
                </div>
              </div>
              <div style="position: absolute; right: -20px; top: -20px; width: 130px; height: 130px; border-radius: 50%; background: rgba(255,255,255,0.08); pointer-events: none;"></div>
            </div>

            <!-- 3. Baris 3 Tombol Aksi Cepat Sejajar (Top-Up & Spend | Minta Saldo | Segarkan) -->
            <div class="appify-action-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 18px 0;">
              <!-- Tombol 1: Top-Up & Spend -->
              <button type="button" class="appify-action-btn" onclick="openConfirmPlatformTopupModal()" style="background: #FFFFFF; border-radius: 16px; padding: 12px 6px; height: auto; border: 1px solid #F0F1F7; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; cursor: pointer; transition: all 180ms ease; box-shadow: 0 4px 14px rgba(26,22,43,0.03);">
                <div class="appify-action-icon-circle" style="width: 38px !important; height: 38px !important; min-width: 38px !important; min-height: 38px !important; border-radius: 50% !important; flex-shrink: 0 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; background: #F2EFFF; color: #5B3DF5;">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="19" x2="12" y2="5"></line>
                    <polyline points="5 12 12 5 19 12"></polyline>
                  </svg>
                </div>
                <span style="font-size: 11.5px; font-weight: 600; color: #1A162B; text-align: center; line-height: 1.2;">Top-Up &amp; Spend</span>
              </button>

              <!-- Tombol 2: Minta Saldo -->
              <button type="button" class="appify-action-btn" onclick="openRequestBalanceWorkerModal()" style="background: #FFFFFF; border-radius: 16px; padding: 12px 6px; height: auto; border: 1px solid #F0F1F7; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; cursor: pointer; transition: all 180ms ease; box-shadow: 0 4px 14px rgba(26,22,43,0.03);">
                <div class="appify-action-icon-circle" style="width: 38px !important; height: 38px !important; min-width: 38px !important; min-height: 38px !important; border-radius: 50% !important; flex-shrink: 0 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; background: #FFF4E5; color: #F59E0B;">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                  </svg>
                </div>
                <span style="font-size: 11.5px; font-weight: 600; color: #1A162B; text-align: center; line-height: 1.2;">Minta Saldo</span>
              </button>

              <!-- Tombol 3: Segarkan -->
              <button type="button" class="appify-action-btn" onclick="loadAdsTrackerData()" style="background: #FFFFFF; border-radius: 16px; padding: 12px 6px; height: auto; border: 1px solid #F0F1F7; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; cursor: pointer; transition: all 180ms ease; box-shadow: 0 4px 14px rgba(26,22,43,0.03);">
                <div class="appify-action-icon-circle" style="width: 38px !important; height: 38px !important; min-width: 38px !important; min-height: 38px !important; border-radius: 50% !important; flex-shrink: 0 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; background: #EEF2FF; color: #3B82F6;">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <polyline points="1 20 1 14 7 14"></polyline>
                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                  </svg>
                </div>
                <span style="font-size: 11.5px; font-weight: 600; color: #1A162B; text-align: center; line-height: 1.2;">Segarkan</span>
              </button>
            </div>

            <!-- 4. Riwayat Transaksi & Bukti Belanja Iklan (Recent Transactions Card List) -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin: 22px 0 12px;">
              <h3 style="font-size: 15px; font-weight: 800; color: #1A162B; margin: 0; letter-spacing: -0.2px;">
                Riwayat Transaksi &amp; Bukti
              </h3>
              <span style="background: #E8F8F2; color: #10B981; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; border: 1px solid rgba(16,185,129,0.2);">
                Tampil di Portal Klien
              </span>
            </div>

            <div id="appify-tx-list-container">
              <div style="text-align: center; padding: 32px 16px; color: #7B7890; font-size: 13px; background: #FFFFFF; border-radius: 16px; border: 1px solid #F0F1F7;">
                Memuat riwayat transaksi ads...
              </div>
            </div>

            <!-- Hidden containers for JS compatibility -->
            <div style="display: none;">
              <div id="ads-kpi-container"></div>
              <table><tbody id="tbody-ads-topups"></tbody></table>
              <div id="ads-logs-feed-container"></div>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <!-- SECTION 3: Report & Rekap Pekerjaan (Bulanan / Per Proyek) -->
        <div id="section-recap-content" style="display: none;">
          <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 22px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(16,24,40,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px;">
              <div>
                <h3 style="font-size: 16px; font-weight: 800; color: #101828; margin: 0;">
                  Report &amp; Rekap Pekerjaan Saya (Bulanan &amp; Per Proyek)
                </h3>
                <p style="font-size: 12.5px; color: var(--text-secondary); margin: 4px 0 0;">
                  Ringkasan kuantitatif dan daftar seluruh output pekerjaan yang Anda kerjakan beserta status approval klien.
                </p>
              </div>

              <button type="button" class="btn btn-secondary btn-sm" onclick="exportWorkerRecapPrint()" style="font-weight: 700; border-radius: 8px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                <span>Cetak / Ekspor PDF Report</span>
              </button>
            </div>

            <!-- Filter Controls -->
            <div style="background: #F9FAFB; border: 1px solid #ECEEF5; border-radius: 12px; padding: 14px 16px; margin-bottom: 20px; display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
              <!-- Month Filter -->
              <div style="min-width: 150px;">
                <label style="font-size: 11px; font-weight: 700; color: #4B5563; text-transform: uppercase; display: block; margin-bottom: 4px;">Bulan</label>
                <select id="worker-recap-filter-month" class="form-select" onchange="loadWorkerRecapData()">
                  <option value="">Semua Periode</option>
                  <option value="<?= date('Y-m') ?>" selected>Bulan Ini (<?= date('M Y') ?>)</option>
                  <option value="<?= date('Y-m', strtotime('-1 month')) ?>">Bulan Lalu (<?= date('M Y', strtotime('-1 month')) ?>)</option>
                </select>
              </div>

              <!-- Project Filter -->
              <div style="min-width: 200px;">
                <label style="font-size: 11px; font-weight: 700; color: #4B5563; text-transform: uppercase; display: block; margin-bottom: 4px;">Klien / Proyek</label>
                <select id="worker-recap-filter-project" class="form-select" onchange="loadWorkerRecapData()">
                  <option value="">Semua Proyek PT</option>
                </select>
              </div>

              <!-- Service Type Filter -->
              <div style="min-width: 170px;">
                <label style="font-size: 11px; font-weight: 700; color: #4B5563; text-transform: uppercase; display: block; margin-bottom: 4px;">Jenis Layanan</label>
                <select id="worker-recap-filter-service" class="form-select" onchange="loadWorkerRecapData()">
                  <option value="all">Semua Layanan</option>
                  <option value="sosmed">Kelola Sosmed (Reguler)</option>
                  <option value="eceran">Desain Eceran / Satuan</option>
                  <option value="web">Jasa Buat Website</option>
                  <option value="ads">Jasa Iklan Meta &amp; Google</option>
                </select>
              </div>

              <div style="margin-top: 18px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="loadWorkerRecapData()">Segarkan</button>
              </div>
            </div>

            <!-- Dynamic 4 KPI Summary Cards -->
            <div class="kpi-grid" style="grid-template-columns: repeat(4, 1fr) !important; gap: 14px; margin-bottom: 22px;">
              <div class="kpi-card" style="border-top: 3px solid #2563EB;">
                <div class="kpi-header">
                  <span class="kpi-title" style="color: #2563EB; font-weight: 700;">Total Pekerjaan</span>
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
                </div>
                <div class="kpi-value" id="worker-recap-stat-total" style="font-size: 24px; color: #111827;">0</div>
                <div class="kpi-meta">Seluruh item kerjaan Anda</div>
              </div>

              <div class="kpi-card" style="border-top: 3px solid #059669;">
                <div class="kpi-header">
                  <span class="kpi-title" style="color: #059669; font-weight: 700;">Selesai Disetujui (Approved)</span>
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <div class="kpi-value" id="worker-recap-stat-approved" style="font-size: 24px; color: #059669;">0</div>
                <div class="kpi-meta">Disetujui klien tanpa revisi</div>
              </div>

              <div class="kpi-card" style="border-top: 3px solid #F59E0B;">
                <div class="kpi-header">
                  <span class="kpi-title" style="color: #D97706; font-weight: 700;">In Progress / Review</span>
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="kpi-value" id="worker-recap-stat-progress" style="font-size: 24px; color: #D97706;">0</div>
                <div class="kpi-meta">Dalam pengerjaan / review</div>
              </div>

              <div class="kpi-card" style="border-top: 3px solid #DC2626;">
                <div class="kpi-header">
                  <span class="kpi-title" style="color: #DC2626; font-weight: 700;">Revisi (Revision)</span>
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <div class="kpi-value" id="worker-recap-stat-revision" style="font-size: 24px; color: #DC2626;">0</div>
                <div class="kpi-meta">Catatan revisi dari klien</div>
              </div>
            </div>

            <!-- Detailed Table of Work Logs -->
            <div id="printable-worker-recap-table" class="kala-table-scroll table-responsive" style="width: 100%; overflow-x: auto;">
              <table class="table-custom" style="min-width: 900px; width: 100%;">
                <thead>
                  <tr>
                    <th>TANGGAL</th>
                    <th>KLIEN &amp; PROYEK</th>
                    <th>JUDUL &amp; RINCIAN PEKERJAAN</th>
                    <th>OUTPUT KATEGORI</th>
                    <th>LINK HASIL KERJA</th>
                    <th>STATUS &amp; APPROVAL KLIEN</th>
                  </tr>
                </thead>
                <tbody id="worker-recap-tasks-tbody">
                  <tr>
                    <td colspan="6" style="text-align: center; padding: 32px; color: var(--text-muted);">
                      Memuat data rekap pekerjaan...
                    </td>
                  </tr>
                </tbody>
              </table>
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
              <input type="text" name="spend_installed" id="ads-spend-amount" class="form-control" placeholder="Contoh: 5.000.000" required oninput="formatRupiahInput(this); enforceAllocBudgetLimit(this);">
              <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                Nominal akumulatif atau spend saat ini (otomatis dibatasi maksimal sesuai pagu yang dialokasikan).
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
                <input type="text" name="platform_topup_amount" id="confirm-platform-amount" class="form-control" placeholder="Contoh: 200.000" required oninput="formatRupiahInput(this); enforceOwnerTransferLimit(this);">
                <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                  Jumlah saldo yang disetor ke platform (otomatis dibatasi maksimal sesuai dana transfer Owner).
                </small>
              </div>

              <div class="form-group">
                <label class="form-label">Realisasi Belanja / Spend Saat Ini (Rp) <span style="color:red;">*</span></label>
                <input type="text" name="ad_spend_amount" id="confirm-spend-amount" class="form-control" placeholder="Contoh: 150.000" required oninput="formatRupiahInput(this); enforceOwnerTransferLimit(this);">
                <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 3px;">
                  Total nominal saldo yang telah terserap/terbelanjakan (otomatis dibatasi maksimal).
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
      const urlParams = new URLSearchParams(window.location.search);
      const reqTab = urlParams.get('tab');
      if (reqTab === 'recap') {
        switchTaskWorkspaceTab('recap');
      } else if (reqTab === 'ads' || reqTab === 'ads_tracker') {
        switchTaskWorkspaceTab('ads');
      } else {
        loadTasksData();
      }

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
          if (data.freelancer_summary) {
            renderFreelancerSaldo(data.freelancer_summary);
          }
          applyFilters();
          checkRevisionAlerts();
        } else {
          showToast(data.message || 'Gagal memuat daftar tugas', 'danger');
        }
      } catch (err) {
        showToast('Koneksi server gagal', 'danger');
      }
    }

    function renderFreelancerSaldo(fs) {
      if (!fs) return;
      const elPaid = document.getElementById('kpi-freelancer-paid');
      if (elPaid) elPaid.innerText = fs.formatted_paid || ('Rp ' + Number(fs.paid_amount || 0).toLocaleString('id-ID'));
      const elPend = document.getElementById('kpi-freelancer-pending');
      if (elPend) elPend.innerText = fs.formatted_pending || ('Rp ' + Number(fs.pending_amount || 0).toLocaleString('id-ID'));
      const elTot = document.getElementById('kpi-freelancer-total');
      if (elTot) elTot.innerText = fs.formatted_total || ('Rp ' + Number(fs.total_fee || 0).toLocaleString('id-ID'));
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

        let catIcon = '';
        let catTintBg = '#F2EFFF';
        let catTintCol = '#5B3DF5';
        const catLow = catLabel.toLowerCase();
        if (catLow.includes('iklan') || catLow.includes('ads')) {
          catTintBg = '#F2EFFF'; catTintCol = '#5B3DF5';
          catIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>';
        } else if (catLow.includes('sosmed') || catLow.includes('konten')) {
          catTintBg = '#E8F8F2'; catTintCol = '#10B981';
          catIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>';
        } else if (catLow.includes('desain') || catLow.includes('design') || catLow.includes('video')) {
          catTintBg = '#FFF4E5'; catTintCol = '#F59E0B';
          catIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polygon points="10 8 16 12 10 16 10 8"></polygon></svg>';
        } else if (catLow.includes('web')) {
          catTintBg = '#EEF2FF'; catTintCol = '#3B82F6';
          catIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>';
        } else {
          catTintBg = '#F2EFFF'; catTintCol = '#5B3DF5';
          catIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>';
        }

        html += `
          <div class="task-item-card ${cardExtraClass}" style="background: #FFFFFF; border-radius: 20px; border: 1px solid #F1F5F9; padding: 16px 16px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(15,23,42,0.03); display: flex; flex-direction: column; gap: 8px; width: 100%; max-width: 100%; box-sizing: border-box; overflow: hidden;">
            <!-- Top row: Task Title + Client & Deadline subtitle -->
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; width: 100%; max-width: 100%; box-sizing: border-box;">
              <div style="min-width: 0; flex: 1;">
                <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 4px; flex-wrap: wrap;">
                  <span class="status-pill ${sInfo.class}" style="border-radius: 999px; font-size: 10.5px; font-weight: 700; padding: 2px 8px;">
                    ${sInfo.label}
                  </span>
                  <span style="font-size: 10.5px; font-weight: 600; color: #64748B; background: #F1F5F9; padding: 2px 7px; border-radius: 6px;">
                    ${escapeHtml(catLabel)}
                  </span>
                </div>
                <h3 style="font-size: 14.5px; font-weight: 700; color: #0F172A; margin: 0 0 3px; line-height: 1.35; letter-spacing: -0.2px; word-break: break-word;">
                  ${escapeHtml(t.title)}
                </h3>
                <div style="font-size: 12px; color: #64748B; line-height: 1.3;">
                  <b>${escapeHtml(t.client_company || 'Klien')}</b> &bull; Deadline: <span style="color: #475569;">${t.due_date ? formatDate(t.due_date) : '-'}</span>
                </div>
              </div>

              <?php if ($isAdminUser): ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="deleteTask(${t.id})" style="padding: 3px 8px; font-size: 10.5px; font-weight: 600; color: #EF4444; border-color: #FEE2E2; background: #FEF2F2; border-radius: 8px; flex-shrink: 0;" title="Hapus Tugas">
                  Hapus
                </button>
              <?php endif; ?>
            </div>

            <!-- Middle row: Description text in muted slate -->
            ${t.description ? `
              <p style="font-size: 13px; color: #64748B; margin: 2px 0 6px; line-height: 1.45; word-break: break-word;">
                ${escapeHtml(t.description)}
              </p>
            ` : ''}

            ${(t.is_ads_task || parseFloat(t.assigned_ads_budget) > 0) ? `
              <!-- Real-Time Ads Monitoring Panel (5 Required Indicators) -->
              <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 12px; margin: 6px 0; width: 100%; box-sizing: border-box; overflow: hidden;">
                ${t.is_depleted ? `
                  <div style="background: #FFEFEF; border: 1px solid #FECDCA; border-radius: 8px; padding: 8px 12px; margin-bottom: 10px; color: #B42318; font-weight: 700; font-size: 12px; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 16px;">⚠️</span>
                    <span><b>SALDO IKLAN HABIS (Rp 0)!</b> Kampanye dihentikan sementara.</span>
                  </div>
                ` : (t.is_warning ? `
                  <div style="background: #FEF6E7; border: 1px solid #FEDF89; border-radius: 8px; padding: 8px 12px; margin-bottom: 10px; color: #B54708; font-weight: 700; font-size: 12px; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 16px;">⚠️</span>
                    <span><b>SALDO IKLAN HAMPIR HABIS (&le; 20%)!</b> Sisa saldo menipis.</span>
                  </div>
                ` : '')}

                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 10px;">
                  <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                    <span style="background: #EEF2FF; color: #4F46E5; font-weight: 700; font-size: 10.5px; padding: 2px 7px; border-radius: 6px;">
                      ${escapeHtml(t.ads_platform || 'Meta Ads')}
                    </span>

                    ${t.campaign_run_status === 'running' ? `
                      <span style="background: #ECFDF5; color: #059669; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; display: inline-flex; align-items: center; gap: 5px;">
                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #059669;"></span>
                        Iklan Jalan
                      </span>
                    ` : `
                      <span style="background: #FFFBEB; color: #D97706; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; display: inline-flex; align-items: center; gap: 5px;">
                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #D97706;"></span>
                        Dijeda
                      </span>
                    `}
                  </div>

                  <div style="display: flex; gap: 6px; align-items: center;">
                    ${t.transfer_proof_url ? `
                      <button type="button" class="btn btn-secondary btn-sm" onclick="openUniversalTransferProofModal('${escapeHtml(t.transfer_proof_url)}', 'Bukti Transfer Saldo Ads', '${escapeHtml(t.title)}')" style="font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px;">
                        Bukti TF
                      </button>
                    ` : ''}
                    <button type="button" class="btn btn-primary btn-sm" onclick='openUpdateAdsSpendModal(${JSON.stringify(t)})' style="font-size: 11px; font-weight: 600; background: #5B3DF5; border-color: #5B3DF5; padding: 3px 8px; border-radius: 6px;">
                      Update Ads
                    </button>
                  </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; text-align: center; margin-bottom: 8px;">
                  <div style="background: #FFFFFF; border: 1px solid #F1F5F9; border-radius: 8px; padding: 6px;">
                    <span style="font-size: 10px; color: #64748B; display: block;">Budget</span>
                    <span style="font-size: 12px; font-weight: 700; color: #0F172A;">${t.assigned_ads_budget_formatted}</span>
                  </div>
                  <div style="background: #FFFFFF; border: 1px solid #F1F5F9; border-radius: 8px; padding: 6px;">
                    <span style="font-size: 10px; color: #EF4444; display: block;">Spend (${t.spent_percent}%)</span>
                    <span style="font-size: 12px; font-weight: 700; color: #EF4444;">${t.ads_spent_amount_formatted}</span>
                  </div>
                  <div style="background: #FFFFFF; border: 1px solid #F1F5F9; border-radius: 8px; padding: 6px;">
                    <span style="font-size: 10px; color: #10B981; display: block;">Sisa</span>
                    <span style="font-size: 12px; font-weight: 700; color: #10B981;">${t.remaining_ads_balance_formatted}</span>
                  </div>
                </div>

                <div style="height: 6px; background: #E2E8F0; border-radius: 999px; overflow: hidden;">
                  <div style="height: 100%; width: ${Math.min(100, t.spent_percent)}%; background: ${t.spent_percent >= 100 ? '#EF4444' : (t.spent_percent >= 80 ? '#F59E0B' : '#10B981')}; border-radius: 999px;"></div>
                </div>
              </div>
            ` : ''}

            ${t.latest_feedback ? `
              <div style="font-size: 11.5px; color: #64748B; margin-bottom: 4px; background: #F8FAFC; border: 1px solid #F1F5F9; padding: 6px 10px; border-radius: 8px; display: flex; align-items: center; gap: 6px; width: 100%; max-width: 100%; box-sizing: border-box; overflow: hidden;">
                <span style="color: #6366F1; flex-shrink: 0;">💬</span>
                <span style="max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; box-sizing: border-box; flex: 1; min-width: 0;">"${escapeHtml(t.latest_feedback)}"</span>
              </div>
            ` : ''}

            <!-- Bottom action row: Grid 3 Kolom Proporsional yang Tidak Mungkin Jebol -->
            <div style="display: grid !important; grid-template-columns: repeat(3, minmax(0, 1fr)) !important; gap: 6px !important; width: 100% !important; box-sizing: border-box !important; padding-top: 10px; border-top: 1px solid #F1F5F9; align-items: center;">
              <!-- Tombol 1: Preview -->
              ${t.deliverable_link ? `
                <a href="${escapeHtml(t.deliverable_link)}" target="_blank" class="btn btn-secondary btn-sm" style="height: 34px; padding: 0 6px; border-radius: 9px; font-size: 12px; font-weight: 600; white-space: nowrap; display: inline-flex; align-items: center; justify-content: center; gap: 4px; border: 1px solid #E2E8F0; background: #FFFFFF; color: #334155; text-decoration: none; width: 100%; min-width: 0; box-sizing: border-box; overflow: hidden; text-overflow: ellipsis;" title="Buka Link Preview Hasil">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                  <span style="overflow: hidden; text-overflow: ellipsis;">Preview</span>
                </a>
              ` : `
                <span style="height: 34px; padding: 0 6px; border-radius: 9px; font-size: 11.5px; font-weight: 500; white-space: nowrap; display: inline-flex; align-items: center; justify-content: center; gap: 3px; border: 1px dashed #CBD5E1; color: #94A3B8; background: #F8FAFC; width: 100%; min-width: 0; box-sizing: border-box; overflow: hidden; text-overflow: ellipsis;" title="Link belum diset">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.6; flex-shrink: 0;"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
                  <span style="overflow: hidden; text-overflow: ellipsis;">No Link</span>
                </span>
              `}

              <!-- Tombol 2: Chat Diskusi (X) -->
              <button type="button" class="btn btn-secondary btn-sm" onclick="KalaRoomChat.openRoom(${t.id})" style="height: 34px; padding: 0 6px; border-radius: 9px; font-size: 12px; font-weight: 600; white-space: nowrap; display: inline-flex; align-items: center; justify-content: center; gap: 4px; border: 1px solid #E2E8F0; color: #4F46E5; background: #FFFFFF; width: 100%; min-width: 0; box-sizing: border-box; overflow: hidden; text-overflow: ellipsis;" title="Diskusi Tugas">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                <span style="overflow: hidden; text-overflow: ellipsis;">Chat (${unreadFeedbacks > 0 ? `<b style="color: #EF4444;">${unreadFeedbacks}</b>` : (t.feedbacks_count || 0)})</span>
              </button>

              <!-- Tombol 3: Update Progres -->
              <button type="button" class="btn btn-primary btn-sm" onclick="openUpdateProgressModal(${t.id}, '${t.status}', '${escapeHtml(t.deliverable_link || '')}')" style="height: 34px; padding: 0 6px; border-radius: 9px; font-size: 12px; font-weight: 600; white-space: nowrap; display: inline-flex; align-items: center; justify-content: center; gap: 4px; background: #5B3DF5; border: 1px solid #5B3DF5; color: #FFFFFF; width: 100%; min-width: 0; box-sizing: border-box; overflow: hidden; text-overflow: ellipsis; box-shadow: 0 1px 2px rgba(91, 61, 245, 0.2);" title="Perbarui Status & Progres">
                <span style="overflow: hidden; text-overflow: ellipsis;">Update</span>
              </button>
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
        if (!inputEl) return;
        const valStr = inputEl.value || '0';
        let spent = parseFloat(valStr.replace(/[^0-9]/g, '')) || 0;

        // Auto-limit to previously inputted maximum budget
        if (currentEditingAdsBudget > 0 && spent > currentEditingAdsBudget) {
          spent = currentEditingAdsBudget;
          inputEl.value = Math.round(spent).toLocaleString('id-ID');
          showToast(`Nominal otomatis dibatasi maksimal Rp ${Math.round(currentEditingAdsBudget).toLocaleString('id-ID')} sesuai budget tugas`, 'warning');
        }

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
    let allLoadedRecapData = null;

    function switchTaskWorkspaceTab(tab) {
      const btnTasks = document.getElementById('tab-btn-tasks');
      const btnAds = document.getElementById('tab-btn-ads');
      const btnRecap = document.getElementById('tab-btn-recap');
      const secTasks = document.getElementById('section-tasks-content');
      const secAds = document.getElementById('section-ads-content');
      const secRecap = document.getElementById('section-recap-content');

      // Reset active states
      btnTasks.classList.remove('active');
      btnAds.classList.remove('active');
      if (btnRecap) btnRecap.classList.remove('active');
      secTasks.style.display = 'none';
      secAds.style.display = 'none';
      if (secRecap) secRecap.style.display = 'none';

      // Sync mobile bottom navigation bar active state
      const mobNav = document.getElementById('mobile-bottom-nav');
      if (mobNav) {
        mobNav.querySelectorAll('.mob-nav-item').forEach(el => el.classList.remove('active'));
        if (tab === 'ads') {
          const adsNav = mobNav.querySelector('a[href*="tab=ads"]') || mobNav.querySelector('a[href*="ads_tracker"]');
          if (adsNav) adsNav.classList.add('active');
        } else if (tab === 'recap') {
          const recapNav = mobNav.querySelector('a[href*="tab=recap"]');
          if (recapNav) recapNav.classList.add('active');
        } else {
          const taskNav = mobNav.querySelector('a[href*="tasks"]') || mobNav.querySelector('a[href$="tasks"]');
          if (taskNav) taskNav.classList.add('active');
        }
      }

      if (tab === 'ads') {
        btnAds.classList.add('active');
        secAds.style.display = 'block';
        loadAdsTrackerData();
      } else if (tab === 'recap') {
        if (btnRecap) btnRecap.classList.add('active');
        if (secRecap) secRecap.style.display = 'block';
        loadWorkerRecapData();
      } else {
        btnTasks.classList.add('active');
        secTasks.style.display = 'block';
      }
    }

    async function loadWorkerRecapData() {
      const month = document.getElementById('worker-recap-filter-month') ? document.getElementById('worker-recap-filter-month').value : '';
      const project = document.getElementById('worker-recap-filter-project') ? document.getElementById('worker-recap-filter-project').value : '';
      const service = document.getElementById('worker-recap-filter-service') ? document.getElementById('worker-recap-filter-service').value : 'all';

      try {
        const uid = '<?= $userId ?>';
        const res = await fetch(`api/portal_tasks.php?action=work_recap&month=${encodeURIComponent(month)}&project_id=${project}&service_type=${encodeURIComponent(service)}&_u=u_${uid}`);
        const data = await res.json();
        if (!data.success) {
          showToast(data.message || 'Gagal memuat rekap pekerjaan', 'danger');
          return;
        }

        allLoadedRecapData = data;

        // Render 4 KPI Cards
        document.getElementById('worker-recap-stat-total').innerText = data.summary.total_tasks || 0;
        document.getElementById('worker-recap-stat-approved').innerText = data.summary.approved_tasks || 0;
        document.getElementById('worker-recap-stat-progress').innerText = data.summary.in_progress_tasks || 0;
        document.getElementById('worker-recap-stat-revision').innerText = data.summary.revision_tasks || 0;

        // Populate Projects Dropdown if empty
        const pSelect = document.getElementById('worker-recap-filter-project');
        if (pSelect && pSelect.options.length <= 1 && data.filters.projects) {
          data.filters.projects.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = `${p.client_company} • ${p.name}`;
            pSelect.appendChild(opt);
          });
        }

        // Render Table Rows
        const tbody = document.getElementById('worker-recap-tasks-tbody');
        tbody.innerHTML = '';

        if (!data.tasks || data.tasks.length === 0) {
          tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 32px; color: var(--text-muted);">Tidak ada pekerjaan pada filter yang dipilih.</td></tr>`;
          return;
        }

        data.tasks.forEach(t => {
          const tr = document.createElement('tr');
          const statusBg = (t.status === 'approved') ? '#ECFDF5' : ((t.status === 'revision') ? '#FEF2F2' : '#EFF6FF');
          const statusColor = (t.status === 'approved') ? '#047857' : ((t.status === 'revision') ? '#B91C1C' : '#1D4ED8');
          const serviceBadgeBg = t.is_eceran ? '#FEF3C7' : '#F1F5F9';
          const serviceBadgeColor = t.is_eceran ? '#92400E' : '#334155';

          tr.innerHTML = `
            <td style="white-space: nowrap;">
              <div style="font-weight: 700; color: #111827; font-size: 12px;">${t.due_date ? t.due_date : (t.created_at ? t.created_at.substring(0, 10) : '-')}</div>
              <div style="font-size: 10.5px; color: #9CA3AF;">Mulai: ${t.start_date || '-'}</div>
            </td>
            <td>
              <div style="font-weight: 700; color: #111827; font-size: 12.5px;">${escapeHtml(t.client_company || t.client_name)}</div>
              <div style="font-size: 11px; color: #6B7280;">${escapeHtml(t.project_name)}</div>
              <span style="display: inline-block; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; background: ${serviceBadgeBg}; color: ${serviceBadgeColor}; margin-top: 2px;">
                ${escapeHtml(t.service_badge)}
              </span>
            </td>
            <td>
              <div style="font-weight: 700; color: #111827; font-size: 13px;">${escapeHtml(t.title)}</div>
              ${t.description ? `<div style="font-size: 11.5px; color: #6B7280; margin-top: 2px; max-width: 280px;">${escapeHtml(t.description)}</div>` : ''}
              ${t.latest_feedback_message ? `<div style="font-size: 11px; color: #D97706; margin-top: 4px; background: #FFFBEB; padding: 3px 6px; border-radius: 4px;">💬 Masukan Klien: "${escapeHtml(t.latest_feedback_message)}"</div>` : ''}
            </td>
            <td>
              <span style="font-size: 11px; font-weight: 700; background: #F3F4F6; color: #374151; padding: 3px 8px; border-radius: 6px;">
                ${escapeHtml(t.category_label)}
              </span>
            </td>
            <td>
              ${t.deliverable_link ? `<a href="${escapeHtml(t.deliverable_link)}" target="_blank" style="font-size: 11.5px; font-weight: 700; color: #2563EB; text-decoration: underline; display: inline-flex; align-items: center; gap: 4px;">🔗 Preview File</a>` : '<span style="font-size: 11px; color: #9CA3AF;">Belum ada link</span>'}
            </td>
            <td>
              <span style="display: inline-block; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 6px; background: ${statusBg}; color: ${statusColor};">
                ${escapeHtml(t.status_label)}
              </span>
            </td>
          `;
          tbody.appendChild(tr);
        });
      } catch (err) {
        console.error(err);
        showToast('Koneksi server gagal memuat rekap pekerjaan', 'danger');
      }
    }

    function exportWorkerRecapPrint() {
      window.print();
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

    function parseAdsNumber(val) {
      if (val === null || val === undefined || val === '') return 0;
      if (typeof val === 'number') return val;
      let str = String(val).trim().replace(/[^0-9,.-]/g, '');
      if (str.indexOf('.') !== -1 && str.indexOf(',') !== -1) {
        str = str.replace(/\./g, '').replace(',', '.');
      } else if ((str.match(/\./g) || []).length > 1) {
        str = str.replace(/\./g, '');
      } else if ((str.match(/\./g) || []).length === 1) {
        const parts = str.split('.');
        if (parts[1] && parts[1].length === 3) {
          str = parts[0] + parts[1];
        }
      }
      return parseFloat(str) || 0;
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
            const amount = parseAdsNumber(r.amount_requested);
            const formattedAmount = (amount > 0) ? 'Rp ' + amount.toLocaleString('id-ID') : 'Rp 0';
            reqHtml += `
              <div style="background: #FFF9F2; border: 1.5px solid #FDB022; border-radius: 10px; padding: 14px 18px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; box-shadow: 0 2px 4px rgba(245, 158, 11, 0.08);">
                <div style="display: flex; align-items: flex-start; gap: 12px;">
                  <span style="font-size: 26px; line-height: 1;">🔔</span>
                  <div>
                    <div style="font-weight: 800; font-size: 14px; color: #B54708;">
                      Permintaan Pencairan Saldo dari Tim Ads (${escapeHtml(r.requester_name || 'Tim Ads')})
                    </div>
                    <div style="font-size: 13px; color: #475467; margin-top: 2px;">
                      Proyek: <b>${escapeHtml(r.client_company || '')} - ${escapeHtml(r.project_name || '')}</b> &bull; Kebutuhan: <b style="color: #B42318; font-size: 14px;">${formattedAmount}</b>
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
        const lowBalanceProjects = projects.filter(p => {
          const pagu = parseAdsNumber(p.total_pagu_ads_klien || p.allocated_ads_budget || 0);
          const transferred = parseAdsNumber(p.total_ditransfer_ke_tim_ads || 0);
          const sisa = parseAdsNumber(p.sisa_saldo_di_tim_ads || 0);
          return (pagu > 0 || transferred > 0) && sisa <= 0;
        });
        if (lowBalanceProjects.length > 0) {
          let alerts = '';
          lowBalanceProjects.forEach(p => {
            const hasPending = p.has_pending_request;
            const sisa = parseAdsNumber(p.sisa_saldo_di_tim_ads || 0);
            const formattedSisa = (sisa > 0) ? 'Rp ' + sisa.toLocaleString('id-ID') : 'Rp 0';
            alerts += `
              <div style="background: #FEF3F2; border: 1px solid #FECDCA; border-radius: 8px; padding: 12px 16px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                  <span style="font-size: 20px;">⚠️</span>
                  <div style="font-size: 13px; color: #B42318; font-weight: 700;">
                    Saldo Iklan untuk [${escapeHtml(p.client_company || '')}] ${escapeHtml(p.project_name || '')} Habis (${formattedSisa})!
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
                    <button type="button" class="btn btn-primary btn-sm" onclick="openRequestBalanceWorkerModal(${p.project_id || p.id})" style="background: #B42318; border-color: #B42318; font-weight: 700; font-size: 11px;">
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
      // KARYAWAN / FREELANCER (TIM ADS) VIEW: APPIFY HERO & TILES
      const s = data.summary || {};
      const transferred = Number(s.total_ditransfer_owner !== undefined ? s.total_ditransfer_owner : (s.total_transferred_from_owner || <?= (float)$total_ditransfer_owner ?>));
      const spend = Number(s.total_dibelanjakan_ads !== undefined ? s.total_dibelanjakan_ads : (s.total_spend_terpakai || <?= (float)$total_dibelanjakan_ads ?>));
      const sisa = transferred - spend;
      const isRunning = (sisa > 0);
      const statusText = isRunning ? 'Iklan Jalan' : (transferred > 0 ? 'Saldo Habis' : 'Belum Ada Saldo');

      // Update Appify Hero elements
      const elTrans = document.getElementById('appify-hero-transferred');
      if (elTrans) elTrans.innerText = 'Rp ' + transferred.toLocaleString('id-ID');

      const elRem = document.getElementById('appify-hero-remaining');
      if (elRem) elRem.innerText = 'Rp ' + sisa.toLocaleString('id-ID');

      const elSpend = document.getElementById('appify-hero-spend');
      if (elSpend) elSpend.innerText = 'Spend: Rp ' + spend.toLocaleString('id-ID');

      const elProg = document.getElementById('appify-hero-progress');
      if (elProg) {
        const pct = transferred > 0 ? Math.min(100, Math.max(0, Math.round((spend / transferred) * 100))) : 0;
        elProg.style.width = pct + '%';
      }

      const elStatusPill = document.getElementById('appify-hero-status-pill');
      const elStatusText = document.getElementById('appify-hero-status-text');
      if (elStatusText) elStatusText.innerText = statusText;
      if (elStatusPill) {
        if (isRunning) {
          elStatusPill.style.background = '#E8F8F2';
          elStatusPill.style.color = '#10B981';
          elStatusPill.style.borderColor = 'rgba(16,185,129,0.2)';
        } else {
          elStatusPill.style.background = '#FFEFEF';
          elStatusPill.style.color = '#EF4444';
          elStatusPill.style.borderColor = 'rgba(239,68,68,0.2)';
        }
      }

      if (container) {
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
      }
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
      const appifyEl = document.getElementById('appify-tx-list-container');
      const isPrivileged = <?= json_encode($isOwnerOrAdmin) ?> && (data.role === 'owner' || data.role === 'admin');
      const isOwner = (data.role === 'owner');
      const topups = data.topup_transactions || [];

      if (topups.length === 0) {
        if (tbody) {
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
        }
        if (appifyEl) {
          appifyEl.innerHTML = `
            <div style="text-align: center; padding: 36px 16px; background: #FFFFFF; border-radius: 16px; border: 1px solid #F0F1F7; color: #7B7890;">
              <div style="font-size: 14px; font-weight: 700; color: #1A162B; margin-bottom: 4px;">Belum Ada Riwayat Transaksi</div>
              <div style="font-size: 12px; color: #7B7890;">Owner belum mencairkan saldo iklan untuk tugas/proyek Anda.</div>
            </div>
          `;
        }
        return;
      }

      let html = '';
      let appifyCardsHtml = '';
      topups.forEach(t => {
        const isVerified = (t.status === 'verified');
        const transferOwner = Number(t.transfer_amount_owner || t.topup_amount || 0);
        const topupPlatform = Number(t.platform_topup_amount || t.topup_amount || 0);
        const spend = Number(t.ad_spend_amount || t.spend_installed || 0);
        const sisa = transferOwner - spend;

        const isPlatformToppedUp = (t.platform_topup_status === 'completed' || topupPlatform > 0);

        // Appify Worker Card Item (Recent Transactions style)
        const pInitial = (t.platform || 'Meta Ads').toLowerCase().includes('google') ? 'G' : ((t.platform || 'Meta Ads').toLowerCase().includes('tiktok') ? 'TT' : 'M');
        appifyCardsHtml += `
          <div class="appify-tx-card" style="display: flex; flex-direction: column; padding: 14px 16px; background: #FFFFFF; border-radius: 16px; border: 1px solid #F0F1F7; margin-bottom: 10px; box-shadow: 0 2px 10px rgba(26, 22, 43, 0.02); gap: 8px; transition: all 180ms ease;">
            <!-- Baris Atas: Info Klien + Nominal Saldo -->
            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; width: 100%;">
              <div style="display: flex; align-items: center; gap: 12px; min-width: 0; flex: 1;">
                <div style="width: 40px; height: 40px; min-width: 40px; border-radius: 12px; background: #F2EFFF; color: #5B3DF5; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 800; font-size: 13px;">
                  ${pInitial}
                </div>
                <div style="min-width: 0; flex: 1;">
                  <div style="font-size: 13.5px; font-weight: 800; color: #1A162B; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    ${escapeHtml(t.client_company || 'Klien')} &bull; ${escapeHtml(t.project_name || 'Proyek')}
                  </div>
                  <div style="font-size: 11.5px; color: #7B7890; margin-top: 2px;">
                    ${escapeHtml(t.platform || 'Meta Ads')} &bull; ${escapeHtml(t.stage_label || 'Tahap 1')} &bull; ${formatDate(t.transaction_date)}
                  </div>
                </div>
              </div>

              <div style="text-align: right; flex-shrink: 0;">
                <div style="font-size: 13.5px; font-weight: 800; color: #10B981; line-height: 1.3;">
                  +Rp ${transferOwner.toLocaleString('id-ID')}
                </div>
                <div style="font-size: 12px; font-weight: 600; color: #1A162B; margin-top: 2px;">
                  -Rp ${spend.toLocaleString('id-ID')}
                </div>
              </div>
            </div>

            <!-- Baris Bawah: Ke-3 Tombol Berjejer Rapi ke Samping (1 Baris) -->
            <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; padding-top: 8px; border-top: 1px solid #F4F5F9; align-items: center;">
              ${t.transfer_proof_owner ? `
                <button type="button" onclick="previewAdsProof('${escapeHtml(t.transfer_proof_owner)}')" style="height: 28px; padding: 0 10px; font-size: 11.5px; border-radius: 8px; font-weight: 600; border: 1px solid #E0DBFD; background: #F2EFFF; color: #5B3DF5; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                  <span>Bukti TF Owner</span>
                </button>
              ` : ''}
              ${t.platform_topup_proof ? `
                <button type="button" onclick="previewAdsProof('${escapeHtml(t.platform_topup_proof)}')" style="height: 28px; padding: 0 10px; font-size: 11.5px; border-radius: 8px; font-weight: 600; border: 1px solid #C4F1DE; background: #E8F8F2; color: #10B981; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                  <span>Bukti Ads</span>
                </button>
              ` : ''}
              <button type="button" onclick="openConfirmPlatformTopupModal(${t.id}, ${transferOwner})" style="height: 28px; padding: 0 10px; font-size: 11.5px; border-radius: 8px; font-weight: 600; border: 1px solid #E5E7EB; background: #FFFFFF; color: #1A162B; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                <span>Update Spend</span>
              </button>
            </div>
          </div>
        `;

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
          // KARYAWAN / FREELANCER (TIM ADS) TABLE (Desktop Fallback)
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

      if (tbody) tbody.innerHTML = html;
      if (appifyEl) appifyEl.innerHTML = appifyCardsHtml;
    }

    function renderAdsLogsFeed(data) {
      const container = document.getElementById('ads-logs-feed-container');
      if (!container) return;

      const topups = data.topup_transactions || [];
      const logs = topups.filter(t => t.notes || t.platform_topup_proof || t.transfer_proof_owner || (Number(t.ad_spend_amount || 0) > 0));

      if (logs.length === 0) {
        container.innerHTML = `
          <div style="text-align: center; padding: 32px 20px; color: var(--text-muted); background: #FCFCFD; border-radius: 8px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 6px; display: block;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <div style="font-weight: 600; color: #111827; font-size: 13.5px;">Belum Ada Riwayat Update atau Screenshot</div>
            <div style="font-size: 12px; color: #6B7280; margin-top: 2px;">
              Setiap kali Tim Ads atau Owner mengunggah bukti transfer, top-up platform, atau memperbarui spend iklan, riwayatnya akan otomatis tercatat di sini.
            </div>
          </div>
        `;
        return;
      }

      let html = '<div style="display: flex; flex-direction: column; gap: 12px;">';
      logs.forEach(item => {
        const dateStr = formatDate(item.transaction_date || item.created_at);
        const spend = Number(item.ad_spend_amount || item.spend_installed || 0);
        const tfOwner = Number(item.transfer_amount_owner || item.topup_amount || 0);

        html += `
          <div style="background: #FAFAFA; border: 1px solid #EAECF0; border-radius: 10px; padding: 14px 16px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
            <div style="flex: 1; min-width: 260px;">
              <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <span style="font-weight: 800; font-size: 13.5px; color: #101828;">${escapeHtml(item.client_company || 'Klien')} &bull; ${escapeHtml(item.project_name || 'Proyek')}</span>
                <span class="badge" style="background: #F4F3FF; color: #5925DC; font-weight: 800; font-size: 10px;">${escapeHtml(item.platform || 'Meta Ads')}</span>
                <span class="badge" style="background: #F2F4F7; color: #344054; font-size: 10px;">${escapeHtml(item.stage_label || 'Tahap 1')}</span>
              </div>
              <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 3px;">
                Tanggal: <b>${dateStr}</b> &bull; Dicatat oleh: <b>${escapeHtml(item.assigned_user_name || item.uploader_name || 'Tim Ads')}</b>
              </div>
              ${item.notes ? `
                <div style="margin-top: 8px; font-size: 12.5px; color: #344054; background: #FFFFFF; border: 1px solid #E4E7EC; border-radius: 6px; padding: 8px 12px; line-height: 1.4;">
                  ${escapeHtml(item.notes)}
                </div>
              ` : ''}
              <div style="margin-top: 8px; display: flex; gap: 12px; font-size: 12px; color: #475467; flex-wrap: wrap;">
                ${tfOwner > 0 ? `<span>Saldo Masuk: <b style="color: #175CD3;">Rp ${tfOwner.toLocaleString('id-ID')}</b></span>` : ''}
                ${spend > 0 ? `<span>Realisasi Spend: <b style="color: #027A48;">Rp ${spend.toLocaleString('id-ID')}</b></span>` : ''}
              </div>
            </div>

            <div style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center;">
              ${item.transfer_proof_owner ? `
                <button type="button" class="btn btn-secondary btn-sm" onclick="previewAdsProof('${escapeHtml(item.transfer_proof_owner)}')" style="font-size: 11px; font-weight: 700; color: #175CD3; border-color: #B2CCFF; background: #EFF8FF; display: inline-flex; align-items: center; gap: 4px;">
                  📄 Bukti TF Owner
                </button>
              ` : ''}
              ${item.platform_topup_proof ? `
                <button type="button" class="btn btn-secondary btn-sm" onclick="previewAdsProof('${escapeHtml(item.platform_topup_proof)}')" style="font-size: 11px; font-weight: 700; color: #027A48; border-color: #A6F4C5; background: #ECFDF3; display: inline-flex; align-items: center; gap: 4px;">
                  📈 Bukti Dashboard Ads
                </button>
              ` : ''}
            </div>
          </div>
        `;
      });
      html += '</div>';

      container.innerHTML = html;
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

    let currentSelectedAllocBudget = 0;
    function onAdsProjectSelectChange(selectElem) {
      const selectedOption = selectElem.options[selectElem.selectedIndex];
      const box = document.getElementById('box-allocated-info');
      const txt = document.getElementById('txt-allocated-budget');
      if (!box || !txt) return;

      const alloc = Number(selectedOption ? selectedOption.getAttribute('data-allocated') || 0 : 0);
      currentSelectedAllocBudget = alloc;
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

    function enforceAllocBudgetLimit(inputElem) {
      if (!inputElem) return;
      let val = parseFloat(inputElem.value.replace(/[^0-9]/g, '')) || 0;
      if (currentSelectedAllocBudget > 0 && val > currentSelectedAllocBudget) {
        val = currentSelectedAllocBudget;
        inputElem.value = Math.round(val).toLocaleString('id-ID');
        showToast(`Nominal otomatis dibatasi maksimal Rp ${Math.round(currentSelectedAllocBudget).toLocaleString('id-ID')} sesuai pagu yang dialokasikan`, 'warning');
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
    let currentOwnerTransferAmount = 0;
    function openConfirmPlatformTopupModal(transId = null, ownerTransferAmount = 0, projectId = null) {
      const form = document.getElementById('form-confirm-platform-topup');
      if (form) form.reset();

      currentOwnerTransferAmount = parseFloat(ownerTransferAmount) || 0;
      document.getElementById('confirm-topup-trans-id').value = transId || '';
      document.getElementById('txt-confirm-owner-transfer').innerText = 'Rp ' + Number(currentOwnerTransferAmount || 0).toLocaleString('id-ID');

      if (allLoadedAdsData && allLoadedAdsData.topup_transactions) {
        const t = allLoadedAdsData.topup_transactions.find(x => String(x.id) === String(transId));
        if (t) {
          const transOwnerAmt = parseFloat(t.transfer_amount_owner > 0 ? t.transfer_amount_owner : (t.topup_amount > 0 ? t.topup_amount : 0));
          if (transOwnerAmt > 0) {
            currentOwnerTransferAmount = transOwnerAmt;
          }
          document.getElementById('txt-confirm-owner-transfer').innerText = 'Rp ' + Number(currentOwnerTransferAmount || 0).toLocaleString('id-ID');
          document.getElementById('txt-confirm-topup-proj-info').innerText = `Proyek: [${t.client_company}] ${t.project_name} (${t.platform})`;
          if (t.platform_topup_amount > 0) {
            document.getElementById('confirm-platform-amount').value = Number(t.platform_topup_amount).toLocaleString('id-ID');
          } else if (currentOwnerTransferAmount > 0) {
            document.getElementById('confirm-platform-amount').value = Number(currentOwnerTransferAmount).toLocaleString('id-ID');
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

    function enforceOwnerTransferLimit(inputElem) {
      if (!inputElem) return;
      let val = parseFloat(inputElem.value.replace(/[^0-9]/g, '')) || 0;
      if (currentOwnerTransferAmount > 0 && val > currentOwnerTransferAmount) {
        val = currentOwnerTransferAmount;
        inputElem.value = Math.round(val).toLocaleString('id-ID');
        showToast(`Nominal otomatis dibatasi maksimal Rp ${Math.round(currentOwnerTransferAmount).toLocaleString('id-ID')} sesuai dana yang ditransfer Owner`, 'warning');
      }
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
      if (!url) return;
      let finalUrl = String(url).trim();
      if (!finalUrl.startsWith('http://') && !finalUrl.startsWith('https://') && !finalUrl.startsWith('/') && !finalUrl.startsWith('assets/')) {
        if (finalUrl.startsWith('uploads/')) {
          finalUrl = 'assets/uploads/receipts/' + finalUrl.replace(/^uploads\/(receipts\/)?/, '');
        } else {
          finalUrl = 'assets/uploads/receipts/' + finalUrl;
        }
      }

      const img = document.getElementById('img-proof-preview');
      const iframe = document.getElementById('iframe-proof-preview');

      if (finalUrl.toLowerCase().endsWith('.pdf')) {
        img.style.display = 'none';
        iframe.src = finalUrl;
        iframe.style.display = 'block';
      } else {
        iframe.style.display = 'none';
        img.src = finalUrl;
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

  <?php require_once BASE_PATH . '/includes/bottom_nav.php'; ?>
  <?php require_once BASE_PATH . '/includes/modals.php'; ?>
  <?php require_once BASE_PATH . '/includes/room_chat_modal.php'; ?>
  <script src="assets/js/app.js?v=<?= time() ?>"></script>
  <script src="assets/js/invoice.js?v=<?= time() ?>"></script>
</body>
</html>

<?php
/**
 * Analytics API Handler for Executive & Operational Dashboards
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_auth();

$db = Database::getConnection();
$range = $_GET['range'] ?? 'month'; // 'month', 'quarter', 'year', 'all'

// Build date filtering WHERE clause
$dateConditionInv = "1=1";
$dateConditionPayout = "1=1";
$dateConditionAds = "1=1";
$dateConditionSalary = "1=1";

if ($range === 'month') {
    $currentMonth = date('Y-m');
    $dateConditionInv = "strftime('%Y-%m', paid_at) = '$currentMonth' OR (paid_at IS NULL AND strftime('%Y-%m', issue_date) = '$currentMonth')";
    $dateConditionPayout = "strftime('%Y-%m', paid_at) = '$currentMonth' OR (paid_at IS NULL AND strftime('%Y-%m', created_at) = '$currentMonth')";
    $dateConditionAds = "strftime('%Y-%m', spent_date) = '$currentMonth'";
    $dateConditionSalary = "strftime('%Y-%m', paid_at) = '$currentMonth' OR (paid_at IS NULL AND month_period = '$currentMonth')";
} elseif ($range === 'quarter') {
    $currentYear = date('Y');
    $currentQ = ceil(intval(date('n')) / 3);
    $startMonth = str_pad(($currentQ - 1) * 3 + 1, 2, '0', STR_PAD_LEFT);
    $endMonth = str_pad($currentQ * 3, 2, '0', STR_PAD_LEFT);
    $startDate = "$currentYear-$startMonth-01";
    $endDate = "$currentYear-$endMonth-31";

    $dateConditionInv = "date(COALESCE(paid_at, issue_date)) BETWEEN '$startDate' AND '$endDate'";
    $dateConditionPayout = "date(COALESCE(paid_at, created_at)) BETWEEN '$startDate' AND '$endDate'";
    $dateConditionAds = "date(spent_date) BETWEEN '$startDate' AND '$endDate'";
    $dateConditionSalary = "date(COALESCE(paid_at, payment_date)) BETWEEN '$startDate' AND '$endDate'";
} elseif ($range === 'year') {
    $currentYear = date('Y');
    $dateConditionInv = "strftime('%Y', COALESCE(paid_at, issue_date)) = '$currentYear'";
    $dateConditionPayout = "strftime('%Y', COALESCE(paid_at, created_at)) = '$currentYear'";
    $dateConditionAds = "strftime('%Y', spent_date) = '$currentYear'";
    $dateConditionSalary = "strftime('%Y', COALESCE(paid_at, payment_date)) = '$currentYear'";
}

$action = $_GET['action'] ?? '';

// 0. Project Financial Breakdown Detail API for Modal Popup
if ($action === 'project_financial_breakdown') {
    $projectId = intval($_GET['project_id'] ?? 0);
    $clientId = intval($_GET['client_id'] ?? 0);

    $project = null;
    if ($projectId > 0) {
        $stmt = $db->prepare("
            SELECT p.*, c.id as client_id, c.name as client_pic, c.company as client_company, c.email as client_email, c.phone as client_phone
            FROM projects p
            JOIN clients c ON p.client_id = c.id
            WHERE p.id = ?
        ");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();
    } elseif ($clientId > 0) {
        $stmt = $db->prepare("
            SELECT p.*, c.id as client_id, c.name as client_pic, c.company as client_company, c.email as client_email, c.phone as client_phone
            FROM clients c
            LEFT JOIN projects p ON p.client_id = c.id
            WHERE c.id = ?
            ORDER BY p.id DESC LIMIT 1
        ");
        $stmt->execute([$clientId]);
        $project = $stmt->fetch();
        if ($project && !empty($project['id'])) {
            $projectId = intval($project['id']);
        }
    }

    if (!$project) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Data Proyek atau Klien tidak ditemukan']);
        exit;
    }

    $cId = intval($project['client_id']);
    $pId = intval($project['id'] ?? 0);

    // 1. Invoices
    $invStmt = $db->prepare("
        SELECT id, invoice_number, total_amount, status, issue_date, due_date, paid_at, receipt_file, notes
        FROM invoices
        WHERE (project_id = ? OR (client_id = ? AND (project_id IS NULL OR project_id = 0))) AND COALESCE(is_deleted, 0) = 0
        ORDER BY issue_date DESC
    ");
    $invStmt->execute([$pId, $cId]);
    $invoices = $invStmt->fetchAll();

    $totalInvoiced = 0;
    $totalPaidInflow = 0;
    $totalUnpaidReceivables = 0;

    foreach ($invoices as &$inv) {
        $amt = floatval($inv['total_amount']);
        $totalInvoiced += $amt;
        if ($inv['status'] === 'Paid') {
            $totalPaidInflow += $amt;
        } else {
            $totalUnpaidReceivables += $amt;
        }
        $inv['formatted_amount'] = format_rupiah($amt);
        $inv['formatted_issue_date'] = format_date($inv['issue_date']);
        $inv['formatted_due_date'] = format_date($inv['due_date']);
    }
    unset($inv);

    // 2. Freelancer Payouts
    $payoutStmt = $db->prepare("
        SELECT id, freelancer_name, freelancer_bank, freelancer_account, task_description, amount, status, paid_at, receipt_file, created_at
        FROM freelancer_payouts
        WHERE project_id = ? AND COALESCE(is_deleted, 0) = 0
        ORDER BY id DESC
    ");
    $payoutStmt->execute([$pId]);
    $freelancerPayouts = $payoutStmt->fetchAll();

    $totalFreelancerCost = 0;
    foreach ($freelancerPayouts as &$fp) {
        $amt = floatval($fp['amount']);
        $totalFreelancerCost += $amt;
        $fp['formatted_amount'] = format_rupiah($amt);
        $fp['formatted_payment_date'] = !empty($fp['paid_at']) ? format_date($fp['paid_at']) : format_date($fp['created_at']);
    }
    unset($fp);

    // 3. Digital Ads Spend
    $adsStmt = $db->prepare("
        SELECT id, platform, account_id, notes as campaign_name, amount, spent_date, receipt_file, notes
        FROM ads_spend
        WHERE project_id = ? AND COALESCE(is_deleted, 0) = 0
        ORDER BY spent_date DESC
    ");
    $adsStmt->execute([$pId]);
    $adsSpend = $adsStmt->fetchAll();

    $totalAdsCost = 0;
    foreach ($adsSpend as &$ad) {
        $amt = floatval($ad['amount']);
        $totalAdsCost += $amt;
        $ad['formatted_amount'] = format_rupiah($amt);
        $ad['formatted_spent_date'] = !empty($ad['spent_date']) ? format_date($ad['spent_date']) : '-';
    }
    unset($ad);

    // 4. Content Deliverables (Content Planner)
    $contentStmt = $db->prepare("
        SELECT cp.id, cp.title, cp.platform, cp.content_type, cp.publish_date, cp.publish_time, cp.status, cp.asset_url, e.name as pic_name
        FROM content_planner cp
        LEFT JOIN employees e ON cp.assignee_id = e.id
        WHERE (cp.project_id = ? OR (cp.client_id = ? AND (cp.project_id IS NULL OR cp.project_id = 0))) AND COALESCE(cp.is_deleted, 0) = 0
        ORDER BY cp.publish_date ASC
    ");
    $contentStmt->execute([$pId, $cId]);
    $contents = $contentStmt->fetchAll();

    // 5. Financial Summary Calculations
    $contractValue = floatval($project['contract_value'] ?? 0);
    if ($contractValue <= 0 && $totalInvoiced > 0) {
        $contractValue = $totalInvoiced;
    }

    $totalProductionCost = $totalFreelancerCost + $totalAdsCost;
    $netProfit = $contractValue - $totalProductionCost;
    $marginPercent = $contractValue > 0 ? ($netProfit / $contractValue) * 100 : 0;
    $targetMargin = floatval($project['target_margin_percent'] ?? 40);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'project' => [
            'id' => $pId,
            'name' => $project['name'] ?? 'Proyek Tanpa Nama',
            'client_id' => $cId,
            'client_company' => $project['client_company'] ?? '-',
            'client_pic' => $project['client_pic'] ?? '-',
            'client_email' => $project['client_email'] ?? '-',
            'client_phone' => $project['client_phone'] ?? '-',
            'status' => $project['status'] ?? 'Active',
            'start_date' => $project['start_date'] ?? null,
            'end_date' => $project['end_date'] ?? null,
            'contract_value' => $contractValue,
            'formatted_contract_value' => format_rupiah($contractValue),
            'target_margin' => $targetMargin
        ],
        'financials' => [
            'contract_value' => $contractValue,
            'total_invoiced' => $totalInvoiced,
            'total_paid_inflow' => $totalPaidInflow,
            'total_unpaid_receivables' => $totalUnpaidReceivables,
            'total_freelancer_cost' => $totalFreelancerCost,
            'total_ads_cost' => $totalAdsCost,
            'total_production_cost' => $totalProductionCost,
            'net_profit' => $netProfit,
            'margin_percent' => round($marginPercent, 1),
            'target_margin_percent' => round($targetMargin, 1),
            'is_profitable' => $netProfit >= 0,
            'formatted' => [
                'contract_value' => format_rupiah($contractValue),
                'total_invoiced' => format_rupiah($totalInvoiced),
                'total_paid_inflow' => format_rupiah($totalPaidInflow),
                'total_unpaid' => format_rupiah($totalUnpaidReceivables),
                'freelancer_cost' => format_rupiah($totalFreelancerCost),
                'ads_cost' => format_rupiah($totalAdsCost),
                'production_cost' => format_rupiah($totalProductionCost),
                'net_profit' => format_rupiah($netProfit),
                'margin_percent' => round($marginPercent, 1) . '%'
            ]
        ],
        'invoices' => $invoices,
        'freelancers' => $freelancerPayouts,
        'ads' => $adsSpend,
        'contents' => $contents
    ]);
    exit;
}

// 1. Export Excel (Rich Formatted XML/HTML Spreadsheet)
if ($action === 'export_excel') {
    $currentMonth = $_GET['month'] ?? date('Y-m');
    $filename = "Rekonsiliasi_Kalamedia_" . str_replace('-', '_', $currentMonth) . "_" . date('Ymd_His') . ".xls";

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Pragma: no-cache');
    header('Expires: 0');

    // Fetch all transactions
    $rows = [];
    $totalInflow = 0;
    $totalOutflow = 0;

    // Invoices
    $invStmt = $db->query("
        SELECT i.*, c.company as client_company 
        FROM invoices i 
        JOIN clients c ON i.client_id = c.id 
        WHERE COALESCE(i.is_deleted, 0) = 0
        ORDER BY i.issue_date DESC, i.id DESC
    ");
    while ($inv = $invStmt->fetch()) {
        $amt = floatval($inv['total_amount']);
        if ($inv['status'] === 'Paid') $totalInflow += $amt;
        $rows[] = [
            'date' => $inv['issue_date'],
            'type' => 'Inflow',
            'category' => 'Invoice Klien',
            'entity' => $inv['client_company'],
            'desc' => "Invoice #" . $inv['invoice_number'],
            'amount' => $amt,
            'status' => $inv['status'],
            'method' => $inv['payment_method'] ?: 'Bank Transfer',
            'paid_at' => $inv['paid_at'] ?: '-',
            'receipt' => !empty($inv['receipt_file']) ? 'Ada' : 'Belum Ada'
        ];
    }

    // Freelancer Payouts
    $payStmt = $db->query("
        SELECT p.*, pr.name as project_name 
        FROM freelancer_payouts p 
        LEFT JOIN projects pr ON p.project_id = pr.id 
        WHERE COALESCE(p.is_deleted, 0) = 0
        ORDER BY p.created_at DESC, p.id DESC
    ");
    while ($pay = $payStmt->fetch()) {
        $amt = floatval($pay['amount']);
        if ($pay['status'] === 'Paid') $totalOutflow += $amt;
        $rows[] = [
            'date' => date('Y-m-d', strtotime($pay['created_at'])),
            'type' => 'Outflow',
            'category' => 'Fee Freelancer',
            'entity' => $pay['freelancer_name'] . ($pay['freelancer_bank'] ? " ({$pay['freelancer_bank']})" : ""),
            'desc' => $pay['task_description'] . ($pay['project_name'] ? " - Proyek: {$pay['project_name']}" : ""),
            'amount' => $amt,
            'status' => $pay['status'],
            'method' => 'Bank Transfer',
            'paid_at' => $pay['paid_at'] ?: '-',
            'receipt' => !empty($pay['receipt_file']) ? 'Ada' : 'Belum Ada'
        ];
    }

    // Employee Salaries
    $salStmt = $db->query("
        SELECT * FROM salaries 
        WHERE COALESCE(is_deleted, 0) = 0
        ORDER BY payment_date DESC, id DESC
    ");
    while ($sal = $salStmt->fetch()) {
        $amt = floatval($sal['net_salary']);
        if ($sal['status'] === 'Paid') $totalOutflow += $amt;
        $rows[] = [
            'date' => $sal['payment_date'],
            'type' => 'Outflow',
            'category' => 'Gaji Karyawan',
            'entity' => $sal['employee_name'] . " ({$sal['employee_position']})",
            'desc' => "Payroll Periode " . $sal['month_period'] . ($sal['notes'] ? " - " . $sal['notes'] : ""),
            'amount' => $amt,
            'status' => $sal['status'],
            'method' => 'Bank Transfer',
            'paid_at' => $sal['paid_at'] ?: '-',
            'receipt' => !empty($sal['receipt_file']) ? 'Ada' : 'Belum Ada'
        ];
    }

    // Ads Spend
    $adsStmt = $db->query("
        SELECT a.*, c.company as client_company 
        FROM ads_spend a 
        LEFT JOIN clients c ON a.client_id = c.id 
        WHERE COALESCE(a.is_deleted, 0) = 0
        ORDER BY a.spent_date DESC, a.id DESC
    ");
    while ($ad = $adsStmt->fetch()) {
        $amt = floatval($ad['amount']);
        $totalOutflow += $amt;
        $rows[] = [
            'date' => $ad['spent_date'],
            'type' => 'Outflow',
            'category' => 'Top-Up Ads',
            'entity' => $ad['client_company'] . ($ad['account_id'] ? " ({$ad['account_id']})" : ""),
            'desc' => "Top-Up " . $ad['platform'] . ($ad['notes'] ? " - " . $ad['notes'] : ""),
            'amount' => $amt,
            'status' => 'Paid',
            'method' => 'Credit Card / Auto-Debit',
            'paid_at' => $ad['spent_date'],
            'receipt' => !empty($ad['receipt_file']) ? 'Ada' : 'Belum Ada'
        ];
    }

    // Sort by date DESC
    usort($rows, function ($a, $b) {
        return strcmp($b['date'], $a['date']);
    });

    $netProfit = $totalInflow - $totalOutflow;
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
      <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
      <style>
        body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; }
        .table-excel { border-collapse: collapse; width: 100%; }
        .table-excel th { background-color: #0F172A; color: #FFFFFF; font-weight: bold; border: 1px solid #334155; padding: 8px; text-align: center; }
        .table-excel td { border: 1px solid #CBD5E1; padding: 6px 8px; font-size: 10pt; }
        .num { text-align: right; mso-number-format: "\#\,\#\#0"; }
        .center { text-align: center; }
        .title { font-size: 16pt; font-weight: bold; color: #0F172A; }
        .subtitle { font-size: 10pt; color: #64748B; }
        .kpi-box { padding: 6px 12px; font-weight: bold; border: 1px solid #94A3B8; }
        .inflow { color: #059669; font-weight: bold; }
        .outflow { color: #DC2626; font-weight: bold; }
        .paid { color: #059669; font-weight: bold; }
        .pending { color: #D97706; font-weight: bold; }
      </style>
    </head>
    <body>
      <table>
        <tr>
          <td colspan="11" class="title"><?= AGENCY_NAME ?> - LAPORAN REKONSILIASI KEUANGAN</td>
        </tr>
        <tr>
          <td colspan="11" class="subtitle">Periode: <?= date('F Y', strtotime($currentMonth . '-01')) ?> | Dicetak: <?= date('d/m/Y H:i') ?> | Oleh: <?= htmlspecialchars($_SESSION['user']['name'] ?? 'Finance') ?></td>
        </tr>
        <tr><td colspan="11"></td></tr>
        <tr>
          <td colspan="3" class="kpi-box" style="background-color: #ECFDF5; color: #065F46;">Total Pemasukan (Inflow): Rp <?= number_format($totalInflow, 0, ',', '.') ?></td>
          <td colspan="4" class="kpi-box" style="background-color: #FEF2F2; color: #991B1B;">Total Pengeluaran (Outflow): Rp <?= number_format($totalOutflow, 0, ',', '.') ?></td>
          <td colspan="4" class="kpi-box" style="background-color: #EFF6FF; color: #1E40AF;">Net Profit Bersih: Rp <?= number_format($netProfit, 0, ',', '.') ?></td>
        </tr>
        <tr><td colspan="11"></td></tr>
      </table>

      <table class="table-excel">
        <thead>
          <tr>
            <th style="width: 40px;">No</th>
            <th style="width: 90px;">Tanggal</th>
            <th style="width: 90px;">Tipe Mutasi</th>
            <th style="width: 120px;">Kategori</th>
            <th style="width: 200px;">Entitas / Klien / Penerima</th>
            <th style="width: 280px;">Deskripsi Transaksi</th>
            <th style="width: 120px;">Nominal (IDR)</th>
            <th style="width: 90px;">Status</th>
            <th style="width: 120px;">Metode Bayar</th>
            <th style="width: 130px;">Waktu Lunas</th>
            <th style="width: 100px;">Bukti Struk</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $idx => $r): ?>
            <tr>
              <td class="center"><?= $idx + 1 ?></td>
              <td class="center"><?= $r['date'] ?></td>
              <td class="center <?= strtolower($r['type']) ?>"><?= $r['type'] === 'Inflow' ? '+ Inflow' : '- Outflow' ?></td>
              <td><?= htmlspecialchars($r['category']) ?></td>
              <td><b><?= htmlspecialchars($r['entity']) ?></b></td>
              <td><?= htmlspecialchars($r['desc']) ?></td>
              <td class="num"><?= $r['amount'] ?></td>
              <td class="center <?= strtolower($r['status']) ?>"><?= $r['status'] ?></td>
              <td><?= htmlspecialchars($r['method']) ?></td>
              <td class="center"><?= $r['paid_at'] ?></td>
              <td class="center"><?= $r['receipt'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </body>
    </html>
    <?php
    exit;
}

// 2. Export CSV (Fixed with sep=; for instant Excel column splitting)
if ($action === 'export_csv') {
    $currentMonth = $_GET['month'] ?? date('Y-m');
    $filename = "Rekonsiliasi_Kalamedia_" . str_replace('-', '_', $currentMonth) . "_" . date('Ymd_His') . ".csv";

    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM
    fputs($output, "\xEF\xBB\xBF");
    // Explicit Excel separator directive
    fputs($output, "sep=;\r\n");

    // Header row
    fputcsv($output, [
        'No', 'Tanggal', 'Tipe Mutasi', 'Kategori', 'Entitas / Klien / Penerima', 
        'Deskripsi Transaksi', 'Nominal (IDR)', 'Status', 'Metode Bayar', 'Waktu Lunas', 'Bukti Transfer'
    ], ';');

    $rowIdx = 1;

    // 1. Invoices
    $invStmt = $db->prepare("
        SELECT i.*, c.company as client_company 
        FROM invoices i 
        JOIN clients c ON i.client_id = c.id 
        WHERE COALESCE(i.is_deleted, 0) = 0
        ORDER BY i.issue_date DESC, i.id DESC
    ");
    $invStmt->execute();
    while ($inv = $invStmt->fetch()) {
        fputcsv($output, [
            $rowIdx++,
            $inv['issue_date'],
            'Inflow',
            'Invoice Klien',
            $inv['client_company'],
            "Invoice #" . $inv['invoice_number'],
            $inv['total_amount'],
            $inv['status'],
            $inv['payment_method'] ?: 'Bank Transfer',
            $inv['paid_at'] ?: '-',
            !empty($inv['receipt_file']) ? 'Ada' : 'Belum Ada'
        ], ';');
    }

    // 2. Freelancer Payouts
    $payStmt = $db->prepare("
        SELECT p.*, pr.name as project_name 
        FROM freelancer_payouts p 
        LEFT JOIN projects pr ON p.project_id = pr.id 
        WHERE COALESCE(p.is_deleted, 0) = 0
        ORDER BY p.created_at DESC, p.id DESC
    ");
    $payStmt->execute();
    while ($pay = $payStmt->fetch()) {
        fputcsv($output, [
            $rowIdx++,
            date('Y-m-d', strtotime($pay['created_at'])),
            'Outflow',
            'Fee Freelancer',
            $pay['freelancer_name'] . ($pay['freelancer_bank'] ? " ({$pay['freelancer_bank']})" : ""),
            $pay['task_description'] . ($pay['project_name'] ? " - Proyek: {$pay['project_name']}" : ""),
            $pay['amount'],
            $pay['status'],
            'Bank Transfer',
            $pay['paid_at'] ?: '-',
            !empty($pay['receipt_file']) ? 'Ada' : 'Belum Ada'
        ], ';');
    }

    // 3. Employee Salaries
    $salStmt = $db->prepare("
        SELECT * FROM salaries 
        WHERE COALESCE(is_deleted, 0) = 0
        ORDER BY payment_date DESC, id DESC
    ");
    $salStmt->execute();
    while ($sal = $salStmt->fetch()) {
        fputcsv($output, [
            $rowIdx++,
            $sal['payment_date'],
            'Outflow',
            'Gaji Karyawan',
            $sal['employee_name'] . " ({$sal['employee_position']})",
            "Payroll Periode " . $sal['month_period'] . ($sal['notes'] ? " - " . $sal['notes'] : ""),
            $sal['net_salary'],
            $sal['status'],
            'Bank Transfer',
            $sal['paid_at'] ?: '-',
            !empty($sal['receipt_file']) ? 'Ada' : 'Belum Ada'
        ], ';');
    }

    // 4. Ads Spend
    $adsStmt = $db->prepare("
        SELECT a.*, c.company as client_company 
        FROM ads_spend a 
        LEFT JOIN clients c ON a.client_id = c.id 
        WHERE COALESCE(a.is_deleted, 0) = 0
        ORDER BY a.spent_date DESC, a.id DESC
    ");
    $adsStmt->execute();
    while ($ad = $adsStmt->fetch()) {
        fputcsv($output, [
            $rowIdx++,
            $ad['spent_date'],
            'Outflow',
            'Top-Up Ads',
            $ad['client_company'] . ($ad['account_id'] ? " ({$ad['account_id']})" : ""),
            "Top-Up " . $ad['platform'] . ($ad['notes'] ? " - " . $ad['notes'] : ""),
            $ad['amount'],
            'Paid',
            'Credit Card / Auto-Debit',
            $ad['spent_date'],
            !empty($ad['receipt_file']) ? 'Ada' : 'Belum Ada'
        ], ';');
    }

    fclose($output);
    exit;
}

// 3. Export PDF Report (Printable Executive Reconciliation Report)
if ($action === 'export_pdf') {
    $currentMonth = $_GET['month'] ?? date('Y-m');
    $filename = "Laporan_Rekonsiliasi_Kalamedia_" . str_replace('-', '_', $currentMonth) . ".pdf";

    header('Content-Type: text/html; charset=utf-8');

    // Fetch summary and rows
    $rows = [];
    $totalInflow = 0;
    $totalOutflow = 0;

    $invStmt = $db->query("SELECT i.*, c.company as client_company FROM invoices i JOIN clients c ON i.client_id = c.id WHERE COALESCE(i.is_deleted, 0) = 0 ORDER BY i.issue_date DESC");
    while ($inv = $invStmt->fetch()) {
        $amt = floatval($inv['total_amount']);
        if ($inv['status'] === 'Paid') $totalInflow += $amt;
        $rows[] = ['date' => $inv['issue_date'], 'type' => 'Inflow', 'category' => 'Invoice Klien', 'entity' => $inv['client_company'], 'desc' => "Invoice #" . $inv['invoice_number'], 'amount' => $amt, 'status' => $inv['status']];
    }
    $payStmt = $db->query("SELECT p.*, pr.name as project_name FROM freelancer_payouts p LEFT JOIN projects pr ON p.project_id = pr.id WHERE COALESCE(p.is_deleted, 0) = 0 ORDER BY p.created_at DESC");
    while ($pay = $payStmt->fetch()) {
        $amt = floatval($pay['amount']);
        if ($pay['status'] === 'Paid') $totalOutflow += $amt;
        $rows[] = ['date' => date('Y-m-d', strtotime($pay['created_at'])), 'type' => 'Outflow', 'category' => 'Fee Freelancer', 'entity' => $pay['freelancer_name'], 'desc' => $pay['task_description'], 'amount' => $amt, 'status' => $pay['status']];
    }
    $salStmt = $db->query("SELECT * FROM salaries WHERE COALESCE(is_deleted, 0) = 0 ORDER BY payment_date DESC");
    while ($sal = $salStmt->fetch()) {
        $amt = floatval($sal['net_salary']);
        if ($sal['status'] === 'Paid') $totalOutflow += $amt;
        $rows[] = ['date' => $sal['payment_date'], 'type' => 'Outflow', 'category' => 'Gaji Karyawan', 'entity' => $sal['employee_name'], 'desc' => "Payroll " . $sal['month_period'], 'amount' => $amt, 'status' => $sal['status']];
    }
    $adsStmt = $db->query("SELECT a.*, c.company as client_company FROM ads_spend a LEFT JOIN clients c ON a.client_id = c.id WHERE COALESCE(a.is_deleted, 0) = 0 ORDER BY a.spent_date DESC");
    while ($ad = $adsStmt->fetch()) {
        $amt = floatval($ad['amount']);
        $totalOutflow += $amt;
        $rows[] = ['date' => $ad['spent_date'], 'type' => 'Outflow', 'category' => 'Top-Up Ads', 'entity' => $ad['client_company'], 'desc' => "Top-Up " . $ad['platform'], 'amount' => $amt, 'status' => 'Paid'];
    }

    usort($rows, function ($a, $b) {
        return strcmp($b['date'], $a['date']);
    });

    $netProfit = $totalInflow - $totalOutflow;
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Laporan Rekonsiliasi - Kala Media</title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
      <script src="../assets/js/html2pdf.bundle.min.js"></script>
      <style>
        @page {
          size: A4 portrait;
          margin: 15mm 12mm 15mm 12mm;
        }

        body {
          font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, Arial, sans-serif;
          margin: 0;
          padding: 0;
          background: #F1F5F9;
          color: #0F172A;
          -webkit-print-color-adjust: exact;
          print-color-adjust: exact;
        }
        
        .top-action-bar {
          background: #0F172A;
          color: #FFF;
          padding: 14px 28px;
          display: flex;
          justify-content: space-between;
          align-items: center;
          position: sticky;
          top: 0;
          z-index: 100;
          box-shadow: 0 2px 12px rgba(0,0,0,0.25);
        }
        .btn-action {
          padding: 8px 18px;
          border-radius: 6px;
          font-weight: 700;
          font-size: 12.5px;
          cursor: pointer;
          border: none;
          display: inline-flex;
          align-items: center;
          gap: 6px;
          text-decoration: none;
        }
        .btn-primary { background: #6366F1; color: #FFF; }
        .btn-primary:hover { background: #4F46E5; }
        .btn-secondary { background: #334155; color: #FFF; }
        .btn-secondary:hover { background: #475569; }

        .report-page-wrapper {
          max-width: 860px;
          margin: 36px auto 50px;
          background: #FFF;
          padding: 50px 52px 44px;
          border-radius: 8px;
          box-shadow: 0 4px 25px rgba(0,0,0,0.08);
          box-sizing: border-box;
        }

        /* 1. Header with generous spacing */
        .header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          border-bottom: 2px solid #0F172A;
          padding-top: 10px;
          padding-bottom: 22px;
          margin-bottom: 28px;
        }
        .logo-box {
          display: flex;
          align-items: center;
          gap: 14px;
        }
        .logo {
          height: 46px;
          width: auto;
          object-fit: contain;
        }
        .title-block {
          text-align: right;
        }
        .title {
          font-size: 20px;
          font-weight: 800;
          color: #0F172A;
          letter-spacing: -0.3px;
          margin: 0;
        }
        .meta {
          font-size: 11px;
          color: #64748B;
          margin-top: 6px;
          font-weight: 500;
        }
        
        /* 2. KPI Summary Cards */
        .kpi-row {
          display: flex;
          gap: 16px;
          margin-bottom: 28px;
        }
        .kpi-box {
          flex: 1;
          padding: 14px 18px;
          border-radius: 8px;
          border: 1px solid #E2E8F0;
        }
        .kpi-box.green { background: #ECFDF5; border-color: #A7F3D0; }
        .kpi-box.red { background: #FEF2F2; border-color: #FECACA; }
        .kpi-box.blue { background: #EFF6FF; border-color: #BFDBFE; }
        .kpi-label { font-size: 10.5px; color: #64748B; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .kpi-val { font-size: 19px; font-weight: 800; margin-top: 5px; }
        .green .kpi-val { color: #059669; }
        .red .kpi-val { color: #DC2626; }
        .blue .kpi-val { color: #2563EB; }
        
        /* 3. Clean Table with No Awkward Word-Wrapping */
        table {
          width: 100%;
          border-collapse: collapse;
          font-size: 11px;
          margin-top: 8px;
        }
        th {
          background: #0F172A;
          color: #FFF;
          padding: 10px 10px;
          text-align: left;
          font-weight: 700;
          font-size: 11px;
          letter-spacing: 0.2px;
        }
        td {
          padding: 10px 10px;
          border-bottom: 1px solid #E2E8F0;
          vertical-align: middle;
        }
        tr:nth-child(even) { background: #F8FAFC; }
        
        .inflow-tag { color: #059669; font-weight: 700; white-space: nowrap; }
        .outflow-tag { color: #DC2626; font-weight: 700; white-space: nowrap; }
        .paid-tag { color: #059669; font-weight: 700; background: #ECFDF5; padding: 3px 8px; border-radius: 4px; border: 1px solid #A7F3D0; white-space: nowrap; }
        .pending-tag { color: #D97706; font-weight: 700; background: #FEF3C7; padding: 3px 8px; border-radius: 4px; border: 1px solid #FDE68A; white-space: nowrap; }
        .num { text-align: right; white-space: nowrap; }

        @media print {
          .top-action-bar { display: none !important; }
          body { background: #FFF !important; }
          .report-page-wrapper {
            box-shadow: none !important;
            padding: 15px 0 0 0 !important;
            margin: 0 !important;
            max-width: 100% !important;
            width: 100% !important;
          }
        }
      </style>
    </head>
    <body>

      <div class="top-action-bar">
        <div style="font-size: 13.5px; font-weight: 700; letter-spacing: -0.2px;">
          Laporan Rekonsiliasi Keuangan Bulanan - Kala Media Creative
        </div>
        <div style="display: flex; gap: 10px;">
          <button type="button" class="btn-action btn-primary" id="btn-dl-pdf" onclick="generatePdf()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            <span>Download PDF</span>
          </button>
          <button type="button" class="btn-action btn-secondary" onclick="window.print()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            <span>Cetak / Print</span>
          </button>
        </div>
      </div>

      <div class="report-page-wrapper" id="report-canvas">
        <div class="header">
          <div class="logo-box">
            <img src="../assets/Jpg/Asset 3.png" alt="Kala Media" class="logo" onerror="this.src='../assets/img/qr_code.svg';">
            <div>
              <div style="font-weight: 800; font-size: 14.5px; color: #0F172A;">Kala Media Creative</div>
              <div style="font-size: 11px; color: #64748B; line-height: 1.3;">Financial & Operational Reconciliation Report</div>
            </div>
          </div>
          <div class="title-block">
            <div class="title">LAPORAN REKONSILIASI KEUANGAN</div>
            <div class="meta">Periode: <?= date('F Y', strtotime($currentMonth . '-01')) ?> | Dicetak: <?= date('d/m/Y H:i') ?> WIB</div>
          </div>
        </div>

        <div class="kpi-row">
          <div class="kpi-box green">
            <div class="kpi-label">Total Pemasukan (Inflow)</div>
            <div class="kpi-val"><?= format_rupiah($totalInflow) ?></div>
          </div>
          <div class="kpi-box red">
            <div class="kpi-label">Total Pengeluaran (Outflow)</div>
            <div class="kpi-val"><?= format_rupiah($totalOutflow) ?></div>
          </div>
          <div class="kpi-box blue">
            <div class="kpi-label">Net Profit Bersih</div>
            <div class="kpi-val"><?= format_rupiah($netProfit) ?></div>
          </div>
        </div>

        <table>
          <thead>
            <tr>
              <th style="width: 32px; text-align: center;">No</th>
              <th style="width: 82px;">Tanggal</th>
              <th style="width: 72px;">Tipe</th>
              <th style="width: 105px;">Kategori</th>
              <th style="width: 145px;">Entitas / Klien</th>
              <th>Deskripsi Transaksi</th>
              <th style="width: 105px; text-align: right;">Nominal</th>
              <th style="width: 70px; text-align: center;">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="8" style="text-align: center; color: #94A3B8; padding: 24px;">Belum ada data transaksi pada periode ini.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $idx => $r): ?>
                <tr>
                  <td style="text-align: center; color: #64748B; font-weight: 600;"><?= $idx + 1 ?></td>
                  <td style="white-space: nowrap; color: #334155;"><?= date('d/m/Y', strtotime($r['date'])) ?></td>
                  <td class="<?= strtolower($r['type']) ?>-tag"><?= $r['type'] === 'Inflow' ? '+ Inflow' : '- Outflow' ?></td>
                  <td style="color: #334155;"><?= htmlspecialchars($r['category']) ?></td>
                  <td><b style="color: #0F172A;"><?= htmlspecialchars($r['entity']) ?></b></td>
                  <td style="color: #334155; line-height: 1.4;"><?= htmlspecialchars($r['desc']) ?></td>
                  <td class="num font-bold" style="color: #0F172A;"><?= format_rupiah($r['amount']) ?></td>
                  <td style="text-align: center;"><span class="<?= strtolower($r['status']) ?>-tag"><?= $r['status'] ?></span></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

        <div style="margin-top: 36px; padding-top: 16px; border-top: 1px solid #E2E8F0; display: flex; justify-content: space-between; font-size: 10.5px; color: #94A3B8;">
          <div>Dokumen ini dihasilkan secara resmi oleh Sistem Finansial Kala Media Creative.</div>
          <div>Halaman 1 / 1</div>
        </div>
      </div>

      <script>
        async function generatePdf() {
          const element = document.getElementById('report-canvas');
          const filename = <?= json_encode($filename) ?>;
          const btn = document.getElementById('btn-dl-pdf');
          const origText = btn.innerHTML;
          btn.disabled = true;
          btn.innerHTML = '<span>Menyiapkan PDF...</span>';

          const opt = {
            margin: [12, 12, 12, 12],
            filename: filename,
            image: { type: 'jpeg', quality: 1 },
            html2canvas: { scale: 2.5, useCORS: true, letterRendering: true, scrollY: 0 },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
          };

          try {
            if (typeof html2pdf !== 'undefined') {
              await html2pdf().set(opt).from(element).save();
            } else {
              window.print();
            }
          } catch(e) {
            console.error(e);
            window.print();
          } finally {
            btn.disabled = false;
            btn.innerHTML = origText;
          }
        }
      </script>
    </body>
    </html>
    <?php
    exit;
}

// 1. Total Revenue (Paid Invoices)
$revQuery = "SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE status = 'Paid' AND COALESCE(is_deleted, 0) = 0 AND ($dateConditionInv)";
$totalRevenue = floatval($db->query($revQuery)->fetchColumn());

// 2. Total Outflow (Freelancer Payouts + Ads Spend + Employee Salaries)
$payoutQuery = "SELECT COALESCE(SUM(amount), 0) FROM freelancer_payouts WHERE status = 'Paid' AND COALESCE(is_deleted, 0) = 0 AND ($dateConditionPayout)";
$totalPayouts = floatval($db->query($payoutQuery)->fetchColumn());

$adsQuery = "SELECT COALESCE(SUM(amount), 0) FROM ads_spend WHERE COALESCE(is_deleted, 0) = 0 AND ($dateConditionAds)";
$totalAds = floatval($db->query($adsQuery)->fetchColumn());

$salaryQuery = "SELECT COALESCE(SUM(net_salary), 0) FROM salaries WHERE status = 'Paid' AND COALESCE(is_deleted, 0) = 0 AND ($dateConditionSalary)";
$totalSalaries = floatval($db->query($salaryQuery)->fetchColumn());

$totalExpense = $totalPayouts + $totalAds + $totalSalaries;

// 3. Net Profit & Margin %
$netProfit = $totalRevenue - $totalExpense;
$profitMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;

// 4. Outstanding Receivables (Unpaid / Sent / Draft / Overdue)
$recQuery = "SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE status IN ('Sent', 'Draft', 'Overdue') AND COALESCE(is_deleted, 0) = 0";
$outstandingReceivables = floatval($db->query($recQuery)->fetchColumn());

// 5. Cashflow Chart Data (6 Months history)
$chartMonths = [];
$chartRevenue = [];
$chartExpense = [];

for ($i = 5; $i >= 0; $i--) {
    $mTime = strtotime("-$i months");
    $mKey = date('Y-m', $mTime);
    $mLabel = date('M Y', $mTime);
    $chartMonths[] = $mLabel;

    // Monthly revenue
    $mRev = floatval($db->query("SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE status = 'Paid' AND COALESCE(is_deleted, 0) = 0 AND strftime('%Y-%m', paid_at) = '$mKey'")->fetchColumn());
    // Monthly expense
    $mPay = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM freelancer_payouts WHERE status = 'Paid' AND COALESCE(is_deleted, 0) = 0 AND strftime('%Y-%m', paid_at) = '$mKey'")->fetchColumn());
    $mAds = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM ads_spend WHERE COALESCE(is_deleted, 0) = 0 AND strftime('%Y-%m', spent_date) = '$mKey'")->fetchColumn());
    $mSal = floatval($db->query("SELECT COALESCE(SUM(net_salary), 0) FROM salaries WHERE status = 'Paid' AND COALESCE(is_deleted, 0) = 0 AND (strftime('%Y-%m', paid_at) = '$mKey' OR month_period = '$mKey')")->fetchColumn());

    $chartRevenue[] = $mRev;
    $chartExpense[] = $mPay + $mAds + $mSal;
}

// 6. Project Profitability Monitor
$projects = $db->query("
    SELECT p.*, c.company as client_company,
           (SELECT COALESCE(SUM(amount), 0) FROM freelancer_payouts WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0) as total_freelancer_cost,
           (SELECT COALESCE(SUM(amount), 0) FROM ads_spend WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0) as total_ads_cost
    FROM projects p
    JOIN clients c ON p.client_id = c.id
    ORDER BY p.id DESC
")->fetchAll();

$profitabilityData = [];
foreach ($projects as $proj) {
    $prodCost = floatval($proj['total_freelancer_cost']) + floatval($proj['total_ads_cost']);
    $contract = floatval($proj['contract_value']);
    $profit = $contract - $prodCost;
    $margin = $contract > 0 ? ($profit / $contract) * 100 : 0;
    $isOverBudget = $margin < floatval($proj['target_margin_percent']);

    $profitabilityData[] = [
        'id' => $proj['id'],
        'name' => $proj['name'],
        'client' => $proj['client_company'],
        'contract_value' => $contract,
        'production_cost' => $prodCost,
        'freelancer_cost' => floatval($proj['total_freelancer_cost']),
        'ads_cost' => floatval($proj['total_ads_cost']),
        'profit' => $profit,
        'margin_percent' => round($margin, 1),
        'target_margin' => floatval($proj['target_margin_percent']),
        'is_over_budget' => $isOverBudget,
        'status' => $proj['status']
    ];
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'kpis' => [
        'total_revenue' => $totalRevenue,
        'total_expense' => $totalExpense,
        'total_payouts' => $totalPayouts,
        'total_ads' => $totalAds,
        'total_salaries' => $totalSalaries,
        'net_profit' => $netProfit,
        'profit_margin' => round($profitMargin, 1),
        'outstanding_receivables' => $outstandingReceivables,
        'formatted' => [
            'revenue' => format_rupiah($totalRevenue),
            'expense' => format_rupiah($totalExpense),
            'salaries' => format_rupiah($totalSalaries),
            'net_profit' => format_rupiah($netProfit),
            'margin' => round($profitMargin, 1) . '%',
            'receivables' => format_rupiah($outstandingReceivables)
        ]
    ],
    'chart' => [
        'labels' => $chartMonths,
        'revenue' => $chartRevenue,
        'expense' => $chartExpense
    ],
    'profitability' => $profitabilityData
]);

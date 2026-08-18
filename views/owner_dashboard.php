<?php
/**
 * Kalamedia Owner Dashboard (Executive View - Untitled UI Design System)
 * Focused on Monthly Net Profit, Margins, Cashflow Trends, Profitability & Internal Team
 */
require_owner();
$db = Database::getConnection();

// Initial data for page load (This Month by default)
$currentMonth = date('Y-m');

// 1. Total Revenue
$totalRevenue = floatval($db->query("SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE status = 'Paid' AND COALESCE(is_deleted, 0) = 0 AND strftime('%Y-%m', paid_at) = '$currentMonth'")->fetchColumn());
if ($totalRevenue == 0) {
    $totalRevenue = floatval($db->query("SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE status = 'Paid' AND COALESCE(is_deleted, 0) = 0")->fetchColumn());
}

// 2. Total Expense
$totalPayouts = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM freelancer_payouts WHERE status = 'Paid' AND COALESCE(is_deleted, 0) = 0")->fetchColumn());
$totalAds = floatval($db->query("SELECT COALESCE(SUM(amount), 0) FROM ads_spend WHERE COALESCE(is_deleted, 0) = 0")->fetchColumn());
$totalSalaries = floatval($db->query("SELECT COALESCE(SUM(net_salary), 0) FROM salaries WHERE status = 'Paid' AND COALESCE(is_deleted, 0) = 0")->fetchColumn());
$totalExpense = $totalPayouts + $totalAds + $totalSalaries;

// 3. Net Profit & Margin
$netProfit = $totalRevenue - $totalExpense;
$profitMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;

// 4. Outstanding Receivables
$outstandingReceivables = floatval($db->query("SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE status IN ('Sent', 'Draft', 'Overdue') AND COALESCE(is_deleted, 0) = 0")->fetchColumn());
$unpaidInvoicesList = $db->query("
    SELECT i.*, c.company as client_company, c.phone as client_phone
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    WHERE i.status IN ('Sent', 'Draft', 'Overdue') AND COALESCE(i.is_deleted, 0) = 0
    ORDER BY i.due_date ASC LIMIT 5
")->fetchAll();

// 5. Project Profitability Leaderboard (Ranked by Profit Margin %)
$rawProjects = $db->query("
    SELECT p.*, c.company as client_company,
           (SELECT COALESCE(SUM(amount), 0) FROM freelancer_payouts WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0) as total_freelancer_cost,
           (SELECT COALESCE(SUM(amount), 0) FROM ads_spend WHERE project_id = p.id AND COALESCE(is_deleted, 0) = 0) as total_ads_cost
    FROM projects p
    JOIN clients c ON p.client_id = c.id
")->fetchAll();

$leaderboard = [];
foreach ($rawProjects as $p) {
    $prodCost = floatval($p['total_freelancer_cost']) + floatval($p['total_ads_cost']);
    $contract = floatval($p['contract_value']);
    $profit = $contract - $prodCost;
    $margin = $contract > 0 ? ($profit / $contract) * 100 : 0;
    $targetMargin = floatval($p['target_margin_percent']);
    $isOverBudget = $margin < $targetMargin;

    $leaderboard[] = array_merge($p, [
        'prod_cost' => $prodCost,
        'profit' => $profit,
        'margin' => $margin,
        'target_margin' => $targetMargin,
        'is_over_budget' => $isOverBudget
    ]);
}

// Sort leaderboard by profit DESC
usort($leaderboard, function($a, $b) {
    return $b['profit'] <=> $a['profit'];
});

// 6. Internal Team & Payroll Summary
$activeEmployees = $db->query("SELECT * FROM employees WHERE status = 'Active' ORDER BY name ASC LIMIT 5")->fetchAll();
$activeEmployeeCount = intval($db->query("SELECT COUNT(*) FROM employees WHERE status = 'Active'")->fetchColumn());
$thisMonthSalaryPaid = floatval($db->query("SELECT COALESCE(SUM(net_salary), 0) FROM salaries WHERE status = 'Paid' AND COALESCE(is_deleted, 0) = 0 AND (month_period = '$currentMonth' OR strftime('%Y-%m', paid_at) = '$currentMonth')")->fetchColumn());
$pendingSalaryTotal = floatval($db->query("SELECT COALESCE(SUM(net_salary), 0) FROM salaries WHERE status = 'Pending' AND COALESCE(is_deleted, 0) = 0")->fetchColumn());
$pendingSalaryCount = intval($db->query("SELECT COUNT(*) FROM salaries WHERE status = 'Pending' AND COALESCE(is_deleted, 0) = 0")->fetchColumn());

// 7. Recent High-Level Activities
$activities = $db->query("SELECT * FROM activities ORDER BY id DESC LIMIT 6")->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Executive Dashboard - Kalamedia</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="assets/js/charts.js"></script>
</head>
<body>
  <div class="app-container">
    <?php require_once BASE_PATH . '/includes/sidebar.php'; ?>

    <main class="main-wrapper">
      <?php require_once BASE_PATH . '/includes/header.php'; ?>

      <div class="content-body">
        
        <!-- Key Performance Indicators (KPI) Cards -->
        <div class="kpi-grid">
          <!-- Total Revenue -->
          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Total Revenue (Inflow)</span>
              <div class="kpi-icon" style="background: var(--success-bg); color: var(--success-text);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
              </div>
            </div>
            <div class="kpi-value" id="kpi-revenue"><?= format_rupiah($totalRevenue) ?></div>
            <div class="kpi-meta">
              <span style="color: var(--success-text); font-weight: 700;">&bull; Terverifikasi</span> dari invoice lunas klien
            </div>
          </div>

          <!-- Total Expense -->
          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Total Expense (Outflow)</span>
              <div class="kpi-icon" style="background: var(--danger-bg); color: var(--danger-text);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"></polyline><polyline points="17 18 23 18 23 12"></polyline></svg>
              </div>
            </div>
            <div class="kpi-value" id="kpi-expense"><?= format_rupiah($totalExpense) ?></div>
            <div class="kpi-meta" style="font-size: 11px;">
              Freelancer: <?= format_rupiah($totalPayouts) ?> | Ads: <?= format_rupiah($totalAds) ?> | Gaji: <?= format_rupiah($totalSalaries) ?>
            </div>
          </div>

          <!-- Net Profit -->
          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Monthly Net Profit & Margin</span>
              <div class="kpi-icon" style="background: #F2F4F7; color: #101828;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path><line x1="12" y1="6" x2="12" y2="18"></line></svg>
              </div>
            </div>
            <div class="kpi-value" id="kpi-profit"><?= format_rupiah($netProfit) ?></div>
            <div class="kpi-meta">
              <span id="kpi-margin" class="badge-margin <?= $profitMargin >= 30 ? 'good' : ($profitMargin >= 15 ? 'warning' : 'bad') ?>">
                Margin <?= round($profitMargin, 1) ?>%
              </span>
              <span>Target Agensi &ge; 30%</span>
            </div>
          </div>

          <!-- Outstanding Receivables -->
          <div class="kpi-card">
            <div class="kpi-header">
              <span class="kpi-title">Outstanding Receivables</span>
              <div class="kpi-icon" style="background: var(--warning-bg); color: var(--warning-text);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
              </div>
            </div>
            <div class="kpi-value" id="kpi-receivables"><?= format_rupiah($outstandingReceivables) ?></div>
            <div class="kpi-meta">
              <span style="color: var(--warning-text); font-weight: 700;">Piutang Klien</span> belum dilunasi
            </div>
          </div>
        </div>

        <!-- Cashflow Chart Section -->
        <div class="glass-panel">
          <div class="panel-header">
            <div>
              <h3 class="panel-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 3v18h18"></path><path d="m19 9-5 5-4-4-3 3"></path></svg>
                Grafik Cashflow Tren Agensi (Inflow vs Outflow)
              </h3>
              <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Tren riwayat arus kas masuk dan biaya operasional 6 bulan terakhir</p>
            </div>
          </div>
          <div style="height: 300px; width: 100%;">
            <canvas id="cashflowChart"></canvas>
          </div>
        </div>

        <!-- Two Column Layout: Project Profitability Leaderboard & Outstanding Receivables -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; align-items: start;">
          
          <!-- Project Profitability Leaderboard -->
          <div class="glass-panel" style="margin-bottom: 0;">
            <div class="panel-header">
              <div>
                <h3 class="panel-title">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                  Project Profitability Leaderboard
                </h3>
                <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Peringkat proyek berdasarkan profit margin & efisiensi biaya produksi</p>
              </div>
              <button class="btn btn-secondary btn-sm" onclick="openModal('modal-create-project')">+ Proyek Baru</button>
            </div>

            <div class="table-responsive">
              <table class="table-custom">
                <thead>
                  <tr>
                    <th>Rank & Nama Proyek</th>
                    <th>Nilai Kontrak</th>
                    <th>Biaya Produksi</th>
                    <th>Net Profit</th>
                    <th>Margin (%)</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($leaderboard)): ?>
                    <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">Belum ada proyek berjalan.</td></tr>
                  <?php else: ?>
                    <?php foreach ($leaderboard as $idx => $p): ?>
                      <tr>
                        <td>
                          <div style="display: flex; align-items: center; gap: 12px;">
                            <?php
                              $rankClass = $idx === 0 ? 'rank-badge-1' : ($idx === 1 ? 'rank-badge-2' : ($idx === 2 ? 'rank-badge-3' : 'rank-badge-other'));
                            ?>
                            <span class="rank-badge <?= $rankClass ?>" title="Peringkat <?= $idx + 1 ?>">
                              <?= $idx === 0 ? '🥇' : ($idx === 1 ? '🥈' : ($idx === 2 ? '🥉' : '#' . ($idx + 1))) ?>
                            </span>
                            <div>
                              <div class="clickable-client-link" onclick="openProjectFinancialDetailModal(<?= $p['id'] ?>, <?= $p['client_id'] ?>)" title="Lihat Rincian Alokasi Keuangan">
                                <span style="font-size: 13.5px;"><?= htmlspecialchars($p['name']) ?></span>
                                <svg class="icon-open" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                              </div>
                              <div style="font-size: 11.5px; color: var(--text-secondary); margin-top: 1px;">
                                <span style="font-weight: 600;"><?= htmlspecialchars($p['client_company']) ?></span> &bull; 
                                <span style="color: #2563EB; font-weight: 600; cursor: pointer;" onclick="openProjectFinancialDetailModal(<?= $p['id'] ?>, <?= $p['client_id'] ?>)">Rincian Biaya ↗</span>
                              </div>
                            </div>
                          </div>
                        </td>
                        <td style="font-weight: 600; color: #101828;"><?= format_rupiah($p['contract_value']) ?></td>
                        <td>
                          <div style="color: var(--danger-text); font-weight: 600;"><?= format_rupiah($p['prod_cost']) ?></div>
                          <div style="font-size: 10.5px; color: var(--text-muted);">Fee: <?= format_rupiah($p['total_freelancer_cost']) ?> | Ads: <?= format_rupiah($p['total_ads_cost']) ?></div>
                        </td>
                        <td style="font-weight: 700; color: <?= $p['profit'] >= 0 ? 'var(--success-text)' : 'var(--danger-text)' ?>;">
                          <?= format_rupiah($p['profit']) ?>
                        </td>
                        <td>
                          <span class="badge-margin <?= $p['margin'] >= $p['target_margin'] ? 'good' : ($p['margin'] >= 15 ? 'warning' : 'bad') ?>">
                            <?= round($p['margin'], 1) ?>%
                          </span>
                          <?php if ($p['is_over_budget']): ?>
                            <div style="font-size: 10px; color: var(--danger-text); font-weight: 700; margin-top: 2px;">Over-Budget!</div>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Outstanding Receivables (Aging Widget) -->
          <div class="glass-panel" style="margin-bottom: 0;">
            <div class="panel-header">
              <div>
                <h3 class="panel-title">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                  Outstanding Receivables
                </h3>
                <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Tagihan klien yang belum masuk</p>
              </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 10px;">
              <?php if (empty($unpaidInvoicesList)): ?>
                <div style="text-align: center; padding: 24px; color: var(--text-muted); font-size: 13px; background: #F9FAFB; border-radius: var(--radius-sm); border: 1px dashed var(--border-color);">
                  Semua invoice klien telah dilunasi! 🎉
                </div>
              <?php else: ?>
                <?php foreach ($unpaidInvoicesList as $u):
                  $isOverdue = (strtotime($u['due_date']) < time() && $u['status'] !== 'Paid');
                ?>
                  <div style="background: #FFFFFF; border: 1px solid <?= $isOverdue ? 'var(--danger-border)' : 'var(--border-color)' ?>; border-radius: 8px; padding: 12px 14px; box-shadow: var(--shadow-xs);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                      <div>
                        <div class="clickable-client-link" onclick="openProjectFinancialDetailModal(<?= !empty($u['project_id']) ? $u['project_id'] : 0 ?>, <?= $u['client_id'] ?>)" title="Lihat Rincian Keuangan Klien">
                          <span style="font-size: 13px; font-weight: 700;"><?= htmlspecialchars($u['client_company']) ?></span>
                          <svg class="icon-open" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                        </div>
                        <div style="font-size: 11px; color: var(--text-secondary);">#<?= htmlspecialchars($u['invoice_number']) ?></div>
                      </div>
                      <span class="badge-status badge-<?= $isOverdue ? 'overdue' : strtolower($u['status']) ?>">
                        <?= $isOverdue ? 'Overdue' : $u['status'] ?>
                      </span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--border-light);">
                      <div style="font-size: 11px; color: var(--text-muted);">
                        Tempo: <?= format_date($u['due_date']) ?>
                      </div>
                      <div style="font-size: 13px; font-weight: 800; color: #101828;">
                        <?= format_rupiah($u['total_amount']) ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- Internal Team & Payroll Summary Widget -->
          <div class="glass-panel" style="grid-column: span 2; margin-top: 20px;">
            <div class="panel-header" style="margin-bottom: 16px;">
              <div>
                <h3 class="panel-title">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="M7 15h0M2 9.5h20"></path><circle cx="16" cy="14" r="2"></circle></svg>
                  Tim Internal & Ringkasan Payroll (Gaji Karyawan)
                </h3>
                <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Pemantauan struktur tim agensi, biaya gaji bulanan, dan status slip gaji</p>
              </div>
              <a href="<?= url('salaries') ?>" class="btn btn-secondary btn-sm" style="font-weight: 700;">
                <span>Kelola Gaji & Slip &rarr;</span>
              </a>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 18px;">
              <div style="background: #F9FAFB; padding: 14px 18px; border-radius: 8px; border: 1px solid var(--border-color); border-left: 4px solid #101828;">
                <div style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase; font-weight: 700;">Total Karyawan Aktif</div>
                <div style="font-size: 20px; font-weight: 800; color: #101828; margin-top: 4px;"><?= $activeEmployeeCount ?> Orang</div>
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Tim internal creative & tech</div>
              </div>

              <div style="background: #F9FAFB; padding: 14px 18px; border-radius: 8px; border: 1px solid var(--border-color); border-left: 4px solid var(--success);">
                <div style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase; font-weight: 700;">Gaji Terbayar Bulan Ini</div>
                <div style="font-size: 20px; font-weight: 800; color: var(--success-text); margin-top: 4px;"><?= format_rupiah($thisMonthSalaryPaid) ?></div>
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Periode <?= date('F Y') ?></div>
              </div>

              <div style="background: #F9FAFB; padding: 14px 18px; border-radius: 8px; border: 1px solid var(--border-color); border-left: 4px solid <?= $pendingSalaryCount > 0 ? 'var(--warning)' : '#D0D5DD' ?>;">
                <div style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase; font-weight: 700;">Antrean Gaji Pending</div>
                <div style="font-size: 20px; font-weight: 800; color: <?= $pendingSalaryCount > 0 ? 'var(--warning-text)' : '#101828' ?>; margin-top: 4px;"><?= format_rupiah($pendingSalaryTotal) ?></div>
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;"><?= $pendingSalaryCount ?> slip menunggu transfer</div>
              </div>
            </div>

            <?php if (!empty($activeEmployees)): ?>
              <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <?php foreach ($activeEmployees as $emp): ?>
                  <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 14px; display: flex; align-items: center; gap: 12px; flex: 1; min-width: 220px; box-shadow: var(--shadow-xs);">
                    <div style="width: 36px; height: 36px; border-radius: 50%; background: #F2F4F7; color: #101828; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; border: 1px solid var(--border-color);">
                      <?= strtoupper(substr($emp['name'], 0, 2)) ?>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                      <div style="font-size: 13px; font-weight: 700; color: #101828; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($emp['name']) ?></div>
                      <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($emp['position'] ?: 'Karyawan') ?> &bull; <span style="color: var(--success-text); font-weight: 600;"><?= format_rupiah($emp['base_salary']) ?></span></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

        </div>

  <?php require_once BASE_PATH . '/includes/footer.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      // Load initial Cashflow chart from analytics API
      fetch('api/analytics.php?range=month')
        .then(res => res.json())
        .then(data => {
          if (data.chart) {
            initCashflowChart(data.chart);
          }
        });
    });
  </script>
</body>
</html>

<?php
/**
 * Clean Total Reset Script
 * Clears all dummy clients, projects, tasks, invoices, ads logs/topups/requests, and non-core accounts.
 * Retains only official Owner and Admin accounts.
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';

$db = Database::getConnection();

echo "=== MEMULAI RESET TOTAL DATA DUMMY & TRANSAKSI ===\n\n";

try {
    $db->beginTransaction();

    // 1. Hapus semua data transaksi finansial, ads, operasional, & laporan
    $tablesToEmpty = [
        'invoices',
        'invoice_items',
        'invoice_payments',
        'freelancer_payouts',
        'ads_spend',
        'ads_budget_logs',
        'ads_topup_transactions',
        'ads_balance_requests',
        'salaries',
        'performance_reports',
        'client_reports',
        'client_monthly_reports',
        'brand_ai_audits',
        'tasks',
        'task_feedbacks',
        'content_planner',
        'content_calendar',
        'activities',
        'employee_clients',
        'employees',
        'projects',
        'clients'
    ];

    foreach ($tablesToEmpty as $table) {
        $db->exec("DELETE FROM `{$table}`");
        echo "[OK] Tabel `{$table}` dikosongkan.\n";
    }

    // 2. Reset akun users: hanya pertahankan Owner dan Admin resmi
    $db->exec("DELETE FROM users WHERE role NOT IN ('owner', 'admin') AND email NOT IN ('owner@kalamedia.id', 'admin@kalamedia.id')");
    echo "[OK] Akun pengguna dummy dihapus, hanya akun resmi Owner dan Admin yang dipertahankan.\n";

    // Pastikan akun Owner dan Admin ada & ter-update
    Database::seedDefaultSystemAccounts($db);
    echo "[OK] Akun resmi Owner dan Admin terverifikasi aktif.\n";

    // 3. Reset auto-increment sequence (SQLite)
    $sequenceTables = implode("','", array_merge($tablesToEmpty, ['users']));
    try {
        $db->exec("DELETE FROM sqlite_sequence WHERE name IN ('{$sequenceTables}')");
        echo "[OK] SQLite auto-increment counter di-reset.\n";
    } catch (\Throwable $e) {}

    $db->commit();
    echo "\n=== SEMUA DATA TRANSAKSI BERHASIL DI-RESET ===\n\n";

    // 4. Bersihkan file bukti transfer/receipt yang diunggah (kecuali sample default & .htaccess)
    $uploadDir = dirname(__DIR__) . '/assets/uploads/receipts';
    $cleanedFiles = 0;
    if (is_dir($uploadDir)) {
        $files = glob($uploadDir . '/*');
        foreach ($files as $f) {
            $base = basename($f);
            if (is_file($f) && $base !== 'sample_receipt.svg' && $base !== '.htaccess') {
                @unlink($f);
                $cleanedFiles++;
            }
        }
    }
    echo "[OK] Membersihkan {$cleanedFiles} berkas bukti transfer upload dummy.\n";

    $reportDir = dirname(__DIR__) . '/assets/uploads/reports';
    $cleanedReports = 0;
    if (is_dir($reportDir)) {
        $files = glob($reportDir . '/*');
        foreach ($files as $f) {
            $base = basename($f);
            if (is_file($f) && $base !== '.htaccess' && !str_ends_with($base, '.svg')) {
                @unlink($f);
                $cleanedReports++;
            }
        }
    }
    echo "[OK] Membersihkan {$cleanedReports} berkas laporan/screenshot upload dummy.\n\n";

    // 5. Verifikasi sisa data akhir
    echo "=== REKAP STATUS DATABASE TERBARU ===\n";
    $verifyTables = [
        'invoices' => 'Invoices / Tagihan',
        'invoice_items' => 'Item Invoice',
        'invoice_payments' => 'Pembayaran Invoice',
        'freelancer_payouts' => 'Payout Freelancer',
        'ads_spend' => 'Pengeluaran Ads',
        'ads_budget_logs' => 'Log Anggaran Ads',
        'ads_topup_transactions' => 'Transaksi Top-Up Ads',
        'ads_balance_requests' => 'Permintaan Saldo Ads',
        'salaries' => 'Penggajian / Payroll',
        'clients' => 'Klien',
        'projects' => 'Proyek',
        'tasks' => 'Tasks / Deliverables',
        'content_planner' => 'Kalender Konten',
        'performance_reports' => 'Laporan Performa',
        'activities' => 'Log Aktivitas',
        'users' => 'Akun Pengguna'
    ];

    foreach ($verifyTables as $tbl => $label) {
        $cnt = $db->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
        echo " - {$label} (`{$tbl}`): {$cnt} data\n";
    }

    $activeUsers = $db->query("SELECT id, name, email, role FROM users")->fetchAll(PDO::FETCH_ASSOC);
    echo "\nAkun Pengguna Resmi yang Tersedia:\n";
    foreach ($activeUsers as $u) {
        echo "   • [ID {$u['id']}] {$u['name']} ({$u['email']}) -> Role: {$u['role']}\n";
    }

    echo "\n🎉 RESET TOTAL SELESAI DENGAN SUKSES! SISTEM 100% BERSIH DAN SIAP UNTUK PRODUKSI.\n";

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "[ERROR] Gagal mereset data: " . $e->getMessage() . "\n";
}

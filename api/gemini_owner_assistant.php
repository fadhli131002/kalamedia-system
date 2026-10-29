<?php
/**
 * Kala AI Owner Executive Copilot API (`api/gemini_owner_assistant.php`)
 * Real-Time Agency Database Context Injection for Owner & Admin:
 * - Invoices & Financial Snapshot (missing invoices this month, unpaid/overdue, revenue/expense/profit)
 * - Client Health, Contract Status, AI Audit Status & Monthly Report Status
 * - Team Tasks, Bottlenecks, Revisions & Overdue Tasks
 * - Client Ad Pool Balances & Low-Balance Alerts
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Sesi berakhir. Silakan login kembali.']);
    exit;
}

$role = current_user_role();
if (!in_array($role, ['owner', 'admin'], true)) {
    echo json_encode(['success' => false, 'message' => 'Fitur Asisten AI Pribadi hanya dapat diakses oleh Owner dan Admin.']);
    exit;
}

$db = Database::getConnection();
$rawInput = file_get_contents('php://input');
$jsonBody = json_decode($rawInput, true);
$data = is_array($jsonBody) ? array_merge($_GET, $_POST, $jsonBody) : array_merge($_GET, $_POST);
$action = trim((string)($data['action'] ?? 'ask'));

/**
 * Helper to build a real-time executive snapshot of the entire Kalamedia database
 */
function buildOwnerAgencySnapshot(PDO $db): array {
    $currentMonth = date('Y-m');
    $today = date('Y-m-d');

    // 1. Clients & Contracts
    $clients = $db->query("
        SELECT id, name, company, email, phone, service_type, package_name, status,
               contract_start_date, contract_end_date, contract_status
        FROM clients
        WHERE is_deleted = 0
        ORDER BY company ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Invoices (All active)
    $invoices = $db->query("
        SELECT i.id, i.client_id, i.invoice_number, i.issue_date, i.due_date, i.total_amount, i.status,
               c.company as client_company, c.name as client_name
        FROM invoices i
        LEFT JOIN clients c ON i.client_id = c.id
        WHERE COALESCE(i.is_deleted, 0) = 0
        ORDER BY i.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $paidRevenueThisMonth = 0.0;
    $paidRevenueAllTime   = 0.0;
    $unpaidInvoices = [];
    $overdueInvoices = [];
    $clientsWithInvoiceThisMonth = [];

    foreach ($invoices as $inv) {
        $amt = (float)($inv['total_amount'] ?? 0);
        $st  = strtolower((string)($inv['status'] ?? ''));
        $issueMonth = substr((string)($inv['issue_date'] ?? ''), 0, 7);
        $clientLabel = $inv['client_company'] ?: ($inv['client_name'] ?: ('Klien #' . $inv['client_id']));

        if ($issueMonth === $currentMonth) {
            $clientsWithInvoiceThisMonth[(int)$inv['client_id']] = true;
        }

        if ($st === 'paid' || $st === 'lunas') {
            $paidRevenueAllTime += $amt;
            if ($issueMonth === $currentMonth) {
                $paidRevenueThisMonth += $amt;
            }
        } else {
            $itemStr = "{$inv['invoice_number']} ({$clientLabel}) - Rp " . number_format($amt, 0, ',', '.') . " [Status: {$inv['status']}, Jatuh Tempo: {$inv['due_date']}]";
            $unpaidInvoices[] = $itemStr;
            if (!empty($inv['due_date']) && $inv['due_date'] < $today) {
                $overdueInvoices[] = $itemStr;
            }
        }
    }

    // Identify clients without any invoice issued this month
    $clientsMissingInvoiceThisMonth = [];
    foreach ($clients as $c) {
        $cid = (int)$c['id'];
        if (empty($clientsWithInvoiceThisMonth[$cid])) {
            $clientsMissingInvoiceThisMonth[] = ($c['company'] ?: $c['name']) . ' (' . ($c['service_type'] ?: $c['package_name']) . ')';
        }
    }

    // 3. Expenses (Freelancer payouts + Salaries + Operational expenses)
    $freelancerTotal = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM freelancer_payouts WHERE COALESCE(is_deleted,0)=0 AND status='Paid'")->fetchColumn();
    $salaryTotal     = (float)$db->query("SELECT COALESCE(SUM(net_salary), 0) FROM salaries WHERE COALESCE(is_deleted,0)=0 AND status='Paid'")->fetchColumn();
    $totalExpenses   = $freelancerTotal + $salaryTotal;

    // 4. Tasks & Team Bottlenecks
    $tasks = $db->query("
        SELECT t.id, t.client_id, t.title, t.category, t.status, t.priority, t.due_date,
               c.company as client_company, u.name as worker_name, u.role as worker_role
        FROM tasks t
        LEFT JOIN clients c ON t.client_id = c.id
        LEFT JOIN users u ON t.assigned_to_user_id = u.id
        WHERE COALESCE(t.is_deleted, 0) = 0
        ORDER BY t.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $revisionTasks = [];
    $overdueTasks  = [];
    $inProgressTasks = [];
    $approvedTaskCount = 0;

    foreach ($tasks as $tk) {
        $st = strtolower((string)$tk['status']);
        $clientLbl = $tk['client_company'] ?: 'Internal';
        $workerLbl = $tk['worker_name'] ?: 'Belum ada PIC';
        if ($st === 'approved' || $st === 'completed' || $st === 'done') {
            $approvedTaskCount++;
        } elseif ($st === 'revision') {
            $revisionTasks[] = "{$tk['title']} (Klien: {$clientLbl}, PIC: {$workerLbl}, Tenggat: " . ($tk['due_date'] ?: '-') . ")";
        } else {
            $inProgressTasks[] = "{$tk['title']} [{$tk['status']}] (Klien: {$clientLbl}, PIC: {$workerLbl})";
            if (!empty($tk['due_date']) && $tk['due_date'] < $today) {
                $overdueTasks[] = "{$tk['title']} (Klien: {$clientLbl}, PIC: {$workerLbl}, Terlambat sejak {$tk['due_date']})";
            }
        }
    }

    // 5. Per-Client Comprehensive Status (Audit AI, Monthly Report, Content Calendar, Ad Pool Balance)
    $clientDetails = [];
    foreach ($clients as $c) {
        $cid = (int)$c['id'];
        $cName = $c['company'] ?: $c['name'];

        // Audit status
        $audStmt = $db->prepare("SELECT brand_stage, health_score, recommended_scheme_name, is_published_to_client FROM brand_ai_audits WHERE client_id = ? ORDER BY id DESC LIMIT 1");
        $audStmt->execute([$cid]);
        $aud = $audStmt->fetch(PDO::FETCH_ASSOC);

        // Monthly report status (current month or latest)
        $repStmt = $db->prepare("SELECT id, report_month, status FROM client_monthly_reports WHERE client_id = ? AND COALESCE(is_deleted,0)=0 ORDER BY report_month DESC, id DESC LIMIT 1");
        $repStmt->execute([$cid]);
        $mRep = $repStmt->fetch(PDO::FETCH_ASSOC);

        // Content calendar count
        $cntStmt = $db->prepare("SELECT COUNT(*) FROM content_planner WHERE client_id = ? AND COALESCE(is_deleted,0)=0");
        $cntStmt->execute([$cid]);
        $contentCount = (int)$cntStmt->fetchColumn();

        // Ads balance
        $paguStmt = $db->prepare("SELECT COALESCE(SUM(ads_budget_pool), 0) FROM projects WHERE client_id = ? AND COALESCE(is_deleted,0)=0");
        $paguStmt->execute([$cid]);
        $pagu = (float)$paguStmt->fetchColumn();

        $spStmt = $db->prepare("SELECT COALESCE(SUM(topup_amount), 0) as topup, COALESCE(SUM(spend_installed), 0) as spend FROM ads_topup_transactions WHERE client_id = ? AND COALESCE(is_deleted,0)=0");
        $spStmt->execute([$cid]);
        $spRow = $spStmt->fetch(PDO::FETCH_ASSOC);
        $spend = max((float)($spRow['spend'] ?? 0), (float)($spRow['topup'] ?? 0));
        $effPagu = $pagu > 0 ? $pagu : $spend;
        $sisaAds = max(0, $effPagu - $spend);

        $clientDetails[] = [
            'client_id' => $cid,
            'company' => $cName,
            'service_type' => $c['service_type'] ?: $c['package_name'],
            'contract_end' => $c['contract_end_date'] ?: '-',
            'has_invoice_this_month' => !empty($clientsWithInvoiceThisMonth[$cid]),
            'ai_audit_status' => $aud ? ("Sudah Dianalisis ({$aud['brand_stage']}, Skor {$aud['health_score']}/100, " . ($aud['is_published_to_client'] ? 'Published ke Klien' : 'Draft Internal') . ")") : 'Belum Dianalisis AI',
            'monthly_report_status' => $mRep ? ("Periode {$mRep['report_month']}: " . ($mRep['status'] === 'published_client' ? 'Approved & Terkirim ke Klien' : 'Draft Menunggu Approval Owner')) : 'Belum Dibuat Bulan Ini',
            'scheduled_content_count' => $contentCount,
            'ads_pagu' => $effPagu,
            'ads_spend' => $spend,
            'ads_remaining' => $sisaAds
        ];
    }

    return [
        'current_date' => $today,
        'current_month' => $currentMonth,
        'financials' => [
            'paid_revenue_this_month' => $paidRevenueThisMonth,
            'paid_revenue_all_time' => $paidRevenueAllTime,
            'total_expenses_paid' => $totalExpenses,
            'net_cashflow_all_time' => $paidRevenueAllTime - $totalExpenses,
            'unpaid_invoices_count' => count($unpaidInvoices),
            'unpaid_invoices_list' => $unpaidInvoices,
            'overdue_invoices_list' => $overdueInvoices,
            'clients_missing_invoice_this_month' => $clientsMissingInvoiceThisMonth
        ],
        'tasks_summary' => [
            'total_tasks' => count($tasks),
            'approved_completed' => $approvedTaskCount,
            'in_progress_count' => count($inProgressTasks),
            'revision_count' => count($revisionTasks),
            'overdue_count' => count($overdueTasks),
            'revision_tasks_list' => array_slice($revisionTasks, 0, 10),
            'overdue_tasks_list' => array_slice($overdueTasks, 0, 10),
            'active_tasks_sample' => array_slice($inProgressTasks, 0, 10)
        ],
        'clients_overview' => $clientDetails
    ];
}

try {
    $snapshot = buildOwnerAgencySnapshot($db);

    if ($action === 'get_snapshot') {
        echo json_encode(['success' => true, 'snapshot' => $snapshot]);
        exit;
    }

    $question = trim((string)($data['question'] ?? ''));
    $history  = $data['history'] ?? [];
    if ($question === '') {
        echo json_encode(['success' => false, 'message' => 'Pertanyaan tidak boleh kosong.']);
        exit;
    }

    $apiKey = getSystemSetting($db, 'gemini_api_key', '');
    $selectedModel = getSystemSetting($db, 'gemini_model', 'gemini-2.5-pro');
    if (empty($apiKey)) {
        echo json_encode([
            'success' => false,
            'message' => 'Google Gemini API Key belum diatur. Silakan atur terlebih dahulu di menu Pengaturan Agensi.'
        ]);
        exit;
    }

    $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $historyText = '';
    if (is_array($history) && !empty($history)) {
        foreach (array_slice($history, -6) as $msg) {
            $r = ($msg['role'] ?? 'user') === 'assistant' ? 'Kala AI' : 'Owner';
            $c = trim((string)($msg['content'] ?? ''));
            if ($c !== '') {
                $historyText .= "{$r}: {$c}\n";
            }
        }
    }

    $prompt = <<<PROMPT
Anda adalah **Kala AI Copilot**, Asisten Eksekutif Pribadi untuk Owner & Admin di agensi digital **Kala Kausa Prima (Kalamedia)**.
Gunakan data real-time dari database agensi di bawah ini untuk menjawab pertanyaan Owner secara akurat, tajam, ringkas, dan berorientasi tindakan (actionable).

ATURAN PENTING:
1. JANGAN gunakan karakter emoji sama sekali di dalam jawaban Anda. Gunakan poin-poin terstruktur yang bersih dan profesional.
2. Sebutkan nama perusahaan/klien, angka rupiah, status invoice, PIC tugas, status laporan bulanan, atau sisa saldo ads secara spesifik sesuai data real-time di bawah ini.
3. Pilih juga 1 hingga 3 tombol aksi cepat (`suggested_actions`) yang paling relevan dengan konteks jawaban Anda dari pilihan `action_type` berikut:
   - `create_invoice`: Jika membahas tagihan/invoice klien yang belum dibuat atau belum lunas.
   - `open_monthly_report`: Jika membahas perkembangan klien, evaluasi bulanan, atau laporan bulanan yang belum dibuat/di-approve.
   - `open_ai_calendar`: Jika membahas jadwal konten, ide konten, atau kalender konten klien.
   - `open_brand_audit`: Jika membahas analisis brand 17 pertanyaan atau strategi klien.
   - `open_tasks`: Jika membahas tugas tim, revisi, atau proyek operasional.

=== DATA REAL-TIME DATABASE KALAMEDIA SAAT INI ===
{$snapshotJson}

=== RIWAYAT PERCAKAPAN SEBELUMNYA ===
{$historyText}

=== PERTANYAAN OWNER SAAT INI ===
{$question}

Kembalikan HANYA JSON valid dengan struktur berikut (tanpa markdown code block):
{
  "reply": "Jawaban lengkap, rapi, terstruktur (gunakan baris baru dan bullet '-' bila perlu, TANPA EMOJI).",
  "highlights": [
    "Ringkasan poin penting / angka kunci 1",
    "Ringkasan poin penting / angka kunci 2"
  ],
  "suggested_actions": [
    {
      "action_type": "create_invoice",
      "label": "Buat Invoice Sekarang",
      "client_id": 0
    },
    {
      "action_type": "open_monthly_report",
      "label": "Buka & Review Report Bulanan Klien",
      "client_id": 0
    },
    {
      "action_type": "open_ai_calendar",
      "label": "Buka Rekomendasi Kalender Konten",
      "client_id": 0
    }
  ]
}
PROMPT;

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.45,
            'maxOutputTokens' => 4096,
            'responseMimeType' => 'application/json'
        ]
    ];

    $geminiResult = kala_call_gemini_with_autodiscovery($apiKey, $selectedModel, $payload, 60, $db);
    if (!$geminiResult['success']) {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal menghubungi Kala AI Copilot: ' . ($geminiResult['error_message'] ?? 'Unknown error')
        ]);
        exit;
    }

    $rawText = trim((string)$geminiResult['raw_text']);
    $cleanJson = preg_replace('/^```(?:json)?\s*/i', '', $rawText);
    $cleanJson = preg_replace('/\s*```$/', '', $cleanJson);
    $parsed = json_decode($cleanJson, true);

    if (!is_array($parsed) || empty($parsed['reply'])) {
        if (preg_match('/\{[\s\S]*\}/', $rawText, $m)) {
            $parsed = json_decode($m[0], true);
        }
    }

    if (!is_array($parsed) || empty($parsed['reply'])) {
        $parsed = [
            'reply' => strip_tags($rawText),
            'highlights' => [],
            'suggested_actions' => [
                ['action_type' => 'create_invoice', 'label' => 'Buat Invoice Sekarang', 'client_id' => 0],
                ['action_type' => 'open_monthly_report', 'label' => 'Buka & Review Report Bulanan Klien', 'client_id' => 0]
            ]
        ];
    }

    echo json_encode([
        'success' => true,
        'model_used' => $geminiResult['used_model'],
        'reply' => $parsed['reply'],
        'highlights' => $parsed['highlights'] ?? [],
        'suggested_actions' => $parsed['suggested_actions'] ?? []
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Kesalahan server Copilot: ' . $e->getMessage()
    ]);
}


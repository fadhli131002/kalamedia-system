<?php
session_start();
$_SESSION['logged_in'] = true;
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Muhammad Fadhli',
    'email' => 'owner@kalamedia.id',
    'role' => 'owner'
];

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// Prepare POST data similar to user's form
$_POST = [
    'action' => 'save',
    'client_id' => 1,
    'report_period' => 'August 2026',
    'objective' => 'Advantage+ Meta Ads Campaign',
    'total_ad_spend' => '971120',
    'revenue' => '15000000',
    'total_conversions' => '398',
    'ads_reach' => '12450',
    'ads_impressions' => '32100',
    'ads_ctr' => '3.85',
    'ads_cpc' => '2440',
    'ads_cpm' => '30250',
    'lost_is_rank' => '5.2',
    'lost_is_budget' => '10.5',
    'ads_evaluation' => 'Evaluasi Advantage+ Meta Ads Campaign berjalan dengan baik.',
    'content_identity' => 'Video ThruPlays Reels Highlight',
    'total_views' => '3968',
    'followers_gained' => '450',
    'avg_video_retention' => '52.4',
    'engagement_rate' => '6.8',
    'what_worked' => "Efisiensi Penargetan Advantage+ Audience: Penggunaan Advantage+ Audience terbukti efektif dalam menemukan audiens yang tepat, menghasilkan 398 percakapan pesan dimulai dengan biaya per hasil yang kompetitif sebesar Rp2.440.\n\nDaya Tarik Visual Awal: Video Anda berhasil menarik perhatian dengan 3.968 ThruPlays, menunjukkan bahwa audiens tertarik untuk menonton konten Anda di platform Instagram.",
    'what_didnt_work' => "Penurunan Retensi Video: Terjadi penurunan drastis pada jumlah penonton video, di mana hanya 768 orang yang menonton hingga selesai (100%) dibandingkan 4.186 orang di titik 25%. Ini menandakan konten di bagian tengah hingga akhir kurang mampu mempertahankan minat audiens.\nKebocoran Konversi: Terdapat selisih yang besar antara 1.332 klik (semua) dengan 398 percakapan yang dimulai.\nKeterbatasan Penempatan: Iklan hanya berjalan di Instagram, sehingga kehilangan potensi biaya yang lebih murah di penempatan lain seperti Facebook atau Messenger.",
    'next_action_plan' => "Optimasi Kreatif Video: Revisi materi iklan video dengan meletakkan pesan utama atau penawaran di 3–5 detik pertama untuk mengatasi masalah retensi yang rendah.\nEkspansi Penempatan: Aktifkan Advantage+ Placements untuk memberikan fleksibilitas pada sistem dalam mencari biaya per hasil terendah di seluruh ekosistem Meta.\nUji Coba Iklan Baru: Karena iklan B tidak mendapatkan distribusi, ganti dengan variasi materi iklan baru untuk menemukan \"winning creative\" tambahan sebelum melakukan scaling anggaran."
];

register_shutdown_function(function() {
    $out = ob_get_contents();
    $data = json_decode($out, true);
    if ($data) {
        echo "API SAVE RESPONSE:\n";
        echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
        echo "Message: " . ($data['message'] ?? '') . "\n";
        echo "Report ID: " . ($data['report_id'] ?? 0) . "\n";
        echo "Redirect: " . ($data['redirect'] ?? '') . "\n";
    } else {
        echo "Raw output: " . $out . "\n";
    }
});

ob_start();
require __DIR__ . '/../api/reports.php';

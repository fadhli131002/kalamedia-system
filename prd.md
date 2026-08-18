Product Requirement Document (PRD)
Project Name: Agency Financial & Project Management System
Document Version: 1.0
Target Audience: Developer / Implementator, Agency Owner
1. Executive Summary
Sistem ini adalah platform manajemen internal (Agency Management System) yang dirancang untuk mengatasi masalah pencatatan keuangan manual di creative agency. Platform ini memusatkan pembuatan invoice klien, pelacakan pembayaran (inflow), pembayaran fee freelancer (outflow), serta pengeluaran iklan (ads spend). Sistem menggunakan pendekatan Role-Based Access Control (RBAC) dengan pemisahan yang jelas antara hak akses Owner dan Admin/Finance.
2. User Roles & Permissions
Sistem ini memiliki 2 peran utama dengan akses yang disesuaikan:
Role
Deskripsi & Wewenang
Owner
Pemilik agensi. Memiliki akses penuh (Super Admin). Fokus utama adalah melihat ringkasan performa finansial, margin profit, dan analitik bisnis secara keseluruhan.
Admin / Finance
Staf operasional/keuangan. Fokus utama adalah input data operasional harian: membuat project, men-generate invoice, mencatat uang masuk, membayar freelancer, top-up ads, dan mengunggah bukti transfer. Tidak memiliki akses untuk menghapus data secara permanen atau melihat analitik profit global (opsional).

3. Spesifikasi Fitur Detail
3.1. Modul Autentikasi (Login & Security)
Deskripsi: Pintu masuk sistem yang aman dengan pengalihan otomatis (auto-routing) berdasarkan peran pengguna.
Fitur & Kebutuhan:
Halaman Login Login: Form login minimalis yang berisi input Email dan Password, serta tombol "Masuk".
Role-Based Routing:
Jika yang login adalah Owner -> otomatis diarahkan ke Owner Dashboard.
Jika yang login adalah Admin -> otomatis diarahkan ke Admin Dashboard.
Forgot Password: Alur pemulihan kata sandi via email.
Session Management: Auto-logout jika sistem tidak ada aktivitas (idle) selama 2 jam.
3.2. Owner Dashboard (Executive View)
Deskripsi: Pusat kendali untuk Owner agensi. Tampilannya berfokus pada data analitik, metrik utama bisnis, dan profitabilitas, bukan untuk pekerjaan input data harian (meskipun Owner tetap bisa melakukan input jika mau).
Komponen UI/UX & Fungsionalitas:
Top Navigation / Header:
Sapaan: "Halo, [Nama Owner]"
Filter Rentang Waktu (Bulan Ini, Kuartal Ini, Tahun Ini).
Key Performance Indicators (KPI) Cards: Empat kartu ringkasan di bagian atas.
Total Revenue: Total uang masuk dari klien (berdasarkan invoice yang statusnya 'Paid').
Total Expense: Total uang keluar (Fee Freelancer + Top-up Ads).
Net Profit: (Total Revenue - Total Expense). Diberi indikator persentase margin laba.
Outstanding Receivables: Total uang klien yang belum dibayar (Invoice 'Unpaid'/'Overdue').
Grafik Cashflow (Visual Chart):
Grafik garis/batang (Line/Bar Chart) membandingkan Pemasukan vs Pengeluaran dari minggu ke minggu atau bulan ke bulan.
Project Profitability Monitor:
Tabel peringkat project yang sedang berjalan.
Kolom: Nama Project, Klien, Total Nilai Kontrak, Biaya Produksi (Freelance+Ads), Margin Profit (%).
Fitur: Owner bisa langsung melihat jika ada project yang over-budget (misal margin di bawah target 30%).
Recent High-Level Activities:
Log aktivitas penting: "Klien A baru saja melunasi Invoice #123", "Admin B membayar Fee Freelancer C".
3.3. Admin / Finance Dashboard (Operational View)
Deskripsi: Ruang kerja utama staf Admin/Finance. Desainnya difokuskan pada Call-to-Action (CTA), antrean pekerjaan (to-do list), dan kemudahan input data.
Komponen UI/UX & Fungsionalitas:
Quick Action Buttons (Tombol Cepat):
[+ Buat Invoice Baru]
[+ Catat Pembayaran Masuk]
[+ Input Fee Freelancer]
[+ Catat Top-Up Ads]
Attention Required (Alert Cards): Kartu notifikasi peringatan tugas yang belum selesai.
Unpaid Invoices: Jumlah dan nilai invoice klien yang jatuh tempo (overdue) atau belum lunas.
Pending Payouts: Daftar freelancer yang pekerjaannya sudah selesai tapi fee belum ditransfer.
Low Ads Balance (Opsional): Peringatan jika sisa saldo iklan klien di bawah batas minimal.
Missing Receipts: Daftar transaksi tercatat yang belum diunggah bukti transfernya (attachment missing).
Tabel Transaksi Berjalan (Active Queue):
Daftar transaksi terbaru (masuk/keluar).
Kolom: Tanggal, Tipe Transaksi (Inflow/Outflow), Deskripsi, Nominal, Status.
Fitur langsung dari tabel: Klik tombol "Upload Bukti" -> muncul pop-up untuk melampirkan foto struk m-banking.
Pencarian & Filter Cepat:
Fitur pencarian berdasarkan nomor rekening, nama klien, nomor invoice, atau nama freelancer untuk mempercepat pencocokan mutasi rekening dengan data sistem.
3.4. Modul Manajemen Inti (Tersedia untuk Owner & Admin)
A. Modul Invoice (Penagihan Klien)
Form dinamis untuk input nama klien, pilihan jasa, kuantitas (qty), dan harga satuan.
Sistem menghitung otomatis subtotal, diskon (jika ada), dan total tagihan.
Tombol "Generate PDF" untuk mengunduh invoice siap kirim dengan desain profesional berlogo agensi.
Status tracking: Draft -> Sent -> Paid.
B. Modul Pengeluaran (Freelancer & Ads)
Freelancer Payout Form: Pilih nama freelancer -> Pilih Project terkait -> Input fee -> Upload struk transfer bank -> Status berubah menjadi Paid.
Ads Top-Up Form: Pilih Klien/Project -> Pilih Platform (Google/Meta/TikTok) -> Input nominal -> Upload struk top-up/tagihan kartu kredit.
4. Acceptance Criteria (Kriteria Sukses Aplikasi)
Aplikasi dianggap siap digunakan jika:
Admin dapat membuat invoice dalam waktu kurang dari 2 menit dan file PDF terbuat tanpa cacat.
Setiap kali Admin mengunggah bukti transfer fee freelancer atau penerimaan dana klien, data pada Owner Dashboard (Net Profit & Cashflow) langsung ter-update secara real-time.
Admin tidak dapat melihat atau memodifikasi pengaturan akun Owner.
Sistem tidak error saat Admin mengunggah file gambar (JPEG/PNG) struk transfer berukuran maksimal 5MB.
5. Rekomendasi Alur Navigasi (Sitemap)
/login
/owner-dashboard (Restricted: Owner Only)
/admin-dashboard (Restricted: Admin & Owner)
/clients (Database Klien & Proyek)
/invoices (Manajemen Penagihan)
/expenses (Manajemen Pengeluaran Freelancer & Ads)

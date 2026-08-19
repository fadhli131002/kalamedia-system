<?php
/**
 * Kalamedia Database Connection & Auto-Migrator
 * Supports SQLite (Zero-Config, Instant) & MySQL
 */

require_once __DIR__ . '/app.php';

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dbPath = BASE_PATH . '/database/kalamedia.sqlite';
            $dbDir = dirname($dbPath);
            
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0777, true);
            }

            // Default to SQLite for zero-friction out-of-the-box operation
            try {
                self::$instance = new PDO("sqlite:" . $dbPath, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 5
                ]);
                self::$instance->exec("PRAGMA foreign_keys = ON;");
                
                // Check if schema or new migrations are needed
                $hasMeta = self::$instance->query("SELECT name FROM sqlite_master WHERE type='table' AND name='system_meta'")->fetchColumn();
                if (!$hasMeta) {
                    self::ensureTablesAndSeed(self::$instance);
                } else {
                    self::runMigrations(self::$instance);
                }
            } catch (PDOException $e) {
                die("Database Connection Error: " . $e->getMessage());
            }
        }
        return self::$instance;
    }

    private static function ensureTablesAndSeed(PDO $db): void {
        // Initialize Schema (Idempotent: CREATE TABLE IF NOT EXISTS)

        // Initialize Schema
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(150) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'admin', -- 'owner' or 'admin'
                avatar VARCHAR(255) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(150) NOT NULL,
                company VARCHAR(150) NOT NULL,
                email VARCHAR(150) NOT NULL,
                phone VARCHAR(50) NOT NULL,
                address TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                name VARCHAR(200) NOT NULL,
                contract_value DECIMAL(15,2) NOT NULL DEFAULT 0,
                target_margin_percent DECIMAL(5,2) DEFAULT 30.00,
                status VARCHAR(30) DEFAULT 'In Progress', -- 'Planning', 'In Progress', 'Completed', 'On Hold'
                start_date DATE DEFAULT NULL,
                end_date DATE DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS invoices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_number VARCHAR(50) UNIQUE NOT NULL,
                client_id INTEGER NOT NULL,
                project_id INTEGER DEFAULT NULL,
                issue_date DATE NOT NULL,
                due_date DATE NOT NULL,
                subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
                discount_percent DECIMAL(5,2) DEFAULT 0,
                discount_amount DECIMAL(15,2) DEFAULT 0,
                tax_percent DECIMAL(5,2) DEFAULT 0,
                tax_amount DECIMAL(15,2) DEFAULT 0,
                total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                status VARCHAR(20) DEFAULT 'Draft', -- 'Draft', 'Sent', 'Paid', 'Overdue'
                paid_at DATETIME DEFAULT NULL,
                payment_method VARCHAR(50) DEFAULT NULL,
                receipt_file VARCHAR(255) DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                is_deleted INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
            );

            CREATE TABLE IF NOT EXISTS invoice_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_id INTEGER NOT NULL,
                service_name VARCHAR(200) NOT NULL,
                quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
                unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
                total_price DECIMAL(15,2) NOT NULL DEFAULT 0,
                FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS freelancer_payouts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                freelancer_name VARCHAR(150) NOT NULL,
                freelancer_bank VARCHAR(100) DEFAULT NULL,
                freelancer_account VARCHAR(100) DEFAULT NULL,
                project_id INTEGER NOT NULL,
                task_description TEXT NOT NULL,
                amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                status VARCHAR(20) DEFAULT 'Pending', -- 'Pending', 'Paid'
                paid_at DATETIME DEFAULT NULL,
                receipt_file VARCHAR(255) DEFAULT NULL,
                is_deleted INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS ads_spend (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                project_id INTEGER DEFAULT NULL,
                platform VARCHAR(50) NOT NULL, -- 'Meta Ads', 'Google Ads', 'TikTok Ads', 'LinkedIn Ads'
                account_id VARCHAR(100) DEFAULT NULL,
                amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                spent_date DATE NOT NULL,
                receipt_file VARCHAR(255) DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                is_deleted INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
            );

            CREATE TABLE IF NOT EXISTS employees (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(150) NOT NULL,
                position VARCHAR(100) NOT NULL,
                department VARCHAR(100) DEFAULT 'Creative & Tech',
                employment_type VARCHAR(50) DEFAULT 'Full-time', -- 'Full-time', 'Part-time', 'Contract', 'Intern'
                email VARCHAR(150) DEFAULT NULL,
                phone VARCHAR(50) DEFAULT NULL,
                bank_name VARCHAR(100) DEFAULT 'BCA',
                bank_account VARCHAR(100) DEFAULT NULL,
                base_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
                status VARCHAR(20) DEFAULT 'Active', -- 'Active', 'Inactive'
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS salaries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER DEFAULT NULL,
                employee_name VARCHAR(150) NOT NULL,
                employee_position VARCHAR(100) DEFAULT NULL,
                bank_name VARCHAR(100) DEFAULT 'BCA',
                bank_account VARCHAR(100) DEFAULT NULL,
                month_period VARCHAR(20) NOT NULL, -- e.g. '2026-08' or 'Agustus 2026'
                base_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
                allowance DECIMAL(15,2) NOT NULL DEFAULT 0, -- Tunjangan, Bonus, Lembur
                deduction DECIMAL(15,2) NOT NULL DEFAULT 0, -- Potongan, BPJS, PPh21, Kasbon
                net_salary DECIMAL(15,2) NOT NULL DEFAULT 0, -- Take-Home Pay (base + allowance - deduction)
                payment_date DATE NOT NULL,
                status VARCHAR(20) DEFAULT 'Pending', -- 'Pending', 'Paid'
                paid_at DATETIME DEFAULT NULL,
                is_deleted INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
            );

            CREATE TABLE IF NOT EXISTS content_planner (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                project_id INTEGER DEFAULT NULL,
                title VARCHAR(255) NOT NULL,
                platform VARCHAR(50) NOT NULL DEFAULT 'Instagram',
                content_type VARCHAR(50) NOT NULL DEFAULT 'Reels / Video',
                publish_date DATE NOT NULL,
                publish_time TIME DEFAULT '10:00:00',
                status VARCHAR(30) NOT NULL DEFAULT 'Draft',
                assignee_id INTEGER DEFAULT NULL,
                asset_url TEXT DEFAULT NULL,
                color_hex VARCHAR(20) DEFAULT '#3B82F6',
                notes TEXT DEFAULT NULL,
                is_deleted INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
                FOREIGN KEY (assignee_id) REFERENCES employees(id) ON DELETE SET NULL
            );

            CREATE TABLE IF NOT EXISTS performance_reports (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                report_period VARCHAR(100) NOT NULL,
                objective VARCHAR(255) DEFAULT NULL,
                total_ad_spend DECIMAL(15,2) NOT NULL DEFAULT 0,
                revenue DECIMAL(15,2) NOT NULL DEFAULT 0,
                roas DECIMAL(5,2) NOT NULL DEFAULT 0,
                roi DECIMAL(5,2) NOT NULL DEFAULT 0,
                total_conversions INT NOT NULL DEFAULT 0,
                cpl_cpa DECIMAL(15,2) NOT NULL DEFAULT 0,
                ads_reach INT NOT NULL DEFAULT 0,
                ads_impressions INT NOT NULL DEFAULT 0,
                ads_ctr DECIMAL(5,2) NOT NULL DEFAULT 0,
                ads_cpc DECIMAL(10,2) NOT NULL DEFAULT 0,
                ads_cpm DECIMAL(10,2) NOT NULL DEFAULT 0,
                lost_is_rank DECIMAL(5,2) NOT NULL DEFAULT 0,
                lost_is_budget DECIMAL(5,2) NOT NULL DEFAULT 0,
                ads_evaluation TEXT DEFAULT NULL,
                content_identity TEXT DEFAULT NULL,
                total_views INT NOT NULL DEFAULT 0,
                followers_gained INT NOT NULL DEFAULT 0,
                avg_video_retention DECIMAL(5,2) NOT NULL DEFAULT 0,
                engagement_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
                winning_content_url VARCHAR(255) DEFAULT NULL,
                underperforming_content_url VARCHAR(255) DEFAULT NULL,
                what_worked TEXT DEFAULT NULL,
                what_didnt_work TEXT DEFAULT NULL,
                next_action_plan TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_deleted INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS client_reports (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                report_month VARCHAR(50) NOT NULL, -- e.g. 'August 2026'
                executive_summary TEXT,
                followers_growth INTEGER NOT NULL DEFAULT 0,
                total_reach INTEGER NOT NULL DEFAULT 0,
                total_impressions INTEGER NOT NULL DEFAULT 0,
                engagement_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                saves_shares INTEGER NOT NULL DEFAULT 0,
                link_clicks INTEGER NOT NULL DEFAULT 0,
                leads_generated INTEGER NOT NULL DEFAULT 0,
                top_content_summary TEXT,
                action_plan TEXT,
                is_deleted INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS activities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER DEFAULT NULL,
                type VARCHAR(50) NOT NULL, -- 'invoice', 'payout', 'ads', 'client', 'project', 'salary', 'report'
                title VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                amount DECIMAL(15,2) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS system_meta (
                key VARCHAR(50) PRIMARY KEY,
                value TEXT
            );
        ");

        // Run migrations on existing tables (idempotent)
        self::runMigrations($db);

        // Check if database was already seeded once
        $isSeeded = $db->query("SELECT value FROM system_meta WHERE key = 'is_seeded'")->fetchColumn();
        if ($isSeeded) {
            return; // Already seeded once. NEVER auto re-seed when user deletes data!
        }

        // Seed Default Users if empty
        $userCount = intval($db->query("SELECT COUNT(*) FROM users")->fetchColumn());
        if ($userCount === 0) {
            $hashedPw = password_hash('password123', PASSWORD_BCRYPT);
            $userStmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $userStmt->execute(['Muhammad Fadhli', 'owner@kalamedia.id', $hashedPw, 'owner']);
            $userStmt->execute(['Ilham Lanang', 'finance@kalamedia.id', $hashedPw, 'admin']);
        }

        // Seed Default Employees if empty
        $empCount = intval($db->query("SELECT COUNT(*) FROM employees")->fetchColumn());
        if ($empCount === 0) {
            $empStmt = $db->prepare("
                INSERT INTO employees (name, position, department, employment_type, email, phone, bank_name, bank_account, base_salary, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')
            ");
            $empStmt->execute(['Muhammad Fadhli', 'Creative Manager', 'Creative & Production', 'Full-time', 'mha.fadhli@gmail.com', '081298765432', 'BCA', '5420987123', 12000000]);
            $empStmt->execute(['Ilham Lanang', 'Marketing Manager', 'Marketing & Operations', 'Full-time', 'ilhamlanang30@gmail.com', '081387654321', 'Bank Jago', '107577583322', 12000000]);
            $empStmt->execute(['Dimas Prasetyo', 'Senior Graphic Designer', 'Creative Design', 'Full-time', 'dimas@kalamedia.id', '081298765431', 'BCA', '5420987123', 7500000]);
            $empStmt->execute(['Annisa Nuraini', 'Social Media Specialist', 'Digital Marketing', 'Full-time', 'annisa@kalamedia.id', '081387654321', 'BCA', '5420112233', 6500000]);
            $empStmt->execute(['Kevin Pratama', 'Motion & Video Editor', 'Video Production', 'Full-time', 'kevin@kalamedia.id', '081277665544', 'Mandiri', '1370019283746', 7000000]);
            $empStmt->execute(['Bima Satria', 'Fullstack Web Developer', 'Technology & IT', 'Full-time', 'bima@kalamedia.id', '081566778899', 'BCA', '5420778899', 8500000]);
        }

        // Seed Default Salaries if empty
        $salCount = intval($db->query("SELECT COUNT(*) FROM salaries")->fetchColumn());
        if ($salCount === 0) {
            $currentMonth = date('Y-m');
            $salStmt = $db->prepare("
                INSERT INTO salaries (
                    employee_id, employee_name, employee_position, bank_name, bank_account,
                    month_period, base_salary, allowance, deduction, net_salary, payment_date, status, paid_at, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            // Paid record 1
            $salStmt->execute([1, 'Dimas Prasetyo', 'Senior Graphic Designer', 'BCA', '5420987123', $currentMonth, 7500000, 500000, 250000, 7750000, date('Y-m-25'), 'Paid', date('Y-m-25 10:30:00'), 'Gaji Pokok + Bonus Desain Project']);
            // Paid record 2
            $salStmt->execute([2, 'Annisa Nuraini', 'Social Media Specialist', 'BCA', '5420112233', $currentMonth, 6500000, 300000, 0, 6800000, date('Y-m-25'), 'Paid', date('Y-m-25 11:15:00'), 'Gaji Pokok + Tunjangan Komunikasi']);
            // Pending record 3
            $salStmt->execute([3, 'Kevin Pratama', 'Motion & Video Editor', 'Mandiri', '1370019283746', $currentMonth, 7000000, 400000, 150000, 7250000, date('Y-m-28'), 'Pending', null, 'Payroll Akhir Bulan']);
            // Pending record 4
            $salStmt->execute([4, 'Bima Satria', 'Fullstack Web Developer', 'BCA', '5420778899', $currentMonth, 8500000, 500000, 200000, 8800000, date('Y-m-28'), 'Pending', null, 'Payroll Akhir Bulan + Bonus Tech']);
        }

        // Seed Performance Reports
        self::seedPerformanceReports($db);

        // Seed Default Client Reports if empty
        $reportCount = intval($db->query("SELECT COUNT(*) FROM client_reports")->fetchColumn());
        if ($reportCount === 0) {
            self::seedDefaultReports($db);
        }

        // Mark as seeded so subsequent deletions stay permanently deleted
        $db->exec("INSERT OR REPLACE INTO system_meta (key, value) VALUES ('is_seeded', '1')");
    }

    public static function seedPerformanceReports(PDO $db): void {
        // Ensure client 1: Grand BSD Residence (Real Estate Project)
        $cRealEstate = $db->query("SELECT id FROM clients WHERE company LIKE '%Grand BSD%' LIMIT 1")->fetchColumn();
        if (!$cRealEstate) {
            $db->exec("INSERT INTO clients (name, company, email, phone, address) VALUES ('Clarissa Tan', 'Grand BSD Residence', 'clarissa@grandbsd.co.id', '081399881122', 'Marketing Gallery Grand BSD, Jl. BSD Raya Utama No. 8, Tangerang')");
            $cRealEstate = $db->lastInsertId();
        }

        // Ensure client 2: Lucere Official (Hijab & Modest Fashion Brand)
        $cLucere = $db->query("SELECT id FROM clients WHERE company LIKE '%Lucere%' LIMIT 1")->fetchColumn();
        if (!$cLucere) {
            $db->exec("INSERT INTO clients (name, company, email, phone, address) VALUES ('Nabila Putri', 'Lucere Official', 'nabila@lucerehijab.com', '081289901234', 'Headquarters Lucere, Jl. Senopati No. 45, Kebayoran Baru, Jakarta Selatan')");
            $cLucere = $db->lastInsertId();
        }

        // Check if performance_reports has data
        $perfCount = intval($db->query("SELECT COUNT(*) FROM performance_reports WHERE COALESCE(is_deleted, 0) = 0")->fetchColumn());
        if ($perfCount === 0) {
            $stmt = $db->prepare("
                INSERT INTO performance_reports (
                    client_id, report_period, objective,
                    total_ad_spend, revenue, roas, roi, total_conversions, cpl_cpa,
                    ads_reach, ads_impressions, ads_ctr, ads_cpc, ads_cpm,
                    lost_is_rank, lost_is_budget, ads_evaluation,
                    content_identity, total_views, followers_gained, avg_video_retention, engagement_rate,
                    winning_content_url, underperforming_content_url,
                    what_worked, what_didnt_work, next_action_plan
                ) VALUES (
                    ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?,
                    ?, ?, ?
                )
            ");

            // 1. Real Estate Project: Grand BSD Residence (Lead Generation & 60 Units Closing)
            $stmt->execute([
                $cRealEstate,
                'August 2026',
                'Lead Generation & Closing Unit Cluster Horizon',
                45000000.00,       // Total Ad Spend: Rp 45.000.000
                1800000000.00,     // Revenue / Omset Booking & DP: Rp 1.800.000.000
                40.00,             // ROAS: 40.00x
                3900.00,           // ROI: 3,900%
                60,                // Total Conversions (60 Closing Units)
                31034.48,          // CPL (Cost Per Lead): Rp 31.034 / CPA Closing: Rp 750.000
                345000,            // Ads Reach
                890000,            // Ads Impressions
                3.85,              // CTR: 3.85%
                1312.00,           // CPC: Rp 1.312
                50560.00,          // CPM: Rp 50.560
                6.40,              // Lost IS (Rank): 6.40%
                12.80,             // Lost IS (Budget): 12.80%
                'Struktur kampanye Instant Form Meta Ads dengan filter qualifying questions (penghasilan > Rp 15 Jt/bln & rencana beli < 3 bulan) sukses menekan junk leads hingga 82% dan mendongkrak booking show unit langsung ke sales gallery.',
                'Virtual Room Tour 360° Unit Hook 2-Lantai & Testimoni Serah Terima Unit Tipe 72/90',
                480000,            // Total Views
                2950,              // Followers Gained
                52.40,             // Avg Video Retention: 52.40%
                5.85,              // Engagement Rate: 5.85%
                'assets/uploads/reports/creative_realestate_win.svg',
                'assets/uploads/reports/creative_realestate_lose.svg',
                "• Hook 3 detik pertama dengan angle 'Beli Rumah Cicilan 3 Jutaan Free Biaya KPR' menghasilkan CTR 4.2%.\n• Format video Reels POV walk-through show unit menghasilkan 68% total qualified leads.\n• Custom Audience Lookalike 1% dari database pembeli terdahulu menghasilkan CPL paling efisien (Rp 28.500).",
                "• Single Image ad banner statis memiliki bounce rate tinggi (CTR < 1.1%) dan CPL membengkak ke Rp 85.000.\n• Penargetan audiens broad interest umum 'Property Investment' terlalu banyak menghasilkan junk leads dari luar Jabodetabek.",
                "1. Scale budget 30% pada ad set winning creative 'Video Tour Hook 2 Lantai'.\n2. Produksi 4 variasi video UGC testimoni serah terima kunci cluster terbaru.\n3. Implementasi WhatsApp Business Automation CRM untuk fast response < 5 menit bagi seluruh lead baru."
            ]);

            // 2. Fashion / Hijab Brand: Lucere Official (E-Commerce ROAS Scale-up)
            $stmt->execute([
                $cLucere,
                'August 2026',
                'E-Commerce ROAS Scale-up & Ramadhan Silk Launch',
                38000000.00,       // Total Ad Spend: Rp 38.000.000
                266000000.00,      // Revenue / Omset: Rp 266.000.000
                7.00,              // ROAS: 7.00x
                600.00,            // ROI: 600%
                1820,              // Total Conversions (1,820 Orders)
                20879.12,          // CPA (Cost Per Acquisition): Rp 20.879
                580000,            // Ads Reach
                1620000,           // Ads Impressions
                4.45,              // CTR: 4.45%
                880.00,            // CPC: Rp 880
                23450.00,          // CPM: Rp 23.450
                4.20,              // Lost IS (Rank): 4.20%
                16.50,             // Lost IS (Budget): 16.50%
                'Kombinasi Advantage+ Shopping Campaigns (ASC) di Meta Ads dan Spark Ads di TikTok berhasil menjangkau repeat buyer scarf silk dengan AOV Rp 146.000 dan Return on Ad Spend stabil di atas 7.00x.',
                'Styling 5 Menit Pashmina Silk Shimmer & ASMR Unboxing Exclusive Box',
                1150000,           // Total Views
                7450,              // Followers Gained
                64.80,             // Avg Video Retention: 64.80%
                8.20,              // Engagement Rate: 8.20%
                'assets/uploads/reports/creative_lucere_win.svg',
                'assets/uploads/reports/creative_lucere_lose.svg',
                "• Format Reels 'Styling Tutorial 3 Cara Pakai Hijab Segiempat' viral dengan 420K views organik & mendongkrak purchase ad score ke Excellent.\n• Bundle offer 'Beli 3 Gratis Pouch' menaikkan keranjang belanja AOV sebesar 35%.\n• Retargeting Website 14-day Add-to-Cart audiens mengonversi dengan ROAS 11.4x.",
                "• Foto Flatlay katalog produk tanpa model manusia mendapatkan click rate rendah (CTR 0.85%).\n• Diskon flash sale direct-to-checkout tanpa teaser storytelling kurang efektif membangun brand equity.",
                "1. Luncurkan bundling New Signature Silk Motif Collection dengan seeding ke 15 micro-influencer hijab.\n2. Alokasikan 20% budget untuk live shopping TikTok Ads retargeting pada jam 19.00 - 22.00 WIB.\n3. Uji coba lead generation program VIP Reseller / Membership di WhatsApp."
            ]);
        }
    }

    public static function seedDefaultReports(PDO $db): void {
        self::seedPerformanceReports($db);
        
        $db->exec("
            CREATE TABLE IF NOT EXISTS client_reports (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                report_month VARCHAR(50) NOT NULL,
                executive_summary TEXT,
                followers_growth INTEGER NOT NULL DEFAULT 0,
                total_reach INTEGER NOT NULL DEFAULT 0,
                total_impressions INTEGER NOT NULL DEFAULT 0,
                engagement_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                saves_shares INTEGER NOT NULL DEFAULT 0,
                link_clicks INTEGER NOT NULL DEFAULT 0,
                leads_generated INTEGER NOT NULL DEFAULT 0,
                top_content_summary TEXT,
                action_plan TEXT,
                is_deleted INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
            );
        ");

        // Ensure standard clients exist for rich reporting
        $c1 = $db->query("SELECT id FROM clients WHERE company LIKE '%Prima Pasir%' LIMIT 1")->fetchColumn();
        if (!$c1) {
            $db->exec("INSERT INTO clients (name, company, email, phone, address) VALUES ('Bang Akbar', 'Prima Pasir Mandiri', 'akbar@primapasir.com', '0895361622252', 'Kp Cisauk Girang, RT 05/RW 05, Kec. Cisauk, Kab. Tangerang, Banten')");
            $c1 = $db->lastInsertId();
        }

        $c2 = $db->query("SELECT id FROM clients WHERE company LIKE '%Autolux%' LIMIT 1")->fetchColumn();
        if (!$c2) {
            $db->exec("INSERT INTO clients (name, company, email, phone, address) VALUES ('Hendra Wijaya', 'Autolux Detailing & Bodyworks', 'hendra@autolux.id', '081288997766', 'Jl. Boulevard Gading Serpong Kav. 22, Tangerang')");
            $c2 = $db->lastInsertId();
        }

        $c3 = $db->query("SELECT id FROM clients WHERE company LIKE '%Grand BSD%' LIMIT 1")->fetchColumn();
        if (!$c3) {
            $db->exec("INSERT INTO clients (name, company, email, phone, address) VALUES ('Clarissa Tan', 'Grand BSD Residence', 'clarissa@grandbsd.co.id', '081399881122', 'Marketing Gallery Grand BSD, Jl. BSD Raya Utama No. 8, Tangerang')");
            $c3 = $db->lastInsertId();
        }

        $reportStmt = $db->prepare("
            INSERT INTO client_reports (
                client_id, report_month, executive_summary,
                followers_growth, total_reach, total_impressions,
                engagement_rate, saves_shares, link_clicks, leads_generated,
                top_content_summary, action_plan
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // 1. Report Prima Pasir Mandiri (Real Estate / Building Materials - Lead Gen & Reach)
        $reportStmt->execute([
            $c1,
            'August 2026',
            'Bulan Agustus 2026 mencatatkan lonjakan awareness dan inquiry material konstruksi sebesar 42% untuk Prima Pasir Mandiri. Fokus konten edukasi pemilihan pasir cor berkualitas tinggi serta transparansi armada pengiriman terbukti mendorong kepercayaan kontraktor dan pemilik proyek residensial di area Jabodetabek.',
            850,
            145200,
            382400,
            4.85,
            1940,
            820,
            48,
            'Reels edukasi "Perbedaan Pasir Cor Cuci vs Pasir Kasar untuk Struktur Bangunan Tahan Gempa" meraih 94.000 views, 1.200 saves, dan mendatangkan 22 direct WhatsApp leads dalam 48 jam pertama penayangan.',
            "1. Gandakan frekuensi konten edukasi teknis konstruksi & tips hemat budget proyek konstruksi.\n2. Luncurkan segmen video testimoni mandor proyek perumahan di Serpong & BSD.\n3. Uji coba Meta Ads retargeting pada audiens yang telah menyimpan (save) postingan tips."
        ]);

        // 2. Report Autolux Detailing & Bodyworks (Automotive - Short Video Views & Booking Inquiries)
        $reportStmt->execute([
            $c2,
            'August 2026',
            'Kampanye Reels dan TikTok Showroom Transformation menghasilkan viralitas tinggi dengan total impresi menembus 520.000+. Peningkatan engagement didorong oleh format ASMR Ceramic Coating dan perbandingan Before-After restorasi cat mobil klasik.',
            1620,
            215000,
            524000,
            6.40,
            3450,
            1150,
            64,
            'Short Video "Restorasi Cat Kusam Porsche 911 1995 Menjadi Seperti Baru (ASMR & Timelapse)" mencatat 182.000 views, 2.100 shares, dan engagement rate 8.2%.',
            "1. Perkuat konten Behind-the-Scenes proses pengerjaan Paint Protection Film (PPF).\n2. Kolaborasi mini influencer car enthusiast lokal Tangerang Selatan.\n3. Siapkan promo early-bird bundling Nano Ceramic Coating untuk periode September 2026."
        ]);

        // 3. Report Grand BSD Residence (Property / Real Estate - Leads & Walkthroughs)
        $reportStmt->execute([
            $c3,
            'July 2026',
            'Peluncuran cluster terbaru "The Grand Horizon" mendapatkan atensi kuat dari segmen keluarga muda mapan. Visualisasi interior dan virtual tour unit rumah contoh mengonversi reach digital menjadi kunjungan show unit offline.',
            1200,
            188000,
            410000,
            5.15,
            2280,
            1420,
            52,
            'Video Walkthrough "Rumah 2 Lantai Konsep Japandi Modern dengan Rooftop Garden" menghasilkan 31 booking jadwal kunjungan marketing gallery.',
            "1. Follow-up lead database dengan konten video FAQ cicilan KPR & promo DP 0%.\n2. Produksi video live streaming open house setiap akhir pekan bersama sales agent."
        ]);
    }

    private static function runMigrations(PDO $db): void {
        // Ensure performance_reports table exists
        $db->exec("
            CREATE TABLE IF NOT EXISTS performance_reports (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                report_period VARCHAR(100) NOT NULL,
                objective VARCHAR(255) DEFAULT NULL,
                total_ad_spend DECIMAL(15,2) NOT NULL DEFAULT 0,
                revenue DECIMAL(15,2) NOT NULL DEFAULT 0,
                roas DECIMAL(5,2) NOT NULL DEFAULT 0,
                roi DECIMAL(5,2) NOT NULL DEFAULT 0,
                total_conversions INT NOT NULL DEFAULT 0,
                cpl_cpa DECIMAL(15,2) NOT NULL DEFAULT 0,
                ads_reach INT NOT NULL DEFAULT 0,
                ads_impressions INT NOT NULL DEFAULT 0,
                ads_ctr DECIMAL(5,2) NOT NULL DEFAULT 0,
                ads_cpc DECIMAL(10,2) NOT NULL DEFAULT 0,
                ads_cpm DECIMAL(10,2) NOT NULL DEFAULT 0,
                lost_is_rank DECIMAL(5,2) NOT NULL DEFAULT 0,
                lost_is_budget DECIMAL(5,2) NOT NULL DEFAULT 0,
                ads_evaluation TEXT DEFAULT NULL,
                content_identity TEXT DEFAULT NULL,
                total_views INT NOT NULL DEFAULT 0,
                followers_gained INT NOT NULL DEFAULT 0,
                avg_video_retention DECIMAL(5,2) NOT NULL DEFAULT 0,
                engagement_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
                winning_content_url VARCHAR(255) DEFAULT NULL,
                underperforming_content_url VARCHAR(255) DEFAULT NULL,
                what_worked TEXT DEFAULT NULL,
                what_didnt_work TEXT DEFAULT NULL,
                next_action_plan TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_deleted INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
            );
        ");

        // Ensure content_planner table exists
        $db->exec("
            CREATE TABLE IF NOT EXISTS content_planner (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                project_id INTEGER DEFAULT NULL,
                title VARCHAR(255) NOT NULL,
                platform VARCHAR(50) NOT NULL DEFAULT 'Instagram',
                content_type VARCHAR(50) NOT NULL DEFAULT 'Reels / Video',
                publish_date DATE NOT NULL,
                publish_time TIME DEFAULT '10:00:00',
                status VARCHAR(30) NOT NULL DEFAULT 'Draft',
                assignee_id INTEGER DEFAULT NULL,
                asset_url TEXT DEFAULT NULL,
                color_hex VARCHAR(20) DEFAULT '#3B82F6',
                notes TEXT DEFAULT NULL,
                is_deleted INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
                FOREIGN KEY (assignee_id) REFERENCES employees(id) ON DELETE SET NULL
            );
        ");

        // Ensure client_reports table exists
        $db->exec("
            CREATE TABLE IF NOT EXISTS client_reports (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                report_month VARCHAR(50) NOT NULL,
                executive_summary TEXT,
                followers_growth INTEGER NOT NULL DEFAULT 0,
                total_reach INTEGER NOT NULL DEFAULT 0,
                total_impressions INTEGER NOT NULL DEFAULT 0,
                engagement_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                saves_shares INTEGER NOT NULL DEFAULT 0,
                link_clicks INTEGER NOT NULL DEFAULT 0,
                leads_generated INTEGER NOT NULL DEFAULT 0,
                top_content_summary TEXT,
                action_plan TEXT,
                is_deleted INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
            );
        ");

        $tables = ['invoices', 'freelancer_payouts', 'ads_spend', 'salaries', 'content_planner', 'client_reports', 'performance_reports'];
        foreach ($tables as $t) {
            try {
                $cols = $db->query("PRAGMA table_info($t)")->fetchAll(PDO::FETCH_COLUMN, 1);
                if (!empty($cols) && !in_array('is_deleted', $cols)) {
                    $db->exec("ALTER TABLE $t ADD COLUMN is_deleted INTEGER DEFAULT 0");
                }
            } catch (Exception $e) {
                // Ignore if table not created yet or already migrated
            }
        }

        // Ensure employees table has email column
        try {
            $empCols = $db->query("PRAGMA table_info(employees)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('email', $empCols)) {
                $db->exec("ALTER TABLE employees ADD COLUMN email VARCHAR(150) DEFAULT NULL");
            }
        } catch (Exception $e) {
            // Ignore
        }

        // Ensure clients table has logo and is_deleted columns
        try {
            $clientCols = $db->query("PRAGMA table_info(clients)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('logo', $clientCols)) {
                $db->exec("ALTER TABLE clients ADD COLUMN logo VARCHAR(255) DEFAULT NULL");
            }
            if (!in_array('is_deleted', $clientCols)) {
                $db->exec("ALTER TABLE clients ADD COLUMN is_deleted INTEGER DEFAULT 0");
            }
        } catch (Exception $e) {
            // Ignore
        }

        // Ensure projects table has is_deleted column
        try {
            $projCols = $db->query("PRAGMA table_info(projects)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('is_deleted', $projCols)) {
                $db->exec("ALTER TABLE projects ADD COLUMN is_deleted INTEGER DEFAULT 0");
            }
        } catch (Exception $e) {
            // Ignore
        }

        // Check and seed default reports if empty
        try {
            $rCount = intval($db->query("SELECT COUNT(*) FROM client_reports WHERE COALESCE(is_deleted, 0) = 0")->fetchColumn());
            if ($rCount === 0) {
                self::seedDefaultReports($db);
            }
        } catch (Exception $e) {
            // Ignore
        }

        // Check and seed default performance reports if empty
        try {
            $rCount = intval($db->query("SELECT COUNT(*) FROM performance_reports WHERE COALESCE(is_deleted, 0) = 0")->fetchColumn());
            if ($rCount === 0) {
                self::seedPerformanceReports($db);
            }
        } catch (Exception $e) {
            // Ignore
        }
    }
}

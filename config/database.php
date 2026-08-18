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
                self::ensureTablesAndSeed(self::$instance);
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

            CREATE TABLE IF NOT EXISTS activities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER DEFAULT NULL,
                type VARCHAR(50) NOT NULL, -- 'invoice', 'payout', 'ads', 'client', 'project', 'salary'
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
            $userStmt->execute(['Owner Kala', 'owner@kalamedia.id', $hashedPw, 'owner']);
            $userStmt->execute(['Finance Kala', 'finance@kalamedia.id', $hashedPw, 'admin']);
        }

        // Seed Default Employees if empty
        $empCount = intval($db->query("SELECT COUNT(*) FROM employees")->fetchColumn());
        if ($empCount === 0) {
            $empStmt = $db->prepare("
                INSERT INTO employees (name, position, department, employment_type, email, phone, bank_name, bank_account, base_salary, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')
            ");
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

        // Mark as seeded so subsequent deletions stay permanently deleted
        $db->exec("INSERT OR REPLACE INTO system_meta (key, value) VALUES ('is_seeded', '1')");
    }

    private static function runMigrations(PDO $db): void {
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

        $tables = ['invoices', 'freelancer_payouts', 'ads_spend', 'salaries', 'content_planner'];
        foreach ($tables as $t) {
            try {
                $cols = $db->query("PRAGMA table_info($t)")->fetchAll(PDO::FETCH_COLUMN, 1);
                if (!in_array('is_deleted', $cols)) {
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
    }
}

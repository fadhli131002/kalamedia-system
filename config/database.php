<?php
/**
 * Kalamedia Database Connection & Auto-Migrator
 * Hybrid Database Engine: Primary MySQL (via XAMPP / Server) with Zero-Config SQLite Fallback
 */

require_once __DIR__ . '/app.php';

class Database {
    private static ?PDO $instance = null;
    private static string $driver = 'sqlite';

    public static function getDriver(): string {
        return self::$driver;
    }

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $mysqlHost = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: '127.0.0.1');
            $mysqlPort = defined('DB_PORT') ? DB_PORT : (getenv('DB_PORT') ?: '3306');
            $mysqlDb   = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'kalamedia');
            $mysqlUser = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'root');
            $mysqlPass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: '');

            $connected = false;

            // 1. Try MySQL Connection First if configured or available
            if (!empty($mysqlHost)) {
                try {
                    // Quick ping/connect with short timeout
                    $dsnServer = "mysql:host={$mysqlHost};port={$mysqlPort};charset=utf8mb4";
                    $pdoServer = new PDO($dsnServer, $mysqlUser, $mysqlPass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_TIMEOUT => 2
                    ]);
                    $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `{$mysqlDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

                    // Connect to specific database
                    $dsnDb = "mysql:host={$mysqlHost};port={$mysqlPort};dbname={$mysqlDb};charset=utf8mb4";
                    self::$instance = new PDO($dsnDb, $mysqlUser, $mysqlPass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_TIMEOUT => 2
                    ]);
                    self::$driver = 'mysql';
                    $connected = true;
                } catch (Throwable $e) {
                    // Fallback to SQLite gracefully
                    $connected = false;
                }
            }

            // 2. Fallback Zero-Config: SQLite
            if (!$connected) {
                $dbPath = BASE_PATH . '/database/kalamedia.sqlite';
                $dbDir = dirname($dbPath);
                
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0777, true);
                }

                try {
                    self::$instance = new PDO("sqlite:" . $dbPath, null, null, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_TIMEOUT => 5
                    ]);
                    self::$instance->exec("PRAGMA foreign_keys = ON;");
                    self::$driver = 'sqlite';
                } catch (PDOException $e) {
                    die("Database Connection Error: " . $e->getMessage());
                }
            }

            // Auto-migration & Schema Check
            self::runMigrations(self::$instance, self::$driver);
        }
        return self::$instance;
    }

    public static function runMigrations(PDO $db, string $driver = 'sqlite'): void {
        $pkDef = ($driver === 'mysql') ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY AUTOINCREMENT";

        // Create base tables if not exists
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id {$pkDef},
                name VARCHAR(100) NOT NULL,
                email VARCHAR(150) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'admin',
                employment_type VARCHAR(30) DEFAULT 'internal',
                phone_wa VARCHAR(50) DEFAULT NULL,
                avatar VARCHAR(255) DEFAULT NULL,
                client_id INT DEFAULT NULL,
                is_active INT DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS clients (
                id {$pkDef},
                name VARCHAR(150) NOT NULL,
                company VARCHAR(150) NOT NULL,
                company_name VARCHAR(150) DEFAULT NULL,
                pic_name VARCHAR(150) DEFAULT NULL,
                email VARCHAR(150) NOT NULL,
                phone VARCHAR(50) NOT NULL,
                package_name VARCHAR(100) DEFAULT 'Social Media & Performance Ads',
                status VARCHAR(30) DEFAULT 'Active',
                address TEXT DEFAULT NULL,
                logo VARCHAR(255) DEFAULT NULL,
                is_deleted INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS projects (
                id {$pkDef},
                client_id INT NOT NULL,
                name VARCHAR(200) NOT NULL,
                project_name VARCHAR(200) DEFAULT NULL,
                contract_value DECIMAL(15,2) NOT NULL DEFAULT 0,
                target_margin_percent DECIMAL(5,2) DEFAULT 30.00,
                progress_percent DECIMAL(5,2) DEFAULT 0,
                status VARCHAR(30) DEFAULT 'In Progress',
                start_date DATE DEFAULT NULL,
                end_date DATE DEFAULT NULL,
                is_deleted INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS tasks (
                id {$pkDef},
                project_id INT NOT NULL,
                client_id INT NOT NULL,
                assigned_to_user_id INT DEFAULT NULL,
                assigned_by_user_id INT DEFAULT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                category VARCHAR(50) NOT NULL DEFAULT 'design',
                worker_type VARCHAR(30) NOT NULL DEFAULT 'karyawan',
                priority VARCHAR(20) NOT NULL DEFAULT 'medium',
                status VARCHAR(30) NOT NULL DEFAULT 'backlog',
                deliverable_link TEXT DEFAULT NULL,
                due_date DATE DEFAULT NULL,
                is_deleted INT NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS task_feedbacks (
                id {$pkDef},
                task_id INT NOT NULL,
                user_id INT NOT NULL,
                feedback_type VARCHAR(30) NOT NULL DEFAULT 'comment',
                message TEXT NOT NULL,
                attachment_path VARCHAR(255) DEFAULT NULL,
                is_read_by_worker INT NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS invoices (
                id {$pkDef},
                invoice_number VARCHAR(50) UNIQUE NOT NULL,
                client_id INT NOT NULL,
                project_id INT DEFAULT NULL,
                issue_date DATE NOT NULL,
                due_date DATE NOT NULL,
                subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
                discount_percent DECIMAL(5,2) DEFAULT 0,
                discount_amount DECIMAL(15,2) DEFAULT 0,
                tax_percent DECIMAL(5,2) DEFAULT 0,
                tax_amount DECIMAL(15,2) DEFAULT 0,
                total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                status VARCHAR(20) DEFAULT 'Draft',
                paid_at DATETIME DEFAULT NULL,
                payment_method VARCHAR(50) DEFAULT NULL,
                receipt_file VARCHAR(255) DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                is_deleted INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS invoice_items (
                id {$pkDef},
                invoice_id INT NOT NULL,
                service_name VARCHAR(200) NOT NULL,
                quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
                unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
                total_price DECIMAL(15,2) NOT NULL DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS freelancer_payouts (
                id {$pkDef},
                freelancer_name VARCHAR(150) NOT NULL,
                freelancer_phone VARCHAR(50) DEFAULT NULL,
                freelancer_bank VARCHAR(100) DEFAULT NULL,
                freelancer_account VARCHAR(100) DEFAULT NULL,
                project_id INT NOT NULL,
                task_description TEXT NOT NULL,
                amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                status VARCHAR(20) DEFAULT 'Pending',
                paid_at DATETIME DEFAULT NULL,
                receipt_file VARCHAR(255) DEFAULT NULL,
                is_deleted INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS ads_spend (
                id {$pkDef},
                client_id INT NOT NULL,
                project_id INT DEFAULT NULL,
                platform VARCHAR(50) NOT NULL,
                account_id VARCHAR(100) DEFAULT NULL,
                amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                spent_date DATE NOT NULL,
                receipt_file VARCHAR(255) DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                is_deleted INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS employees (
                id {$pkDef},
                name VARCHAR(150) NOT NULL,
                position VARCHAR(100) NOT NULL,
                department VARCHAR(100) DEFAULT 'Creative & Tech',
                employment_type VARCHAR(50) DEFAULT 'Full-time',
                email VARCHAR(150) DEFAULT NULL,
                phone VARCHAR(50) DEFAULT NULL,
                bank_name VARCHAR(100) DEFAULT 'BCA',
                bank_account VARCHAR(100) DEFAULT NULL,
                base_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
                status VARCHAR(20) DEFAULT 'Active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS salaries (
                id {$pkDef},
                employee_id INT DEFAULT NULL,
                employee_name VARCHAR(150) NOT NULL,
                employee_position VARCHAR(100) DEFAULT NULL,
                bank_name VARCHAR(100) DEFAULT 'BCA',
                bank_account VARCHAR(100) DEFAULT NULL,
                month_period VARCHAR(20) NOT NULL,
                base_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
                allowance DECIMAL(15,2) NOT NULL DEFAULT 0,
                deduction DECIMAL(15,2) NOT NULL DEFAULT 0,
                net_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
                payment_date DATE NOT NULL,
                status VARCHAR(20) DEFAULT 'Pending',
                paid_at DATETIME DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                is_deleted INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS content_planner (
                id {$pkDef},
                client_id INT NOT NULL,
                project_id INT DEFAULT NULL,
                title VARCHAR(255) NOT NULL,
                platform VARCHAR(50) NOT NULL DEFAULT 'Instagram',
                content_type VARCHAR(50) NOT NULL DEFAULT 'Reels / Video',
                publish_date DATE NOT NULL,
                publish_time TIME DEFAULT '10:00:00',
                status VARCHAR(30) NOT NULL DEFAULT 'Draft',
                assignee_id INT DEFAULT NULL,
                asset_url TEXT DEFAULT NULL,
                color_hex VARCHAR(20) DEFAULT '#3B82F6',
                notes TEXT DEFAULT NULL,
                is_deleted INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS performance_reports (
                id {$pkDef},
                client_id INT NOT NULL,
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
                is_deleted INT NOT NULL DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS client_reports (
                id {$pkDef},
                client_id INT NOT NULL,
                report_month VARCHAR(50) NOT NULL,
                executive_summary TEXT,
                followers_growth INT NOT NULL DEFAULT 0,
                total_reach INT NOT NULL DEFAULT 0,
                total_impressions INT NOT NULL DEFAULT 0,
                engagement_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                saves_shares INT NOT NULL DEFAULT 0,
                link_clicks INT NOT NULL DEFAULT 0,
                leads_generated INT NOT NULL DEFAULT 0,
                top_content_summary TEXT,
                action_plan TEXT,
                is_deleted INT NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS activities (
                id {$pkDef},
                user_id INT DEFAULT NULL,
                type VARCHAR(50) NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                amount DECIMAL(15,2) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS system_meta (
                `key` VARCHAR(50) PRIMARY KEY,
                `value` TEXT
            );
        ");

        // Helper to check existing columns for migration
        $getTableCols = function(string $table) use ($db, $driver): array {
            try {
                if ($driver === 'mysql') {
                    $stmt = $db->prepare("SHOW COLUMNS FROM `{$table}`");
                    $stmt->execute();
                    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                } else {
                    $stmt = $db->query("PRAGMA table_info({$table})");
                    return $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
                }
            } catch (Exception $e) {
                return [];
            }
        };

        // 1. Migrate `users` table columns
        $userCols = $getTableCols('users');
        if (!empty($userCols)) {
            if (!in_array('employment_type', $userCols)) {
                $db->exec("ALTER TABLE users ADD COLUMN employment_type VARCHAR(30) DEFAULT 'internal'");
            }
            if (!in_array('phone_wa', $userCols)) {
                $db->exec("ALTER TABLE users ADD COLUMN phone_wa VARCHAR(50) DEFAULT NULL");
            }
            if (!in_array('client_id', $userCols)) {
                $db->exec("ALTER TABLE users ADD COLUMN client_id INT DEFAULT NULL");
            }
            if (!in_array('is_active', $userCols)) {
                $db->exec("ALTER TABLE users ADD COLUMN is_active INT DEFAULT 1");
            }
        }

        // 2. Migrate `clients` table columns
        $clientCols = $getTableCols('clients');
        if (!empty($clientCols)) {
            if (!in_array('company_name', $clientCols)) {
                $db->exec("ALTER TABLE clients ADD COLUMN company_name VARCHAR(150) DEFAULT NULL");
                $db->exec("UPDATE clients SET company_name = company WHERE company_name IS NULL OR company_name = ''");
            }
            if (!in_array('pic_name', $clientCols)) {
                $db->exec("ALTER TABLE clients ADD COLUMN pic_name VARCHAR(150) DEFAULT NULL");
                $db->exec("UPDATE clients SET pic_name = name WHERE pic_name IS NULL OR pic_name = ''");
            }
            if (!in_array('package_name', $clientCols)) {
                $db->exec("ALTER TABLE clients ADD COLUMN package_name VARCHAR(100) DEFAULT 'Social Media & Performance Ads'");
            }
            if (!in_array('status', $clientCols)) {
                $db->exec("ALTER TABLE clients ADD COLUMN status VARCHAR(30) DEFAULT 'Active'");
            }
            if (!in_array('logo', $clientCols)) {
                $db->exec("ALTER TABLE clients ADD COLUMN logo VARCHAR(255) DEFAULT NULL");
            }
            if (!in_array('is_deleted', $clientCols)) {
                $db->exec("ALTER TABLE clients ADD COLUMN is_deleted INT DEFAULT 0");
            }
        }

        // 3. Migrate `projects` table columns
        $projCols = $getTableCols('projects');
        if (!empty($projCols)) {
            if (!in_array('project_name', $projCols)) {
                $db->exec("ALTER TABLE projects ADD COLUMN project_name VARCHAR(200) DEFAULT NULL");
                $db->exec("UPDATE projects SET project_name = name WHERE project_name IS NULL OR project_name = ''");
            }
            if (!in_array('progress_percent', $projCols)) {
                $db->exec("ALTER TABLE projects ADD COLUMN progress_percent DECIMAL(5,2) DEFAULT 0");
            }
            if (!in_array('is_deleted', $projCols)) {
                $db->exec("ALTER TABLE projects ADD COLUMN is_deleted INT DEFAULT 0");
            }
        }

        // 4. Ensure is_deleted on other standard tables
        $tablesWithDeleted = ['tasks', 'invoices', 'freelancer_payouts', 'ads_spend', 'salaries', 'content_planner', 'client_reports', 'performance_reports'];
        foreach ($tablesWithDeleted as $t) {
            $cols = $getTableCols($t);
            if (!empty($cols) && !in_array('is_deleted', $cols)) {
                try {
                    $db->exec("ALTER TABLE {$t} ADD COLUMN is_deleted INT DEFAULT 0");
                } catch (Exception $e) {}
            }
        }

        // 5. Auto-purge legacy dummy tasks, projects, clients, and demo accounts across SQLite & MySQL
        try {
            $db->exec("DELETE FROM task_feedbacks WHERE task_id IN (SELECT id FROM tasks WHERE title LIKE '%Copywriting Meta Ads%' OR title LIKE '%Setup Kampanye Meta Ads%' OR title LIKE '%Desain Feed Carousel%' OR title LIKE '%Shooting Video Reels%')");
            $db->exec("DELETE FROM tasks WHERE title LIKE '%Copywriting Meta Ads%' OR title LIKE '%Setup Kampanye Meta Ads%' OR title LIKE '%Desain Feed Carousel%' OR title LIKE '%Shooting Video Reels%'");
            $db->exec("DELETE FROM projects WHERE name LIKE '%Grand BSD%' OR name LIKE '%Horizon%' OR client_id IN (SELECT id FROM clients WHERE company LIKE '%Grand BSD%' OR name LIKE '%Clarissa Tan%')");
            $db->exec("DELETE FROM clients WHERE company LIKE '%Grand BSD%' OR name LIKE '%Clarissa Tan%'");
            $db->exec("DELETE FROM users WHERE email IN ('annisa@kalamedia.id', 'dimas@kalamedia.id', 'clarissa@grandbsd.com') OR name IN ('Annisa Nuraini', 'Dimas Prasetyo', 'Clarissa Tan')");
        } catch (Exception $e) {
            // Ignore if tables do not exist yet
        }

        // 6. Seed official system accounts (Owner and Admin only)
        self::seedDefaultSystemAccounts($db);
    }

    public static function seedDefaultSystemAccounts(PDO $db): void {
        $hashedPw = password_hash('password123', PASSWORD_BCRYPT);

        // 1. Owner Account (Official, clean, no personal name)
        $ownerStmt = $db->prepare("SELECT id FROM users WHERE email = 'owner@kalamedia.id' OR role = 'owner' LIMIT 1");
        $ownerStmt->execute();
        $ownerId = $ownerStmt->fetchColumn();

        if (!$ownerId) {
            $insert = $db->prepare("
                INSERT INTO users (name, email, password, role, employment_type, phone_wa, is_active)
                VALUES ('Owner', 'owner@kalamedia.id', ?, 'owner', 'internal', '08881124215', 1)
            ");
            $insert->execute([$hashedPw]);
        } else {
            $db->prepare("UPDATE users SET name = 'Owner', role = 'owner' WHERE id = ?")->execute([$ownerId]);
        }

        // 2. Admin Account (Official, clean, no personal name)
        $adminStmt = $db->prepare("SELECT id FROM users WHERE email = 'admin@kalamedia.id' OR (role = 'admin' AND email != 'owner@kalamedia.id') LIMIT 1");
        $adminStmt->execute();
        $adminId = $adminStmt->fetchColumn();

        if (!$adminId) {
            $insert = $db->prepare("
                INSERT INTO users (name, email, password, role, employment_type, phone_wa, is_active)
                VALUES ('Admin', 'admin@kalamedia.id', ?, 'admin', 'internal', '081382573705', 1)
            ");
            $insert->execute([$hashedPw]);
        } else {
            $db->prepare("UPDATE users SET name = 'Admin', role = 'admin' WHERE id = ?")->execute([$adminId]);
        }
    }
}

<?php
declare(strict_types=1);

final class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $config = self::resolveConfig();

        $host = $config['DB_HOST'];
        $port = $config['DB_PORT'];
        $db   = $config['DB_NAME'];
        $user = $config['DB_USER'];
        $pass = $config['DB_PASS'];

        $serverPdo = new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $user,
            $pass
        );
        $serverPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $safeDb = str_replace('`', '``', $db);
        $serverPdo->exec(
            "CREATE DATABASE IF NOT EXISTS `{$safeDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );

        $this->pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
            $user,
            $pass
        );
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('SET time_zone = "+00:00"');

        $this->ensureSchema();
        if ($this->needsSeed()) {
            $this->seedData();
        }
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** @return array{DB_HOST:string,DB_PORT:int,DB_NAME:string,DB_USER:string,DB_PASS:string} */
    private static function resolveConfig(): array
    {
        $fileConfig = [];
        $configFile = __DIR__ . '/config.php';
        if (is_readable($configFile)) {
            $loaded = require $configFile;
            if (is_array($loaded)) {
                $fileConfig = $loaded;
            }
        }

        return [
            'DB_HOST' => (string)($fileConfig['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1'),
            'DB_PORT' => (int)($fileConfig['DB_PORT'] ?? getenv('DB_PORT') ?: 3306),
            'DB_NAME' => (string)($fileConfig['DB_NAME'] ?? getenv('DB_NAME') ?: 'death_registration'),
            'DB_USER' => (string)($fileConfig['DB_USER'] ?? getenv('DB_USER') ?: 'root'),
            'DB_PASS' => (string)($fileConfig['DB_PASS'] ?? (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '')),
        ];
    }

    private function needsSeed(): bool
    {
        if (!$this->tableExists('users')) {
            return true;
        }

        return (int)$this->pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'] === 0;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetch();
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                full_name VARCHAR(200) NOT NULL,
                role ENUM('super_admin','registrar','hospital_officer','data_entry_clerk','auditor') NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                failed_attempts INT NOT NULL DEFAULT 0,
                locked_until DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS death_records (
                id INT AUTO_INCREMENT PRIMARY KEY,
                certificate_no VARCHAR(50) UNIQUE,
                deceased_name VARCHAR(200) NOT NULL,
                passport_number VARCHAR(100) NULL,
                gender ENUM('Male','Female','Other') NOT NULL,
                date_of_birth DATE NULL,
                date_of_death DATE NOT NULL,
                place_of_death VARCHAR(255) NOT NULL,
                cause_of_death VARCHAR(255) NOT NULL,
                hospital_name VARCHAR(255) NULL,
                district VARCHAR(100) NOT NULL,
                region VARCHAR(100) NOT NULL,
                applicant_name VARCHAR(200) NOT NULL,
                applicant_relationship VARCHAR(100) NOT NULL,
                applicant_contact VARCHAR(50) NOT NULL,
                status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                registered_by INT NOT NULL,
                approved_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (registered_by) REFERENCES users(id),
                FOREIGN KEY (approved_by) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                action VARCHAR(100) NOT NULL,
                details TEXT NULL,
                ip_address VARCHAR(45) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS rate_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                identifier VARCHAR(100) NOT NULL,
                action VARCHAR(50) NOT NULL,
                attempts INT NOT NULL DEFAULT 1,
                window_start DATETIME NOT NULL,
                INDEX idx_rate_limits_lookup (identifier, action, window_start)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->ensureIndex('death_records', 'idx_deaths_status', 'status');
        $this->ensureIndex('death_records', 'idx_deaths_name', 'deceased_name');
        $this->ensureIndex('death_records', 'idx_deaths_date', 'date_of_death');
    }

    private function ensureIndex(string $table, string $index, string $columns): void
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) c
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
        ");
        $stmt->execute([$table, $index]);
        if ((int)$stmt->fetch()['c'] === 0) {
            $this->pdo->exec("CREATE INDEX {$index} ON {$table} ({$columns})");
        }
    }

    private function seedData(): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)"
        );

        $stmt->execute([
            'admin',
            password_hash('Admin@123', PASSWORD_DEFAULT),
            'System Administrator',
            'super_admin',
        ]);

        $stmt->execute([
            'clerk',
            password_hash('Clerk@123', PASSWORD_DEFAULT),
            'Data Entry Clerk',
            'data_entry_clerk',
        ]);

        $sample = $this->pdo->prepare("
            INSERT INTO death_records
            (certificate_no, deceased_name, gender, date_of_birth, date_of_death, place_of_death,
             cause_of_death, hospital_name, district, region, applicant_name, applicant_relationship,
             applicant_contact, status, registered_by, approved_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $sample->execute([
            'DRS-2026-000001', 'John Mwakalinga', 'Male', '1950-04-12', '2026-06-01',
            'Muhimbili National Hospital', 'Cardiac arrest', 'Muhimbili National Hospital',
            'Ilala', 'Dar es Salaam', 'Grace Mwakalinga', 'Daughter', '0712345678',
            'approved', 1, 1,
        ]);
        $sample->execute([
            'DRS-2026-000002', 'Amina Juma', 'Female', '1978-11-03', '2026-06-20',
            'Home', 'Natural causes', null, 'Kinondoni', 'Dar es Salaam',
            'Hassan Juma', 'Husband', '0755123456', 'pending', 2, null,
        ]);
    }
}

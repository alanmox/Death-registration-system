<?php
declare(strict_types=1);

final class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $dbFile = __DIR__ . DIRECTORY_SEPARATOR . 'death_registration.sqlite';
        $isNew  = !file_exists($dbFile);

        $this->pdo = new PDO('sqlite:' . $dbFile);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON;');

        if ($isNew) {
            $this->createSchema();
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

    private function createSchema(): void
    {
        $this->pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                full_name TEXT NOT NULL,
                role TEXT NOT NULL CHECK(role IN
                    ('super_admin','registrar','hospital_officer','data_entry_clerk','auditor')),
                is_active INTEGER NOT NULL DEFAULT 1,
                failed_attempts INTEGER NOT NULL DEFAULT 0,
                locked_until TEXT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            );
        ");

        $this->pdo->exec("
            CREATE TABLE death_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                certificate_no TEXT UNIQUE,
                deceased_name TEXT NOT NULL,
                gender TEXT NOT NULL CHECK(gender IN ('Male','Female','Other')),
                date_of_birth TEXT NULL,
                date_of_death TEXT NOT NULL,
                place_of_death TEXT NOT NULL,
                cause_of_death TEXT NOT NULL,
                hospital_name TEXT NULL,
                district TEXT NOT NULL,
                region TEXT NOT NULL,
                applicant_name TEXT NOT NULL,
                applicant_relationship TEXT NOT NULL,
                applicant_contact TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','approved','rejected')),
                registered_by INTEGER NOT NULL,
                approved_by INTEGER NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (registered_by) REFERENCES users(id),
                FOREIGN KEY (approved_by) REFERENCES users(id)
            );
        ");

        $this->pdo->exec("
            CREATE TABLE audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                action TEXT NOT NULL,
                details TEXT NULL,
                ip_address TEXT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (user_id) REFERENCES users(id)
            );
        ");

        $this->pdo->exec("CREATE INDEX idx_deaths_status ON death_records(status);");
        $this->pdo->exec("CREATE INDEX idx_deaths_name ON death_records(deceased_name);");
        $this->pdo->exec("CREATE INDEX idx_deaths_date ON death_records(date_of_death);");
    }

    private function seedData(): void
    {
        $hash = password_hash('Admin@123', PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, 'super_admin')"
        );
        $stmt->execute(['admin', $hash, 'System Administrator']);

        $clerkHash = password_hash('Clerk@123', PASSWORD_DEFAULT);
        $stmt->execute(['clerk', $clerkHash, 'Data Entry Clerk']);
        $clerkStmt = $this->pdo->prepare("UPDATE users SET role='data_entry_clerk' WHERE username='clerk'");
        $clerkStmt->execute();

        // A couple of sample records so the dashboard/reports aren't empty
        $sample = $this->pdo->prepare("
            INSERT INTO death_records
            (certificate_no, deceased_name, gender, date_of_birth, date_of_death, place_of_death,
             cause_of_death, hospital_name, district, region, applicant_name, applicant_relationship,
             applicant_contact, status, registered_by, approved_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $sample->execute(['DRS-2026-000001', 'John Mwakalinga', 'Male', '1950-04-12', '2026-06-01',
            'Muhimbili National Hospital', 'Cardiac arrest', 'Muhimbili National Hospital',
            'Ilala', 'Dar es Salaam', 'Grace Mwakalinga', 'Daughter', '0712345678',
            'approved', 1, 1]);
        $sample->execute(['DRS-2026-000002', 'Amina Juma', 'Female', '1978-11-03', '2026-06-20',
            'Home', 'Natural causes', null, 'Kinondoni', 'Dar es Salaam',
            'Hassan Juma', 'Husband', '0755123456', 'pending', 2, null]);
    }
}

<?php
declare(strict_types=1);

final class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        // Load config file if present (created during deployment)
        $config = [];
        $configFile = __DIR__ . '/config.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
        }

        $host = $config['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
        $port = $config['DB_PORT'] ?? getenv('DB_PORT') ?: 3306;
        $db   = $config['DB_NAME'] ?? getenv('DB_NAME') ?: 'death_registration';
        $user = $config['DB_USER'] ?? getenv('DB_USER') ?: 'death_reg_user';
        $pass = $config['DB_PASS'] ?? getenv('DB_PASS') ?: 'Kibanda123';

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

        $this->pdo = new PDO($dsn, $user, $pass);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('SET time_zone = "+00:00"');
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
}

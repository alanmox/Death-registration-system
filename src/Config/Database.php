<?php
declare(strict_types=1);

final class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $host = '127.0.0.1';
        $port = 3306;
        $db   = 'death_registration';
        $user = 'root';
        $pass = '';

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

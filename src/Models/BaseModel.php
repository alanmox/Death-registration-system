<?php
declare(strict_types=1);

abstract class BaseModel
{
    protected PDO $pdo;
    protected string $table;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->pdo();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}

<?php
declare(strict_types=1);

final class AuditLog
{
    public static function record(?int $userId, string $action, string $details = ''): void
    {
        $pdo = Database::getInstance()->pdo();
        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?,?,?,?)"
        );
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (($pos = strpos($ip, ',')) !== false) {
            $ip = trim(substr($ip, 0, $pos));
        }
        if ($ip === '::1') {
            $ip = '127.0.0.1';
        }
        $stmt->execute([$userId, $action, $details, $ip]);
    }

    public static function recent(int $limit = 20): array
    {
        $pdo = Database::getInstance()->pdo();
        $stmt = $pdo->prepare("
            SELECT al.*, u.username FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            ORDER BY al.id DESC LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private static function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = "(u.username LIKE ? OR al.details LIKE ? OR al.ip_address LIKE ?)";
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like);
        }
        if (!empty($filters['action'])) {
            $where[] = "al.action = ?";
            $params[] = $filters['action'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(al.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(al.created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        $sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        return [$sql, $params];
    }

    public static function all(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        [$whereSql, $params] = self::buildWhere($filters);
        $offset = ($page - 1) * $perPage;
        
        $sql = "
            SELECT al.*, u.username 
            FROM audit_logs al 
            LEFT JOIN users u ON u.id = al.user_id 
            $whereSql 
            ORDER BY al.id DESC LIMIT ? OFFSET ?
        ";
        
        $pdo = Database::getInstance()->pdo();
        $stmt = $pdo->prepare($sql);
        
        $i = 1;
        foreach ($params as $p) {
            $stmt->bindValue($i++, $p);
        }
        $stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
        $stmt->bindValue($i++, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    public static function count(array $filters = []): int
    {
        [$whereSql, $params] = self::buildWhere($filters);
        $sql = "
            SELECT COUNT(*) c 
            FROM audit_logs al 
            LEFT JOIN users u ON u.id = al.user_id 
            $whereSql
        ";
        $pdo = Database::getInstance()->pdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetch()['c'];
    }
}

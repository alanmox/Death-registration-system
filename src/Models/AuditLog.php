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
        $stmt->execute([$userId, $action, $details, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
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
}

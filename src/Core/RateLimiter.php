<?php
declare(strict_types=1);

final class RateLimiter
{
    private const WINDOW_SECONDS = 60;
    private const MAX_REQUESTS = [
        'login'            => 5,
        'password_reset'   => 3,
        'api'              => 30,
        'default'          => 20,
    ];

    public static function limit(string $action, ?string $identifier = null): bool
    {
        $identifier ??= $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $max = self::MAX_REQUESTS[$action] ?? self::MAX_REQUESTS['default'];

        $pdo = Database::getInstance()->pdo();

        $stmt = $pdo->prepare(
            "SELECT SUM(attempts) AS total FROM rate_limits
             WHERE identifier = ? AND action = ? AND window_start > DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        $window = self::WINDOW_SECONDS;
        $stmt->execute([$identifier, $action, $window]);
        $row = $stmt->fetch();

        $total = (int)($row['total'] ?? 0);

        if ($total >= $max) {
            return false;
        }

        $pdo->prepare(
            "INSERT INTO rate_limits (identifier, action, attempts, window_start) VALUES (?, ?, 1, NOW())"
        )->execute([$identifier, $action]);

        $pdo->prepare(
            "DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL ? SECOND)"
        )->execute([$window * 2]);

        return true;
    }

    public static function exceeded(string $action, ?string $identifier = null): bool
    {
        return !self::limit($action, $identifier);
    }

    public static function remaining(string $action, ?string $identifier = null): int
    {
        $identifier ??= $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $max = self::MAX_REQUESTS[$action] ?? self::MAX_REQUESTS['default'];

        $pdo = Database::getInstance()->pdo();
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(attempts), 0) AS total FROM rate_limits
             WHERE identifier = ? AND action = ? AND window_start > DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        $stmt->execute([$identifier, $action, self::WINDOW_SECONDS]);
        $used = (int)$stmt->fetch()['total'];

        return max(0, $max - $used);
    }

    public static function reset(string $action, ?string $identifier = null): void
    {
        $identifier ??= $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $pdo = Database::getInstance()->pdo();
        $pdo->prepare("DELETE FROM rate_limits WHERE identifier = ? AND action = ?")
            ->execute([$identifier, $action]);
    }
}

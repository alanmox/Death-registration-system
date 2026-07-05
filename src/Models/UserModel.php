<?php
declare(strict_types=1);

final class UserModel extends BaseModel implements Crudable
{
    protected string $table = 'users';

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = "(username LIKE ? OR full_name LIKE ?)";
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like);
        }
        if (!empty($filters['role'])) {
            $where[] = "role = ?";
            $params[] = $filters['role'];
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $where[] = "is_active = ?";
            $params[] = (int)$filters['status'];
        }

        $sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        return [$sql, $params];
    }

    public function all(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM users $whereSql ORDER BY id DESC LIMIT ? OFFSET ?";
        $stmt = $this->pdo->prepare($sql);
        
        $i = 1;
        foreach ($params as $p) {
            $stmt->bindValue($i++, $p);
        }
        $stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
        $stmt->bindValue($i++, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    public function count(array $filters = []): int
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) c FROM users $whereSql");
        $stmt->execute($params);
        return (int)$stmt->fetch()['c'];
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO users (username, password_hash, full_name, role) VALUES (?,?,?,?)
        ");
        $stmt->execute([
            $data['username'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['full_name'],
            $data['role'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE users SET full_name = ?, role = ?, is_active = ? WHERE id = ?
        ");
        return $stmt->execute([$data['full_name'], $data['role'], $data['is_active'] ?? 1, $id]);
    }

    public function toggleStatus(int $id, int $isActive): bool
    {
        $stmt = $this->pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        return $stmt->execute([$isActive, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function registerFailedAttempt(int $id, int $attempts): void
    {
        $lockUntil = null;
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_SECONDS);
        }
        $stmt = $this->pdo->prepare("UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?");
        $stmt->execute([$attempts, $lockUntil, $id]);
    }

    public function resetFailedAttempts(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?");
        $stmt->execute([$id]);
    }
}

<?php
declare(strict_types=1);

final class Auth
{
    private static array $rolePermissions = [
        'super_admin'      => ['*'],
        'registrar'        => ['deaths.view', 'deaths.create', 'deaths.edit', 'deaths.approve', 'reports.view'],
        'hospital_officer' => ['deaths.view', 'deaths.create', 'reports.view'],
        'data_entry_clerk' => ['deaths.view', 'deaths.create', 'deaths.edit'],
        'auditor'          => ['deaths.view', 'reports.view', 'audit.view'],
    ];

    public static function attempt(string $username, string $password): array
    {
        $userModel = new UserModel();
        $user = $userModel->findByUsername($username);

        if (!$user) {
            AuditLog::record(null, 'login_failed', "Unknown username: $username");
            return ['ok' => false, 'message' => 'Invalid username or password.'];
        }

        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            $mins = ceil((strtotime($user['locked_until']) - time()) / 60);
            return ['ok' => false, 'message' => "Account locked. Try again in {$mins} minute(s)."];
        }

        if (!$user['is_active']) {
            return ['ok' => false, 'message' => 'This account has been deactivated.'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            $attempts = (int)$user['failed_attempts'] + 1;
            $userModel->registerFailedAttempt((int)$user['id'], $attempts);
            AuditLog::record((int)$user['id'], 'login_failed', "Bad password (attempt $attempts)");
            return ['ok' => false, 'message' => 'Invalid username or password.'];
        }

        $userModel->resetFailedAttempts((int)$user['id']);
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role'],
        ];
        $_SESSION['last_activity'] = time();
        AuditLog::record((int)$user['id'], 'login_success', '');
        return ['ok' => true];
    }

    public static function logout(): void
    {
        if (self::check()) {
            AuditLog::record(self::user()['id'], 'logout', '');
        }
        $_SESSION = [];
        session_destroy();
    }

    public static function check(): bool
    {
        if (empty($_SESSION['user'])) {
            return false;
        }
        if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT_SECONDS) {
            self::logout();
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function can(string $permission): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }
        $perms = self::$rolePermissions[$user['role']] ?? [];
        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ?page=login');
            exit;
        }
    }

    public static function requirePermission(string $permission): void
    {
        self::requireLogin();
        if (!self::can($permission)) {
            http_response_code(403);
            echo Layout::render('Access Denied', '<div class="alert alert-danger mt-4">'
                . '403 &mdash; You do not have permission to access this page.</div>');
            exit;
        }
    }
}

<?php
declare(strict_types=1);

final class AuthController
{
    public static function loginPage(): string
    {
        $token = Csrf::token();
        $flashHtml = Flash::render();
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login · Death Registration System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
  body{ background:linear-gradient(135deg,#0b3d2e 0%,#146c43 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:'Segoe UI',Roboto,Arial,sans-serif; }
  .card-login{ border:none; border-radius:1rem; box-shadow:0 10px 40px rgba(0,0,0,.2); max-width:420px; width:100%; }
  .brand{ color:#0b3d2e; font-weight:600; }
  .btn-brand{ background:#0b3d2e; color:#fff; }
  .btn-brand:hover{ background:#146c43; color:#fff; }
</style>
</head>
<body>
{$flashHtml}
<div class="card card-login p-4 m-3">
  <div class="text-center mb-4">
    <i class="bi bi-file-earmark-medical fs-1 brand"></i>
    <h4 class="brand mt-2">Death Registration System</h4>
    <p class="text-muted small">Sign in to your account</p>
  </div>
  <form method="post" action="?page=login">
    <input type="hidden" name="csrf_token" value="{$token}">
    <div class="mb-3">
      <label class="form-label">Username</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-person"></i></span>
        <input type="text" name="username" class="form-control" placeholder="Enter username" required autofocus>
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label">Password</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-lock"></i></span>
        <input type="password" name="password" class="form-control" placeholder="Enter password" required>
      </div>
    </div>
    <button type="submit" class="btn btn-brand w-100 py-2"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</button>
  </form>
  <div class="mt-3 text-center">
    <small class="text-muted">Death Registration System v1.0</small>
  </div>
</div>
</body>
</html>
HTML;
    }

    public static function login(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Flash::set('danger', 'Invalid or expired form submission. Please try again.');
            header('Location: ?page=login');
            exit;
        }

        $v = new Validator();
        $v->required($_POST, 'username', 'Username')
          ->required($_POST, 'password', 'Password');

        if (!$v->passes()) {
            Flash::set('danger', implode(' ', $v->errors()));
            header('Location: ?page=login');
            exit;
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (RateLimiter::exceeded('login', $ip)) {
            $remaining = RateLimiter::remaining('login', $ip);
            Flash::set('warning', "Too many login attempts. Please wait before trying again. ($remaining remaining)");
            header('Location: ?page=login');
            exit;
        }

        if (RateLimiter::exceeded('login', $username)) {
            Flash::set('warning', 'This account has been temporarily locked due to too many attempts. Please try again later.');
            header('Location: ?page=login');
            exit;
        }

        $result = Auth::attempt($username, $password);

        if ($result['ok']) {
            RateLimiter::reset('login', $ip);
            RateLimiter::reset('login', $username);
            Flash::set('success', 'Welcome back, ' . htmlspecialchars(Auth::user()['full_name']) . '!');
            header('Location: ?page=dashboard');
            exit;
        }

        Flash::set('danger', $result['message']);
        header('Location: ?page=login');
        exit;
    }

    public static function logout(): void
    {
        Auth::logout();
        session_start();
        Flash::set('info', 'You have been logged out successfully.');
        session_write_close();
        header('Location: ?page=login');
        exit;
    }
}

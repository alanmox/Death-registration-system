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

  #flash-container{ position:fixed; top:0; left:0; right:0; bottom:0; z-index:9999; display:flex; align-items:center; justify-content:center; pointer-events:none; }
  .flash-card{ pointer-events:auto; display:flex; align-items:center; gap:1rem; min-width:340px; max-width:480px; padding:1.25rem 1.5rem; border-radius:1rem; box-shadow:0 12px 48px rgba(0,0,0,.18); animation:flashPop .4s cubic-bezier(.34,1.56,.64,1) forwards; position:relative; }
  .flash-card.flash-success{ background:linear-gradient(135deg,#d4edda,#c3e6cb); border-left:5px solid #28a745; }
  .flash-card.flash-danger{ background:linear-gradient(135deg,#f8d7da,#f5c6cb); border-left:5px solid #dc3545; }
  .flash-card.flash-warning{ background:linear-gradient(135deg,#fff3cd,#ffeaa7); border-left:5px solid #ffc107; }
  .flash-card.flash-info{ background:linear-gradient(135deg,#d1ecf1,#bee5eb); border-left:5px solid #17a2b8; }
  .flash-icon{ font-size:2rem; line-height:1; flex-shrink:0; }
  .flash-success .flash-icon{ color:#28a745; }
  .flash-danger .flash-icon{ color:#dc3545; }
  .flash-warning .flash-icon{ color:#e0a800; }
  .flash-info .flash-icon{ color:#17a2b8; }
  .flash-body{ flex:1; }
  .flash-title{ font-weight:700; font-size:1rem; text-transform:uppercase; letter-spacing:.5px; }
  .flash-message{ font-size:.9rem; color:#333; margin-top:2px; }
  .flash-close{ background:none; border:none; font-size:1.5rem; line-height:1; cursor:pointer; color:#666; padding:0; align-self:flex-start; }
  .flash-close:hover{ color:#000; }
  .flash-card.fade-out{ animation:flashOut .35s ease forwards; }
  @keyframes flashPop{ 0%{ opacity:0; transform:scale(.6) translateY(-20px); } 100%{ opacity:1; transform:scale(1) translateY(0); } }
  @keyframes flashOut{ 0%{ opacity:1; transform:scale(1); } 100%{ opacity:0; transform:scale(.8) translateY(20px); } }
</style>
<script>
function dismissFlash(btn) {
  var card = btn.closest('.flash-card');
  if (!card) return;
  card.classList.add('fade-out');
  setTimeout(function() {
    card.remove();
    var container = document.getElementById('flash-container');
    if (container && container.querySelectorAll('.flash-card').length === 0) {
      container.remove();
    }
  }, 350);
}
document.addEventListener('DOMContentLoaded', function() {
  setTimeout(function() {
    document.querySelectorAll('.flash-card').forEach(function(card) {
      card.classList.add('fade-out');
      setTimeout(function() { card.remove(); }, 350);
    });
    setTimeout(function() {
      var container = document.getElementById('flash-container');
      if (container) container.remove();
    }, 400);
  }, 2500);
});
</script>
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

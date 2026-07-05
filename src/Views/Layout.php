<?php
declare(strict_types=1);

final class Layout
{
    public static function render(string $title, string $content, bool $withChrome = true): string
    {
        $user = Auth::user();
        $csrf = Csrf::token();

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?> · Death Registration System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
  :root{ --brand:#0b3d2e; --brand-light:#146c43; }
  body{ background:#f4f6f8; font-family:'Segoe UI',Roboto,Arial,sans-serif; }
  .navbar-brand{ font-weight:600; letter-spacing:.3px; }
  .sidebar{ min-height: calc(100vh - 56px); background:#fff; border-right:1px solid #e3e6ea; }
  .sidebar a{ color:#333; text-decoration:none; display:block; padding:.6rem 1rem; border-radius:.4rem; }
  .sidebar a.active, .sidebar a:hover{ background:var(--brand); color:#fff; }
  .navbar-custom{ background: var(--brand) !important; }
  .card-stat{ border:none; border-radius:.8rem; box-shadow:0 2px 10px rgba(0,0,0,.06); }
  .badge-status-pending{ background:#ffc107;color:#212529; }
  .badge-status-approved{ background:#198754; }
  .badge-status-rejected{ background:#dc3545; }
  .certificate{ border:8px double var(--brand); padding:2.5rem; background:#fffdf7; }
  @media print { .no-print{ display:none !important; } .certificate{ border:8px double #000; } }
  .table-responsive{ background:#fff; border-radius:.6rem; }
  .brand-mark{ font-size:1.4rem; }
</style>
</head>
<body>
<?= Flash::render() ?>
<?php if ($withChrome && $user): ?>
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom no-print">
  <div class="container-fluid">
    <a class="navbar-brand brand-mark" href="?page=dashboard"><i class="bi bi-file-earmark-medical"></i> DRS</a>
    <div class="d-flex align-items-center text-white ms-auto">
      <span class="me-3"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['full_name']) ?>
        <small class="text-white-50">(<?= htmlspecialchars($user['role']) ?>)</small></span>
      <form method="post" action="?page=logout" class="m-0">
        <?= Csrf::field() ?>
        <button class="btn btn-sm btn-outline-light">Logout</button>
      </form>
    </div>
  </div>
</nav>
<div class="d-flex">
  <div class="sidebar p-3 no-print" style="width:230px;">
    <?= self::navLink('dashboard', 'bi-speedometer2', 'Dashboard') ?>
    <?= self::navLink('deaths', 'bi-file-earmark-text', 'Death Records') ?>
    <?php if (Auth::can('deaths.create')): ?>
      <?= self::navLink('deaths_create', 'bi-plus-circle', 'New Registration') ?>
    <?php endif; ?>
    <?php if (Auth::can('reports.view')): ?>
      <?= self::navLink('reports', 'bi-bar-chart', 'Reports') ?>
    <?php endif; ?>
    <?php if (Auth::can('*')): ?>
      <?= self::navLink('users', 'bi-people', 'User Management') ?>
    <?php endif; ?>
    <?php if (Auth::can('audit.view') || Auth::can('*')): ?>
      <?= self::navLink('audit', 'bi-shield-check', 'Audit Logs') ?>
    <?php endif; ?>
  </div>
  <div class="flex-fill p-4">
<?php endif; ?>
    <?= $content ?>
<?php if ($withChrome && $user): ?>
  </div>
</div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    private static function navLink(string $page, string $icon, string $label): string
    {
        $active = ($_GET['page'] ?? 'dashboard') === $page ? 'active' : '';
        return '<a class="' . $active . '" href="?page=' . $page . '"><i class="bi ' . $icon . ' me-2"></i>' . $label . '</a>';
    }

    public static function alert(string $type, string $message, bool $escape = true): string
    {
        if ($escape) {
            $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        }
        return '<div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show" role="alert">'
            . $message
            . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}

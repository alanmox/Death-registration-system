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
function dismissFlash(btn){ var card=btn.closest('.flash-card'); card.classList.add('fade-out'); setTimeout(function(){ card.style.display='none'; if(!document.querySelector('.flash-card:not([style*=\"display: none\"])')){ document.getElementById('flash-container').style.display='none'; } },350); }
document.addEventListener('DOMContentLoaded',function(){ setTimeout(function(){ document.querySelectorAll('.flash-card').forEach(function(c){ c.classList.add('fade-out'); setTimeout(function(){ c.style.display='none'; },350); }); },4000); });
</script>
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

    public static function alert(string $type, string $message): string
    {
        return '<div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show" role="alert">'
            . htmlspecialchars($message)
            . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}

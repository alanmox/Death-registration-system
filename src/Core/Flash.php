<?php
declare(strict_types=1);

final class Flash
{
    private static bool $assetsRendered = false;

    public static function set(string $type, string $message): void
    {
        $_SESSION['_flash'] = [['type' => $type, 'message' => $message]];
    }

    public static function get(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }

    public static function has(): bool
    {
        return !empty($_SESSION['_flash']);
    }

    public static function assets(): string
    {
        if (self::$assetsRendered) {
            return '';
        }
        self::$assetsRendered = true;

        return <<<'HTML'
<style>
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
(function() {
  function dismissCard(card) {
    if (!card || card.classList.contains('fade-out')) return;
    card.classList.add('fade-out');
    card.style.pointerEvents = 'none';
    setTimeout(function() {
      card.remove();
      var container = document.getElementById('flash-container');
      if (container && container.querySelectorAll('.flash-card').length === 0) {
        container.remove();
      }
    }, 350);
  }
  function initFlash() {
    var cards = document.querySelectorAll('.flash-card');
    if (cards.length === 0) return;
    document.addEventListener('click', function(e) {
      if (e.target && e.target.classList.contains('flash-close')) {
        dismissCard(e.target.closest('.flash-card'));
      }
    });
    setTimeout(function() {
      cards.forEach(function(card) { dismissCard(card); });
    }, 2500);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFlash);
  } else {
    initFlash();
  }
})();
</script>
HTML;
    }

    public static function render(): string
    {
        $messages = self::get();
        if (empty($messages)) {
            return '';
        }

        $icons = [
            'success' => 'bi-check-circle-fill',
            'danger'  => 'bi-x-circle-fill',
            'warning' => 'bi-exclamation-triangle-fill',
            'info'    => 'bi-info-circle-fill',
        ];

        $html = '
<style>
  #flash-container{ position:fixed; top:20px; left:0; right:0; z-index:9999; display:flex; flex-direction:column; align-items:center; pointer-events:none; gap:10px; }
  .flash-card{ pointer-events:auto; display:flex; align-items:center; gap:1rem; min-width:340px; max-width:480px; padding:1rem 1.25rem; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,.15); animation:flashPop .3s ease forwards; position:relative; }
  .flash-card.flash-success{ background:#d4edda; border-left:4px solid #28a745; color:#155724; }
  .flash-card.flash-danger{ background:#f8d7da; border-left:4px solid #dc3545; color:#721c24; }
  .flash-card.flash-warning{ background:#fff3cd; border-left:4px solid #ffc107; color:#856404; }
  .flash-card.flash-info{ background:#d1ecf1; border-left:4px solid #17a2b8; color:#0c5460; }
  .flash-icon{ font-size:1.5rem; line-height:1; }
  .flash-body{ flex:1; }
  .flash-title{ font-weight:700; font-size:.9rem; text-transform:uppercase; letter-spacing:.5px; margin-bottom:2px; }
  .flash-message{ font-size:.9rem; }
  .flash-close{ background:none; border:none; font-size:1.25rem; line-height:1; cursor:pointer; color:inherit; opacity:0.6; padding:0; align-self:flex-start; margin-top:2px; }
  .flash-close:hover{ opacity:1; }
  .flash-card.fade-out{ animation:flashOut .3s ease forwards; }
  @keyframes flashPop{ 0%{ opacity:0; transform:translateY(-20px); } 100%{ opacity:1; transform:translateY(0); } }
  @keyframes flashOut{ 0%{ opacity:1; transform:translateY(0); } 100%{ opacity:0; transform:translateY(-20px); } }
</style>
<script>
if (typeof dismissFlash === "undefined") {
  function dismissFlash(btn) {
    var card = btn.closest(".flash-card");
    if (!card) return;
    card.classList.add("fade-out");
    setTimeout(function() {
      card.remove();
      var container = document.getElementById("flash-container");
      if (container && container.children.length === 0) container.remove();
    }, 300);
  }
  document.addEventListener("DOMContentLoaded", function() {
    setTimeout(function() {
      document.querySelectorAll(".flash-card").forEach(function(card) {
        card.classList.add("fade-out");
        setTimeout(function() { card.remove(); }, 300);
      });
      setTimeout(function() {
        var container = document.getElementById("flash-container");
        if (container) container.remove();
      }, 350);
    }, 2500);
  });
}
</script>
<div id="flash-container">';
        foreach ($messages as $msg) {
            $type = htmlspecialchars($msg['type']);
            $icon = $icons[$msg['type']] ?? 'bi-info-circle-fill';
            $html .= '<div class="flash-card flash-' . $type . '">
                <div class="flash-icon"><i class="bi ' . $icon . '"></i></div>
                <div class="flash-body">
                    <div class="flash-title">' . htmlspecialchars(ucfirst($msg['type'])) . '</div>
                    <div class="flash-message">' . htmlspecialchars($msg['message']) . '</div>
                </div>
                <button type="button" class="flash-close" onclick="dismissFlash(this)">&times;</button>
            </div>';
        }
        $html .= '</div>';
        return $html;
    }
}

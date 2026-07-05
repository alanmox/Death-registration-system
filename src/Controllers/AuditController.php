<?php
declare(strict_types=1);

final class AuditController
{
    public static function index(): string
    {
        $logs = AuditLog::recent(50);
        $rows = '';
        foreach ($logs as $l) {
            $rows .= '<tr><td>' . htmlspecialchars($l['created_at']) . '</td>'
                . '<td>' . htmlspecialchars($l['username'] ?? 'system') . '</td>'
                . '<td>' . htmlspecialchars($l['action']) . '</td>'
                . '<td>' . htmlspecialchars($l['details'] ?? '') . '</td>'
                . '<td>' . htmlspecialchars($l['ip_address'] ?? '') . '</td></tr>';
        }
        $content = <<<HTML
<h3 class="mb-3"><i class="bi bi-shield-check"></i> Audit Logs</h3>
<div class="table-responsive p-2">
  <table class="table table-sm table-hover">
    <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
    <tbody>{$rows}</tbody>
  </table>
</div>
HTML;
        return Layout::render('Audit Logs', $content);
    }
}

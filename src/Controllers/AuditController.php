<?php
declare(strict_types=1);

final class AuditController
{
    public static function index(): string
    {
        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'action' => $_GET['action'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ];
        
        $page = max(1, (int)($_GET['p'] ?? 1));
        $perPage = 50;
        
        $logs = AuditLog::all($filters, $page, $perPage);
        $total = AuditLog::count($filters);
        $totalPages = max(1, (int)ceil($total / $perPage));

        $rows = '';
        foreach ($logs as $l) {
            $username = htmlspecialchars($l['username'] ?? 'system');
            if ($username === 'system') {
                $username = '<span class="badge bg-secondary">System</span>';
            }
            
            $action = htmlspecialchars($l['action']);
            $actionBadge = 'bg-primary';
            if (str_contains($action, 'delete')) $actionBadge = 'bg-danger';
            elseif (str_contains($action, 'create')) $actionBadge = 'bg-success';
            elseif (str_contains($action, 'update') || str_contains($action, 'edit') || str_contains($action, 'reset')) $actionBadge = 'bg-warning text-dark';
            elseif (str_contains($action, 'login')) $actionBadge = 'bg-info text-dark';
            
            $rows .= '<tr>'
                . '<td class="text-nowrap text-muted small">' . htmlspecialchars($l['created_at']) . '</td>'
                . '<td class="fw-semibold">' . $username . '</td>'
                . '<td><span class="badge ' . $actionBadge . '">' . $action . '</span></td>'
                . '<td>' . htmlspecialchars($l['details'] ?? '') . '</td>'
                . '<td class="font-monospace small text-muted">' . htmlspecialchars($l['ip_address'] ?? '') . '</td>'
                . '</tr>';
        }
        
        if (!$rows) {
            $rows = '<tr><td colspan="5" class="text-center text-muted py-5">No security audit logs match your criteria.</td></tr>';
        }

        $pagination = self::renderPagination($page, $totalPages, $filters);
        
        $qHtml = htmlspecialchars($filters['q']);
        $dateFromHtml = htmlspecialchars($filters['date_from']);
        $dateToHtml = htmlspecialchars($filters['date_to']);
        $actionHtml = htmlspecialchars($filters['action']);

        $content = <<<HTML
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="mb-0"><i class="bi bi-shield-lock"></i> Security Audit Logs</h3>
    <small class="text-muted">Monitor all system actions, user behaviors, and security events</small>
  </div>
</div>

<div class="card card-stat p-3 mb-4 shadow-sm border-0">
  <form method="get" action="" class="row g-2 align-items-end">
    <input type="hidden" name="page" value="audit">
    <div class="col-md-3">
      <label class="form-label text-muted small fw-bold text-uppercase">Search</label>
      <input type="text" name="q" value="{$qHtml}" class="form-control" placeholder="Username, IP, or Details...">
    </div>
    <div class="col-md-2">
      <label class="form-label text-muted small fw-bold text-uppercase">Action Type</label>
      <input type="text" name="action" value="{$actionHtml}" class="form-control" placeholder="e.g. login_success">
    </div>
    <div class="col-md-2">
      <label class="form-label text-muted small fw-bold text-uppercase">Date From</label>
      <input type="date" name="date_from" value="{$dateFromHtml}" class="form-control">
    </div>
    <div class="col-md-2">
      <label class="form-label text-muted small fw-bold text-uppercase">Date To</label>
      <input type="date" name="date_to" value="{$dateToHtml}" class="form-control">
    </div>
    <div class="col-md-3">
      <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Filter Logs</button>
    </div>
  </form>
</div>

<div class="card card-stat shadow-sm border-0">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="bg-light">
        <tr>
          <th class="text-muted text-uppercase small">Timestamp</th>
          <th class="text-muted text-uppercase small">User</th>
          <th class="text-muted text-uppercase small">Action</th>
          <th class="text-muted text-uppercase small">Details</th>
          <th class="text-muted text-uppercase small">IP Address</th>
        </tr>
      </thead>
      <tbody>{$rows}</tbody>
    </table>
  </div>
</div>

{$pagination}
HTML;
        return Layout::render('Audit Logs', $content);
    }

    private static function renderPagination(int $page, int $totalPages, array $filters): string
    {
        if ($totalPages <= 1) return '';
        $qsData = array_filter($filters, fn($v) => $v !== '');
        $qs = http_build_query($qsData);
        $qs = $qs ? '&' . $qs : '';
        
        $html = '<nav class="mt-4"><ul class="pagination justify-content-center shadow-sm">';
        
        // Prev
        $prevDisabled = $page <= 1 ? 'disabled' : '';
        $html .= "<li class=\"page-item {$prevDisabled}\"><a class=\"page-link\" href=\"?page=audit&p=" . ($page - 1) . "{$qs}\">Previous</a></li>";
        
        // Smart pagination window
        $start = max(1, $page - 3);
        $end = min($totalPages, $page + 3);
        
        if ($start > 1) {
            $html .= "<li class=\"page-item\"><a class=\"page-link\" href=\"?page=audit&p=1{$qs}\">1</a></li>";
            if ($start > 2) $html .= "<li class=\"page-item disabled\"><a class=\"page-link\">...</a></li>";
        }
        
        for ($i = $start; $i <= $end; $i++) {
            $active = $i === $page ? 'active' : '';
            $html .= "<li class=\"page-item $active\"><a class=\"page-link\" href=\"?page=audit&p={$i}{$qs}\">{$i}</a></li>";
        }
        
        if ($end < $totalPages) {
            if ($end < $totalPages - 1) $html .= "<li class=\"page-item disabled\"><a class=\"page-link\">...</a></li>";
            $html .= "<li class=\"page-item\"><a class=\"page-link\" href=\"?page=audit&p={$totalPages}{$qs}\">{$totalPages}</a></li>";
        }
        
        // Next
        $nextDisabled = $page >= $totalPages ? 'disabled' : '';
        $html .= "<li class=\"page-item {$nextDisabled}\"><a class=\"page-link\" href=\"?page=audit&p=" . ($page + 1) . "{$qs}\">Next</a></li>";
        
        $html .= '</ul></nav>';
        return $html;
    }
}

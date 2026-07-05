<?php
declare(strict_types=1);

final class DashboardController
{
    public static function index(): string
    {
        $user = Auth::user();
        $role = $user['role'];
        $userId = (int)$user['id'];
        
        $model = new DeathRecordModel();
        $pdo = Database::getInstance()->pdo();
        
        // Context-aware queries based on role
        $isClerk = in_array($role, ['data_entry_clerk', 'hospital_officer']);
        
        $period = $_GET['period'] ?? 'all_time';
        $dateFilter = "";
        if ($period === 'last_30_days') {
            $dateFilter = "date_of_death >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        } elseif ($period === 'this_year') {
            $dateFilter = "YEAR(date_of_death) = YEAR(CURDATE())";
        } elseif ($period === 'this_month') {
            $dateFilter = "YEAR(date_of_death) = YEAR(CURDATE()) AND MONTH(date_of_death) = MONTH(CURDATE())";
        }
        
        $whereClauses = [];
        if ($isClerk) {
            $whereClauses[] = "registered_by = " . $userId;
        }
        if ($dateFilter) {
            $whereClauses[] = $dateFilter;
        }
        
        $whereSql = $whereClauses ? "WHERE " . implode(' AND ', $whereClauses) : "";
        
        $stats = $pdo->query("SELECT status, COUNT(*) c FROM death_records $whereSql GROUP BY status")->fetchAll();
        $statusCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($stats as $s) {
            $statusCounts[$s['status']] = (int)$s['c'];
        }
        $total = array_sum($statusCounts);
        
        // Global charts (only show global to non-clerks, or show user-specific to clerks)
        $trend = $pdo->query("
            SELECT DATE_FORMAT(date_of_death, '%Y-%m') ym, COUNT(*) c
            FROM death_records $whereSql GROUP BY ym ORDER BY ym DESC LIMIT 12
        ")->fetchAll();
        $trend = array_reverse($trend);
        
        $genderStats = $pdo->query("SELECT gender, COUNT(*) c FROM death_records $whereSql GROUP BY gender")->fetchAll();
        $genderCounts = [];
        foreach ($genderStats as $s) {
            $genderCounts[$s['gender']] = (int)$s['c'];
        }

        $trendLabels = json_encode(array_column($trend, 'ym'));
        $trendData   = json_encode(array_map('intval', array_column($trend, 'c')));
        $genderLabels = json_encode(array_keys($genderCounts));
        $genderData   = json_encode(array_values($genderCounts));

        // Context-aware recent records
        $recentStmt = $pdo->query("
            SELECT d.*, u.full_name AS registered_by_name 
            FROM death_records d 
            LEFT JOIN users u ON u.id = d.registered_by 
            $whereSql 
            ORDER BY d.id DESC LIMIT 5
        ");
        $recent = $recentStmt->fetchAll();
        
        $rows = '';
        foreach ($recent as $rawR) {
            $r = array_map(fn($v) => is_string($v) ? htmlspecialchars($v, ENT_QUOTES, 'UTF-8') : $v, $rawR);
            $rows .= self::recentRow($r);
        }
        if (!$rows) {
            $rows = '<tr><td colspan="5" class="text-center text-muted">No records yet.</td></tr>';
        }
        
        // Quick Actions & Alerts
        $alerts = '';
        $quickActions = '';
        
        if (Auth::can('deaths.create')) {
            $quickActions .= '<a href="?page=deaths_create" class="btn btn-sm btn-success me-2"><i class="bi bi-plus-circle"></i> Register Death</a>';
        }
        
        if (Auth::can('deaths.approve') || Auth::can('*')) {
            $quickActions .= '<a href="?page=deaths&status=pending" class="btn btn-sm btn-warning me-2"><i class="bi bi-list-check"></i> Review Pending</a>';
            if ($statusCounts['pending'] > 0) {
                $alerts .= Layout::alert('warning', '<i class="bi bi-exclamation-triangle"></i> You have <strong>' . $statusCounts['pending'] . '</strong> death registration(s) waiting for your approval.');
            }
        }
        
        if (Auth::can('reports.view')) {
            $quickActions .= '<a href="?page=reports" class="btn btn-sm btn-info me-2"><i class="bi bi-bar-chart"></i> View Reports</a>';
        }

        $statLabelPre = $isClerk ? 'My ' : '';

        $periodOptions = '';
        $periods = [
            'all_time' => 'All Time',
            'this_month' => 'This Month',
            'last_30_days' => 'Last 30 Days',
            'this_year' => 'This Year'
        ];
        foreach ($periods as $val => $label) {
            $sel = $period === $val ? 'selected' : '';
            $periodOptions .= "<option value=\"$val\" $sel>$label</option>";
        }

        $content = <<<HTML
<div class="d-flex justify-content-between align-items-center mb-4">
  <h3 class="mb-0"><i class="bi bi-speedometer2"></i> Dashboard</h3>
  <div class="d-flex gap-2">
    <form method="get" action="" class="d-flex">
      <input type="hidden" name="page" value="dashboard">
      <select name="period" class="form-select form-select-sm" onchange="this.form.submit()">
        {$periodOptions}
      </select>
    </form>
    <div>{$quickActions}</div>
  </div>
</div>

{$alerts}

<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card card-stat p-3">
      <div class="text-muted small">{$statLabelPre}Total Records</div>
      <div class="fs-3 fw-bold">{$total}</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-stat p-3">
      <div class="text-muted small">{$statLabelPre}Pending Approval</div>
      <div class="fs-3 fw-bold text-warning">{$statusCounts['pending']}</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-stat p-3">
      <div class="text-muted small">{$statLabelPre}Approved Certificates</div>
      <div class="fs-3 fw-bold text-success">{$statusCounts['approved']}</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-stat p-3">
      <div class="text-muted small">{$statLabelPre}Rejected</div>
      <div class="fs-3 fw-bold text-danger">{$statusCounts['rejected']}</div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-8">
    <div class="card card-stat p-3">
      <div class="fw-semibold mb-2">{$statLabelPre}Monthly Registration Trend</div>
      <canvas id="trendChart" height="90"></canvas>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card card-stat p-3">
      <div class="fw-semibold mb-2">{$statLabelPre}Gender Distribution</div>
      <canvas id="genderChart" height="90"></canvas>
    </div>
  </div>
</div>

<div class="card card-stat p-3">
  <div class="fw-semibold mb-2">{$statLabelPre}Recent Registrations</div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Cert. No.</th><th>Deceased</th><th>Date of Death</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>{$rows}</tbody>
    </table>
  </div>
</div>

<script>
new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: { labels: {$trendLabels}, datasets: [{ label:'Registrations', data: {$trendData},
    borderColor:'#146c43', backgroundColor:'rgba(20,108,67,.15)', fill:true, tension:.3 }]},
  options: { plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true, ticks:{precision:0}}} }
});
new Chart(document.getElementById('genderChart'), {
  type: 'doughnut',
  data: { labels: {$genderLabels}, datasets:[{ data: {$genderData},
    backgroundColor:['#0b3d2e','#d63384','#ffc107'] }]},
  options: { plugins:{legend:{position:'bottom'}} }
});
</script>
HTML;
        return Layout::render('Dashboard', $content);
    }

    private static function recentRow(array $r): string
    {
        $badge = 'badge-status-' . $r['status'];
        return '<tr>'
            . '<td>' . htmlspecialchars($r['certificate_no'] ?? '') . '</td>'
            . '<td>' . htmlspecialchars($r['deceased_name']) . '</td>'
            . '<td>' . htmlspecialchars($r['date_of_death']) . '</td>'
            . '<td><span class="badge ' . $badge . '">' . htmlspecialchars(ucfirst($r['status'])) . '</span></td>'
            . '<td><a href="?page=deaths_view&id=' . (int)$r['id'] . '" class="btn btn-sm btn-outline-secondary">View</a></td>'
            . '</tr>';
    }
}

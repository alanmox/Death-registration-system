<?php
declare(strict_types=1);

final class DashboardController
{
    public static function index(): string
    {
        $model = new DeathRecordModel();
        $statusCounts = $model->statusCounts();
        $genderCounts = $model->genderCounts();
        $trend = $model->monthlyTrend();
        $total = $model->totalCount();

        $trendLabels = json_encode(array_column($trend, 'ym'));
        $trendData   = json_encode(array_map('intval', array_column($trend, 'c')));
        $genderLabels = json_encode(array_keys($genderCounts));
        $genderData   = json_encode(array_values($genderCounts));

        $recent = $model->all([], 1, 5);
        $rows = '';
        foreach ($recent as $r) {
            $rows .= self::recentRow($r);
        }
        if (!$rows) {
            $rows = '<tr><td colspan="5" class="text-center text-muted">No records yet.</td></tr>';
        }

        $content = <<<HTML
<h3 class="mb-4"><i class="bi bi-speedometer2"></i> Dashboard</h3>
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card card-stat p-3">
      <div class="text-muted small">Total Records</div>
      <div class="fs-3 fw-bold">{$total}</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-stat p-3">
      <div class="text-muted small">Pending Approval</div>
      <div class="fs-3 fw-bold text-warning">{$statusCounts['pending']}</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-stat p-3">
      <div class="text-muted small">Approved Certificates</div>
      <div class="fs-3 fw-bold text-success">{$statusCounts['approved']}</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card card-stat p-3">
      <div class="text-muted small">Rejected</div>
      <div class="fs-3 fw-bold text-danger">{$statusCounts['rejected']}</div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-8">
    <div class="card card-stat p-3">
      <div class="fw-semibold mb-2">Monthly Registration Trend</div>
      <canvas id="trendChart" height="90"></canvas>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card card-stat p-3">
      <div class="fw-semibold mb-2">Gender Distribution</div>
      <canvas id="genderChart" height="90"></canvas>
    </div>
  </div>
</div>

<div class="card card-stat p-3">
  <div class="fw-semibold mb-2">Recent Registrations</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Cert. No.</th><th>Deceased</th><th>Date of Death</th><th>Status</th><th></th></tr></thead>
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

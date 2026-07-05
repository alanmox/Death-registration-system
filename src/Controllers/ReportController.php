<?php
declare(strict_types=1);

final class ReportController
{
    public static function index(): string
    {
        // 1. Process filters
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        $status = $_GET['status'] ?? '';
        
        $pdo = Database::getInstance()->pdo();
        
        // Build WHERE clause for custom reporting
        $where = [];
        $params = [];
        if ($dateFrom) {
            $where[] = "date_of_death >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $where[] = "date_of_death <= ?";
            $params[] = $dateTo;
        }
        if ($status) {
            $where[] = "status = ?";
            $params[] = $status;
        }
        
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // 2. Fetch Data
        // Status Counts
        $statusCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        $stRows = $pdo->prepare("SELECT status, COUNT(*) c FROM death_records $whereSql GROUP BY status");
        $stRows->execute($params);
        foreach ($stRows->fetchAll() as $r) {
            $statusCounts[$r['status']] = (int)$r['c'];
        }
        $total = array_sum($statusCounts);
        
        // Region Stats
        $regRows = $pdo->prepare("SELECT region, COUNT(*) c FROM death_records $whereSql GROUP BY region ORDER BY c DESC LIMIT 10");
        $regRows->execute($params);
        $regions = $regRows->fetchAll();
        
        $regionLabels = json_encode(array_column($regions, 'region'));
        $regionData = json_encode(array_map('intval', array_column($regions, 'c')));
        
        $regionHtml = '';
        foreach ($regions as $r) {
            $perc = $total > 0 ? round(($r['c'] / $total) * 100, 1) : 0;
            $regionHtml .= '<tr>'
                . '<td class="fw-semibold">' . htmlspecialchars($r['region']) . '</td>'
                . '<td>' . (int)$r['c'] . '</td>'
                . '<td>'
                . '<div class="progress" style="height: 6px; margin-top: 8px;">'
                . '<div class="progress-bar bg-primary" role="progressbar" style="width: '.$perc.'%"></div>'
                . '</div>'
                . '</td>'
                . '<td class="text-end text-muted small">'.$perc.'%</td>'
                . '</tr>';
        }
        if (!$regionHtml) $regionHtml = '<tr><td colspan="4" class="text-center text-muted py-3">No regional data found.</td></tr>';
        
        // Cause of Death Top 5
        $causeRows = $pdo->prepare("SELECT cause_of_death, COUNT(*) c FROM death_records $whereSql GROUP BY cause_of_death ORDER BY c DESC LIMIT 5");
        $causeRows->execute($params);
        $causes = $causeRows->fetchAll();
        $causeHtml = '';
        foreach ($causes as $r) {
            $causeHtml .= '<li class="list-group-item d-flex justify-content-between align-items-center bg-transparent border-0 px-0 py-2 border-bottom">'
                . htmlspecialchars(ucfirst($r['cause_of_death']))
                . '<span class="badge bg-secondary rounded-pill">' . (int)$r['c'] . '</span>'
                . '</li>';
        }
        if (!$causeHtml) $causeHtml = '<li class="list-group-item text-muted text-center border-0">No cause of death data found.</li>';
        
        // Gender Distribution
        $genRows = $pdo->prepare("SELECT gender, COUNT(*) c FROM death_records $whereSql GROUP BY gender");
        $genRows->execute($params);
        $genders = $genRows->fetchAll();
        $genderLabels = json_encode(array_column($genders, 'gender'));
        $genderData = json_encode(array_map('intval', array_column($genders, 'c')));

        // 3. Render HTML
        $qsData = array_filter(['date_from'=>$dateFrom, 'date_to'=>$dateTo, 'status'=>$status], fn($v) => $v !== '');
        $exportUrl = '?page=export_csv' . ($qsData ? '&' . http_build_query($qsData) : '');
        
        $dateFromHtml = htmlspecialchars($dateFrom);
        $dateToHtml = htmlspecialchars($dateTo);
        
        $statusOptions = '<option value="">All Statuses</option>';
        foreach (['pending'=>'Pending', 'approved'=>'Approved', 'rejected'=>'Rejected'] as $v=>$l) {
            $sel = $status === $v ? 'selected' : '';
            $statusOptions .= "<option value=\"$v\" $sel>$l</option>";
        }

        $content = <<<HTML
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h3 class="mb-0"><i class="bi bi-file-earmark-bar-graph"></i> Advanced Analytics & Reports</h3>
    <small class="text-muted">Generate insights and export system data</small>
  </div>
  <a href="{$exportUrl}" class="btn btn-success shadow-sm"><i class="bi bi-file-earmark-excel"></i> Export CSV Report</a>
</div>

<div class="card card-stat p-3 mb-4 shadow-sm border-0">
  <form method="get" action="" class="row g-3 align-items-end">
    <input type="hidden" name="page" value="reports">
    <div class="col-6 col-sm-6 col-md-3">
      <label class="form-label text-muted small fw-bold text-uppercase">Date of Death (From)</label>
      <input type="date" name="date_from" value="{$dateFromHtml}" class="form-control">
    </div>
    <div class="col-6 col-sm-6 col-md-3">
      <label class="form-label text-muted small fw-bold text-uppercase">Date of Death (To)</label>
      <input type="date" name="date_to" value="{$dateToHtml}" class="form-control">
    </div>
    <div class="col-6 col-sm-6 col-md-3">
      <label class="form-label text-muted small fw-bold text-uppercase">Approval Status</label>
      <select name="status" class="form-select">{$statusOptions}</select>
    </div>
    <div class="col-6 col-sm-6 col-md-3">
      <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Generate Report</button>
    </div>
  </form>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card card-stat p-3 text-center border-0 shadow-sm h-100">
      <div class="text-muted text-uppercase small fw-bold mb-2">Total Extracted</div>
      <div class="fs-3 fw-bolder text-dark">{$total}</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card card-stat p-3 text-center border-0 shadow-sm h-100">
      <div class="text-muted text-uppercase small fw-bold mb-2">Approved</div>
      <div class="fs-3 fw-bolder text-success">{$statusCounts['approved']}</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card card-stat p-3 text-center border-0 shadow-sm h-100">
      <div class="text-muted text-uppercase small fw-bold mb-2">Pending Review</div>
      <div class="fs-3 fw-bolder text-warning">{$statusCounts['pending']}</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card card-stat p-3 text-center border-0 shadow-sm h-100">
      <div class="text-muted text-uppercase small fw-bold mb-2">Rejected</div>
      <div class="fs-3 fw-bolder text-danger">{$statusCounts['rejected']}</div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-12 col-md-8">
    <div class="card card-stat p-3 border-0 shadow-sm h-100">
      <h5 class="fw-bold mb-4">Registrations by Region</h5>
      <canvas id="regionChart" height="80"></canvas>
      <div class="table-responsive mt-4">
        <table class="table table-borderless align-middle mb-0">
          <thead class="border-bottom"><tr><th class="text-muted text-uppercase small">Region</th><th class="text-muted text-uppercase small">Count</th><th class="text-muted text-uppercase small w-50">Volume</th><th></th></tr></thead>
          <tbody>{$regionHtml}</tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-4 d-flex flex-column gap-3">
    <div class="card card-stat p-3 border-0 shadow-sm">
      <h5 class="fw-bold mb-4">Top Causes of Death</h5>
      <ul class="list-group list-group-flush">
        {$causeHtml}
      </ul>
    </div>
    <div class="card card-stat p-3 border-0 shadow-sm flex-grow-1">
      <h5 class="fw-bold mb-4">Gender Distribution</h5>
      <div style="max-width: 200px; margin: 0 auto;">
        <canvas id="reportGenderChart"></canvas>
      </div>
    </div>
  </div>
</div>

<script>
// Region Bar Chart
new Chart(document.getElementById('regionChart'), {
  type: 'bar',
  data: { 
    labels: {$regionLabels}, 
    datasets: [{ 
      label: 'Registrations', 
      data: {$regionData},
      backgroundColor: '#0b3d2e',
      borderRadius: 4
    }]
  },
  options: { 
    plugins: { legend: { display: false } }, 
    scales: { 
      y: { beginAtZero: true, ticks: { precision: 0 } },
      x: { grid: { display: false } }
    } 
  }
});

// Gender Doughnut Chart
new Chart(document.getElementById('reportGenderChart'), {
  type: 'doughnut',
  data: { 
    labels: {$genderLabels}, 
    datasets:[{ 
      data: {$genderData},
      backgroundColor:['#0b3d2e','#d63384','#ffc107'],
      borderWidth: 0
    }]
  },
  options: { 
    plugins: { legend: { position: 'bottom' } },
    cutout: '70%'
  }
});
</script>
HTML;
        return Layout::render('Reports', $content);
    }

    public static function exportCsv(): void
    {
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        $status = $_GET['status'] ?? '';
        
        $pdo = Database::getInstance()->pdo();
        
        $where = [];
        $params = [];
        if ($dateFrom) {
            $where[] = "date_of_death >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $where[] = "date_of_death <= ?";
            $params[] = $dateTo;
        }
        if ($status) {
            $where[] = "status = ?";
            $params[] = $status;
        }
        
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $stmt = $pdo->prepare("SELECT * FROM death_records $whereSql ORDER BY id DESC");
        $stmt->execute($params);
        $all = $stmt->fetchAll();
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="death_records_export_' . date('Ymd_His') . '.csv"');
        
        // Output BOM for Excel UTF-8 compatibility
        echo "\xEF\xBB\xBF";
        
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Certificate No', 'Deceased Name', 'Gender', 'Date of Birth', 'Date of Death', 'Place of Death', 'Cause of Death', 'Hospital Name', 'District', 'Region', 'Status', 'Applicant Name', 'Applicant Contact', 'System Timestamp']);
        
        foreach ($all as $r) {
            fputcsv($out, [
                $r['certificate_no'], 
                $r['deceased_name'], 
                $r['gender'], 
                $r['date_of_birth'],
                $r['date_of_death'], 
                $r['place_of_death'],
                $r['cause_of_death'],
                $r['hospital_name'],
                $r['district'], 
                $r['region'], 
                $r['status'],
                $r['applicant_name'],
                $r['applicant_contact'],
                $r['created_at'] ?? ''
            ]);
        }
        fclose($out);
        
        $auditDesc = 'Exported ' . count($all) . ' death records to CSV';
        if ($where) $auditDesc .= ' (Filtered)';
        AuditLog::record(Auth::user()['id'], 'export_csv', $auditDesc);
        
        exit;
    }
}

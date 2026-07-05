<?php
declare(strict_types=1);

final class ReportController
{
    public static function index(): string
    {
        $model = new DeathRecordModel();
        $status = $model->statusCounts();
        $gender = $model->genderCounts();
        $total = $model->totalCount();

        $rows = '';
        $pdo = Database::getInstance()->pdo();
        $byRegion = $pdo->query("SELECT region, COUNT(*) c FROM death_records GROUP BY region ORDER BY c DESC")->fetchAll();
        foreach ($byRegion as $r) {
            $rows .= '<tr><td>' . htmlspecialchars($r['region']) . '</td><td>' . (int)$r['c'] . '</td></tr>';
        }
        if (!$rows) $rows = '<tr><td colspan="2" class="text-muted text-center">No data</td></tr>';

        $exportUrl = '?page=export_csv';

        $content = <<<HTML
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3><i class="bi bi-bar-chart"></i> Reports & Statistics</h3>
  <a href="{$exportUrl}" class="btn btn-outline-success"><i class="bi bi-download"></i> Export CSV</a>
</div>
<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card card-stat p-3"><div class="text-muted small">Total</div><div class="fs-4 fw-bold">{$total}</div></div></div>
  <div class="col-md-3"><div class="card card-stat p-3"><div class="text-muted small">Pending</div><div class="fs-4 fw-bold text-warning">{$status['pending']}</div></div></div>
  <div class="col-md-3"><div class="card card-stat p-3"><div class="text-muted small">Approved</div><div class="fs-4 fw-bold text-success">{$status['approved']}</div></div></div>
  <div class="col-md-3"><div class="card card-stat p-3"><div class="text-muted small">Rejected</div><div class="fs-4 fw-bold text-danger">{$status['rejected']}</div></div></div>
</div>
<div class="card card-stat p-3">
  <div class="fw-semibold mb-2">Registrations by Region</div>
  <table class="table table-sm"><thead><tr><th>Region</th><th>Count</th></tr></thead><tbody>{$rows}</tbody></table>
</div>
HTML;
        return Layout::render('Reports', $content);
    }

    public static function exportCsv(): void
    {
        $model = new DeathRecordModel();
        $all = $model->all([], 1, 100000);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="death_records_export.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Certificate No', 'Deceased Name', 'Gender', 'Date of Death', 'District', 'Region', 'Status']);
        foreach ($all as $r) {
            fputcsv($out, [$r['certificate_no'], $r['deceased_name'], $r['gender'], $r['date_of_death'], $r['district'], $r['region'], $r['status']]);
        }
        fclose($out);
        AuditLog::record(Auth::user()['id'], 'export_csv', 'Exported death records to CSV');
        exit;
    }
}

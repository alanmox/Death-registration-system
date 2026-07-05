<?php
declare(strict_types=1);

final class DeathController
{
    public static function list(): string
    {
        $model = new DeathRecordModel();
        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'status' => $_GET['status'] ?? '',
            'region' => $_GET['region'] ?? '',
        ];
        $page = max(1, (int)($_GET['p'] ?? 1));
        $perPage = 10;
        $records = $model->all($filters, $page, $perPage);
        $total = $model->count($filters);
        $totalPages = max(1, (int)ceil($total / $perPage));

        $rows = '';
        foreach ($records as $r) {
            $badge = 'badge-status-' . $r['status'];
            $actions = '<a href="?page=deaths_view&id=' . (int)$r['id'] . '" class="btn btn-sm btn-outline-primary" title="View"><i class="bi bi-eye"></i></a> ';
            if (Auth::can('deaths.edit') || Auth::can('*')) {
                $actions .= '<a href="?page=deaths_edit&id=' . (int)$r['id'] . '" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a> ';
            }
            if (Auth::can('*')) {
                $actions .= '<form method="post" action="?page=deaths_delete" class="d-inline" onsubmit="return confirm(\'Delete this record?\');">'
                    . Csrf::field() . '<input type="hidden" name="id" value="' . (int)$r['id'] . '">'
                    . '<button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button></form>';
            }
            $rows .= '<tr>'
                . '<td>' . htmlspecialchars($r['certificate_no'] ?? '') . '</td>'
                . '<td>' . htmlspecialchars($r['deceased_name']) . '</td>'
                . '<td>' . htmlspecialchars($r['gender']) . '</td>'
                . '<td>' . htmlspecialchars($r['date_of_death']) . '</td>'
                . '<td>' . htmlspecialchars($r['region']) . '</td>'
                . '<td><span class="badge ' . $badge . '">' . htmlspecialchars(ucfirst($r['status'])) . '</span></td>'
                . '<td>' . $actions . '</td>'
                . '</tr>';
        }
        if (!$rows) {
            $rows = '<tr><td colspan="7" class="text-center text-muted py-4">No records found.</td></tr>';
        }

        $q = htmlspecialchars($filters['q']);
        $statusSel = $filters['status'];
        $statusOptions = '';
        foreach (['' => 'All Status', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $val => $label) {
            $sel = $val === $statusSel ? 'selected' : '';
            $statusOptions .= "<option value=\"$val\" $sel>$label</option>";
        }

        $pagination = self::renderPagination($page, $totalPages, $filters);

        $content = <<<HTML
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3><i class="bi bi-file-earmark-text"></i> Death Records</h3>
  <a href="?page=deaths_create" class="btn btn-success"><i class="bi bi-plus-circle"></i> New Registration</a>
</div>

<form method="get" action="" class="row g-2 mb-3">
  <input type="hidden" name="page" value="deaths">
  <div class="col-md-5">
    <input type="text" name="q" value="{$q}" class="form-control" placeholder="Search name, certificate no., applicant...">
  </div>
  <div class="col-md-3">
    <select name="status" class="form-select">{$statusOptions}</select>
  </div>
  <div class="col-md-2">
    <button class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filter</button>
  </div>
</form>

<div class="table-responsive p-2">
  <table class="table table-hover align-middle mb-0">
    <thead>
      <tr><th>Cert. No.</th><th>Deceased</th><th>Gender</th><th>Date of Death</th><th>Region</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>{$rows}</tbody>
  </table>
</div>
{$pagination}
HTML;
        return Layout::render('Death Records', $content);
    }

    private static function renderPagination(int $page, int $totalPages, array $filters): string
    {
        if ($totalPages <= 1) return '';
        $qs = http_build_query(array_filter(['q' => $filters['q'], 'status' => $filters['status']]));
        $qs = $qs ? '&' . $qs : '';
        $html = '<nav class="mt-3"><ul class="pagination">';
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = $i === $page ? 'active' : '';
            $html .= "<li class=\"page-item $active\"><a class=\"page-link\" href=\"?page=deaths&p={$i}{$qs}\">{$i}</a></li>";
        }
        $html .= '</ul></nav>';
        return $html;
    }

    public static function createForm(string $errorHtml = '', array $old = []): string
    {
        return self::form(null, $errorHtml, $old);
    }

    public static function editForm(int $id): string
    {
        $model = new DeathRecordModel();
        $record = $model->find($id);
        if (!$record) {
            return Layout::render('Not Found', Layout::alert('warning', 'Record not found.'));
        }
        return self::form($record, '', $record);
    }

    private static function form(?array $record, string $errorHtml, array $old): string
    {
        $isEdit = $record !== null;
        $id = $isEdit ? (int)$record['id'] : 0;
        $action = $isEdit ? '?page=deaths_update' : '?page=deaths_store';
        $title = $isEdit ? 'Edit Death Record' : 'New Death Registration';

        $g = fn($f) => htmlspecialchars((string)($old[$f] ?? ''));
        $genderOpt = function (string $g2) use ($old) {
            $sel = ($old['gender'] ?? '') === $g2 ? 'selected' : '';
            return "<option $sel>$g2</option>";
        };

        $idField = $isEdit ? '<input type="hidden" name="id" value="' . $id . '">' : '';
        $csrf = Csrf::field();

        $content = <<<HTML
<h3 class="mb-4">{$title}</h3>
{$errorHtml}
<div class="card card-stat p-4">
<form method="post" action="{$action}">
  {$csrf}
  {$idField}
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Deceased Full Name *</label>
      <input type="text" name="deceased_name" class="form-control" value="{$g('deceased_name')}" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">Gender *</label>
      <select name="gender" class="form-select" required>
        <option value="">Select</option>
        {$genderOpt('Male')}{$genderOpt('Female')}{$genderOpt('Other')}
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Date of Birth</label>
      <input type="date" name="date_of_birth" class="form-control" value="{$g('date_of_birth')}">
    </div>

    <div class="col-md-3">
      <label class="form-label">Date of Death *</label>
      <input type="date" name="date_of_death" class="form-control" value="{$g('date_of_death')}" required>
    </div>
    <div class="col-md-5">
      <label class="form-label">Place of Death *</label>
      <input type="text" name="place_of_death" class="form-control" value="{$g('place_of_death')}" required>
    </div>
    <div class="col-md-4">
      <label class="form-label">Hospital (if applicable)</label>
      <input type="text" name="hospital_name" class="form-control" value="{$g('hospital_name')}">
    </div>

    <div class="col-md-12">
      <label class="form-label">Cause of Death *</label>
      <textarea name="cause_of_death" class="form-control" rows="2" required>{$g('cause_of_death')}</textarea>
    </div>

    <div class="col-md-6">
      <label class="form-label">District *</label>
      <input type="text" name="district" class="form-control" value="{$g('district')}" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Region *</label>
      <input type="text" name="region" class="form-control" value="{$g('region')}" required>
    </div>

    <div class="col-12"><hr><h5>Applicant Information</h5></div>
    <div class="col-md-4">
      <label class="form-label">Applicant Name *</label>
      <input type="text" name="applicant_name" class="form-control" value="{$g('applicant_name')}" required>
    </div>
    <div class="col-md-4">
      <label class="form-label">Relationship to Deceased *</label>
      <input type="text" name="applicant_relationship" class="form-control" value="{$g('applicant_relationship')}" required>
    </div>
    <div class="col-md-4">
      <label class="form-label">Applicant Contact *</label>
      <input type="text" name="applicant_contact" class="form-control" value="{$g('applicant_contact')}" required>
    </div>
  </div>

  <div class="mt-4">
    <button class="btn btn-success"><i class="bi bi-save"></i> Save Registration</button>
    <a href="?page=deaths" class="btn btn-outline-secondary">Cancel</a>
  </div>
</form>
</div>
HTML;
        return Layout::render($title, $content);
    }

    public static function store(): void
    {
        self::requireCsrf();
        $data = self::sanitizeInput($_POST);
        $v = new Validator();
        $v->required($data, 'deceased_name', 'Deceased name')
          ->required($data, 'gender', 'Gender')
          ->required($data, 'date_of_death', 'Date of death')
          ->date($data, 'date_of_death', 'Date of death')
          ->date($data, 'date_of_birth', 'Date of birth')
          ->required($data, 'place_of_death', 'Place of death')
          ->required($data, 'cause_of_death', 'Cause of death')
          ->required($data, 'district', 'District')
          ->required($data, 'region', 'Region')
          ->required($data, 'applicant_name', 'Applicant name')
          ->required($data, 'applicant_relationship', 'Relationship')
          ->required($data, 'applicant_contact', 'Applicant contact')
          ->phone($data, 'applicant_contact', 'Applicant contact');

        if (!$v->passes()) {
            $errHtml = Layout::alert('danger', implode(' ', $v->errors()));
            echo DeathController::createForm($errHtml, $data);
            return;
        }

        $data['registered_by'] = Auth::user()['id'];
        $model = new DeathRecordModel();
        $id = $model->create($data);
        AuditLog::record(Auth::user()['id'], 'death_record_created', "Record #$id ({$data['deceased_name']})");
        Flash::set('success', 'Death record created successfully.');
        header('Location: ?page=deaths_view&id=' . $id);
        exit;
    }

    public static function update(): void
    {
        self::requireCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $data = self::sanitizeInput($_POST);
        $v = new Validator();
        $v->required($data, 'deceased_name', 'Deceased name')
          ->required($data, 'date_of_death', 'Date of death')
          ->date($data, 'date_of_death', 'Date of death')
          ->required($data, 'place_of_death', 'Place of death')
          ->required($data, 'cause_of_death', 'Cause of death');

        if (!$v->passes()) {
            $errHtml = Layout::alert('danger', implode(' ', $v->errors()));
            $data['id'] = $id;
            echo DeathController::form_public($id, $errHtml, $data);
            return;
        }

        $model = new DeathRecordModel();
        $model->update($id, $data);
        AuditLog::record(Auth::user()['id'], 'death_record_updated', "Record #$id");
        Flash::set('success', 'Death record updated successfully.');
        header('Location: ?page=deaths_view&id=' . $id);
        exit;
    }

    // small public wrapper so update() can re-render the form on validation failure
    public static function form_public(int $id, string $err, array $old): string
    {
        $model = new DeathRecordModel();
        $record = $model->find($id);
        return self::form($record, $err, $old);
    }

    public static function delete(): void
    {
        self::requireCsrf();
        if (!Auth::can('*')) {
            http_response_code(403);
            exit('Forbidden');
        }
        $id = (int)($_POST['id'] ?? 0);
        $model = new DeathRecordModel();
        $model->delete($id);
        AuditLog::record(Auth::user()['id'], 'death_record_deleted', "Record #$id");
        Flash::set('info', 'Death record deleted successfully.');
        header('Location: ?page=deaths');
        exit;
    }

    public static function view(int $id): string
    {
        $model = new DeathRecordModel();
        $raw = $model->find($id);
        if (!$raw) {
            return Layout::render('Not Found', Layout::alert('warning', 'Record not found.'));
        }
        
        $r = array_map(fn($v) => is_string($v) ? htmlspecialchars($v, ENT_QUOTES, 'UTF-8') : $v, $raw);
        $badge = 'badge-status-' . $r['status'];
        $csrf = Csrf::field();

        $approveButtons = '';
        if (($r['status'] === 'pending') && (Auth::can('deaths.approve') || Auth::can('*'))) {
            $approveButtons = <<<HTML
<form method="post" action="?page=deaths_status" class="d-inline">
  {$csrf}
  <input type="hidden" name="id" value="{$id}">
  <input type="hidden" name="status" value="approved">
  <button class="btn btn-success"><i class="bi bi-check-circle"></i> Approve</button>
</form>
<form method="post" action="?page=deaths_status" class="d-inline ms-2">
  {$csrf}
  <input type="hidden" name="id" value="{$id}">
  <input type="hidden" name="status" value="rejected">
  <button class="btn btn-danger"><i class="bi bi-x-circle"></i> Reject</button>
</form>
HTML;
        }

        $certificateBlock = '';
        if ($r['status'] === 'approved') {
            $certificateBlock = self::certificateHtml($r);
        }

        $content = <<<HTML
<div class="d-flex justify-content-between align-items-center mb-3 no-print">
  <h3><i class="bi bi-file-earmark-text"></i> Record #{$r['id']}</h3>
  <div>
    <a href="?page=deaths" class="btn btn-outline-secondary">Back</a>
    {$approveButtons}
  </div>
</div>

<div class="card card-stat p-4 mb-4 no-print">
  <div class="row">
    <div class="col-md-6">
      <p><b>Certificate No.:</b> {$r['certificate_no']}</p>
      <p><b>Deceased Name:</b> {$r['deceased_name']}</p>
      <p><b>Gender:</b> {$r['gender']}</p>
      <p><b>Date of Birth:</b> {$r['date_of_birth']}</p>
      <p><b>Date of Death:</b> {$r['date_of_death']}</p>
      <p><b>Place of Death:</b> {$r['place_of_death']}</p>
      <p><b>Cause of Death:</b> {$r['cause_of_death']}</p>
      <p><b>Hospital:</b> {$r['hospital_name']}</p>
    </div>
    <div class="col-md-6">
      <p><b>District / Region:</b> {$r['district']} / {$r['region']}</p>
      <p><b>Applicant:</b> {$r['applicant_name']} ({$r['applicant_relationship']})</p>
      <p><b>Applicant Contact:</b> {$r['applicant_contact']}</p>
      <p><b>Status:</b> <span class="badge {$badge}">{$r['status']}</span></p>
      <p><b>Registered By:</b> {$r['registered_by_name']}</p>
      <p><b>Approved By:</b> {$r['approved_by_name']}</p>
    </div>
  </div>
</div>

{$certificateBlock}
HTML;
        return Layout::render('Record Detail', $content);
    }

    private static function certificateHtml(array $r): string
    {
        $qrData = urlencode('CERT:' . $r['certificate_no']);
        return <<<HTML
<div class="certificate mb-3">
  <div class="text-center mb-3">
    <h4 class="mb-0">OFFICIAL CERTIFICATE OF DEATH</h4>
    <small class="text-muted">Civil Registration Authority</small>
  </div>
  <hr>
  <div class="row">
    <div class="col-md-9">
      <p>This is to certify that <b>{$r['deceased_name']}</b> ({$r['gender']}),
      passed away on <b>{$r['date_of_death']}</b> at <b>{$r['place_of_death']}</b>,
      {$r['district']}, {$r['region']}.</p>
      <p><b>Cause of Death:</b> {$r['cause_of_death']}</p>
      <p><b>Certificate Number:</b> {$r['certificate_no']}</p>
      <p><b>Applicant:</b> {$r['applicant_name']} ({$r['applicant_relationship']})</p>
    </div>
    <div class="col-md-3 text-center">
      <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={$qrData}" alt="QR verification code" class="img-fluid">
      <div class="small text-muted mt-1">Scan to verify</div>
    </div>
  </div>
  <div class="text-end mt-4 no-print">
    <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print Certificate</button>
  </div>
</div>
HTML;
    }

    public static function setStatus(): void
    {
        self::requireCsrf();
        if (!Auth::can('deaths.approve') && !Auth::can('*')) {
            http_response_code(403);
            exit('Forbidden');
        }
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['approved', 'rejected'], true)) {
            exit('Invalid status');
        }
        $model = new DeathRecordModel();
        $model->setStatus($id, $status, Auth::user()['id']);
        AuditLog::record(Auth::user()['id'], 'death_status_changed', "Record #$id -> $status");
        $label = $status === 'approved' ? 'approved' : 'rejected';
        Flash::set('success', "Death record {$label} successfully.");
        header('Location: ?page=deaths_view&id=' . $id);
        exit;
    }

    private static function requireCsrf(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            exit('Invalid or expired form submission (CSRF check failed). Please go back and try again.');
        }
    }

    private static function sanitizeInput(array $data): array
    {
        $fields = ['deceased_name','gender','date_of_birth','date_of_death','place_of_death',
            'cause_of_death','hospital_name','district','region','applicant_name',
            'applicant_relationship','applicant_contact'];
        $clean = [];
        foreach ($fields as $f) {
            $clean[$f] = trim((string)($data[$f] ?? ''));
        }
        return $clean;
    }
}

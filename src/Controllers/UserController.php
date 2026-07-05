<?php
declare(strict_types=1);

final class UserController
{
    public static function index(): string
    {
        $model = new UserModel();
        
        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'role' => $_GET['role'] ?? '',
            'status' => $_GET['status'] ?? '',
        ];
        
        $page = max(1, (int)($_GET['p'] ?? 1));
        $perPage = 10;
        
        $users = $model->all($filters, $page, $perPage);
        $total = $model->count($filters);
        $totalPages = max(1, (int)ceil($total / $perPage));

        $rows = '';
        $modals = '';
        $csrf = Csrf::field();

        foreach ($users as $u) {
            $id = (int)$u['id'];
            $isActive = (bool)$u['is_active'];
            $statusBadge = $isActive ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
            $toggleBtnClass = $isActive ? 'btn-outline-warning' : 'btn-outline-success';
            $toggleIcon = $isActive ? 'bi-pause-circle' : 'bi-play-circle';
            $toggleTitle = $isActive ? 'Deactivate' : 'Activate';
            $newStatus = $isActive ? 0 : 1;

            $rows .= '<tr>'
                . '<td>' . htmlspecialchars($u['username']) . '</td>'
                . '<td>' . htmlspecialchars($u['full_name']) . '</td>'
                . '<td>' . htmlspecialchars(ucwords(str_replace('_', ' ', $u['role']))) . '</td>'
                . '<td>' . $statusBadge . '</td>'
                . '<td>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal'.$id.'" title="Edit">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <form method="post" action="?page=users_toggle" class="d-inline" onsubmit="return confirm(\'Are you sure you want to change the status of this user?\');">
                        '.$csrf.'
                        <input type="hidden" name="id" value="'.$id.'">
                        <input type="hidden" name="is_active" value="'.$newStatus.'">
                        <button type="submit" class="btn btn-sm '.$toggleBtnClass.'" title="'.$toggleTitle.'"><i class="bi '.$toggleIcon.'"></i></button>
                    </form>
                    <form method="post" action="?page=users_delete" class="d-inline" onsubmit="return confirm(\'WARNING: Are you sure you want to PERMANENTLY delete this user? This action cannot be undone!\');">
                        '.$csrf.'
                        <input type="hidden" name="id" value="'.$id.'">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                    </form>
                   </td>'
                . '</tr>';

            $modals .= self::renderEditModal($u, $csrf);
        }

        if (!$rows) {
            $rows = '<tr><td colspan="5" class="text-center text-muted py-4">No users found matching your criteria.</td></tr>';
        }

        $addUserModal = self::renderAddModal($csrf);
        $pagination = self::renderPagination($page, $totalPages, $filters);
        
        // Search & Filter Form HTML
        $qHtml = htmlspecialchars($filters['q']);
        $roleOptions = '<option value="">All Roles</option>';
        foreach (['registrar' => 'Registrar', 'hospital_officer' => 'Hospital Officer', 'data_entry_clerk' => 'Data Entry Clerk', 'auditor' => 'Auditor', 'super_admin' => 'Super Administrator'] as $val => $label) {
            $sel = $filters['role'] === $val ? 'selected' : '';
            $roleOptions .= "<option value=\"$val\" $sel>$label</option>";
        }
        $statusOptions = '<option value="">All Statuses</option>';
        $statusOptions .= '<option value="1" '.($filters['status'] === '1' ? 'selected' : '').'>Active</option>';
        $statusOptions .= '<option value="0" '.($filters['status'] === '0' ? 'selected' : '').'>Inactive</option>';

        $content = <<<HTML
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3><i class="bi bi-people"></i> User Management</h3>
  <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-person-plus"></i> Add New User</button>
</div>

<form method="get" action="" class="row g-2 mb-3">
  <input type="hidden" name="page" value="users">
  <div class="col-md-5">
    <input type="text" name="q" value="{$qHtml}" class="form-control" placeholder="Search by username or full name...">
  </div>
  <div class="col-md-3">
    <select name="role" class="form-select">{$roleOptions}</select>
  </div>
  <div class="col-md-2">
    <select name="status" class="form-select">{$statusOptions}</select>
  </div>
  <div class="col-md-2">
    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Filter</button>
  </div>
</form>

<div class="card card-stat p-3">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Username</th><th>Full Name</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>{$rows}</tbody>
    </table>
  </div>
</div>

{$pagination}
{$addUserModal}
{$modals}
HTML;
        return Layout::render('User Management', $content);
    }

    private static function renderPagination(int $page, int $totalPages, array $filters): string
    {
        if ($totalPages <= 1) return '';
        $qsData = array_filter($filters, fn($v) => $v !== '');
        $qs = http_build_query($qsData);
        $qs = $qs ? '&' . $qs : '';
        
        $html = '<nav class="mt-4"><ul class="pagination justify-content-center">';
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = $i === $page ? 'active' : '';
            $html .= "<li class=\"page-item $active\"><a class=\"page-link\" href=\"?page=users&p={$i}{$qs}\">{$i}</a></li>";
        }
        $html .= '</ul></nav>';
        return $html;
    }

    private static function renderAddModal(string $csrf): string
    {
        return <<<HTML
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="?page=users_store">
        <div class="modal-header">
          <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          {$csrf}
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input class="form-control" name="username" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input class="form-control" name="full_name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input class="form-control" type="password" name="password" required minlength="8">
          </div>
          <div class="mb-3">
            <label class="form-label">Role</label>
            <select class="form-select" name="role" required>
              <option value="registrar">Registrar</option>
              <option value="hospital_officer">Hospital Officer</option>
              <option value="data_entry_clerk">Data Entry Clerk</option>
              <option value="auditor">Auditor</option>
              <option value="super_admin">Super Administrator</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Create User</button>
        </div>
      </form>
    </div>
  </div>
</div>
HTML;
    }

    private static function renderEditModal(array $u, string $csrf): string
    {
        $id = (int)$u['id'];
        $username = htmlspecialchars($u['username']);
        $fullName = htmlspecialchars($u['full_name']);
        
        $roles = ['registrar' => 'Registrar', 'hospital_officer' => 'Hospital Officer', 'data_entry_clerk' => 'Data Entry Clerk', 'auditor' => 'Auditor', 'super_admin' => 'Super Administrator'];
        $roleOptions = '';
        foreach ($roles as $val => $label) {
            $sel = $u['role'] === $val ? 'selected' : '';
            $roleOptions .= "<option value=\"$val\" $sel>$label</option>";
        }

        return <<<HTML
<div class="modal fade" id="editUserModal{$id}" tabindex="-1" aria-labelledby="editUserModalLabel{$id}" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="?page=users_update">
        <div class="modal-header">
          <h5 class="modal-title" id="editUserModalLabel{$id}">Edit User: {$username}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          {$csrf}
          <input type="hidden" name="id" value="{$id}">
          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input class="form-control" name="full_name" value="{$fullName}" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Role</label>
            <select class="form-select" name="role" required>
              {$roleOptions}
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted">Reset Password (leave blank to keep current)</label>
            <input class="form-control" type="password" name="password" minlength="8">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>
HTML;
    }

    public static function store(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            exit('Invalid form submission.');
        }
        
        $v = new Validator();
        $v->required($_POST, 'username', 'Username')
          ->required($_POST, 'full_name', 'Full Name')
          ->required($_POST, 'password', 'Password')
          ->required($_POST, 'role', 'Role');

        if (!$v->passes()) {
            echo Layout::render('User Management', Layout::alert('danger', implode(' ', $v->errors())) . self::index());
            return;
        }

        $model = new UserModel();
        $username = trim($_POST['username'] ?? '');
        $existing = $model->findByUsername($username);
        if ($existing) {
            echo Layout::render('User Management', Layout::alert('danger', 'Username already exists.') . self::index());
            return;
        }
        $model->create([
            'username' => $username,
            'password' => (string)($_POST['password'] ?? ''),
            'full_name' => trim($_POST['full_name'] ?? ''),
            'role' => $_POST['role'] ?? 'data_entry_clerk',
        ]);
        AuditLog::record(Auth::user()['id'], 'user_created', "Created user $username");
        Flash::set('success', 'User created successfully.');
        header('Location: ?page=users');
        exit;
    }

    public static function update(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            exit('Invalid form submission.');
        }

        $v = new Validator();
        $v->required($_POST, 'id', 'User ID')
          ->required($_POST, 'full_name', 'Full Name')
          ->required($_POST, 'role', 'Role');

        if (!$v->passes()) {
            echo Layout::render('User Management', Layout::alert('danger', implode(' ', $v->errors())) . self::index());
            return;
        }

        $id = (int)$_POST['id'];
        $model = new UserModel();
        $user = $model->find($id);

        if (!$user) {
            echo Layout::render('User Management', Layout::alert('danger', 'User not found.') . self::index());
            return;
        }

        $data = [
            'full_name' => trim($_POST['full_name']),
            'role' => $_POST['role'],
            'is_active' => $user['is_active']
        ];

        // If updating password
        if (!empty($_POST['password'])) {
            $pdo = $model->pdo();
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), $id]);
            AuditLog::record(Auth::user()['id'], 'user_password_reset', "Reset password for user ID $id");
        }

        $model->update($id, $data);
        AuditLog::record(Auth::user()['id'], 'user_updated', "Updated user ID $id");
        Flash::set('success', 'User updated successfully.');
        header('Location: ?page=users');
        exit;
    }

    public static function toggleStatus(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            exit('Invalid form submission.');
        }

        $id = (int)($_POST['id'] ?? 0);
        $isActive = (int)($_POST['is_active'] ?? 0);

        if ($id === Auth::user()['id']) {
            echo Layout::render('User Management', Layout::alert('danger', 'You cannot deactivate your own account.') . self::index());
            return;
        }

        $model = new UserModel();
        $model->toggleStatus($id, $isActive);
        
        $statusStr = $isActive ? 'activated' : 'deactivated';
        AuditLog::record(Auth::user()['id'], 'user_status_changed', "User ID $id was $statusStr");
        Flash::set('success', "User {$statusStr} successfully.");
        header('Location: ?page=users');
        exit;
    }

    public static function delete(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            exit('Invalid form submission.');
        }

        $id = (int)($_POST['id'] ?? 0);

        if ($id === Auth::user()['id']) {
            echo Layout::render('User Management', Layout::alert('danger', 'You cannot delete your own account.') . self::index());
            return;
        }

        $model = new UserModel();
        $model->delete($id);
        
        AuditLog::record(Auth::user()['id'], 'user_deleted', "Permanently deleted User ID $id");
        Flash::set('info', 'User deleted permanently.');
        header('Location: ?page=users');
        exit;
    }
}

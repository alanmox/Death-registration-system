<?php
declare(strict_types=1);

final class UserController
{
    public static function index(): string
    {
        $model = new UserModel();
        $users = $model->all();
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
                . '<td>' . htmlspecialchars($u['role']) . '</td>'
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
                   </td>'
                . '</tr>';

            // Edit Modal for each user
            $modals .= self::renderEditModal($u, $csrf);
        }

        $addUserModal = self::renderAddModal($csrf);

        $content = <<<HTML
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3><i class="bi bi-people"></i> User Management</h3>
  <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-person-plus"></i> Add New User</button>
</div>
<div class="card card-stat p-3">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead><tr><th>Username</th><th>Full Name</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>{$rows}</tbody>
    </table>
  </div>
</div>
{$addUserModal}
{$modals}
HTML;
        return Layout::render('User Management', $content);
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
        
        header('Location: ?page=users');
        exit;
    }
}

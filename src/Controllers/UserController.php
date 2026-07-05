<?php
declare(strict_types=1);

final class UserController
{
    public static function index(): string
    {
        $model = new UserModel();
        $users = $model->all();
        $rows = '';
        foreach ($users as $u) {
            $statusBadge = $u['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
            $rows .= '<tr><td>' . htmlspecialchars($u['username']) . '</td><td>' . htmlspecialchars($u['full_name']) . '</td>'
                . '<td>' . htmlspecialchars($u['role']) . '</td><td>' . $statusBadge . '</td></tr>';
        }
        $csrf = Csrf::field();
        $content = <<<HTML
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3><i class="bi bi-people"></i> User Management</h3>
</div>
<div class="row">
  <div class="col-md-7">
    <div class="table-responsive p-2">
      <table class="table table-hover">
        <thead><tr><th>Username</th><th>Full Name</th><th>Role</th><th>Status</th></tr></thead>
        <tbody>{$rows}</tbody>
      </table>
    </div>
  </div>
  <div class="col-md-5">
    <div class="card card-stat p-3">
      <div class="fw-semibold mb-2">Add New User</div>
      <form method="post" action="?page=users_store">
        {$csrf}
        <div class="mb-2"><input class="form-control" name="username" placeholder="Username" required></div>
        <div class="mb-2"><input class="form-control" name="full_name" placeholder="Full Name" required></div>
        <div class="mb-2"><input class="form-control" type="password" name="password" placeholder="Password" required minlength="8"></div>
        <div class="mb-2">
          <select class="form-select" name="role" required>
            <option value="registrar">Registrar</option>
            <option value="hospital_officer">Hospital Officer</option>
            <option value="data_entry_clerk">Data Entry Clerk</option>
            <option value="auditor">Auditor</option>
            <option value="super_admin">Super Administrator</option>
          </select>
        </div>
        <button class="btn btn-success w-100">Create User</button>
      </form>
    </div>
  </div>
</div>
HTML;
        return Layout::render('User Management', $content);
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
}

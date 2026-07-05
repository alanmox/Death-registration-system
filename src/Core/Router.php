<?php
declare(strict_types=1);

final class Router
{
    public static function dispatch(): void
    {
        $page = $_GET['page'] ?? ($_POST['page'] ?? 'dashboard');
        $method = $_SERVER['REQUEST_METHOD'];

        switch ($page) {
            case 'login':
                if ($method === 'POST') { AuthController::login(); return; }
                if (Auth::check()) { header('Location: ?page=dashboard'); exit; }
                echo AuthController::loginPage();
                return;

            case 'logout':
                AuthController::logout();
                return;

            case 'dashboard':
                Auth::requireLogin();
                echo DashboardController::index();
                return;

            case 'deaths':
                Auth::requirePermission('deaths.view');
                echo DeathController::list();
                return;

            case 'deaths_create':
                Auth::requirePermission('deaths.create');
                echo DeathController::createForm();
                return;

            case 'deaths_store':
                Auth::requirePermission('deaths.create');
                DeathController::store();
                return;

            case 'deaths_edit':
                Auth::requirePermission('deaths.edit');
                echo DeathController::editForm((int)($_GET['id'] ?? 0));
                return;

            case 'deaths_update':
                Auth::requirePermission('deaths.edit');
                DeathController::update();
                return;

            case 'deaths_delete':
                Auth::requireLogin();
                DeathController::delete();
                return;

            case 'deaths_view':
                Auth::requirePermission('deaths.view');
                echo DeathController::view((int)($_GET['id'] ?? 0));
                return;

            case 'deaths_status':
                Auth::requireLogin();
                DeathController::setStatus();
                return;

            case 'reports':
                Auth::requirePermission('reports.view');
                echo ReportController::index();
                return;

            case 'export_csv':
                Auth::requirePermission('reports.view');
                ReportController::exportCsv();
                return;

            case 'users':
                Auth::requirePermission('*');
                echo UserController::index();
                return;

            case 'users_store':
                Auth::requirePermission('*');
                UserController::store();
                return;

            case 'users_update':
                Auth::requirePermission('*');
                UserController::update();
                return;

            case 'users_toggle':
                Auth::requirePermission('*');
                UserController::toggleStatus();
                return;

            case 'audit':
                Auth::requireLogin();
                if (!Auth::can('audit.view') && !Auth::can('*')) {
                    http_response_code(403);
                    echo Layout::render('Access Denied', Layout::alert('danger', '403 — Access denied.'));
                    return;
                }
                echo AuditController::index();
                return;

            default:
                http_response_code(404);
                echo Layout::render('Not Found', Layout::alert('warning', 'Page not found.'), Auth::check());
                return;
        }
    }
}

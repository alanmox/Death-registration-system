<?php
/**
 * =====================================================================
 *  DEATH REGISTRATION SYSTEM  —  MVC Edition
 * =====================================================================
 *  A complete, working Object-Oriented PHP + PDO web application.
 *
 *  WHAT THIS IS
 *  ------------
 *  A real, runnable civil-registration system: authentication, RBAC,
 *  dashboard with live statistics, full CRUD + approval workflow for
 *  death records, certificate generation/printing, search & pagination,
 *  and an audit trail — fully restructured into MVC components.
 *
 *  DATABASE
 *  --------
 *  Uses SQLite (via PDO) so it runs with ZERO setup — no MySQL user,
 *  password, or schema import needed. A file called
 *  "death_registration.sqlite" is created automatically next to this
 *  script on first run, complete with tables and a seeded admin user.
 *
 *  To use MySQL instead, only the connect() method of the Database
 *  class needs to change (DSN + credentials) — everything else
 *  (all SQL is standard ANSI/PDO) works unchanged.
 *
 *  HOW TO RUN
 *  ----------
 *  Option A (instant, no server install):
 *      php -S localhost:8000 index.php
 *      then open http://localhost:8000
 *
 *  Option B (XAMPP / WAMP / LAMP):
 *      Copy this folder into htdocs/ (or www/), then visit it in the
 *      browser through your local Apache server.
 *
 *  DEFAULT LOGIN
 *  --------------
 *      Username: admin
 *      Password: Admin@123
 *  (You will be encouraged to change this after first login.)
 *
 *  SECURITY FEATURES IMPLEMENTED
 *  ------------------------------
 *   - PDO prepared statements everywhere (no string-built SQL)
 *   - Password hashing with password_hash()/password_verify()
 *   - CSRF tokens on every state-changing form
 *   - Output escaping (htmlspecialchars) to prevent XSS
 *   - Session regeneration on login, session timeout
 *   - Role-Based Access Control (RBAC)
 *   - Account lockout after repeated failed logins
 *   - Full audit log of sensitive actions
 *
 *  OOP CONCEPTS DEMONSTRATED
 *  --------------------------
 *   - Classes/Objects, Constructors, Encapsulation (Database, Auth,
 *     DeathRecordModel, Validator, Csrf, AuditLog, Router)
 *   - Singleton pattern (Database::getInstance)
 *   - Inheritance + Abstraction (BaseModel -> DeathRecordModel, UserModel)
 *   - Interfaces (Crudable)
 *   - Static methods & properties
 *   - Method overriding (find(), validate())
 * =====================================================================
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0'); // production-style: log, don't leak
ini_set('log_errors', '1');

// ---------------------------------------------------------------------
// SESSION HARDENING
// ---------------------------------------------------------------------
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS'])) {
    ini_set('session.cookie_secure', '1');
}
session_start();

const SESSION_TIMEOUT_SECONDS = 1800; // 30 minutes
const MAX_LOGIN_ATTEMPTS       = 5;
const LOCKOUT_SECONDS          = 300; // 5 minutes


// ---------------------------------------------------------------------
// SECURITY HEADERS (applied to every response)
// ---------------------------------------------------------------------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; img-src 'self' data: https://api.qrserver.com; font-src 'self' https://cdn.jsdelivr.net; connect-src 'self';");


spl_autoload_register(function ($class) {
    $dirs = ['Config', 'Core', 'Models', 'Controllers', 'Views'];
    foreach ($dirs as $dir) {
        $file = __DIR__ . "/src/$dir/$class.php";
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});


// =====================================================================
//  BOOTSTRAP
// =====================================================================
try {
    Database::getInstance(); // ensures schema exists before anything else
    Router::dispatch();
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo '<h3 style="font-family:sans-serif;color:#b00;">Something went wrong.</h3>'
        . '<p style="font-family:sans-serif;">Please try again, or check the server error log for details.</p>';
}

<?php
/**
 * admin/_bootstrap.php
 * ------------------------------------------------------------
 * Shared bootstrap for the GAIA Admin Panel.
 *
 * Loads the platform's existing config, PDO, dynamic bootstrap,
 * auth services, CSRF helpers and template helpers, then enforces
 * the admin (super_admin) guard.
 *
 * Also exposes common admin helpers:
 *   - admin_base()          : URL prefix back to the site root
 *   - admin_url($path)      : build an absolute admin URL
 *   - admin_user()          : current admin user row
 *   - admin_flash($msg,$type): set/read one-time flash messages
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../components/bootstrap.php';
require_once __DIR__ . '/../components/gaia-config.php';
require_once __DIR__ . '/../auth/Auth.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../db.php';

// Reusable admin services (lazy-safe, some may not exist everywhere).
require_once __DIR__ . '/../services/ReportingService.php';
require_once __DIR__ . '/../services/BookingSummaryService.php';
require_once __DIR__ . '/../services/BookingService.php';
require_once __DIR__ . '/../services/PaymentService.php';
require_once __DIR__ . '/../services/InvoiceService.php';

require_once __DIR__ . '/helpers/AdminQuery.php';
require_once __DIR__ . '/helpers/CrudService.php';

/**
 * Enforce the admin guard. Guests go to /login.php; non-admins go home.
 */
function require_admin()
{
    Auth::startSession();
    if (!Auth::check()) {
        Auth::loginViaRememberToken();
    }
    if (!Auth::check()) {
        $target = 'admin/index.php';
        header('Location: ' . (function_exists('gaia_url') ? gaia_url('login.php') : 'login.php') . '?redirect=' . rawurlencode($target));
        exit;
    }
    $role = Auth::role();
    if ($role !== 'super_admin' && $role !== 'admin') {
        header('Location: ' . (function_exists('gaia_url') ? gaia_url('index.php') : 'index.php'));
        exit;
    }
}

/**
 * Enforce the super_admin guard. Non-super_admins go home or get 403.
 */
function require_super_admin()
{
    require_admin();
    if (Auth::role() !== 'super_admin') {
        http_response_code(403);
        echo "403 Forbidden - Super Admin access required.";
        exit;
    }
}

/**
 * Return the current admin user row (or null).
 */
function admin_user()
{
    static $admin = null;
    if ($admin === null) {
        $admin = Auth::user();
    }
    return $admin;
}

/**
 * Absolute site-root URL for an admin resource.
 */
function admin_url(string $path = ''): string
{
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/')), '/');
    // Ensure the returned path is rooted at /admin.
    $base = '/admin';
    return $base . '/' . ltrim($path, '/');
}

/**
 * One-time flash message helper (session based).
 */
function admin_flash(?string $msg = null, string $type = 'success')
{
    Auth::startSession();
    if ($msg !== null) {
        $_SESSION['admin_flash'] = ['type' => $type, 'msg' => $msg];
        return;
    }
    if (!empty($_SESSION['admin_flash'])) {
        $f = $_SESSION['admin_flash'];
        unset($_SESSION['admin_flash']);
        return $f;
    }
    return null;
}

/**
 * Read the current pending flash (without clearing) — used by layout.
 */
function admin_flash_peek()
{
    Auth::startSession();
    return $_SESSION['admin_flash'] ?? null;
}

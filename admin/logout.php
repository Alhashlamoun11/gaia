<?php
/**
 * admin/logout.php
 * ------------------------------------------------------------
 * POST-only logout with CSRF verification, reusing Auth::logout().
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/_bootstrap.php';

Auth::startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed. Use POST to logout.');
}
if (!csrf_verify()) {
    csrf_fail();
}

Auth::logout();

// Clear the admin-only location, then send home.
header('Location: ' . admin_url('login.php'));
exit;

<?php
/**
 * logout.php
 * ------------------------------------------------------------
 * POST-only logout with CSRF verification.
 * Destroys session + remember-me cookie, then redirects home.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/components/bootstrap.php';
require_once __DIR__ . '/components/gaia-config.php';
require_once __DIR__ . '/auth/Auth.php';
require_once __DIR__ . '/auth/csrf.php';

Auth::startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // GET logout is disabled for CSRF safety; show a confirm page.
    http_response_code(405);
    echo 'Method not allowed. Use POST to logout.';
    exit;
}

if (!csrf_verify()) {
    csrf_fail();
}

Auth::logout();
header('Location: index.php');
exit;

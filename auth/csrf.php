<?php
/**
 * auth/csrf.php
 * ------------------------------------------------------------
 * Lightweight CSRF protection helpers.
 *
 * Provides:
 *   - csrf_token()      : generate/retrieve a per-session token
 *   - csrf_field()      : render a hidden <input> for forms
 *   - csrf_verify()     : validate a submitted token (POST)
 *
 * Tokens are stored in the session and rotated on every request
 * to mitigate token-fixation attacks.
 * ------------------------------------------------------------
 */

if (!function_exists('csrf_token')) {
    /**
     * Return the current CSRF token, generating one if absent.
     */
    function csrf_token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Render a hidden CSRF input field.
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
             . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrf_verify')) {
    /**
     * Verify the submitted CSRF token against the session token.
     * Expects the token in $_POST['csrf_token'] (or $_GET fallback).
     */
    function csrf_verify(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $token = $_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? '');
        if ($token === '' || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('csrf_fail')) {
    /**
     * Abort the request with a 419 CSRF error.
     */
    function csrf_fail()
    {
        http_response_code(419);
        exit('CSRF token mismatch.');
    }
}

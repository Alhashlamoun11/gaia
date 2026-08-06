<?php
/**
 * auth/middleware.php
 * ------------------------------------------------------------
 * Convenience guards for protected pages.
 *
 *   require_login()          : redirect guests to /login
 *   require_role('...')      : restrict to specific roles
 *   require_permission('...'): restrict by permission
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/csrf.php';

if (!function_exists('redirect_to')) {
    /**
     * Redirect using a locale-aware URL when the GAIA bootstrap
     * (gaia_url) is available, otherwise fall back to the raw path.
     */
    function redirect_to(string $url)
    {
        if (function_exists('gaia_url')) {
            $url = gaia_url($url);
        }
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('safe_redirect_target')) {
    /**
     * Validate an internal redirect target to prevent open-redirect.
     * Only allows relative paths that start with '/' or a known base.
     * Returns '' if the target is not safe (caller falls back).
     */
    function safe_redirect_target($target): string
    {
        $target = trim((string)$target);
        if ($target === '' || $target === '#') {
            return '';
        }
        // Reject absolute URLs (http/https//) and protocol-relative (//)
        if (preg_match('#^(https?:)?//#i', $target)) {
            return '';
        }
        // Reject control chars / backslashes
        if (preg_match('/[\x00-\x1f\x7f\\\\]/', $target)) {
            return '';
        }
        // Allow relative paths (e.g. checkout.php?route_id=.., ../weroad/booking.php)
        if (strpos($target, '/') === 0 || strpos($target, '.') === 0 || strpos($target, 'index.php') === 0) {
            return $target;
        }
        // Allow bare script names (e.g. checkout.php)
        if (preg_match('/^[a-z0-9_\-]+\.php(\?.*)?$/i', $target)) {
            return $target;
        }
        return '';
    }
}

if (!function_exists('require_booking_login')) {
    /**
     * Require authentication for a booking flow and return the user to
     * the original booking page after login.
     *
     * Guests are redirected to login.php?redirect=<safe internal path>.
     */
    function require_booking_login(?string $returnUrl = null)
    {
        Auth::startSession();
        if (!Auth::check()) {
            Auth::loginViaRememberToken();
        }
        if (Auth::check()) {
            return;
        }
        $target = safe_redirect_target($returnUrl);
        $query  = $target !== '' ? '?redirect=' . rawurlencode($target) : '';
        redirect_to('login.php' . $query);
    }
}

if (!function_exists('require_login')) {
    /**
     * Redirect to login if no authenticated user.
     */
    function require_login()
    {
        Auth::startSession();

        // Attempt remember-me auto-login for guests
        if (!Auth::check()) {
            Auth::loginViaRememberToken();
        }
        if (!Auth::check()) {
            redirect_to('login.php');
        }
    }
}

if (!function_exists('require_role')) {
    /**
     * Restrict a page to one or more role slugs.
     * Guests/others are redirected away.
     */
    function require_role(string ...$roles)
    {
        require_login();
        $role = Auth::role();
        if (!in_array($role, $roles, true)) {
            redirect_to('index.php');
        }
    }
}

if (!function_exists('require_permission')) {
    /**
     * Restrict a page to users with a given permission.
     */
    function require_permission(string $permission)
    {
        require_login();
        if (!Auth::hasPermission($permission)) {
            redirect_to('index.php');
        }
    }
}

if (!function_exists('redirect_if_guest_or_else')) {
    /**
     * If already logged in, redirect to the matching dashboard.
     */
    function redirect_if_guest_or_else()
    {
        Auth::startSession();
        if (!Auth::check()) {
            return;
        }
        $role = Auth::role();
        if ($role === 'super_admin') {
            redirect_to('index.php');
        } elseif ($role === 'hotel_manager') {
            redirect_to('index.php');
        }
        redirect_to('account/index.php');
    }
}

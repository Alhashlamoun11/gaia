<?php
/**
 * admin/middleware/admin_auth.php
 * ------------------------------------------------------------
 * Alias guard for admin pages. Requires the super_admin role.
 * This just re-uses require_admin() from _bootstrap.php so pages
 * can include it directly without pulling the whole bootstrap
 * helper set (keeps dependencies explicit).
 * ------------------------------------------------------------
 */

if (!function_exists('require_admin')) {
    require_once __DIR__ . '/../_bootstrap.php';
}

/**
 * Convenience wrapper so pages that already loaded _bootstrap.php
 * can call require_admin() without a second include.
 */
function admin_guard()
{
    require_admin();
}

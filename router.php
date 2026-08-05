<?php
/**
 * router.php — PHP Built-in Server Front Controller
 * ------------------------------------------------------------
 * The PHP built-in server (`php -S localhost:8000`) does NOT
 * process `.htaccess` rewrite rules. This router mirrors the
 * Apache mod_rewrite rules in `.htaccess` so the SAME clean
 * multilingual URLs work in both development and production.
 *
 * Run:  php -S localhost:8000 router.php
 *
 * Supported routes (identical to .htaccess):
 *   /en/                    -> index.php?lang=en
 *   /ar/                    -> index.php?lang=ar
 *   /en/tours               -> weroad/search.php?lang=en
 *   /ar/tours/jordan-360    -> weroad/trip.php?lang=ar&slug=jordan-360
 *   /en/gaia-night          -> gaia-night.php?lang=en
 *   /en/transfer-booking    -> route.php?lang=en
 *   /en/about               -> page.php?lang=en&slug=about
 *   ... and it passes through any existing static file.
 * ------------------------------------------------------------
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$q   = $_GET; // existing query params (QSA)

/**
 * Rewrite the path to a target script + merge query params.
 * Returns false when no rewrite applies (fall through to static).
 */
function rewrite($path, $query)
{
    // Normalise trailing slash
    $p = rtrim($path, '/');

// --- Language-aware routes ---
    if (preg_match('#^/(en|ar)/tours/(.+)$#', $p, $m)) {
        $query['lang'] = $m[1];
        $query['slug'] = $m[2];
        return ['tour.php', $query];
    }
    if (preg_match('#^/(en|ar)/events/(.+)$#', $p, $m)) {
        $query['lang'] = $m[1];
        $query['slug'] = $m[2];
        return ['events.php', $query];
    }
    if (preg_match('#^/(en|ar)/hotels/(.+)$#', $p, $m)) {
        $query['lang'] = $m[1];
        $query['slug'] = $m[2];
        return ['hotel.php', $query];
    }
    if (preg_match('#^/(en|ar)/rooms/(\d+)$#', $p, $m)) {
        $query['lang'] = $m[1];
        $query['id'] = $m[2];
        return ['room.php', $query];
    }
    if (preg_match('#^/(en|ar)/tours$#', $p, $m)) {
        $query['lang'] = $m[1];
        return ['weroad/search.php', $query];
    }
    if (preg_match('#^/(en|ar)/destinations$#', $p, $m)) {
        $query['lang'] = $m[1];
        $query['destinations'] = 1;
        return ['weroad/search.php', $query];
    }
    if (preg_match('#^/(en|ar)/gaia-night$#', $p, $m)) {
        $query['lang'] = $m[1];
        return ['gaia-night.php', $query];
    }
    if (preg_match('#^/(en|ar)/events$#', $p, $m)) {
        $query['lang'] = $m[1];
        return ['gaia-night.php', $query];
    }
    if (preg_match('#^/(en|ar)/reviews$#', $p, $m)) {
        $query['lang'] = $m[1];
        return ['index.php', $query];
    }
    if (preg_match('#^/(en|ar)/hotels$#', $p, $m)) {
        $query['lang'] = $m[1];
        return ['index.php', $query];
    }
    if (preg_match('#^/(en|ar)/transfers$#', $p, $m)) {
        $query['lang'] = $m[1];
        return ['route.php', $query];
    }
    if (preg_match('#^/(en|ar)/(route|transfer-booking)$#', $p, $m)) {
        $query['lang'] = $m[1];
        return ['route.php', $query];
    }
    if (preg_match('#^/(en|ar)/checkout$#', $p, $m)) {
        $query['lang'] = $m[1];
        return ['checkout.php', $query];
    }
    if (preg_match('#^/(en|ar)/(about|contact)$#', $p, $m)) {
        $query['lang'] = $m[1];
        $query['slug'] = $m[2];
        return ['page.php', $query];
    }
    // Homepage with language prefix
    if (preg_match('#^/(en|ar)$#', $p, $m)) {
        $query['lang'] = $m[1];
        return ['index.php', $query];
    }

// --- Non-prefixed clean routes (defaults to English) ---
    if ($p === '/tours') {
        return ['weroad/search.php', $query];
    }
    if (preg_match('#^/tours/(.+)$#', $p, $m)) {
        $query['slug'] = $m[1];
        return ['tour.php', $query];
    }
    if (preg_match('#^/events/(.+)$#', $p, $m)) {
        $query['slug'] = $m[1];
        return ['events.php', $query];
    }
    if (preg_match('#^/hotels/(.+)$#', $p, $m)) {
        $query['slug'] = $m[1];
        return ['hotel.php', $query];
    }
    if (preg_match('#^/rooms/(\d+)$#', $p, $m)) {
        $query['id'] = $m[1];
        return ['room.php', $query];
    }
    if ($p === '/destinations') {
        $query['destinations'] = 1;
        return ['weroad/search.php', $query];
    }
    if ($p === '/gaia-night') {
        return ['gaia-night.php', $query];
    }
    if ($p === '/checkout') {
        return ['checkout.php', $query];
    }
    if ($p === '/transfer-booking' || $p === '/transfers' || $p === '/route') {
        return ['route.php', $query];
    }
    if ($p === '/about') {
        $query['slug'] = 'about';
        return ['page.php', $query];
    }
    if ($p === '/contact') {
        $query['slug'] = 'contact';
        return ['page.php', $query];
    }
    // Anchor sections that live on the platform home page
    if ($p === '/reviews' || $p === '/hotels') {
        return ['index.php', $query];
    }
    // Anchor section that lives on the GAIA Night page
    if ($p === '/events') {
        return ['gaia-night.php', $query];
    }

    return false;
}

$target = rewrite($uri, $q);

if ($target !== false) {
    list($script, $query) = $target;
    // Merge rewritten query params into $_GET
    $_GET = array_merge($_GET, $query);
    $file = __DIR__ . '/' . $script;
    if (file_exists($file)) {
        require $file;
        return true;
    }
}

// Assets / static files / existing PHP files: serve directly.
$file = __DIR__ . $uri;
if ($uri !== '/' && file_exists($file)) {
    return false; // let the built-in server serve it
}

// Anything else under /en/ or /ar/ that doesn't exist -> homepage with lang
$path = ltrim($uri, '/');
if (preg_match('#^(en|ar)/#', $path, $m)) {
    $_GET['lang'] = $m[1];
    require __DIR__ . '/index.php';
    return true;
}

// Fallback to the homepage (default English).
require __DIR__ . '/index.php';
return true;

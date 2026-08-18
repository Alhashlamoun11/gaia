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
    // Auth & account (language-prefixed)
    if (preg_match('#^/(en|ar)/login$#', $p, $m)) {
        $query['lang'] = $m[1];
        return ['login.php', $query];
    }
    if (preg_match('#^/(en|ar)/register$#', $p, $m)) {
        $query['lang'] = $m[1];
        return ['register.php', $query];
    }
    if (preg_match('#^/(en|ar)/forgot-password$#', $p, $m)) {
        $query['lang'] = $m[1];
        return ['forgot-password.php', $query];
    }
    if (preg_match('#^/(en|ar)/reset-password$#', $p, $m)) {
        $query['lang'] = $m[1];
        return ['reset-password.php', $query];
    }
    if (preg_match('#^/(en|ar)/logout$#', $p, $m)) {
        $query['lang'] = $m[1];
        return ['logout.php', $query];
    }
if (preg_match('#^/(en|ar)/account/(dashboard|bookings|payments|invoices|profile|security|claim-booking|my-bookings)$#', $p, $m)) {
        $query['lang'] = $m[1];
        $file = $m[2] === 'dashboard' ? 'index' : $m[2];
        return ['account/' . $file . '.php', $query];
    }
    if (preg_match('#^/(en|ar)/account/(bookings|booking)/([A-Za-z0-9\-]+)$#', $p, $m)) {
        $query['lang'] = $m[1];
        $query['reference'] = $m[3];
        return ['account/booking.php', $query];
    }
    if (preg_match('#^/(en|ar)/account$#', $p, $m)) {
        $query['lang'] = $m[1];
        return ['account/index.php', $query];
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
    // Auth & account (non-prefixed, default English)
    if ($p === '/login') {
        return ['login.php', $query];
    }
    if ($p === '/register') {
        return ['register.php', $query];
    }
    if ($p === '/forgot-password') {
        return ['forgot-password.php', $query];
    }
    if ($p === '/reset-password') {
        return ['reset-password.php', $query];
    }
    if ($p === '/logout') {
        return ['logout.php', $query];
    }
    if (preg_match('#^/account/(profile|my-bookings|claim-booking)$#', $p, $m)) {
        return ['account/' . $m[1] . '.php', $query];
    }
    if ($p === '/account') {
        return ['account/index.php', $query];
    }
    // Anchor sections that live on the platform home page
    if ($p === '/reviews' || $p === '/hotels') {
        return ['index.php', $query];
    }
// Anchor section that lives on the GAIA Night page
    if ($p === '/events') {
        return ['gaia-night.php', $query];
    }

    // Authoritative fallback mirroring .htaccess's generic rule:
    // /en/<script>.php -> <script>.php?lang=en
    // This makes /en/login.php, /en/register.php, /en/account/index.php etc.
    // work under the PHP built-in server (no physical en/ directory).
    if (preg_match('#^/(en|ar)/([A-Za-z0-9_\-/]+\.php)$#', $p, $m)) {
        $query['lang'] = $m[1];
        return [$m[2], $query];
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

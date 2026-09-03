<?php
/**
 * bootstrap.php
 * ------------------------------------------------------------
 * Central runtime bootstrap for the GAIA TOURS & TRAVEL platform.
 *
 * Loads ALL site configuration from MySQL into in-memory arrays:
 *   - settings  (company, contact, social, pricing, newsletter)
 *   - languages (EN/AR)
 *   - translations (all translatable strings)
 *   - menu_items (header + footer navigation)
 *   - seo_meta (per-page titles/descriptions)
 *   - social_links
 *   - transfer_extras / tour_extras (add-on pricing)
 *
 * This makes every public page fully database-driven so the
 * future Admin Panel can edit content without touching code.
 * ------------------------------------------------------------
 */

// Prevent double-loading
if (defined('GAIA_BOOTSTRAP_LOADED')) {
    return;
}
define('GAIA_BOOTSTRAP_LOADED', true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth/Auth.php';
Auth::startSession();

/**
 * Load a settings array from a table.
 */
function gaia_load_rows($table, $cacheKey, $keyCol = 'id')
{
    static $cache = [];
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    try {
        $pdo = getPDO();
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll();
        $cache[$cacheKey] = $rows;
        return $rows;
    } catch (PDOException $e) {
        $cache[$cacheKey] = [];
        return [];
    }
}

// ------------------------------------------------------------
// SETTINGS
// ------------------------------------------------------------
function gaia_settings()
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }
    $settings = [];
    foreach (gaia_load_rows('settings', 'settings') as $row) {
        $settings[$row['key']] = $row['value'];
    }
    return $settings;
}

/**
 * Get one setting value (with optional default).
 */
function site_setting($key, $default = '')
{
    $s = gaia_settings();
    return isset($s[$key]) && $s[$key] !== '' ? $s[$key] : $default;
}

// ------------------------------------------------------------
// LOCALIZED CONTENT HELPER
// ------------------------------------------------------------
/**
 * Return the localized value for a content field.
 *
 * If the current language is 'ar' and the row has a `{field}_ar`
 * column with a non-empty value, return that. Otherwise fall back
 * to the original (English) field.
 *
 * Usage:
 *   $event['title']     = original (EN)
 *   $event['title_ar']  = Arabic
 *   lang_value($event, 'title')  ->  localized title
 */
function lang_value($row, $field, $fallback = '')
{
    $lang = gaia_current_lang();
    if ($lang === 'ar' && isset($row[$field . '_ar'])) {
        $arVal = trim((string)$row[$field . '_ar']);
        if ($arVal !== '') {
            return $arVal;
        }
    }
    if (isset($row[$field]) && trim((string)$row[$field]) !== '') {
        return $row[$field];
    }
    return $fallback;
}

/**
 * Return a localized URL-safe optional value (falls back to EN slug).
 */
function lang_slug($row, $slugField = 'slug', $fallback = '')
{
    return lang_value($row, $slugField, $fallback);
}

// ------------------------------------------------------------
// LANGUAGES / TRANSLATIONS
// ------------------------------------------------------------
function gaia_current_lang($refresh = false)
{
    static $lang = null;
    if ($lang !== null && !$refresh) {
        return $lang;
    }
    $lang = 'en';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#^/(en|ar)(/|\?|$)#i', $uri, $m)) {
        $lang = strtolower($m[1]);
    } elseif (isset($_GET['lang']) && in_array(strtolower(trim($_GET['lang'])), ['en', 'ar'], true)) {
        $lang = strtolower(trim($_GET['lang']));
    } elseif (isset($_SESSION['gaia_lang']) && in_array($_SESSION['gaia_lang'], ['en', 'ar'], true)) {
        $lang = $_SESSION['gaia_lang'];
    } elseif (isset($_COOKIE['gaia_lang']) && in_array($_COOKIE['gaia_lang'], ['en', 'ar'], true)) {
        $lang = $_COOKIE['gaia_lang'];
    }
    return $lang;
}

function gaia_languages()
{
    return gaia_load_rows('languages', 'languages');
}

/**
 * Returns 'rtl' or 'ltr' based on the current language.
 */
function gaia_dir()
{
    return gaia_current_lang() === 'ar' ? 'rtl' : 'ltr';
}

/**
 * Returns true when the current language is Arabic (RTL).
 */
function gaia_is_rtl()
{
    return gaia_current_lang() === 'ar';
}

/**
 * Build a locale-prefixed URL for the current language.
 *
 *   gaia_url('index.php')          -> '/en/index.php' when lang=en
 *   gaia_url('weroad/search.php')  -> '/en/weroad/search.php'
 *
 * $moduleBase: optional relative prefix ('../' etc.) stripped before building.
 */
function gaia_url($path, $moduleBase = '')
{
    // Normalise the module base (e.g. '../')
    if ($moduleBase !== '') {
        $path = preg_replace('#^' . preg_quote($moduleBase, '#') . '#', '', $path);
    }
    $lang = gaia_current_lang();
    $prefix = $lang === 'en' ? '/en' : '/ar';

// Full URLs / hashes / query strings pass through unchanged.
    if (preg_match('~^(https?:|//|#|\?)~i', $path)) {
        return $path;
    }

    // Extract an embedded query string (e.g. "tour.php?slug=x") so we can
    // build clean SEO URLs while preserving the passed arguments. This is
    // important on detail pages where the current page's $_GET differs from
    // the target page's slug/id.
    $queryArgs = [];
    if (preg_match('#^([^?]+)\?(.+)$#', $path, $qm)) {
        $path = $qm[1];
        parse_str($qm[2], $queryArgs);
    }

    // Map DB menu URLs to clean SEO-friendly routes (see .htaccess).
    $clean = null;
    if ($path === 'index.php') {
        $clean = '';
    } elseif ($path === 'weroad/search.php' || $path === 'search.php') {
        $clean = 'tours';
} elseif ($path === 'weroad/trip.php' || $path === 'trip.php' || $path === 'tour.php') {
        $clean = 'tours/' . urlencode($queryArgs['slug'] ?? ($_GET['slug'] ?? ''));
    } elseif ($path === 'events.php') {
        $clean = 'events/' . urlencode($queryArgs['slug'] ?? ($_GET['slug'] ?? ''));
    } elseif ($path === 'hotel.php') {
        $clean = 'hotels/' . urlencode($queryArgs['slug'] ?? ($_GET['slug'] ?? ''));
    } elseif ($path === 'room.php') {
        $clean = 'rooms/' . urlencode($queryArgs['id'] ?? ($_GET['id'] ?? ''));
    } elseif ($path === 'gaia-night.php') {
        $clean = 'gaia-night';
    } elseif ($path === 'route.php') {
        $clean = 'transfer-booking';
    } elseif ($path === 'checkout.php') {
        $clean = 'checkout';
    } elseif ($path === 'search-hotels.php') {
        $clean = 'search-hotels';
    } elseif ($path === 'partner/apply.php' || $path === 'partner/apply') {
        $clean = 'partner/apply';
    } elseif (strpos($path, 'index.php#') === 0) {
        $anchor = substr($path, strpos($path, '#') + 1);
        $map = [
            'hotels'    => 'hotels',
            'transfers' => 'transfers',
            'reviews'   => 'reviews',
            'contact'   => 'contact',
            'faq'       => 'faq',
            'night'     => 'gaia-night',
            'events'    => 'events',
        ];
        $clean = isset($map[$anchor]) ? $map[$anchor] : ('' . ($anchor !== '' ? '#' . $anchor : ''));
    } elseif (strpos($path, 'gaia-night.php#') === 0) {
        $anchor = substr($path, strpos($path, '#') + 1);
        $clean = $anchor === 'events' ? 'events' : 'gaia-night' . ($anchor !== '' ? '#' . $anchor : '');
    } elseif (strpos($path, 'page.php') === 0) {
        // /about, /contact CMS pages
        if (!empty($queryArgs['slug'])) {
            $clean = $queryArgs['slug'];
        } else {
            $clean = null;
        }
    }

    if ($clean !== null) {
        $url = $prefix . '/' . ltrim($clean, '/');
        // Preserve any remaining query args (e.g. departure_id booking params).
        $safeArgs = array_diff_key($queryArgs, array_flip(['slug', 'id', 'lang']));
        if ($safeArgs) {
            $url .= '?' . http_build_query($safeArgs);
        }
        return $url;
    }

    // Generic fallback prefix.
    return $prefix . '/' . ltrim($path, '/');
}

/**
 * Build a URL for switching the current page to another locale,
 * preserving query parameters where possible (e.g. ?slug=).
 */
function gaia_switch_lang_url($targetLang)
{
    // Rebuild the current script path without any `lang` query param.
    $script = $_SERVER['SCRIPT_NAME'] ?? 'index.php';
    $script = ltrim($script, '/');

    // When running inside /weroad, path is .../weroad/search.php.
    $base   = '';
    if (strpos($script, 'weroad/') === 0) {
        $base   = 'weroad/';
        $script = substr($script, strlen('weroad/'));
    }

    // Strip a leading locale segment if present (e.g. /en/weroad/search.php).
    if (preg_match('#^(en|ar)/(.+)$#', $script, $m)) {
        $script = $m[2];
    }

    // Keep query params, but drop `lang`.
    $q = $_GET;
    unset($q['lang']);

    // Map friendly URLs back to their canonical path equivalents:
    //   search.php / trip.php  -> tours / tours/{slug}
$url = $script;
    if ($script === 'weroad/search.php' || $script === 'search.php') {
        $url = 'tours';
    } elseif ($script === 'weroad/trip.php' || $script === 'trip.php' || $script === 'tour.php') {
        $url = 'tours/' . urlencode($q['slug'] ?? '');
        unset($q['slug']);
    } elseif ($script === 'events.php') {
        $url = 'events/' . urlencode($q['slug'] ?? '');
        unset($q['slug']);
    } elseif ($script === 'hotel.php') {
        $url = 'hotels/' . urlencode($q['slug'] ?? '');
        unset($q['slug']);
    } elseif ($script === 'room.php') {
        $url = 'rooms/' . (int)($q['id'] ?? 0);
        unset($q['id']);
    } elseif ($script === 'gaia-night.php') {
        $url = 'gaia-night';
    } elseif ($script === 'index.php') {
        $url = '';
    } elseif ($script === 'route.php') {
        $url = 'transfer-booking';
    } elseif ($script === 'checkout.php') {
        $url = 'checkout';
    } elseif ($script === 'search-hotels.php') {
        $url = 'search-hotels';
    } elseif ($script === 'page.php') {
        $url = $q['slug'] ?? 'about';
        unset($q['slug']);
    }

    $prefix = $targetLang === 'en' ? '/en' : '/ar';
    $url = $prefix . '/' . ltrim($url, '/');
    if ($q) {
        $url .= '?' . http_build_query($q);
    }
    return $url;
}

function gaia_translations()
{
    static $translations = null;
    if ($translations !== null) {
        return $translations;
    }
    $translations = [];
    foreach (gaia_load_rows('translations', 'translations') as $row) {
        $translations[$row['lang']][$row['key']] = $row['value'];
    }
    return $translations;
}

/**
 * Translate a key for the current (or given) language.
 */
function t($key, $default = null, $lang = null)
{
    if ($lang === null && ($default === 'en' || $default === 'ar')) {
        $lang = $default;
        $default = null;
    }
    if ($lang === null) {
        $lang = gaia_current_lang();
    }
    $all = gaia_translations();
    if (isset($all[$lang][$key]) && $all[$lang][$key] !== '') {
        return $all[$lang][$key];
    }
    if (isset($all['en'][$key]) && $all['en'][$key] !== '') {
        return $all['en'][$key];
    }
    return $default !== null ? $default : $key;
}

/**
 * Translate a string with {placeholder} replacement.
 */
function t_fmt($key, $replace = [], $lang = null)
{
    $str = t($key, $lang);
    foreach ($replace as $k => $v) {
        $str = str_replace('{' . $k . '}', $v, $str);
    }
    return $str;
}

// ------------------------------------------------------------
// MENUS
// ------------------------------------------------------------
function gaia_menu($menu = 'header', $parentId = 0)
{
    $items = [];
    foreach (gaia_load_rows('menu_items', 'menu_items') as $row) {
        if ($row['menu'] === $menu && (int)$row['parent_id'] === (int)$parentId && (int)$row['is_active'] === 1) {
            $items[] = $row;
        }
    }
    usort($items, fn($a, $b) => (int)$a['sort_order'] <=> (int)$b['sort_order']);
    return $items;
}

// ------------------------------------------------------------
// SEO
// ------------------------------------------------------------
function seo_meta($page, $lang = null)
{
    if ($lang === null) {
        $lang = gaia_current_lang();
    }
    $rows = gaia_load_rows('seo_meta', 'seo_meta');
    foreach ($rows as $row) {
        if ($row['page'] === $page && $row['lang'] === $lang && (int)$row['is_active'] === 1) {
            return $row;
        }
    }
    // Fallback to English
    foreach ($rows as $row) {
        if ($row['page'] === $page && $row['lang'] === 'en') {
            return $row;
        }
    }
    return null;
}

function seo_title($page, $default = 'GAIA TOURS & TRAVEL')
{
    $meta = seo_meta($page);
    return $meta && $meta['title'] ? $meta['title'] : $default;
}

function seo_description($page)
{
    $meta = seo_meta($page);
    return $meta ? $meta['meta_description'] : '';
}

function seo_keywords($page)
{
    $meta = seo_meta($page);
    return $meta ? $meta['keywords'] : '';
}

function seo_og_image($page)
{
    $meta = seo_meta($page);
    return $meta ? $meta['og_image'] : '';
}

// ------------------------------------------------------------
// SOCIAL LINKS
// ------------------------------------------------------------
function gaia_social_links()
{
    $links = [];
    foreach (gaia_load_rows('social_links', 'social_links') as $row) {
        if ((int)$row['is_active'] === 1) {
            $links[] = $row;
        }
    }
    usort($links, fn($a, $b) => (int)$a['sort_order'] <=> (int)$b['sort_order']);
    return $links;
}

// ------------------------------------------------------------
// BANNERS / HIGHLIGHTS / EVENTS / NIGHT / TRUST / AWARDS
// ------------------------------------------------------------
function gaia_banners($page = 'home')
{
    $rows = [];
    foreach (gaia_load_rows('banners', 'banners') as $row) {
        if ($row['page'] === $page && (int)$row['is_active'] === 1) {
            $rows[] = $row;
        }
    }
    usort($rows, fn($a, $b) => (int)$a['sort_order'] <=> (int)$b['sort_order']);
    return $rows;
}

function gaia_first_banner($page = 'home')
{
    $b = gaia_banners($page);
    return $b ? $b[0] : null;
}

function gaia_highlights($section = 'expect')
{
    $rows = [];
    foreach (gaia_load_rows('highlights', 'highlights') as $row) {
        if ($row['section'] === $section && (int)$row['is_active'] === 1) {
            $rows[] = $row;
        }
    }
    usort($rows, fn($a, $b) => (int)$a['sort_order'] <=> (int)$b['sort_order']);
    return $rows;
}

function gaia_events($category = 'home')
{
    $rows = [];
    foreach (gaia_load_rows('events', 'events') as $row) {
        if (($category === '' || $row['category'] === $category) && (int)$row['is_active'] === 1) {
            $rows[] = $row;
        }
    }
    usort($rows, fn($a, $b) => (int)$a['sort_order'] <=> (int)$b['sort_order']);
    return $rows;
}

function gaia_night_offerings($type = 'service')
{
    $rows = [];
    foreach (gaia_load_rows('night_offerings', 'night_offerings') as $row) {
        if ($row['type'] === $type && (int)$row['is_active'] === 1) {
            $rows[] = $row;
        }
    }
    usort($rows, fn($a, $b) => (int)$a['sort_order'] <=> (int)$b['sort_order']);
    return $rows;
}

function gaia_trust_scores()
{
    $rows = [];
    foreach (gaia_load_rows('trust_scores', 'trust_scores') as $row) {
        if ((int)$row['is_active'] === 1) {
            $rows[] = $row;
        }
    }
    usort($rows, fn($a, $b) => (int)$a['sort_order'] <=> (int)$b['sort_order']);
    return $rows;
}

function gaia_awards()
{
    $rows = [];
    foreach (gaia_load_rows('awards', 'awards') as $row) {
        if ((int)$row['is_active'] === 1) {
            $rows[] = $row;
        }
    }
    usort($rows, fn($a, $b) => (int)$a['sort_order'] <=> (int)$b['sort_order']);
    return $rows;
}

// ------------------------------------------------------------
// MEDIA (centralized image management)
// ------------------------------------------------------------
function gaia_media($key)
{
    static $media = null;
    if ($media === null) {
        $media = [];
        foreach (gaia_load_rows('media', 'media') as $row) {
            if ((int)$row['is_active'] === 1) {
                $media[$row['key']] = $row;
            }
        }
    }
    return isset($media[$key]) ? $media[$key] : null;
}

function gaia_media_url($key, $default = '')
{
    $m = gaia_media($key);
    return $m && $m['file_path'] ? $m['file_path'] : $default;
}

function gaia_media_alt($key, $default = '')
{
    $m = gaia_media($key);
    return $m && $m['alt_text'] ? $m['alt_text'] : $default;
}

// ------------------------------------------------------------
// PAGES (About, Contact, etc.)
// ------------------------------------------------------------
function gaia_page($slug, $lang = null)
{
    if ($lang === null) {
        $lang = gaia_current_lang();
    }
    $rows = gaia_load_rows('pages', 'pages');
    foreach ($rows as $row) {
        if ($row['slug'] === $slug && $row['lang'] === $lang && (int)$row['is_active'] === 1) {
            return $row;
        }
    }
    foreach ($rows as $row) {
        if ($row['slug'] === $slug && $row['lang'] === 'en') {
            return $row;
        }
    }
    return null;
}

function gaia_page_sections($pageSlug)
{
    $rows = [];
    foreach (gaia_load_rows('page_sections', 'page_sections') as $row) {
        if ($row['page_slug'] === $pageSlug && (int)$row['is_active'] === 1) {
            $rows[] = $row;
        }
    }
    usort($rows, fn($a, $b) => (int)$a['sort_order'] <=> (int)$b['sort_order']);
    return $rows;
}

// ------------------------------------------------------------
// TOUR CATEGORIES / DESTINATIONS
// ------------------------------------------------------------
function gaia_tour_categories()
{
    $rows = [];
    foreach (gaia_load_rows('tour_categories', 'tour_categories') as $row) {
        if ((int)$row['is_active'] === 1) {
            $rows[] = $row;
        }
    }
    usort($rows, fn($a, $b) => (int)$a['sort_order'] <=> (int)$b['sort_order']);
    return $rows;
}

function gaia_tour_destinations()
{
    $rows = [];
    foreach (gaia_load_rows('tour_destinations', 'tour_destinations') as $row) {
        if ((int)$row['is_active'] === 1) {
            $rows[] = $row;
        }
    }
    usort($rows, fn($a, $b) => (int)$a['sort_order'] <=> (int)$b['sort_order']);
    return $rows;
}

// ------------------------------------------------------------
// TRANSFERS LOCATIONS / SERVICES / BOOKING OPTIONS
// ------------------------------------------------------------
function gaia_transfer_locations($type = '')
{
    $rows = [];
    foreach (gaia_load_rows('transfers_locations', 'transfers_locations') as $row) {
        if ((int)$row['is_active'] === 1 && ($type === '' || $row['type'] === $type)) {
            $rows[] = $row;
        }
    }
    usort($rows, fn($a, $b) => (int)$a['sort_order'] <=> (int)$b['sort_order']);
    return $rows;
}

function gaia_transfer_services()
{
    $rows = [];
    foreach (gaia_load_rows('transfer_services', 'transfer_services') as $row) {
        if ((int)$row['is_active'] === 1) {
            $rows[] = $row;
        }
    }
    usort($rows, fn($a, $b) => (int)$a['sort_order'] <=> (int)$b['sort_order']);
    return $rows;
}

function gaia_booking_options()
{
    static $options = null;
    if ($options !== null) {
        return $options;
    }
    $options = [];
    foreach (gaia_load_rows('booking_options', 'booking_options') as $row) {
        if ((int)$row['is_active'] === 1) {
            $options[$row['key']] = $row;
        }
    }
    return $options;
}

function booking_option_price($key, $default = 0.00)
{
    $options = gaia_booking_options();
    return isset($options[$key]) ? (float)$options[$key]['price'] : (float)$default;
}

// ------------------------------------------------------------
// EXTRAS PRICING (transfers + tours) — from DB
// ------------------------------------------------------------
function gaia_transfer_extras()
{
    static $extras = null;
    if ($extras !== null) {
        return $extras;
    }
    $extras = [];
    foreach (gaia_load_rows('transfer_extras', 'transfer_extras') as $row) {
        if ((int)$row['is_active'] === 1) {
            $extras[$row['key']] = $row;
        }
    }
    return $extras;
}

function transfer_extra_price($key, $default = 0.00)
{
    $extras = gaia_transfer_extras();
    return isset($extras[$key]) ? (float)$extras[$key]['price'] : (float)$default;
}

function gaia_tour_extras()
{
    static $extras = null;
    if ($extras !== null) {
        return $extras;
    }
    $extras = [];
    foreach (gaia_load_rows('tour_extras', 'tour_extras') as $row) {
        if ((int)$row['is_active'] === 1) {
            $extras[$row['key']] = $row;
        }
    }
    return $extras;
}

function tour_extra_price($key, $default = 0.00)
{
    $extras = gaia_tour_extras();
    return isset($extras[$key]) ? (float)$extras[$key]['price'] : (float)$default;
}

/**
 * Render common SEO <head> tags from the seo_meta table.
 * Includes hreflang alternates + canonical for the active locale.
 */
function gaia_seo_tags($page, $defaultTitle = 'GAIA TOURS & TRANSPORTATION')
{
    $lang = gaia_current_lang();
    $title = seo_title($page, $defaultTitle);
    $desc  = seo_description($page);
    $og    = seo_og_image($page);
    $html  = '<title>' . htmlspecialchars($title) . '</title>' . "\n";
    if ($desc) {
        $html .= '<meta name="description" content="' . htmlspecialchars($desc) . '">' . "\n";
    }
    $kw = seo_keywords($page);
    if ($kw) {
        $html .= '<meta name="keywords" content="' . htmlspecialchars($kw) . '">' . "\n";
    }
    $html .= '<meta property="og:title" content="' . htmlspecialchars($title) . '">' . "\n";
    if ($desc) {
        $html .= '<meta property="og:description" content="' . htmlspecialchars($desc) . '">' . "\n";
    }
    if ($og) {
        $html .= '<meta property="og:image" content="' . htmlspecialchars($og) . '">' . "\n";
    }
    // hreflang alternates + canonical (dynamic per locale)
    $canonicalEn = gaia_switch_lang_url('en');
    $canonicalAr = gaia_switch_lang_url('ar');
    $html .= '<link rel="canonical" href="' . htmlspecialchars($canonicalEn) . '">' . "\n";
    $html .= '<link rel="alternate" hreflang="en" href="' . htmlspecialchars($canonicalEn) . '">' . "\n";
    $html .= '<link rel="alternate" hreflang="ar" href="' . htmlspecialchars($canonicalAr) . '">' . "\n";
    $html .= '<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($canonicalEn) . '">' . "\n";
    return $html;
}
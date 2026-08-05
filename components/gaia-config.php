<?php
/**
 * gaia-config.php
 * ------------------------------------------------------------
 * Shared configuration & helpers for the unified GAIA platform.
 *
 * This file now loads the global navigation, footer links, and
 * cross-sell strings from MySQL (via bootstrap.php) so that the
 * future Admin Panel can edit them without touching code.
 *
 * Usage from any page:
 *   $gaia_base = '';            // root module (transfers)
 *   $gaia_base = '../';         // tours module (/weroad)
 *   require_once __DIR__ . '/bootstrap.php';
 *   require_once __DIR__ . '/gaia-config.php';
 *   require gaia-header.php / gaia-footer.php
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * Return the unified navigation items from the menu_items table.
 * $base is the relative path prefix back to the site root.
 */
function gaia_nav_items($base = '')
{
    $items = [];
    foreach (gaia_menu('header', 0) as $row) {
        // Translate the menu label from the translations table
        // (nav_key is matched against 'nav.{key}' translation keys).
        $label = $row['label'];
        if (!empty($row['nav_key']) && t('nav.' . $row['nav_key']) !== 'nav.' . $row['nav_key']) {
            $label = t('nav.' . $row['nav_key']);
        }
        $item = [
            'label'   => $label,
            'url'     => gaia_url($base . $row['url'], $base),
            'premium' => (int)$row['is_premium'] === 1,
        ];
        if (!empty($row['nav_key'])) {
            $item['key'] = $row['nav_key'];
        }
        $items[] = $item;
    }
    return $items;
}

/**
 * Return the unified footer link columns grouped by the parent
 * label from the menu_items table (parent_id > 0).
 */
function gaia_footer_columns($base = '')
{
    $all = gaia_load_rows('menu_items', 'menu_items');
    $parents = [];
    $children = [];
    foreach ($all as $row) {
        if ((int)$row['menu'] !== 'footer' || (int)$row['is_active'] !== 1) {
            continue;
        }
        if ((int)$row['parent_id'] === 0) {
            $parents[$row['id']] = $row;
        } else {
            $children[$row['parent_id']][] = $row;
        }
    }
    $cols = [];
    foreach ($parents as $pid => $parent) {
        $links = [];
        if (isset($children[$pid])) {
            usort($children[$pid], fn($a, $b) => (int)$a['sort_order'] <=> (int)$b['sort_order']);
            foreach ($children[$pid] as $child) {
                // Translate footer link label if a nav_key exists
                $label = $child['label'];
                if (!empty($child['nav_key']) && t('nav.' . $child['nav_key']) !== 'nav.' . $child['nav_key']) {
                    $label = t('nav.' . $child['nav_key']);
                }
                $links[] = ['label' => $label, 'url' => $base . $child['url']];
            }
        }
        // Translate footer column title if a nav_key exists
        $title = $parent['label'];
        if (!empty($parent['nav_key']) && t('nav.' . $parent['nav_key']) !== 'nav.' . $parent['nav_key']) {
            $title = t('nav.' . $parent['nav_key']);
        }
        $cols[] = [
            'title' => $title,
            'links' => $links,
        ];
    }
    return $cols;
}

/**
 * Determine the active nav key based on the current page + module.
 */
function gaia_active_nav($currentPath, $module)
{
    $p = basename($currentPath);
    if ($module === 'tours' || $p === 'search.php' || $p === 'trip.php' || $p === 'booking.php') {
        return 'tours';
    }
    if ($p === 'route.php' || $p === 'checkout.php') {
        return 'transfers';
    }
    return 'home';
}

/**
 * Render a simple cross-sell block (CTAs between modules).
 * $type: 'tours' | 'transfers'
 */
function gaia_cross_sell($type, $base = '')
{
    if ($type === 'tours') {
        // On a TOUR page: promote transfers back to the platform.
        return '<div class="gaia-cross-sell">
            <div class="gaia-cross-icon">&#128663;</div>
            <div class="gaia-cross-body">
                <strong>' . htmlspecialchars(t('cross.transfers_title')) . '</strong>
                <span>' . htmlspecialchars(t('cross.transfers_text')) . '</span>
            </div>
            <a class="gaia-cross-btn" href="' . gaia_url('index.php', $base) . '">' . htmlspecialchars(t('cross.transfers_btn')) . '</a>
        </div>';
    }
    // On a TRANSFER page: promote tours.
    return '<div class="gaia-cross-sell">
        <div class="gaia-cross-icon">&#127757;</div>
        <div class="gaia-cross-body">
            <strong>' . htmlspecialchars(t('cross.tours_title')) . '</strong>
            <span>' . htmlspecialchars(t('cross.tours_text')) . '</span>
        </div>
        <a class="gaia-cross-btn" href="' . gaia_url('weroad/search.php', $base) . '">' . htmlspecialchars(t('cross.tours_btn')) . '</a>
    </div>';
}

/**
 * Render the GAIA master-brand logo (used by header + footer).
 * $sub lets the header/footer show the active service line
 * (e.g. 'TOURS & TRAVEL' at root or on the tours module).
 */
function gaia_logo($base = '', $sub = 'TOURS & TRAVEL')
{
    $logoImg = site_setting('logo_image', '');
    $homeUrl = gaia_url('index.php', $base);
    if ($logoImg !== '') {
        return '<a class="gaia-logo" href="' . $homeUrl . '">'
             . '<img src="' . $base . htmlspecialchars($logoImg) . '" alt="' . htmlspecialchars(site_setting('company_short', 'GAIA')) . '" class="gaia-logo-mark">'
             . '<span class="gaia-logo-text">' . htmlspecialchars(site_setting('company_short', 'GAIA')) . ' <em>' . $sub . '</em></span>'
             . '</a>';
    }
    $logoMark = site_setting('company_logo_mark', 'G');
    return '<a class="gaia-logo" href="' . $homeUrl . '">'
         . '<span class="gaia-logo-mark">' . htmlspecialchars($logoMark) . '</span>'
         . '<span class="gaia-logo-text">GAIA <em>' . $sub . '</em></span>'
         . '</a>';
}

/**
 * Render a gold VIP badge for GAIA Night premium content.
 */
function gaia_vip_badge($label = 'VIP')
{
    return '<span class="gaia-vip-badge">&#11088; ' . htmlspecialchars($label) . '</span>';
}

/**
 * Render a premium GAIA Night cross-sell block.
 * Used on regular pages to surface the premium service line.
 */
function gaia_cross_sell_night($base = '')
{
    return '<div class="gaia-cross-sell gaia-cross-sell-night">
        <div class="gaia-cross-icon">&#127881;</div>
        <div class="gaia-cross-body">
            <strong>' . htmlspecialchars(t('cross.night_title')) . '</strong>
            <span>' . htmlspecialchars(t('cross.night_text')) . '</span>
        </div>
    </div>';
}

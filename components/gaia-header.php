<?php
/**
 * gaia-header.php
 * ------------------------------------------------------------
 * UNIFIED GLOBAL HEADER for the GAIA TOURS & TRAVEL platform.
 *
 * Includes:
 *   - GAIA logo (wordmark)
 *   - Desktop navigation (from DB menu_items, translated)
 *   - Language switcher (EN ⇄ AR, preserves current page)
 *   - WhatsApp
 *   - Mobile menu (hamburger + drawer)
 *
 * Required before include:
 *   $gaia_base   = '' or '../' — relative path back to site root
 *   $gaia_active = nav key to mark active ('home','tours','transfers',...)
 *
 * Optional:
 *   $gaia_header_style = 'solid' (default) or 'overlay' (transparent on hero)
 * ------------------------------------------------------------
 */

// Ensure bootstrap is loaded (do not require config/db here — pages do)
if (!function_exists('gaia_nav_items')) {
    require_once __DIR__ . '/gaia-config.php';
}

// Resolve defaults
$gaia_base         = $gaia_base         ?? '';
$gaia_active       = $gaia_active       ?? 'home';
$gaia_header_style = $gaia_header_style ?? 'solid';

$gaiaNav = gaia_nav_items($gaia_base);

// DB-driven company settings
$gaia_company_name  = site_setting('company_name', 'GAIA TOURS & TRAVEL');
$gaia_company_short = site_setting('company_short', 'GAIA');
$gaia_logo_mark     = site_setting('company_logo_mark', 'G');
$gaia_tagline       = site_setting('company_tagline', 'TOURS & TRAVEL');
$gaia_whatsapp      = site_setting('contact_whatsapp', '962790123456');
$gaia_contact_email = site_setting('contact_email', 'support@gaiatours.com');
$gaia_currency      = site_setting('currency_symbol', '$');

// Reusable RTL classes
$gaiaLang  = gaia_current_lang();
$gaiaOther = $gaiaLang === 'ar' ? 'en' : 'ar';
$gaiaSwitchUrl = gaia_switch_lang_url($gaiaOther);
$gaiaDir = gaia_dir();

// Simple inline SVG icons
function gaia_icon_globe() {
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 010 18 15 15 0 010-18z"/></svg>';
}
function gaia_icon_whatsapp() {
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2a9.9 9.9 0 00-8.5 15L2 22l5.1-1.5A9.9 9.9 0 1012.04 2zm5.8 14.1c-.24.68-1.4 1.3-1.93 1.35-.5.05-.98.24-3.3-.68-2.8-1.12-4.55-3.98-4.7-4.16-.14-.18-1.1-1.47-1.1-2.8 0-1.34.7-2 1.02-2.28.24-.2.54-.27.72-.27h.52c.18 0 .4-.05.65.5.25.56.85 1.96.93 2.1.08.14.13.3.02.5-.1.18-.16.3-.3.47-.14.17-.3.38-.43.5-.15.16-.3.33-.13.65.17.3.76 1.26 1.64 2.04 1.13 1 2.06 1.32 2.36 1.46.3.15.47.12.65-.07.17-.18.75-.87.94-1.17.2-.3.4-.25.67-.15.27.1 1.74.82 2.04.97.3.15.5.22.57.35.08.13.08.68-.16 1.33z"/></svg>';
}
function gaia_icon_account() {
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 5-6 8-6s6.5 2 8 6"/></svg>';
}
?>
<header class="gaia-header <?= $gaia_header_style === 'overlay' ? 'gaia-header-overlay' : 'gaia-header-solid' ?> <?= gaia_is_rtl() ? 'gaia-header-rtl' : 'gaia-header-ltr' ?>" dir="<?= $gaiaDir ?>">
  <div class="gaia-header-inner">

    <a class="gaia-header-logo" href="<?= gaia_url('index.php', $gaia_base) ?>">
      <img src="<?= htmlspecialchars(site_setting('logo_image', 'assets/images/image.png')) ?>" alt="<?= htmlspecialchars($gaia_company_short) ?>" class="gaia-logo-mark">
    </a>

    <nav class="gaia-nav" aria-label="Main navigation">
      <?php foreach ($gaiaNav as $item): ?>
        <?php $itemKey = $item['key'] ?? ''; ?>
        <a class="gaia-nav-link <?= $itemKey === $gaia_active ? 'active' : '' ?> <?= !empty($item['premium']) ? 'gaia-nav-premium' : '' ?>"
           href="<?= htmlspecialchars($item['url']) ?>"><?= htmlspecialchars($item['label']) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="gaia-header-actions">
      <a class="gaia-icon-btn gaia-lang" href="<?= htmlspecialchars($gaiaSwitchUrl) ?>" title="<?= htmlspecialchars(t('lang.' . $gaiaOther, strtoupper($gaiaOther))) ?>">
        <?= gaia_icon_globe() ?><span><?= htmlspecialchars(strtoupper($gaiaOther)) ?></span>
      </a>
      <a class="gaia-icon-btn gaia-whatsapp" href="https://wa.me/<?= htmlspecialchars($gaia_whatsapp) ?>" target="_blank" rel="noopener" title="<?= htmlspecialchars(t('header.whatsapp')) ?>">
        <?= gaia_icon_whatsapp() ?>
      </a>
      <a class="gaia-header-account" href="<?= gaia_url('contact') ?>" title="<?= htmlspecialchars(t('header.account')) ?>">
        <?= gaia_icon_account() ?><span><?= htmlspecialchars(t('header.account')) ?></span>
      </a>
    </div>

    <button class="gaia-burger" aria-label="Open menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>

  <!-- Mobile drawer -->
  <div class="gaia-mobile-menu" aria-hidden="true">
    <div class="gaia-mobile-menu-head">
      <span class="gaia-logo-mark"><?= htmlspecialchars($gaia_logo_mark) ?></span>
      <button class="gaia-mobile-close" aria-label="Close menu">×</button>
    </div>
    <nav class="gaia-mobile-nav">
      <?php foreach ($gaiaNav as $item): ?>
        <?php $itemKey = $item['key'] ?? ''; ?>
        <a class="gaia-mobile-link <?= $itemKey === $gaia_active ? 'active' : '' ?> <?= !empty($item['premium']) ? 'gaia-mobile-premium' : '' ?>"
           href="<?= htmlspecialchars($item['url']) ?>"><?= htmlspecialchars($item['label']) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="gaia-mobile-actions">
      <a class="gaia-btn gaia-btn-block gaia-btn-ghost" href="<?= gaia_url('contact') ?>"><?= htmlspecialchars(t('header.account')) ?></a>
      <a class="gaia-btn gaia-btn-block" href="<?= gaia_url('transfer-booking') ?>"><?= htmlspecialchars(t('header.start_booking')) ?></a>
    </div>
  </div>
  <div class="gaia-mobile-backdrop"></div>
</header>
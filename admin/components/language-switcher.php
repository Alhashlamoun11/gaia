<?php
/**
 * admin/components/language-switcher.php
 * ------------------------------------------------------------
 * Reusable EN/AR language switcher for the admin topbar.
 * Preserves the current query string while switching lang.
 * ------------------------------------------------------------
 */
if (!function_exists('gaia_current_lang')) {
    return;
}
$lang = gaia_current_lang();
$other = $lang === 'ar' ? 'en' : 'ar';

// Build the current page URL with the other language.
$script = $_SERVER['SCRIPT_NAME'] ?? 'index.php';
$script = ltrim($script, '/');
$query  = $_GET;
$query['lang'] = $other;
$url = $script . '?' . http_build_query($query);
?>
<div class="lang-switch">
  <a href="<?= htmlspecialchars($url) ?>" class="<?= $lang === 'en' ? 'active' : '' ?>">EN</a>
  <span>/</span>
  <a href="<?= htmlspecialchars($url) ?>" class="<?= $lang === 'ar' ? 'active' : '' ?>" lang="ar">عربي</a>
</div>

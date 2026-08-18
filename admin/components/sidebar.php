<?php
/**
 * admin/components/sidebar.php
 * ------------------------------------------------------------
 * Reusable admin sidebar. Requires bootstrap (t, admin_user).
 * $admin_active : string — sidebar key to highlight.
 * ------------------------------------------------------------
 */
$admin_active = $admin_active ?? 'dashboard';
$adminMe      = admin_user();
$safeName     = htmlspecialchars(trim(($adminMe['first_name'] ?? '') . ' ' . ($adminMe['last_name'] ?? '')));
$safeEmail    = htmlspecialchars((string)($adminMe['email'] ?? ''));

function _admin_nav_item($key, $icon, $label, $url, $active)
{
    $class = $key === $active ? ' class="active"' : '';
    $href  = htmlspecialchars($url);
    return '<a href="' . $href . '"' . $class . '><i class="fa-solid ' . $icon . '"></i> ' . htmlspecialchars($label) . '</a>';
}
?>
<aside class="admin-sidebar" id="adminSidebar">
  <div class="admin-sidebar-brand">
    <span class="mark"><?= htmlspecialchars(site_setting('company_logo_mark', 'G')) ?></span>
    <span class="txt">
      <strong><?= htmlspecialchars(site_setting('company_short', 'GAIA')) ?></strong>
      <span><?= htmlspecialchars(t('admin.dashboard')) ?></span>
    </span>
  </div>

  <nav class="admin-sidebar-nav">
    <div class="group"><?= htmlspecialchars(t('admin.dashboard')) ?></div>
    <?php
      echo _admin_nav_item('dashboard', 'fa-gauge-high', t('admin.dashboard'), admin_url('index.php'), $admin_active);
      echo _admin_nav_item('profile', 'fa-user', t('admin.profile'), admin_url('profile.php'), $admin_active);
      echo _admin_nav_item('settings', 'fa-gear', t('admin.settings'), admin_url('settings.php'), $admin_active);
      echo _admin_nav_item('home', 'fa-house', t('admin.homepage'), admin_url('home/index.php'), $admin_active);
      echo _admin_nav_item('seo', 'fa-magnifying-glass-chart', t('admin.seo'), admin_url('seo/index.php'), $admin_active);
      echo _admin_nav_item('translations', 'fa-language', t('admin.translations'), admin_url('translations/index.php'), $admin_active);
    ?>

    <div class="group"><?= htmlspecialchars(t('admin.bookings')) ?></div>
    <?php
      echo _admin_nav_item('bookings', 'fa-bookmark', t('admin.bookings'), admin_url('bookings/index.php'), $admin_active);
      echo _admin_nav_item('users', 'fa-users', t('admin.users'), admin_url('users/index.php'), $admin_active);
    ?>

    <div class="group"><?= htmlspecialchars(t('admin.languages', 'Content')) ?></div>
    <?php
      echo _admin_nav_item('hotels', 'fa-hotel', t('admin.hotels'), admin_url('hotels/index.php'), $admin_active);
      echo _admin_nav_item('rooms', 'fa-bed', t('admin.rooms'), admin_url('rooms/index.php'), $admin_active);
      echo _admin_nav_item('tours', 'fa-mountain-sun', t('admin.tours'), admin_url('tours/index.php'), $admin_active);
      echo _admin_nav_item('transfers', 'fa-car', t('admin.transfers'), admin_url('transfers/index.php'), $admin_active);
      echo _admin_nav_item('events', 'fa-wand-magic-sparkles', t('admin.events'), admin_url('events/index.php'), $admin_active);
      echo _admin_nav_item('reviews', 'fa-star', t('admin.reviews'), admin_url('reviews/index.php'), $admin_active);
      echo _admin_nav_item('faq', 'fa-circle-question', t('admin.faq'), admin_url('faq/index.php'), $admin_active);
      echo _admin_nav_item('partners', 'fa-handshake', t('admin.partners'), admin_url('partners/index.php'), $admin_active);
      echo _admin_nav_item('services', 'fa-briefcase', t('admin.services'), admin_url('services/index.php'), $admin_active);
      echo _admin_nav_item('media', 'fa-images', t('admin.media'), admin_url('media/index.php'), $admin_active);
    ?>
  </nav>

  <div class="admin-sidebar-foot">
    <div class="user">
      <img class="avatar lazy" data-src="<?= htmlspecialchars(auth_avatar()) ?>" src="" alt="">
      <div>
        <div class="u-name"><?= $safeName ?></div>
        <div class="u-email"><?= $safeEmail ?></div>
      </div>
    </div>
    <form method="post" action="<?= htmlspecialchars(admin_url('logout.php')) ?>">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-ghost btn-sm" style="width:100%;justify-content:center;">
        <i class="fa-solid fa-right-from-bracket"></i> <?= htmlspecialchars(t('admin.logout')) ?>
      </button>
    </form>
  </div>
</aside>

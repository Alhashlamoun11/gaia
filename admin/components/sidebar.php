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
    <div class="group"><?= htmlspecialchars(t('admin.dashboard', 'Overview')) ?></div>
    <?php
      echo _admin_nav_item('dashboard', 'fa-gauge-high', t('admin.dashboard', 'Dashboard'), admin_url('index.php'), $admin_active);
      echo _admin_nav_item('profile', 'fa-user', t('admin.profile', 'Profile'), admin_url('profile.php'), $admin_active);
      echo _admin_nav_item('settings', 'fa-gear', t('admin.settings', 'Settings'), admin_url('settings.php'), $admin_active);
      echo _admin_nav_item('home', 'fa-house', t('admin.homepage', 'Homepage CMS'), admin_url('home/index.php'), $admin_active);
      echo _admin_nav_item('seo', 'fa-magnifying-glass-chart', t('admin.seo', 'SEO Meta'), admin_url('seo/index.php'), $admin_active);
      echo _admin_nav_item('translations', 'fa-language', t('admin.translations', 'Translations'), admin_url('translations/index.php'), $admin_active);
    ?>

    <div class="group"><?= htmlspecialchars(t('admin.bookings_group', 'Sales & Finance')) ?></div>
    <?php
      echo _admin_nav_item('bookings', 'fa-bookmark', t('admin.bookings', 'Bookings'), admin_url('bookings/index.php'), $admin_active);
      echo _admin_nav_item('invoices', 'fa-file-invoice-dollar', 'Invoices & Billing', admin_url('invoices/index.php'), $admin_active);
      echo _admin_nav_item('users', 'fa-users', t('admin.users', 'Users & Clients'), admin_url('users/index.php'), $admin_active);
    ?>

    <div class="group"><?= htmlspecialchars(t('admin.catalog_group', 'Catalog & Fleet')) ?></div>
    <?php
      echo _admin_nav_item('hotels', 'fa-hotel', t('admin.hotels', 'Hotels'), admin_url('hotels/index.php'), $admin_active);
      echo _admin_nav_item('rooms', 'fa-bed', t('admin.rooms', 'Hotel Rooms'), admin_url('rooms/index.php'), $admin_active);
      echo _admin_nav_item('tours', 'fa-mountain-sun', t('admin.tours', 'Tours & Packages'), admin_url('tours/index.php'), $admin_active);
      echo _admin_nav_item('destinations', 'fa-map-location-dot', 'Tour Destinations', admin_url('destinations/index.php'), $admin_active);
      echo _admin_nav_item('transfers', 'fa-car', t('admin.transfers', 'Transfers & Routes'), admin_url('transfers/index.php'), $admin_active);
      echo _admin_nav_item('car_classes', 'fa-taxi', t('admin.car_classes', 'Car Classes'), admin_url('transfers/index.php?tab=car_classes'), $admin_active);
      echo _admin_nav_item('events', 'fa-wand-magic-sparkles', t('admin.events', 'Events'), admin_url('events/index.php'), $admin_active);
      echo _admin_nav_item('reviews', 'fa-star', t('admin.reviews', 'Reviews'), admin_url('reviews/index.php'), $admin_active);
      echo _admin_nav_item('faq', 'fa-circle-question', t('admin.faq', 'FAQ'), admin_url('faq/index.php'), $admin_active);
      echo _admin_nav_item('partners', 'fa-handshake', t('admin.partners', 'Partners'), admin_url('partners/index.php'), $admin_active);
      if (($adminMe['role_slug'] ?? '') === 'super_admin') {
          echo _admin_nav_item('applications', 'fa-file-signature', t('admin.partners.applications', 'Partner Apps'), admin_url('partners/applications.php'), $admin_active);
      }
      echo _admin_nav_item('services', 'fa-briefcase', t('admin.services', 'Services'), admin_url('services/index.php'), $admin_active);
      echo _admin_nav_item('media', 'fa-images', t('admin.media', 'Media Library'), admin_url('media/index.php'), $admin_active);
    ?>

    <!-- <div class="group">Marketing &amp; Promotion</div> -->
    <?php
      // echo _admin_nav_item('promo-codes', 'fa-tag', 'Promo Codes', admin_url('promo-codes/index.php'), $admin_active);
      // echo _admin_nav_item('newsletter', 'fa-envelope-open-text', 'Newsletter Subscribers', admin_url('newsletter/index.php'), $admin_active);
    ?>

    <!-- <div class="group">CMS &amp; Inquiries</div> -->
    <?php
      // echo _admin_nav_item('pages', 'fa-file-lines', 'Pages & Policies', admin_url('pages/index.php'), $admin_active);
      // echo _admin_nav_item('menu', 'fa-bars-staggered', 'Navigation Menu', admin_url('menu/index.php'), $admin_active);
      // echo _admin_nav_item('messages', 'fa-comments', 'Inquiries & Messages', admin_url('messages/index.php'), $admin_active);
    ?>

    <?php if (($adminMe['role_slug'] ?? '') === 'super_admin'): ?>
      <!-- <div class="group">System &amp; Access</div> -->
      <?php
        // echo _admin_nav_item('roles', 'fa-shield-halved', 'Roles & Permissions', admin_url('roles/index.php'), $admin_active);
        // echo _admin_nav_item('notifications', 'fa-bell', 'Hotel Broadcasts', admin_url('notifications/index.php'), $admin_active);
      ?>
    <?php endif; ?>
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

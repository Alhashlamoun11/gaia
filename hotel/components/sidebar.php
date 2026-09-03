<?php
$hotel_active = $hotel_active ?? 'dashboard';
$adminMe      = hotel_manager_user();
$safeName     = htmlspecialchars(trim(($adminMe['first_name'] ?? '') . ' ' . ($adminMe['last_name'] ?? '')));
$safeEmail    = htmlspecialchars((string)($adminMe['email'] ?? ''));

$currentHotel = current_hotel();
$hotelName    = $currentHotel ? $currentHotel['name'] : 'Hotel Portal';

function _hotel_nav_item($key, $icon, $label, $url, $active)
{
    $class = $key === $active ? ' class="active"' : '';
    $href  = htmlspecialchars($url);
    return '<a href="' . $href . '"' . $class . '><i class="fa-solid ' . $icon . '"></i> <span>' . htmlspecialchars($label) . '</span></a>';
}
?>
<aside class="admin-sidebar" id="adminSidebar">
  <div class="admin-sidebar-brand">
    <div class="admin-sidebar-brand-inner">
      <span class="mark"><?= htmlspecialchars(site_setting('company_logo_mark', 'G')) ?></span>
      <span class="txt">
        <strong><?= htmlspecialchars(site_setting('company_short', 'GAIA')) ?></strong>
        <span title="<?= htmlspecialchars($hotelName) ?>"><?= htmlspecialchars($hotelName) ?></span>
      </span>
    </div>
    <button type="button" class="admin-sidebar-close" id="adminSidebarClose" aria-label="Close sidebar">
      <i class="fa-solid fa-xmark"></i>
    </button>
  </div>

  <nav class="admin-sidebar-nav">
    <div class="group"><?= htmlspecialchars(t('hotel.dashboard')) ?></div>
    <?php
      echo _hotel_nav_item('dashboard', 'fa-gauge-high', t('hotel.dashboard'), hotel_url('index.php'), $hotel_active);
      echo _hotel_nav_item('profile', 'fa-hotel', t('hotel.profile'), hotel_url('profile.php'), $hotel_active);
      echo _hotel_nav_item('rooms', 'fa-bed', t('hotel.rooms'), hotel_url('rooms.php'), $hotel_active);
      echo _hotel_nav_item('availability', 'fa-calendar-check', t('hotel.availability'), hotel_url('availability.php'), $hotel_active);
    ?>

    <div class="group"><?= htmlspecialchars(t('hotel.bookings')) ?></div>
    <?php
      echo _hotel_nav_item('bookings', 'fa-bookmark', t('hotel.bookings'), hotel_url('bookings.php'), $hotel_active);
      echo _hotel_nav_item('reviews', 'fa-star', t('hotel.reviews'), hotel_url('reviews.php'), $hotel_active);
      echo _hotel_nav_item('notifications', 'fa-bell', t('hotel.notifications'), hotel_url('notifications.php'), $hotel_active);
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
    <form method="post" action="<?= htmlspecialchars(gaia_url('logout.php')) ?>">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-ghost btn-sm" style="width:100%;justify-content:center;">
        <i class="fa-solid fa-right-from-bracket"></i> <?= htmlspecialchars(t('auth.logout', 'Logout')) ?>
      </button>
    </form>
  </div>
</aside>
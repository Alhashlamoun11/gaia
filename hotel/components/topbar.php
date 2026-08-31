<?php
$topbar_title = $hotel_page_title ?? t('hotel.dashboard');
$currentHotel = current_hotel();
$hotelName    = $currentHotel ? $currentHotel['name'] : 'Hotel Portal';
?>
<header class="admin-topbar">
  <div class="admin-topbar-left">
    <button class="admin-sidebar-toggle" id="adminSidebarToggle"><i class="fa-solid fa-bars"></i></button>
    <h1 class="admin-page-title"><?= htmlspecialchars($topbar_title) ?></h1>
  </div>
  <div class="admin-topbar-right">
    <span style="margin-right: 15px; font-weight: 500; font-size: 14px; opacity: 0.8;"><i class="fa-solid fa-hotel" style="margin-right:6px; color:#1f6f8f;"></i><?= htmlspecialchars($hotelName) ?></span>
    <a href="<?= htmlspecialchars(gaia_url('index.php')) ?>" target="_blank" class="btn btn-ghost btn-icon" title="View Site">
      <i class="fa-solid fa-arrow-up-right-from-square"></i>
    </a>
  </div>
</header>
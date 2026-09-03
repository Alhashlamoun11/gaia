<?php
$topbar_title = $hotel_page_title ?? t('hotel.dashboard');
$currentHotel = current_hotel();
$hotelName    = $currentHotel ? $currentHotel['name'] : 'Hotel Portal';
?>
<header class="admin-topbar">
  <div class="admin-topbar-left">
    <button type="button" class="admin-sidebar-toggle" id="adminSidebarToggle" aria-label="Toggle navigation menu">
      <i class="fa-solid fa-bars"></i>
    </button>
    <h1 class="admin-page-title"><?= htmlspecialchars($topbar_title) ?></h1>
  </div>
  <div class="admin-topbar-right">
    <span class="topbar-hotel-badge" title="<?= htmlspecialchars($hotelName) ?>">
      <i class="fa-solid fa-hotel"></i>
      <span><?= htmlspecialchars($hotelName) ?></span>
    </span>
    <a href="<?= htmlspecialchars(gaia_url('index.php')) ?>" target="_blank" class="btn btn-ghost btn-sm" title="View Site">
      <i class="fa-solid fa-arrow-up-right-from-square"></i>
    </a>
  </div>
</header>
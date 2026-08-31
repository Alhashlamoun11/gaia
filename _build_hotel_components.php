<?php
@mkdir(__DIR__ . '/hotel/components', 0755, true);

// 1. Header
file_put_contents(__DIR__ . '/hotel/components/header.php', <<<'EOT'
<?php
$hotel_page_title = $hotel_page_title ?? (t('hotel.dashboard', 'Hotel Portal') . ' — GAIA TOURS &amp; TRAVEL');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($hotel_page_title) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body>
EOT
);

// 2. Sidebar
file_put_contents(__DIR__ . '/hotel/components/sidebar.php', <<<'EOT'
<?php
$hotel_active = $hotel_active ?? 'dashboard';
$adminMe      = hotel_manager_user();
$safeName     = htmlspecialchars(trim(($adminMe['first_name'] ?? '') . ' ' . ($adminMe['last_name'] ?? '')));
$safeEmail    = htmlspecialchars((string)($adminMe['email'] ?? ''));

function _hotel_nav_item($key, $icon, $label, $url, $active)
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
      <span>Hotel Portal</span>
    </span>
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
EOT
);

// 3. Topbar
file_put_contents(__DIR__ . '/hotel/components/topbar.php', <<<'EOT'
<?php
$topbar_title = $topbar_title ?? t('hotel.dashboard');
?>
<header class="admin-topbar">
  <div class="admin-topbar-left">
    <button class="admin-sidebar-toggle" id="adminSidebarToggle"><i class="fa-solid fa-bars"></i></button>
    <h1 class="admin-page-title"><?= htmlspecialchars($topbar_title) ?></h1>
  </div>
  <div class="admin-topbar-right">
    <a href="<?= htmlspecialchars(gaia_url('index.php')) ?>" target="_blank" class="btn btn-ghost btn-icon" title="View Site">
      <i class="fa-solid fa-arrow-up-right-from-square"></i>
    </a>
  </div>
</header>
EOT
);

// 4. Footer
file_put_contents(__DIR__ . '/hotel/components/footer.php', <<<'EOT'
<script>
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('adminSidebarToggle');
  const sidebar = document.getElementById('adminSidebar');
  if(toggle && sidebar) {
    toggle.addEventListener('click', () => {
      sidebar.classList.toggle('open');
    });
  }
  // Lazy load
  document.querySelectorAll('img.lazy').forEach(img => {
    if(img.dataset.src) img.src = img.dataset.src;
  });
});
</script>
</body>
</html>
EOT
);

// 5. Alert
file_put_contents(__DIR__ . '/hotel/components/alert.php', <<<'EOT'
<?php
$f = hotel_flash();
if ($f):
    $cls = $f['type'] === 'error' ? 'alert-error' : 'alert-success';
?>
<div class="alert <?= $cls ?>" style="margin-bottom:20px; padding:12px 16px; border-radius:10px; background:<?= $f['type']==='error'?'#fdecea':'#e8f5e9' ?>; color:<?= $f['type']==='error'?'#b3261e':'#1b5e20' ?>;">
  <?= htmlspecialchars($f['msg']) ?>
</div>
<?php endif; ?>
EOT
);

echo "Hotel components created.\n";

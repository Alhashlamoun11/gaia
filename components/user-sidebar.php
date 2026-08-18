<?php
/**
 * components/user-sidebar.php
 * ------------------------------------------------------------
 * REUSABLE customer sidebar navigation for the customer portal.
 *
 * Expected variables (populated by the caller / account pages):
 *   $activeTab  : string — dashboard|bookings|payments|invoices|
 *                 profile|security|notifications|favorites|claim
 *   $base       : string — relative path back to the site root (default '../')
 *
 * Also renders the account logout form (CSRF-protected).
 * ------------------------------------------------------------
 */

if (!function_exists('auth_check')) {
    require_once __DIR__ . '/../auth/helpers.php';
}

$activeTab = $activeTab ?? 'dashboard';
$base      = $base      ?? '../';

// Count unread notifications for a badge if the service exists.
$unreadBadge = '';
if (class_exists('NotificationService') && function_exists('auth_id')) {
    $unreadBadge = (int)NotificationService::unreadCount((int)auth_id());
}

$nav = [
    ['key' => 'dashboard',      'icon' => 'fa-solid fa-gauge-high',        'label' => 'account.dashboard'],
    ['key' => 'bookings',       'icon' => 'fa-solid fa-bookmark',          'label' => 'account.my_bookings'],
    ['key' => 'payments',       'icon' => 'fa-solid fa-credit-card',       'label' => 'account.my_payments'],
    ['key' => 'invoices',       'icon' => 'fa-solid fa-file-invoice-dollar','label' => 'account.my_invoices'],
    ['key' => 'favorites',      'icon' => 'fa-solid fa-heart',             'label' => 'account.favorites'],
    ['key' => 'notifications',  'icon' => 'fa-solid fa-bell',              'label' => 'account.notifications'],
    ['key' => 'claim',          'icon' => 'fa-solid fa-link',              'label' => 'account.claim_booking', 'file' => 'claim-booking.php'],
    ['key' => 'profile',        'icon' => 'fa-solid fa-user',              'label' => 'account.profile'],
    ['key' => 'security',       'icon' => 'fa-solid fa-shield-halved',     'label' => 'account.security'],
];
?>
<aside class="account-sidebar">
  <div class="account-sidebar-user">
    <img src="<?= htmlspecialchars(auth_avatar()) ?>" alt="" class="account-avatar" loading="lazy">
    <div>
      <strong><?= htmlspecialchars(auth_name()) ?></strong>
      <span><?= htmlspecialchars(auth_email()) ?></span>
    </div>
  </div>
  <nav class="account-nav">
    <?php foreach ($nav as $item):
      $fileName = $item['key'] === 'dashboard' ? 'index.php' : (($item['file'] ?? '') !== '' ? $item['file'] : $item['key'] . '.php');
      $href = function_exists('gaia_url') ? gaia_url('account/' . $fileName) : (htmlspecialchars($base) . 'account/' . $fileName);
    ?>
      <a href="<?= htmlspecialchars($href) ?>"
         class="<?= $activeTab === $item['key'] ? 'active' : '' ?>">
        <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
        <?= htmlspecialchars(t($item['label'])) ?>
        <?php if ($item['key'] === 'notifications' && $unreadBadge > 0): ?>
          <span class="account-badge"><?= (int)$unreadBadge ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <?php $logoutUrl = function_exists('gaia_url') ? gaia_url('logout.php') : (htmlspecialchars($base) . 'logout.php'); ?>
  <form method="post" action="<?= htmlspecialchars($logoutUrl) ?>" class="account-logout-form">
    <?php
      require_once __DIR__ . '/../auth/csrf.php';
      echo csrf_field();
    ?>
    <button type="submit" class="account-logout-btn">
      <i class="fa-solid fa-right-from-bracket"></i> <?= htmlspecialchars(t('auth.logout')) ?>
    </button>
  </form>
</aside>


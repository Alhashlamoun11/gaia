<?php
/**
 * account/_layout.php
 * ------------------------------------------------------------
 * Shared account-sidebar layout for the customer area.
 * Requires bootstrap + auth + helpers to already be loaded.
 * Renders the account navigation sidebar used across pages.
 * ------------------------------------------------------------
 */

if (!function_exists('auth_check')) {
    require_once __DIR__ . '/../auth/helpers.php';
}

$activeTab = $activeTab ?? 'dashboard';
$base = '../';
?>
<div class="account-layout">
  <!-- Sidebar -->
  <aside class="account-sidebar">
    <div class="account-sidebar-user">
      <img src="<?= htmlspecialchars(auth_avatar()) ?>" alt="" class="account-avatar">
      <div>
        <strong><?= htmlspecialchars(auth_name()) ?></strong>
        <span><?= htmlspecialchars(auth_email()) ?></span>
      </div>
    </div>
    <nav class="account-nav">
      <a href="<?= $base ?>account/index.php" class="<?= $activeTab === 'dashboard' ? 'active' : '' ?>">
        <i class="fa-solid fa-gauge-high"></i> <?= htmlspecialchars(t('account.dashboard')) ?>
      </a>
      <a href="<?= $base ?>account/my-bookings.php" class="<?= $activeTab === 'bookings' ? 'active' : '' ?>">
        <i class="fa-solid fa-bookmark"></i> <?= htmlspecialchars(t('account.my_bookings')) ?>
      </a>
      <a href="<?= $base ?>account/claim-booking.php" class="<?= $activeTab === 'claim' ? 'active' : '' ?>">
        <i class="fa-solid fa-link"></i> <?= htmlspecialchars(t('account.claim_booking')) ?>
      </a>
      <a href="<?= $base ?>account/profile.php" class="<?= $activeTab === 'profile' ? 'active' : '' ?>">
        <i class="fa-solid fa-user"></i> <?= htmlspecialchars(t('account.profile')) ?>
      </a>
    </nav>
    <form method="post" action="<?= $base ?>logout.php" class="account-logout-form">
      <?php
        require_once __DIR__ . '/../auth/csrf.php';
        echo csrf_field();
      ?>
      <button type="submit" class="account-logout-btn">
        <i class="fa-solid fa-right-from-bracket"></i> <?= htmlspecialchars(t('auth.logout')) ?>
      </button>
    </form>
  </aside>

  <!-- Main content -->
  <main class="account-main">
    <?php if (!empty($account_alerts)): ?>
      <?php foreach ($account_alerts as $alert): ?>
        <div class="alert alert-<?= htmlspecialchars($alert['type'] ?? 'success') ?>"><?= htmlspecialchars($alert['msg']) ?></div>
      <?php endforeach; ?>
    <?php endif; ?>

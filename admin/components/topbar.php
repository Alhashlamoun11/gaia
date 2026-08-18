<?php
/**
 * admin/components/topbar.php
 * ------------------------------------------------------------
 * Sticky topbar with sidebar toggle, page title, view-site link,
 * language switch and avatar.
 *
 * Expected variables:
 *   $admin_topbar_title : string — current page heading
 * ------------------------------------------------------------
 */
$admin_topbar_title = $admin_topbar_title ?? t('admin.dashboard', 'Dashboard');
?>
<header class="admin-topbar">
  <button type="button" class="menu-toggle" id="adminMenuToggle" aria-label="Menu">
    <i class="fa-solid fa-bars"></i>
  </button>
  <h1 class="page-title"><?= htmlspecialchars($admin_topbar_title) ?></h1>
  <div class="spacer"></div>
  <div class="top-actions">
    <?php require __DIR__ . '/language-switcher.php'; ?>
    <a class="top-link" href="<?= htmlspecialchars(gaia_url('index.php')) ?>" target="_blank" rel="noopener">
      <i class="fa-solid fa-arrow-up-right-from-square"></i> <?= htmlspecialchars(t('admin.view_site')) ?>
    </a>
    <img class="top-avatar lazy" data-src="<?= htmlspecialchars(auth_avatar()) ?>" src="" alt="">
  </div>
</header>

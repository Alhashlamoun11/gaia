<?php
/**
 * components/breadcrumbs.php
 * ------------------------------------------------------------
 * REUSABLE breadcrumb trail for the customer portal.
 *
 * Expected variables (set by the caller):
 *   $crumbs : array of ['label' => translated string, 'url' => ''|link]
 *             The last item is rendered as the current (non-link) page.
 * ------------------------------------------------------------
 */
$crumbs = $crumbs ?? [];
?>
<?php if (!empty($crumbs)): ?>
  <nav class="breadcrumbs" aria-label="<?= htmlspecialchars(t('account.you_are_here', 'You are here:')) ?>">
    <a href="<?= htmlspecialchars(gaia_url('index.php')) ?>"><i class="fa-solid fa-house"></i> <?= htmlspecialchars(t('account.home', 'Home')) ?></a>
    <?php foreach ($crumbs as $i => $crumb): ?>
      <span class="sep">/</span>
      <?php if (!empty($crumb['url']) && $i < count($crumbs) - 1): ?>
        <a href="<?= htmlspecialchars($crumb['url']) ?>"><?= htmlspecialchars($crumb['label']) ?></a>
      <?php else: ?>
        <span class="current"><?= htmlspecialchars($crumb['label']) ?></span>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

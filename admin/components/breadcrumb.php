<?php
/**
 * admin/components/breadcrumb.php
 * ------------------------------------------------------------
 * Reusable breadcrumb bar.
 * Expected: $crumbs = [ ['label'=>, 'url'=>(optional)], ... ]
 * The last item is rendered as the current (non-link) page.
 * ------------------------------------------------------------
 */
$crumbs = $crumbs ?? [];
if (!$crumbs) return;
?>
<nav class="breadcrumbs" aria-label="Breadcrumb">
  <a href="<?= htmlspecialchars(admin_url('index.php')) ?>"><i class="fa-solid fa-house"></i></a>
  <?php foreach ($crumbs as $i => $crumb): $isLast = ($i === count($crumbs) - 1); ?>
    <span class="sep">/</span>
    <?php if ($isLast || empty($crumb['url'])): ?>
      <span class="current"><?= htmlspecialchars((string)($crumb['label'] ?? '')) ?></span>
    <?php else: ?>
      <a href="<?= htmlspecialchars($crumb['url']) ?>"><?= htmlspecialchars((string)($crumb['label'] ?? '')) ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
</nav>

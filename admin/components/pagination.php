<?php
/**
 * admin/components/pagination.php
 * ------------------------------------------------------------
 * Reusable pagination bar.
 * Expected:
 *   $page       : int current page (1-based)
 *   $pages      : int total pages
 *   $total      : int total items
 *   $offset     : int offset
 *   $per_page   : int
 *   $url_builder: callable(array $overrides):string
 * ------------------------------------------------------------
 */
$page        = $page        ?? 1;
$pages       = $pages       ?? 1;
$total       = $total       ?? 0;
$offset      = $offset      ?? 0;
$per_page    = $per_page    ?? 10;
$url_builder = $url_builder ?? null;

if ($pages <= 1) return;
?>
<div class="pagination">
  <div class="info">
    Showing <?= (int)($offset + 1) ?>–<?= (int)min($total, $offset + $per_page) ?> of <?= (int)$total ?>
  </div>
  <div class="pages">
    <?php if ($page > 1 && is_callable($url_builder)): ?>
      <a href="<?= htmlspecialchars($url_builder(['page' => $page - 1])) ?>">&laquo;</a>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <?php if ($i === $page): ?>
        <span class="current"><?= (int)$i ?></span>
      <?php elseif (is_callable($url_builder)): ?>
        <a href="<?= htmlspecialchars($url_builder(['page' => $i])) ?>"><?= (int)$i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $pages && is_callable($url_builder)): ?>
      <a href="<?= htmlspecialchars($url_builder(['page' => $page + 1])) ?>">&raquo;</a>
    <?php endif; ?>
  </div>
</div>

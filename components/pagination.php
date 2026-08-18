<?php
/**
 * components/pagination.php
 * ------------------------------------------------------------
 * REUSABLE pagination bar.
 *
 * Expected variables (set by the caller):
 *   $page       : int current page (1-based)
 *   $pages      : int total pages
 *   $total      : int total items
 *   $offset     : int offset of current page
 *   $per_page   : int items per page
 *   $url_builder: callable(array $overrides):string  — builds the URL
 * ------------------------------------------------------------
 */
$page       = $page       ?? 1;
$pages      = $pages      ?? 1;
$total      = $total      ?? 0;
$offset     = $offset     ?? 0;
$per_page   = $per_page   ?? 10;
$url_builder= $url_builder ?? null;

if ($pages <= 1) {
    return;
}
?>
<div class="pagination">
  <div class="info">
    <?= htmlspecialchars(t('account.showing', 'Showing')) ?> <?= (int)($offset + 1) ?>–<?= (int)min($total, $offset + $per_page) ?> <?= htmlspecialchars(t('account.of', 'of')) ?> <?= (int)$total ?>
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
<style>
.pagination{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:18px;flex-wrap:wrap;font-size:13.5px;}
.pagination .info{color:var(--muted,#6b7280);}
.pagination .pages{display:flex;gap:6px;flex-wrap:wrap;}
.pagination .pages a,.pagination .pages span{min-width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--line,#e8e6e0);border-radius:8px;font-size:13px;color:var(--ink,#1c1e26);background:#fff;text-decoration:none;}
.pagination .pages a:hover{background:#f4efe6;}
.pagination .pages span.current{background:var(--navy,#1b2a4a);color:#fff;border-color:var(--navy,#1b2a4a);font-weight:600;}
</style>

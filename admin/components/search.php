<?php
/**
 * admin/components/search.php
 * ------------------------------------------------------------
 * Reusable search input bar for listing pages.
 * Expected:
 *   $search_action : string form action URL
 *   $search_q      : string current query
 *   $search_placeholder : string placeholder (optional)
 *   $search_hidden : array of hidden fields => values (optional)
 * ------------------------------------------------------------
 */
$search_action        = $search_action        ?? '';
$search_q             = $search_q             ?? '';
$search_placeholder   = $search_placeholder   ?? t('admin.search');
$search_hidden        = $search_hidden        ?? [];
?>
<form class="admin-search" method="get" action="<?= htmlspecialchars($search_action) ?>">
  <input type="search" name="q" value="<?= htmlspecialchars($search_q) ?>" placeholder="<?= htmlspecialchars($search_placeholder) ?>">
  <?php foreach ($search_hidden as $k => $v): ?>
    <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars((string)$v) ?>">
  <?php endforeach; ?>
  <button type="submit" class="btn btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
</form>

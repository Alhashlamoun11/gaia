<?php
/**
 * admin/components/empty-state.php
 * ------------------------------------------------------------
 * Reusable empty-state block.
 * Expected:
 *   $empty_title  : string
 *   $empty_icon   : Font Awesome class (optional)
 *   $empty_action : ['label'=>, 'url'=>] optional
 * ------------------------------------------------------------
 */
$empty_title  = $empty_title  ?? '';
$empty_icon   = $empty_icon   ?? 'fa-inbox';
$empty_action = $empty_action ?? [];
if ($empty_title === '') return;
?>
<div class="empty-state">
  <div class="icon"><i class="fa-solid <?= htmlspecialchars($empty_icon) ?>"></i></div>
  <p><?= htmlspecialchars($empty_title) ?></p>
<?php if (!empty($empty_action['label']) && !empty($empty_action['url'])): ?>
    <a class="btn btn-sm" href="<?= htmlspecialchars($empty_action['url']) ?>"><?= htmlspecialchars($empty_action['label']) ?></a>
  <?php endif; ?>
</div>
<?php endif; ?>

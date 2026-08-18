<?php
/**
 * components/empty-state.php
 * ------------------------------------------------------------
 * REUSABLE empty-state block.
 *
 * Expected variables (set by the caller):
 *   $empty_title : string  — translated message (required)
 *   $empty_icon  : string  — Font Awesome class (optional)
 *   $empty_action: array   — ['label' => ..., 'url' => ...] optional CTA
 * ------------------------------------------------------------
 */
$empty_title  = $empty_title  ?? '';
$empty_icon   = $empty_icon   ?? 'fa-inbox';
$empty_action = $empty_action ?? [];
if ($empty_title === '') return;
?>
<div class="empty-state">
  <div class="empty-state-icon"><i class="fa-solid <?= htmlspecialchars($empty_icon) ?>"></i></div>
  <p><?= htmlspecialchars($empty_title) ?></p>
  <?php if (!empty($empty_action['label']) && !empty($empty_action['url'])): ?>
    <a class="btn btn-sm" href="<?= htmlspecialchars($empty_action['url']) ?>"><?= htmlspecialchars($empty_action['label']) ?></a>
  <?php endif; ?>
</div>
<style>
.empty-state{text-align:center;padding:40px 20px;color:var(--muted,#6b7280);}
.empty-state-icon{font-size:38px;color:#d9c9a8;margin-bottom:12px;}
.empty-state p{margin:0 0 16px;font-size:14px;}
</style>

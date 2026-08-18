<?php
/**
 * admin/components/modal-delete.php
 * ------------------------------------------------------------
 * Reusable delete confirmation modal.
 * Expected:
 *   $modal_id   : string unique id
 *   $modal_url  : string POST action URL
 *   $modal_title: string (optional)
 *   $modal_msg  : string (optional)
 *   $modal_hidden: array of hidden fields => values (optional)
 * ------------------------------------------------------------
 */
$modal_id     = $modal_id     ?? 'deleteModal';
$modal_url    = $modal_url    ?? '#';
$modal_title  = $modal_title  ?? t('admin.confirm_delete');
$modal_msg    = $modal_msg    ?? t('admin.delete_confirm_text');
$modal_hidden = $modal_hidden ?? [];
?>
<div class="modal" id="<?= htmlspecialchars($modal_id) ?>" hidden>
  <div class="modal-overlay" data-close></div>
  <div class="modal-card">
    <h3><?= htmlspecialchars($modal_title) ?></h3>
    <p><?= htmlspecialchars($modal_msg) ?></p>
    <form method="post" action="<?= htmlspecialchars($modal_url) ?>">
      <?= csrf_field() ?>
      <?php foreach ($modal_hidden as $k => $v): ?>
        <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars((string)$v) ?>">
      <?php endforeach; ?>
      <div class="modal-actions">
        <button type="button" class="btn" data-close><?= htmlspecialchars(t('admin.cancel')) ?></button>
        <button type="submit" class="btn btn-danger"><?= htmlspecialchars(t('admin.delete')) ?></button>
      </div>
    </form>
  </div>
</div>

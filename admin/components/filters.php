<?php
/**
 * admin/components/filters.php
 * ------------------------------------------------------------
 * Reusable filter bar for listing pages (GET form).
 * Expected:
 *   $filter_action : string form action URL
 *   $filter_fields : array of [
 *       'name'=>, 'label'=>, 'options'=>[value=>label], 'value'=>(current)
 *   ]
 *   $filter_hidden : array of hidden fields => values (optional)
 * ------------------------------------------------------------
 */
$filter_action = $filter_action ?? '';
$filter_fields = $filter_fields ?? [];
$filter_hidden = $filter_hidden ?? [];
if (!$filter_fields) {
    return;
}
?>
<form class="admin-filters" method="get" action="<?= htmlspecialchars($filter_action) ?>">
  <?php foreach ($filter_hidden as $k => $v): ?>
    <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars((string)$v) ?>">
  <?php endforeach; ?>
  <?php foreach ($filter_fields as $field): ?>
    <div class="filter-field">
      <label><?= htmlspecialchars((string)($field['label'] ?? '')) ?></label>
      <select name="<?= htmlspecialchars((string)($field['name'] ?? '')) ?>">
        <?php foreach (($field['options'] ?? []) as $value => $label): ?>
          <option value="<?= htmlspecialchars((string)$value) ?>" <?= (string)($field['value'] ?? '') === (string)$value ? 'selected' : '' ?>><?= htmlspecialchars((string)$label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endforeach; ?>
  <div class="filter-actions">
    <button type="submit" class="btn btn-sm btn-primary"><?= htmlspecialchars(t('admin.apply')) ?></button>
  </div>
</form>

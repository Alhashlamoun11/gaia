<?php
/**
 * admin/components/table.php
 * ------------------------------------------------------------
 * Reusable data table wrapper.
 *
 * Expected variables:
 *   $table_columns : array of ['label'=>, 'key'=>(optional), 'sortable'=>(optional)]
 *   $table_rows    : array of associative arrays
 *   $table_empty   : string empty message (optional)
 *   $table_badges  : array of 'column' => true to render as badge
 *   $table_modifiers: optional callable($row, $key) returning HTML for a cell
 *   $table_isLast  : unused
 * ------------------------------------------------------------
 */
$table_columns   = $table_columns   ?? [];
$table_rows      = $table_rows      ?? [];
$table_empty     = $table_empty     ?? t('admin.no_records');
$table_badges    = $table_badges    ?? [];
$table_modifiers = $table_modifiers ?? null;
?>
<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <?php foreach ($table_columns as $col): ?>
          <th><?= htmlspecialchars((string)($col['label'] ?? '')) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php if (!$table_rows): ?>
        <tr><td colspan="<?= (int)max(1, count($table_columns)) ?>"><div class="muted" style="text-align:center;padding:24px;"><?= htmlspecialchars($table_empty) ?></div></td></tr>
      <?php else: ?>
        <?php foreach ($table_rows as $row): ?>
          <tr>
            <?php foreach ($table_columns as $col): ?>
              <?php
                $key = $col['key'] ?? '';
                $val = $key !== '' ? ($row[$key] ?? '') : '';
                if (is_callable($table_modifiers)) {
                    $cell = $table_modifiers($row, $key);
                } else {
                    $cell = '';
                    if ($key !== '') {
                        $cell = htmlspecialchars((string)$val);
                        if (!empty($table_badges[$key])) {
                            $cell = '<span class="badge badge-' . htmlspecialchars((string)$val) . '">' . htmlspecialchars((string)$val) . '</span>';
                        }
                    }
                }
              ?>
              <td><?= $cell ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

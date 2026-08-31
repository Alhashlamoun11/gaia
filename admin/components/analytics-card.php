<?php
/**
 * analytics-card.php
 * Reusable analytics card component.
 * Parameters (set before include):
 *   $card_title   – Card title (string)
 *   $card_id      – Unique ID for the card (string) – used for chart canvas and export button.
 *   $chart_type   – Chart.js type (e.g., 'line', 'bar', 'pie'). Optional.
 *   $chart_data   – PHP array of data to be JSON‑encoded for the chart. Optional.
 *   $show_export  – bool – whether to show CSV export button.
 */
?>
<div class="analytics-card" id="<?= htmlspecialchars($card_id) ?>-card">
    <h3 class="analytics-card-title"><?= htmlspecialchars($card_title) ?></h3>
    <?php if (!empty($chart_type) && !empty($chart_data)): ?>
        <canvas id="<?= htmlspecialchars($card_id) ?>-canvas" class="analytics-canvas"></canvas>
    <?php endif; ?>
    <?php if (!empty($show_export)): ?>
        <button class="export-btn" data-card-id="<?= htmlspecialchars($card_id) ?>">Export CSV</button>
    <?php endif; ?>
</div>

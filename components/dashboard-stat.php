<?php
/**
 * components/dashboard-stat.php
 * ------------------------------------------------------------
 * REUSABLE dashboard stat card.
 *
 * Expected variables (set by the caller):
 *   $stat_value : string|int  — the number/value
 *   $stat_label : string      — translated label
 *   $stat_icon  : string      — Font Awesome class e.g. 'fa-bookmark'
 *   $stat_color : string      — 'blue'|'green'|'gold'|'red' (optional)
 *   $stat_link  : string      — optional URL wrapping the card
 * ------------------------------------------------------------
 */
$stat_value = $stat_value ?? 0;
$stat_label = $stat_label ?? '';
$stat_icon  = $stat_icon  ?? 'fa-bookmark';
$stat_color = $stat_color ?? 'blue';
$stat_link  = $stat_link  ?? '';
$stat_color = in_array($stat_color, ['blue', 'green', 'gold', 'red'], true) ? $stat_color : 'blue';

$inner = '<div class="stat-icon ' . htmlspecialchars($stat_color) . '"><i class="fa-solid ' . htmlspecialchars($stat_icon) . '"></i></div>'
       . '<div><div class="stat-value">' . htmlspecialchars((string)$stat_value) . '</div>'
       . '<div class="stat-label">' . htmlspecialchars((string)$stat_label) . '</div></div>';

if ($stat_link !== ''):
?>
  <a class="stat-card" href="<?= htmlspecialchars($stat_link) ?>"><?php echo $inner; ?></a>
<?php else: ?>
  <div class="stat-card"><?php echo $inner; ?></div>
<?php endif; ?>

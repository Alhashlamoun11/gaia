<?php
/**
 * admin/components/stats-card.php
 * ------------------------------------------------------------
 * Reusable dashboard stat card.
 * Expected:
 *   $stat_value : string|int
 *   $stat_label : string
 *   $stat_icon  : Font Awesome class
 *   $stat_color : 'blue'|'green'|'gold'|'red'|'navy'
 *   $stat_link  : optional URL
 * ------------------------------------------------------------
 */
$stat_value = $stat_value ?? 0;
$stat_label = $stat_label ?? '';
$stat_icon  = $stat_icon  ?? 'fa-bookmark';
$stat_color = $stat_color ?? 'blue';
$stat_link  = $stat_link  ?? '';
$stat_color = in_array($stat_color, ['blue', 'green', 'gold', 'red', 'navy'], true) ? $stat_color : 'blue';

$inner = '<div class="stat-icon ' . htmlspecialchars($stat_color) . '"><i class="fa-solid ' . htmlspecialchars($stat_icon) . '"></i></div>'
       . '<div><div class="stat-value">' . htmlspecialchars((string)$stat_value) . '</div>'
       . '<div class="stat-label">' . htmlspecialchars((string)$stat_label) . '</div></div>';

if ($stat_link !== ''):
?>
<a class="stat-card" href="<?= htmlspecialchars($stat_link) ?>"><?php echo $inner; ?></a>
<?php else: ?>
<div class="stat-card"><?php echo $inner; ?></div>
<?php endif; ?>

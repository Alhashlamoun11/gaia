<?php
require __DIR__ . '/db.php';
$pdo = getPDO();
echo "=== seo_meta cols ===\n";
echo implode(',', array_column($pdo->query('DESCRIBE seo_meta')->fetchAll(), 'Field')) . "\n";
echo "=== seo_meta rows ===\n";
foreach ($pdo->query('SELECT page, lang, title FROM seo_meta') as $r) {
    echo $r['page'] . ' (' . $r['lang'] . ') - ' . $r['title'] . "\n";
}
echo "=== seo_meta count by page ===\n";
foreach ($pdo->query('SELECT page, COUNT(*) c, GROUP_CONCAT(lang) l FROM seo_meta GROUP BY page') as $r) {
    echo $r['page'] . ' [' . $r['l'] . '] x' . $r['c'] . "\n";
}
echo "=== menu items header ===\n";
foreach ($pdo->query('SELECT id, nav_key, url FROM menu_items WHERE menu="header"') as $r) {
    echo $r['id'] . ': ' . $r['nav_key'] . ' = ' . $r['url'] . "\n";
}
echo "=== events categories ===\n";
foreach ($pdo->query('SELECT category, COUNT(*) c FROM events GROUP BY category') as $r) {
    echo $r['category'] . ': ' . $r['c'] . "\n";
}
echo "=== night_offerings cols ===\n";
echo implode(',', array_column($pdo->query('DESCRIBE night_offerings')->fetchAll(), 'Field')) . "\n";
echo "=== destinations count ===\n";
echo $pdo->query('SELECT COUNT(*) FROM destinations')->fetchColumn() . "\n";
echo "=== tour_destinations count ===\n";
echo $pdo->query('SELECT COUNT(*) FROM tour_destinations')->fetchColumn() . "\n";
echo "=== weroad trips count ===\n";
echo $pdo->query('SELECT COUNT(*) FROM weroad_trips')->fetchColumn() . "\n";
echo "=== media count ===\n";
echo $pdo->query('SELECT COUNT(*) FROM media')->fetchColumn() . "\n";

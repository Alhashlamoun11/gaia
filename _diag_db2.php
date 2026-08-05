<?php
require __DIR__ . '/db.php';
$pdo = getPDO();
foreach (['events','hotels','hotel_rooms','hotel_facilities','hotel_offers','hotel_reviews'] as $t) {
    echo "=== $t cols ===\n";
    echo implode(', ', array_column($pdo->query("DESCRIBE `$t`")->fetchAll(), 'Field')) . "\n";
}
echo "=== sample event row ===\n";
var_export($pdo->query('SELECT * FROM events LIMIT 1')->fetch());
echo "\n=== sample hotel row ===\n";
var_export($pdo->query('SELECT * FROM hotels LIMIT 1')->fetch());
echo "\n=== sample tour trip row (cols) ===\n";
echo implode(', ', array_column($pdo->query('DESCRIBE weroad_trips')->fetchAll(), 'Field')) . "\n";
echo "=== sample trip ===\n";
var_export($pdo->query('SELECT slug, tagline, rating, reviews_count FROM weroad_trips LIMIT 1')->fetch());
echo "\n=== tour_destinations ===\n";
var_export($pdo->query('SELECT * FROM tour_destinations')->fetchAll());
echo "\n=== hotspots: gather tour trips with prices ===\n";
foreach ($pdo->query('SELECT t.id, t.name, t.slug, t.base_price, t.category, d.name dest, d.slug dest_slug FROM weroad_trips t LEFT JOIN tour_destinations d ON 1=0') as $r) {
    // placeholder to see joins - not used
}
echo "done\n";

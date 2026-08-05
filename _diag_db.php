<?php
require __DIR__ . '/db.php';
$pdo = getPDO();
foreach (['events','hotels','hotel_rooms','hotel_facilities','hotel_offers','hotel_reviews','media','destinations','tour_destinations','hotel_room_media','hotel_gallery','event_gallery','tour_gallery'] as $t) {
    try {
        $r = $pdo->query('SELECT COUNT(*) c FROM `' . $t . '`')->fetch();
        echo $t . ': ' . $r['c'] . PHP_EOL;
    } catch (Exception $e) {
        echo $t . ': ERROR ' . $e->getMessage() . PHP_EOL;
    }
}
echo "--- media ---" . PHP_EOL;
foreach ($pdo->query('SELECT * FROM media') as $row) {
    echo $row['key'] . "\n";
}
echo "--- events ---" . PHP_EOL;
foreach ($pdo->query('SELECT * FROM events') as $row) {
    echo $row['id'] . ' | ' . $row['title'] . ' | ' . $row['category'] . PHP_EOL;
}
echo "--- hotels ---" . PHP_EOL;
foreach ($pdo->query('SELECT * FROM hotels') as $row) {
    echo $row['id'] . ' | ' . $row['name'] . ' | ' . $row['slug'] . ' | dest=' . var_export($row['destination_id'], true) . PHP_EOL;
}
echo "--- hotel_rooms ---" . PHP_EOL;
foreach ($pdo->query('SELECT * FROM hotel_rooms') as $row) {
    echo $row['id'] . ' | hotel=' . $row['hotel_id'] . ' | ' . $row['name'] . ' | ' . $row['price'] . PHP_EOL;
}
echo "--- hotel_facilities ---" . PHP_EOL;
foreach ($pdo->query('SELECT * FROM hotel_facilities') as $row) {
    echo $row['id'] . ' | hotel=' . $row['hotel_id'] . ' | ' . $row['name'] . PHP_EOL;
}
echo "--- hotel_offers ---" . PHP_EOL;
foreach ($pdo->query('SELECT * FROM hotel_offers') as $row) {
    echo $row['id'] . ' | hotel=' . $row['hotel_id'] . ' | ' . $row['title'] . PHP_EOL;
}
echo "--- hotel_reviews ---" . PHP_EOL;
foreach ($pdo->query('SELECT * FROM hotel_reviews') as $row) {
    echo $row['id'] . ' | hotel=' . $row['hotel_id'] . ' | ' . $row['reviewer'] . PHP_EOL;
}
echo "--- destinations ---" . PHP_EOL;
foreach ($pdo->query('SELECT * FROM destinations') as $row) {
    echo $row['id'] . ' | ' . $row['name'] . ' | ' . $row['slug'] . PHP_EOL;
}
echo "--- tour_destinations ---" . PHP_EOL;
foreach ($pdo->query('SELECT * FROM tour_destinations') as $row) {
    echo $row['id'] . ' | ' . $row['name'] . ' | ' . $row['slug'] . PHP_EOL;
}
echo "--- seo_meta ---" . PHP_EOL;
foreach ($pdo->query('SELECT page, lang FROM seo_meta') as $row) {
    echo $row['page'] . ' [' . $row['lang'] . ']' . PHP_EOL;
}

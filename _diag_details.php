<?php
require __DIR__ . '/db.php';
$pdo = getPDO();

function c($label, $sql, $pdo) {
    try {
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        echo "$label: " . count($rows) . " rows\n";
    } catch (Exception $e) {
        echo "$label: ERROR - " . $e->getMessage() . "\n";
    }
}

echo "=== Detail pages DB diagnostic ===\n";
c("events", "SELECT * FROM events WHERE is_active=1", $pdo);
c("hotels", "SELECT * FROM hotels WHERE is_active=1", $pdo);
c("hotel_rooms", "SELECT * FROM hotel_rooms WHERE is_active=1", $pdo);
c("hotel_facilities", "SELECT * FROM hotel_facilities", $pdo);
c("hotel_offers", "SELECT * FROM hotel_offers WHERE is_active=1", $pdo);
c("hotel_reviews", "SELECT * FROM hotel_reviews WHERE is_active=1", $pdo);
c("weroad_trips", "SELECT id,slug,name FROM weroad_trips WHERE is_active=1", $pdo);
c("weroad_trip_itineraries", "SELECT * FROM weroad_trip_itineraries", $pdo);
c("weroad_trip_inclusions", "SELECT * FROM weroad_trip_inclusions", $pdo);
c("weroad_trip_optional_activities", "SELECT * FROM weroad_trip_optional_activities", $pdo);
c("weroad_trip_faqs", "SELECT * FROM weroad_trip_faqs", $pdo);
c("weroad_departures", "SELECT * FROM weroad_departures WHERE is_active=1", $pdo);
c("weroad_reviews", "SELECT * FROM weroad_reviews", $pdo);
c("destinations", "SELECT id,name,country FROM destinations WHERE is_active=1", $pdo);

// Check column presence
echo "\n=== Column checks ===\n";
foreach ([
    ['events','slug'],['events','organizer'],['events','destination_id'],['events','gallery_urls'],
    ['hotels','location'],['hotels','avg_rating'],
    ['hotel_rooms','slug'],['hotel_rooms','capacity'],['hotel_rooms','beds'],['hotel_rooms','size_sqm'],['hotel_rooms','facilities'],['hotel_rooms','gallery_urls'],
    ['weroad_trips','destination_id'],
    ['night_offerings','slug'],
] as [$t,$col]) {
    $r = $pdo->query("SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=" . $pdo->quote($t) . " AND COLUMN_NAME=" . $pdo->quote($col))->fetch();
    echo "$t.$col: " . ($r['c'] ? 'YES' : 'NO') . "\n";
}
echo "Done.\n";

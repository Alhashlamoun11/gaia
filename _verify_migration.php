<?php
require __DIR__ . '/db.php';
$pdo = getPDO();

function check($label, $sql, $pdo) {
    try {
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        echo "$label: " . count($rows) . " rows\n";
        if ($rows) {
            echo "  First row: " . json_encode($rows[0], JSON_UNESCAPED_UNICODE) . "\n";
        }
    } catch (Exception $e) {
        echo "$label: ERROR - " . $e->getMessage() . "\n";
    }
}

echo "=== Migration #8 Verification ===\n\n";

// Events
echo "--- Events ---\n";
check("events total", "SELECT COUNT(*) c FROM events", $pdo);
check("events with slug", "SELECT id, title, slug, organizer, destination_id, date_label, location FROM events LIMIT 5", $pdo);
check("events gallery", "SELECT id, gallery_urls FROM events WHERE gallery_urls IS NOT NULL LIMIT 3", $pdo);

// Hotels
echo "\n--- Hotels ---\n";
check("hotels total", "SELECT COUNT(*) c FROM hotels", $pdo);
check("hotels with location", "SELECT id, name, slug, location, avg_rating, gallery_urls FROM hotels LIMIT 5", $pdo);

// Hotel Rooms
echo "\n--- Hotel Rooms ---\n";
check("hotel_rooms total", "SELECT COUNT(*) c FROM hotel_rooms", $pdo);
check("hotel_rooms detail", "SELECT id, hotel_id, name, slug, capacity, beds, size_sqm, price, facilities FROM hotel_rooms LIMIT 5", $pdo);

// Hotel Facilities
check("hotel_facilities total", "SELECT COUNT(*) c FROM hotel_facilities", $pdo);
check("hotel_facilities", "SELECT id, hotel_id, name FROM hotel_facilities LIMIT 5", $pdo);

// Hotel Offers
check("hotel_offers total", "SELECT COUNT(*) c FROM hotel_offers", $pdo);
check("hotel_offers", "SELECT id, hotel_id, title, price FROM hotel_offers LIMIT 5", $pdo);

// Hotel Reviews
check("hotel_reviews total", "SELECT COUNT(*) c FROM hotel_reviews", $pdo);
check("hotel_reviews", "SELECT id, hotel_id, reviewer, rating, LEFT(text,60) t FROM hotel_reviews LIMIT 5", $pdo);

// SEO Meta
echo "\n--- SEO Meta ---\n";
check("seo_meta total", "SELECT COUNT(*) c FROM seo_meta", $pdo);
$seo = $pdo->query("SELECT page, lang FROM seo_meta ORDER BY page, lang")->fetchAll();
foreach ($seo as $s) {
    echo "  {$s['page']} ({$s['lang']})\n";
}

// Translations
echo "\n--- Translations (detail pages) ---\n";
check("detail translations", "SELECT COUNT(*) c FROM translations WHERE `key` LIKE 'detail.%'", $pdo);
check("event translations", "SELECT COUNT(*) c FROM translations WHERE `key` LIKE 'event.%'", $pdo);
check("hotel translations", "SELECT COUNT(*) c FROM translations WHERE `key` LIKE 'hotel.%'", $pdo);
check("room translations", "SELECT COUNT(*) c FROM translations WHERE `key` LIKE 'room.%'", $pdo);
check("tour translations", "SELECT COUNT(*) c FROM translations WHERE `key` LIKE 'tour.%'", $pdo);

echo "\nDone.\n";

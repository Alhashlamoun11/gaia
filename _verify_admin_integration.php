<?php
require __DIR__ . '/db.php';
$pdo = getPDO();

echo "=== GAIA Admin Panel Integration Verification ===\n\n";

// 1. Database tables
$tables = ['settings','translations','seo_meta','banners','highlights','trust_scores','awards','night_offerings','social_links','media','reviews','hotel_reviews','weroad_reviews','faq','partners','services','hotels','hotel_rooms','weroad_trips','events','routes','car_classes','transfers_locations','transfer_services','transfer_extras','tour_categories','tour_destinations','weroad_trip_itineraries','weroad_trip_inclusions','weroad_trip_optional_activities','weroad_departures','weroad_trip_faqs','hotel_facilities','hotel_offers','hotel_amenities','menu_items','pages','page_sections'];
echo "--- Database Tables ---\n";
foreach ($tables as $t) {
    try {
        $c = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo "  [OK] $t: $c rows\n";
    } catch (PDOException $e) {
        echo "  [MISSING] $t\n";
    }
}

// 2. Admin pages
echo "\n--- Admin Pages ---\n";
$adminPages = [
    'admin/index.php', 'admin/settings.php', 'admin/profile.php',
    'admin/home/index.php', 'admin/home/form.php',
    'admin/seo/index.php',
    'admin/translations/index.php', 'admin/translations/edit.php',
    'admin/tours/index.php', 'admin/tours/form.php', 'admin/tours/detail.php', 'admin/tours/form-detail.php',
    'admin/tours/categories.php', 'admin/tours/category-form.php', 'admin/tours/destinations.php', 'admin/tours/destination-form.php',
    'admin/hotels/index.php', 'admin/hotels/form.php', 'admin/hotels/detail.php', 'admin/hotels/form-detail.php',
    'admin/rooms/index.php', 'admin/rooms/form.php',
    'admin/transfers/index.php', 'admin/transfers/form.php',
    'admin/events/index.php', 'admin/events/form.php',
    'admin/reviews/index.php', 'admin/reviews/edit.php',
    'admin/faq/index.php', 'admin/faq/form.php',
    'admin/partners/index.php', 'admin/partners/form.php',
    'admin/services/index.php', 'admin/services/form.php',
    'admin/media/index.php',
    'admin/bookings/index.php', 'admin/bookings/view.php',
    'admin/users/index.php', 'admin/users/view.php', 'admin/users/edit.php',
];
foreach ($adminPages as $p) {
    $full = __DIR__ . '/' . $p;
    echo "  " . (file_exists($full) ? "[OK]" : "[MISSING]") . " $p\n";
}

// 3. Public pages
echo "\n--- Public Pages ---\n";
$publicPages = ['index.php','tour.php','hotel.php','room.php','events.php','route.php','search.php','page.php','gaia-night.php','checkout.php','process_booking.php'];
foreach ($publicPages as $p) {
    $full = __DIR__ . '/' . $p;
    echo "  " . (file_exists($full) ? "[OK]" : "[MISSING]") . " $p\n";
}

// 4. Settings keys
echo "\n--- Settings Keys ---\n";
$settings = $pdo->query("SELECT `key` FROM settings ORDER BY `key`")->fetchAll(PDO::FETCH_COLUMN);
foreach ($settings as $k) {
    echo "  [OK] $k\n";
}

// 5. Translation count
echo "\n--- Translations ---\n";
$en = (int)$pdo->query("SELECT COUNT(*) FROM translations WHERE lang='en'")->fetchColumn();
$ar = (int)$pdo->query("SELECT COUNT(*) FROM translations WHERE lang='ar'")->fetchColumn();
echo "  EN: $en, AR: $ar\n";

// 6. SEO meta
echo "\n--- SEO Meta ---\n";
$seo = $pdo->query("SELECT page, lang FROM seo_meta ORDER BY page, lang")->fetchAll();
foreach ($seo as $s) {
    echo "  [OK] {$s['page']} ({$s['lang']})\n";
}

echo "\n=== Verification Complete ===\n";
<?php
require_once __DIR__ . '/db.php';
try {
    $pdo = getPDO();
    $pdo->exec("ALTER TABLE hotel_rooms ADD COLUMN quantity INT NOT NULL DEFAULT 1 AFTER capacity");
    echo "Added quantity to hotel_rooms\n";
} catch (Exception $e) {
    echo "hotel_rooms update error: " . $e->getMessage() . "\n";
}

try {
    $pdo = getPDO();
    $pdo->exec("ALTER TABLE hotel_bookings ADD COLUMN rooms_count INT NOT NULL DEFAULT 1 AFTER guests");
    echo "Added rooms_count to hotel_bookings\n";
} catch (Exception $e) {
    echo "hotel_bookings update error: " . $e->getMessage() . "\n";
}

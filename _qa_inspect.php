<?php
require 'db.php';
$pdo = getPDO();

echo "=== HOTEL ROOMS ===\n";
$stmt = $pdo->query('SELECT r.id, r.hotel_id, r.name, r.quantity, h.name as hotel_name FROM hotel_rooms r JOIN hotels h ON h.id=r.hotel_id ORDER BY r.hotel_id, r.id LIMIT 15');
foreach($stmt->fetchAll() as $row) {
    echo "  Room #{$row['id']} [{$row['hotel_name']}] {$row['name']} qty={$row['quantity']}\n";
}

echo "\n=== HOTEL BOOKINGS (last 10) ===\n";
$stmt = $pdo->query('SELECT id, booking_reference, hotel_id, room_id, check_in_date, check_out_date, rooms_count, status, guest_email FROM hotel_bookings ORDER BY id DESC LIMIT 10');
foreach($stmt->fetchAll() as $row) {
    echo "  Booking #{$row['id']} ref={$row['booking_reference']} hotel={$row['hotel_id']} room={$row['room_id']} {$row['check_in_date']}->{$row['check_out_date']} qty={$row['rooms_count']} status={$row['status']} guest={$row['guest_email']}\n";
}

echo "\n=== HOTEL MANAGERS ===\n";
$stmt = $pdo->query('SELECT u.id, u.email, h.id as hotel_id, h.name as hotel_name FROM users u JOIN hotels h ON h.manager_user_id=u.id LIMIT 5');
foreach($stmt->fetchAll() as $row) {
    echo "  User #{$row['id']} {$row['email']} => Hotel #{$row['hotel_id']} {$row['hotel_name']}\n";
}

<?php
/**
 * _qa_calendar_render.php
 * Simulates what hotel/availability.php computes and renders for Hotel #1
 * for the current month — exactly what the hotel manager portal shows.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/services/HotelAvailabilityService.php';

$pdo = getPDO();

$HOTEL_ID = 1;

// Get rooms for hotel 1
$stmt = $pdo->prepare("SELECT * FROM hotel_rooms WHERE hotel_id = ? ORDER BY name ASC");
$stmt->execute([$HOTEL_ID]);
$rooms = $stmt->fetchAll();

// Check September 2026 (where booking #9 and #10 exist)
$month = 9; $year = 2026;
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$startDate = sprintf('%04d-%02d-01', $year, $month);
$endDate   = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
$roomIds   = array_column($rooms, 'id');

$bookedMap = HotelAvailabilityService::batchBookedByDate($pdo, $roomIds, $startDate, $endDate);

echo "=======================================================\n";
echo " GAIA QA — Hotel Manager Availability Calendar Render\n";
echo " Hotel #$HOTEL_ID — " . date('F Y', strtotime($startDate)) . "\n";
echo "=======================================================\n";

// Print header
echo str_pad("Room", 28) . " | QTY | ";
for ($d = 1; $d <= $daysInMonth; $d++) {
    echo str_pad($d, 3);
}
echo "\n" . str_repeat("-", 28 + 7 + $daysInMonth * 3) . "\n";

foreach ($rooms as $r) {
    $rid = (int)$r['id'];
    $qty = (int)$r['quantity'];
    echo str_pad("#{$rid} {$r['name']}", 28) . " |  $qty | ";
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $date   = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $booked = $bookedMap[$rid][$date] ?? 0;
        $avail  = $qty - $booked;
        if ($booked === 0) {
            $cell = " . "; // green
        } elseif ($booked >= $qty) {
            $cell = "[X]"; // red — fully booked
        } else {
            $cell = "[-]"; // amber — partial
        }
        echo $cell;
    }
    echo "  (. = avail, [-] = partial, [X] = full)\n";
}

echo "\n=== Active Blocking Bookings for Hotel #$HOTEL_ID in Sep 2026 ===\n";
$placeholders = implode(',', array_fill(0, count(HotelAvailabilityService::BLOCKING_STATUSES), '?'));
$sql = "SELECT id, booking_reference, room_id, check_in_date, check_out_date, rooms_count, status
        FROM hotel_bookings
        WHERE hotel_id = ?
        AND status IN ($placeholders)
        AND check_in_date <= ? AND check_out_date > ?
        ORDER BY room_id, check_in_date";
$params = array_merge([$HOTEL_ID], HotelAvailabilityService::BLOCKING_STATUSES, [$endDate, $startDate]);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bks = $stmt->fetchAll();
if (empty($bks)) {
    echo "  (no active blocking bookings found for this period)\n";
} else {
    foreach ($bks as $b) {
        echo "  Booking #{$b['id']} ref={$b['booking_reference']} room={$b['room_id']} {$b['check_in_date']}->{$b['check_out_date']} rooms={$b['rooms_count']} status={$b['status']}\n";
    }
}

echo "\n=== All Bookings for Hotel #$HOTEL_ID (all statuses) ===\n";
$stmt = $pdo->prepare("SELECT id, booking_reference, room_id, check_in_date, check_out_date, rooms_count, status FROM hotel_bookings WHERE hotel_id = ? ORDER BY id DESC");
$stmt->execute([$HOTEL_ID]);
foreach($stmt->fetchAll() as $b) {
    $blocking = in_array($b['status'], HotelAvailabilityService::BLOCKING_STATUSES) ? '[BLOCKS]' : '[no-block]';
    echo "  #{$b['id']} {$b['booking_reference']} room={$b['room_id']} {$b['check_in_date']}->{$b['check_out_date']} qty={$b['rooms_count']} status={$b['status']} $blocking\n";
}

<?php
/**
 * _qa_availability.php
 * Senior QA: Room Availability & Booking Visibility Test
 *
 * Tests:
 *  TC-01: Availability calendar shows correct initial state
 *  TC-02: A new booking in 'awaiting_payment' blocks inventory
 *  TC-03: Cancelling a booking frees inventory
 *  TC-04: Confirming a booking keeps inventory blocked AND shows on calendar
 *  TC-05: Completed bookings do NOT block inventory
 *  TC-06: batchBookedByDate isolation (room A booking doesn't affect room B)
 *  TC-07: Multi-night booking spans all correct dates on calendar
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/services/HotelAvailabilityService.php';

$pdo = getPDO();
$PASS = 0; $FAIL = 0; $results = [];

function tc(string $id, string $label, bool $ok, string $detail = ''): void {
    global $PASS, $FAIL, $results;
    $status = $ok ? 'PASS' : 'FAIL';
    if ($ok) $PASS++; else $FAIL++;
    $results[] = compact('id','label','status','detail');
}

// ──────────────────────────────────────────────────────────────
// Setup: use Room #1 (St. Regis Deluxe King, qty=5) for Hotel #1
// ──────────────────────────────────────────────────────────────
$ROOM_ID   = 1;
$HOTEL_ID  = 1;
$QTY       = 5;
$CI        = '2026-12-10';
$CO        = '2026-12-13'; // 3 nights: Dec10, Dec11, Dec12 occupied

// Clean up any leftover QA test bookings
$pdo->prepare("DELETE FROM hotel_bookings WHERE booking_reference LIKE 'QA-TEST-%'")->execute();

// ──────────────────────────────────────────────────────────────
// TC-01: Baseline — no bookings in test range, should show full qty
// ──────────────────────────────────────────────────────────────
$booked = HotelAvailabilityService::bookedInventory($pdo, $ROOM_ID, $CI, $CO);
tc('TC-01', "Baseline inventory: booked=0 (no bookings yet)", $booked === 0, "Got booked=$booked");

// ──────────────────────────────────────────────────────────────
// TC-02: Insert a blocking booking (awaiting_payment) — should block 1 room
// ──────────────────────────────────────────────────────────────
$pdo->prepare("INSERT INTO hotel_bookings
    (booking_code, booking_reference, hotel_id, room_id, guest_name, guest_email, check_in_date, check_out_date, rooms_count, guests, total_price, status)
    VALUES ('QA001', 'QA-TEST-001', ?, ?, 'QA Guest', 'qa@test.com', ?, ?, 1, 2, 100, 'awaiting_payment')")
    ->execute([$HOTEL_ID, $ROOM_ID, $CI, $CO]);
$bid1 = (int)$pdo->lastInsertId();

$booked = HotelAvailabilityService::bookedInventory($pdo, $ROOM_ID, $CI, $CO);
tc('TC-02', "awaiting_payment booking blocks 1 room", $booked === 1, "Got booked=$booked");

$avail = HotelAvailabilityService::availableInventory($pdo, $ROOM_ID, $QTY, $CI, $CO);
tc('TC-02b', "Available rooms = qty-1 = ".($QTY-1), $avail === $QTY-1, "Got avail=$avail");

// ──────────────────────────────────────────────────────────────
// TC-03: Confirm the booking — should still block (confirmed is blocking)
// ──────────────────────────────────────────────────────────────
$pdo->prepare("UPDATE hotel_bookings SET status='confirmed' WHERE id=?")->execute([$bid1]);
$booked = HotelAvailabilityService::bookedInventory($pdo, $ROOM_ID, $CI, $CO);
tc('TC-03', "Confirmed booking still blocks inventory", $booked === 1, "Got booked=$booked");

// ──────────────────────────────────────────────────────────────
// TC-04: batchBookedByDate — confirmed booking should appear on Dec10, Dec11, Dec12
// ──────────────────────────────────────────────────────────────
$startDate = '2026-12-01';
$endDate   = '2026-12-31';
$map = HotelAvailabilityService::batchBookedByDate($pdo, [$ROOM_ID], $startDate, $endDate);

$dec10 = $map[$ROOM_ID]['2026-12-10'] ?? 0;
$dec11 = $map[$ROOM_ID]['2026-12-11'] ?? 0;
$dec12 = $map[$ROOM_ID]['2026-12-12'] ?? 0;
$dec13 = $map[$ROOM_ID]['2026-12-13'] ?? 0; // check-out day: NOT occupied

tc('TC-04a', "Calendar shows 1 booked on Dec 10 (check-in night)", $dec10 === 1, "Got $dec10");
tc('TC-04b', "Calendar shows 1 booked on Dec 11 (middle night)", $dec11 === 1, "Got $dec11");
tc('TC-04c', "Calendar shows 1 booked on Dec 12 (last night)", $dec12 === 1, "Got $dec12");
tc('TC-04d', "Calendar shows 0 booked on Dec 13 (check-out day, not occupied)", $dec13 === 0, "Got $dec13");

// ──────────────────────────────────────────────────────────────
// TC-05: Cancel the booking — inventory should be freed
// ──────────────────────────────────────────────────────────────
$pdo->prepare("UPDATE hotel_bookings SET status='cancelled' WHERE id=?")->execute([$bid1]);
$booked = HotelAvailabilityService::bookedInventory($pdo, $ROOM_ID, $CI, $CO);
tc('TC-05a', "Cancelled booking frees inventory: booked=0", $booked === 0, "Got booked=$booked");

$avail = HotelAvailabilityService::availableInventory($pdo, $ROOM_ID, $QTY, $CI, $CO);
tc('TC-05b', "Available after cancel = full qty=$QTY", $avail === $QTY, "Got avail=$avail");

// ──────────────────────────────────────────────────────────────
// TC-06: Completed booking does NOT block inventory
// ──────────────────────────────────────────────────────────────
$pdo->prepare("UPDATE hotel_bookings SET status='completed' WHERE id=?")->execute([$bid1]);
$booked = HotelAvailabilityService::bookedInventory($pdo, $ROOM_ID, $CI, $CO);
tc('TC-06', "Completed booking does NOT block inventory", $booked === 0, "Got booked=$booked");

// ──────────────────────────────────────────────────────────────
// TC-07: Room isolation — booking for Room #2 doesn't affect Room #1
// ──────────────────────────────────────────────────────────────
$ROOM_ID_B = 2;
$pdo->prepare("INSERT INTO hotel_bookings
    (booking_code, booking_reference, hotel_id, room_id, guest_name, guest_email, check_in_date, check_out_date, rooms_count, guests, total_price, status)
    VALUES ('QA002', 'QA-TEST-002', ?, ?, 'QA Guest B', 'qab@test.com', ?, ?, 2, 4, 200, 'confirmed')")
    ->execute([$HOTEL_ID, $ROOM_ID_B, $CI, $CO]);

$bookedA = HotelAvailabilityService::bookedInventory($pdo, $ROOM_ID,   $CI, $CO);
$bookedB = HotelAvailabilityService::bookedInventory($pdo, $ROOM_ID_B, $CI, $CO);
tc('TC-07a', "Room #1 isolation: booking for Room #2 doesn't affect Room #1", $bookedA === 0, "Room1 booked=$bookedA");
tc('TC-07b', "Room #2 correctly shows 2 rooms booked (2 rooms_count)", $bookedB === 2, "Room2 booked=$bookedB");

// ──────────────────────────────────────────────────────────────
// TC-08: Overbooking guard — isAvailable returns false when requesting > available
// ──────────────────────────────────────────────────────────────
// Room #2 qty=5, booked=2, available=3
$canBook3 = HotelAvailabilityService::isAvailable($pdo, $ROOM_ID_B, 5, $CI, $CO, 3);
$canBook4 = HotelAvailabilityService::isAvailable($pdo, $ROOM_ID_B, 5, $CI, $CO, 4);
tc('TC-08a', "isAvailable: requesting 3 of 3 available = true", $canBook3 === true, "Got ".($canBook3?'true':'false'));
tc('TC-08b', "isAvailable: requesting 4 of 3 available = false (overbook guard)", $canBook4 === false, "Got ".($canBook4?'true':'false'));

// ──────────────────────────────────────────────────────────────
// TC-09: batchBookedByDate multi-room — both rooms shown correctly
// ──────────────────────────────────────────────────────────────
$map2 = HotelAvailabilityService::batchBookedByDate($pdo, [$ROOM_ID, $ROOM_ID_B], $startDate, $endDate);
$r1dec10 = $map2[$ROOM_ID]['2026-12-10'] ?? 0;
$r2dec10 = $map2[$ROOM_ID_B]['2026-12-10'] ?? 0;
tc('TC-09a', "Batch calendar: Room #1 Dec10 = 0 (no active blocking booking)", $r1dec10 === 0, "Got $r1dec10");
tc('TC-09b', "Batch calendar: Room #2 Dec10 = 2 (confirmed 2-room booking)", $r2dec10 === 2, "Got $r2dec10");

// ──────────────────────────────────────────────────────────────
// Cleanup
// ──────────────────────────────────────────────────────────────
$pdo->prepare("DELETE FROM hotel_bookings WHERE booking_reference LIKE 'QA-TEST-%'")->execute();

// ──────────────────────────────────────────────────────────────
// Report
// ──────────────────────────────────────────────────────────────
echo "\n";
echo "=======================================================\n";
echo " GAIA QA — Room Availability Test Suite\n";
echo "=======================================================\n";
$maxLabel = max(array_map(fn($r) => strlen($r['label']), $results));
foreach ($results as $r) {
    $pad = str_pad($r['id'], 7);
    $label = str_pad($r['label'], $maxLabel + 2);
    $mark = $r['status'] === 'PASS' ? '✓' : '✗';
    $detail = $r['detail'] ? " ({$r['detail']})" : '';
    echo "  $mark $pad $label [{$r['status']}]$detail\n";
}
echo "-------------------------------------------------------\n";
echo "  PASS: $PASS   FAIL: $FAIL   TOTAL: " . ($PASS + $FAIL) . "\n";
echo "=======================================================\n";

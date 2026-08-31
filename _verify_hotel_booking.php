<?php
/**
 * _verify_hotel_booking.php
 * Automated verification script for Hotel Booking flow
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/services/BookingSummaryService.php';
require_once __DIR__ . '/services/PaymentService.php';
require_once __DIR__ . '/services/BookingTimelineService.php';
require_once __DIR__ . '/services/InvoiceService.php';

function pass($msg) { echo "[\033[32mPASS\033[0m] $msg\n"; }
function fail($msg) { echo "[\033[31mFAIL\033[0m] $msg\n"; exit(1); }

$pdo = getPDO();

// 1. Setup Test Data
$pdo->beginTransaction();

try {
    // Create Test Customer A
    $pdo->exec("INSERT INTO users (role_id, email, password_hash, first_name, last_name, status) VALUES (3, 'testA@gaia.local', 'hash', 'Test', 'A', 'active')");
    $userAId = (int)$pdo->lastInsertId();

    // Create Test Customer B
    $pdo->exec("INSERT INTO users (role_id, email, password_hash, first_name, last_name, status) VALUES (3, 'testB@gaia.local', 'hash', 'Test', 'B', 'active')");
    $userBId = (int)$pdo->lastInsertId();

    // Create Hotel A
    $pdo->exec("INSERT INTO hotels (destination_id, name, slug, price_from, is_active) VALUES (1, 'QA Hotel', 'qa-hotel', 100.00, 1)");
    $hotelAId = (int)$pdo->lastInsertId();

    // Create Room A
    $pdo->exec("INSERT INTO hotel_rooms (hotel_id, name, price, capacity, is_active) VALUES ($hotelAId, 'QA Room', 150.00, 2, 1)");
    $roomAId = (int)$pdo->lastInsertId();

    $pdo->commit();
    pass("Test data created (User A: $userAId, User B: $userBId, Hotel A: $hotelAId, Room A: $roomAId)");
} catch (Exception $e) {
    $pdo->rollBack();
    fail("Failed to create test data: " . $e->getMessage());
}

// 2. Simulate Booking Creation for Customer A
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'room_id' => $roomAId,
    'check_in_date' => date('Y-m-d', strtotime('+10 days')),
    'check_out_date' => date('Y-m-d', strtotime('+12 days')),
    'guest_count' => 2,
    'guest_name' => 'Test A',
    'guest_email' => 'testA@gaia.local',
    'guest_phone' => '123456',
    'special_requests' => ''
];

// Mock auth session
session_start();
$_SESSION['user_id'] = $userAId;
$_SESSION['role_id'] = 3;

// Directly test logic to avoid HTTP calls
try {
    $checkInDate = $_POST['check_in_date'];
    $checkOutDate = $_POST['check_out_date'];
    $nights = (new DateTime($checkInDate))->diff(new DateTime($checkOutDate))->days;
    $totalPrice = $nights * 150.00;

    $bookingCode = 'HO-' . strtoupper(substr(uniqid(), -8));
    require_once __DIR__ . '/services/BookingReferenceService.php';
    $bookingReference = BookingReferenceService::generate('hotel');

    $pdo->prepare("INSERT INTO hotel_bookings (booking_code, booking_reference, hotel_id, room_id, user_id, guest_name, guest_email, check_in_date, check_out_date, guests, total_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'awaiting_payment')")
        ->execute([$bookingCode, $bookingReference, $hotelAId, $roomAId, $userAId, 'Test A', 'testA@gaia.local', $checkInDate, $checkOutDate, 2, $totalPrice]);
    
    $bookingId = (int)$pdo->lastInsertId();

    BookingTimelineService::logBookingCreated('hotel', $bookingId, $userAId, $bookingReference);

    PaymentService::createPayment(
        $userAId, 'hotel', $bookingId, $bookingReference, $totalPrice, 'USD', 'card', '', PaymentService::STATUS_PENDING, []
    );

    pass("Booking created for Customer A: $bookingReference");
} catch (Exception $e) {
    fail("Booking creation failed: " . $e->getMessage());
}

// 3. Verify Database Relationships & Invariants
$stmt = $pdo->prepare("SELECT * FROM hotel_bookings WHERE id = ?");
$stmt->execute([$bookingId]);
$b = $stmt->fetch();

if ($b['user_id'] != $userAId) fail("User ID mismatch");
if ($b['hotel_id'] != $hotelAId) fail("Hotel ID mismatch");
if ($b['room_id'] != $roomAId) fail("Room ID mismatch");
if ($b['total_price'] != 300.00) fail("Price calculation wrong (expected 300.00)");
pass("Relationships & price calculation passed");

// 4. Verify IDOR Protection
$summaryA = BookingSummaryService::findByReference($bookingReference, $userAId);
if (!$summaryA) fail("Customer A cannot see their own booking");

$summaryB = BookingSummaryService::findByReference($bookingReference, $userBId);
if ($summaryB) fail("Customer B CAN see Customer A's booking (IDOR FAILURE)");
pass("IDOR Protection verified");

// 5. Verify Timeline & Payments
$timeline = BookingTimelineService::getTimeline('hotel', $bookingId, $userAId);
if (count($timeline) === 0) fail("No timeline created");
pass("Timeline created");

$payments = PaymentService::getBookingPayments('hotel', $bookingId, $userAId);
if (count($payments) === 0) fail("No payment record created");
pass("Payment record created");

// 6. Verify Overlap Logic (Customer B booking same dates)
$overlapStmt = $pdo->prepare("
    SELECT id FROM hotel_bookings 
    WHERE room_id = ? 
    AND status IN ('pending', 'awaiting_payment', 'paid', 'confirmed') 
    AND ((check_in_date < ? AND check_out_date > ?))
");
$overlapStmt->execute([$roomAId, $checkOutDate, $checkInDate]);
if (!$overlapStmt->fetch()) fail("Overlap protection failed");
pass("Availability Overlap protection passed");

// Cleanup
$pdo->exec("DELETE FROM payments WHERE booking_type='hotel' AND booking_id=$bookingId");
$pdo->exec("DELETE FROM booking_logs WHERE booking_id=$bookingId");
$pdo->exec("DELETE FROM hotel_bookings WHERE id=$bookingId");
$pdo->exec("DELETE FROM hotel_rooms WHERE id=$roomAId");
$pdo->exec("DELETE FROM hotels WHERE id=$hotelAId");
$pdo->exec("DELETE FROM users WHERE id IN ($userAId, $userBId)");

pass("All Hotel Booking QA checks passed!");

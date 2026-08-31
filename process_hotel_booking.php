<?php
/**
 * process_hotel_booking.php
 * ------------------------------------------------------------
 * Receives POST data from checkout-hotel.php, checks availability,
 * calculates price, inserts into hotel_bookings, logs timeline,
 * and creates a payment record.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/components/gaia-config.php';
require_once __DIR__ . '/auth/helpers.php';
require_once __DIR__ . '/auth/Auth.php';
require_once __DIR__ . '/auth/middleware.php';
require_once __DIR__ . '/services/BookingReferenceService.php';
require_once __DIR__ . '/services/BookingTimelineService.php';
require_once __DIR__ . '/services/PaymentService.php';
require_once __DIR__ . '/services/HotelAvailabilityService.php';
require_once __DIR__ . '/services/EmailService.php';
require_once __DIR__ . '/components/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_booking_login('checkout-hotel.php');

$authUserId = (int)auth_id();
if ($authUserId <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You must be signed in to book.']);
    exit;
}

function post($key, $default = '') {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

$roomId = (int)post('room_id');
$checkInDate = post('check_in_date');
$checkOutDate = post('check_out_date');
$guestCount = (int)post('guest_count', 1);
$roomsCount = (int)post('rooms_count', 1);
$guestName = post('guest_name');
$guestEmail = post('guest_email');
$guestPhone = post('guest_phone');
$specialRequests = post('special_requests');

$errors = [];
if ($roomId <= 0) $errors[] = 'A valid room is required.';
if ($guestName === '') $errors[] = 'Guest name is required.';
if ($guestEmail === '' || !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
if ($checkInDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkInDate)) $errors[] = 'Valid check-in date is required.';
if ($checkOutDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkOutDate)) $errors[] = 'Valid check-out date is required.';
if ($checkInDate >= $checkOutDate) $errors[] = 'Check-out date must be after check-in date.';

if ($errors) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

try {
    $pdo = getPDO();
    $pdo->beginTransaction();

    try {
        // Verify room and lock it FOR UPDATE to prevent race conditions
        $stmt = $pdo->prepare('SELECT * FROM hotel_rooms WHERE id = ? AND is_active = 1 FOR UPDATE');
        $stmt->execute([$roomId]);
        $room = $stmt->fetch();

        if (!$room) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Room not found.']);
            exit;
        }

        $hotelId = (int)$room['hotel_id'];
        // Fetch hotel to get admin commission percent
        $hotelStmt = $pdo->prepare('SELECT admin_commission_percent FROM hotels WHERE id = ?');
        $hotelStmt->execute([$hotelId]);
        $hotel = $hotelStmt->fetch();
        $adminCommission = (float)($hotel['admin_commission_percent'] ?? 0);
        $gatewayFee = (float) site_setting('payment_gateway_fee_percent', '2.5');
        $basePrice = (float)$room['price'];
        $roomPrice = round($basePrice * (1 + $adminCommission/100) * (1 + $gatewayFee/100), 0);
        $roomQuantity = (int)$room['quantity'];

        if ($roomsCount < 1 || $roomsCount > $roomQuantity) {
            $pdo->rollBack();
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid number of rooms requested.']);
            exit;
        }

        // Overlap check using canonical availability service
        $booked = HotelAvailabilityService::bookedInventory($pdo, $roomId, $checkInDate, $checkOutDate);

        if (($booked + $roomsCount) > $roomQuantity) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'The selected dates do not have enough rooms available.']);
            exit;
        }

        // Pricing
        $d1 = new DateTime($checkInDate);
        $d2 = new DateTime($checkOutDate);
        $nights = $d1->diff($d2)->days;
        if ($nights <= 0) {
            $pdo->rollBack();
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid stay duration.']);
            exit;
        }

        $totalPrice = round($nights * $roomPrice * $roomsCount, 2);

        $bookingCode = 'HO-' . strtoupper(substr(uniqid(), -8));
        $bookingReference = BookingReferenceService::generate('hotel');
        $insertStmt = $pdo->prepare("
            INSERT INTO hotel_bookings 
            (booking_code, booking_reference, hotel_id, room_id, user_id, 
             guest_name, guest_email, guest_phone, check_in_date, check_out_date, 
             guests, rooms_count, total_price, admin_commission_percent, status) 
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'awaiting_payment')
        ");
        
        $insertStmt->execute([
            $bookingCode,
            $bookingReference,
            $hotelId,
            $roomId,
            $authUserId,
            $guestName,
            $guestEmail,
            $guestPhone,
            $checkInDate,
            $checkOutDate,
            $guestCount,
            $roomsCount,
            $totalPrice,
            $adminCommission
        ]);

        $bookingId = (int)$pdo->lastInsertId();

        if ($specialRequests !== '') {
            BookingTimelineService::log('hotel', $bookingId, $authUserId, 'Special Requests', substr($specialRequests, 0, 255));
        }


        BookingTimelineService::logBookingCreated('hotel', $bookingId, $authUserId, $bookingReference);

        PaymentService::createPayment(
            $authUserId,
            'hotel',
            $bookingId,
            $bookingReference,
            $totalPrice,
            defined('CURRENCY') ? CURRENCY : 'USD',
            'card',
            '',
            PaymentService::STATUS_PENDING,
            []
        );

        $pdo->commit();

        // Send booking confirmation email (best-effort, outside transaction)
        try {
            $bookingRow = [
                'booking_reference' => $bookingReference,
                'guest_email'       => $guestEmail,
                'guest_name'        => $guestName,
                'check_in_date'     => $checkInDate,
                'check_out_date'    => $checkOutDate,
                'rooms_count'       => $roomsCount,
                'guests'            => $guestCount,
                'total_price'       => $totalPrice,
            ];
            EmailService::sendBookingConfirmation(
                $bookingRow,
                $room,
                $hotel,
                gaia_current_lang()
            );
        } catch (\Throwable $emailEx) {
            error_log('[EmailService] Booking confirmation failed: ' . $emailEx->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'Booking created successfully!',
            'booking_reference' => $bookingReference,
            'redirect' => gaia_url('account/booking.php?reference=' . $bookingReference)
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Hotel Booking insert failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save booking.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}

<?php
/**
 * process_booking.php — WeRoad booking handler
 * ------------------------------------------------------------
 * Receives POST data from the checkout form, calculates the
 * final price, and uses PDO transactions to insert the booking
 * and any extras into MySQL.
 *
 * Pricing formula:
 *   Departure price + Flexible cancellation + Private room - Promo discount
 *
 * Returns a JSON confirmation.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../components/gaia-config.php';
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../auth/Auth.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../services/BookingReferenceService.php';
require_once __DIR__ . '/../services/BookingTimelineService.php';
require_once __DIR__ . '/../services/PaymentService.php';
require_once __DIR__ . '/../services/BookingService.php';

header('Content-Type: application/json; charset=utf-8');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

// ------------------------------------------------------------
// SECURITY: Anonymous bookings are no longer allowed.
// The user MUST be authenticated before a booking can be created.
// If not, redirect to the login page and return afterwards.
// ------------------------------------------------------------
require_booking_login('../weroad/booking.php');

$authUserId = (int)auth_id();
if ($authUserId <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You must be signed in to book.']);
    exit;
}

// --- Helper to read a POST field safely ---
function wrPost($key, $default = '') {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

// --- Read all form fields ---
$tripId       = (int)wrPost('trip_id');
$departureId  = (int)wrPost('departure_id');
$fullName     = wrPost('full_name');
$email        = wrPost('email');
$phone        = wrPost('phone');
$female       = (int)wrPost('travellers_female', 0);
$male         = (int)wrPost('travellers_male', 0);
$mixedRoom    = wrPost('mixed_room') === '1' ? 1 : 0;
$flexible     = wrPost('flexible_cancellation') === '1' ? 1 : 0;
$privateRoom  = wrPost('private_room') === '1' ? 1 : 0;
$promoCode    = strtoupper(wrPost('promo_code'));

// --- Basic validation ---
$errors = [];
$travellerCount = $female + $male;
if ($tripId <= 0)          $errors[] = 'A valid trip is required.';
if ($departureId <= 0)     $errors[] = 'A valid departure is required.';
if ($fullName === '')      $errors[] = 'Full name is required.';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
if ($travellerCount < 1)   $errors[] = 'Select at least one traveller.';

if ($errors) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

try {
    $pdo = getWeRoadPDO();

    // --- Load trip ---
    $tripStmt = $pdo->prepare('SELECT * FROM weroad_trips WHERE id = :id AND is_active = 1');
    $tripStmt->execute([':id' => $tripId]);
    $trip = $tripStmt->fetch();

    // --- Load departure ---
    $depStmt = $pdo->prepare('SELECT * FROM weroad_departures WHERE id = :id AND trip_id = :trip_id AND is_active = 1');
    $depStmt->execute([':id' => $departureId, ':trip_id' => $tripId]);
    $departure = $depStmt->fetch();

    if (!$trip || !$departure) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Trip or departure not found.']);
        exit;
    }

    // --- Load promo code (if provided) ---
    $promo = null;
    $promoId = null;
    if ($promoCode !== '') {
        $promoStmt = $pdo->prepare('SELECT * FROM weroad_promo_codes WHERE code = :code LIMIT 1');
        $promoStmt->execute([':code' => $promoCode]);
        $promo = $promoStmt->fetch();

        if ($promo) {
            $today = date('Y-m-d');
            $valid = (int)$promo['is_active'] === 1
                  && (int)$promo['used_count'] < (int)$promo['max_uses']
                  && ($promo['valid_from'] === null || $today >= $promo['valid_from'])
                  && ($promo['valid_until'] === null || $today <= $promo['valid_until']);
            if ($valid) {
                $promoId = (int)$promo['id'];
            } else {
                echo json_encode(['success' => false, 'message' => 'Promo code is invalid or expired.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Promo code not found.']);
            exit;
        }
    }

    // ============================================================
    // PRICE CALCULATION
    // ============================================================
    $basePrice = (float)$departure['price'];

    // Extras (from tour_extras DB with config fallback)
    $flexFee = $flexible ? tour_extra_price('flexible_cancellation', defined('WR_PRICE_FLEXIBLE_CANCELLATION') ? WR_PRICE_FLEXIBLE_CANCELLATION : 99.00) : 0.00;
    $roomFee = $privateRoom ? tour_extra_price('private_room', defined('WR_PRICE_PRIVATE_ROOM') ? WR_PRICE_PRIVATE_ROOM : 247.00) : 0.00;
    $extrasTotal = $flexFee + $roomFee;

    // Promo discount
    $discount = 0.00;
    if ($promo) {
        $subtotal = $basePrice + $extrasTotal;
        if ($promo['discount_type'] === 'percent') {
            $discount = round($subtotal * (float)$promo['discount_value'] / 100, 2);
        } else { // flat
            $discount = round((float)$promo['discount_value'], 2);
        }
        $discount = min($discount, $subtotal);
    }

    $totalPrice = round($basePrice + $extrasTotal - $discount, 2);

// Booking code
    $bookingCode = WR_BOOKING_CODE_PREFIX . strtoupper(substr(uniqid(), -8));

    // Generate a unique GAIA booking reference (GAIA-TO-XXXXXX)
    $bookingReference = BookingReferenceService::generate('tour');

    // ============================================================
    // DATABASE TRANSACTION
    // ============================================================
    $pdo->beginTransaction();

    try {
// Authenticated user (0 = guest). Guest bookings keep user_id NULL.
        $userId = $authUserId > 0 ? $authUserId : null;

        // Insert the booking
        $insertBooking = $pdo->prepare(
            "INSERT INTO weroad_bookings
               (booking_code, booking_reference, trip_id, departure_id, user_id, promo_code_id,
                full_name, email, phone,
                guest_name, guest_email, guest_phone,
                travellers_female, travellers_male, mixed_room,
                flexible_cancellation, private_room,
                base_price, flexible_fee, private_room_fee, discount, total_price,
                status)
             VALUES
               (:booking_code, :booking_reference, :trip_id, :departure_id, :user_id, :promo_code_id,
                :full_name, :email, :phone,
                :guest_name, :guest_email, :guest_phone,
                :travellers_female, :travellers_male, :mixed_room,
                :flexible_cancellation, :private_room,
:base_price, :flexible_fee, :private_room_fee, :discount, :total_price,
                'awaiting_payment')"
        );

        $insertBooking->execute([
            ':booking_code'         => $bookingCode,
            ':booking_reference'    => $bookingReference,
            ':trip_id'              => $tripId,
            ':departure_id'         => $departureId,
            ':user_id'              => $userId,
            ':promo_code_id'        => $promoId,
            ':full_name'            => $fullName,
            ':email'                => $email,
            ':phone'                => $phone !== '' ? $phone : null,
            ':guest_name'           => $fullName,
            ':guest_email'          => $email,
            ':guest_phone'          => $phone !== '' ? $phone : null,
            ':travellers_female'    => $female,
            ':travellers_male'      => $male,
            ':mixed_room'           => $mixedRoom,
            ':flexible_cancellation'=> $flexible,
            ':private_room'         => $privateRoom,
            ':base_price'           => $basePrice,
            ':flexible_fee'         => $flexFee,
            ':private_room_fee'     => $roomFee,
            ':discount'             => $discount,
            ':total_price'          => $totalPrice,
        ]);

$bookingId = (int)$pdo->lastInsertId();

        // Log the "Booking Created" timeline event.
        BookingTimelineService::logBookingCreated('tour', $bookingId, $authUserId, $bookingReference);

        // Create the unified payment record (pending) linked to this booking.
        // Gateway-independent readiness layer — no CyberSource code yet.
        PaymentService::createPayment(
            $authUserId,
            'tour',
            $bookingId,
            $bookingReference,
            (float)$totalPrice,
            defined('WR_CURRENCY_SYMBOL') ? WR_CURRENCY_SYMBOL : 'EUR',
            'card',        // payment method placeholder (future)
            '',            // gateway name — left empty until CyberSource integration
            PaymentService::STATUS_PENDING,
            []             // gateway response — none yet
        );

        // Insert extras line items
        $extras = [];
        if ($flexible)  $extras[] = ['Flexible cancellation', 1, tour_extra_price('flexible_cancellation', defined('WR_PRICE_FLEXIBLE_CANCELLATION') ? WR_PRICE_FLEXIBLE_CANCELLATION : 99.00), $flexFee];
        if ($privateRoom) $extras[] = ['Private room', 1, tour_extra_price('private_room', defined('WR_PRICE_PRIVATE_ROOM') ? WR_PRICE_PRIVATE_ROOM : 247.00), $roomFee];

        if (!empty($extras)) {
            $insertExtra = $pdo->prepare(
                "INSERT INTO weroad_booking_extras (booking_id, extra_name, quantity, unit_price, total_price)
                 VALUES (:booking_id, :extra_name, :quantity, :unit_price, :total_price)"
            );
            foreach ($extras as $extra) {
                $insertExtra->execute([
                    ':booking_id'  => $bookingId,
                    ':extra_name'  => $extra[0],
                    ':quantity'    => $extra[1],
                    ':unit_price'  => $extra[2],
                    ':total_price' => $extra[3],
                ]);
            }
        }

        // Increment promo usage
        if ($promoId) {
            $pdo->prepare('UPDATE weroad_promo_codes SET used_count = used_count + 1 WHERE id = :id')
                ->execute([':id' => $promoId]);
        }

        // Decrement departure spots_left (if available)
        if ($departure['spots_left'] > 0) {
            $pdo->prepare('UPDATE weroad_departures SET spots_left = spots_left - 1 WHERE id = :id')
                ->execute([':id' => $departureId]);
        }

        $pdo->commit();

echo json_encode([
            'success'          => true,
            'message'          => 'Booking confirmed successfully!',
            'booking_code'     => $bookingCode,
            'booking_reference'=> $bookingReference,
            'booking_id'       => $bookingId,
            'price_breakdown'  => [
                'base_price'       => $basePrice,
                'flexible_fee'     => $flexFee,
                'private_room_fee' => $roomFee,
                'discount'         => $discount,
                'total_price'      => $totalPrice,
            ],
        ]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('WeRoad booking insert failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save the booking. Please try again.',
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
    ]);
}

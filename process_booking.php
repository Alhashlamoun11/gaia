<?php
/**
 * process_booking.php
 * ------------------------------------------------------------
 * Receives POST data from the checkout form, calculates the
 * final price, and uses PDO transactions to insert the booking
 * and its selected extras into MySQL.
 *
 * Pricing formula:
 *   Base price * Car multiplier + Return fee + Extras - Discount
 *
 * Accepts POST:
 *   route_id, car_class_id, origin, destination, pickup_date, pickup_time,
 *   passenger_count, flight_number, arrival_time, destination_address,
 *   return_trip, full_name, email, phone,
 *   child_seats, water_bottles, pet_carrier, women_driver, comments,
 *   promo_code
 *
 * Returns JSON confirmation.
 * ------------------------------------------------------------
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
require_once __DIR__ . '/services/BookingService.php';

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
require_booking_login('checkout.php');

$authUserId = (int)auth_id();
if ($authUserId <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You must be signed in to book.']);
    exit;
}

// --- Helper to read a POST field safely ---
function post($key, $default = '') {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

// --- Read all form fields ---
$routeId         = (int)post('route_id');
$carClassId      = (int)post('car_class_id');
$origin          = post('origin');
$destination     = post('destination');
$pickupDate      = post('pickup_date');
$pickupTime      = post('pickup_time');
$passengerCount  = (int)post('passenger_count', 1);
$flightNumber    = post('flight_number');
$arrivalTime     = post('arrival_time');
$destAddress     = post('destination_address');
$returnTrip      = post('return_trip') === '1' || post('return_trip') === 'on' ? 1 : 0;
$fullName        = post('full_name');
$email           = post('email');
$phone           = post('phone');
$childSeats      = (int)post('child_seats', 0);
$waterBottles    = (int)post('water_bottles', 0);
$petCarrier      = post('pet_carrier') === '1' || post('pet_carrier') === 'on' ? 1 : 0;
$womenDriver     = post('women_driver') === '1' || post('women_driver') === 'on' ? 1 : 0;
$comments        = post('comments');
$promoCode       = strtoupper(post('promo_code'));

// --- Basic validation ---
$errors = [];
if ($routeId <= 0)        $errors[] = 'A valid route is required.';
if ($carClassId <= 0)     $errors[] = 'A valid car class is required.';
if ($fullName === '')     $errors[] = 'Full name is required.';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
if ($phone === '')        $errors[] = 'Phone number is required.';
if ($pickupDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $pickupDate)) $errors[] = 'A valid pickup date is required.';
if ($passengerCount < 1)  $errors[] = 'Passenger count must be at least 1.';

if ($errors) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

try {
    $pdo = getPDO();

    // --- Load route and car class rows ---
    $routeStmt = $pdo->prepare('SELECT * FROM routes WHERE id = :id AND is_active = 1');
    $routeStmt->execute([':id' => $routeId]);
    $route = $routeStmt->fetch();

    $carStmt = $pdo->prepare('SELECT * FROM car_classes WHERE id = :id AND is_active = 1');
    $carStmt->execute([':id' => $carClassId]);
    $carClass = $carStmt->fetch();

    if (!$route || !$carClass) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Route or car class not found.']);
        exit;
    }

    // --- Load promo code (if provided) ---
    $promo = null;
    $promoId = null;
    if ($promoCode !== '') {
        $promoStmt = $pdo->prepare('SELECT * FROM promo_codes WHERE code = :code LIMIT 1');
        $promoStmt->execute([':code' => $promoCode]);
        $promo = $promoStmt->fetch();

        if ($promo) {
            // Validate active status, date range, and usage limit
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
    $routeBasePrice = (float)$route['base_price'];
    $multiplier     = (float)$carClass['price_multiplier'];

    // 1. Base fare = route base price * car class multiplier
    $basePrice = round($routeBasePrice * $multiplier, 2);

    // 2. Return trip fee (only if return trip is selected) — from transfer_extras DB
    $returnFee = $returnTrip ? transfer_extra_price('return_trip_fee', defined('RETURN_TRIP_FEE') ? RETURN_TRIP_FEE : 50.00) : 0.00;

    // 3. Extras (from transfer_extras DB with config fallback)
    $extrasTotal = 0.00;
    $extras = []; // accumulates line items for booking_extras

    $childSeatPrice = transfer_extra_price('child_seat', defined('PRICE_CHILD_SEAT') ? PRICE_CHILD_SEAT : 10.00);
    $waterPrice     = transfer_extra_price('water_bottle', defined('PRICE_WATER_BOTTLE') ? PRICE_WATER_BOTTLE : 3.00);
    $petPrice       = transfer_extra_price('pet_carrier', defined('PRICE_PET_CARRIER') ? PRICE_PET_CARRIER : 15.00);
    $womenPrice     = transfer_extra_price('women_driver', defined('PRICE_WOMEN_DRIVER') ? PRICE_WOMEN_DRIVER : 20.00);

    if ($childSeats > 0) {
        $lineTotal = round($childSeats * $childSeatPrice, 2);
        $extrasTotal += $lineTotal;
        $extras[] = ['Child seats', $childSeats, $childSeatPrice, $lineTotal];
    }
    if ($waterBottles > 0) {
        $lineTotal = round($waterBottles * $waterPrice, 2);
        $extrasTotal += $lineTotal;
        $extras[] = ['Drinking water', $waterBottles, $waterPrice, $lineTotal];
    }
    if ($petCarrier) {
        $extrasTotal += $petPrice;
        $extras[] = ['Pet carrier', 1, $petPrice, $petPrice];
    }
    if ($womenDriver) {
        $extrasTotal += $womenPrice;
        $extras[] = ['Women driver preference', 1, $womenPrice, $womenPrice];
    }

    // 4. Discount
    $discount = 0.00;
    if ($promo) {
        $subtotalBeforeDiscount = $basePrice + $returnFee + $extrasTotal;
        if ($promo['discount_type'] === 'percent') {
            $discount = round($subtotalBeforeDiscount * (float)$promo['discount_value'] / 100, 2);
        } else { // flat
            $discount = round((float)$promo['discount_value'], 2);
        }
        // Discount cannot exceed the subtotal
        $discount = min($discount, $subtotalBeforeDiscount);
    }

    // 5. Final total
    $totalPrice = round($basePrice + $returnFee + $extrasTotal - $discount, 2);

// Generate a unique booking code
    $bookingCode = BOOKING_CODE_PREFIX . strtoupper(substr(uniqid(), -8));

    // Generate a unique GAIA booking reference (GAIA-TR-XXXXXX)
    $bookingReference = BookingReferenceService::generate('transfer');

    // ============================================================
    // DATABASE TRANSACTION
    // ============================================================
    $pdo->beginTransaction();

    try {
// Authenticated user (0 = guest). Guest bookings keep user_id NULL.
        $userId = $authUserId > 0 ? $authUserId : null;

        // Insert the booking
        $insertBooking = $pdo->prepare(
            "INSERT INTO bookings
               (booking_code, booking_reference, route_id, car_class_id, user_id, promo_code_id,
                origin, destination, pickup_date, pickup_time, passenger_count,
                flight_number, arrival_time, destination_address, return_trip,
                full_name, email, phone,
                guest_name, guest_email, guest_phone,
                child_seats, water_bottles, pet_carrier, women_driver, comments,
                base_price, price_multiplier, return_fee, extras_total, discount, total_price,
                status)
             VALUES
               (:booking_code, :booking_reference, :route_id, :car_class_id, :user_id, :promo_code_id,
                :origin, :destination, :pickup_date, :pickup_time, :passenger_count,
                :flight_number, :arrival_time, :destination_address, :return_trip,
                :full_name, :email, :phone,
                :guest_name, :guest_email, :guest_phone,
:child_seats, :water_bottles, :pet_carrier, :women_driver, :comments,
                :base_price, :price_multiplier, :return_fee, :extras_total, :discount, :total_price,
                'awaiting_payment')"
        );

        $insertBooking->execute([
            ':booking_code'        => $bookingCode,
            ':booking_reference'   => $bookingReference,
            ':route_id'            => $routeId,
            ':car_class_id'        => $carClassId,
            ':user_id'             => $userId,
            ':promo_code_id'       => $promoId,
            ':origin'              => $origin !== '' ? $origin : $route['origin'],
            ':destination'         => $destination !== '' ? $destination : $route['destination'],
            ':pickup_date'         => $pickupDate,
            ':pickup_time'         => $pickupTime !== '' ? $pickupTime : null,
            ':passenger_count'     => $passengerCount,
            ':flight_number'       => $flightNumber !== '' ? $flightNumber : null,
            ':arrival_time'        => $arrivalTime !== '' ? $arrivalTime : null,
            ':destination_address' => $destAddress !== '' ? $destAddress : null,
            ':return_trip'         => $returnTrip,
            ':full_name'           => $fullName,
            ':email'               => $email,
            ':phone'               => $phone,
            ':guest_name'          => $fullName,
            ':guest_email'         => $email,
            ':guest_phone'         => $phone,
            ':child_seats'         => $childSeats,
            ':water_bottles'       => $waterBottles,
            ':pet_carrier'         => $petCarrier,
            ':women_driver'        => $womenDriver,
            ':comments'            => $comments !== '' ? $comments : null,
            ':base_price'          => $basePrice,
            ':price_multiplier'    => $multiplier,
            ':return_fee'          => $returnFee,
            ':extras_total'        => $extrasTotal,
            ':discount'            => $discount,
            ':total_price'         => $totalPrice,
        ]);

$bookingId = (int)$pdo->lastInsertId();

        // Log the "Booking Created" timeline event.
        BookingTimelineService::logBookingCreated('transfer', $bookingId, $authUserId, $bookingReference);

        // Create the unified payment record (pending) linked to this booking.
        // This is the gateway-independent "payment readiness" layer — no
        // CyberSource code. A future gateway driver plugs in via GatewayManager.
        PaymentService::createPayment(
            $authUserId,
            'transfer',
            $bookingId,
            $bookingReference,
            (float)$totalPrice,
            defined('CURRENCY') ? CURRENCY : 'USD',
            'card',        // payment method placeholder (future)
            '',            // gateway name — left empty until CyberSource integration
            PaymentService::STATUS_PENDING,
            []             // gateway response — none yet
        );

        // Insert each extra line item
        $insertExtra = $pdo->prepare(
            "INSERT INTO booking_extras (booking_id, extra_name, quantity, unit_price, total_price)
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

        // Increment promo code usage count (if a promo was used)
        if ($promoId) {
            $pdo->prepare('UPDATE promo_codes SET used_count = used_count + 1 WHERE id = :id')
                ->execute([':id' => $promoId]);
        }

        // Commit the transaction
        $pdo->commit();

        // Return JSON confirmation
echo json_encode([
            'success'          => true,
            'message'          => 'Booking confirmed successfully!',
            'booking_code'     => $bookingCode,
            'booking_reference'=> $bookingReference,
            'booking_id'       => $bookingId,
            'price_breakdown'  => [
                'base_price'       => $basePrice,
                'price_multiplier' => $multiplier,
                'return_fee'       => $returnFee,
                'extras_total'     => $extrasTotal,
                'discount'         => $discount,
                'total_price'      => $totalPrice,
            ],
        ]);

    } catch (PDOException $e) {
        // Roll back any partial inserts on error
        $pdo->rollBack();
        error_log('Booking insert failed: ' . $e->getMessage());
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

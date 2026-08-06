<?php
/**
 * _verify_ownership.php
 * ------------------------------------------------------------
 * Verification script for the booking ownership system.
 * Confirms:
 *   1. Every booking table has user_id / guest_* columns
 *   2. Foreign keys (ON DELETE SET NULL) exist for user_id
 *   3. payments table is ready (user_id, booking_type, booking_id)
 *   4. BookingRepository methods exist and are scoped to user_id
 *   5. IDOR protection: cross-user lookup returns null
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth/BookingRepository.php';

$pdo = getPDO();
$pass = true;

function ok($msg) { echo "[PASS] $msg\n"; }
function fail($msg) { echo "[FAIL] $msg\n"; $GLOBALS['pass'] = false; }

echo "== 1. Booking table ownership columns ==\n";
$tables  = ['bookings', 'weroad_bookings', 'taxi_bookings', 'hotel_bookings', 'event_bookings'];
$columns = ['user_id', 'guest_name', 'guest_email', 'guest_phone'];
foreach ($tables as $t) {
    $stmt = $pdo->prepare(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME IN ('user_id','guest_name','guest_email','guest_phone')"
    );
    $stmt->execute([$t]);
    $found = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $missing = array_diff($columns, $found);
    if (empty($missing)) {
        ok("$t has user_id + guest_* (" . implode(',', $found) . ")");
    } else {
        fail("$t missing: " . implode(',', $missing));
    }
}

echo "\n== 2. Foreign keys (ON DELETE SET NULL) ==\n";
$fks = [
    'bookings'         => 'fk_booking_user',
    'weroad_bookings'  => 'fk_wb_user',
    'taxi_bookings'    => 'fk_tb_user',
    'hotel_bookings'   => 'fk_hb_user',
    'event_bookings'   => 'fk_eb_user',
];
foreach ($fks as $t => $fk) {
    $stmt = $pdo->prepare(
        "SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?"
    );
    $stmt->execute([$t, $fk]);
    $rule = $stmt->fetchColumn();
    if ($rule === 'SET NULL') {
        ok("$t.$fk -> ON DELETE SET NULL");
    } else {
        fail("$t.$fk rule=" . var_export($rule, true) . " (expected SET NULL)");
    }
}

echo "\n== 3. payments readiness ==\n";
$stmt = $pdo->prepare(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments'
     AND COLUMN_NAME IN ('user_id','booking_type','booking_id')"
);
$stmt->execute();
$payCols = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (count(array_intersect(['user_id','booking_type','booking_id'], $payCols)) === 3) {
    ok("payments has user_id, booking_type, booking_id");
} else {
    fail("payments missing columns: " . implode(',', $payCols));
}

echo "\n== 4. BookingRepository helper methods ==\n";
$methods = ['getUserBookings','getUserTourBookings','getUserHotelBookings','getUserTransferBookings','getUserEventBookings','getBookingDetails','countUserBookings','claimBookingsByEmail'];
foreach ($methods as $m) {
    if (method_exists('BookingRepository', $m)) {
        ok("BookingRepository::$m exists");
    } else {
        fail("BookingRepository::$m MISSING");
    }
}

echo "\n== 5. IDOR protection (ownership scoping) ==\n";
// Insert two test users + one booking owned by user A, then confirm user B cannot read it.
// NOTE: BookingRepository opens its own PDO connection, so the test data is
// committed before the ownership checks (matches production behaviour where
// each request reads committed rows).
$emailA = 'idor_a_' . uniqid() . '@test.local';
$emailB = 'idor_b_' . uniqid() . '@test.local';
$uidA = 0; $uidB = 0; $bookingId = 0;
try {
    $pdo->prepare("INSERT INTO users (first_name,last_name,email,password_hash,role_id,status) VALUES ('A','A',?,?,3,'active')")->execute([$emailA, password_hash('x', PASSWORD_BCRYPT)]);
    $uidA = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO users (first_name,last_name,email,password_hash,role_id,status) VALUES ('B','B',?,?,3,'active')")->execute([$emailB, password_hash('x', PASSWORD_BCRYPT)]);
    $uidB = (int)$pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO bookings (booking_code, user_id, origin, destination, pickup_date, full_name, email, phone, guest_name, guest_email, guest_phone, total_price, status)
         VALUES ('IDOR-TEST', ?, 'A', 'B', '2030-01-01', 'User A', ?, '000', 'User A', ?, '000', 100, 'pending')"
    )->execute([$uidA, $emailA, $emailA]);
    $bookingId = (int)$pdo->lastInsertId();

    // Owner reads: should return the booking.
    $owner = BookingRepository::getBookingDetails($uidA, 'transfer', $bookingId);
    if ($owner) {
        ok("Owner (User A) can read booking #$bookingId");
    } else {
        fail("Owner should be able to read own booking");
    }

    // Other user reads: must be null (403).
    $intruder = BookingRepository::getBookingDetails($uidB, 'transfer', $bookingId);
    if ($intruder === null) {
        ok("User B cannot read User A's booking (IDOR blocked)");
    } else {
        fail("IDOR VULNERABILITY: User B read User A's booking!");
    }

    // Claim by email: user B claims with A's email. The A booking has user_id set
    // (not NULL) so it must NOT be re-claimed.
    $claimed = BookingRepository::claimBookingsByEmail($uidB, $emailA);
    if ($claimed === 0) {
        ok("claimBookingsByEmail did not steal owned booking (claimed=$claimed)");
    } else {
        fail("claimBookingsByEmail wrongly claimed owned booking (claimed=$claimed)");
    }
} catch (Throwable $e) {
    fail("Exception during IDOR test: " . $e->getMessage());
} finally {
    // Clean up committed test data.
    try {
        if ($bookingId > 0) $pdo->prepare("DELETE FROM bookings WHERE id = ?")->execute([$bookingId]);
        if ($uidA > 0) $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uidA]);
        if ($uidB > 0) $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uidB]);
    } catch (Throwable $e) {
        fail("Cleanup error (non-fatal): " . $e->getMessage());
    }
}

echo "\n" . ($pass ? "ALL CHECKS PASSED\n" : "SOME CHECKS FAILED\n");
exit($pass ? 0 : 1);

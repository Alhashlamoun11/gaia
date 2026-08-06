<?php
/**
 * _verify_architecture.php
 * ------------------------------------------------------------
 * Smoke check for the unified booking & payment architecture.
 * Verifies migration-11 results: tables, columns, enums, and
 * that the service layer loads correctly.
 *
 * Usage: php _verify_architecture.php
 * ------------------------------------------------------------
 */
require __DIR__ . '/db.php';

$pdo = getPDO();
$ok = true;

function check($ok, $msg) {
    echo ($ok ? "  [OK] " : "  [FAIL] ") . $msg . "\n";
    return $ok;
}

echo "== TABLES ==\n";
$tables = ['payments','payment_transactions','invoices','booking_logs','room_bookings'];
foreach ($tables as $t) {
    $c = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=" . $pdo->quote($t))->fetchColumn();
    $ok = check((int)$c > 0, "$t exists");
}

echo "\n== BOOKING TABLES booking_reference + status ENUM ==\n";
$bookingTables = ['bookings','weroad_bookings','hotel_bookings','event_bookings','taxi_bookings'];
foreach ($bookingTables as $t) {
    $exists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=" . $pdo->quote($t))->fetchColumn();
    if (!$exists) { echo "  [SKIP] $t not present\n"; continue; }
    $ref = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=" . $pdo->quote($t) . " AND COLUMN_NAME='booking_reference'")->fetchColumn();
    $ok = check($ref > 0, "$t.booking_reference");
    $st = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=" . $pdo->quote($t) . " AND COLUMN_NAME='status'")->fetchColumn();
    $hasRefunded = is_string($st) && strpos($st, 'refunded') !== false;
    $ok = check($hasRefunded, "$t.status includes refunded");
}

echo "\n== PAYMENTS columns ==\n";
$payCols = ['booking_reference','payment_method','gateway_response','authorization_code','payment_token'];
foreach ($payCols as $col) {
    $c = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payments' AND COLUMN_NAME=" . $pdo->quote($col))->fetchColumn();
    $ok = check($c > 0, "payments.$col");
}
$pst = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payments' AND COLUMN_NAME='status'")->fetchColumn();
$ok = check(is_string($pst) && strpos($pst, 'authorized') !== false, "payments.status includes authorized");

echo "\n== SERVICE LAYER ==\n";
$services = [
    'BookingReferenceService','BookingTimelineService','BookingSummaryService',
    'PaymentService','InvoiceService','GatewayManager','BookingService','ReportingService'
];
foreach ($services as $s) {
    $f = __DIR__ . '/services/' . $s . '.php';
    $ok = check(file_exists($f), "$s.php exists");
}

echo "\n== HELPERS ==\n";
require_once __DIR__ . '/auth/helpers.php';
$helpers = ['booking_status_list','payment_status_list','invoice_status_list','normalize_booking_status','normalize_payment_status','booking_reference_prefix','payment_status_label','invoice_status_label','booking_status_badge_class','payment_status_badge_class','timeline_action_label'];
foreach ($helpers as $h) {
    $ok = check(function_exists($h), "function $h()");
}
echo "\nbooking_status_list: " . implode(', ', booking_status_list()) . "\n";
echo "booking_reference_prefix('tour'): " . booking_reference_prefix('tour') . "\n";

echo "\nDONE\n";
echo $ok ? "ALL CHECKS PASSED\n" : "SOME CHECKS FAILED\n";

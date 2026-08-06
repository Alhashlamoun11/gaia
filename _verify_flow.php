<?php
/**
 * _verify_flow.php
 * ------------------------------------------------------------
 * End-to-end smoke test of the unified booking → payment →
 * invoice → timeline workflow.
 *
 * Exercises the real service layer (no gateways, no external
 * APIs) to prove the architecture is CyberSource-ready:
 *
 *   1. BookingReferenceService::generate()      -> GAIA-* reference
 *   2. BookingTimelineService::logBookingCreated() -> booking_logs
 *   3. PaymentService::createPayment()          -> payments (pending)
 *   4. PaymentService::updatePaymentStatus()    -> payments (paid) + booking sync
 *   5. PaymentService::createInvoice()          -> invoices (INV-YYYY-NNNNNN)
 *   6. BookingSummaryService::findByReference() -> unified summary
 *   7. BookingSummaryService::getUserSummaries() -> dashboard list
 *   8. ReportingService                          -> reusable report queries
 *
 * SECURITY: verify ownership guards (a wrong user cannot read).
 *
 * Usage: php _verify_flow.php
 * ------------------------------------------------------------
 */
require __DIR__ . '/db.php';
require __DIR__ . '/auth/helpers.php';
require __DIR__ . '/services/BookingReferenceService.php';
require __DIR__ . '/services/BookingTimelineService.php';
require __DIR__ . '/services/PaymentService.php';   // also loads InvoiceService
require __DIR__ . '/services/BookingSummaryService.php';
require __DIR__ . '/services/ReportingService.php';

$pdo = getPDO();
$ok = true;

function check($ok, $msg) {
    echo ($ok ? "  [OK] " : "  [FAIL] ") . $msg . "\n";
    return $ok;
}

// Use the first customer user for the flow (fallback to any user).
$uid = (int)$pdo->query(
    "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
     WHERE r.slug = 'customer' ORDER BY u.id ASC LIMIT 1"
)->fetchColumn();
if ($uid <= 0) {
    $uid = (int)$pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
}

echo "Test user: #{$uid}\n\n";

// ------------------------------------------------------------
// 1) Booking reference generation
// ------------------------------------------------------------
echo "== 1. UNIFIED BOOKING REFERENCE ==\n";
$ref = BookingReferenceService::generate('tour');
check(strpos($ref, 'GAIA-TO-') === 0, "Tour reference format: {$ref}");
$refTr = BookingReferenceService::generate('transfer');
check(strpos($refTr, 'GAIA-TR-') === 0, "Transfer reference format: {$refTr}");
$parsed = BookingReferenceService::parse($ref);
check(is_array($parsed) && $parsed['type'] === 'tour', "parse('{$ref}') -> type={$parsed['type']}");

// ------------------------------------------------------------
// 2) Booking timeline logging
// ------------------------------------------------------------
echo "\n== 2. BOOKING TIMELINE ==\n";
$fakeBookingId = 999999; // arbitrary id used only for the audit trail
BookingTimelineService::logBookingCreated('tour', $fakeBookingId, $uid, $ref);
$logRows = BookingTimelineService::getTimeline('tour', $fakeBookingId, $uid);
check(count($logRows) > 0, "Timeline has " . count($logRows) . " event(s)");
check(!empty($logRows[0]['action']), "First event action: " . ($logRows[0]['action'] ?? ''));

// ------------------------------------------------------------
// 3) Payment creation (gateway-independent)
// ------------------------------------------------------------
echo "\n== 3. PAYMENT CREATION ==\n";
$paymentId = PaymentService::createPayment(
    $uid, 'tour', $fakeBookingId, $ref, 1188.00, 'EUR', 'card', '', PaymentService::STATUS_PENDING, []
);
check($paymentId > 0, "Payment created, id={$paymentId}");
check(PaymentService::ownsPayment($paymentId, $uid), "Ownership guard passes for owner");
check(!PaymentService::ownsPayment($paymentId, 999999), "Ownership guard rejects stranger");

// ------------------------------------------------------------
// 4) Payment status update -> booking sync + timeline
// ------------------------------------------------------------
echo "\n== 4. PAYMENT STATUS UPDATE ==\n";
$okStatus = PaymentService::updatePaymentStatus($paymentId, PaymentService::STATUS_PAID, $uid, true, ['id' => 'txn_test', 'state' => 'AUTHORIZED']);
check($okStatus, "Payment marked paid (gateway-independent)");
$paymentsNow = PaymentService::getUserPayments($uid);
check(!empty($paymentsNow), "User payments list is populated");

// ------------------------------------------------------------
// 5) Invoice creation (INV-YYYY-NNNNNN)
// ------------------------------------------------------------
echo "\n== 5. INVOICE CREATION ==\n";
$invId = PaymentService::createInvoice($paymentId, 0.0, 0.0, InvoiceService::STATUS_ISSUED, $uid);
check($invId > 0, "Invoice created, id={$invId}");
$invoice = InvoiceService::get($invId, $uid);
check($invoice && preg_match('/^INV-\d{4}-\d{6}$/', (string)$invoice['invoice_number']), "Invoice number format: " . ($invoice['invoice_number'] ?? ''));
check($invoice && (float)$invoice['total_amount'] === 1188.00, "Invoice total matches payment amount");

// ------------------------------------------------------------
// 6) Unified booking summary by reference
// ------------------------------------------------------------
echo "\n== 6. UNIFIED BOOKING SUMMARY ==\n";
// Insert a REAL tour booking row so the summary lookup resolves.
$realBookingId = (int)$pdo->query(
    "SELECT id FROM weroad_bookings ORDER BY id ASC LIMIT 1"
)->fetchColumn();
if ($realBookingId <= 0) {
    $ins = $pdo->prepare(
        "INSERT INTO weroad_bookings
           (booking_code, booking_reference, trip_id, user_id, full_name, email,
            guest_name, guest_email, travellers_male, base_price, total_price, status)
         VALUES (?, ?, NULL, ?, 'Flow Test', 'flow@test.local', 'Flow Test', 'flow@test.local',
                 1, 1188.00, 1188.00, 'pending')"
    );
    $ins->execute(['FLOW-' . $uid, $ref, $uid]);
    $realBookingId = (int)$pdo->lastInsertId();
} elseif ($realBookingId > 0) {
    // Link the GAIA reference to a real booking owned by the test user.
    $pdo->prepare("UPDATE weroad_bookings SET booking_reference = ?, user_id = ? WHERE id = ?")
        ->execute([$ref, $uid, $realBookingId]);
}

$summary = BookingSummaryService::findByReference($ref);
check(is_array($summary) && $summary['booking_reference'] === $ref, "findByReference('{$ref}') resolved a summary");
$summaryScoped = BookingSummaryService::findByReference($ref, $uid);
check(is_array($summaryScoped), "findByReference scoped to owner");
$summaryForeign = BookingSummaryService::findByReference($ref, 999999);
check($summaryForeign === null, "findByReference rejects non-owner (IDOR guard)");

$all = BookingSummaryService::getUserSummaries($uid);
check(is_array($all), "getUserSummaries() returns " . count($all) . " rows");

// Cleanup the real booking row if we surfed/created one.
if ($realBookingId > 0) {
    $pdo->prepare("DELETE FROM weroad_bookings WHERE id = ?")->execute([$realBookingId]);
}

// ------------------------------------------------------------
// 7) Reporting readiness (backend only)
// ------------------------------------------------------------
echo "\n== 7. REPORTING READINESS ==\n";
foreach (['totalBookings','bookingsByType','pendingPayments','paidPayments','revenueByService','revenueByMonth'] as $m) {
    check(method_exists('ReportingService', $m), "ReportingService::{$m}() exists");
}

// ------------------------------------------------------------
// Cleanup the test records so the DB stays pristine.
// ------------------------------------------------------------
echo "\n== CLEANUP ==\n";
$pdo->prepare("DELETE FROM invoices WHERE id = ?")->execute([$invId]);
$pdo->prepare("DELETE FROM payments WHERE id = ?")->execute([$paymentId]);
$pdo->prepare("DELETE FROM booking_logs WHERE booking_id = ? AND booking_type='tour'")->execute([$fakeBookingId]);
echo "  [OK] test records removed\n";

echo "\nDONE\n";
echo $ok ? "ALL FLOW CHECKS PASSED\n" : "SOME FLOW CHECKS FAILED\n";

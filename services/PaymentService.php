<?php
/**
 * services/PaymentService.php
 * ------------------------------------------------------------
 * Payment readiness layer — CyberSource READY, gateway-independent.
 *
 * Responsibilities:
 *   - Create payment record
 *   - Update payment status
 *   - Create invoice
 *   - Link booking to payment
 *   - Log gateway response
 *   - Log timeline events
 *
 * ALL operations are gateway-independent. No CyberSource code is
 * present. The class is designed so a future gateway driver can be
 * plugged in via GatewayManager without changing this service.
 *
 * Supported payment statuses:
 *   pending, authorized, paid, failed, cancelled, refunded
 *
 * SECURITY: payment ownership is validated via user_id scoping.
 * Gateway responses are stored securely (JSON) and never exposed
 * publicly. All queries use PDO prepared statements.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../services/InvoiceService.php';
require_once __DIR__ . '/../services/BookingTimelineService.php';
require_once __DIR__ . '/../services/GatewayManager.php';

class PaymentService
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_PAID       = 'paid';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_CANCELLED  = 'cancelled';
    public const STATUS_REFUNDED   = 'refunded';

    /**
     * Create a payment record for a booking.
     *
     * @param int    $userId       owning user id
     * @param string $bookingType  booking type (transfer|tour|hotel|room|event|taxi)
     * @param int    $bookingId    booking record id
     * @param string $reference    booking reference (GAIA-*)
     * @param float  $amount       payment amount
     * @param string $currency     ISO currency
     * @param string $paymentMethod payment method (card, manual, etc.)
     * @param string $gateway      gateway name (optional, future)
     * @param string $status       payment status (default 'pending')
     * @param array  $gatewayResponse optional gateway response payload
     *
     * @return int payment id
     */
    public static function createPayment(
        int $userId,
        string $bookingType,
        int $bookingId,
        string $reference,
        float $amount,
        string $currency = 'USD',
        string $paymentMethod = '',
        string $gateway = '',
        string $status = self::STATUS_PENDING,
        array $gatewayResponse = []
    ): int {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "INSERT INTO payments
                   (user_id, booking_type, booking_id, booking_reference, amount, currency,
                    payment_method, gateway, status, gateway_response)
                 VALUES
                   (:uid, :btype, :bid, :bref, :amt, :cur,
                    :method, :gw, :status, :resp)"
            );
            $stmt->execute([
                ':uid'    => (int)$userId,
                ':btype'  => $bookingType,
                ':bid'    => (int)$bookingId,
                ':bref'   => $reference ?: null,
                ':amt'    => $amount,
                ':cur'    => $currency,
                ':method' => $paymentMethod ?: null,
                ':gw'     => $gateway ?: null,
                ':status' => $status,
                ':resp'   => $gatewayResponse ? json_encode($gatewayResponse) : null,
            ]);
            $paymentId = (int)$pdo->lastInsertId();

            // Record the start of payment in the timeline.
            BookingTimelineService::log(
                $bookingType,
                $bookingId,
                $userId,
                BookingTimelineService::ACTION_PAYMENT_STARTED,
                "Payment record #{$paymentId} created ({$currency} {$amount})."
            );

            return $paymentId;
        } catch (PDOException $e) {
            error_log('PaymentService::createPayment failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Update a payment's status and optionally sync the booking status.
     *
     * @param int    $paymentId    payment id
     * @param string $status       new payment status
     * @param int|null $userId     ownership scope (prevent cross-user updates)
     * @param bool   $syncBooking  whether to sync the parent booking status
     * @param array  $gatewayResponse optional gateway response to store
     *
     * @return bool
     */
    public static function updatePaymentStatus(
        int $paymentId,
        string $status,
        ?int $userId = null,
        bool $syncBooking = true,
        array $gatewayResponse = []
    ): bool {
        try {
            $pdo = getPDO();

            // Load the payment (ownership-scoped if userId given).
            if ($userId && $userId > 0) {
                $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = :id AND user_id = :uid LIMIT 1");
                $stmt->execute([':id' => $paymentId, ':uid' => $userId]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $paymentId]);
            }
            $payment = $stmt->fetch();
            if (!$payment) {
                return false;
            }

            // Update the payment row.
            $responseJson = $gatewayResponse ? json_encode($gatewayResponse) : null;
            $upd = $pdo->prepare(
                "UPDATE payments
                 SET status = :status,
                     gateway_response = COALESCE(:resp, gateway_response),
                     updated_at = NOW()
                 WHERE id = :id"
            );
            $upd->execute([
                ':status' => $status,
                ':resp'   => $responseJson,
                ':id'     => $paymentId,
            ]);

            // Log timeline event for the status change.
            $action = self::timelineActionForStatus($status);
            BookingTimelineService::log(
                $payment['booking_type'],
                (int)$payment['booking_id'],
                $userId ?: (isset($payment['user_id']) ? (int)$payment['user_id'] : null),
                $action,
                "Payment #{$paymentId} status changed to {$status}."
            );

            // Optionally sync the parent booking status.
            if ($syncBooking) {
                self::syncBookingStatus($payment['booking_type'], (int)$payment['booking_id'], $status, $userId);
            }

            return true;
        } catch (PDOException $e) {
            error_log('PaymentService::updatePaymentStatus failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create an invoice for a payment's booking.
     * Compute subtotal/tax/discount from the total amount.
     *
     * @param int    $paymentId     payment id
     * @param float  $taxRate       tax rate (0.0 - 1.0), default 0
     * @param float  $discountAmount discount amount
     * @param string $status        invoice status
     *
     * @return int invoice id
     */
    public static function createInvoice(int $paymentId, float $taxRate = 0.0, float $discountAmount = 0.0, string $status = 'issued', ?int $userId = null): int
    {
        try {
            $pdo = getPDO();
            if ($userId && $userId > 0) {
                $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = :id AND user_id = :uid LIMIT 1");
                $stmt->execute([':id' => $paymentId, ':uid' => $userId]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $paymentId]);
            }
            $payment = $stmt->fetch();
            if (!$payment) {
                return 0;
            }

            $total    = (float)$payment['amount'];
            $subtotal = round($total / (1 + $taxRate), 2);
            $tax      = round($total - ($total - $discountAmount) / (1 + $taxRate), 2);
            if ($taxRate <= 0) {
                // No tax config: subtotal = total - discount, tax = 0.
                $subtotal = round($total - $discountAmount, 2);
                $tax      = 0.0;
            }

            $ownerId = $userId ?: (int)$payment['user_id'];

            return InvoiceService::create(
                $payment['booking_type'],
                (int)$payment['booking_id'],
                $ownerId,
                (string)$payment['booking_reference'],
                $subtotal,
                $tax,
                $discountAmount,
                $total,
                (string)$payment['currency'],
                $status
            );
        } catch (PDOException $e) {
            error_log('PaymentService::createInvoice failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Link a booking to a payment (by reference) — convenience wrapper.
     */
    public static function linkBookingToPayment(string $bookingType, int $bookingId, int $paymentId): bool
    {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "UPDATE payments
                 SET booking_type = :btype, booking_id = :bid, updated_at = NOW()
                 WHERE id = :pid"
            );
            return $stmt->execute([':btype' => $bookingType, ':bid' => $bookingId, ':pid' => $paymentId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Log a gateway response payload for a payment.
     */
    public static function logGatewayResponse(int $paymentId, array $payload, ?int $userId = null): bool
    {
        try {
            $pdo = getPDO();
            $json = json_encode($payload);
            if ($userId && $userId > 0) {
                $stmt = $pdo->prepare(
                    "UPDATE payments SET gateway_response = :resp, updated_at = NOW()
                     WHERE id = :id AND user_id = :uid"
                );
                return $stmt->execute([':resp' => $json, ':id' => $paymentId, ':uid' => $userId]);
            }
            $stmt = $pdo->prepare("UPDATE payments SET gateway_response = :resp, updated_at = NOW() WHERE id = :id");
            return $stmt->execute([':resp' => $json, ':id' => $paymentId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Fetch all payments for a user (ownership-scoped).
     */
    public static function getUserPayments(int $userId): array
    {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "SELECT id, booking_type, booking_id, booking_reference, amount, currency,
                        payment_method, gateway, transaction_reference, status, created_at
                 FROM payments WHERE user_id = :uid ORDER BY id DESC"
            );
            $stmt->execute([':uid' => $userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Fetch payments for a booking (ownership-scoped).
     */
    public static function getBookingPayments(string $bookingType, int $bookingId, ?int $userId = null): array
    {
        try {
            $pdo = getPDO();
            if ($userId && $userId > 0) {
                $stmt = $pdo->prepare(
                    "SELECT id, booking_reference, amount, currency, payment_method, gateway,
                            transaction_reference, status, created_at, updated_at
                     FROM payments
                     WHERE booking_type = :btype AND booking_id = :bid AND user_id = :uid
                     ORDER BY id DESC"
                );
                $stmt->execute([':btype' => $bookingType, ':bid' => $bookingId, ':uid' => $userId]);
            } else {
                $stmt = $pdo->prepare(
                    "SELECT id, booking_reference, amount, currency, payment_method, gateway,
                            transaction_reference, status, created_at, updated_at
                     FROM payments
                     WHERE booking_type = :btype AND booking_id = :bid
                     ORDER BY id DESC"
                );
                $stmt->execute([':btype' => $bookingType, ':bid' => $bookingId]);
            }
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get the latest payment for a booking (ownership-scoped).
     */
    public static function getBookingPayment(string $bookingType, int $bookingId, ?int $userId = null): ?array
    {
        $payments = self::getBookingPayments($bookingType, $bookingId, $userId);
        return $payments ? $payments[0] : null;
    }

    /**
     * Verify that a user owns a payment (IDOR guard).
     */
    public static function ownsPayment(int $paymentId, int $userId): bool
    {
        try {
            $pdo  = getPDO();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE id = :id AND user_id = :uid");
            $stmt->execute([':id' => $paymentId, ':uid' => $userId]);
            return ((int)$stmt->fetchColumn()) > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    // ------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------

    /**
     * Map a payment status to a timeline action.
     */
    private static function timelineActionForStatus(string $status): string
    {
        switch ($status) {
            case self::STATUS_AUTHORIZED:
                return BookingTimelineService::ACTION_PAYMENT_AUTHORIZED;
            case self::STATUS_PAID:
                return BookingTimelineService::ACTION_PAYMENT_COMPLETED;
            case self::STATUS_REFUNDED:
                return BookingTimelineService::ACTION_REFUND_ISSUED;
            case self::STATUS_CANCELLED:
                return BookingTimelineService::ACTION_BOOKING_CANCELLED;
            default:
                return BookingTimelineService::ACTION_PAYMENT_STARTED;
        }
    }

    /**
     * Sync a booking's status based on the payment status.
     * Maps payment status -> booking status.
     */
    private static function syncBookingStatus(string $bookingType, int $bookingId, string $paymentStatus, ?int $userId = null): void
    {
        $bookingStatusMap = [
            self::STATUS_PENDING    => 'awaiting_payment',
            self::STATUS_AUTHORIZED => 'awaiting_payment',
            self::STATUS_PAID       => 'paid',
            self::STATUS_REFUNDED   => 'refunded',
            self::STATUS_CANCELLED  => 'cancelled',
        ];
        if (!isset($bookingStatusMap[$paymentStatus])) {
            return; // failed does not change the booking lifecycle directly
        }
        $bookingStatus = $bookingStatusMap[$paymentStatus];

        $tableMap = [
            'transfer' => 'bookings',
            'taxi'     => 'taxi_bookings',
            'tour'     => 'weroad_bookings',
            'hotel'    => 'hotel_bookings',
            'room'     => 'room_bookings',
            'event'    => 'event_bookings',
        ];
        if (!isset($tableMap[$bookingType])) {
            return;
        }

        try {
            $pdo = getPDO();
            $table = $tableMap[$bookingType];
            $stmt = $pdo->prepare(
                "UPDATE `{$table}` SET status = :status WHERE id = :id"
            );
            $stmt->execute([':status' => $bookingStatus, ':id' => $bookingId]);

            // Log the booking status change to the timeline.
            BookingTimelineService::logStatusChange(
                $bookingType,
                $bookingId,
                $userId,
                'previous',
                $bookingStatus
            );
        } catch (PDOException $e) {
            error_log('PaymentService::syncBookingStatus failed: ' . $e->getMessage());
        }
    }
}

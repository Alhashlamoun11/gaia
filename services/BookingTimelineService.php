<?php
/**
 * services/BookingTimelineService.php
 * ------------------------------------------------------------
 * Booking timeline / audit history for the GAIA platform.
 *
 * Records every action on a booking in the generic `booking_logs`
 * table so all booking types (tours, hotels, rooms, events,
 * transfers) share one audit trail — ready for customer dashboards,
 * admin dashboards and financial audit.
 *
 * Common actions:
 *   Booking Created, Payment Started, Payment Authorized,
 *   Payment Completed, Booking Confirmed, Booking Cancelled,
 *   Refund Issued
 *
 * SECURITY: timeline lookups are scoped to user_id to prevent IDOR.
 * All queries use PDO prepared statements.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../db.php';

class BookingTimelineService
{
    /**
     * Canonical action keys used across the platform.
     */
    public const ACTION_BOOKING_CREATED      = 'Booking Created';
    public const ACTION_PAYMENT_STARTED      = 'Payment Started';
    public const ACTION_PAYMENT_AUTHORIZED   = 'Payment Authorized';
    public const ACTION_PAYMENT_COMPLETED    = 'Payment Completed';
    public const ACTION_BOOKING_CONFIRMED    = 'Booking Confirmed';
    public const ACTION_BOOKING_CANCELLED    = 'Booking Cancelled';
    public const ACTION_REFUND_ISSUED        = 'Refund Issued';

    /**
     * Append an event to a booking's timeline.
     *
     * @param string      $type        booking type (transfer|tour|hotel|room|event|taxi)
     * @param int         $bookingId   booking record id
     * @param int|null    $userId      acting user id (0/null if none)
     * @param string      $action      action key (see constants)
     * @param string|null $description optional human-readable detail
     */
    public static function log(string $type, int $bookingId, ?int $userId, string $action, ?string $description = null): void
    {
        if ($bookingId <= 0) {
            return;
        }
        // Normalise the booking type to a supported value.
        $validTypes = ['transfer', 'taxi', 'tour', 'hotel', 'room', 'event'];
        if (!in_array($type, $validTypes, true)) {
            return;
        }
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "INSERT INTO booking_logs (booking_type, booking_id, user_id, action, description)
                 VALUES (:type, :bid, :uid, :action, :desc)"
            );
            $stmt->execute([
                ':type'   => $type,
                ':bid'    => (int)$bookingId,
                ':uid'    => ($userId && $userId > 0) ? (int)$userId : null,
                ':action' => $action,
                ':desc'   => $description !== null && $description !== '' ? $description : null,
            ]);
        } catch (PDOException $e) {
            // Timeline logging is best-effort and must never break a booking.
            error_log('BookingTimelineService::log failed: ' . $e->getMessage());
        }
    }

    /**
     * Fetch the full timeline for a booking (oldest first).
     *
     * SECURITY: if $userId is provided, only that user's timeline
     * entries are returned (ownership guard).
     *
     * @return array[]
     */
    public static function getTimeline(string $type, int $bookingId, ?int $userId = null): array
    {
        try {
            $pdo = getPDO();
            if ($userId !== null && $userId > 0) {
                $stmt = $pdo->prepare(
                    "SELECT * FROM booking_logs
                     WHERE booking_type = :type AND booking_id = :bid AND user_id = :uid
                     ORDER BY created_at ASC, id ASC"
                );
                $stmt->execute([':type' => $type, ':bid' => $bookingId, ':uid' => $userId]);
            } else {
                $stmt = $pdo->prepare(
                    "SELECT * FROM booking_logs
                     WHERE booking_type = :type AND booking_id = :bid
                     ORDER BY created_at ASC, id ASC"
                );
                $stmt->execute([':type' => $type, ':bid' => $bookingId]);
            }
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Convenience: log a "Booking Created" event.
     */
    public static function logBookingCreated(string $type, int $bookingId, ?int $userId, ?string $reference = null): void
    {
        self::log(
            $type,
            $bookingId,
            $userId,
            self::ACTION_BOOKING_CREATED,
            $reference ? "Booking reference {$reference} created." : 'Booking created.'
        );
    }

    /**
     * Convenience: log a status-change event.
     */
    public static function logStatusChange(string $type, int $bookingId, ?int $userId, string $from, string $to, ?string $reference = null): void
    {
        $action = self::ACTION_BOOKING_CONFIRMED;
        if ($to === 'cancelled') {
            $action = self::ACTION_BOOKING_CANCELLED;
        } elseif ($to === 'refunded') {
            $action = self::ACTION_REFUND_ISSUED;
        } elseif ($to === 'paid') {
            $action = self::ACTION_PAYMENT_COMPLETED;
        }
        self::log(
            $type,
            $bookingId,
            $userId,
            $action,
            "Status changed from {$from} to {$to}."
        );
    }
}

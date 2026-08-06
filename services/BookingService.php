<?php
/**
 * services/BookingService.php
 * ------------------------------------------------------------
 * Unified booking lifecycle service for the GAIA platform.
 *
 * Every booking type (transfer, tour, hotel, room, event, taxi)
 * follows the same lifecycle:
 *
 *   pending → awaiting_payment → paid → confirmed → completed
 *   (cancelled / refunded at any relevant stage)
 *
 * This service centralises booking logic so it is NOT duplicated
 * across tours, hotels, rooms, events and transfers. It:
 *   - Generates unique booking references (GAIA-*)
 *   - Creates the booking record in the correct table
 *   - Logs timeline events (Booking Created, etc.)
 *   - Transitions booking statuses consistently
 *
 * SECURITY: all creations require a user_id (no anonymous bookings).
 * All queries use PDO prepared statements.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../services/BookingReferenceService.php';
require_once __DIR__ . '/../services/BookingTimelineService.php';

class BookingService
{
    /**
     * Canonical booking statuses shared by all booking types.
     */
    public const STATUS_PENDING         = 'pending';
    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';
    public const STATUS_PAID            = 'paid';
    public const STATUS_CONFIRMED       = 'confirmed';
    public const STATUS_COMPLETED       = 'completed';
    public const STATUS_CANCELLED       = 'cancelled';
    public const STATUS_REFUNDED        = 'refunded';

    /**
     * Valid lifecycle transitions (from => allowed next states).
     */
    private const TRANSITIONS = [
        self::STATUS_PENDING          => [self::STATUS_AWAITING_PAYMENT, self::STATUS_CANCELLED],
        self::STATUS_AWAITING_PAYMENT => [self::STATUS_PAID, self::STATUS_PENDING, self::STATUS_CANCELLED],
        self::STATUS_PAID             => [self::STATUS_CONFIRMED, self::STATUS_REFUNDED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED        => [self::STATUS_COMPLETED, self::STATUS_REFUNDED, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED        => [self::STATUS_REFUNDED],
        self::STATUS_CANCELLED        => [],
        self::STATUS_REFUNDED         => [],
    ];

    /**
     * Map booking type => table name.
     */
    private const TABLE_MAP = [
        'transfer' => 'bookings',
        'taxi'     => 'taxi_bookings',
        'tour'     => 'weroad_bookings',
        'hotel'    => 'hotel_bookings',
        'room'     => 'room_bookings',
        'event'    => 'event_bookings',
    ];

    /**
     * All booking types.
     */
    public static function types(): array
    {
        return array_keys(self::TABLE_MAP);
    }

    /**
     * Generate a fresh booking reference for a type.
     */
    public static function newReference(string $type): string
    {
        return BookingReferenceService::generate($type);
    }

    /**
     * Create a booking record and log the "Booking Created" event.
     *
     * @param string $type       booking type
     * @param int    $userId     owning user (REQUIRED — no anonymous)
     * @param array  $data       column => value map for the booking table
     * @param string $status     initial status (default pending)
     * @param float  $totalPrice total price (stored in data['total_price'] if present)
     *
     * @return array{id: int, reference: string}|null
     */
    public static function create(string $type, int $userId, array $data, string $status = self::STATUS_PENDING): ?array
    {
        if ($userId <= 0) {
            // No anonymous bookings allowed.
            throw new InvalidArgumentException('BookingService::create requires an authenticated user_id.');
        }
        if (!isset(self::TABLE_MAP[$type])) {
            throw new InvalidArgumentException("Unknown booking type: {$type}");
        }

        $table     = self::TABLE_MAP[$type];
        $reference = BookingReferenceService::generate($type);

        // Always inject ownership + reference.
        $data['user_id']            = (int)$userId;
        $data['booking_reference']  = $reference;
        if (!isset($data['status'])) {
            $data['status'] = $status;
        }

        try {
            $pdo = getPDO();

            // Whitelist columns from the actual table to avoid SQL errors.
            $columns = self::tableColumns($pdo, $table);
            $insertData = array_intersect_key($data, array_flip($columns));

            if (empty($insertData)) {
                throw new RuntimeException('No valid columns to insert for booking table.');
            }

            $cols = array_keys($insertData);
            $placeholders = ':' . implode(', :', $cols);
            $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $cols) . "`) VALUES ({$placeholders})";

            $params = [];
            foreach ($insertData as $k => $v) {
                $params[':' . $k] = $v;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $bookingId = (int)$pdo->lastInsertId();

            // Log timeline event.
            BookingTimelineService::logBookingCreated($type, $bookingId, $userId, $reference);

            return ['id' => $bookingId, 'reference' => $reference];
        } catch (PDOException $e) {
            error_log('BookingService::create failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Transition a booking to a new status (validates the lifecycle).
     *
     * @return bool true if the transition was applied
     */
    public static function transition(string $type, int $bookingId, string $newStatus, ?int $actingUserId = null): bool
    {
        if (!isset(self::TABLE_MAP[$type])) {
            return false;
        }
        $table = self::TABLE_MAP[$type];

        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare("SELECT status FROM `{$table}` WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $bookingId]);
            $current = $stmt->fetchColumn();
            if ($current === false) {
                return false;
            }

            // Validate the transition.
            $allowed = self::TRANSITIONS[$current] ?? [];
            if ($newStatus === $current) {
                return true; // no-op
            }
            if (!in_array($newStatus, $allowed, true)) {
                return false;
            }

            $upd = $pdo->prepare("UPDATE `{$table}` SET status = :status WHERE id = :id");
            $ok = $upd->execute([':status' => $newStatus, ':id' => $bookingId]);

            if ($ok) {
                BookingTimelineService::logStatusChange($type, $bookingId, $actingUserId, (string)$current, $newStatus);
            }
            return $ok;
        } catch (PDOException $e) {
            error_log('BookingService::transition failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the current status of a booking, ownership-scoped.
     */
    public static function getStatus(string $type, int $bookingId, ?int $userId = null): ?string
    {
        if (!isset(self::TABLE_MAP[$type])) {
            return null;
        }
        $table = self::TABLE_MAP[$type];
        try {
            $pdo = getPDO();
            if ($userId && $userId > 0) {
                $stmt = $pdo->prepare("SELECT status FROM `{$table}` WHERE id = :id AND user_id = :uid LIMIT 1");
                $stmt->execute([':id' => $bookingId, ':uid' => $userId]);
            } else {
                $stmt = $pdo->prepare("SELECT status FROM `{$table}` WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $bookingId]);
            }
            $v = $stmt->fetchColumn();
            return $v === false ? null : (string)$v;
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * List all columns present in a booking table (safety whitelist).
     */
    private static function tableColumns(PDO $pdo, string $table): array
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }
        $stmt = $pdo->prepare(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t"
        );
        $stmt->execute([':t' => $table]);
        $cache[$table] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $cache[$table];
    }
}

<?php
/**
 * auth/BookingRepository.php
 * ------------------------------------------------------------
 * Central booking ownership service for the GAIA platform.
 *
 * Every booking created on the platform belongs to a customer
 * account (user_id). Guest bookings leave user_id NULL and store
 * guest_name / guest_email / guest_phone instead.
 *
 * Provides:
 *   - getUserBookings()          : all bookings (normalised list)
 *   - getUserTourBookings()      : weroad_bookings (tours)
 *   - getUserHotelBookings()     : hotel_bookings
 *   - getUserTransferBookings()  : bookings (transfers)
 *   - getUserEventBookings()     : event_bookings
 *   - getBookingDetails()        : single booking with ownership guard
 *   - countUserBookings()        : total count (header badge)
 *   - claimBookingsByEmail()     : auto-associate guest bookings on register
 *
 * These methods are reused by the customer dashboard, admin
 * dashboards, hotel managers and future payment/booking reporting.
 *
 * SECURITY: booking lookups are always scoped to user_id so a
 * user can never read another user's booking (prevents IDOR).
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../db.php';

class BookingRepository
{
    /**
     * Normalise a booking into a consistent shape for display lists.
     */
private static function normalize(array $row): array
    {
        return [
            'id'                => (int)($row['id'] ?? 0),
            'booking_code'      => $row['booking_code'] ?? '',
            'booking_reference' => $row['booking_reference'] ?? '',
            'type'              => $row['type'] ?? '',
            'title'             => $row['title'] ?? '',
            'date'              => $row['date'] ?? '',
            'status'            => $row['status'] ?? 'pending',
            'total_price'       => (float)($row['total_price'] ?? 0),
        ];
    }

    /**
     * All bookings belonging to a user, merged and sorted by date.
     *
     * @return array[]
     */
    public static function getUserBookings(int $userId): array
    {
        $all = array_merge(
            self::getUserTourBookings($userId),
            self::getUserHotelBookings($userId),
            self::getUserEventBookings($userId),
            self::getUserTransferBookings($userId)
        );

        usort($all, function ($a, $b) {
            return strcmp((string)$b['date'], (string)$a['date']);
        });

        return $all;
    }

    /**
     * Tours (weroad_bookings).
     */
    public static function getUserTourBookings(int $userId): array
    {
        try {
            $pdo  = getPDO();
            $call = self::tableExists('weroad_bookings');
            if (!$call) {
                return [];
            }
$stmt = $pdo->prepare(
                "SELECT wb.id, wb.booking_code, wb.booking_reference,
                        COALESCE(NULLIF(wt.name, ''), 'GAIA Tour') AS title,
                        DATE(wb.created_at) AS date, wb.total_price, wb.status
                 FROM weroad_bookings wb
                 LEFT JOIN weroad_trips wt ON wt.id = wb.trip_id
                 WHERE wb.user_id = ?"
            );
            $stmt->execute([(int)$userId]);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) { $r['type'] = 'tour'; }
            return array_map([self::class, 'normalize'], $rows);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Hotels (hotel_bookings).
     */
    public static function getUserHotelBookings(int $userId): array
    {
        try {
            $pdo  = getPDO();
            if (!self::tableExists('hotel_bookings')) {
                return [];
            }
            $stmt = $pdo->prepare(
                "SELECT hb.id, hb.booking_code, hb.booking_reference,
                        COALESCE(NULLIF(CONCAT_WS(' · ', h.name, IFNULL(hr.name, '')), ''), 'GAIA Hotel') AS title,
                        hb.check_in_date AS date, hb.total_price, hb.status
                 FROM hotel_bookings hb
                 LEFT JOIN hotels h ON h.id = hb.hotel_id
                 LEFT JOIN hotel_rooms hr ON hr.id = hb.room_id
                 WHERE hb.user_id = ?"
            );
            $stmt->execute([(int)$userId]);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) { $r['type'] = 'hotel'; }
            return array_map([self::class, 'normalize'], $rows);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Events (event_bookings).
     */
    public static function getUserEventBookings(int $userId): array
    {
        try {
            $pdo  = getPDO();
            if (!self::tableExists('event_bookings')) {
                return [];
            }
            $stmt = $pdo->prepare(
                "SELECT eb.id, eb.booking_code, eb.booking_reference,
                        COALESCE(NULLIF(ev.title, ''), 'GAIA Event') AS title,
                        DATE(eb.created_at) AS date, eb.total_price, eb.status
                 FROM event_bookings eb
                 LEFT JOIN events ev ON ev.id = eb.event_id
                 WHERE eb.user_id = ?"
            );
            $stmt->execute([(int)$userId]);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) { $r['type'] = 'event'; }
            return array_map([self::class, 'normalize'], $rows);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Transfers (bookings).
     */
    public static function getUserTransferBookings(int $userId): array
    {
        try {
            $pdo  = getPDO();
            if (!self::tableExists('bookings')) {
                return [];
            }
            $stmt = $pdo->prepare(
                "SELECT id, booking_code, booking_reference,
                        CONCAT_WS(' → ', origin, destination) AS title,
                        pickup_date AS date, total_price, status
                 FROM bookings WHERE user_id = ?"
            );
            $stmt->execute([(int)$userId]);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) { $r['type'] = 'transfer'; }
            return array_map([self::class, 'normalize'], $rows);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Total number of bookings for a user (used for the header badge).
     */
    public static function countUserBookings(int $userId): int
    {
        return count(self::getUserBookings($userId));
    }

    /**
     * Load a single booking by type + id, verifying the user owns it.
     *
     * @param int    $userId  authenticated user id
     * @param string $type    one of tour|hotel|event|transfer
     * @param int    $id      booking record id
     *
     * @return array|null  the booking row, or null if missing / not owned
     */
    public static function getBookingDetails(int $userId, string $type, int $id): ?array
    {
        try {
            $pdo = getPDO();
            switch ($type) {
                case 'tour':
                    if (!self::tableExists('weroad_bookings')) return null;
                    $stmt = $pdo->prepare(
                        "SELECT wb.*, wt.name AS service_name, wt.slug AS service_slug
                         FROM weroad_bookings wb
                         LEFT JOIN weroad_trips wt ON wt.id = wb.trip_id
                         WHERE wb.id = ? AND wb.user_id = ? LIMIT 1"
                    );
                    break;
                case 'hotel':
                    if (!self::tableExists('hotel_bookings')) return null;
                    $stmt = $pdo->prepare(
                        "SELECT hb.*, h.name AS service_name, h.slug AS service_slug,
                                hr.name AS room_name
                         FROM hotel_bookings hb
                         LEFT JOIN hotels h ON h.id = hb.hotel_id
                         LEFT JOIN hotel_rooms hr ON hr.id = hb.room_id
                         WHERE hb.id = ? AND hb.user_id = ? LIMIT 1"
                    );
                    break;
                case 'event':
                    if (!self::tableExists('event_bookings')) return null;
                    $stmt = $pdo->prepare(
                        "SELECT eb.*, ev.title AS service_name, ev.slug AS service_slug
                         FROM event_bookings eb
                         LEFT JOIN events ev ON ev.id = eb.event_id
                         WHERE eb.id = ? AND eb.user_id = ? LIMIT 1"
                    );
                    break;
                case 'transfer':
                default:
                    if (!self::tableExists('bookings')) return null;
                    $type = 'transfer';
                    $stmt = $pdo->prepare(
                        "SELECT b.*, r.origin AS service_origin, r.destination AS service_destination,
                                CONCAT_WS(' → ', b.origin, b.destination) AS service_name
                         FROM bookings b
                         LEFT JOIN routes r ON r.id = b.route_id
                         WHERE b.id = ? AND b.user_id = ? LIMIT 1"
                    );
                    break;
            }

            $stmt->execute([(int)$id, (int)$userId]);
            $row = $stmt->fetch();
            if (!$row) {
                return null;
            }
            $row['type'] = $type;
            return $row;
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Auto-associate all guest bookings whose guest_email matches a
     * newly-registered user's email. Applied across every booking table.
     *
     * @return int number of bookings claimed
     */
    public static function claimBookingsByEmail(int $userId, string $email): int
    {
        $email = strtolower(trim($email));
        if ($userId <= 0 || $email === '') {
            return 0;
        }

        $claimed = 0;
        try {
            $pdo = getPDO();
            $configs = [
                ['bookings', 'guest_email'],
                ['weroad_bookings', 'guest_email'],
                ['hotel_bookings', 'guest_email'],
                ['event_bookings', 'guest_email'],
                ['taxi_bookings', 'guest_email'],
            ];

            foreach ($configs as [$table, $emailCol]) {
                if (!self::tableExists($table)) {
                    continue;
                }
                $upd = $pdo->prepare(
                    "UPDATE `{$table}` SET user_id = :uid
                     WHERE user_id IS NULL AND `{$emailCol}` = :email"
                );
                $upd->execute([':uid' => $userId, ':email' => $email]);
                $claimed += (int)$upd->rowCount();
            }
} catch (PDOException $e) {
            // ignore — claim is best-effort
        }

        return $claimed;
    }

/**
     * Public accessor for the table-existence check (used by dashboards).
     */
    public static function tableExistsPublic(string $table): bool
    {
        return self::tableExists($table);
    }

    /**
     * Does a table exist in the current schema?
     */
    private static function tableExists(string $table): bool
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }
        try {
            $pdo  = getPDO();
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
            );
            $stmt->execute([$table]);
            return $cache[$table] = ((int)$stmt->fetchColumn() > 0);
        } catch (PDOException $e) {
            return $cache[$table] = false;
        }
    }
}

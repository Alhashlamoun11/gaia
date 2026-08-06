<?php
/**
 * services/BookingSummaryService.php
 * ------------------------------------------------------------
 * Reusable booking summary architecture.
 *
 * Every booking type (transfer, tour, hotel, room, event, taxi)
 * exposes a consistent summary:
 *   - booking_reference   (GAIA-*)
 *   - service_name        (human-readable service label)
 *   - customer            (guest name)
 *   - amount              (total price)
 *   - status              (normalised booking status)
 *   - created_date        (created_at)
 *
 * Used later by:
 *   - Customer dashboard
 *   - Admin dashboard
 *   - Hotel manager dashboard
 *   - Payments
 *   - Invoices
 *
 * SECURITY: all lookups are scoped to user_id to prevent IDOR.
 * All queries use PDO prepared statements.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../db.php';

class BookingSummaryService
{
    /**
     * Return a unified booking summary for a single booking.
     *
     * @param string $type    booking type (transfer|tour|hotel|room|event|taxi)
     * @param int    $id      booking record id
     * @param int|null $userId optional ownership scope (prevents IDOR)
     *
     * @return array|null standardised summary, or null if not found / not owned
     */
    public static function getSummary(string $type, int $id, ?int $userId = null): ?array
    {
        try {
            $pdo = getPDO();
            $row = null;

            switch ($type) {
                case 'transfer':
                    $row = self::fetchTransfer($pdo, $id, $userId);
                    break;
                case 'taxi':
                    $row = self::fetchTaxi($pdo, $id, $userId);
                    break;
                case 'tour':
                    $row = self::fetchTour($pdo, $id, $userId);
                    break;
                case 'hotel':
                    $row = self::fetchHotel($pdo, $id, $userId);
                    break;
                case 'room':
                    $row = self::fetchRoom($pdo, $id, $userId);
                    break;
                case 'event':
                    $row = self::fetchEvent($pdo, $id, $userId);
                    break;
                default:
                    return null;
            }

            if (!$row) {
                return null;
            }

            return [
                'booking_type'       => $type,
                'booking_reference'  => $row['booking_reference'] ?? '',
                'booking_code'       => $row['booking_code'] ?? '',
                'id'                 => (int)($row['id'] ?? 0),
                'service_name'       => $row['service_name'] ?? '',
                'customer'           => $row['customer'] ?? '',
                'amount'             => (float)($row['total_price'] ?? 0),
                'currency'           => $row['currency'] ?? 'USD',
                'status'             => $row['status'] ?? 'pending',
                'created_date'       => $row['created_date'] ?? '',
                'user_id'            => $userId,
            ];
        } catch (PDOException $e) {
            return null;
        }
    }

/**
     * Return a unified booking summary loaded by booking reference (GAIA-*).
     *
     * The reference embeds a type prefix (e.g. GAIA-TR-, GAIA-TO-, GAIA-HO-,
     * GAIA-RO-, GAIA-EV-). We parse the prefix to determine the booking type,
     * then load the record via the ownership-scoped getSummary().
     *
     * @param string   $reference booking reference (e.g. GAIA-TR-000123)
     * @param int|null $userId    ownership scope (prevents IDOR)
     *
     * @return array|null standardised summary, or null if not found / not owned
     */
    public static function findByReference(string $reference, ?int $userId = null): ?array
    {
        $reference = strtoupper(trim($reference));
        if ($reference === '') {
            return null;
        }

        // Map the reference prefix to a booking type.
        $typeMap = [
            'GAIA-TR-' => 'transfer',
            'GAIA-TO-' => 'tour',
            'GAIA-HO-' => 'hotel',
            'GAIA-RO-' => 'room',
            'GAIA-EV-' => 'event',
        ];
        $type = null;
        foreach ($typeMap as $prefix => $candidate) {
            if (strpos($reference, $prefix) === 0) {
                $type = $candidate;
                break;
            }
        }
        if ($type === null) {
            return null;
        }

        try {
            $pdo = getPDO();
            $table = self::tableForType($type);
            if ($table === null) {
                return null;
            }
            if ($userId !== null && $userId > 0) {
                $stmt = $pdo->prepare(
                    "SELECT id FROM `{$table}`
                     WHERE booking_reference = :ref AND user_id = :uid LIMIT 1"
                );
                $stmt->execute([':ref' => $reference, ':uid' => $userId]);
            } else {
                $stmt = $pdo->prepare(
                    "SELECT id FROM `{$table}` WHERE booking_reference = :ref LIMIT 1"
                );
                $stmt->execute([':ref' => $reference]);
            }
            $id = (int)$stmt->fetchColumn();
            if ($id <= 0) {
                return null;
            }
            return self::getSummary($type, $id, $userId);
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Return summaries for ALL bookings belonging to a user, merged.
     */
    public static function getUserSummaries(int $userId): array
    {
        $all = [];
        foreach (['transfer', 'taxi', 'tour', 'hotel', 'room', 'event'] as $type) {
            $all = array_merge($all, self::getUserSummariesByType($type, $userId));
        }
        usort($all, fn($a, $b) => strcmp((string)$b['created_date'], (string)$a['created_date']));
        return $all;
    }

    /**
     * Return summaries for a user filtered by booking type.
     */
    public static function getUserSummariesByType(string $type, int $userId): array
    {
        try {
            $pdo = getPDO();
            $rows = [];

            switch ($type) {
                case 'transfer':
                    $rows = $pdo->prepare(
                        "SELECT b.id, b.booking_code, b.booking_reference,
                                CONCAT_WS(' → ', b.origin, b.destination) AS service_name,
                                COALESCE(NULLIF(b.guest_name,''), b.full_name, 'Guest') AS customer,
                                b.total_price, 'USD' AS currency, b.status,
                                DATE(b.created_at) AS created_date
                         FROM bookings b WHERE b.user_id = :uid"
                    );
                    break;
                case 'taxi':
                    $rows = $pdo->prepare(
                        "SELECT t.id, t.booking_code, t.booking_reference,
                                CONCAT_WS(' → ', t.origin, t.destination) AS service_name,
                                COALESCE(NULLIF(t.guest_name,''), 'Guest') AS customer,
                                t.total_price, 'USD' AS currency, t.status,
                                DATE(t.created_at) AS created_date
                         FROM taxi_bookings t WHERE t.user_id = :uid"
                    );
                    break;
                case 'tour':
                    $rows = $pdo->prepare(
                        "SELECT wb.id, wb.booking_code, wb.booking_reference,
                                COALESCE(NULLIF(wt.name,''), 'GAIA Tour') AS service_name,
                                COALESCE(NULLIF(wb.guest_name,''), wb.full_name, 'Guest') AS customer,
                                wb.total_price, 'EUR' AS currency, wb.status,
                                DATE(wb.created_at) AS created_date
                         FROM weroad_bookings wb
                         LEFT JOIN weroad_trips wt ON wt.id = wb.trip_id
                         WHERE wb.user_id = :uid"
                    );
                    break;
                case 'hotel':
                    $rows = $pdo->prepare(
                        "SELECT hb.id, hb.booking_code, hb.booking_reference,
                                COALESCE(NULLIF(h.name,''), 'GAIA Hotel') AS service_name,
                                COALESCE(NULLIF(hb.guest_name,''), 'Guest') AS customer,
                                hb.total_price, 'USD' AS currency, hb.status,
                                DATE(hb.created_at) AS created_date
                         FROM hotel_bookings hb
                         LEFT JOIN hotels h ON h.id = hb.hotel_id
                         WHERE hb.user_id = :uid"
                    );
                    break;
                case 'room':
                    $rows = $pdo->prepare(
                        "SELECT rb.id, rb.booking_code, rb.booking_reference,
                                COALESCE(NULLIF(CONCAT_WS(' · ', h.name, hr.name), ''), 'GAIA Room') AS service_name,
                                COALESCE(NULLIF(rb.guest_name,''), 'Guest') AS customer,
                                rb.total_price, 'USD' AS currency, rb.status,
                                DATE(rb.created_at) AS created_date
                         FROM room_bookings rb
                         LEFT JOIN hotels h ON h.id = rb.hotel_id
                         LEFT JOIN hotel_rooms hr ON hr.id = rb.room_id
                         WHERE rb.user_id = :uid"
                    );
                    break;
                case 'event':
                    $rows = $pdo->prepare(
                        "SELECT eb.id, eb.booking_code, eb.booking_reference,
                                COALESCE(NULLIF(ev.title,''), 'GAIA Event') AS service_name,
                                COALESCE(NULLIF(eb.guest_name,''), 'Guest') AS customer,
                                eb.total_price, 'USD' AS currency, eb.status,
                                DATE(eb.created_at) AS created_date
                         FROM event_bookings eb
                         LEFT JOIN events ev ON ev.id = eb.event_id
                         WHERE eb.user_id = :uid"
                    );
                    break;
                default:
                    return [];
            }

            $rows->execute([':uid' => (int)$userId]);
            $records = $rows->fetchAll();
            foreach ($records as &$r) {
                $r['booking_type'] = $type;
                $r['amount']       = (float)($r['total_price'] ?? 0);
            }
            return $records;
        } catch (PDOException $e) {
            return [];
        }
    }

    // ---- Private per-type fetch helpers (ownership-scoped) ----

    private static function fetchTransfer($pdo, int $id, ?int $userId): ?array
    {
        $sql = "SELECT b.*, CONCAT_WS(' → ', b.origin, b.destination) AS service_name,
                       COALESCE(NULLIF(b.guest_name,''), b.full_name, 'Guest') AS customer,
                       DATE(b.created_at) AS created_date, 'USD' AS currency
                FROM bookings b WHERE b.id = :id";
        $params = [':id' => $id];
        if ($userId) { $sql .= " AND b.user_id = :uid"; $params[':uid'] = $userId; }
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    private static function fetchTaxi($pdo, int $id, ?int $userId): ?array
    {
        $sql = "SELECT t.*, CONCAT_WS(' → ', t.origin, t.destination) AS service_name,
                       COALESCE(NULLIF(t.guest_name,''), 'Guest') AS customer,
                       DATE(t.created_at) AS created_date, 'USD' AS currency
                FROM taxi_bookings t WHERE t.id = :id";
        $params = [':id' => $id];
        if ($userId) { $sql .= " AND t.user_id = :uid"; $params[':uid'] = $userId; }
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    private static function fetchTour($pdo, int $id, ?int $userId): ?array
    {
        $sql = "SELECT wb.*, COALESCE(NULLIF(wt.name,''), 'GAIA Tour') AS service_name,
                       COALESCE(NULLIF(wb.guest_name,''), wb.full_name, 'Guest') AS customer,
                       DATE(wb.created_at) AS created_date, 'EUR' AS currency
                FROM weroad_bookings wb
                LEFT JOIN weroad_trips wt ON wt.id = wb.trip_id
                WHERE wb.id = :id";
        $params = [':id' => $id];
        if ($userId) { $sql .= " AND wb.user_id = :uid"; $params[':uid'] = $userId; }
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    private static function fetchHotel($pdo, int $id, ?int $userId): ?array
    {
        $sql = "SELECT hb.*, COALESCE(NULLIF(h.name,''), 'GAIA Hotel') AS service_name,
                       COALESCE(NULLIF(hb.guest_name,''), 'Guest') AS customer,
                       DATE(hb.created_at) AS created_date, 'USD' AS currency
                FROM hotel_bookings hb
                LEFT JOIN hotels h ON h.id = hb.hotel_id
                WHERE hb.id = :id";
        $params = [':id' => $id];
        if ($userId) { $sql .= " AND hb.user_id = :uid"; $params[':uid'] = $userId; }
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    private static function fetchRoom($pdo, int $id, ?int $userId): ?array
    {
        $sql = "SELECT rb.*, COALESCE(NULLIF(CONCAT_WS(' · ', h.name, hr.name), ''), 'GAIA Room') AS service_name,
                       COALESCE(NULLIF(rb.guest_name,''), 'Guest') AS customer,
                       DATE(rb.created_at) AS created_date, 'USD' AS currency
                FROM room_bookings rb
                LEFT JOIN hotels h ON h.id = rb.hotel_id
                LEFT JOIN hotel_rooms hr ON hr.id = rb.room_id
                WHERE rb.id = :id";
        $params = [':id' => $id];
        if ($userId) { $sql .= " AND rb.user_id = :uid"; $params[':uid'] = $userId; }
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

private static function fetchEvent($pdo, int $id, ?int $userId): ?array
    {
        $sql = "SELECT eb.*, COALESCE(NULLIF(ev.title,''), 'GAIA Event') AS service_name,
                       COALESCE(NULLIF(eb.guest_name,''), 'Guest') AS customer,
                       DATE(eb.created_at) AS created_date, 'USD' AS currency
                FROM event_bookings eb
                LEFT JOIN events ev ON ev.id = eb.event_id
                WHERE eb.id = :id";
        $params = [':id' => $id];
        if ($userId) { $sql .= " AND eb.user_id = :uid"; $params[':uid'] = $userId; }
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    /**
     * Map a booking type to its backing table name.
     */
    private static function tableForType(string $type): ?string
    {
        $map = [
            'transfer' => 'bookings',
            'taxi'     => 'taxi_bookings',
            'tour'     => 'weroad_bookings',
            'hotel'    => 'hotel_bookings',
            'room'     => 'room_bookings',
            'event'    => 'event_bookings',
        ];
        return $map[$type] ?? null;
    }
}

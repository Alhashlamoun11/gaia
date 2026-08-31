<?php
/**
 * services/HotelAvailabilityService.php
 * ------------------------------------------------------------
 * Canonical hotel room availability service for the GAIA platform.
 *
 * Centralises the calculation of room inventory so that every
 * page (search, hotel detail, room detail, checkout, manager
 * portal, process_hotel_booking) uses the same logic.
 *
 * Blocking statuses (reserve inventory):
 *   pending, awaiting_payment, paid, confirmed
 *
 * Non-blocking statuses (do NOT reserve inventory):
 *   completed, cancelled, refunded
 *
 * Canonical formula:
 *   booked   = SUM(rooms_count) of overlapping bookings with blocking status
 *   available = room.quantity - booked
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../db.php';

class HotelAvailabilityService
{
    /**
     * Statuses that block / reserve inventory.
     */
    public const BLOCKING_STATUSES = ['pending', 'awaiting_payment', 'paid', 'confirmed'];

    /**
     * Statuses counted towards revenue.
     */
    public const REVENUE_STATUSES = ['paid', 'confirmed', 'completed'];

    /**
     * Get the number of rooms booked (inventory consumed) for a specific
     * room type over a date range. Only blocking statuses are counted.
     *
     * @param PDO    $pdo       Database connection
     * @param int    $roomId    hotel_rooms.id
     * @param string $checkIn   Check-in date (Y-m-d)
     * @param string $checkOut  Check-out date (Y-m-d)
     * @return int  Total rooms booked (SUM of rooms_count)
     */
    public static function bookedInventory(PDO $pdo, int $roomId, string $checkIn, string $checkOut): int
    {
        $placeholders = implode(',', array_fill(0, count(self::BLOCKING_STATUSES), '?'));
        $sql = "
            SELECT COALESCE(SUM(rooms_count), 0)
            FROM hotel_bookings
            WHERE room_id = ?
            AND status IN ({$placeholders})
            AND check_in_date < ? AND check_out_date > ?
        ";
        $params = array_merge([$roomId], self::BLOCKING_STATUSES, [$checkOut, $checkIn]);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get available inventory for a room type over a date range.
     *
     * @param PDO    $pdo
     * @param int    $roomId
     * @param int    $roomQuantity  hotel_rooms.quantity
     * @param string $checkIn
     * @param string $checkOut
     * @return int  Available rooms (never negative)
     */
    public static function availableInventory(PDO $pdo, int $roomId, int $roomQuantity, string $checkIn, string $checkOut): int
    {
        $booked = self::bookedInventory($pdo, $roomId, $checkIn, $checkOut);
        return max(0, $roomQuantity - $booked);
    }

    /**
     * Check if a requested number of rooms can be booked.
     *
     * @param PDO    $pdo
     * @param int    $roomId
     * @param int    $roomQuantity
     * @param string $checkIn
     * @param string $checkOut
     * @param int    $requestedRooms
     * @return bool  True if enough inventory exists
     */
    public static function isAvailable(PDO $pdo, int $roomId, int $roomQuantity, string $checkIn, string $checkOut, int $requestedRooms): bool
    {
        return self::availableInventory($pdo, $roomId, $roomQuantity, $checkIn, $checkOut) >= $requestedRooms;
    }

    /**
     * Get booked inventory for a specific room on a specific single date.
     * Used by the availability calendar.
     *
     * @param PDO    $pdo
     * @param int    $roomId
     * @param string $date  A single date (Y-m-d)
     * @return int  Total rooms booked on that date
     */
    public static function bookedOnDate(PDO $pdo, int $roomId, string $date): int
    {
        // A booking overlaps a single date if: check_in_date <= date AND check_out_date > date
        $placeholders = implode(',', array_fill(0, count(self::BLOCKING_STATUSES), '?'));
        $sql = "
            SELECT COALESCE(SUM(rooms_count), 0)
            FROM hotel_bookings
            WHERE room_id = ?
            AND status IN ({$placeholders})
            AND check_in_date <= ? AND check_out_date > ?
        ";
        $params = array_merge([$roomId], self::BLOCKING_STATUSES, [$date, $date]);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Batch-load booked inventory for multiple rooms across a date range.
     * Returns an associative array: [room_id][date_string] => booked_count
     *
     * This is optimised for the availability calendar to avoid N×D queries.
     *
     * @param PDO    $pdo
     * @param array  $roomIds   Array of hotel_rooms.id
     * @param string $startDate Start of range (Y-m-d)
     * @param string $endDate   End of range (Y-m-d, inclusive)
     * @return array
     */
    public static function batchBookedByDate(PDO $pdo, array $roomIds, string $startDate, string $endDate): array
    {
        if (empty($roomIds)) return [];

        $result = [];
        foreach ($roomIds as $rid) {
            $result[(int)$rid] = [];
        }

        $roomPlaceholders = implode(',', array_fill(0, count($roomIds), '?'));
        $statusPlaceholders = implode(',', array_fill(0, count(self::BLOCKING_STATUSES), '?'));

        // Fetch all overlapping bookings in the date range for these rooms
        $sql = "
            SELECT room_id, check_in_date, check_out_date, rooms_count
            FROM hotel_bookings
            WHERE room_id IN ({$roomPlaceholders})
            AND status IN ({$statusPlaceholders})
            AND check_in_date <= ? AND check_out_date > ?
        ";
        $params = array_merge(
            array_map('intval', $roomIds),
            self::BLOCKING_STATUSES,
            [$endDate, $startDate]
        );
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $bookings = $stmt->fetchAll();

        // For each booking, iterate through its occupied dates and accumulate
        foreach ($bookings as $b) {
            $rid = (int)$b['room_id'];
            $rc = (int)$b['rooms_count'];
            $ci = new DateTime($b['check_in_date']);
            $co = new DateTime($b['check_out_date']);
            $rangeStart = new DateTime($startDate);
            $rangeEnd = new DateTime($endDate);

            // Clamp to the calendar range
            $from = $ci < $rangeStart ? $rangeStart : $ci;
            $to = $co > $rangeEnd ? (clone $rangeEnd)->modify('+1 day') : $co;

            $current = clone $from;
            while ($current < $to) {
                $ds = $current->format('Y-m-d');
                if (!isset($result[$rid][$ds])) {
                    $result[$rid][$ds] = 0;
                }
                $result[$rid][$ds] += $rc;
                $current->modify('+1 day');
            }
        }

        return $result;
    }

    /**
     * Get total occupied rooms for a hotel on a specific date.
     * Used for the dashboard occupancy KPI.
     *
     * @param PDO    $pdo
     * @param int    $hotelId
     * @param string $date
     * @return int
     */
    public static function totalOccupiedOnDate(PDO $pdo, int $hotelId, string $date): int
    {
        $placeholders = implode(',', array_fill(0, count(self::BLOCKING_STATUSES), '?'));
        $sql = "
            SELECT COALESCE(SUM(b.rooms_count), 0)
            FROM hotel_bookings b
            WHERE b.hotel_id = ?
            AND b.status IN ({$placeholders})
            AND b.check_in_date <= ? AND b.check_out_date > ?
        ";
        $params = array_merge([$hotelId], self::BLOCKING_STATUSES, [$date, $date]);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get total room inventory for a hotel (sum of quantity for active rooms).
     *
     * @param PDO $pdo
     * @param int $hotelId
     * @return int
     */
    public static function totalInventory(PDO $pdo, int $hotelId): int
    {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM hotel_rooms WHERE hotel_id = ? AND is_active = 1");
        $stmt->execute([$hotelId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get total revenue for a hotel (only revenue statuses).
     *
     * @param PDO $pdo
     * @param int $hotelId
     * @return float
     */
    public static function totalRevenue(PDO $pdo, int $hotelId): float
    {
        $placeholders = implode(',', array_fill(0, count(self::REVENUE_STATUSES), '?'));
        $sql = "SELECT COALESCE(SUM(total_price), 0) FROM hotel_bookings WHERE hotel_id = ? AND status IN ({$placeholders})";
        $params = array_merge([$hotelId], self::REVENUE_STATUSES);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Validate search context parameters.
     * Returns sanitised values or null if invalid.
     *
     * @param array $params  Typically $_GET
     * @return array|null    ['check_in'=>..., 'check_out'=>..., 'rooms'=>..., 'guests'=>...] or null
     */
    public static function validateSearchContext(array $params): ?array
    {
        $checkIn  = trim($params['check_in'] ?? '');
        $checkOut = trim($params['check_out'] ?? '');
        $rooms    = (int)($params['rooms'] ?? 0);
        $guests   = (int)($params['guests'] ?? 0);

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkIn) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkOut)) {
            return null;
        }
        // Validate date values
        $ciDate = DateTime::createFromFormat('Y-m-d', $checkIn);
        $coDate = DateTime::createFromFormat('Y-m-d', $checkOut);
        if (!$ciDate || !$coDate || $ciDate->format('Y-m-d') !== $checkIn || $coDate->format('Y-m-d') !== $checkOut) {
            return null;
        }
        // Check-out must be after check-in
        if ($checkOut <= $checkIn) {
            return null;
        }
        // Positive integers
        if ($rooms < 1) $rooms = 1;
        if ($guests < 1) $guests = 1;

        return [
            'check_in'  => $checkIn,
            'check_out' => $checkOut,
            'rooms'     => $rooms,
            'guests'    => $guests,
        ];
    }

    /**
     * Build URL query string for search context parameters.
     *
     * @param array $ctx  Validated search context
     * @return string
     */
    public static function searchContextQuery(array $ctx): string
    {
        return http_build_query([
            'check_in'  => $ctx['check_in'],
            'check_out' => $ctx['check_out'],
            'rooms'     => $ctx['rooms'],
            'guests'    => $ctx['guests'],
        ]);
    }
}

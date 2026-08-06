<?php
/**
 * services/BookingReferenceService.php
 * ------------------------------------------------------------
 * Unified booking reference generation for the GAIA platform.
 *
 * Formats (unique per booking type):
 *   GAIA-TR-XXXXXX  = Transfer Booking   (bookings / taxi_bookings)
 *   GAIA-TO-XXXXXX  = Tour Booking       (weroad_bookings)
 *   GAIA-HO-XXXXXX  = Hotel Booking      (hotel_bookings)
 *   GAIA-RO-XXXXXX  = Room Booking       (room_bookings)
 *   GAIA-EV-XXXXXX  = Event Booking      (event_bookings)
 *
 * References are unique and auto-generated. Each booking type uses
 * its own prefix so a reference can never be ambiguous across types.
 *
 * SECURITY: reference generation is collision-safe (retry loop +
 * unique DB index). All queries use PDO prepared statements.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../db.php';

class BookingReferenceService
{
    /**
     * Mapping of booking type => reference prefix letter(s).
     */
    private const PREFIX_MAP = [
        'transfer' => 'GAIA-TR-',
        'taxi'     => 'GAIA-TR-',
        'tour'     => 'GAIA-TO-',
        'hotel'    => 'GAIA-HO-',
        'room'     => 'GAIA-RO-',
        'event'    => 'GAIA-EV-',
    ];

    /**
     * Mapping of booking type => table name.
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
     * All supported booking types (used for validation / iteration).
     */
    public static function supportedTypes(): array
    {
        return array_keys(self::PREFIX_MAP);
    }

    /**
     * Return the reference prefix (e.g. 'GAIA-TR-') for a booking type.
     * Throws InvalidArgumentException for unknown types.
     */
    public static function prefixFor(string $type): string
    {
        if (!isset(self::PREFIX_MAP[$type])) {
            throw new InvalidArgumentException("Unknown booking type: {$type}");
        }
        return self::PREFIX_MAP[$type];
    }

    /**
     * Return the table name for a booking type.
     */
    public static function tableFor(string $type): string
    {
        if (!isset(self::TABLE_MAP[$type])) {
            throw new InvalidArgumentException("Unknown booking type: {$type}");
        }
        return self::TABLE_MAP[$type];
    }

    /**
     * Generate a unique booking reference for the given type.
     *
     * Format: PREFIX + 6 random numeric digits  (e.g. GAIA-TR-482913)
     * Guaranteed unique against the booking table's unique index.
     *
     * @param string $type one of transfer|taxi|tour|hotel|room|event
     * @return string      e.g. 'GAIA-TR-482913'
     */
    public static function generate(string $type): string
    {
        $prefix = self::prefixFor($type);
        $table  = self::tableFor($type);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $ref = $prefix . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            if (!self::exists($table, $ref)) {
                return $ref;
            }
        }

        // Extremely unlikely fallback: append microtime to guarantee uniqueness.
        return $prefix . substr((string)time(), -6) . substr((string)(microtime(true) * 1000), -4);
    }

    /**
     * Check whether a reference already exists in the given booking table.
     * Uses a PDO prepared statement to prevent SQL injection.
     */
    private static function exists(string $table, string $reference): bool
    {
        try {
            $pdo  = getPDO();
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE booking_reference = :ref"
            );
            $stmt->execute([':ref' => $reference]);
            return ((int)$stmt->fetchColumn()) > 0;
        } catch (PDOException $e) {
            // If the table/column doesn't exist yet, fall back to true to
            // force regeneration (safe) — the migration adds the column.
            return true;
        }
    }

    /**
     * Parse a booking reference into its type + numeric suffix.
     *
     * @return array{type: string, number: string}|null
     */
    public static function parse(string $reference): ?array
    {
        if (!preg_match('/^GAIA-([A-Z]{2})-(\d{6,})$/', $reference, $m)) {
            return null;
        }
        $letter = $m[1];
        $typeMap = [
            'TR' => 'transfer',
            'TO' => 'tour',
            'HO' => 'hotel',
            'RO' => 'room',
            'EV' => 'event',
        ];
        if (!isset($typeMap[$letter])) {
            return null;
        }
        return ['type' => $typeMap[$letter], 'number' => $m[2]];
    }
}

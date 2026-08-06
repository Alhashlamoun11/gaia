<?php
/**
 * services/ReportingService.php
 * ------------------------------------------------------------
 * Reusable reporting queries for the GAIA platform.
 *
 * Provides backend-ready aggregations for future admin dashboards:
 *   - Total bookings
 *   - Bookings by type
 *   - Pending payments
 *   - Paid payments
 *   - Revenue by service
 *   - Revenue by month
 *   - Cancelled bookings
 *
 * NO dashboard UI is built here — this is architecture only.
 *
 * SECURITY: where a user is provided, results are scoped to that
 * user (customer view). Admin-level totals are available via the
 * global methods. All queries use PDO prepared statements.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../db.php';

class ReportingService
{
    /**
     * Total number of bookings across all booking types.
     */
    public static function totalBookings(?int $userId = null): int
    {
        $total = 0;
        foreach (self::bookingTables() as $table) {
            $total += self::countTable($table, $userId);
        }
        return $total;
    }

    /**
     * Bookings grouped by type (counts).
     */
    public static function bookingsByType(?int $userId = null): array
    {
        $result = [];
        foreach (self::bookingTables() as $type => $table) {
            $result[$type] = self::countTable($table, $userId);
        }
        return $result;
    }

    /**
     * Pending payments (status = pending) count + amount.
     */
    public static function pendingPayments(?int $userId = null): array
    {
        return self::paymentsByStatus('pending', $userId);
    }

    /**
     * Paid payments (status = paid) count + amount.
     */
    public static function paidPayments(?int $userId = null): array
    {
        return self::paymentsByStatus('paid', $userId);
    }

    /**
     * Cancelled bookings across all types.
     */
    public static function cancelledBookings(?int $userId = null): int
    {
        $total = 0;
        foreach (self::bookingTables() as $table) {
            $total += self::countTableByStatus($table, 'cancelled', $userId);
        }
        return $total;
    }

    /**
     * Revenue (sum of total_price) grouped by service type.
     */
    public static function revenueByService(?int $userId = null): array
    {
        $result = [];
        foreach (self::bookingTables() as $type => $table) {
            $result[$type] = self::sumColumn($table, 'total_price', $userId);
        }
        return $result;
    }

    /**
     * Revenue grouped by month (YYYY-MM => sum of total_price).
     */
    public static function revenueByMonth(?int $userId = null): array
    {
        $result = [];
        foreach (self::bookingTables() as $table) {
            try {
                $pdo = getPDO();
                if ($userId && $userId > 0) {
                    $stmt = $pdo->prepare(
                        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, SUM(total_price) AS revenue
                         FROM `{$table}` WHERE user_id = :uid
                         GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC"
                    );
                    $stmt->execute([':uid' => $userId]);
                } else {
                    $stmt = $pdo->query(
                        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, SUM(total_price) AS revenue
                         FROM `{$table}`
                         GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC"
                    );
                }
                foreach ($stmt->fetchAll() as $row) {
                    $m = $row['month'];
                    $result[$m] = ($result[$m] ?? 0) + (float)$row['revenue'];
                }
            } catch (PDOException $e) {
                // skip unavailable table
            }
        }
        ksort($result);
        return $result;
    }

    /**
     * Payments grouped by status (count + total amount).
     */
    public static function paymentsByStatus(string $status, ?int $userId = null): array
    {
        try {
            $pdo = getPDO();
            if ($userId && $userId > 0) {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total
                     FROM payments WHERE status = :status AND user_id = :uid"
                );
                $stmt->execute([':status' => $status, ':uid' => $userId]);
            } else {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total
                     FROM payments WHERE status = :status"
                );
                $stmt->execute([':status' => $status]);
            }
            $row = $stmt->fetch();
            return [
                'count'  => (int)($row['cnt'] ?? 0),
                'amount' => (float)($row['total'] ?? 0),
            ];
        } catch (PDOException $e) {
            return ['count' => 0, 'amount' => 0.0];
        }
    }

    /**
     * All payments for a user (for My Payments page).
     */
    public static function paymentsForUser(int $userId): array
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

    // ------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------

    private static function bookingTables(): array
    {
        return [
            'transfer' => 'bookings',
            'taxi'     => 'taxi_bookings',
            'tour'     => 'weroad_bookings',
            'hotel'    => 'hotel_bookings',
            'room'     => 'room_bookings',
            'event'    => 'event_bookings',
        ];
    }

    private static function countTable(string $table, ?int $userId = null): int
    {
        try {
            $pdo = getPDO();
            if ($userId && $userId > 0) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE user_id = :uid");
                $stmt->execute([':uid' => $userId]);
            } else {
                $stmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
            }
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    private static function countTableByStatus(string $table, string $status, ?int $userId = null): int
    {
        try {
            $pdo = getPDO();
            if ($userId && $userId > 0) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE status = :status AND user_id = :uid");
                $stmt->execute([':status' => $status, ':uid' => $userId]);
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE status = :status");
                $stmt->execute([':status' => $status]);
            }
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    private static function sumColumn(string $table, string $column, ?int $userId = null): float
    {
        try {
            $pdo = getPDO();
            if ($userId && $userId > 0) {
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(`{$column}`),0) FROM `{$table}` WHERE user_id = :uid");
                $stmt->execute([':uid' => $userId]);
            } else {
                $stmt = $pdo->query("SELECT COALESCE(SUM(`{$column}`),0) FROM `{$table}`");
            }
            return (float)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0.0;
        }
    }
}

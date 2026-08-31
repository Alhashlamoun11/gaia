<?php
// admin/api/analytics.php
// Provides JSON analytics data for the super admin dashboard.
// Accessible via: /admin/api/analytics.php?section=hotels&start=2023-01-01&end=2023-12-31

require_once __DIR__ . '/../_bootstrap.php';
require_admin(); // ensure super admin

$pdo = getPDO();
$section = $_GET['section'] ?? '';
$start = $_GET['start'] ?? null; // YYYY-MM-DD
$end   = $_GET['end'] ?? null;

function dateRangeClause($col, $start, $end) {
    $parts = [];
    if ($start) $parts[] = "$col >= "$start"";
    if ($end) $parts[] = "$col <= "$end"";
    return $parts ? ' AND ' . implode(' AND ', $parts) : '';
}

header('Content-Type: application/json');

switch ($section) {
    case 'hotels':
        // Occupancy rate (rooms occupied / total rooms) for the selected period
        $roomsTotal = (int)$pdo->query('SELECT COUNT(*) FROM hotel_rooms')->fetchColumn();
        // Assume a booking occupies a room for its duration; we'll count distinct room_ids in hotel_bookings
        $dateClause = dateRangeClause('created_at', $start, $end);
        $occupied = (int)$pdo->query("SELECT COUNT(DISTINCT room_id) FROM hotel_bookings WHERE 1=1 $dateClause")->fetchColumn();
        $occupancyRate = $roomsTotal ? round(($occupied / $roomsTotal) * 100, 2) : 0;
        // Average revenue per reservation (hotel_bookings)
        $revenue = (float)$pdo->query('SELECT COALESCE(SUM(total_price),0) FROM hotel_bookings')->fetchColumn();
        $bookings = (int)$pdo->query('SELECT COUNT(*) FROM hotel_bookings')->fetchColumn();
        $avgRevenue = $bookings ? round($revenue / $bookings, 2) : 0;
        echo json_encode([
            'occupancyRate' => $occupancyRate,
            'averageRevenuePerReservation' => $avgRevenue,
        ]);
        break;
    case 'payments':
        // Payment success rate: completed / (completed + pending)
        $pending = (int)$pdo->query('SELECT COUNT(*) FROM payments WHERE status = "pending"')->fetchColumn();
        $completed = (int)$pdo->query('SELECT COUNT(*) FROM payments WHERE status = "paid"')->fetchColumn();
        $total = $pending + $completed;
        $successRate = $total ? round(($completed / $total) * 100, 2) : 0;
        // Pending vs completed amounts for trend (last 30 days)
        $dateClause = dateRangeClause('created_at', $start, $end);
        $trend = $pdo->query("SELECT DATE(created_at) as d, SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) as paid, SUM(CASE WHEN status='pending' THEN amount ELSE 0 END) as pending FROM payments WHERE 1=1 $dateClause GROUP BY d ORDER BY d")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'successRate' => $successRate,
            'trend' => $trend,
        ]);
        break;
    case 'reservations':
        // Total bookings across all services (already in ReportingService)
        $totalBookings = ReportingService::totalBookings();
        // Cancellation rate: count where status='cancelled'
        $cancelled = 0;
        foreach (['bookings','weroad_bookings','hotel_bookings','room_bookings','event_bookings'] as $tbl) {
            $cancelled += (int)$pdo->query("SELECT COUNT(*) FROM `$tbl` WHERE status='cancelled'")->fetchColumn();
        }
        $cancellationRate = $totalBookings ? round(($cancelled / $totalBookings) * 100, 2) : 0;
        // Average booking value (revenue / totalBookings)
        $revenue = 0.0;
        foreach (['bookings','weroad_bookings','hotel_bookings','room_bookings','event_bookings','taxi_bookings'] as $tbl) {
            $revenue += (float)$pdo->query("SELECT COALESCE(SUM(total_price),0) FROM `$tbl`")->fetchColumn();
        }
        $avgBookingValue = $totalBookings ? round($revenue / $totalBookings, 2) : 0;
        echo json_encode([
            'totalBookings' => $totalBookings,
            'cancellationRate' => $cancellationRate,
            'averageBookingValue' => $avgBookingValue,
        ]);
        break;
    case 'users':
        // New users per period
        $dateClause = dateRangeClause('created_at', $start, $end);
        $newUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE 1=1 $dateClause")->fetchColumn();
        echo json_encode([
            'newUsers' => $newUsers,
        ]);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid section parameter']);
}
?>

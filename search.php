<?php
/**
 * search.php
 * ------------------------------------------------------------
 * Native PHP script that queries available routes and car
 * classes based on the user's search input.
 *
 * Accepts via GET (or POST):
 *   - origin      : origin city / airport name
 *   - destination : destination city / hotel name
 *   - date        : pickup date (YYYY-MM-DD)  [optional]
 *   - passengers  : number of passengers       [optional]
 *
 * Returns JSON:
 *   {
 *     "success": true,
 *     "routes": [ ... ],
 *     "car_classes": [ ... ]
 *   }
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

// --- Read & sanitize input ---
$origin      = trim($_GET['origin']      ?? $_POST['origin']      ?? '');
$destination = trim($_GET['destination'] ?? $_POST['destination'] ?? '');
$date        = trim($_GET['date']        ?? $_POST['date']        ?? '');
$passengers  = (int)($_GET['passengers'] ?? $_POST['passengers'] ?? 1);

// Basic validation
if ($origin === '' || $destination === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Origin and destination are required.',
    ]);
    exit;
}

try {
    $pdo = getPDO();

// --- 1) Find matching routes (LIKE search on origin/destination) ---
    // "LIKE" allows partial matches like "Milan" -> "Milan Bergamo Airport".
    // NOTE: with native prepared statements (EMULATE_PREPARES=false), a named
    // parameter cannot be reused twice, so we use two separate placeholders.
    $sql = "SELECT * FROM routes
            WHERE is_active = 1
              AND (origin LIKE :originA OR destination LIKE :originB)
            ORDER BY base_price ASC";

    $stmt = $pdo->prepare($sql);
    $like = '%' . $origin . '%';
    $stmt->bindValue(':originA', $like, PDO::PARAM_STR);
    $stmt->bindValue(':originB', $like, PDO::PARAM_STR);
    $stmt->execute();
    $routes = $stmt->fetchAll();

    // --- 2) Find available car classes for the passenger count ---
    // We only return classes whose capacity can hold the passengers.
    $sql = "SELECT *
            FROM car_classes
            WHERE is_active = 1
              AND passenger_capacity >= :passengers
            ORDER BY price_multiplier ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':passengers', max(1, $passengers), PDO::PARAM_INT);
    $stmt->execute();
    $carClasses = $stmt->fetchAll();

    // --- 3) Build the response ---
    echo json_encode([
        'success'    => true,
        'date'       => $date,
        'passengers' => $passengers,
        'routes'     => $routes,
        'car_classes'=> $carClasses,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
    ]);
}

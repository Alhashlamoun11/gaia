<?php
/**
 * validate_promo.php
 * ------------------------------------------------------------
 * Simple PHP script to check if a promo code exists, is active,
 * and within its valid date range / usage limit.
 *
 * Accepts via GET or POST:
 *   - code : the promo code string (case-insensitive)
 *
 * Returns JSON:
 *   {
 *     "success": true,
 *     "code": "WELCOME10",
 *     "discount_type": "percent",
 *     "discount_value": 10.00,
 *     "message": "Promo code applied!"
 *   }
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

// --- Read & sanitize input ---
$code = strtoupper(trim($_GET['code'] ?? $_POST['code'] ?? ''));

if ($code === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Promo code is required.',
    ]);
    exit;
}

try {
    $pdo = getPDO();

    // Look up the promo code (case-insensitive match).
    $sql = "SELECT * FROM promo_codes WHERE code = :code LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':code', $code, PDO::PARAM_STR);
    $stmt->execute();
    $promo = $stmt->fetch();

    // 1) Code must exist
    if (!$promo) {
        echo json_encode([
            'success' => false,
            'message' => 'Promo code not found.',
        ]);
        exit;
    }

    // 2) Code must be active
    if ((int)$promo['is_active'] !== 1) {
        echo json_encode([
            'success' => false,
            'message' => 'This promo code is not active.',
        ]);
        exit;
    }

    // 3) Usage limit must not be exceeded
    if ((int)$promo['used_count'] >= (int)$promo['max_uses']) {
        echo json_encode([
            'success' => false,
            'message' => 'This promo code has reached its usage limit.',
        ]);
        exit;
    }

    // 4) Date range check (if valid_from / valid_until are set)
    $today = date('Y-m-d');
    if ($promo['valid_from'] !== null && $today < $promo['valid_from']) {
        echo json_encode([
            'success' => false,
            'message' => 'This promo code is not valid yet.',
        ]);
        exit;
    }
    if ($promo['valid_until'] !== null && $today > $promo['valid_until']) {
        echo json_encode([
            'success' => false,
            'message' => 'This promo code has expired.',
        ]);
        exit;
    }

    // --- All checks passed ---
    echo json_encode([
        'success'        => true,
        'code'           => $promo['code'],
        'discount_type'  => $promo['discount_type'],
        'discount_value' => (float)$promo['discount_value'],
        'message'        => 'Promo code applied!',
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
    ]);
}

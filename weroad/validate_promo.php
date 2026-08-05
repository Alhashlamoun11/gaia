<?php
/**
 * validate_promo.php — WeRoad promo code validation
 * ------------------------------------------------------------
 * Checks if a promo code exists, is active, within date range,
 * and not over its usage limit. Returns JSON.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$code = strtoupper(trim($_GET['code'] ?? $_POST['code'] ?? ''));

if ($code === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Promo code is required.']);
    exit;
}

try {
    $pdo = getWeRoadPDO();

    $sql = 'SELECT * FROM weroad_promo_codes WHERE code = :code LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':code' => $code]);
    $promo = $stmt->fetch();

    if (!$promo) {
        echo json_encode(['success' => false, 'message' => 'Promo code not found.']);
        exit;
    }
    if ((int)$promo['is_active'] !== 1) {
        echo json_encode(['success' => false, 'message' => 'This promo code is not active.']);
        exit;
    }
    if ((int)$promo['used_count'] >= (int)$promo['max_uses']) {
        echo json_encode(['success' => false, 'message' => 'This promo code has reached its usage limit.']);
        exit;
    }

    $today = date('Y-m-d');
    if ($promo['valid_from'] !== null && $today < $promo['valid_from']) {
        echo json_encode(['success' => false, 'message' => 'This promo code is not valid yet.']);
        exit;
    }
    if ($promo['valid_until'] !== null && $today > $promo['valid_until']) {
        echo json_encode(['success' => false, 'message' => 'This promo code has expired.']);
        exit;
    }

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

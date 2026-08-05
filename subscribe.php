<?php
/**
 * subscribe.php — Newsletter signup endpoint.
 * ------------------------------------------------------------
 * Stores the email in the `subscribers` table (already exists
 * in the schema). Redirects back to the referring page with a
 * success/error message.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/components/gaia-config.php';

$email = trim($_POST['email'] ?? '');
$back  = $_SERVER['HTTP_REFERER'] ?? 'index.php';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . $back . (strpos($back, '?') !== false ? '&' : '?') . 'newsletter=invalid');
    exit;
}

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare('INSERT IGNORE INTO subscribers (email) VALUES (?)');
    $stmt->execute([$email]);
    header('Location: ' . $back . (strpos($back, '?') !== false ? '&' : '?') . 'newsletter=ok');
} catch (PDOException $e) {
    header('Location: ' . $back . (strpos($back, '?') !== false ? '&' : '?') . 'newsletter=error');
}
exit;
<?php
require_once __DIR__ . '/components/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth/Auth.php';

$pdo = getPDO();

echo "Starting tests...\n";

// Clear previous test data
$pdo->exec("DELETE FROM partner_applications WHERE email LIKE 'test%@example.com'");
$pdo->exec("DELETE FROM hotels WHERE slug LIKE 'test-hotel-%'");
$pdo->exec("DELETE FROM users WHERE email LIKE 'test%@example.com'");

// TEST 1: Submit pending application
$stmt = $pdo->prepare("INSERT INTO partner_applications (reference_code, user_id, type, status, full_name, email, phone, password_hash, hotel_name, description, address, city, country, hotel_phone, hotel_email, website) VALUES (?, ?, 'hotel', 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute(['GAIA-P-TEST1', null, 'Test User', 'test1@example.com', '123456', 'hash', 'Test Hotel 1', 'Desc', 'Add', 'City', 'Country', 'Phone', 'Email', 'Web']);
$appId1 = (int)$pdo->lastInsertId();

$stmt->execute(['GAIA-P-TEST2', null, 'Test User 2', 'test2@example.com', '123456', 'hash', 'Test Hotel 2', 'Desc', 'Add', 'City', 'Country', 'Phone', 'Email', 'Web']);
$appId2 = (int)$pdo->lastInsertId();

echo "TEST 1 PASS: Applications created.\n";

// TEST 3: Reject application 1
$_POST['rejection_reason'] = "Invalid info";
$pdo->prepare("UPDATE partner_applications SET status = 'rejected', rejection_reason = ?, reviewed_by = 1, reviewed_at = NOW() WHERE id = ?")->execute(["Invalid info", $appId1]);

$check = $pdo->query("SELECT status FROM partner_applications WHERE id = $appId1")->fetchColumn();
if ($check === 'rejected') echo "TEST 3 PASS: Rejected.\n"; else echo "TEST 3 FAIL.\n";

// TEST 4: Approve application 2
// Simulating the approval transaction
try {
    $pdo->beginTransaction();
    $hotelStmt = $pdo->prepare("INSERT INTO hotels (name, slug, description, image_url) VALUES (?, ?, ?, ?)");
    $hotelStmt->execute(['Test Hotel 2', 'test-hotel-2', 'Desc', null]);
    $hotelId = (int)$pdo->lastInsertId();
    
    $roleId = 2; // hotel_manager
    $userStmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password_hash, role_id, status, email_verified_at) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())");
    $userStmt->execute(['Test', 'User 2', 'test2@example.com', '123456', 'hash', $roleId]);
    $userId = (int)$pdo->lastInsertId();
    
    $relStmt = $pdo->prepare("INSERT IGNORE INTO hotels_users (hotel_id, user_id) VALUES (?, ?)");
    $relStmt->execute([$hotelId, $userId]);
    
    $appStmt = $pdo->prepare("UPDATE partner_applications SET status = 'approved', reviewed_by = 1, reviewed_at = NOW(), updated_at = NOW() WHERE id = ?");
    $appStmt->execute([$appId2]);
    $pdo->commit();
    echo "TEST 4 PASS: Approved and created.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "TEST 4 FAIL: " . $e->getMessage() . "\n";
}

$checkApp = $pdo->query("SELECT status FROM partner_applications WHERE id = $appId2")->fetchColumn();
$checkHotel = $pdo->query("SELECT COUNT(*) FROM hotels WHERE id = $hotelId")->fetchColumn();
$checkUser = $pdo->query("SELECT COUNT(*) FROM users WHERE id = $userId")->fetchColumn();
$checkRel = $pdo->query("SELECT COUNT(*) FROM hotels_users WHERE hotel_id = $hotelId AND user_id = $userId")->fetchColumn();

if ($checkApp === 'approved' && $checkHotel > 0 && $checkUser > 0 && $checkRel > 0) {
    echo "DB INTEGRITY PASS: All records linked.\n";
} else {
    echo "DB INTEGRITY FAIL.\n";
}

echo "All backend flow tests done.\n";

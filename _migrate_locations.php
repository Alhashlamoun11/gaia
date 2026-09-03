<?php
require_once __DIR__ . '/db.php';

$pdo = getPDO();

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pickup_locations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB;
    ");
    
    // Check if pickup_address exists in bookings
    $stmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'pickup_address'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN pickup_address VARCHAR(255) NULL AFTER destination");
    }
    
    // Seed some initial data if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM pickup_locations");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO pickup_locations (name, sort_order) VALUES ('Queen Alia International Airport (AMM)', 1)");
        $pdo->exec("INSERT INTO pickup_locations (name, sort_order) VALUES ('Amman Civil Airport (Marka)', 2)");
        $pdo->exec("INSERT INTO pickup_locations (name, sort_order) VALUES ('King Hussein Bridge', 3)");
        $pdo->exec("INSERT INTO pickup_locations (name, sort_order) VALUES ('Amman City Center (Any Hotel)', 4)");
    }
    
    echo "Migration successful!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<?php
require_once __DIR__ . '/db.php';
$pdo = getPDO();
$stmt = $pdo->query("DESCRIBE hotel_rooms");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $c) {
    echo $c['Field'] . " - " . $c['Type'] . "\n";
}

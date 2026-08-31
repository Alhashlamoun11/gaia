<?php
require __DIR__ . '/db.php';
$pdo = getPDO();

function dumpTable($pdo, $table) {
    echo "=== $table ===\n";
    try {
        $cols = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
        foreach($cols as $c) {
            echo "{$c['Field']} - {$c['Type']}\n";
        }
    } catch (Exception $e) {
        echo "Table does not exist\n";
    }
    echo "\n";
}

dumpTable($pdo, 'hotels_users');
dumpTable($pdo, 'roles');
dumpTable($pdo, 'users');

<?php
require_once __DIR__ . '/db.php';
$pdo = getPDO();
$sql = file_get_contents(__DIR__ . '/schema-migration-19.sql');
$pdo->exec($sql);
echo "Migration applied\n";

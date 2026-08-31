<?php
require 'db.php';
$pdo = getPDO();

echo "=== hotels columns ===\n";
$r = $pdo->query('SHOW COLUMNS FROM hotels');
foreach($r->fetchAll() as $c) echo "  ".$c['Field']."\n";

echo "\n=== users columns ===\n";
$r = $pdo->query('SHOW COLUMNS FROM users');
foreach($r->fetchAll() as $c) echo "  ".$c['Field']."\n";

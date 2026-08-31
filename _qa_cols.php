<?php
require_once __DIR__ . '/db.php';
$pdo = getPDO();
$r = $pdo->query('SHOW COLUMNS FROM password_resets');
foreach($r->fetchAll() as $c) echo $c['Field']." | ".$c['Type']." | null=".$c['Null']." | default=".$c['Default']."\n";

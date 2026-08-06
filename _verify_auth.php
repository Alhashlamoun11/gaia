<?php
require __DIR__ . '/db.php';
$pdo = getPDO();

echo "=== Roles ===\n";
foreach ($pdo->query("SELECT id, name, slug FROM roles") as $r) {
    echo "  {$r['name']} ({$r['slug']})\n";
}

echo "\n=== Permissions ===\n";
foreach ($pdo->query("SELECT id, name, slug FROM permissions") as $r) {
    echo "  {$r['name']} ({$r['slug']})\n";
}

echo "\n=== Role permissions count ===\n";
$rp = $pdo->query("SELECT COUNT(*) c FROM role_permissions")->fetch();
echo "  {$rp['c']} rows\n";

echo "\n=== Users ===\n";
$users = $pdo->query("SELECT id, first_name, last_name, email, role_id, status FROM users")->fetchAll();
foreach ($users as $u) {
    echo "  #{$u['id']} {$u['first_name']} {$u['last_name']} <{$u['email']}> role={$u['role_id']} status={$u['status']}\n";
}

echo "\n=== Admin password check ===\n";
$admin = $pdo->prepare("SELECT password_hash FROM users WHERE email = ?");
$admin->execute(['admin@gaiatours.com']);
$row = $admin->fetch();
if ($row) {
    var_dump(password_verify('Admin@12345', $row['password_hash']));
} else {
    echo "Admin not found.\n";
}

echo "\n=== Tables ===\n";
foreach ($pdo->query("SHOW TABLES") as $t) {
    $name = array_values($t)[0];
    echo "  $name\n";
}

echo "\n=== Existing bookings ALTER check ===\n";
$cols = $pdo->query("SHOW COLUMNS FROM bookings")->fetchAll();
$names = array_column($cols, 'Field');
echo "  bookings has user_id: " . (in_array('user_id', $names) ? 'YES' : 'NO') . "\n";
echo "  bookings has guest_name: " . (in_array('guest_name', $names) ? 'YES' : 'NO') . "\n";

$wcols = $pdo->query("SHOW COLUMNS FROM weroad_bookings")->fetchAll();
$wnames = array_column($wcols, 'Field');
echo "  weroad_bookings has user_id: " . (in_array('user_id', $wnames) ? 'YES' : 'NO') . "\n";

<?php
// _run_migrations.php — execute the SQL migration files against the DB.
// Usage: php _run_migrations.php [all|<number>|<name>]
//   php _run_migrations.php all     — run every migration file in order
//   php _run_migrations.php 9       — run only schema-migration-9.sql
//   php _run_migrations.php auth    — run only schema-migration-auth.sql
require __DIR__ . '/db.php';
$pdo = getPDO();

$files = [
    __DIR__ . '/schema-migration.sql',
    __DIR__ . '/schema-migration-2.sql',
    __DIR__ . '/schema-migration-3.sql',
    __DIR__ . '/schema-migration-4.sql',
    __DIR__ . '/schema-migration-5.sql',
    __DIR__ . '/schema-migration-6.sql',
    __DIR__ . '/schema-migration-7.sql',
__DIR__ . '/schema-migration-8.sql',
    __DIR__ . '/schema-migration-9.sql',
__DIR__ . '/schema-migration-auth.sql',
    __DIR__ . '/schema-migration-10.sql',
    __DIR__ . '/schema-migration-11.sql',
];

// Only run the newest one (this runner is for incremental changes).
// To run all, pass "all" argument.
$target = $argv[1] ?? 'auth';

foreach ($files as $file) {
    $base = basename($file);
    $key = str_replace(['schema-migration-', '.sql'], '', $base);
    $match = false;
    if ($target === 'all') {
        $match = true;
    } elseif (is_numeric($target)) {
        $match = ((int)$key === (int)$target);
    } else {
        // Named target (e.g. 'auth') — match the file key suffix.
        $match = (strtolower($key) === strtolower($target));
    }
    if ($match) {
        echo "Running $base ...\n";
        $sql = file_get_contents($file);
        // Execute statement-by-statement (the file uses DELIMITER-free statements).
        $pdo->exec($sql);
        echo "  OK\n";
    }
}
echo "Done.\n";

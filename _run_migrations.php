<?php
// _run_migrations.php — execute the SQL migration files against the DB.
// Usage: php _run_migrations.php
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
];

// Only run the newest one (this runner is for incremental changes).
// To run all, pass "all" argument.
$target = $argv[1] ?? '8';

foreach ($files as $file) {
    $base = basename($file);
    $num = (int)str_replace(['schema-migration-', '.sql'], '', $base);
    if ($target === 'all' || $num === (int)$target) {
        echo "Running $base ...\n";
        $sql = file_get_contents($file);
        // Execute statement-by-statement (the file uses DELIMITER-free statements).
        $pdo->exec($sql);
        echo "  OK\n";
    }
}
echo "Done.\n";

<?php
/**
 * db.php
 * ------------------------------------------------------------
 * Standard PDO connection setup with error handling.
 * Returns a PDO instance (or throws a PDOException).
 *
 * Usage:
 *   require_once __DIR__ . '/db.php';
 *   $pdo = getPDO();
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/config.php';

/**
 * Create and return a PDO connection to MySQL.
 *
 * @return PDO
 * @throws PDOException if the connection fails
 */
function getPDO(): PDO
{
    // Build the DSN for MySQL using the constants from config.php
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    // PDO options:
    //  - ERRMODE_EXCEPTION : throw exceptions on errors (easiest for debugging)
    //  - ATTR_DEFAULT_FETCH_MODE : fetch associative arrays by default
    //  - ATTR_EMULATE_PREPARES : false -> use real prepared statements
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Log the error and return a friendly message to the caller.
        error_log('Database connection failed: ' . $e->getMessage());
        // Re-throw so the calling script can handle it (e.g. output JSON).
        throw new PDOException('Database connection failed. Please check your settings in config.php.');
    }
}

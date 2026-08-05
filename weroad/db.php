<?php
/**
 * db.php
 * ------------------------------------------------------------
 * Standard PDO connection setup with error handling.
 * Returns a PDO instance (or throws a PDOException).
 *
 * Usage:
 *   require_once __DIR__ . '/db.php';
 *   $pdo = getWeRoadPDO();
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/config.php';

/**
 * Create and return a PDO connection to MySQL.
 *
 * @return PDO
 * @throws PDOException if the connection fails
 */
function getWeRoadPDO(): PDO
{
    // Build the DSN for MySQL using the constants from config.php
    $dsn = 'mysql:host=' . WR_DB_HOST . ';dbname=' . WR_DB_NAME . ';charset=utf8mb4';

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
        $pdo = new PDO($dsn, WR_DB_USER, WR_DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Log the error and throw a friendly message.
        error_log('WeRoad database connection failed: ' . $e->getMessage());
        throw new PDOException('Database connection failed. Please check your settings in config.php.');
    }
}

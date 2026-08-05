<?php
/**
 * config.php
 * ------------------------------------------------------------
 * Global configuration for the WeRoad Tour Booking Platform.
 * Uses the SAME MySQL database as the KiwiTaxi system
 * (gaia_tours), but all tables are prefixed with `weroad_`.
 *
 * Edit the DATABASE_* constants to match your environment.
 * ------------------------------------------------------------
 */

// ---- Database connection settings ----------------------------
define('WR_DB_HOST', '127.0.0.1');
define('WR_DB_NAME', 'gaia_tours');
define('WR_DB_USER', 'root');
define('WR_DB_PASS', '');

// ---- Table prefix (to avoid clashes with the KiwiTaxi tables) --
define('WR_TABLE_PREFIX', 'weroad_');

// ---- Pricing constants (used by process_booking.php) ----------
// Optional extras at checkout
define('WR_PRICE_FLEXIBLE_CANCELLATION', 99.00);  // EUR
define('WR_PRICE_PRIVATE_ROOM', 247.00);          // EUR

// ---- Misc ------------------------------------------------------
define('WR_CURRENCY_SYMBOL', 'EUR');
define('WR_BOOKING_CODE_PREFIX', 'WR-');

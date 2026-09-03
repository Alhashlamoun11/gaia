<?php
/**
 * config.php
 * ------------------------------------------------------------
 * Global configuration for the native PHP + MySQL
 * Car Rental / Transfer System.
 *
 * Edit the DATABASE_* constants to match your environment.
 * ------------------------------------------------------------
 */

// ---- Database connection settings ----------------------------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'gaia_tours');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- Pricing constants (used by process_booking.php) ----------
// Return trip surcharge (fixed amount added on top of the one-way fare).
define('RETURN_TRIP_FEE', 50.00);

// Add-on unit prices (USD)
define('PRICE_CHILD_SEAT', 10.00);   // per child seat
define('PRICE_WATER_BOTTLE', 3.00);  // per bottle of water
define('PRICE_PET_CARRIER', 15.00);  // flat fee for pet carrier
define('PRICE_WOMEN_DRIVER', 20.00); // flat fee for women driver preference

// ---- Misc ------------------------------------------------------
define('CURRENCY', 'USD');
define('BOOKING_CODE_PREFIX', 'KT-');

<?php
/**
 * hotel/_bootstrap.php
 * ------------------------------------------------------------
 * Shared bootstrap for the GAIA Hotel Partner Portal.
 *
 * Enforces the hotel_manager guard and securely fetches the
 * assigned hotel_id to prevent IDOR across all hotel views.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../components/bootstrap.php';
require_once __DIR__ . '/../components/gaia-config.php';
require_once __DIR__ . '/../auth/Auth.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../db.php';

// Reusable admin services (lazy-safe, some may not exist everywhere).
require_once __DIR__ . '/../services/ReportingService.php';
require_once __DIR__ . '/../services/BookingSummaryService.php';
require_once __DIR__ . '/../services/BookingService.php';
require_once __DIR__ . '/../services/PaymentService.php';
require_once __DIR__ . '/../services/InvoiceService.php';

require_once __DIR__ . '/../admin/helpers/AdminQuery.php';
require_once __DIR__ . '/../admin/helpers/CrudService.php';

/**
 * Enforce the hotel_manager guard and return the assigned hotel_id.
 * Guests go to /login.php; non-managers go home.
 */
function require_hotel_manager()
{
    Auth::startSession();
    if (!Auth::check()) {
        Auth::loginViaRememberToken();
    }
    if (!Auth::check()) {
        $target = 'hotel/index.php';
        header('Location: ' . (function_exists('gaia_url') ? gaia_url('login.php') : 'login.php') . '?redirect=' . rawurlencode($target));
        exit;
    }
    if (Auth::role() !== 'hotel_manager') {
        header('Location: ' . (function_exists('gaia_url') ? gaia_url('index.php') : 'index.php'));
        exit;
    }

    // Securely retrieve the assigned hotel ID
    $userId = auth_id();
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT hotel_id FROM hotels_users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $hotelId = $stmt->fetchColumn();

    if (!$hotelId) {
        // Logged in as manager but no hotel assigned
        die(htmlspecialchars(t('hotel.unauthorized', 'Unauthorized: No hotel assigned.')));
    }

    return (int)$hotelId;
}

/**
 * Return the current hotel_manager user row (or null).
 */
function hotel_manager_user()
{
    static $manager = null;
    if ($manager === null) {
        $manager = Auth::user();
    }
    return $manager;
}

/**
 * Return the current hotel row (or null).
 */
function current_hotel()
{
    static $hotel = null;
    if ($hotel === null) {
        $hotelId = require_hotel_manager();
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT * FROM hotels WHERE id = ?");
        $stmt->execute([$hotelId]);
        $hotel = $stmt->fetch() ?: null;
    }
    return $hotel;
}

/**
 * Absolute site-root URL for a hotel portal resource.
 */
function hotel_url(string $path = ''): string
{
    $base = '/hotel';
    return $base . '/' . ltrim($path, '/');
}

/**
 * One-time flash message helper (session based).
 */
function hotel_flash(?string $msg = null, string $type = 'success')
{
    Auth::startSession();
    if ($msg !== null) {
        $_SESSION['hotel_flash'] = ['type' => $type, 'msg' => $msg];
        return;
    }
    if (!empty($_SESSION['hotel_flash'])) {
        $f = $_SESSION['hotel_flash'];
        unset($_SESSION['hotel_flash']);
        return $f;
    }
    return null;
}

/**
 * Read the current pending flash (without clearing) — used by layout.
 */
function hotel_flash_peek()
{
    Auth::startSession();
    return $_SESSION['hotel_flash'] ?? null;
}

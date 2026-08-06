<?php
/**
 * auth/helpers.php
 * ------------------------------------------------------------
 * Template helpers for the auth/account screens.
 *
 * Provides:
 *   - auth_user()      : current user row (or null)
 *   - auth_check()     : whether a user is authenticated
 *   - auth_id()        : current user id (or 0)
 *   - auth_name()      : display name
 *   - auth_email()     : current email
 *   - auth_role()      : role slug
 *   - auth_avatar()    : avatar URL or generated initials
 *   - e()              : output escaping shorthand
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/Auth.php';

if (!function_exists('auth_user')) {
    function auth_user()
    {
        return Auth::user();
    }
}

if (!function_exists('auth_check')) {
    function auth_check(): bool
    {
        return Auth::check();
    }
}

if (!function_exists('auth_id')) {
    function auth_id(): int
    {
        $u = Auth::user();
        return $u ? (int)$u['id'] : 0;
    }
}

if (!function_exists('auth_name')) {
    function auth_name(): string
    {
        $u = Auth::user();
        return $u ? trim($u['first_name'] . ' ' . $u['last_name']) : '';
    }
}

if (!function_exists('auth_email')) {
    function auth_email(): string
    {
        $u = Auth::user();
        return $u ? $u['email'] : '';
    }
}

if (!function_exists('auth_role')) {
    function auth_role(): string
    {
        return Auth::role();
    }
}

if (!function_exists('auth_avatar')) {
    function auth_avatar(): string
    {
        $u = Auth::user();
        if ($u && !empty($u['avatar'])) {
            return $u['avatar'];
        }
        // Generated letter avatar via initials
        if ($u) {
            $initials = strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1));
            return 'data:image/svg+xml;utf8,' . rawurlencode(
                '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96">'
                . '<rect width="96" height="96" rx="48" fill="#1b2a4a"/>'
                . '<text x="48" y="60" font-family="Arial" font-size="36" font-weight="700" fill="#ffffff" text-anchor="middle">'
                . htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') . '</text></svg>'
            );
        }
        return '';
    }
}

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('booking_type_label')) {
    /**
     * Translate a booking type/status key to a localized label.
     */
    function booking_type_label(string $type): string
    {
        return t('account.booking_type_' . $type, $type);
    }
}

if (!function_exists('booking_status_label')) {
    function booking_status_label(string $status): string
    {
        return t('account.status_' . $status, $status);
    }
}

if (!function_exists('booking_status_list')) {
    /**
     * The canonical, unified list of booking statuses shared by all
     * booking types (tours, hotels, rooms, events, transfers).
     */
    function booking_status_list(): array
    {
        return [
            'pending',
            'awaiting_payment',
            'paid',
            'confirmed',
            'completed',
            'cancelled',
            'refunded',
        ];
    }
}

if (!function_exists('payment_status_list')) {
    /**
     * The canonical list of payment statuses.
     */
    function payment_status_list(): array
    {
        return [
            'pending',
            'authorized',
            'paid',
            'failed',
            'cancelled',
            'refunded',
        ];
    }
}

if (!function_exists('invoice_status_list')) {
    /**
     * The canonical list of invoice statuses.
     */
    function invoice_status_list(): array
    {
        return ['draft', 'issued', 'paid', 'void', 'cancelled'];
    }
}

if (!function_exists('normalize_booking_status')) {
    /**
     * Map a (possibly legacy) booking status to the canonical set.
     * Unknown statuses fall back to 'pending'.
     */
    function normalize_booking_status($status): string
    {
        $status = strtolower(trim((string)$status));
        $allowed = booking_status_list();
        return in_array($status, $allowed, true) ? $status : 'pending';
    }
}

if (!function_exists('normalize_payment_status')) {
    /**
     * Map a (possibly legacy) payment status to the canonical set.
     */
    function normalize_payment_status($status): string
    {
        $status = strtolower(trim((string)$status));
        $allowed = payment_status_list();
        return in_array($status, $allowed, true) ? $status : 'pending';
    }
}

if (!function_exists('booking_reference_prefix')) {
    /**
     * Return the GAIA reference prefix for a booking type.
     *   transfer/taxi -> GAIA-TR-  tour -> GAIA-TO-
     *   hotel -> GAIA-HO-  room -> GAIA-RO-  event -> GAIA-EV-
     */
    function booking_reference_prefix(string $type): string
    {
        static $map = [
            'transfer' => 'GAIA-TR-',
            'taxi'     => 'GAIA-TR-',
            'tour'     => 'GAIA-TO-',
            'hotel'    => 'GAIA-HO-',
            'room'     => 'GAIA-RO-',
            'event'    => 'GAIA-EV-',
        ];
        return $map[$type] ?? 'GAIA-';
    }
}

if (!function_exists('payment_status_label')) {
    /**
     * Localized label for a payment status.
     */
    function payment_status_label(string $status): string
    {
        return t('account.status_' . $status, $status);
    }
}

if (!function_exists('invoice_status_label')) {
    /**
     * Localized label for an invoice status.
     */
    function invoice_status_label(string $status): string
    {
        return t('account.status_' . $status, $status);
    }
}

if (!function_exists('booking_status_badge_class')) {
    /**
     * Return the CSS badge class for a booking status.
     */
    function booking_status_badge_class(string $status): string
    {
        $class = 'badge badge-' . htmlspecialchars(normalize_booking_status($status));
        return $class;
    }
}

if (!function_exists('payment_status_badge_class')) {
    /**
     * Return the CSS badge class for a payment status.
     */
    function payment_status_badge_class(string $status): string
    {
        return 'badge badge-' . htmlspecialchars(normalize_payment_status($status));
    }
}

if (!function_exists('timeline_action_label')) {
    /**
     * Localized label for a booking timeline action.
     */
    function timeline_action_label(string $action): string
    {
        $key = 'timeline.' . strtolower(str_replace(' ', '_', $action));
        return t($key, $action);
    }
}

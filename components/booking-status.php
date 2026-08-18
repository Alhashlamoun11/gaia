<?php
/**
 * components/booking-status.php
 * ------------------------------------------------------------
 * REUSABLE status badge renderer for booking / payment / invoice
 * statuses. Reduces duplicated badge HTML + label resolution.
 *
 * Expected variables (set by the caller):
 *   $status_value  : string raw status value
 *   $status_kind   : string 'booking'|'payment'|'invoice' (default booking)
 * ------------------------------------------------------------
 */
if (!function_exists('normalize_booking_status')) {
    require_once __DIR__ . '/../auth/helpers.php';
}
$status_value = $status_value ?? 'pending';
$status_kind  = $status_kind  ?? 'booking';
$status_kind  = in_array($status_kind, ['booking', 'payment', 'invoice'], true) ? $status_kind : 'booking';

if ($status_kind === 'payment') {
    $badgeClass = function_exists('payment_status_badge_class') ? payment_status_badge_class((string)$status_value) : 'badge';
    $label = function_exists('payment_status_label') ? payment_status_label((string)$status_value) : (string)$status_value;
} elseif ($status_kind === 'invoice') {
    $normalized = (string)$status_value;
    $badgeClass = 'badge badge-' . htmlspecialchars($normalized);
    $label = function_exists('invoice_status_label') ? invoice_status_label($normalized) : $normalized;
} else {
    $badgeClass = function_exists('booking_status_badge_class') ? booking_status_badge_class((string)$status_value) : 'badge badge-' . htmlspecialchars((string)$status_value);
    $label = function_exists('booking_status_label') ? booking_status_label((string)$status_value) : (string)$status_value;
}
?>
<span class="<?= htmlspecialchars($badgeClass) ?>"><?= htmlspecialchars($label) ?></span>

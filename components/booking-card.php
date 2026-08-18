<?php
/**
 * components/booking-card.php
 * ------------------------------------------------------------
 * REUSABLE booking card for the customer portal (used on the
 * dashboard and the unified bookings page).
 *
 * Expected variables (set by the caller):
 *   $booking : array with keys:
 *     booking_type, booking_reference, service_name, amount,
 *     currency, status, created_date, image (optional url),
 *     payment_status (optional), invoice_status (optional)
 *   $show_cancel : bool — render the Cancel button (default false)
 *
 * The card is presentation-only; all data must be loaded through
 * ownership-scoped services by the caller.
 * ------------------------------------------------------------
 */
if (!function_exists('booking_type_label')) {
    require_once __DIR__ . '/../auth/helpers.php';
}
$booking     = $booking ?? [];
$show_cancel = (bool)($show_cancel ?? false);

$type      = (string)($booking['booking_type'] ?? '');
$ref       = (string)($booking['booking_reference'] ?? ($booking['booking_code'] ?? ''));
$status    = normalize_booking_status($booking['status'] ?? 'pending');
$amount    = (float)($booking['amount'] ?? 0);
$currency  = (string)($booking['currency'] ?? 'USD');
$image     = (string)($booking['image'] ?? '');
$date      = (string)($booking['created_date'] ?? '');
$name      = (string)($booking['service_name'] ?? '');
$payStatus = (string)($booking['payment_status'] ?? '');
$invStatus = (string)($booking['invoice_status'] ?? '');

$typeIcon = [
    'tour'     => 'fa-mountain-sun',
    'hotel'    => 'fa-hotel',
    'room'     => 'fa-bed',
    'event'    => 'fa-wand-magic-sparkles',
    'transfer' => 'fa-car',
    'taxi'     => 'fa-taxi',
];
$icon = 'fa-bookmark';
if (isset($typeIcon[$type])) { $icon = $typeIcon[$type]; }

if ($image === '') {
    $image = 'https://images.unsplash.com/photo-1502920917128-1aa500764cbd?w=600&q=80';
}

$detailUrl = gaia_url('account/booking.php') . '?reference=' . urlencode($ref);
?>
<div class="booking-card">
  <div class="booking-card-media">
    <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($name) ?>" loading="lazy" class="lazy">
    <span class="booking-card-type"><i class="fa-solid <?= htmlspecialchars($icon) ?>"></i> <?= htmlspecialchars(booking_type_label($type)) ?></span>
  </div>
  <div class="booking-card-body">
    <div class="booking-card-top">
      <span class="code"><?= htmlspecialchars($ref) ?></span>
      <span class="booking-card-status">
        <?php $status_value = $status; $status_kind = 'booking'; require __DIR__ . '/booking-status.php'; ?>
      </span>
    </div>
    <div class="booking-card-name"><?= htmlspecialchars($name) ?></div>
    <div class="booking-card-meta">
      <span><i class="fa-regular fa-calendar"></i> <?= htmlspecialchars($date) ?></span>
      <span class="booking-card-amount"><?= htmlspecialchars(number_format($amount, 2)) ?> <?= htmlspecialchars($currency) ?></span>
    </div>
    <?php if ($payStatus !== ''): ?>
      <div class="booking-card-sub">
        <?= htmlspecialchars(t('account.payment_status', 'Payment')) ?>:
        <?php $status_value = $payStatus; $status_kind = 'payment'; require __DIR__ . '/booking-status.php'; ?>
      </div>
    <?php endif; ?>
    <?php if ($invStatus !== ''): ?>
      <div class="booking-card-sub">
        <?= htmlspecialchars(t('account.invoice_status', 'Invoice')) ?>:
        <?php $status_value = $invStatus; $status_kind = 'invoice'; require __DIR__ . '/booking-status.php'; ?>
      </div>
    <?php endif; ?>
    <div class="booking-card-actions">
      <a class="btn-link" href="<?= htmlspecialchars($detailUrl) ?>"><i class="fa-solid fa-eye"></i> <?= htmlspecialchars(t('account.view_details')) ?></a>
      <?php if ($show_cancel && in_array($status, ['pending', 'awaiting_payment'], true)): ?>
        <form method="post" action="<?= htmlspecialchars(gaia_url('account/bookings.php')) ?>" class="inline-form" onsubmit="return confirm('<?= htmlspecialchars(t('account.cancel_confirm', 'Cancel this booking?')) ?>');">
          <?php
            require_once __DIR__ . '/../auth/csrf.php';
            echo csrf_field();
          ?>
          <input type="hidden" name="action" value="cancel">
          <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
          <input type="hidden" name="id" value="<?= (int)($booking['id'] ?? 0) ?>">
          <button type="submit" class="btn-link btn-danger-link"><i class="fa-solid fa-ban"></i> <?= htmlspecialchars(t('account.cancel_booking')) ?></button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<style>
.booking-card{background:#fff;border:1px solid var(--line,#e8e6e0);border-radius:16px;overflow:hidden;box-shadow:var(--shadow,0 10px 30px rgba(27,42,74,0.08));display:flex;flex-direction:column;transition:transform .2s,box-shadow .2s;}
.booking-card:hover{transform:translateY(-3px);box-shadow:0 14px 34px rgba(27,42,74,.14);}
.booking-card-media{position:relative;height:150px;overflow:hidden;}
.booking-card-media img{width:100%;height:100%;object-fit:cover;transition:transform .4s;}
.booking-card:hover .booking-card-media img{transform:scale(1.05);}
.booking-card-type{position:absolute;top:10px;left:10px;background:rgba(27,42,74,.85);color:#fff;font-size:11.5px;font-weight:700;padding:5px 10px;border-radius:20px;display:inline-flex;gap:6px;align-items:center;}
.booking-card-body{padding:16px;display:flex;flex-direction:column;gap:8px;flex:1;}
.booking-card-top{display:flex;justify-content:space-between;align-items:center;gap:8px;}
.booking-card-top .code{font-weight:700;font-size:13.5px;}
.booking-card-name{font-weight:600;font-size:15px;font-family:'Playfair Display',serif;line-height:1.3;}
.booking-card-meta{display:flex;justify-content:space-between;align-items:center;gap:8px;font-size:13px;color:var(--muted,#6b7280);}
.booking-card-amount{font-weight:700;color:var(--teal,#1f6f8f);}
.booking-card-sub{font-size:12.5px;color:var(--muted,#6b7280);display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.booking-card-actions{margin-top:auto;padding-top:10px;border-top:1px solid #f0ede6;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;}
.inline-form{display:inline;}
.btn-danger-link{color:#b3261e;}
.btn-danger-link:hover{color:#8f1d17;}
</style>

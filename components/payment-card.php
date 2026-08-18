<?php
/**
 * components/payment-card.php
 * ------------------------------------------------------------
 * REUSABLE payment record card for the customer portal
 * (used on account/payments.php).
 *
 * Expected variables (set by the caller):
 *   $payment : array with keys:
 *     id, booking_type, booking_reference, amount, currency,
 *     payment_method, gateway, status, created_at,
 *     transaction_reference (optional)
 * ------------------------------------------------------------
 */
if (!function_exists('payment_status_label')) {
    require_once __DIR__ . '/../auth/helpers.php';
}
$payment = $payment ?? [];

$id       = (int)($payment['id'] ?? 0);
$ref      = (string)($payment['booking_reference'] ?? '');
$amount   = (float)($payment['amount'] ?? 0);
$currency = (string)($payment['currency'] ?? 'USD');
$method   = (string)($payment['payment_method'] ?? '');
$gateway  = (string)($payment['gateway'] ?? '');
$status   = normalize_payment_status($payment['status'] ?? 'pending');
$date     = (string)($payment['created_at'] ?? '');
$tref     = (string)($payment['transaction_reference'] ?? '');
$ttype    = (string)($payment['booking_type'] ?? '');
?>
<div class="payment-card">
  <div class="payment-card-head">
    <div>
      <span class="code">#<?= htmlspecialchars((string)$id) ?></span>
      <span class="payment-card-meta">
        <?php if ($ref !== ''): ?>
          <a class="btn-link" href="<?= htmlspecialchars(gaia_url('account/booking.php') . '?reference=' . urlencode($ref)) ?>"><?= htmlspecialchars($ref) ?></a>
        <?php else: ?>
          <span class="muted">—</span>
        <?php endif; ?>
      </span>
    </div>
    <div class="payment-card-status">
      <?php $status_value = $status; $status_kind = 'payment'; require __DIR__ . '/booking-status.php'; ?>
    </div>
  </div>
  <div class="payment-card-amount"><?= htmlspecialchars(number_format($amount, 2)) ?> <small><?= htmlspecialchars($currency) ?></small></div>
  <div class="payment-card-grid">
    <?php if ($method !== ''): ?>
      <div class="payment-card-cell"><span><?= htmlspecialchars(t('account.payment_method', 'Method')) ?></span><strong><?= htmlspecialchars($method) ?></strong></div>
    <?php endif; ?>
    <?php if ($gateway !== ''): ?>
      <div class="payment-card-cell"><span><?= htmlspecialchars(t('account.gateway', 'Gateway')) ?></span><strong><?= htmlspecialchars($gateway) ?></strong></div>
    <?php endif; ?>
    <?php if ($tref !== ''): ?>
      <div class="payment-card-cell"><span><?= htmlspecialchars(t('account.transaction_ref', 'Transaction')) ?></span><strong><?= htmlspecialchars($tref) ?></strong></div>
    <?php endif; ?>
    <div class="payment-card-cell"><span><?= htmlspecialchars(t('account.date', 'Date')) ?></span><strong><?= htmlspecialchars($date) ?></strong></div>
  </div>
<?php if (!empty($payment['_show_retry']) && in_array($status, ['pending', 'failed'], true)): ?>
    <div class="payment-card-actions">
      <button type="button" class="btn btn-sm btn-secondary" onclick="alert('<?= htmlspecialchars(t('account.cybersource_ready', 'Online payment will be enabled soon.')) ?>')">
        <i class="fa-solid fa-rotate-right"></i> <?= htmlspecialchars(t('account.retry_payment')) ?>
      </button>
    </div>
  <?php endif; ?>
</div>
<style>
.payment-card{background:#fff;border:1px solid var(--line,#e8e6e0);border-radius:14px;padding:18px;box-shadow:var(--shadow,0 10px 30px rgba(27,42,74,0.05));display:flex;flex-direction:column;gap:12px;}
.payment-card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;}
.payment-card-head .code{font-weight:700;font-size:14px;display:block;}
.payment-card-meta{font-size:13px;color:var(--teal,#1f6f8f);}
.payment-card-status{flex:none;}
.payment-card-amount{font-family:'Playfair Display',serif;font-size:24px;font-weight:800;color:var(--navy,#1b2a4a);}
.payment-card-amount small{font-size:13px;color:var(--muted,#6b7280);font-family:'Inter',sans-serif;font-weight:600;}
.payment-card-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;border-top:1px solid #f0ede6;padding-top:12px;}
.payment-card-cell{display:flex;flex-direction:column;gap:3px;font-size:12.5px;}
.payment-card-cell span{color:var(--muted,#6b7280);text-transform:uppercase;letter-spacing:.3px;font-size:11px;}
.payment-card-cell strong{font-weight:600;word-break:break-word;}
.payment-card-actions{padding-top:12px;border-top:1px solid #f0ede6;}
</style>

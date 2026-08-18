<?php
/**
 * components/booking-summary.php
 * ------------------------------------------------------------
 * REUSABLE booking summary component for the GAIA platform.
 *
 * Renders the unified booking details used by the customer
 * dashboard, admin dashboards, hotel manager dashboards, payments
 * and invoices — for ALL booking types (transfer, tour, hotel,
 * room, event, taxi).
 *
 * Expected variables (populated by the caller):
 *   $summary      : array from BookingSummaryService::getSummary()
 *   $payments     : array from PaymentService::getBookingPayments()  (may be empty)
 *   $invoice      : array|null from InvoiceService::getForBooking()  (may be null)
 *   $timeline     : array from BookingTimelineService::getTimeline() (may be empty)
 *
 * SECURITY: This component is presentation-only. All data must be
 * loaded through ownership-scoped service methods by the caller.
 * Never pass gateway responses here — only payment status/labels.
 *
 * Usage:
 *   require_once __DIR__ . '/../components/booking-summary.php';
 * ------------------------------------------------------------
 */

if (!function_exists('auth_check')) {
    require_once __DIR__ . '/../auth/helpers.php';
}

$summary  = $summary ?? null;
$payments = $payments ?? [];
$invoice  = $invoice ?? null;
$timeline = $timeline ?? [];

if (!$summary) {
    return; // nothing to render
}

// Normalise status labels via existing helpers (fall back to raw value).
$bookingStatusLabel = function_exists('booking_status_label')
    ? booking_status_label((string)($summary['status'] ?? 'pending'))
    : ($summary['status'] ?? 'pending');
$paymentStatusLabel = null;
$invoiceStatusLabel =null;

$currency = $summary['currency'] ?? 'USD';
$amount   = (float)($summary['amount'] ?? 0);
?>

<!-- ===================================================== -->
<!-- UNIFIED BOOKING SUMMARY                               -->
<!-- ===================================================== -->
<div class="booking-summary">

  <!-- Header: booking reference + status -->
  <div class="card">
    <h2><?= htmlspecialchars(t('account.booking_details', 'Booking details')) ?></h2>
    <p class="sub">
      <strong><?= htmlspecialchars($summary['booking_reference'] ?? $summary['booking_code'] ?? '#') ?></strong>
      &nbsp;·&nbsp;
      <span class="badge badge-<?= htmlspecialchars((string)($summary['status'] ?? 'pending')) ?>">
        <?= htmlspecialchars($bookingStatusLabel) ?>
      </span>
    </p>

    <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.type', 'Type')) ?></span><span class="value"><?= htmlspecialchars(function_exists('booking_type_label') ? booking_type_label((string)$summary['booking_type']) : ($summary['booking_type'] ?? '')) ?></span></div>
    <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.service_name', 'Service')) ?></span><span class="value"><?= htmlspecialchars($summary['service_name'] ?? '') ?></span></div>
    <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.booking_date', 'Booking date')) ?></span><span class="value"><?= htmlspecialchars($summary['created_date'] ?? '') ?></span></div>
    <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.customer', 'Customer')) ?></span><span class="value"><?= htmlspecialchars($summary['customer'] ?? '') ?></span></div>
    <div class="list-item total"><span class="label"><?= htmlspecialchars(t('account.amount', 'Amount')) ?></span><span class="value"><?= htmlspecialchars(number_format($amount, 2)) ?> <?= htmlspecialchars($currency) ?></span></div>
  </div>

  <!-- Payment status -->
  <?php if (!empty($payments)): ?>
    <div class="card">
      <h2><?= htmlspecialchars(t('account.payment_status', 'Payment')) ?></h2>
      <?php foreach ($payments as $p): ?>
        <div class="list-item">
          <span class="label">
            <?= htmlspecialchars(t('account.payment_status', 'Payment')) ?>
            #<?= (int)($p['id'] ?? 0) ?>
          </span>
          <span class="value">
            <span class="badge badge-<?= htmlspecialchars((string)($p['status'] ?? 'pending')) ?>">
            </span>
          </span>
        </div>
        <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.amount', 'Amount')) ?></span><span class="value"><?= htmlspecialchars(number_format((float)($p['amount'] ?? 0), 2)) ?> <?= htmlspecialchars($p['currency'] ?? $currency) ?></span></div>
        <?php if (!empty($p['payment_method'])): ?>
          <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.payment_method', 'Method')) ?></span><span class="value"><?= htmlspecialchars($p['payment_method']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($p['transaction_reference'])): ?>
          <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.transaction_ref', 'Transaction')) ?></span><span class="value"><?= htmlspecialchars($p['transaction_reference']) ?></span></div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="card">
      <h2><?= htmlspecialchars(t('account.payment_status', 'Payment')) ?></h2>
      <p class="sub"><?= htmlspecialchars(t('account.no_payment', 'No payment record yet.')) ?></p>
    </div>
  <?php endif; ?>

  <!-- Invoice status -->
  <?php if ($invoice): ?>
    <div class="card">
      <h2><?= htmlspecialchars(t('account.invoice', 'Invoice')) ?></h2>
      <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.invoice_number', 'Invoice number')) ?></span><span class="value"><?= htmlspecialchars($invoice['invoice_number'] ?? '') ?></span></div>
      <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.invoice_status', 'Status')) ?></span><span class="value"><span class="badge badge-<?= htmlspecialchars((string)($invoice['status'] ?? 'draft')) ?>"><?= htmlspecialchars($invoiceStatusLabel((string)($invoice['status'] ?? 'draft'))) ?></span></span></div>
      <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.invoice_total', 'Total')) ?></span><span class="value"><?= htmlspecialchars(number_format((float)($invoice['total_amount'] ?? 0), 2)) ?> <?= htmlspecialchars($invoice['currency'] ?? $currency) ?></span></div>
      <?php if (!empty($invoice['issued_at'])): ?>
        <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.issued_at', 'Issued')) ?></span><span class="value"><?= htmlspecialchars($invoice['issued_at']) ?></span></div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="card">
      <h2><?= htmlspecialchars(t('account.invoice', 'Invoice')) ?></h2>
      <p class="sub"><?= htmlspecialchars(t('account.no_invoice', 'No invoice issued yet.')) ?></p>
    </div>
  <?php endif; ?>

  <!-- Timeline history -->
  <?php if (!empty($timeline)): ?>
    <div class="card">
      <h2><?= htmlspecialchars(t('account.timeline', 'Timeline')) ?></h2>
      <div class="timeline">
        <?php foreach ($timeline as $entry): ?>
          <div class="timeline-item">
            <div class="timeline-dot"></div>
            <div class="timeline-content">
              <div class="timeline-title">
                <?= htmlspecialchars(function_exists('timeline_action_label') ? timeline_action_label((string)($entry['action'] ?? '')) : ($entry['action'] ?? '')) ?>
              </div>
              <?php if (!empty($entry['description'])): ?>
                <div class="timeline-desc"><?= htmlspecialchars($entry['description']) ?></div>
              <?php endif; ?>
              <div class="timeline-date"><?= htmlspecialchars($entry['created_at'] ?? '') ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="card">
      <h2><?= htmlspecialchars(t('account.timeline', 'Timeline')) ?></h2>
      <p class="sub"><?= htmlspecialchars(t('account.no_timeline', 'No timeline events yet.')) ?></p>
    </div>
  <?php endif; ?>
</div>

<style>
.booking-summary .card{margin-bottom:22px;}
.timeline{position:relative;padding-left:24px;}
.timeline::before{content:'';position:absolute;left:7px;top:4px;bottom:4px;width:2px;background:var(--line,#e8e6e0);}
.timeline-item{position:relative;padding:0 0 18px;}
.timeline-item:last-child{padding-bottom:0;}
.timeline-dot{position:absolute;left:-22px;top:4px;width:12px;height:12px;border-radius:50%;background:var(--teal,#1f6f8f);border:2px solid #fff;box-shadow:0 0 0 2px var(--teal,#1f6f8f);}
.timeline-title{font-weight:600;font-size:14px;}
.timeline-desc{font-size:13px;color:var(--muted,#6b7280);margin-top:2px;}
.timeline-date{font-size:12px;color:var(--muted,#6b7280);margin-top:2px;}
</style>

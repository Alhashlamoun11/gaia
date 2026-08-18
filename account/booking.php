<?php
/**
 * account/booking.php
 * ------------------------------------------------------------
 * Single booking details (read-only) for the logged-in customer.
 *
 * Route: account/booking.php?reference=GAIA-XXXXXX
 * (backward-compatible: account/booking.php?type=..&id=..)
 *
 * SECURITY: The booking is loaded through
 * BookingSummaryService::findByReference($ref, $userId) which
 * ALWAYS scopes the query to the authenticated user_id. If the
 * user does NOT own the booking (or it does not exist), a 403
 * Forbidden is returned. This prevents IDOR: User A can never
 * load User B's booking.
 *
 * Displays:
 *   - Booking information (reference, type, status, created date)
 *   - Service information (tour / hotel / room / event / transfer)
 *   - Customer information (profile)
 *   - Payment information (amount, currency, status, transaction)
 *   - Invoice information (number, status, issue date)
 *   - Timeline (booking_logs history)
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../components/bootstrap.php';
require_once __DIR__ . '/../components/gaia-config.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../services/BookingSummaryService.php';
require_once __DIR__ . '/../services/PaymentService.php';
require_once __DIR__ . '/../services/InvoiceService.php';
require_once __DIR__ . '/../services/BookingTimelineService.php';

$gaia_base         = '';
$gaia_active       = 'home';
$gaia_header_style = 'solid';

require_login();

$userId = (int)auth_id();

// ------------------------------------------------------------
// Load the booking by reference (preferred) or type+id (legacy)
// ------------------------------------------------------------
$reference = strtoupper(trim($_GET['reference'] ?? ''));
$legacyType = trim($_GET['type'] ?? '');
$legacyId   = (int)($_GET['id'] ?? 0);

$summary = null;
$forbidden = true;

if ($reference !== '') {
    // Preferred path: ownership-scoped lookup by booking reference.
    $summary = BookingSummaryService::findByReference($reference, $userId);
    $forbidden = ($summary === null);
} elseif ($legacyType !== '' && $legacyId > 0) {
    // Backward-compatible path: type+id (still ownership-scoped).
    $summary = BookingSummaryService::getSummary($legacyType, $legacyId, $userId);
    $forbidden = ($summary === null);
}

$type = '';
$id = 0;
$payments = [];
$invoice = null;
$timeline = [];
$customer = null;

if ($forbidden) {
    http_response_code(403);
} else {
    $type = (string)($summary['booking_type'] ?? '');
    $id   = (int)($summary['id'] ?? 0);

    // Load payment(s), invoice and timeline for this booking (ownership-scoped).
    $payments = PaymentService::getBookingPayments($type, $id, $userId);
    $invoice  = InvoiceService::getForBooking($type, $id, $userId);
    $timeline = BookingTimelineService::getTimeline($type, $id, $userId);

    // Customer information from the authenticated profile.
    $customer = auth_user();
}

// Build the "view service" URL based on booking type.
$serviceUrl = '';
if (!$forbidden) {
    $svcRef = $summary['booking_reference'] ?? '';
    switch ($type) {
case 'tour':
        case 'hotel':
        case 'event':
            // These detail pages are slug/id based; we don't reliably have the
            // slug here, so we point to the browse/search pages as a safe fallback.
            if ($type === 'tour') {
                $serviceUrl = gaia_url('weroad/search.php');
            } elseif ($type === 'hotel') {
                $serviceUrl = gaia_url('index.php', '') . '#hotels';
            } else {
                $serviceUrl = gaia_url('gaia-night.php');
            }
            break;
        case 'transfer':
        case 'taxi':
            $serviceUrl = gaia_url('route.php');
            break;
        case 'room':
            $serviceUrl = gaia_url('index.php', '') . '#hotels';
            break;
    }
}

$gaia_page_key   = 'account_booking';
$gaia_page_title = t('account.booking_details') . ' — GAIA TOURS &amp; TRAVEL';
$activeTab = 'bookings';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<?php require __DIR__ . '/../components/account-head.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../components/gaia-header.php'; ?>

<div class="account-shell">
  <?php
    $crumbs = [
        ['label' => t('account.dashboard'), 'url' => gaia_url('account/index.php')],
        ['label' => t('account.my_bookings'), 'url' => gaia_url('account/bookings.php')],
        ['label' => t('account.booking_details'), 'url' => ''],
    ];
    require __DIR__ . '/../components/breadcrumbs.php';
  ?>
  <div class="account-layout">
    <?php require __DIR__ . '/../components/user-sidebar.php'; ?>
    <main class="account-main">

  <?php if ($forbidden): ?>
    <div class="card forbidden">
      <div class="code">403</div>
      <h2><?= htmlspecialchars(t('account.unauthorized')) ?></h2>
      <p><?= htmlspecialchars(t('account.booking_not_found')) ?></p>
      <a class="btn" href="<?= htmlspecialchars(gaia_url('account/bookings.php')) ?>"><?= htmlspecialchars(t('account.back_to_bookings')) ?></a>
    </div>
  <?php else: ?>

    <!-- ACTION BUTTONS -->
    <div class="card" style="margin-bottom:22px;">
      <div class="detail-actions">
        <?php if ($invoice): ?>
          <a class="btn" href="<?= htmlspecialchars(gaia_url('account/invoice-print.php') . '?invoice=' . (int)$invoice['id']) ?>" target="_blank" rel="noopener">
            <i class="fa-solid fa-download"></i> <?= htmlspecialchars(t('account.download_invoice')) ?>
          </a>
          <a class="btn btn-ghost" href="<?= htmlspecialchars(gaia_url('account/invoice-print.php') . '?invoice=' . (int)$invoice['id']) ?>" target="_blank" rel="noopener">
            <i class="fa-solid fa-print"></i> <?= htmlspecialchars(t('account.print')) ?>
          </a>
        <?php endif; ?>
        <?php if ($serviceUrl !== ''): ?>
          <a class="btn btn-ghost" href="<?= htmlspecialchars($serviceUrl) ?>">
            <i class="fa-solid fa-eye"></i> <?= htmlspecialchars(t('account.view_' . $type, t('account.view_service'))) ?>
          </a>
        <?php endif; ?>
        <a class="btn btn-ghost" href="mailto:<?= htmlspecialchars(site_setting('support_email', 'support@gaiatours.com')) ?>">
          <i class="fa-solid fa-headset"></i> <?= htmlspecialchars(t('account.contact_support')) ?>
        </a>
      </div>
    </div>

    <?php require __DIR__ . '/../components/booking-summary.php'; ?>

    <!-- CUSTOMER INFORMATION -->
    <div class="card">
      <h2><?= htmlspecialchars(t('account.customer_info')) ?></h2>
      <?php if ($customer): ?>
        <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.customer')) ?></span><span class="value"><?= htmlspecialchars(trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''))) ?></span></div>
        <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.guest_email')) ?></span><span class="value"><?= htmlspecialchars($customer['email'] ?? '') ?></span></div>
        <?php if (!empty($customer['phone'])): ?>
          <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.guest_phone')) ?></span><span class="value"><?= htmlspecialchars($customer['phone']) ?></span></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- PAYMENT INFORMATION -->
    <div class="card">
      <h2><?= htmlspecialchars(t('account.payment_info')) ?></h2>
      <?php if (!$payments): ?>
        <p class="sub"><?= htmlspecialchars(t('account.no_payments')) ?></p>
      <?php else: ?>
        <?php foreach ($payments as $p): ?>
          <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.amount')) ?></span><span class="value"><?= htmlspecialchars(number_format((float)($p['amount'] ?? 0), 2)) ?> <?= htmlspecialchars($p['currency'] ?? 'USD') ?></span></div>
          <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.currency')) ?></span><span class="value"><?= htmlspecialchars($p['currency'] ?? 'USD') ?></span></div>
          <!-- <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.status')) ?></span><span class="value"><span class="badge badge-<?= htmlspecialchars($p['status'] ?? 'pending') ?>"><?= htmlspecialchars(payment_status_label((string)($p['status'] ?? 'pending'))) ?></span></span></div> -->
          <?php if (!empty($p['payment_method'])): ?>
            <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.payment_method')) ?></span><span class="value"><?= htmlspecialchars($p['payment_method']) ?></span></div>
          <?php endif; ?>
          <?php if (!empty($p['transaction_reference'])): ?>
            <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.transaction_ref')) ?></span><span class="value"><?= htmlspecialchars($p['transaction_reference']) ?></span></div>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- INVOICE INFORMATION -->
    <div class="card">
      <h2><?= htmlspecialchars(t('account.invoice_info')) ?></h2>
      <?php if (!$invoice): ?>
        <p class="sub"><?= htmlspecialchars(t('account.no_invoices')) ?></p>
      <?php else: ?>
        <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.invoice_number')) ?></span><span class="value"><?= htmlspecialchars($invoice['invoice_number'] ?? '') ?></span></div>
        <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.invoice_status')) ?></span><span class="value"><span class="badge badge-<?= htmlspecialchars($invoice['status'] ?? 'draft') ?>"><?= htmlspecialchars(invoice_status_label((string)($invoice['status'] ?? 'draft'))) ?></span></span></div>
        <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.issue_date')) ?></span><span class="value"><?= htmlspecialchars($invoice['issued_at'] ?? '') ?></span></div>
        <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.total_amount')) ?></span><span class="value"><?= htmlspecialchars(number_format((float)($invoice['total_amount'] ?? 0), 2)) ?> <?= htmlspecialchars($invoice['currency'] ?? 'USD') ?></span></div>
      <?php endif; ?>
    </div>

    <!-- STATUS HISTORY / TIMELINE -->
    <?php if ($timeline): ?>
      <?php $timeline_title = t('account.status_history', 'Status History'); require __DIR__ . '/../components/timeline.php'; ?>
    <?php else: ?>
      <div class="card">
        <h2><?= htmlspecialchars(t('account.status_history', 'Status History')) ?></h2>
        <p class="sub"><?= htmlspecialchars(t('account.no_timeline')) ?></p>
      </div>
    <?php endif; ?>
  <?php endif; ?>
    </main>
  </div>
</div>

<style>
.detail-actions{display:flex;gap:10px;flex-wrap:wrap;}
</style>

<?php require __DIR__ . '/../components/gaia-footer.php'; ?>
</body>
</html>
</content>

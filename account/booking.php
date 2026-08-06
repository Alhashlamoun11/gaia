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
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags('account_booking', t('account.booking_details') . ' — GAIA TOURS &amp; TRAVEL') ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<style>
:root{--navy:#1b2a4a;--navy-2:#243761;--teal:#1f6f8f;--teal-dark:#175a75;--ink:#1c1e26;--muted:#6b7280;--line:#e8e6e0;--bg-soft:#f4efe6;--white:#fff;--radius:14px;--shadow:0 10px 30px rgba(27,42,74,0.08);}
*{box-sizing:border-box;}body{margin:0;font-family:'Inter',sans-serif;color:var(--ink);background:var(--bg-soft);-webkit-font-smoothing:antialiased;}
h1,h2,h3{font-family:'Playfair Display',serif;margin:0;}
a{text-decoration:none;color:inherit;}
.account-shell{max-width:1280px;margin:0 auto;padding:32px;}
.account-layout{display:grid;grid-template-columns:260px 1fr;gap:26px;align-items:start;}
.account-sidebar{background:#fff;border:1px solid var(--line);border-radius:18px;padding:20px;box-shadow:var(--shadow);}
.account-sidebar-user{display:flex;gap:12px;align-items:center;padding-bottom:16px;border-bottom:1px solid var(--line);margin-bottom:14px;}
.account-sidebar-user strong{display:block;font-size:14.5px;}
.account-sidebar-user span{display:block;font-size:12px;color:var(--muted);word-break:break-all;}
.account-avatar{width:48px;height:48px;border-radius:50%;object-fit:cover;background:var(--navy);}
.account-nav{display:flex;flex-direction:column;gap:4px;}
.account-nav a{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:10px;font-size:14px;color:var(--ink);transition:.15s;}
.account-nav a:hover{background:#f4efe6;}
.account-nav a.active{background:var(--navy);color:#fff;font-weight:600;}
.account-nav i{width:18px;text-align:center;}
.account-logout-form{margin-top:16px;border-top:1px solid var(--line);padding-top:14px;}
.account-logout-btn{width:100%;display:flex;align-items:center;gap:11px;padding:10px 12px;border:none;background:none;border-radius:10px;font-size:14px;color:#b3261e;cursor:pointer;font-family:inherit;}
.account-logout-btn:hover{background:#fdecea;}
.account-main{min-width:0;}
.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:24px;box-shadow:var(--shadow);margin-bottom:22px;}
.card h2{font-size:20px;margin-bottom:18px;}
.card .sub{color:var(--muted);font-size:14px;margin:0 0 18px;}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;text-transform:uppercase;}
.badge-confirmed,.badge-paid,.badge-completed{background:#e8f5ee;color:#1b6e43;}
.badge-pending,.badge-awaiting_payment,.badge-authorized{background:#fff3d6;color:#8a6d00;}
.badge-cancelled,.badge-failed{background:#fdecea;color:#b3261e;}
.badge-refunded{background:#f0ece6;color:#6b6060;}
.badge-issued{background:#e8f5ee;color:#1b6e43;}
.badge-draft{background:#eef0f4;color:#5a6270;}
.badge-void{background:#f0ece6;color:#6b6060;}
.list-item{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f0ede6;font-size:13.5px;flex-wrap:wrap;}
.list-item:last-child{border-bottom:none;}
.list-item .label{color:var(--muted);}
.list-item .value{font-weight:600;text-align:right;word-break:break-word;}
.list-item.total{font-weight:700;font-size:15px;}
.list-item.total .value{color:var(--teal);}
.forbidden{text-align:center;padding:60px 24px;}
.forbidden .code{font-family:'Playfair Display',serif;font-size:60px;color:var(--teal);font-weight:800;}
.forbidden p{color:var(--muted);margin:10px 0 24px;}
.btn-primary{display:inline-block;background:var(--teal);color:#fff;border:none;border-radius:10px;padding:12px 24px;font-size:14px;font-weight:700;cursor:pointer;}
.btn-primary:hover{background:var(--teal-dark);}
@media (max-width:860px){.account-layout{grid-template-columns:1fr;}.account-nav{flex-direction:row;flex-wrap:wrap;}.account-logout-form{border-top:none;padding-top:0;}}
</style>
</head>
<body>

<?php require __DIR__ . '/../components/gaia-header.php'; ?>

<div class="account-shell">
  <?php $activeTab = 'bookings'; require __DIR__ . '/_layout.php'; ?>

  <?php if ($forbidden): ?>
    <div class="card forbidden">
      <div class="code">403</div>
      <h2><?= htmlspecialchars(t('account.unauthorized')) ?></h2>
      <p><?= htmlspecialchars(t('account.booking_not_found')) ?></p>
      <a class="btn-primary" href="<?= gaia_url('account/bookings.php') ?>"><?= htmlspecialchars(t('account.back_to_bookings')) ?></a>
    </div>
  <?php else: ?>
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
          <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.status')) ?></span><span class="value"><span class="badge badge-<?= htmlspecialchars($p['status'] ?? 'pending') ?>"><?= htmlspecialchars(payment_status_label((string)($p['status'] ?? 'pending'))) ?></span></span></div>
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
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../components/gaia-footer.php'; ?>
</body>
</html>
</content>

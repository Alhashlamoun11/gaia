<?php
/**
 * account/booking.php
 * ------------------------------------------------------------
 * Single booking details (read-only) for the logged-in customer.
 *
 * Route: account/booking.php?type=tour|hotel|event|transfer&id={id}
 *
 * SECURITY: The booking is loaded through BookingRepository::
 * getBookingDetails($userId, $type, $id) which ALWAYS scopes the
 * query to the authenticated user_id. If the user does NOT own the
 * booking (or it does not exist), a 403 Forbidden is returned.
 * This prevents IDOR: User A can never load User B's booking.
 *
 * Displays:
 *   - Booking information
 *   - Service information
 *   - Status
 *   - Price breakdown
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../components/bootstrap.php';
require_once __DIR__ . '/../components/gaia-config.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../auth/BookingRepository.php';
require_once __DIR__ . '/../services/BookingSummaryService.php';
require_once __DIR__ . '/../services/PaymentService.php';
require_once __DIR__ . '/../services/InvoiceService.php';
require_once __DIR__ . '/../services/BookingTimelineService.php';

$gaia_base         = '';
$gaia_active       = 'home';
$gaia_header_style = 'solid';

require_login();

$userId = (int)auth_id();
$type   = trim($_GET['type'] ?? 'transfer');
$id     = (int)($_GET['id'] ?? 0);

// Allowed types (anything else is treated as not found / forbidden).
$allowedTypes = ['tour', 'hotel', 'event', 'transfer'];
if (!in_array($type, $allowedTypes, true)) {
    http_response_code(403);
    $booking = null;
    $forbidden = true;
} else {
    // Ownership-scoped lookup — returns null for missing OR not-owned rows.
    $booking = BookingRepository::getBookingDetails($userId, $type, $id);
    $forbidden = ($booking === null);
    if ($forbidden) {
        http_response_code(403);
    }
}

// Build a price breakdown array for the current booking type.
$breakdown = [];
if ($booking) {
    switch ($booking['type']) {
        case 'transfer':
            $breakdown = [
                ['label' => t('account.base_fare', 'Base fare'), 'value' => (float)($booking['base_price'] ?? 0)],
                ['label' => t('account.return_fee', 'Return trip fee'), 'value' => (float)($booking['return_fee'] ?? 0)],
                ['label' => t('account.extras', 'Extras'), 'value' => (float)($booking['extras_total'] ?? 0)],
            ];
            if ((float)($booking['discount'] ?? 0) > 0) {
                $breakdown[] = ['label' => t('account.discount', 'Discount'), 'value' => -(float)$booking['discount']];
            }
            break;
        case 'tour':
            $breakdown = [
                ['label' => t('account.base_fare', 'Base fare'), 'value' => (float)($booking['base_price'] ?? 0)],
                ['label' => t('account.flex_fee', 'Flexible cancellation'), 'value' => (float)($booking['flexible_fee'] ?? 0)],
                ['label' => t('account.room_fee', 'Private room'), 'value' => (float)($booking['private_room_fee'] ?? 0)],
            ];
            if ((float)($booking['discount'] ?? 0) > 0) {
                $breakdown[] = ['label' => t('account.discount', 'Discount'), 'value' => -(float)$booking['discount']];
            }
            break;
        case 'hotel':
            $breakdown[] = ['label' => t('account.total', 'Total'), 'value' => (float)($booking['total_price'] ?? 0)];
            break;
        case 'event':
            $breakdown[] = ['label' => t('account.total', 'Total'), 'value' => (float)($booking['total_price'] ?? 0)];
            break;
    }
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
.badge-confirmed{background:#e8f5ee;color:#1b6e43;}
.badge-pending{background:#fff3d6;color:#8a6d00;}
.badge-cancelled{background:#fdecea;color:#b3261e;}
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
    <?php
      // Build unified data for the reusable booking-summary component.
      $summary  = BookingSummaryService::getSummary($type, $id, $userId);
      $payments = PaymentService::getBookingPayments($type, $id, $userId);
      $invoice  = InvoiceService::getForBooking($type, $id, $userId);
      $timeline = BookingTimelineService::getTimeline($type, $id, $userId);
    ?>

    <?php require __DIR__ . '/../components/booking-summary.php'; ?>

    <div class="card">
      <h2><?= htmlspecialchars(t('account.service_details')) ?></h2>
      <?php if (!empty($booking['service_name'])): ?>
        <div class="list-item"><span class="label"><?= htmlspecialchars(t('account.service_name')) ?></span><span class="value"><?= htmlspecialchars($booking['service_name']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($booking['room_name'])): ?>
        <div class="list-item"><span class="label"><?= htmlspecialchars(t('room.name', 'Room')) ?></span><span class="value"><?= htmlspecialchars($booking['room_name']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($booking['origin']) && !empty($booking['destination'])): ?>
        <div class="list-item"><span class="label"><?= htmlspecialchars(t('detail.route', 'Route')) ?></span><span class="value"><?= htmlspecialchars($booking['origin'] . ' → ' . $booking['destination']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($booking['pickup_date'])): ?>
        <div class="list-item"><span class="label"><?= htmlspecialchars(t('checkout.pickup_date', 'Pickup date')) ?></span><span class="value"><?= htmlspecialchars($booking['pickup_date']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($booking['check_in_date'])): ?>
        <div class="list-item"><span class="label"><?= htmlspecialchars(t('room.check_in', 'Check-in')) ?></span><span class="value"><?= htmlspecialchars($booking['check_in_date']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($booking['check_out_date'])): ?>
        <div class="list-item"><span class="label"><?= htmlspecialchars(t('room.check_out', 'Check-out')) ?></span><span class="value"><?= htmlspecialchars($booking['check_out_date']) ?></span></div>
      <?php endif; ?>
      <?php if (isset($booking['tickets']) && (int)$booking['tickets'] > 0): ?>
        <div class="list-item"><span class="label"><?= htmlspecialchars(t('event.tickets', 'Tickets')) ?></span><span class="value"><?= (int)$booking['tickets'] ?></span></div>
      <?php endif; ?>
      <?php if (isset($booking['guests']) && (int)$booking['guests'] > 0): ?>
        <div class="list-item"><span class="label"><?= htmlspecialchars(t('room.capacity', 'Guests')) ?></span><span class="value"><?= (int)$booking['guests'] ?></span></div>
      <?php endif; ?>
    </div>

<div class="card">
      <h2><?= htmlspecialchars(t('account.price_breakdown')) ?></h2>
      <?php foreach ($breakdown as $line): ?>
        <div class="list-item"><span class="label"><?= htmlspecialchars($line['label']) ?></span><span class="value"><?= htmlspecialchars(number_format((float)$line['value'], 2)) ?></span></div>
      <?php endforeach; ?>
<div class="list-item total"><span class="label"><?= htmlspecialchars(t('account.total')) ?></span><span class="value"><?= htmlspecialchars(number_format((float)$booking['total_price'], 2)) ?></span></div>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../components/gaia-footer.php'; ?>
</body>
</html>

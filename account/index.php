<?php
/**
 * account/index.php
 * ------------------------------------------------------------
 * Customer account dashboard (main portal home).
 *
 * Displays:
 *   - Welcome + profile summary
 *   - Quick stats (total / upcoming / completed / pending payments)
 *   - Upcoming bookings
 *   - Latest bookings
 *   - Pending payments
 *   - Recent invoices
 *   - Quick actions (Book Tour / Hotel / Transfer / Event,
 *     Edit Profile, Change Password, Booking History, Invoices,
 *     Payments, Wishlist, Notification Center)
 *
 * SECURITY: all data is scoped to the authenticated user_id via
 * BookingSummaryService / PaymentService / InvoiceService /
 * BookingRepository. Uses PDO prepared statements; XSS-escaped.
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
require_once __DIR__ . '/../services/NotificationService.php';

$gaia_base         = '';
$gaia_active       = 'home';
$gaia_header_style = 'solid';

require_login();

$user   = auth_user();
$userId = (int)$user['id'];

// ------------------------------------------------------------
// Quick stats (ownership-scoped)
// ------------------------------------------------------------
$summary = BookingSummaryService::getUserSummaries($userId);

$totalBookings     = count($summary);
$completedBookings = 0;
$upcomingTrips     = 0;
foreach ($summary as $b) {
    $st = normalize_booking_status($b['status'] ?? '');
    if ($st === 'completed') {
        $completedBookings++;
    }
    if (in_array($st, ['pending', 'awaiting_payment', 'confirmed', 'paid'], true)) {
        $upcomingTrips++;
    }
}

$pendingPayments = 0;
foreach (PaymentService::getUserPayments($userId) as $p) {
    if (in_array(normalize_payment_status($p['status'] ?? ''), ['pending', 'authorized'], true)) {
        $pendingPayments++;
    }
}

// ------------------------------------------------------------
// Latest bookings (top 4) + upcoming bookings (top 4)
// ------------------------------------------------------------
$latestBookings  = array_slice($summary, 0, 4);

// Upcoming = active statuses, sorted by created date desc (nearest first is
// approximated by created_date; a real "date" column varies per type).
$upcoming = array_values(array_filter($summary, function ($b) {
    return in_array(normalize_booking_status($b['status'] ?? ''), ['pending', 'awaiting_payment', 'confirmed', 'paid'], true);
}));
$upcomingBookings = array_slice($upcoming, 0, 4);

// ------------------------------------------------------------
// Pending payments (list, top 4)
// ------------------------------------------------------------
$pendingPaymentRows = [];
foreach (PaymentService::getUserPayments($userId) as $p) {
    if (in_array(normalize_payment_status($p['status'] ?? ''), ['pending', 'authorized'], true)) {
        $pendingPaymentRows[] = $p;
        if (count($pendingPaymentRows) >= 4) {
            break;
        }
    }
}

// ------------------------------------------------------------
// Recent invoices (top 3)
// ------------------------------------------------------------
$recentInvoices = array_slice(InvoiceService::getForUser($userId), 0, 3);

// ------------------------------------------------------------
// Quick actions
// ------------------------------------------------------------
$quickActions = [
    ['label' => t('account.book_tour'),      'icon' => 'fa-solid fa-mountain-sun',       'url' => gaia_url('weroad/search.php')],
    ['label' => t('account.book_hotel'),     'icon' => 'fa-solid fa-hotel',              'url' => gaia_url('index.php', '') . '#hotels'],
    ['label' => t('account.book_transfer'),  'icon' => 'fa-solid fa-car',                'url' => gaia_url('route.php')],
    ['label' => t('account.book_event'),     'icon' => 'fa-solid fa-wand-magic-sparkles','url' => gaia_url('gaia-night.php')],
    ['label' => t('account.edit_profile'),   'icon' => 'fa-solid fa-user-pen',           'url' => gaia_url('account/profile.php')],
    ['label' => t('account.change_password'),'icon' => 'fa-solid fa-key',                'url' => gaia_url('account/security.php')],
    ['label' => t('account.booking_history'),'icon' => 'fa-solid fa-clock-rotate-left',  'url' => gaia_url('account/bookings.php')],
    ['label' => t('account.my_invoices'),    'icon' => 'fa-solid fa-file-invoice-dollar','url' => gaia_url('account/invoices.php')],
    ['label' => t('account.my_payments'),    'icon' => 'fa-solid fa-credit-card',        'url' => gaia_url('account/payments.php')],
    ['label' => t('account.wishlist'),       'icon' => 'fa-solid fa-heart',              'url' => gaia_url('account/favorites.php')],
    ['label' => t('account.notification_center'),'icon' => 'fa-solid fa-bell',          'url' => gaia_url('account/notifications.php')],
];

$gaia_page_key   = 'account_dashboard';
$gaia_page_title = t('account.dashboard') . ' — GAIA TOURS &amp; TRAVEL';
$activeTab = 'dashboard';
$base      = '../';
$account_alerts = [];
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
        ['label' => t('account.dashboard'), 'url' => ''],
    ];
    require __DIR__ . '/../components/breadcrumbs.php';
  ?>
  <div class="account-layout">
    <?php require __DIR__ . '/../components/user-sidebar.php'; ?>
    <main class="account-main">
      <?php require __DIR__ . '/../components/alert.php'; ?>

      <!-- WELCOME + PROFILE SUMMARY -->
      <div class="welcome-card">
        <div>
          <h1><?= htmlspecialchars(t_fmt('account.welcome_back', ['name' => auth_name()])) ?></h1>
          <p><?= htmlspecialchars(auth_email()) ?></p>
          <p style="margin-top:6px;">
            <?= htmlspecialchars(t('account.member_since')) ?>:
            <?= htmlspecialchars($user['created_at'] ? date('M Y', strtotime($user['created_at'])) : '—') ?>
            · <?= htmlspecialchars(t('account.account_status')) ?>:
            <span class="badge badge-active"><?= htmlspecialchars(t('account.' . $user['status'], $user['status'])) ?></span>
          </p>
        </div>
        <div class="welcome-actions">
          <a class="btn btn-sm" href="<?= htmlspecialchars(gaia_url('account/profile.php')) ?>"><i class="fa-solid fa-user-pen"></i> <?= htmlspecialchars(t('account.edit_profile')) ?></a>
        </div>
      </div>

      <!-- QUICK STATS -->
      <div class="stats-grid">
        <?php
          $stat_value = $totalBookings; $stat_label = t('account.total_bookings'); $stat_icon = 'fa-bookmark'; $stat_color = 'blue'; $stat_link = gaia_url('account/bookings.php');
          require __DIR__ . '/../components/dashboard-stat.php';
          $stat_value = $upcomingTrips; $stat_label = t('account.upcoming_bookings'); $stat_icon = 'fa-calendar-check'; $stat_color = 'green'; $stat_link = gaia_url('account/bookings.php');
          require __DIR__ . '/../components/dashboard-stat.php';
          $stat_value = $completedBookings; $stat_label = t('account.completed_bookings'); $stat_icon = 'fa-circle-check'; $stat_color = 'gold'; $stat_link = gaia_url('account/bookings.php');
          require __DIR__ . '/../components/dashboard-stat.php';
          $stat_value = $pendingPayments; $stat_label = t('account.pending_payments'); $stat_icon = 'fa-credit-card'; $stat_color = 'red'; $stat_link = gaia_url('account/payments.php');
          require __DIR__ . '/../components/dashboard-stat.php';
        ?>
      </div>

      <div class="card-grid">
        <!-- UPCOMING BOOKINGS -->
        <div class="card">
          <h3><i class="fa-solid fa-plane-up"></i> <?= htmlspecialchars(t('account.upcoming_bookings')) ?></h3>
          <?php if (!$upcomingBookings): ?>
            <?php $empty_title = t('account.no_upcoming'); $empty_icon = 'fa-calendar'; require __DIR__ . '/../components/empty-state.php'; ?>
          <?php else: ?>
            <?php foreach ($upcomingBookings as $b): ?>
              <div class="list-item">
                <div>
                  <div class="code"><?= htmlspecialchars($b['booking_reference'] ?? $b['booking_code'] ?? '') ?></div>
                  <span class="muted"><?= htmlspecialchars($b['service_name'] ?? '') ?> · <?= htmlspecialchars($b['created_date'] ?? '') ?></span>
                </div>
                <span class="badge badge-<?= htmlspecialchars(normalize_booking_status($b['status'] ?? '')) ?>"><?= htmlspecialchars(booking_status_label(normalize_booking_status($b['status'] ?? ''))) ?></span>
              </div>
            <?php endforeach; ?>
            <div style="margin-top:12px;"><a class="btn-link" href="<?= htmlspecialchars(gaia_url('account/bookings.php')) ?>"><?= htmlspecialchars(t('account.view_all_bookings')) ?> →</a></div>
          <?php endif; ?>
        </div>

        <!-- LATEST BOOKINGS -->
        <div class="card">
          <h3><i class="fa-solid fa-bolt"></i> <?= htmlspecialchars(t('account.latest_bookings')) ?></h3>
          <?php if (!$latestBookings): ?>
            <?php $empty_title = t('account.no_bookings'); $empty_icon = 'fa-bookmark'; require __DIR__ . '/../components/empty-state.php'; ?>
          <?php else: ?>
            <?php foreach ($latestBookings as $b): ?>
              <div class="list-item">
                <div>
                  <div class="code"><?= htmlspecialchars($b['booking_reference'] ?? $b['booking_code'] ?? '') ?></div>
                  <span class="muted"><?= htmlspecialchars($b['service_name'] ?? '') ?> · <?= htmlspecialchars(booking_type_label((string)($b['booking_type'] ?? ''))) ?></span>
                </div>
                <span class="badge badge-<?= htmlspecialchars(normalize_booking_status($b['status'] ?? '')) ?>"><?= htmlspecialchars(booking_status_label(normalize_booking_status($b['status'] ?? ''))) ?></span>
              </div>
            <?php endforeach; ?>
            <div style="margin-top:12px;"><a class="btn-link" href="<?= htmlspecialchars(gaia_url('account/bookings.php')) ?>"><?= htmlspecialchars(t('account.view_all_bookings')) ?> →</a></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card-grid">
        <!-- PENDING PAYMENTS -->
        <div class="card">
          <h3><i class="fa-solid fa-credit-card"></i> <?= htmlspecialchars(t('account.pending_payments')) ?></h3>
          <?php if (!$pendingPaymentRows): ?>
            <?php $empty_title = t('account.no_payment_records'); $empty_icon = 'fa-credit-card'; require __DIR__ . '/../components/empty-state.php'; ?>
          <?php else: ?>
            <?php foreach ($pendingPaymentRows as $p): ?>
              <div class="list-item">
                <div>
                  <div class="code"><?= htmlspecialchars($p['booking_reference'] ?? ('#' . (int)$p['id'])) ?></div>
                  <span class="muted"><?= htmlspecialchars(payment_status_label(normalize_payment_status($p['status'] ?? ''))) ?></span>
                </div>
                <span class="value"><?= htmlspecialchars(number_format((float)($p['amount'] ?? 0), 2)) ?> <?= htmlspecialchars($p['currency'] ?? 'USD') ?></span>
              </div>
            <?php endforeach; ?>
            <div style="margin-top:12px;"><a class="btn-link" href="<?= htmlspecialchars(gaia_url('account/payments.php')) ?>"><?= htmlspecialchars(t('account.view_all')) ?> →</a></div>
          <?php endif; ?>
        </div>

        <!-- RECENT INVOICES -->
        <div class="card">
          <h3><i class="fa-solid fa-file-invoice-dollar"></i> <?= htmlspecialchars(t('account.recent_invoices')) ?></h3>
          <?php if (!$recentInvoices): ?>
            <?php $empty_title = t('account.no_invoices'); $empty_icon = 'fa-file-invoice-dollar'; require __DIR__ . '/../components/empty-state.php'; ?>
          <?php else: ?>
            <?php foreach ($recentInvoices as $inv): ?>
              <div class="list-item">
                <div>
                  <div class="code"><?= htmlspecialchars($inv['invoice_number'] ?? '') ?></div>
                  <span class="muted"><?= htmlspecialchars($inv['booking_reference'] ?? '') ?> · <?= htmlspecialchars($inv['issued_at'] ?? '') ?></span>
                </div>
                <span class="badge badge-<?= htmlspecialchars($inv['status'] ?? 'draft') ?>"><?= htmlspecialchars(invoice_status_label((string)($inv['status'] ?? 'draft'))) ?></span>
              </div>
            <?php endforeach; ?>
            <div style="margin-top:12px;"><a class="btn-link" href="<?= htmlspecialchars(gaia_url('account/invoices.php')) ?>"><?= htmlspecialchars(t('account.view_all')) ?> →</a></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- QUICK ACTIONS -->
      <div class="card" style="margin-bottom:0;">
        <h3><i class="fa-solid fa-bolt"></i> <?= htmlspecialchars(t('account.quick_actions')) ?></h3>
        <div class="actions-grid">
          <?php foreach ($quickActions as $qa): ?>
            <a class="action-card" href="<?= htmlspecialchars($qa['url']) ?>">
              <i class="<?= htmlspecialchars($qa['icon']) ?>"></i>
              <span><?= htmlspecialchars($qa['label']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </main>
  </div>
</div>

<?php require __DIR__ . '/../components/gaia-footer.php'; ?>
</body>
</html>

<?php
/**
 * account/index.php
 * ------------------------------------------------------------
 * Customer account dashboard.
 *
 * Shows:
 *   - Quick Stats (Total Bookings, Upcoming Trips, Completed
 *     Bookings, Pending Payments)
 *   - Recent Activity (latest booking_logs entries)
 *   - Upcoming Reservations (nearest future bookings)
 *   - Quick Actions (Browse Tours/Hotels/Events, Book Transfer)
 *
 * SECURITY: all data is scoped to the authenticated user_id via
 * BookingSummaryService / BookingTimelineService / PaymentService.
 * Uses PDO prepared statements; XSS-escaped output.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../components/bootstrap.php';
require_once __DIR__ . '/../components/gaia-config.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../auth/BookingRepository.php';
require_once __DIR__ . '/../services/BookingSummaryService.php';
require_once __DIR__ . '/../services/PaymentService.php';
require_once __DIR__ . '/../services/BookingTimelineService.php';

$gaia_base         = '';
$gaia_active       = 'home';
$gaia_header_style = 'solid';

require_login();

$user   = auth_user();
$userId = (int)$user['id'];

// ------------------------------------------------------------
// QUICK STATS (ownership-scoped)
// ------------------------------------------------------------
$allBookings   = BookingRepository::getUserBookings($userId);
$totalBookings = count($allBookings);

$upcomingTrips     = 0;
$completedBookings = 0;
$pendingPayments   = 0;

foreach ($allBookings as $b) {
    $status = (string)($b['status'] ?? '');
    if ($status === 'completed') {
        $completedBookings++;
    }
    // Upcoming = confirmed/pending/awaiting_payment with a future date
    if ($status === 'confirmed' || $status === 'pending' || $status === 'awaiting_payment') {
        $upcomingTrips++;
    }
}

// Pending payments (ownership-scoped via PaymentService)
$payments      = PaymentService::getUserPayments($userId);
$pendingPayments = 0;
foreach ($payments as $p) {
    if (in_array((string)($p['status'] ?? ''), ['pending', 'authorized'], true)) {
        $pendingPayments++;
    }
}

// ------------------------------------------------------------
// RECENT ACTIVITY (latest booking_logs for this user)
// ------------------------------------------------------------
$recentActivity = [];
try {
    $pdo = getPDO();
    $stmt = $pdo->prepare(
        "SELECT bl.*, b.booking_reference AS ref
         FROM booking_logs bl
         LEFT JOIN bookings b ON bl.booking_type = 'transfer' AND b.id = bl.booking_id AND b.user_id = :uid
         WHERE bl.user_id = :uid
         ORDER BY bl.created_at DESC, bl.id DESC
         LIMIT 8"
    );
    $stmt->execute([':uid' => $userId]);
    $recentActivity = $stmt->fetchAll();
} catch (PDOException $e) {
    $recentActivity = [];
}

// ------------------------------------------------------------
// UPCOMING RESERVATIONS (nearest future bookings)
// ------------------------------------------------------------
$upcomingBookings = [];
// Transfers — future pickup date
try {
    $pdo = getPDO();
    $stmt = $pdo->prepare(
        "SELECT booking_reference, CONCAT_WS(' → ', origin, destination) AS title,
                pickup_date AS date, total_price, status, 'transfer' AS type
         FROM bookings
         WHERE user_id = :uid AND status NOT IN ('cancelled','refunded','completed')
           AND pickup_date >= CURDATE()
         ORDER BY pickup_date ASC LIMIT 5"
    );
    $stmt->execute([':uid' => $userId]);
    $upcomingBookings = $stmt->fetchAll();
} catch (PDOException $e) {
    $upcomingBookings = [];
}

// ------------------------------------------------------------
// QUICK ACTIONS
// ------------------------------------------------------------
$quickActions = [
    ['label' => t('account.browse_tours'),     'icon' => 'fa-solid fa-mountain-sun',      'url' => gaia_url('weroad/search.php')],
    ['label' => t('account.browse_hotels'),    'icon' => 'fa-solid fa-hotel',             'url' => gaia_url('index.php', '') . '#hotels'],
    ['label' => t('account.browse_events'),    'icon' => 'fa-solid fa-wand-magic-sparkles','url' => gaia_url('gaia-night.php')],
    ['label' => t('account.book_transfer'),    'icon' => 'fa-solid fa-car',               'url' => gaia_url('route.php')],
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags('account', t('account.dashboard') . ' — GAIA TOURS &amp; TRAVEL') ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<style>
:root{--navy:#1b2a4a;--navy-2:#243761;--teal:#1f6f8f;--teal-dark:#175a75;--ink:#1c1e26;--muted:#6b7280;--line:#e8e6e0;--bg-soft:#f4efe6;--white:#fff;--radius:14px;--shadow:0 10px 30px rgba(27,42,74,0.08);}
*{box-sizing:border-box;}body{margin:0;font-family:'Inter',sans-serif;color:var(--ink);background:var(--bg-soft);-webkit-font-smoothing:antialiased;}
h1,h2,h3,h4{font-family:'Playfair Display',serif;margin:0;}
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
.welcome-card{background:var(--navy);color:#fff;border-radius:18px;padding:28px;margin-bottom:22px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;}
.welcome-card h1{font-size:24px;}
.welcome-card p{margin:6px 0 0;color:#c7c3e2;font-size:14px;}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;text-transform:uppercase;}
.badge-active,.badge-confirmed,.badge-paid,.badge-completed{background:#e8f5ee;color:#1b6e43;}
.badge-pending,.badge-awaiting_payment,.badge-authorized{background:#fff3d6;color:#8a6d00;}
.badge-cancelled,.badge-failed{background:#fdecea;color:#b3261e;}
.badge-refunded{background:#f0ece6;color:#6b6060;}
/* Quick stats */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.stat-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:20px;box-shadow:var(--shadow);display:flex;align-items:center;gap:16px;}
.stat-card .stat-icon{width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;flex:none;}
.stat-card .stat-value{font-family:'Playfair Display',serif;font-size:26px;font-weight:700;line-height:1;}
.stat-card .stat-label{color:var(--muted);font-size:12.5px;margin-top:4px;}
.stat-icon.blue{background:var(--teal);}.stat-icon.green{background:#2eae6e;}.stat-icon.gold{background:#d9a441;}.stat-icon.red{background:#d96a4a;}
/* Card grid */
.card-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:24px;}
.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:20px;box-shadow:var(--shadow);}
.card h3{font-size:16px;margin-bottom:16px;display:flex;align-items:center;gap:8px;}
.card h3 i{color:var(--teal);}
.card .muted{color:var(--muted);font-size:13px;}
.list-item{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f0ede6;font-size:13.5px;flex-wrap:wrap;}
.list-item:last-child{border-bottom:none;}
.list-item .code{font-weight:700;}
.list-item .link{color:var(--teal);font-weight:600;font-size:13px;}
.list-item .link:hover{text-decoration:underline;}
/* Quick actions */
.actions-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;}
.action-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:22px;box-shadow:var(--shadow);text-align:center;transition:.2s;}
.action-card:hover{transform:translateY(-3px);box-shadow:0 14px 34px rgba(27,42,74,.14);}
.action-card i{font-size:26px;color:var(--teal);margin-bottom:10px;}
.action-card span{display:block;font-size:14px;font-weight:600;}
.alert{background:#e8f5ee;color:#1b6e43;border:1px solid #b9e4cb;border-radius:10px;padding:12px 14px;font-size:13.5px;margin-bottom:18px;}
@media (max-width:1100px){.stats-grid,.actions-grid{grid-template-columns:1fr 1fr;}}
@media (max-width:860px){.account-layout{grid-template-columns:1fr;}.account-nav{flex-direction:row;flex-wrap:wrap;}.account-logout-form{border-top:none;padding-top:0;}.card-grid{grid-template-columns:1fr;}.stats-grid,.actions-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>

<?php require __DIR__ . '/../components/gaia-header.php'; ?>

<div class="account-shell">
  <?php require __DIR__ . '/_layout.php'; ?>

  <div class="welcome-card">
    <div>
      <h1><?= htmlspecialchars(t_fmt('account.welcome', ['name' => auth_name()])) ?></h1>
      <p><?= htmlspecialchars(auth_email()) ?></p>
    </div>
    <div>
      <span class="badge badge-active"><?= htmlspecialchars(t('account.role_' . $user['role_slug'], $user['role_slug'])) ?></span>
    </div>
  </div>

  <!-- QUICK STATS -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="fa-solid fa-bookmark"></i></div>
      <div><div class="stat-value"><?= (int)$totalBookings ?></div><div class="stat-label"><?= htmlspecialchars(t('account.total_bookings')) ?></div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class="fa-solid fa-calendar-check"></i></div>
      <div><div class="stat-value"><?= (int)$upcomingTrips ?></div><div class="stat-label"><?= htmlspecialchars(t('account.upcoming_trips')) ?></div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon gold"><i class="fa-solid fa-circle-check"></i></div>
      <div><div class="stat-value"><?= (int)$completedBookings ?></div><div class="stat-label"><?= htmlspecialchars(t('account.completed_bookings')) ?></div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon red"><i class="fa-solid fa-credit-card"></i></div>
      <div><div class="stat-value"><?= (int)$pendingPayments ?></div><div class="stat-label"><?= htmlspecialchars(t('account.pending_payments')) ?></div></div>
    </div>
  </div>

  <div class="card-grid">
    <!-- RECENT ACTIVITY -->
    <div class="card">
      <h3><i class="fa-solid fa-bolt"></i> <?= htmlspecialchars(t('account.recent_activity')) ?></h3>
      <?php if (!$recentActivity): ?>
        <p class="muted"><?= htmlspecialchars(t('account.no_activity')) ?></p>
      <?php else: ?>
        <?php foreach ($recentActivity as $a): ?>
          <div class="list-item">
            <div>
              <strong style="font-size:13.5px;"><?= htmlspecialchars(function_exists('timeline_action_label') ? timeline_action_label((string)$a['action']) : $a['action']) ?></strong>
              <?php if (!empty($a['ref'])): ?>
                <div class="muted"><?= htmlspecialchars($a['ref']) ?></div>
              <?php endif; ?>
            </div>
            <span class="muted" style="font-size:12px;"><?= htmlspecialchars($a['created_at']) ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- UPCOMING RESERVATIONS -->
    <div class="card">
      <h3><i class="fa-solid fa-plane-up"></i> <?= htmlspecialchars(t('account.upcoming_reservations')) ?></h3>
      <?php if (!$upcomingBookings): ?>
        <p class="muted"><?= htmlspecialchars(t('account.no_upcoming')) ?></p>
      <?php else: ?>
        <?php foreach ($upcomingBookings as $b): ?>
          <div class="list-item">
            <div>
              <div class="code"><?= htmlspecialchars($b['booking_reference'] ?: $b['title']) ?></div>
              <span class="muted"><?= htmlspecialchars($b['date']) ?> · <?= htmlspecialchars(booking_type_label($b['type'])) ?></span>
            </div>
            <span class="badge badge-<?= htmlspecialchars($b['status']) ?>"><?= htmlspecialchars(booking_status_label($b['status'])) ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
      <div style="margin-top:14px;">
        <a class="link" href="<?= gaia_url('account/bookings.php') ?>" style="color:var(--teal);font-weight:600;font-size:14px;"><?= htmlspecialchars(t('account.view_all_bookings')) ?> →</a>
      </div>
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
</div>

<?php require __DIR__ . '/../components/gaia-footer.php'; ?>
</body>
</html>

<?php
/**
 * admin/index.php
 * ------------------------------------------------------------
 * Admin dashboard.
 *
 * Displays aggregate stats (users, bookings, services, revenue,
 * payments) plus latest bookings / users / reviews / payments /
 * contacts, all reusing existing services and PDO prepared
 * statements. Guards with require_admin() (super_admin only).
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/_bootstrap.php';
require_admin();

$pdo       = getPDO();
$adminUser = admin_user();

// ------------------------------------------------------------
// STATS — aggregations
// ------------------------------------------------------------
$totalUsers    = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$toursCount    = (int)$pdo->query('SELECT COUNT(*) FROM weroad_trips')->fetchColumn();
$hotelsCount   = (int)$pdo->query('SELECT COUNT(*) FROM hotels')->fetchColumn();
$roomsCount    = (int)$pdo->query('SELECT COUNT(*) FROM hotel_rooms')->fetchColumn();
$transfersCount = (int)$pdo->query('SELECT COUNT(*) FROM routes')->fetchColumn();
$eventsCount   = (int)$pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();

// Unified booking count via the existing ReportingService.
$totalBookings = ReportingService::totalBookings();

// Revenue = sum of total_price across all booking tables.
$revenue = 0.0;
foreach (['bookings', 'weroad_bookings', 'hotel_bookings', 'room_bookings', 'event_bookings', 'taxi_bookings'] as $tbl) {
    $revenue += (float)$pdo->query("SELECT COALESCE(SUM(total_price),0) FROM `{$tbl}`")->fetchColumn();
}

// Payments via existing PaymentService helper sets.
$pendingP = ReportingService::pendingPayments();
$paidP    = ReportingService::paidPayments();
$pendingPayments = $pendingP['count'] ?? 0;
$completedPayments = $paidP['count'] ?? 0;

// ------------------------------------------------------------
// LATEST LISTS
// ------------------------------------------------------------
// Build a lightweight unified "latest bookings" list by querying each bookable table.
$bookingsRows = [];
foreach (['bookings','weroad_bookings','hotel_bookings','room_bookings','event_bookings'] as $tbl) {
    try {
        $stmt = $pdo->query("SELECT id, booking_reference, status, total_price, created_at FROM `{$tbl}` ORDER BY created_at DESC LIMIT 5");
        foreach ($stmt->fetchAll() as $r) {
            $r['table'] = $tbl;
            $bookingsRows[] = $r;
        }
    } catch (PDOException $e) {}
}
usort($bookingsRows, fn($a,$b) => strcmp((string)($b['created_at'] ?? ''),(string)($a['created_at'] ?? '')));
$latestBookings = array_slice($bookingsRows, 0, 6);

$latestUsers = $pdo->query('SELECT id, first_name, last_name, email, role_id, status, created_at FROM users ORDER BY created_at DESC LIMIT 5')->fetchAll();

$latestReviews = $pdo->query('SELECT id, reviewer, rating, place, is_active, created_at FROM reviews ORDER BY created_at DESC LIMIT 5')->fetchAll();

// Latest payments (from payments table).
$latestPayments = $pdo->query('SELECT id, booking_reference, amount, status, created_at, currency FROM payments ORDER BY created_at DESC LIMIT 5')->fetchAll();

// Latest contacts — the contacts table may or may not exist.
$latestContacts = [];
try {
    $latestContacts = $pdo->query('SELECT id, name, email, subject, created_at FROM contacts ORDER BY created_at DESC LIMIT 5')->fetchAll();
} catch (PDOException $e) {
    $latestContacts = [];
}

// Role map for display.
$roles = [];
foreach ($pdo->query('SELECT id, slug, name FROM roles')->fetchAll() as $_r) {
    $roles[(int)$_r['id']] = $_r['slug'];
}

$admin_page_title     = t('admin.dashboard') . ' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title   = t('admin.dashboard');
$admin_active         = 'dashboard';
?>
<?php require __DIR__ . '/components/header.php'; ?>
<div class="admin-app">
  <?php require __DIR__ . '/components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/components/alert.php'; ?>

      <div class="stats-grid">
        <?php
          $stat_value = $totalUsers; $stat_label = t('admin.users'); $stat_icon = 'fa-users'; $stat_color = 'blue'; $stat_link = admin_url('users/index.php');
          require __DIR__ . '/components/stats-card.php';
          $stat_value = $totalBookings; $stat_label = t('admin.total_bookings'); $stat_icon = 'fa-bookmark'; $stat_color = 'navy'; $stat_link = admin_url('bookings/index.php');
          require __DIR__ . '/components/stats-card.php';
          $stat_value = $revenue; $stat_label = t('admin.revenue'); $stat_icon = 'fa-dollar-sign'; $stat_color = 'gold'; $stat_link = admin_url('bookings/index.php');
          require __DIR__ . '/components/stats-card.php';
          $stat_value = $toursCount; $stat_label = t('admin.tours'); $stat_icon = 'fa-mountain-sun'; $stat_color = 'green'; $stat_link = admin_url('tours/index.php');
          require __DIR__ . '/components/stats-card.php';
          $stat_value = $hotelsCount; $stat_label = t('admin.hotels'); $stat_icon = 'fa-hotel'; $stat_color = 'blue'; $stat_link = admin_url('hotels/index.php');
          require __DIR__ . '/components/stats-card.php';
          $stat_value = $roomsCount; $stat_label = t('admin.rooms'); $stat_icon = 'fa-bed'; $stat_color = 'green'; $stat_link = admin_url('rooms/index.php');
          require __DIR__ . '/components/stats-card.php';
          $stat_value = $transfersCount; $stat_label = t('admin.transfers'); $stat_icon = 'fa-car'; $stat_color = 'navy'; $stat_link = admin_url('transfers/index.php');
          require __DIR__ . '/components/stats-card.php';
          $stat_value = $eventsCount; $stat_label = t('admin.events'); $stat_icon = 'fa-wand-magic-sparkles'; $stat_color = 'gold'; $stat_link = admin_url('events/index.php');
          require __DIR__ . '/components/stats-card.php';
          $stat_value = $pendingPayments; $stat_label = t('admin.pending_payments'); $stat_icon = 'fa-clock'; $stat_color = 'red'; $stat_link = admin_url('bookings/index.php');
          require __DIR__ . '/components/stats-card.php';
          $stat_value = $completedPayments; $stat_label = t('admin.completed_payments', 'Completed Payments'); $stat_icon = 'fa-circle-check'; $stat_color = 'green';
          require __DIR__ . '/components/stats-card.php';
        ?>

<!-- Analytics Section -->
<section class="analytics-section">
  <div class="analytics-card" id="hotels-card" data-card-id="hotels-card" data-show-export="true">
    <h3 class="analytics-card-title">Hotels Overview</h3>
  </div>
  <div class="analytics-card" id="payments-card" data-card-id="payments-card" data-show-export="true">
    <h3 class="analytics-card-title">Payments Overview</h3>
  </div>
  <div class="analytics-card" id="reservations-card" data-card-id="reservations-card" data-show-export="true">
    <h3 class="analytics-card-title">Reservations Overview</h3>
  </div>
  <div class="analytics-card" id="users-card" data-show-export="true">
    <h3 class="analytics-card-title">New Users</h3>
  </div>
</section>


      </div>

      <div class="grid-2">
        <div class="card" style="margin-bottom:0;">
          <h3><i class="fa-solid fa-bolt"></i> <?= htmlspecialchars(t('admin.latest_bookings')) ?></h3>
          <div class="table-wrap" style="box-shadow:none;border:none;">
            <table>
              <thead><tr><th><?= htmlspecialchars(t('admin.created_date')) ?></th><th><?= htmlspecialchars(t('admin.service')) ?></th><th><?= htmlspecialchars(t('admin.amount')) ?></th><th><?= htmlspecialchars(t('admin.status')) ?></th></tr></thead>
              <tbody>
                <?php if (!$latestBookings): ?>
                  <tr><td colspan="4"><div class="muted" style="text-align:center;padding:18px;"><?= htmlspecialchars(t('admin.no_records')) ?></div></td></tr>
                <?php else: foreach ($latestBookings as $b): $ref = $b['booking_reference'] ?: ('#' . (int)$b['id']); ?>
                  <tr>
                    <td><?= htmlspecialchars(date('Y-m-d', strtotime((string)($b['created_at'] ?? 'now')))) ?></td>
                    <td class="code"><?= htmlspecialchars($ref) ?><div class="muted" style="font-weight:400;font-size:11px;"><?= htmlspecialchars($b['table']) ?></div></td>
                    <td><?= htmlspecialchars(number_format((float)($b['total_price'] ?? 0), 2)) ?></td>
                    <td><span class="badge badge-<?= htmlspecialchars(normalize_booking_status((string)($b['status'] ?? 'pending'))) ?>"><?= htmlspecialchars(booking_status_label(normalize_booking_status((string)($b['status'] ?? 'pending')))) ?></span></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card" style="margin-bottom:0;">
          <h3><i class="fa-solid fa-users"></i> <?= htmlspecialchars(t('admin.latest_users')) ?></h3>
          <div class="table-wrap" style="box-shadow:none;border:none;">
            <table>
              <thead><tr><th><?= htmlspecialchars(t('admin.created')) ?></th><th><?= htmlspecialchars(t('admin.name')) ?></th><th><?= htmlspecialchars(t('admin.role')) ?></th><th><?= htmlspecialchars(t('admin.status')) ?></th></tr></thead>
              <tbody>
                <?php if (!$latestUsers): ?>
                  <tr><td colspan="4"><div class="muted" style="text-align:center;padding:18px;"><?= htmlspecialchars(t('admin.no_records')) ?></div></td></tr>
                <?php else: foreach ($latestUsers as $u): ?>
                  <tr>
                    <td><?= htmlspecialchars(date('Y-m-d', strtotime((string)($u['created_at'] ?? 'now')))) ?></td>
                    <td class="code"><?= htmlspecialchars(trim($u['first_name'] . ' ' . $u['last_name'])) ?></td>
                    <td><?= htmlspecialchars($roles[(int)$u['role_id']] ?? 'customer') ?></td>
                    <td><span class="badge badge-<?= htmlspecialchars($u['status']) ?>"><?= htmlspecialchars($u['status']) ?></span></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="grid-3" style="margin-top:22px;">
        <div class="card" style="margin-bottom:0;">
          <h3><i class="fa-solid fa-star"></i> <?= htmlspecialchars(t('admin.latest_reviews')) ?></h3>
          <div class="table-wrap" style="box-shadow:none;border:none;">
            <table>
              <thead><tr><th><?= htmlspecialchars(t('admin.reviewer')) ?></th><th><?= htmlspecialchars(t('admin.rating')) ?></th><th><?= htmlspecialchars(t('admin.status')) ?></th></tr></thead>
              <tbody>
                <?php if (!$latestReviews): ?>
                  <tr><td colspan="3"><div class="muted" style="text-align:center;padding:18px;"><?= htmlspecialchars(t('admin.no_records')) ?></div></td></tr>
                <?php else: foreach ($latestReviews as $rv): ?>
                  <tr>
                    <td class="code"><?= htmlspecialchars($rv['reviewer']) ?></td>
                    <td><?= htmlspecialchars($rv['rating']) ?>/5</td>
                    <td><span class="badge badge-<?= (int)$rv['is_active'] ? 'active' : 'inactive' ?>"><?= (int)$rv['is_active'] ? htmlspecialchars(t('admin.active')) : htmlspecialchars(t('admin.inactive')) ?></span></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card" style="margin-bottom:0;">
          <h3><i class="fa-solid fa-credit-card"></i> <?= htmlspecialchars(t('admin.latest_payments')) ?></h3>
          <div class="table-wrap" style="box-shadow:none;border:none;">
            <table>
              <thead><tr><th><?= htmlspecialchars(t('admin.created')) ?></th><th><?= htmlspecialchars(t('admin.amount')) ?></th><th><?= htmlspecialchars(t('admin.payment_status')) ?></th></tr></thead>
              <tbody>
                <?php if (!$latestPayments): ?>
                  <tr><td colspan="3"><div class="muted" style="text-align:center;padding:18px;"><?= htmlspecialchars(t('admin.no_records')) ?></div></td></tr>
                <?php else: foreach ($latestPayments as $pay): ?>
                  <tr>
                    <td><?= htmlspecialchars(date('Y-m-d', strtotime((string)($pay['created_at'] ?? 'now')))) ?></td>
                    <td class="code"><?= htmlspecialchars(number_format((float)($pay['amount'] ?? 0), 2)) ?> <?= htmlspecialchars($pay['currency'] ?? 'USD') ?></td>
                    <td><span class="badge badge-<?= htmlspecialchars(normalize_payment_status((string)($pay['status'] ?? 'pending'))) ?>"><?= htmlspecialchars(payment_status_label(normalize_payment_status((string)($pay['status'] ?? 'pending')))) ?></span></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card" style="margin-bottom:0;">
          <h3><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars(t('admin.latest_contacts')) ?></h3>
          <div class="table-wrap" style="box-shadow:none;border:none;">
            <table>
              <thead><tr><th><?= htmlspecialchars(t('admin.name')) ?></th><th><?= htmlspecialchars(t('admin.email')) ?></th><th><?= htmlspecialchars(t('admin.created')) ?></th></tr></thead>
              <tbody>
                <?php if (!$latestContacts): ?>
                  <tr><td colspan="3"><div class="muted" style="text-align:center;padding:18px;"><?= htmlspecialchars(t('admin.no_records')) ?></div></td></tr>
                <?php else: foreach ($latestContacts as $c): ?>
                  <tr>
                    <td class="code"><?= htmlspecialchars($c['name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
                    <td><?= htmlspecialchars(date('Y-m-d', strtotime((string)($c['created_at'] ?? 'now')))) ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<?php require __DIR__ . '/components/footer.php'; ?>

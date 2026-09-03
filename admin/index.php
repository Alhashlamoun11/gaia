<?php
/**
 * admin/index.php
 * ------------------------------------------------------------
 * Admin Executive Dashboard with Full Statistics System:
 * - Top Metric Counters
 * - Interactive Analytics System (Hotels, Payments, Reservations, Users)
 * - Latest Activity Feeds
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

// Unified booking count
$totalBookings = ReportingService::totalBookings();

// Revenue = sum of total_price across all booking tables
$revenue = 0.0;
foreach (['bookings', 'weroad_bookings', 'hotel_bookings', 'room_bookings', 'event_bookings', 'taxi_bookings'] as $tbl) {
    try {
        $revenue += (float)$pdo->query("SELECT COALESCE(SUM(total_price),0) FROM `{$tbl}`")->fetchColumn();
    } catch (PDOException $e) {}
}

// Payments via existing PaymentService helper sets
$pendingP = ReportingService::pendingPayments();
$paidP    = ReportingService::paidPayments();
$pendingPayments = $pendingP['count'] ?? 0;
$completedPayments = $paidP['count'] ?? 0;

// ------------------------------------------------------------
// LATEST LISTS
// ------------------------------------------------------------
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

// Latest payments
$latestPayments = $pdo->query('SELECT id, booking_reference, amount, status, created_at, currency FROM payments ORDER BY created_at DESC LIMIT 5')->fetchAll();

// Latest contacts / inquiries
$latestContacts = [];
try {
    $latestContacts = $pdo->query('SELECT id, name, email, subject, created_at FROM contacts ORDER BY created_at DESC LIMIT 5')->fetchAll();
} catch (PDOException $e) {
    try {
        $latestContacts = $pdo->query('SELECT id, name, email, subject, created_at FROM messages ORDER BY created_at DESC LIMIT 5')->fetchAll();
    } catch (PDOException $e) {
        $latestContacts = [];
    }
}

// Role map for display
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

      <!-- Quick KPI Counters -->
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
      </div>

      <!-- ======================================================== -->
      <!-- EXECUTIVE STATISTICS & ANALYTICS SYSTEM -->
      <!-- ======================================================== -->
      <div class="analytics-toolbar">
        <div style="display:flex; align-items:center; gap:10px;">
          <h3 style="margin:0; font-size:16px; color:var(--navy); font-weight:800;">
            <i class="fa-solid fa-chart-pie" style="color:var(--teal); margin-right:6px;"></i> Business Intelligence &amp; Analytics
          </h3>
        </div>

        <div class="analytics-range-group">
          <button type="button" class="analytics-range-pill" data-range="7d">7 Days</button>
          <button type="button" class="analytics-range-pill active" data-range="30d">Last 30 Days</button>
          <button type="button" class="analytics-range-pill" data-range="90d">90 Days</button>
          <button type="button" class="analytics-range-pill" data-range="1y">This Year</button>
          <button type="button" class="analytics-range-pill" data-range="all">All Time</button>
        </div>
      </div>

      <section class="analytics-section">
        <!-- 1. Hotels Overview Card -->
        <div class="analytics-card" id="hotels-card">
          <div class="analytics-card-head">
            <h3 class="analytics-card-title"><i class="fa-solid fa-hotel"></i> Hotels &amp; Hospitality Overview</h3>
            <button class="analytics-export-btn" data-card-id="hotels-card" title="Export Hotel Data to CSV">
              <i class="fa-solid fa-download"></i> Export CSV
            </button>
          </div>

          <div class="analytics-kpi-grid">
            <div class="analytics-kpi-pill">
              <div class="kpi-lbl">Occupancy Rate</div>
              <div class="kpi-val kpi-occupancy">—</div>
            </div>
            <div class="analytics-kpi-pill">
              <div class="kpi-lbl">Hotel Revenue</div>
              <div class="kpi-val kpi-revenue" style="color:#10b981;">—</div>
            </div>
            <div class="analytics-kpi-pill">
              <div class="kpi-lbl">Avg. Rev / Res</div>
              <div class="kpi-val kpi-avg-rate">—</div>
            </div>
            <div class="analytics-kpi-pill">
              <div class="kpi-lbl">Active Rooms</div>
              <div class="kpi-val kpi-rooms">—</div>
            </div>
          </div>

          <div class="analytics-chart-wrap">
            <canvas id="hotels-canvas"></canvas>
          </div>
        </div>

        <!-- 2. Payments Overview Card -->
        <div class="analytics-card" id="payments-card">
          <div class="analytics-card-head">
            <h3 class="analytics-card-title"><i class="fa-solid fa-credit-card"></i> Payments &amp; Cashflow Overview</h3>
            <button class="analytics-export-btn" data-card-id="payments-card" title="Export Payments Data to CSV">
              <i class="fa-solid fa-download"></i> Export CSV
            </button>
          </div>

          <div class="analytics-kpi-grid">
            <div class="analytics-kpi-pill">
              <div class="kpi-lbl">Collected (Paid)</div>
              <div class="kpi-val kpi-paid" style="color:#10b981;">—</div>
            </div>
            <div class="analytics-kpi-pill">
              <div class="kpi-lbl">Pending Volume</div>
              <div class="kpi-val kpi-pending" style="color:#f59e0b;">—</div>
            </div>
            <div class="analytics-kpi-pill">
              <div class="kpi-lbl">Success Rate</div>
              <div class="kpi-val kpi-success-rate">—</div>
            </div>
            <div class="analytics-kpi-pill">
              <div class="kpi-lbl">Refunded</div>
              <div class="kpi-val kpi-refunded" style="color:#ef4444;">—</div>
            </div>
          </div>

          <div class="analytics-chart-wrap">
            <canvas id="payments-canvas"></canvas>
          </div>
        </div>

        <!-- 3. Reservations Overview Card -->
        <div class="analytics-card" id="reservations-card">
          <div class="analytics-card-head">
            <h3 class="analytics-card-title"><i class="fa-solid fa-calendar-check"></i> Reservations &amp; Bookings Overview</h3>
            <button class="analytics-export-btn" data-card-id="reservations-card" title="Export Reservations to CSV">
              <i class="fa-solid fa-download"></i> Export CSV
            </button>
          </div>

          <div class="analytics-kpi-grid">
            <div class="analytics-kpi-pill">
              <div class="kpi-lbl">Total Bookings</div>
              <div class="kpi-val kpi-total-res">—</div>
            </div>
            <div class="analytics-kpi-pill">
              <div class="kpi-lbl">Total Sales</div>
              <div class="kpi-val kpi-res-revenue" style="color:#10b981;">—</div>
            </div>
            <div class="analytics-kpi-pill">
              <div class="kpi-lbl">Cancellation %</div>
              <div class="kpi-val kpi-cancel-rate" style="color:#ef4444;">—</div>
            </div>
            <div class="analytics-kpi-pill">
              <div class="kpi-lbl">Avg Booking Value</div>
              <div class="kpi-val kpi-avg-booking">—</div>
            </div>
          </div>

          <div class="analytics-chart-wrap">
            <canvas id="reservations-canvas"></canvas>
          </div>
        </div>

        <!-- 4. New Users Overview Card -->
        <div class="analytics-card" id="users-card">
          <div class="analytics-card-head">
            <h3 class="analytics-card-title"><i class="fa-solid fa-user-plus"></i> New Users &amp; Client Acquisition</h3>
            <button class="analytics-export-btn" data-card-id="users-card" title="Export Users Data to CSV">
              <i class="fa-solid fa-download"></i> Export CSV
            </button>
          </div>

          <div class="analytics-kpi-grid">
            <div class="analytics-kpi-pill">
              <div class="kpi-lbl">Total Registered</div>
              <div class="kpi-val kpi-total-users">—</div>
            </div>
            <div class="analytics-kpi-pill">
              <div class="kpi-lbl">New in Period</div>
              <div class="kpi-val kpi-new-users" style="color:var(--teal);">—</div>
            </div>
            <div class="analytics-kpi-pill">
              <div class="kpi-lbl">Active Accounts</div>
              <div class="kpi-val kpi-active-users" style="color:#10b981;">—</div>
            </div>
            <div class="analytics-kpi-pill">
              <div class="kpi-lbl">Inactive</div>
              <div class="kpi-val kpi-inactive-users" style="color:var(--muted);">—</div>
            </div>
          </div>

          <div class="analytics-chart-wrap">
            <canvas id="users-canvas"></canvas>
          </div>
        </div>
      </section>

      <!-- Latest Feeds -->
      <div class="grid-2">
        <div class="card" style="margin-bottom:0;">
          <h3><i class="fa-solid fa-bolt"></i> <?= htmlspecialchars(t('admin.latest_bookings')) ?></h3>
          <div class="list-group">
            <?php if (!$latestBookings): ?>
              <div class="muted" style="text-align:center;padding:18px;"><?= htmlspecialchars(t('admin.no_records')) ?></div>
            <?php else: foreach ($latestBookings as $b): $ref = $b['booking_reference'] ?: ('#' . (int)$b['id']); ?>
              <div class="list-item">
                <div class="list-item-main">
                  <div class="list-item-title"><?= htmlspecialchars($ref) ?></div>
                  <div class="list-item-sub"><?= htmlspecialchars($b['table']) ?> &bull; <?= htmlspecialchars(date('Y-m-d', strtotime((string)($b['created_at'] ?? 'now')))) ?></div>
                </div>
                <div class="list-item-right">
                  <div class="list-item-value"><?= htmlspecialchars(number_format((float)($b['total_price'] ?? 0), 2)) ?></div>
                  <span class="badge badge-<?= htmlspecialchars(normalize_booking_status((string)($b['status'] ?? 'pending'))) ?>"><?= htmlspecialchars(booking_status_label(normalize_booking_status((string)($b['status'] ?? 'pending')))) ?></span>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>

        <div class="card" style="margin-bottom:0;">
          <h3><i class="fa-solid fa-users"></i> <?= htmlspecialchars(t('admin.latest_users')) ?></h3>
          <div class="list-group">
            <?php if (!$latestUsers): ?>
              <div class="muted" style="text-align:center;padding:18px;"><?= htmlspecialchars(t('admin.no_records')) ?></div>
            <?php else: foreach ($latestUsers as $u): ?>
              <div class="list-item">
                <div class="list-item-main">
                  <div class="list-item-title"><?= htmlspecialchars(trim($u['first_name'] . ' ' . $u['last_name'])) ?></div>
                  <div class="list-item-sub"><?= htmlspecialchars($roles[(int)$u['role_id']] ?? 'customer') ?> &bull; <?= htmlspecialchars(date('Y-m-d', strtotime((string)($u['created_at'] ?? 'now')))) ?></div>
                </div>
                <div class="list-item-right">
                  <span class="badge badge-<?= htmlspecialchars($u['status']) ?>"><?= htmlspecialchars($u['status']) ?></span>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>

      <div class="grid-3" style="margin-top:22px;">
        <div class="card" style="margin-bottom:0;">
          <h3><i class="fa-solid fa-star"></i> <?= htmlspecialchars(t('admin.latest_reviews')) ?></h3>
          <div class="list-group">
            <?php if (!$latestReviews): ?>
              <div class="muted" style="text-align:center;padding:18px;"><?= htmlspecialchars(t('admin.no_records')) ?></div>
            <?php else: foreach ($latestReviews as $rv): ?>
              <div class="list-item">
                <div class="list-item-main">
                  <div class="list-item-title"><?= htmlspecialchars($rv['reviewer']) ?></div>
                  <div class="list-item-sub"><?= htmlspecialchars($rv['rating']) ?>/5 <i class="fa-solid fa-star" style="color:var(--gold);font-size:10px;"></i></div>
                </div>
                <div class="list-item-right">
                  <span class="badge badge-<?= (int)$rv['is_active'] ? 'active' : 'inactive' ?>"><?= (int)$rv['is_active'] ? htmlspecialchars(t('admin.active')) : htmlspecialchars(t('admin.inactive')) ?></span>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>

        <div class="card" style="margin-bottom:0;">
          <h3><i class="fa-solid fa-credit-card"></i> <?= htmlspecialchars(t('admin.latest_payments')) ?></h3>
          <div class="list-group">
            <?php if (!$latestPayments): ?>
              <div class="muted" style="text-align:center;padding:18px;"><?= htmlspecialchars(t('admin.no_records')) ?></div>
            <?php else: foreach ($latestPayments as $pay): ?>
              <div class="list-item">
                <div class="list-item-main">
                  <div class="list-item-title"><?= htmlspecialchars(number_format((float)($pay['amount'] ?? 0), 2)) ?> <?= htmlspecialchars($pay['currency'] ?? 'USD') ?></div>
                  <div class="list-item-sub"><?= htmlspecialchars(date('Y-m-d', strtotime((string)($pay['created_at'] ?? 'now')))) ?></div>
                </div>
                <div class="list-item-right">
                  <span class="badge badge-<?= htmlspecialchars(normalize_payment_status((string)($pay['status'] ?? 'pending'))) ?>"><?= htmlspecialchars(payment_status_label(normalize_payment_status((string)($pay['status'] ?? 'pending')))) ?></span>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>

        <div class="card" style="margin-bottom:0;">
          <h3><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars(t('admin.latest_contacts')) ?></h3>
          <div class="list-group">
            <?php if (!$latestContacts): ?>
              <div class="muted" style="text-align:center;padding:18px;"><?= htmlspecialchars(t('admin.no_records')) ?></div>
            <?php else: foreach ($latestContacts as $c): ?>
              <div class="list-item">
                <div class="list-item-main">
                  <div class="list-item-title"><?= htmlspecialchars($c['name'] ?? '') ?></div>
                  <div class="list-item-sub"><?= htmlspecialchars($c['email'] ?? '') ?> &bull; <?= htmlspecialchars(date('Y-m-d', strtotime((string)($c['created_at'] ?? 'now')))) ?></div>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<script src="/admin/assets/js/analytics.js"></script>
<?php require __DIR__ . '/components/footer.php'; ?>

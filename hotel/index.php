<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../services/HotelAvailabilityService.php';
$my_hotel_id = require_hotel_manager();

$hotel_active = 'dashboard';
$hotel_page_title = t('hotel.dashboard', 'Hotel Dashboard') . ' — GAIA TOURS';

$pdo = getPDO();
$currentHotel = current_hotel();
$hotelName = $currentHotel ? lang_value($currentHotel, 'name', 'Your Hotel') : 'Your Hotel';
$hotelSlug = $currentHotel['slug'] ?? '';
$hotelStars = (int)($currentHotel['star_rating'] ?? 5);
$hotelRating = (float)($currentHotel['avg_rating'] ?? 4.8);

// 1. Fetch KPI stats
$q = $pdo->prepare("SELECT COUNT(*) FROM hotel_rooms WHERE hotel_id = ?");
$q->execute([$my_hotel_id]);
$total_rooms = (int)$q->fetchColumn();

$q = $pdo->prepare("SELECT COUNT(*) FROM hotel_rooms WHERE hotel_id = ? AND is_active = 1");
$q->execute([$my_hotel_id]);
$active_rooms = (int)$q->fetchColumn();

$q = $pdo->prepare("SELECT COUNT(*) FROM hotel_bookings WHERE hotel_id = ? AND status = 'pending'");
$q->execute([$my_hotel_id]);
$pending_bookings = (int)$q->fetchColumn();

$q = $pdo->prepare("SELECT COUNT(*) FROM hotel_bookings WHERE hotel_id = ? AND status IN ('confirmed','paid')");
$q->execute([$my_hotel_id]);
$confirmed_bookings = (int)$q->fetchColumn();

$today = date('Y-m-d');
$q = $pdo->prepare("SELECT COUNT(*) FROM hotel_bookings WHERE hotel_id = ? AND check_in_date = ? AND status IN ('confirmed','paid')");
$q->execute([$my_hotel_id, $today]);
$checkins_today = (int)$q->fetchColumn();

$q = $pdo->prepare("SELECT COUNT(*) FROM hotel_bookings WHERE hotel_id = ? AND check_out_date = ? AND status IN ('confirmed','paid')");
$q->execute([$my_hotel_id, $today]);
$checkouts_today = (int)$q->fetchColumn();

// 2. Fetch recent bookings with room details
$q = $pdo->prepare("
    SELECT b.*, r.name as room_name, r.name_ar as room_name_ar 
    FROM hotel_bookings b 
    LEFT JOIN hotel_rooms r ON b.room_id = r.id 
    WHERE b.hotel_id = ? 
    ORDER BY b.id DESC LIMIT 6
");
$q->execute([$my_hotel_id]);
$recent_bookings = $q->fetchAll();

// 3. Revenue KPI
$total_revenue = HotelAvailabilityService::totalRevenue($pdo, $my_hotel_id);

// 4. Occupancy KPI
$total_inventory = HotelAvailabilityService::totalInventory($pdo, $my_hotel_id);
$occupied_today = HotelAvailabilityService::totalOccupiedOnDate($pdo, $my_hotel_id, $today);
if ($occupied_today > $total_inventory && $total_inventory > 0) {
    $occupied_today = $total_inventory;
}
$occupancy_percent = $total_inventory > 0 ? round(($occupied_today / $total_inventory) * 100) : 0;

// 5. Fetch Room Categories with availability
$q = $pdo->prepare("SELECT * FROM hotel_rooms WHERE hotel_id = ? AND is_active = 1 ORDER BY price ASC");
$q->execute([$my_hotel_id]);
$room_list = $q->fetchAll();

// 6. Fetch Recent Reviews
$q = $pdo->prepare("SELECT * FROM hotel_reviews WHERE hotel_id = ? ORDER BY id DESC LIMIT 3");
$q->execute([$my_hotel_id]);
$recent_reviews = $q->fetchAll();

$currency = site_setting('currency_symbol', '$');

require __DIR__ . '/components/header.php';
?>
<div class="admin-app">
  <?php require __DIR__ . '/components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/components/alert.php'; ?>

      <!-- 1. Welcome & Hotel Status Hero -->
      <div class="hotel-hero">
        <div class="hotel-hero-content">
          <div class="hotel-hero-tag">
            <i class="fa-solid fa-crown"></i> <?= str_repeat('★', $hotelStars) ?> <?= htmlspecialchars($hotelName) ?>
          </div>
          <h2 class="hotel-hero-title">Welcome back, Manager</h2>
          <p class="hotel-hero-sub">Here is your live hotel performance, today's arrivals, and room inventory overview.</p>
        </div>
        <div class="hotel-hero-actions">
          <?php if ($hotelSlug): ?>
            <a href="<?= gaia_url('hotel.php?slug=' . urlencode($hotelSlug)) ?>" target="_blank" class="btn btn-ghost" style="background:rgba(255,255,255,0.15); color:#fff; border:1px solid rgba(255,255,255,0.3); backdrop-filter:blur(4px);">
              <i class="fa-solid fa-arrow-up-right-from-square"></i> View Live Page
            </a>
          <?php endif; ?>
          <a href="<?= hotel_url('availability.php') ?>" class="btn" style="background:#17c9b3; color:#0d1730; font-weight:700; border:none;">
            <i class="fa-solid fa-calendar-days"></i> Inventory Calendar
          </a>
        </div>
      </div>

      <!-- 2. KPI Metrics Grid -->
      <div class="stats-grid">
        
        <!-- Total Revenue -->
        <div class="stat-card">
          <div class="stat-icon blue" style="background:rgba(39,174,96,0.12); color:#27ae60;">
            <i class="fa-solid fa-money-bill-trend-up"></i>
          </div>
          <div>
            <div class="stat-value" style="font-size:22px;"><?= $currency ?><?= number_format($total_revenue, 2) ?></div>
            <div class="stat-label">Total Revenue</div>
          </div>
        </div>

        <!-- Today's Occupancy -->
        <div class="stat-card">
          <div class="stat-icon green" style="background:rgba(23,201,179,0.12); color:#0c7c6d;">
            <i class="fa-solid fa-chart-pie"></i>
          </div>
          <div>
            <div class="stat-value" style="font-size:22px;"><?= $occupancy_percent ?>% <small style="font-size:12px; color:var(--muted); font-weight:500;">(<?= $occupied_today ?>/<?= $total_inventory ?>)</small></div>
            <div class="stat-label">Today's Occupancy</div>
          </div>
        </div>

        <!-- Today Arrivals -->
        <div class="stat-card">
          <div class="stat-icon blue" style="background:rgba(41,128,185,0.12); color:#2980b9;">
            <i class="fa-solid fa-plane-arrival"></i>
          </div>
          <div>
            <div class="stat-value" style="font-size:22px;"><?= $checkins_today ?></div>
            <div class="stat-label">Arrivals Today</div>
          </div>
        </div>

        <!-- Today Departures -->
        <div class="stat-card">
          <div class="stat-icon gold" style="background:rgba(230,126,34,0.12); color:#e67e22;">
            <i class="fa-solid fa-plane-departure"></i>
          </div>
          <div>
            <div class="stat-value" style="font-size:22px;"><?= $checkouts_today ?></div>
            <div class="stat-label">Departures Today</div>
          </div>
        </div>

        <!-- Active Room Types -->
        <div class="stat-card">
          <div class="stat-icon navy" style="background:rgba(142,68,173,0.12); color:#8e44ad;">
            <i class="fa-solid fa-bed"></i>
          </div>
          <div>
            <div class="stat-value" style="font-size:22px;"><?= $active_rooms ?> <small style="font-size:12px; color:var(--muted); font-weight:500;">/ <?= $total_rooms ?></small></div>
            <div class="stat-label">Active Room Types</div>
          </div>
        </div>

      </div>

      <!-- 3. Operational Grid (2 Columns: Recent Bookings & Inventory Health) -->
      <div class="hotel-grid-main">
        
        <!-- Left: Recent Guest Bookings -->
        <div class="card" style="margin-bottom:0;">
          <div class="card-head">
            <div>
              <h3 style="font-size:17px; font-weight:800; margin:0 0 4px;"><i class="fa-solid fa-bookmark" style="color:var(--teal);"></i> Recent Guest Bookings</h3>
              <p style="font-size:12.5px; color:var(--muted); margin:0;">Latest reservations confirmed on GAIA platform.</p>
            </div>
            <a href="<?= hotel_url('bookings.php') ?>" class="btn-link" style="font-weight:700; font-size:13px;">View All →</a>
          </div>

          <div class="table-wrap" style="box-shadow:none; border:1px solid var(--line);">
            <table>
              <thead>
                <tr>
                  <th>Reference</th>
                  <th>Guest</th>
                  <th>Room Category</th>
                  <th>Dates</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($recent_bookings)): ?>
                  <?php foreach ($recent_bookings as $b): ?>
                    <?php
                      $statusColor = 'gray';
                      if ($b['status'] === 'confirmed') $statusColor = 'green';
                      elseif ($b['status'] === 'paid') $statusColor = 'green';
                      elseif ($b['status'] === 'pending') $statusColor = 'gold';
                      elseif ($b['status'] === 'cancelled') $statusColor = 'red';
                    ?>
                    <tr>
                      <td><strong style="font-family:monospace; color:#111317; font-size:13px;"><?= htmlspecialchars($b['booking_code'] ?: 'BK-' . $b['id']) ?></strong></td>
                      <td>
                        <div style="font-weight:700; color:#111317; font-size:13.5px;"><?= htmlspecialchars($b['guest_name'] ?: 'Guest') ?></div>
                        <div style="font-size:11.5px; color:var(--muted);"><?= htmlspecialchars($b['guest_email'] ?: '') ?></div>
                      </td>
                      <td><?= htmlspecialchars(lang_value($b, 'room_name') ?: 'Standard Suite') ?></td>
                      <td>
                        <div style="font-size:12.5px; font-weight:600;"><?= htmlspecialchars($b['check_in_date']) ?></div>
                        <div style="font-size:11px; color:var(--muted);">to <?= htmlspecialchars($b['check_out_date']) ?></div>
                      </td>
                      <td><span class="status-badge <?= $statusColor ?>"><?= htmlspecialchars(ucfirst($b['status'])) ?></span></td>
                      <td>
                        <a href="booking.php?id=<?= $b['id'] ?>" class="btn-link" style="font-weight:700; font-size:12.5px;">Manage</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="6"><div class="muted" style="text-align:center; padding:28px;">No guest reservations recorded yet.</div></td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Right: Inventory Status & Recent Reviews -->
        <div style="display:flex; flex-direction:column; gap:20px;">
          
          <!-- Room Inventory Summary -->
          <div class="card" style="margin-bottom:0;">
            <div class="card-head" style="margin-bottom:14px;">
              <h3 style="font-size:16px; font-weight:800; margin:0;"><i class="fa-solid fa-door-open" style="color:#0c7c6d;"></i> Room Types & Rates</h3>
              <a href="<?= hotel_url('rooms.php') ?>" class="btn-link" style="font-size:12.5px; font-weight:700;">Manage</a>
            </div>

            <div style="display:flex; flex-direction:column; gap:10px;">
              <?php if (!empty($room_list)): ?>
                <?php foreach ($room_list as $rm): ?>
                  <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 12px; background:#fbfaf7; border:1px solid var(--line); border-radius:10px; gap:8px;">
                    <div style="min-width:0;">
                      <div style="font-weight:700; font-size:13.5px; color:#111317; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars(lang_value($rm, 'name')) ?></div>
                      <div style="font-size:11.5px; color:var(--muted);"><i class="fa-solid fa-bed"></i> <?= (int)$rm['quantity'] ?> units · Up to <?= (int)$rm['capacity'] ?> guests</div>
                    </div>
                    <div style="text-align:right; flex-shrink:0;">
                      <div style="font-weight:800; font-size:14.5px; color:#111317;"><?= $currency ?><?= number_format((float)$rm['price'], 0) ?></div>
                      <div style="font-size:10.5px; color:var(--muted);">per night</div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div style="font-size:13px; color:var(--muted); text-align:center; padding:14px;">No rooms configured yet.</div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Recent Guest Reviews -->
          <div class="card" style="margin-bottom:0;">
            <div class="card-head" style="margin-bottom:14px;">
              <h3 style="font-size:16px; font-weight:800; margin:0;"><i class="fa-solid fa-star" style="color:#f59e0b;"></i> Guest Reviews</h3>
              <span style="font-size:13px; font-weight:700; color:#111317;">★ <?= number_format($hotelRating, 1) ?></span>
            </div>

            <?php if (!empty($recent_reviews)): ?>
              <div style="display:flex; flex-direction:column; gap:10px;">
                <?php foreach ($recent_reviews as $rv): ?>
                  <div style="background:#fbfaf7; border:1px solid var(--line); border-radius:10px; padding:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px; gap:8px;">
                      <strong style="font-size:13px; color:#111317;"><?= htmlspecialchars($rv['reviewer']) ?></strong>
                      <span style="color:#f59e0b; font-size:11px; flex-shrink:0;"><?= str_repeat('★', (int)$rv['rating']) ?></span>
                    </div>
                    <p style="font-size:12px; color:#475569; margin:0; line-height:1.45;"><?= htmlspecialchars(mb_strimwidth($rv['text'], 0, 90, '...')) ?></p>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div style="font-size:13px; color:var(--muted); text-align:center; padding:14px;">No reviews yet.</div>
            <?php endif; ?>
          </div>

        </div>

      </div>

    </div>
  </div>
</div>
<?php require __DIR__ . '/components/footer.php'; ?>

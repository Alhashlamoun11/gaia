<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../services/HotelAvailabilityService.php';
$my_hotel_id = require_hotel_manager();

$hotel_active = 'dashboard';
$hotel_page_title = t('hotel.dashboard', 'Dashboard');

$pdo = getPDO();

// Fetch basic stats
$q = $pdo->prepare("SELECT COUNT(*) FROM hotel_rooms WHERE hotel_id = ?");
$q->execute([$my_hotel_id]);
$total_rooms = $q->fetchColumn();

$q = $pdo->prepare("SELECT COUNT(*) FROM hotel_rooms WHERE hotel_id = ? AND is_active = 1");
$q->execute([$my_hotel_id]);
$active_rooms = $q->fetchColumn();

$q = $pdo->prepare("SELECT COUNT(*) FROM hotel_bookings WHERE hotel_id = ? AND status = 'pending'");
$q->execute([$my_hotel_id]);
$pending_bookings = $q->fetchColumn();

$q = $pdo->prepare("SELECT COUNT(*) FROM hotel_bookings WHERE hotel_id = ? AND status = 'confirmed'");
$q->execute([$my_hotel_id]);
$confirmed_bookings = $q->fetchColumn();

$today = date('Y-m-d');
$q = $pdo->prepare("SELECT COUNT(*) FROM hotel_bookings WHERE hotel_id = ? AND check_in_date = ? AND status IN ('confirmed','paid')");
$q->execute([$my_hotel_id, $today]);
$checkins_today = $q->fetchColumn();

$q = $pdo->prepare("SELECT COUNT(*) FROM hotel_bookings WHERE hotel_id = ? AND check_out_date = ? AND status IN ('confirmed','paid')");
$q->execute([$my_hotel_id, $today]);
$checkouts_today = $q->fetchColumn();

// Fetch recent bookings
$q = $pdo->prepare("SELECT * FROM hotel_bookings WHERE hotel_id = ? ORDER BY id DESC LIMIT 5");
$q->execute([$my_hotel_id]);
$recent_bookings = $q->fetchAll();

// Revenue KPI (only revenue statuses: paid, confirmed, completed)
$total_revenue = HotelAvailabilityService::totalRevenue($pdo, $my_hotel_id);

// Occupancy KPI
$total_inventory = HotelAvailabilityService::totalInventory($pdo, $my_hotel_id);
$occupied_today = HotelAvailabilityService::totalOccupiedOnDate($pdo, $my_hotel_id, $today);
// Cap to prevent display inconsistency from legacy data
if ($occupied_today > $total_inventory && $total_inventory > 0) {
    error_log("Phase3: Occupancy exceeds inventory for hotel_id={$my_hotel_id}: occupied={$occupied_today} > inventory={$total_inventory}");
    $occupied_today = $total_inventory;
}

require __DIR__ . '/components/header.php';
?>
<div class="admin-app">
  <?php require __DIR__ . '/components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/components/alert.php'; ?>

      <div class="stats-grid">
        <?php
          $stat_value = $total_rooms; $stat_label = t('admin.rooms'); $stat_icon = 'fa-bed'; $stat_color = 'navy'; $stat_link = hotel_url('rooms.php');
          require __DIR__ . '/../admin/components/stats-card.php';
          $stat_value = $active_rooms; $stat_label = "Active Rooms"; $stat_icon = 'fa-door-open'; $stat_color = 'green'; $stat_link = hotel_url('rooms.php');
          require __DIR__ . '/../admin/components/stats-card.php';
          $stat_value = $pending_bookings; $stat_label = "Pending Bookings"; $stat_icon = 'fa-clock'; $stat_color = 'red'; $stat_link = hotel_url('bookings.php');
          require __DIR__ . '/../admin/components/stats-card.php';
          $stat_value = $confirmed_bookings; $stat_label = "Confirmed Bookings"; $stat_icon = 'fa-circle-check'; $stat_color = 'gold'; $stat_link = hotel_url('bookings.php');
          require __DIR__ . '/../admin/components/stats-card.php';
          $stat_value = '$' . number_format($total_revenue, 2); $stat_label = t('hotel.total_revenue', 'Total Revenue'); $stat_icon = 'fa-money-bill-trend-up'; $stat_color = 'green'; $stat_link = hotel_url('bookings.php');
          require __DIR__ . '/../admin/components/stats-card.php';
          $stat_value = $occupied_today . ' / ' . $total_inventory; $stat_label = t('hotel.occupancy_today', 'Occupancy Today'); $stat_icon = 'fa-chart-pie'; $stat_color = 'navy'; $stat_link = hotel_url('availability.php');
          require __DIR__ . '/../admin/components/stats-card.php';
        ?>
      </div>

      <div class="stats-grid" style="grid-template-columns:repeat(2,1fr);">
        <div class="card" style="margin-bottom:0;">
          <h3><i class="fa-solid fa-bolt"></i> Recent Bookings</h3>
          <div class="table-wrap" style="box-shadow:none;border:none;">
            <table>
              <thead>
                <tr>
                  <th>Reference</th>
                  <th>Guest</th>
                  <th>Check-in</th>
                  <th>Check-out</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($recent_bookings as $b): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($b['booking_code']) ?></strong></td>
                    <td><?= htmlspecialchars($b['guest_name']) ?></td>
                    <td><?= htmlspecialchars($b['check_in_date']) ?></td>
                    <td><?= htmlspecialchars($b['check_out_date']) ?></td>
                    <td><span class="status-badge <?= $b['status'] === 'confirmed' ? 'green' : ($b['status'] === 'pending' ? 'gold' : 'gray') ?>"><?= htmlspecialchars($b['status']) ?></span></td>
                    <td>
                      <a href="booking.php?id=<?= $b['id'] ?>" class="btn-link">View</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if(!$recent_bookings): ?>
                  <tr><td colspan="6"><div class="muted" style="text-align:center;padding:18px;">No bookings yet.</div></td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<?php require __DIR__ . '/components/footer.php'; ?>

<?php
require_once __DIR__ . '/_bootstrap.php';
$my_hotel_id = require_hotel_manager();

$hotel_active = 'bookings';
$hotel_page_title = t('hotel.bookings', 'Bookings');

$pdo = getPDO();

$stmt = $pdo->prepare("SELECT b.*, r.name as room_name FROM hotel_bookings b LEFT JOIN hotel_rooms r ON b.room_id = r.id WHERE b.hotel_id = ? ORDER BY b.id DESC");
$stmt->execute([$my_hotel_id]);
$bookings = $stmt->fetchAll();

require __DIR__ . '/components/header.php';
?>
<div class="admin-app">
  <?php require __DIR__ . '/components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/components/alert.php'; ?>

      <div class="toolbar">
        <h2>Guest Reservations</h2>
        <div class="spacer" style="flex:1;"></div>
        <span style="font-size:13px; color:var(--muted); font-weight:600;"><?= count($bookings) ?> Total Bookings</span>
      </div>

      <div class="card" style="padding:0; overflow:hidden;">
        <div class="table-wrap" style="box-shadow:none; border:none;">
          <table>
            <thead>
              <tr>
                <th>Reference</th>
                <th>Guest</th>
                <th>Room</th>
                <th>Stay Dates</th>
                <th>Total</th>
                <th style="text-align:center;"><?= htmlspecialchars(t('hotel.rooms_label', 'Qty')) ?></th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($bookings as $b): ?>
                <tr>
                  <td><strong style="font-family:monospace; font-size:13px;"><?= htmlspecialchars($b['booking_code']) ?></strong></td>
                  <td>
                    <div style="font-weight:700; color:var(--ink);"><?= htmlspecialchars($b['guest_name']) ?></div>
                    <div style="font-size:11.5px; color:var(--muted);"><?= htmlspecialchars($b['guest_email'] ?: '') ?></div>
                  </td>
                  <td><?= htmlspecialchars($b['room_name']) ?></td>
                  <td>
                    <div style="font-weight:600; font-size:13px;"><?= htmlspecialchars($b['check_in_date']) ?></div>
                    <div style="font-size:11px; color:var(--muted);">to <?= htmlspecialchars($b['check_out_date']) ?></div>
                  </td>
                  <td><strong>$<?= number_format($b['total_price'], 2) ?></strong></td>
                  <td style="text-align:center; font-weight:600;"><?= (int)($b['rooms_count'] ?? 1) ?></td>
                  <td>
                    <?php
                       $c = match($b['status']){
                         'pending'=>'gold',
                         'confirmed','paid'=>'green',
                         'cancelled'=>'red',
                         'completed'=>'blue',
                         default=>'gray'
                       };
                    ?>
                    <span class="status-badge <?= $c ?>"><?= ucfirst($b['status']) ?></span>
                  </td>
                  <td>
                    <a href="booking.php?id=<?= $b['id'] ?>" class="btn-link">Manage</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if(!$bookings): ?>
                <tr><td colspan="8"><div class="muted" style="text-align:center;padding:24px;">No reservations found.</div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>
<?php require __DIR__ . '/components/footer.php'; ?>

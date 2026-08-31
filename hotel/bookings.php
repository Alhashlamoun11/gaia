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

      <div class="card">
        <div class="card-head">
          <h2>Bookings</h2>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Ref</th>
                <th>Guest</th>
                <th>Room</th>
                <th>Check-in</th>
                <th>Check-out</th>
                <th>Amount</th>
                <th><?= htmlspecialchars(t('hotel.rooms_label', 'Rooms')) ?></th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($bookings as $b): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($b['booking_code']) ?></strong></td>
                  <td><?= htmlspecialchars($b['guest_name']) ?></td>
                  <td><?= htmlspecialchars($b['room_name']) ?></td>
                  <td><?= htmlspecialchars($b['check_in_date']) ?></td>
                  <td><?= htmlspecialchars($b['check_out_date']) ?></td>
                  <td>$<?= number_format($b['total_price'], 2) ?></td>
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
                <tr><td colspan="9"><div class="muted" style="text-align:center;padding:18px;">No bookings found.</div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>
<?php require __DIR__ . '/components/footer.php'; ?>

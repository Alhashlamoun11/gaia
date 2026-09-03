<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../services/BookingTimelineService.php';

$my_hotel_id = require_hotel_manager();

$hotel_active = 'bookings';
$hotel_page_title = t('hotel.bookings', 'Manage Booking');

$pdo = getPDO();
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $new_status = $_POST['status'] ?? '';
    
    // Verify ownership
    $check = $pdo->prepare("SELECT id FROM hotel_bookings WHERE id = ? AND hotel_id = ?");
    $check->execute([$id, $my_hotel_id]);
    if ($check->fetchColumn()) {
        $stmt = $pdo->prepare("UPDATE hotel_bookings SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $id]);
        
        BookingTimelineService::log('hotel', $id, auth_id(), 'status_changed', "Hotel Manager changed status to $new_status");
        
        Auth::logAudit('hotel_booking_status_updated');
        hotel_flash('Booking status updated.', 'success');
    }
    header("Location: booking.php?id=$id");
    exit;
}

$stmt = $pdo->prepare("SELECT b.*, r.name as room_name, u.email as user_email FROM hotel_bookings b LEFT JOIN hotel_rooms r ON b.room_id = r.id LEFT JOIN users u ON b.user_id = u.id WHERE b.id = ? AND b.hotel_id = ?");
$stmt->execute([$id, $my_hotel_id]);
$booking = $stmt->fetch();

if (!$booking) {
    die("Booking not found or unauthorized.");
}

$logs = $pdo->prepare("SELECT l.*, u.first_name, u.last_name FROM booking_logs l LEFT JOIN users u ON l.user_id = u.id WHERE l.booking_id = ? AND l.booking_type = 'hotel' ORDER BY l.created_at DESC");
$logs->execute([$id]);
$timeline = $logs->fetchAll();

require __DIR__ . '/components/header.php';
?>
<div class="admin-app">
  <?php require __DIR__ . '/components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/components/alert.php'; ?>

      <div class="toolbar">
        <div style="display:flex; align-items:center; gap:10px;">
          <a href="bookings.php" class="btn btn-ghost btn-sm" aria-label="Back to bookings"><i class="fa-solid fa-arrow-left"></i></a>
          <h2>Booking #<?= htmlspecialchars($booking['booking_code']) ?></h2>
        </div>
        <div class="spacer" style="flex:1;"></div>
        <?php
           $c = match($booking['status']){
             'pending'=>'gold',
             'confirmed','paid'=>'green',
             'cancelled'=>'red',
             'completed'=>'blue',
             default=>'gray'
           };
        ?>
        <span class="status-badge <?= $c ?>"><?= strtoupper($booking['status']) ?></span>
      </div>

      <div class="detail-grid">
        <div>
          <div class="card" style="margin-bottom:20px;">
            <h3><i class="fa-solid fa-circle-info"></i> Booking Details</h3>
            <div class="table-wrap" style="box-shadow:none; border:1px solid var(--line);">
              <table>
                <tr><th style="background:#fbfaf7; width:35%; min-width:110px;">Reference</th><td><strong><?= htmlspecialchars($booking['booking_code']) ?></strong></td></tr>
                <tr><th style="background:#fbfaf7;">Room Type</th><td><?= htmlspecialchars($booking['room_name']) ?></td></tr>
                <tr><th style="background:#fbfaf7;">Guest Name</th><td><?= htmlspecialchars($booking['guest_name']) ?></td></tr>
                <tr><th style="background:#fbfaf7;">Contact Email</th><td><a href="mailto:<?= htmlspecialchars($booking['guest_email'] ?: $booking['user_email']) ?>" class="btn-link"><?= htmlspecialchars($booking['guest_email'] ?: $booking['user_email']) ?></a></td></tr>
                <tr><th style="background:#fbfaf7;">Phone</th><td><?= htmlspecialchars($booking['guest_phone']) ?></td></tr>
                <tr><th style="background:#fbfaf7;">Check-in</th><td><?= htmlspecialchars($booking['check_in_date']) ?></td></tr>
                <tr><th style="background:#fbfaf7;">Check-out</th><td><?= htmlspecialchars($booking['check_out_date']) ?></td></tr>
                <tr><th style="background:#fbfaf7;"><?= htmlspecialchars(t('hotel.rooms_booked', 'Rooms Booked')) ?></th><td><strong><?= (int)($booking['rooms_count'] ?? 1) ?></strong></td></tr>
                <tr><th style="background:#fbfaf7;"><?= htmlspecialchars(t('hotel.guests_label', 'Guests')) ?></th><td><?= (int)($booking['guests'] ?? 1) ?></td></tr>
                <tr><th style="background:#fbfaf7;"><?= htmlspecialchars(t('admin.admin_commission', 'Admin Commission')) ?></th><td><strong><?= htmlspecialchars($booking['admin_commission_percent']) ?>%</strong></td></tr>
                <tr><th style="background:#fbfaf7;">Total Price</th><td><strong style="color:var(--teal); font-size:16px;">$<?= number_format($booking['total_price'], 2) ?></strong></td></tr>
              </table>
            </div>
          </div>

          <div class="card">
            <h3><i class="fa-solid fa-clock-rotate-left"></i> Timeline Logs</h3>
            <?php if($timeline): ?>
              <ul style="list-style:none; padding:0; margin:0;">
                <?php foreach($timeline as $l): ?>
                  <li style="padding:12px 0; border-bottom:1px solid var(--line);">
                    <small style="color:var(--muted); display:block; margin-bottom:4px;"><?= htmlspecialchars($l['created_at']) ?> by <?= htmlspecialchars(($l['first_name'] ?? 'System') . ' ' . ($l['last_name'] ?? '')) ?></small>
                    <strong><?= htmlspecialchars($l['action'] ?? '') ?></strong><?= !empty($l['description']) ? ': ' . htmlspecialchars($l['description']) : '' ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p style="color:var(--muted);">No timeline records found.</p>
            <?php endif; ?>
          </div>
        </div>

        <div>
          <div class="card" style="background:#fff;">
            <h3><i class="fa-solid fa-sliders"></i> Update Status</h3>
            <form method="post" action="booking.php?id=<?= $id ?>">
              <?= csrf_field() ?>
              <div class="field">
                <label for="statusSelect">Reservation Status</label>
                <select id="statusSelect" name="status" style="width:100%; margin-bottom:16px; padding:11px 14px; border:1px solid var(--line); border-radius:10px; font-weight:600;">
                  <option value="pending" <?= $booking['status']==='pending'?'selected':'' ?>>Pending</option>
                  <option value="confirmed" <?= $booking['status']==='confirmed'?'selected':'' ?>>Confirmed</option>
                  <option value="completed" <?= $booking['status']==='completed'?'selected':'' ?>>Completed</option>
                  <option value="cancelled" <?= $booking['status']==='cancelled'?'selected':'' ?>>Cancelled</option>
                </select>
              </div>
              <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Update Status</button>
            </form>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<?php require __DIR__ . '/components/footer.php'; ?>

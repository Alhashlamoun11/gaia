<?php

// 1. Availability
file_put_contents(__DIR__ . '/hotel/availability.php', <<<'EOT'
<?php
require_once __DIR__ . '/_bootstrap.php';
$my_hotel_id = require_hotel_manager();

$hotel_active = 'availability';
$hotel_page_title = t('hotel.availability', 'Availability');

$pdo = getPDO();

$stmt = $pdo->prepare("SELECT * FROM hotel_rooms WHERE hotel_id = ? ORDER BY name ASC");
$stmt->execute([$my_hotel_id]);
$rooms = $stmt->fetchAll();

$month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');
$year = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
if ($month < 1 || $month > 12) $month = (int)date('m');

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

$startDate = sprintf('%04d-%02d-01', $year, $month);
$endDate = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

// Get bookings for this month
$q = $pdo->prepare("
    SELECT room_id, check_in_date, check_out_date, status 
    FROM hotel_bookings 
    WHERE hotel_id = ? 
    AND status IN ('confirmed','paid')
    AND check_in_date <= ? AND check_out_date >= ?
");
$q->execute([$my_hotel_id, $endDate, $startDate]);
$bookings = $q->fetchAll();

require __DIR__ . '/components/header.php';
?>
<div class="admin-layout">
  <?php require __DIR__ . '/components/sidebar.php'; ?>
  <main class="admin-main">
    <?php require __DIR__ . '/components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/components/alert.php'; ?>

      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Room Availability</h4>
        <div class="d-flex gap-2">
          <?php
            $prevM = $month - 1; $prevY = $year; if($prevM < 1) { $prevM = 12; $prevY--; }
            $nextM = $month + 1; $nextY = $year; if($nextM > 12) { $nextM = 1; $nextY++; }
          ?>
          <a href="?m=<?= $prevM ?>&y=<?= $prevY ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-chevron-left"></i> Prev Month</a>
          <span class="btn btn-light btn-sm fw-bold"><?= date('F Y', strtotime($startDate)) ?></span>
          <a href="?m=<?= $nextM ?>&y=<?= $nextY ?>" class="btn btn-outline-secondary btn-sm">Next Month <i class="fa-solid fa-chevron-right"></i></a>
        </div>
      </div>

      <div class="card shadow-sm border-0">
        <div class="table-responsive" style="max-height: 70vh;">
          <table class="table table-bordered mb-0 table-sm" style="min-width: 1200px;">
            <thead class="table-light sticky-top">
              <tr>
                <th style="min-width: 200px;">Room</th>
                <?php for($d=1; $d<=$daysInMonth; $d++): ?>
                  <th class="text-center" style="width:30px; font-size:12px;"><?= $d ?></th>
                <?php endfor; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach($rooms as $r): ?>
                <tr>
                  <td class="fw-bold bg-light"><?= htmlspecialchars($r['name']) ?></td>
                  <?php 
                    for($d=1; $d<=$daysInMonth; $d++) {
                      $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
                      $isBooked = false;
                      foreach($bookings as $b) {
                        if($b['room_id'] == $r['id'] && $currentDate >= $b['check_in_date'] && $currentDate < $b['check_out_date']) {
                          $isBooked = true;
                          break;
                        }
                      }
                      echo '<td class="text-center p-0" style="vertical-align:middle;">';
                      if($isBooked) {
                         echo '<div style="background-color:#ffebee; color:#d32f2f; height:100%; width:100%; min-height:24px; font-size:10px; display:flex; align-items:center; justify-content:center;"><i class="fa-solid fa-xmark"></i></div>';
                      } else {
                         echo '<div style="background-color:#e8f5e9; color:#2e7d32; height:100%; width:100%; min-height:24px; font-size:10px; display:flex; align-items:center; justify-content:center;"><i class="fa-solid fa-check"></i></div>';
                      }
                      echo '</td>';
                    }
                  ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="mt-3 text-muted small">
        <span class="badge" style="background-color:#e8f5e9; color:#2e7d32;">&nbsp;&nbsp;</span> Available
        <span class="badge ms-3" style="background-color:#ffebee; color:#d32f2f;">&nbsp;&nbsp;</span> Booked
      </div>

    </div>
  </main>
</div>
<?php require __DIR__ . '/components/footer.php'; ?>
EOT
);

// 2. Bookings
file_put_contents(__DIR__ . '/hotel/bookings.php', <<<'EOT'
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
<div class="admin-layout">
  <?php require __DIR__ . '/components/sidebar.php'; ?>
  <main class="admin-main">
    <?php require __DIR__ . '/components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/components/alert.php'; ?>

      <h4 class="mb-4">Bookings</h4>

      <div class="card shadow-sm border-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th>Ref</th>
                <th>Guest</th>
                <th>Room</th>
                <th>Check-in</th>
                <th>Check-out</th>
                <th>Amount</th>
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
                  <td>
                    <?php
                       $c = match($b['status']){
                         'pending'=>'bg-warning text-dark',
                         'confirmed','paid'=>'bg-success',
                         'cancelled'=>'bg-danger',
                         'completed'=>'bg-info text-dark',
                         default=>'bg-secondary'
                       };
                    ?>
                    <span class="badge <?= $c ?>"><?= ucfirst($b['status']) ?></span>
                  </td>
                  <td>
                    <a href="booking.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary">Manage</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if(!$bookings): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">No bookings found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>
<?php require __DIR__ . '/components/footer.php'; ?>
EOT
);

// 3. Booking Detail
file_put_contents(__DIR__ . '/hotel/booking.php', <<<'EOT'
<?php
require_once __DIR__ . '/_bootstrap.php';
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
        
        $log = $pdo->prepare("INSERT INTO booking_logs (booking_id, user_id, action, notes) VALUES (?, ?, 'status_changed', ?)");
        $log->execute([$id, Auth::id(), "Hotel Manager changed status to $new_status"]);
        
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

$logs = $pdo->prepare("SELECT l.*, u.first_name, u.last_name FROM booking_logs l LEFT JOIN users u ON l.user_id = u.id WHERE l.booking_id = ? ORDER BY l.created_at DESC");
$logs->execute([$id]);
$timeline = $logs->fetchAll();

require __DIR__ . '/components/header.php';
?>
<div class="admin-layout">
  <?php require __DIR__ . '/components/sidebar.php'; ?>
  <main class="admin-main">
    <?php require __DIR__ . '/components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/components/alert.php'; ?>

      <div class="d-flex align-items-center gap-3 mb-4">
        <a href="bookings.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
        <h4 class="mb-0">Booking #<?= htmlspecialchars($booking['booking_code']) ?></h4>
        <span class="badge bg-secondary ms-auto fs-6"><?= strtoupper($booking['status']) ?></span>
      </div>

      <div class="row">
        <div class="col-md-8">
          <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
              <h5 class="mb-3">Booking Details</h5>
              <table class="table table-bordered">
                <tr><th class="bg-light" style="width:200px;">Reference</th><td><?= htmlspecialchars($booking['booking_code']) ?></td></tr>
                <tr><th class="bg-light">Room Type</th><td><?= htmlspecialchars($booking['room_name']) ?></td></tr>
                <tr><th class="bg-light">Guest Name</th><td><?= htmlspecialchars($booking['guest_name']) ?></td></tr>
                <tr><th class="bg-light">Contact Email</th><td><?= htmlspecialchars($booking['guest_email'] ?: $booking['user_email']) ?></td></tr>
                <tr><th class="bg-light">Phone</th><td><?= htmlspecialchars($booking['guest_phone']) ?></td></tr>
                <tr><th class="bg-light">Check-in</th><td><?= htmlspecialchars($booking['check_in_date']) ?></td></tr>
                <tr><th class="bg-light">Check-out</th><td><?= htmlspecialchars($booking['check_out_date']) ?></td></tr>
                <tr><th class="bg-light">Total Price</th><td><strong>$<?= number_format($booking['total_price'], 2) ?></strong></td></tr>
              </table>
            </div>
          </div>

          <div class="card shadow-sm border-0">
            <div class="card-body p-4">
              <h5 class="mb-3">Timeline Logs</h5>
              <?php if($timeline): ?>
                <ul class="list-group list-group-flush">
                  <?php foreach($timeline as $l): ?>
                    <li class="list-group-item px-0">
                      <small class="text-muted d-block"><?= htmlspecialchars($l['created_at']) ?> by <?= htmlspecialchars($l['first_name'] . ' ' . $l['last_name']) ?></small>
                      <?= htmlspecialchars($l['notes']) ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <p class="text-muted">No timeline records found.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card shadow-sm border-0 mb-4 bg-light">
            <div class="card-body">
              <h5 class="mb-3">Update Status</h5>
              <form method="post" action="booking.php?id=<?= $id ?>">
                <?= csrf_field() ?>
                <select name="status" class="form-select mb-3">
                  <option value="pending" <?= $booking['status']==='pending'?'selected':'' ?>>Pending</option>
                  <option value="confirmed" <?= $booking['status']==='confirmed'?'selected':'' ?>>Confirmed</option>
                  <option value="completed" <?= $booking['status']==='completed'?'selected':'' ?>>Completed</option>
                  <option value="cancelled" <?= $booking['status']==='cancelled'?'selected':'' ?>>Cancelled</option>
                </select>
                <button type="submit" class="btn btn-primary w-100">Update Status</button>
              </form>
            </div>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>
<?php require __DIR__ . '/components/footer.php'; ?>
EOT
);

// 4. Reviews
file_put_contents(__DIR__ . '/hotel/reviews.php', <<<'EOT'
<?php
require_once __DIR__ . '/_bootstrap.php';
$my_hotel_id = require_hotel_manager();

$hotel_active = 'reviews';
$hotel_page_title = t('hotel.reviews', 'Reviews');

$pdo = getPDO();

$stmt = $pdo->prepare("SELECT r.*, u.first_name, u.last_name FROM reviews r LEFT JOIN users u ON r.user_id = u.id WHERE r.module_type = 'hotel' AND r.module_id = ? ORDER BY r.id DESC");
$stmt->execute([$my_hotel_id]);
$reviews = $stmt->fetchAll();

require __DIR__ . '/components/header.php';
?>
<div class="admin-layout">
  <?php require __DIR__ . '/components/sidebar.php'; ?>
  <main class="admin-main">
    <?php require __DIR__ . '/components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/components/alert.php'; ?>

      <h4 class="mb-4">Guest Reviews</h4>

      <div class="card shadow-sm border-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th>Guest</th>
                <th>Rating</th>
                <th>Review</th>
                <th>Status</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($reviews as $r): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></strong></td>
                  <td><span class="badge bg-warning text-dark"><i class="fa-solid fa-star"></i> <?= (int)$r['rating'] ?>/5</span></td>
                  <td style="max-width:300px;" class="text-truncate" title="<?= htmlspecialchars($r['comment']) ?>"><?= htmlspecialchars($r['comment']) ?></td>
                  <td>
                    <?php if($r['status'] === 'approved'): ?>
                      <span class="badge bg-success">Approved</span>
                    <?php elseif($r['status'] === 'rejected'): ?>
                      <span class="badge bg-danger">Rejected</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">Pending</span>
                    <?php endif; ?>
                  </td>
                  <td><small class="text-muted"><?= htmlspecialchars($r['created_at']) ?></small></td>
                </tr>
              <?php endforeach; ?>
              <?php if(!$reviews): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">No reviews found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <p class="mt-3 text-muted small"><i class="fa-solid fa-info-circle"></i> Reviews are moderated by Super Admins before being published publicly.</p>

    </div>
  </main>
</div>
<?php require __DIR__ . '/components/footer.php'; ?>
EOT
);

// 5. Notifications
file_put_contents(__DIR__ . '/hotel/notifications.php', <<<'EOT'
<?php
require_once __DIR__ . '/_bootstrap.php';
$my_hotel_id = require_hotel_manager();

$hotel_active = 'notifications';
$hotel_page_title = t('hotel.notifications', 'Notifications');

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    if (!csrf_verify()) csrf_fail();
    $stmt = $pdo->prepare("UPDATE hotel_notifications SET is_read = 1 WHERE hotel_id = ?");
    $stmt->execute([$my_hotel_id]);
    hotel_flash('All notifications marked as read.', 'success');
    header('Location: notifications.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM hotel_notifications WHERE hotel_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$my_hotel_id]);
$notifications = $stmt->fetchAll();

require __DIR__ . '/components/header.php';
?>
<div class="admin-layout">
  <?php require __DIR__ . '/components/sidebar.php'; ?>
  <main class="admin-main">
    <?php require __DIR__ . '/components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/components/alert.php'; ?>

      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Notifications</h4>
        <form method="post" action="notifications.php">
          <?= csrf_field() ?>
          <input type="hidden" name="mark_read" value="1">
          <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-check-double"></i> Mark all as read</button>
        </form>
      </div>

      <div class="card shadow-sm border-0">
        <div class="list-group list-group-flush">
          <?php foreach($notifications as $n): ?>
            <div class="list-group-item p-3 <?= !$n['is_read'] ? 'bg-light border-start border-primary border-4' : '' ?>">
              <div class="d-flex w-100 justify-content-between mb-1">
                <h6 class="mb-0 fw-bold"><?= htmlspecialchars($n['title']) ?></h6>
                <small class="text-muted"><?= htmlspecialchars($n['created_at']) ?></small>
              </div>
              <p class="mb-0 text-muted small"><?= htmlspecialchars($n['message']) ?></p>
            </div>
          <?php endforeach; ?>
          <?php if(!$notifications): ?>
            <div class="list-group-item p-4 text-center text-muted">No notifications right now.</div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </main>
</div>
<?php require __DIR__ . '/components/footer.php'; ?>
EOT
);

echo "Remaining hotel pages generated.\n";

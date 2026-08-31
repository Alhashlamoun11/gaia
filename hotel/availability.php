<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../services/HotelAvailabilityService.php';
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

// Batch-load booked inventory for all rooms across the month
$roomIds = array_column($rooms, 'id');
$bookedMap = HotelAvailabilityService::batchBookedByDate($pdo, $roomIds, $startDate, $endDate);

require __DIR__ . '/components/header.php';
?>
<div class="admin-app">
  <?php require __DIR__ . '/components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/components/alert.php'; ?>

      <div class="toolbar">
        <h2><?= htmlspecialchars(t('hotel.availability', 'Room Availability')) ?></h2>
        <div class="spacer" style="flex:1;"></div>
        <div style="display:flex; gap:8px; align-items:center;">
          <?php
            $prevM = $month - 1; $prevY = $year; if($prevM < 1) { $prevM = 12; $prevY--; }
            $nextM = $month + 1; $nextY = $year; if($nextM > 12) { $nextM = 1; $nextY++; }
          ?>
          <a href="?m=<?= $prevM ?>&y=<?= $prevY ?>" class="btn btn-ghost btn-sm"><i class="fa-solid fa-chevron-left"></i> <?= htmlspecialchars(t('admin.prev', 'Prev Month')) ?></a>
          <span style="font-weight:700; font-size:14px; padding:0 12px;"><?= date('F Y', strtotime($startDate)) ?></span>
          <a href="?m=<?= $nextM ?>&y=<?= $nextY ?>" class="btn btn-ghost btn-sm"><?= htmlspecialchars(t('admin.next', 'Next Month')) ?> <i class="fa-solid fa-chevron-right"></i></a>
        </div>
      </div>

      <div class="card" style="padding:0;">
        <div class="table-wrap" style="max-height: 70vh; overflow:auto;">
          <table style="min-width: 1200px;">
            <thead style="position:sticky; top:0; background:#fff; z-index:10;">
              <tr>
                <th style="min-width: 200px;"><?= htmlspecialchars(t('hotel.rooms', 'Room')) ?></th>
                <th style="min-width: 60px; text-align:center; font-size:12px;"><?= htmlspecialchars(t('hotel.rooms_label', 'Qty')) ?></th>
                <?php for($d=1; $d<=$daysInMonth; $d++): ?>
                  <th style="text-align:center; width:50px; font-size:12px; padding:8px 2px;"><?= $d ?></th>
                <?php endfor; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach($rooms as $r):
                $rid = (int)$r['id'];
                $qty = (int)$r['quantity'];
              ?>
                <tr>
                  <td style="font-weight:700; background:#fbfaf7;"><?= htmlspecialchars($r['name']) ?></td>
                  <td style="text-align:center; background:#fbfaf7; font-weight:600; font-size:13px;"><?= $qty ?></td>
                  <?php
                    for($d=1; $d<=$daysInMonth; $d++) {
                      $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
                      $booked = isset($bookedMap[$rid][$currentDate]) ? (int)$bookedMap[$rid][$currentDate] : 0;
                      // Cap at quantity to prevent display glitches from legacy data
                      if ($booked > $qty) {
                        error_log("Phase3: Overbooking detected for room_id={$rid} on {$currentDate}: booked={$booked} > qty={$qty}");
                        $booked = $qty;
                      }

                      if ($booked === 0) {
                        // Fully available — green
                        $bg = '#e8f5e9'; $fg = '#2e7d32';
                      } elseif ($booked >= $qty) {
                        // Fully booked — red
                        $bg = '#ffebee'; $fg = '#d32f2f';
                      } else {
                        // Partially occupied — amber
                        $bg = '#fff8e1'; $fg = '#f57f17';
                      }

                      echo '<td style="text-align:center; padding:2px; vertical-align:middle; border-left:1px solid var(--line); border-right:1px solid var(--line);">';
                      echo '<div style="background-color:' . $bg . '; color:' . $fg . '; height:28px; display:flex; align-items:center; justify-content:center; border-radius:4px; font-size:11px; font-weight:600; white-space:nowrap;">';
                      echo $booked . '/' . $qty;
                      echo '</div>';
                      echo '</td>';
                    }
                  ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div style="margin-top:12px; font-size:13px; color:var(--muted); display:flex; gap:16px; flex-wrap:wrap;">
        <div style="display:flex; align-items:center; gap:6px;"><span style="display:inline-block; width:16px; height:16px; border-radius:4px; background-color:#e8f5e9;"></span> <?= htmlspecialchars(t('hotel.available', 'Available')) ?></div>
        <div style="display:flex; align-items:center; gap:6px;"><span style="display:inline-block; width:16px; height:16px; border-radius:4px; background-color:#fff8e1;"></span> <?= htmlspecialchars(t('hotel.partially_occupied', 'Partially Occupied')) ?></div>
        <div style="display:flex; align-items:center; gap:6px;"><span style="display:inline-block; width:16px; height:16px; border-radius:4px; background-color:#ffebee;"></span> <?= htmlspecialchars(t('hotel.fully_booked', 'Fully Booked')) ?></div>
      </div>

    </div>
  </div>
</div>
<?php require __DIR__ . '/components/footer.php'; ?>

<?php
require_once __DIR__ . '/_bootstrap.php';
$my_hotel_id = require_hotel_manager();

$hotel_active = 'rooms';
$hotel_page_title = t('hotel.rooms', 'Rooms');

$pdo = getPDO();

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!csrf_verify()) csrf_fail();
    $id = (int)$_POST['id'];
    
    // Ensure room belongs to THIS hotel
    $stmt = $pdo->prepare("DELETE FROM hotel_rooms WHERE id = ? AND hotel_id = ?");
    $stmt->execute([$id, $my_hotel_id]);
    
    Auth::logAudit('hotel_room_deleted');
    hotel_flash('Room deleted successfully.', 'success');
    header('Location: rooms.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM hotel_rooms WHERE hotel_id = ? ORDER BY sort_order ASC, id DESC");
$stmt->execute([$my_hotel_id]);
$rooms = $stmt->fetchAll();

require __DIR__ . '/components/header.php';
?>
<div class="admin-app">
  <?php require __DIR__ . '/components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/components/alert.php'; ?>

      <div class="toolbar">
        <h2>Manage Rooms</h2>
        <div class="spacer" style="flex:1;"></div>
        <a href="room-form.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Room</a>
      </div>

      <div class="card" style="padding:0;">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Room Name</th>
                <th>Capacity</th>
                <th>Price</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($rooms as $r): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                  <td><?= (int)$r['capacity'] ?> Guests</td>
                  <td>$<?= number_format($r['price'], 2) ?></td>
                  <td>
                    <?php if($r['is_active']): ?>
                      <span class="status-badge green">Active</span>
                    <?php else: ?>
                      <span class="status-badge red">Inactive</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div style="display:flex; gap:8px;">
                      <a href="room-form.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-ghost"><i class="fa-solid fa-pen"></i></a>
                      <form method="post" action="rooms.php" onsubmit="return confirm('Delete this room?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i></button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if(!$rooms): ?>
                <tr><td colspan="5"><div class="muted" style="text-align:center;padding:18px;">No rooms found. Add your first room.</div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>
<?php require __DIR__ . '/components/footer.php'; ?>

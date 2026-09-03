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
        <h2>Room Types & Categories</h2>
        <div class="spacer" style="flex:1;"></div>
        <a href="room-form.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add New Room</a>
      </div>

      <div class="card" style="padding:0; overflow:hidden;">
        <div class="table-wrap" style="box-shadow:none; border:none;">
          <table>
            <thead>
              <tr>
                <th>Room Name</th>
                <th>Capacity</th>
                <th>Units</th>
                <th>Rate / Night</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($rooms as $r): ?>
                <tr>
                  <td>
                    <div style="font-weight:700; color:var(--ink);"><?= htmlspecialchars($r['name']) ?></div>
                    <?php if (!empty($r['beds'])): ?>
                      <div style="font-size:11.5px; color:var(--muted);"><i class="fa-solid fa-bed"></i> <?= htmlspecialchars($r['beds']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?= (int)$r['capacity'] ?> Guests</td>
                  <td style="font-weight:600;"><?= (int)$r['quantity'] ?></td>
                  <td><strong style="color:var(--teal);">$<?= number_format($r['price'], 2) ?></strong></td>
                  <td>
                    <?php if($r['is_active']): ?>
                      <span class="status-badge green">Active</span>
                    <?php else: ?>
                      <span class="status-badge red">Inactive</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="actions">
                      <a href="room-form.php?id=<?= $r['id'] ?>" class="icon-btn" title="Edit Room"><i class="fa-solid fa-pen"></i></a>
                      <form method="post" action="rooms.php" onsubmit="return confirm('Delete this room category?');" style="margin:0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button type="submit" class="icon-btn danger" title="Delete Room"><i class="fa-solid fa-trash"></i></button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if(!$rooms): ?>
                <tr><td colspan="6"><div class="muted" style="text-align:center;padding:24px;">No rooms found. Add your first room category.</div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>
<?php require __DIR__ . '/components/footer.php'; ?>

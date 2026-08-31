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
<div class="admin-app">
  <?php require __DIR__ . '/components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/components/alert.php'; ?>

      <div class="toolbar">
        <h2>Notifications</h2>
        <div class="spacer" style="flex:1;"></div>
        <form method="post" action="notifications.php" style="margin:0;">
          <?= csrf_field() ?>
          <input type="hidden" name="mark_read" value="1">
          <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-check-double"></i> Mark all as read</button>
        </form>
      </div>

      <div class="card" style="padding:0;">
        <ul style="list-style:none; padding:0; margin:0;">
          <?php foreach($notifications as $n): ?>
            <li style="padding:16px 20px; border-bottom:1px solid var(--line); <?= !$n['is_read'] ? 'background-color:#fbfaf7; border-left:4px solid var(--teal);' : '' ?>">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                <h4 style="font-size:15px; margin:0; font-family:'Inter', sans-serif; font-weight:600;"><?= htmlspecialchars($n['title']) ?></h4>
                <small style="color:var(--muted); font-size:12px;"><?= htmlspecialchars($n['created_at']) ?></small>
              </div>
              <p style="margin:0; color:var(--muted); font-size:13.5px;"><?= htmlspecialchars($n['message']) ?></p>
            </li>
          <?php endforeach; ?>
          <?php if(!$notifications): ?>
            <li style="padding:24px; text-align:center; color:var(--muted);">No notifications right now.</li>
          <?php endif; ?>
        </ul>
      </div>

    </div>
  </div>
</div>
<?php require __DIR__ . '/components/footer.php'; ?>

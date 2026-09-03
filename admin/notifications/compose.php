<?php
/**
 * admin/notifications/compose.php
 * Compose & Dispatch Broadcast / Direct Hotel Notification
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$adminMe = admin_user();

if (($adminMe['role_slug'] ?? '') !== 'super_admin') {
    admin_flash(t('admin.access_denied', 'Access Denied. Only Super Administrators can broadcast notifications.'), 'error');
    header('Location: ' . admin_url('index.php'));
    exit;
}

$hotels = $pdo->query("SELECT id, name, city FROM hotels WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();

    $targetHotel = trim($_POST['hotel_id'] ?? 'all');
    $type = in_array($_POST['type'] ?? 'info', ['info', 'success', 'warning', 'alert'], true) ? $_POST['type'] : 'info';
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($title === '') {
        $errors[] = 'Notification title is required.';
    }
    if ($message === '') {
        $errors[] = 'Notification message body is required.';
    }

    if (!$errors) {
        $ins = $pdo->prepare("INSERT INTO hotel_notifications (hotel_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");

        if ($targetHotel === 'all') {
            // Broadcast to all active hotels
            foreach ($hotels as $h) {
                $ins->execute([$h['id'], $title, $message, $type]);
            }
            admin_flash('Broadcast notification sent successfully to all ' . count($hotels) . ' hotels.', 'success');
        } else {
            // Single target hotel
            $hotelId = (int)$targetHotel;
            $ins->execute([$hotelId, $title, $message, $type]);
            admin_flash('Notification sent successfully to hotel.', 'success');
        }

        header('Location: ' . admin_url('notifications/index.php'));
        exit;
    }
}

$account_alerts = array_map(fn($e) => ['type' => 'error', 'msg' => $e], $errors);
if (admin_flash_peek()) $account_alerts[] = admin_flash();

$admin_page_title = 'Compose Hotel Notification — GAIA Admin';
$admin_topbar_title = 'Hotel Notifications';
$admin_active = 'notifications';
?>
<?php require __DIR__ . '/../components/header.php'; ?>
<div class="admin-app">
  <?php require __DIR__ . '/../components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/../components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/../components/alert.php'; ?>

      <div class="card">
        <div class="card-head">
          <h3><i class="fa-solid fa-paper-plane"></i> Send Hotel Partner Broadcast / Alert</h3>
          <a class="btn btn-ghost btn-sm" href="index.php"><i class="fa-solid fa-arrow-left"></i> Back to Notifications</a>
        </div>

        <form method="post" action="compose.php">
          <?= csrf_field() ?>

          <div class="grid-2">
            <div class="field">
              <label>Target Audience / Recipient <span style="color:#b3261e">*</span></label>
              <select name="hotel_id" required>
                <option value="all">📢 All Active Hotels (Global Broadcast)</option>
                <optgroup label="Specific Hotel Partner">
                  <?php foreach ($hotels as $h): ?>
                    <option value="<?= (int)$h['id'] ?>"><?= htmlspecialchars($h['name']) ?> (<?= htmlspecialchars($h['city'] ?? 'Jordan') ?>)</option>
                  <?php endforeach; ?>
                </optgroup>
              </select>
            </div>

            <div class="field">
              <label>Notification Type / Priority <span style="color:#b3261e">*</span></label>
              <select name="type" required>
                <option value="info">🔵 Information (General Update / Announcement)</option>
                <option value="success">🟢 Success (Promotion / Rate Activation)</option>
                <option value="warning">🟡 Warning (Policy / Schedule Reminder)</option>
                <option value="alert">🔴 Urgent Alert (System Action Required)</option>
              </select>
            </div>
          </div>

          <div class="field">
            <label>Notification Title <span style="color:#b3261e">*</span></label>
            <input type="text" name="title" required placeholder="e.g. High Season Room Inventory Audit Required" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
          </div>

          <div class="field">
            <label>Message Content Body <span style="color:#b3261e">*</span></label>
            <textarea name="message" rows="5" required placeholder="Enter the complete message text for hotel managers..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
            <small class="hint">This message will appear immediately in the hotel manager portal notifications center.</small>
          </div>

          <div class="form-actions" style="margin-top:24px; display:flex; gap:10px;">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Send Notification</button>
            <a class="btn btn-ghost" href="index.php">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../components/footer.php'; ?>

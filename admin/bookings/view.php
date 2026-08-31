<?php
/**
 * admin/bookings/view.php
 * ------------------------------------------------------------
 * Full booking detail for any type. Uses BookingSummaryService
 * for normalized summary + per-type fetch for extra fields.
 * Shows customer, payment(s), invoice, timeline.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo       = getPDO();
$adminUser = admin_user();

$type = $_GET['type'] ?? '';
$id   = (int)($_GET['id'] ?? 0);
$allowed = ['transfer','taxi','tour','hotel','room','event'];
$tableMap = [
    'transfer' => 'bookings', 'taxi' => 'taxi_bookings', 'tour' => 'weroad_bookings',
    'hotel' => 'hotel_bookings', 'room' => 'room_bookings', 'event' => 'event_bookings',
];

$notFound = false;
if (!in_array($type, $allowed, true) || $id <= 0) {
    $notFound = true;
} else {
    $stmt = $pdo->prepare("SELECT * FROM `{$tableMap[$type]}` WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) $notFound = true;
}

if ($notFound) {
    http_response_code(404);
    $admin_page_title = t('admin.not_found', 'Not Found') . ' — GAIA TOURS &amp; TRAVEL';
    $admin_topbar_title = t('admin.not_found', 'Not Found');
    $admin_active = 'bookings';
    require __DIR__ . '/../components/header.php';
    echo '<div class="admin-app"><div class="admin-main"><div class="admin-content"><div class="card"><div class="muted" style="text-align:center;padding:40px;">' . htmlspecialchars(t('admin.booking_not_found', 'Booking not found.')) . '</div><div style="text-align:center;"><a class="btn" href="index.php">' . htmlspecialchars(t('admin.back', 'Back')) . '</a></div></div></div></div></div>';
    require __DIR__ . '/../components/footer.php';
    exit;
}

// Payment(s) + invoice(s)
$payments = PaymentService::getBookingPayments($type, $id);
$invoices = [];
try {
    $invStmt = $pdo->prepare("SELECT * FROM invoices WHERE booking_type = :t AND booking_id = :id ORDER BY id DESC");
    $invStmt->execute([':t' => $type, ':id' => $id]);
    $invoices = $invStmt->fetchAll();
} catch (PDOException $e) { $invoices = []; }

// Timeline
$timeline = [];
try {
    $tlStmt = $pdo->prepare("SELECT action, description, user_id, created_at FROM booking_logs WHERE booking_type = :t AND booking_id = :id ORDER BY created_at DESC LIMIT 20");
    $tlStmt->execute([':t' => $type, ':id' => $id]);
    $timeline = $tlStmt->fetchAll();
} catch (PDOException $e) { $timeline = []; }

$ref = $row['booking_reference'] ?: ($row['booking_code'] ?? ('#' . $id));

// Handle status transition
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $newStatus = $_POST['new_status'] ?? '';
    $allowedStatuses = ['pending','awaiting_payment','paid','confirmed','completed','cancelled','refunded'];
    if (in_array($newStatus, $allowedStatuses, true)) {
        $ok = BookingService::transition($type, $id, $newStatus, (int)$adminUser['id']);
        admin_flash($ok ? t('admin.status_updated', 'Status updated successfully.') : t('admin.status_transition_invalid', 'Status transition not allowed.'), $ok ? 'success' : 'error');
    }
    header('Location: ' . admin_url('bookings/view.php?type=' . urlencode($type) . '&id=' . (int)$id));
    exit;
}

$account_alerts     = admin_flash_peek() ? [admin_flash()] : [];
$admin_page_title   = $ref . ' — GAIA TOURS & TRAVEL';
$admin_topbar_title = $ref;
$admin_active       = 'bookings';
?>
<?php require __DIR__ . '/../components/header.php'; ?>
<div class="admin-app">
  <?php require __DIR__ . '/../components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/../components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/../components/alert.php'; ?>

      <div class="stats-grid" style="grid-template-columns:1fr 1fr;">
        <div class="card" style="margin-bottom:0;">
          <div class="card-head">
            <h3><i class="fa-solid fa-bookmark"></i> <?= htmlspecialchars(t('admin.booking_info', 'Booking Information')) ?></h3>
          </div>
          <div class="detail-grid">
            <div><span><?= htmlspecialchars(t('admin.reference')) ?></span><strong><?= htmlspecialchars($ref) ?></strong></div>
            <div><span><?= htmlspecialchars(t('admin.type', 'Type')) ?></span><strong><?= htmlspecialchars($type) ?></strong></div>
            <div><span><?= htmlspecialchars(t('admin.status')) ?></span><strong><span class="badge badge-<?= htmlspecialchars(normalize_booking_status((string)$row['status'])) ?>"><?= htmlspecialchars(booking_status_label(normalize_booking_status((string)$row['status']))) ?></span></strong></div>
            <div><span><?= htmlspecialchars(t('admin.created')) ?></span><strong><?= htmlspecialchars(date('Y-m-d H:i', strtotime((string)($row['created_at'] ?? 'now')))) ?></strong></div>
            <div><span><?= htmlspecialchars(t('admin.customer')) ?></span><strong><?= htmlspecialchars($row['guest_name'] ?? $row['full_name'] ?? 'Guest') ?></strong></div>
            <div><span><?= htmlspecialchars(t('admin.email')) ?></span><strong><?= htmlspecialchars($row['guest_email'] ?? $row['email'] ?? '') ?></strong></div>
            <div><span><?= htmlspecialchars(t('admin.phone')) ?></span><strong><?= htmlspecialchars($row['guest_phone'] ?? $row['phone'] ?? '') ?></strong></div>
            <div><span><?= htmlspecialchars(t('admin.amount')) ?></span><strong class="gold-text"><?= htmlspecialchars(number_format((float)($row['total_price'] ?? 0), 2)) ?> USD</strong></div>
            <div><span><?= htmlspecialchars(t('admin.user_id', 'User ID')) ?></span><strong><?= htmlspecialchars($row['user_id'] ?? '—') ?></strong></div>
            <?php if ($type === 'hotel'): ?>
              <div><span><?= htmlspecialchars(t('hotel.rooms_booked', 'Rooms Booked')) ?></span><strong><?= (int)($row['rooms_count'] ?? 1) ?></strong></div>
              <div><span><?= htmlspecialchars(t('hotel.guests_label', 'Guests')) ?></span><strong><?= (int)($row['guests'] ?? 1) ?></strong></div>
            <?php endif; ?>
          </div>
        </div>

        <div class="card" style="margin-bottom:0;">
          <div class="card-head">
            <h3><i class="fa-solid fa-file-lines"></i> <?= htmlspecialchars(t('admin.payment_info', 'Payment & Invoice')) ?></h3>
            <a class="btn btn-ghost btn-sm" href="index.php"><?= htmlspecialchars(t('admin.back', 'Back')) ?></a>
          </div>
          <?php if (!$payments): ?>
            <div class="muted" style="padding:14px;"><?= htmlspecialchars(t('admin.no_payments', 'No payments.')) ?></div>
          <?php else: foreach ($payments as $p): ?>
            <div class="list-item">
              <div>
                <div class="code"><?= htmlspecialchars($p['booking_reference'] ?? ('#' . (int)$p['id'])) ?></div>
                <span class="muted"><?= htmlspecialchars(number_format((float)$p['amount'], 2)) ?> <?= htmlspecialchars($p['currency'] ?? 'USD') ?> · <?= htmlspecialchars($p['payment_method'] ?? 'card') ?></span>
              </div>
              <span class="badge badge-<?= htmlspecialchars(normalize_payment_status((string)$p['status'])) ?>"><?= htmlspecialchars(payment_status_label(normalize_payment_status((string)$p['status']))) ?></span>
            </div>
          <?php endforeach; endif; ?>

          <?php if ($invoices): ?>
            <h4 style="margin:16px 0 8px;"><?= htmlspecialchars(t('admin.invoices', 'Invoices')) ?></h4>
            <?php foreach ($invoices as $inv): ?>
              <div class="list-item">
                <div>
                  <div class="code"><?= htmlspecialchars($inv['invoice_number']) ?></div>
                  <span class="muted"><?= htmlspecialchars(number_format((float)$inv['total_amount'], 2)) ?> <?= htmlspecialchars($inv['currency'] ?? 'USD') ?></span>
                </div>
                <span class="badge badge-<?= htmlspecialchars($inv['status']) ?>"><?= htmlspecialchars(invoice_status_label((string)$inv['status'])) ?></span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Status Transition -->
      <div class="card" style="margin-top:22px;">
        <div class="card-head">
          <h3><i class="fa-solid fa-arrow-right-arrow-left"></i> <?= htmlspecialchars(t('admin.update_status', 'Update Status')) ?></h3>
        </div>
        <form method="post" action="view.php?type=<?= htmlspecialchars($type) ?>&id=<?= (int)$id ?>" class="toolbar">
          <?= csrf_field() ?>
          <span class="muted" style="margin-right:8px;"><?= htmlspecialchars(t('admin.current_status', 'Current Status')) ?>:</span>
          <span class="badge badge-<?= htmlspecialchars(normalize_booking_status((string)$row['status'])) ?>"><?= htmlspecialchars(booking_status_label(normalize_booking_status((string)$row['status']))) ?></span>
          <select name="new_status" style="margin-left:12px;">
            <?php foreach (['pending','awaiting_payment','paid','confirmed','completed','cancelled','refunded'] as $sopt): ?>
              <option value="<?= $sopt ?>" <?= normalize_booking_status((string)$row['status']) === $sopt ? 'selected' : '' ?>><?= htmlspecialchars(booking_status_label($sopt)) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-sm"><?= htmlspecialchars(t('admin.update_status', 'Update Status')) ?></button>
        </form>
      </div>

      <?php if ($timeline): ?>
        <div class="card" style="margin-top:22px;">
          <h3><i class="fa-solid fa-clock-rotate-left"></i> <?= htmlspecialchars(t('admin.timeline', 'Timeline')) ?></h3>
          <div class="table-wrap">
            <table>
              <thead><tr><th><?= htmlspecialchars(t('admin.created')) ?></th><th><?= htmlspecialchars(t('admin.action', 'Action')) ?></th><th><?= htmlspecialchars(t('admin.description', 'Description')) ?></th><th><?= htmlspecialchars(t('admin.user', 'User')) ?></th></tr></thead>
              <tbody>
                <?php foreach ($timeline as $tl): ?>
                  <tr>
                    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime((string)$tl['created_at']))) ?></td>
                    <td class="code"><?= htmlspecialchars($tl['action']) ?></td>
                    <td><?= htmlspecialchars($tl['description']) ?></td>
                    <td>#<?= htmlspecialchars($tl['user_id'] ?? '—') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>
<?php require __DIR__ . '/../components/footer.php'; ?>

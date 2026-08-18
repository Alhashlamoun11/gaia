<?php
/**
 * account/notifications.php
 * ------------------------------------------------------------
 * Customer Notification Center.
 *
 * DB-ready. No email sending. Shows booking updates, payment
 * updates and system notifications, an unread counter and a
 * "Mark all as read" action.
 *
 * SECURITY: all lookups are scoped to the authenticated user_id
 * via NotificationService. CSRF protected POST actions. All output
 * is XSS-escaped; PDO prepared statements used.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../components/bootstrap.php';
require_once __DIR__ . '/../components/gaia-config.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../services/NotificationService.php';

$gaia_base         = '';
$gaia_active       = 'home';
$gaia_header_style = 'solid';

require_login();

$userId = (int)auth_id();
$alerts = [];
$typeFilter = trim($_GET['type'] ?? '');
$allowedTypes = ['booking', 'payment', 'system'];
if ($typeFilter !== '' && !in_array($typeFilter, $allowedTypes, true)) {
    $typeFilter = '';
}

// Handle POST actions (mark read / mark all read / delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        csrf_fail();
    }
    $action = $_POST['action'] ?? '';
    $notifId = (int)($_POST['notification_id'] ?? 0);

    if ($action === 'mark_all_read') {
        NotificationService::markAllRead($userId);
        $alerts[] = ['type' => 'success', 'msg' => t('account.marked_all_read')];
    } elseif ($action === 'mark_read' && $notifId > 0) {
        NotificationService::markRead($notifId, $userId);
    } elseif ($action === 'delete' && $notifId > 0) {
        NotificationService::delete($notifId, $userId);
    }
    // Redirect (PRG) to avoid resubmission.
    $query = $typeFilter !== '' ? '?type=' . urlencode($typeFilter) : '';
    header('Location: notifications.php' . $query);
    exit;
}

$notifications = NotificationService::list($userId, $typeFilter, 50);
$unreadCount   = NotificationService::unreadCount($userId);

$typeLabels = [
    'booking' => 'account.notif_booking',
    'payment' => 'account.notif_payment',
    'system'  => 'account.notif_system',
];
$typeIcons = [
    'booking' => 'fa-calendar-check',
    'payment' => 'fa-credit-card',
    'system'  => 'fa-gear',
];

$gaia_page_key   = 'account_notifications';
$gaia_page_title = t('account.notifications') . ' — GAIA TOURS &amp; TRAVEL';
$activeTab = 'notifications';
$base      = '../';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<?php require __DIR__ . '/../components/account-head.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../components/gaia-header.php'; ?>

<div class="account-shell">
  <?php
    $crumbs = [
        ['label' => t('account.dashboard'), 'url' => gaia_url('account/index.php')],
        ['label' => t('account.notifications'), 'url' => ''],
    ];
    require __DIR__ . '/../components/breadcrumbs.php';
  ?>
  <div class="account-layout">
    <?php require __DIR__ . '/../components/user-sidebar.php'; ?>
    <main class="account-main">
      <?php $account_alerts = $alerts; require __DIR__ . '/../components/alert.php'; ?>

      <div class="card">
        <div class="notif-head">
          <h2><i class="fa-solid fa-bell"></i> <?= htmlspecialchars(t('account.notification_center')) ?></h2>
          <?php if ($unreadCount > 0): ?>
            <span class="badge badge-pending"><?= (int)$unreadCount ?> <?= htmlspecialchars(t('account.unread')) ?></span>
          <?php endif; ?>
        </div>

        <!-- type filters -->
        <form method="get" action="notifications.php" class="filters">
          <select name="type" onchange="this.form.submit()">
            <option value=""><?= htmlspecialchars(t('account.all')) ?></option>
            <option value="booking" <?= $typeFilter === 'booking' ? 'selected' : '' ?>><?= htmlspecialchars(t('account.notif_booking')) ?></option>
            <option value="payment" <?= $typeFilter === 'payment' ? 'selected' : '' ?>><?= htmlspecialchars(t('account.notif_payment')) ?></option>
            <option value="system"  <?= $typeFilter === 'system'  ? 'selected' : '' ?>><?= htmlspecialchars(t('account.notif_system')) ?></option>
          </select>
          <?php if ($unreadCount > 0): ?>
            <button type="submit" name="action" value="mark_all_read" class="btn-link" onclick="return confirm('<?= htmlspecialchars(t('account.mark_all_read')) ?>?');">
              <i class="fa-solid fa-check-double"></i> <?= htmlspecialchars(t('account.mark_all_read')) ?>
            </button>
          <?php endif; ?>
        </form>

        <?php if (!$notifications): ?>
          <?php $empty_title = t('account.no_notifications'); $empty_icon = 'fa-bell'; require __DIR__ . '/../components/empty-state.php'; ?>
        <?php else: ?>
          <div class="notif-list">
            <?php foreach ($notifications as $n):
              $nType = (string)($n['type'] ?? 'system');
              $nTypeIcon = isset($typeIcons[$nType]) ? $typeIcons[$nType] : 'fa-bell';
              $nTypeLabel = isset($typeLabels[$nType]) ? t($typeLabels[$nType]) : $nType;
              $isRead = (int)($n['is_read'] ?? 0) === 1;
            ?>
              <div class="notif-item <?= $isRead ? '' : 'unread' ?>">
                <div class="notif-icon"><i class="fa-solid <?= htmlspecialchars($nTypeIcon) ?>"></i></div>
                <div class="notif-body">
                  <div class="notif-title"><?= htmlspecialchars($n['title'] ?? '') ?></div>
                  <div class="notif-type"><?= htmlspecialchars($nTypeLabel) ?></div>
                  <?php if (!empty($n['message'])): ?>
                    <div class="notif-message"><?= htmlspecialchars($n['message']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($n['link'])): ?>
                    <a class="btn-link" href="<?= htmlspecialchars((string)$n['link']) ?>"><?= htmlspecialchars(t('account.view_all')) ?> →</a>
                  <?php endif; ?>
                </div>
                <div class="notif-meta">
                  <span class="muted"><?= htmlspecialchars($n['created_at'] ?? '') ?></span>
                  <form method="post" action="notifications.php" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="notification_id" value="<?= (int)$n['id'] ?>">
                    <button type="submit" class="btn-link btn-danger-link" title="<?= htmlspecialchars(t('account.delete', 'Delete')) ?>"><i class="fa-solid fa-trash"></i></button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>

<?php require __DIR__ . '/../components/gaia-footer.php'; ?>
</body>
</html>

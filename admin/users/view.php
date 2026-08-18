<?php
/**
 * admin/users/view.php
 * ------------------------------------------------------------
 * View a single user's full profile + aggregate stats.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT u.*, r.slug AS role_slug, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    $admin_page_title = t('admin.not_found', 'Not Found') . ' — GAIA TOURS &amp; TRAVEL';
    $admin_topbar_title = t('admin.not_found', 'Not Found');
    $admin_active       = 'users';
    require __DIR__ . '/../components/header.php';
    echo '<div class="admin-app"><div class="admin-main"><div class="admin-content"><div class="card"><div class="muted" style="text-align:center;padding:40px;">' . htmlspecialchars(t('admin.not_found_text', 'User not found.')) . '</div>';
    echo '<div style="text-align:center;margin-top:12px;"><a class="btn" href="index.php">' . htmlspecialchars(t('admin.back', 'Back')) . '</a></div></div></div></div></div>';
    require __DIR__ . '/../components/footer.php';
    exit;
}

// Aggregate stats
$bCounts = ReportingService::bookingsByType();
$userBookings = ReportingService::totalBookings($id);
$userPayments = count(PaymentService::getUserPayments($id));

$account_alerts     = [];
$admin_page_title   = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . ' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = t('admin.users');
$admin_active       = 'users';
?>
<?php require __DIR__ . '/../components/header.php'; ?>
<div class="admin-app">
  <?php require __DIR__ . '/../components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/../components/topbar.php'; ?>
    <div class="admin-content">
      <div class="card">
        <div class="card-head">
          <h3><i class="fa-solid fa-user"></i> <?= htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) ?></h3>
          <a class="btn btn-sm" href="edit.php?id=<?= (int)$id ?>"><?= htmlspecialchars(t('admin.edit')) ?></a>
        </div>
        <div class="profile-banner">
          <img class="avatar-lg" src="<?= htmlspecialchars('/' . ltrim((string)$user['avatar'], '/')) ?>" alt="">
          <div>
            <h4><?= htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) ?></h4>
            <div class="badge badge-<?= htmlspecialchars($user['status']) ?>"><?= htmlspecialchars($user['status']) ?></div>
            <div class="muted"><?= htmlspecialchars($user['role_name']) ?></div>
          </div>
        </div>
        <div class="detail-grid">
          <div><span><?= htmlspecialchars(t('admin.email')) ?></span><strong><?= htmlspecialchars($user['email']) ?></strong></div>
          <div><span><?= htmlspecialchars(t('admin.phone')) ?></span><strong><?= htmlspecialchars($user['phone']) ?></strong></div>
          <div><span><?= htmlspecialchars(t('admin.country', 'Country')) ?></span><strong><?= htmlspecialchars($user['country']) ?></strong></div>
          <div><span><?= htmlspecialchars(t('admin.role')) ?></span><strong><?= htmlspecialchars($user['role_slug']) ?></strong></div>
          <div><span><?= htmlspecialchars(t('admin.created')) ?></span><strong><?= htmlspecialchars(date('Y-m-d H:i', strtotime((string)$user['created_at']))) ?></strong></div>
          <div><span><?= htmlspecialchars(t('admin.last_login', 'Last Login')) ?></span><strong><?= htmlspecialchars($user['last_login_at'] ? date('Y-m-d H:i', strtotime((string)$user['last_login_at'])) : '—') ?></strong></div>
        </div>
      </div>

      <div class="stats-grid">
        <?php
          $stat_value = $userBookings; $stat_label = t('admin.total_bookings'); $stat_icon = 'fa-bookmark'; $stat_color = 'blue';
          require __DIR__ . '/../components/stats-card.php';
          $stat_value = $userPayments; $stat_label = t('admin.payments', 'Payments'); $stat_icon = 'fa-credit-card'; $stat_color = 'gold';
          require __DIR__ . '/../components/stats-card.php';
        ?>
      </div>

      <div class="form-actions">
        <a class="btn btn-ghost" href="index.php"><?= htmlspecialchars(t('admin.back', 'Back')) ?></a>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../components/footer.php'; ?>

<?php
/**
 * account/index.php
 * ------------------------------------------------------------
 * Customer account dashboard.
 *
 * Shows:
 *   - Welcome section
 *   - Profile summary card
 *   - Recent bookings card
 *   - Upcoming bookings card
 *   - Account status card
 * All cards are responsive.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../components/bootstrap.php';
require_once __DIR__ . '/../components/gaia-config.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../auth/helpers.php';

$gaia_base         = '';
$gaia_active       = 'home';
$gaia_header_style = 'solid';

require_login();

$user = auth_user();
$userId = (int)$user['id'];

$pdo = getPDO();

// Recent bookings from bookings (transfers)
$recentBookings = [];
$stmt = $pdo->prepare(
    'SELECT booking_code, origin, destination, pickup_date AS date, total_price, status, "transfer" AS type
     FROM bookings WHERE user_id = ? ORDER BY id DESC LIMIT 5'
);
$stmt->execute([$userId]);
$recentBookings = array_merge($recentBookings, $stmt->fetchAll());

// Recent bookings from weroad_bookings (tours)
$stmt = $pdo->prepare(
    'SELECT booking_code, "" AS origin, (SELECT name FROM weroad_trips WHERE id = wb.trip_id) AS destination, created_at AS date, total_price, status, "tour" AS type
     FROM weroad_bookings wb WHERE user_id = ? ORDER BY id DESC LIMIT 5'
);
$stmt->execute([$userId]);
$recentBookings = array_merge($recentBookings, $stmt->fetchAll());

$recentBookings = array_slice($recentBookings, 0, 5);

// Upcoming bookings (transfers with future pickup date, not cancelled)
$upcoming = [];
$stmt = $pdo->prepare(
    'SELECT booking_code, origin, destination, pickup_date AS date, total_price, status, "transfer" AS type
     FROM bookings
     WHERE user_id = ? AND status = "confirmed" AND pickup_date >= CURDATE()
     ORDER BY pickup_date ASC LIMIT 5'
);
$stmt->execute([$userId]);
$upcoming = $stmt->fetchAll();

// Account status
$status = $user['status'];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags('account', t('account.dashboard') . ' — GAIA TOURS &amp; TRAVEL') ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<style>
:root{--navy:#1b2a4a;--navy-2:#243761;--teal:#1f6f8f;--teal-dark:#175a75;--ink:#1c1e26;--muted:#6b7280;--line:#e8e6e0;--bg-soft:#f4efe6;--white:#fff;--radius:14px;--shadow:0 10px 30px rgba(27,42,74,0.08);}
*{box-sizing:border-box;}body{margin:0;font-family:'Inter',sans-serif;color:var(--ink);background:var(--bg-soft);-webkit-font-smoothing:antialiased;}
h1,h2,h3{font-family:'Playfair Display',serif;margin:0;}
a{text-decoration:none;color:inherit;}
.account-shell{max-width:1280px;margin:0 auto;padding:32px;}
.account-layout{display:grid;grid-template-columns:260px 1fr;gap:26px;align-items:start;}
.account-sidebar{background:#fff;border:1px solid var(--line);border-radius:18px;padding:20px;box-shadow:var(--shadow);}
.account-sidebar-user{display:flex;gap:12px;align-items:center;padding-bottom:16px;border-bottom:1px solid var(--line);margin-bottom:14px;}
.account-sidebar-user strong{display:block;font-size:14.5px;}
.account-sidebar-user span{display:block;font-size:12px;color:var(--muted);word-break:break-all;}
.account-avatar{width:48px;height:48px;border-radius:50%;object-fit:cover;background:var(--navy);}
.account-nav{display:flex;flex-direction:column;gap:4px;}
.account-nav a{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:10px;font-size:14px;color:var(--ink);transition:.15s;}
.account-nav a:hover{background:#f4efe6;}
.account-nav a.active{background:var(--navy);color:#fff;font-weight:600;}
.account-nav i{width:18px;text-align:center;}
.account-logout-form{margin-top:16px;border-top:1px solid var(--line);padding-top:14px;}
.account-logout-btn{width:100%;display:flex;align-items:center;gap:11px;padding:10px 12px;border:none;background:none;border-radius:10px;font-size:14px;color:#b3261e;cursor:pointer;font-family:inherit;}
.account-logout-btn:hover{background:#fdecea;}
.account-main{min-width:0;}
.welcome-card{background:var(--navy);color:#fff;border-radius:18px;padding:28px;margin-bottom:22px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;}
.welcome-card h1{font-size:24px;}
.welcome-card p{margin:6px 0 0;color:#c7c3e2;font-size:14px;}
.card-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px;}
.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:20px;box-shadow:var(--shadow);}
.card h3{font-size:16px;margin-bottom:12px;display:flex;align-items:center;gap:8px;}
.card h3 i{color:var(--teal);}
.card .muted{color:var(--muted);font-size:13px;}
.list-item{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f0ede6;font-size:13.5px;flex-wrap:wrap;}
.list-item:last-child{border-bottom:none;}
.list-item .code{font-weight:700;}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;text-transform:uppercase;}
.badge-confirmed,.badge-active{background:#e8f5ee;color:#1b6e43;}
.badge-pending{background:#fff3d6;color:#8a6d00;}
.badge-cancelled,.badge-suspended,.badge-inactive{background:#fdecea;color:#b3261e;}
.status-pill{display:inline-flex;align-items:center;gap:8px;}
.status-dot{width:9px;height:9px;border-radius:50%;background:#2eae6e;}
.alert{background:#e8f5ee;color:#1b6e43;border:1px solid #b9e4cb;border-radius:10px;padding:12px 14px;font-size:13.5px;margin-bottom:18px;}
@media (max-width:860px){.account-layout{grid-template-columns:1fr;}.account-nav{flex-direction:row;flex-wrap:wrap;}.account-logout-form{border-top:none;padding-top:0;}}
</style>
</head>
<body>

<?php require __DIR__ . '/../components/gaia-header.php'; ?>

<div class="account-shell">
  <?php require __DIR__ . '/_layout.php'; ?>

  <div class="welcome-card">
    <div>
      <h1><?= htmlspecialchars(t_fmt('account.welcome', ['name' => auth_name()])) ?></h1>
      <p><?= htmlspecialchars(auth_email()) ?></p>
    </div>
    <div>
      <span class="badge badge-active"><?= htmlspecialchars(t('account.role_' . $user['role_slug'], $user['role_slug'])) ?></span>
    </div>
  </div>

  <div class="card-grid">
    <!-- Profile summary -->
    <div class="card">
      <h3><i class="fa-solid fa-user"></i> <?= htmlspecialchars(t('account.profile_summary')) ?></h3>
      <div class="list-item"><span class="muted"><?= htmlspecialchars(t('auth.first_name')) ?></span><span><?= htmlspecialchars($user['first_name']) ?></span></div>
      <div class="list-item"><span class="muted"><?= htmlspecialchars(t('auth.last_name')) ?></span><span><?= htmlspecialchars($user['last_name']) ?></span></div>
      <div class="list-item"><span class="muted"><?= htmlspecialchars(t('auth.email')) ?></span><span><?= htmlspecialchars($user['email']) ?></span></div>
      <div class="list-item"><span class="muted"><?= htmlspecialchars(t('auth.phone')) ?></span><span><?= htmlspecialchars($user['phone'] ?: '—') ?></span></div>
      <div style="margin-top:14px;">
        <a href="profile.php" style="color:var(--teal);font-weight:600;font-size:14px;"><?= htmlspecialchars(t('account.profile')) ?> →</a>
      </div>
    </div>

    <!-- Recent bookings -->
    <div class="card">
      <h3><i class="fa-solid fa-bookmark"></i> <?= htmlspecialchars(t('account.recent_bookings')) ?></h3>
      <?php if (!$recentBookings): ?>
        <p class="muted"><?= htmlspecialchars(t('account.no_bookings')) ?></p>
      <?php else: ?>
        <?php foreach ($recentBookings as $b): ?>
          <div class="list-item">
            <div>
              <div class="code"><?= htmlspecialchars($b['booking_code']) ?></div>
              <span class="muted"><?= htmlspecialchars(booking_type_label($b['type'])) ?></span>
            </div>
            <span class="badge badge-<?= htmlspecialchars($b['status']) ?>"><?= htmlspecialchars(booking_status_label($b['status'])) ?></span>
          </div>
        <?php endforeach; ?>
<div style="margin-top:14px;"><a href="bookings.php" style="color:var(--teal);font-weight:600;font-size:14px;"><?= htmlspecialchars(t('account.my_bookings')) ?> →</a></div>
      <?php endif; ?>
    </div>

    <!-- Upcoming bookings -->
    <div class="card">
      <h3><i class="fa-solid fa-calendar-check"></i> <?= htmlspecialchars(t('account.upcoming_bookings')) ?></h3>
      <?php if (!$upcoming): ?>
        <p class="muted"><?= htmlspecialchars(t('account.no_bookings')) ?></p>
      <?php else: ?>
        <?php foreach ($upcoming as $b): ?>
          <div class="list-item">
            <div>
              <div class="code"><?= htmlspecialchars($b['booking_code']) ?></div>
              <span class="muted"><?= htmlspecialchars($b['date']) ?></span>
            </div>
            <span class="badge badge-confirmed"><?= htmlspecialchars(booking_status_label($b['status'])) ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Account status -->
    <div class="card">
      <h3><i class="fa-solid fa-shield-halved"></i> <?= htmlspecialchars(t('account.account_status')) ?></h3>
      <div class="list-item">
        <span class="status-pill"><span class="status-dot"></span> <?= htmlspecialchars(t('account.' . ($status === 'active' ? 'active' : 'inactive'))) ?></span>
        <span class="badge badge-<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></span>
      </div>
      <div class="list-item"><span class="muted"><?= htmlspecialchars(t('auth.email')) ?></span><span><?= $user['email_verified_at'] ? htmlspecialchars($user['email_verified_at']) : '—' ?></span></div>
      <div class="list-item"><span class="muted"><?= htmlspecialchars(t('account.booking_summary')) ?></span><span><?= count($recentBookings) ?></span></div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../components/gaia-footer.php'; ?>
</body>
</html>

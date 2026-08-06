<?php
/**
 * account/claim-booking.php
 * ------------------------------------------------------------
 * Lets a logged-in customer attach a previous guest booking to
 * their account by entering the guest email + booking code.
 *
 * Searches across bookings, weroad_bookings, hotel_bookings,
 * event_bookings and taxi_bookings where the guest_name/email
 * match and user_id is still NULL.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../components/bootstrap.php';
require_once __DIR__ . '/../components/gaia-config.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../auth/csrf.php';

$gaia_base         = '';
$gaia_active       = 'home';
$gaia_header_style = 'solid';

require_login();

$userId = (int)auth_id();
$alerts = [];
$email = '';
$code  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        csrf_fail();
    }
    $email = strtolower(trim($_POST['email'] ?? ''));
    $code  = strtoupper(trim($_POST['booking_code'] ?? ''));

    $pdo = getPDO();
    $claimed = false;

    // transfers
    $stmt = $pdo->prepare("SELECT id FROM bookings WHERE booking_code = ? AND guest_email = ? AND user_id IS NULL LIMIT 1");
    $stmt->execute([$code, $email]);
    if ($row = $stmt->fetch()) {
        $pdo->prepare("UPDATE bookings SET user_id = ? WHERE id = ?")->execute([$userId, (int)$row['id']]);
        $claimed = true;
    }

    // tours
    if (!$claimed) {
        $stmt = $pdo->prepare("SELECT id FROM weroad_bookings WHERE booking_code = ? AND guest_email = ? AND user_id IS NULL LIMIT 1");
        $stmt->execute([$code, $email]);
        if ($row = $stmt->fetch()) {
            $pdo->prepare("UPDATE weroad_bookings SET user_id = ? WHERE id = ?")->execute([$userId, (int)$row['id']]);
            $claimed = true;
        }
    }

    // hotels
    if (!$claimed) {
        $stmt = $pdo->prepare("SELECT id FROM hotel_bookings WHERE booking_code = ? AND guest_email = ? AND user_id IS NULL LIMIT 1");
        $stmt->execute([$code, $email]);
        if ($row = $stmt->fetch()) {
            $pdo->prepare("UPDATE hotel_bookings SET user_id = ? WHERE id = ?")->execute([$userId, (int)$row['id']]);
            $claimed = true;
        }
    }

    // events
    if (!$claimed) {
        $stmt = $pdo->prepare("SELECT id FROM event_bookings WHERE booking_code = ? AND guest_email = ? AND user_id IS NULL LIMIT 1");
        $stmt->execute([$code, $email]);
        if ($row = $stmt->fetch()) {
            $pdo->prepare("UPDATE event_bookings SET user_id = ? WHERE id = ?")->execute([$userId, (int)$row['id']]);
            $claimed = true;
        }
    }

    // taxi
    if (!$claimed) {
        $stmt = $pdo->prepare("SELECT id FROM taxi_bookings WHERE booking_code = ? AND guest_email = ? AND user_id IS NULL LIMIT 1");
        $stmt->execute([$code, $email]);
        if ($row = $stmt->fetch()) {
            $pdo->prepare("UPDATE taxi_bookings SET user_id = ? WHERE id = ?")->execute([$userId, (int)$row['id']]);
            $claimed = true;
        }
    }

    if ($claimed) {
        $alerts[] = ['type' => 'success', 'msg' => t_fmt('account.claim_success', ['code' => $code])];
    } else {
        $alerts[] = ['type' => 'error', 'msg' => t('account.claim_not_found')];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags('account_claim', t('account.claim_booking') . ' — GAIA TOURS &amp; TRAVEL') ?>
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
.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:24px;box-shadow:var(--shadow);}
.card h2{font-size:20px;margin-bottom:6px;}
.card .sub{color:var(--muted);font-size:14px;margin:0 0 20px;}
.field{margin-bottom:16px;}
.field label{display:block;font-size:13px;font-weight:600;margin-bottom:6px;}
.field input{width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:10px;font-size:14px;font-family:inherit;background:#fbfaf7;transition:.2s;}
.field input:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px rgba(31,111,143,.12);background:#fff;}
.btn-primary{background:var(--teal);border:none;border-radius:10px;padding:13px 26px;color:#fff;font-size:15px;font-weight:700;cursor:pointer;transition:.2s;font-family:inherit;}
.btn-primary:hover{background:var(--teal-dark);}
.alert{background:#e8f5ee;color:#1b6e43;border:1px solid #b9e4cb;border-radius:10px;padding:12px 14px;font-size:13.5px;margin-bottom:18px;}
.alert-error{background:#fdecea;color:#b3261e;border-color:#f5c6c2;}
.alert-success{background:#e8f5ee;color:#1b6e43;border-color:#b9e4cb;}
@media (max-width:860px){.account-layout{grid-template-columns:1fr;}.account-nav{flex-direction:row;flex-wrap:wrap;}.account-logout-form{border-top:none;padding-top:0;}}
</style>
</head>
<body>

<?php require __DIR__ . '/../components/gaia-header.php'; ?>

<div class="account-shell">
<?php $activeTab = 'claim'; require __DIR__ . '/_layout.php'; ?>

  <div class="card">
    <h2><?= htmlspecialchars(t('account.claim_title')) ?></h2>
    <p class="sub"><?= htmlspecialchars(t('account.claim_text')) ?></p>

    <?php foreach ($alerts as $alert): ?>
      <div class="alert alert-<?= htmlspecialchars($alert['type']) ?>"><?= htmlspecialchars($alert['msg']) ?></div>
    <?php endforeach; ?>

    <form method="post" action="claim-booking.php" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="email"><?= htmlspecialchars(t('auth.email')) ?></label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
      </div>
      <div class="field">
        <label for="booking_code"><?= htmlspecialchars(t('account.booking_code')) ?></label>
        <input type="text" id="booking_code" name="booking_code" value="<?= htmlspecialchars($code) ?>" required>
      </div>
      <button type="submit" class="btn-primary"><?= htmlspecialchars(t('account.claim_booking')) ?></button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../components/gaia-footer.php'; ?>
</body>
</html>

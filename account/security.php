<?php
/**
 * account/security.php
 * ------------------------------------------------------------
 * Customer security settings.
 *
 * Features:
 *   - Change password (requires current password, validate new +
 *     confirmation, uses password_hash()/password_verify())
 *   - Account security information (created date, last login,
 *     email verification status)
 *
 * SECURITY:
 *   - CSRF protection on all POSTs
 *   - PDO prepared statements
 *   - password_verify() on the current password before updating
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

require_once __DIR__ . '/../auth/Auth.php';
$user   = auth_user();
$userId = (int)$user['id'];

$alerts = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        csrf_fail();
    }

    $current       = $_POST['current_password'] ?? '';
    $newPassword   = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($current === '') {
        $errors[] = t('account.current_password_required', 'Current password is required.');
    }
    if (strlen($newPassword) < Auth::PASSWORD_MIN_LENGTH) {
        $errors[] = t_fmt('account.password_min_length', ['min' => Auth::PASSWORD_MIN_LENGTH], gaia_current_lang());
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = t('account.password_mismatch', 'New passwords do not match.');
    }

    if (!$errors) {
        // Verify the current password against the stored hash.
        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $hash = $stmt->fetchColumn();

        if ($hash === false || !password_verify($current, $hash)) {
            $errors[] = t('account.current_password_wrong', 'Current password is incorrect.');
        } elseif ($newPassword !== '' && password_verify($newPassword, $hash)) {
            $errors[] = t('account.password_same', 'New password must be different from the current password.');
        } else {
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $upd = $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
            $upd->execute([':hash' => $newHash, ':id' => $userId]);
            $alerts[] = ['type' => 'success', 'msg' => t('account.password_updated', 'Password updated successfully.')];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags('account_security', t('account.security') . ' — GAIA TOURS &amp; TRAVEL') ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<style>
:root{--navy:#1b2a4a;--navy-2:#243761;--teal:#1f6f8f;--teal-dark:#175a75;--ink:#1c1e26;--muted:#6b7280;--line:#e8e6e0;--bg-soft:#f4efe6;--white:#fff;--radius:14px;--shadow:0 10px 30px rgba(27,42,74,0.08);}
*{box-sizing:border-box;}body{margin:0;font-family:'Inter',sans-serif;color:var(--ink);background:var(--bg-soft);-webkit-font-smoothing:antialiased;}
h1,h2,h3{font-family:'Playfair Display',serif;margin:0;}a{text-decoration:none;color:inherit;}
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
.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:24px;box-shadow:var(--shadow);margin-bottom:22px;}
.card h2{font-size:20px;margin-bottom:18px;}
.field{margin-bottom:16px;}
.field label{display:block;font-size:13px;font-weight:600;margin-bottom:6px;}
.field input{width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:10px;font-size:14px;font-family:inherit;background:#fbfaf7;transition:.2s;}
.field input:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px rgba(31,111,143,.12);background:#fff;}
.btn-primary{background:var(--teal);border:none;border-radius:10px;padding:13px 26px;color:#fff;font-size:15px;font-weight:700;cursor:pointer;transition:.2s;font-family:inherit;}
.btn-primary:hover{background:var(--teal-dark);}
.alert{background:#e8f5ee;color:#1b6e43;border:1px solid #b9e4cb;border-radius:10px;padding:12px 14px;font-size:13.5px;margin-bottom:18px;}
.alert-error{background:#fdecea;color:#b3261e;border-color:#f5c6c2;}
.alert-success{background:#e8f5ee;color:#1b6e43;border-color:#b9e4cb;}
.alert ul{margin:0;padding-left:18px;}
.list-item{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f0ede6;font-size:13.5px;flex-wrap:wrap;}
.list-item:last-child{border-bottom:none;}
.list-item .label{color:var(--muted);}
.list-item .value{font-weight:600;text-align:right;word-break:break-word;}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;text-transform:uppercase;}
.badge-verified,.badge-active{background:#e8f5ee;color:#1b6e43;}
.badge-unverified{background:#fff3d6;color:#8a6d00;}
@media (max-width:860px){.account-layout{grid-template-columns:1fr;}.account-nav{flex-direction:row;flex-wrap:wrap;}.account-logout-form{border-top:none;padding-top:0;}}
</style>
</head>
<body>

<?php require __DIR__ . '/../components/gaia-header.php'; ?>

<div class="account-shell">
  <?php $activeTab = 'security'; require __DIR__ . '/_layout.php'; ?>

  <div class="card">
    <h2><?= htmlspecialchars(t('account.security')) ?></h2>

    <?php if ($errors): ?>
      <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <?php foreach ($alerts as $alert): ?>
      <div class="alert alert-<?= htmlspecialchars($alert['type']) ?>"><?= htmlspecialchars($alert['msg']) ?></div>
    <?php endforeach; ?>

    <form method="post" action="security.php" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="current_password"><?= htmlspecialchars(t('account.current_password')) ?> *</label>
        <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
      </div>
      <div class="field">
        <label for="new_password"><?= htmlspecialchars(t('account.new_password')) ?> *</label>
        <input type="password" id="new_password" name="new_password" required autocomplete="new-password">
      </div>
      <div class="field">
        <label for="confirm_password"><?= htmlspecialchars(t('account.confirm_password')) ?> *</label>
        <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
      </div>
      <button type="submit" class="btn-primary"><?= htmlspecialchars(t('account.change_password')) ?></button>
    </form>
  </div>

  <div class="card">
    <h2><?= htmlspecialchars(t('account.security_info')) ?></h2>
    <div class="list-item">
      <span class="label"><?= htmlspecialchars(t('account.created_at')) ?></span>
      <span class="value"><?= htmlspecialchars($user['created_at'] ?? '—') ?></span>
    </div>
    <div class="list-item">
      <span class="label"><?= htmlspecialchars(t('account.last_login')) ?></span>
      <span class="value"><?= htmlspecialchars($user['last_login_at'] ? $user['last_login_at'] : '—') ?></span>
    </div>
    <div class="list-item">
      <span class="label"><?= htmlspecialchars(t('account.email_verified')) ?></span>
      <span class="value">
        <?php if (!empty($user['email_verified_at'])): ?>
          <span class="badge badge-verified"><?= htmlspecialchars(t('account.verified')) ?></span>
        <?php else: ?>
          <span class="badge badge-unverified"><?= htmlspecialchars(t('account.unverified')) ?></span>
        <?php endif; ?>
      </span>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../components/gaia-footer.php'; ?>
</body>
</html>

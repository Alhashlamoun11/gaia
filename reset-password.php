<?php
/**
 * reset-password.php
 * ------------------------------------------------------------
 * Handles the token-based password reset.
 * Validates the token, then lets the user choose a new password.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/components/bootstrap.php';
require_once __DIR__ . '/components/gaia-config.php';
require_once __DIR__ . '/auth/Auth.php';
require_once __DIR__ . '/auth/csrf.php';

$gaia_base         = '';
$gaia_active       = 'home';
$gaia_header_style = 'solid';

Auth::startSession();

$errors = [];
$done = false;
$token = $_GET['token'] ?? ($_POST['token'] ?? '');

// Validate the token up front
$userId = $token !== '' ? Auth::validateResetToken($token) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        csrf_fail();
    }
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if (!$userId) {
        $errors[] = t('auth.invalid_reset_token');
    }
    if ($password === '') {
        $errors[] = t('auth.password_required');
    } elseif (strlen($password) < Auth::PASSWORD_MIN_LENGTH) {
        $errors[] = t('auth.password_min');
    }
    if ($password !== $confirm) {
        $errors[] = t('auth.password_mismatch');
    }

    if (!$errors && $userId) {
        Auth::resetPassword($userId, $password, $token);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags('reset_password', t('auth.reset_password_title') . ' — GAIA TOURS &amp; TRAVEL') ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<style>
:root{--navy:#1b2a4a;--navy-2:#243761;--teal:#1f6f8f;--teal-dark:#175a75;--ink:#1c1e26;--muted:#6b7280;--line:#e8e6e0;--bg-soft:#f4efe6;--white:#fff;--radius:14px;--shadow:0 10px 30px rgba(27,42,74,0.08);}
*{box-sizing:border-box;}body{margin:0;font-family:'Inter',sans-serif;color:var(--ink);background:var(--bg-soft);-webkit-font-smoothing:antialiased;}
.auth-wrap{min-height:calc(100vh - 140px);display:flex;align-items:center;justify-content:center;padding:48px 16px;}
.auth-card{background:#fff;border:1px solid var(--line);border-radius:22px;box-shadow:var(--shadow);width:100%;max-width:440px;padding:40px;}
.auth-card h1{font-family:'Playfair Display',serif;font-size:24px;margin:0 0 6px;}
.auth-card .sub{color:var(--muted);font-size:14px;margin:0 0 24px;}
.field{margin-bottom:18px;}
.field label{display:block;font-size:13px;font-weight:600;margin-bottom:6px;}
.field input{width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:10px;font-size:14px;font-family:inherit;background:#fbfaf7;transition:.2s;}
.field input:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px rgba(31,111,143,.12);background:#fff;}
.btn-primary{width:100%;background:var(--teal);border:none;border-radius:10px;padding:13px;color:#fff;font-size:15px;font-weight:700;cursor:pointer;transition:.2s;font-family:inherit;}
.btn-primary:hover{background:var(--teal-dark);}
.auth-switch{text-align:center;margin-top:20px;font-size:14px;color:var(--muted);}
.auth-switch a{color:var(--teal);font-weight:600;text-decoration:none;}
.alert{background:#fdecea;color:#b3261e;border:1px solid #f5c6c2;border-radius:10px;padding:11px 14px;font-size:13.5px;margin-bottom:18px;}
.alert ul{margin:0;padding-left:18px;}
.success{background:#e8f5ee;color:#1b6e43;border:1px solid #b9e4cb;border-radius:10px;padding:14px 16px;font-size:13.5px;margin-bottom:18px;}
</style>
</head>
<body>

<?php require __DIR__ . '/components/gaia-header.php'; ?>

<div class="auth-wrap">
  <div class="auth-card">
    <h1><?= htmlspecialchars(t('auth.reset_password_title')) ?></h1>
    <p class="sub"><?= htmlspecialchars(t('auth.password_min')) ?></p>

    <?php if ($errors): ?>
      <div class="alert"><ul><?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if ($done): ?>
      <div class="success"><?= htmlspecialchars(t('auth.reset_success')) ?></div>
      <div class="auth-switch"><a href="login.php"><?= htmlspecialchars(t('auth.login')) ?></a></div>
    <?php elseif ($userId): ?>
      <form method="post" action="reset-password.php" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div class="field">
          <label for="password"><?= htmlspecialchars(t('auth.password')) ?></label>
          <input type="password" id="password" name="password" required autofocus autocomplete="new-password">
        </div>
        <div class="field">
          <label for="password_confirm"><?= htmlspecialchars(t('auth.confirm_password')) ?></label>
          <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn-primary"><?= htmlspecialchars(t('auth.submit')) ?></button>
      </form>
    <?php else: ?>
      <div class="alert"><?= htmlspecialchars(t('auth.invalid_reset_token')) ?></div>
      <div class="auth-switch"><a href="forgot-password.php"><?= htmlspecialchars(t('auth.forgot_password')) ?></a></div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/components/gaia-footer.php'; ?>
</body>
</html>

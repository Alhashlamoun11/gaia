<?php
/**
 * login.php
 * ------------------------------------------------------------
 * Customer / staff login page.
 *
 * Features:
 *   - Email + password
 *   - Remember me
 *   - CSRF protection
 *   - Login throttling
 *   - Session regeneration on success
 *   - Role-based redirect after login
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/components/bootstrap.php';
require_once __DIR__ . '/components/gaia-config.php';
require_once __DIR__ . '/auth/Auth.php';
require_once __DIR__ . '/auth/csrf.php';
require_once __DIR__ . '/auth/middleware.php';

$gaia_base         = '';
$gaia_active       = 'home';
$gaia_header_style = 'solid';

Auth::startSession();

// Capture a safe return-to target (booking flow) before any redirect.
$redirect = safe_redirect_target($_GET['redirect'] ?? '');

// Already logged in? Send to the right place.
if (Auth::check()) {
    $role = Auth::role();
    if ($role === 'super_admin' || $role === 'hotel_manager') {
        header('Location: ' . (function_exists('gaia_url') ? gaia_url('index.php') : 'index.php'));
        exit;
    }
    if ($redirect !== '') {
        header('Location: ' . $redirect);
        exit;
    }
    header('Location: ' . (function_exists('gaia_url') ? gaia_url('account/index.php') : 'account/index.php'));
    exit;
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        csrf_fail();
    }
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('auth.email_required');
    }
    if ($password === '') {
        $errors[] = t('auth.password_required');
    }

    if (!$errors) {
[$ok, $errKey] = Auth::login($email, $password, $remember);
        if ($ok) {
            $role = Auth::role();
            if ($role === 'super_admin' || $role === 'hotel_manager') {
                header('Location: ' . (function_exists('gaia_url') ? gaia_url('index.php') : 'index.php'));
                exit;
            }
            if ($redirect !== '') {
                header('Location: ' . $redirect);
                exit;
            }
            header('Location: ' . (function_exists('gaia_url') ? gaia_url('account/index.php') : 'account/index.php'));
            exit;
        }
        $errors[] = t($errKey, $errKey);
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags('login', t('auth.login') . ' — GAIA TOURS &amp; TRAVEL') ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<style>
:root{--navy:#1b2a4a;--navy-2:#243761;--teal:#1f6f8f;--teal-dark:#175a75;--ink:#1c1e26;--muted:#6b7280;--line:#e8e6e0;--bg-soft:#f4efe6;--white:#fff;--radius:14px;--shadow:0 10px 30px rgba(27,42,74,0.08);}
*{box-sizing:border-box;}body{margin:0;font-family:'Inter',sans-serif;color:var(--ink);background:var(--bg-soft);-webkit-font-smoothing:antialiased;}
.auth-wrap{min-height:calc(100vh - 140px);display:flex;align-items:center;justify-content:center;padding:48px 16px;}
.auth-card{background:#fff;border:1px solid var(--line);border-radius:22px;box-shadow:var(--shadow);width:100%;max-width:440px;padding:40px;}
.auth-card h1{font-family:'Playfair Display',serif;font-size:26px;margin:0 0 6px;}
.auth-card .sub{color:var(--muted);font-size:14px;margin:0 0 24px;}
.field{margin-bottom:16px;}
.field label{display:block;font-size:13px;font-weight:600;margin-bottom:6px;}
.field input{width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:10px;font-size:14px;font-family:inherit;background:#fbfaf7;transition:.2s;}
.field input:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px rgba(31,111,143,.12);background:#fff;}
.row-between{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:4px 0 20px;font-size:13.5px;}
.row-between label{display:flex;align-items:center;gap:7px;color:var(--ink);cursor:pointer;}
.row-between a{color:var(--teal);text-decoration:none;font-weight:600;}
.btn-primary{width:100%;background:var(--teal);border:none;border-radius:10px;padding:13px;color:#fff;font-size:15px;font-weight:700;cursor:pointer;transition:.2s;font-family:inherit;}
.btn-primary:hover{background:var(--teal-dark);}
.auth-switch{text-align:center;margin-top:20px;font-size:14px;color:var(--muted);}
.auth-switch a{color:var(--teal);font-weight:600;text-decoration:none;}
.alert{background:#fdecea;color:#b3261e;border:1px solid #f5c6c2;border-radius:10px;padding:11px 14px;font-size:13.5px;margin-bottom:18px;}
.alert ul{margin:0;padding-left:18px;}
</style>
</head>
<body>

<?php require __DIR__ . '/components/gaia-header.php'; ?>

<div class="auth-wrap">
  <div class="auth-card">
    <h1><?= htmlspecialchars(t('auth.login')) ?></h1>
    <p class="sub"><?= htmlspecialchars(t('auth.have_account') === 'auth.have_account' ? 'Sign in to your GAIA account' : t('auth.have_account')) ?></p>

    <?php if ($errors): ?>
      <div class="alert"><ul><?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="post" action="login.php" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="email"><?= htmlspecialchars(t('auth.email')) ?></label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required autofocus autocomplete="email">
      </div>
      <div class="field">
        <label for="password"><?= htmlspecialchars(t('auth.password')) ?></label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <div class="row-between">
        <label><input type="checkbox" name="remember" value="1"> <?= htmlspecialchars(t('auth.remember_me')) ?></label>
        <a href="forgot-password.php"><?= htmlspecialchars(t('auth.forgot_password')) ?></a>
      </div>
      <button type="submit" class="btn-primary"><?= htmlspecialchars(t('auth.login')) ?></button>
    </form>

    <div class="auth-switch">
      <?= htmlspecialchars(t('auth.no_account')) ?> <a href="register.php"><?= htmlspecialchars(t('auth.register')) ?></a>
    </div>
  </div>
</div>

<?php require __DIR__ . '/components/gaia-footer.php'; ?>
</body>
</html>

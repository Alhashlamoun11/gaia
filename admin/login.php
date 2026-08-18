<?php
/**
 * admin/login.php
 * ------------------------------------------------------------
 * Admin panel login. Uses the EXISTING GAIA Auth system.
 *
 * Only super_admin can continue into /admin/* after login.
 * Guests see this form; other roles are redirected home.
 * Reuses csrf_field() / csrf_verify() and Auth::login().
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/_bootstrap.php';

Auth::startSession();

// Already logged in?
if (Auth::check()) {
    if (Auth::role() === 'super_admin') {
        header('Location: ' . admin_url('index.php'));
        exit;
    }
    header('Location: ' . (function_exists('gaia_url') ? gaia_url('index.php') : 'index.php'));
    exit;
}

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        csrf_fail();
    }
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('auth.email_required', 'A valid email is required.');
    }
    if ($password === '') {
        $errors[] = t('auth.password_required', 'Password is required.');
    }

    if (!$errors) {
        [$ok, $errKey] = Auth::login($email, $password, $remember);
        if ($ok) {
            if (Auth::role() === 'super_admin') {
                admin_flash(t('auth.login_success', 'Welcome back!'), 'success');
                header('Location: ' . admin_url('index.php'));
                exit;
            }
            Auth::logout();
            $errors[] = t('admin.access_denied', 'You do not have administrator access.');
        } else {
            $errors[] = t($errKey, 'Invalid email or password.');
        }
    }
}

$admin_page_title = t('admin.dashboard') . ' — Admin Login';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= htmlspecialchars($admin_page_title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/admin/assets/admin.css">
<style>
 body{background:linear-gradient(160deg,#1b2a4a,#243761 60%,#2c3f6b);display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px;}
 .login-wrap{width:100%;max-width:420px;}
 .login-card{background:#fff;border-radius:20px;box-shadow:0 30px 70px rgba(0,0,0,.35);padding:40px;}
 .login-logo{display:flex;align-items:center;gap:12px;margin-bottom:8px;}
 .login-logo .mark{width:48px;height:48px;border-radius:14px;background:var(--gold);color:var(--navy);display:flex;align-items:center;justify-content:center;font-family:'Playfair Display',serif;font-weight:800;font-size:24px;}
 .login-logo strong{font-family:'Playfair Display',serif;font-size:22px;color:var(--navy);}
 .login-logo span{display:block;font-size:12px;color:var(--muted);}
 .login-card h1{font-size:22px;margin:14px 0 4px;}
 .login-card .sub{color:var(--muted);font-size:14px;margin-bottom:24px;}
 .field{margin-bottom:16px;}
 .field label{display:block;font-size:13px;font-weight:600;margin-bottom:6px;}
 .field input{width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:10px;font-size:14px;font-family:inherit;background:#fbfaf7;}
 .field input:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px rgba(31,111,143,.12);background:#fff;}
 .row-between{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:4px 0 20px;font-size:13.5px;}
 .row-between label{display:flex;align-items:center;gap:7px;color:var(--ink);}
 .btn-primary{width:100%;background:var(--teal);border:none;border-radius:10px;padding:13px;color:#fff;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;}
 .btn-primary:hover{background:var(--teal-dark);}
 .alert{background:#fdecea;color:#b3261e;border:1px solid #f5c6c2;border-radius:10px;padding:11px 14px;font-size:13.5px;margin-bottom:18px;}
 .alert ul{margin:0;padding-left:18px;}
 .back-link{display:block;text-align:center;margin-top:18px;color:#c7c3e2;font-size:13px;text-decoration:none;}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <span class="mark"><?= htmlspecialchars(site_setting('company_logo_mark', 'G')) ?></span>
      <div>
        <strong><?= htmlspecialchars(site_setting('company_short', 'GAIA')) ?></strong>
        <span><?= htmlspecialchars(t('admin.dashboard', 'Admin Panel')) ?></span>
      </div>
    </div>
    <h1><?= htmlspecialchars(t('auth.login', 'Login')) ?></h1>
    <p class="sub"><?= htmlspecialchars(t('admin.login_sub', 'Sign in to manage your website.')) ?></p>

    <?php if ($errors): ?>
      <div class="alert"><ul><?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="post" action="login.php" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="email"><?= htmlspecialchars(t('auth.email', 'Email')) ?></label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required autofocus autocomplete="email">
      </div>
      <div class="field">
        <label for="password"><?= htmlspecialchars(t('auth.password', 'Password')) ?></label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <div class="row-between">
        <label><input type="checkbox" name="remember" value="1"> <?= htmlspecialchars(t('auth.remember_me', 'Remember me')) ?></label>
      </div>
      <button type="submit" class="btn-primary"><?= htmlspecialchars(t('auth.login', 'Login')) ?></button>
    </form>
  </div>
  <a class="back-link" href="<?= htmlspecialchars(gaia_url('index.php')) ?>"><?= htmlspecialchars(t('header.start_booking', '← Back to website')) ?></a>
</div>
</body>
</html>

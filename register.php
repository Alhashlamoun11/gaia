<?php
/**
 * register.php
 * ------------------------------------------------------------
 * Customer registration.
 *
 * Features:
 *   - First name, last name, email, phone, password, confirm
 *   - Unique email + password hashing (password_hash)
 *   - Automatic customer role assignment
 *   - Auto-login after registration
 *   - Redirect to account dashboard
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
if (Auth::check()) {
    header('Location: account/index.php');
    exit;
}

$errors = [];
$old = [
    'first_name' => '',
    'last_name'  => '',
    'email'      => '',
    'phone'      => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        csrf_fail();
    }
    $old = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name'  => trim($_POST['last_name'] ?? ''),
        'email'      => strtolower(trim($_POST['email'] ?? '')),
        'phone'      => trim($_POST['phone'] ?? ''),
    ];
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['password_confirm'] ?? '';
    $prefLang  = in_array($_POST['preferred_language'] ?? '', ['en', 'ar'], true) ? $_POST['preferred_language'] : 'en';

    if ($old['first_name'] === '') $errors[] = t('auth.first_name');
    if ($old['last_name'] === '')  $errors[] = t('auth.last_name');
    if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('auth.email_required');
    }
    if ($password === '') {
        $errors[] = t('auth.password_required');
    } elseif (strlen($password) < Auth::PASSWORD_MIN_LENGTH) {
        $errors[] = t('auth.password_min');
    }
    if ($password !== $confirm) {
        $errors[] = t('auth.password_mismatch');
    }

    if (!$errors) {
        [$ok, $user] = Auth::register([
            'first_name'          => $old['first_name'],
            'last_name'           => $old['last_name'],
            'email'               => $old['email'],
            'phone'               => $old['phone'],
            'preferred_language'  => $prefLang,
            'password'            => $password,
        ]);
        if (!$ok) {
            $errors[] = t('auth.email_taken');
        } else {
            // Auto-login
            Auth::login($old['email'], $password, false);
            header('Location: account/index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags('register', t('auth.register') . ' — GAIA TOURS &amp; TRAVEL') ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<style>
:root{--navy:#1b2a4a;--navy-2:#243761;--teal:#1f6f8f;--teal-dark:#175a75;--ink:#1c1e26;--muted:#6b7280;--line:#e8e6e0;--bg-soft:#f4efe6;--white:#fff;--radius:14px;--shadow:0 10px 30px rgba(27,42,74,0.08);}
*{box-sizing:border-box;}body{margin:0;font-family:'Inter',sans-serif;color:var(--ink);background:var(--bg-soft);-webkit-font-smoothing:antialiased;}
.auth-wrap{min-height:calc(100vh - 140px);display:flex;align-items:center;justify-content:center;padding:48px 16px;}
.auth-card{background:#fff;border:1px solid var(--line);border-radius:22px;box-shadow:var(--shadow);width:100%;max-width:520px;padding:40px;}
.auth-card h1{font-family:'Playfair Display',serif;font-size:26px;margin:0 0 6px;}
.auth-card .sub{color:var(--muted);font-size:14px;margin:0 0 24px;}
.field{margin-bottom:16px;}
.field label{display:block;font-size:13px;font-weight:600;margin-bottom:6px;}
.field input{width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:10px;font-size:14px;font-family:inherit;background:#fbfaf7;transition:.2s;}
.field input:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px rgba(31,111,143,.12);background:#fff;}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.btn-primary{width:100%;background:var(--teal);border:none;border-radius:10px;padding:13px;color:#fff;font-size:15px;font-weight:700;cursor:pointer;transition:.2s;font-family:inherit;margin-top:6px;}
.btn-primary:hover{background:var(--teal-dark);}
.auth-switch{text-align:center;margin-top:20px;font-size:14px;color:var(--muted);}
.auth-switch a{color:var(--teal);font-weight:600;text-decoration:none;}
.alert{background:#fdecea;color:#b3261e;border:1px solid #f5c6c2;border-radius:10px;padding:11px 14px;font-size:13.5px;margin-bottom:18px;}
.alert ul{margin:0;padding-left:18px;}
@media (max-width:520px){.grid-2{grid-template-columns:1fr;}}
</style>
</head>
<body>

<?php require __DIR__ . '/components/gaia-header.php'; ?>

<div class="auth-wrap">
  <div class="auth-card">
    <h1><?= htmlspecialchars(t('auth.register')) ?></h1>
    <p class="sub"><?= htmlspecialchars(t('auth.no_account')) ?> — <?= htmlspecialchars(t('auth.register_success')) ?></p>

    <?php if ($errors): ?>
      <div class="alert"><ul><?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="post" action="register.php" novalidate>
      <?= csrf_field() ?>
      <div class="grid-2">
        <div class="field">
          <label for="first_name"><?= htmlspecialchars(t('auth.first_name')) ?> *</label>
          <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($old['first_name']) ?>" required autofocus>
        </div>
        <div class="field">
          <label for="last_name"><?= htmlspecialchars(t('auth.last_name')) ?> *</label>
          <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($old['last_name']) ?>" required>
        </div>
      </div>
      <div class="field">
        <label for="email"><?= htmlspecialchars(t('auth.email')) ?> *</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($old['email']) ?>" required autocomplete="email">
      </div>
      <div class="field">
        <label for="phone"><?= htmlspecialchars(t('auth.phone')) ?></label>
        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($old['phone']) ?>" autocomplete="tel">
      </div>
      <div class="grid-2">
        <div class="field">
          <label for="password"><?= htmlspecialchars(t('auth.password')) ?> *</label>
          <input type="password" id="password" name="password" required autocomplete="new-password">
        </div>
        <div class="field">
          <label for="password_confirm"><?= htmlspecialchars(t('auth.confirm_password')) ?> *</label>
          <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
        </div>
      </div>
      <button type="submit" class="btn-primary"><?= htmlspecialchars(t('auth.register')) ?></button>
    </form>

    <div class="auth-switch">
      <?= htmlspecialchars(t('auth.have_account')) ?> <a href="login.php"><?= htmlspecialchars(t('auth.login')) ?></a>
    </div>
  </div>
</div>

<?php require __DIR__ . '/components/gaia-footer.php'; ?>
</body>
</html>

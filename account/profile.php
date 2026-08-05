<?php
/**
 * account/profile.php
 * ------------------------------------------------------------
 * Customer profile editing.
 *
 * Editable: first name, last name, email, phone, country,
 * preferred language, avatar (URL).
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

$user = auth_user();
$userId = (int)$user['id'];

$alerts = [];
$errors = [];

$old = [
    'first_name'         => $user['first_name'],
    'last_name'          => $user['last_name'],
    'email'              => $user['email'],
    'phone'              => $user['phone'],
    'country'            => $user['country'],
    'preferred_language' => $user['preferred_language'],
    'avatar'             => $user['avatar'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        csrf_fail();
    }
    $old = [
        'first_name'         => trim($_POST['first_name'] ?? ''),
        'last_name'          => trim($_POST['last_name'] ?? ''),
        'email'              => strtolower(trim($_POST['email'] ?? '')),
        'phone'              => trim($_POST['phone'] ?? ''),
        'country'            => trim($_POST['country'] ?? ''),
        'preferred_language' => in_array($_POST['preferred_language'] ?? '', ['en', 'ar'], true) ? $_POST['preferred_language'] : 'en',
        'avatar'             => trim($_POST['avatar'] ?? ''),
    ];

    if ($old['first_name'] === '') $errors[] = t('auth.first_name');
    if ($old['last_name'] === '')  $errors[] = t('auth.last_name');
    if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('auth.email_required');
    }

    if (!$errors) {
        // Unique email check (excluding self)
        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
        $stmt->execute([$old['email'], $userId]);
        if ($stmt->fetch()) {
            $errors[] = t('auth.email_taken');
        }
    }

    if (!$errors) {
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            'UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, country = ?, preferred_language = ?, avatar = ? WHERE id = ?'
        );
        $stmt->execute([
            $old['first_name'],
            $old['last_name'],
            $old['email'],
            $old['phone'] !== '' ? $old['phone'] : null,
            $old['country'] !== '' ? $old['country'] : null,
            $old['preferred_language'],
            $old['avatar'] !== '' ? $old['avatar'] : null,
            $userId,
        ]);

        // Update session name/email/lang
        $_SESSION['auth_name']  = $old['first_name'] . ' ' . $old['last_name'];
        $_SESSION['auth_email'] = $old['email'];
        $_SESSION['gaia_lang']  = $old['preferred_language'];

        $alerts[] = ['type' => 'success', 'msg' => t('account.profile_updated')];
        $user = auth_user(); // refresh
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags('account_profile', t('account.profile') . ' — GAIA TOURS &amp; TRAVEL') ?>
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
.card h2{font-size:20px;margin-bottom:18px;}
.field{margin-bottom:16px;}
.field label{display:block;font-size:13px;font-weight:600;margin-bottom:6px;}
.field input{width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:10px;font-size:14px;font-family:inherit;background:#fbfaf7;transition:.2s;}
.field input:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px rgba(31,111,143,.12);background:#fff;}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.btn-primary{background:var(--teal);border:none;border-radius:10px;padding:13px 26px;color:#fff;font-size:15px;font-weight:700;cursor:pointer;transition:.2s;font-family:inherit;}
.btn-primary:hover{background:var(--teal-dark);}
.alert{background:#e8f5ee;color:#1b6e43;border:1px solid #b9e4cb;border-radius:10px;padding:12px 14px;font-size:13.5px;margin-bottom:18px;}
.alert-error{background:#fdecea;color:#b3261e;border-color:#f5c6c2;}
.alert-success{background:#e8f5ee;color:#1b6e43;border-color:#b9e4cb;}
.alert ul{margin:0;padding-left:18px;}
.avatar-preview{width:72px;height:72px;border-radius:50%;object-fit:cover;background:var(--navy);margin-bottom:12px;}
@media (max-width:860px){.account-layout{grid-template-columns:1fr;}.account-nav{flex-direction:row;flex-wrap:wrap;}.account-logout-form{border-top:none;padding-top:0;}.grid-2{grid-template-columns:1fr;}}
</style>
</head>
<body>

<?php require __DIR__ . '/../components/gaia-header.php'; ?>

<div class="account-shell">
  <?php
    $activeTab = 'profile';
    require __DIR__ . '/_layout.php';
  ?>

  <div class="card">
    <h2><?= htmlspecialchars(t('account.profile')) ?></h2>

    <?php if ($errors): ?>
      <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <?php foreach ($alerts as $alert): ?>
      <div class="alert alert-<?= htmlspecialchars($alert['type']) ?>"><?= htmlspecialchars($alert['msg']) ?></div>
    <?php endforeach; ?>

    <form method="post" action="profile.php" novalidate>
      <?= csrf_field() ?>
      <div class="grid-2">
        <div class="field">
          <label for="first_name"><?= htmlspecialchars(t('auth.first_name')) ?> *</label>
          <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($old['first_name']) ?>" required>
        </div>
        <div class="field">
          <label for="last_name"><?= htmlspecialchars(t('auth.last_name')) ?> *</label>
          <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($old['last_name']) ?>" required>
        </div>
      </div>
      <div class="grid-2">
        <div class="field">
          <label for="email"><?= htmlspecialchars(t('auth.email')) ?> *</label>
          <input type="email" id="email" name="email" value="<?= htmlspecialchars($old['email']) ?>" required>
        </div>
        <div class="field">
          <label for="phone"><?= htmlspecialchars(t('auth.phone')) ?></label>
          <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($old['phone']) ?>">
        </div>
      </div>
      <div class="grid-2">
        <div class="field">
          <label for="country"><?= htmlspecialchars(t('auth.country')) ?></label>
          <input type="text" id="country" name="country" value="<?= htmlspecialchars($old['country']) ?>">
        </div>
        <div class="field">
          <label for="preferred_language"><?= htmlspecialchars(t('auth.preferred_language')) ?></label>
          <select id="preferred_language" name="preferred_language" style="width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:10px;font-size:14px;font-family:inherit;background:#fbfaf7;">
            <option value="en" <?= $old['preferred_language'] === 'en' ? 'selected' : '' ?>>English</option>
            <option value="ar" <?= $old['preferred_language'] === 'ar' ? 'selected' : '' ?>>العربية</option>
          </select>
        </div>
      </div>
      <div class="field">
        <label for="avatar"><?= htmlspecialchars(t('account.avatar')) ?></label>
        <img src="<?= htmlspecialchars(auth_avatar()) ?>" alt="" class="avatar-preview">
        <input type="url" id="avatar" name="avatar" value="<?= htmlspecialchars($old['avatar']) ?>" placeholder="https://...">
      </div>
      <button type="submit" class="btn-primary"><?= htmlspecialchars(t('account.save_changes')) ?></button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../components/gaia-footer.php'; ?>
</body>
</html>

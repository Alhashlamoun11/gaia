<?php
/**
 * account/profile.php
 * ------------------------------------------------------------
 * Customer profile editing.
 *
 * Editable: first name, last name, phone, country, city, address,
 * nationality, preferred language, avatar (file or URL).
 * EMAIL IS IMMUTABLE — it is displayed as read-only and never
 * updated in this phase.
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
    'city'               => $user['city'] ?? '',
    'address'            => $user['address'] ?? '',
    'nationality'        => $user['nationality'] ?? '',
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
        'email'              => strtolower(trim($_POST['email'] ?? $user['email'])),
        'phone'              => trim($_POST['phone'] ?? ''),
        'country'            => trim($_POST['country'] ?? ''),
        'city'               => trim($_POST['city'] ?? ''),
        'address'            => trim($_POST['address'] ?? ''),
        'nationality'        => trim($_POST['nationality'] ?? ''),
        'preferred_language' => in_array($_POST['preferred_language'] ?? '', ['en', 'ar'], true) ? $_POST['preferred_language'] : 'en',
        'avatar'             => trim($_POST['avatar'] ?? ''),
    ];

    if ($old['first_name'] === '') $errors[] = t('auth.first_name');
    if ($old['last_name'] === '')  $errors[] = t('auth.last_name');
    if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('auth.email_required');
    }

    // ------------------------------------------------------------
    // Avatar FILE upload (optional) with validation.
    // Allowed: JPG / PNG / WEBP, max 2MB. Stored in uploads/avatars.
    // The URL field is kept as a manual fallback.
    // ------------------------------------------------------------
    $uploadedAvatar = null;
    if (!empty($_FILES['avatar_file']['name']) && $_FILES['avatar_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file      = $_FILES['avatar_file'];
        $tmpPath   = $file['tmp_name'] ?? '';
        $fileError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $fileSize  = (int)($file['size'] ?? 0);

        if ($fileError !== UPLOAD_ERR_OK) {
            $errors[] = t('account.avatar_upload_error');
        } elseif ($fileSize > 2 * 1024 * 1024) {
            $errors[] = t('account.avatar_too_large');
        } else {
            // Validate real MIME type via finfo (not just extension).
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($tmpPath);
            $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($allowedMimes[$mime])) {
                $errors[] = t('account.avatar_invalid_type');
            } else {
                $ext = $allowedMimes[$mime];
                $dir = __DIR__ . '/../uploads/avatars';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                if (!is_dir($dir) || !is_writable($dir)) {
                    $errors[] = t('account.avatar_upload_error');
                } else {
                    $filename = 'u' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $dest = $dir . '/' . $filename;
                    if (!@move_uploaded_file($tmpPath, $dest)) {
                        $errors[] = t('account.avatar_upload_error');
                    } else {
                        $uploadedAvatar = 'uploads/avatars/' . $filename;
                        // Remove the previous uploaded avatar (keep URL fallback).
                        $oldAvatar = (string)($user['avatar'] ?? '');
                        if ($oldAvatar !== '' && strpos($oldAvatar, 'uploads/') === 0) {
                            $prev = __DIR__ . '/../' . $oldAvatar;
                            if (is_file($prev)) {
                                @unlink($prev);
                            }
                        }
                    }
                }
            }
        }
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
        // Resolve final avatar: uploaded file wins, else the URL field.
        $finalAvatar = $uploadedAvatar !== null ? $uploadedAvatar : ($old['avatar'] !== '' ? $old['avatar'] : null);

        $pdo = getPDO();
        $stmt = $pdo->prepare(
            'UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, country  = ?, preferred_language = ?, avatar = ? WHERE id = ?'
        );
        $stmt->execute([
            $old['first_name'],
            $old['last_name'],
            $old['email'],
            $old['phone'] !== '' ? $old['phone'] : null,
            // $old['country'] !== '' ? $old['country'] : null,
            // $old['city'] !== '' ? $old['city'] : null,
            // $old['address'] !== '' ? $old['address'] : null,
            $old['nationality'] !== '' ? $old['nationality'] : null,
            $old['preferred_language'],
            $finalAvatar,
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

$gaia_page_key   = 'account_profile';
$gaia_page_title = t('account.profile') . ' — GAIA TOURS &amp; TRAVEL';
$activeTab = 'profile';
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
        ['label' => t('account.profile'), 'url' => ''],
    ];
    require __DIR__ . '/../components/breadcrumbs.php';
  ?>
  <div class="account-layout">
    <?php require __DIR__ . '/../components/user-sidebar.php'; ?>
    <main class="account-main">
      <?php
        $account_alerts = array_merge(
            array_map(function ($e) { return ['type' => 'error', 'msg' => $e]; }, $errors),
            $alerts
        );
        require __DIR__ . '/../components/alert.php';
      ?>

      <div class="card">
    <h2><?= htmlspecialchars(t('account.profile')) ?></h2>

    <form method="post" action="profile.php" enctype="multipart/form-data" novalidate>
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
          <input type="email" id="email" name="email" value="<?= htmlspecialchars($old['email']) ?>" disabled>
          <p class="avatar-hint" style="margin-top:6px;"><?= htmlspecialchars(t('account.email_immutable', 'Email cannot be changed.')) ?></p>
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
          <label for="city"><?= htmlspecialchars(t('account.city')) ?></label>
          <input type="text" id="city" name="city" value="<?= htmlspecialchars($old['city']) ?>">
        </div>
      </div>
      <div class="grid-2">
        <div class="field">
          <label for="address"><?= htmlspecialchars(t('account.address')) ?></label>
          <input type="text" id="address" name="address" value="<?= htmlspecialchars($old['address']) ?>">
        </div>
        <div class="field">
          <label for="nationality"><?= htmlspecialchars(t('account.nationality')) ?></label>
          <input type="text" id="nationality" name="nationality" value="<?= htmlspecialchars($old['nationality']) ?>">
        </div>
      </div>
      <div class="field">
        <label for="preferred_language"><?= htmlspecialchars(t('auth.preferred_language')) ?></label>
        <select id="preferred_language" name="preferred_language" style="width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:10px;font-size:14px;font-family:inherit;background:#fbfaf7;">
          <option value="en" <?= $old['preferred_language'] === 'en' ? 'selected' : '' ?>>English</option>
          <option value="ar" <?= $old['preferred_language'] === 'ar' ? 'selected' : '' ?>>العربية</option>
        </select>
      </div>
      <div class="field">
        <label><?= htmlspecialchars(t('account.avatar')) ?></label>
        <img src="<?= htmlspecialchars(auth_avatar()) ?>" alt="" class="avatar-preview">
        <input type="file" id="avatar_file" name="avatar_file" accept="image/jpeg,image/png,image/webp">
        <div class="avatar-hint"><?= htmlspecialchars(t('account.avatar_hint')) ?></div>
      </div>
      <div class="field">
        <label for="avatar"><?= htmlspecialchars(t('account.avatar_url')) ?></label>
        <input type="url" id="avatar" name="avatar" value="<?= htmlspecialchars($old['avatar']) ?>" placeholder="https://...">
      </div>
<button type="submit" class="btn-primary"><?= htmlspecialchars(t('account.save_changes')) ?></button>
    </form>
      </div>
    </main>
  </div>
</div>

<?php require __DIR__ . '/../components/gaia-footer.php'; ?>
</body>
</html>

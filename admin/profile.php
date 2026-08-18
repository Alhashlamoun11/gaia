<?php
/**
 * admin/profile.php
 * ------------------------------------------------------------
 * Admin profile: update name, phone, avatar + change password.
 * Email is read-only (immutable). CSRF protected. Uses Auth.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/_bootstrap.php';
require_admin();

$pdo       = getPDO();
$adminUser = admin_user();
$userId    = (int)$adminUser['id'];

$alerts = [];
$errors = [];

$old = [
    'first_name' => $adminUser['first_name'],
    'last_name'  => $adminUser['last_name'],
    'email'      => $adminUser['email'],
    'phone'      => $adminUser['phone'],
    'avatar'     => $adminUser['avatar'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        csrf_fail();
    }

    $action = $_POST['action'] ?? 'profile';

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($current === '') {
            $errors[] = t('account.current_password_required', 'Current password is required.');
        }
        if (mb_strlen($new) < 8) {
            $errors[] = t('account.password_min_length', 'Password must be at least 8 characters.');
        }
        if ($new !== $confirm) {
            $errors[] = t('account.password_mismatch', 'New passwords do not match.');
        }
        if ($new === $current) {
            $errors[] = t('account.password_same', 'New password must be different from the current password.');
        }

        if (!$errors) {
            if (!password_verify($current, $adminUser['password_hash'])) {
                $errors[] = t('account.current_password_wrong', 'Current password is incorrect.');
            } else {
                $hash = password_hash($new, PASSWORD_BCRYPT);
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);
                $alerts[] = ['type' => 'success', 'msg' => t('account.password_updated', 'Password updated successfully.')];
                $adminUser = auth_user();
            }
        }
    } else {
        // Profile update
        $old = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name'  => trim($_POST['last_name'] ?? ''),
            'email'      => $adminUser['email'], // immutable
            'phone'      => trim($_POST['phone'] ?? ''),
            'avatar'     => trim($_POST['avatar'] ?? ''),
        ];

        if ($old['first_name'] === '') $errors[] = t('auth.first_name', 'First name is required.');
        if ($old['last_name'] === '')  $errors[] = t('auth.last_name', 'Last name is required.');

        // Avatar file upload
        $uploadedAvatar = null;
        if (!empty($_FILES['avatar_file']['name']) && $_FILES['avatar_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadedAvatar = CrudService::handleUpload($_FILES['avatar_file'], 'avatars', 2 * 1024 * 1024);
            if ($uploadedAvatar === null) {
                $errors[] = t('account.avatar_invalid_type', 'Invalid image type or too large.');
            } else {
                // Clean up previous uploaded avatar
                $prev = (string)($adminUser['avatar'] ?? '');
                if ($prev !== '' && strpos($prev, 'uploads/avatars/') === 0 && is_file(__DIR__ . '/../' . $prev)) {
                    @unlink(__DIR__ . '/../' . $prev);
                }
            }
        }

        if (!$errors) {
            $finalAvatar = $uploadedAvatar !== null ? $uploadedAvatar : ($old['avatar'] !== '' ? $old['avatar'] : null);
            $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, phone = ?, avatar = ? WHERE id = ?')
                ->execute([$old['first_name'], $old['last_name'], $old['phone'] !== '' ? $old['phone'] : null, $finalAvatar, $userId]);

            // sync session
            $_SESSION['auth_name'] = $old['first_name'] . ' ' . $old['last_name'];
            $alerts[] = ['type' => 'success', 'msg' => t('account.profile_updated', 'Profile updated successfully.')];
            $adminUser = auth_user();
        }
    }
}

$account_alerts     = array_merge(array_map(fn($e) => ['type' => 'error', 'msg' => $e], $errors), $alerts);
$admin_page_title   = t('admin.profile') . ' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = t('admin.profile');
$admin_active       = 'profile';
?>
<?php require __DIR__ . '/components/header.php'; ?>
<div class="admin-app">
  <?php require __DIR__ . '/components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/components/alert.php'; ?>

      <div class="stats-grid" style="grid-template-columns:1fr 1fr;align-items:start;">
        <div class="card">
          <h2><?= htmlspecialchars(t('admin.profile')) ?></h2>
          <p class="sub"><?= htmlspecialchars(t('admin.profile_sub', 'Update your name, phone and avatar.')) ?></p>
          <form method="post" action="profile.php" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="profile">
            <div class="grid-2">
              <div class="field">
                <label><?= htmlspecialchars(t('auth.first_name', 'First name')) ?> *</label>
                <input type="text" name="first_name" value="<?= htmlspecialchars($old['first_name']) ?>" required>
              </div>
              <div class="field">
                <label><?= htmlspecialchars(t('auth.last_name', 'Last name')) ?> *</label>
                <input type="text" name="last_name" value="<?= htmlspecialchars($old['last_name']) ?>" required>
              </div>
            </div>
            <div class="field">
              <label><?= htmlspecialchars(t('admin.email')) ?> *</label>
              <input type="email" value="<?= htmlspecialchars($old['email']) ?>" disabled>
              <div class="hint"><?= htmlspecialchars(t('account.email_immutable', 'Email cannot be changed.')) ?></div>
            </div>
            <div class="field">
              <label><?= htmlspecialchars(t('admin.phone')) ?></label>
              <input type="tel" name="phone" value="<?= htmlspecialchars($old['phone']) ?>">
            </div>
            <div class="field">
              <label><?= htmlspecialchars(t('admin.avatar')) ?></label>
              <img class="preview-img" id="avatarPreview" src="<?= htmlspecialchars(auth_avatar()) ?>" alt="">
              <input type="file" name="avatar_file" accept="image/jpeg,image/png,image/webp" data-preview-target="#avatarPreview" style="margin-top:8px;">
              <div class="hint">JPG, PNG, WEBP. Max 2MB.</div>
            </div>
            <div class="field">
              <label><?= htmlspecialchars(t('admin.avatar_url', 'Or image URL')) ?></label>
              <input type="url" name="avatar" value="<?= htmlspecialchars($old['avatar']) ?>" placeholder="https://...">
            </div>
            <div class="form-actions">
              <button type="submit" class="btn"><?= htmlspecialchars(t('admin.save')) ?></button>
            </div>
          </form>
        </div>

        <div class="card">
          <h2><?= htmlspecialchars(t('admin.change_password')) ?></h2>
          <p class="sub"><?= htmlspecialchars(t('admin.password_sub', 'Use a strong password you do not use elsewhere.')) ?></p>
          <form method="post" action="profile.php" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">
            <div class="field">
              <label><?= htmlspecialchars(t('admin.current_password')) ?> *</label>
              <input type="password" name="current_password" required autocomplete="current-password">
            </div>
            <div class="field">
              <label><?= htmlspecialchars(t('admin.new_password')) ?> *</label>
              <input type="password" name="new_password" required autocomplete="new-password">
              <div class="hint"><?= htmlspecialchars(t('auth.password_min', 'At least 8 characters.')) ?></div>
            </div>
            <div class="field">
              <label><?= htmlspecialchars(t('admin.confirm_password')) ?> *</label>
              <input type="password" name="confirm_password" required autocomplete="new-password">
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-secondary"><?= htmlspecialchars(t('admin.change_password')) ?></button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>
<?php require __DIR__ . '/components/footer.php'; ?>

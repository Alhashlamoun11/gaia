<?php
/**
 * admin/users/edit.php
 * ------------------------------------------------------------
 * Edit a user's name, phone, country, preferred language,
 * role, and status. Email is immutable.
 * Cannot change one's OWN role/status (self-lockout protection).
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo       = getPDO();
$adminUser = admin_user();
$meId      = (int)$adminUser['id'];
$editing = $id > 0;
$user    = null;

if ($editing) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        $admin_page_title = t('admin.not_found', 'Not Found') . ' — GAIA TOURS &amp; TRAVEL';
        $admin_topbar_title = t('admin.not_found', 'Not Found');
        $admin_active       = 'users';
        require __DIR__ . '/../components/header.php';
        echo '<div class="admin-app"><div class="admin-main"><div class="admin-content"><div class="card"><div class="muted" style="text-align:center;padding:40px;">' . htmlspecialchars(t('admin.not_found_text', 'User not found.')) . '</div></div></div></div></div>';
        require __DIR__ . '/../components/footer.php';
        exit;
    }

    if (Auth::role() !== 'super_admin') {
        $targetRoleStmt = $pdo->prepare('SELECT slug FROM roles WHERE id = ?');
        $targetRoleStmt->execute([$user['role_id']]);
        $targetRole = $targetRoleStmt->fetchColumn();
        if ($targetRole !== 'customer') {
            http_response_code(403);
            echo "403 Forbidden - You can only edit customers.";
            exit;
        }
    }
} else {
    $user = [
        'id' => 0,
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        'phone' => '',
        'country' => '',
        'preferred_language' => 'en',
        'role_id' => 3,
        'status' => 'active'
    ];
    $r = $pdo->query("SELECT id FROM roles WHERE slug = 'customer' LIMIT 1")->fetch();
    if ($r) $user['role_id'] = (int)$r['id'];
}

$isSelf = ($id === $meId);

if (Auth::role() === 'super_admin') {
    $roles = $pdo->query('SELECT id, name, slug FROM roles ORDER BY id')->fetchAll();
} else {
    $roles = $pdo->query("SELECT id, name, slug FROM roles WHERE slug = 'customer' ORDER BY id")->fetchAll();
}

$alerts = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        csrf_fail();
    }

    $first = trim($_POST['first_name'] ?? '');
    $last  = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $lang  = in_array($_POST['preferred_language'] ?? '', ['en', 'ar'], true) ? $_POST['preferred_language'] : 'en';
    $roleId = (int)($_POST['role_id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive', 'suspended'], true) ? $_POST['status'] : 'active';

    if ($first === '') $errors[] = t('auth.first_name', 'First name is required.');
    if ($last === '')  $errors[] = t('auth.last_name', 'Last name is required.');
    
    $roleExists = false;
    foreach ($roles as $r) { if ((int)$r['id'] === $roleId) { $roleExists = true; break; } }
    if (!$roleExists) $errors[] = 'Invalid role.';

    if ($isSelf && ($roleId !== (int)$user['role_id'] || $status !== $user['status'])) {
        $errors[] = t('admin.self_lockout', 'You cannot change your own role or status.');
    }

    $email = '';
    $password = '';
    if (!$editing) {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = t('auth.email_invalid', 'A valid email is required.');
        } else {
            $checkEmail = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
            $checkEmail->execute([$email]);
            if ((int)$checkEmail->fetchColumn() > 0) {
                $errors[] = t('auth.email_taken', 'Email is already taken.');
            }
        }
        if (strlen($password) < 6) {
            $errors[] = t('auth.password_min', 'Password must be at least 6 characters.');
        }
    }

    if (!$errors) {
        if ($editing) {
            $pdo->prepare('UPDATE users SET first_name=?, last_name=?, phone=?, country=?, preferred_language=?, role_id=?, status=? WHERE id=?')
                ->execute([$first, $last, $phone !== '' ? $phone : null, $country !== '' ? $country : null, $lang, $roleId, $status, $id]);
            $alerts[] = ['type' => 'success', 'msg' => t('admin.user_updated', 'User updated successfully.')];
            // reload
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $user = $stmt->fetch();
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                'INSERT INTO users (first_name, last_name, email, phone, country, preferred_language, password_hash, role_id, status, email_verified_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$first, $last, $email, $phone !== '' ? $phone : null, $country !== '' ? $country : null, $lang, $hash, $roleId, $status]);
            $newId = (int)$pdo->lastInsertId();
            admin_flash(t('admin.user_created', 'User created successfully.'), 'success');
            header('Location: edit.php?id=' . $newId);
            exit;
        }
    }
}

$account_alerts     = array_merge(array_map(fn($e) => ['type' => 'error', 'msg' => $e], $errors), $alerts);
$admin_page_title   = ($editing ? t('admin.edit') : t('admin.add_user', 'Add User')) . ' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = $editing ? t('admin.edit') : t('admin.add_user', 'Add User');
$admin_active       = 'users';
?>
<?php require __DIR__ . '/../components/header.php'; ?>
<div class="admin-app">
  <?php require __DIR__ . '/../components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/../components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/../components/alert.php'; ?>

      <div class="card">
        <div class="card-head">
          <h3><i class="fa-solid fa-pen"></i> <?= $editing ? (htmlspecialchars(t('admin.edit')) . ' — ' . htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name']))) : htmlspecialchars(t('admin.add_user', 'Add User')) ?></h3>
          <?php if ($editing): ?>
            <a class="btn btn-ghost btn-sm" href="view.php?id=<?= (int)$id ?>"><?= htmlspecialchars(t('admin.view')) ?></a>
          <?php endif; ?>
        </div>

        <form method="post" action="edit.php?id=<?= (int)$id ?>" novalidate>
          <?= csrf_field() ?>
          <div class="grid-2">
            <div class="field">
              <label><?= htmlspecialchars(t('admin.first_name', 'First name')) ?> *</label>
              <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
            </div>
            <div class="field">
              <label><?= htmlspecialchars(t('admin.last_name', 'Last name')) ?> *</label>
              <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
            </div>
          </div>
          <div class="field">
            <label><?= htmlspecialchars(t('admin.email')) ?> *</label>
            <?php if ($editing): ?>
              <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
            <?php else: ?>
              <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
            <?php endif; ?>
          </div>
          <?php if (!$editing): ?>
            <div class="field">
              <label><?= htmlspecialchars(t('admin.password', 'Password')) ?> *</label>
              <input type="password" name="password" required>
            </div>
          <?php endif; ?>
          <div class="grid-2">
            <div class="field"><label><?= htmlspecialchars(t('admin.phone')) ?></label><input type="tel" name="phone" value="<?= htmlspecialchars($user['phone']) ?>"></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.country', 'Country')) ?></label><input type="text" name="country" value="<?= htmlspecialchars($user['country']) ?>"></div>
          </div>
          <div class="grid-2">
            <div class="field">
              <label><?= htmlspecialchars(t('admin.language', 'Preferred Language')) ?></label>
              <select name="preferred_language">
                <option value="en" <?= ($user['preferred_language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                <option value="ar" <?= ($user['preferred_language'] ?? 'en') === 'ar' ? 'selected' : '' ?>>العربية</option>
              </select>
            </div>
            <div class="field">
              <label><?= htmlspecialchars(t('admin.role')) ?></label>
              <select name="role_id" <?= $isSelf ? 'disabled' : '' ?>>
                <?php foreach ($roles as $r): ?>
                  <option value="<?= (int)$r['id'] ?>" <?= (int)$user['role_id'] === (int)$r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if ($isSelf): ?>
                <input type="hidden" name="role_id" value="<?= (int)$user['role_id'] ?>">
              <?php endif; ?>
            </div>
          </div>
          <div class="field">
            <label><?= htmlspecialchars(t('admin.status')) ?></label>
            <select name="status" <?= $isSelf ? 'disabled' : '' ?>>
              <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>><?= htmlspecialchars(t('admin.active')) ?></option>
              <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>><?= htmlspecialchars(t('admin.inactive')) ?></option>
              <option value="suspended" <?= $user['status'] === 'suspended' ? 'selected' : '' ?>><?= htmlspecialchars(t('admin.suspended', 'Suspended')) ?></option>
            </select>
            <?php if ($isSelf): ?>
              <input type="hidden" name="status" value="<?= htmlspecialchars($user['status']) ?>">
            <?php endif; ?>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn"><?= htmlspecialchars(t('admin.save')) ?></button>
            <a class="btn btn-ghost" href="index.php"><?= htmlspecialchars(t('admin.back', 'Back')) ?></a>
          </div>
        </form>
      </div>

    </div>
  </div>
</div>
<?php require __DIR__ . '/../components/footer.php'; ?>

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
$id        = (int)($_GET['id'] ?? 0);

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

$isSelf = ($id === $meId);

$roles = $pdo->query('SELECT id, name, slug FROM roles ORDER BY id')->fetchAll();

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

    if (!$errors) {
        $pdo->prepare('UPDATE users SET first_name=?, last_name=?, phone=?, country=?, preferred_language=?, role_id=?, status=? WHERE id=?')
            ->execute([$first, $last, $phone !== '' ? $phone : null, $country !== '' ? $country : null, $lang, $roleId, $status, $id]);
        $alerts[] = ['type' => 'success', 'msg' => t('admin.user_updated', 'User updated successfully.')];
        // reload
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
    }
}

$account_alerts     = array_merge(array_map(fn($e) => ['type' => 'error', 'msg' => $e], $errors), $alerts);
$admin_page_title   = t('admin.edit') . ' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = t('admin.edit');
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
          <h3><i class="fa-solid fa-pen"></i> <?= htmlspecialchars(t('admin.edit')) ?> — <?= htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) ?></h3>
          <a class="btn btn-ghost btn-sm" href="view.php?id=<?= (int)$id ?>"><?= htmlspecialchars(t('admin.view')) ?></a>
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
            <label><?= htmlspecialchars(t('admin.email')) ?></label>
            <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
          </div>
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
              <?php if (!$isSelf && (int)$user['role_id'] !== 1): ?>
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
            <?php if (!$isSelf && $user['status'] !== 'active'): ?>
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

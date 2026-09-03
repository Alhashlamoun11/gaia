<?php
/**
 * admin/roles/form.php
 * Create / Edit Staff Role and Granular Permissions Matrix
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$adminMe = admin_user();

if (($adminMe['role_slug'] ?? '') !== 'super_admin') {
    admin_flash(t('admin.access_denied', 'Access Denied. Only Super Administrators can manage roles & permissions.'), 'error');
    header('Location: ' . admin_url('index.php'));
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$role = null;

if ($editing) {
    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$role) {
        admin_flash('Role not found.', 'error');
        header('Location: ' . admin_url('roles/index.php'));
        exit;
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();

    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $selectedPermissions = $_POST['permissions'] ?? [];
    if (!is_array($selectedPermissions)) $selectedPermissions = [];

    if ($name === '') {
        $errors[] = 'Role name is required.';
    }
    if ($slug === '') {
        $slug = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', $name));
    }

    // Check duplicate slug
    $check = $pdo->prepare("SELECT id FROM roles WHERE slug = ? AND id != ?");
    $check->execute([$slug, $id]);
    if ($check->fetch()) {
        $errors[] = 'A role with this slug already exists.';
    }

    if (!$errors) {
        if ($editing) {
            // Update role info
            $pdo->prepare("UPDATE roles SET name = ?, slug = ? WHERE id = ?")->execute([$name, $slug, $id]);
            admin_flash('Role updated successfully.', 'success');
        } else {
            // Insert new role
            $pdo->prepare("INSERT INTO roles (name, slug) VALUES (?, ?)")->execute([$name, $slug]);
            $id = (int)$pdo->lastInsertId();
            $editing = true;
            admin_flash('Role created successfully.', 'success');
        }

        // Sync permissions
        $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$id]);
        if (!empty($selectedPermissions)) {
            $ins = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($selectedPermissions as $pid) {
                $pid = (int)$pid;
                if ($pid > 0) {
                    $ins->execute([$id, $pid]);
                }
            }
        }

        header('Location: ' . admin_url('roles/form.php?id=' . $id));
        exit;
    }
}

// Fetch all available permissions in the system
$allPermissions = $pdo->query("SELECT * FROM permissions ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// If permissions table is small/empty, ensure standard comprehensive permissions exist
if (count($allPermissions) < 5) {
    $defaults = [
        ['Manage Bookings', 'manage_bookings'],
        ['Manage Hotels & Rooms', 'manage_hotels'],
        ['Manage Tours & Itineraries', 'manage_tours'],
        ['Manage Transfers & Fleet', 'manage_transfers'],
        ['Manage Users & Accounts', 'manage_users'],
        ['Manage Reviews & Ratings', 'manage_reviews'],
        ['Manage Invoices & Billing', 'manage_invoices'],
        ['Manage Content & CMS', 'manage_content'],
        ['Manage Site Settings', 'manage_settings'],
        ['Broadcast Notifications', 'broadcast_notifications'],
    ];
    $pIns = $pdo->prepare("INSERT IGNORE INTO permissions (name, slug) VALUES (?, ?)");
    foreach ($defaults as $def) {
        $pIns->execute([$def[0], $def[1]]);
    }
    $allPermissions = $pdo->query("SELECT * FROM permissions ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch currently granted permission IDs for this role
$currentPermissions = [];
if ($editing) {
    $curStmt = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
    $curStmt->execute([$id]);
    $currentPermissions = $curStmt->fetchAll(PDO::FETCH_COLUMN);
}

$account_alerts = array_map(fn($e) => ['type'=>'error','msg'=>$e], $errors);
if (admin_flash_peek()) $account_alerts[] = admin_flash();

$admin_page_title = ($editing ? 'Edit Role' : 'Create Role') . ' — GAIA Admin';
$admin_topbar_title = 'Roles & Permissions';
$admin_active = 'roles';
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
          <h3>
            <i class="fa-solid fa-shield-halved"></i> 
            <?= $editing ? 'Edit Staff Role: ' . htmlspecialchars($role['name']) : 'Create New Staff Role' ?>
          </h3>
          <a class="btn btn-ghost btn-sm" href="index.php"><i class="fa-solid fa-arrow-left"></i> Back to Roles</a>
        </div>

        <form method="post" action="form.php<?= $editing ? '?id=' . (int)$id : '' ?>">
          <?= csrf_field() ?>

          <div class="grid-2">
            <div class="field">
              <label>Role Name <span style="color:#b3261e">*</span></label>
              <input type="text" name="name" value="<?= htmlspecialchars((string)($role['name'] ?? ($_POST['name'] ?? ''))) ?>" required placeholder="e.g. Operations Coordinator">
            </div>
            <div class="field">
              <label>Role Slug (System Key) <span style="color:#b3261e">*</span></label>
              <input type="text" name="slug" value="<?= htmlspecialchars((string)($role['slug'] ?? ($_POST['slug'] ?? ''))) ?>" placeholder="e.g. ops_coordinator">
              <small class="hint">Unique identifier used in backend permission guards.</small>
            </div>
          </div>

          <div style="margin-top:24px;">
            <h4 style="margin:0 0 8px; font-size:16px; color:var(--navy);"><i class="fa-solid fa-key" style="color:var(--teal);"></i> Assign Module Permissions</h4>
            <p class="muted" style="font-size:13px; margin:0 0 16px;">Check the specific capabilities this role is authorized to perform:</p>

            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:12px; background:#fbfaf7; padding:18px; border:1px solid var(--line); border-radius:12px;">
              <?php foreach ($allPermissions as $perm): ?>
                <?php $checked = in_array((int)$perm['id'], array_map('intval', $currentPermissions), true) || (!empty($_POST['permissions']) && in_array((int)$perm['id'], array_map('intval', $_POST['permissions']), true)); ?>
                <label style="display:flex; align-items:flex-start; gap:10px; padding:10px 12px; background:#fff; border:1px solid var(--line); border-radius:8px; cursor:pointer;">
                  <input type="checkbox" name="permissions[]" value="<?= (int)$perm['id'] ?>" <?= $checked ? 'checked' : '' ?> style="margin-top:3px; cursor:pointer;">
                  <div>
                    <strong style="font-size:13.5px; color:var(--ink); display:block;"><?= htmlspecialchars($perm['name']) ?></strong>
                    <code style="font-size:11px; color:var(--muted);"><?= htmlspecialchars($perm['slug']) ?></code>
                  </div>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="form-actions" style="margin-top:24px; display:flex; gap:10px;">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= $editing ? 'Save Changes' : 'Create Role' ?></button>
            <a class="btn btn-ghost" href="index.php">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../components/footer.php'; ?>

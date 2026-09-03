<?php
/**
 * admin/roles/index.php
 * Staff Roles & Granular Permissions (RBAC) Management
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$adminMe = admin_user();

// Super admin guard
if (($adminMe['role_slug'] ?? '') !== 'super_admin') {
    admin_flash(t('admin.access_denied', 'Access Denied. Only Super Administrators can manage roles & permissions.'), 'error');
    header('Location: ' . admin_url('index.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0 && $action === 'delete') {
        // Prevent deleting core system roles
        $role = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $role->execute([$id]);
        $r = $role->fetch();

        if ($r && in_array($r['slug'], ['super_admin', 'hotel_manager', 'customer'], true)) {
            admin_flash('Cannot delete default system role (' . htmlspecialchars($r['name']) . ').', 'error');
        } else {
            $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM roles WHERE id = ?")->execute([$id]);
            admin_flash('Role deleted successfully.', 'success');
        }
    }
    header('Location: ' . admin_url('roles/index.php'));
    exit;
}

// Fetch all roles with assigned user count and granted permissions
$rolesStmt = $pdo->query("
    SELECT r.*, 
           COUNT(DISTINCT u.id) AS user_count,
           GROUP_CONCAT(p.name ORDER BY p.name ASC SEPARATOR '||') AS permission_names,
           COUNT(DISTINCT rp.permission_id) AS permission_count
    FROM roles r
    LEFT JOIN users u ON u.role_id = r.id
    LEFT JOIN role_permissions rp ON rp.role_id = r.id
    LEFT JOIN permissions p ON p.id = rp.permission_id
    GROUP BY r.id
    ORDER BY r.id ASC
");
$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

$admin_page_title = 'Roles & Permissions — GAIA Admin';
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
          <div>
            <h3><i class="fa-solid fa-shield-halved"></i> Staff Roles &amp; Permissions (RBAC)</h3>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">Manage access levels, staff responsibilities, and granular system privileges.</p>
          </div>
          <a class="btn btn-sm btn-primary" href="form.php"><i class="fa-solid fa-plus"></i> Add New Role</a>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Role Name</th>
                <th>System Key (Slug)</th>
                <th>Active Users</th>
                <th>Assigned Permissions</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($roles as $r): ?>
                <tr>
                  <td>
                    <strong style="color:var(--navy); font-size:14px;"><?= htmlspecialchars($r['name']) ?></strong>
                    <?php if (in_array($r['slug'], ['super_admin', 'hotel_manager', 'customer'], true)): ?>
                      <span class="badge" style="background:#fef3c7; color:#92400e; font-size:10.5px; margin-left:4px;">System</span>
                    <?php endif; ?>
                  </td>
                  <td><code style="background:#f1f5f9; padding:3px 8px; border-radius:4px; font-size:12px;"><?= htmlspecialchars($r['slug']) ?></code></td>
                  <td>
                    <span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:700;">
                      <i class="fa-solid fa-users" style="margin-right:4px;"></i> <?= (int)$r['user_count'] ?> Users
                    </span>
                  </td>
                  <td>
                    <?php if ($r['slug'] === 'super_admin'): ?>
                      <span class="badge badge-active" style="background:#dcfce7; color:#15803d; font-weight:700;">
                        <i class="fa-solid fa-check-double"></i> Full Super Admin Access
                      </span>
                    <?php elseif (!empty($r['permission_names'])): ?>
                      <div style="display:flex; flex-wrap:wrap; gap:4px; max-width:320px;">
                        <?php foreach (explode('||', $r['permission_names']) as $pname): ?>
                          <span class="badge" style="background:#f4efe6; color:var(--ink); font-size:11px;"><?= htmlspecialchars($pname) ?></span>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <span class="muted" style="font-size:12px;">No permissions assigned</span>
                    <?php endif; ?>
                  </td>
                  <td style="font-size:12px; color:var(--muted);"><?= htmlspecialchars(substr($r['created_at'] ?? '', 0, 10)) ?></td>
                  <td>
                    <div class="actions">
                      <a class="icon-btn" href="form.php?id=<?= (int)$r['id'] ?>" title="Edit Permissions"><i class="fa-solid fa-pen"></i></a>
                      <?php if (!in_array($r['slug'], ['super_admin', 'hotel_manager', 'customer'], true)): ?>
                        <form method="post" action="index.php" style="display:inline;"><?= csrf_field() ?>
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                          <button class="icon-btn danger" type="submit" onclick="return confirm('Are you sure you want to delete this staff role?')" title="Delete"><i class="fa-solid fa-trash"></i></button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../components/footer.php'; ?>

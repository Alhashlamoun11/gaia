<?php
/**
 * admin/users/index.php
 * ------------------------------------------------------------
 * User management (CRUD): list, view, edit, deactivate/activate,
 * delete. Search, status filter, pagination, sorting.
 * Cannot delete/demote one's own super_admin account.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo       = getPDO();
$adminUser = admin_user();
$meId      = (int)$adminUser['id'];

// ---- Handle actions (POST) ----
$alert = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        csrf_fail();
    }
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
if ($action === 'toggle' && $id > 0 && $id !== $meId) {
        $st = $pdo->prepare('SELECT status FROM users WHERE id = ?');
        $st->execute([$id]);
        $cur = (string)$st->fetchColumn();
        $new = $cur === 'active' ? 'inactive' : 'active';
        $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$new, $id]);
        Auth::logAudit('admin_user_status_toggled');
        admin_flash(t('admin.user_status_updated', 'User status updated.'), 'success');
        header('Location: ' . admin_url('users/index.php'));
        exit;
    }
    if ($action === 'delete' && $id > 0 && $id !== $meId) {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        Auth::logAudit('admin_user_deleted');
        admin_flash(t('admin.user_deleted', 'User deleted.'), 'success');
        header('Location: ' . admin_url('users/index.php'));
        exit;
    }
}

// ---- Query params ----
$pg   = AdminQuery::pagination(15);
$q    = AdminQuery::search();
$s    = AdminQuery::sort(['id','first_name','email','status','created_at'], 'created_at');
$st   = AdminQuery::statusFilter(['active','inactive','suspended']);

$where  = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = "(first_name LIKE :q OR last_name LIKE :q OR email LIKE :q OR phone LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
if ($st !== '') {
    $where[] = 'status = :st';
    $params[':st'] = $st;
}
if (Auth::role() !== 'super_admin') {
    $where[] = "role_id = (SELECT id FROM roles WHERE slug = 'customer')";
}
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $pg['per_page']));
if ($pg['page'] > $pages) {
    $pg['page'] = $pages;
    $pg['offset'] = ($pg['page'] - 1) * $pg['per_page'];
}

$sql = "SELECT id, first_name, last_name, email, phone, role_id, status, created_at
        FROM users WHERE $whereSql
        ORDER BY {$s['col']} {$s['dir']}
        LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$roles = [];
foreach ($pdo->query('SELECT id, slug, name FROM roles')->fetchAll() as $r) {
    $roles[(int)$r['id']] = ['slug' => $r['slug'], 'name' => $r['name']];
}

$url_builder = function (array $ov = []) {
    return '?' . http_build_query(array_merge($_GET, $ov));
};

$account_alerts     = admin_flash_peek() ? [admin_flash()] : [];
$admin_page_title   = t('admin.users') . ' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = t('admin.users');
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
          <h3><i class="fa-solid fa-users"></i> <?= htmlspecialchars(t('admin.users')) ?></h3>
          <a class="btn btn-sm" href="edit.php"><?= htmlspecialchars(t('admin.add_user', 'Add User')) ?></a>
        </div>

        <form method="get" action="index.php" class="toolbar">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="<?= htmlspecialchars(t('admin.search')) ?>...">
          </div>
          <select name="status">
            <option value=""><?= htmlspecialchars(t('admin.all_statuses', 'All Statuses')) ?></option>
            <option value="active" <?= $st === 'active' ? 'selected' : '' ?>><?= htmlspecialchars(t('admin.active')) ?></option>
            <option value="inactive" <?= $st === 'inactive' ? 'selected' : '' ?>><?= htmlspecialchars(t('admin.inactive')) ?></option>
            <option value="suspended" <?= $st === 'suspended' ? 'selected' : '' ?>><?= htmlspecialchars(t('admin.suspended', 'Suspended')) ?></option>
          </select>
          <button type="submit" class="btn btn-sm"><?= htmlspecialchars(t('admin.apply', 'Apply')) ?></button>
          <a href="index.php" class="btn btn-ghost btn-sm"><?= htmlspecialchars(t('admin.reset', 'Reset')) ?></a>
        </form>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th><?= AdminQuery::sortLink('id', 'ID', ['id','first_name','email','status','created_at'], 'created_at') ?></th>
                <th><?= AdminQuery::sortLink('first_name', t('admin.name'), ['id','first_name','email','status','created_at'], 'created_at') ?></th>
                <th><?= htmlspecialchars(t('admin.email')) ?></th>
                <th><?= htmlspecialchars(t('admin.phone')) ?></th>
                <th><?= htmlspecialchars(t('admin.role')) ?></th>
                <th><?= AdminQuery::sortLink('status', t('admin.status'), ['id','first_name','email','status','created_at'], 'created_at') ?></th>
                <th><?= htmlspecialchars(t('admin.created')) ?></th>
                <th><?= htmlspecialchars(t('admin.actions')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr><td colspan="8"><div class="muted" style="text-align:center;padding:20px;"><?= htmlspecialchars(t('admin.no_records')) ?></div></td></tr>
              <?php else: foreach ($rows as $u): ?>
                <tr>
                  <td class="code">#<?= (int)$u['id'] ?></td>
                  <td class="code"><?= htmlspecialchars(trim($u['first_name'] . ' ' . $u['last_name'])) ?></td>
                  <td><?= htmlspecialchars($u['email']) ?></td>
                  <td><?= htmlspecialchars($u['phone']) ?></td>
                  <td><?= htmlspecialchars($roles[(int)$u['role_id']]['slug'] ?? 'customer') ?></td>
                  <td><span class="badge badge-<?= htmlspecialchars($u['status']) ?>"><?= htmlspecialchars($u['status']) ?></span></td>
                  <td><?= htmlspecialchars(date('Y-m-d', strtotime((string)$u['created_at']))) ?></td>
                  <td>
                    <div class="actions">
                      <a class="icon-btn" href="view.php?id=<?= (int)$u['id'] ?>"><i class="fa-solid fa-eye"></i></a>
                      <a class="icon-btn" href="edit.php?id=<?= (int)$u['id'] ?>"><i class="fa-solid fa-pen"></i></a>
                      <?php if ((int)$u['id'] !== $meId): ?>
                        <form method="post" action="index.php" style="display:inline;">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="toggle">
                          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                          <button type="submit" class="icon-btn" title="<?= htmlspecialchars($u['status'] === 'active' ? t('admin.deactivate') : t('admin.activate')) ?>">
                            <i class="fa-solid <?= $u['status'] === 'active' ? 'fa-ban' : 'fa-check' ?>"></i>
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <?php
          $page = $pg['page']; $pages = $pages; $total = $total; $offset = $pg['offset']; $per_page = $pg['per_page']; $url_builder = $url_builder;
          require __DIR__ . '/../components/pagination.php';
        ?>
      </div>

    </div>
  </div>
</div>
<?php require __DIR__ . '/../components/footer.php'; ?>

<?php
/**
 * admin/tours/destinations.php
 * ============================================================
 * Tour Destinations management (tour_destinations table).
 * Bilingual name/description, image, sort order, status.
 * ============================================================
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo   = getPDO();
$table = 'tour_destinations';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        if ($action === 'delete') {
            CrudService::delete($table, $id);
            admin_flash(t('admin.deleted','Deleted.'), 'success');
        } elseif ($action === 'toggle') {
            $st = $pdo->prepare("SELECT is_active FROM {$table} WHERE id=?");
            $st->execute([$id]);
            $rv = (int)$st->fetchColumn();
            CrudService::setFlag($table, $id, 'is_active', $rv ? 0 : 1);
            admin_flash(t('admin.status_updated','Status updated.'), 'success');
        }
        header('Location: ' . admin_url('tours/destinations.php')); exit;
    }
}

$pg = AdminQuery::pagination(20);
$q  = AdminQuery::search();

$where = ['1=1']; $params = [];
if ($q !== '') { $where[] = '(name LIKE :q OR slug LIKE :q OR country LIKE :q)'; $params[':q'] = '%'.$q.'%'; }
$wh = implode(' AND ', $where);

$total = (int)$pdo->query("SELECT COUNT(*) FROM {$table} WHERE $wh")->fetchColumn();
$pages = max(1, (int)ceil($total / $pg['per_page']));
if ($pg['page'] > $pages) { $pg['page']=$pages; $pg['offset']=($pg['page']-1)*$pg['per_page']; }

$stmt = $pdo->prepare("SELECT * FROM {$table} WHERE $wh ORDER BY sort_order ASC, id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$url_builder = fn($ov=[]) => '?' . http_build_query(array_merge($_GET, $ov));
$account_alerts = admin_flash_peek() ? [admin_flash()] : [];
$admin_page_title = t('admin.destinations').' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = t('admin.destinations');
$admin_active = 'tours';
$C = fn($v)=>htmlspecialchars((string)($v ?? ''));
?>
<?php require __DIR__.'/../components/header.php'; ?>
<div class="admin-app">
  <?php require __DIR__.'/../components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__.'/../components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__.'/../components/alert.php'; ?>
      <div class="card">
        <div class="card-head">
          <h3><i class="fa-solid fa-location-dot"></i> <?= $C(t('admin.destinations')) ?></h3>
          <a class="btn btn-sm" href="destination-form.php"><?= $C(t('admin.add_new')) ?></a>
        </div>
        <form method="get" action="destinations.php" class="toolbar">
          <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="text" name="q" value="<?= $C($q) ?>" placeholder="<?= $C(t('admin.search')) ?>..."></div>
          <button type="submit" class="btn btn-sm"><?= $C(t('admin.apply','Apply')) ?></button>
          <a href="destinations.php" class="btn btn-ghost btn-sm"><?= $C(t('admin.reset','Reset')) ?></a>
        </form>
        <div class="table-wrap">
          <table>
            <thead><tr>
              <th>ID</th><th><?= $C(t('admin.name')) ?></th><th><?= $C(t('admin.slug')) ?></th><th><?= $C(t('admin.country')) ?></th>
              <th><?= $C(t('admin.sort_order')) ?></th><th><?= $C(t('admin.status')) ?></th><th><?= $C(t('admin.actions')) ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="7"><div class="muted" style="text-align:center;padding:20px;"><?= $C(t('admin.no_records')) ?></div></td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td class="code"><?= $C(lang_value($r, 'name', $r['name'] ?? '')) ?></td>
                <td><?= $C($r['slug'] ?? '') ?></td>
                <td><?= $C($r['country'] ?? '') ?></td>
                <td><?= (int)($r['sort_order'] ?? 0) ?></td>
                <td><span class="badge badge-<?= (int)($r['is_active']??0)?'active':'inactive' ?>"><?= (int)($r['is_active']??0)?$C(t('admin.active')):$C(t('admin.inactive')) ?></span></td>
                <td>
                  <div class="actions">
                    <a class="icon-btn" href="destination-form.php?id=<?= (int)$r['id'] ?>"><i class="fa-solid fa-pen"></i></a>
                    <form method="post" action="destinations.php" style="display:inline;"><?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="icon-btn" type="submit"><i class="fa-solid <?= (int)($r['is_active']??0)?'fa-ban':'fa-check' ?>"></i></button>
                    </form>
                    <form method="post" action="destinations.php" style="display:inline;"><?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="icon-btn danger" type="submit" onclick="return confirm('<?= $C(t('admin.delete_confirm')) ?>')"><i class="fa-solid fa-trash"></i></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <?php $page=$pg['page'];$pages=$pages;$total=$total;$offset=$pg['offset'];$per_page=$pg['per_page'];$url_builder=$url_builder; require __DIR__.'/../components/pagination.php'; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__.'/../components/footer.php'; ?>
</content>


<?php
/**
 * admin/menu/index.php
 * Navigation Management — manage header/footer menu items.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $action = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        if ($action === 'toggle') {
            $st = $pdo->prepare('SELECT is_active FROM menu_items WHERE id=?'); $st->execute([$id]);
            $cur = (int)$st->fetchColumn();
            $pdo->prepare('UPDATE menu_items SET is_active=? WHERE id=?')->execute([$cur?0:1,$id]);
            admin_flash(t('admin.status_updated','Status updated.'), 'success');
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM menu_items WHERE id=?')->execute([$id]);
            admin_flash(t('admin.deleted','Deleted.'), 'success');
        }
        header('Location: ' . admin_url('menu/index.php')); exit;
    }
}

$menu = $_GET['menu'] ?? 'header';
if (!in_array($menu, ['header','footer'], true)) $menu = 'header';

$pg = AdminQuery::pagination(20);
$q  = AdminQuery::search();
$s  = AdminQuery::sort(['id','label','sort_order'], 'sort_order');

$where = ['1=1']; $params = [':menu' => $menu];
$where[] = 'menu = :menu';
if ($q !== '') { $where[] = '(label LIKE :q OR url LIKE :q OR nav_key LIKE :q)'; $params[':q'] = '%'.$q.'%'; }
$wh = implode(' AND ', $where);

$c = $pdo->prepare("SELECT COUNT(*) FROM menu_items WHERE $wh"); $c->execute($params);
$total = (int)$c->fetchColumn();
$pages = max(1, (int)ceil($total / $pg['per_page']));
if ($pg['page'] > $pages) { $pg['page']=$pages; $pg['offset']=($pg['page']-1)*$pg['per_page']; }

$stmt = $pdo->prepare("SELECT * FROM menu_items WHERE $wh ORDER BY {$s['col']} {$s['dir']} LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$url_builder = fn($ov=[]) => '?' . http_build_query(array_merge($_GET, $ov));
$account_alerts = admin_flash_peek() ? [admin_flash()] : [];
$admin_page_title = t('admin.menu').' — GAIA TOURS & TRAVEL';
$admin_topbar_title = t('admin.menu');
$admin_active = 'menu';
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
          <h3><i class="fa-solid fa-bars"></i> <?= $C(t('admin.menu')) ?></h3>
          <a class="btn btn-sm" href="form.php?menu=<?= $C($menu) ?>"><?= $C(t('admin.add_new')) ?></a>
        </div>
        <div class="tabs">
          <a class="tab <?= $menu==='header'?'active':'' ?>" href="?menu=header"><?= $C(t('admin.header','Header')) ?></a>
          <a class="tab <?= $menu==='footer'?'active':'' ?>" href="?menu=footer"><?= $C(t('admin.footer','Footer')) ?></a>
        </div>
        <form method="get" action="index.php" class="toolbar">
          <input type="hidden" name="menu" value="<?= $C($menu) ?>">
          <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="text" name="q" value="<?= $C($q) ?>" placeholder="<?= $C(t('admin.search')) ?>..."></div>
          <button type="submit" class="btn btn-sm"><?= $C(t('admin.apply','Apply')) ?></button>
          <a href="?menu=<?= $C($menu) ?>" class="btn btn-ghost btn-sm"><?= $C(t('admin.reset','Reset')) ?></a>
        </form>
        <div class="table-wrap">
          <table>
            <thead><tr>
              <th><?= $C(t('admin.label','Label')) ?></th>
              <th><?= $C(t('admin.url','URL')) ?></th>
              <th><?= $C(t('admin.parent','Parent')) ?></th>
              <th><?= $C(t('admin.sort_order')) ?></th>
              <th><?= $C(t('admin.status')) ?></th>
              <th><?= $C(t('admin.actions')) ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6"><div class="muted" style="text-align:center;padding:20px;"><?= $C(t('admin.no_records')) ?></div></td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <td class="code"><?= $C($r['label']) ?><?= (int)$r['is_premium']?' <span class="badge badge-active">VIP</span>':'' ?></td>
                <td class="truncate"><?= $C($r['url']) ?></td>
                <td><?= (int)$r['parent_id'] > 0 ? '#' . (int)$r['parent_id'] : '—' ?></td>
                <td><?= (int)$r['sort_order'] ?></td>
                <td><span class="badge badge-<?= (int)$r['is_active']?'active':'inactive' ?>"><?= (int)$r['is_active']?$C(t('admin.active')):$C(t('admin.inactive')) ?></span></td>
                <td>
                  <div class="actions">
                    <a class="icon-btn" href="form.php?menu=<?= $C($menu) ?>&id=<?= (int)$r['id'] ?>"><i class="fa-solid fa-pen"></i></a>
                    <form method="post" action="index.php" style="display:inline;"><?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="icon-btn" type="submit"><i class="fa-solid <?= (int)$r['is_active']?'fa-ban':'fa-check' ?>"></i></button>
                    </form>
                    <form method="post" action="index.php" style="display:inline;"><?= csrf_field() ?>
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
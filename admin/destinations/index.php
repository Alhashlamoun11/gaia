<?php
/**
 * admin/destinations/index.php
 * Destinations CRUD (name, slug, country, description, image, featured, SEO).
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $action = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        if ($action === 'toggle') {
            $st = $pdo->prepare('SELECT is_active FROM destinations WHERE id=?'); $st->execute([$id]);
            $cur = (int)$st->fetchColumn();
            $pdo->prepare('UPDATE destinations SET is_active=? WHERE id=?')->execute([$cur?0:1,$id]);
            admin_flash(t('admin.status_updated','Status updated.'), 'success');
        } elseif ($action === 'featured') {
            $pdo->prepare('UPDATE destinations SET featured=? WHERE id=?')->execute([(int)($_POST['value']??0), $id]);
            admin_flash(t('admin.status_updated','Status updated.'), 'success');
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM destinations WHERE id=?')->execute([$id]);
            admin_flash(t('admin.deleted','Deleted.'), 'success');
        }
        header('Location: ' . admin_url('destinations/index.php')); exit;
    }
}

$pg = AdminQuery::pagination(15);
$q  = AdminQuery::search();
$st = AdminQuery::statusFilter(['1','0'], '1');
$s  = AdminQuery::sort(['id','name','country','sort_order'], 'name');

$where = ['1=1']; $params = [];
if ($q !== '') { $where[] = '(name LIKE :q OR country LIKE :q OR slug LIKE :q)'; $params[':q'] = '%'.$q.'%'; }
if ($st !== '') { $where[] = 'is_active = :st'; $params[':st'] = (int)$st; }
$wh = implode(' AND ', $where);

$c = $pdo->prepare("SELECT COUNT(*) FROM destinations WHERE $wh"); $c->execute($params);
$total = (int)$c->fetchColumn();
$pages = max(1, (int)ceil($total / $pg['per_page']));
if ($pg['page'] > $pages) { $pg['page']=$pages; $pg['offset']=($pg['page']-1)*$pg['per_page']; }

$stmt = $pdo->prepare("SELECT * FROM destinations WHERE $wh ORDER BY {$s['col']} {$s['dir']} LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$url_builder = fn($ov=[]) => '?' . http_build_query(array_merge($_GET, $ov));
$account_alerts = admin_flash_peek() ? [admin_flash()] : [];
$admin_page_title = t('admin.destinations').' — GAIA TOURS & TRAVEL';
$admin_topbar_title = t('admin.destinations');
$admin_active = 'destinations';
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
          <a class="btn btn-sm" href="form.php"><?= $C(t('admin.add_new')) ?></a>
        </div>
        <form method="get" action="index.php" class="toolbar">
          <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="text" name="q" value="<?= $C($q) ?>" placeholder="<?= $C(t('admin.search')) ?>..."></div>
          <select name="status">
            <option value=""><?= $C(t('admin.all_statuses','All Statuses')) ?></option>
            <option value="1" <?=$st==='1'?'selected':''?>><?= $C(t('admin.active')) ?></option>
            <option value="0" <?=$st==='0'?'selected':''?>><?= $C(t('admin.inactive')) ?></option>
          </select>
          <button type="submit" class="btn btn-sm"><?= $C(t('admin.apply','Apply')) ?></button>
          <a href="index.php" class="btn btn-ghost btn-sm"><?= $C(t('admin.reset','Reset')) ?></a>
        </form>
        <div class="table-wrap">
          <table>
            <thead><tr>
              <th><?= $C(t('admin.image')) ?></th>
              <th><?= AdminQuery::sortLink('name', t('admin.name'), ['id','name','country','sort_order'], 'name') ?></th>
              <th><?= $C(t('admin.country')) ?></th>
              <th><?= $C(t('admin.featured')) ?></th>
              <th><?= $C(t('admin.status')) ?></th>
              <th><?= $C(t('admin.actions')) ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6"><div class="muted" style="text-align:center;padding:20px;"><?= $C(t('admin.no_records')) ?></div></td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <td><?php if(!empty($r['image_url'])): ?><img class="thumb" src="<?= $C('/' . ltrim($r['image_url'], '/')) ?>" alt="" style="height:38px;width:58px;object-fit:cover;border-radius:6px;"><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                <td class="code"><strong><?= $C($r['name']) ?></strong></td>
                <td><?= $C($r['country'] ?? '—') ?></td>
                <td><span class="badge badge-<?= (int)($r['featured'] ?? 0)?'active':'inactive' ?>"><?= (int)($r['featured'] ?? 0)?'★ Featured':'—' ?></span></td>
                <td><span class="badge badge-<?= (int)($r['is_active'] ?? 1)?'active':'inactive' ?>"><?= (int)($r['is_active'] ?? 1)?$C(t('admin.active')):$C(t('admin.inactive')) ?></span></td>
                <td>
                  <div class="actions">
                    <a class="icon-btn" href="form.php?id=<?= (int)$r['id'] ?>" title="Edit"><i class="fa-solid fa-pen"></i></a>
                    <form method="post" action="index.php" style="display:inline;"><?= csrf_field() ?>
                      <input type="hidden" name="action" value="featured"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="value" value="<?= (int)($r['featured'] ?? 0)?0:1 ?>">
                      <button class="icon-btn" type="submit" title="<?= $C(t('admin.featured')) ?>"><i class="<?= (int)($r['featured'] ?? 0)?'fa-solid fa-star':'fa-regular fa-star' ?>" style="color:<?= (int)($r['featured'] ?? 0)?'#f59e0b':'inherit' ?>;"></i></button>
                    </form>
                    <form method="post" action="index.php" style="display:inline;"><?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="icon-btn" type="submit" title="Toggle Status"><i class="fa-solid <?= (int)($r['is_active'] ?? 1)?'fa-ban':'fa-check' ?>"></i></button>
                    </form>
                    <form method="post" action="index.php" style="display:inline;"><?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="icon-btn danger" type="submit" onclick="return confirm('<?= $C(t('admin.delete_confirm')) ?>')" title="Delete"><i class="fa-solid fa-trash"></i></button>
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
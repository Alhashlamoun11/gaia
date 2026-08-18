<?php
/**
 * admin/transfers/index.php
 * Transfers management via tabs:
 *   routes | car_classes | transfer_services | locations | extras
 * Each tab lists its records; add/edit uses a shared form.php?tab=...
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();

$tabs = [
    'routes'             => ['table'=>'routes','title_key'=>'admin.routes'],
    'car_classes'        => ['table'=>'car_classes','title_key'=>'admin.car_classes'],
    'transfer_services'  => ['table'=>'transfer_services','title_key'=>'admin.transfer_services'],
    'locations'          => ['table'=>'transfers_locations','title_key'=>'admin.locations'],
    'extras'             => ['table'=>'transfer_extras','title_key'=>'admin.extras'],
];
$tab = $_GET['tab'] ?? 'routes';
if (!isset($tabs[$tab])) $tab = 'routes';
$cfg = $tabs[$tab];
$table = $cfg['table'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $action = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($id > 0 && $action === 'delete') {
        CrudService::delete($table, $id);
        admin_flash(t('admin.deleted','Deleted.'), 'success');
    }
    header('Location: ' . admin_url('transfers/index.php?tab=' . urlencode($tab))); exit;
}

$pg = AdminQuery::pagination(15);
$q  = AdminQuery::search();
$s  = AdminQuery::sort(['id','name','origin','created_at','sort_order'], 'id');

// Query columns differ per table. Build a generic safe column list.
$colMap = [
    'routes'            => ['id','origin','destination','base_price','distance_km','travel_time_min','is_active','created_at'],
    'car_classes'       => ['id','name','passenger_capacity','luggage_capacity','price_multiplier','is_active','created_at'],
    'transfer_services' => ['id','name','price','unit','is_active','sort_order','created_at'],
    'locations'         => ['id','name','type','country','is_active','sort_order','created_at'],
    'extras'            => ['id','key','name','price','unit','is_active','sort_order','created_at'],
];
$cols = $colMap[$tab];
$sortAllowed = array_keys(array_flip(array_merge(['id'], $s['col'] ? [$s['col']] : [])));
$s = AdminQuery::sort($cols, 'id');

$where = ['1=1']; $params = [];
if ($q !== '') {
    $like = "(`{$cols[1]}` LIKE :q OR `{$cols[1]}` LIKE :q)";
    // Routes use origin/destination; others use name/key.
    if ($tab === 'routes') $like = '(origin LIKE :q OR destination LIKE :q)';
    elseif ($tab === 'extras') $like = '(`key` LIKE :q OR name LIKE :q)';
    $where[] = $like; $params[':q'] = '%'.$q.'%';
}
$wh = implode(' AND ', $where);
$sortQ = in_array($s['col'], $cols, true) ? "`{$s['col']}` {$s['dir']}" : 'id DESC';

$c = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE $wh"); $c->execute($params);
$total = (int)$c->fetchColumn();
$pages = max(1, (int)ceil($total / $pg['per_page']));
if ($pg['page'] > $pages) { $pg['page']=$pages; $pg['offset']=($pg['page']-1)*$pg['per_page']; }
$stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE $wh ORDER BY $sortQ LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$url_builder = fn($ov=[]) => '?' . http_build_query(array_merge($_GET, $ov));
$account_alerts = admin_flash_peek() ? [admin_flash()] : [];
$admin_page_title = t('admin.transfers').' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = t('admin.transfers');
$admin_active = 'transfers';
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
          <h3><i class="fa-solid fa-car-side"></i> <?= htmlspecialchars(t('admin.transfers')) ?></h3>
          <a class="btn btn-sm" href="form.php?tab=<?= htmlspecialchars($tab) ?>"><?= htmlspecialchars(t('admin.add_new')) ?></a>
        </div>

        <div class="tabs">
          <?php foreach ($tabs as $tk => $tcfg): ?>
            <a class="tab <?= $tab===$tk?'active':'' ?>" href="?tab=<?= $tk ?>"><?= htmlspecialchars(t($tcfg['title_key'], ucfirst($tk))) ?></a>
          <?php endforeach; ?>
        </div>

        <form method="get" action="index.php" class="toolbar">
          <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
          <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="<?= htmlspecialchars(t('admin.search')) ?>..."></div>
          <button type="submit" class="btn btn-sm"><?= htmlspecialchars(t('admin.apply','Apply')) ?></button>
          <a href="?tab=<?= htmlspecialchars($tab) ?>" class="btn btn-ghost btn-sm"><?= htmlspecialchars(t('admin.reset','Reset')) ?></a>
        </form>

        <div class="table-wrap">
          <table>
            <thead>
              <?php if ($tab === 'routes'): ?>
                <tr><th><?= htmlspecialchars(t('admin.origin')) ?></th><th><?= htmlspecialchars(t('admin.destination_field')) ?></th><th><?= htmlspecialchars(t('admin.base_price')) ?></th><th><?= htmlspecialchars(t('admin.distance')) ?></th><th><?= htmlspecialchars(t('admin.status')) ?></th><th><?= htmlspecialchars(t('admin.actions')) ?></th></tr>
              <?php elseif ($tab === 'car_classes'): ?>
                <tr><th><?= htmlspecialchars(t('admin.name')) ?></th><th><?= htmlspecialchars(t('admin.passenger_capacity')) ?></th><th><?= htmlspecialchars(t('admin.luggage_capacity')) ?></th><th><?= htmlspecialchars(t('admin.price_multiplier')) ?></th><th><?= htmlspecialchars(t('admin.status')) ?></th><th><?= htmlspecialchars(t('admin.actions')) ?></th></tr>
              <?php elseif ($tab === 'transfer_services'): ?>
                <tr><th><?= htmlspecialchars(t('admin.name')) ?></th><th><?= htmlspecialchars(t('admin.price')) ?></th><th><?= htmlspecialchars(t('admin.unit')) ?></th><th><?= htmlspecialchars(t('admin.status')) ?></th><th><?= htmlspecialchars(t('admin.actions')) ?></th></tr>
              <?php elseif ($tab === 'locations'): ?>
                <tr><th><?= htmlspecialchars(t('admin.name')) ?></th><th><?= htmlspecialchars(t('admin.type')) ?></th><th><?= htmlspecialchars(t('admin.country')) ?></th><th><?= htmlspecialchars(t('admin.status')) ?></th><th><?= htmlspecialchars(t('admin.actions')) ?></th></tr>
              <?php else: ?>
                <tr><th><?= htmlspecialchars(t('admin.key')) ?></th><th><?= htmlspecialchars(t('admin.name')) ?></th><th><?= htmlspecialchars(t('admin.price')) ?></th><th><?= htmlspecialchars(t('admin.unit')) ?></th><th><?= htmlspecialchars(t('admin.status')) ?></th><th><?= htmlspecialchars(t('admin.actions')) ?></th></tr>
              <?php endif; ?>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6"><div class="muted" style="text-align:center;padding:20px;"><?= htmlspecialchars(t('admin.no_records')) ?></div></td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <?php if ($tab === 'routes'): ?>
                  <td><?= htmlspecialchars($r['origin']) ?></td>
                  <td><?= htmlspecialchars($r['destination']) ?></td>
                  <td class="code"><?= htmlspecialchars(number_format((float)$r['base_price'],2)) ?></td>
                  <td><?= htmlspecialchars($r['distance_km']) ?> km · <?= htmlspecialchars($r['travel_time_min']) ?> min</td>
                <?php elseif ($tab === 'car_classes'): ?>
                  <td class="code"><?= htmlspecialchars($r['name']) ?></td>
                  <td><?= (int)$r['passenger_capacity'] ?></td>
                  <td><?= (int)$r['luggage_capacity'] ?></td>
                  <td><?= htmlspecialchars($r['price_multiplier']) ?>×</td>
                <?php elseif ($tab === 'transfer_services'): ?>
                  <td class="code"><?= htmlspecialchars($r['name']) ?></td>
                  <td class="code"><?= htmlspecialchars(number_format((float)$r['price'],2)) ?></td>
                  <td><?= htmlspecialchars($r['unit']) ?></td>
                <?php elseif ($tab === 'locations'): ?>
                  <td class="code"><?= htmlspecialchars($r['name']) ?></td>
                  <td><?= htmlspecialchars($r['type']) ?></td>
                  <td><?= htmlspecialchars($r['country'] ?? '—') ?></td>
                <?php else: ?>
                  <td class="code"><?= htmlspecialchars($r['key'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['name']) ?></td>
                  <td class="code"><?= htmlspecialchars(number_format((float)$r['price'],2)) ?></td>
                  <td><?= htmlspecialchars($r['unit'] ?? '—') ?></td>
                <?php endif; ?>
                <td><span class="badge badge-<?= (int)$r['is_active']?'active':'inactive' ?>"><?= (int)$r['is_active']?htmlspecialchars(t('admin.active')):htmlspecialchars(t('admin.inactive')) ?></span></td>
                <td>
                  <div class="actions">
                    <a class="icon-btn" href="form.php?tab=<?= htmlspecialchars($tab) ?>&id=<?= (int)$r['id'] ?>"><i class="fa-solid fa-pen"></i></a>
                    <form method="post" action="index.php" style="display:inline;"><?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="icon-btn danger" type="submit" onclick="return confirm('<?= htmlspecialchars(t('admin.delete_confirm')) ?>')"><i class="fa-solid fa-trash"></i></button>
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

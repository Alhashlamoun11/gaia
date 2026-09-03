<?php
/**
 * admin/transfers/index.php
 * Transfers management via tabs:
 *   routes | car_classes | pickup_points | transfer_services | locations | extras
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();

$tabs = [
    'routes'             => ['table'=>'routes','title_key'=>'admin.routes'],
    'car_classes'        => ['table'=>'car_classes','title_key'=>'admin.car_classes'],
    'pickup_points'      => ['table'=>'pickup_locations','title_key'=>'admin.pickup_points'],
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

$colMap = [
    'routes'            => ['id','origin','destination','base_price','distance_km','travel_time_min','is_active','created_at'],
    'car_classes'       => ['id','name','passenger_capacity','luggage_capacity','price_multiplier','is_active','created_at'],
    'pickup_points'     => ['id','name','sort_order','is_active'],
    'transfer_services' => ['id','name','price','unit','is_active','sort_order','created_at'],
    'locations'         => ['id','name','type','country','is_active','sort_order','created_at'],
    'extras'            => ['id','key','name','price','unit','is_active','sort_order','created_at'],
];
$cols = $colMap[$tab];
$s = AdminQuery::sort($cols, 'id');

$where = ['1=1']; $params = [];
if ($q !== '') {
    if ($tab === 'routes') {
        $where[] = '(origin LIKE :q OR destination LIKE :q)';
    } elseif ($tab === 'car_classes') {
        $where[] = '(name LIKE :q OR name_ar LIKE :q OR models LIKE :q OR slug LIKE :q)';
    } elseif ($tab === 'extras') {
        $where[] = '(`key` LIKE :q OR name LIKE :q)';
    } else {
        $where[] = "(name LIKE :q)";
    }
    $params[':q'] = '%'.$q.'%';
}
$wh = implode(' AND ', $where);
$sortQ = in_array($s['col'], $cols, true) ? "`{$s['col']}` {$s['dir']}" : ($tab === 'car_classes' || $tab === 'pickup_points' ? 'sort_order ASC, id ASC' : 'id DESC');

$c = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE $wh"); $c->execute($params);
$total = (int)$c->fetchColumn();
$pages = max(1, (int)ceil($total / $pg['per_page']));
if ($pg['page'] > $pages) { $pg['page']=$pages; $pg['offset']=($pg['page']-1)*$pg['per_page']; }
$stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE $wh ORDER BY $sortQ LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$url_builder = fn($ov=[]) => '?' . http_build_query(array_merge($_GET, $ov));
$account_alerts = admin_flash_peek() ? [admin_flash()] : [];
$admin_page_title = ($tab === 'car_classes' ? t('admin.car_classes', 'Car Classes') : ($tab === 'pickup_points' ? 'Pickup Points' : t('admin.transfers'))).' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = $tab === 'car_classes' ? t('admin.car_classes', 'Car Classes') : ($tab === 'pickup_points' ? 'Pickup Points' : t('admin.transfers'));
$admin_active = $tab === 'car_classes' ? 'car_classes' : 'transfers';
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
          <h3><i class="<?= $tab==='car_classes'?'fa-solid fa-taxi':($tab==='pickup_points'?'fa-solid fa-location-dot':'fa-solid fa-car-side') ?>"></i> <?= htmlspecialchars($admin_topbar_title) ?></h3>
          <a class="btn btn-sm btn-primary" href="form.php?tab=<?= htmlspecialchars($tab) ?>"><i class="fa-solid fa-plus"></i> <?= htmlspecialchars(t('admin.add_new')) ?></a>
        </div>

        <div class="tabs">
          <?php foreach ($tabs as $tk => $tcfg): ?>
            <a class="tab <?= $tab===$tk?'active':'' ?>" href="?tab=<?= $tk ?>"><?= htmlspecialchars(t($tcfg['title_key'], ucfirst(str_replace('_',' ',$tk)))) ?></a>
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
                <tr><th>Vehicle</th><th>Class Name</th><th>Example Fleet Models</th><th>Capacity</th><th>Multiplier</th><th>Status</th><th><?= htmlspecialchars(t('admin.actions')) ?></th></tr>
              <?php elseif ($tab === 'pickup_points'): ?>
                <tr><th>Pickup Point Location Name</th><th>Sort Order</th><th>Status</th><th><?= htmlspecialchars(t('admin.actions')) ?></th></tr>
              <?php elseif ($tab === 'transfer_services'): ?>
                <tr><th><?= htmlspecialchars(t('admin.name')) ?></th><th><?= htmlspecialchars(t('admin.price')) ?></th><th><?= htmlspecialchars(t('admin.unit')) ?></th><th><?= htmlspecialchars(t('admin.status')) ?></th><th><?= htmlspecialchars(t('admin.actions')) ?></th></tr>
              <?php elseif ($tab === 'locations'): ?>
                <tr><th><?= htmlspecialchars(t('admin.name')) ?></th><th><?= htmlspecialchars(t('admin.type')) ?></th><th><?= htmlspecialchars(t('admin.country')) ?></th><th><?= htmlspecialchars(t('admin.status')) ?></th><th><?= htmlspecialchars(t('admin.actions')) ?></th></tr>
              <?php else: ?>
                <tr><th><?= htmlspecialchars(t('admin.key')) ?></th><th><?= htmlspecialchars(t('admin.name')) ?></th><th><?= htmlspecialchars(t('admin.price')) ?></th><th><?= htmlspecialchars(t('admin.unit')) ?></th><th><?= htmlspecialchars(t('admin.status')) ?></th><th><?= htmlspecialchars(t('admin.actions')) ?></th></tr>
              <?php endif; ?>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="7"><div class="muted" style="text-align:center;padding:24px;"><?= htmlspecialchars(t('admin.no_records')) ?></div></td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <?php if ($tab === 'routes'): ?>
                  <td><?= htmlspecialchars($r['origin']) ?></td>
                  <td><?= htmlspecialchars($r['destination']) ?></td>
                  <td class="code"><?= htmlspecialchars(number_format((float)$r['base_price'],2)) ?></td>
                  <td><?= htmlspecialchars($r['distance_km']) ?> km · <?= htmlspecialchars($r['travel_time_min']) ?> min</td>
                <?php elseif ($tab === 'car_classes'): ?>
                  <td>
                    <?php $img = !empty($r['image_url']) ? $r['image_url'] : 'https://images.unsplash.com/photo-1550355291-bbee04a92027?auto=format&fit=crop&w=600&q=80'; ?>
                    <img src="<?= htmlspecialchars('/' . ltrim($img, '/')) ?>" alt="" style="height:44px;width:66px;object-fit:cover;border-radius:8px;border:1px solid var(--line);background:#f0ede6;">
                  </td>
                  <td>
                    <div style="font-weight:700; color:var(--ink); font-size:14px;"><?= htmlspecialchars($r['name']) ?></div>
                    <?php if (!empty($r['name_ar'])): ?>
                      <div style="font-size:12px; color:var(--teal); font-weight:600;"><?= htmlspecialchars($r['name_ar']) ?></div>
                    <?php endif; ?>
                    <div style="font-size:11px; color:var(--muted); font-family:monospace;"><?= htmlspecialchars($r['slug']) ?></div>
                  </td>
                  <td>
                    <div style="font-size:13px; color:var(--ink); max-width:260px; line-height:1.4;">
                      <?= !empty($r['models']) ? htmlspecialchars($r['models']) : '<span class="muted">—</span>' ?>
                    </div>
                  </td>
                  <td>
                    <div style="display:flex; flex-direction:column; gap:3px;">
                      <span style="font-size:12.5px; font-weight:600; color:var(--teal);"><i class="fa-solid fa-user" style="width:16px;"></i> <?= (int)$r['passenger_capacity'] ?> Pax</span>
                      <span style="font-size:12px; color:var(--muted);"><i class="fa-solid fa-suitcase-rolling" style="color:#e67e22; width:16px;"></i> <?= (int)$r['luggage_capacity'] ?> Bags</span>
                    </div>
                  </td>
                  <td>
                    <span style="display:inline-block; padding:4px 10px; background:#f4efe6; border:1px solid var(--line); border-radius:6px; font-weight:800; font-size:13px; color:var(--navy);">
                      <?= htmlspecialchars($r['price_multiplier']) ?>×
                    </span>
                  </td>
                <?php elseif ($tab === 'pickup_points'): ?>
                  <td>
                    <strong style="color:var(--ink); font-size:14px;">
                      <i class="fa-solid fa-location-dot" style="color:var(--teal); margin-right:6px;"></i>
                      <?= htmlspecialchars($r['name']) ?>
                    </strong>
                  </td>
                  <td><span class="badge" style="background:#eef2f6; color:var(--navy); font-weight:700;">#<?= (int)$r['sort_order'] ?></span></td>
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
                    <a class="icon-btn" href="form.php?tab=<?= htmlspecialchars($tab) ?>&id=<?= (int)$r['id'] ?>" title="Edit"><i class="fa-solid fa-pen"></i></a>
                    <form method="post" action="index.php?tab=<?= htmlspecialchars($tab) ?>" style="display:inline;"><?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="icon-btn danger" type="submit" onclick="return confirm('<?= htmlspecialchars(t('admin.delete_confirm')) ?>')" title="Delete"><i class="fa-solid fa-trash"></i></button>
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

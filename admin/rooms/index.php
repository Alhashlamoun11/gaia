<?php
/**
 * admin/rooms/index.php
 * Rooms CRUD listing (hotel_rooms), linked to hotels.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        if ($action === 'delete') {
            CrudService::delete('hotel_rooms', $id);
            admin_flash(t('admin.deleted', 'Deleted.'), 'success');
        }
        header('Location: ' . admin_url('rooms/index.php')); exit;
    }
}

$pg = AdminQuery::pagination(15);
$q  = AdminQuery::search();
$s  = AdminQuery::sort(['id','name','price','capacity','quantity','sort_order'], 'name');
$hid = (int)($_GET['hotel_id'] ?? 0);

$where = ['1=1']; $params = [];
if ($q !== '') { $where[] = '(hr.name LIKE :q)'; $params[':q'] = '%'.$q.'%'; }
if ($hid > 0) { $where[] = 'hr.hotel_id = :hid'; $params[':hid'] = $hid; }
$wh = implode(' AND ', $where);

$c = $pdo->prepare("SELECT COUNT(*) FROM hotel_rooms hr WHERE $wh"); $c->execute($params);
$total = (int)$c->fetchColumn();
$pages = max(1, (int)ceil($total / $pg['per_page']));
if ($pg['page'] > $pages) { $pg['page']=$pages; $pg['offset']=($pg['page']-1)*$pg['per_page']; }

$stmt = $pdo->prepare("SELECT hr.*, h.name AS hotel_name FROM hotel_rooms hr LEFT JOIN hotels h ON h.id=hr.hotel_id WHERE $wh ORDER BY {$s['col']} {$s['dir']} LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$hotels = $pdo->query("SELECT id,name FROM hotels ORDER BY name")->fetchAll();

$url_builder = fn($ov=[]) => '?' . http_build_query(array_merge($_GET, $ov));
$account_alerts = admin_flash_peek() ? [admin_flash()] : [];
$admin_page_title = t('admin.rooms').' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = t('admin.rooms');
$admin_active = 'rooms';
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
          <h3><i class="fa-solid fa-bed"></i> <?= htmlspecialchars(t('admin.rooms')) ?></h3>
          <a class="btn btn-sm" href="form.php"><?= htmlspecialchars(t('admin.add_room', 'Add Room')) ?></a>
        </div>

        <form method="get" action="index.php" class="toolbar">
          <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="<?= htmlspecialchars(t('admin.search')) ?>..."></div>
          <select name="hotel_id">
            <option value=""><?= htmlspecialchars(t('admin.all_hotels', 'All Hotels')) ?></option>
            <?php foreach ($hotels as $h): ?><option value="<?= (int)$h['id'] ?>" <?= $hid===(int)$h['id']?'selected':'' ?>><?= htmlspecialchars($h['name']) ?></option><?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-sm"><?= htmlspecialchars(t('admin.apply', 'Apply')) ?></button>
          <a href="index.php" class="btn btn-ghost btn-sm"><?= htmlspecialchars(t('admin.reset', 'Reset')) ?></a>
        </form>

        <div class="table-wrap">
          <table>
            <thead><tr>
              <th><?= AdminQuery::sortLink('name', t('admin.name'), ['id','name','price','capacity','quantity','sort_order'], 'name') ?></th>
              <th><?= htmlspecialchars(t('admin.hotel')) ?></th>
              <th><?= AdminQuery::sortLink('price', t('admin.price'), ['id','name','price','capacity','quantity','sort_order'], 'name') ?></th>
              <th><?= AdminQuery::sortLink('capacity', t('admin.capacity'), ['id','name','price','capacity','quantity','sort_order'], 'name') ?></th>
              <th><?= AdminQuery::sortLink('quantity', t('admin.quantity', 'Quantity'), ['id','name','price','capacity','quantity','sort_order'], 'name') ?></th>
              <th><?= htmlspecialchars(t('admin.status')) ?></th>
              <th><?= htmlspecialchars(t('admin.actions')) ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="7"><div class="muted" style="text-align:center;padding:20px;"><?= htmlspecialchars(t('admin.no_records')) ?></div></td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <td class="code"><?= htmlspecialchars($r['name']) ?></td>
                <td><?= htmlspecialchars($r['hotel_name'] ?? '—') ?></td>
                <td class="code"><?= htmlspecialchars(number_format((float)$r['price'], 2)) ?></td>
                <td><?= (int)$r['capacity'] ?></td>
                <td><?= (int)$r['quantity'] ?></td>
                <td><span class="badge badge-<?= (int)$r['is_active']?'active':'inactive' ?>"><?= (int)$r['is_active']?htmlspecialchars(t('admin.active')):htmlspecialchars(t('admin.inactive')) ?></span></td>
                <td>
                  <div class="actions">
                    <a class="icon-btn" href="form.php?id=<?= (int)$r['id'] ?>"><i class="fa-solid fa-pen"></i></a>
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

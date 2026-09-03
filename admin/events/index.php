<?php
/**
 * admin/events/index.php
 * Events CRUD listing.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $action = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        if ($action === 'toggle') {
            $st = $pdo->prepare('SELECT is_active FROM events WHERE id=?'); $st->execute([$id]);
            $cur = (int)$st->fetchColumn();
            $pdo->prepare('UPDATE events SET is_active=? WHERE id=?')->execute([$cur?0:1,$id]);
            admin_flash(t('admin.status_updated','Status updated.'), 'success');
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM events WHERE id=?')->execute([$id]);
            admin_flash(t('admin.deleted','Deleted.'), 'success');
        }
        header('Location: ' . admin_url('events/index.php')); exit;
    }
}

$pg = AdminQuery::pagination(12);
$q  = AdminQuery::search();
$st = AdminQuery::statusFilter(['1','0'], 'active');
$s  = AdminQuery::sort(['id','title','date_label','location','price','created_at'], 'title');

$where = ['1=1']; $params = [];
if ($q !== '') { $where[] = '(title LIKE :q OR location LIKE :q OR category LIKE :q)'; $params[':q'] = '%'.$q.'%'; }
if ($st !== '') { $where[] = 'is_active = :st'; $params[':st'] = (int)$st; }
$wh = implode(' AND ', $where);

$c = $pdo->prepare("SELECT COUNT(*) FROM events WHERE $wh"); $c->execute($params);
$total = (int)$c->fetchColumn();
$pages = max(1, (int)ceil($total / $pg['per_page']));
if ($pg['page'] > $pages) { $pg['page']=$pages; $pg['offset']=($pg['page']-1)*$pg['per_page']; }

$stmt = $pdo->prepare("SELECT id, title, location, date_label, category, price, capacity, is_active, created_at FROM events WHERE $wh ORDER BY {$s['col']} {$s['dir']} LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$url_builder = fn($ov=[]) => '?' . http_build_query(array_merge($_GET, $ov));
$account_alerts = admin_flash_peek() ? [admin_flash()] : [];
$admin_page_title = t('admin.events').' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = t('admin.events');
$admin_active = 'events';
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
          <h3><i class="fa-solid fa-calendar-check"></i> <?= htmlspecialchars(t('admin.events')) ?></h3>
          <a class="btn btn-sm" href="form.php"><?= htmlspecialchars(t('admin.add_event','Add Event')) ?></a>
        </div>
        <form method="get" action="index.php" class="toolbar">
          <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="<?= htmlspecialchars(t('admin.search')) ?>..."></div>
          <select name="status">
            <option value=""><?= htmlspecialchars(t('admin.all_statuses','All Statuses')) ?></option>
            <option value="1" <?=$st==='1'?'selected':''?>><?= htmlspecialchars(t('admin.active')) ?></option>
            <option value="0" <?=$st==='0'?'selected':''?>><?= htmlspecialchars(t('admin.inactive')) ?></option>
          </select>
          <button type="submit" class="btn btn-sm"><?= htmlspecialchars(t('admin.apply','Apply')) ?></button>
          <a href="index.php" class="btn btn-ghost btn-sm"><?= htmlspecialchars(t('admin.reset','Reset')) ?></a>
        </form>
        <div class="table-wrap">
          <table>
            <thead><tr>
              <th><?= AdminQuery::sortLink('title', t('admin.title'), ['id','title','date_label','location','price','created_at'], 'title') ?></th>
              <th><?= htmlspecialchars(t('admin.location')) ?></th>
              <th><?= htmlspecialchars(t('admin.date')) ?></th>
              <th><?= htmlspecialchars(t('admin.category')) ?></th>
              <th><?= AdminQuery::sortLink('price', t('admin.price'), ['id','title','date_label','location','price','created_at'], 'title') ?></th>
              <th><?= htmlspecialchars(t('admin.capacity')) ?></th>
              <th><?= htmlspecialchars(t('admin.status')) ?></th>
              <th><?= htmlspecialchars(t('admin.actions')) ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="8"><div class="muted" style="text-align:center;padding:20px;"><?= htmlspecialchars(t('admin.no_records')) ?></div></td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <td class="code"><?= htmlspecialchars($r['title']) ?></td>
                <td><?= htmlspecialchars($r['location'] ?? '—') ?></td>
                <td><?= htmlspecialchars($r['date_label'] ?? '—') ?></td>
                <td><?= htmlspecialchars($r['category'] ?? '—') ?></td>
                <td class="code"><?= htmlspecialchars(number_format((float)$r['price'],2)) ?></td>
                <td><?= (int)$r['capacity'] ?></td>
                <td><span class="badge badge-<?= (int)$r['is_active']?'active':'inactive' ?>"><?= (int)$r['is_active']?htmlspecialchars(t('admin.active')):htmlspecialchars(t('admin.inactive')) ?></span></td>
                <td>
                  <div class="actions">
                    <a class="icon-btn" href="form.php?id=<?= (int)$r['id'] ?>"><i class="fa-solid fa-pen"></i></a>
                    <form method="post" action="index.php" style="display:inline;"><?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="icon-btn" type="submit"><i class="fa-solid <?= (int)$r['is_active']?'fa-ban':'fa-check' ?>"></i></button>
                    </form>
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

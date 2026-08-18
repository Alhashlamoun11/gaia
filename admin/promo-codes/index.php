<?php
/**
 * admin/promo-codes/index.php
 * Promo Codes CRUD management.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $action = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        if ($action === 'toggle') {
            $st = $pdo->prepare('SELECT is_active FROM promo_codes WHERE id=?'); $st->execute([$id]);
            $cur = (int)$st->fetchColumn();
            $pdo->prepare('UPDATE promo_codes SET is_active=? WHERE id=?')->execute([$cur?0:1,$id]);
            admin_flash(t('admin.status_updated','Status updated.'), 'success');
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM promo_codes WHERE id=?')->execute([$id]);
            admin_flash(t('admin.deleted','Deleted.'), 'success');
        }
        header('Location: ' . admin_url('promo-codes/index.php')); exit;
    }
}

$pg = AdminQuery::pagination(20);
$q  = AdminQuery::search();
$st = AdminQuery::statusFilter(['1','0'], '1');
$s  = AdminQuery::sort(['id','code','discount_type','discount_value','max_uses'], 'code');

$where = ['1=1']; $params = [];
if ($q !== '') { $where[] = '(code LIKE :q OR discount_type LIKE :q)'; $params[':q'] = '%'.$q.'%'; }
if ($st !== '') { $where[] = 'is_active = :st'; $params[':st'] = (int)$st; }
$wh = implode(' AND ', $where);

$c = $pdo->prepare("SELECT COUNT(*) FROM promo_codes WHERE $wh"); $c->execute($params);
$total = (int)$c->fetchColumn();
$pages = max(1, (int)ceil($total / $pg['per_page']));
if ($pg['page'] > $pages) { $pg['page']=$pages; $pg['offset']=($pg['page']-1)*$pg['per_page']; }

$stmt = $pdo->prepare("SELECT * FROM promo_codes WHERE $wh ORDER BY {$s['col']} {$s['dir']} LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$url_builder = fn($ov=[]) => '?' . http_build_query(array_merge($_GET, $ov));
$account_alerts = admin_flash_peek() ? [admin_flash()] : [];
$admin_page_title = t('admin.promo_codes').' — GAIA TOURS & TRAVEL';
$admin_topbar_title = t('admin.promo_codes');
$admin_active = 'promo-codes';
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
          <h3><i class="fa-solid fa-ticket"></i> <?= $C(t('admin.promo_codes')) ?></h3>
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
              <th><?= $C(AdminQuery::sortLink('code', t('admin.code','Code'), ['id','code','discount_type','discount_value','max_uses'], 'code')) ?></th>
              <th><?= $C(t('admin.type','Type')) ?></th>
              <th><?= $C(AdminQuery::sortLink('discount_value', t('admin.value','Value'), ['id','code','discount_type','discount_value','max_uses'], 'code')) ?></th>
              <th><?= $C(AdminQuery::sortLink('max_uses', t('admin.max_uses','Max Uses'), ['id','code','discount_type','discount_value','max_uses'], 'code')) ?></th>
              <th><?= $C(t('admin.used','Used')) ?></th>
              <th><?= $C(t('admin.status')) ?></th>
              <th><?= $C(t('admin.actions')) ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="7"><div class="muted" style="text-align:center;padding:20px;"><?= $C(t('admin.no_records')) ?></div></td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <td class="code"><?= $C($r['code']) ?></td>
                <td><span class="badge"><?= $C($r['discount_type']) ?></span></td>
                <td class="code"><?= $C($r['discount_type']==='percent' ? $r['discount_value'].'%' : number_format((float)$r['discount_value'],2)) ?></td>
                <td><?= (int)$r['max_uses'] ?></td>
                <td><?= (int)$r['used_count'] ?></td>
                <td><span class="badge badge-<?= (int)$r['is_active']?'active':'inactive' ?>"><?= (int)$r['is_active']?$C(t('admin.active')):$C(t('admin.inactive')) ?></span></td>
                <td>
                  <div class="actions">
                    <a class="icon-btn" href="form.php?id=<?= (int)$r['id'] ?>"><i class="fa-solid fa-pen"></i></a>
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
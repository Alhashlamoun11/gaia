<?php
/**
 * admin/reviews/index.php
 * Reviews management across all review tables (reviews, hotel_reviews,
 * weroad_reviews). Unified listing with approve/reject/edit/featured/delete.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();

// Source registry
$sources = [
    'reviews'        => ['table'=>'reviews',        'label'=>'Review'],
    'hotel_reviews'  => ['table'=>'hotel_reviews',  'label'=>'Hotel Review'],
    'weroad_reviews' => ['table'=>'weroad_reviews', 'label'=>'Tour Review'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $action = $_POST['action'] ?? ''; $src = $_POST['source'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if (isset($sources[$src]) && $id > 0) {
        $tbl = $sources[$src]['table'];
        if ($action === 'approve') {
            $pdo->prepare("UPDATE `{$tbl}` SET is_active=1 WHERE id=?")->execute([$id]);
            admin_flash(t('admin.approve_review','Review approved.'), 'success');
        } elseif ($action === 'reject') {
            $pdo->prepare("UPDATE `{$tbl}` SET is_active=0 WHERE id=?")->execute([$id]);
            admin_flash(t('admin.review_rejected','Review rejected.'), 'success');
        } elseif ($action === 'featured') {
            $pdo->prepare("UPDATE `{$tbl}` SET featured=? WHERE id=?")->execute([(int)($_POST['value']??0), $id]);
            admin_flash(t('admin.status_updated','Status updated.'), 'success');
        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM `{$tbl}` WHERE id=?")->execute([$id]);
            admin_flash(t('admin.deleted','Deleted.'), 'success');
        }
        header('Location: ' . admin_url('reviews/index.php')); exit;
    }
}

$pg = AdminQuery::pagination(15);
$q  = AdminQuery::search();
$st = AdminQuery::statusFilter(['1','0'], '');

// Merge rows from all sources
$allRows = []; $total = 0;
foreach ($sources as $sk => $scfg) {
    $tbl = $scfg['table'];
    $where = ['1=1']; $params = [];
    if ($q !== '') { $where[] = '(reviewer LIKE :q OR text LIKE :q OR author LIKE :q)'; $params[':q'] = '%'.$q.'%'; }
    if ($st !== '') { $where[] = 'is_active = :st'; $params[':st'] = (int)$st; }
    $wh = implode(' AND ', $where);
    $c = $pdo->prepare("SELECT COUNT(*) FROM `{$tbl}` WHERE $wh"); $c->execute($params);
    $total += (int)$c->fetchColumn();
    $stmt = $pdo->prepare("SELECT *, '{$sk}' AS source FROM `{$tbl}` WHERE $wh ORDER BY id DESC LIMIT 200");
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) {
        $r['_source'] = $sk;
        $allRows[] = $r;
    }
}
// Sort merged by id desc (approx created)
usort($allRows, fn($a,$b) => (int)$b['id'] <=> (int)$a['id']);
$pages = max(1, (int)ceil($total / $pg['per_page']));
if ($pg['page'] > $pages) { $pg['page']=$pages; $pg['offset']=($pg['page']-1)*$pg['per_page']; }
$rows = array_slice($allRows, $pg['offset'], $pg['per_page']);

$url_builder = fn($ov=[]) => '?' . http_build_query(array_merge($_GET, $ov));
$account_alerts = admin_flash_peek() ? [admin_flash()] : [];
$admin_page_title = t('admin.reviews').' — GAIA TOURS & TRAVEL';
$admin_topbar_title = t('admin.reviews');
$admin_active = 'reviews';
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
          <h3><i class="fa-solid fa-star"></i> <?= $C(t('admin.reviews')) ?></h3>
        </div>
        <form method="get" action="index.php" class="toolbar">
          <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="text" name="q" value="<?= $C($q) ?>" placeholder="<?= $C(t('admin.search')) ?>..."></div>
          <select name="status">
            <option value=""><?= $C(t('admin.all_statuses','All Statuses')) ?></option>
            <option value="1" <?=$st==='1'?'selected':''?>><?= $C(t('admin.approved','Approved')) ?></option>
            <option value="0" <?=$st==='0'?'selected':''?>><?= $C(t('admin.pending','Pending')) ?></option>
          </select>
          <button type="submit" class="btn btn-sm"><?= $C(t('admin.apply','Apply')) ?></button>
          <a href="index.php" class="btn btn-ghost btn-sm"><?= $C(t('admin.reset','Reset')) ?></a>
        </form>
        <div class="table-wrap">
          <table>
            <thead><tr>
              <th><?= $C(t('admin.reviewer')) ?></th>
              <th><?= $C(t('admin.rating')) ?></th>
              <th><?= $C(t('admin.text','Review')) ?></th>
              <th><?= $C(t('admin.source','Source')) ?></th>
              <th><?= $C(t('admin.status')) ?></th>
              <th><?= $C(t('admin.featured')) ?></th>
              <th><?= $C(t('admin.actions')) ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="7"><div class="muted" style="text-align:center;padding:20px;"><?= $C(t('admin.no_records')) ?></div></td></tr>
            <?php else: foreach ($rows as $r): $isMain = $r['_source']==='reviews'; ?>
              <tr>
                <td class="code"><?= $C($r['reviewer'] ?? $r['author'] ?? '') ?></td>
                <td><?= str_repeat('★', (int)($r['rating']??0)) ?></td>
                <td class="truncate"><?= $C(mb_substr($r['text'] ?? '', 0, 80)) ?></td>
                <td><span class="badge"><?= $C($sources[$r['_source']]['label']) ?></span></td>
                <td><?php if (isset($r['is_active'])): ?><span class="badge badge-<?= (int)$r['is_active']?'active':'inactive' ?>"><?= (int)$r['is_active']?$C(t('admin.approved','Approved')):$C(t('admin.pending','Pending')) ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                <td><?php if (isset($r['featured'])): ?><span class="badge badge-<?= (int)$r['featured']?'active':'inactive' ?>"><?= (int)$r['featured']?'★':'' ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                <td>
                  <div class="actions">
                    <a class="icon-btn" href="edit.php?source=<?= $C($r['_source']) ?>&id=<?= (int)$r['id'] ?>" title="<?= $C(t('admin.edit')) ?>"><i class="fa-solid fa-pen"></i></a>
                    <?php if (isset($r['is_active'])): ?>
                      <form method="post" action="index.php" style="display:inline;"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="<?= (int)$r['is_active']?'reject':'approve' ?>"><input type="hidden" name="source" value="<?= $C($r['_source']) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="icon-btn" type="submit" title="<?= $C(t('admin.approve')) ?>"><i class="fa-solid <?= (int)$r['is_active']?'fa-ban':'fa-check' ?>"></i></button>
                      </form>
                    <?php endif; ?>
                    <?php if (isset($r['featured'])): ?>
                      <form method="post" action="index.php" style="display:inline;"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="featured"><input type="hidden" name="source" value="<?= $C($r['_source']) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="value" value="<?= (int)$r['featured']?0:1 ?>">
                        <button class="icon-btn" type="submit" title="<?= $C(t('admin.featured')) ?>"><i class="fa-solid <?= (int)$r['featured']?'fa-star':'fa-star-o' ?>"></i></button>
                      </form>
                    <?php endif; ?>
                    <form method="post" action="index.php" style="display:inline;"><?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete"><input type="hidden" name="source" value="<?= $C($r['_source']) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
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
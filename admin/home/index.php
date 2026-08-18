<?php
/**
 * admin/home/index.php
 * ------------------------------------------------------------
 * Homepage CMS — manage banners, highlights, trust scores,
 * awards, night offerings, and social links.
 * All data comes from their existing tables.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();

$tabs = [
    'banners'        => ['table'=>'banners',        'title_key'=>'admin.banners'],
    'highlights'     => ['table'=>'highlights',     'title_key'=>'admin.highlights'],
    'trust_scores'   => ['table'=>'trust_scores',   'title_key'=>'admin.trust_scores'],
    'awards'         => ['table'=>'awards',         'title_key'=>'admin.awards'],
    'night_offerings'=> ['table'=>'night_offerings','title_key'=>'admin.night_offerings'],
    'social_links'   => ['table'=>'social_links',   'title_key'=>'admin.social_links'],
];
$tab = $_GET['tab'] ?? 'banners';
if (!isset($tabs[$tab])) $tab = 'banners';
$cfg = $tabs[$tab];
$table = $cfg['table'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0 && $action === 'delete') {
        CrudService::delete($table, $id);
        admin_flash(t('admin.deleted', 'Deleted.'), 'success');
    }
    if ($id > 0 && $action === 'toggle_active') {
        CrudService::setFlag($table, $id, 'is_active', (int)($_POST['value'] ?? 0));
        admin_flash(t('admin.status_updated', 'Status updated.'), 'success');
    }
    header('Location: ' . admin_url('home/index.php?tab=' . urlencode($tab)));
    exit;
}

$pg = AdminQuery::pagination(20);
$q  = AdminQuery::search();

$where = ['1=1']; $params = [];
if ($q !== '') {
    $where[] = '(title LIKE :q OR description LIKE :q OR name LIKE :q OR label LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
$wh = implode(' AND ', $where);

$c = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE $wh");
$c->execute($params);
$total = (int)$c->fetchColumn();
$pages = max(1, (int)ceil($total / $pg['per_page']));
if ($pg['page'] > $pages) { $pg['page'] = $pages; $pg['offset'] = ($pg['page'] - 1) * $pg['per_page']; }

$orderCol = in_array($tab, ['banners','highlights','awards','night_offerings','social_links']) ? 'sort_order' : 'id';
$rows = $pdo->prepare("SELECT * FROM `{$table}` WHERE $wh ORDER BY {$orderCol} ASC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$rows->execute($params);
$rows = $rows->fetchAll();

// For each tab, define columns
$cols = [];
switch ($tab) {
    case 'banners': $cols = ['id','page','title','sort_order','is_active']; break;
    case 'highlights': $cols = ['id','section','title','icon','image_url','sort_order','is_active']; break;
    case 'trust_scores': $cols = ['id','icon','score','label','color','sort_order','is_active']; break;
    case 'awards': $cols = ['id','title','icon','sort_order','is_active']; break;
    case 'night_offerings': $cols = ['id','type','title','badge','price_value','price_label','image_url','sort_order','is_active']; break;
    case 'social_links': $cols = ['id','platform','url','icon','sort_order','is_active']; break;
}

$url_builder = fn($ov = []) => '?' . http_build_query(array_merge($_GET, $ov));
$account_alerts = admin_flash_peek() ? [admin_flash()] : [];
$admin_page_title = t('admin.homepage') . ' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = t('admin.homepage');
$admin_active = 'home';

function _home_esc($v) { return htmlspecialchars((string)$v); }
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
          <h3><i class="fa-solid fa-house"></i> <?= _home_esc(t('admin.homepage')) ?></h3>
          <a class="btn btn-sm" href="form.php?tab=<?= _home_esc($tab) ?>"><?= _home_esc(t('admin.add_new')) ?></a>
        </div>

        <div class="tabs">
          <?php foreach ($tabs as $tk => $tcfg): ?>
            <a class="tab <?= $tab === $tk ? 'active' : '' ?>" href="?tab=<?= _home_esc($tk) ?>"><?= _home_esc(t($tcfg['title_key'])) ?></a>
          <?php endforeach; ?>
        </div>

        <form method="get" action="index.php" class="toolbar">
          <input type="hidden" name="tab" value="<?= _home_esc($tab) ?>">
          <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="text" name="q" value="<?= _home_esc($q) ?>" placeholder="<?= _home_esc(t('admin.search')) ?>..."></div>
          <button type="submit" class="btn btn-sm"><?= _home_esc(t('admin.apply', 'Apply')) ?></button>
          <a href="?tab=<?= _home_esc($tab) ?>" class="btn btn-ghost btn-sm"><?= _home_esc(t('admin.reset', 'Reset')) ?></a>
        </form>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <?php foreach ($cols as $col): ?>
                  <th><?= _home_esc(t('admin.' . $col, ucfirst(str_replace('_', ' ', $col)))) ?></th>
                <?php endforeach; ?>
                <th><?= _home_esc(t('admin.actions')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr><td colspan="<?= count($cols) + 1 ?>"><div class="muted" style="text-align:center;padding:20px;"><?= _home_esc(t('admin.no_records')) ?></div></td></tr>
              <?php else: foreach ($rows as $r): ?>
                <tr>
                  <?php foreach ($cols as $col): ?>
                    <td>
                      <?php if ($col === 'is_active'): ?>
                        <span class="badge badge-<?= (int)$r[$col] ? 'active' : 'inactive' ?>"><?= (int)$r[$col] ? _home_esc(t('admin.active')) : _home_esc(t('admin.inactive')) ?></span>
                      <?php elseif ($col === 'image_url' || $col === 'icon'): ?>
                        <?php if (!empty($r[$col])): ?>
                          <?php if (strpos($col, 'image') !== false): ?>
                            <img src="<?= _home_esc($r[$col]) ?>" alt="" style="width:60px;height:40px;object-fit:cover;border-radius:4px;">
                          <?php else: ?>
                            <i class="<?= _home_esc($r[$col]) ?>"></i> <?= _home_esc($r[$col]) ?>
                          <?php endif; ?>
                        <?php else: ?>&mdash;<?php endif; ?>
                      <?php elseif ($col === 'score'): ?>
                        <strong><?= _home_esc($r[$col]) ?></strong>
                      <?php elseif ($col === 'page'): ?>
                        <span class="code"><?= _home_esc($r[$col]) ?></span>
                      <?php else: ?>
                        <?= _home_esc(mb_substr((string)($r[$col] ?? ''), 0, 60)) ?>
                      <?php endif; ?>
                    </td>
                  <?php endforeach; ?>
                  <td>
                    <div class="actions">
                      <a class="icon-btn" href="form.php?tab=<?= _home_esc($tab) ?>&id=<?= (int)$r['id'] ?>"><i class="fa-solid fa-pen"></i></a>
                      <form method="post" action="index.php" style="display:inline;"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="value" value="<?= (int)$r['is_active'] ? 0 : 1 ?>">
                        <button class="icon-btn" type="submit"><i class="fa-solid <?= (int)$r['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i></button>
                      </form>
                      <form method="post" action="index.php" style="display:inline;"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="icon-btn danger" type="submit" onclick="return confirm('<?= _home_esc(t('admin.delete_confirm')) ?>')"><i class="fa-solid fa-trash"></i></button>
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
<?php require __DIR__ . '/../components/footer.php'; ?>


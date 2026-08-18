<?php
/**
 * admin/menu/form.php
 * Navigation item create/edit.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$row = $editing ? CrudService::find('menu_items', $id) : null;
if ($editing && !$row) { http_response_code(404);
    $admin_page_title = t('admin.not_found','Not Found').' — GAIA TOURS & TRAVEL'; require __DIR__.'/../components/header.php';
    echo '<div class="admin-app"><div class="admin-main"><div class="admin-content"><div class="card"><div class="muted" style="text-align:center;padding:40px;">'.htmlspecialchars(t('admin.not_found_text','Not found.')).'</div></div></div></div></div>'; require __DIR__.'/../components/footer.php'; exit;
}

$menu = $_GET['menu'] ?? 'header';
if (!in_array($menu, ['header','footer'], true)) $menu = 'header';

// Parent items for dropdown
$parents = $pdo->prepare("SELECT id, label FROM menu_items WHERE menu = ? AND parent_id = 0 ORDER BY sort_order");
$parents->execute([$menu]);
$parentRows = $parents->fetchAll();

$errors = []; $alerts = [];
$old = $row ?: ['menu'=>$menu,'parent_id'=>0,'label'=>'','url'=>'#','nav_key'=>'','sort_order'=>0,'is_premium'=>0,'is_active'=>1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $data = [
        'menu' => in_array($_POST['menu'] ?? 'header', ['header','footer'], true) ? $_POST['menu'] : 'header',
        'parent_id' => (int)($_POST['parent_id'] ?? 0),
        'label' => trim($_POST['label'] ?? ''),
        'url' => trim($_POST['url'] ?? '#'),
        'nav_key' => trim($_POST['nav_key'] ?? ''),
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
        'is_premium' => !empty($_POST['is_premium']) ? 1 : 0,
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
    ];
    if ($data['label']==='') $errors[]=t('admin.name_req','Label is required.');

    if (!$errors) {
        if ($editing) { CrudService::update('menu_items',$id,$data); $alerts[]=['type'=>'success','msg'=>t('admin.saved','Saved successfully.')]; }
        else { $id=CrudService::insert('menu_items',$data); $editing=true; $alerts[]=['type'=>'success','msg'=>t('admin.created','Created successfully.')]; }
        $row=CrudService::find('menu_items',$id); $old=$row;
    }
}

$account_alerts = array_merge(array_map(fn($e)=>['type'=>'error','msg'=>$e], $errors), $alerts);
$admin_page_title = ($editing?t('admin.edit'):t('admin.create')).' — GAIA TOURS & TRAVEL';
$admin_topbar_title = ($editing&&$row)?$row['label']:t('admin.add_new');
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
          <h3><i class="fa-solid fa-bars"></i> <?= $C(t('admin.menu_item','Menu Item')) ?></h3>
          <a class="btn btn-ghost btn-sm" href="index.php?menu=<?= $C($menu) ?>"><?= $C(t('admin.back_to_list')) ?></a>
        </div>
        <form method="post" action="form.php?menu=<?= $C($menu) ?><?= $editing?'&id='.(int)$id:'' ?>" novalidate>
          <?= csrf_field() ?>
          <div class="grid-2">
            <div class="field"><label><?= $C(t('admin.label','Label')) ?> *</label><input type="text" name="label" value="<?= $C($old['label']) ?>" required></div>
            <div class="field"><label><?= $C(t('admin.url','URL')) ?></label><input type="text" name="url" value="<?= $C($old['url']) ?>" placeholder="index.php"></div>
          </div>
          <div class="grid-2">
            <div class="field"><label><?= $C(t('admin.menu','Menu')) ?></label>
              <select name="menu">
                <option value="header" <?= $old['menu']==='header'?'selected':'' ?>><?= $C(t('admin.header','Header')) ?></option>
                <option value="footer" <?= $old['menu']==='footer'?'selected':'' ?>><?= $C(t('admin.footer','Footer')) ?></option>
              </select>
            </div>
            <div class="field"><label><?= $C(t('admin.parent','Parent')) ?></label>
              <select name="parent_id">
                <option value="0"><?= $C(t('admin.none','None')) ?></option>
                <?php foreach ($parentRows as $p): ?>
                  <option value="<?= (int)$p['id'] ?>" <?= (int)($old['parent_id']??0)===(int)$p['id']?'selected':'' ?>><?= $C($p['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="grid-3">
            <div class="field"><label><?= $C(t('admin.nav_key','Nav Key')) ?></label><input type="text" name="nav_key" value="<?= $C($old['nav_key']) ?>" placeholder="home"></div>
            <div class="field"><label><?= $C(t('admin.sort_order')) ?></label><input type="number" name="sort_order" value="<?= (int)($old['sort_order']??0) ?>"></div>
            <div class="field"><label><?= $C(t('admin.premium','Premium')) ?></label><input type="checkbox" name="is_premium" value="1" <?= (int)($old['is_premium']??0)?'checked':'' ?>></div>
          </div>
          <div class="field"><label><?= $C(t('admin.status')) ?></label><input type="checkbox" name="is_active" value="1" <?= (int)$old['is_active']?'checked':'' ?>> <?= $C(t('admin.active')) ?></div>
          <div class="form-actions">
            <button type="submit" class="btn"><?= $C(t('admin.save')) ?></button>
            <a class="btn btn-ghost" href="index.php?menu=<?= $C($menu) ?>"><?= $C(t('admin.cancel')) ?></a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__.'/../components/footer.php'; ?>
<?php
/**
 * admin/partners/form.php
 * Partners create/edit (name, logo/icon, website, order, status).
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$id = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$row = $editing ? CrudService::find('partners', $id) : null;
if ($editing && !$row) { http_response_code(404);
    $admin_page_title = t('admin.not_found','Not Found').' — GAIA TOURS &amp; TRAVEL'; require __DIR__.'/../components/header.php';
    echo '<div class="admin-app"><div class="admin-main"><div class="admin-content"><div class="card"><div class="muted" style="text-align:center;padding:40px;">'.htmlspecialchars(t('admin.not_found_text','Not found.')).'</div></div></div></div></div>'; require __DIR__.'/../components/footer.php'; exit;
}

$errors=[]; $alerts=[];
$old = $row ?: ['name'=>'','icon'=>'','sort_order'=>0,'is_active'=>1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $data = [
        'name'=>trim($_POST['name']??''),
        'sort_order'=>(int)($_POST['sort_order']??0),
        'is_active'=>!empty($_POST['is_active'])?1:0,
    ];
    if ($data['name']==='') $errors[]=t('admin.name_req','Name is required.');
    if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $up = CrudService::handleUpload($_FILES['logo'], 'partners');
        if ($up !== null) $data['icon']=$up;
        else $errors[]=t('admin.image_invalid','Invalid image.');
    } elseif (isset($_POST['icon']) && trim($_POST['icon']) !== '') {
        $data['icon']=trim($_POST['icon']);
    }
    if (!$errors) {
        if ($editing) { CrudService::update('partners',$id,$data); $alerts[]=['type'=>'success','msg'=>t('admin.saved','Saved successfully.')]; }
        else { $id=CrudService::insert('partners',$data); $editing=true; $alerts[]=['type'=>'success','msg'=>t('admin.created','Created successfully.')]; }
        $row=CrudService::find('partners',$id); $old=$row;
    }
}

$account_alerts = array_merge(array_map(fn($e)=>['type'=>'error','msg'=>$e], $errors), $alerts);
$admin_page_title = ($editing?t('admin.edit'):t('admin.create')).' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = $editing&&$row ? $row['name'] : t('admin.add_new');
$admin_active = 'partners';
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
          <h3><i class="fa-solid fa-handshake"></i> <?= htmlspecialchars(t('admin.partner_info','Partner Information')) ?></h3>
          <a class="btn btn-ghost btn-sm" href="index.php"><?= htmlspecialchars(t('admin.back_to_list')) ?></a>
        </div>
        <form method="post" action="form.php<?= $editing?'?id='.(int)$id:'' ?>" enctype="multipart/form-data" novalidate>
          <?= csrf_field() ?>
          <div class="field"><label><?= htmlspecialchars(t('admin.name')) ?> *</label><input type="text" name="name" value="<?= htmlspecialchars($old['name']) ?>" required></div>
          <div class="field">
            <label><?= htmlspecialchars(t('admin.logo','Logo')) ?></label>
            <?php if (!empty($old['icon'])): ?><img class="preview-img" src="<?= htmlspecialchars($old['icon']) ?>" alt=""><br><?php endif; ?>
            <input type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/svg+xml" style="margin-top:8px;">
            <input type="hidden" name="icon" value="<?= htmlspecialchars($old['icon'] ?? '') ?>">
          </div>
          <div class="grid-2">
            <div class="field"><label><?= htmlspecialchars(t('admin.sort_order')) ?></label><input type="number" name="sort_order" value="<?= (int)$old['sort_order'] ?>"></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.status')) ?></label><input type="checkbox" name="is_active" value="1" <?= (int)$old['is_active']?'checked':'' ?>> <?= htmlspecialchars(t('admin.active')) ?></div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn"><?= htmlspecialchars(t('admin.save')) ?></button>
            <a class="btn btn-ghost" href="index.php"><?= htmlspecialchars(t('admin.cancel')) ?></a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__.'/../components/footer.php'; ?>

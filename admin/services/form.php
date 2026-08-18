<?php
/**
 * admin/services/form.php
 * Services create/edit (icon, title, description, order, status).
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$id = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$row = $editing ? CrudService::find('services', $id) : null;
if ($editing && !$row) { http_response_code(404);
    $admin_page_title = t('admin.not_found','Not Found').' — GAIA TOURS &amp; TRAVEL'; require __DIR__.'/../components/header.php';
    echo '<div class="admin-app"><div class="admin-main"><div class="admin-content"><div class="card"><div class="muted" style="text-align:center;padding:40px;">'.htmlspecialchars(t('admin.not_found_text','Not found.')).'</div></div></div></div></div>'; require __DIR__.'/../components/footer.php'; exit;
}

$errors=[]; $alerts=[];
$old = $row ?: ['title'=>'','description'=>'','icon'=>'fa-solid fa-star','sort_order'=>0,'is_active'=>1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $data = [
        'title'=>trim($_POST['title']??''),
        'description'=>trim($_POST['description']??''),
        'icon'=>trim($_POST['icon']??'fa-solid fa-star'),
        'sort_order'=>(int)($_POST['sort_order']??0),
        'is_active'=>!empty($_POST['is_active'])?1:0,
    ];
    if ($data['title']==='') $errors[]=t('admin.title_req','Title is required.');
    if (!$errors) {
        if ($editing) { CrudService::update('services',$id,$data); $alerts[]=['type'=>'success','msg'=>t('admin.saved','Saved successfully.')]; }
        else { $id=CrudService::insert('services',$data); $editing=true; $alerts[]=['type'=>'success','msg'=>t('admin.created','Created successfully.')]; }
        $row=CrudService::find('services',$id); $old=$row;
    }
}

$account_alerts = array_merge(array_map(fn($e)=>['type'=>'error','msg'=>$e], $errors), $alerts);
$admin_page_title = ($editing?t('admin.edit'):t('admin.create')).' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = $editing&&$row ? $row['title'] : t('admin.add_new');
$admin_active = 'services';
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
          <h3><i class="fa-solid fa-bell-concierge"></i> <?= htmlspecialchars(t('admin.service_info','Service Information')) ?></h3>
          <a class="btn btn-ghost btn-sm" href="index.php"><?= htmlspecialchars(t('admin.back_to_list')) ?></a>
        </div>
        <form method="post" action="form.php<?= $editing?'?id='.(int)$id:'' ?>" novalidate>
          <?= csrf_field() ?>
          <div class="grid-2">
            <div class="field"><label><?= htmlspecialchars(t('admin.title')) ?> *</label><input type="text" name="title" value="<?= htmlspecialchars($old['title']) ?>" required></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.icon')) ?></label><input type="text" name="icon" value="<?= htmlspecialchars($old['icon']) ?>" placeholder="fa-solid fa-car"></div>
          </div>
          <div class="field"><label><?= htmlspecialchars(t('admin.description')) ?> *</label><textarea name="description" rows="3" required><?= htmlspecialchars($old['description']) ?></textarea></div>
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

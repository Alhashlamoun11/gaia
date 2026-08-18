<?php
/**
 * admin/destinations/form.php
 * Destinations create/edit.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$row = $editing ? CrudService::find('destinations', $id) : null;
if ($editing && !$row) { http_response_code(404);
    $admin_page_title = t('admin.not_found','Not Found').' — GAIA TOURS & TRAVEL'; require __DIR__.'/../components/header.php';
    echo '<div class="admin-app"><div class="admin-main"><div class="admin-content"><div class="card"><div class="muted" style="text-align:center;padding:40px;">'.htmlspecialchars(t('admin.not_found_text','Not found.')).'</div></div></div></div></div>'; require __DIR__.'/../components/footer.php'; exit;
}

$errors = []; $alerts = [];
$old = $row ?: ['name'=>'','slug'=>'','country'=>'','description'=>'','image_url'=>'','city'=>'','featured'=>0,'sort_order'=>0,'is_active'=>1,'seo_title'=>'','seo_description'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'slug' => trim($_POST['slug'] ?? ''),
        'country' => trim($_POST['country'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'featured' => !empty($_POST['featured']) ? 1 : 0,
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
        'seo_title' => trim($_POST['seo_title'] ?? ''),
        'seo_description' => trim($_POST['seo_description'] ?? ''),
    ];
    if ($data['name']==='') $errors[]=t('admin.name_req','Name is required.');
    if ($data['slug']==='') $data['slug'] = strtolower(preg_replace('/[^a-z0-9]+/i','-', $data['name']));
    $data['slug'] = preg_replace('/-+/','-', trim($data['slug'],'-'));

    if (!empty($_FILES['image_file']['name']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $up = CrudService::handleUpload($_FILES['image_file'], 'destinations');
        if ($up !== null) $data['image_url']=$up;
        else $errors[]=t('admin.image_invalid','Invalid image.');
    } elseif (isset($_POST['image_url']) && trim($_POST['image_url']) !== '') {
        $data['image_url']=trim($_POST['image_url']);
    }

    if (!$errors) {
        if ($editing) { CrudService::update('destinations',$id,$data); $alerts[]=['type'=>'success','msg'=>t('admin.saved','Saved successfully.')]; }
        else { $id=CrudService::insert('destinations',$data); $editing=true; $alerts[]=['type'=>'success','msg'=>t('admin.created','Created successfully.')]; }
        $row=CrudService::find('destinations',$id); $old=$row;
    }
}

$account_alerts = array_merge(array_map(fn($e)=>['type'=>'error','msg'=>$e], $errors), $alerts);
$admin_page_title = ($editing?t('admin.edit'):t('admin.create')).' — GAIA TOURS & TRAVEL';
$admin_topbar_title = ($editing&&$row)?$row['name']:t('admin.add_new');
$admin_active = 'destinations';
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
          <h3><i class="fa-solid fa-location-dot"></i> <?= $C(t('admin.destination_info','Destination Information')) ?></h3>
          <a class="btn btn-ghost btn-sm" href="index.php"><?= $C(t('admin.back_to_list')) ?></a>
        </div>
        <form method="post" action="form.php<?= $editing?'?id='.(int)$id:'' ?>" enctype="multipart/form-data" novalidate>
          <?= csrf_field() ?>
          <div class="grid-2">
            <div class="field"><label><?= $C(t('admin.name')) ?> *</label><input type="text" name="name" value="<?= $C($old['name']) ?>" required></div>
            <div class="field"><label><?= $C(t('admin.slug')) ?></label><input type="text" name="slug" value="<?= $C($old['slug']) ?>" placeholder="auto-generated"></div>
          </div>
          <div class="grid-2">
            <div class="field"><label><?= $C(t('admin.country')) ?></label><input type="text" name="country" value="<?= $C($old['country']) ?>"></div>
            <div class="field"><label><?= $C(t('admin.city')) ?></label><input type="text" name="city" value="<?= $C($old['city']) ?>"></div>
          </div>
          <div class="field"><label><?= $C(t('admin.description')) ?></label><textarea name="description" rows="4"><?= $C($old['description']) ?></textarea></div>
          <div class="field">
            <label><?= $C(t('admin.image')) ?></label>
            <?php if (!empty($old['image_url'])): ?><img class="preview-img" src="<?= $C($old['image_url']) ?>" alt=""><br><?php endif; ?>
            <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp" style="margin-top:8px;">
            <input type="hidden" name="image_url" value="<?= $C($old['image_url'] ?? '') ?>">
          </div>
          <div class="grid-3">
            <div class="field"><label><?= $C(t('admin.seo_title','SEO Title')) ?></label><input type="text" name="seo_title" value="<?= $C($old['seo_title']) ?>"></div>
            <div class="field" style="grid-column:span 2;"><label><?= $C(t('admin.seo_description','SEO Description')) ?></label><input type="text" name="seo_description" value="<?= $C($old['seo_description']) ?>"></div>
          </div>
          <div class="grid-3">
            <div class="field"><label><?= $C(t('admin.sort_order')) ?></label><input type="number" name="sort_order" value="<?= (int)($old['sort_order']??0) ?>"></div>
            <div class="field"><label><?= $C(t('admin.featured')) ?></label><input type="checkbox" name="featured" value="1" <?= (int)($old['featured']??0)?'checked':'' ?>></div>
            <div class="field"><label><?= $C(t('admin.status')) ?></label><input type="checkbox" name="is_active" value="1" <?= (int)$old['is_active']?'checked':'' ?>> <?= $C(t('admin.active')) ?></div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn"><?= $C(t('admin.save')) ?></button>
            <a class="btn btn-ghost" href="index.php"><?= $C(t('admin.cancel')) ?></a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__.'/../components/footer.php'; ?>
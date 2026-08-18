<?php
/**
 * admin/events/form.php
 * Events create/edit with slug, gallery, destination, featured, SEO.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$id = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$row = $editing ? CrudService::find('events', $id) : null;
if ($editing && !$row) { http_response_code(404);
    $admin_page_title = t('admin.not_found','Not Found').' — GAIA TOURS & TRAVEL'; require __DIR__.'/../components/header.php';
    echo '<div class="admin-app"><div class="admin-main"><div class="admin-content"><div class="card"><div class="muted" style="text-align:center;padding:40px;">'.htmlspecialchars(t('admin.not_found_text','Not found.')).'</div></div></div></div></div>'; require __DIR__.'/../components/footer.php'; exit;
}

// Destinations for dropdown
$destinations = $pdo->query("SELECT id, name FROM destinations WHERE is_active = 1 ORDER BY name")->fetchAll();

$errors=[]; $alerts=[];
$old = $row ?: ['title'=>'','slug'=>'','description'=>'','image_url'=>'','gallery_urls'=>'','date_label'=>'','icon'=>'','location'=>'','category'=>'home','destination_id'=>null,'price'=>0,'capacity'=>0,'featured'=>0,'sort_order'=>0,'is_active'=>1,'seo_title'=>'','seo_description'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $data = [
        'title'=>trim($_POST['title']??''),
        'slug'=>trim($_POST['slug']??''),
        'description'=>trim($_POST['description']??''),
        'date_label'=>trim($_POST['date_label']??''),
        'icon'=>trim($_POST['icon']??''),
        'location'=>trim($_POST['location']??''),
        'category'=>trim($_POST['category']??'home'),
        'destination_id'=>((int)($_POST['destination_id']??0)) > 0 ? (int)$_POST['destination_id'] : null,
        'price'=>(float)($_POST['price']??0),
        'capacity'=>max(0,(int)($_POST['capacity']??0)),
        'featured'=>!empty($_POST['featured'])?1:0,
        'sort_order'=>(int)($_POST['sort_order']??0),
        'is_active'=>!empty($_POST['is_active'])?1:0,
        'seo_title'=>trim($_POST['seo_title']??''),
        'seo_description'=>trim($_POST['seo_description']??''),
    ];
    if ($data['title']==='') $errors[]=t('admin.title_req','Title is required.');
    if ($data['slug']==='') $data['slug'] = strtolower(preg_replace('/[^a-z0-9]+/i','-', $data['title']));
    $data['slug'] = preg_replace('/-+/','-', trim($data['slug'],'-'));

    if (!empty($_FILES['image_file']['name']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $up = CrudService::handleUpload($_FILES['image_file'], 'events');
        if ($up !== null) $data['image_url']=$up;
        else $errors[]=t('admin.image_invalid','Invalid image.');
    } elseif (isset($_POST['image_url']) && trim($_POST['image_url']) !== '') {
        $data['image_url']=trim($_POST['image_url']);
    }
    $gallery = trim($_POST['gallery_urls'] ?? '');
    $data['gallery_urls'] = $gallery !== '' ? $gallery : null;

    if (!$errors) {
        if ($editing) { CrudService::update('events',$id,$data); $alerts[]=['type'=>'success','msg'=>t('admin.saved','Saved successfully.')]; }
        else { $id=CrudService::insert('events',$data); $editing=true; $alerts[]=['type'=>'success','msg'=>t('admin.created','Created successfully.')]; }
        $row=CrudService::find('events',$id); $old=$row;
    }
}

$account_alerts = array_merge(array_map(fn($e)=>['type'=>'error','msg'=>$e], $errors), $alerts);
$admin_page_title = ($editing?t('admin.edit'):t('admin.create')).' — GAIA TOURS & TRAVEL';
$admin_topbar_title = ($editing&&$row)?$row['title']:t('admin.add_event','Add Event');
$admin_active = 'events';
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
          <h3><i class="fa-solid fa-calendar-check"></i> <?= $C(t('admin.event_info','Event Information')) ?></h3>
          <a class="btn btn-ghost btn-sm" href="index.php"><?= $C(t('admin.back_to_list')) ?></a>
        </div>
        <form method="post" action="form.php<?= $editing?'?id='.(int)$id:'' ?>" enctype="multipart/form-data" novalidate>
          <?= csrf_field() ?>
          <div class="grid-2">
            <div class="field"><label><?= $C(t('admin.title')) ?> *</label><input type="text" name="title" value="<?= $C($old['title']) ?>" required></div>
            <div class="field"><label><?= $C(t('admin.slug')) ?></label><input type="text" name="slug" value="<?= $C($old['slug']) ?>" placeholder="auto-generated"></div>
          </div>
          <div class="grid-2">
            <div class="field"><label><?= $C(t('admin.location')) ?></label><input type="text" name="location" value="<?= $C($old['location']) ?>"></div>
            <div class="field"><label><?= $C(t('admin.date_label','Date Label')) ?></label><input type="text" name="date_label" value="<?= $C($old['date_label']) ?>" placeholder="MAR 2025 · AMMAN"></div>
          </div>
          <div class="grid-2">
            <div class="field"><label><?= $C(t('admin.category')) ?></label><input type="text" name="category" value="<?= $C($old['category']) ?>"></div>
            <div class="field"><label><?= $C(t('admin.destination')) ?></label>
              <select name="destination_id">
                <option value=""><?= $C(t('admin.select','Select')) ?></option>
                <?php foreach ($destinations as $d): ?>
                  <option value="<?= (int)$d['id'] ?>" <?= (int)($old['destination_id']??0)===(int)$d['id']?'selected':'' ?>><?= $C($d['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="field"><label><?= $C(t('admin.description')) ?></label><textarea name="description" rows="4"><?= $C($old['description']) ?></textarea></div>
          <div class="grid-3">
            <div class="field"><label><?= $C(t('admin.price')) ?></label><input type="number" step="0.01" name="price" value="<?= $C($old['price']) ?>"></div>
            <div class="field"><label><?= $C(t('admin.capacity')) ?></label><input type="number" name="capacity" value="<?= (int)$old['capacity'] ?>"></div>
            <div class="field"><label><?= $C(t('admin.icon')) ?></label><input type="text" name="icon" value="<?= $C($old['icon']) ?>" placeholder="fa-solid fa-music"></div>
          </div>
          <div class="grid-2">
            <div class="field">
              <label><?= $C(t('admin.image')) ?></label>
              <?php if (!empty($old['image_url'])): ?><img class="preview-img" src="<?= $C($old['image_url']) ?>" alt=""><br><?php endif; ?>
              <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp" style="margin-top:8px;">
              <input type="hidden" name="image_url" value="<?= $C($old['image_url'] ?? '') ?>">
            </div>
            <div class="field"><label><?= $C(t('admin.gallery_urls','Gallery URLs (comma-separated)')) ?></label><textarea name="gallery_urls" rows="3" placeholder="https://...,https://..."><?= $C($old['gallery_urls']) ?></textarea></div>
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
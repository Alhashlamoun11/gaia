<?php
/**
 * admin/tours/form.php
 * Tours create/edit (weroad_trips).
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$row = $editing ? CrudService::find('weroad_trips', $id) : null;
if ($editing && !$row) { http_response_code(404);
    $admin_page_title = t('admin.not_found','Not Found').' — GAIA TOURS &amp; TRAVEL'; require __DIR__.'/../components/header.php';
    echo '<div class="admin-app"><div class="admin-main"><div class="admin-content"><div class="card"><div class="muted" style="text-align:center;padding:40px;">'.htmlspecialchars(t('admin.not_found_text','Not found.')).'</div></div></div></div></div>'; require __DIR__.'/../components/footer.php'; exit;
}

$errors = []; $alerts = [];
$old = $row ?: ['name'=>'','slug'=>'','tagline'=>'','description'=>'','duration_days'=>1,'max_group_size'=>20,'age_range'=>'18–39','effort_level'=>'moderate','comfort_level'=>'','accommodation'=>'','category'=>'','base_price'=>0,'discount_percent'=>0,'rating'=>0,'reviews_count'=>0,'image_url'=>'','gallery_urls'=>'','is_active'=>1,'featured'=>0,'seo_title'=>'','seo_description'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'slug' => trim($_POST['slug'] ?? ''),
        'tagline' => trim($_POST['tagline'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'duration_days' => max(1,(int)($_POST['duration_days'] ?? 1)),
        'max_group_size' => max(1,(int)($_POST['max_group_size'] ?? 20)),
        'age_range' => trim($_POST['age_range'] ?? '18–39'),
        'effort_level' => in_array($_POST['effort_level'] ?? 'moderate', ['easy','moderate','hard'], true) ? $_POST['effort_level'] : 'moderate',
        'comfort_level' => trim($_POST['comfort_level'] ?? ''),
        'accommodation' => trim($_POST['accommodation'] ?? ''),
        'category' => trim($_POST['category'] ?? ''),
        'base_price' => (float)($_POST['base_price'] ?? 0),
        'discount_percent' => max(0,(float)($_POST['discount_percent'] ?? 0)),
        'rating' => max(0,min(5,(float)($_POST['rating'] ?? 0))),
        'reviews_count' => max(0,(int)($_POST['reviews_count'] ?? 0)),
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
        'featured' => !empty($_POST['featured']) ? 1 : 0,
        'seo_title' => trim($_POST['seo_title'] ?? ''),
        'seo_description' => trim($_POST['seo_description'] ?? ''),
    ];
    if ($data['name'] === '') $errors[] = t('admin.name_req','Name is required.');
    if ($data['slug'] === '') $data['slug'] = strtolower(preg_replace('/[^a-z0-9]+/i','-', $data['name']));
    $data['slug'] = preg_replace('/-+/','-', trim($data['slug'],'-'));

    if (!empty($_FILES['image_file']['name']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $up = CrudService::handleUpload($_FILES['image_file'], 'tours');
        if ($up !== null) $data['image_url'] = $up;
        else $errors[] = t('admin.image_invalid','Invalid image.');
    } elseif (isset($_POST['image_url']) && trim($_POST['image_url']) !== '') {
        $data['image_url'] = trim($_POST['image_url']);
    }
    $gallery = trim($_POST['gallery_urls'] ?? '');
    $data['gallery_urls'] = $gallery !== '' ? $gallery : null;

    if (!$errors) {
        if ($editing) { CrudService::update('weroad_trips', $id, $data); $alerts[]=['type'=>'success','msg'=>t('admin.saved','Saved successfully.')]; }
        else { $id = CrudService::insert('weroad_trips', $data); $editing=true; $alerts[]=['type'=>'success','msg'=>t('admin.created','Created successfully.')]; }
        $row = CrudService::find('weroad_trips', $id); $old = $row;
    }
}

$account_alerts = array_merge(array_map(fn($e)=>['type'=>'error','msg'=>$e], $errors), $alerts);
$admin_page_title = ($editing?t('admin.edit'):t('admin.create')).' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = ($editing&&$row)?$row['name']:t('admin.add_tour','Add Tour');
$admin_active = 'tours';
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
          <h3><i class="fa-solid fa-mountain-sun"></i> <?= htmlspecialchars(t('admin.tour_info','Tour Information')) ?></h3>
          <a class="btn btn-ghost btn-sm" href="index.php"><?= htmlspecialchars(t('admin.back_to_list')) ?></a>
        </div>
        <form method="post" action="form.php<?= $editing?'?id='.(int)$id:'' ?>" enctype="multipart/form-data" novalidate>
          <?= csrf_field() ?>
          <div class="grid-2">
            <div class="field"><label><?= htmlspecialchars(t('admin.name')) ?> *</label><input type="text" name="name" value="<?= htmlspecialchars($old['name']) ?>" required></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.slug')) ?></label><input type="text" name="slug" value="<?= htmlspecialchars($old['slug']) ?>" placeholder="auto-generated"></div>
          </div>
          <div class="grid-2">
            <div class="field"><label><?= htmlspecialchars(t('admin.tagline','Tagline')) ?></label><input type="text" name="tagline" value="<?= htmlspecialchars($old['tagline']) ?>"></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.category')) ?></label><input type="text" name="category" value="<?= htmlspecialchars($old['category']) ?>"></div>
          </div>
          <div class="field"><label><?= htmlspecialchars(t('admin.description')) ?></label><textarea name="description" rows="4"><?= htmlspecialchars($old['description']) ?></textarea></div>
          <div class="grid-3">
            <div class="field"><label><?= htmlspecialchars(t('admin.days','Days')) ?></label><input type="number" name="duration_days" value="<?= (int)$old['duration_days'] ?>"></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.max_group','Max Group Size')) ?></label><input type="number" name="max_group_size" value="<?= (int)$old['max_group_size'] ?>"></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.effort','Effort')) ?></label>
              <select name="effort_level">
                <?php foreach (['easy','moderate','hard'] as $eff): ?><option value="<?=$eff?>" <?=$old['effort_level']===$eff?'selected':''?>><?=htmlspecialchars(ucfirst($eff))?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="grid-3">
            <div class="field"><label><?= htmlspecialchars(t('admin.base_price')) ?></label><input type="number" step="0.01" name="base_price" value="<?= htmlspecialchars($old['base_price']) ?>"></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.discount_pct','Discount %')) ?></label><input type="number" step="0.01" name="discount_percent" value="<?= htmlspecialchars($old['discount_percent']) ?>"></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.rating')) ?></label><input type="number" step="0.1" name="rating" value="<?= htmlspecialchars($old['rating']) ?>"></div>
          </div>
          <div class="grid-2">
            <div class="field"><label><?= htmlspecialchars(t('admin.age_range','Age Range')) ?></label><input type="text" name="age_range" value="<?= htmlspecialchars($old['age_range']) ?>"></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.comfort','Comfort Level')) ?></label><input type="text" name="comfort_level" value="<?= htmlspecialchars($old['comfort_level']) ?>"></div>
          </div>
          <div class="field"><label><?= htmlspecialchars(t('admin.accommodation')) ?></label><input type="text" name="accommodation" value="<?= htmlspecialchars($old['accommodation']) ?>"></div>
          <div class="grid-2">
            <div class="field">
              <label><?= htmlspecialchars(t('admin.image')) ?></label>
              <?php if (!empty($old['image_url'])): ?><img class="preview-img" src="<?= htmlspecialchars($old['image_url']) ?>" alt=""><br><?php endif; ?>
              <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp" style="margin-top:8px;">
              <input type="hidden" name="image_url" value="<?= htmlspecialchars($old['image_url'] ?? '') ?>">
            </div>
            <div class="field"><label><?= htmlspecialchars(t('admin.gallery_urls','Gallery URLs (comma-separated)')) ?></label><textarea name="gallery_urls" rows="3" placeholder="https://...,https://..."><?= htmlspecialchars($old['gallery_urls']) ?></textarea></div>
          </div>
          <div class="grid-3">
            <div class="field"><label><?= htmlspecialchars(t('admin.seo_title','SEO Title')) ?></label><input type="text" name="seo_title" value="<?= htmlspecialchars($old['seo_title']) ?>"></div>
            <div class="field" style="grid-column:span 2;"><label><?= htmlspecialchars(t('admin.seo_description','SEO Description')) ?></label><input type="text" name="seo_description" value="<?= htmlspecialchars($old['seo_description']) ?>"></div>
          </div>
          <div class="grid-2">
            <div class="field"><label><?= htmlspecialchars(t('admin.status')) ?></label><input type="checkbox" name="is_active" value="1" <?= (int)$old['is_active']?'checked':'' ?>> <?= htmlspecialchars(t('admin.active')) ?></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.featured')) ?></label><input type="checkbox" name="featured" value="1" <?= (int)$old['featured']?'checked':'' ?>></div>
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

<?php
/**
 * admin/hotels/form.php
 * Hotels create/edit form with image upload + SEO fields.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$row = $editing ? CrudService::find('hotels', $id) : null;
if ($editing && !$row) {
    http_response_code(404);
    $admin_page_title = t('admin.not_found', 'Not Found').' — GAIA TOURS &amp; TRAVEL';
    require __DIR__ . '/../components/header.php';
    echo '<div class="admin-app"><div class="admin-main"><div class="admin-content"><div class="card"><div class="muted" style="text-align:center;padding:40px;">' . htmlspecialchars(t('admin.not_found_text', 'Not found.')) . '</div></div></div></div></div>';
    require __DIR__ . '/../components/footer.php';
    exit;
}

$alerts = []; $errors = [];
$old = $row ?: [
    'name'=>'', 'slug'=>'', 'city'=>'', 'country'=>'', 'star_rating'=>3,'price_from'=>0,
    'admin_commission_percent'=>0,
    'description'=>'','image_url'=>'','gallery_urls'=>'','featured'=>0,'is_active'=>1,
    'seo_title'=>'','seo_description'=>''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();

    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'slug' => trim($_POST['slug'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'country' => trim($_POST['country'] ?? ''),
        'star_rating' => max(1, min(5, (int)($_POST['star_rating'] ?? 3))),
        'price_from' => (float)($_POST['price_from'] ?? 0),
        'admin_commission_percent' => (float)($_POST['admin_commission_percent'] ?? 0),
        'description' => trim($_POST['description'] ?? ''),
        'featured' => !empty($_POST['featured']) ? 1 : 0,
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
        'seo_title' => trim($_POST['seo_title'] ?? ''),
        'seo_description' => trim($_POST['seo_description'] ?? ''),
    ];
    if ($data['name'] === '') $errors[] = t('admin.name_req', 'Name is required.');
    if ($data['slug'] === '') $data['slug'] = strtolower(preg_replace('/[^a-z0-9]+/i','-', $data['name']));
    $data['slug'] = preg_replace('/-+/','-', trim($data['slug'],'-'));

    // image upload
    if (!empty($_FILES['image_file']['name']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $up = CrudService::handleUpload($_FILES['image_file'], 'hotels');
        if ($up !== null) $data['image_url'] = $up;
        else $errors[] = t('admin.image_invalid', 'Invalid image upload.');
    } elseif (isset($_POST['image_url']) && trim($_POST['image_url']) !== '') {
        $data['image_url'] = trim($_POST['image_url']);
    }

    $gallery = trim($_POST['gallery_urls'] ?? '');
    $data['gallery_urls'] = $gallery !== '' ? $gallery : null;

    if (!$errors) {
        if ($editing) {
            CrudService::update('hotels', $id, $data);
            $alerts[] = ['type'=>'success','msg'=>t('admin.saved','Saved successfully.')];
        } else {
            $id = CrudService::insert('hotels', $data);
            $editing = true;
            $alerts[] = ['type'=>'success','msg'=>t('admin.created','Created successfully.')];
        }
        $row = CrudService::find('hotels', $id);
        $old = $row;
    }
}

$account_alerts = array_merge(array_map(fn($e)=>['type'=>'error','msg'=>$e], $errors), $alerts);
$admin_page_title = ($editing?t('admin.edit'):t('admin.create')).' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = ($editing&&$row)?$row['name']:t('admin.add_hotel','Add Hotel');
$admin_active = 'hotels';
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
          <h3><i class="fa-solid fa-hotel"></i> <?= htmlspecialchars(t('admin.hotel_info','Hotel Information')) ?></h3>
          <a class="btn btn-ghost btn-sm" href="index.php"><?= htmlspecialchars(t('admin.back_to_list','Back to list')) ?></a>
        </div>

        <form method="post" action="form.php<?= $editing?'?id='.(int)$id:'' ?>" enctype="multipart/form-data" novalidate>
          <?= csrf_field() ?>
          <div class="grid-2">
            <div class="field"><label><?= htmlspecialchars(t('admin.name')) ?> *</label><input type="text" name="name" value="<?= htmlspecialchars($old['name']) ?>" required></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.slug')) ?></label><input type="text" name="slug" value="<?= htmlspecialchars($old['slug']) ?>" placeholder="auto-generated"></div>
          </div>
          <div class="grid-2">
            <div class="field"><label><?= htmlspecialchars(t('admin.city')) ?></label><input type="text" name="city" value="<?= htmlspecialchars($old['city']) ?>"></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.country')) ?></label><input type="text" name="country" value="<?= htmlspecialchars($old['country']) ?>"></div>
          </div>
          <div class="grid-3">
            <div class="field"><label><?= htmlspecialchars(t('admin.stars')) ?></label>
              <select name="star_rating">
                <?php for ($i=1;$i<=5;$i++): ?><option value="<?=$i?>" <?= (int)$old['star_rating']===$i?'selected':'' ?>><?=$i?> ★</option><?php endfor; ?>
              </select>
            </div>
            <div class="field"><label><?= htmlspecialchars(t('admin.price','Price')) ?></label><input type="number" step="0.01" name="price_from" value="<?= htmlspecialchars($old['price_from']) ?>" /></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.admin_commission','Admin Commission (%)')) ?></label><input type="number" step="0.01" name="admin_commission_percent" value="<?= htmlspecialchars($old['admin_commission_percent']) ?>" /></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.featured')) ?></label><input type="checkbox" name="featured" value="1" <?= (int)$old['featured']?'checked':'' ?>></div>
          </div>
          <div class="field"><label><?= htmlspecialchars(t('admin.description')) ?></label><textarea name="description" rows="4"><?= htmlspecialchars($old['description']) ?></textarea></div>
          <div class="grid-2">
            <div class="field">
              <label><?= htmlspecialchars(t('admin.image')) ?></label>
              <?php if (!empty($old['image_url'])): ?><img class="preview-img" src="<?= htmlspecialchars($old['image_url']) ?>" alt=""><br><?php endif; ?>
              <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp" style="margin-top:8px;">
              <input type="hidden" name="image_url" value="<?= htmlspecialchars($old['image_url'] ?? '') ?>">
            </div>
            <div class="field"><label><?= htmlspecialchars(t('admin.gallery_urls','Gallery URLs (one per line)')) ?></label><textarea name="gallery_urls" rows="3" placeholder="https://...&#10;https://..."><?= htmlspecialchars($old['gallery_urls']) ?></textarea></div>
          </div>
          <div class="grid-3">
            <div class="field"><label><?= htmlspecialchars(t('admin.seo_title','SEO Title')) ?></label><input type="text" name="seo_title" value="<?= htmlspecialchars($old['seo_title']) ?>"></div>
            <div class="field" style="grid-column:span 2;"><label><?= htmlspecialchars(t('admin.seo_description','SEO Description')) ?></label><input type="text" name="seo_description" value="<?= htmlspecialchars($old['seo_description']) ?>"></div>
          </div>
          <div class="field"><label><?= htmlspecialchars(t('admin.status')) ?></label><input type="checkbox" name="is_active" value="1" <?= (int)$old['is_active']?'checked':'' ?>> <?= htmlspecialchars(t('admin.active')) ?></div>
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

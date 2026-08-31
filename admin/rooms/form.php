<?php
/**
 * admin/rooms/form.php
 * Rooms create/edit.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$row = $editing ? CrudService::find('hotel_rooms', $id) : null;
if ($editing && !$row) { http_response_code(404);
    $admin_page_title = t('admin.not_found','Not Found').' — GAIA TOURS &amp; TRAVEL'; require __DIR__.'/../components/header.php';
    echo '<div class="admin-app"><div class="admin-main"><div class="admin-content"><div class="card"><div class="muted" style="text-align:center;padding:40px;">'.htmlspecialchars(t('admin.not_found_text','Not found.')).'</div></div></div></div></div>'; require __DIR__.'/../components/footer.php'; exit;
}

$hotels = $pdo->query("SELECT id,name FROM hotels ORDER BY name")->fetchAll();
$errors = []; $alerts = [];
$old = $row ?: ['hotel_id'=>0,'name'=>'','description'=>'','price'=>0,'capacity'=>2,'quantity'=>1,'image_url'=>'','beds'=>'','size_sqm'=>null,'facilities'=>'','gallery_urls'=>'','sort_order'=>0,'is_active'=>1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $data = [
        'hotel_id' => (int)($_POST['hotel_id'] ?? 0),
        'name' => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'price' => (float)($_POST['price'] ?? 0),
        'capacity' => max(1,(int)($_POST['capacity'] ?? 2)),
        'quantity' => max(0,(int)($_POST['quantity'] ?? 1)),
        'beds' => trim($_POST['beds'] ?? ''),
        'size_sqm' => ($_POST['size_sqm'] ?? '') !== '' ? max(0,(int)$_POST['size_sqm']) : null,
        'facilities' => trim($_POST['facilities'] ?? ''),
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
    ];
    $gallery = trim($_POST['gallery_urls'] ?? '');
    $data['gallery_urls'] = $gallery !== '' ? $gallery : null;
    if ($data['hotel_id'] <= 0) $errors[] = t('admin.hotel_req','Hotel is required.');
    if ($data['name'] === '') $errors[] = t('admin.name_req','Name is required.');
    if (!empty($_FILES['image_file']['name']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $up = CrudService::handleUpload($_FILES['image_file'], 'rooms');
        if ($up !== null) $data['image_url'] = $up;
        else $errors[] = t('admin.image_invalid','Invalid image.');
    } elseif (isset($_POST['image_url']) && trim($_POST['image_url']) !== '') {
        $data['image_url'] = trim($_POST['image_url']);
    }

    if (!$errors) {
        if ($editing) { CrudService::update('hotel_rooms', $id, $data); $alerts[]=['type'=>'success','msg'=>t('admin.saved','Saved successfully.')]; }
        else { $id = CrudService::insert('hotel_rooms', $data); $editing=true; $alerts[]=['type'=>'success','msg'=>t('admin.created','Created successfully.')]; }
        $row = CrudService::find('hotel_rooms', $id); $old = $row;
    }
}

$account_alerts = array_merge(array_map(fn($e)=>['type'=>'error','msg'=>$e], $errors), $alerts);
$admin_page_title = ($editing?t('admin.edit'):t('admin.create')).' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = ($editing&&$row)?$row['name']:t('admin.add_room','Add Room');
$admin_active = 'rooms';
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
          <h3><i class="fa-solid fa-bed"></i> <?= htmlspecialchars(t('admin.room_info','Room Information')) ?></h3>
          <a class="btn btn-ghost btn-sm" href="index.php"><?= htmlspecialchars(t('admin.back_to_list')) ?></a>
        </div>
        <form method="post" action="form.php<?= $editing?'?id='.(int)$id:'' ?>" enctype="multipart/form-data" novalidate>
          <?= csrf_field() ?>
          <div class="grid-2">
            <div class="field"><label><?= htmlspecialchars(t('admin.hotel')) ?> *</label>
              <select name="hotel_id">
                <option value=""><?= htmlspecialchars(t('admin.select','Select')) ?></option>
                <?php foreach ($hotels as $h): ?><option value="<?= (int)$h['id'] ?>" <?= (int)$old['hotel_id']===(int)$h['id']?'selected':'' ?>><?= htmlspecialchars($h['name']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="field"><label><?= htmlspecialchars(t('admin.name')) ?> *</label><input type="text" name="name" value="<?= htmlspecialchars($old['name']) ?>" required></div>
          </div>
          <div class="field"><label><?= htmlspecialchars(t('admin.description')) ?></label><textarea name="description" rows="3"><?= htmlspecialchars($old['description']) ?></textarea></div>
          <div class="grid-3">
            <div class="field"><label><?= htmlspecialchars(t('admin.price')) ?></label><input type="number" step="0.01" name="price" value="<?= htmlspecialchars($old['price']) ?>"></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.capacity')) ?></label><input type="number" name="capacity" value="<?= (int)$old['capacity'] ?>"></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.quantity', 'Quantity')) ?></label><input type="number" name="quantity" value="<?= (int)$old['quantity'] ?>"></div>
          </div>
          <div class="grid-3">
            <div class="field"><label><?= htmlspecialchars(t('admin.beds', 'Beds')) ?></label><input type="text" name="beds" value="<?= htmlspecialchars($old['beds'] ?? '') ?>" placeholder="1 Queen Bed"></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.size_sqm', 'Size (m²)')) ?></label><input type="number" name="size_sqm" value="<?= htmlspecialchars(($old['size_sqm'] ?? '') !== '' ? (int)$old['size_sqm'] : '') ?>" placeholder="30"></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.sort_order')) ?></label><input type="number" name="sort_order" value="<?= (int)$old['sort_order'] ?>"></div>
          </div>
          <div class="field"><label><?= htmlspecialchars(t('admin.room_facilities', 'Room Facilities')) ?></label><input type="text" name="facilities" value="<?= htmlspecialchars($old['facilities'] ?? '') ?>" placeholder="WiFi, AC, Mini Bar"></div>
          <div class="grid-2">
            <div class="field">
              <label><?= htmlspecialchars(t('admin.image')) ?></label>
              <?php if (!empty($old['image_url'])): ?><img class="preview-img" src="<?= htmlspecialchars($old['image_url']) ?>" alt=""><br><?php endif; ?>
              <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp" style="margin-top:8px;">
              <input type="hidden" name="image_url" value="<?= htmlspecialchars($old['image_url'] ?? '') ?>">
            </div>
            <div class="field"><label><?= htmlspecialchars(t('admin.gallery_urls', 'Gallery URLs (one per line)')) ?></label><textarea name="gallery_urls" rows="3" placeholder="https://...&#10;https://..."><?= htmlspecialchars($old['gallery_urls'] ?? '') ?></textarea></div>
          </div>
          <div class="field"><label> <?= htmlspecialchars(t('admin.status')) ?></label><input type="checkbox" name="is_active" value="1" <?= (int)$old['is_active']?'checked':'' ?>> <?= htmlspecialchars(t('admin.active')) ?></div>
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

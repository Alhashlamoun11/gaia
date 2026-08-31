<?php
/**
 * admin/hotels/form-detail.php
 * ============================================================
 * Create/Edit hotel sub-entities (facilities, offers, amenities).
 * Bilingual fields via lang_value (EN/AR). Image upload for offers.
 * ============================================================
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$hotelId = (int)($_GET['hotel_id'] ?? 0);
$hotel = $hotelId > 0 ? CrudService::find('hotels', $hotelId) : null;
if (!$hotel) {
    http_response_code(404);
    $admin_page_title = t('admin.not_found','Not Found').' — GAIA TOURS &amp; TRAVEL';
    require __DIR__.'/../components/header.php';
    echo '<div class="admin-app"><div class="admin-main"><div class="admin-content"><div class="card"><div class="muted" style="text-align:center;padding:40px;">'.htmlspecialchars(t('admin.not_found_text','Not found.')).'</div></div></div></div></div>';
    require __DIR__.'/../components/footer.php'; exit;
}

$tab = $_GET['tab'] ?? 'facilities';
$tabs = ['facilities','offers'];
if (!in_array($tab, $tabs, true)) $tab = 'facilities';
$tableMap = [
    'facilities'=>'hotel_facilities',
    'offers'=>'hotel_offers',
];
$table = $tableMap[$tab];

$id = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$row = $editing ? CrudService::find($table, $id) : null;
if ($editing && !$row) {
    http_response_code(404);
    $admin_page_title = t('admin.not_found','Not Found').' — GAIA TOURS &amp; TRAVEL';
    require __DIR__.'/../components/header.php';
    echo '<div class="admin-app"><div class="admin-main"><div class="admin-content"><div class="card"><div class="muted" style="text-align:center;padding:40px;">'.htmlspecialchars(t('admin.not_found_text','Not found.')).'</div></div></div></div></div>';
    require __DIR__.'/../components/footer.php'; exit;
}

$errors = []; $alerts = [];
$defs = [
    'facilities'=>['name'=>'','name_ar'=>'','icon'=>'fa-solid fa-check','sort_order'=>0],
    'offers'=>['title'=>'','title_ar'=>'','description'=>'','description_ar'=>'','price'=>0,'image_url'=>'','sort_order'=>0,'is_active'=>1],
    'amenities'=>['name'=>'','name_ar'=>'','icon'=>'fa-solid fa-check','sort_order'=>0],
];
$old = $row ?: $defs[$tab];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $data = ['hotel_id' => $hotelId, 'sort_order' => (int)($_POST['sort_order'] ?? 0)];
    if ($tab === 'offers') {
        $data['is_active'] = !empty($_POST['is_active']) ? 1 : 0;
    }
    switch ($tab) {
        case 'facilities':
            $data['name'] = trim($_POST['facility'] ?? '');
            $data['name_ar'] = trim($_POST['facility_ar'] ?? '');
            $data['icon'] = trim($_POST['icon'] ?? 'fa-solid fa-check');
            if ($data['name'] === '') $errors[] = t('admin.name_req','Name is required.');
            break;
        case 'offers':
            $data['title'] = trim($_POST['title'] ?? '');
            $data['title_ar'] = trim($_POST['title_ar'] ?? '');
            $data['description'] = trim($_POST['description'] ?? '');
            $data['description_ar'] = trim($_POST['description_ar'] ?? '');
            $data['price'] = (float)($_POST['price'] ?? 0);
            if ($data['title'] === '') $errors[] = t('admin.title_req','Title is required.');
            if (!empty($_FILES['image_file']['name']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $up = CrudService::handleUpload($_FILES['image_file'], 'hotel_offers');
                if ($up !== null) $data['image_url'] = $up;
                else $errors[] = t('admin.image_invalid','Invalid image.');
            } elseif (isset($_POST['image_url']) && trim($_POST['image_url']) !== '') {
                $data['image_url'] = trim($_POST['image_url']);
            }
            break;
        case 'amenities':
            $data['name'] = trim($_POST['amenity'] ?? '');
            $data['name_ar'] = trim($_POST['amenity_ar'] ?? '');
            $data['icon'] = trim($_POST['icon'] ?? 'fa-solid fa-check');
            if ($data['name'] === '') $errors[] = t('admin.name_req','Name is required.');
            break;
    }
    if (!$errors) {
        if ($editing) {
            CrudService::update($table, $id, $data);
            $alerts[] = ['type'=>'success','msg'=>t('admin.saved','Saved successfully.')];
        } else {
            $id = CrudService::insert($table, $data);
            $editing = true;
            $alerts[] = ['type'=>'success','msg'=>t('admin.created','Created successfully.')];
        }
        $row = CrudService::find($table, $id); $old = $row;
    }
}

$account_alerts = array_merge(array_map(fn($e)=>['type'=>'error','msg'=>$e], $errors), $alerts);
$admin_page_title = ($editing?t('admin.edit'):t('admin.create')).' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = $hotel['name'];
$admin_active = 'hotels';
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
          <h3><i class="fa-solid fa-hotel"></i> <?= $C($hotel['name']) ?> — <?= $C(t('admin.hotel_'.$tab, ucfirst($tab))) ?></h3>
          <a class="btn btn-ghost btn-sm" href="detail.php?id=<?= (int)$hotelId ?>&tab=<?= $C($tab) ?>"><?= $C(t('admin.back_to_list')) ?></a>
        </div>
        <form method="post" action="form-detail.php?hotel_id=<?= (int)$hotelId ?>&tab=<?= $C($tab) ?><?= $editing?'&id='.(int)$id:'' ?>" enctype="multipart/form-data" novalidate>
          <?= csrf_field() ?>

          <?php if ($tab === 'facilities'): ?>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.facility','Facility')) ?> (EN) *</label><input type="text" name="facility" value="<?= $C($old['name'] ?? '') ?>" required></div>
              <div class="field"><label><?= $C(t('admin.facility','Facility')) ?> (AR)</label><input type="text" name="facility_ar" value="<?= $C($old['name_ar'] ?? '') ?>"></div>
            </div>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.icon')) ?></label><input type="text" name="icon" value="<?= $C($old['icon'] ?? '') ?>" placeholder="fa-solid fa-wifi"></div>
              <div class="field"><label><?= $C(t('admin.sort_order')) ?></label><input type="number" name="sort_order" value="<?= (int)($old['sort_order'] ?? 0) ?>"></div>
            </div>
          <?php elseif ($tab === 'offers'): ?>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.title')) ?> (EN) *</label><input type="text" name="title" value="<?= $C($old['title'] ?? '') ?>" required></div>
              <div class="field"><label><?= $C(t('admin.title')) ?> (AR)</label><input type="text" name="title_ar" value="<?= $C($old['title_ar'] ?? '') ?>"></div>
            </div>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.description')) ?> (EN)</label><textarea name="description" rows="3"><?= $C($old['description'] ?? '') ?></textarea></div>
              <div class="field"><label><?= $C(t('admin.description')) ?> (AR)</label><textarea name="description_ar" rows="3"><?= $C($old['description_ar'] ?? '') ?></textarea></div>
            </div>
            <div class="grid-3">
              <div class="field"><label><?= $C(t('admin.price')) ?></label><input type="number" step="0.01" name="price" value="<?= $C($old['price'] ?? 0) ?>"></div>
              <div class="field"><label><?= $C(t('admin.sort_order')) ?></label><input type="number" name="sort_order" value="<?= (int)($old['sort_order'] ?? 0) ?>"></div>
              <div class="field">
                <label><?= $C(t('admin.image')) ?></label>
                <?php if (!empty($old['image_url'])): ?><img class="preview-img" src="<?= $C($old['image_url']) ?>" alt=""><br><?php endif; ?>
                <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp" style="margin-top:8px;">
                <input type="hidden" name="image_url" value="<?= $C($old['image_url'] ?? '') ?>">
              </div>
            </div>
          <?php elseif ($tab === 'amenities'): ?>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.amenity','Amenity')) ?> (EN) *</label><input type="text" name="amenity" value="<?= $C($old['name'] ?? '') ?>" required></div>
              <div class="field"><label><?= $C(t('admin.amenity','Amenity')) ?> (AR)</label><input type="text" name="amenity_ar" value="<?= $C($old['name_ar'] ?? '') ?>"></div>
            </div>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.icon')) ?></label><input type="text" name="icon" value="<?= $C($old['icon'] ?? '') ?>" placeholder="fa-solid fa-wifi"></div>
              <div class="field"><label><?= $C(t('admin.sort_order')) ?></label><input type="number" name="sort_order" value="<?= (int)($old['sort_order'] ?? 0) ?>"></div>
            </div>
          <?php endif; ?>

          <?php if ($tab === 'offers'): ?>
            <div class="field"><label><?= $C(t('admin.status')) ?></label><input type="checkbox" name="is_active" value="1" <?= (int)($old['is_active'] ?? 1)?'checked':'' ?>> <?= $C(t('admin.active')) ?></div>
          <?php endif; ?>

          <div class="form-actions">
            <button type="submit" class="btn"><?= $C(t('admin.save')) ?></button>
            <a class="btn btn-ghost" href="detail.php?id=<?= (int)$hotelId ?>&tab=<?= $C($tab) ?>"><?= $C(t('admin.cancel')) ?></a>
          </div>

        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__.'/../components/footer.php'; ?>
</content>


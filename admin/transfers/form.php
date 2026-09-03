<?php
/**
 * admin/transfers/form.php
 * Add/edit for routes, car_classes, pickup_points, transfer_services, locations, extras.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$tab = $_GET['tab'] ?? 'routes';
$tabs = ['routes','car_classes','pickup_points','transfer_services','locations','extras'];
if (!in_array($tab, $tabs, true)) $tab = 'routes';
$tableMap = [
    'routes'=>'routes','car_classes'=>'car_classes','pickup_points'=>'pickup_locations',
    'transfer_services'=>'transfer_services','locations'=>'transfers_locations','extras'=>'transfer_extras',
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
    require __DIR__.'/../components/footer.php'; 
    exit;
}

$errors=[]; $alerts=[];

// Default empty row per tab
$defs = [
    'routes'=>['origin'=>'','destination'=>'','distance_km'=>0,'travel_time_min'=>0,'base_price'=>0,'is_active'=>1],
    'car_classes'=>['name'=>'','name_ar'=>'','slug'=>'','models'=>'','description'=>'','description_ar'=>'','passenger_capacity'=>4,'luggage_capacity'=>3,'price_multiplier'=>1.00,'image_url'=>'','sort_order'=>0,'is_active'=>1],
    'pickup_points'=>['name'=>'','sort_order'=>0,'is_active'=>1],
    'transfer_services'=>['name'=>'','description'=>'','icon'=>'','price'=>0,'unit'=>'flat','is_active'=>1,'sort_order'=>0],
    'locations'=>['name'=>'','slug'=>'','type'=>'airport','country'=>'','is_active'=>1,'sort_order'=>0],
    'extras'=>['key'=>'','name'=>'','description'=>'','price'=>0,'unit'=>'flat','is_active'=>1,'sort_order'=>0],
];
$old = $row ?: $defs[$tab];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $data = [];
    switch ($tab) {
        case 'routes':
            $data = [
                'origin'=>trim($_POST['origin']??''), 'destination'=>trim($_POST['destination']??''),
                'distance_km'=>(float)($_POST['distance_km']??0), 'travel_time_min'=>(int)($_POST['travel_time_min']??0),
                'base_price'=>(float)($_POST['base_price']??0), 'is_active'=>!empty($_POST['is_active'])?1:0,
            ];
            if ($data['origin']===''||$data['destination']==='') $errors[]=t('admin.route_req','Origin and destination are required.');
            break;
        case 'car_classes':
            $imageUrl = trim($_POST['image_url'] ?? ($old['image_url'] ?? ''));
            if (!empty($_FILES['image_file']['name']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $up = CrudService::handleUpload($_FILES['image_file'], 'cars');
                if ($up) $imageUrl = $up;
            }

            $data = [
                'name'=>trim($_POST['name']??''),
                'name_ar'=>trim($_POST['name_ar']??''),
                'slug'=>trim($_POST['slug']??''),
                'models'=>trim($_POST['models']??''),
                'description'=>trim($_POST['description']??''),
                'description_ar'=>trim($_POST['description_ar']??''),
                'passenger_capacity'=>max(1,(int)($_POST['passenger_capacity']??4)),
                'luggage_capacity'=>max(0,(int)($_POST['luggage_capacity']??3)),
                'price_multiplier'=>(float)($_POST['price_multiplier']??1),
                'image_url'=>$imageUrl,
                'sort_order'=>(int)($_POST['sort_order']??0),
                'is_active'=>!empty($_POST['is_active'])?1:0,
            ];
            if ($data['name']==='') $errors[]=t('admin.name_req','Name is required.');
            if ($data['slug']==='') $data['slug']=strtolower(preg_replace('/[^a-z0-9]+/i','-',$data['name']));
            break;
        case 'pickup_points':
            $data = [
                'name'=>trim($_POST['name']??''),
                'sort_order'=>(int)($_POST['sort_order']??0),
                'is_active'=>!empty($_POST['is_active'])?1:0,
            ];
            if ($data['name']==='') $errors[]=t('admin.name_req','Name is required.');
            break;
        case 'transfer_services':
            $data = [
                'name'=>trim($_POST['name']??''), 'description'=>trim($_POST['description']??''), 'icon'=>trim($_POST['icon']??''),
                'price'=>(float)($_POST['price']??0), 'unit'=>in_array($_POST['unit']??'flat',['flat','per_seat','per_item'],true)?$_POST['unit']:'flat',
                'is_active'=>!empty($_POST['is_active'])?1:0, 'sort_order'=>(int)($_POST['sort_order']??0),
            ];
            if ($data['name']==='') $errors[]=t('admin.name_req','Name is required.');
            break;
        case 'locations':
            $data = [
                'name'=>trim($_POST['name']??''), 'slug'=>trim($_POST['slug']??''),
                'type'=>in_array($_POST['type']??'other',['airport','city','port','hotel','station','other'],true)?$_POST['type']:'other',
                'country'=>trim($_POST['country']??''), 'is_active'=>!empty($_POST['is_active'])?1:0, 'sort_order'=>(int)($_POST['sort_order']??0),
            ];
            if ($data['name']==='') $errors[]=t('admin.name_req','Name is required.');
            if ($data['slug']==='') $data['slug']=strtolower(preg_replace('/[^a-z0-9]+/i','-',$data['name']));
            break;
        case 'extras':
            $data = [
                'key'=>trim($_POST['key']??''), 'name'=>trim($_POST['name']??''), 'description'=>trim($_POST['description']??''),
                'price'=>(float)($_POST['price']??0), 'unit'=>in_array($_POST['unit']??'flat',['flat','per_seat','per_item'],true)?$_POST['unit']:'flat',
                'is_active'=>!empty($_POST['is_active'])?1:0, 'sort_order'=>(int)($_POST['sort_order']??0),
            ];
            if ($data['name']==='') $errors[]=t('admin.name_req','Name is required.');
            if ($data['key']==='') $data['key']=strtolower(preg_replace('/[^a-z0-9_]+/i','_',$data['name']));
            break;
    }
    if (!$errors) {
        if ($editing) { 
            CrudService::update($table, $id, $data); 
            $alerts[]=['type'=>'success','msg'=>t('admin.saved','Saved successfully.')]; 
        } else { 
            $id=CrudService::insert($table, $data); 
            $editing=true; 
            $alerts[]=['type'=>'success','msg'=>t('admin.created','Created successfully.')]; 
        }
        $row=CrudService::find($table,$id); $old=$row;
    }
}

$account_alerts = array_merge(array_map(fn($e)=>['type'=>'error','msg'=>$e], $errors), $alerts);
$admin_page_title = ($editing?t('admin.edit'):t('admin.create')).' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = $tab === 'car_classes' ? t('admin.car_classes', 'Car Classes') : ($tab === 'pickup_points' ? 'Pickup Points' : t('admin.transfers'));
$admin_active = $tab === 'car_classes' ? 'car_classes' : 'transfers';
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
          <h3>
            <i class="<?= $tab==='car_classes'?'fa-solid fa-taxi':($tab==='pickup_points'?'fa-solid fa-location-dot':'fa-solid fa-car-side') ?>"></i> 
            <?= $tab === 'car_classes' ? ($editing ? 'Edit Car Class' : 'Add New Car Class') : ($tab === 'pickup_points' ? ($editing ? 'Edit Pickup Point' : 'Add New Pickup Point') : htmlspecialchars(t('admin.transfer_info','Transfer Information'))) ?>
          </h3>
          <a class="btn btn-ghost btn-sm" href="index.php?tab=<?= htmlspecialchars($tab) ?>"><?= htmlspecialchars(t('admin.back_to_list')) ?></a>
        </div>
        <form method="post" action="form.php?tab=<?= htmlspecialchars($tab) ?><?= $editing?'&id='.(int)$id:'' ?>" enctype="multipart/form-data" novalidate>
          <?= csrf_field() ?>

          <?php if ($tab === 'routes'): ?>
            <div class="grid-2">
              <div class="field"><label><?= htmlspecialchars(t('admin.origin')) ?> *</label><input type="text" name="origin" value="<?= htmlspecialchars($old['origin']) ?>" required></div>
              <div class="field"><label><?= htmlspecialchars(t('admin.destination_field')) ?> *</label><input type="text" name="destination" value="<?= htmlspecialchars($old['destination']) ?>" required></div>
            </div>
            <div class="grid-3">
              <div class="field"><label><?= htmlspecialchars(t('admin.distance')) ?></label><input type="number" step="0.01" name="distance_km" value="<?= htmlspecialchars($old['distance_km']) ?>"></div>
              <div class="field"><label><?= htmlspecialchars(t('admin.travel_time')) ?></label><input type="number" name="travel_time_min" value="<?= (int)$old['travel_time_min'] ?>"></div>
              <div class="field"><label><?= htmlspecialchars(t('admin.base_price')) ?></label><input type="number" step="0.01" name="base_price" value="<?= htmlspecialchars($old['base_price']) ?>"></div>
            </div>

          <?php elseif ($tab === 'car_classes'): ?>
            <div class="grid-2">
              <div class="field">
                <label>Class Name (English) <span style="color:#b3261e">*</span></label>
                <input type="text" name="name" value="<?= htmlspecialchars((string)($old['name'] ?? '')) ?>" required placeholder="e.g. Business VIP">
              </div>
              <div class="field">
                <label>Class Name (Arabic)</label>
                <input type="text" name="name_ar" value="<?= htmlspecialchars((string)($old['name_ar'] ?? '')) ?>" placeholder="e.g. رجال الأعمال VIP">
              </div>
            </div>

            <div class="grid-2">
              <div class="field">
                <label>Slug (URL Key)</label>
                <input type="text" name="slug" value="<?= htmlspecialchars((string)($old['slug'] ?? '')) ?>" placeholder="auto-generated from name if blank">
              </div>
              <div class="field">
                <label>Example Fleet Models</label>
                <input type="text" name="models" value="<?= htmlspecialchars((string)($old['models'] ?? '')) ?>" placeholder="e.g. Mercedes-Benz E-Class, BMW 5 Series, Audi A6">
              </div>
            </div>

            <div class="grid-3">
              <div class="field">
                <label><i class="fa-solid fa-user" style="color:var(--teal);"></i> Passenger Capacity</label>
                <input type="number" min="1" max="100" name="passenger_capacity" value="<?= (int)($old['passenger_capacity'] ?? 4) ?>">
              </div>
              <div class="field">
                <label><i class="fa-solid fa-suitcase-rolling" style="color:#e67e22;"></i> Luggage Capacity (Bags)</label>
                <input type="number" min="0" max="100" name="luggage_capacity" value="<?= (int)($old['luggage_capacity'] ?? 3) ?>">
              </div>
              <div class="field">
                <label><i class="fa-solid fa-percent" style="color:var(--navy);"></i> Price Multiplier (Base Route ×)</label>
                <input type="number" step="0.01" min="0.10" max="50.00" name="price_multiplier" value="<?= htmlspecialchars((string)($old['price_multiplier'] ?? '1.00')) ?>">
                <small class="hint">Applied to route base rate (e.g. 1.00 = base price, 1.40 = +40%).</small>
              </div>
            </div>

            <div class="grid-2">
              <div class="field">
                <label>Description (English)</label>
                <textarea name="description" rows="3" placeholder="Optional highlights and luxury amenities..."><?= htmlspecialchars((string)($old['description'] ?? '')) ?></textarea>
              </div>
              <div class="field">
                <label>Description (Arabic)</label>
                <textarea name="description_ar" rows="3" placeholder="وصف الفئة والميزات باللغة العربية..."><?= htmlspecialchars((string)($old['description_ar'] ?? '')) ?></textarea>
              </div>
            </div>

            <div class="grid-2">
              <div class="field">
                <label><i class="fa-solid fa-image"></i> Vehicle Image (Upload File)</label>
                <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp">
                <small class="hint">Upload a transparent PNG or high-res car photo.</small>
              </div>
              <div class="field">
                <label>Or Image URL</label>
                <input type="text" name="image_url" value="<?= htmlspecialchars((string)($old['image_url'] ?? '')) ?>" placeholder="https://images.unsplash.com/... or /uploads/cars/car.png">
              </div>
            </div>

            <?php if (!empty($old['image_url'])): ?>
              <div class="field">
                <label>Current Photo Preview</label>
                <div style="padding:10px; background:#fbfaf7; border:1px solid var(--line); border-radius:10px; display:inline-block;">
                  <img src="<?= htmlspecialchars('/' . ltrim($old['image_url'], '/')) ?>" alt="Preview" style="height:80px; max-width:180px; object-fit:contain; border-radius:6px;">
                </div>
              </div>
            <?php endif; ?>

            <div class="field">
              <label>Sort Order</label>
              <input type="number" name="sort_order" value="<?= (int)($old['sort_order'] ?? 0) ?>" style="max-width:200px;">
              <small class="hint">Lower numbers display first.</small>
            </div>

          <?php elseif ($tab === 'pickup_points'): ?>
            <div class="grid-2">
              <div class="field">
                <label>Pickup Location Name <span style="color:#b3261e">*</span></label>
                <input type="text" name="name" value="<?= htmlspecialchars((string)($old['name'] ?? '')) ?>" required placeholder="e.g. Queen Alia International Airport (AMM)">
              </div>
              <div class="field">
                <label>Sort Order</label>
                <input type="number" name="sort_order" value="<?= (int)($old['sort_order'] ?? 0) ?>" style="max-width:200px;">
                <small class="hint">Lower numbers appear first in customer dropdown lists.</small>
              </div>
            </div>

          <?php elseif ($tab === 'transfer_services' || $tab === 'extras'): ?>
            <div class="grid-2">
              <div class="field"><label><?= htmlspecialchars(t('admin.name')) ?></label><input type="text" name="name" value="<?= htmlspecialchars($old['name']) ?>"></div>
              <?php if ($tab === 'extras'): ?>
                <div class="field"><label><?= htmlspecialchars(t('admin.key')) ?></label><input type="text" name="key" value="<?= htmlspecialchars($old['key']) ?>" placeholder="auto"></div>
              <?php else: ?>
                <div class="field"><label><?= htmlspecialchars(t('admin.icon')) ?></label><input type="text" name="icon" value="<?= htmlspecialchars($old['icon']) ?>" placeholder="fa-solid fa-car"></div>
              <?php endif; ?>
            </div>
            <div class="field"><label><?= htmlspecialchars(t('admin.description')) ?></label><input type="text" name="description" value="<?= htmlspecialchars($old['description']) ?>"></div>
            <div class="grid-3">
              <div class="field"><label><?= htmlspecialchars(t('admin.price')) ?></label><input type="number" step="0.01" name="price" value="<?= htmlspecialchars($old['price']) ?>"></div>
              <div class="field"><label><?= htmlspecialchars(t('admin.unit')) ?></label>
                <select name="unit"><?php foreach (['flat','per_seat','per_item'] as $u): ?><option value="<?=$u?>" <?=$old['unit']===$u?'selected':''?>><?=htmlspecialchars($u)?></option><?php endforeach; ?></select>
              </div>
              <div class="field"><label><?= htmlspecialchars(t('admin.sort_order')) ?></label><input type="number" name="sort_order" value="<?= (int)$old['sort_order'] ?>"></div>
            </div>
          <?php elseif ($tab === 'locations'): ?>
            <div class="grid-2">
              <div class="field"><label><?= htmlspecialchars(t('admin.name')) ?></label><input type="text" name="name" value="<?= htmlspecialchars($old['name']) ?>"></div>
              <div class="field"><label><?= htmlspecialchars(t('admin.slug')) ?></label><input type="text" name="slug" value="<?= htmlspecialchars($old['slug']) ?>" placeholder="auto"></div>
            </div>
            <div class="grid-2">
              <div class="field"><label><?= htmlspecialchars(t('admin.type')) ?></label>
                <select name="type"><?php foreach (['airport','city','port','hotel','station','other'] as $tp): ?><option value="<?=$tp?>" <?=$old['type']===$tp?'selected':''?>><?=htmlspecialchars(ucfirst($tp))?></option><?php endforeach; ?></select>
              </div>
              <div class="field"><label><?= htmlspecialchars(t('admin.country')) ?></label><input type="text" name="country" value="<?= htmlspecialchars($old['country']) ?>"></div>
            </div>
            <div class="field"><label><?= htmlspecialchars(t('admin.sort_order')) ?></label><input type="number" name="sort_order" value="<?= (int)$old['sort_order'] ?>"></div>
          <?php endif; ?>

          <div class="field" style="margin-top:16px;">
            <label style="display:inline-flex; align-items:center; gap:8px; cursor:pointer;">
              <input type="checkbox" name="is_active" value="1" <?= (int)($old['is_active'] ?? 1)?'checked':'' ?> style="width:auto; margin:0; cursor:pointer;"> 
              <strong><?= htmlspecialchars(t('admin.active', 'Active (Visible for customer selection)')) ?></strong>
            </label>
          </div>

          <div class="form-actions" style="margin-top:24px; display:flex; gap:10px;">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= htmlspecialchars(t('admin.save')) ?></button>
            <a class="btn btn-ghost" href="index.php?tab=<?= htmlspecialchars($tab) ?>"><?= htmlspecialchars(t('admin.cancel')) ?></a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__.'/../components/footer.php'; ?>

<?php
/**
 * admin/transfers/form.php
 * Add/edit for routes, car_classes, transfer_services, locations, extras.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$tab = $_GET['tab'] ?? 'routes';
$tabs = ['routes','car_classes','transfer_services','locations','extras'];
if (!in_array($tab, $tabs, true)) $tab = 'routes';
$tableMap = [
    'routes'=>'routes','car_classes'=>'car_classes','transfer_services'=>'transfer_services',
    'locations'=>'transfers_locations','extras'=>'transfer_extras',
];
$table = $tableMap[$tab];

$id = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$row = $editing ? CrudService::find($table, $id) : null;
if ($editing && !$row) { http_response_code(404);
    $admin_page_title = t('admin.not_found','Not Found').' — GAIA TOURS &amp; TRAVEL'; require __DIR__.'/../components/header.php';
    echo '<div class="admin-app"><div class="admin-main"><div class="admin-content"><div class="card"><div class="muted" style="text-align:center;padding:40px;">'.htmlspecialchars(t('admin.not_found_text','Not found.')).'</div></div></div></div></div>'; require __DIR__.'/../components/footer.php'; exit;
}

$errors=[]; $alerts=[];

// Default empty row per tab
$defs = [
    'routes'=>['origin'=>'','destination'=>'','distance_km'=>0,'travel_time_min'=>0,'base_price'=>0,'is_active'=>1],
    'car_classes'=>['name'=>'','slug'=>'','passenger_capacity'=>4,'luggage_capacity'=>3,'price_multiplier'=>1.00,'is_active'=>1],
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
            $data = [
                'name'=>trim($_POST['name']??''), 'slug'=>trim($_POST['slug']??''),
                'passenger_capacity'=>max(1,(int)($_POST['passenger_capacity']??4)), 'luggage_capacity'=>max(0,(int)($_POST['luggage_capacity']??3)),
                'price_multiplier'=>(float)($_POST['price_multiplier']??1), 'is_active'=>!empty($_POST['is_active'])?1:0,
            ];
            if ($data['name']==='') $errors[]=t('admin.name_req','Name is required.');
            if ($data['slug']==='') $data['slug']=strtolower(preg_replace('/[^a-z0-9]+/i','-',$data['name']));
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
        if ($editing) { CrudService::update($table, $id, $data); $alerts[]=['type'=>'success','msg'=>t('admin.saved','Saved successfully.')]; }
        else { $id=CrudService::insert($table, $data); $editing=true; $alerts[]=['type'=>'success','msg'=>t('admin.created','Created successfully.')]; }
        $row=CrudService::find($table,$id); $old=$row;
    }
}

$account_alerts = array_merge(array_map(fn($e)=>['type'=>'error','msg'=>$e], $errors), $alerts);
$admin_page_title = ($editing?t('admin.edit'):t('admin.create')).' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = t('admin.transfers');
$admin_active = 'transfers';
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
          <h3><i class="fa-solid fa-car-side"></i> <?= htmlspecialchars(t('admin.transfer_info','Transfer Information')) ?></h3>
          <a class="btn btn-ghost btn-sm" href="index.php?tab=<?= htmlspecialchars($tab) ?>"><?= htmlspecialchars(t('admin.back_to_list')) ?></a>
        </div>
        <form method="post" action="form.php?tab=<?= htmlspecialchars($tab) ?><?= $editing?'&id='.(int)$id:'' ?>" novalidate>
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
              <div class="field"><label><?= htmlspecialchars(t('admin.name')) ?></label><input type="text" name="name" value="<?= htmlspecialchars($old['name']) ?>"></div>
              <div class="field"><label><?= htmlspecialchars(t('admin.slug')) ?></label><input type="text" name="slug" value="<?= htmlspecialchars($old['slug']) ?>" placeholder="auto"></div>
            </div>
            <div class="grid-3">
              <div class="field"><label><?= htmlspecialchars(t('admin.passenger_capacity')) ?></label><input type="number" name="passenger_capacity" value="<?= (int)$old['passenger_capacity'] ?>"></div>
              <div class="field"><label><?= htmlspecialchars(t('admin.luggage_capacity')) ?></label><input type="number" name="luggage_capacity" value="<?= (int)$old['luggage_capacity'] ?>"></div>
              <div class="field"><label><?= htmlspecialchars(t('admin.price_multiplier')) ?></label><input type="number" step="0.01" name="price_multiplier" value="<?= htmlspecialchars($old['price_multiplier']) ?>"></div>
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

          <div class="field"><label><?= htmlspecialchars(t('admin.status')) ?></label><input type="checkbox" name="is_active" value="1" <?= (int)$old['is_active']?'checked':'' ?>> <?= htmlspecialchars(t('admin.active')) ?></div>
          <div class="form-actions">
            <button type="submit" class="btn"><?= htmlspecialchars(t('admin.save')) ?></button>
            <a class="btn btn-ghost" href="index.php?tab=<?= htmlspecialchars($tab) ?>"><?= htmlspecialchars(t('admin.cancel')) ?></a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__.'/../components/footer.php'; ?>

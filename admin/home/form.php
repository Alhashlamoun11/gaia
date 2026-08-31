<?php
/**
 * admin/home/form.php
 * Create/Edit for all homepage CMS entities.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();

$tabs = ['banners','highlights','trust_scores','awards','night_offerings','social_links'];
$tab = $_GET['tab'] ?? 'banners';
if (!in_array($tab, $tabs, true)) $tab = 'banners';

$tableMap = [
    'banners'=>'banners','highlights'=>'highlights','trust_scores'=>'trust_scores',
    'awards'=>'awards','night_offerings'=>'night_offerings','social_links'=>'social_links',
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

// Default empty rows per tab
$defs = [
    'banners'=>['page'=>'home','title'=>'','subtitle'=>'','cta_label'=>'','cta_url'=>'','image_url'=>'','stats_json'=>'','sort_order'=>0,'is_active'=>1],
    'highlights'=>['section'=>'expect','title'=>'','description'=>'','icon'=>'','image_url'=>'','sort_order'=>0,'is_active'=>1],
    'trust_scores'=>['icon'=>'','score'=>'','label'=>'','color'=>'#1f6f8f','sort_order'=>0,'is_active'=>1],
    'awards'=>['title'=>'','icon'=>'','sort_order'=>0,'is_active'=>1],
    'night_offerings'=>['type'=>'card','title'=>'','description'=>'','badge'=>'','price_label'=>'','price_value'=>0,'per_label'=>'','image_url'=>'','sort_order'=>0,'is_active'=>1],
    'social_links'=>['platform'=>'','url'=>'','icon'=>'','sort_order'=>0,'is_active'=>1],
];
$old = $row ?: $defs[$tab];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $data = [];
    switch ($tab) {
        case 'banners':
            $data = [
                'page'=>trim($_POST['page']??'home'),
                'title'=>trim($_POST['title']??''),
                'subtitle'=>trim($_POST['subtitle']??''),
                'cta_label'=>trim($_POST['cta_label']??''),
                'cta_url'=>trim($_POST['cta_url']??''),
                'stats_json'=>trim($_POST['stats_json']??''),
                'sort_order'=>(int)($_POST['sort_order']??0),
                'is_active'=>!empty($_POST['is_active'])?1:0,
            ];
            if ($data['title']==='') $errors[]=t('admin.title_req','Title is required.');
            break;
        case 'highlights':
            $data = [
                'section'=>trim($_POST['section']??'expect'),
                'title'=>trim($_POST['title']??''),
                'description'=>trim($_POST['description']??''),
                'icon'=>trim($_POST['icon']??''),
                'sort_order'=>(int)($_POST['sort_order']??0),
                'is_active'=>!empty($_POST['is_active'])?1:0,
            ];
            if ($data['title']==='') $errors[]=t('admin.title_req','Title is required.');
            break;
        case 'trust_scores':
            $data = [
                'icon'=>trim($_POST['icon']??''),
                'score'=>trim($_POST['score']??''),
                'label'=>trim($_POST['label']??''),
                'color'=>trim($_POST['color']??'#1f6f8f'),
                'sort_order'=>(int)($_POST['sort_order']??0),
                'is_active'=>!empty($_POST['is_active'])?1:0,
            ];
            if ($data['label']==='') $errors[]=t('admin.name_req','Label is required.');
            break;
        case 'awards':
            $data = [
                'title'=>trim($_POST['title']??''),
                'icon'=>trim($_POST['icon']??''),
                'sort_order'=>(int)($_POST['sort_order']??0),
                'is_active'=>!empty($_POST['is_active'])?1:0,
            ];
            if ($data['title']==='') $errors[]=t('admin.title_req','Title is required.');
            break;
        case 'night_offerings':
            $data = [
                'type'=>trim($_POST['type']??'card'),
                'title'=>trim($_POST['title']??''),
                'description'=>trim($_POST['description']??''),
                'badge'=>trim($_POST['badge']??''),
                'price_label'=>trim($_POST['price_label']??''),
                'price_value'=>(float)($_POST['price_value']??0),
                'per_label'=>trim($_POST['per_label']??''),
                'sort_order'=>(int)($_POST['sort_order']??0),
                'is_active'=>!empty($_POST['is_active'])?1:0,
            ];
            if ($data['title']==='') $errors[]=t('admin.title_req','Title is required.');
            break;
        case 'social_links':
            $data = [
                'platform'=>trim($_POST['platform']??''),
                'url'=>trim($_POST['url']??''),
                'icon'=>trim($_POST['icon']??''),
                'sort_order'=>(int)($_POST['sort_order']??0),
                'is_active'=>!empty($_POST['is_active'])?1:0,
            ];
            if ($data['platform']==='') $errors[]=t('admin.name_req','Platform is required.');
            break;
    }

    // Handle image uploads where applicable
    if (in_array($tab, ['banners','highlights','night_offerings'])) {
        if (!empty($_FILES['image_file']['name']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $up = CrudService::handleUpload($_FILES['image_file'], 'home');
            if ($up !== null) $data['image_url'] = $up;
            else $errors[] = t('admin.image_invalid','Invalid image.');
        } elseif (isset($_POST['image_url']) && trim($_POST['image_url']) !== '') {
            $data['image_url'] = trim($_POST['image_url']);
        }
    }

    if (!$errors) {
        if ($editing) {
            CrudService::update($table, $id, $data);
            $alerts[]=['type'=>'success','msg'=>t('admin.saved','Saved successfully.')];
        } else {
            $id = CrudService::insert($table, $data);
            $editing = true;
            $alerts[]=['type'=>'success','msg'=>t('admin.created','Created successfully.')];
        }
        $row = CrudService::find($table, $id);
        $old = $row;
    }
}

$account_alerts = array_merge(array_map(fn($e)=>['type'=>'error','msg'=>$e], $errors), $alerts);
$admin_page_title = ($editing?t('admin.edit'):t('admin.create')).' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = t('admin.homepage');
$admin_active = 'home';
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
          <h3><i class="fa-solid fa-house"></i> <?= $C(t('admin.homepage')) ?> — <?= $C(t('admin.'.$tab, $tab)) ?></h3>
          <a class="btn btn-ghost btn-sm" href="index.php?tab=<?= $C($tab) ?>"><?= $C(t('admin.back_to_list')) ?></a>
        </div>

        <form method="post" action="form.php?tab=<?= $C($tab) ?><?= $editing?'&id='.(int)$id:'' ?>" enctype="multipart/form-data" novalidate>
          <?= csrf_field() ?>

          <?php if ($tab === 'banners'): ?>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.page')) ?></label><input type="text" name="page" value="<?= $C($old['page']) ?>"></div>
              <div class="field"><label><?= $C(t('admin.title')) ?><?= $editing && isset($old['title_ar']) ? ' / AR' : '' ?></label><input type="text" name="title" value="<?= $C($old['title']) ?>"></div>
            </div>
            <div class="field"><label><?= $C(t('admin.subtitle','Subtitle')) ?></label><input type="text" name="subtitle" value="<?= $C($old['subtitle']) ?>"></div>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.cta_text','CTA Text')) ?></label><input type="text" name="cta_label" value="<?= $C($old['cta_label']) ?>"></div>
              <div class="field"><label><?= $C(t('admin.cta_url','CTA URL')) ?></label><input type="text" name="cta_url" value="<?= $C($old['cta_url']) ?>"></div>
            </div>
            <div class="field"><label><?= $C(t('admin.stats_json')) ?></label><textarea name="stats_json" rows="4" placeholder='[{"icon":"fa-solid fa-car-side","value":"1 400 000+","label":"Completed rides"}]'><?= $C($old['stats_json']) ?></textarea></div>
          <?php elseif ($tab === 'highlights'): ?>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.section','Section')) ?></label><input type="text" name="section" value="<?= $C($old['section']) ?>"></div>
              <div class="field"><label><?= $C(t('admin.icon')) ?></label><input type="text" name="icon" value="<?= $C($old['icon']) ?>" placeholder="fa-solid fa-check"></div>
            </div>
            <div class="field"><label><?= $C(t('admin.title')) ?></label><input type="text" name="title" value="<?= $C($old['title']) ?>"></div>
            <div class="field"><label><?= $C(t('admin.description')) ?></label><textarea name="description" rows="3"><?= $C($old['description']) ?></textarea></div>
          <?php elseif ($tab === 'trust_scores'): ?>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.icon')) ?></label><input type="text" name="icon" value="<?= $C($old['icon']) ?>"></div>
              <div class="field"><label><?= $C(t('admin.score')) ?></label><input type="text" name="score" value="<?= $C($old['score']) ?>" placeholder="4.8/5"></div>
            </div>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.label','Label')) ?></label><input type="text" name="label" value="<?= $C($old['label']) ?>"></div>
              <div class="field"><label><?= $C(t('admin.color','Color')) ?></label><input type="color" name="color" value="<?= $C($old['color']) ?>"></div>
            </div>
          <?php elseif ($tab === 'awards'): ?>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.title')) ?></label><input type="text" name="title" value="<?= $C($old['title']) ?>"></div>
              <div class="field"><label><?= $C(t('admin.icon')) ?></label><input type="text" name="icon" value="<?= $C($old['icon']) ?>" placeholder="fa-solid fa-trophy"></div>
            </div>
          <?php elseif ($tab === 'night_offerings'): ?>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.badge')) ?></label><input type="text" name="badge" value="<?= $C($old['badge']) ?>"></div>
              <div class="field"><label><?= $C(t('admin.type','Type')) ?></label><input type="text" name="type" value="<?= $C($old['type']) ?>"></div>
            </div>
            <div class="field"><label><?= $C(t('admin.title')) ?></label><input type="text" name="title" value="<?= $C($old['title']) ?>"></div>
            <div class="field"><label><?= $C(t('admin.description')) ?></label><textarea name="description" rows="3"><?= $C($old['description']) ?></textarea></div>
            <div class="grid-3">
              <div class="field"><label><?= $C(t('admin.price_value')) ?></label><input type="number" step="0.01" name="price_value" value="<?= $C($old['price_value']) ?>"></div>
              <div class="field"><label><?= $C(t('admin.per_label')) ?></label><input type="text" name="per_label" value="<?= $C($old['per_label']) ?>"></div>
              <div class="field"><label><?= $C(t('admin.price_label','Price Label')) ?></label><input type="text" name="price_label" value="<?= $C($old['price_label']) ?>"></div>
            </div>
          <?php elseif ($tab === 'social_links'): ?>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.platform','Platform')) ?></label><input type="text" name="platform" value="<?= $C($old['platform']) ?>" placeholder="facebook"></div>
              <div class="field"><label><?= $C(t('admin.icon')) ?></label><input type="text" name="icon" value="<?= $C($old['icon']) ?>" placeholder="fa-brands fa-facebook"></div>
            </div>
            <div class="field"><label><?= $C(t('admin.url','URL')) ?></label><input type="text" name="url" value="<?= $C($old['url']) ?>"></div>
          <?php endif; ?>

          <?php if (in_array($tab, ['banners','highlights','night_offerings'])): ?>
            <div class="field">
              <label><?= $C(t('admin.banner_image','Image')) ?></label>
              <?php if (!empty($old['image_url'])): ?><img class="preview-img" src="<?= $C($old['image_url']) ?>" alt=""><br><?php endif; ?>
              <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp" style="margin-top:8px;">
              <input type="hidden" name="image_url" value="<?= $C($old['image_url'] ?? '') ?>">
            </div>
          <?php endif; ?>

          <div class="grid-2">
            <div class="field"><label><?= $C(t('admin.sort_order')) ?></label><input type="number" name="sort_order" value="<?= (int)($old['sort_order']??0) ?>"></div>
            <div class="field"><label><?= $C(t('admin.status')) ?></label><input type="checkbox" name="is_active" value="1" <?= (int)($old['is_active']??1)?'checked':'' ?>> <?= $C(t('admin.active')) ?></div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn"><?= $C(t('admin.save')) ?></button>
            <a class="btn btn-ghost" href="index.php?tab=<?= $C($tab) ?>"><?= $C(t('admin.cancel')) ?></a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__.'/../components/footer.php'; ?>


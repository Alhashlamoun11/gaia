<?php
/**
 * admin/tours/category-form.php
 * ============================================================
 * Create/Edit tour categories (tour_categories table).
 * Bilingual name/description via lang_value (EN/AR).
 * ============================================================
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo   = getPDO();
$table = 'tour_categories';
$id    = (int)($_GET['id'] ?? 0);
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
$old = $row ?: ['name'=>'','name_ar'=>'','slug'=>'','description'=>'','description_ar'=>'','sort_order'=>0,'is_active'=>1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'name_ar' => trim($_POST['name_ar'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'description_ar' => trim($_POST['description_ar'] ?? ''),
        'slug' => trim($_POST['slug'] ?? ''),
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
    ];
    if ($data['name'] === '') $errors[] = t('admin.name_req','Name is required.');
    if ($data['slug'] === '') $data['slug'] = strtolower(preg_replace('/[^a-z0-9]+/i','-', $data['name']));
    $data['slug'] = preg_replace('/-+/','-', trim($data['slug'],'-'));

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
$admin_topbar_title = t('admin.categories');
$admin_active = 'tours';
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
          <h3><i class="fa-solid fa-tags"></i> <?= $C(t('admin.categories')) ?></h3>
          <a class="btn btn-ghost btn-sm" href="categories.php"><?= $C(t('admin.back_to_list')) ?></a>
        </div>
        <form method="post" action="category-form.php<?= $editing?'?id='.(int)$id:'' ?>" novalidate>
          <?= csrf_field() ?>
          <div class="grid-2">
            <div class="field"><label><?= $C(t('admin.name')) ?> (EN) *</label><input type="text" name="name" value="<?= $C($old['name']) ?>" required></div>
            <div class="field"><label><?= $C(t('admin.name')) ?> (AR)</label><input type="text" name="name_ar" value="<?= $C($old['name_ar']) ?>"></div>
          </div>
          <div class="grid-2">
            <div class="field"><label><?= $C(t('admin.slug')) ?></label><input type="text" name="slug" value="<?= $C($old['slug']) ?>" placeholder="auto-generated"></div>
            <div class="field"><label><?= $C(t('admin.sort_order')) ?></label><input type="number" name="sort_order" value="<?= (int)$old['sort_order'] ?>"></div>
          </div>
          <div class="grid-2">
            <div class="field"><label><?= $C(t('admin.description')) ?> (EN)</label><textarea name="description" rows="3"><?= $C($old['description']) ?></textarea></div>
            <div class="field"><label><?= $C(t('admin.description')) ?> (AR)</label><textarea name="description_ar" rows="3"><?= $C($old['description_ar']) ?></textarea></div>
          </div>
          <div class="field"><label><?= $C(t('admin.status')) ?></label><input type="checkbox" name="is_active" value="1" <?= (int)$old['is_active']?'checked':'' ?>> <?= $C(t('admin.active')) ?></div>
          <div class="form-actions">
            <button type="submit" class="btn"><?= $C(t('admin.save')) ?></button>
            <a class="btn btn-ghost" href="categories.php"><?= $C(t('admin.cancel')) ?></a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__.'/../components/footer.php'; ?>
</content>


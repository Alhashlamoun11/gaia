<?php
/**
 * admin/promo-codes/form.php
 * Promo Code create/edit.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$row = $editing ? CrudService::find('promo_codes', $id) : null;
if ($editing && !$row) { http_response_code(404);
    $admin_page_title = t('admin.not_found','Not Found').' — GAIA TOURS & TRAVEL'; require __DIR__.'/../components/header.php';
    echo '<div class="admin-app"><div class="admin-main"><div class="admin-content"><div class="card"><div class="muted" style="text-align:center;padding:40px;">'.htmlspecialchars(t('admin.not_found_text','Not found.')).'</div></div></div></div></div>'; require __DIR__.'/../components/footer.php'; exit;
}

$errors = []; $alerts = [];
$old = $row ?: ['code'=>'','discount_type'=>'percent','discount_value'=>0,'max_uses'=>1,'used_count'=>0,'is_active'=>1,'valid_from'=>'','valid_until'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $data = [
        'code' => strtoupper(trim($_POST['code'] ?? '')),
        'discount_type' => in_array($_POST['discount_type'] ?? 'percent', ['percent','flat'], true) ? $_POST['discount_type'] : 'percent',
        'discount_value' => (float)($_POST['discount_value'] ?? 0),
        'max_uses' => max(1,(int)($_POST['max_uses'] ?? 1)),
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
        'valid_from' => trim($_POST['valid_from'] ?? '') !== '' ? $_POST['valid_from'] : null,
        'valid_until' => trim($_POST['valid_until'] ?? '') !== '' ? $_POST['valid_until'] : null,
    ];
    if ($data['code']==='') $errors[]=t('admin.code_req','Code is required.');
    if ($data['discount_value'] <= 0) $errors[]=t('admin.value_req','Value must be greater than 0.');

    if (!$errors) {
        if ($editing) {
            $data['used_count'] = max(0,(int)($_POST['used_count'] ?? 0));
            CrudService::update('promo_codes',$id,$data); $alerts[]=['type'=>'success','msg'=>t('admin.saved','Saved successfully.')];
        } else {
            $id=CrudService::insert('promo_codes',$data); $editing=true; $alerts[]=['type'=>'success','msg'=>t('admin.created','Created successfully.')];
        }
        $row=CrudService::find('promo_codes',$id); $old=$row;
    }
}

$account_alerts = array_merge(array_map(fn($e)=>['type'=>'error','msg'=>$e], $errors), $alerts);
$admin_page_title = ($editing?t('admin.edit'):t('admin.create')).' — GAIA TOURS & TRAVEL';
$admin_topbar_title = ($editing&&$row)?$row['code']:t('admin.add_new');
$admin_active = 'promo-codes';
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
          <h3><i class="fa-solid fa-ticket"></i> <?= $C(t('admin.promo_info','Promo Code Information')) ?></h3>
          <a class="btn btn-ghost btn-sm" href="index.php"><?= $C(t('admin.back_to_list')) ?></a>
        </div>
        <form method="post" action="form.php<?= $editing?'?id='.(int)$id:'' ?>" novalidate>
          <?= csrf_field() ?>
          <div class="grid-2">
            <div class="field"><label><?= $C(t('admin.code','Code')) ?> *</label><input type="text" name="code" value="<?= $C($old['code']) ?>" required placeholder="SUMMER10"></div>
            <div class="field"><label><?= $C(t('admin.type','Type')) ?></label>
              <select name="discount_type">
                <option value="percent" <?= $old['discount_type']==='percent'?'selected':'' ?>>%</option>
                <option value="flat" <?= $old['discount_type']==='flat'?'selected':'' ?>><?= $C(t('admin.flat','Flat')) ?></option>
              </select>
            </div>
          </div>
          <div class="grid-3">
            <div class="field"><label><?= $C(t('admin.discount_value','Discount Value')) ?> *</label><input type="number" step="0.01" name="discount_value" value="<?= $C($old['discount_value']) ?>" required></div>
            <div class="field"><label><?= $C(t('admin.max_uses','Max Uses')) ?></label><input type="number" name="max_uses" value="<?= (int)$old['max_uses'] ?>"></div>
            <?php if ($editing): ?><div class="field"><label><?= $C(t('admin.used','Used')) ?></label><input type="number" name="used_count" value="<?= (int)($old['used_count']??0) ?>"></div><?php endif; ?>
          </div>
          <div class="grid-2">
            <div class="field"><label><?= $C(t('admin.valid_from','Valid From')) ?></label><input type="date" name="valid_from" value="<?= $C($old['valid_from'] ?? '') ?>"></div>
            <div class="field"><label><?= $C(t('admin.valid_until','Valid Until')) ?></label><input type="date" name="valid_until" value="<?= $C($old['valid_until'] ?? '') ?>"></div>
          </div>
          <div class="field"><label><?= $C(t('admin.status')) ?></label><input type="checkbox" name="is_active" value="1" <?= (int)$old['is_active']?'checked':'' ?>> <?= $C(t('admin.active')) ?></div>
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
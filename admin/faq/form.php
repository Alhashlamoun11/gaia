<?php
/**
 * admin/faq/form.php
 * FAQ create/edit.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$id = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$row = $editing ? CrudService::find('faq', $id) : null;
if ($editing && !$row) { http_response_code(404);
    $admin_page_title = t('admin.not_found','Not Found').' — GAIA TOURS &amp; TRAVEL'; require __DIR__.'/../components/header.php';
    echo '<div class="admin-app"><div class="admin-main"><div class="admin-content"><div class="card"><div class="muted" style="text-align:center;padding:40px;">'.htmlspecialchars(t('admin.not_found_text','Not found.')).'</div></div></div></div></div>'; require __DIR__.'/../components/footer.php'; exit;
}

$errors=[]; $alerts=[];
$old = $row ?: ['category'=>'Booking','question'=>'','answer'=>'','sort_order'=>0,'is_active'=>1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $data = [
        'category'=>trim($_POST['category']??'Booking'),
        'question'=>trim($_POST['question']??''),
        'answer'=>trim($_POST['answer']??''),
        'sort_order'=>(int)($_POST['sort_order']??0),
        'is_active'=>!empty($_POST['is_active'])?1:0,
    ];
    if ($data['question']==='') $errors[]=t('admin.question_req','Question is required.');
    if ($data['answer']==='') $errors[]=t('admin.answer_req','Answer is required.');
    if (!$errors) {
        if ($editing) { CrudService::update('faq',$id,$data); $alerts[]=['type'=>'success','msg'=>t('admin.saved','Saved successfully.')]; }
        else { $id=CrudService::insert('faq',$data); $editing=true; $alerts[]=['type'=>'success','msg'=>t('admin.created','Created successfully.')]; }
        $row=CrudService::find('faq',$id); $old=$row;
    }
}

$account_alerts = array_merge(array_map(fn($e)=>['type'=>'error','msg'=>$e], $errors), $alerts);
$admin_page_title = ($editing?t('admin.edit'):t('admin.create')).' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = ($editing&&$row)?$row['question']:t('admin.add_faq','Add FAQ');
$admin_active = 'faq';
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
          <h3><i class="fa-solid fa-circle-question"></i> <?= htmlspecialchars(t('admin.faq_info','FAQ Information')) ?></h3>
          <a class="btn btn-ghost btn-sm" href="index.php"><?= htmlspecialchars(t('admin.back_to_list')) ?></a>
        </div>
        <form method="post" action="form.php<?= $editing?'?id='.(int)$id:'' ?>" novalidate>
          <?= csrf_field() ?>
          <div class="grid-2">
            <div class="field"><label><?= htmlspecialchars(t('admin.category')) ?></label><input type="text" name="category" value="<?= htmlspecialchars($old['category']) ?>"></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.sort_order')) ?></label><input type="number" name="sort_order" value="<?= (int)$old['sort_order'] ?>"></div>
          </div>
          <div class="field"><label><?= htmlspecialchars(t('admin.question','Question')) ?> *</label><input type="text" name="question" value="<?= htmlspecialchars($old['question']) ?>" required></div>
          <div class="field"><label><?= htmlspecialchars(t('admin.answer','Answer')) ?> *</label><textarea name="answer" rows="4" required><?= htmlspecialchars($old['answer']) ?></textarea></div>
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

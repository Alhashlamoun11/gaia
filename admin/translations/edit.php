<?php
/**
 * admin/translations/edit.php
 * ------------------------------------------------------------
 * Edit a single translation value.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$id = (int)($_GET['id'] ?? 0);
$row = null;
if ($id > 0) {
    $st = $pdo->prepare("SELECT * FROM translations WHERE id = ?");
    $st->execute([$id]);
    $row = $st->fetch() ?: null;
}

if (!$row) {
    http_response_code(404);
    $admin_page_title = t('admin.not_found', 'Not Found') . ' — GAIA TOURS & TRAVEL';
    require __DIR__ . '/../components/header.php';
    echo '<div class="admin-app"><div class="admin-main"><div class="admin-content"><div class="card"><div class="muted" style="text-align:center;padding:40px;">' . htmlspecialchars(t('admin.not_found_text', 'Not found.')) . '</div></div></div></div></div>';
    require __DIR__ . '/../components/footer.php';
    exit;
}

$errors = [];
$alerts = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $value = trim($_POST['value'] ?? '');
    if ($value === '') {
        $errors[] = t('admin.value_req', 'Value is required.');
    } else {
        $pdo->prepare("UPDATE translations SET `value` = ? WHERE id = ?")->execute([$value, $id]);
        $row['value'] = $value;
        $alerts[] = ['type' => 'success', 'msg' => t('admin.settings_saved', 'Settings saved successfully.')];
    }
}

$account_alerts = array_merge(array_map(fn($e) => ['type' => 'error', 'msg' => $e], $errors), $alerts);
$admin_page_title = t('admin.edit') . ' — GAIA TOURS & TRAVEL';
$admin_topbar_title = $row['key'];
$admin_active = 'translations';
$C = fn($v) => htmlspecialchars((string)($v ?? ''));
?>
<?php require __DIR__ . '/../components/header.php'; ?>
<div class="admin-app">
  <?php require __DIR__ . '/../components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/../components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/../components/alert.php'; ?>

      <div class="card">
        <div class="card-head">
          <h3><i class="fa-solid fa-language"></i> <?= $C(t('admin.edit_review', 'Edit Translation')) ?></h3>
          <a class="btn btn-ghost btn-sm" href="index.php"><?= $C(t('admin.back_to_list')) ?></a>
        </div>

        <form method="post" action="edit.php?id=<?= (int)$id ?>">
          <?= csrf_field() ?>
          <div class="grid-3">
            <div class="field"><label><?= $C(t('admin.translation_key')) ?></label><input type="text" value="<?= $C($row['key']) ?>" disabled></div>
            <div class="field"><label><?= $C(t('admin.lang')) ?></label><input type="text" value="<?= $C($row['lang']) ?>" disabled></div>
            <div class="field"><label><?= $C(t('admin.translation_group')) ?></label><input type="text" value="<?= $C($row['group']) ?>" disabled></div>
          </div>
          <div class="field"><label><?= $C(t('admin.value')) ?> *</label><textarea name="value" rows="4"><?= $C($row['value']) ?></textarea></div>
          <div class="form-actions">
            <button type="submit" class="btn"><?= $C(t('admin.save')) ?></button>
            <a class="btn btn-ghost" href="index.php"><?= $C(t('admin.cancel')) ?></a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../components/footer.php'; ?>
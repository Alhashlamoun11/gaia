<?php
/**
 * admin/reviews/edit.php
 * ------------------------------------------------------------
 * Edit a review from any source (reviews, hotel_reviews, weroad_reviews).
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();

$sources = [
    'reviews'        => ['table'=>'reviews',        'label'=>'Review'],
    'hotel_reviews'  => ['table'=>'hotel_reviews',  'label'=>'Hotel Review'],
    'weroad_reviews' => ['table'=>'weroad_reviews', 'label'=>'Tour Review'],
];

$src = $_GET['source'] ?? 'reviews';
$id = (int)($_GET['id'] ?? 0);
if (!isset($sources[$src])) $src = 'reviews';
$tbl = $sources[$src]['table'];

$row = null;
if ($id > 0) {
    $st = $pdo->prepare("SELECT * FROM `{$tbl}` WHERE id = ?");
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

    $data = [];
    // Common fields across all review tables
    if (isset($row['reviewer'])) $data['reviewer'] = trim($_POST['reviewer'] ?? '');
    if (isset($row['author'])) $data['author'] = trim($_POST['author'] ?? '');
    if (isset($row['rating'])) $data['rating'] = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    if (isset($row['text'])) $data['text'] = trim($_POST['text'] ?? '');
    if (isset($row['place'])) $data['place'] = trim($_POST['place'] ?? '');
    if (isset($row['date_label'])) $data['date_label'] = trim($_POST['date_label'] ?? '');
    if (isset($row['is_active'])) $data['is_active'] = !empty($_POST['is_active']) ? 1 : 0;
    if (isset($row['featured'])) $data['featured'] = !empty($_POST['featured']) ? 1 : 0;

    if (!$errors) {
        $set = [];
        $params = [':id' => $id];
        foreach ($data as $k => $v) {
            $set[] = "`{$k}` = :{$k}";
            $params[":{$k}"] = $v;
        }
        $sql = "UPDATE `{$tbl}` SET " . implode(', ', $set) . " WHERE id = :id";
        $pdo->prepare($sql)->execute($params);
        $alerts[] = ['type' => 'success', 'msg' => t('admin.review_updated', 'Review updated.')];
        $row = array_merge($row, $data);
    }
}

$account_alerts = array_merge(array_map(fn($e) => ['type' => 'error', 'msg' => $e], $errors), $alerts);
$admin_page_title = t('admin.edit_review', 'Edit Review') . ' — GAIA TOURS & TRAVEL';
$admin_topbar_title = t('admin.edit_review', 'Edit Review');
$admin_active = 'reviews';
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
          <h3><i class="fa-solid fa-star"></i> <?= $C(t('admin.edit_review', 'Edit Review')) ?></h3>
          <a class="btn btn-ghost btn-sm" href="index.php"><?= $C(t('admin.back_to_list')) ?></a>
        </div>

        <form method="post" action="edit.php?source=<?= $C($src) ?>&id=<?= (int)$id ?>">
          <?= csrf_field() ?>
          <div class="grid-2">
            <div class="field">
              <label><?= $C(t('admin.reviewer')) ?></label>
              <input type="text" name="reviewer" value="<?= $C($row['reviewer'] ?? $row['author'] ?? '') ?>">
            </div>
            <div class="field">
              <label><?= $C(t('admin.rating')) ?></label>
              <select name="rating">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <option value="<?= $i ?>" <?= (int)($row['rating'] ?? 5) === $i ? 'selected' : '' ?>><?= $i ?> ★</option>
                <?php endfor; ?>
              </select>
            </div>
          </div>
          <?php if (isset($row['place'])): ?>
            <div class="field"><label><?= $C(t('admin.place', 'Place')) ?></label><input type="text" name="place" value="<?= $C($row['place']) ?>"></div>
          <?php endif; ?>
          <?php if (isset($row['date_label'])): ?>
            <div class="field"><label><?= $C(t('admin.date_label', 'Date Label')) ?></label><input type="text" name="date_label" value="<?= $C($row['date_label']) ?>"></div>
          <?php endif; ?>
          <div class="field"><label><?= $C(t('admin.text', 'Review Text')) ?></label><textarea name="text" rows="5"><?= $C($row['text'] ?? '') ?></textarea></div>
          <div class="grid-2">
            <?php if (isset($row['is_active'])): ?>
              <div class="field"><label><?= $C(t('admin.status')) ?></label><input type="checkbox" name="is_active" value="1" <?= (int)$row['is_active'] ? 'checked' : '' ?>> <?= $C(t('admin.approved', 'Approved')) ?></div>
            <?php endif; ?>
            <?php if (isset($row['featured'])): ?>
              <div class="field"><label><?= $C(t('admin.featured')) ?></label><input type="checkbox" name="featured" value="1" <?= (int)$row['featured'] ? 'checked' : '' ?>></div>
            <?php endif; ?>
          </div>
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
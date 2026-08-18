<?php
/**
 * admin/tours/form-detail.php
 * ------------------------------------------------------------
 * Create/Edit for tour sub-entities (itinerary, inclusions,
 * activities, departures, FAQs, reviews).
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$tourId = (int)($_GET['tour_id'] ?? 0);
$tab = $_GET['tab'] ?? 'itinerary';

$tabs = [
    'itinerary'    => ['table'=>'weroad_trip_itineraries',         'tour_col'=>'trip_id',   'fields'=>['day_number','title','title_ar','description','description_ar','image_url']],
    'inclusions'   => ['table'=>'weroad_trip_inclusions',          'tour_col'=>'trip_id',   'fields'=>['include_type','item_text','item_text_ar']],
    'activities'   => ['table'=>'weroad_trip_optional_activities', 'tour_col'=>'trip_id',   'fields'=>['name','name_ar','description','description_ar','price']],
    'departures'   => ['table'=>'weroad_departures',               'tour_col'=>'trip_id',   'fields'=>['start_date','end_date','spots_left','price','is_active']],
    'trip_faqs'    => ['table'=>'weroad_trip_faqs',                'tour_col'=>'trip_id',   'fields'=>['faq_group','group_ar','label','label_ar','question','question_ar','answer','answer_ar']],
    'trip_reviews' => ['table'=>'weroad_reviews',                  'tour_col'=>'trip_id',   'fields'=>['author','author_ar','rating','text','text_ar','date_label']],
];

if (!isset($tabs[$tab])) $tab = 'itinerary';
$cfg = $tabs[$tab];
$table = $cfg['table'];
$tourCol = $cfg['tour_col'];
$fields = $cfg['fields'];

$tour = null;
if ($tourId > 0) {
    $st = $pdo->prepare("SELECT id, name FROM weroad_trips WHERE id = ?");
    $st->execute([$tourId]);
    $tour = $st->fetch();
}
if (!$tour) { http_response_code(404); exit('Tour not found.'); }

$id = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$row = null;
if ($editing) {
    $st = $pdo->prepare("SELECT * FROM `{$table}` WHERE id = ? AND `{$tourCol}` = ?");
    $st->execute([$id, $tourId]);
    $row = $st->fetch() ?: null;
}

$errors = []; $alerts = [];

// Defaults per tab
$defs = [
    'itinerary'=>['day_number'=>1,'title'=>'','title_ar'=>'','description'=>'','description_ar'=>'','image_url'=>''],
    'inclusions'=>['include_type'=>'included','item_text'=>'','item_text_ar'=>''],
    'activities'=>['name'=>'','name_ar'=>'','description'=>'','description_ar'=>'','price'=>0],
    'departures'=>['start_date'=>'','end_date'=>'','spots_left'=>0,'price'=>0,'is_active'=>1],
    'trip_faqs'=>['faq_group'=>'General','group_ar'=>'','label'=>'','label_ar'=>'','question'=>'','question_ar'=>'','answer'=>'','answer_ar'=>''],
    'trip_reviews'=>['author'=>'','author_ar'=>'','rating'=>5,'text'=>'','text_ar'=>'','date_label'=>''],
];
$old = $row ?: $defs[$tab];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $data = [];
    $data[$tourCol] = $tourId;

    foreach ($fields as $f) {
        if ($f === 'is_active') { $data[$f] = !empty($_POST[$f]) ? 1 : 0; continue; }
        $v = $_POST[$f] ?? '';
        switch ($f) {
            case 'day_number': $data[$f] = max(1, (int)$v); break;
            case 'price': $data[$f] = (float)$v; break;
            case 'spots_left': $data[$f] = max(0, (int)$v); break;
            case 'rating': $data[$f] = max(0, min(5, (int)$v)); break;
            default: $data[$f] = trim($v);
        }
    }

    // Image upload for itinerary
    if ($tab === 'itinerary' && !empty($_FILES['image_file']['name']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $up = CrudService::handleUpload($_FILES['image_file'], 'tour_itinerary');
        if ($up !== null) $data['image_url'] = $up;
        elseif (!empty($_POST['image_url'])) $data['image_url'] = trim($_POST['image_url']);
    } elseif ($tab === 'itinerary' && isset($_POST['image_url']) && trim($_POST['image_url']) !== '') {
        $data['image_url'] = trim($_POST['image_url']);
    }

    if ($editing && !$row) {
        $errors[] = 'Invalid record.';
    } else {
        if ($editing) {
            $pdo->prepare("UPDATE `{$table}` SET " . implode(', ', array_map(fn($f)=>'`'.$f.'` = :'.$f, array_keys($data))) . " WHERE id = :id")->execute(array_merge($data, [':id'=>$id]));
            $alerts[]=['type'=>'success','msg'=>'Saved successfully.'];
        } else {
            // Insert
            $cols = array_keys($data);
            $pdo->prepare("INSERT INTO `{$table}` (`" . implode('`, `', $cols) . "`) VALUES (:" . implode(', :', $cols) . ")")->execute($data);
            $id = (int)$pdo->lastInsertId();
            $editing = true;
            $alerts[]=['type'=>'success','msg'=>'Created successfully.'];
        }
        $st = $pdo->prepare("SELECT * FROM `{$table}` WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch(); $old = $row;
    }
}

$account_alerts = array_merge(array_map(fn($e)=>['type'=>'error','msg'=>$e], $errors), $alerts);
$admin_page_title = ($editing?'Edit':'Add').' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = $tour['name'] . ' — ' . t('admin.' . $tab, $tab);
$admin_active = 'tours';
$C = fn($v)=>htmlspecialchars((string)($v ?? ''));
$ret = 'detail.php?tour_id=' . (int)$tourId . '&tab=' . urlencode($tab);
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
          <h3><?= htmlspecialchars(t('admin.'.$tab, ucfirst($tab))) ?></h3>
          <a class="btn btn-ghost btn-sm" href="<?= $C($ret) ?>"><?= htmlspecialchars(t('admin.back_to_list')) ?></a>
        </div>
        <form method="post" action="form-detail.php?tour_id=<?= (int)$tourId ?>&tab=<?= $C($tab) ?><?= $editing?'&id='.(int)$id:'' ?>" enctype="multipart/form-data" novalidate>
          <?= csrf_field() ?>

          <?php if ($tab === 'itinerary'): ?>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.day', 'Day Number')) ?></label><input type="number" name="day_number" value="<?= (int)$old['day_number'] ?>"></div>
              <div class="field"><label><?= $C(t('admin.image')) ?></label>
                <?php if (!empty($old['image_url'])): ?><img class="preview-img" src="<?= $C($old['image_url']) ?>" alt=""><br><?php endif; ?>
                <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp" style="margin-top:8px;">
                <input type="hidden" name="image_url" value="<?= $C($old['image_url'] ?? '') ?>">
              </div>
            </div>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.title')) ?> (EN)</label><input type="text" name="title" value="<?= $C($old['title']) ?>"></div>
              <div class="field"><label><?= $C(t('admin.title')) ?> (AR)</label><input type="text" name="title_ar" value="<?= $C($old['title_ar']) ?>"></div>
            </div>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.description')) ?> (EN)</label><textarea name="description" rows="4"><?= $C($old['description']) ?></textarea></div>
              <div class="field"><label><?= $C(t('admin.description')) ?> (AR)</label><textarea name="description_ar" rows="4"><?= $C($old['description_ar']) ?></textarea></div>
            </div>
          <?php elseif ($tab === 'inclusions'): ?>
            <div class="field"><label><?= $C(t('admin.include_type', 'Type')) ?></label>
              <select name="include_type">
                <option value="included" <?= $old['include_type']==='included'?'selected':'' ?>>Included</option>
                <option value="excluded" <?= $old['include_type']==='excluded'?'selected':'' ?>>Excluded</option>
              </select>
            </div>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.item', 'Item')) ?> (EN)</label><input type="text" name="item_text" value="<?= $C($old['item_text']) ?>"></div>
              <div class="field"><label><?= $C(t('admin.item', 'Item')) ?> (AR)</label><input type="text" name="item_text_ar" value="<?= $C($old['item_text_ar']) ?>"></div>
            </div>
          <?php elseif ($tab === 'activities'): ?>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.name')) ?> (EN)</label><input type="text" name="name" value="<?= $C($old['name']) ?>"></div>
              <div class="field"><label><?= $C(t('admin.name')) ?> (AR)</label><input type="text" name="name_ar" value="<?= $C($old['name_ar']) ?>"></div>
            </div>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.description')) ?> (EN)</label><textarea name="description" rows="3"><?= $C($old['description']) ?></textarea></div>
              <div class="field"><label><?= $C(t('admin.description')) ?> (AR)</label><textarea name="description_ar" rows="3"><?= $C($old['description_ar']) ?></textarea></div>
            </div>
            <div class="field"><label><?= $C(t('admin.price')) ?></label><input type="number" step="0.01" name="price" value="<?= $C($old['price']) ?>"></div>
          <?php elseif ($tab === 'departures'): ?>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.start_date', 'Start Date')) ?></label><input type="date" name="start_date" value="<?= $C($old['start_date']) ?>"></div>
              <div class="field"><label><?= $C(t('admin.end_date', 'End Date')) ?></label><input type="date" name="end_date" value="<?= $C($old['end_date']) ?>"></div>
            </div>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.spots_left', 'Spots Left')) ?></label><input type="number" name="spots_left" value="<?= (int)$old['spots_left'] ?>"></div>
              <div class="field"><label><?= $C(t('admin.price')) ?></label><input type="number" step="0.01" name="price" value="<?= $C($old['price']) ?>"></div>
            </div>
            <div class="field"><label><?= $C(t('admin.status')) ?></label><input type="checkbox" name="is_active" value="1" <?= (int)$old['is_active']?'checked':'' ?>> <?= $C(t('admin.active')) ?></div>
          <?php elseif ($tab === 'trip_faqs'): ?>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.faq_group', 'Group')) ?></label><input type="text" name="faq_group" value="<?= $C($old['faq_group']) ?>"></div>
              <div class="field"><label><?= $C(t('admin.faq_group', 'Group')) ?> (AR)</label><input type="text" name="group_ar" value="<?= $C($old['group_ar']) ?>"></div>
            </div>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.question')) ?> (EN)</label><input type="text" name="question" value="<?= $C($old['question']) ?>"></div>
              <div class="field"><label><?= $C(t('admin.question')) ?> (AR)</label><input type="text" name="question_ar" value="<?= $C($old['question_ar']) ?>"></div>
            </div>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.answer')) ?> (EN)</label><textarea name="answer" rows="4"><?= $C($old['answer']) ?></textarea></div>
              <div class="field"><label><?= $C(t('admin.answer')) ?> (AR)</label><textarea name="answer_ar" rows="4"><?= $C($old['answer_ar']) ?></textarea></div>
            </div>
          <?php elseif ($tab === 'trip_reviews'): ?>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.reviewer')) ?> (EN)</label><input type="text" name="author" value="<?= $C($old['author']) ?>"></div>
              <div class="field"><label><?= $C(t('admin.reviewer')) ?> (AR)</label><input type="text" name="author_ar" value="<?= $C($old['author_ar']) ?>"></div>
            </div>
            <div class="grid-2">
              <div class="field"><label><?= $C(t('admin.rating')) ?></label><input type="number" min="0" max="5" name="rating" value="<?= (int)$old['rating'] ?>"></div>
              <div class="field"><label><?= $C(t('admin.date')) ?></label><input type="text" name="date_label" value="<?= $C($old['date_label']) ?>"></div>
            </div>
            <div class="field"><label><?= $C(t('admin.text', 'Text')) ?> (EN)</label><textarea name="text" rows="4"><?= $C($old['text']) ?></textarea></div>
            <div class="field"><label><?= $C(t('admin.text', 'Text')) ?> (AR)</label><textarea name="text_ar" rows="4"><?= $C($old['text_ar']) ?></textarea></div>
          <?php endif; ?>

          <div class="form-actions">
            <button type="submit" class="btn"><?= $C(t('admin.save')) ?></button>
            <a class="btn btn-ghost" href="<?= $C($ret) ?>"><?= $C(t('admin.cancel')) ?></a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__.'/../components/footer.php'; ?>

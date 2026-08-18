<?php
/**
 * admin/tours/detail.php
 * ------------------------------------------------------------
 * Tour sub-entities management: Itinerary, Inclusions, Optional
 * Activities, Departures, FAQs, Reviews, Categories, Destinations.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$tourId = (int)($_GET['tour_id'] ?? 0);
$tab = $_GET['tab'] ?? 'itinerary';

$tabs = [
    'itinerary'    => ['table'=>'weroad_trip_itineraries',          'title'=>'admin.itinerary',       'tour_col'=>'trip_id'],
    'inclusions'   => ['table'=>'weroad_trip_inclusions',           'title'=>'admin.included',        'tour_col'=>'trip_id'],
    'activities'   => ['table'=>'weroad_trip_optional_activities',  'title'=>'admin.optional_activities', 'tour_col'=>'trip_id'],
    'departures'   => ['table'=>'weroad_departures',                'title'=>'admin.departures',      'tour_col'=>'trip_id'],
    'trip_faqs'    => ['table'=>'weroad_trip_faqs',                 'title'=>'admin.trip_faqs',       'tour_col'=>'trip_id'],
    'trip_reviews' => ['table'=>'weroad_reviews',                   'title'=>'admin.trip_reviews',    'tour_col'=>'trip_id'],
];

if (!isset($tabs[$tab])) $tab = 'itinerary';
$cfg = $tabs[$tab];
$table = $cfg['table'];
$tourCol = $cfg['tour_col'];

// Verify tour exists
$tour = null;
if ($tourId > 0) {
    $st = $pdo->prepare("SELECT id, name FROM weroad_trips WHERE id = ?");
    $st->execute([$tourId]);
    $tour = $st->fetch();
}

if (!$tour) {
    http_response_code(404);
    $admin_page_title = 'Not Found — GAIA TOURS &amp; TRAVEL';
    require __DIR__.'/../components/header.php';
    echo '<div class="admin-app"><div class="admin-main"><div class="admin-content"><div class="card"><div class="muted" style="text-align:center;padding:40px;">Tour not found.</div><div style="text-align:center;"><a class="btn" href="index.php">Back to Tours</a></div></div></div></div></div>';
    require __DIR__.'/../components/footer.php'; exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0 && $action === 'delete') {
        $pdo->prepare("DELETE FROM `{$table}` WHERE id = ?")->execute([$id]);
        admin_flash('Deleted.', 'success');
    }
    header('Location: ' . admin_url('tours/detail.php?tour_id=' . $tourId . '&tab=' . urlencode($tab)));
    exit;
}

$rows = $pdo->prepare("SELECT * FROM `{$table}` WHERE `{$tourCol}` = ? ORDER BY id ASC");
$rows->execute([$tourId]);
$rows = $rows->fetchAll();

$admin_page_title = $tour['name'] . ' — Details';
$admin_topbar_title = $tour['name'] . ' — ' . t($cfg['title']);
$admin_active = 'tours';
$C = fn($v)=>(string)($v ?? '');
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
          <h3><i class="fa-solid fa-mountain-sun"></i> <?= htmlspecialchars($tour['name']) ?></h3>
          <a class="btn btn-ghost btn-sm" href="index.php"><?= htmlspecialchars(t('admin.back_to_list')) ?></a>
        </div>

        <div class="tabs">
          <?php foreach ($tabs as $tk => $tc): ?>
            <a class="tab <?= $tab === $tk ? 'active' : '' ?>" href="?tour_id=<?= (int)$tourId ?>&tab=<?= $tk ?>"><?= htmlspecialchars(t($tc['title'])) ?></a>
          <?php endforeach; ?>
        </div>

        <div style="text-align:right;margin:10px 0;">
          <a class="btn btn-sm" href="form-detail.php?tour_id=<?= (int)$tourId ?>&tab=<?= htmlspecialchars($tab) ?>"><?= htmlspecialchars(t('admin.add_new')) ?></a>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <?php if ($tab === 'itinerary'): ?>
                  <th>Day</th><th>Title</th><th>Image</th>
                <?php elseif ($tab === 'inclusions'): ?>
                  <th>Type</th><th>Item</th>
                <?php elseif ($tab === 'activities'): ?>
                  <th>Name</th><th>Price</th>
                <?php elseif ($tab === 'departures'): ?>
                  <th>Start</th><th>End</th><th>Spots</th><th>Price</th>
                <?php elseif ($tab === 'trip_faqs'): ?>
                  <th>Group</th><th>Question</th>
                <?php elseif ($tab === 'trip_reviews'): ?>
                  <th>Author</th><th>Rating</th><th>Text</th>
                <?php endif; ?>
                <th><?= htmlspecialchars(t('admin.actions')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr><td colspan="10"><div class="muted" style="text-align:center;padding:20px;"><?= htmlspecialchars(t('admin.no_records')) ?></div></td></tr>
              <?php else: foreach ($rows as $r): ?>
                <tr>
                  <td><?= (int)$r['id'] ?></td>
                  <?php if ($tab === 'itinerary'): ?>
                    <td><?= (int)$r['day_number'] ?></td>
                    <td class="code"><?= htmlspecialchars($r['title'] ?? '') ?></td>
                    <td><?= $r['image_url'] ? '<img src="'.htmlspecialchars($r['image_url']).'" style="width:60px;height:40px;object-fit:cover;border-radius:4px;">' : '—' ?></td>
                  <?php elseif ($tab === 'inclusions'): ?>
                    <td><span class="badge badge-<?= $r['include_type'] === 'included' ? 'active' : 'inactive' ?>"><?= htmlspecialchars($r['include_type']) ?></span></td>
                    <td><?= htmlspecialchars($r['item_text'] ?? '') ?></td>
                  <?php elseif ($tab === 'activities'): ?>
                    <td class="code"><?= htmlspecialchars($r['name'] ?? '') ?></td>
                    <td><?= htmlspecialchars(number_format((float)$r['price'], 2)) ?></td>
                  <?php elseif ($tab === 'departures'): ?>
                    <td><?= htmlspecialchars($r['start_date']) ?></td>
                    <td><?= htmlspecialchars($r['end_date'] ?? '') ?></td>
                    <td><?= (int)$r['spots_left'] ?></td>
                    <td class="code"><?= htmlspecialchars(number_format((float)$r['price'], 2)) ?></td>
                  <?php elseif ($tab === 'trip_faqs'): ?>
                    <td><?= htmlspecialchars($r['faq_group'] ?? '') ?></td>
                    <td><?= htmlspecialchars(mb_substr($r['question'] ?? '', 0, 60)) ?></td>
                  <?php elseif ($tab === 'trip_reviews'): ?>
                    <td class="code"><?= htmlspecialchars($r['author'] ?? '') ?></td>
                    <td><?= str_repeat('★', (int)($r['rating'] ?? 0)) ?></td>
                    <td class="truncate"><?= htmlspecialchars(mb_substr($r['text'] ?? '', 0, 60)) ?></td>
                  <?php endif; ?>
                  <td>
                    <div class="actions">
                      <a class="icon-btn" href="form-detail.php?tour_id=<?= (int)$tourId ?>&tab=<?= htmlspecialchars($tab) ?>&id=<?= (int)$r['id'] ?>"><i class="fa-solid fa-pen"></i></a>
                      <form method="post" action="detail.php" style="display:inline;"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="icon-btn danger" type="submit" onclick="return confirm('<?= htmlspecialchars(t('admin.delete_confirm')) ?>')"><i class="fa-solid fa-trash"></i></button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__.'/../components/footer.php'; ?>

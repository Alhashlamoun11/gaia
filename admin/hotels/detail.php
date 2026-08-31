<?php
/**
 * admin/hotels/detail.php
 * ============================================================
 * Hotel sub-entities manager (facilities, offers, amenities).
 * Keyed by hotel_id. Reuses CrudService + existing layout.
 * ============================================================
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$hotelId = (int)($_GET['id'] ?? 0);
$hotel = $hotelId > 0 ? CrudService::find('hotels', $hotelId) : null;
if (!$hotel) {
    http_response_code(404);
    $admin_page_title = t('admin.not_found','Not Found').' — GAIA TOURS &amp; TRAVEL';
    require __DIR__.'/../components/header.php';
    echo '<div class="admin-app"><div class="admin-main"><div class="admin-content"><div class="card"><div class="muted" style="text-align:center;padding:40px;">'.htmlspecialchars(t('admin.not_found_text','Not found.')).'</div></div></div></div></div>';
    require __DIR__.'/../components/footer.php'; exit;
}

$tab = $_GET['tab'] ?? 'facilities';
$tabs = ['facilities','offers'];
if (!in_array($tab, $tabs, true)) $tab = 'facilities';
$tableMap = [
    'facilities'=>'hotel_facilities',
    'offers'=>'hotel_offers',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $tbl = $tableMap[$_POST['tab'] ?? $tab] ?? null;
    if ($tbl && $id > 0) {
        if ($action === 'delete') {
            CrudService::delete($tbl, $id);
            admin_flash(t('admin.deleted','Deleted.'), 'success');
        } elseif ($action === 'toggle') {
            $st = $pdo->prepare("SELECT is_active FROM {$tbl} WHERE id=?");
            $st->execute([$id]);
            $rv = (int)$st->fetchColumn();
            CrudService::setFlag($tbl, $id, 'is_active', $rv ? 0 : 1);
            admin_flash(t('admin.status_updated','Status updated.'), 'success');
        }
        header('Location: ' . admin_url('hotels/detail.php?id='.$hotelId.'&tab='.$tab)); exit;
    }
}

$tbl = $tableMap[$tab];
$rows = $pdo->prepare("SELECT * FROM {$tbl} WHERE hotel_id = ? ORDER BY sort_order ASC, id DESC");
$rows->execute([$hotelId]);
$items = $rows->fetchAll();

$account_alerts = admin_flash_peek() ? [admin_flash()] : [];
$admin_page_title = t('admin.hotel_content','Hotel Content').' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = $hotel['name'];
$admin_active = 'hotels';
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
          <h3><i class="fa-solid fa-hotel"></i> <?= $C($hotel['name']) ?></h3>
          <a class="btn btn-ghost btn-sm" href="index.php"><?= $C(t('admin.back_to_list')) ?></a>
        </div>
        <div class="tabs">
          <?php foreach ($tabs as $tk): ?>
            <a class="tab <?= $tab===$tk?'active':'' ?>" href="detail.php?id=<?= (int)$hotelId ?>&tab=<?= $tk ?>"><?= $C(t('admin.hotel_'.$tk, ucfirst($tk))) ?></a>
          <?php endforeach; ?>
          <a class="btn btn-sm" style="margin-left:auto;" href="form-detail.php?hotel_id=<?= (int)$hotelId ?>&tab=<?= $C($tab) ?>"><?= $C(t('admin.add_new')) ?></a>
            <a class="btn btn-sm" href="policy.php?id=<?= (int)$hotelId ?>" style="margin-left:8px;">Cancellation Policy</a>
        </div>

        <div class="table-wrap" style="margin-top:14px;">
          <table>
            <thead><tr>
              <th>ID</th><th><?= $C(t('admin.title')) ?></th><th><?= $C(t('admin.price')) ?></th>
              <th><?= $C(t('admin.sort_order')) ?></th><th><?= $C(t('admin.status')) ?></th><th><?= $C(t('admin.actions')) ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$items): ?><tr><td colspan="6"><div class="muted" style="text-align:center;padding:20px;"><?= $C(t('admin.no_records')) ?></div></td></tr>
            <?php else: foreach ($items as $r):
                $itemTitle = $r['title'] ?? $r['name'] ?? $r['facility'] ?? ('#'.(int)$r['id']);
            ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td class="code"><?= $C(lang_value($r, 'title', $r['name'] ?? $r['facility'] ?? '')) ?></td>
                <td><?= isset($r['price']) && $r['price'] !== '' ? $C(number_format((float)$r['price'],2)) : '—' ?></td>
                <td><?= (int)($r['sort_order'] ?? 0) ?></td>
                <td><span class="badge badge-<?= (int)($r['is_active']??1)?'active':'inactive' ?>"><?= (int)($r['is_active']??1)?$C(t('admin.active')):$C(t('admin.inactive')) ?></span></td>
                <td>
                  <div class="actions">
                    <a class="icon-btn" href="form-detail.php?hotel_id=<?= (int)$hotelId ?>&tab=<?= $C($tab) ?>&id=<?= (int)$r['id'] ?>"><i class="fa-solid fa-pen"></i></a>
                    <?php if (isset($r['is_active'])): ?>
                    <form method="post" action="detail.php" style="display:inline;"><?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle"><input type="hidden" name="tab" value="<?= $C($tab) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="icon-btn" type="submit"><i class="fa-solid <?= (int)$r['is_active']?'fa-ban':'fa-check' ?>"></i></button>
                    </form>
                    <?php endif; ?>
                    <form method="post" action="detail.php" style="display:inline;"><?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete"><input type="hidden" name="tab" value="<?= $C($tab) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="icon-btn danger" type="submit" onclick="return confirm('<?= $C(t('admin.delete_confirm')) ?>')"><i class="fa-solid fa-trash"></i></button>
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
</content>


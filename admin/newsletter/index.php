<?php
/**
 * admin/newsletter/index.php
 * Newsletter subscribers management with CSV export.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscribers-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Email', 'Subscribed At']);
    $rows = $pdo->query("SELECT id, email, created_at FROM subscribers ORDER BY id DESC")->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, [$r['id'], $r['email'], $r['created_at']]);
    }
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $action = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($action === 'delete' && $id > 0) {
        $pdo->prepare("DELETE FROM subscribers WHERE id=?")->execute([$id]);
        admin_flash(t('admin.deleted','Deleted.'), 'success');
        header('Location: ' . admin_url('newsletter/index.php')); exit;
    }
}

$pg = AdminQuery::pagination(20);
$q  = AdminQuery::search();

$where = ['1=1']; $params = [];
if ($q !== '') { $where[] = 'email LIKE :q'; $params[':q'] = '%'.$q.'%'; }
$wh = implode(' AND ', $where);

$c = $pdo->prepare("SELECT COUNT(*) FROM subscribers WHERE $wh"); $c->execute($params);
$total = (int)$c->fetchColumn();
$pages = max(1, (int)ceil($total / $pg['per_page']));
if ($pg['page'] > $pages) { $pg['page']=$pages; $pg['offset']=($pg['page']-1)*$pg['per_page']; }

$stmt = $pdo->prepare("SELECT * FROM subscribers WHERE $wh ORDER BY id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$url_builder = fn($ov=[]) => '?' . http_build_query(array_merge($_GET, $ov));
$account_alerts = admin_flash_peek() ? [admin_flash()] : [];
$admin_page_title = t('admin.newsletter').' — GAIA TOURS & TRAVEL';
$admin_topbar_title = t('admin.newsletter');
$admin_active = 'newsletter';
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
          <h3><i class="fa-solid fa-envelope-open-text"></i> <?= $C(t('admin.newsletter')) ?></h3>
          <a class="btn btn-sm" href="?export=csv"><i class="fa-solid fa-download"></i> <?= $C(t('admin.export_csv')) ?></a>
        </div>
        <form method="get" action="index.php" class="toolbar">
          <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="text" name="q" value="<?= $C($q) ?>" placeholder="<?= $C(t('admin.search')) ?>..."></div>
          <button type="submit" class="btn btn-sm"><?= $C(t('admin.apply','Apply')) ?></button>
          <a href="index.php" class="btn btn-ghost btn-sm"><?= $C(t('admin.reset','Reset')) ?></a>
        </form>
        <div class="table-wrap">
          <table>
            <thead><tr>
              <th>ID</th>
              <th><?= $C(t('admin.email')) ?></th>
              <th><?= $C(t('admin.created')) ?></th>
              <th><?= $C(t('admin.actions')) ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="4"><div class="muted" style="text-align:center;padding:20px;"><?= $C(t('admin.no_records')) ?></div></td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td class="code"><?= $C($r['email']) ?></td>
                <td><?= $C(date('Y-m-d H:i', strtotime($r['created_at']))) ?></td>
                <td>
                  <div class="actions">
                    <form method="post" action="index.php" style="display:inline;"><?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="icon-btn danger" type="submit" onclick="return confirm('<?= $C(t('admin.delete_confirm')) ?>')"><i class="fa-solid fa-trash"></i></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <?php $page=$pg['page'];$pages=$pages;$total=$total;$offset=$pg['offset'];$per_page=$pg['per_page'];$url_builder=$url_builder; require __DIR__.'/../components/pagination.php'; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__.'/../components/footer.php'; ?>
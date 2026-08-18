<?php
/**
 * admin/messages/index.php
 * Contact messages inbox with read/reply status/delete.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $action = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        if ($action === 'read') {
            $pdo->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?")->execute([$id]);
            admin_flash(t('admin.status_updated','Status updated.'), 'success');
        } elseif ($action === 'unread') {
            $pdo->prepare("UPDATE contact_messages SET is_read=0 WHERE id=?")->execute([$id]);
            admin_flash(t('admin.status_updated','Status updated.'), 'success');
        } elseif ($action === 'replied') {
            $pdo->prepare("UPDATE contact_messages SET replied=1 WHERE id=?")->execute([$id]);
            admin_flash(t('admin.status_updated','Status updated.'), 'success');
        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM contact_messages WHERE id=?")->execute([$id]);
            admin_flash(t('admin.deleted','Deleted.'), 'success');
        }
        header('Location: ' . admin_url('messages/index.php')); exit;
    }
}

$pg = AdminQuery::pagination(15);
$q  = AdminQuery::search();
$st = AdminQuery::statusFilter(['1','0'], '');

$where = ['1=1']; $params = [];
if ($q !== '') { $where[] = '(name LIKE :q OR email LIKE :q OR subject LIKE :q OR message LIKE :q)'; $params[':q'] = '%'.$q.'%'; }
if ($st !== '') { $where[] = 'is_read = :st'; $params[':st'] = (int)$st; }
$wh = implode(' AND ', $where);

$c = $pdo->prepare("SELECT COUNT(*) FROM contact_messages WHERE $wh"); $c->execute($params);
$total = (int)$c->fetchColumn();
$pages = max(1, (int)ceil($total / $pg['per_page']));
if ($pg['page'] > $pages) { $pg['page']=$pages; $pg['offset']=($pg['page']-1)*$pg['per_page']; }

$stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE $wh ORDER BY id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$url_builder = fn($ov=[]) => '?' . http_build_query(array_merge($_GET, $ov));
$account_alerts = admin_flash_peek() ? [admin_flash()] : [];
$admin_page_title = t('admin.messages').' — GAIA TOURS & TRAVEL';
$admin_topbar_title = t('admin.messages');
$admin_active = 'messages';
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
          <h3><i class="fa-solid fa-inbox"></i> <?= $C(t('admin.messages')) ?></h3>
        </div>
        <form method="get" action="index.php" class="toolbar">
          <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="text" name="q" value="<?= $C($q) ?>" placeholder="<?= $C(t('admin.search')) ?>..."></div>
          <select name="status">
            <option value=""><?= $C(t('admin.all_statuses','All Statuses')) ?></option>
            <option value="1" <?=$st==='1'?'selected':''?>><?= $C(t('admin.read','Read')) ?></option>
            <option value="0" <?=$st==='0'?'selected':''?>><?= $C(t('admin.unread','Unread')) ?></option>
          </select>
          <button type="submit" class="btn btn-sm"><?= $C(t('admin.apply','Apply')) ?></button>
          <a href="index.php" class="btn btn-ghost btn-sm"><?= $C(t('admin.reset','Reset')) ?></a>
        </form>
        <div class="table-wrap">
          <table>
            <thead><tr>
              <th><?= $C(t('admin.name')) ?></th>
              <th><?= $C(t('admin.email')) ?></th>
              <th><?= $C(t('admin.subject','Subject')) ?></th>
              <th><?= $C(t('admin.status')) ?></th>
              <th><?= $C(t('admin.created')) ?></th>
              <th><?= $C(t('admin.actions')) ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6"><div class="muted" style="text-align:center;padding:20px;"><?= $C(t('admin.no_records')) ?></div></td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <td class="code"><?= $C($r['name']) ?></td>
                <td><?= $C($r['email']) ?></td>
                <td class="truncate"><?= $C(mb_substr($r['subject'] ?? $r['message'], 0, 50)) ?></td>
                <td>
                  <span class="badge badge-<?= (int)$r['is_read']?'active':'inactive' ?>"><?= (int)$r['is_read']?$C(t('admin.read','Read')):$C(t('admin.unread','Unread')) ?></span>
                  <?php if ((int)$r['replied']): ?><span class="badge badge-active"><?= $C(t('admin.replied','Replied')) ?></span><?php endif; ?>
                </td>
                <td><?= $C(date('Y-m-d H:i', strtotime($r['created_at']))) ?></td>
                <td>
                  <div class="actions">
                    <a class="icon-btn" href="view.php?id=<?= (int)$r['id'] ?>"><i class="fa-solid fa-eye"></i></a>
                    <form method="post" action="index.php" style="display:inline;"><?= csrf_field() ?>
                      <input type="hidden" name="action" value="<?= (int)$r['is_read']?'unread':'read' ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="icon-btn" type="submit"><i class="fa-solid <?= (int)$r['is_read']?'fa-envelope':'fa-envelope-open' ?>"></i></button>
                    </form>
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
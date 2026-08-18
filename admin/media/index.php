<?php
/**
 * admin/media/index.php
 * Media library — upload images/documents, search, delete, preview,
 * replace, folder organization (logical only via key prefix).
 * Reuses the existing `media` table.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $action = $_POST['action'] ?? '';
    if ($action === 'upload' && !empty($_FILES['file']['name'])) {
        $key = trim($_POST['key'] ?? '');
        $res = CrudService::handleUpload($_FILES['file'], 'media');
        if (!$res) {
            admin_flash(t('admin.upload_failed','Upload failed. Allowed: JPG, PNG, WEBP, GIF (max 4MB).'), 'error');
        } else {
            $data = ['file_path'=>$res, 'alt_text'=>trim($_POST['alt_text']??''), 'title'=>trim($_POST['title']??$_FILES['file']['name']), 'sort_order'=>(int)($_POST['sort_order']??0), 'is_active'=>1];
            if ($key !== '') $data['key']=$key;
            CrudService::insert('media',$data);
            admin_flash(t('admin.uploaded','Uploaded successfully.'), 'success');
        }
        header('Location: ' . admin_url('media/index.php')); exit;
    }
    if ($action === 'replace' && !empty($_FILES['file']['name'])) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $st = $pdo->prepare("SELECT file_path FROM media WHERE id=?"); $st->execute([$id]);
            $oldPath = $st->fetchColumn();
            $res = CrudService::handleUpload($_FILES['file'], 'media');
            if ($res) {
                // Delete old file if it was uploaded
                if ($oldPath && strpos($oldPath, 'uploads/') === 0) { @unlink(__DIR__ . '/../../' . $oldPath); }
                $pdo->prepare("UPDATE media SET file_path=? WHERE id=?")->execute([$res, $id]);
                admin_flash(t('admin.media_replace','File replaced.'), 'success');
            } else {
                admin_flash(t('admin.upload_failed','Upload failed.'), 'error');
            }
        }
        header('Location: ' . admin_url('media/index.php')); exit;
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $st = $pdo->prepare("SELECT file_path FROM media WHERE id=?"); $st->execute([$id]);
            $fp = $st->fetchColumn();
            if ($fp && strpos($fp, 'uploads/') === 0) { @unlink(__DIR__ . '/../../' . $fp); }
            $pdo->prepare("DELETE FROM media WHERE id=?")->execute([$id]);
            admin_flash(t('admin.deleted','Deleted.'), 'success');
        }
        header('Location: ' . admin_url('media/index.php')); exit;
    }
    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $st = $pdo->prepare('SELECT is_active FROM media WHERE id=?'); $st->execute([$id]);
            $cur = (int)$st->fetchColumn();
            $pdo->prepare('UPDATE media SET is_active=? WHERE id=?')->execute([$cur?0:1,$id]);
            admin_flash(t('admin.status_updated','Status updated.'), 'success');
        }
        header('Location: ' . admin_url('media/index.php')); exit;
    }
}

$pg = AdminQuery::pagination(24);
$q  = AdminQuery::search();
$s  = AdminQuery::sort(['id','key','title','created_at'], 'id');
$folder = trim($_GET['folder'] ?? '');

$where = ['1=1']; $params = [];
if ($q !== '') { $where[] = '(`key` LIKE :q OR title LIKE :q OR alt_text LIKE :q OR file_path LIKE :q)'; $params[':q'] = '%'.$q.'%'; }
if ($folder !== '') { $where[] = '(`key` LIKE :f OR file_path LIKE :f)'; $params[':f'] = $folder . '%'; }
$wh = implode(' AND ', $where);

$c = $pdo->prepare("SELECT COUNT(*) FROM media WHERE $wh"); $c->execute($params);
$total = (int)$c->fetchColumn();
$pages = max(1, (int)ceil($total / $pg['per_page']));
if ($pg['page'] > $pages) { $pg['page']=$pages; $pg['offset']=($pg['page']-1)*$pg['per_page']; }

$stmt = $pdo->prepare("SELECT * FROM media WHERE $wh ORDER BY {$s['col']} {$s['dir']} LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Logical folders from key prefixes
$folders = [];
foreach ($pdo->query("SELECT `key` FROM media WHERE `key` IS NOT NULL AND `key` != ''")->fetchAll(PDO::FETCH_COLUMN) as $k) {
    $prefix = explode('_', $k)[0];
    if ($prefix !== '') $folders[$prefix] = true;
}
$folders = array_keys($folders);
sort($folders);

$url_builder = fn($ov=[]) => '?' . http_build_query(array_merge($_GET, $ov));
$account_alerts = admin_flash_peek() ? [admin_flash()] : [];
$admin_page_title = t('admin.media').' — GAIA TOURS & TRAVEL';
$admin_topbar_title = t('admin.media');
$admin_active = 'media';
$C = fn($v)=>htmlspecialchars((string)($v ?? ''));

function admin_is_image($path){
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg','jpeg','png','webp','gif','svg'], true);
}
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
          <h3><i class="fa-solid fa-images"></i> <?= $C(t('admin.media')) ?></h3>
        </div>

        <!-- UPLOAD -->
        <form method="post" action="index.php" enctype="multipart/form-data" class="upload-panel">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="upload">
          <div class="grid-2">
            <div class="field"><label><?= $C(t('admin.key')) ?></label><input type="text" name="key" placeholder="hero_home"></div>
            <div class="field"><label><?= $C(t('admin.title')) ?></label><input type="text" name="title" placeholder="Title"></div>
          </div>
          <div class="field"><label><?= $C(t('admin.alt_text')) ?></label><input type="text" name="alt_text" placeholder="Alt text"></div>
          <div class="field"><label><?= $C(t('admin.upload')) ?></label><input type="file" name="file" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml,application/pdf" required></div>
          <div class="field"><label><?= $C(t('admin.sort_order')) ?></label><input type="number" name="sort_order" value="0"></div>
          <div class="form-actions"><button type="submit" class="btn"><i class="fa-solid fa-upload"></i> <?= $C(t('admin.upload')) ?></button></div>
        </form>

        <!-- Folder filter -->
        <div class="tabs" style="margin-top:16px;flex-wrap:wrap;">
          <a class="tab <?= $folder === '' ? 'active' : '' ?>" href="?"><?= $C(t('admin.all', 'All')) ?></a>
          <?php foreach ($folders as $f): ?>
            <a class="tab <?= $folder === $f ? 'active' : '' ?>" href="?folder=<?= $C($f) ?>"><?= $C($f) ?></a>
          <?php endforeach; ?>
        </div>

        <form method="get" action="index.php" class="toolbar" style="margin-top:16px;">
          <?php if ($folder !== ''): ?><input type="hidden" name="folder" value="<?= $C($folder) ?>"><?php endif; ?>
          <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="text" name="q" value="<?= $C($q) ?>" placeholder="<?= $C(t('admin.search')) ?>..."></div>
          <button type="submit" class="btn btn-sm"><?= $C(t('admin.search')) ?></button>
          <a href="index.php" class="btn btn-ghost btn-sm"><?= $C(t('admin.reset','Reset')) ?></a>
        </form>

        <?php if (!$rows): ?>
          <div class="muted" style="text-align:center;padding:30px;"><?= $C(t('admin.no_records')) ?></div>
        <?php else: ?>
          <div class="media-grid">
            <?php foreach ($rows as $m): $img = admin_is_image($m['file_path']); ?>
              <div class="media-item">
                <div class="preview">
                  <?php if ($img): ?><img src="<?= $C($m['file_path']) ?>" alt="<?= $C($m['alt_text'] ?? $m['title'] ?? '') ?>" loading="lazy">
                  <?php else: ?><span class="doc"><i class="fa-solid fa-file"></i></span><?php endif; ?>
                </div>
                <div class="meta">
                  <div class="code"><?= $C($m['key'] ?? $m['title'] ?? ('#' . (int)$m['id'])) ?></div>
                  <div class="path"><?= $C($m['file_path']) ?></div>
                  <div class="actions">
                    <span class="badge badge-<?= (int)$m['is_active']?'active':'inactive' ?>"><?= (int)$m['is_active']?$C(t('admin.active')):$C(t('admin.inactive')) ?></span>
                    <div class="flex">
                      <?php if ($img): ?><a class="icon-btn" href="<?= $C($m['file_path']) ?>" target="_blank" title="<?= $C(t('admin.view')) ?>"><i class="fa-solid fa-eye"></i></a><?php endif; ?>
                      <form method="post" action="index.php" enctype="multipart/form-data" style="display:inline;" class="replace-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="replace">
                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                        <label class="icon-btn" title="<?= $C(t('admin.media_replace')) ?>"><i class="fa-solid fa-rotate"></i>
                          <input type="file" name="file" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;" onchange="this.form.submit()">
                        </label>
                      </form>
                      <form method="post" action="index.php" style="display:inline;"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                        <button class="icon-btn" type="submit"><i class="fa-solid <?= (int)$m['is_active']?'fa-ban':'fa-check' ?>"></i></button>
                      </form>
                      <form method="post" action="index.php" style="display:inline;"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                        <button class="icon-btn danger" type="submit" onclick="return confirm('<?= $C(t('admin.delete_confirm')) ?>')"><i class="fa-solid fa-trash"></i></button>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php $page=$pg['page'];$pages=$pages;$total=$total;$offset=$pg['offset'];$per_page=$pg['per_page'];$url_builder=$url_builder; require __DIR__.'/../components/pagination.php'; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__.'/../components/footer.php'; ?>
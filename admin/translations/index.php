<?php
/**
 * admin/translations/index.php
 * ------------------------------------------------------------
 * Translations Management — edit EN/AR translations without
 * changing any PHP files. Uses the existing `translations` table
 * (key, lang, value, group).
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();

// Handle POST (save)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $value = trim($_POST['value'] ?? '');
        if ($id > 0) {
            $pdo->prepare("UPDATE translations SET `value` = ? WHERE id = ?")->execute([$value, $id]);
            admin_flash(t('admin.settings_saved', 'Settings saved successfully.'), 'success');
        }
        header('Location: ' . admin_url('translations/index.php'));
        exit;
    }

    if ($action === 'add') {
        $key = trim($_POST['key'] ?? '');
        $lang = trim($_POST['lang'] ?? 'en');
        $value = trim($_POST['value'] ?? '');
        $group = trim($_POST['group'] ?? 'general');
        if ($key !== '' && in_array($lang, ['en', 'ar'], true)) {
            // Check for duplicate
            $st = $pdo->prepare("SELECT id FROM translations WHERE `key` = :k AND lang = :l LIMIT 1");
            $st->execute([':k' => $key, ':l' => $lang]);
            if (!$st->fetchColumn()) {
                $pdo->prepare("INSERT INTO translations (`key`, lang, `value`, `group`) VALUES (?, ?, ?, ?)")
                    ->execute([$key, $lang, $value, $group]);
                admin_flash(t('admin.created', 'Created successfully.'), 'success');
            } else {
                admin_flash('Translation key already exists for this language.', 'error');
            }
        }
        header('Location: ' . admin_url('translations/index.php'));
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM translations WHERE id = ?")->execute([$id]);
            admin_flash(t('admin.deleted', 'Deleted.'), 'success');
        }
        header('Location: ' . admin_url('translations/index.php'));
        exit;
    }
}

// Filters
$pg = AdminQuery::pagination(25);
$q  = AdminQuery::search();
$langFilter = $_GET['lang'] ?? '';
$groupFilter = $_GET['group'] ?? '';

$where = ['1=1']; $params = [];
if ($q !== '') { $where[] = '(`key` LIKE :q OR `value` LIKE :q)'; $params[':q'] = '%' . $q . '%'; }
if ($langFilter !== '' && in_array($langFilter, ['en', 'ar'], true)) { $where[] = 'lang = :lang'; $params[':lang'] = $langFilter; }
if ($groupFilter !== '') { $where[] = '`group` = :grp'; $params[':grp'] = $groupFilter; }
$wh = implode(' AND ', $where);

$c = $pdo->prepare("SELECT COUNT(*) FROM translations WHERE $wh"); $c->execute($params);
$total = (int)$c->fetchColumn();
$pages = max(1, (int)ceil($total / $pg['per_page']));
if ($pg['page'] > $pages) { $pg['page'] = $pages; $pg['offset'] = ($pg['page'] - 1) * $pg['per_page']; }

$stmt = $pdo->prepare("SELECT * FROM translations WHERE $wh ORDER BY `key` ASC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Groups for filter
$groups = $pdo->query("SELECT DISTINCT `group` FROM translations ORDER BY `group`")->fetchAll(PDO::FETCH_COLUMN);

$url_builder = fn($ov = []) => '?' . http_build_query(array_merge($_GET, $ov));
$account_alerts = admin_flash_peek() ? [admin_flash()] : [];
$admin_page_title = t('admin.translations') . ' — GAIA TOURS & TRAVEL';
$admin_topbar_title = t('admin.translations');
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
          <h3><i class="fa-solid fa-language"></i> <?= $C(t('admin.translations')) ?></h3>
        </div>

        <!-- Add new translation -->
        <form method="post" action="index.php" class="upload-panel" style="margin-bottom:16px;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add">
          <div class="grid-4">
            <div class="field"><label><?= $C(t('admin.translation_key')) ?></label><input type="text" name="key" placeholder="home.hero_title" required></div>
            <div class="field"><label><?= $C(t('admin.lang')) ?></label>
              <select name="lang">
                <option value="en">English</option>
                <option value="ar">Arabic</option>
              </select>
            </div>
            <div class="field"><label><?= $C(t('admin.translation_group')) ?></label><input type="text" name="group" value="general" placeholder="general"></div>
            <div class="field"><label><?= $C(t('admin.value')) ?></label><input type="text" name="value" placeholder="Translation value" required></div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn"><?= $C(t('admin.add_new')) ?></button>
          </div>
        </form>

        <!-- Filters -->
        <form method="get" action="index.php" class="toolbar">
          <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="text" name="q" value="<?= $C($q) ?>" placeholder="<?= $C(t('admin.search')) ?>..."></div>
          <select name="lang">
            <option value=""><?= $C(t('admin.all', 'All Languages')) ?></option>
            <option value="en" <?= $langFilter === 'en' ? 'selected' : '' ?>>English</option>
            <option value="ar" <?= $langFilter === 'ar' ? 'selected' : '' ?>>Arabic</option>
          </select>
          <select name="group">
            <option value=""><?= $C(t('admin.all', 'All Groups')) ?></option>
            <?php foreach ($groups as $g): ?>
              <option value="<?= $C($g) ?>" <?= $groupFilter === $g ? 'selected' : '' ?>><?= $C($g) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-sm"><?= $C(t('admin.apply', 'Apply')) ?></button>
          <a href="index.php" class="btn btn-ghost btn-sm"><?= $C(t('admin.reset', 'Reset')) ?></a>
        </form>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th><?= $C(t('admin.translation_key')) ?></th>
                <th><?= $C(t('admin.lang')) ?></th>
                <th><?= $C(t('admin.translation_group')) ?></th>
                <th><?= $C(t('admin.value')) ?></th>
                <th><?= $C(t('admin.actions')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr><td colspan="5"><div class="muted" style="text-align:center;padding:20px;"><?= $C(t('admin.no_records')) ?></div></td></tr>
              <?php else: foreach ($rows as $r): ?>
                <tr>
                  <td class="code"><?= $C($r['key']) ?></td>
                  <td><span class="badge"><?= $C($r['lang']) ?></span></td>
                  <td><span class="badge"><?= $C($r['group']) ?></span></td>
                  <td class="truncate" style="max-width:300px;"><?= $C(mb_substr($r['value'], 0, 80)) ?></td>
                  <td>
                    <div class="actions">
                      <a class="icon-btn" href="edit.php?id=<?= (int)$r['id'] ?>"><i class="fa-solid fa-pen"></i></a>
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
        <?php $page = $pg['page']; $pages = $pages; $total = $total; $offset = $pg['offset']; $per_page = $pg['per_page']; $url_builder = $url_builder; require __DIR__ . '/../components/pagination.php'; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../components/footer.php'; ?>
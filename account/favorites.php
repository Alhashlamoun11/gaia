<?php
/**
 * account/favorites.php
 * ------------------------------------------------------------
 * Customer Favorites / Wishlist (DB-ready placeholder).
 *
 * Supports tours, hotels and events. Future expansion can add
 * rooms, transfers, etc. Items are looked up from their source
 * tables and rendered with a View link.
 *
 * SECURITY: all lookups are scoped to the authenticated user_id
 * via FavoriteService. CSRF protected POST actions. All output is
 * XSS-escaped; PDO prepared statements used.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../components/bootstrap.php';
require_once __DIR__ . '/../components/gaia-config.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../services/FavoriteService.php';

$gaia_base         = '';
$gaia_active       = 'home';
$gaia_header_style = 'solid';

require_login();

$userId = (int)auth_id();
$alerts = [];
$typeFilter = trim($_GET['type'] ?? '');
$allowedTypes = FavoriteService::types(); // tour, hotel, event
if ($typeFilter !== '' && !in_array($typeFilter, $allowedTypes, true)) {
    $typeFilter = '';
}

// Remove a favorite (CSRF protected)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        csrf_fail();
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'remove') {
        $rType = (string)($_POST['item_type'] ?? '');
        $rId   = (int)($_POST['item_id'] ?? 0);
        if (in_array($rType, $allowedTypes, true) && $rId > 0) {
            FavoriteService::remove($userId, $rType, $rId);
        }
    }
    header('Location: favorites.php' . ($typeFilter !== '' ? '?type=' . urlencode($typeFilter) : ''));
    exit;
}

$favorites = FavoriteService::list($userId, $typeFilter);
$totalFav  = FavoriteService::count($userId);

// Build enriched items from the source tables.
$enriched = [];
$typeInfo = [
    'tour'  => ['label' => 'account.favorite_tours',  'table' => 'weroad_trips',   'name_col' => 'name',  'url' => 'weroad/trip.php?slug=', 'param' => 'slug'],
    'hotel' => ['label' => 'account.favorite_hotels', 'table' => 'hotels',          'name_col' => 'name',  'url' => 'hotel.php?slug=',       'param' => 'slug'],
    'event' => ['label' => 'account.favorite_events', 'table' => 'events',          'name_col' => 'title', 'url' => 'events.php?slug=',       'param' => 'slug'],
];

if ($favorites) {
    $pdo = getPDO();
    foreach ($favorites as $fav) {
        $ftype = (string)($fav['item_type'] ?? '');
        $fid   = (int)($fav['item_id'] ?? 0);
        if (!isset($typeInfo[$ftype]) || $fid <= 0) {
            continue;
        }
        $info = $typeInfo[$ftype];
        try {
            $stmt = $pdo->prepare("SELECT id, `{$info['name_col']}` AS name, `{$info['param']}` AS slug FROM `{$info['table']}` WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $fid]);
            $row = $stmt->fetch();
            if ($row) {
                $enriched[] = [
                    'id'    => $fid,
                    'type'  => $ftype,
                    'label' => t($info['label']),
                    'name'  => $row['name'] ?? '',
                    'url'   => gaia_url($info['url'] . urlencode((string)($row['slug'] ?? ''))),
                ];
            }
        } catch (PDOException $e) {
            // ignore missing source rows
        }
    }
}

$typeTabs = [];
foreach ($typeInfo as $k => $info) {
    $typeTabs[$k] = t($info['label']);
}

$gaia_page_key   = 'account_favorites';
$gaia_page_title = t('account.favorites') . ' — GAIA TOURS &amp; TRAVEL';
$activeTab = 'favorites';
$base      = '../';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<?php require __DIR__ . '/../components/account-head.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../components/gaia-header.php'; ?>

<div class="account-shell">
  <?php
    $crumbs = [
        ['label' => t('account.dashboard'), 'url' => gaia_url('account/index.php')],
        ['label' => t('account.favorites'), 'url' => ''],
    ];
    require __DIR__ . '/../components/breadcrumbs.php';
  ?>
  <div class="account-layout">
    <?php require __DIR__ . '/../components/user-sidebar.php'; ?>
    <main class="account-main">
      <?php $account_alerts = $alerts; require __DIR__ . '/../components/alert.php'; ?>

      <div class="card">
        <h2><i class="fa-solid fa-heart"></i> <?= htmlspecialchars(t('account.favorites')) ?></h2>
        <p class="sub"><?= htmlspecialchars(t_fmt('account.total_favorites', ['count' => $totalFav])) ?></p>

        <!-- type tabs -->
        <div class="tabs">
          <a class="tab <?= $typeFilter === '' ? 'active' : '' ?>" href="favorites.php"><?= htmlspecialchars(t('account.all')) ?></a>
          <?php foreach ($typeTabs as $k => $label): ?>
            <a class="tab <?= $typeFilter === $k ? 'active' : '' ?>" href="favorites.php?type=<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($label) ?></a>
          <?php endforeach; ?>
        </div>

        <?php if (!$enriched): ?>
          <?php $empty_title = t('account.no_favorites'); $empty_icon = 'fa-heart'; require __DIR__ . '/../components/empty-state.php'; ?>
        <?php else: ?>
          <div class="fav-grid">
            <?php foreach ($enriched as $item): ?>
              <div class="fav-card">
                <div class="fav-card-top">
                  <span class="badge badge-pending"><?= htmlspecialchars($item['label']) ?></span>
                  <form method="post" action="favorites.php" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="item_type" value="<?= htmlspecialchars($item['type']) ?>">
                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                    <button type="submit" class="btn-link btn-danger-link" title="<?= htmlspecialchars(t('account.not_now')) ?>"><i class="fa-solid fa-heart-crack"></i></button>
                  </form>
                </div>
                <div class="fav-card-name"><?= htmlspecialchars($item['name']) ?></div>
                <a class="btn btn-sm btn-ghost" href="<?= htmlspecialchars($item['url']) ?>"><i class="fa-solid fa-eye"></i> <?= htmlspecialchars(t('account.view_all')) ?></a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="fav-hint">
          <i class="fa-solid fa-circle-info"></i> <?= htmlspecialchars(t('account.favorites_placeholder')) ?>
        </div>
      </div>
    </main>
  </div>
</div>

<?php require __DIR__ . '/../components/gaia-footer.php'; ?>
</body>
</html>

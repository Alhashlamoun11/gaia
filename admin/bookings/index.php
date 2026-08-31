<?php
/**
 * admin/bookings/index.php
 * ------------------------------------------------------------
 * Unified booking management across all types (tours, hotels,
 * rooms, transfers, events). Uses tabs, search, status filter,
 * date filter, pagination + sorting. Reuses BookingSummaryService
 * style logic. Cancel action transitions via BookingService.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo       = getPDO();
$adminUser = admin_user();

// ---------- Actions (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $action = $_POST['action'] ?? '';
    $type   = $_POST['type'] ?? '';
    $bid    = (int)($_POST['id'] ?? 0);
    if ($action === 'cancel' && $bid > 0) {
        // Reuse BookingService (validates lifecycle transition).
        $ok = BookingService::transition($type, $bid, BookingService::STATUS_CANCELLED, (int)$adminUser['id']);
        admin_flash($ok ? t('admin.booking_cancelled', 'Booking cancelled.') : t('admin.cancel_failed', 'Could not cancel booking.'), $ok ? 'success' : 'error');
        header('Location: ' . admin_url('bookings/index.php'));
        exit;
    }
}

// ---------- Tables per type ----------
$tables = [
    'tour'     => ['table' => 'weroad_bookings', 'singular' => 'tour'],
    'hotel'    => ['table' => 'hotel_bookings', 'singular' => 'hotel'],
    'room'     => ['table' => 'room_bookings', 'singular' => 'room'],
    'transfer' => ['table' => 'bookings', 'singular' => 'transfer'],
    'event'    => ['table' => 'event_bookings', 'singular' => 'event'],
];

// Active tab (default 'all')
$tab = $_GET['tab'] ?? 'all';
if (!in_array($tab, array_merge(['all'], array_keys($tables)), true)) {
    $tab = 'all';
}

$pg       = AdminQuery::pagination(15);
$q        = AdminQuery::search();
$st       = AdminQuery::statusFilter(['pending','awaiting_payment','paid','confirmed','completed','cancelled','refunded']);
$sort     = AdminQuery::sort(['id','created_at','total_price','status'], 'created_at');
$dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_from'] ?? '')) ? $_GET['date_from'] : '';
$dateTo   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_to'] ?? '')) ? $_GET['date_to'] : '';

// Common filter parts (per-table column names match across booking tables:
// id, booking_reference, guest_name, total_price, status, created_at).
$build = function (string $table) use ($q, $st, $dateFrom, $dateTo) {
    $where = ['1=1'];
    $params = [];
    if ($q !== '') {
        $where[] = '(booking_reference LIKE :q OR guest_name LIKE :q OR guest_email LIKE :q)';
        $params[':q'] = '%' . $q . '%';
    }
    if ($st !== '') { $where[] = 'status = :st'; $params[':st'] = $st; }
    if ($dateFrom !== '') { $where[] = 'DATE(created_at) >= :df'; $params[':df'] = $dateFrom; }
    if ($dateTo !== '') { $where[] = 'DATE(created_at) <= :dt'; $params[':dt'] = $dateTo; }
    return ['sql' => implode(' AND ', $where), 'params' => $params];
};

// Per-type counts (for tabs)
$counts = ['all' => 0];
$totalAll = 0;
foreach ($tables as $tType => $cfg) {
    $tbl = $cfg['table'];
    $c = (int)$pdo->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
    $counts[$tType] = $c;
    $totalAll += $c;
}
$counts['all'] = $totalAll;

// Build combined listing.
function fetch_type_rows(PDO $pdo, string $type, string $table, array $f, string $sortCol, string $sortDir, int $per, int $off, int $page): array
{
    $sql = "SELECT id, booking_reference, guest_name, guest_email, total_price,
                   status, created_at, '{$type}' AS type
            FROM `{$table}`
            WHERE {$f['sql']}
            ORDER BY `{$sortCol}` {$sortDir}
            LIMIT {$per} OFFSET {$off}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($f['params']);
    return $stmt->fetchAll();
}

if ($tab === 'all') {
    $allRows = [];
    $allCount = 0;
    foreach ($tables as $tType => $cfg) {
        $f = $build($cfg['table']);
        $cStmt = $pdo->prepare("SELECT COUNT(*) FROM `{$cfg['table']}` WHERE {$f['sql']}");
        $cStmt->execute($f['params']);
        $allCount += (int)$cStmt->fetchColumn();
        // fetch (pagination applies globally after merge; fetch a bounded set per type)
        $allRows = array_merge($allRows, fetch_type_rows($pdo, $tType, $cfg['table'], $f, $sort['col'], $sort['dir'], $pg['per_page'] * 2, 0, $pg['page']));
    }
    // Merge sort by created_at
    usort($allRows, fn($a, $b) => $sort['dir'] === 'asc'
        ? strcmp((string)$a['created_at'], (string)$b['created_at'])
        : strcmp((string)$b['created_at'], (string)$a['created_at']));
    $total = $allCount;
    $pages = max(1, (int)ceil($total / $pg['per_page']));
    $rows = array_slice($allRows, 0, $pg['per_page']);
} else {
    $cfg = $tables[$tab];
    $f = $build($cfg['table']);
    $cStmt = $pdo->prepare("SELECT COUNT(*) FROM `{$cfg['table']}` WHERE {$f['sql']}");
    $cStmt->execute($f['params']);
    $total = (int)$cStmt->fetchColumn();
    $pages = max(1, (int)ceil($total / $pg['per_page']));
    if ($pg['page'] > $pages) { $pg['page'] = $pages; $pg['offset'] = ($pg['page'] - 1) * $pg['per_page']; }
    $rows = fetch_type_rows($pdo, $tab, $cfg['table'], $f, $sort['col'], $sort['dir'], $pg['per_page'], $pg['offset'], $pg['page']);
}

$url_builder = function (array $ov = []) {
    return '?' . http_build_query(array_merge($_GET, $ov));
};

$account_alerts     = admin_flash_peek() ? [admin_flash()] : [];
$admin_page_title   = t('admin.bookings') . ' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = t('admin.bookings');
$admin_active       = 'bookings';
?>
<?php require __DIR__ . '/../components/header.php'; ?>
<div class="admin-app">
  <?php require __DIR__ . '/../components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/../components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/../components/alert.php'; ?>

      <div class="card">
        <div class="tabs">
          <a class="tab <?= $tab === 'all' ? 'active' : '' ?>" href="?tab=all"><?= htmlspecialchars(t('admin.all', 'All')) ?> (<?= (int)$counts['all'] ?>)</a>
          <?php foreach ($tables as $tType => $cfg): ?>
            <a class="tab <?= $tab === $tType ? 'active' : '' ?>" href="?tab=<?= $tType ?>"><?= htmlspecialchars(t('admin.' . $tType, ucfirst($tType))) ?> (<?= (int)$counts[$tType] ?>)</a>
          <?php endforeach; ?>
        </div>

        <form method="get" action="index.php" class="toolbar">
          <?php if ($tab !== 'all'): ?><input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>"><?php endif; ?>
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="<?= htmlspecialchars(t('admin.search_ref', 'Search reference')) ?>...">
          </div>
          <select name="status">
            <option value=""><?= htmlspecialchars(t('admin.all_statuses', 'All Statuses')) ?></option>
            <?php foreach (['pending','awaiting_payment','paid','confirmed','completed','cancelled','refunded'] as $sopt): ?>
              <option value="<?= $sopt ?>" <?= $st === $sopt ? 'selected' : '' ?>><?= htmlspecialchars(booking_status_label($sopt)) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
          <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
          <button type="submit" class="btn btn-sm"><?= htmlspecialchars(t('admin.apply', 'Apply')) ?></button>
          <a href="?tab=<?= htmlspecialchars($tab) ?>" class="btn btn-ghost btn-sm"><?= htmlspecialchars(t('admin.reset', 'Reset')) ?></a>
        </form>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th><?= AdminQuery::sortLink('booking_reference', t('admin.reference'), ['id','created_at','total_price','status'], 'created_at') ?></th>
                <th><?= htmlspecialchars(t('admin.type', 'Type')) ?></th>
                <th><?= htmlspecialchars(t('admin.customer')) ?></th>
                <th><?= AdminQuery::sortLink('total_price', t('admin.amount'), ['id','created_at','total_price','status'], 'created_at') ?></th>
                <th><?= AdminQuery::sortLink('status', t('admin.status'), ['id','created_at','total_price','status'], 'created_at') ?></th>
                <th><?= AdminQuery::sortLink('created_at', t('admin.created'), ['id','created_at','total_price','status'], 'created_at') ?></th>
                <th><?= htmlspecialchars(t('admin.actions')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr><td colspan="7"><div class="muted" style="text-align:center;padding:20px;"><?= htmlspecialchars(t('admin.no_records')) ?></div></td></tr>
              <?php else: foreach ($rows as $b): ?>
                <?php $ref = $b['booking_reference'] ?: ('#' . (int)$b['id']); ?>
                <tr>
                  <td class="code"><?= htmlspecialchars($ref) ?></td>
                  <td><span class="badge badge-<?= htmlspecialchars($b['type']) ?>"><?= htmlspecialchars($b['type']) ?></span></td>
                  <td><?= htmlspecialchars($b['guest_name'] ?? 'Guest') ?><div class="muted" style="font-size:11px;"><?= htmlspecialchars($b['guest_email'] ?? '') ?></div></td>
                  <td class="code"><?= htmlspecialchars(number_format((float)$b['total_price'], 2)) ?></td>
                  <td><span class="badge badge-<?= htmlspecialchars(normalize_booking_status((string)$b['status'])) ?>"><?= htmlspecialchars(booking_status_label(normalize_booking_status((string)$b['status']))) ?></span></td>
                  <td><?= htmlspecialchars(date('Y-m-d', strtotime((string)$b['created_at']))) ?></td>
                  <td>
                    <div class="actions">
                      <a class="icon-btn" href="view.php?type=<?= htmlspecialchars($b['type']) ?>&id=<?= (int)$b['id'] ?>"><i class="fa-solid fa-eye"></i></a>
                      <?php if (!in_array(normalize_booking_status((string)$b['status']), ['cancelled', 'refunded', 'completed'], true)): ?>
                        <form method="post" action="index.php" style="display:inline;">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="cancel">
                          <input type="hidden" name="type" value="<?= htmlspecialchars($b['type']) ?>">
                          <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                          <button type="submit" class="icon-btn danger" onclick="return confirm('<?= htmlspecialchars(t('admin.cancel_confirm', 'Cancel this booking?')) ?>')"><i class="fa-solid fa-ban"></i></button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <?php
          $page = $pg['page']; $pages = $pages; $total = $total; $offset = $pg['offset']; $per_page = $pg['per_page']; $url_builder = $url_builder;
          require __DIR__ . '/../components/pagination.php';
        ?>
      </div>

    </div>
  </div>
</div>
<?php require __DIR__ . '/../components/footer.php'; ?>

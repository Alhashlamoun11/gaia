<?php
/**
 * account/bookings.php
 * ------------------------------------------------------------
 * My Bookings — unified booking centre for the logged-in
 * customer across ALL booking types (tours, hotels, rooms,
 * events, transfers, taxi).
 *
 * Features:
 *   - Tabs (All / Tours / Hotels / Rooms / Transfers / Events)
 *   - Filters (booking reference, date, status, booking type)
 *   - Sorting (newest / oldest / highest price / lowest price)
 *   - Booking cards with cancel action (pending only)
 *
 * SECURITY: data is loaded via BookingSummaryService (ownership-
 * scoped). Cancel is validated via BookingService::getStatus +
 * transition (ownership-scoped, only pending). CSRF protected POST.
 * All output is XSS-escaped; PDO prepared statements used.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../components/bootstrap.php';
require_once __DIR__ . '/../components/gaia-config.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../auth/csrf.php';
require_once __DIR__ . '/../services/BookingSummaryService.php';
require_once __DIR__ . '/../services/BookingService.php';
require_once __DIR__ . '/../services/PaymentService.php';
require_once __DIR__ . '/../services/InvoiceService.php';

$gaia_base         = '';
$gaia_active       = 'home';
$gaia_header_style = 'solid';

require_login();

$userId = (int)auth_id();
$alerts = [];

// ------------------------------------------------------------
// POST: cancel a booking (pending / awaiting_payment only)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        csrf_fail();
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'cancel') {
        $cType = (string)($_POST['type'] ?? '');
        $cId   = (int)($_POST['id'] ?? 0);
        $allowedTypes = ['transfer', 'taxi', 'tour', 'hotel', 'room', 'event'];
        if (in_array($cType, $allowedTypes, true) && $cId > 0) {
            // Ownership-scoped status check first.
            $current = BookingService::getStatus($cType, $cId, $userId);
            if (in_array($current, ['pending', 'awaiting_payment'], true)) {
                if (BookingService::transition($cType, $cId, BookingService::STATUS_CANCELLED, $userId)) {
                    $alerts[] = ['type' => 'success', 'msg' => t('account.cancel_success')];
                } else {
                    $alerts[] = ['type' => 'error', 'msg' => t('account.cancel_failed')];
                }
            } else {
                $alerts[] = ['type' => 'error', 'msg' => t('account.cannot_cancel')];
            }
        } else {
            $alerts[] = ['type' => 'error', 'msg' => t('account.cannot_cancel')];
        }
    }
}

// ------------------------------------------------------------
// Filters
// ------------------------------------------------------------
$typeFilter   = trim($_GET['type'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$search       = strtolower(trim($_GET['search'] ?? ''));
$dateFilter   = trim($_GET['date'] ?? '');
$sort         = trim($_GET['sort'] ?? '');
$tabFilter    = trim($_GET['tab'] ?? '');

$allowedTypes   = ['tour', 'hotel', 'room', 'event', 'transfer', 'taxi'];
$allowedStatuses = booking_status_list();
$allowedSorts   = ['newest', 'oldest', 'highest', 'lowest'];

// 'tab' overrides 'type' — tabs are a friendlier filter layer.
$tab = $tabFilter !== '' ? $tabFilter : $typeFilter;
if (in_array($tab, ['all', ''], true)) { $tab = ''; }
if ($tab !== '' && !in_array($tab, $allowedTypes, true)) { $tab = ''; }
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) { $statusFilter = ''; }
if ($sort !== '' && !in_array($sort, $allowedSorts, true)) { $sort = 'newest'; }

// ------------------------------------------------------------
// Load all bookings (ownership-scoped) + payments/invoices once.
// ------------------------------------------------------------
$allBookings = BookingSummaryService::getUserSummaries($userId);

// Map latest payment + invoice status per booking to avoid N+1.
$payMap = [];
$payments = PaymentService::getUserPayments($userId);
foreach ($payments as $p) {
    $key = (string)($p['booking_type'] ?? '') . ':' . (int)($p['booking_id'] ?? 0);
    if (!isset($payMap[$key])) {
        $payMap[$key] = normalize_payment_status($p['status'] ?? '');
    }
}
$invMap = [];
$invoices = InvoiceService::getForUser($userId);
foreach ($invoices as $i) {
    $key = (string)($i['booking_type'] ?? '') . ':' . (int)($i['booking_id'] ?? 0);
    if (!isset($invMap[$key])) {
        $invMap[$key] = (string)($i['status'] ?? '');
    }
}

// Enrich each booking with payment/invoice status and stable ref/date.
foreach ($allBookings as &$b) {
    $key = (string)($b['booking_type'] ?? '') . ':' . (int)($b['id'] ?? 0);
    $b['payment_status'] = $payMap[$key] ?? '';
    $b['invoice_status'] = $invMap[$key] ?? '';
    $b['ref'] = (string)($b['booking_reference'] ?? ($b['booking_code'] ?? ''));
    $b['sort_date'] = (string)($b['created_date'] ?? '');
}
unset($b);

// Apply tab filter
if ($tab !== '') {
    $allBookings = array_values(array_filter($allBookings, function ($b) use ($tab) {
        return (string)($b['booking_type'] ?? '') === $tab;
    }));
}

// Apply status filter
if ($statusFilter !== '') {
    $allBookings = array_values(array_filter($allBookings, function ($b) use ($statusFilter) {
        return normalize_booking_status($b['status'] ?? '') === $statusFilter;
    }));
}

// Apply reference / date search
if ($search !== '') {
    $allBookings = array_values(array_filter($allBookings, function ($b) use ($search) {
        return strpos(strtolower((string)$b['ref']), $search) !== false;
    }));
}
if ($dateFilter !== '') {
    $allBookings = array_values(array_filter($allBookings, function ($b) use ($dateFilter) {
        return (string)($b['created_date'] ?? '') === $dateFilter;
    }));
}

// Apply sorting
usort($allBookings, function ($a, $b) use ($sort) {
    if ($sort === 'oldest') { return strcmp((string)$a['sort_date'], (string)$b['sort_date']); }
    if ($sort === 'highest') { return (float)($b['amount'] ?? 0) <=> (float)($a['amount'] ?? 0); }
    if ($sort === 'lowest')  { return (float)($a['amount'] ?? 0) <=> (float)($b['amount'] ?? 0); }
    // default newest
    return strcmp((string)$b['sort_date'], (string)$a['sort_date']);
});

// ------------------------------------------------------------
// Pagination
// ------------------------------------------------------------
$per_page = 9;
$total    = count($allBookings);
$pages    = max(1, (int)ceil($total / $per_page));
$page     = max(1, (int)($_GET['page'] ?? 1));
if ($page > $pages) { $page = $pages; }
$offset   = ($page - 1) * $per_page;
$bookings = array_slice($allBookings, $offset, $per_page);

function bookings_url(array $overrides = []): string
{
    $q = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === '' || $v === null) { unset($q[$k]); } else { $q[$k] = $v; }
    }
    unset($q['lang']);
    return 'bookings.php?' . http_build_query($q);
}

// Tab definitions (order + labels + icons).
$tabs = [
    ['key' => '',            'label' => 'account.all',             'icon' => 'fa-layer-group'],
    ['key' => 'tour',        'label' => 'account.type_tour',       'icon' => 'fa-mountain-sun'],
    ['key' => 'hotel',       'label' => 'account.type_hotel',      'icon' => 'fa-hotel'],
    ['key' => 'room',        'label' => 'account.type_room',       'icon' => 'fa-bed'],
    ['key' => 'transfer',    'label' => 'account.type_transfer',   'icon' => 'fa-car'],
    ['key' => 'event',       'label' => 'account.type_event',      'icon' => 'fa-wand-magic-sparkles'],
];

$gaia_page_key   = 'account_bookings';
$gaia_page_title = t('account.my_bookings') . ' — GAIA TOURS &amp; TRAVEL';
$activeTab = 'bookings';
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
        ['label' => t('account.my_bookings'), 'url' => ''],
    ];
    require __DIR__ . '/../components/breadcrumbs.php';
  ?>
  <div class="account-layout">
    <?php require __DIR__ . '/../components/user-sidebar.php'; ?>
    <main class="account-main">
      <?php $account_alerts = $alerts; require __DIR__ . '/../components/alert.php'; ?>

      <div class="card">
        <h2><i class="fa-solid fa-bookmark"></i> <?= htmlspecialchars(t('account.my_bookings')) ?></h2>
        <p class="sub"><?= htmlspecialchars(t_fmt('account.total_bookings', ['count' => $total])) ?></p>

        <!-- TABS -->
        <div class="tabs">
          <?php foreach ($tabs as $tb): ?>
            <a class="tab <?= $tab === $tb['key'] ? 'active' : '' ?>" href="<?= htmlspecialchars(bookings_url(['tab' => $tb['key'], 'page' => ''])) ?>">
              <i class="fa-solid <?= htmlspecialchars($tb['icon']) ?>"></i> <?= htmlspecialchars(t($tb['label'])) ?>
            </a>
          <?php endforeach; ?>
        </div>

        <!-- FILTERS + SORT -->
        <form method="get" action="bookings.php" class="filters">
          <?php if ($tab !== ''): ?><input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>"><?php endif; ?>
          <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="<?= htmlspecialchars(t('account.search_booking')) ?>">
          <input type="date" name="date" value="<?= htmlspecialchars($dateFilter) ?>">
          <select name="status">
            <option value=""><?= htmlspecialchars(t('account.all_statuses')) ?></option>
            <?php foreach ($allowedStatuses as $st): ?>
              <option value="<?= htmlspecialchars($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= htmlspecialchars(booking_status_label($st)) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="sort">
            <option value="newest"  <?= $sort === 'newest'  ? 'selected' : '' ?>><?= htmlspecialchars(t('account.sort_newest')) ?></option>
            <option value="oldest"  <?= $sort === 'oldest'  ? 'selected' : '' ?>><?= htmlspecialchars(t('account.sort_oldest')) ?></option>
            <option value="highest" <?= $sort === 'highest' ? 'selected' : '' ?>><?= htmlspecialchars(t('account.sort_highest')) ?></option>
            <option value="lowest"  <?= $sort === 'lowest'  ? 'selected' : '' ?>><?= htmlspecialchars(t('account.sort_lowest')) ?></option>
          </select>
          <button type="submit" class="filter-btn"><i class="fa-solid fa-magnifying-glass"></i> <?= htmlspecialchars(t('account.apply_filters')) ?></button>
          <a class="filter-btn" href="<?= htmlspecialchars(bookings_url(['search' => '', 'status' => '', 'sort' => '', 'date' => '', 'page' => ''])) ?>"><i class="fa-solid fa-rotate-left"></i> <?= htmlspecialchars(t('account.reset_filters')) ?></a>
        </form>

        <?php if (!$bookings): ?>
          <?php $empty_title = t('account.no_bookings_found'); $empty_icon = 'fa-bookmark'; require __DIR__ . '/../components/empty-state.php'; ?>
        <?php else: ?>
          <div class="booking-grid">
            <?php foreach ($bookings as $b):
              $booking = $b;
              $show_cancel = in_array(normalize_booking_status($b['status'] ?? ''), ['pending', 'awaiting_payment'], true);
              require __DIR__ . '/../components/booking-card.php';
            endforeach; ?>
          </div>

          <?php
            $url_builder = function (array $o) { return bookings_url($o); };
            require __DIR__ . '/../components/pagination.php';
          ?>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>

<?php require __DIR__ . '/../components/gaia-footer.php'; ?>
</body>
</html>

<?php
/**
 * account/invoices.php
 * ------------------------------------------------------------
 * My Invoices — lists all invoice records for the logged-in
 * customer, ownership-scoped via InvoiceService::getForUser().
 *
 * Displays invoice number, booking reference, amount, status and
 * issue date. Includes a search box and invoice status filter,
 * plus View / Print / Download-PDF (placeholder) actions.
 *
 * SECURITY: InvoiceService::getForUser() scopes every query to the
 * authenticated user_id (prevents IDOR). All output is XSS-escaped.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../components/bootstrap.php';
require_once __DIR__ . '/../components/gaia-config.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../services/InvoiceService.php';

$gaia_base         = '';
$gaia_active       = 'home';
$gaia_header_style = 'solid';

require_login();

$userId = (int)auth_id();
$invoices = InvoiceService::getForUser($userId);

// ------------------------------------------------------------
// Search + status filter
// ------------------------------------------------------------
$search = strtolower(trim($_GET['search'] ?? ''));
$statusFilter = trim($_GET['status'] ?? '');
$allowedStatuses = invoice_status_list(); // draft, issued, paid, void, cancelled
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

if ($search !== '') {
    $invoices = array_values(array_filter($invoices, function ($inv) use ($search) {
        $hay = strtolower((string)($inv['invoice_number'] ?? '') . ' ' . (string)($inv['booking_reference'] ?? ''));
        return strpos($hay, $search) !== false;
    }));
}
if ($statusFilter !== '') {
    $invoices = array_values(array_filter($invoices, function ($inv) use ($statusFilter) {
        return (string)($inv['status'] ?? '') === $statusFilter;
    }));
}

// ------------------------------------------------------------
// Pagination
// ------------------------------------------------------------
$per_page = 9;
$total    = count($invoices);
$pages    = max(1, (int)ceil($total / $per_page));
$page     = max(1, (int)($_GET['page'] ?? 1));
if ($page > $pages) { $page = $pages; }
$offset   = ($page - 1) * $per_page;
$pageRows = array_slice($invoices, $offset, $per_page);

function invoices_url(array $overrides = []): string
{
    $q = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === '' || $v === null) { unset($q[$k]); } else { $q[$k] = $v; }
    }
    unset($q['lang']);
    return 'invoices.php?' . http_build_query($q);
}

$gaia_page_key   = 'account_invoices';
$gaia_page_title = t('account.my_invoices') . ' — GAIA TOURS &amp; TRAVEL';
$activeTab = 'invoices';
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
        ['label' => t('account.my_invoices'), 'url' => ''],
    ];
    require __DIR__ . '/../components/breadcrumbs.php';
  ?>
  <div class="account-layout">
    <?php require __DIR__ . '/../components/user-sidebar.php'; ?>
    <main class="account-main">
      <div class="card">
        <h2><?= htmlspecialchars(t('account.my_invoices')) ?></h2>
        <p class="sub"><?= htmlspecialchars(t_fmt('account.total_invoices', ['count' => $total])) ?></p>

        <!-- Search + filter -->
        <form method="get" action="invoices.php" class="filters">
          <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="<?= htmlspecialchars(t('account.search_invoice')) ?>">
          <select name="status" onchange="this.form.submit()">
            <option value=""><?= htmlspecialchars(t('account.all_statuses')) ?></option>
            <?php foreach ($allowedStatuses as $st): ?>
              <option value="<?= htmlspecialchars($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= htmlspecialchars(invoice_status_label($st)) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="filter-btn"><i class="fa-solid fa-magnifying-glass"></i> <?= htmlspecialchars(t('account.apply_filters')) ?></button>
          <a class="filter-btn" href="<?= htmlspecialchars(invoices_url(['search' => '', 'status' => '', 'page' => ''])) ?>"><i class="fa-solid fa-rotate-left"></i> <?= htmlspecialchars(t('account.reset_filters')) ?></a>
        </form>

        <?php if (!$pageRows): ?>
          <?php $empty_title = t('account.no_invoices_found'); $empty_icon = 'fa-file-invoice-dollar'; require __DIR__ . '/../components/empty-state.php'; ?>
        <?php else: ?>
          <div class="invoice-grid">
            <?php foreach ($pageRows as $inv):
              $invoice = $inv;
              require __DIR__ . '/../components/invoice-card.php';
            endforeach; ?>
          </div>

          <?php
            $url_builder = function (array $o) { return invoices_url($o); };
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

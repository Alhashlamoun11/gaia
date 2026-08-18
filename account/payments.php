<?php
/**
 * account/payments.php
 * ------------------------------------------------------------
 * My Payments — lists all payment records for the logged-in
 * customer, ownership-scoped via PaymentService.
 *
 * Shows payment history with payment reference, booking reference,
 * gateway, method, amount, currency, status and date. Includes a
 * "Retry Payment" placeholder button (visible only for
 * awaiting_payment status). NO gateway integration — placeholder only.
 *
 * SECURITY: PaymentService::getUserPayments() scopes every query to
 * the authenticated user_id (prevents IDOR). Gateway responses are
 * never rendered. All output is XSS-escaped; PDO prepared statements.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../components/bootstrap.php';
require_once __DIR__ . '/../components/gaia-config.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../services/PaymentService.php';

$gaia_base         = '';
$gaia_active       = 'home';
$gaia_header_style = 'solid';

require_login();

$userId = (int)auth_id();

// ------------------------------------------------------------
// Status filter
// ------------------------------------------------------------
$statusFilter = trim($_GET['status'] ?? '');
$allowedStatuses = payment_status_list(); // pending, authorized, paid, failed, cancelled, refunded
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

// Ownership-scoped payments
$payments = PaymentService::getUserPayments($userId);

// Apply status filter
if ($statusFilter !== '') {
    $payments = array_values(array_filter($payments, function ($p) use ($statusFilter) {
        return normalize_payment_status($p['status'] ?? '') === $statusFilter;
    }));
}

// ------------------------------------------------------------
// Pagination
// ------------------------------------------------------------
$per_page = 9;
$total    = count($payments);
$pages    = max(1, (int)ceil($total / $per_page));
$page     = max(1, (int)($_GET['page'] ?? 1));
if ($page > $pages) {
    $page = $pages;
}
$offset   = ($page - 1) * $per_page;
$pageRows = array_slice($payments, $offset, $per_page);

function payments_url(array $overrides = []): string
{
    $q = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === '' || $v === null) {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }
    unset($q['lang']);
    return 'payments.php?' . http_build_query($q);
}

$gaia_page_key   = 'account_payments';
$gaia_page_title = t('account.my_payments') . ' — GAIA TOURS &amp; TRAVEL';
$activeTab = 'payments';
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
        ['label' => t('account.my_payments'), 'url' => ''],
    ];
    require __DIR__ . '/../components/breadcrumbs.php';
  ?>
  <div class="account-layout">
    <?php require __DIR__ . '/../components/user-sidebar.php'; ?>
    <main class="account-main">
      <div class="card">
        <h2><?= htmlspecialchars(t('account.my_payments')) ?></h2>
        <p class="sub"><?= htmlspecialchars(t_fmt('account.total_payments', ['count' => $total])) ?></p>

        <!-- FILTERS -->
        <form method="get" action="payments.php" class="filters">
          <select name="status" onchange="this.form.submit()">
            <option value=""><?= htmlspecialchars(t('account.all_statuses')) ?></option>
            <?php foreach ($allowedStatuses as $st): ?>
              <option value="<?= htmlspecialchars($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= htmlspecialchars(payment_status_label($st)) ?></option>
            <?php endforeach; ?>
          </select>
          <a class="filter-btn" href="<?= htmlspecialchars(payments_url(['status' => '', 'page' => ''])) ?>"><i class="fa-solid fa-rotate-left"></i> <?= htmlspecialchars(t('account.reset_filters')) ?></a>
        </form>

        <?php if (!$pageRows): ?>
          <?php $empty_title = t('account.no_payment_records'); $empty_icon = 'fa-credit-card'; require __DIR__ . '/../components/empty-state.php'; ?>
        <?php else: ?>
          <div class="payment-grid">
            <?php foreach ($pageRows as $p):
              $p['_show_retry'] = true;
              $payment = $p;
              require __DIR__ . '/../components/payment-card.php';
            endforeach; ?>
          </div>

          <?php
            $url_builder = function (array $o) { return payments_url($o); };
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

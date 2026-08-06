<?php
/**
 * account/payments.php
 * ------------------------------------------------------------
 * My Payments — lists all payment records for the logged-in
 * customer, ownership-scoped via PaymentService.
 *
 * Columns:
 *   Payment Reference, Booking Reference, Amount, Currency,
 *   Method, Status, Date
 *
 * Features:
 *   - Status filters (Paid / Pending / Failed / Refunded / All)
 *   - Pagination
 *
 * SECURITY: PaymentService::getUserPayments() scopes every query
 * to the authenticated user_id (prevents IDOR). Gateway responses
 * are never rendered — only safe metadata (amount, status, method).
 * All output is XSS-escaped; PDO prepared statements used.
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
$perPage = 10;
$total   = count($payments);
$pages   = max(1, (int)ceil($total / $perPage));
$page    = max(1, (int)($_GET['page'] ?? 1));
if ($page > $pages) {
    $page = $pages;
}
$offset   = ($page - 1) * $perPage;
$pageRows = array_slice($payments, $offset, $perPage);

// Helper to build a filtered/paginated URL preserving query params
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
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags('account_payments', t('account.my_payments') . ' — GAIA TOURS &amp; TRAVEL') ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<style>
:root{--navy:#1b2a4a;--navy-2:#243761;--teal:#1f6f8f;--teal-dark:#175a75;--ink:#1c1e26;--muted:#6b7280;--line:#e8e6e0;--bg-soft:#f4efe6;--white:#fff;--radius:14px;--shadow:0 10px 30px rgba(27,42,74,0.08);}
*{box-sizing:border-box;}body{margin:0;font-family:'Inter',sans-serif;color:var(--ink);background:var(--bg-soft);-webkit-font-smoothing:antialiased;}
h1,h2,h3{font-family:'Playfair Display',serif;margin:0;}
a{text-decoration:none;color:inherit;}
.account-shell{max-width:1280px;margin:0 auto;padding:32px;}
.account-layout{display:grid;grid-template-columns:260px 1fr;gap:26px;align-items:start;}
.account-sidebar{background:#fff;border:1px solid var(--line);border-radius:18px;padding:20px;box-shadow:var(--shadow);}
.account-sidebar-user{display:flex;gap:12px;align-items:center;padding-bottom:16px;border-bottom:1px solid var(--line);margin-bottom:14px;}
.account-sidebar-user strong{display:block;font-size:14.5px;}
.account-sidebar-user span{display:block;font-size:12px;color:var(--muted);word-break:break-all;}
.account-avatar{width:48px;height:48px;border-radius:50%;object-fit:cover;background:var(--navy);}
.account-nav{display:flex;flex-direction:column;gap:4px;}
.account-nav a{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:10px;font-size:14px;color:var(--ink);transition:.15s;}
.account-nav a:hover{background:#f4efe6;}
.account-nav a.active{background:var(--navy);color:#fff;font-weight:600;}
.account-nav i{width:18px;text-align:center;}
.account-logout-form{margin-top:16px;border-top:1px solid var(--line);padding-top:14px;}
.account-logout-btn{width:100%;display:flex;align-items:center;gap:11px;padding:10px 12px;border:none;background:none;border-radius:10px;font-size:14px;color:#b3261e;cursor:pointer;font-family:inherit;}
.account-logout-btn:hover{background:#fdecea;}
.account-main{min-width:0;}
.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:24px;box-shadow:var(--shadow);margin-bottom:22px;}
.card h2{font-size:20px;margin-bottom:4px;}
.card .sub{color:var(--muted);font-size:14px;margin:0 0 18px;}
.filters{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;}
.filters select,.filters a.filter-btn{padding:10px 14px;border:1px solid var(--line);border-radius:10px;font-size:13.5px;font-family:inherit;background:#fbfaf7;color:var(--ink);}
.filters a.filter-btn{display:inline-flex;align-items:center;gap:6px;text-decoration:none;cursor:pointer;}
.filters a.filter-btn:hover{background:#f4efe6;}
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;min-width:680px;}
th,td{text-align:left;padding:12px 14px;border-bottom:1px solid #f0ede6;font-size:13.5px;}
th{font-size:12px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);}
td .code{font-weight:700;}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;text-transform:uppercase;}
.badge-paid,.badge-authorized{background:#e8f5ee;color:#1b6e43;}
.badge-pending{background:#fff3d6;color:#8a6d00;}
.badge-failed,.badge-cancelled{background:#fdecea;color:#b3261e;}
.badge-refunded{background:#f0ece6;color:#6b6060;}
.empty{color:var(--muted);font-size:14px;padding:20px 0;}
/* Pagination */
.pagination{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:18px;flex-wrap:wrap;font-size:13.5px;}
.pagination .info{color:var(--muted);}
.pagination .pages{display:flex;gap:6px;flex-wrap:wrap;}
.pagination .pages a,.pagination .pages span{min-width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--line);border-radius:8px;font-size:13px;color:var(--ink);background:#fff;text-decoration:none;}
.pagination .pages a:hover{background:#f4efe6;}
.pagination .pages span.current{background:var(--navy);color:#fff;border-color:var(--navy);font-weight:600;}
@media (max-width:860px){.account-layout{grid-template-columns:1fr;}.account-nav{flex-direction:row;flex-wrap:wrap;}.account-logout-form{border-top:none;padding-top:0;}}
</style>
</head>
<body>

<?php require __DIR__ . '/../components/gaia-header.php'; ?>

<div class="account-shell">
  <?php $activeTab = 'payments'; require __DIR__ . '/_layout.php'; ?>

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
      <p class="empty"><?= htmlspecialchars(t('account.no_payments')) ?></p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th><?= htmlspecialchars(t('account.payment_reference')) ?></th>
              <th><?= htmlspecialchars(t('account.booking_reference')) ?></th>
              <th><?= htmlspecialchars(t('account.amount')) ?></th>
              <th><?= htmlspecialchars(t('account.currency')) ?></th>
              <th><?= htmlspecialchars(t('account.payment_method')) ?></th>
              <th><?= htmlspecialchars(t('account.status')) ?></th>
              <th><?= htmlspecialchars(t('account.date')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pageRows as $p): ?>
              <?php
                $status = normalize_payment_status($p['status'] ?? '');
                $ref = $p['booking_reference'] ?? '';
              ?>
              <tr>
                <td class="code">#<?= (int)($p['id'] ?? 0) ?></td>
                <td>
                  <?php if ($ref !== ''): ?>
                    <a class="btn-link" href="<?= gaia_url('account/booking.php') ?>?reference=<?= urlencode($ref) ?>"><?= htmlspecialchars($ref) ?></a>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars(number_format((float)($p['amount'] ?? 0), 2)) ?></td>
                <td><?= htmlspecialchars($p['currency'] ?? 'USD') ?></td>
                <td><?= htmlspecialchars($p['payment_method'] ?? '—') ?></td>
                <td><span class="badge badge-<?= htmlspecialchars($status) ?>"><?= htmlspecialchars(payment_status_label($status)) ?></span></td>
                <td><?= htmlspecialchars($p['created_at'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- PAGINATION -->
      <?php if ($pages > 1): ?>
        <div class="pagination">
          <div class="info">
            <?= htmlspecialchars(t('account.showing')) ?> <?= (int)($offset + 1) ?>–<?= (int)min($total, $offset + $perPage) ?> <?= htmlspecialchars(t('account.of')) ?> <?= (int)$total ?>
          </div>
          <div class="pages">
            <?php if ($page > 1): ?>
              <a href="<?= htmlspecialchars(payments_url(['page' => $page - 1])) ?>">&laquo;</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $pages; $i++): ?>
              <?php if ($i === $page): ?>
                <span class="current"><?= (int)$i ?></span>
              <?php else: ?>
                <a href="<?= htmlspecialchars(payments_url(['page' => $i])) ?>"><?= (int)$i ?></a>
              <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $pages): ?>
              <a href="<?= htmlspecialchars(payments_url(['page' => $page + 1])) ?>">&raquo;</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../components/gaia-footer.php'; ?>
</body>
</html>
</content>

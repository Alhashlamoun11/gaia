<?php
/**
 * account/bookings.php
 * ------------------------------------------------------------
 * My Bookings — grouped booking history for the logged-in customer.
 *
 * Shows all bookings belonging to the authenticated user, grouped by:
 *   Tours / Hotels / Events / Transfers
 *
 * Each row displays:
 *   Booking Reference, Booking Date, Service Name, Status, Amount, Actions
 *
 * Uses BookingRepository::getUser*Bookings() so the same ownership-aware
 * helpers are reused by admin dashboards, hotel managers and future
 * payment reporting.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../components/bootstrap.php';
require_once __DIR__ . '/../components/gaia-config.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../auth/BookingRepository.php';

$gaia_base         = '';
$gaia_active       = 'home';
$gaia_header_style = 'solid';

require_login();

$userId = (int)auth_id();

// Ownership-scoped bookings (never surfaces another user's records).
$tours     = BookingRepository::getUserTourBookings($userId);
$hotels    = BookingRepository::getUserHotelBookings($userId);
$events    = BookingRepository::getUserEventBookings($userId);
$transfers = BookingRepository::getUserTransferBookings($userId);
$total     = count($tours) + count($hotels) + count($events) + count($transfers);

$sections = [
    'tours'     => ['label' => t('account.section_tours'),     'rows' => $tours],
    'hotels'    => ['label' => t('account.section_hotels'),    'rows' => $hotels],
    'events'    => ['label' => t('account.section_events'),    'rows' => $events],
    'transfers' => ['label' => t('account.section_transfers'), 'rows' => $transfers],
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags('account_bookings', t('account.my_bookings') . ' — GAIA TOURS &amp; TRAVEL') ?>
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
.section-head{display:flex;align-items:center;gap:10px;margin:22px 0 12px;padding-bottom:10px;border-bottom:1px solid var(--line);}
.section-head:first-of-type{margin-top:0;}
.section-head h3{font-size:17px;}
.section-head .count{background:var(--navy);color:#fff;border-radius:20px;padding:2px 10px;font-size:12px;font-weight:700;}
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;min-width:640px;}
th,td{text-align:left;padding:12px 14px;border-bottom:1px solid #f0ede6;font-size:13.5px;}
tbody tr:last-child td{border-bottom:none;}
th{font-size:12px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);}
td .code{font-weight:700;}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;text-transform:uppercase;}
.badge-confirmed{background:#e8f5ee;color:#1b6e43;}
.badge-pending{background:#fff3d6;color:#8a6d00;}
.badge-cancelled{background:#fdecea;color:#b3261e;}
.btn-link{color:var(--teal);font-weight:600;font-size:13px;}
.btn-link:hover{text-decoration:underline;}
.empty{color:var(--muted);font-size:14px;}
@media (max-width:860px){.account-layout{grid-template-columns:1fr;}.account-nav{flex-direction:row;flex-wrap:wrap;}.account-logout-form{border-top:none;padding-top:0;}}
</style>
</head>
<body>

<?php require __DIR__ . '/../components/gaia-header.php'; ?>

<div class="account-shell">
  <?php $activeTab = 'bookings'; require __DIR__ . '/_layout.php'; ?>

  <div class="card">
    <h2><?= htmlspecialchars(t('account.my_bookings')) ?></h2>
    <p class="sub"><?= htmlspecialchars(t_fmt('account.total_bookings', ['count' => $total])) ?></p>

    <?php if ($total === 0): ?>
      <p class="empty"><?= htmlspecialchars(t('account.no_bookings')) ?></p>
    <?php else: ?>
      <?php foreach ($sections as $key => $section): ?>
        <?php if (empty($section['rows'])) continue; ?>
        <div class="section-head">
          <h3><?= htmlspecialchars($section['label']) ?></h3>
          <span class="count"><?= count($section['rows']) ?></span>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th><?= htmlspecialchars(t('account.booking_code')) ?></th>
                <th><?= htmlspecialchars(t('account.booking_date')) ?></th>
                <th><?= htmlspecialchars(t('account.service_name')) ?></th>
                <th><?= htmlspecialchars(t('account.status')) ?></th>
                <th><?= htmlspecialchars(t('account.amount')) ?></th>
                <th><?= htmlspecialchars(t('account.actions')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($section['rows'] as $b): ?>
                <tr>
                  <td class="code"><?= htmlspecialchars($b['booking_code']) ?></td>
                  <td><?= htmlspecialchars($b['date']) ?></td>
                  <td><?= htmlspecialchars($b['title']) ?></td>
                  <td><span class="badge badge-<?= htmlspecialchars($b['status']) ?>"><?= htmlspecialchars(booking_status_label($b['status'])) ?></span></td>
                  <td><?= htmlspecialchars(number_format((float)$b['total_price'], 2)) ?></td>
                  <td>
                    <a class="btn-link" href="<?= gaia_url('account/booking.php') ?>?type=<?= urlencode($b['type']) ?>&id=<?= (int)$b['id'] ?>">
                      <?= htmlspecialchars(t('account.view_details')) ?>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../components/gaia-footer.php'; ?>
</body>
</html>

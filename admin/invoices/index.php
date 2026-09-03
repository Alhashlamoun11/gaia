<?php
/**
 * admin/invoices/index.php
 * Invoices & Billing Management Hub
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0 && $action === 'update_status') {
        $newStatus = in_array($_POST['status'] ?? '', ['paid', 'issued', 'draft', 'void', 'cancelled'], true) ? $_POST['status'] : 'issued';
        $pdo->prepare("UPDATE invoices SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $id]);
        admin_flash('Invoice status updated to ' . ucfirst($newStatus) . '.', 'success');
    }
    header('Location: ' . admin_url('invoices/index.php'));
    exit;
}

// Financial KPI Metrics
$kpiTotals = $pdo->query("
    SELECT 
        COUNT(*) AS total_invoices,
        COALESCE(SUM(total_amount), 0) AS total_amount,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) AS total_paid,
        COALESCE(SUM(CASE WHEN status IN ('draft', 'issued') THEN total_amount ELSE 0 END), 0) AS total_pending,
        COALESCE(SUM(CASE WHEN status IN ('void', 'cancelled') THEN total_amount ELSE 0 END), 0) AS total_cancelled
    FROM invoices
")->fetch(PDO::FETCH_ASSOC);

$pg = AdminQuery::pagination(15);
$q = AdminQuery::search();
$statusFilter = trim($_GET['status'] ?? '');

$where = ['1=1'];
$params = [];

if ($statusFilter !== '') {
    $where[] = 'i.status = :st';
    $params[':st'] = $statusFilter;
}

if ($q !== '') {
    $where[] = '(i.invoice_number LIKE :q OR i.booking_reference LIKE :q OR b.booking_code LIKE :q OR b.guest_name LIKE :q OR b.guest_email LIKE :q OR b.full_name LIKE :q OR b.email LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
$wh = implode(' AND ', $where);

$c = $pdo->prepare("
    SELECT COUNT(*) 
    FROM invoices i
    LEFT JOIN bookings b ON b.id = i.booking_id
    WHERE $wh
");
$c->execute($params);
$total = (int)$c->fetchColumn();
$pages = max(1, (int)ceil($total / $pg['per_page']));
if ($pg['page'] > $pages) { $pg['page'] = $pages; $pg['offset'] = ($pg['page'] - 1) * $pg['per_page']; }

$stmt = $pdo->prepare("
    SELECT i.*, 
           COALESCE(i.booking_reference, b.booking_reference, b.booking_code) AS display_booking_ref,
           COALESCE(b.guest_name, b.full_name, 'Guest Customer') AS display_customer_name,
           COALESCE(b.guest_email, b.email, '') AS display_customer_email,
           COALESCE(b.guest_phone, b.phone, '') AS display_customer_phone,
           b.origin,
           b.destination
    FROM invoices i
    LEFT JOIN bookings b ON b.id = i.booking_id
    WHERE $wh
    ORDER BY i.id DESC
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
");
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$url_builder = fn($ov = []) => '?' . http_build_query(array_merge($_GET, $ov));
$account_alerts = admin_flash_peek() ? [admin_flash()] : [];

$admin_page_title = 'Invoices & Billing — GAIA Admin';
$admin_topbar_title = 'Invoices & Billing';
$admin_active = 'invoices';
?>
<?php require __DIR__ . '/../components/header.php'; ?>
<div class="admin-app">
  <?php require __DIR__ . '/../components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/../components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/../components/alert.php'; ?>

      <!-- Financial KPI Dashboard Cards -->
      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:16px; margin-bottom:24px;">
        <div class="card" style="padding:18px; margin:0; border-left:4px solid var(--navy);">
          <div class="muted" style="font-size:12px; font-weight:700; text-transform:uppercase;">Total Invoiced</div>
          <div style="font-size:24px; font-weight:800; color:var(--navy); margin-top:4px;">$<?= number_format((float)($kpiTotals['total_amount'] ?? 0), 2) ?></div>
          <div class="muted" style="font-size:12px; margin-top:2px;"><?= (int)($kpiTotals['total_invoices'] ?? 0) ?> Invoices Generated</div>
        </div>

        <div class="card" style="padding:18px; margin:0; border-left:4px solid #10b981;">
          <div class="muted" style="font-size:12px; font-weight:700; text-transform:uppercase;">Collected / Paid</div>
          <div style="font-size:24px; font-weight:800; color:#10b981; margin-top:4px;">$<?= number_format((float)($kpiTotals['total_paid'] ?? 0), 2) ?></div>
          <div class="muted" style="font-size:12px; margin-top:2px;">Settled &amp; Confirmed</div>
        </div>

        <div class="card" style="padding:18px; margin:0; border-left:4px solid #f59e0b;">
          <div class="muted" style="font-size:12px; font-weight:700; text-transform:uppercase;">Pending / Issued</div>
          <div style="font-size:24px; font-weight:800; color:#f59e0b; margin-top:4px;">$<?= number_format((float)($kpiTotals['total_pending'] ?? 0), 2) ?></div>
          <div class="muted" style="font-size:12px; margin-top:2px;">Awaiting Customer Payment</div>
        </div>

        <div class="card" style="padding:18px; margin:0; border-left:4px solid #ef4444;">
          <div class="muted" style="font-size:12px; font-weight:700; text-transform:uppercase;">Void / Cancelled</div>
          <div style="font-size:24px; font-weight:800; color:#ef4444; margin-top:4px;">$<?= number_format((float)($kpiTotals['total_cancelled'] ?? 0), 2) ?></div>
          <div class="muted" style="font-size:12px; margin-top:2px;">Cancelled &amp; Reconciled</div>
        </div>
      </div>

      <div class="card">
        <div class="card-head">
          <div>
            <h3><i class="fa-solid fa-file-invoice-dollar"></i> Invoices &amp; Billing Ledger</h3>
            <p class="muted" style="margin:4px 0 0; font-size:13px;">Search, inspect, print branded PDF receipts, and manage payment status.</p>
          </div>
        </div>

        <form method="get" action="index.php" class="toolbar">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search invoice #, booking ref, or customer...">
          </div>
          <select name="status" style="max-width:160px; padding:6px 10px; border-radius:6px; border:1px solid var(--line);">
            <option value="">All Statuses</option>
            <option value="paid" <?= $statusFilter==='paid'?'selected':'' ?>>Paid</option>
            <option value="issued" <?= $statusFilter==='issued'?'selected':'' ?>>Issued</option>
            <option value="draft" <?= $statusFilter==='draft'?'selected':'' ?>>Draft</option>
            <option value="void" <?= $statusFilter==='void'?'selected':'' ?>>Void</option>
            <option value="cancelled" <?= $statusFilter==='cancelled'?'selected':'' ?>>Cancelled</option>
          </select>
          <button type="submit" class="btn btn-sm">Filter</button>
          <a href="index.php" class="btn btn-ghost btn-sm">Reset</a>
        </form>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Invoice #</th>
                <th>Booking Ref</th>
                <th>Customer Details</th>
                <th>Date</th>
                <th>Total Amount</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$invoices): ?>
                <tr><td colspan="7"><div class="muted" style="text-align:center;padding:24px;">No invoices found matching criteria.</div></td></tr>
              <?php else: foreach ($invoices as $inv): ?>
                <tr>
                  <td>
                    <strong style="color:var(--navy); font-size:14px; font-family:monospace;">
                      <i class="fa-solid fa-file-invoice" style="color:var(--teal); margin-right:4px;"></i>
                      <?= htmlspecialchars($inv['invoice_number'] ?: ('INV-' . str_pad($inv['id'], 6, '0', STR_PAD_LEFT))) ?>
                    </strong>
                  </td>
                  <td>
                    <?php if (!empty($inv['display_booking_ref'])): ?>
                      <a href="<?= admin_url('bookings/view.php?id=' . (int)$inv['booking_id']) ?>" style="font-weight:700; color:var(--teal); text-decoration:underline;">
                        <?= htmlspecialchars($inv['display_booking_ref']) ?>
                      </a>
                      <div class="muted" style="font-size:11px;"><?= htmlspecialchars(ucfirst($inv['booking_type'] ?? 'Transfer')) ?></div>
                    <?php else: ?>
                      <span class="muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <strong style="color:var(--ink); font-size:13.5px;"><?= htmlspecialchars($inv['display_customer_name']) ?></strong>
                    <div class="muted" style="font-size:12px;"><?= htmlspecialchars($inv['display_customer_email']) ?></div>
                  </td>
                  <td style="font-size:12.5px; color:var(--muted);"><?= htmlspecialchars(substr($inv['issued_at'] ?? $inv['created_at'] ?? '', 0, 10)) ?></td>
                  <td>
                    <strong style="font-size:14.5px; color:var(--navy);">
                      $<?= number_format((float)$inv['total_amount'], 2) ?>
                    </strong>
                    <span class="muted" style="font-size:11px;"><?= htmlspecialchars($inv['currency'] ?: 'USD') ?></span>
                  </td>
                  <td>
                    <?php
                      $st = strtolower($inv['status'] ?? 'draft');
                      $stBadges = [
                        'paid' => ['bg'=>'#dcfce7', 'color'=>'#15803d', 'lbl'=>'Paid'],
                        'issued' => ['bg'=>'#fef3c7', 'color'=>'#92400e', 'lbl'=>'Issued'],
                        'draft' => ['bg'=>'#e0f2fe', 'color'=>'#0369a1', 'lbl'=>'Draft'],
                        'void' => ['bg'=>'#ede9fe', 'color'=>'#6d28d9', 'lbl'=>'Void'],
                        'cancelled' => ['bg'=>'#fee2e2', 'color'=>'#b91c1c', 'lbl'=>'Cancelled'],
                      ];
                      $badge = $stBadges[$st] ?? $stBadges['draft'];
                    ?>
                    <span class="badge" style="background:<?= $badge['bg'] ?>; color:<?= $badge['color'] ?>; font-weight:700;">
                      <?= $badge['lbl'] ?>
                    </span>
                  </td>
                  <td>
                    <div class="actions">
                      <a class="icon-btn" href="view.php?id=<?= (int)$inv['id'] ?>" title="View Invoice"><i class="fa-solid fa-eye"></i></a>
                      <a class="icon-btn" href="print.php?id=<?= (int)$inv['id'] ?>" target="_blank" title="Print / PDF Invoice"><i class="fa-solid fa-print"></i></a>
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

<?php
/**
 * admin/invoices/view.php
 * Invoice Detailed View and Payment Status Manager
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    admin_flash('Invalid invoice ID.', 'error');
    header('Location: ' . admin_url('invoices/index.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $newStatus = in_array($_POST['status'] ?? '', ['paid', 'issued', 'draft', 'void', 'cancelled'], true) ? $_POST['status'] : 'issued';

    $pdo->prepare("UPDATE invoices SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $id]);
    admin_flash('Invoice status updated successfully.', 'success');
    header('Location: ' . admin_url('invoices/view.php?id=' . $id));
    exit;
}

$stmt = $pdo->prepare("
    SELECT i.*, 
           COALESCE(i.booking_reference, b.booking_reference, b.booking_code) AS display_booking_ref,
           COALESCE(b.guest_name, b.full_name, 'Guest Customer') AS display_customer_name,
           COALESCE(b.guest_email, b.email, '') AS display_customer_email,
           COALESCE(b.guest_phone, b.phone, '') AS display_customer_phone,
           b.origin,
           b.destination,
           b.pickup_date,
           b.passenger_count
    FROM invoices i
    LEFT JOIN bookings b ON b.id = i.booking_id
    WHERE i.id = ?
");
$stmt->execute([$id]);
$inv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inv) {
    admin_flash('Invoice not found.', 'error');
    header('Location: ' . admin_url('invoices/index.php'));
    exit;
}

// Fetch any associated payments
$payStmt = $pdo->prepare("SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC");
$payStmt->execute([(int)$inv['booking_id']]);
$payments = $payStmt->fetchAll(PDO::FETCH_ASSOC);

$admin_page_title = 'Invoice ' . htmlspecialchars($inv['invoice_number'] ?: ('INV-' . str_pad($inv['id'], 6, '0', STR_PAD_LEFT))) . ' — GAIA Admin';
$admin_topbar_title = 'Invoice Details';
$admin_active = 'invoices';
$account_alerts = admin_flash_peek() ? [admin_flash()] : [];
?>
<?php require __DIR__ . '/../components/header.php'; ?>
<div class="admin-app">
  <?php require __DIR__ . '/../components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/../components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/../components/alert.php'; ?>

      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
        <div>
          <a class="btn btn-ghost btn-sm" href="index.php" style="margin-bottom:8px;"><i class="fa-solid fa-arrow-left"></i> Back to Invoices</a>
          <h2 style="margin:0; font-size:22px; color:var(--navy);">
            Invoice <?= htmlspecialchars($inv['invoice_number'] ?: ('INV-' . str_pad($inv['id'], 6, '0', STR_PAD_LEFT))) ?>
          </h2>
        </div>
        <div style="display:flex; gap:10px;">
          <a class="btn btn-primary btn-sm" href="print.php?id=<?= (int)$inv['id'] ?>" target="_blank"><i class="fa-solid fa-print"></i> Print / PDF Receipt</a>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:2fr 1fr; gap:20px;">
        <!-- Left Column: Invoice Details & Breakdown -->
        <div class="card" style="margin:0;">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:1px solid var(--line); padding-bottom:18px; margin-bottom:18px;">
            <div>
              <div style="font-size:18px; font-weight:800; color:var(--navy);">GAIA TOURS &amp; TRAVEL</div>
              <div class="muted" style="font-size:12px; margin-top:2px;">Airport Transfers, Chauffeur &amp; Tourism Services</div>
              <div class="muted" style="font-size:12px;">Amman, Jordan · info@gaiatours.com</div>
            </div>
            <div style="text-align:right;">
              <span class="badge badge-<?= $inv['status']==='paid'?'active':'inactive' ?>" style="font-size:13px; font-weight:800;">
                STATUS: <?= strtoupper(htmlspecialchars($inv['status'] ?: 'DRAFT')) ?>
              </span>
              <div class="muted" style="font-size:12px; margin-top:6px;">Issue Date: <?= htmlspecialchars(substr($inv['issued_at'] ?? $inv['created_at'] ?? '', 0, 10)) ?></div>
            </div>
          </div>

          <!-- Billed To & Service Details -->
          <div class="grid-2" style="margin-bottom:20px;">
            <div style="background:#fbfaf7; padding:14px; border-radius:8px; border:1px solid var(--line);">
              <div style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--muted); margin-bottom:6px;">Billed To</div>
              <strong style="font-size:14px; color:var(--ink);"><?= htmlspecialchars($inv['display_customer_name']) ?></strong>
              <div style="font-size:12.5px; color:var(--muted); margin-top:2px;"><?= htmlspecialchars($inv['display_customer_email']) ?></div>
              <div style="font-size:12.5px; color:var(--muted);"><?= htmlspecialchars($inv['display_customer_phone']) ?></div>
            </div>

            <div style="background:#fbfaf7; padding:14px; border-radius:8px; border:1px solid var(--line);">
              <div style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--muted); margin-bottom:6px;">Booking Service</div>
              <strong style="font-size:14px; color:var(--navy);">
                <?php if (!empty($inv['origin']) && !empty($inv['destination'])): ?>
                  <?= htmlspecialchars($inv['origin']) ?> → <?= htmlspecialchars($inv['destination']) ?>
                <?php else: ?>
                  <?= htmlspecialchars(ucfirst($inv['booking_type'] ?? 'Transfer Service')) ?>
                <?php endif; ?>
              </strong>
              <div style="font-size:12.5px; color:var(--muted); margin-top:2px;">Booking Ref: <strong><?= htmlspecialchars($inv['display_booking_ref'] ?: '—') ?></strong></div>
              <?php if (!empty($inv['pickup_date'])): ?>
                <div style="font-size:12.5px; color:var(--muted);">Service Date: <?= htmlspecialchars($inv['pickup_date']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Invoice Line Items Table -->
          <table style="width:100%; border-collapse:collapse; margin-bottom:20px;">
            <thead>
              <tr style="background:#f8fafc; border-bottom:2px solid var(--line);">
                <th style="padding:10px 12px; text-align:left; font-size:12px;">Description</th>
                <th style="padding:10px 12px; text-align:right; font-size:12px;">Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr style="border-bottom:1px solid var(--line);">
                <td style="padding:12px;">
                  <strong><?= htmlspecialchars(ucfirst($inv['booking_type'] ?? 'Transfer')) ?> Booking Service</strong>
                  <?php if (!empty($inv['passenger_count'])): ?>
                    <div class="muted" style="font-size:12px;"><?= (int)$inv['passenger_count'] ?> Passengers</div>
                  <?php endif; ?>
                </td>
                <td style="padding:12px; text-align:right; font-weight:700;">
                  $<?= number_format((float)($inv['subtotal'] > 0 ? $inv['subtotal'] : $inv['total_amount']), 2) ?>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Financial Calculation Breakdown -->
          <div style="max-width:300px; margin-left:auto; font-size:13.5px; line-height:1.8;">
            <div style="display:flex; justify-content:space-between;">
              <span class="muted">Subtotal:</span>
              <strong>$<?= number_format((float)($inv['subtotal'] > 0 ? $inv['subtotal'] : $inv['total_amount']), 2) ?></strong>
            </div>
            <?php if ((float)$inv['discount_amount'] > 0): ?>
              <div style="display:flex; justify-content:space-between; color:#15803d;">
                <span>Discount / Promo:</span>
                <strong>-$<?= number_format((float)$inv['discount_amount'], 2) ?></strong>
              </div>
            <?php endif; ?>
            <?php if ((float)$inv['tax_amount'] > 0): ?>
              <div style="display:flex; justify-content:space-between; color:var(--muted);">
                <span>Tax:</span>
                <strong>+$<?= number_format((float)$inv['tax_amount'], 2) ?></strong>
              </div>
            <?php endif; ?>
            <div style="display:flex; justify-content:space-between; font-size:18px; font-weight:800; color:var(--navy); border-top:2px solid var(--line); padding-top:8px; margin-top:8px;">
              <span>Total Amount:</span>
              <span>$<?= number_format((float)$inv['total_amount'], 2) ?> <?= htmlspecialchars($inv['currency'] ?: 'USD') ?></span>
            </div>
          </div>
        </div>

        <!-- Right Column: Status Controls & Payment Ledger -->
        <div>
          <div class="card" style="margin:0 0 20px;">
            <h4 style="margin:0 0 14px; font-size:15px; color:var(--navy);"><i class="fa-solid fa-gear"></i> Update Invoice Status</h4>
            <form method="post" action="view.php?id=<?= (int)$id ?>">
              <?= csrf_field() ?>
              <div class="field">
                <label>Payment Status</label>
                <select name="status" style="width:100%; padding:8px 12px; border-radius:6px; border:1px solid var(--line);">
                  <option value="paid" <?= $inv['status']==='paid'?'selected':'' ?>>Paid / Settled</option>
                  <option value="issued" <?= $inv['status']==='issued'?'selected':'' ?>>Issued / Awaiting</option>
                  <option value="draft" <?= $inv['status']==='draft'?'selected':'' ?>>Draft</option>
                  <option value="void" <?= $inv['status']==='void'?'selected':'' ?>>Void</option>
                  <option value="cancelled" <?= $inv['status']==='cancelled'?'selected':'' ?>>Cancelled</option>
                </select>
              </div>

              <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; margin-top:10px;">
                <i class="fa-solid fa-save"></i> Save Status
              </button>
            </form>
          </div>

          <!-- Associated Payments -->
          <div class="card" style="margin:0;">
            <h4 style="margin:0 0 12px; font-size:15px; color:var(--navy);"><i class="fa-solid fa-receipt"></i> Payment Transactions</h4>
            <?php if (!$payments): ?>
              <div class="muted" style="font-size:12.5px;">No recorded gateway transactions for this booking.</div>
            <?php else: foreach ($payments as $p): ?>
              <div style="background:#fbfaf7; border:1px solid var(--line); border-radius:8px; padding:10px; margin-bottom:8px; font-size:12.5px;">
                <div style="display:flex; justify-content:space-between; font-weight:700;">
                  <span>$<?= number_format((float)$p['amount'], 2) ?> <?= htmlspecialchars($p['currency']) ?></span>
                  <span class="badge badge-<?= $p['status']==='completed'?'active':'inactive' ?>"><?= htmlspecialchars(ucfirst($p['status'])) ?></span>
                </div>
                <div class="muted" style="margin-top:4px;">Method: <?= htmlspecialchars(ucfirst($p['payment_method'] ?? 'Card')) ?></div>
                <div class="muted" style="font-size:11px; font-family:monospace;">TxID: <?= htmlspecialchars($p['transaction_id'] ?: 'N/A') ?></div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../components/footer.php'; ?>

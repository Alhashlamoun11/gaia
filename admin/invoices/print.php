<?php
/**
 * admin/invoices/print.php
 * Clean, Printable Branded Invoice Template (PDF-Ready)
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();
$id = (int)($_GET['id'] ?? 0);

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
    die('Invoice not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice_<?= htmlspecialchars($inv['invoice_number'] ?: ('INV-' . str_pad($inv['id'], 6, '0', STR_PAD_LEFT))) ?></title>
<style>
  body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #1e293b; background: #fff; margin: 0; padding: 40px; font-size: 14px; }
  .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 8px; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; border-bottom: 2px solid #0f172a; padding-bottom: 20px; }
  .logo { font-size: 24px; font-weight: 900; color: #0f172a; letter-spacing: 1px; }
  .logo span { color: #0284c7; }
  .sub-text { font-size: 12px; color: #64748b; margin-top: 4px; line-height: 1.5; }
  .inv-title { text-align: right; }
  .inv-title h1 { margin: 0; font-size: 26px; color: #0f172a; }
  .inv-meta { font-size: 13px; color: #64748b; margin-top: 6px; }
  .badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 800; text-transform: uppercase; margin-top: 6px; }
  .badge-paid { background: #dcfce7; color: #15803d; }
  .badge-issued { background: #fef3c7; color: #92400e; }
  .badge-draft { background: #e0f2fe; color: #0369a1; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 30px; }
  .section-title { font-size: 12px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
  th { background: #f8fafc; color: #334155; font-size: 12px; font-weight: 700; text-transform: uppercase; padding: 12px; text-align: left; border-bottom: 2px solid #cbd5e1; }
  td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
  .total-table { width: 280px; margin-left: auto; margin-bottom: 30px; }
  .total-table td { padding: 6px 0; border: none; font-size: 13.5px; }
  .total-table .grand-total { font-size: 18px; font-weight: 900; color: #0f172a; border-top: 2px solid #0f172a; padding-top: 10px; }
  .footer { border-top: 1px solid #e2e8f0; padding-top: 18px; text-align: center; font-size: 12px; color: #94a3b8; }
  @media print {
    body { padding: 0; }
    .invoice-box { border: none; padding: 0; }
    .no-print { display: none !important; }
  }
</style>
</head>
<body>

<div class="no-print" style="max-width:800px; margin: 0 auto 20px; display:flex; justify-content:space-between;">
  <button onclick="window.print()" style="background:#0f172a; color:#fff; border:none; padding:10px 18px; border-radius:6px; font-weight:700; cursor:pointer;">
    🖨️ Print / Save as PDF
  </button>
  <button onclick="window.close()" style="background:#e2e8f0; color:#334155; border:none; padding:10px 18px; border-radius:6px; font-weight:700; cursor:pointer;">
    ✕ Close
  </button>
</div>

<div class="invoice-box">
  <div class="header">
    <div>
      <div class="logo">GAIA <span>TOURS</span></div>
      <div class="sub-text">
        GAIA Tours &amp; Chauffeur Services Co.<br>
        Airport Road, Amman, Jordan<br>
        Email: info@gaiatours.com · Tel: +962 6 500 0000
      </div>
    </div>
    <div class="inv-title">
      <h1>INVOICE</h1>
      <div class="inv-meta">
        <strong>#<?= htmlspecialchars($inv['invoice_number'] ?: ('INV-' . str_pad($inv['id'], 6, '0', STR_PAD_LEFT))) ?></strong><br>
        Date: <?= htmlspecialchars(substr($inv['issued_at'] ?? $inv['created_at'] ?? '', 0, 10)) ?><br>
        <?php $st = strtolower($inv['status'] ?? 'draft'); ?>
        <span class="badge badge-<?= in_array($st, ['paid', 'issued', 'draft'], true) ? $st : 'draft' ?>">
          <?= strtoupper(htmlspecialchars($inv['status'] ?: 'DRAFT')) ?>
        </span>
      </div>
    </div>
  </div>

  <div class="grid">
    <div>
      <div class="section-title">Billed To</div>
      <strong style="font-size:15px;"><?= htmlspecialchars($inv['display_customer_name']) ?></strong><br>
      <?= htmlspecialchars($inv['display_customer_email']) ?><br>
      <?= htmlspecialchars($inv['display_customer_phone']) ?>
    </div>
    <div>
      <div class="section-title">Service Details</div>
      <strong>Booking Reference:</strong> <?= htmlspecialchars($inv['display_booking_ref'] ?: 'N/A') ?><br>
      <strong>Service Type:</strong> <?= htmlspecialchars(ucfirst($inv['booking_type'] ?? 'Airport Transfer')) ?><br>
      <?php if (!empty($inv['origin']) && !empty($inv['destination'])): ?>
        <strong>Route:</strong> <?= htmlspecialchars($inv['origin']) ?> → <?= htmlspecialchars($inv['destination']) ?><br>
      <?php endif; ?>
      <?php if (!empty($inv['pickup_date'])): ?>
        <strong>Pickup Date:</strong> <?= htmlspecialchars($inv['pickup_date']) ?><br>
      <?php endif; ?>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Description</th>
        <th style="text-align:center;">Qty / Pax</th>
        <th style="text-align:right;">Amount (<?= htmlspecialchars($inv['currency'] ?: 'USD') ?>)</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>
          <strong><?= htmlspecialchars(ucfirst($inv['booking_type'] ?? 'Transfer')) ?> Booking Service</strong>
          <?php if (!empty($inv['origin']) && !empty($inv['destination'])): ?>
            <div style="font-size:12px; color:#64748b;"><?= htmlspecialchars($inv['origin']) ?> → <?= htmlspecialchars($inv['destination']) ?></div>
          <?php endif; ?>
        </td>
        <td style="text-align:center;"><?= !empty($inv['passenger_count']) ? (int)$inv['passenger_count'] : 1 ?></td>
        <td style="text-align:right; font-weight:700;">
          $<?= number_format((float)($inv['subtotal'] > 0 ? $inv['subtotal'] : $inv['total_amount']), 2) ?>
        </td>
      </tr>
    </tbody>
  </table>

  <table class="total-table">
    <tr>
      <td style="color:#64748b;">Subtotal:</td>
      <td style="text-align:right; font-weight:700;">$<?= number_format((float)($inv['subtotal'] > 0 ? $inv['subtotal'] : $inv['total_amount']), 2) ?></td>
    </tr>
    <?php if ((float)$inv['discount_amount'] > 0): ?>
      <tr style="color:#15803d;">
        <td>Discount:</td>
        <td style="text-align:right; font-weight:700;">-$<?= number_format((float)$inv['discount_amount'], 2) ?></td>
      </tr>
    <?php endif; ?>
    <?php if ((float)$inv['tax_amount'] > 0): ?>
      <tr style="color:#64748b;">
        <td>Tax:</td>
        <td style="text-align:right;">+$<?= number_format((float)$inv['tax_amount'], 2) ?></td>
      </tr>
    <?php endif; ?>
    <tr class="grand-total">
      <td>Total Due:</td>
      <td style="text-align:right;">$<?= number_format((float)$inv['total_amount'], 2) ?> <?= htmlspecialchars($inv['currency'] ?: 'USD') ?></td>
    </tr>
  </table>

  <div class="footer">
    Thank you for choosing GAIA Tours &amp; Travel. For billing inquiries, contact billing@gaiatours.com.<br>
    This is a computer-generated invoice and valid without signature.
  </div>
</div>

</body>
</html>

<?php
/**
 * account/invoice-print.php
 * ------------------------------------------------------------
 * Printable invoice (browser print view) for the logged-in customer.
 *
 * Route: account/invoice-print.php?invoice={id}
 *
 * SECURITY: The invoice is loaded via InvoiceService::get($id, $userId)
 * which scopes the query to the authenticated user_id. If the user
 * does NOT own the invoice (or it does not exist), a 403 is returned.
 * This prevents IDOR: User A can never print User B's invoice.
 *
 * No PDF generation — this is a clean, print-optimised HTML layout.
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

$userId   = (int)auth_id();
$invoiceId = (int)($_GET['invoice'] ?? 0);

// Ownership-scoped lookup — returns null for missing OR not-owned rows.
$invoice = $invoiceId > 0 ? InvoiceService::get($invoiceId, $userId) : null;
$forbidden = ($invoice === null);

if ($forbidden) {
    http_response_code(403);
}

$customer = auth_user();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars(t('account.print_invoice')) ?> — GAIA TOURS &amp; TRAVEL</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--navy:#1b2a4a;--teal:#1f6f8f;--ink:#1c1e26;--muted:#6b7280;--line:#e8e6e0;}
*{box-sizing:border-box;}
body{margin:0;font-family:'Inter',sans-serif;color:var(--ink);background:#eef0f4;-webkit-font-smoothing:antialiased;}
h1,h2,h3{font-family:'Playfair Display',serif;margin:0;}
.invoice-page{max-width:820px;margin:40px auto;padding:0 20px;}
.invoice{
  background:#fff;border-radius:16px;box-shadow:0 10px 30px rgba(27,42,74,.1);
  padding:48px 52px;position:relative;
}
.invoice.forbidden{text-align:center;padding:80px 40px;}
.invoice.forbidden .code{font-family:'Playfair Display',serif;font-size:64px;color:var(--teal);font-weight:800;}
.invoice.forbidden p{color:var(--muted);margin:10px 0 24px;}
.btn{display:inline-block;background:var(--teal);color:#fff;border:none;border-radius:10px;padding:12px 24px;font-size:14px;font-weight:700;cursor:pointer;text-decoration:none;}
.invoice-head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap;border-bottom:2px solid var(--navy);padding-bottom:24px;margin-bottom:28px;}
.brand{font-family:'Playfair Display',serif;font-size:26px;font-weight:800;color:var(--navy);}
.brand span{color:var(--teal);}
.invoice-meta{text-align:right;}
.invoice-meta .label{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;}
.invoice-meta .value{font-weight:700;font-size:16px;margin-top:2px;}
.parties{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:28px;}
.party h3{font-size:13px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:10px;}
.party p{margin:3px 0;font-size:14px;line-height:1.5;}
.party p strong{display:inline-block;min-width:70px;color:var(--muted);font-weight:500;}
table{width:100%;border-collapse:collapse;margin-bottom:24px;}
th,td{padding:12px 14px;border-bottom:1px solid var(--line);text-align:left;font-size:14px;}
th{background:#f4efe6;font-size:12px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);}
td .code{font-weight:700;}
.totals{max-width:340px;margin-left:auto;}
.totals .row{display:flex;justify-content:space-between;padding:8px 0;font-size:14px;border-bottom:1px solid var(--line);}
.totals .row.grand{font-weight:800;font-size:17px;border-bottom:none;padding-top:14px;}
.totals .row.grand .val{color:var(--teal);}
.invoice-foot{margin-top:32px;padding-top:18px;border-top:1px solid var(--line);font-size:12px;color:var(--muted);text-align:center;}
.toolbar{display:flex;justify-content:flex-end;gap:12px;margin-bottom:20px;}
.toolbar .btn{display:inline-flex;align-items:center;gap:8px;}
@media print{
  body{background:#fff;}
  .invoice-page{margin:0;padding:0;max-width:none;}
  .invoice{box-shadow:none;border-radius:0;padding:24px;}
  .toolbar{display:none;}
}
@media (max-width:640px){.parties{grid-template-columns:1fr;}.invoice{padding:28px 22px;}}
</style>
</head>
<body>

<div class="invoice-page">
  <?php if ($forbidden): ?>
    <div class="invoice forbidden">
      <div class="code">403</div>
      <h2><?= htmlspecialchars(t('account.unauthorized')) ?></h2>
      <p><?= htmlspecialchars(t('account.invoice_not_found')) ?></p>
      <a class="btn" href="<?= gaia_url('account/invoices.php') ?>"><?= htmlspecialchars(t('account.back_to_invoices')) ?></a>
    </div>
  <?php else: ?>
    <div class="toolbar">
      <button class="btn" onclick="window.print()"><i class="fa-solid fa-print"></i> <?= htmlspecialchars(t('account.print_invoice')) ?></button>
      <a class="btn" href="<?= gaia_url('account/invoices.php') ?>" style="background:var(--navy);"><i class="fa-solid fa-arrow-left"></i> <?= htmlspecialchars(t('account.back_to_invoices')) ?></a>
    </div>

    <div class="invoice">
      <div class="invoice-head">
        <div class="brand">GAIA <span>TOURS &amp; TRAVEL</span></div>
        <div class="invoice-meta">
          <div class="label"><?= htmlspecialchars(t('account.invoice_number')) ?></div>
          <div class="value"><?= htmlspecialchars($invoice['invoice_number'] ?? '') ?></div>
          <div class="label" style="margin-top:10px;"><?= htmlspecialchars(t('account.issue_date')) ?></div>
          <div class="value" style="font-size:14px;"><?= htmlspecialchars($invoice['issued_at'] ?? '') ?></div>
        </div>
      </div>

      <div class="parties">
        <div class="party">
          <h3><?= htmlspecialchars(t('account.billed_to')) ?></h3>
          <p><strong><?= htmlspecialchars(t('account.customer')) ?></strong> <?= htmlspecialchars(trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''))) ?></p>
          <p><strong><?= htmlspecialchars(t('account.guest_email')) ?></strong> <?= htmlspecialchars($customer['email'] ?? '') ?></p>
          <?php if (!empty($customer['phone'])): ?>
            <p><strong><?= htmlspecialchars(t('account.guest_phone')) ?></strong> <?= htmlspecialchars($customer['phone']) ?></p>
          <?php endif; ?>
        </div>
        <div class="party">
          <h3><?= htmlspecialchars(t('account.invoice_from')) ?></h3>
          <p><strong><?= htmlspecialchars(t('account.service_name')) ?></strong> <?= htmlspecialchars(t('account.booking_type_' . $invoice['booking_type'], $invoice['booking_type'])) ?></p>
          <p><strong><?= htmlspecialchars(t('account.booking_reference')) ?></strong> <?= htmlspecialchars($invoice['booking_reference'] ?? '—') ?></p>
          <p><strong><?= htmlspecialchars(t('account.invoice_status')) ?></strong> <?= htmlspecialchars(invoice_status_label((string)($invoice['status'] ?? 'draft'))) ?></p>
        </div>
      </div>

      <table>
        <thead>
          <tr>
            <th><?= htmlspecialchars(t('account.description')) ?></th>
            <th style="text-align:right;"><?= htmlspecialchars(t('account.amount')) ?></th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><?= htmlspecialchars(t_fmt('account.invoice_line', ['type' => booking_type_label((string)($invoice['booking_type'] ?? '')), 'ref' => $invoice['booking_reference'] ?? ''])) ?></td>
            <td class="code" style="text-align:right;"><?= htmlspecialchars(number_format((float)($invoice['subtotal'] ?? 0), 2)) ?> <?= htmlspecialchars($invoice['currency'] ?? 'USD') ?></td>
          </tr>
          <?php if ((float)($invoice['tax_amount'] ?? 0) > 0): ?>
            <tr>
              <td><?= htmlspecialchars(t('account.tax')) ?></td>
              <td style="text-align:right;"><?= htmlspecialchars(number_format((float)$invoice['tax_amount'], 2)) ?> <?= htmlspecialchars($invoice['currency'] ?? 'USD') ?></td>
            </tr>
          <?php endif; ?>
          <?php if ((float)($invoice['discount_amount'] ?? 0) > 0): ?>
            <tr>
              <td><?= htmlspecialchars(t('account.discount')) ?></td>
              <td style="text-align:right;">-<?= htmlspecialchars(number_format((float)$invoice['discount_amount'], 2)) ?> <?= htmlspecialchars($invoice['currency'] ?? 'USD') ?></td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>

      <div class="totals">
        <div class="row"><span><?= htmlspecialchars(t('account.subtotal')) ?></span><span><?= htmlspecialchars(number_format((float)($invoice['subtotal'] ?? 0), 2)) ?></span></div>
        <?php if ((float)($invoice['tax_amount'] ?? 0) > 0): ?>
          <div class="row"><span><?= htmlspecialchars(t('account.tax')) ?></span><span><?= htmlspecialchars(number_format((float)$invoice['tax_amount'], 2)) ?></span></div>
        <?php endif; ?>
        <?php if ((float)($invoice['discount_amount'] ?? 0) > 0): ?>
          <div class="row"><span><?= htmlspecialchars(t('account.discount')) ?></span><span>-<?= htmlspecialchars(number_format((float)$invoice['discount_amount'], 2)) ?></span></div>
        <?php endif; ?>
        <div class="row grand"><span><?= htmlspecialchars(t('account.total')) ?></span><span class="val"><?= htmlspecialchars(number_format((float)($invoice['total_amount'] ?? 0), 2)) ?> <?= htmlspecialchars($invoice['currency'] ?? 'USD') ?></span></div>
      </div>

      <div class="invoice-foot">
        <?= htmlspecialchars(t('account.invoice_footer')) ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js"></script>
</body>
</html>
</content>

<?php
/**
 * components/invoice-card.php
 * ------------------------------------------------------------
 * REUSABLE invoice row/card for the customer portal
 * (used on account/invoices.php).
 *
 * Expected variables (set by the caller):
 *   $invoice : array with keys:
 *     id, invoice_number, booking_reference, booking_type,
 *     total_amount, currency, status, issued_at
 * ------------------------------------------------------------
 */
if (!function_exists('invoice_status_label')) {
    require_once __DIR__ . '/../auth/helpers.php';
}
$invoice = $invoice ?? [];

$id       = (int)($invoice['id'] ?? 0);
$number   = (string)($invoice['invoice_number'] ?? '');
$ref      = (string)($invoice['booking_reference'] ?? '');
$type     = (string)($invoice['booking_type'] ?? '');
$amount   = (float)($invoice['total_amount'] ?? 0);
$currency = (string)($invoice['currency'] ?? 'USD');
$status   = (string)($invoice['status'] ?? 'draft');
$issued   = (string)($invoice['issued_at'] ?? '');

$printUrl = gaia_url('account/invoice-print.php') . '?invoice=' . $id;
?>
<div class="invoice-card">
  <div class="invoice-card-head">
    <div>
      <span class="code"><?= htmlspecialchars($number) ?></span>
      <span class="muted"><?= htmlspecialchars(function_exists('booking_type_label') ? booking_type_label($type) : $type) ?></span>
    </div>
    <div class="invoice-card-status">
      <?php $status_value = $status; $status_kind = 'invoice'; require __DIR__ . '/booking-status.php'; ?>
    </div>
  </div>
  <div class="invoice-card-row">
    <span class="muted"><?= htmlspecialchars(t('account.booking_reference', 'Booking')) ?></span>
    <strong class="code"><?= htmlspecialchars($ref ?: '—') ?></strong>
  </div>
  <div class="invoice-card-row">
    <span class="muted"><?= htmlspecialchars(t('account.issue_date', 'Issued')) ?></span>
    <strong><?= htmlspecialchars($issued) ?></strong>
  </div>
  <div class="invoice-card-row">
    <span class="muted"><?= htmlspecialchars(t('account.total_amount', 'Total')) ?></span>
    <strong class="invoice-card-amount"><?= htmlspecialchars(number_format($amount, 2)) ?> <?= htmlspecialchars($currency) ?></strong>
  </div>
  <div class="invoice-card-actions">
    <a class="btn-link" href="<?= htmlspecialchars($printUrl) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-eye"></i> <?= htmlspecialchars(t('account.view_invoice')) ?></a>
    <span style="margin:0 4px;">·</span>
    <a class="btn-link" href="<?= htmlspecialchars($printUrl) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-print"></i> <?= htmlspecialchars(t('account.print_invoice')) ?></a>
    <span style="margin:0 4px;">·</span>
    <button type="button" class="btn-link" onclick="alert('<?= htmlspecialchars(t('account.pdf_placeholder', 'PDF download will be available soon.')) ?>')"><i class="fa-solid fa-file-pdf"></i> <?= htmlspecialchars(t('account.download_pdf')) ?></button>
  </div>
</div>
<style>
.invoice-card{background:#fff;border:1px solid var(--line,#e8e6e0);border-radius:14px;padding:18px;box-shadow:var(--shadow,0 10px 30px rgba(27,42,74,0.05));display:flex;flex-direction:column;gap:10px;}
.invoice-card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;}
.invoice-card-head .code{font-weight:700;font-size:14px;display:block;}
.invoice-card-head .muted{font-size:12.5px;}
.invoice-card-status{flex:none;}
.invoice-card-row{display:flex;justify-content:space-between;align-items:center;gap:10px;font-size:13.5px;}
.invoice-card-row .muted{font-size:12.5px;}
.invoice-card-amount{color:var(--teal,#1f6f8f);}
.invoice-card-actions{padding-top:10px;border-top:1px solid #f0ede6;font-size:13px;display:flex;align-items:center;flex-wrap:wrap;}
.invoice-card-actions .btn-link{background:none;border:none;padding:0;font-size:13px;}
</style>

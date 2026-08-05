<?php
/**
 * booking.php — WeRoad booking checkout page
 * ------------------------------------------------------------
 * Loads a trip + departure, shows gender counters, room
 * preferences, extras (flexible cancellation, private room),
 * and a dynamic order summary. Posts to process_booking.php.
 * All user-facing labels come from the `translations` table.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../components/gaia-config.php';

$tripId      = (int)($_GET['trip_id'] ?? 0);
$departureId = (int)($_GET['departure_id'] ?? 0);

try {
    $pdo = getWeRoadPDO();

    // Load trip
    $tripStmt = $pdo->prepare("SELECT * FROM weroad_trips WHERE id = :id AND is_active = 1 LIMIT 1");
    $tripStmt->execute([':id' => $tripId]);
    $trip = $tripStmt->fetch();

    if (!$trip) {
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><title>' . t('tours.not_found_title') . '</title><link rel="stylesheet" href="styles.css"></head><body>';
        echo '<div class="wrap" style="padding:80px 0;text-align:center;"><h1>' . t('tours.not_found_title') . '</h1><p style="color:var(--muted);">' . t('tours.not_found_text') . '</p><a class="btn btn-primary" href="' . gaia_url('search.php', '../') . '" style="margin-top:20px;">' . t('tours.browse_trips') . '</a></div>';
        echo '</body></html>';
        exit;
    }

    // Load departure
    $depStmt = $pdo->prepare("SELECT * FROM weroad_departures WHERE id = :id AND trip_id = :trip_id AND is_active = 1 LIMIT 1");
    $depStmt->execute([':id' => $departureId, ':trip_id' => $tripId]);
    $departure = $depStmt->fetch();

    if (!$departure) {
        // Fall back to the first available departure
        $depStmt2 = $pdo->prepare("SELECT * FROM weroad_departures WHERE trip_id = :trip_id AND is_active = 1 ORDER BY start_date LIMIT 1");
        $depStmt2->execute([':trip_id' => $tripId]);
        $departure = $depStmt2->fetch();
    }

    if (!$departure) {
        http_response_code(404);
        echo '<p>' . t('tours.trip_no_departures') . '</p>';
        exit;
    }

} catch (PDOException $e) {
    echo '<pre>Error: ' . htmlspecialchars($e->getMessage()) . '</pre>';
    exit;
}

// Pricing (from tour_extras DB with config fallback)
$flexFee   = tour_extra_price('flexible_cancellation', defined('WR_PRICE_FLEXIBLE_CANCELLATION') ? WR_PRICE_FLEXIBLE_CANCELLATION : 99.00);
$roomFee   = tour_extra_price('private_room', defined('WR_PRICE_PRIVATE_ROOM') ? WR_PRICE_PRIVATE_ROOM : 247.00);
$basePrice = (float)$departure['price'];

$pageTitle = 'Book: ' . $trip['name'] . ' — GAIA Tours';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags('tour_booking', $pageTitle) ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<link rel="stylesheet" href="styles.css">
</head>
<body>

<?php
$gaia_base = '../';
$gaia_active = 'tours';
$gaia_header_style = 'solid';
require_once __DIR__ . '/../components/gaia-config.php';
require __DIR__ . '/../components/gaia-header.php';
?>

<div class="wrap booking-page">
  <div class="stepper">
    <div class="step active"><span class="dot"></span><?= t('tours.booking_step1') ?></div>
    <div class="step"><span class="dot"></span><?= t('tours.booking_step2') ?></div>
    <div class="step"><span class="dot"></span><?= t('tours.booking_step3') ?></div>
    <div class="step"><span class="dot"></span><?= t('tours.booking_step4') ?></div>
  </div>

  <?php if (isset($_GET['error'])): ?>
    <div style="padding:16px;background:#FFEDEE;border-radius:12px;color:#C94F4F;font-weight:600;margin-bottom:20px;">
      <?php echo htmlspecialchars($_GET['error']); ?>
    </div>
  <?php endif; ?>

  <form class="booking-layout" method="post" action="<?= gaia_url('process_booking.php', '../') ?>" id="bookingForm">

    <div class="booking-main">
      <h4 class="mandatory"><?= t('tours.booking_mandatory') ?></h4>

      <input type="hidden" name="trip_id" value="<?php echo (int)$trip['id']; ?>">
      <input type="hidden" name="departure_id" value="<?php echo (int)$departure['id']; ?>">
      <input type="hidden" name="trip_name" value="<?php echo htmlspecialchars($trip['name']); ?>">
      <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($departure['start_date']); ?>">
      <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($departure['end_date']); ?>">

      <div class="form-field" style="margin-bottom:18px;">
        <label style="font-weight:700;font-size:14px;"><?= t('tours.booking_fullname') ?></label>
        <input type="text" name="full_name" required style="width:100%;border:1.5px solid var(--border);border-radius:12px;padding:13px 14px;font-size:14.5px;font-family:inherit;outline:none;">
      </div>
      <div class="form-field" style="margin-bottom:18px;">
        <label style="font-weight:700;font-size:14px;"><?= t('tours.booking_email') ?></label>
        <input type="email" name="email" required style="width:100%;border:1.5px solid var(--border);border-radius:12px;padding:13px 14px;font-size:14.5px;font-family:inherit;outline:none;">
      </div>
      <div class="form-field" style="margin-bottom:26px;">
        <label style="font-weight:700;font-size:14px;"><?= t('tours.booking_phone') ?></label>
        <input type="tel" name="phone" style="width:100%;border:1.5px solid var(--border);border-radius:12px;padding:13px 14px;font-size:14.5px;font-family:inherit;outline:none;">
      </div>

      <h2><?= t('tours.booking_who') ?></h2>
      <a href="<?= gaia_url('contact') ?>" class="hint"><?= t('tours.booking_why_gender') ?></a>

      <div class="gender-row">
        <div class="gender-col">
          <div class="avatar">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 5-6 8-6s6.5 2 8 6"/></svg>
          </div>
          <label><?= t('tours.booking_female') ?></label>
          <div class="counter">
            <button type="button" aria-label="Decrease">–</button>
            <span class="num">0</span>
            <button type="button" aria-label="Increase">+</button>
          </div>
          <input type="hidden" name="travellers_female" value="0" class="guest-input">
        </div>
        <div class="gender-divider"></div>
        <div class="gender-col">
          <div class="avatar">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 5-6 8-6s6.5 2 8 6"/></svg>
          </div>
          <label><?= t('tours.booking_male') ?></label>
          <div class="counter">
            <button type="button" aria-label="Decrease">–</button>
            <span class="num">0</span>
            <button type="button" aria-label="Increase">+</button>
          </div>
          <input type="hidden" name="travellers_male" value="0" class="guest-input">
        </div>
      </div>

      <div class="section-label"><?= t('tours.booking_included_label') ?></div>
      <div class="section-sub"><?= t('tours.booking_included_sub') ?></div>

      <label class="check-card">
        <input type="checkbox" name="mixed_room" value="1" checked>
        <div>
          <strong><?= t('tours.booking_mixed_title') ?></strong>
          <p><?= t('tours.booking_mixed_desc') ?></p>
        </div>
      </label>

      <div class="info-card">
        <strong><?= t('tours.booking_insurance_title') ?></strong>
        <p><?= t('tours.booking_insurance_text') ?> <a href="<?= gaia_url('contact') ?>"><?= t('tours.booking_more_info') ?></a>.</p>
      </div>

      <div class="section-label"><?= t('tours.booking_options_label') ?></div>

      <div class="extra-card">
        <div class="extra-card-left">
          <input type="checkbox" id="flex-cancel" name="flexible_cancellation" value="1">
          <div>
            <strong><?= t('tours.booking_flex_title') ?></strong>
            <p><?= t('tours.booking_flex_desc') ?></p>
            <a href="<?= gaia_url('contact') ?>"><?= t('tours.booking_whats_covered') ?></a>
          </div>
        </div>
        <div class="extra-price"><?= t_fmt('tours.booking_extra_price', ['price' => (int)$flexFee]) ?></div>
      </div>

      <div class="extra-card">
        <div class="extra-card-left">
          <input type="checkbox" id="private-room" name="private_room" value="1">
          <div>
            <strong><?= t('tours.booking_room_title') ?></strong>
            <p><?= t('tours.booking_room_desc') ?></p>
            <a href="<?= gaia_url('contact') ?>"><?= t('tours.booking_what_it_means') ?></a>
          </div>
        </div>
        <div class="extra-price"><?= t_fmt('tours.booking_extra_price', ['price' => (int)$roomFee]) ?></div>
      </div>

      <div class="form-field" style="margin-top:18px;margin-bottom:18px;">
        <label style="font-weight:700;font-size:14px;"><?= t('checkout.promo_code', 'Promo code') ?></label>
        <div style="display:flex;gap:10px;">
          <input type="text" name="promo_code" placeholder="<?= t('tours.booking_promo_ph') ?>" style="flex:1;border:1.5px solid var(--border);border-radius:12px;padding:12px 14px;font-size:14px;font-family:inherit;outline:none;">
          <button type="button" class="btn btn-outline btn-sm" id="promoApply"><?= t('tours.booking_apply') ?></button>
        </div>
        <span id="promoMsg" style="font-size:13px;font-weight:600;"></span>
      </div>

      <button class="btn btn-primary" id="booking-continue" disabled style="margin-top:20px; min-width:220px;" type="submit"><?= t('tours.booking_continue') ?></button>
    </div>

    <aside class="summary-card">
      <div class="eyebrow"><?= t('tours.booking_your_trip') ?></div>
      <h3><?php echo htmlspecialchars($trip['name']); ?></h3>
      <div class="amount" id="summary-amount">€<?php echo number_format($basePrice, 0); ?></div>
      <div class="summary-dates">
        <div><span><?= t('tours.booking_from') ?></span><strong><?php echo date('D, d M y', strtotime($departure['start_date'])); ?></strong><small><?php echo htmlspecialchars($trip['category'] ?? ''); ?></small></div>
        <div style="align-self:center; color:var(--muted-2);">→</div>
        <div style="text-align:right;"><span><?= t('tours.booking_to') ?></span><strong><?php echo date('D, d M y', strtotime($departure['end_date'])); ?></strong><small><?php echo htmlspecialchars($trip['category'] ?? ''); ?></small></div>
      </div>
      <div class="avail-badge">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
        <?= t('tours.booking_available') ?>
      </div>

      <!-- Live summary breakdown -->
      <div id="summary-breakdown" style="border-top:1px solid var(--border);padding:14px 0;font-size:13.5px;color:var(--muted);">
        <div style="display:flex;justify-content:space-between;"><span><?= t('tours.booking_trip_price') ?></span><span id="sum-base">€<?php echo number_format($basePrice, 0); ?></span></div>
        <div style="display:flex;justify-content:space-between;margin-top:6px;"><span><?= t('tours.booking_flex_line') ?></span><span id="sum-flex">€0</span></div>
        <div style="display:flex;justify-content:space-between;margin-top:6px;"><span><?= t('tours.booking_room_line') ?></span><span id="sum-room">€0</span></div>
        <div style="display:flex;justify-content:space-between;margin-top:6px;"><span><?= t('tours.booking_discount') ?></span><span id="sum-discount">-€0</span></div>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-top:1px solid var(--border);">
        <strong><?= t('tours.booking_total') ?></strong>
        <strong id="sum-total" style="font-size:18px;">€<?php echo number_format($basePrice, 0); ?></strong>
      </div>

      <div class="install-card">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/></svg>
        <?= t('tours.booking_installments') ?>
      </div>
      <div class="social-proof">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0; margin-top:2px;"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 5-6 8-6s6.5 2 8 6"/></svg>
        <span><?= t_fmt('tours.booking_social_proof', ['count' => 400]) ?></span>
      </div>
      <div class="summary-help">
        <strong><?= t('tours.booking_need_help') ?></strong>
        <a href="<?= gaia_url('contact') ?>"><?= t('tours.booking_ask') ?></a>
      </div>
    </aside>
  </form>
</div>

<?php require __DIR__ . '/../components/gaia-footer.php'; ?>

<script src="/assets/gaia.js"></script>
<script src="script.js"></script>
<script>
(function(){
  // ---- Config ---- 
  const BASE_PRICE = <?php echo $basePrice; ?>;
  const FLEX_FEE   = <?php echo $flexFee; ?>;
  const ROOM_FEE   = <?php echo $roomFee; ?>;

  // ---- Translated promo messages (from PHP) ----
  const MSG_ENTER = <?php echo json_encode(t('tours.promo_enter')); ?>;
  const MSG_APPLIED = <?php echo json_encode(t('tours.promo_applied')); ?>;
  const MSG_ERROR = <?php echo json_encode(t('tours.promo_validate_error')); ?>;

  const sumBase     = document.getElementById('sum-base');
  const sumFlex     = document.getElementById('sum-flex');
  const sumRoom     = document.getElementById('sum-room');
  const sumDiscount = document.getElementById('sum-discount');
  const sumTotal    = document.getElementById('sum-total');
  const summaryAmt  = document.getElementById('summary-amount');

  let discountAmount = 0;
  let activePromoMsg = false;

  function recompute() {
    const flex = document.getElementById('flex-cancel').checked;
    const room = document.getElementById('private-room').checked;
    const total = BASE_PRICE + (flex ? FLEX_FEE : 0) + (room ? ROOM_FEE : 0) - discountAmount;

    sumBase.textContent = '€' + BASE_PRICE.toLocaleString('en-US', {maximumFractionDigits:0});
    sumFlex.textContent = '€' + (flex ? FLEX_FEE : 0);
    sumRoom.textContent = '€' + (room ? ROOM_FEE : 0);
    sumDiscount.textContent = '-€' + discountAmount.toLocaleString('en-US', {maximumFractionDigits:0});
    sumTotal.textContent = '€' + total.toLocaleString('en-US', {maximumFractionDigits:0});
    summaryAmt.textContent = '€' + total.toLocaleString('en-US', {maximumFractionDigits:0});
  }

  document.getElementById('flex-cancel').addEventListener('change', recompute);
  document.getElementById('private-room').addEventListener('change', recompute);

  // ---- Sync hidden guest inputs from counters ----
  function syncGuests(){
    const cols = document.querySelectorAll('.gender-col');
    cols.forEach(col => {
      const num = parseInt(col.querySelector('.counter .num').textContent, 10) || 0;
      col.querySelector('.guest-input').value = num;
    });
  }
  document.addEventListener('guests-changed', syncGuests);

  // ---- Enable continue when at least 1 traveller selected ----
  const continueBtn = document.getElementById('booking-continue');
  function checkGuests(){
    const nums = Array.from(document.querySelectorAll('.gender-col .counter .num')).map(el => parseInt(el.textContent,10)||0);
    const total = nums.reduce((a,b)=>a+b,0);
    const email = document.querySelector('input[name=email]').value.trim();
    const name  = document.querySelector('input[name=full_name]').value.trim();
    continueBtn.disabled = total === 0 || email === '' || name === '';
  }
  document.addEventListener('guests-changed', checkGuests);
  document.querySelector('input[name=email]').addEventListener('input', checkGuests);
  document.querySelector('input[name=full_name]').addEventListener('input', checkGuests);
  checkGuests();

  // ---- Promo code validation (AJAX) ----
  document.getElementById('promoApply').addEventListener('click', function(){
    const code = document.querySelector('input[name=promo_code]').value.trim();
    const msgEl = document.getElementById('promoMsg');
    if (!code) { msgEl.textContent = MSG_ENTER; msgEl.style.color = 'var(--red)'; return; }
    fetch('validate_promo.php?code=' + encodeURIComponent(code))
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          const subtotal = BASE_PRICE + (document.getElementById('flex-cancel').checked ? FLEX_FEE : 0) + (document.getElementById('private-room').checked ? ROOM_FEE : 0);
          discountAmount = data.discount_type === 'percent'
            ? Math.round(subtotal * data.discount_value / 100)
            : data.discount_value;
          activePromoMsg = true;
          msgEl.textContent = MSG_APPLIED.replace('{amount}', discountAmount);
          msgEl.style.color = '#1F9D66';
        } else {
          discountAmount = 0;
          activePromoMsg = false;
          msgEl.textContent = data.message;
          msgEl.style.color = 'var(--red)';
        }
        recompute();
      })
      .catch(() => {
        discountAmount = 0; activePromoMsg = false; recompute();
        msgEl.textContent = MSG_ERROR;
        msgEl.style.color = 'var(--red)';
      });
  });

  recompute();
})();
</script>
</body>
</html>

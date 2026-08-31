<?php
/**
 * checkout.php — Dynamic booking form.
 * Loads the selected route + car class from query params,
 * shows live pricing, validates promo codes via validate_promo.php,
 * and posts the booking to process_booking.php.
 *
 * Prices for add-ons (return trip, child seat, water, pet, women
 * driver) are loaded from the transfer_extras MySQL table. All
 * labels/SEO come from translations / seo_meta via bootstrap.
 */
require_once __DIR__ . '/components/bootstrap.php';
require_once __DIR__ . '/components/gaia-config.php';
require_once __DIR__ . '/auth/helpers.php';
require_once __DIR__ . '/auth/Auth.php';
require_once __DIR__ . '/auth/middleware.php';

// Shared header/footer variables
$gaia_base          = '';
$gaia_active        = 'transfers';
$gaia_header_style  = 'solid';

// ------------------------------------------------------------
// SECURITY: Only authenticated customers may reach the booking
// form. Guests are redirected to login and returned afterwards.
// ------------------------------------------------------------
require_booking_login('checkout.php');

$authUser = auth_user();
$prefillName  = trim($authUser['first_name'] . ' ' . $authUser['last_name']);
$prefillEmail = $authUser['email'];
$prefillPhone = $authUser['phone'] ?? '';

$routeId    = (int)($_GET['route_id'] ?? 0);
$carClassId = (int)($_GET['car_class_id'] ?? 0);
$date       = trim($_GET['date'] ?? '');
$passengers = (int)($_GET['passengers'] ?? 2);

$route = null;
$car = null;
$error = null;

try {
    $pdo = getPDO();
    if ($routeId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM routes WHERE id = ? AND is_active = 1');
        $stmt->execute([$routeId]);
        $route = $stmt->fetch();
    }
    if ($carClassId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM car_classes WHERE id = ? AND is_active = 1');
        $stmt->execute([$carClassId]);
        $car = $stmt->fetch();
    }
    if (!$route || !$car) {
        // Fall back to first route + car class
        $route = $pdo->query('SELECT * FROM routes WHERE is_active = 1 ORDER BY base_price ASC LIMIT 1')->fetch();
        $car   = $pdo->query('SELECT * FROM car_classes WHERE is_active = 1 ORDER BY price_multiplier ASC LIMIT 1')->fetch();
    }
} catch (PDOException $e) {
    $error = 'Database unavailable: ' . htmlspecialchars($e->getMessage());
}

$basePrice = $route && $car ? round((float)$route['base_price'] * (float)$car['price_multiplier'], 2) : 0;

// --- Add-on pricing from transfer_extras DB (with config fallback) ---
$returnFee     = transfer_extra_price('return_trip_fee', defined('RETURN_TRIP_FEE') ? RETURN_TRIP_FEE : 50.00);
$childSeatPrice = transfer_extra_price('child_seat', defined('PRICE_CHILD_SEAT') ? PRICE_CHILD_SEAT : 10.00);
$waterPrice     = transfer_extra_price('water_bottle', defined('PRICE_WATER_BOTTLE') ? PRICE_WATER_BOTTLE : 3.00);
$petPrice       = transfer_extra_price('pet_carrier', defined('PRICE_PET_CARRIER') ? PRICE_PET_CARRIER : 15.00);
$womenPrice     = transfer_extra_price('women_driver', defined('PRICE_WOMEN_DRIVER') ? PRICE_WOMEN_DRIVER : 20.00);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags('checkout', 'Checkout — GAIA TOURS &amp; TRAVEL') ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<style>
:root{--navy:#1b2a4a;--teal:#1f6f8f;--teal-dark:#175a75;--ink:#1c1e26;--muted:#6b7280;--line:#e8e6e0;--bg-soft:#f4efe6;--white:#fff;--radius:14px;--shadow:0 10px 30px rgba(27,42,74,0.08);}
  *{box-sizing:border-box;}body{margin:0;font-family:'Inter',sans-serif;color:var(--ink);background:var(--bg-soft);-webkit-font-smoothing:antialiased;}
  h1,h2,h3{font-family:'Playfair Display',serif;margin:0;}a{text-decoration:none;color:inherit;}ul{list-style:none;margin:0;padding:0;}button{font-family:inherit;cursor:pointer;}
  .container{max-width:1280px;margin:0 auto;padding:0 32px;}
  header.site-header{background:var(--navy);padding:16px 0;}.header-inner{display:flex;align-items:center;justify-content:space-between;}
  .logo{display:flex;align-items:center;gap:8px;font-family:'Poppins';font-weight:800;font-size:20px;color:#fff;}.logo .dot{color:var(--teal);}
  nav.main-nav ul{display:flex;gap:34px;}nav.main-nav a{color:#fff;font-size:14.5px;font-weight:500;opacity:.92;}nav.main-nav a:hover{opacity:1;color:var(--teal);}
  .header-right{display:flex;align-items:center;gap:22px;color:#fff;}.header-right .icon-btn{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.35);font-size:13px;}.currency{font-size:14px;font-weight:600;}
  .page-head{padding:40px 0 10px;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:20px;}.page-head h1{font-size:34px;font-weight:800;}
  .stepper{display:flex;gap:34px;margin-top:6px;}.step{font-size:13.5px;color:var(--muted);padding-bottom:12px;border-bottom:3px solid var(--line);font-weight:600;}.step.active{color:var(--ink);border-color:var(--teal);}.step.done{color:var(--ink);border-color:var(--teal);}
  .layout{display:grid;grid-template-columns:1fr 360px;gap:26px;padding:30px 0 80px;align-items:start;}
  .card{background:#fff;border-radius:16px;padding:32px;box-shadow:var(--shadow);margin-bottom:22px;}.card h2{font-size:21px;font-weight:700;margin-bottom:24px;}
  .form-row{display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:20px;}
  .field label{display:block;font-size:13.5px;font-weight:600;margin-bottom:8px;}
  .field input,.field textarea{width:100%;border:1px solid var(--line);border-radius:10px;padding:13px 14px;font-size:14px;font-family:inherit;outline:none;color:var(--ink);transition:border-color .2s;}
  .field input:focus,.field textarea:focus{border-color:var(--teal);}.field .hint{font-size:11.5px;color:var(--muted);margin-top:6px;}
  .field .input-icon{position:relative;}.field .input-icon i{position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:14px;}.field .input-icon input{padding-right:38px;}
  .divider{height:1px;background:var(--line);margin:22px 0;}
  .checkbox-row{display:flex;align-items:flex-start;gap:12px;margin-bottom:18px;}.checkbox-row input[type=checkbox]{width:20px;height:20px;flex:none;margin-top:1px;accent-color:var(--teal);border-radius:5px;}
  .checkbox-row label{font-size:14px;font-weight:600;cursor:pointer;}.checkbox-row .sub{font-size:12px;color:var(--muted);font-weight:400;margin-top:3px;}.checkbox-row .price{margin-left:auto;font-weight:700;font-size:14px;}
  .phone-input{display:flex;border:1px solid var(--line);border-radius:10px;overflow:hidden;}.phone-input .flag{display:flex;align-items:center;gap:6px;padding:0 12px;border-right:1px solid var(--line);font-size:14px;background:#fafafe;}.phone-input input{border:none;flex:1;padding:13px 14px;font-size:14px;outline:none;}
  .counter{display:flex;align-items:center;justify-content:space-between;border:1px solid var(--line);border-radius:10px;padding:6px 14px;max-width:100%;}.counter button{width:32px;height:32px;border-radius:8px;border:1px solid var(--line);background:#fff;font-size:16px;color:var(--ink);}.counter span{font-weight:700;font-size:15px;}
  .info-box{display:flex;align-items:center;gap:16px;background:var(--bg-soft);border-radius:12px;padding:16px 18px;margin-top:18px;}.info-box img{width:52px;height:52px;border-radius:10px;object-fit:cover;flex:none;}.info-box p{margin:0;font-size:13px;color:var(--muted);}
  .continue-btn{display:block;width:100%;background:var(--teal);border:none;color:#0a2b28;font-weight:700;font-size:16px;padding:16px;border-radius:10px;margin-top:10px;transition:background .2s;}.continue-btn:hover{background:var(--teal-dark);}
  .consent{text-align:center;font-size:12px;color:var(--muted);margin-top:14px;}.consent a{color:var(--teal-dark);text-decoration:underline;}
  .apply-row{display:flex;gap:10px;}.apply-row button{flex:none;border:none;background:var(--navy);color:#fff;border-radius:10px;padding:0 22px;font-weight:700;font-size:13.5px;}
  .summary-card{background:#fff;border-radius:16px;padding:28px;box-shadow:var(--shadow);position:sticky;top:24px;}.summary-card h3{font-size:18px;font-weight:700;margin-bottom:22px;}
  .summary-path{display:flex;gap:14px;margin-bottom:6px;}.summary-path .line{display:flex;flex-direction:column;align-items:center;padding-top:4px;}.summary-path .dot{width:9px;height:9px;border-radius:50%;background:var(--teal);}.summary-path .bar{width:2px;flex:1;background:var(--line);margin:4px 0;}.summary-path .pin{color:var(--muted);}.summary-path .pts{display:flex;flex-direction:column;gap:18px;}.summary-path .pt{display:flex;align-items:center;gap:10px;font-size:14.5px;font-weight:600;}
  .summary-meta{display:flex;align-items:center;gap:16px;font-size:13px;color:var(--muted);margin:18px 0;padding-bottom:18px;border-bottom:1px solid var(--line);}.summary-meta b{color:var(--ink);margin-left:auto;font-size:15px;}
  .summary-class{display:flex;align-items:center;justify-content:space-between;font-size:14px;font-weight:600;padding-bottom:18px;border-bottom:1px solid var(--line);margin-bottom:18px;}.summary-class span{display:flex;align-items:center;gap:6px;color:var(--muted);font-weight:400;}
  .summary-total{display:flex;align-items:center;justify-content:space-between;font-size:16px;font-weight:700;}.summary-total .amt{font-size:22px;}
  .promo-msg{font-size:12.5px;margin-top:8px;}.promo-msg.ok{color:var(--teal-dark);}.promo-msg.err{color:#b3261e;}
  .db-error{background:#fdecea;color:#b3261e;border:1px solid #f5c6c2;border-radius:10px;padding:14px 18px;font-size:13px;margin-bottom:20px;}
  .cookie-toast{position:fixed;right:32px;bottom:26px;background:#1c1738;color:#fff;padding:14px 18px;border-radius:12px;display:flex;align-items:center;gap:14px;max-width:340px;font-size:12.5px;box-shadow:var(--shadow);z-index:80;}.cookie-toast a{color:var(--teal);text-decoration:underline;}.cookie-toast button{background:var(--teal);border:none;color:#0d1730;font-weight:700;border-radius:6px;padding:8px 16px;font-size:13px;}
  @media (max-width:1000px){.layout{grid-template-columns:1fr;}.form-row{grid-template-columns:1fr;}nav.main-nav{display:none;}.summary-card{position:static;}}
</style>
</head>
<body>

<?php require __DIR__ . '/components/gaia-header.php'; ?>

<div class="container page-head">
  <h1><?= t('checkout.title', 'Checkout') ?></h1>
  <div class="stepper"><div class="step done"><?= t('checkout.step_request', 'Request') ?></div><div class="step active"><?= t('checkout.step_checkout', 'Checkout') ?></div><div class="step"><?= t('checkout.step_payment', 'Payment') ?></div></div>
</div>

<?php if (isset($error)): ?>
  <div class="container"><div class="db-error"><?= $error ?></div></div>
<?php endif; ?>

<?php if ($route && $car): ?>
<div class="container layout">
  <div>
    <form method="post" action="<?= gaia_url('process_booking.php') ?>" id="bookingForm">
      <!-- Hidden route + car -->
      <input type="hidden" name="route_id" value="<?= (int)$route['id'] ?>">
      <input type="hidden" name="car_class_id" value="<?= (int)$car['id'] ?>">
      <input type="hidden" name="origin" value="<?= htmlspecialchars($route['origin']) ?>">
      <input type="hidden" name="destination" value="<?= htmlspecialchars($route['destination']) ?>">

      <!-- STEP 1 -->
      <div class="card">
        <h2><?= t('checkout.book_title', 'Step 1. Book') ?></h2>
        <div class="form-row">
          <div class="field"><label><?= t('checkout.flight_number', 'Flight number') ?></label><input type="text" name="flight_number" placeholder="<?= t('checkout.flight_placeholder', 'e.g. AA 0000') ?>"><div class="hint"><?= t('checkout.flight_hint', 'According to the ticket') ?></div></div>
        </div>
        <div class="form-row">
          <div class="field"><label><?= t('checkout.pickup_date', 'Pickup date') ?></label><div class="input-icon"><input type="date" name="pickup_date" value="<?= htmlspecialchars($date) ?>" required><i class="fa-regular fa-calendar"></i></div></div>
          <div class="field"><label><?= t('checkout.arrival_time', 'Scheduled arrival time') ?></label><div class="input-icon"><input type="time" name="pickup_time" placeholder="00:00"><i class="fa-regular fa-clock"></i></div></div>
        </div>
        <div class="field" style="margin-bottom:6px;"><label><?= t('checkout.destination_label', 'Destination (address or hotel name)') ?></label><input type="text" name="destination_address" placeholder="<?= t('checkout.destination_placeholder', 'Laguna Hotel') ?>"></div>
        <div class="divider"></div>
        <div class="checkbox-row" style="margin-bottom:0;">
          <input type="checkbox" id="returnTrip" name="return_trip" value="1">
          <div><label for="returnTrip"><?= t('checkout.return_trip', 'Return trip') ?></label><div class="sub">+$<?= number_format($returnFee, 2) ?></div></div>
        </div>
      </div>

      <!-- STEP 2 -->
      <div class="card">
        <h2><?= t('checkout.passengers_title', 'Step 2. Passengers') ?></h2>
        <div class="form-row">
<div class="field"><label><?= t('checkout.name_label', 'Name and Surname') ?></label><input type="text" name="full_name" value="<?= htmlspecialchars($prefillName) ?>" placeholder="<?= t('checkout.name_placeholder', 'e.g. John Watson') ?>" required></div>
          <div class="field"><label><?= t('checkout.email_label', 'E-mail') ?></label><input type="email" name="email" value="<?= htmlspecialchars($prefillEmail) ?>" placeholder="<?= t('checkout.email_placeholder', 'johnwatson@mail.com') ?>" required><div class="hint"><?= t('checkout.email_hint', 'We will send a booking confirmation, voucher, and reminder to this e-mail address') ?></div></div>
        </div>
        <div class="field" style="max-width:400px;margin-bottom:22px;">
          <label><?= t('checkout.phone_label', 'Phone number') ?></label>
          <div class="phone-input"><span class="flag">🇯🇴</span><input type="text" name="phone" value="<?= htmlspecialchars($prefillPhone) ?>" placeholder="+962 7 9012 3456" required></div>
          <div class="hint"><?= t('checkout.phone_hint', 'We need it for urgent communication with you. It must be available on the day of the transfer') ?></div>
        </div>
        <div class="divider"></div>
        <div class="field" style="max-width:240px;margin-bottom:6px;">
          <label><?= t('checkout.passengers_label', 'Passengers') ?></label>
          <div class="counter">
            <button type="button" id="paxMinus">–</button>
            <input type="hidden" name="passenger_count" id="paxCount" value="<?= max(1, $passengers) ?>">
            <span id="paxCountLabel"><?= max(1, $passengers) ?></span>
            <button type="button" id="paxPlus">+</button>
          </div>
          <div class="hint"><?= t('checkout.passengers_hint', 'Including children and infants') ?></div>
        </div>
        <div class="divider"></div>
        <div class="field" style="max-width:240px;margin-bottom:6px;">
          <label><?= t('checkout.child_seats', 'Child seats') ?></label>
          <div class="counter">
            <button type="button" id="childMinus">–</button>
            <input type="hidden" name="child_seats" id="childCount" value="0">
            <span id="childCountLabel">0</span>
            <button type="button" id="childPlus">+</button>
          </div>
          <div class="hint"><?= t_fmt('checkout.child_seats_hint', ['price' => number_format($childSeatPrice, 2)], gaia_current_lang()) ?></div>
        </div>
      </div>

      <!-- STEP 3 -->
      <div class="card">
        <h2><?= t('checkout.additionally_title', 'Step 3. Additionally') ?></h2>
        <div class="checkbox-row" style="align-items:center;">
          <div style="flex:1;"><label style="font-weight:600;"><?= t('checkout.water', 'Drinking water') ?> <span style="font-weight:400;color:var(--muted);font-size:12px;"><?= t('checkout.water_desc', 'A bottle of still water (0,5l)') ?></span></label><div class="sub"><?= t_fmt('checkout.water_price', ['price' => number_format($waterPrice, 2)], gaia_current_lang()) ?></div></div>
          <div class="counter" style="max-width:130px;">
            <button type="button" id="waterMinus">–</button>
            <input type="hidden" name="water_bottles" id="waterCount" value="0">
            <span id="waterCountLabel">0</span>
            <button type="button" id="waterPlus">+</button>
          </div>
        </div>
        <div class="divider"></div>
        <div class="checkbox-row">
          <input type="checkbox" id="pets" name="pet_carrier" value="1">
          <div><label for="pets"><?= t('checkout.pets', 'I am travelling with pets') ?></label><div class="sub"><?= t_fmt('checkout.pets_desc', ['price' => number_format($petPrice, 2)], gaia_current_lang()) ?></div></div>
        </div>
        <div class="checkbox-row">
          <input type="checkbox" id="womenDriving" name="women_driver" value="1">
          <div><label for="womenDriving"><?= t('checkout.women_driver', 'Women Driving Women') ?></label><div class="sub"><?= t_fmt('checkout.women_driver_desc', ['price' => number_format($womenPrice, 2)], gaia_current_lang()) ?></div></div>
        </div>
        <div class="divider"></div>
        <div class="field" style="margin-bottom:22px;"><label><?= t('checkout.comments_label', 'Comments to the order') ?></label><textarea name="comments" rows="3" placeholder="<?= t('checkout.comments_placeholder', 'e.g. Non-Standard luggage') ?>"></textarea></div>
        <div class="field" style="margin-bottom:26px;max-width:420px;">
          <label><?= t('checkout.promo_code', 'Promo code') ?></label>
          <div class="apply-row">
            <input type="text" id="promoInput" name="promo_code" placeholder="e.g. HELLO">
            <button type="button" id="applyPromo"><?= t('checkout.apply', 'Apply') ?></button>
          </div>
          <div class="promo-msg" id="promoMsg"></div>
        </div>
        <button class="continue-btn" type="submit"><?= t('checkout.continue', 'Continue') ?></button>
        <div class="consent"><?= t('checkout.consent', 'By clicking Continue, you consent to the processing of your personal data and agree to the Privacy Policy') ?> <a href="<?= gaia_url('contact') ?>"><?= t('footer.privacy', 'Privacy Policy') ?></a></div>
      </div>
    </form>
  </div>

  <!-- ORDER SUMMARY -->
  <div class="summary-card">
    <h3><?= t('checkout.order_summary', 'Order summary') ?></h3>
    <div class="summary-path">
      <div class="line"><div class="dot"></div><div class="bar"></div><i class="fa-solid fa-location-dot pin"></i></div>
      <div class="pts">
        <div class="pt"><i class="fa-solid fa-plane" style="color:var(--muted)"></i> <?= htmlspecialchars($route['origin']) ?></div>
        <div class="pt"><i class="fa-solid fa-location-dot" style="color:var(--muted)"></i> <?= htmlspecialchars($route['destination']) ?></div>
      </div>
    </div>
    <div class="summary-meta">
      <span><?= $date !== '' ? htmlspecialchars($date) : t('transfers.select_date', 'Select date') ?></span>
      <span>~ <?= (int)$route['travel_time_min'] ?> min</span>
      <b>$<?= number_format($basePrice, 2) ?></b>
    </div>
    <div class="summary-class">
      <?= htmlspecialchars($car['name']) ?>
      <span><i class="fa-solid fa-user"></i> <span id="sumPax"><?= max(1, $passengers) ?></span></span>
    </div>
    <div id="priceBreakdown" style="font-size:13px;color:var(--muted);margin-bottom:16px;"></div>
    <div class="summary-total">
      <?= t('checkout.total', 'Total') ?> <span class="amt" id="totalAmt">$<?= number_format($basePrice, 2) ?></span>
    </div>
  </div>
</div>

<div class="cookie-toast" id="cookieToast">
  <span>&#127850; <?= t('home.cookie_text', 'We use cookies to personalise your experience.') ?> <a href="<?= gaia_url('contact') ?>"><?= t('home.cookie_policy', 'Read our Cookie Policy') ?></a>.</span>
<button onclick="document.getElementById('cookieToast').style.display='none'"><?= t('home.cookie_ok', 'Ok') ?></button>
</div>

<?php require __DIR__ . '/components/gaia-footer.php'; ?>

<script src="assets/gaia.js"></script>
<script>
  const BASE_PRICE = <?= $basePrice ?>;
  const RETURN_FEE = <?= $returnFee ?>;
  const CHILD_PRICE = <?= $childSeatPrice ?>;
  const WATER_PRICE = <?= $waterPrice ?>;
const PET_PRICE = <?= $petPrice ?>;
  const WOMEN_PRICE = <?= $womenPrice ?>;

  // ---- Translated promo messages (from DB) ----
  const MSG_PROMO_ENTER = <?= json_encode(t('checkout.promo_enter', 'Enter a promo code.')) ?>;
  const MSG_PROMO_ERROR = <?= json_encode(t('checkout.promo_validate_error', 'Could not validate promo code.')) ?>;

  let promoDiscount = 0;
  let promoType = null;

  function getValues() {
    return {
      pax: parseInt(document.getElementById('paxCount').value) || 1,
      child: parseInt(document.getElementById('childCount').value) || 0,
      water: parseInt(document.getElementById('waterCount').value) || 0,
      pet: document.getElementById('pets').checked ? 1 : 0,
      women: document.getElementById('womenDriving').checked ? 1 : 0,
      return: document.getElementById('returnTrip').checked ? 1 : 0
    };
  }

  function updateSummary() {
    const v = getValues();
    const base = BASE_PRICE;
    const ch = v.child * CHILD_PRICE;
    const wa = v.water * WATER_PRICE;
    const pe = v.pet ? PET_PRICE : 0;
    const wo = v.women ? WOMEN_PRICE : 0;
    const ret = v.return ? RETURN_FEE : 0;
    const extras = ch + wa + pe + wo;
    let subtotal = base + ret + extras;
    let discount = 0;
    if (promoType === 'percent') discount = subtotal * promoDiscount / 100;
    else if (promoType === 'flat') discount = promoDiscount;
    discount = Math.min(discount, subtotal);
    const total = subtotal - discount;

    document.getElementById('sumPax').textContent = v.pax;

    let html = '';
    html += '<?= t('checkout.base_fare', 'Base fare') ?>: $' + base.toFixed(2) + '<br>';
    if (ret) html += '<?= t('checkout.return_trip_line', 'Return trip') ?>: +$' + ret.toFixed(2) + '<br>';
    if (ch) html += '<?= t('checkout.child_seats_line', 'Child seats') ?>: +$' + ch.toFixed(2) + '<br>';
    if (wa) html += '<?= t('checkout.water_line', 'Water') ?>: +$' + wa.toFixed(2) + '<br>';
    if (pe) html += '<?= t('checkout.pet_line', 'Pet carrier') ?>: +$' + pe.toFixed(2) + '<br>';
    if (wo) html += '<?= t('checkout.women_line', 'Women driver') ?>: +$' + wo.toFixed(2) + '<br>';
    if (discount > 0) html += '<?= t('checkout.promo_discount', 'Promo discount') ?>: -$' + discount.toFixed(2) + '<br>';
    document.getElementById('priceBreakdown').innerHTML = html;

    document.getElementById('totalAmt').textContent = '$' + total.toFixed(2);
  }

  // Passenger counters
  function setupCounter(minusId, plusId, hiddenId, labelId, min, max) {
    const minus = document.getElementById(minusId);
    const plus = document.getElementById(plusId);
    const hidden = document.getElementById(hiddenId);
    const label = document.getElementById(labelId);
    minus.addEventListener('click', () => { hidden.value = Math.max(min, parseInt(hidden.value) - 1); label.textContent = hidden.value; updateSummary(); });
    plus.addEventListener('click', () => { hidden.value = Math.min(max, parseInt(hidden.value) + 1); label.textContent = hidden.value; updateSummary(); });
  }
  setupCounter('paxMinus', 'paxPlus', 'paxCount', 'paxCountLabel', 1, 19);
  setupCounter('childMinus', 'childPlus', 'childCount', 'childCountLabel', 0, 19);
  setupCounter('waterMinus', 'waterPlus', 'waterCount', 'waterCountLabel', 0, 99);

  // Checkboxes update summary
  ['returnTrip', 'pets', 'womenDriving'].forEach(id => {
    document.getElementById(id).addEventListener('change', updateSummary);
  });

  // Promo validation via AJAX
  document.getElementById('applyPromo').addEventListener('click', () => {
    const code = document.getElementById('promoInput').value.trim();
    const msg = document.getElementById('promoMsg');
if (!code) { msg.className = 'promo-msg err'; msg.textContent = MSG_PROMO_ENTER; return; }
    fetch('validate_promo.php?code=' + encodeURIComponent(code))
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          promoDiscount = parseFloat(data.discount_value);
          promoType = data.discount_type;
          msg.className = 'promo-msg ok';
          msg.textContent = data.message + ' (' + (promoType === 'percent' ? promoDiscount + '%' : '$' + promoDiscount.toFixed(2)) + ')';
        } else {
          promoDiscount = 0; promoType = null;
          msg.className = 'promo-msg err';
          msg.textContent = data.message;
        }
        updateSummary();
      })
.catch(() => { msg.className = 'promo-msg err'; msg.textContent = MSG_PROMO_ERROR; });
  });

  document.getElementById('bookingForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Processing...';
    
    fetch(this.action, {
      method: 'POST',
      body: new FormData(this)
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        window.location.href = '<?= gaia_url("account/booking.php") ?>?reference=' + data.booking_reference;
      } else {
        alert(data.message);
        btn.disabled = false;
        btn.textContent = '<?= t('checkout.continue', 'Continue') ?>';
      }
    })
    .catch(err => {
      alert('An error occurred.');
      btn.disabled = false;
      btn.textContent = '<?= t('checkout.continue', 'Continue') ?>';
    });
  });

  updateSummary();
</script>
<?php endif; ?>
</body>
</html>


<?php
/**
 * checkout-hotel.php — Dynamic booking form for Hotels/Rooms.
 *
 * Loads the selected room from query params,
 * shows live pricing, validates inputs, and posts
 * the booking to process_hotel_booking.php.
 */
require_once __DIR__ . '/components/bootstrap.php';
require_once __DIR__ . '/components/gaia-config.php';
require_once __DIR__ . '/auth/helpers.php';
require_once __DIR__ . '/auth/Auth.php';
require_once __DIR__ . '/auth/middleware.php';
require_once __DIR__ . '/services/HotelAvailabilityService.php';

$gaia_base          = '';
$gaia_active        = 'hotels';
$gaia_header_style  = 'solid';

// SECURITY: Only authenticated customers may reach the booking form.
require_booking_login('checkout-hotel.php');

$authUser = auth_user();
$prefillName  = trim(($authUser['first_name'] ?? '') . ' ' . ($authUser['last_name'] ?? ''));
$prefillEmail = $authUser['email'] ?? '';
$prefillPhone = $authUser['phone'] ?? '';

$roomId = (int)($_GET['room_id'] ?? 0);

$searchCtx = HotelAvailabilityService::validateSearchContext($_GET);
if ($searchCtx) {
    $checkIn = $searchCtx['check_in'];
    $checkOut = $searchCtx['check_out'];
    $guests = $searchCtx['guests'];
    $rooms = $searchCtx['rooms'];
} else {
    $checkIn = trim($_GET['check_in'] ?? '');
    $checkOut = trim($_GET['check_out'] ?? '');
    $guests = (int)($_GET['guests'] ?? 1);
    $rooms = (int)($_GET['rooms'] ?? 1);
}

$room = null;
$hotel = null;
$error = null;

try {
    $pdo = getPDO();
    if ($roomId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM hotel_rooms WHERE id = ? AND is_active = 1');
        $stmt->execute([$roomId]);
        $room = $stmt->fetch();
    }
    if ($room) {
        $stmt = $pdo->prepare('SELECT * FROM hotels WHERE id = ? AND is_active = 1');
        $stmt->execute([$room['hotel_id']]);
        $hotel = $stmt->fetch();
    }
} catch (PDOException $e) {
    $error = 'Database unavailable: ' . htmlspecialchars($e->getMessage());
}

if (!$room || !$hotel) {
    http_response_code(404);
    die('Room not found.');
}
$adminCommission = (float) ($hotel['admin_commission_percent'] ?? 0);
$gatewayFee = (float) site_setting('payment_gateway_fee_percent', '2.5');
$roomPrice = round((float)$room['price'] * (1 + $adminCommission/100) * (1 + $gatewayFee/100), 0);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags('checkout', 'Hotel Checkout — GAIA TOURS &amp; TRAVEL') ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<style>
:root{--navy:#1b2a4a;--teal:#1f6f8f;--teal-dark:#175a75;--ink:#1c1e26;--muted:#6b7280;--line:#e8e6e0;--bg-soft:#f4efe6;--white:#fff;--radius:14px;--shadow:0 10px 30px rgba(27,42,74,0.08);}
  *{box-sizing:border-box;}body{margin:0;font-family:'Inter',sans-serif;color:var(--ink);background:var(--bg-soft);-webkit-font-smoothing:antialiased;}
  h1,h2,h3{font-family:'Playfair Display',serif;margin:0;}a{text-decoration:none;color:inherit;}ul{list-style:none;margin:0;padding:0;}button{font-family:inherit;cursor:pointer;}
  .container{max-width:1280px;margin:0 auto;padding:0 32px;}
  header.site-header{background:var(--navy);padding:16px 0;}
  .page-head{padding:40px 0 10px;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:20px;}.page-head h1{font-size:34px;font-weight:800;}
  .stepper{display:flex;gap:34px;margin-top:6px;}.step{font-size:13.5px;color:var(--muted);padding-bottom:12px;border-bottom:3px solid var(--line);font-weight:600;}.step.active{color:var(--ink);border-color:var(--teal);}.step.done{color:var(--ink);border-color:var(--teal);}
  .layout{display:grid;grid-template-columns:1fr 360px;gap:26px;padding:30px 0 80px;align-items:start;}
  .card{background:#fff;border-radius:16px;padding:32px;box-shadow:var(--shadow);margin-bottom:22px;}.card h2{font-size:21px;font-weight:700;margin-bottom:24px;}
  .form-row{display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:20px;}
  .field label{display:block;font-size:13.5px;font-weight:600;margin-bottom:8px;}
  .field input,.field textarea{width:100%;border:1px solid var(--line);border-radius:10px;padding:13px 14px;font-size:14px;font-family:inherit;outline:none;color:var(--ink);transition:border-color .2s;}
  .field input:focus,.field textarea:focus{border-color:var(--teal);}.field .hint{font-size:11.5px;color:var(--muted);margin-top:6px;}
  .divider{height:1px;background:var(--line);margin:22px 0;}
  .counter{display:flex;align-items:center;justify-content:space-between;border:1px solid var(--line);border-radius:10px;padding:6px 14px;max-width:100%;}.counter button{width:32px;height:32px;border-radius:8px;border:1px solid var(--line);background:#fff;font-size:16px;color:var(--ink);}.counter span{font-weight:700;font-size:15px;}
  .continue-btn{display:block;width:100%;background:var(--teal);border:none;color:#fff;font-weight:700;font-size:16px;padding:16px;border-radius:10px;margin-top:10px;transition:background .2s;}.continue-btn:hover{background:var(--teal-dark);}
  .summary-card{background:#fff;border-radius:16px;padding:28px;box-shadow:var(--shadow);position:sticky;top:24px;}.summary-card h3{font-size:18px;font-weight:700;margin-bottom:22px;}
  .summary-path{display:flex;gap:14px;margin-bottom:6px;}.summary-path .pts{display:flex;flex-direction:column;gap:18px;}.summary-path .pt{display:flex;align-items:center;gap:10px;font-size:14.5px;font-weight:600;}
  .summary-meta{display:flex;align-items:center;justify-content:space-between;font-size:14px;color:var(--muted);margin:18px 0;padding-bottom:18px;border-bottom:1px solid var(--line);}
  .summary-total{display:flex;align-items:center;justify-content:space-between;font-size:16px;font-weight:700;}.summary-total .amt{font-size:22px;}
  .db-error{background:#fdecea;color:#b3261e;border:1px solid #f5c6c2;border-radius:10px;padding:14px 18px;font-size:13px;margin-bottom:20px;}
  @media (max-width:1000px){.layout{grid-template-columns:1fr;}.form-row{grid-template-columns:1fr;}.summary-card{position:static;}}
</style>
</head>
<body>

<?php require __DIR__ . '/components/gaia-header.php'; ?>

<div class="container page-head">
  <h1><?= t('checkout.hotel_title', 'Complete Booking') ?></h1>
  <div class="stepper"><div class="step done"><?= t('checkout.step_request', 'Request') ?></div><div class="step active"><?= t('checkout.step_checkout', 'Checkout') ?></div><div class="step"><?= t('checkout.step_payment', 'Payment') ?></div></div>
</div>

<?php if (isset($error)): ?>
  <div class="container"><div class="db-error"><?= $error ?></div></div>
<?php endif; ?>

<div class="container layout">
  <div>
    <form method="post" action="<?= gaia_url('process_hotel_booking.php') ?>" id="bookingForm">
      <input type="hidden" name="room_id" value="<?= (int)$room['id'] ?>">

      <div class="card">
        <h2><?= t('checkout.dates', 'Dates & Details') ?></h2>
        <div class="form-row">
          <div class="field"><label><?= t('checkout.check_in', 'Check-in Date') ?></label><input type="date" id="checkIn" name="check_in_date" value="<?= htmlspecialchars($checkIn) ?>" required></div>
          <div class="field"><label><?= t('checkout.check_out', 'Check-out Date') ?></label><input type="date" id="checkOut" name="check_out_date" value="<?= htmlspecialchars($checkOut) ?>" required></div>
        </div>
        <div class="divider"></div>
        <div class="form-row" style="margin-bottom:6px;">
          <div class="field" style="max-width:240px;">
            <label><?= t('checkout.guests', 'Guests') ?></label>
            <div class="counter">
              <button type="button" id="guestMinus">–</button>
              <input type="hidden" name="guest_count" id="guestCount" value="<?= htmlspecialchars((int)$guests) ?>">
              <span id="guestCountLabel"><?= htmlspecialchars((int)$guests) ?></span>
              <button type="button" id="guestPlus">+</button>
            </div>
          </div>
          <div class="field" style="max-width:240px;">
            <label><?= t('checkout.rooms', 'Rooms') ?></label>
            <div class="counter">
              <button type="button" id="roomMinus">–</button>
              <input type="hidden" name="rooms_count" id="roomCount" value="<?= htmlspecialchars((int)$rooms) ?>">
              <span id="roomCountLabel"><?= htmlspecialchars((int)$rooms) ?></span>
              <button type="button" id="roomPlus">+</button>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <h2><?= t('checkout.guest_info', 'Guest Information') ?></h2>
        <div class="form-row">
          <div class="field"><label><?= t('checkout.name_label', 'Name and Surname') ?></label><input type="text" name="guest_name" value="<?= htmlspecialchars($prefillName) ?>" required></div>
          <div class="field"><label><?= t('checkout.email_label', 'E-mail') ?></label><input type="email" name="guest_email" value="<?= htmlspecialchars($prefillEmail) ?>" required></div>
        </div>
        <div class="field" style="max-width:400px;margin-bottom:22px;">
          <label><?= t('checkout.phone_label', 'Phone number') ?></label>
          <input type="text" name="guest_phone" value="<?= htmlspecialchars($prefillPhone) ?>" required>
        </div>
        <div class="field" style="margin-bottom:22px;"><label><?= t('checkout.special_requests', 'Special Requests') ?></label><textarea name="special_requests" rows="3"></textarea></div>
        <button class="continue-btn" type="submit"><?= t('checkout.confirm_booking', 'Confirm Booking') ?></button>
      </div>
    </form>
  </div>

  <div class="summary-card">
    <h3><?= t('checkout.order_summary', 'Order summary') ?></h3>
    <div class="summary-path">
      <div class="pts">
        <div class="pt"><i class="fa-solid fa-hotel" style="color:var(--muted)"></i> <?= htmlspecialchars(lang_value($hotel, 'name')) ?></div>
        <div class="pt"><i class="fa-solid fa-door-open" style="color:var(--muted)"></i> <?= htmlspecialchars(lang_value($room, 'name')) ?></div>
      </div>
    </div>
    <div class="summary-meta">
      <span id="nightsLabel">0 <?= t('checkout.nights', 'nights') ?></span>
      <span id="pricePerNight">$<?= number_format($roomPrice, 0) ?> <?= t('checkout.per_night', '/ night') ?></span>
    </div>
    <div class="summary-total">
      <?= t('checkout.total', 'Total') ?> <span class="amt" id="totalAmt">$0.00</span>
    </div>
  </div>
</div>

<?php require __DIR__ . '/components/gaia-footer.php'; ?>

<script>
  const ROOM_PRICE = <?= $roomPrice ?>;
  const CAPACITY = <?= (int)$room['capacity'] ?>;

  function updateSummary() {
    const ci = document.getElementById('checkIn').value;
    const co = document.getElementById('checkOut').value;
    let nights = 0;
    
    if (ci && co) {
      const d1 = new Date(ci);
      const d2 = new Date(co);
      const diffTime = d2 - d1;
      nights = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
      if (nights < 0) nights = 0;
    }
    
    const rCount = parseInt(document.getElementById('roomCount').value) || 1;
    const total = nights * ROOM_PRICE * rCount;
    document.getElementById('nightsLabel').textContent = nights + ' <?= t('checkout.nights', 'nights') ?> × ' + rCount + ' <?= t('checkout.rooms', 'rooms') ?>';
    document.getElementById('totalAmt').textContent = '$' + total.toFixed(0);
  }

  document.getElementById('checkIn').addEventListener('change', updateSummary);
  document.getElementById('checkOut').addEventListener('change', updateSummary);

  const guestMinus = document.getElementById('guestMinus');
  const guestPlus = document.getElementById('guestPlus');
  const guestCount = document.getElementById('guestCount');
  const guestCountLabel = document.getElementById('guestCountLabel');

  guestMinus.addEventListener('click', () => { 
    guestCount.value = Math.max(1, parseInt(guestCount.value) - 1); 
    guestCountLabel.textContent = guestCount.value; 
  });
  guestPlus.addEventListener('click', () => { 
    guestCount.value = Math.min(CAPACITY * parseInt(roomCount.value), parseInt(guestCount.value) + 1); 
    guestCountLabel.textContent = guestCount.value; 
  });

  const roomMinus = document.getElementById('roomMinus');
  const roomPlus = document.getElementById('roomPlus');
  const roomCount = document.getElementById('roomCount');
  const roomCountLabel = document.getElementById('roomCountLabel');

  roomMinus.addEventListener('click', () => { 
    roomCount.value = Math.max(1, parseInt(roomCount.value) - 1); 
    roomCountLabel.textContent = roomCount.value; 
    updateSummary();
  });
  roomPlus.addEventListener('click', () => { 
    roomCount.value = parseInt(roomCount.value) + 1; 
    roomCountLabel.textContent = roomCount.value; 
    updateSummary();
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
        window.location.href = data.redirect;
      } else {
        alert(data.message);
        btn.disabled = false;
        btn.textContent = '<?= t('checkout.confirm_booking', 'Confirm Booking') ?>';
      }
    })
    .catch(err => {
      alert('An error occurred.');
      btn.disabled = false;
      btn.textContent = '<?= t('checkout.confirm_booking', 'Confirm Booking') ?>';
    });
  });

  updateSummary();
</script>
</body>
</html>

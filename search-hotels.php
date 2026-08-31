<?php
/**
 * search-hotels.php
 * Dynamic search page for hotels with inventory checks.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/components/gaia-config.php';
require_once __DIR__ . '/services/HotelAvailabilityService.php';

$gaia_base          = '';
$gaia_active        = 'hotels';
$gaia_header_style  = 'solid';

$destination = trim($_GET['destination'] ?? '');
$checkInDate = trim($_GET['check_in'] ?? '');
$checkOutDate = trim($_GET['check_out'] ?? '');
$guestCount = (int)($_GET['guests'] ?? 2);
$roomsCount = (int)($_GET['rooms'] ?? 1);

// Sanitize inputs
if ($guestCount < 1) $guestCount = 1;
if ($roomsCount < 1) $roomsCount = 1;

$error = null;
$hotels = [];

if ($destination !== '' && $checkInDate !== '' && $checkOutDate !== '') {
    if ($checkInDate >= $checkOutDate) {
        $error = t('search.invalid_dates', 'Check-out date must be after check-in date.');
    } else {
        try {
            $pdo = getPDO();
            // We need to find hotels that have at least ONE room type that satisfies the request.
            // A room type satisfies if:
            // 1. is_active = 1
            // 2. capacity * roomsCount >= guestCount
            // 3. (quantity - COALESCE(SUM(bookings.rooms_count), 0)) >= roomsCount
            
            $statusList = implode(',', array_map(function($s) { return "'" . $s . "'"; }, HotelAvailabilityService::BLOCKING_STATUSES));

            $sql = "
                SELECT h.*, MIN(r.price) as min_price 
                FROM hotels h
                JOIN hotel_rooms r ON h.id = r.hotel_id
                WHERE h.is_active = 1 
                AND r.is_active = 1
                AND (h.location LIKE :dest OR h.name LIKE :dest2)
                AND (r.capacity * :roomsCount) >= :guestCount
                AND (
                    r.quantity - COALESCE((
                        SELECT SUM(b.rooms_count)
                        FROM hotel_bookings b
                        WHERE b.room_id = r.id
                        AND b.status IN ({$statusList})
                        AND b.check_in_date < :checkOut AND b.check_out_date > :checkIn
                    ), 0)
                ) >= :roomsCount2
                GROUP BY h.id
                ORDER BY min_price ASC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':dest', '%' . $destination . '%', PDO::PARAM_STR);
            $stmt->bindValue(':dest2', '%' . $destination . '%', PDO::PARAM_STR);
            $stmt->bindValue(':roomsCount', $roomsCount, PDO::PARAM_INT);
            $stmt->bindValue(':guestCount', $guestCount, PDO::PARAM_INT);
            $stmt->bindValue(':checkIn', $checkInDate, PDO::PARAM_STR);
            $stmt->bindValue(':checkOut', $checkOutDate, PDO::PARAM_STR);
            $stmt->bindValue(':roomsCount2', $roomsCount, PDO::PARAM_INT);
            
            $stmt->execute();
            $hotels = $stmt->fetchAll();
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
} else if (isset($_GET['search'])) {
    $error = t('search.missing_fields', 'Please fill in all search fields.');
}

$pageTitle = t('nav.hotels', 'Hotels') . ' — GAIA TOURS & TRAVEL';
$currency = site_setting('currency_symbol', '$');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<style>
  :root { --ink: var(--gaia-ink); --muted: var(--gaia-muted); --line: var(--gaia-line); --warm: var(--gaia-warm); --teal: var(--gaia-accent); }
  * { box-sizing: border-box; }
  body { margin: 0; font-family: 'Inter', sans-serif; color: var(--ink); background: var(--bg-soft, #f4efe6); -webkit-font-smoothing: antialiased; }
  h1, h2, h3 { font-family: 'Playfair Display', serif; margin: 0; }
  a { text-decoration: none; color: inherit; }
  .container { max-width: 1280px; margin: 0 auto; padding: 0 24px; }
  
  .search-hero { background: var(--navy, #1b2a4a); color: #fff; padding: 60px 0 80px; text-align: center; }
  .search-hero h1 { font-size: 38px; margin-bottom: 12px; }
  
  .search-card { background: #fff; border-radius: 16px; box-shadow: 0 15px 35px rgba(0,0,0,.1); display: flex; flex-wrap: wrap; padding: 12px; gap: 8px; max-width: 1000px; margin: -40px auto 40px; position: relative; z-index: 10; color: var(--ink); }
  .search-field { flex: 1 1 160px; display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-right: 1px solid var(--line); }
  .search-field:last-of-type { border-right: none; }
  .search-field i { color: var(--teal); font-size: 16px; }
  .search-field input { border: none; outline: none; font-size: 14px; width: 100%; font-family: inherit; color: var(--ink); background: transparent; }
  .search-field .lbl { font-size: 11px; color: var(--muted); display: block; margin-bottom: 4px; text-align: left; }
  .search-btn { background: var(--teal); color: #fff; border: none; border-radius: 10px; padding: 0 30px; font-weight: 700; font-size: 15px; white-space: nowrap; display: flex; align-items: center; gap: 8px; cursor: pointer; transition: background .2s; }
  .search-btn:hover { background: #175a75; }

  .results { padding: 40px 0 80px; }
  .error-msg { background: #fdecea; color: #b3261e; border: 1px solid #f5c6c2; border-radius: 10px; padding: 14px 18px; text-align: center; margin-bottom: 24px; font-size: 14px; }
  
  .hotel-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
  .hotel-card { border: 1px solid var(--line); border-radius: 14px; overflow: hidden; background: #fff; display: flex; flex-direction: column; transition: transform .2s, box-shadow .2s; }
  .hotel-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,.08); }
  .hotel-card .media { aspect-ratio: 4/3; overflow: hidden; position: relative; }
  .hotel-card .media img { width: 100%; height: 100%; object-fit: cover; }
  .hotel-card .stars { position: absolute; top: 12px; left: 12px; background: rgba(0,0,0,.6); color: var(--gaia-gold); padding: 4px 10px; border-radius: 20px; font-size: 12px; backdrop-filter: blur(4px); }
  .hotel-card .body { padding: 20px; flex: 1; display: flex; flex-direction: column; }
  .hotel-card h3 { font-size: 18px; margin-bottom: 8px; line-height: 1.3; }
  .hotel-card p { color: var(--muted); font-size: 13.5px; line-height: 1.5; margin: 0 0 16px; flex: 1; }
  .hotel-card .meta { display: flex; align-items: center; justify-content: space-between; border-top: 1px solid var(--line); padding-top: 16px; }
  .hotel-card .price { font-weight: 700; color: var(--teal); font-size: 18px; }
  .hotel-card .price small { font-size: 12px; color: var(--muted); font-weight: 400; }
  
  .empty-state { text-align: center; padding: 60px 20px; background: #fff; border-radius: 16px; border: 1px dashed var(--line); }
  .empty-state i { font-size: 40px; color: var(--muted); margin-bottom: 16px; opacity: .5; }
  .empty-state h3 { font-size: 20px; margin-bottom: 8px; }
  .empty-state p { color: var(--muted); font-size: 14px; }

  @media (max-width: 992px) { .hotel-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 768px) { .hotel-grid { grid-template-columns: 1fr; } .search-field { border-right: none; border-bottom: 1px solid var(--line); } .search-btn { width: 100%; padding: 14px; justify-content: center; } }
</style>
</head>
<body>
<?php require __DIR__ . '/components/gaia-header.php'; ?>

<section class="search-hero">
  <div class="container">
    <h1><?= t('search.hotel_title', 'Find Your Perfect Stay') ?></h1>
    <p><?= t('search.hotel_sub', 'Search through our exclusive collection of premium hotels.') ?></p>
  </div>
</section>

<div class="container">
  <form class="search-card" action="search-hotels.php" method="get">
    <input type="hidden" name="search" value="1">
    <div class="search-field">
      <i class="fa-solid fa-location-dot"></i>
      <div style="width:100%">
        <span class="lbl"><?= t('search.destination', 'Destination') ?></span>
        <input type="text" name="destination" value="<?= htmlspecialchars($destination) ?>" placeholder="City or Hotel Name" required>
      </div>
    </div>
    <div class="search-field">
      <i class="fa-solid fa-calendar-check"></i>
      <div style="width:100%">
        <span class="lbl"><?= t('search.check_in', 'Check-in') ?></span>
        <input type="date" name="check_in" value="<?= htmlspecialchars($checkInDate) ?>" required>
      </div>
    </div>
    <div class="search-field">
      <i class="fa-solid fa-calendar-xmark"></i>
      <div style="width:100%">
        <span class="lbl"><?= t('search.check_out', 'Check-out') ?></span>
        <input type="date" name="check_out" value="<?= htmlspecialchars($checkOutDate) ?>" required>
      </div>
    </div>
    <div class="search-field" style="max-width:120px;">
      <i class="fa-solid fa-user-group"></i>
      <div style="width:100%">
        <span class="lbl"><?= t('search.guests', 'Guests') ?></span>
        <input type="number" name="guests" min="1" value="<?= $guestCount ?>" required>
      </div>
    </div>
    <div class="search-field" style="max-width:120px;">
      <i class="fa-solid fa-door-open"></i>
      <div style="width:100%">
        <span class="lbl"><?= t('search.rooms', 'Rooms') ?></span>
        <input type="number" name="rooms" min="1" value="<?= $roomsCount ?>" required>
      </div>
    </div>
    <button class="search-btn" type="submit"><i class="fa-solid fa-magnifying-glass"></i> <?= t('search.btn', 'Search') ?></button>
  </form>
</div>

<section class="results container">
  <?php if ($error): ?>
    <div class="error-msg"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (isset($_GET['search']) && !$error): ?>
    <?php if (empty($hotels)): ?>
      <div class="empty-state">
        <i class="fa-solid fa-bed-pulse"></i>
        <h3><?= t('search.no_results', 'No hotels available') ?></h3>
        <p><?= t('search.no_results_sub', 'Try adjusting your dates, destination, or guest count.') ?></p>
      </div>
    <?php else: ?>
      <div style="margin-bottom:24px; font-size:15px; font-weight:600;">
        <?= count($hotels) ?> <?= t('search.hotels_found', 'Hotels found') ?>
      </div>
      <div class="hotel-grid">
        <?php foreach ($hotels as $h): ?>
          <a class="hotel-card" href="<?= gaia_url('hotel.php') ?>?slug=<?= urlencode($h['slug']) ?>&check_in=<?= urlencode($checkInDate) ?>&check_out=<?= urlencode($checkOutDate) ?>&guests=<?= $guestCount ?>&rooms=<?= $roomsCount ?>">
            <div class="media">
              <img src="<?= htmlspecialchars($h['image_url']) ?>" alt="<?= htmlspecialchars(lang_value($h, 'name')) ?>" loading="lazy">
              <?php if($h['star_rating']): ?>
                <div class="stars"><?= str_repeat('★', (int)$h['star_rating']) ?></div>
              <?php endif; ?>
            </div>
            <div class="body">
              <h3><?= htmlspecialchars(lang_value($h, 'name')) ?></h3>
              <p><i class="fa-solid fa-location-dot" style="opacity:.6;"></i> <?= htmlspecialchars(lang_value($h, 'location')) ?></p>
              <div class="meta">
                <?php
                  $gatewayFee = (float) site_setting('payment_gateway_fee_percent', '2.5');
                  $adminCommission = (float)($h['admin_commission_percent'] ?? 0);
                  $userPrice = (float)$h['min_price'] * (1 + $adminCommission / 100) * (1 + $gatewayFee / 100);
                ?>
                <span class="price"><?= $currency ?><?= number_format($userPrice, 0) ?> <small><?= t('hotel.per_night', '/ night') ?></small></span>
                <span class="gaia-btn gaia-btn-sm" style="pointer-events:none;"><?= t('hotel.view', 'View') ?></span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/components/gaia-footer.php'; ?>
<script src="/assets/gaia.js"></script>
</body>
</html>

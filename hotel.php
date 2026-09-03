<?php
/**
 * hotel.php — Legendary Hotel Details & Room Booking Experience
 * ------------------------------------------------------------
 * Dynamic, high-conversion UI/UX with Bento gallery, instant room selector,
 * sticky booking summary widget, and responsive layout across all screens.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/components/gaia-config.php';
require_once __DIR__ . '/services/HotelAvailabilityService.php';

$gaia_base          = '';
$gaia_active        = 'hotels';
$gaia_header_style  = 'solid';

$slug = trim($_GET['slug'] ?? '');
$hotel = null;

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM hotels WHERE slug = :slug AND is_active = 1 LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    $hotel = $stmt->fetch();
} catch (PDOException $e) {
    $hotel = null;
}

if (!$hotel) {
    http_response_code(404);
    $is404 = true;
    $gallery = [];
} else {
    $is404 = false;
    $hotelId = (int)$hotel['id'];

    // Localized hotel fields
    $hotelName = lang_value($hotel, 'name', 'Hotel');
    $hotelDesc = lang_value($hotel, 'description', '');
    $hotelLoc  = lang_value($hotel, 'location', '');

    // Gallery
    $gallery = $hotel['gallery_urls'] !== null
        ? array_filter(array_map('trim', explode(',', $hotel['gallery_urls'])))
        : [];
    if (empty($gallery) && $hotel['image_url']) {
        $gallery = [$hotel['image_url']];
    }
    if (empty($gallery)) {
        $gallery = ['https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=900&q=80'];
    }

    // Facilities
    $facStmt = $pdo->prepare("SELECT * FROM hotel_facilities WHERE hotel_id = :id ORDER BY sort_order");
    $facStmt->execute([':id' => $hotelId]);
    $facilities = $facStmt->fetchAll();

    // Rooms
    $roomStmt = $pdo->prepare("SELECT * FROM hotel_rooms WHERE hotel_id = :id AND is_active = 1 ORDER BY price ASC");
    $roomStmt->execute([':id' => $hotelId]);
    $rooms = $roomStmt->fetchAll();

    // Offers
    $offStmt = $pdo->prepare("SELECT * FROM hotel_offers WHERE hotel_id = :id AND is_active = 1 ORDER BY sort_order");
    $offStmt->execute([':id' => $hotelId]);
    $offers = $offStmt->fetchAll();

    // Reviews
    $revStmt = $pdo->prepare("SELECT * FROM hotel_reviews WHERE hotel_id = :id AND is_active = 1 ORDER BY id DESC");
    $revStmt->execute([':id' => $hotelId]);
    $reviews = $revStmt->fetchAll();

    // Nearby hotels (excluding current)
    $nearStmt = $pdo->prepare("SELECT * FROM hotels WHERE id != :id AND is_active = 1 ORDER BY avg_rating DESC LIMIT 3");
    $nearStmt->execute([':id' => $hotelId]);
    $nearby = $nearStmt->fetchAll();
}

// Search context
$searchCtx = HotelAvailabilityService::validateSearchContext($_GET);
$hasSearchCtx = $searchCtx !== null;

$checkInDate  = $searchCtx['check_in'] ?? ($_GET['check_in'] ?? date('Y-m-d', strtotime('+3 days')));
$checkOutDate = $searchCtx['check_out'] ?? ($_GET['check_out'] ?? date('Y-m-d', strtotime('+5 days')));
$guestsCount  = $searchCtx['guests'] ?? (int)($_GET['guests'] ?? 2);
$roomsCount   = $searchCtx['rooms'] ?? (int)($_GET['rooms'] ?? 1);

// Calculate nights
$nights = 2;
if ($checkInDate && $checkOutDate) {
    $tIn = strtotime($checkInDate);
    $tOut = strtotime($checkOutDate);
    if ($tOut > $tIn) {
        $nights = (int)round(($tOut - $tIn) / 86400);
    }
}

// Pre-compute availability per room
$roomAvailability = [];
if (!$is404 && !empty($rooms)) {
    foreach ($rooms as $r) {
        $avail = HotelAvailabilityService::availableInventory(
            $pdo, (int)$r['id'], (int)$r['quantity'],
            $checkInDate, $checkOutDate
        );
        $roomAvailability[(int)$r['id']] = $avail;
    }
}

// Dynamic SEO
if (!$is404) {
    $seoTitle = $hotelName . ' — Luxury Accommodation & Stays';
    $seoDesc  = mb_strimwidth(strip_tags($hotelDesc), 0, 150, '…');
    $seoImage = $gallery[0] ?? '';
    $avgRating = (float)($hotel['avg_rating'] ?? 4.8);
    if ($avgRating <= 0) $avgRating = 4.8;
} else {
    $seoTitle = t('detail.not_found', 'Hotel Not Found') . ' — GAIA TOURS & TRAVEL';
    $seoDesc  = t('detail.not_found_text', 'The page you are looking for does not exist.');
    $seoImage = '';
    $avgRating = 0;
}

$currency = site_setting('currency_symbol', '$');
$adminCommission = (float)($hotel['admin_commission_percent'] ?? 0);
$gatewayFee = (float) site_setting('payment_gateway_fee_percent', '2.5');
$basePrice = (float)($hotel['price_from'] ?? 180);
$userFromPrice = $basePrice * (1 + $adminCommission / 100) * (1 + $gatewayFee / 100);
$whatsappNum = site_setting('contact_whatsapp', '962790123456');
$cleanWa = preg_replace('/[^0-9]/', '', $whatsappNum);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($seoTitle) ?></title>
<meta name="description" content="<?= htmlspecialchars($seoDesc) ?>">
<?php if ($seoImage): ?><meta property="og:image" content="<?= htmlspecialchars($seoImage) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<style>
  @media (min-width: 901px) {
    .gaia-burger { display: none !important; }
    .gaia-nav { display: flex !important; }
  }

  :root { 
    --ink: var(--gaia-ink, #111317); 
    --muted: var(--gaia-muted, #727a8c); 
    --line: var(--gaia-line, #e6e8ec); 
    --gold: #d4af37; 
    --teal: var(--gaia-accent, #17c9b3); 
    --teal-dark: #0f9f8c;
    --navy: #0d1730;
    --radius: 18px;
    --shadow: 0 14px 36px rgba(0,0,0,0.06);
  }
  * { box-sizing: border-box; }
  html { scroll-behavior: smooth; }
  body { margin: 0; font-family: 'Inter', sans-serif; color: var(--ink); background: #f8f9fc; -webkit-font-smoothing: antialiased; }
  h1, h2, h3, h4 { font-family: 'Playfair Display', serif; margin: 0; }
  a { text-decoration: none; color: inherit; }
  ul { list-style: none; margin: 0; padding: 0; }
  img { max-width: 100%; display: block; }
  .container { max-width: 1260px; margin: 0 auto; padding: 0 24px; }

  /* Breadcrumb & Hotel Header Bar */
  .hotel-top-bar { background: #ffffff; border-bottom: 1px solid var(--line); padding: 18px 0; }
  .gaia-breadcrumb { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--muted); margin-bottom: 10px; }
  .gaia-breadcrumb a { color: var(--muted); transition: color .2s; }
  .gaia-breadcrumb a:hover { color: var(--ink); }
  
  .hotel-header-flex { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; flex-wrap: wrap; }
  .hotel-title-box h1 { font-size: 32px; font-weight: 800; color: #111317; margin-bottom: 8px; line-height: 1.25; }
  .hotel-badges-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
  .stars-gold { color: #f59e0b; font-size: 14px; letter-spacing: 2px; }
  .loc-badge { font-size: 13.5px; color: var(--muted); display: inline-flex; align-items: center; gap: 6px; }
  .rating-pill { 
    background: #111317; 
    color: #fff; 
    padding: 4px 10px; 
    border-radius: 8px; 
    font-size: 13px; 
    font-weight: 700; 
    display: inline-flex; 
    align-items: center; 
    gap: 5px; 
  }
  .rating-pill i { color: #ffd700; }

  .hotel-actions-box { display: flex; align-items: center; gap: 12px; }
  .btn-concierge { 
    background: #25d366; 
    color: #fff; 
    padding: 10px 18px; 
    border-radius: 12px; 
    font-size: 13.5px; 
    font-weight: 700; 
    display: inline-flex; 
    align-items: center; 
    gap: 8px; 
    transition: transform .2s;
  }
  .btn-concierge:hover { transform: translateY(-2px); color: #fff; }
  .btn-jump-book { 
    background: #111317; 
    color: #fff; 
    padding: 10px 22px; 
    border-radius: 12px; 
    font-size: 13.5px; 
    font-weight: 700; 
    transition: background .2s;
  }
  .btn-jump-book:hover { background: #2b303a; }

  /* Sub-Navigation Bar */
  .hotel-subnav {
    position: sticky;
    top: 0;
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid var(--line);
    z-index: 100;
    padding: 10px 0;
    margin-bottom: 24px;
  }
  .subnav-links { display: flex; gap: 24px; }
  .subnav-links a { font-size: 13.5px; font-weight: 700; color: var(--muted); padding: 6px 0; transition: color .2s; }
  .subnav-links a:hover { color: #111317; }

  /* Bento Gallery Grid */
  .bento-gallery { 
    display: grid; 
    grid-template-columns: 2fr 1fr 1fr; 
    grid-template-rows: 200px 200px; 
    gap: 12px; 
    margin: 18px auto 30px; 
    border-radius: 20px; 
    overflow: hidden; 
  }
  .bento-item { position: relative; overflow: hidden; background: #e2e8f0; }
  .bento-item img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
  .bento-item:hover img { transform: scale(1.04); }
  .bento-main { grid-column: 1 / 2; grid-row: 1 / 3; }
  .bento-item.more-overlay::after {
    content: 'View All Photos';
    position: absolute;
    inset: 0;
    background: rgba(13,23,48,0.7);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 700;
    backdrop-filter: blur(4px);
    cursor: pointer;
  }

  /* Two-column layout */
  .hotel-detail-grid { 
    display: grid; 
    grid-template-columns: 1fr 380px; 
    gap: 36px; 
    align-items: start; 
    margin-bottom: 70px; 
  }
  
  /* Sections inside main column */
  .hotel-section { 
    background: #ffffff; 
    border-radius: var(--radius); 
    padding: 32px; 
    border: 1px solid var(--line); 
    box-shadow: var(--shadow); 
    margin-bottom: 24px; 
  }
  .hotel-section h2 { font-size: 21px; font-weight: 800; margin-bottom: 14px; color: #111317; }
  .hotel-section p { font-size: 14.5px; line-height: 1.8; color: #4b5262; margin-bottom: 0; }

  /* Facilities Grid */
  .fac-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 16px; }
  .fac-item { 
    display: flex; 
    align-items: center; 
    gap: 12px; 
    background: #f8fafc; 
    border: 1px solid var(--line); 
    border-radius: 12px; 
    padding: 12px 14px; 
    font-size: 13px; 
    font-weight: 600; 
    color: #334155; 
  }
  .fac-item i { color: var(--teal); font-size: 15px; }

  /* Room Cards in List */
  .room-card-modern {
    border: 1px solid var(--line);
    border-radius: 16px;
    overflow: hidden;
    margin-top: 18px;
    background: #ffffff;
    display: grid;
    grid-template-columns: 240px 1fr;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s;
  }
  .room-card-modern:hover { transform: translateY(-3px); box-shadow: 0 16px 32px rgba(0,0,0,0.08); border-color: var(--teal); }
  .room-media { position: relative; background: #e2e8f0; }
  .room-media img { width: 100%; height: 100%; object-fit: cover; }
  .room-avail-tag {
    position: absolute;
    top: 12px;
    left: 12px;
    background: #059669;
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    padding: 4px 10px;
    border-radius: 999px;
  }
  .room-body { padding: 20px; display: flex; flex-direction: column; justify-content: space-between; }
  .room-body h3 { font-size: 18px; font-weight: 800; margin-bottom: 6px; color: #111317; }
  
  .room-features { display: flex; gap: 12px; font-size: 12px; color: var(--muted); margin-bottom: 12px; flex-wrap: wrap; }
  .room-features span { display: flex; align-items: center; gap: 5px; }

  .room-inclusions { display: flex; flex-direction: column; gap: 5px; margin-bottom: 16px; }
  .room-inclusions li { font-size: 12.5px; color: #059669; display: flex; align-items: center; gap: 7px; font-weight: 600; }
  
  .room-pricing-row { display: flex; justify-content: space-between; align-items: flex-end; border-top: 1px solid var(--line); padding-top: 14px; }
  .room-price-val { font-size: 21px; font-weight: 800; color: #111317; }
  .room-price-val small { font-size: 12px; color: var(--muted); font-weight: 500; }
  .btn-select-room {
    background: #111317;
    color: #fff;
    padding: 10px 22px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 700;
    transition: background .2s;
  }
  .btn-select-room:hover { background: var(--teal); color: #0d1730; }

  /* Sticky Booking Widget Sidebar */
  .booking-widget-sticky {
    position: sticky;
    top: 70px;
    background: #ffffff;
    border-radius: var(--radius);
    padding: 26px;
    border: 1px solid var(--line);
    box-shadow: 0 20px 45px rgba(0,0,0,0.08);
  }
  .widget-price-header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 16px; }
  .widget-from-rate { font-size: 26px; font-weight: 800; color: #111317; }
  .widget-from-rate small { font-size: 12.5px; color: var(--muted); font-weight: 500; }
  
  .widget-dates-grid { 
    border: 1px solid var(--line); 
    border-radius: 12px; 
    overflow: hidden; 
    margin-bottom: 14px; 
    background: #f8fafc; 
  }
  .widget-date-row { display: grid; grid-template-columns: 1fr 1fr; border-bottom: 1px solid var(--line); }
  .widget-date-field { padding: 10px 12px; }
  .widget-date-field:first-child { border-right: 1px solid var(--line); }
  .widget-date-field .lbl { font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--muted); display: block; margin-bottom: 2px; }
  .widget-date-field input { border: none; outline: none; background: transparent; font-size: 13px; font-weight: 600; width: 100%; color: #111317; }
  .widget-guests-field { padding: 10px 12px; display: flex; justify-content: space-between; align-items: center; }
  .widget-guests-field input { border: none; outline: none; background: transparent; font-size: 13px; font-weight: 600; width: 50px; }

  .widget-breakdown { border-top: 1px solid var(--line); padding-top: 12px; margin-top: 12px; display: flex; flex-direction: column; gap: 8px; font-size: 13px; }
  .widget-row { display: flex; justify-content: space-between; color: var(--muted); }
  .widget-total-row { display: flex; justify-content: space-between; font-size: 15.5px; font-weight: 800; color: #111317; margin-top: 4px; }

  .btn-book-primary {
    background: #111317;
    color: #fff;
    border: none;
    border-radius: 12px;
    width: 100%;
    padding: 14px;
    font-size: 14.5px;
    font-weight: 700;
    margin-top: 16px;
    cursor: pointer;
    text-align: center;
    display: block;
    transition: background .2s, transform .2s;
  }
  .btn-book-primary:hover { background: #2b303a; transform: translateY(-1px); }

  /* Reviews Section */
  .review-score-box {
    background: linear-gradient(135deg, #0d1730 0%, #16264d 100%);
    color: #fff;
    border-radius: 16px;
    padding: 22px;
    display: flex;
    align-items: center;
    gap: 18px;
    margin-bottom: 22px;
  }
  .score-num { font-size: 38px; font-weight: 800; color: #ffd700; line-height: 1; }
  .score-text h4 { font-size: 17px; font-weight: 700; margin-bottom: 3px; color: #fff; }
  .score-text p { font-size: 12.5px; color: #cbd5e1; margin: 0; }
  
  .reviews-list { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
  .review-item-card { background: #f8fafc; border: 1px solid var(--line); border-radius: 12px; padding: 16px; }
  .review-item-card .stars { color: #f59e0b; font-size: 12px; margin-bottom: 6px; }
  .review-item-card h5 { font-size: 13.5px; font-weight: 700; margin-bottom: 6px; display: flex; justify-content: space-between; }
  .review-item-card p { font-size: 12.5px; color: #475569; line-height: 1.55; margin: 0; }

  /* Mobile Bottom Floating Bar */
  .mobile-booking-bar {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #ffffff;
    border-top: 1px solid var(--line);
    padding: 12px 20px;
    z-index: 999;
    box-shadow: 0 -8px 24px rgba(0,0,0,0.1);
    align-items: center;
    justify-content: space-between;
  }
  .mobile-booking-bar .mob-price { font-size: 18px; font-weight: 800; color: #111317; }
  .mobile-booking-bar .mob-price small { font-size: 11.5px; color: var(--muted); font-weight: 500; }
  .mobile-booking-bar .mob-btn {
    background: #111317;
    color: #fff;
    padding: 10px 20px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 13.5px;
  }

  /* Responsive Breakpoints */
  @media (max-width: 1024px) {
    .hotel-detail-grid { grid-template-columns: 1fr; }
    .booking-widget-sticky { position: static; margin-bottom: 24px; }
    .bento-gallery { grid-template-columns: 1fr 1fr; grid-template-rows: 240px 140px; }
    .bento-main { grid-column: 1 / 3; grid-row: 1 / 2; }
  }
  @media (max-width: 768px) {
    .hotel-header-flex { flex-direction: column; align-items: flex-start; }
    .fac-grid { grid-template-columns: 1fr 1fr; }
    .room-card-modern { grid-template-columns: 1fr; }
    .room-media { aspect-ratio: 16/10; }
    .reviews-list { grid-template-columns: 1fr; }
    .bento-gallery { display: flex; flex-direction: column; height: 260px; }
    .bento-item:not(.bento-main) { display: none; }
    .mobile-booking-bar { display: flex; }
    body { padding-bottom: 70px; }
  }
</style>
</head>
<body>
<?php require __DIR__ . '/components/gaia-header.php'; ?>

<?php if ($is404): ?>
  <section class="container" style="text-align:center; padding:100px 20px;">
    <h1 style="font-size:36px; margin-bottom:12px;"><?= t('detail.not_found', 'Hotel Not Found') ?></h1>
    <p style="color:var(--muted); margin-bottom:24px;"><?= t('detail.not_found_text', 'The property you are looking for is currently unavailable.') ?></p>
    <a href="<?= gaia_url('search-hotels.php') ?>" class="btn-jump-book">Browse All Hotels</a>
  </section>
<?php else: ?>

  <!-- Top Navigation & Hotel Header -->
  <div class="hotel-top-bar">
    <div class="container">
      <nav class="gaia-breadcrumb">
        <a href="<?= gaia_url('index.php') ?>"><?= t('detail.home', 'Home') ?></a>
        <span>/</span>
        <a href="<?= gaia_url('search-hotels.php') ?>"><?= t('nav.hotels', 'Hotels') ?></a>
        <span>/</span>
        <span style="color:#111317; font-weight:600;"><?= htmlspecialchars($hotelName) ?></span>
      </nav>

      <div class="hotel-header-flex">
        <div class="hotel-title-box">
          <h1><?= htmlspecialchars($hotelName) ?></h1>
          <div class="hotel-badges-row">
            <span class="stars-gold"><?= str_repeat('★', (int)$hotel['star_rating']) ?></span>
            <div class="rating-pill"><i class="fa-solid fa-star"></i> <?= number_format($avgRating, 1) ?> (<?= count($reviews) ?: 28 ?> <?= t('hotel.reviews', 'reviews') ?>)</div>
            <span class="loc-badge"><i class="fa-solid fa-location-dot" style="color:var(--teal);"></i> <?= htmlspecialchars($hotelLoc) ?></span>
          </div>
        </div>

        <div class="hotel-actions-box">
          <a href="https://wa.me/<?= htmlspecialchars($cleanWa) ?>?text=<?= urlencode('Inquiry regarding ' . $hotelName) ?>" target="_blank" rel="noopener" class="btn-concierge">
            <i class="fa-brands fa-whatsapp"></i> <?= t('hotel.contact', 'WhatsApp Concierge') ?>
          </a>
          <a href="#rooms" class="btn-jump-book"><?= t('hotel.quick_booking', 'Select Room') ?></a>
        </div>
      </div>
    </div>
  </div>

  <!-- Sticky Sub-Nav Anchor Bar -->
  <div class="hotel-subnav">
    <div class="container">
      <div class="subnav-links">
        <a href="#about"><?= t('hotel.description', 'Overview') ?></a>
        <a href="#rooms"><?= t('hotel.rooms', 'Rooms & Suites') ?></a>
        <a href="#facilities"><?= t('hotel.facilities', 'Facilities') ?></a>
        <a href="#reviews"><?= t('hotel.reviews_title', 'Reviews') ?></a>
      </div>
    </div>
  </div>

  <div class="container">
    
    <!-- Bento Photo Gallery -->
    <div class="bento-gallery" id="about">
      <div class="bento-item bento-main">
        <img src="<?= htmlspecialchars($gallery[0] ?? 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=900&q=80') ?>" alt="<?= htmlspecialchars($hotelName) ?> Feature" loading="lazy">
      </div>
      <?php for ($i = 1; $i <= 4; $i++): ?>
        <?php if (!empty($gallery[$i])): ?>
          <div class="bento-item <?= $i === 4 ? 'more-overlay' : '' ?>">
            <img src="<?= htmlspecialchars($gallery[$i]) ?>" alt="<?= htmlspecialchars($hotelName) ?> Photo <?= $i ?>" loading="lazy">
          </div>
        <?php endif; ?>
      <?php endfor; ?>
    </div>

    <!-- Main Two Column Grid -->
    <div class="hotel-detail-grid">
      
      <!-- Left Column: Hotel Details & Rooms -->
      <div>
        
        <!-- About Section -->
        <div class="hotel-section">
          <h2><?= t('hotel.description', 'About the Property') ?></h2>
          <p><?= nl2br(htmlspecialchars($hotelDesc ?: 'Experience unmatched luxury, authentic Arabian hospitality, and bespoke comfort.')) ?></p>
        </div>

        <!-- Available Rooms & Suites -->
        <div class="hotel-section" id="rooms">
          <h2><?= t('hotel.rooms', 'Available Rooms & Suites') ?></h2>
          <p style="margin-bottom:14px; font-size:13.5px; color:var(--muted);">Select your preferred room type. Instant confirmation with best rates guaranteed.</p>

          <?php if (!empty($rooms)): ?>
            <?php foreach ($rooms as $r): ?>
              <?php
                $rId = (int)$r['id'];
                $avail = $roomAvailability[$rId] ?? 5;
                $isSoldOut = ($avail !== null && $avail < $roomsCount);
                $roomUserPrice = (float)$r['price'] * (1 + $adminCommission / 100) * (1 + $gatewayFee / 100);
                $bookUrl = gaia_url('checkout-hotel.php') . '?room_id=' . $rId . '&check_in=' . urlencode($checkInDate) . '&check_out=' . urlencode($checkOutDate) . '&guests=' . $guestsCount . '&rooms=' . $roomsCount;
              ?>
              <div class="room-card-modern">
                <div class="room-media">
                  <img src="<?= htmlspecialchars($r['image_url'] ?: 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=600&q=80') ?>" alt="<?= htmlspecialchars(lang_value($r, 'name')) ?>" loading="lazy">
                  <span class="room-avail-tag"><?= $avail ?> <?= t('hotel.rooms_available', 'Rooms Available') ?></span>
                </div>
                <div class="room-body">
                  <div>
                    <h3><?= htmlspecialchars(lang_value($r, 'name')) ?></h3>
                    <div class="room-features">
                      <span><i class="fa-solid fa-user-group"></i> Up to <?= (int)$r['capacity'] ?> Guests</span>
                      <span><i class="fa-solid fa-bed"></i> <?= htmlspecialchars(lang_value($r, 'beds') ?: '1 King Bed') ?></span>
                      <?php if (!empty($r['size_sqm'])): ?>
                        <span><i class="fa-solid fa-ruler-combined"></i> <?= (int)$r['size_sqm'] ?> m²</span>
                      <?php endif; ?>
                    </div>
                    <ul class="room-inclusions">
                      <li><i class="fa-solid fa-circle-check"></i> Complimentary Gourmet Breakfast</li>
                      <li><i class="fa-solid fa-circle-check"></i> Free High-Speed Wi-Fi</li>
                      <li><i class="fa-solid fa-circle-check"></i> Free Cancellation up to 48 hours before check-in</li>
                    </ul>
                  </div>

                  <div class="room-pricing-row">
                    <div>
                      <div class="room-price-val"><?= $currency ?><?= number_format($roomUserPrice, 0) ?> <small><?= t('hotel.per_night', '/ night') ?></small></div>
                      <div style="font-size:11.5px; color:var(--muted);">Taxes & fees included</div>
                    </div>
                    <a href="<?= $bookUrl ?>" class="btn-select-room"><?= t('hotel.quick_booking', 'Reserve Room') ?></a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Facilities -->
        <?php if (!empty($facilities)): ?>
          <div class="hotel-section" id="facilities">
            <h2><?= t('hotel.facilities', 'Hotel Facilities & Highlights') ?></h2>
            <div class="fac-grid">
              <?php foreach ($facilities as $f): ?>
                <div class="fac-item">
                  <i class="<?= htmlspecialchars($f['icon'] ?? 'fa-solid fa-check') ?>"></i>
                  <span><?= htmlspecialchars(lang_value($f, 'name')) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Guest Reviews -->
        <div class="hotel-section" id="reviews">
          <h2><?= t('hotel.reviews_title', 'Guest Reviews & Ratings') ?></h2>
          
          <div class="review-score-box">
            <div class="score-num"><?= number_format($avgRating, 1) ?></div>
            <div class="score-text">
              <h4>Superb Luxury Experience</h4>
              <p>Based on verified guest stays across Jordan.</p>
            </div>
          </div>

          <div class="reviews-list">
            <?php if (!empty($reviews)): ?>
              <?php foreach ($reviews as $rv): ?>
                <div class="review-item-card">
                  <div class="stars"><?= str_repeat('★', (int)$rv['rating']) ?></div>
                  <h5><?= htmlspecialchars(lang_value($rv, 'reviewer')) ?> <span><?= htmlspecialchars($rv['date_label'] ?? 'Recent stay') ?></span></h5>
                  <p><?= htmlspecialchars(lang_value($rv, 'text')) ?></p>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="review-item-card">
                <div class="stars">★★★★★</div>
                <h5>Alexander M. <span>Verified Guest</span></h5>
                <p>Exceptional service and stunning views. The staff went above and beyond to make our stay memorable.</p>
              </div>
              <div class="review-item-card">
                <div class="stars">★★★★★</div>
                <h5>Sarah K. <span>Verified Guest</span></h5>
                <p>Outstanding amenities, plush rooms, and delicious dining. Highly recommended for couples and families.</p>
              </div>
            <?php endif; ?>
          </div>
        </div>

      </div>

      <!-- Right Column: Sticky Reservation Summary Widget -->
      <aside>
        <div class="booking-widget-sticky">
          <div class="widget-price-header">
            <div>
              <div class="widget-from-rate"><?= $currency ?><?= number_format($userFromPrice, 0) ?> <small><?= t('hotel.per_night', '/ night') ?></small></div>
            </div>
            <div class="rating-pill"><i class="fa-solid fa-star"></i> <?= number_format($avgRating, 1) ?></div>
          </div>

          <form action="<?= gaia_url('hotel.php') ?>" method="get">
            <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>">
            
            <div class="widget-dates-grid">
              <div class="widget-date-row">
                <div class="widget-date-field">
                  <span class="lbl"><?= t('search.check_in', 'Check-In') ?></span>
                  <input type="date" name="check_in" value="<?= htmlspecialchars($checkInDate) ?>" onchange="this.form.submit()">
                </div>
                <div class="widget-date-field">
                  <span class="lbl"><?= t('search.check_out', 'Check-Out') ?></span>
                  <input type="date" name="check_out" value="<?= htmlspecialchars($checkOutDate) ?>" onchange="this.form.submit()">
                </div>
              </div>
              <div class="widget-guests-field">
                <span class="lbl" style="margin:0;"><?= t('search.guests', 'Guests') ?>:</span>
                <input type="number" name="guests" min="1" max="10" value="<?= $guestsCount ?>" onchange="this.form.submit()">
                <span class="lbl" style="margin:0; margin-left:10px;"><?= t('search.rooms', 'Rooms') ?>:</span>
                <input type="number" name="rooms" min="1" max="5" value="<?= $roomsCount ?>" onchange="this.form.submit()">
              </div>
            </div>
          </form>

          <div class="widget-breakdown">
            <div class="widget-row">
              <span><?= $currency ?><?= number_format($userFromPrice, 0) ?> × <?= $nights ?> <?= t('checkout.nights', 'nights') ?></span>
              <span><?= $currency ?><?= number_format($userFromPrice * $nights, 0) ?></span>
            </div>
            <div class="widget-row">
              <span>Estimated Taxes & Service (10%)</span>
              <span><?= $currency ?><?= number_format($userFromPrice * $nights * 0.10, 0) ?></span>
            </div>
            <div class="widget-total-row">
              <span>Total Stay Price</span>
              <span><?= $currency ?><?= number_format($userFromPrice * $nights * 1.10, 0) ?></span>
            </div>
          </div>

          <a href="#rooms" class="btn-book-primary">
            <i class="fa-solid fa-check"></i> <?= t('hotel.quick_booking', 'Select Room') ?>
          </a>

          <div style="text-align:center; margin-top:14px; font-size:12px; color:var(--muted);">
            <i class="fa-solid fa-lock"></i> Instant Confirmation · Secure Booking
          </div>
        </div>
      </aside>

    </div>

  </div>

  <!-- Mobile Bottom Floating Action Bar -->
  <div class="mobile-booking-bar">
    <div class="mob-price">
      <?= $currency ?><?= number_format($userFromPrice, 0) ?> <small><?= t('hotel.per_night', '/ night') ?></small>
    </div>
    <a href="#rooms" class="mob-btn"><?= t('hotel.quick_booking', 'Select Room') ?></a>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/components/gaia-footer.php'; ?>
<script src="/assets/gaia.js"></script>
</body>
</html>
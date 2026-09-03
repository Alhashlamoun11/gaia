<?php
/**
 * search-hotels.php
 * Legendary Hotel Portal — Dynamic search, curated luxury stays, interactive filters,
 * and responsive UI across all desktop, tablet, and mobile screens.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/components/gaia-config.php';
require_once __DIR__ . '/services/HotelAvailabilityService.php';

$gaia_base          = '';
$gaia_active        = 'hotels';
$gaia_header_style  = 'solid';

$destination  = trim($_GET['destination'] ?? '');
$checkInDate  = trim($_GET['check_in'] ?? '');
$checkOutDate = trim($_GET['check_out'] ?? '');
$guestCount   = (int)($_GET['guests'] ?? 2);
$roomsCount   = (int)($_GET['rooms'] ?? 1);
$cityFilter   = trim($_GET['city'] ?? '');
$category     = trim($_GET['category'] ?? '');
$sortBy       = trim($_GET['sort'] ?? 'recommended');

// Sanitize inputs
if ($guestCount < 1) $guestCount = 1;
if ($roomsCount < 1) $roomsCount = 1;

// Nights calculation
$nights = 1;
if ($checkInDate && $checkOutDate) {
    $tIn = strtotime($checkInDate);
    $tOut = strtotime($checkOutDate);
    if ($tOut > $tIn) {
        $nights = (int)round(($tOut - $tIn) / 86400);
    }
}

$error = null;
$searchResults = [];
$isSearching = (isset($_GET['search']) || ($destination !== '' && $checkInDate !== '' && $checkOutDate !== ''));

$pdo = getPDO();
$gatewayFee = (float) site_setting('payment_gateway_fee_percent', '2.5');
$currency = site_setting('currency_symbol', '$');

// 1. Search Query
if ($isSearching) {
    if ($destination !== '' && $checkInDate !== '' && $checkOutDate !== '') {
        if ($checkInDate >= $checkOutDate) {
            $error = t('search.invalid_dates', 'Check-out date must be after check-in date.');
        } else {
            try {
                $statusList = implode(',', array_map(function($s) { return "'" . $s . "'"; }, HotelAvailabilityService::BLOCKING_STATUSES));

                $sql = "
                    SELECT h.*, COALESCE(MIN(r.price), h.price_from, 150) as min_price,
                           COUNT(DISTINCT r.id) as room_types_cnt
                    FROM hotels h
                    LEFT JOIN hotel_rooms r ON h.id = r.hotel_id AND r.is_active = 1
                    WHERE h.is_active = 1 
                    AND (h.location LIKE :dest OR h.name LIKE :dest2 OR h.city LIKE :dest3)
                    AND (
                        r.id IS NULL 
                        OR (
                            (r.capacity * :roomsCount) >= :guestCount
                            AND (
                                r.quantity - COALESCE((
                                    SELECT SUM(b.rooms_count)
                                    FROM hotel_bookings b
                                    WHERE b.room_id = r.id
                                    AND b.status IN ({$statusList})
                                    AND b.check_in_date < :checkOut AND b.check_out_date > :checkIn
                                ), 0)
                            ) >= :roomsCount2
                        )
                    )
                    GROUP BY h.id
                    ORDER BY min_price ASC
                ";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':dest', '%' . $destination . '%', PDO::PARAM_STR);
                $stmt->bindValue(':dest2', '%' . $destination . '%', PDO::PARAM_STR);
                $stmt->bindValue(':dest3', '%' . $destination . '%', PDO::PARAM_STR);
                $stmt->bindValue(':roomsCount', $roomsCount, PDO::PARAM_INT);
                $stmt->bindValue(':guestCount', $guestCount, PDO::PARAM_INT);
                $stmt->bindValue(':checkIn', $checkInDate, PDO::PARAM_STR);
                $stmt->bindValue(':checkOut', $checkOutDate, PDO::PARAM_STR);
                $stmt->bindValue(':roomsCount2', $roomsCount, PDO::PARAM_INT);
                
                $stmt->execute();
                $searchResults = $stmt->fetchAll();
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } else if (isset($_GET['search']) && ($destination !== '' || $cityFilter !== '')) {
        $term = $destination ?: $cityFilter;
        $stmt = $pdo->prepare("SELECT h.*, COALESCE(MIN(r.price), h.price_from, 150) as min_price FROM hotels h LEFT JOIN hotel_rooms r ON h.id = r.hotel_id WHERE h.is_active = 1 AND (h.location LIKE ? OR h.name LIKE ? OR h.city LIKE ?) GROUP BY h.id ORDER BY h.avg_rating DESC");
        $stmt->execute(['%' . $term . '%', '%' . $term . '%', '%' . $term . '%']);
        $searchResults = $stmt->fetchAll();
    }
}

// 2. Query Featured Hotels
$featuredHotels = [];
try {
    $fSql = "SELECT h.*, COALESCE(MIN(r.price), h.price_from, 150) as min_price 
             FROM hotels h 
             LEFT JOIN hotel_rooms r ON h.id = r.hotel_id AND r.is_active = 1
             WHERE h.is_active = 1 AND (h.featured = 1 OR h.star_rating >= 5 OR h.avg_rating >= 4.8)
             GROUP BY h.id 
             ORDER BY h.featured DESC, h.avg_rating DESC, h.star_rating DESC 
             LIMIT 6";
    $fStmt = $pdo->query($fSql);
    if ($fStmt) $featuredHotels = $fStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $featuredHotels = []; }

// 3. Query Curated Stays (with city / category filter)
$suggestedHotels = [];
try {
    $sWhere = "WHERE h.is_active = 1";
    $sParams = [];
    if ($cityFilter !== '' && strtolower($cityFilter) !== 'all') {
        $sWhere .= " AND (h.city LIKE ? OR h.location LIKE ?)";
        $sParams[] = '%' . $cityFilter . '%';
        $sParams[] = '%' . $cityFilter . '%';
    }
    if ($category === 'bubble') {
        $sWhere .= " AND (h.name LIKE '%Camp%' OR h.name LIKE '%Bubble%' OR h.location LIKE '%Wadi Rum%')";
    } elseif ($category === 'spa') {
        $sWhere .= " AND (h.name LIKE '%Spa%' OR h.name LIKE '%Resort%' OR h.location LIKE '%Dead Sea%')";
    } elseif ($category === 'luxury5') {
        $sWhere .= " AND h.star_rating = 5";
    }

    $orderClause = "ORDER BY h.avg_rating DESC, h.price_from ASC";
    if ($sortBy === 'price_asc') {
        $orderClause = "ORDER BY min_price ASC";
    } elseif ($sortBy === 'price_desc') {
        $orderClause = "ORDER BY min_price DESC";
    }

    $sSql = "SELECT h.*, COALESCE(MIN(r.price), h.price_from, 120) as min_price 
             FROM hotels h 
             LEFT JOIN hotel_rooms r ON h.id = r.hotel_id AND r.is_active = 1
             {$sWhere}
             GROUP BY h.id 
             {$orderClause}";
    $sStmt = $pdo->prepare($sSql);
    $sStmt->execute($sParams);
    $suggestedHotels = $sStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $suggestedHotels = []; }

$pageTitle = t('nav.hotels', 'Luxury Hotels & Stays') . ' — GAIA TOURS & TRAVEL';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<style>
  /* Explicit Desktop / Mobile Reset */
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
    --radius: 20px;
    --shadow: 0 16px 40px rgba(0,0,0,0.06);
    --shadow-hover: 0 24px 50px rgba(13,23,48,0.12);
  }
  * { box-sizing: border-box; }
  body { margin: 0; font-family: 'Inter', sans-serif; color: var(--ink); background: #f8fafc; -webkit-font-smoothing: antialiased; }
  h1, h2, h3, h4 { font-family: 'Playfair Display', serif; margin: 0; }
  a { text-decoration: none; color: inherit; }
  .container { max-width: 1260px; margin: 0 auto; padding: 0 24px; }
  
  /* Hero Banner */
  .hotel-hero { 
    background: linear-gradient(180deg, rgba(13,23,48,0.88) 0%, rgba(13,23,48,0.72) 50%, rgba(13,23,48,0.94) 100%), 
                url('https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?auto=format&fit=crop&w=1920&q=80') center 35%/cover no-repeat; 
    color: #fff; 
    padding: 90px 0 120px; 
    text-align: center; 
    position: relative;
  }
  .hotel-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255,255,255,0.14);
    border: 1px solid rgba(255,255,255,0.22);
    padding: 6px 18px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 16px;
    backdrop-filter: blur(8px);
    color: #ffd700;
  }
  .hotel-hero h1 { font-size: 46px; font-weight: 800; margin-bottom: 14px; letter-spacing: -0.5px; text-shadow: 0 4px 20px rgba(0,0,0,0.5); }
  .hotel-hero p { font-size: 16.5px; color: #cbd5e1; max-width: 680px; margin: 0 auto 24px; line-height: 1.65; }
  
  /* Quick Location Tags */
  .quick-tags { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; margin-top: 16px; }
  .quick-tag { 
    background: rgba(255,255,255,0.12); 
    color: #fff; 
    border: 1px solid rgba(255,255,255,0.2); 
    padding: 7px 16px; 
    border-radius: 999px; 
    font-size: 13px; 
    font-weight: 600; 
    backdrop-filter: blur(6px);
    transition: all .2s ease;
  }
  .quick-tag:hover { background: #fff; color: #111317; transform: translateY(-2px); }

  /* Floating Search Card */
  .search-card-wrapper { max-width: 1140px; margin: -55px auto 36px; position: relative; z-index: 20; padding: 0 16px; }
  .search-card { 
    background: #fff; 
    border-radius: var(--radius); 
    box-shadow: 0 20px 50px rgba(13,23,48,0.14); 
    display: flex; 
    flex-wrap: wrap; 
    align-items: center;
    padding: 12px; 
    gap: 8px; 
    color: var(--ink); 
    border: 1px solid rgba(0,0,0,0.06);
  }
  .search-field { flex: 1 1 180px; display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-right: 1px solid var(--line); }
  .search-field:last-of-type { border-right: none; }
  .search-field i { color: var(--teal); font-size: 16px; flex-shrink: 0; }
  .search-field input { border: none; outline: none; font-size: 14px; font-weight: 600; width: 100%; font-family: inherit; color: var(--ink); background: transparent; }
  .search-field .lbl { font-size: 11px; font-weight: 700; color: var(--muted); display: block; margin-bottom: 2px; text-transform: uppercase; letter-spacing: 0.5px; }
  
  .search-btn { 
    background: #111317; 
    color: #fff; 
    border: none; 
    border-radius: 14px; 
    padding: 14px 34px; 
    font-weight: 700; 
    font-size: 14.5px; 
    white-space: nowrap; 
    display: flex; 
    align-items: center; 
    gap: 10px; 
    cursor: pointer; 
    transition: background .2s, transform .2s; 
  }
  .search-btn:hover { background: #2b303a; transform: translateY(-1px); }

  /* Category Curations Tabs */
  .category-tabs {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-bottom: 36px;
    flex-wrap: wrap;
  }
  .cat-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #ffffff;
    border: 1px solid var(--line);
    padding: 10px 22px;
    border-radius: 999px;
    font-size: 13.5px;
    font-weight: 700;
    color: #475569;
    box-shadow: 0 4px 14px rgba(0,0,0,0.03);
    transition: all .2s ease;
  }
  .cat-tab-btn:hover, .cat-tab-btn.active {
    background: #111317;
    color: #ffffff;
    border-color: #111317;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
  }

  /* Filter & Control Bar */
  .filter-control-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ffffff;
    border: 1px solid var(--line);
    border-radius: 16px;
    padding: 14px 20px;
    margin-bottom: 30px;
    gap: 16px;
    flex-wrap: wrap;
    box-shadow: 0 4px 16px rgba(0,0,0,0.03);
  }
  .city-pills { display: flex; gap: 6px; flex-wrap: wrap; }
  .city-pill { 
    padding: 7px 16px; 
    border-radius: 999px; 
    background: #f8fafc; 
    border: 1px solid var(--line); 
    font-size: 12.5px; 
    font-weight: 600; 
    color: #4b5262; 
    transition: all .2s ease;
  }
  .city-pill:hover, .city-pill.active { 
    background: #111317; 
    color: #fff; 
    border-color: #111317; 
  }

  .sort-select-box { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--muted); }
  .sort-select-box select {
    border: 1px solid var(--line);
    background: #fff;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #111317;
    outline: none;
    cursor: pointer;
  }

  /* Assurance Bar */
  .assurance-bar {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    background: #ffffff;
    border: 1px solid var(--line);
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 40px;
    box-shadow: var(--shadow);
  }
  .assurance-item { display: flex; align-items: center; gap: 12px; }
  .assurance-icon { 
    width: 42px; 
    height: 42px; 
    border-radius: 12px; 
    background: rgba(23,201,179,0.12); 
    color: #0c7c6d; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-size: 16px; 
    flex-shrink: 0;
  }
  .assurance-text h6 { font-size: 13.5px; font-weight: 700; margin: 0 0 2px; color: #111317; }
  .assurance-text p { font-size: 11.5px; color: var(--muted); margin: 0; }

  /* Hotel Cards Grid */
  .hotel-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 26px; margin-bottom: 50px; }
  .hotel-card { 
    border: 1px solid var(--line); 
    border-radius: var(--radius); 
    overflow: hidden; 
    background: #fff; 
    display: flex; 
    flex-direction: column; 
    transition: transform .25s ease, box-shadow .25s ease, border-color .2s; 
    position: relative;
    box-shadow: var(--shadow);
  }
  .hotel-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-hover); border-color: #cbd5e1; }
  .hotel-card .media { aspect-ratio: 16/10; overflow: hidden; position: relative; background: #e2e4e8; }
  .hotel-card .media img { width: 100%; height: 100%; object-fit: cover; transition: transform .45s ease; }
  .hotel-card:hover .media img { transform: scale(1.05); }
  
  .badge-featured {
    position: absolute;
    top: 12px;
    right: 12px;
    background: #111317;
    color: #ffd700;
    font-size: 11px;
    font-weight: 800;
    padding: 5px 12px;
    border-radius: 999px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    box-shadow: 0 4px 14px rgba(0,0,0,0.25);
    display: flex;
    align-items: center;
    gap: 5px;
    z-index: 2;
  }
  .stars-badge { 
    position: absolute; 
    top: 12px; 
    left: 12px; 
    background: rgba(0,0,0,.72); 
    color: #ffd700; 
    padding: 4px 10px; 
    border-radius: 20px; 
    font-size: 11.5px; 
    font-weight: 700;
    backdrop-filter: blur(4px); 
    z-index: 2;
  }
  .hotel-card .body { padding: 22px; flex: 1; display: flex; flex-direction: column; }
  .hotel-card h3 { font-size: 19px; font-weight: 800; margin-bottom: 6px; line-height: 1.35; color: #111317; }
  .hotel-location { color: var(--muted); font-size: 12.5px; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; }
  .hotel-card p { color: #555d6e; font-size: 13px; line-height: 1.55; margin: 0 0 16px; flex: 1; }
  
  .hotel-amenities-tags { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px; }
  .amenity-tag { font-size: 11.5px; background: #f1f3f7; color: #4b5262; padding: 4px 9px; border-radius: 6px; font-weight: 600; }

  .hotel-card .meta { display: flex; align-items: center; justify-content: space-between; border-top: 1px solid var(--line); padding-top: 16px; }
  .hotel-card .price { font-weight: 800; color: #111317; font-size: 21px; }
  .hotel-card .price small { font-size: 12px; color: var(--muted); font-weight: 500; }
  .btn-view-stay { 
    background: #f1f3f7; 
    color: #111317; 
    padding: 9px 18px; 
    border-radius: 10px; 
    font-size: 13px; 
    font-weight: 700; 
    transition: all .2s; 
  }
  .hotel-card:hover .btn-view-stay { 
    background: var(--teal); 
    color: #0d1730; 
  }

  .results-section { padding: 10px 0 60px; }
  .error-msg { background: #fdecea; color: #b3261e; border: 1px solid #f5c6c2; border-radius: 12px; padding: 14px 18px; text-align: center; margin-bottom: 24px; font-size: 14px; }

  /* Responsive Mobile Breakpoints */
  @media (max-width: 1024px) { 
    .hotel-grid { grid-template-columns: repeat(2, 1fr); } 
    .assurance-bar { grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 768px) { 
    .hotel-grid { grid-template-columns: 1fr; } 
    .assurance-bar { grid-template-columns: 1fr; }
    .search-card { flex-direction: column; align-items: stretch; padding: 16px; }
    .search-field { border-right: none; border-bottom: 1px solid var(--line); width: 100%; } 
    .search-btn { width: 100%; padding: 14px; justify-content: center; } 
    .hotel-hero h1 { font-size: 30px; }
    .filter-control-bar { flex-direction: column; align-items: flex-start; }
  }
</style>
</head>
<body>
<?php require __DIR__ . '/components/gaia-header.php'; ?>

<!-- Hero Section -->
<section class="hotel-hero">
  <div class="container">
    <div class="hotel-hero-badge"><i class="fa-solid fa-crown"></i> Exclusive Stays in Jordan</div>
    <h1><?= t('search.hotel_title', 'Find Your Sanctuary in Jordan') ?></h1>
    <p><?= t('search.hotel_sub', 'Handpicked 5-star luxury resorts, panoramic desert bubbles, and boutique retreats.') ?></p>
    
    <div class="quick-tags">
      <a href="<?= gaia_url('search-hotels.php') ?>?city=Amman" class="quick-tag">🏙️ Amman 5-Star</a>
      <a href="<?= gaia_url('search-hotels.php') ?>?city=Petra" class="quick-tag">🏛️ Petra Mountain Resorts</a>
      <a href="<?= gaia_url('search-hotels.php') ?>?city=Dead+Sea" class="quick-tag">🌊 Dead Sea Spas</a>
      <a href="<?= gaia_url('search-hotels.php') ?>?city=Wadi+Rum" class="quick-tag">🌌 Wadi Rum Stargazing</a>
      <a href="<?= gaia_url('search-hotels.php') ?>?city=Aqaba" class="quick-tag">🏖️ Aqaba Red Sea</a>
    </div>
  </div>
</section>

<!-- Floating Search Bar -->
<div class="search-card-wrapper">
  <form class="search-card" action="<?= gaia_url('search-hotels.php') ?>" method="get">
    <input type="hidden" name="search" value="1">
    <div class="search-field">
      <i class="fa-solid fa-location-dot"></i>
      <div style="width:100%">
        <span class="lbl"><?= t('search.destination', 'Destination') ?></span>
        <input type="text" name="destination" value="<?= htmlspecialchars($destination) ?>" placeholder="<?= t('search.destination_ph', 'Amman, Petra, Dead Sea...') ?>">
      </div>
    </div>
    <div class="search-field">
      <i class="fa-solid fa-calendar-check"></i>
      <div style="width:100%">
        <span class="lbl"><?= t('search.check_in', 'Check-in') ?></span>
        <input type="date" name="check_in" value="<?= htmlspecialchars($checkInDate) ?>">
      </div>
    </div>
    <div class="search-field">
      <i class="fa-solid fa-calendar-xmark"></i>
      <div style="width:100%">
        <span class="lbl"><?= t('search.check_out', 'Check-out') ?></span>
        <input type="date" name="check_out" value="<?= htmlspecialchars($checkOutDate) ?>">
      </div>
    </div>
    <div class="search-field" style="max-width:130px;">
      <i class="fa-solid fa-user-group"></i>
      <div style="width:100%">
        <span class="lbl"><?= t('search.guests', 'Guests') ?></span>
        <input type="number" name="guests" min="1" max="10" value="<?= $guestCount ?>">
      </div>
    </div>
    <div class="search-field" style="max-width:130px;">
      <i class="fa-solid fa-door-open"></i>
      <div style="width:100%">
        <span class="lbl"><?= t('search.rooms', 'Rooms') ?></span>
        <input type="number" name="rooms" min="1" max="5" value="<?= $roomsCount ?>">
      </div>
    </div>
    <button class="search-btn" type="submit"><i class="fa-solid fa-magnifying-glass"></i> <?= t('search.btn', 'Search Stays') ?></button>
  </form>
</div>

<!-- Main Content Area -->
<section class="results-section container">
  <?php if ($error): ?>
    <div class="error-msg"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Curated Collection Filter Pills -->
  <div class="category-tabs">
    <a href="<?= gaia_url('search-hotels.php') ?>" class="cat-tab-btn <?= empty($category) ? 'active' : '' ?>">
      <i class="fa-solid fa-hotel"></i> All Stays
    </a>
    <a href="<?= gaia_url('search-hotels.php') ?>?category=luxury5" class="cat-tab-btn <?= $category === 'luxury5' ? 'active' : '' ?>">
      <i class="fa-solid fa-crown"></i> 5-Star Luxury
    </a>
    <a href="<?= gaia_url('search-hotels.php') ?>?category=bubble" class="cat-tab-btn <?= $category === 'bubble' ? 'active' : '' ?>">
      <i class="fa-solid fa-campground"></i> Desert Domes & Bubbles
    </a>
    <a href="<?= gaia_url('search-hotels.php') ?>?category=spa" class="cat-tab-btn <?= $category === 'spa' ? 'active' : '' ?>">
      <i class="fa-solid fa-spa"></i> Wellness & Dead Sea Spas
    </a>
  </div>

  <!-- Assurance Perks -->
  <div class="assurance-bar">
    <div class="assurance-item">
      <div class="assurance-icon"><i class="fa-solid fa-tags"></i></div>
      <div class="assurance-text">
        <h6>Best Rate Guarantee</h6>
        <p>Transparent pricing, no hidden fees</p>
      </div>
    </div>
    <div class="assurance-item">
      <div class="assurance-icon"><i class="fa-solid fa-bolt"></i></div>
      <div class="assurance-text">
        <h6>Instant Confirmation</h6>
        <p>Immediate voucher & room hold</p>
      </div>
    </div>
    <div class="assurance-item">
      <div class="assurance-icon"><i class="fa-solid fa-headset"></i></div>
      <div class="assurance-text">
        <h6>24/7 Hotel Concierge</h6>
        <p>Direct WhatsApp support in Jordan</p>
      </div>
    </div>
    <div class="assurance-item">
      <div class="assurance-icon"><i class="fa-solid fa-shield-halved"></i></div>
      <div class="assurance-text">
        <h6>Flexible Cancellation</h6>
        <p>Easy cancellation on select rates</p>
      </div>
    </div>
  </div>

  <!-- Interactive Destination & Star Filter Controls -->
  <div class="filter-control-bar">
    <div class="city-pills">
      <a href="<?= gaia_url('search-hotels.php') ?>" class="city-pill <?= empty($cityFilter) ? 'active' : '' ?>">All Jordan</a>
      <a href="<?= gaia_url('search-hotels.php') ?>?city=Amman" class="city-pill <?= strtolower($cityFilter) === 'amman' ? 'active' : '' ?>">Amman</a>
      <a href="<?= gaia_url('search-hotels.php') ?>?city=Petra" class="city-pill <?= strtolower($cityFilter) === 'petra' ? 'active' : '' ?>">Petra</a>
      <a href="<?= gaia_url('search-hotels.php') ?>?city=Dead Sea" class="city-pill <?= strtolower($cityFilter) === 'dead sea' ? 'active' : '' ?>">Dead Sea</a>
      <a href="<?= gaia_url('search-hotels.php') ?>?city=Wadi Rum" class="city-pill <?= strtolower($cityFilter) === 'wadi rum' ? 'active' : '' ?>">Wadi Rum</a>
      <a href="<?= gaia_url('search-hotels.php') ?>?city=Aqaba" class="city-pill <?= strtolower($cityFilter) === 'aqaba' ? 'active' : '' ?>">Aqaba</a>
    </div>

    <form method="get" action="<?= gaia_url('search-hotels.php') ?>" class="sort-select-box">
      <?php if ($cityFilter): ?><input type="hidden" name="city" value="<?= htmlspecialchars($cityFilter) ?>"><?php endif; ?>
      <?php if ($category): ?><input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>"><?php endif; ?>
      <?php if ($destination): ?><input type="hidden" name="destination" value="<?= htmlspecialchars($destination) ?>"><?php endif; ?>
      <span>Sort by:</span>
      <select name="sort" onchange="this.form.submit()">
        <option value="recommended" <?= $sortBy === 'recommended' ? 'selected' : '' ?>>Top Recommended</option>
        <option value="price_asc" <?= $sortBy === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
        <option value="price_desc" <?= $sortBy === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
      </select>
    </form>
  </div>

  <!-- 1. Search Results (if user performed a search) -->
  <?php if ($isSearching && !$error): ?>
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:24px; gap:16px; flex-wrap:wrap;">
      <div>
        <h2 style="font-size:26px; font-weight:800; color:#111317;">Available Accommodations</h2>
        <div style="font-size:14px; color:var(--muted); margin-top:4px;"><?= count($searchResults) ?> <?= t('search.hotels_found', 'properties matching your itinerary') ?></div>
      </div>
      <a href="<?= gaia_url('search-hotels.php') ?>" class="city-pill">Clear Search ✕</a>
    </div>

    <?php if (empty($searchResults)): ?>
      <div style="text-align:center; padding:50px 20px; background:#fff; border-radius:18px; border:1px dashed var(--line); margin-bottom:50px;">
        <i class="fa-solid fa-bed-pulse" style="font-size:40px; color:var(--muted); margin-bottom:14px; display:inline-block;"></i>
        <h3 style="font-size:20px; margin-bottom:8px;"><?= t('search.no_results', 'No exact room matches found') ?></h3>
        <p style="color:var(--muted); font-size:14px; margin:0;"><?= t('search.no_results_sub', 'Explore our curated and suggested stays below.') ?></p>
      </div>
    <?php else: ?>
      <div class="hotel-grid">
        <?php foreach ($searchResults as $h): ?>
          <?php
            $adminCommission = (float)($h['admin_commission_percent'] ?? 0);
            $userPrice = (float)$h['min_price'] * (1 + $adminCommission / 100) * (1 + $gatewayFee / 100);
            $detailUrl = gaia_url('hotel.php') . '?slug=' . urlencode($h['slug']) . ($checkInDate ? '&check_in=' . urlencode($checkInDate) : '') . ($checkOutDate ? '&check_out=' . urlencode($checkOutDate) : '');
          ?>
          <a class="hotel-card" href="<?= $detailUrl ?>">
            <div class="media">
              <img src="<?= htmlspecialchars($h['image_url'] ?: 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=700&q=80') ?>" alt="<?= htmlspecialchars(lang_value($h, 'name')) ?>" loading="lazy">
              <?php if (!empty($h['featured'])): ?>
                <div class="badge-featured"><i class="fa-solid fa-crown"></i> Featured</div>
              <?php endif; ?>
              <?php if (!empty($h['star_rating'])): ?>
                <div class="stars-badge"><?= str_repeat('★', (int)$h['star_rating']) ?></div>
              <?php endif; ?>
            </div>
            <div class="body">
              <h3><?= htmlspecialchars(lang_value($h, 'name')) ?></h3>
              <div class="hotel-location">
                <i class="fa-solid fa-location-dot" style="color:var(--teal);"></i>
                <?= htmlspecialchars(lang_value($h, 'location') ?: lang_value($h, 'city') ?: 'Jordan') ?>
              </div>
              <div class="hotel-amenities-tags">
                <span class="amenity-tag">Free Breakfast</span>
                <span class="amenity-tag">High-Speed Wi-Fi</span>
                <span class="amenity-tag">Spa & Wellness</span>
              </div>
              <p><?= htmlspecialchars(mb_substr(lang_value($h, 'description') ?: 'Experience world-class hospitality in Jordan.', 0, 110)) ?>...</p>
              <div class="meta">
                <span class="price"><?= $currency ?><?= number_format($userPrice, 0) ?> <small><?= t('hotel.per_night', '/ night') ?></small></span>
                <span class="btn-view-stay"><?= t('hotel.view', 'View Stay') ?></span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- 2. Featured Hotels Section -->
  <?php if (!empty($featuredHotels)): ?>
    <div style="margin: 20px 0 24px;">
      <h2 style="font-size:26px; font-weight:800; color:#111317;">✨ Featured Hotels & Luxury Resorts</h2>
      <div style="font-size:14px; color:var(--muted); margin-top:4px;">Handpicked 5-star properties, panoramic bubble camps, and heritage retreats</div>
    </div>

    <div class="hotel-grid">
      <?php foreach ($featuredHotels as $h): ?>
        <?php
          $adminCommission = (float)($h['admin_commission_percent'] ?? 0);
          $userPrice = (float)$h['min_price'] * (1 + $adminCommission / 100) * (1 + $gatewayFee / 100);
          $detailUrl = gaia_url('hotel.php') . '?slug=' . urlencode($h['slug']);
        ?>
        <a class="hotel-card" href="<?= $detailUrl ?>">
          <div class="media">
            <img src="<?= htmlspecialchars($h['image_url'] ?: 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?auto=format&fit=crop&w=700&q=80') ?>" alt="<?= htmlspecialchars(lang_value($h, 'name')) ?>" loading="lazy">
            <div class="badge-featured"><i class="fa-solid fa-crown"></i> Featured Stay</div>
            <?php if (!empty($h['star_rating'])): ?>
              <div class="stars-badge"><?= str_repeat('★', (int)$h['star_rating']) ?></div>
            <?php endif; ?>
          </div>
          <div class="body">
            <h3><?= htmlspecialchars(lang_value($h, 'name')) ?></h3>
            <div class="hotel-location">
              <i class="fa-solid fa-location-dot" style="color:var(--teal);"></i>
              <?= htmlspecialchars(lang_value($h, 'location') ?: lang_value($h, 'city') ?: 'Jordan') ?>
            </div>
            <div class="hotel-amenities-tags">
              <span class="amenity-tag">5-Star Concierge</span>
              <span class="amenity-tag">Panoramic Views</span>
              <span class="amenity-tag">Luxury Dining</span>
            </div>
            <p><?= htmlspecialchars(mb_substr(lang_value($h, 'description') ?: 'Exclusive accommodation offering unparalleled comfort.', 0, 110)) ?>...</p>
            <div class="meta">
              <span class="price"><?= $currency ?><?= number_format($userPrice, 0) ?> <small><?= t('hotel.per_night', '/ night') ?></small></span>
              <span class="btn-view-stay"><?= t('hotel.view', 'View Details') ?></span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- 3. Curated Stays in Jordan -->
  <?php if (!empty($suggestedHotels)): ?>
    <div style="margin: 30px 0 24px;">
      <h2 style="font-size:26px; font-weight:800; color:#111317;">🏨 <?= t('hotel.all_stays', 'Curated Accommodations in Jordan') ?></h2>
      <div style="font-size:14px; color:var(--muted); margin-top:4px;">Stays across Amman, Dead Sea, Petra, Wadi Rum & Aqaba (<?= count($suggestedHotels) ?> available)</div>
    </div>

    <div class="hotel-grid">
      <?php foreach ($suggestedHotels as $h): ?>
        <?php
          $adminCommission = (float)($h['admin_commission_percent'] ?? 0);
          $userPrice = (float)$h['min_price'] * (1 + $adminCommission / 100) * (1 + $gatewayFee / 100);
          $detailUrl = gaia_url('hotel.php') . '?slug=' . urlencode($h['slug']);
        ?>
        <a class="hotel-card" href="<?= $detailUrl ?>">
          <div class="media">
            <img src="<?= htmlspecialchars($h['image_url'] ?: 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=700&q=80') ?>" alt="<?= htmlspecialchars(lang_value($h, 'name')) ?>" loading="lazy">
            <?php if (!empty($h['featured'])): ?>
              <div class="badge-featured"><i class="fa-solid fa-crown"></i> Featured</div>
            <?php endif; ?>
            <?php if (!empty($h['star_rating'])): ?>
              <div class="stars-badge"><?= str_repeat('★', (int)$h['star_rating']) ?></div>
            <?php endif; ?>
          </div>
          <div class="body">
            <h3><?= htmlspecialchars(lang_value($h, 'name')) ?></h3>
            <div class="hotel-location">
              <i class="fa-solid fa-location-dot" style="color:var(--teal);"></i>
              <?= htmlspecialchars(lang_value($h, 'location') ?: lang_value($h, 'city') ?: 'Jordan') ?>
            </div>
            <div class="hotel-amenities-tags">
              <span class="amenity-tag">Verified Quality</span>
              <span class="amenity-tag">Free Cancellation</span>
            </div>
            <p><?= htmlspecialchars(mb_substr(lang_value($h, 'description') ?: 'Authentic stay with exceptional hospitality.', 0, 110)) ?>...</p>
            <div class="meta">
              <span class="price"><?= $currency ?><?= number_format($userPrice, 0) ?> <small><?= t('hotel.per_night', '/ night') ?></small></span>
              <span class="btn-view-stay"><?= t('hotel.view', 'View Details') ?></span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</section>

<?php require __DIR__ . '/components/gaia-footer.php'; ?>
<script src="/assets/gaia.js"></script>
</body>
</html>

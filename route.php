<?php
/**
 * route.php — Route + car class selection page (fully dynamic).
 * Reads a route_id (or searches by origin/destination) and shows
 * available car classes with prices computed from the DB.
 *
 * All text, SEO, country, car features and trust scores are
 * loaded from MySQL via bootstrap.php / gaia-config.php.
 */
require_once __DIR__ . '/components/bootstrap.php';
require_once __DIR__ . '/components/gaia-config.php';

// Shared header/footer variables
$gaia_base          = '';
$gaia_active        = 'transfers';
$gaia_header_style  = 'solid';

$routeId     = (int)($_GET['route_id'] ?? 0);
$origin      = trim($_GET['origin'] ?? '');
$destination = trim($_GET['destination'] ?? '');
$date        = trim($_GET['date'] ?? '');
$passengers  = (int)($_GET['passengers'] ?? 2);

$route = null;
$carClasses = [];
$reviews = [];
$error = null;

try {
    $pdo = getPDO();

    // Load the route by id, or by origin/destination match
    if ($routeId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM routes WHERE id = ? AND is_active = 1');
        $stmt->execute([$routeId]);
        $route = $stmt->fetch();
    } elseif ($origin !== '' && $destination !== '') {
        $stmt = $pdo->prepare(
            'SELECT * FROM routes WHERE is_active = 1
             AND origin LIKE :origin AND destination LIKE :dest
             ORDER BY base_price ASC LIMIT 1'
        );
        $stmt->execute([
            ':origin' => '%' . $origin . '%',
            ':dest'   => '%' . $destination . '%',
        ]);
        $route = $stmt->fetch();
    }

    if (!$route) {
        // Fall back to first active route
        $route = $pdo->query('SELECT * FROM routes WHERE is_active = 1 ORDER BY base_price ASC LIMIT 1')->fetch();
    }

    // Car classes that can hold the requested passengers
    $stmt = $pdo->prepare('SELECT * FROM car_classes WHERE is_active = 1 AND passenger_capacity >= ? ORDER BY price_multiplier ASC');
    $stmt->execute([max(1, $passengers)]);
    $carClasses = $stmt->fetchAll();

    // Reviews (for the route page)
    $reviews = $pdo->query('SELECT * FROM reviews WHERE is_active = 1 ORDER BY id ASC LIMIT 4')->fetchAll();

    if (!$route) {
        $error = t('transfers.no_routes', 'No routes available.');
    }
} catch (PDOException $e) {
    $error = 'Database unavailable: ' . htmlspecialchars($e->getMessage());
}

// DB-driven trust scores for the review box
$trustScores = gaia_trust_scores();

// DB-driven default country (settings.default_country)
$defaultCountry = site_setting('default_country', 'Italy');

// DB-driven car features (translated)
$carFeatures = [
    t('transfers.feature_1', 'One booking'),
    t('transfers.feature_2', 'Easy coordination'),
    t('transfers.feature_3', 'Lower cost per passenger'),
];

// Average rating from reviews
$avgRating = 0;
if ($reviews) {
    $avgRating = round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1);
}

// Helper: compute price for a car class on this route
function classPrice($route, $car) {
    return round((float)$route['base_price'] * (float)$car['price_multiplier'], 2);
}

// Helper: build a car SVG in a given color
function carSVG($color) {
    return '<svg viewBox="0 0 140 90" xmlns="http://www.w3.org/2000/svg">
        <ellipse cx="70" cy="78" rx="55" ry="6" fill="#00000012"/>
        <rect x="14" y="42" width="112" height="26" rx="9" fill="' . $color . '"/>
        <path d="M32 42 L44 20 H96 L108 42 Z" fill="' . $color . '"/>
        <rect x="48" y="24" width="44" height="18" rx="3" fill="#dfe6f2"/>
        <circle cx="38" cy="68" r="12" fill="#26262f"/><circle cx="102" cy="68" r="12" fill="#26262f"/>
        <circle cx="38" cy="68" r="5" fill="#d5d8e2"/><circle cx="102" cy="68" r="5" fill="#d5d8e2"/>
    </svg>';
}

$colors = ['#e7e9ee', '#c9ccd6', '#9aa0af', '#3a3d4d', '#26262f', '#5e6577'];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags('transfers_route', htmlspecialchars(($route['origin'] ?? '') . ' → ' . ($route['destination'] ?? '')) . ' — GAIA TOURS &amp; TRAVEL') ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<link rel="stylesheet" href="/assets/styles.css">
<style>
:root{--navy:#1b2a4a;--navy-2:#243761;--teal:#1f6f8f;--teal-dark:#175a75;--ink:#1c1e26;--muted:#6b7280;--line:#e8e6e0;--bg-soft:#f4efe6;--white:#fff;--radius:14px;--shadow:0 10px 30px rgba(27,42,74,0.08);}
  *{box-sizing:border-box;}body{margin:0;font-family:'Inter',sans-serif;color:var(--ink);background:var(--bg-soft);-webkit-font-smoothing:antialiased;}
  h1,h2,h3,h4{font-family:'Playfair Display',serif;margin:0;}a{text-decoration:none;color:inherit;}ul{list-style:none;margin:0;padding:0;}img{max-width:100%;display:block;}button{font-family:inherit;cursor:pointer;}
  .container{max-width:1280px;margin:0 auto;padding:0 32px;}
  header.site-header{background:var(--navy);padding:16px 0;}
  .header-inner{display:flex;align-items:center;justify-content:space-between;}
  .logo{display:flex;align-items:center;gap:8px;font-family:'Poppins';font-weight:800;font-size:20px;color:#fff;}.logo .dot{color:var(--teal);}
  nav.main-nav ul{display:flex;gap:34px;}nav.main-nav a{color:#fff;font-size:14.5px;font-weight:500;opacity:.92;}nav.main-nav a:hover{opacity:1;color:var(--teal);}
  .header-right{display:flex;align-items:center;gap:22px;color:#fff;}.header-right .icon-btn{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.35);font-size:13px;}
  .currency{font-size:14px;font-weight:600;}
  .page-head{background:#fff;padding:36px 0;}.page-head h1{font-size:26px;font-weight:700;max-width:640px;line-height:1.3;}
  .route-bar{background:#fff;border-top:1px solid var(--line);padding:20px 0;display:flex;align-items:center;flex-wrap:wrap;gap:26px;}
  .route-bar .pt{display:flex;flex-direction:column;gap:2px;}.route-bar .pt .name{font-weight:700;font-size:14px;}.route-bar .pt .sub{font-size:12px;color:var(--muted);}
  .route-bar i.plane{color:var(--muted);}.route-bar .meta{display:flex;align-items:center;gap:22px;margin-left:auto;font-size:13px;color:var(--muted);}
  .route-bar .meta span{display:flex;align-items:center;gap:6px;}.route-bar .edit-btn{width:34px;height:34px;border-radius:8px;border:1px solid var(--line);background:#fff;display:flex;align-items:center;justify-content:center;color:var(--ink);}
  .section{padding:44px 0;}.section h2{font-size:26px;font-weight:700;}
  .tabs{display:flex;gap:8px;margin:20px 0 26px;flex-wrap:wrap;}.tab{padding:8px 20px;border-radius:24px;font-size:14px;font-weight:600;color:var(--muted);border:1px solid var(--line);background:#fff;}.tab.active{background:var(--navy);color:#fff;border-color:var(--navy);}
  .class-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;}
  .class-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:22px;position:relative;transition:.2s;display:flex;flex-direction:column;}
  .class-card.best{border-color:var(--teal);box-shadow:0 0 0 2px rgba(23,201,179,.25);}
  .best-tag{position:absolute;top:14px;left:22px;color:var(--teal-dark);font-weight:700;font-size:11.5px;}
  .class-card .car-img{height:70px;display:flex;align-items:center;justify-content:center;margin:26px 0 14px;}.class-card .car-img svg{height:64px;}
  .class-card h5{font-size:16px;font-weight:700;margin-bottom:4px;}.class-card .tagline{font-size:12.5px;color:var(--muted);margin-bottom:14px;min-height:32px;}
  .class-card .feat{display:flex;flex-direction:column;gap:8px;margin-bottom:16px;}.class-card .feat span{display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--ink);}.class-card .feat i{color:var(--teal);font-size:11px;}
  .class-card .meta-row{display:flex;align-items:center;gap:16px;font-size:12px;color:var(--muted);margin-bottom:16px;}.class-card .meta-row span{display:flex;align-items:center;gap:5px;}
  .price-btn{margin-top:auto;width:100%;background:var(--teal);border:none;border-radius:8px;padding:12px;color:#0d1730;font-weight:700;font-size:15px;display:flex;align-items:center;justify-content:center;transition:.2s;}
  .price-btn:hover{background:var(--teal-dark);}
  .reviews-box{background:var(--navy);border-radius:22px;padding:50px 40px;color:#fff;}.reviews-head{text-align:center;}.reviews-head h2{font-size:30px;}
  .rating-row{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:16px;font-size:20px;font-weight:700;}.rating-row i{color:#ffb400;}
  .reviews-head .from{font-size:12px;color:#b7b3d4;font-weight:400;margin-left:4px;}.reviews-head p{color:#c7c3e2;max-width:520px;margin:14px auto 0;font-size:14px;}
  .review-nav{display:flex;justify-content:center;gap:10px;margin-top:20px;}.review-nav button{width:34px;height:34px;border-radius:50%;border:1px solid rgba(255,255,255,.25);background:transparent;color:#fff;}
  .review-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:30px;}.review-card{background:#fff;color:var(--ink);border-radius:12px;padding:18px;}
  .review-card .stars{color:#ffb400;font-size:12px;margin-bottom:4px;}.review-card h6{font-size:14px;font-weight:700;}.review-card .place{font-size:11.5px;color:var(--muted);margin:4px 0 10px;}.review-card p{font-size:12px;color:#42405a;line-height:1.55;margin:0 0 8px;}
  .trust-row{display:flex;justify-content:center;gap:60px;margin-top:34px;flex-wrap:wrap;}.trust-item{display:flex;align-items:center;gap:10px;}.trust-item .tscore{font-size:20px;font-weight:800;}.trust-item .tsub{font-size:11px;color:#b7b3d4;}
  .route-info-card{background:#fff;border:1px solid var(--line);border-radius:16px;max-width:520px;overflow:hidden;margin-top:30px;}
  .route-info-row{display:flex;align-items:center;gap:16px;padding:20px 22px;border-bottom:1px solid var(--line);}.route-info-row:last-child{border-bottom:none;}
  .route-info-row i{color:var(--teal);font-size:17px;width:20px;}.route-info-row .lbl{font-size:12.5px;color:var(--muted);margin-bottom:2px;}.route-info-row .val{font-size:14.5px;font-weight:700;}
  .db-error{background:#fdecea;color:#b3261e;border:1px solid #f5c6c2;border-radius:10px;padding:14px 18px;font-size:13px;}
  footer{background:var(--navy);color:#cfcbe8;padding:60px 0 24px;margin-top:20px;}.footer-top{display:flex;justify-content:space-between;gap:40px;flex-wrap:wrap;}
  .footer-logo{font-family:'Poppins';font-weight:800;font-size:20px;color:#fff;margin-bottom:16px;display:block;}.footer-cols{display:flex;gap:60px;flex-wrap:wrap;}
  .footer-col h6{color:#fff;font-size:13.5px;margin-bottom:16px;}.footer-col li{margin-bottom:10px;font-size:13px;}.footer-col a{display:flex;align-items:center;gap:8px;color:#b8b4d8;}.footer-col a:hover{color:var(--teal);}
  .footer-mid{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;border-top:1px solid rgba(255,255,255,.1);border-bottom:1px solid rgba(255,255,255,.1);padding:24px 0;margin:36px 0;}.pay-icons{display:flex;gap:10px;}.pay-icons span{background:#fff;border-radius:6px;padding:6px 10px;color:var(--navy);font-size:11px;font-weight:700;}
  .footer-final{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;font-size:11.5px;color:#8a86ab;margin-top:20px;}.social-row{display:flex;gap:14px;}.social-row a{width:34px;height:34px;border-radius:50%;border:1px solid rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;}
  .app-badges{display:flex;gap:10px;}.app-badges span{display:flex;align-items:center;gap:6px;background:#100c28;border:1px solid rgba(255,255,255,.15);border-radius:8px;padding:7px 12px;font-size:11px;color:#fff;}
  @media (max-width:1100px){.class-grid{grid-template-columns:1fr 1fr;}.review-grid{grid-template-columns:1fr 1fr;}nav.main-nav{display:none;}}
  @media (max-width:680px){.class-grid{grid-template-columns:1fr;}.review-grid{grid-template-columns:1fr;}.route-bar .meta{margin-left:0;}}
</style>
</head>
<body>

<?php require __DIR__ . '/components/gaia-header.php'; ?>

<?php if ($error): ?>
  <div class="container" style="padding-top:30px;"><div class="db-error"><?= $error ?></div></div>
<?php endif; ?>

<?php if ($route): ?>
<div class="page-head">
  <div class="container"><h1><?= t_fmt('transfers.page_title_pattern', ['origin' => $route['origin'], 'destination' => $route['destination']], gaia_current_lang()) ?></h1></div>
</div>

<div class="route-bar">
  <div class="container" style="display:flex;align-items:center;flex-wrap:wrap;gap:26px;">
    <div class="pt"><div class="name"><i class="fa-solid fa-plane-departure" style="color:var(--muted);margin-right:6px;"></i><?= htmlspecialchars($route['origin']) ?></div><div class="sub"><?= t_fmt('transfers.country_suffix', ['place' => $route['origin'], 'country' => $defaultCountry], gaia_current_lang()) ?></div></div>
    <i class="fa-solid fa-chevron-right" style="color:var(--line)"></i>
    <div class="pt"><div class="name"><i class="fa-solid fa-location-dot" style="color:var(--muted);margin-right:6px;"></i><?= htmlspecialchars($route['destination']) ?></div><div class="sub"><?= htmlspecialchars($route['destination']) ?></div></div>
    <div class="meta">
      <span><i class="fa-regular fa-calendar"></i> <?= $date !== '' ? htmlspecialchars($date) : t('transfers.select_date', 'Select date') ?></span>
      <span><i class="fa-regular fa-clock"></i> <?= t_fmt('transfers.min', ['min' => (int)$route['travel_time_min']], gaia_current_lang()) ?></span>
      <span><i class="fa-solid fa-user"></i> <?= max(1, $passengers) ?></span>
      <a class="edit-btn" href="<?= gaia_url('index.php') ?>"><i class="fa-solid fa-sliders"></i></a>
    </div>
  </div>
</div>

<!-- CAR CLASSES -->
<section class="section">
  <div class="container">
    <h2><?= t('transfers.choose_class', 'Choose a Car class') ?></h2>
    <div class="class-grid" id="classGrid">
      <?php foreach ($carClasses as $i => $car): ?>
        <?php
          $price = classPrice($route, $car);
          $color = $colors[$i % count($colors)];
          $isBest = ($price == 0) || ($i === 0);
        ?>
        <div class="class-card <?= $isBest ? 'best' : '' ?>">
          <?php if ($isBest): ?><div class="best-tag"><?= t('transfers.best_choice', 'Best Choice') ?></div><?php endif; ?>
          <div class="car-img"><?= carSVG($color) ?></div>
          <h5><?= htmlspecialchars($car['name']) ?></h5>
          <div class="tagline"><?= t_fmt('transfers.passenger_bags', ['passengers' => (int)$car['passenger_capacity'], 'bags' => (int)$car['luggage_capacity']], gaia_current_lang()) ?></div>
          <div class="feat">
            <?php foreach ($carFeatures as $feat): ?>
              <span><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($feat) ?></span>
            <?php endforeach; ?>
          </div>
          <div class="meta-row">
            <span><i class="fa-solid fa-user"></i> <?= (int)$car['passenger_capacity'] ?></span>
            <span><i class="fa-solid fa-suitcase-rolling"></i> <?= (int)$car['luggage_capacity'] ?></span>
          </div>
          <a class="price-btn" href="<?= gaia_url('checkout.php') ?>?route_id=<?= (int)$route['id'] ?>&car_class_id=<?= (int)$car['id'] ?>&date=<?= urlencode($date) ?>&passengers=<?= max(1,$passengers) ?>">$<?= number_format($price, 2) ?></a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- REVIEWS -->
<section class="section">
  <div class="container">
    <div class="reviews-box">
      <div class="reviews-head"><h2><?= t('home.section.reviews_title', 'Customer Reviews') ?></h2><div class="rating-row"><i class="fa-solid fa-star"></i> <?= $avgRating ?> <span class="from"><?= str_replace('{count}', count($reviews), t('home.reviews_from')) ?></span></div><p><?= t('home.section.reviews_sub', 'See why our customers delighted about their experiences. Discover how we make every trip truly special!') ?></p></div>
      <div class="review-nav"><button><i class="fa-solid fa-chevron-left"></i></button><button><i class="fa-solid fa-chevron-right"></i></button></div>
      <div class="review-grid">
        <?php foreach ($reviews as $r): ?>
          <div class="review-card"><div class="stars"><?= str_repeat('★', (int)$r['rating']) ?></div><h6><?= htmlspecialchars($r['reviewer']) ?></h6><div class="place"><?= htmlspecialchars($r['place']) ?> · <?= htmlspecialchars($r['date_label']) ?></div><p><?= htmlspecialchars($r['text']) ?></p></div>
        <?php endforeach; ?>
      </div>
      <div class="trust-row">
        <?php foreach ($trustScores as $ts): ?>
          <div class="trust-item"><i class="<?= htmlspecialchars($ts['icon']) ?>" style="font-size:26px;color:<?= htmlspecialchars($ts['color'] ?? '#34e0a1') ?>"></i><div><div class="tscore"><?= htmlspecialchars($ts['score']) ?></div><div class="tsub"><?= htmlspecialchars($ts['count_label']) ?></div></div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ROUTE INFO -->
<section class="section">
  <div class="container">
    <h2 class="section-title" style="text-align:left;"><?= t('transfers.route_info', 'Route information') ?></h2>
    <div class="route-info-card">
      <div class="route-info-row"><i class="fa-solid fa-route"></i><div><div class="lbl"><?= t('transfers.label_route', 'Route') ?></div><div class="val"><?= htmlspecialchars($route['origin']) ?> → <?= htmlspecialchars($route['destination']) ?></div></div></div>
      <div class="route-info-row"><i class="fa-solid fa-arrows-left-right"></i><div><div class="lbl"><?= t('transfers.label_distance', 'Distance') ?></div><div class="val"><?= t_fmt('transfers.distance_km', ['distance' => (float)$route['distance_km']], gaia_current_lang()) ?></div></div></div>
      <div class="route-info-row"><i class="fa-regular fa-clock"></i><div><div class="lbl"><?= t('transfers.label_travel_time', 'Travel Time') ?></div><div class="val"><?= t_fmt('transfers.min', ['min' => (int)$route['travel_time_min']], gaia_current_lang()) ?></div></div></div>
      <div class="route-info-row"><i class="fa-regular fa-credit-card"></i><div><div class="lbl"><?= t('transfers.label_price', 'Price') ?></div><div class="val"><?= t_fmt('transfers.price_from', ['price' => number_format((float)$route['base_price'], 2)], gaia_current_lang()) ?></div></div></div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- CROSS-SELL: TOURS -->
<?php if ($route): ?>
<section class="container"><?= gaia_cross_sell('tours', $gaia_base) ?></section>
<?php endif; ?>

<?php require __DIR__ . '/components/gaia-footer.php'; ?>

<script src="assets/gaia.js"></script>
</body>
</html>

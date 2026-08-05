<?php
/**
 * index.php — Transfers home page (fully dynamic from MySQL).
 * Uses the unified GAIA TOURS & TRAVEL header/footer.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/components/gaia-config.php';

// Shared header/footer variables
$gaia_base          = '';
$gaia_active        = 'transfers';
$gaia_header_style  = 'solid';

try {
    $pdo = getPDO();

    // Car classes
    $carClasses = $pdo->query('SELECT * FROM car_classes WHERE is_active = 1 ORDER BY price_multiplier ASC')->fetchAll();

    // Services
    $services = $pdo->query('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();

    // Reviews
    $reviews = $pdo->query('SELECT * FROM reviews WHERE is_active = 1 ORDER BY id ASC LIMIT 6')->fetchAll();

    // FAQ
    $faqRows = $pdo->query('SELECT * FROM faq WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();

    // Partners
    $partners = $pdo->query('SELECT * FROM partners WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();

    // DB-driven content blocks
    $banner     = gaia_first_banner('home');
    $highlights = gaia_highlights('expect');
    $advantages = gaia_highlights('advantages');
    $nightCards = gaia_night_offerings('card');
    $homeEvents = gaia_events('home');
    $trustScores = gaia_trust_scores();
    $awards      = gaia_awards();
    $socials     = gaia_social_links();

    // Precompute average rating
    $avgRating = 4.6;
    if ($reviews) {
        $sum = array_sum(array_column($reviews, 'rating'));
        $avgRating = round($sum / count($reviews), 1);
    }
} catch (PDOException $e) {
    $carClasses = $services = $reviews = $faqRows = $partners = [];
    $banner = $highlights = $advantages = $nightCards = $homeEvents = $trustScores = $awards = $socials = [];
    $avgRating = 4.6;
    $dbError = 'Database unavailable: ' . htmlspecialchars($e->getMessage());
}
// SEO + translated page variables
$pageTitle    = seo_title('home', 'GAIA TOURS &amp; TRAVEL — Airport Transfers &amp; Car Rentals');
$pageDesc     = seo_description('home');
$bannerTitle  = $banner ? $banner['title'] : t('home.hero_title', 'Airport transfers.<br>Simpler than ever.');
$heroImage    = $banner && $banner['image_url'] ? $banner['image_url'] : gaia_media_url('hero_transfers', 'https://images.pexels.com/photos/8872883/pexels-photo-8872883.jpeg?auto=compress&cs=tinysrgb&w=1920');
$bannerStats  = [];
if ($banner && $banner['stats_json']) {
    $decoded = json_decode($banner['stats_json'], true);
    if (is_array($decoded)) {
        $bannerStats = $decoded;
    }
}
$heroStats = !empty($bannerStats)
    ? array_map(fn($s) => ['icon' => $s['icon'] ?? 'fa-solid fa-check', 'value' => $s['value'] ?? '', 'label' => $s['label'] ?? ''], $bannerStats)
    : [
        ['icon' => 'fa-solid fa-car-side', 'value' => '1 400 000+', 'label' => t('home.stat_rides', 'Completed rides')],
        ['icon' => 'fa-solid fa-earth-americas', 'value' => '100+', 'label' => t('home.stat_countries', 'Countries served')],
    ];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags('home', $pageTitle) ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<style>
:root{
    --navy:#1b2a4a; --navy-2:#243761; --teal:#1f6f8f; --teal-dark:#175a75;
    --ink:#1c1e26; --muted:#6b7280; --line:#e8e6e0; --bg-soft:#f4efe6; --white:#ffffff;
    --radius:14px; --shadow:0 10px 30px rgba(27,42,74,0.08);
  }
  *{box-sizing:border-box;}
  html{scroll-behavior:smooth;}
  body{margin:0;font-family:'Inter',sans-serif;color:var(--ink);background:var(--white);-webkit-font-smoothing:antialiased;}
  h1,h2,h3,h4{font-family:'Playfair Display',serif;margin:0;}
  a{text-decoration:none;color:inherit;}
  ul{list-style:none;margin:0;padding:0;}
  img{max-width:100%;display:block;}
  button{font-family:inherit;cursor:pointer;}
  .container{max-width:1280px;margin:0 auto;padding:0 32px;}
  .eyebrow-divider{width:56px;height:4px;background:var(--teal);border-radius:4px;margin:14px auto 0;}
  .section-title{text-align:center;font-size:34px;font-weight:700;}
  .section-sub{text-align:center;color:var(--muted);max-width:520px;margin:14px auto 0;font-size:15px;line-height:1.6;}

  .hero{position:relative;min-height:640px;background:linear-gradient(180deg, rgba(20,15,45,.55) 0%, rgba(20,15,45,.35) 40%, rgba(20,15,45,.85) 100%),url('<?= htmlspecialchars($heroImage) ?>') center 25%/cover no-repeat;padding-bottom:70px;}
  .hero-inner{position:relative;padding-top:140px;max-width:620px;}
  .hero-inner h1{color:#fff;font-size:46px;line-height:1.15;font-weight:700;text-shadow:0 2px 18px rgba(0,0,0,.25);}
  .hero-stats{display:flex;gap:34px;margin-top:22px;}
  .hero-stats .stat{display:flex;align-items:center;gap:9px;color:#fff;font-size:14.5px;font-weight:500;}
  .hero-stats .stat i{background:var(--teal);color:#12123a;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;}
  .hero-stats b{font-weight:800;font-size:15px;}

  .search-card{margin-top:34px;background:#fff;border-radius:16px;box-shadow:0 20px 45px rgba(0,0,0,.25);display:flex;flex-wrap:wrap;padding:10px;gap:6px;max-width:900px;}
  .search-field{flex:1 1 200px;display:flex;align-items:center;gap:10px;padding:12px 14px;border-right:1px solid var(--line);min-width:160px;}
  .search-field:last-of-type{border-right:none;}
  .search-field i{color:var(--teal);font-size:15px;}
  .search-field input{border:none;outline:none;font-size:14px;width:100%;font-family:inherit;color:var(--ink);}
  .search-field .lbl{font-size:11px;color:var(--muted);display:block;margin-bottom:2px;}
  .pax-control{display:flex;align-items:center;gap:10px;}
  .pax-control button{width:26px;height:26px;border-radius:50%;border:1px solid var(--line);background:#fff;color:var(--ink);font-weight:700;}
  .search-btn{background:var(--teal);color:#0d1730;border:none;border-radius:10px;padding:0 30px;font-weight:700;font-size:15px;white-space:nowrap;display:flex;align-items:center;gap:8px;transition:background .2s;}
  .search-btn:hover{background:var(--teal-dark);}

  .cookie-toast{position:absolute;right:32px;bottom:-42px;background:#1c1738;color:#fff;padding:14px 18px;border-radius:12px;display:flex;align-items:center;gap:14px;max-width:340px;font-size:12.5px;box-shadow:var(--shadow);}
  .cookie-toast a{color:var(--teal);text-decoration:underline;}
  .cookie-toast button{background:var(--teal);border:none;color:#0d1730;font-weight:700;border-radius:6px;padding:8px 16px;font-size:13px;}

  .section{padding:100px 0 70px;}
  .section.tight{padding-top:70px;}
  .expect-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;margin-top:44px;}
  .expect-card{position:relative;border-radius:var(--radius);overflow:hidden;height:340px;box-shadow:var(--shadow);}
  .expect-card img{width:100%;height:100%;object-fit:cover;}
  .expect-overlay{position:absolute;left:0;right:0;bottom:0;background:linear-gradient(180deg,transparent, rgba(10,8,25,.9) 70%);padding:60px 20px 20px;color:#fff;}
  .expect-overlay .pin{width:26px;height:26px;border-radius:50%;background:var(--teal);color:#0d1730;display:flex;align-items:center;justify-content:center;font-size:11px;margin-bottom:10px;}
  .expect-overlay h4{font-size:17px;margin-bottom:6px;}
  .expect-overlay p{font-size:12.5px;color:#d8d6e8;line-height:1.5;margin:0;}
  .expand-btn{position:absolute;top:14px;right:14px;width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,.85);display:flex;align-items:center;justify-content:center;font-size:12px;color:var(--ink);}

  .car-scroll{display:flex;gap:16px;overflow-x:auto;padding-bottom:14px;scroll-snap-type:x proximity;}
  .car-scroll::-webkit-scrollbar{height:6px;}
  .car-scroll::-webkit-scrollbar-thumb{background:var(--line);border-radius:6px;}
  .car-card{flex:0 0 168px;scroll-snap-align:start;background:var(--bg-soft);border-radius:12px;padding:16px 14px;text-align:left;border:1px solid var(--line);}
  .car-card .car-img{height:64px;display:flex;align-items:center;justify-content:center;margin-bottom:10px;}
  .car-card .car-img svg{width:100%;height:56px;}
  .car-card h5{font-size:14.5px;font-weight:700;margin-bottom:6px;}
  .car-card .meta{display:flex;gap:12px;font-size:11.5px;color:var(--muted);margin-bottom:8px;}
  .car-card .meta span{display:flex;align-items:center;gap:4px;}
  .car-card .models{font-size:11px;color:var(--muted);line-height:1.5;}

  .services-head{display:flex;align-items:center;justify-content:space-between;}
  .services-head h2{font-size:30px;}
  .arrow-pair{display:flex;gap:8px;}
  .arrow-pair button{width:36px;height:36px;border-radius:50%;border:1px solid var(--line);background:#fff;color:var(--ink);}
  .services-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-top:30px;}
  .service-card{display:flex;align-items:center;gap:16px;background:var(--bg-soft);border:1px solid var(--line);border-radius:12px;padding:20px;}
  .service-card .ic{width:52px;height:52px;border-radius:10px;background:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;color:var(--teal);flex:none;}
  .service-card h5{font-size:15px;margin-bottom:4px;}
  .service-card p{font-size:12px;color:var(--muted);margin:0;line-height:1.5;}

  .reviews-box{background:var(--navy);border-radius:22px;padding:50px 40px;color:#fff;margin-top:20px;}
  .reviews-head{text-align:center;}
  .reviews-head h2{font-size:30px;}
  .rating-row{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:16px;font-size:20px;font-weight:700;}
  .rating-row i{color:#ffb400;}
  .reviews-head .from{font-size:12px;color:#b7b3d4;font-weight:400;margin-left:4px;}
  .reviews-head p{color:#c7c3e2;max-width:520px;margin:14px auto 0;font-size:14px;}
  .review-nav{display:flex;justify-content:center;gap:10px;margin-top:20px;}
  .review-nav button{width:34px;height:34px;border-radius:50%;border:1px solid rgba(255,255,255,.25);background:transparent;color:#fff;}
  .review-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:30px;}
  .review-card{background:#fff;color:var(--ink);border-radius:12px;padding:18px;}
  .review-card .stars{color:#ffb400;font-size:12px;margin-bottom:4px;}
  .review-card h6{font-size:14px;font-weight:700;display:flex;justify-content:space-between;}
  .review-card h6 span{font-weight:400;color:var(--muted);font-size:11px;}
  .review-card .place{font-size:11.5px;color:var(--muted);margin:4px 0 10px;}
  .review-card p{font-size:12px;color:#42405a;line-height:1.55;margin:0 0 8px;}
  .review-card .read-more{font-size:12px;font-weight:700;color:var(--teal-dark);}
  .trust-row{display:flex;justify-content:center;gap:60px;margin-top:34px;flex-wrap:wrap;}
  .trust-item{display:flex;align-items:center;gap:10px;}
  .trust-item .tscore{font-size:20px;font-weight:800;}
  .trust-item .tsub{font-size:11px;color:#b7b3d4;}
  .awards-row{display:flex;justify-content:center;gap:16px;margin-top:26px;flex-wrap:wrap;}
  .award-pill{display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.15);border-radius:30px;padding:10px 18px;font-size:12.5px;color:#e6e4f4;}
  .award-pill .badge{width:30px;height:30px;border-radius:50%;background:var(--teal);display:flex;align-items:center;justify-content:center;color:#0d1730;font-size:13px;flex:none;}

  .partners{background:var(--bg-soft);padding:70px 0;}
  .partners-row{display:flex;justify-content:center;align-items:center;gap:60px;margin-top:40px;flex-wrap:wrap;filter:grayscale(1);opacity:.6;font-family:'Poppins';font-weight:700;font-size:22px;}

  .faq-list{max-width:820px;margin:0 auto;}
  .faq-item{border-bottom:1px solid var(--line);}
  .faq-q{display:flex;align-items:center;justify-content:space-between;padding:20px 4px;font-size:15px;font-weight:600;cursor:pointer;}
  .faq-q i{transition:transform .25s;color:var(--muted);}
  .faq-item.open .faq-q i{transform:rotate(45deg);color:var(--teal);}
  .faq-a{max-height:0;overflow:hidden;transition:max-height .3s ease;font-size:13.5px;color:var(--muted);line-height:1.7;}
  .faq-item.open .faq-a{max-height:200px;padding-bottom:18px;}

  .newsletter{background:linear-gradient(120deg,#efeafd,#f6f6fb);border-radius:22px;padding:44px;display:flex;align-items:center;gap:40px;margin-top:20px;}
  .newsletter-content h3{font-size:26px;margin-bottom:8px;}
  .newsletter-content p{color:var(--muted);font-size:14px;margin-bottom:18px;}
  .sub-form{display:flex;gap:10px;max-width:420px;}
  .sub-form input{flex:1;border:1px solid var(--line);border-radius:8px;padding:12px 14px;font-size:14px;outline:none;}
  .sub-form button{background:var(--teal);border:none;color:#0d1730;font-weight:700;border-radius:8px;padding:0 22px;font-size:14px;}
  .newsletter-content small{display:block;margin-top:10px;font-size:11px;color:var(--muted);}
  .newsletter-content small a{color:var(--teal-dark);text-decoration:underline;}

  .adv-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-top:34px;}
  .adv-card{background:var(--navy);border-radius:14px;padding:20px;color:#fff;min-height:140px;display:flex;flex-direction:column;justify-content:space-between;position:relative;}
  .adv-card i.top-ic{width:34px;height:34px;border-radius:8px;background:rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;color:var(--teal);font-size:15px;margin-bottom:14px;}
  .adv-card h5{font-size:14px;font-weight:600;line-height:1.4;}
  .adv-card .expand{position:absolute;bottom:14px;right:14px;font-size:11px;opacity:.6;}

  .db-error{background:#fdecea;color:#b3261e;border:1px solid #f5c6c2;border-radius:10px;padding:14px 18px;max-width:900px;margin:20px auto 0;font-size:13px;}

  @media (max-width:1100px){.expect-grid{grid-template-columns:1fr;}.services-grid{grid-template-columns:1fr 1fr;}.review-grid{grid-template-columns:1fr 1fr;}.adv-grid{grid-template-columns:repeat(3,1fr);}}
  @media (max-width:680px){.services-grid,.review-grid{grid-template-columns:1fr;}.adv-grid{grid-template-columns:1fr 1fr;}.hero-inner h1{font-size:32px;}.newsletter{flex-direction:column;text-align:center;}.search-field{border-right:none;border-bottom:1px solid var(--line);}}
</style>
</head>
<body>

<?php require __DIR__ . '/components/gaia-header.php'; ?>

<section class="hero">
  <div class="container hero-inner">
    <h1><?= $bannerTitle ?></h1>
    <div class="hero-stats">
      <?php foreach ($heroStats as $s): ?>
        <div class="stat"><i class="<?= htmlspecialchars($s['icon']) ?>"></i> <b><?= htmlspecialchars($s['value']) ?></b>&nbsp;<?= htmlspecialchars($s['label']) ?></div>
      <?php endforeach; ?>
    </div>

    <?php if (isset($dbError)): ?>
      <div class="db-error"><?= $dbError ?></div>
    <?php endif; ?>

    <form class="search-card" action="<?= gaia_url('route.php') ?>" method="get">
      <div class="search-field">
        <i class="fa-solid fa-location-dot"></i>
        <div style="width:100%">
          <span class="lbl"><?= t('home.search_from') ?></span>
          <input type="text" name="origin" placeholder="<?= t('home.search_origin_ph') ?>" required>
        </div>
      </div>
      <div class="search-field">
        <i class="fa-solid fa-right-left"></i>
        <div style="width:100%">
          <span class="lbl"><?= t('home.search_to') ?></span>
          <input type="text" name="destination" placeholder="<?= t('home.search_dest_ph') ?>" required>
        </div>
      </div>
      <div class="search-field" style="max-width:150px">
        <i class="fa-regular fa-calendar"></i>
        <div style="width:100%">
          <span class="lbl"><?= t('home.search_date') ?></span>
          <input type="date" name="date">
        </div>
      </div>
      <div class="search-field" style="max-width:150px">
        <div style="width:100%">
          <span class="lbl"><?= t('home.search_passengers') ?></span>
          <div class="pax-control">
            <button type="button" id="paxMinus">–</button>
            <input type="hidden" name="passengers" id="paxCount" value="2">
            <span id="paxCountLabel">2</span>
            <button type="button" id="paxPlus">+</button>
          </div>
        </div>
      </div>
      <button class="search-btn" type="submit"><i class="fa-solid fa-magnifying-glass"></i> <?= t('home.search_btn') ?></button>
    </form>
  </div>

  <div class="cookie-toast" id="cookieToast">
    <span>&#127850; <?= t('home.cookie') ?> <a href="<?= gaia_url('contact') ?>"><?= t('footer.privacy') ?></a></span>
    <button onclick="document.getElementById('cookieToast').style.display='none'"><?= t('home.cookie_ok') ?></button>
  </div>
</section>

<!-- ================= BEYOND EXPECTATIONS ================= -->
<section class="section">
  <div class="container">
    <h2 class="section-title"><?= t('home.section.expect_title') ?></h2>
    <div class="eyebrow-divider"></div>
    <p class="section-sub"><?= t('home.section.expect_sub') ?></p>
    <div class="expect-grid">
      <?php foreach ($highlights as $h): ?>
        <div class="expect-card">
          <img src="<?= htmlspecialchars($h['image_url']) ?>" alt="<?= htmlspecialchars($h['title']) ?>">
          <div class="expand-btn"><i class="fa-solid fa-up-right-and-down-left-from-center"></i></div>
          <div class="expect-overlay"><div class="pin"><i class="<?= htmlspecialchars($h['icon']) ?>"></i></div><h4><?= htmlspecialchars($h['title']) ?></h4><p><?= htmlspecialchars($h['description']) ?></p></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= CAR CLASSES ================= -->
<section class="section tight">
  <div class="container">
    <h2 class="section-title"><?= t('home.section.car_title') ?></h2>
    <div class="eyebrow-divider"></div>
    <p class="section-sub"><?= t('home.section.car_sub') ?></p>
    <div class="car-scroll" id="carScroll">
      <?php foreach ($carClasses as $car): ?>
        <div class="car-card">
          <div class="car-img">
            <svg viewBox="0 0 120 60" xmlns="http://www.w3.org/2000/svg">
              <rect x="10" y="28" width="100" height="18" rx="6" fill="#9aa0af"/>
              <path d="M25 28 L35 12 H85 L95 28 Z" fill="#9aa0af"/>
              <rect x="40" y="16" width="40" height="14" rx="3" fill="#e9edf5"/>
              <circle cx="32" cy="46" r="9" fill="#2b2b3a"/><circle cx="88" cy="46" r="9" fill="#2b2b3a"/>
              <circle cx="32" cy="46" r="4" fill="#c9ccd6"/><circle cx="88" cy="46" r="4" fill="#c9ccd6"/>
            </svg>
          </div>
          <h5><?= htmlspecialchars($car['name']) ?></h5>
          <div class="meta">
            <span><i class="fa-solid fa-user"></i> <?= (int)$car['passenger_capacity'] ?></span>
            <span><i class="fa-solid fa-suitcase-rolling"></i> <?= (int)$car['luggage_capacity'] ?></span>
          </div>
          <div class="models"><?= htmlspecialchars($car['slug']) ?> · <?= t('home.multiplier', 'multiplier') ?> <?= rtrim(rtrim(number_format((float)$car['price_multiplier'],2,'.',''),'0'),'.') ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= ADDITIONAL SERVICES ================= -->
<section class="section tight">
  <div class="container">
<div class="services-head">
      <div>
        <h2><?= t('home.section.services_title') ?></h2>
        <p style="color:var(--muted);font-size:14px;margin-top:6px;"><?= t('home.section.services_sub') ?></p>
      </div>
      <div class="arrow-pair"><button><i class="fa-solid fa-chevron-left"></i></button><button><i class="fa-solid fa-chevron-right"></i></button></div>
    </div>
    <div class="services-grid">
      <?php foreach ($services as $svc): ?>
        <div class="service-card">
          <div class="ic"><i class="<?= htmlspecialchars($svc['icon']) ?>"></i></div>
          <div>
            <h5><?= htmlspecialchars($svc['title']) ?></h5>
            <p><?= htmlspecialchars($svc['description']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= GAIA NIGHT EXPERIENCES (premium accent layer) ================= -->
<section class="section tight" id="night">
  <div class="container" style="max-width:1280px;">
    <div class="gaia-night-band">
      <div style="text-align:center;max-width:640px;margin:0 auto 38px;">
        <span class="gaia-vip-badge">&#11088; GAIA Night</span>
        <h2 class="gaia-section-title" style="margin-top:16px;"><?= t('home.section.night_title') ?></h2>
        <div class="gaia-eyebrow-divider gold"></div>
        <p class="gaia-section-sub"><?= t('home.section.night_sub') ?></p>
      </div>
      <div class="night-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:18px;">
        <?php foreach ($nightCards as $nc): ?>
          <div class="gaia-premium-card">
            <div class="gaia-premium-media"><img src="<?= htmlspecialchars($nc['image_url']) ?>" alt="<?= htmlspecialchars($nc['title']) ?>"><span class="gaia-vip-badge" style="position:absolute;top:12px;left:12px;"><?= htmlspecialchars($nc['badge']) ?></span></div>
            <div class="gaia-premium-body">
              <h3><?= htmlspecialchars($nc['title']) ?></h3>
              <p><?= htmlspecialchars($nc['description']) ?></p>
              <span class="gaia-premium-price"><?= htmlspecialchars($nc['price_label']) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <!-- <div style="text-align:center;margin-top:34px;">
        <a class="gaia-btn gaia-btn-gold" href="gaia-night.php"><?= t('home.section.night_btn') ?></a>
      </div> -->
    </div>
  </div>
</section>

<!-- ================= EVENTS ================= -->
<section class="section tight" id="events">
  <div class="container">
    <h2 class="section-title"><?= t('home.section.events_title') ?></h2>
    <div class="eyebrow-divider"></div>
    <p class="section-sub"><?= t('home.section.events_sub') ?></p>
<div class="services-grid" style="margin-top:34px;">
      <?php foreach ($homeEvents as $ev): ?>
        <a class="service-card" style="text-decoration:none;color:inherit;transition:transform .2s,box-shadow .2s;" href="<?= gaia_url('events.php') ?>?slug=<?= urlencode($ev['slug'] ?? '') ?>">
          <div class="ic"><i class="<?= htmlspecialchars($ev['icon']) ?>"></i></div>
          <div><h5><?= htmlspecialchars($ev['title']) ?></h5><p><?= htmlspecialchars($ev['description']) ?></p></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= REVIEWS ================= -->
<section class="section tight">
  <div class="container">
    <div class="reviews-box">
<div class="reviews-head">
        <h2><?= t('home.section.reviews_title') ?></h2>
        <div class="rating-row"><i class="fa-solid fa-star"></i> <?= $avgRating ?> <span class="from"><?= str_replace('{count}', count($reviews), t('home.reviews_from')) ?></span></div>
        <p><?= t('home.section.reviews_sub') ?></p>
      </div>
      <div class="review-nav"><button><i class="fa-solid fa-chevron-left"></i></button><button><i class="fa-solid fa-chevron-right"></i></button></div>
      <div class="review-grid">
        <?php foreach ($reviews as $r): ?>
          <div class="review-card">
            <div class="stars"><?= str_repeat('★', (int)$r['rating']) ?></div>
            <h6><?= htmlspecialchars($r['reviewer']) ?> <span><?= htmlspecialchars($r['date_label']) ?></span></h6>
            <div class="place"><?= htmlspecialchars($r['place']) ?></div>
            <p><?= htmlspecialchars($r['text']) ?></p>
            <span class="read-more"><?= t('home.read_more', 'Read more') ?></span>
          </div>
        <?php endforeach; ?>
      </div>
<div class="trust-row">
        <?php foreach ($trustScores as $ts): ?>
          <div class="trust-item"><i class="<?= htmlspecialchars($ts['icon']) ?>" style="font-size:26px;color:<?= htmlspecialchars($ts['color']) ?>"></i><div><div class="tscore"><?= htmlspecialchars($ts['score']) ?></div><div class="tsub"><?= htmlspecialchars($ts['label']) ?></div></div></div>
        <?php endforeach; ?>
      </div>
      <div class="awards-row">
        <?php foreach ($awards as $aw): ?>
          <div class="award-pill"><span class="badge"><i class="<?= htmlspecialchars($aw['icon'] ?? '') ?>"></i></span> <?= htmlspecialchars($aw['label'] ?? ($aw['title'] ?? '')) ?></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ================= PARTNERS ================= -->
<section class="partners">
  <div class="container" style="text-align:center">
    <h2 class="section-title"><?= t('home.partners_title', 'Our Partners') ?></h2>
    <div class="eyebrow-divider"></div>
    <p class="section-sub"><?= t('home.partners_sub', 'Leading companies trust us with their most valuable asset: their clients') ?></p>
    <div class="partners-row">
      <?php foreach ($partners as $p): ?>
        <span><?= $p['icon'] ? '<i class="' . htmlspecialchars($p['icon']) . '"></i> ' : '' ?><?= htmlspecialchars($p['name']) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= FAQ ================= -->
<section class="section" id="faq">
  <div class="container">
    <h2 class="section-title"><?= t('home.faq_title', 'Frequently Asked Questions') ?></h2>
    <div class="eyebrow-divider"></div>
    <div class="faq-list">
      <?php foreach ($faqRows as $i => $f): ?>
        <div class="faq-item <?= $i === 0 ? 'open' : '' ?>">
          <div class="faq-q"><?= htmlspecialchars($f['question']) ?> <i class="fa-solid fa-plus"></i></div>
          <div class="faq-a"><?= htmlspecialchars($f['answer']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= NEWSLETTER ================= -->
<section class="container">
  <div class="newsletter">
    <div class="newsletter-content">
      <h3><?= t('home.section.news_title') ?></h3>
      <p><?= t('home.section.news_sub') ?></p>
      <div class="sub-form">
        <input type="email" placeholder="<?= t('home.section.news_placeholder') ?>">
        <button><?= t('home.section.news_btn') ?></button>
      </div>
      <small><?= t('home.section.news_consent') ?> <a href="<?= gaia_url('contact') ?>"><?= t('home.section.news_privacy') ?></a></small>
    </div>
  </div>
</section>

<!-- ================= ADVANTAGES ================= -->
<section class="section">
  <div class="container">
    <h2 class="section-title"><?= t('home.section.advantages_title') ?></h2>
    <div class="eyebrow-divider"></div>
    <p class="section-sub"><?= t('home.section.advantages_sub') ?></p>
    <div class="adv-grid">
      <?php foreach ($advantages as $ad): ?>
        <div class="adv-card"><i class="top-ic <?= htmlspecialchars($ad['icon']) ?>"></i><h5><?= htmlspecialchars($ad['title']) ?></h5><span class="expand"><i class="fa-solid fa-up-right-and-down-left-from-center"></i></span></div>
      <?php endforeach; ?>
    </div>
  </div>

<!-- ================= CROSS-SELL: TOURS ================= -->
<section class="container">
  <?= gaia_cross_sell('tours', $gaia_base) ?>
</section>

<?php require __DIR__ . '/components/gaia-footer.php'; ?>

<script src="/assets/gaia.js"></script>
<script>
  // Passenger counter
  let paxCount = 2;
  const paxCountEl = document.getElementById('paxCount');
  const paxCountLabel = document.getElementById('paxCountLabel');
  document.getElementById('paxPlus').addEventListener('click', () => {
    paxCount = Math.min(paxCount + 1, 20);
    paxCountEl.value = paxCount;
    paxCountLabel.textContent = paxCount;
  });
  document.getElementById('paxMinus').addEventListener('click', () => {
    paxCount = Math.max(paxCount - 1, 1);
    paxCountEl.value = paxCount;
    paxCountLabel.textContent = paxCount;
  });

  // FAQ accordion
  document.querySelectorAll('.faq-item').forEach(item => {
    item.querySelector('.faq-q').addEventListener('click', () => {
      const wasOpen = item.classList.contains('open');
      document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
      if (!wasOpen) item.classList.add('open');
    });
  });
</script>
</body>
</html>

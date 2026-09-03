<?php
/**
 * transfer-booking.php — Airport Transfers & Chauffeur Services
 * Enhanced design with optimized widths, responsive grid cards, and live DB data.
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
    $carClasses = $pdo->query('SELECT * FROM car_classes WHERE is_active = 1 ORDER BY sort_order ASC, price_multiplier ASC')->fetchAll();

    // Services
    $services = $pdo->query('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();

    // Reviews
    $reviews = $pdo->query('SELECT * FROM reviews WHERE is_active = 1 ORDER BY id ASC LIMIT 6')->fetchAll();

    // FAQ
    $faqRows = $pdo->query('SELECT * FROM faq WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();

    // Partners
    $partners = $pdo->query('SELECT * FROM partners WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll();

    // DB-driven content blocks
    $banner      = gaia_first_banner('home');
    $highlights  = gaia_highlights('expect');
    $advantages  = gaia_highlights('advantages');
    $nightCards  = gaia_night_offerings('card');
    $homeEvents  = gaia_events('home');
    $trustScores = gaia_trust_scores();
    $awards      = gaia_awards();
    $socials     = gaia_social_links();

    // Precompute average rating
    $avgRating = 4.8;
    if ($reviews) {
        $sum = array_sum(array_column($reviews, 'rating'));
        $avgRating = round($sum / count($reviews), 1);
    }
} catch (PDOException $e) {
    $carClasses = $services = $reviews = $faqRows = $partners = [];
    $banner = $highlights = $advantages = $nightCards = $homeEvents = $trustScores = $awards = $socials = [];
    $avgRating = 4.8;
    $dbError = 'Database unavailable: ' . htmlspecialchars($e->getMessage());
}

// SEO + translated page variables
$pageTitle    = seo_title('home', 'GAIA TOURS & TRAVEL — Airport Transfers & Private Chauffeur');
$pageDesc     = seo_description('home');
$bannerTitle  = $banner ? $banner['title'] : t('home.hero_title', 'Seamless Airport Transfers & Private Chauffeurs');
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
        ['icon' => 'fa-solid fa-car-side', 'value' => '1 400 000+', 'label' => t('home.stat_rides', 'Completed Rides')],
        ['icon' => 'fa-solid fa-earth-americas', 'value' => '100+', 'label' => t('home.stat_countries', 'Destinations Served')],
        ['icon' => 'fa-solid fa-star', 'value' => '4.8 / 5', 'label' => 'Customer Satisfaction']
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
  :root {
    --navy: #0d1730;
    --navy-2: #16264d;
    --teal: var(--gaia-accent, #17c9b3);
    --teal-dark: #129e8d;
    --ink: #111317;
    --muted: #6b7280;
    --line: #e5e7eb;
    --bg-soft: #f8f9fa;
    --white: #ffffff;
    --gold: #d4af37;
    --radius: 16px;
    --shadow: 0 12px 36px rgba(0,0,0,0.06);
  }
  * { box-sizing: border-box; }
  html { scroll-behavior: smooth; }
  body { margin: 0; font-family: 'Inter', sans-serif; color: var(--ink); background: var(--white); -webkit-font-smoothing: antialiased; }
  h1, h2, h3, h4 { font-family: 'Playfair Display', serif; margin: 0; }
  a { text-decoration: none; color: inherit; }
  ul { list-style: none; margin: 0; padding: 0; }
  img { max-width: 100%; display: block; }
  button { font-family: inherit; cursor: pointer; }
  
  /* Universal Container */
  .container { max-width: 1240px; margin: 0 auto; padding: 0 24px; }
  
  .eyebrow-divider { width: 48px; height: 3px; background: var(--teal); border-radius: 3px; margin: 12px auto 0; }
  .section-title { text-align: center; font-size: 32px; font-weight: 800; color: #111317; letter-spacing: -0.5px; }
  .section-sub { text-align: center; color: var(--muted); max-width: 580px; margin: 12px auto 0; font-size: 15px; line-height: 1.6; }

  /* Hero Section */
  .hero { 
    position: relative; 
    min-height: 600px; 
    background: linear-gradient(180deg, rgba(13,23,48,0.72) 0%, rgba(13,23,48,0.5) 50%, rgba(13,23,48,0.85) 100%), url('<?= htmlspecialchars($heroImage) ?>') center 30%/cover no-repeat; 
    padding: 90px 0 100px;
    display: flex;
    align-items: center;
  }
  .hero-content { width: 100%; text-align: center; }
  .hero h1 { 
    color: #ffffff; 
    font-size: 46px; 
    line-height: 1.2; 
    font-weight: 800; 
    max-width: 820px; 
    margin: 0 auto 16px;
    text-shadow: 0 3px 20px rgba(0,0,0,0.4);
  }
  .hero-stats { 
    display: flex; 
    justify-content: center; 
    align-items: center; 
    gap: 24px; 
    margin: 0 auto 36px; 
    flex-wrap: wrap; 
  }
  .hero-stats .stat { 
    display: flex; 
    align-items: center; 
    gap: 10px; 
    color: #ffffff; 
    font-size: 14px; 
    font-weight: 500; 
    background: rgba(255,255,255,0.1); 
    padding: 6px 16px; 
    border-radius: 999px; 
    backdrop-filter: blur(6px);
    border: 1px solid rgba(255,255,255,0.15);
  }
  .hero-stats .stat i { color: var(--teal); font-size: 14px; }
  .hero-stats b { font-weight: 800; color: #ffffff; font-size: 14.5px; }

  /* Search Engine Card */
  .search-card { 
    background: #ffffff; 
    border-radius: 18px; 
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); 
    display: flex; 
    flex-wrap: wrap; 
    align-items: center; 
    padding: 10px; 
    gap: 8px; 
    max-width: 1100px; 
    margin: 0 auto; 
    text-align: left;
    border: 1px solid rgba(255,255,255,0.2);
  }
  .search-field { 
    flex: 1 1 200px; 
    display: flex; 
    align-items: center; 
    gap: 12px; 
    padding: 10px 16px; 
    border-right: 1px solid var(--line); 
    min-width: 170px; 
  }
  .search-field:last-of-type { border-right: none; }
  .search-field i { color: var(--teal); font-size: 16px; flex-shrink: 0; }
  .search-field input { 
    border: none; 
    outline: none; 
    font-size: 14.5px; 
    font-weight: 600; 
    width: 100%; 
    font-family: inherit; 
    color: var(--ink); 
    background: transparent;
  }
  .search-field .lbl { font-size: 11px; font-weight: 600; color: var(--muted); display: block; margin-bottom: 2px; text-transform: uppercase; letter-spacing: 0.5px; }
  .pax-control { display: flex; align-items: center; gap: 10px; }
  .pax-control button { 
    width: 28px; 
    height: 28px; 
    border-radius: 50%; 
    border: 1px solid var(--line); 
    background: #f8f9fa; 
    color: var(--ink); 
    font-weight: 700; 
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .2s;
  }
  .pax-control button:hover { background: #e5e7eb; }
  .pax-control span { font-weight: 700; font-size: 14.5px; min-width: 18px; text-align: center; }
  
  .search-btn { 
    background: #111317; 
    color: #ffffff; 
    border: none; 
    border-radius: 12px; 
    padding: 14px 32px; 
    font-weight: 700; 
    font-size: 15px; 
    white-space: nowrap; 
    display: flex; 
    align-items: center; 
    gap: 10px; 
    transition: background .2s, transform .2s; 
  }
  .search-btn:hover { background: #2b303a; transform: translateY(-1px); }

  /* Sections */
  .section { padding: 80px 0; }
  .section.tight { padding: 60px 0; }
  .section.bg-soft { background: var(--bg-soft); }

  /* Beyond Expectations Cards */
  .expect-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 40px; }
  .expect-card { 
    position: relative; 
    border-radius: var(--radius); 
    overflow: hidden; 
    height: 320px; 
    box-shadow: var(--shadow); 
    transition: transform .25s ease;
  }
  .expect-card:hover { transform: translateY(-4px); }
  .expect-card img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
  .expect-card:hover img { transform: scale(1.05); }
  .expect-overlay { 
    position: absolute; 
    inset: auto 0 0 0; 
    background: linear-gradient(180deg, transparent 0%, rgba(13,23,48,0.92) 80%); 
    padding: 60px 24px 24px; 
    color: #fff; 
  }
  .expect-overlay .pin { 
    width: 32px; 
    height: 32px; 
    border-radius: 50%; 
    background: var(--teal); 
    color: #0d1730; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-size: 13px; 
    margin-bottom: 10px; 
  }
  .expect-overlay h4 { font-size: 18px; font-weight: 700; margin-bottom: 6px; }
  .expect-overlay p { font-size: 13px; color: #d8d6e8; line-height: 1.5; margin: 0; }

  /* Car Classes Fleet Grid */
  .car-grid { 
    display: grid; 
    grid-template-columns: repeat(4, 1fr); 
    gap: 20px; 
    margin-top: 36px; 
  }
  .car-card { 
    background: #ffffff; 
    border-radius: 16px; 
    padding: 24px 20px; 
    text-align: left; 
    border: 1px solid var(--line); 
    box-shadow: 0 4px 16px rgba(0,0,0,0.03);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s;
  }
  .car-card:hover { 
    transform: translateY(-4px); 
    box-shadow: var(--shadow); 
    border-color: var(--teal);
  }
  .car-card .car-img { 
    height: 130px; 
    border-radius: 12px;
    overflow: hidden;
    display: flex; 
    align-items: center; 
    justify-content: center; 
    margin-bottom: 16px; 
    background: #f1f3f7;
  }
  .car-card .car-img img { 
    width: 100%; 
    height: 100%; 
    object-fit: cover; 
    transition: transform .35s ease;
  }
  .car-card:hover .car-img img {
    transform: scale(1.06);
  }
  .car-card h5 { font-size: 17px; font-weight: 800; margin-bottom: 8px; color: #111317; }
  .car-card .meta { display: flex; gap: 14px; font-size: 12.5px; color: var(--muted); margin-bottom: 12px; }
  .car-card .meta span { display: flex; align-items: center; gap: 5px; }
  .car-card .models { font-size: 12px; color: #555d6e; line-height: 1.4; margin-bottom: 16px; }
  .car-card .car-multiplier {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: #111317;
    background: #f1f3f7;
    padding: 4px 10px;
    border-radius: 6px;
    display: inline-block;
    width: fit-content;
  }

  /* Services & Events Grid */
  .services-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 36px; }
  .service-card { 
    display: flex; 
    align-items: flex-start; 
    gap: 16px; 
    background: #ffffff; 
    border: 1px solid var(--line); 
    border-radius: 14px; 
    padding: 24px; 
    transition: transform .2s ease, box-shadow .2s ease;
  }
  .service-card:hover { transform: translateY(-3px); box-shadow: var(--shadow); border-color: #cbd5e1; }
  .service-card .ic { 
    width: 48px; 
    height: 48px; 
    border-radius: 12px; 
    background: rgba(23,201,179,0.12); 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-size: 20px; 
    color: #0c7c6d; 
    flex-shrink: 0; 
  }
  .service-card h5 { font-size: 16px; font-weight: 700; margin-bottom: 6px; color: #111317; }
  .service-card p { font-size: 13px; color: var(--muted); margin: 0; line-height: 1.5; }

  /* GAIA Night Band */
  .gaia-night-band {
    background: linear-gradient(135deg, #0d1730 0%, #16264d 100%);
    border-radius: 24px;
    padding: 60px 40px;
    color: #ffffff;
  }
  .gaia-night-band h2 { color: #ffffff; }
  .gaia-vip-badge {
    display: inline-block;
    background: rgba(212,175,55,0.15);
    color: var(--gold);
    border: 1px solid rgba(212,175,55,0.3);
    font-size: 11px;
    font-weight: 800;
    padding: 5px 14px;
    border-radius: 999px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
  }
  .night-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 36px; }
  .gaia-premium-card {
    background: #172445;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,0.08);
    display: flex;
    flex-direction: column;
    transition: transform .2s ease;
  }
  .gaia-premium-card:hover { transform: translateY(-4px); }
  .gaia-premium-media { position: relative; aspect-ratio: 16/10; overflow: hidden; }
  .gaia-premium-media img { width: 100%; height: 100%; object-fit: cover; }
  .gaia-premium-body { padding: 18px; flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
  .gaia-premium-body h3 { font-size: 16px; font-weight: 700; margin-bottom: 6px; color: #fff; }
  .gaia-premium-body p { font-size: 12.5px; color: #a4b3d6; line-height: 1.45; margin: 0 0 14px; }
  .gaia-premium-price { font-size: 13.5px; font-weight: 800; color: var(--gold); }

  /* Reviews Dark Box */
  .reviews-box { 
    background: #0d1730; 
    border-radius: 24px; 
    padding: 60px 40px; 
    color: #ffffff; 
  }
  .reviews-box h2 { color: #ffffff; }
  .rating-row { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 14px; font-size: 20px; font-weight: 800; }
  .rating-row i { color: #ffb400; }
  .review-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 40px; }
  .review-card { 
    background: #ffffff; 
    color: var(--ink); 
    border-radius: 14px; 
    padding: 22px; 
    display: flex; 
    flex-direction: column; 
    justify-content: space-between; 
  }
  .review-card .stars { color: #ffb400; font-size: 12px; margin-bottom: 6px; }
  .review-card h6 { font-size: 14.5px; font-weight: 700; margin: 0 0 4px; display: flex; justify-content: space-between; }
  .review-card h6 span { font-weight: 400; color: var(--muted); font-size: 11.5px; }
  .review-card .place { font-size: 12px; color: var(--muted); margin-bottom: 10px; }
  .review-card p { font-size: 13px; color: #42405a; line-height: 1.55; margin: 0; }
  
  .trust-row { display: flex; justify-content: center; gap: 40px; margin-top: 40px; flex-wrap: wrap; }
  .trust-item { display: flex; align-items: center; gap: 12px; }
  .trust-item .tscore { font-size: 20px; font-weight: 800; }
  .trust-item .tsub { font-size: 12px; color: #a4b3d6; }

  /* Advantages Grid */
  .adv-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-top: 36px; }
  .adv-card { 
    background: #f8f9fa; 
    border: 1px solid var(--line); 
    border-radius: 14px; 
    padding: 24px 20px; 
    display: flex; 
    flex-direction: column; 
    gap: 12px;
    transition: transform .2s ease, box-shadow .2s ease;
  }
  .adv-card:hover { transform: translateY(-3px); box-shadow: var(--shadow); }
  .adv-card i.top-ic { 
    width: 42px; 
    height: 42px; 
    border-radius: 10px; 
    background: rgba(23,201,179,0.15); 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    color: #0c7c6d; 
    font-size: 18px; 
  }
  .adv-card h5 { font-size: 15px; font-weight: 700; line-height: 1.4; margin: 0; color: #111317; }

  /* Partners Row */
  .partners { background: var(--bg-soft); padding: 60px 0; }
  .partners-row { display: flex; justify-content: center; align-items: center; gap: 48px; margin-top: 30px; flex-wrap: wrap; filter: grayscale(1); opacity: .7; font-weight: 700; font-size: 20px; }

  /* FAQ Accordion */
  .faq-list { max-width: 860px; margin: 36px auto 0; }
  .faq-item { border-bottom: 1px solid var(--line); }
  .faq-q { display: flex; align-items: center; justify-content: space-between; padding: 20px 4px; font-size: 16px; font-weight: 700; cursor: pointer; color: #111317; }
  .faq-q i { transition: transform .25s ease; color: var(--muted); }
  .faq-item.open .faq-q i { transform: rotate(45deg); color: var(--teal); }
  .faq-a { max-height: 0; overflow: hidden; transition: max-height .3s ease; font-size: 14px; color: #4b5262; line-height: 1.65; }
  .faq-item.open .faq-a { max-height: 200px; padding-bottom: 20px; }

  /* Newsletter Box */
  .newsletter { 
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); 
    border-radius: 20px; 
    padding: 48px; 
    display: flex; 
    align-items: center; 
    justify-content: space-between; 
    gap: 40px; 
    margin: 40px auto; 
    max-width: 1240px;
  }
  .newsletter-content h3 { font-size: 26px; font-weight: 800; margin-bottom: 8px; color: #111317; }
  .newsletter-content p { color: var(--muted); font-size: 14.5px; margin: 0; }
  .sub-form { display: flex; gap: 10px; min-width: 360px; }
  .sub-form input { flex: 1; border: 1px solid var(--line); border-radius: 10px; padding: 12px 18px; font-size: 14px; outline: none; background: #fff; }
  .sub-form button { background: #111317; border: none; color: #fff; font-weight: 700; border-radius: 10px; padding: 0 24px; font-size: 14px; transition: background .2s; }
  .sub-form button:hover { background: #2b303a; }

  /* Responsive Breakpoints */
  @media (max-width: 1100px) {
    .car-grid { grid-template-columns: repeat(2, 1fr); }
    .services-grid { grid-template-columns: repeat(2, 1fr); }
    .review-grid { grid-template-columns: repeat(2, 1fr); }
    .night-grid { grid-template-columns: repeat(2, 1fr); }
    .adv-grid { grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 768px) {
    .hero { padding: 60px 0 80px; }
    .hero h1 { font-size: 32px; }
    .search-card { flex-direction: column; align-items: stretch; padding: 16px; }
    .search-field { border-right: none; border-bottom: 1px solid var(--line); padding: 12px 4px; }
    .search-btn { width: 100%; justify-content: center; padding: 14px; }
    .expect-grid, .car-grid, .services-grid, .review-grid, .night-grid, .adv-grid { grid-template-columns: 1fr; }
    .newsletter { flex-direction: column; text-align: center; padding: 32px 20px; }
    .sub-form { width: 100%; min-width: auto; flex-direction: column; }
    .gaia-night-band, .reviews-box { padding: 40px 20px; }
  }
</style>
</head>
<body>

<?php require __DIR__ . '/components/gaia-header.php'; ?>

<!-- ================= HERO WITH RIDE SEARCH ENGINE ================= -->
<section class="hero">
  <div class="container hero-content">
    <h1><?= htmlspecialchars($bannerTitle) ?></h1>
    
    <div class="hero-stats">
      <?php foreach ($heroStats as $s): ?>
        <div class="stat">
          <i class="<?= htmlspecialchars($s['icon']) ?>"></i>
          <span><b><?= htmlspecialchars($s['value']) ?></b> <?= htmlspecialchars($s['label']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Wide Ride Search Engine -->
    <form class="search-card" action="<?= gaia_url('route.php') ?>" method="get">
      <div class="search-field">
        <i class="fa-solid fa-location-dot"></i>
        <div style="width:100%">
          <span class="lbl"><?= t('home.search_from', 'Pick-up Location') ?></span>
          <input type="text" name="origin" placeholder="<?= t('home.search_origin_ph', 'Airport, Hotel, City...') ?>" required>
        </div>
      </div>
      <div class="search-field">
        <i class="fa-solid fa-flag-checkered"></i>
        <div style="width:100%">
          <span class="lbl"><?= t('home.search_to', 'Drop-off Destination') ?></span>
          <input type="text" name="destination" placeholder="<?= t('home.search_dest_ph', 'Destination address...') ?>" required>
        </div>
      </div>
      <div class="search-field" style="max-width:180px">
        <i class="fa-regular fa-calendar"></i>
        <div style="width:100%">
          <span class="lbl"><?= t('home.search_date', 'Date & Time') ?></span>
          <input type="date" name="date" value="<?= date('Y-m-d') ?>">
        </div>
      </div>
      <div class="search-field" style="max-width:160px">
        <i class="fa-solid fa-users"></i>
        <div style="width:100%">
          <span class="lbl"><?= t('home.search_passengers', 'Passengers') ?></span>
          <div class="pax-control">
            <button type="button" id="paxMinus">–</button>
            <input type="hidden" name="passengers" id="paxCount" value="2">
            <span id="paxCountLabel">2</span>
            <button type="button" id="paxPlus">+</button>
          </div>
        </div>
      </div>
      <button class="search-btn" type="submit">
        <i class="fa-solid fa-magnifying-glass"></i> <?= t('home.search_btn', 'Find Ride') ?>
      </button>
    </form>
  </div>
</section>

<!-- ================= CAR CLASSES / FLEET ================= -->
<section class="section">
  <div class="container">
    <h2 class="section-title"><?= t('home.section.car_title', 'Vehicle Classes & Private Fleet') ?></h2>
    <div class="eyebrow-divider"></div>
    <p class="section-sub"><?= t('home.section.car_sub', 'From luxury executive sedans to spacious group vans, select your preferred travel class.') ?></p>
    
    <div class="car-grid">
      <?php foreach ($carClasses as $car): 
        $cName = lang_value($car, 'name');
        $cDesc = lang_value($car, 'description');
        $cModels = !empty($car['models']) ? $car['models'] : $car['slug'];
        $cImg = !empty($car['image_url']) ? $car['image_url'] : 'https://images.unsplash.com/photo-1550355291-bbee04a92027?auto=format&fit=crop&w=600&q=80';
      ?>
        <div class="car-card">
          <div>
            <div class="car-img">
              <img src="<?= htmlspecialchars('/' . ltrim($cImg, '/')) ?>" alt="<?= htmlspecialchars($cName) ?>" loading="lazy">
            </div>
            <h5><?= htmlspecialchars($cName) ?></h5>
            <div class="meta">
              <span><i class="fa-solid fa-user"></i> <?= (int)$car['passenger_capacity'] ?> <?= t('transfers.passengers', 'Passengers') ?></span>
              <span><i class="fa-solid fa-suitcase-rolling"></i> <?= (int)$car['luggage_capacity'] ?> <?= t('transfers.bags', 'Bags') ?></span>
            </div>
            <div class="models" title="<?= htmlspecialchars($cModels) ?>"><?= htmlspecialchars($cModels) ?></div>
            <?php if (!empty($cDesc)): ?>
              <p style="font-size:12.5px; color:var(--text-muted); margin-top:6px; line-height:1.4;"><?= htmlspecialchars($cDesc) ?></p>
            <?php endif; ?>
          </div>
          <div>
            <span class="car-multiplier"><?= t('transfers.rate_index', 'Rate index') ?> <?= rtrim(rtrim(number_format((float)$car['price_multiplier'], 2, '.', ''), '0'), '.') ?>x</span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= BEYOND EXPECTATIONS ================= -->
<section class="section bg-soft">
  <div class="container">
    <h2 class="section-title"><?= t('home.section.expect_title', 'Beyond Expectations') ?></h2>
    <div class="eyebrow-divider"></div>
    <p class="section-sub"><?= t('home.section.expect_sub', 'Every journey is tailored with precision, punctuality, and professional hospitality.') ?></p>
    
    <div class="expect-grid">
      <?php foreach ($highlights as $h): ?>
        <div class="expect-card">
          <img src="<?= htmlspecialchars($h['image_url'] ?: 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&w=600&q=80') ?>" alt="<?= htmlspecialchars($h['title']) ?>" loading="lazy">
          <div class="expect-overlay">
            <div class="pin"><i class="<?= htmlspecialchars($h['icon']) ?>"></i></div>
            <h4><?= htmlspecialchars($h['title']) ?></h4>
            <p><?= htmlspecialchars($h['description']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= ADDITIONAL SERVICES ================= -->
<section class="section">
  <div class="container">
    <h2 class="section-title"><?= t('home.section.services_title', 'Tailored Transfer Services') ?></h2>
    <div class="eyebrow-divider"></div>
    <p class="section-sub"><?= t('home.section.services_sub', 'Flexible options designed for business, leisure, and VIP travel.') ?></p>
    
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

<!-- ================= GAIA NIGHT EXPERIENCES ================= -->
<?php if (!empty($nightCards)): ?>
<section class="section tight" id="night">
  <div class="container">
    <div class="gaia-night-band">
      <div style="text-align:center; max-width:640px; margin:0 auto;">
        <span class="gaia-vip-badge">⭐ GAIA Signature Experiences</span>
        <h2 class="section-title" style="margin-top:14px;"><?= t('home.section.night_title', 'GAIA Night & Private Excursions') ?></h2>
        <div class="eyebrow-divider" style="background:var(--gold);"></div>
        <p class="section-sub" style="color:#a4b3d6;"><?= t('home.section.night_sub', 'Exclusive evening desert stargazing, rooftop tastings, and VIP excursions.') ?></p>
      </div>
      
      <div class="night-grid">
        <?php foreach ($nightCards as $nc): ?>
          <div class="gaia-premium-card">
            <div class="gaia-premium-media">
              <img src="<?= htmlspecialchars($nc['image_url']) ?>" alt="<?= htmlspecialchars($nc['title']) ?>" loading="lazy">
              <?php if (!empty($nc['badge'])): ?>
                <span class="gaia-vip-badge" style="position:absolute; top:12px; left:12px;"><?= htmlspecialchars($nc['badge']) ?></span>
              <?php endif; ?>
            </div>
            <div class="gaia-premium-body">
              <div>
                <h3><?= htmlspecialchars($nc['title']) ?></h3>
                <p><?= htmlspecialchars($nc['description']) ?></p>
              </div>
              <span class="gaia-premium-price"><?= htmlspecialchars($nc['price_label']) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ================= ADVANTAGES ================= -->
<?php if (!empty($advantages)): ?>
<section class="section bg-soft">
  <div class="container">
    <h2 class="section-title"><?= t('home.section.advantages_title', 'Why Travel with GAIA') ?></h2>
    <div class="eyebrow-divider"></div>
    <p class="section-sub"><?= t('home.section.advantages_sub', 'Guaranteed reliability, fixed upfront pricing, and 24/7 dedicated support.') ?></p>
    
    <div class="adv-grid">
      <?php foreach ($advantages as $ad): ?>
        <div class="adv-card">
          <i class="top-ic <?= htmlspecialchars($ad['icon']) ?>"></i>
          <h5><?= htmlspecialchars($ad['title']) ?></h5>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ================= REVIEWS ================= -->
<?php if (!empty($reviews)): ?>
<section class="section">
  <div class="container">
    <div class="reviews-box">
      <div style="text-align:center;">
        <h2><?= t('home.section.reviews_title', 'Trusted by Thousands of Travelers') ?></h2>
        <div class="rating-row">
          <i class="fa-solid fa-star"></i> <?= $avgRating ?>
          <span style="font-size:13px; font-weight:400; color:#a4b3d6; margin-left:4px;">
            <?= str_replace('{count}', count($reviews), t('home.reviews_from', 'from verified bookings')) ?>
          </span>
        </div>
      </div>
      
      <div class="review-grid">
        <?php foreach ($reviews as $r): ?>
          <div class="review-card">
            <div>
              <div class="stars"><?= str_repeat('★', (int)$r['rating']) ?></div>
              <h6><?= htmlspecialchars($r['reviewer']) ?> <span><?= htmlspecialchars($r['date_label']) ?></span></h6>
              <div class="place"><?= htmlspecialchars($r['place']) ?></div>
              <p><?= htmlspecialchars($r['text']) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="trust-row">
        <?php foreach ($trustScores as $ts): ?>
          <div class="trust-item">
            <i class="<?= htmlspecialchars($ts['icon']) ?>" style="font-size:24px; color:<?= htmlspecialchars($ts['color']) ?>"></i>
            <div>
              <div class="tscore"><?= htmlspecialchars($ts['score']) ?></div>
              <div class="tsub"><?= htmlspecialchars($ts['label']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ================= FAQ ================= -->
<?php if (!empty($faqRows)): ?>
<section class="section bg-soft" id="faq">
  <div class="container">
    <h2 class="section-title"><?= t('home.faq_title', 'Frequently Asked Questions') ?></h2>
    <div class="eyebrow-divider"></div>
    
    <div class="faq-list">
      <?php foreach ($faqRows as $i => $f): ?>
        <div class="faq-item <?= $i === 0 ? 'open' : '' ?>">
          <div class="faq-q">
            <span><?= htmlspecialchars($f['question']) ?></span>
            <i class="fa-solid fa-plus"></i>
          </div>
          <div class="faq-a"><?= htmlspecialchars($f['answer']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ================= NEWSLETTER ================= -->
<div class="container">
  <div class="newsletter">
    <div class="newsletter-content">
      <h3><?= t('home.section.news_title', 'Stay Updated with GAIA Exclusive Deals') ?></h3>
      <p><?= t('home.section.news_sub', 'Get special offers on private transfers, luxury desert camps, and tours.') ?></p>
    </div>
    <form class="sub-form" action="/subscribe" method="post" onsubmit="event.preventDefault(); alert('Thank you for subscribing!');">
      <input type="email" placeholder="<?= t('home.section.news_placeholder', 'Enter your email') ?>" required>
      <button type="submit"><?= t('home.section.news_btn', 'Subscribe') ?></button>
    </form>
  </div>
</div>

<!-- ================= CROSS-SELL: TOURS ================= -->
<section class="container" style="margin-bottom: 60px;">
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

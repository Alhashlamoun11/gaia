<?php
/**
 * tour.php — Tour Details page (dynamic)
 * ------------------------------------------------------------
 * Route:  tour.php?slug={slug}
 * Example: tour.php?slug=jordan-360
 *
 * Loads a tour from `weroad_trips` plus its itinerary,
 * inclusions, optional activities, gallery, departures,
 * reviews and FAQs. Shares the GAIA header/footer, EN/AR +
 * dynamic SEO, sticky booking sidebar. 404 handled gracefully.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/components/gaia-config.php';

$gaia_base          = '';
$gaia_active        = 'tours';
$gaia_header_style  = 'solid';

$slug = trim($_GET['slug'] ?? '');
$trip = null;

try {
    $pdo = getPDO();

    $tripStmt = $pdo->prepare("SELECT * FROM weroad_trips WHERE slug = :slug AND is_active = 1 LIMIT 1");
    $tripStmt->execute([':slug' => $slug]);
    $trip = $tripStmt->fetch();
} catch (PDOException $e) {
    $trip = null;
}

if (!$trip) {
    http_response_code(404);
    $is404 = true;
    $gallery = $itinerary = $included = $excluded = $activities = $departures = $reviews = $faqGroups = $relatedTours = [];
} else {
    $is404 = false;
    $tripId = (int)$trip['id'];

    // Gallery
    $gallery = $trip['gallery_urls'] !== null
        ? array_filter(array_map('trim', explode(',', $trip['gallery_urls'])))
        : [];
    if (empty($gallery) && $trip['image_url']) {
        $gallery = [$trip['image_url']];
    }
    if (empty($gallery)) {
        $gallery = ['https://images.unsplash.com/photo-1548786811-dd6e453ccca7?w=900&q=80'];
    }

    // Itinerary
    $itStmt = $pdo->prepare("SELECT * FROM weroad_trip_itineraries WHERE trip_id = :id ORDER BY day_number");
    $itStmt->execute([':id' => $tripId]);
    $itinerary = $itStmt->fetchAll();

    // Inclusions
    $incStmt = $pdo->prepare("SELECT * FROM weroad_trip_inclusions WHERE trip_id = :id ORDER BY include_type, id");
    $incStmt->execute([':id' => $tripId]);
    $inclusions = $incStmt->fetchAll();
    $included = array_filter($inclusions, fn($i) => $i['include_type'] === 'included');
    $excluded = array_filter($inclusions, fn($i) => $i['include_type'] === 'excluded');

    // Optional activities
    $actStmt = $pdo->prepare("SELECT * FROM weroad_trip_optional_activities WHERE trip_id = :id ORDER BY price");
    $actStmt->execute([':id' => $tripId]);
    $activities = $actStmt->fetchAll();

    // Departures
    $depStmt = $pdo->prepare("SELECT * FROM weroad_departures WHERE trip_id = :id AND is_active = 1 ORDER BY start_date");
    $depStmt->execute([':id' => $tripId]);
    $departures = $depStmt->fetchAll();

    // Reviews
    $revStmt = $pdo->prepare("SELECT * FROM weroad_reviews WHERE trip_id = :id ORDER BY id");
    $revStmt->execute([':id' => $tripId]);
    $reviews = $revStmt->fetchAll();

    // FAQs grouped
    $faqStmt = $pdo->prepare("SELECT * FROM weroad_trip_faqs WHERE trip_id = :id ORDER BY faq_group, id");
    $faqStmt->execute([':id' => $tripId]);
    $faqGroups = [];
    foreach ($faqStmt->fetchAll() as $faq) {
        $faqGroups[$faq['faq_group']][] = $faq;
    }

    // Related tours (same category, excluding current)
    $relStmt = $pdo->prepare("SELECT * FROM weroad_trips WHERE category = :cat AND id != :id AND is_active = 1 ORDER BY rating DESC LIMIT 4");
    $relStmt->execute([':cat' => $trip['category'], ':id' => $tripId]);
    $relatedTours = $relStmt->fetchAll();
    if (empty($relatedTours)) {
        $relatedTours = $pdo->query("SELECT * FROM weroad_trips WHERE is_active = 1 ORDER BY rating DESC LIMIT 4")->fetchAll();
    }
}

// Dynamic SEO
if (!$is404) {
    $seoTitle = ($trip['name'] ?? 'Tour') . ' — GAIA TOURS & TRAVEL';
    $seoDesc  = mb_strimwidth(strip_tags($trip['tagline'] ?: $trip['description'] ?: ''), 0, 150, '…');
    $seoImage = $gallery[0];
} else {
    $seoTitle = t('detail.not_found', 'Not Found') . ' — GAIA TOURS & TRAVEL';
    $seoDesc  = t('detail.not_found_text', 'The page you are looking for does not exist.');
    $seoImage = '';
}

$currency = site_setting('currency_symbol', '$');
$whatsapp = htmlspecialchars(site_setting('contact_whatsapp', '962790123456'));
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($seoTitle) ?></title>
<meta name="description" content="<?= htmlspecialchars($seoDesc) ?>">
<meta property="og:title" content="<?= htmlspecialchars($seoTitle) ?>">
<meta property="og:description" content="<?= htmlspecialchars($seoDesc) ?>">
<?php if ($seoImage): ?><meta property="og:image" content="<?= htmlspecialchars($seoImage) ?>"><?php endif; ?>
<link rel="canonical" href="<?= htmlspecialchars(gaia_switch_lang_url('en')) ?>">
<link rel="alternate" hreflang="en" href="<?= htmlspecialchars(gaia_switch_lang_url('en')) ?>">
<link rel="alternate" hreflang="ar" href="<?= htmlspecialchars(gaia_switch_lang_url('ar')) ?>">
<link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars(gaia_switch_lang_url('en')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<style>
  :root { --ink: var(--gaia-ink); --muted: var(--gaia-muted); --line: var(--gaia-line); --warm: var(--gaia-warm); }
  * { box-sizing: border-box; }
  body { margin: 0; font-family: 'Inter', sans-serif; color: var(--ink); background: #fff; -webkit-font-smoothing: antialiased; }
  h1, h2, h3, h4, h5 { font-family: 'Playfair Display', serif; margin: 0; }
  a { text-decoration: none; color: inherit; }
  ul { list-style: none; margin: 0; padding: 0; }
  img { max-width: 100%; display: block; }
  .container { max-width: 1280px; margin: 0 auto; padding: 0 24px; }

  /* ---------- Hero ---------- */
  .detail-hero { position: relative; min-height: 520px; display: flex; align-items: flex-end; background: linear-gradient(180deg, rgba(20,22,40,.4), rgba(20,22,40,.85)), url('<?= htmlspecialchars($gallery[0] ?? '') ?>') center 40%/cover no-repeat; color: #fff; }
  .detail-hero-inner { width: 100%; padding: 160px 0 54px; }
  .gaia-breadcrumb { display: flex; align-items: center; gap: 8px; font-size: 13px; color: rgba(255,255,255,.85); margin-bottom: 18px; flex-wrap: wrap; }
  .gaia-breadcrumb a { color: rgba(255,255,255,.85); text-underline-offset: 3px; text-decoration: underline; }
  .gaia-breadcrumb .sep { opacity: .5; }
  .detail-hero h1 { font-size: 44px; font-weight: 700; max-width: 820px; line-height: 1.12; }
  .detail-hero .tagline { font-size: 17px; opacity: .92; max-width: 640px; margin: 14px 0 0; }
  .hero-meta { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 22px; }
  .pill { display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,.15); padding: 8px 15px; border-radius: 999px; font-size: 13.5px; backdrop-filter: blur(4px); }
  .pill i { color: var(--gaia-gold); }
  .pill .ratestars { color: var(--gaia-gold); }

  /* ---------- Layout ---------- */
  .detail-layout { display: grid; grid-template-columns: 1fr 350px; gap: 34px; padding: 48px 0 20px; align-items: start; }
  .section { padding: 0 0 42px; }
  .section-head { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
  .section-head h2 { font-size: 25px; font-weight: 700; }
  .eyebrow { width: 48px; height: 4px; background: var(--gaia-accent); border-radius: 4px; }
  .desc { font-size: 15px; line-height: 1.9; color: #3a3d48; }

  /* ---------- Fit box ---------- */
  .fit-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
  .fit-row { display: flex; gap: 12px; background: var(--warm); border: 1px solid var(--line); border-radius: 12px; padding: 14px; }
  .fit-row .ic { width: 40px; height: 40px; border-radius: 10px; background: #fff; display: flex; align-items: center; justify-content: center; color: var(--gaia-accent); font-size: 16px; flex: none; }
  .fit-row b { display: block; font-size: 13px; margin-bottom: 2px; }
  .fit-row span { font-size: 12.5px; color: var(--muted); }

  /* ---------- Itinerary ---------- */
  .day-card { border: 1px solid var(--line); border-radius: 12px; margin-bottom: 10px; overflow: hidden; background: #fff; }
  .day-card summary { display: flex; align-items: center; gap: 14px; padding: 14px 16px; cursor: pointer; list-style: none; }
  .day-card summary::-webkit-details-marker { display: none; }
  .day-card summary .thumb { width: 64px; height: 48px; object-fit: cover; border-radius: 8px; flex: none; }
  .day-card summary .day-label { font-size: 11px; color: var(--gaia-accent); font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
  .day-card summary h4 { font-size: 15px; flex: 1; }
  .day-card summary .chev { transition: transform .25s; color: var(--muted); }
  .day-card[open] summary .chev { transform: rotate(180deg); }
  .day-body { padding: 0 16px 16px 94px; font-size: 13.5px; color: var(--muted); line-height: 1.7; }

  /* ---------- Included ---------- */
  .included-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; }
  .included-col h4 { font-size: 16px; margin-bottom: 12px; }
  .included-col li { display: flex; gap: 10px; align-items: flex-start; font-size: 13.5px; margin-bottom: 10px; }
  .included-col li.yes i { color: #2e9e5b; margin-top: 2px; }
  .included-col li.no i { color: #c0392b; margin-top: 2px; }

  /* ---------- Optional activities ---------- */
  .act-item { display: flex; justify-content: space-between; align-items: center; gap: 14px; background: var(--warm); border: 1px solid var(--line); border-radius: 12px; padding: 16px; margin-bottom: 10px; }
  .act-item h4 { font-size: 15px; margin-bottom: 3px; }
  .act-item p { font-size: 13px; color: var(--muted); margin: 0; line-height: 1.6; }
  .act-item .price { font-weight: 700; color: var(--gaia-accent); white-space: nowrap; }

  /* ---------- Gallery ---------- */
  .gallery-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
  .gallery-grid img { width: 100%; height: 190px; object-fit: cover; border-radius: 10px; }

  /* ---------- Departures ---------- */
  .dep-card { display: flex; align-items: center; justify-content: space-between; gap: 16px; border: 1px solid var(--line); border-left: 4px solid var(--gaia-accent); border-radius: 12px; padding: 16px 18px; margin-bottom: 10px; background: #fff; flex-wrap: wrap; }
  .dep-card .date { font-weight: 700; font-size: 15px; }
  .dep-card .date small { display: block; font-weight: 400; color: var(--muted); font-size: 12px; }
  .dep-card .seats { font-size: 13px; color: var(--muted); }
  .dep-card .seats b { color: var(--gaia-accent); }
  .dep-card .price { font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 700; text-align: right; }
  .dep-card .price small { font-size: 12px; color: var(--muted); font-weight: 400; display: block; }

  /* ---------- Reviews ---------- */
  .reviews-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
  .review-card { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 18px; }
  .review-card .stars { color: var(--gaia-gold); font-size: 13px; margin-bottom: 6px; }
  .review-card h5 { font-size: 14px; }
  .review-card p { font-size: 13px; color: #42405a; line-height: 1.6; margin: 8px 0 0; }

  /* ---------- FAQ ---------- */
  .faq-item { border: 1px solid var(--line); border-radius: 10px; margin-bottom: 8px; overflow: hidden; }
  .faq-item summary { padding: 14px 16px; cursor: pointer; font-weight: 600; font-size: 14px; list-style: none; display: flex; justify-content: space-between; align-items: center; }
  .faq-item summary::-webkit-details-marker { display: none; }
  .faq-item summary i { color: var(--gaia-accent); transition: transform .2s; }
  .faq-item[open] summary i { transform: rotate(45deg); }
  .faq-item .faq-body { padding: 0 16px 16px; font-size: 13.5px; color: var(--muted); line-height: 1.7; }

  /* ---------- Related tours ---------- */
  .tour-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
  .tour-card { border: 1px solid var(--line); border-radius: 12px; overflow: hidden; background: #fff; box-shadow: var(--gaia-shadow); display: block; transition: transform .2s; }
  .tour-card:hover { transform: translateY(-3px); }
  .tour-card .media { aspect-ratio: 4/3; overflow: hidden; }
  .tour-card .media img { width: 100%; height: 100%; object-fit: cover; }
  .tour-card .body { padding: 14px; }
  .tour-card h3 { font-size: 14px; line-height: 1.35; margin-bottom: 8px; }
  .tour-card .price { font-weight: 700; color: var(--gaia-accent); font-size: 14px; }

  /* ---------- Sidebar ---------- */
  .sidebar { position: sticky; top: 90px; display: flex; flex-direction: column; gap: 14px; }
  .book-card { background: var(--warm); border: 1px solid var(--line); border-radius: 16px; padding: 24px; }
  .book-card .from { font-size: 13px; color: var(--muted); }
  .book-card .amount { font-family: 'Playfair Display', serif; font-size: 34px; font-weight: 700; color: var(--gaia-accent); }
  .book-card .per { font-size: 13px; color: var(--muted); }
  .book-card .next { margin-top: 16px; background: #fff; border: 1px solid var(--line); border-radius: 10px; padding: 12px 14px; font-size: 13px; }
  .book-card .next b { display: block; font-size: 14px; }
  .book-card .cta { margin-top: 16px; display: flex; flex-direction: column; gap: 10px; }
  .info-line { display: flex; align-items: center; gap: 10px; font-size: 13px; color: var(--muted); margin-top: 12px; }
  .info-line i { color: var(--gaia-accent); width: 18px; text-align: center; }
  .divider { height: 1px; background: var(--line); margin: 16px 0; }

  /* ---------- 404 ---------- */
  .not-found { text-align: center; padding: 110px 24px; }
  .not-found .code { font-family: 'Playfair Display', serif; font-size: 84px; color: var(--gaia-accent); font-weight: 800; line-height: 1; }
  .not-found h1 { font-size: 30px; margin: 18px 0 10px; }
  .not-found p { color: var(--muted); max-width: 480px; margin: 0 auto 28px; }

  @media (max-width: 992px) { .detail-layout { grid-template-columns: 1fr; } .sidebar { position: static; } .fit-grid, .tour-row { grid-template-columns: 1fr 1fr; } }
  @media (max-width: 720px) {
    .detail-hero h1 { font-size: 30px; }
    .gallery-grid, .fit-grid, .included-grid, .reviews-grid, .tour-row { grid-template-columns: 1fr; }
    .day-body { padding-left: 16px; }
  }
</style>
</head>
<body>
<?php require __DIR__ . '/components/gaia-header.php'; ?>

<?php if ($is404): ?>
  <section class="not-found">
    <div class="code">404</div>
    <h1><?= t('detail.not_found', 'Page Not Found') ?></h1>
    <p><?= t('detail.not_found_text', 'The page you are looking for does not exist or has been removed.') ?></p>
    <a class="gaia-btn" href="<?= gaia_url('weroad/search.php') ?>"><?= t('detail.back_to_tours', 'Browse Tours') ?></a>
  </section>
<?php else: ?>

  <!-- ================= HERO ================= -->
  <section class="detail-hero">
    <div class="container detail-hero-inner">
      <nav class="gaia-breadcrumb">
        <a href="<?= gaia_url('index.php') ?>"><?= t('detail.home', 'Home') ?></a>
        <span class="sep">/</span>
        <a href="<?= gaia_url('weroad/search.php') ?>"><?= t('detail.tours', 'Tours') ?></a>
        <span class="sep">/</span>
        <span><?= htmlspecialchars($trip['name']) ?></span>
      </nav>
      <h1><?= htmlspecialchars($trip['name']) ?></h1>
      <?php if ($trip['tagline']): ?><p class="tagline"><?= htmlspecialchars($trip['tagline']) ?></p><?php endif; ?>
      <div class="hero-meta">
        <span class="pill"><span class="ratestars">★</span> <?= number_format((float)$trip['rating'], 1) ?> (<?= (int)$trip['reviews_count'] ?> <?= t('tour.reviews', 'Reviews') ?>)</span>
        <span class="pill"><i class="fa-regular fa-clock"></i> <?= t_fmt('tour.duration', ['days' => (int)$trip['duration_days']]) ?></span>
        <span class="pill"><i class="fa-solid fa-signal"></i> <?= t_fmt('tour.difficulty', ['level' => htmlspecialchars($trip['effort_level'])]) ?></span>
        <span class="pill"><i class="fa-solid fa-user-group"></i> <?= t_fmt('tour.group_size', ['max' => (int)$trip['max_group_size']]) ?></span>
      </div>
    </div>
  </section>

  <div class="container">
    <div class="detail-layout">

      <!-- ================= MAIN ================= -->
      <div class="detail-main">

        <!-- Is this trip for me? -->
        <section class="section">
          <div class="section-head"><h2><?= t('tour.overview', 'Overview') ?></h2><div class="eyebrow"></div></div>
          <div class="fit-grid">
            <div class="fit-row"><div class="ic"><i class="fa-solid fa-user"></i></div><div><b><?= t('tours.trip_age', 'Age range') ?></b><span><?= htmlspecialchars($trip['age_range']) ?></span></div></div>
            <div class="fit-row"><div class="ic"><i class="fa-solid fa-bolt"></i></div><div><b><?= t('tours.trip_physical', 'Physical effort') ?></b><span><?= ucfirst(htmlspecialchars($trip['effort_level'])) ?></span></div></div>
            <div class="fit-row"><div class="ic"><i class="fa-solid fa-house-chimney"></i></div><div><b><?= t('tours.trip_accommodation', 'Accommodation') ?></b><span><?= htmlspecialchars($trip['accommodation'] ?? 'Shared rooms') ?></span></div></div>
            <div class="fit-row"><div class="ic"><i class="fa-solid fa-bed"></i></div><div><b><?= t('tours.trip_comfort', 'Comfort level') ?></b><span><?= htmlspecialchars($trip['comfort_level'] ?? 'Standard') ?></span></div></div>
          </div>
        </section>

        <!-- Description -->
        <?php if ($trip['description']): ?>
        <section class="section">
          <p class="desc"><?= nl2br(htmlspecialchars($trip['description'])) ?></p>
        </section>
        <?php endif; ?>

        <!-- Itinerary -->
        <?php if (!empty($itinerary)): ?>
        <section class="section">
          <div class="section-head"><h2><?= t('tour.itinerary', 'Itinerary') ?></h2><div class="eyebrow"></div></div>
          <?php foreach ($itinerary as $day): ?>
          <details class="day-card" <?= (int)$day['day_number'] === 1 ? 'open' : '' ?>>
            <summary>
              <?php if ($day['image_url']): ?><img class="thumb" src="<?= htmlspecialchars($day['image_url']) ?>" alt=""><?php endif; ?>
              <div><span class="day-label"><?= t_fmt('tour.day', ['day' => (int)$day['day_number']]) ?></span><h4><?= htmlspecialchars($day['title']) ?></h4></div>
              <i class="fa-solid fa-chevron-down chev"></i>
            </summary>
            <div class="day-body"><?= nl2br(htmlspecialchars($day['description'])) ?></div>
          </details>
          <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <!-- Included / Excluded -->
        <?php if (!empty($included) || !empty($excluded)): ?>
        <section class="section">
          <div class="section-head"><h2><?= t('tours.trip_included', 'What\'s included') ?></h2><div class="eyebrow"></div></div>
          <div class="included-grid">
            <div class="included-col">
              <h4><?= t('tour.included', 'Included') ?></h4>
              <ul>
                <?php foreach ($included as $item): ?><li class="yes"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($item['item_text']) ?></li><?php endforeach; ?>
              </ul>
            </div>
            <div class="included-col">
              <h4><?= t('tour.excluded', 'Not included') ?></h4>
              <ul>
                <?php foreach ($excluded as $item): ?><li class="no"><i class="fa-solid fa-circle-xmark"></i> <?= htmlspecialchars($item['item_text']) ?></li><?php endforeach; ?>
              </ul>
            </div>
          </div>
        </section>
        <?php endif; ?>

        <!-- Gallery -->
        <?php if (count($gallery) > 1): ?>
        <section class="section">
          <div class="section-head"><h2><?= t('tour.gallery', 'Gallery') ?></h2><div class="eyebrow"></div></div>
          <div class="gallery-grid">
            <?php foreach ($gallery as $i => $img): ?>
              <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($trip['name']) ?> — <?= $i + 1 ?>" loading="lazy">
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>

        <!-- Optional activities -->
        <?php if (!empty($activities)): ?>
        <section class="section">
          <div class="section-head"><h2><?= t('tour.optional', 'Optional Activities') ?></h2><div class="eyebrow"></div></div>
          <?php foreach ($activities as $a): ?>
            <div class="act-item">
              <div><h4><?= htmlspecialchars($a['name']) ?></h4><?php if ($a['description']): ?><p><?= htmlspecialchars($a['description']) ?></p><?php endif; ?></div>
              <span class="price"><?= t_fmt('tour.optional_price', ['price' => number_format((float)$a['price'], 0)]) ?></span>
            </div>
          <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <!-- Departures -->
        <?php if (!empty($departures)): ?>
        <section class="section">
          <div class="section-head"><h2><?= t('tour.departures', 'Upcoming Departures') ?></h2><div class="eyebrow"></div></div>
          <p style="color:var(--muted);font-size:13.5px;margin:0 0 16px;"><?= t('tour.departures_sub', 'Pick a date that fits your plans.') ?></p>
          <?php foreach ($departures as $d): ?>
            <div class="dep-card">
              <div class="date"><?= date('M j, Y', strtotime($d['start_date'])) ?> <small><?= date('M j, Y', strtotime($d['end_date'])) ?></small></div>
              <div class="seats"><b><?= (int)$d['spots_left'] ?></b> <?= t_fmt('tour.spots_left', ['spots' => (int)$d['spots_left']]) ?></div>
              <div class="price"><?= $currency ?><?= number_format((float)$d['price'], 0) ?></div>
              <a class="gaia-btn" href="<?= gaia_url('weroad/booking.php') ?>?trip_id=<?= (int)$trip['id'] ?>&departure_id=<?= (int)$d['id'] ?>"><?= t('tour.book_now', 'Book Now') ?></a>
            </div>
          <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <!-- Reviews -->
        <?php if (!empty($reviews)): ?>
        <section class="section">
          <div class="section-head"><h2><?= t('tour.reviews_title', 'What travellers say') ?></h2><div class="eyebrow"></div></div>
          <div class="reviews-grid">
            <?php foreach ($reviews as $r): ?>
              <div class="review-card">
                <div class="stars"><?= str_repeat('★', (int)$r['rating']) ?></div>
                <h5><?= htmlspecialchars($r['author']) ?></h5>
                <p><?= htmlspecialchars($r['text']) ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>

        <!-- FAQ -->
        <?php if (!empty($faqGroups)): ?>
        <section class="section">
          <div class="section-head"><h2><?= t('tour.faq', 'Frequently Asked Questions') ?></h2><div class="eyebrow"></div></div>
          <?php foreach ($faqGroups as $groupName => $groupFaqs): ?>
            <h5 style="font-size:15px;margin:6px 0 10px;"><?= htmlspecialchars($groupName) ?></h5>
            <?php foreach ($groupFaqs as $faq): ?>
              <details class="faq-item"><summary><?= htmlspecialchars($faq['question']) ?> <i class="fa-solid fa-plus"></i></summary><div class="faq-body"><?= nl2br(htmlspecialchars($faq['answer'])) ?></div></details>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </section>
        <?php endif; ?>

      </div>

      <!-- ================= STICKY SIDEBAR ================= -->
      <aside class="sidebar">
        <div class="book-card">
          <div class="from"><?= t('tour.from', 'From') ?></div>
<div class="amount"><?= $currency ?><?= number_format((float)$trip['base_price'], 0) ?> <span class="per"><?= t('tour.per_person', 'per person') ?></span></div>
          <?php if (!empty($departures)): $next = $departures[0]; ?>
            <div class="next">
              <span style="color:var(--muted);font-size:12px;"><?= t('tour.next_departure', 'Next Departure') ?></span>
              <b><?= date('M j, Y', strtotime($next['start_date'])) ?></b>
              <span style="color:var(--muted);"><?= t_fmt('tour.spots_left', ['spots' => (int)$next['spots_left']]) ?></span>
            </div>
          <?php endif; ?>
          <div class="cta">
            <?php if (!empty($departures)): ?>
              <a class="gaia-btn" href="<?= gaia_url('weroad/booking.php') ?>?trip_id=<?= (int)$trip['id'] ?>&departure_id=<?= (int)$departures[0]['id'] ?>"><?= t('tour.book_now', 'Book Now') ?></a>
            <?php endif; ?>
            <a class="gaia-btn gaia-btn-ghost" href="https://wa.me/<?= $whatsapp ?>" target="_blank" rel="noopener"><?= t('tour.contact', 'Ask an expert') ?></a>
          </div>
          <div class="divider"></div>
          <div class="info-line"><i class="fa-solid fa-shield-halved"></i> <?= t('tours.trip_insurance', 'Medical & baggage insurance included') ?></div>
          <div class="info-line"><i class="fa-solid fa-cart-arrow-down"></i> <?= t('tours.trip_installments', 'Interest-free installments') ?></div>
          <div class="info-line"><i class="fa-solid fa-rotate-left"></i> <?= t('tours.trip_confidence_sub', 'Free cancellation up to 30 days before') ?></div>
        </div>
      </aside>

    </div>

    <!-- ================= RELATED TOURS ================= -->
    <?php if (!empty($relatedTours)): ?>
    <section class="section">
      <div class="section-head"><h2><?= t('tour.related', 'Related Tours') ?></h2><div class="eyebrow"></div></div>
      <div class="tour-row">
        <?php foreach ($relatedTours as $rt): ?>
        <a class="tour-card" href="<?= gaia_url('tour.php') ?>?slug=<?= urlencode($rt['slug']) ?>">
          <div class="media"><img src="<?= htmlspecialchars($rt['image_url']) ?>" alt="<?= htmlspecialchars($rt['name']) ?>" loading="lazy"></div>
          <div class="body">
            <h3><?= htmlspecialchars($rt['name']) ?></h3>
            <span class="price"><?= t('tour.from', 'From') ?> $<?= number_format((float)$rt['base_price'], 0) ?></span>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

  </div>

<?php endif; ?>

<?php require __DIR__ . '/components/gaia-footer.php'; ?>
<script src="assets/gaia.js"></script>
</body>
</html>

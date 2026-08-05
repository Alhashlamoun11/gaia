<?php
/**
 * hotel.php — Hotel Details page (dynamic)
 * ------------------------------------------------------------
 * Route:  hotel.php?slug={slug}
 * Example: hotel.php?slug=st-regis-amman
 *
 * Loads a hotel from the `hotels` table plus its facilities,
 * gallery, rooms, offers, reviews, nearby hotels and related
 * tours. Shares the GAIA header/footer, EN/AR + dynamic SEO.
 * 404 handled gracefully.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/components/gaia-config.php';

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
    $roomStmt = $pdo->prepare("SELECT * FROM hotel_rooms WHERE hotel_id = :id AND is_active = 1 ORDER BY price");
    $roomStmt->execute([':id' => $hotelId]);
    $rooms = $roomStmt->fetchAll();

    // Offers
    $offStmt = $pdo->prepare("SELECT * FROM hotel_offers WHERE hotel_id = :id AND is_active = 1 ORDER BY sort_order");
    $offStmt->execute([':id' => $hotelId]);
    $offers = $offStmt->fetchAll();

    // Reviews
    $revStmt = $pdo->prepare("SELECT * FROM hotel_reviews WHERE hotel_id = :id AND is_active = 1 ORDER BY id");
    $revStmt->execute([':id' => $hotelId]);
    $reviews = $revStmt->fetchAll();

    // Nearby hotels (same destination, excluding current)
    $nearStmt = $pdo->prepare("SELECT * FROM hotels WHERE destination_id = :dest AND id != :id AND is_active = 1 ORDER BY price_from LIMIT 3");
    $nearStmt->execute([':dest' => (int)$hotel['destination_id'], ':id' => $hotelId]);
    $nearby = $nearStmt->fetchAll();

    // Related tours (in same destination)
    $toursStmt = $pdo->prepare(
        "SELECT t.* FROM weroad_trips t
         LEFT JOIN tour_destinations td ON td.id = t.destination_id
         LEFT JOIN destinations d ON LOWER(d.name) = LOWER(td.name)
         WHERE d.id = :dest AND t.is_active = 1
         ORDER BY t.rating DESC LIMIT 4"
    );
    $toursStmt->execute([':dest' => (int)$hotel['destination_id']]);
    $relatedTours = $toursStmt->fetchAll();
    if (empty($relatedTours)) {
        $relatedTours = $pdo->query("SELECT * FROM weroad_trips WHERE is_active = 1 ORDER BY rating DESC LIMIT 4")->fetchAll();
    }
}

// Dynamic SEO
if (!$is404) {
    $seoTitle = ($hotel['name'] ?? 'Hotel') . ' — GAIA TOURS & TRAVEL';
    $seoDesc  = mb_strimwidth(strip_tags($hotel['description'] ?? ''), 0, 150, '…');
    $seoImage = $gallery[0];
    // Average review rating
    $avgRating = (float)($hotel['avg_rating'] ?? 0);
} else {
    $seoTitle = t('detail.not_found', 'Not Found') . ' — GAIA TOURS & TRAVEL';
    $seoDesc  = t('detail.not_found_text', 'The page you are looking for does not exist.');
    $seoImage = '';
    $avgRating = 0;
}

$currency = site_setting('currency_symbol', '$');
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
  h1, h2, h3, h4 { font-family: 'Playfair Display', serif; margin: 0; }
  a { text-decoration: none; color: inherit; }
  ul { list-style: none; margin: 0; padding: 0; }
  img { max-width: 100%; display: block; }
  .container { max-width: 1280px; margin: 0 auto; padding: 0 24px; }

  /* ---------- Hero ---------- */
  .detail-hero { position: relative; min-height: 460px; display: flex; align-items: flex-end; background: linear-gradient(180deg, rgba(20,22,40,.42), rgba(20,22,40,.82)), url('<?= htmlspecialchars($gallery[0] ?? '') ?>') center 40%/cover no-repeat; color: #fff; }
  .detail-hero-inner { width: 100%; padding: 150px 0 50px; }
  .gaia-breadcrumb { display: flex; align-items: center; gap: 8px; font-size: 13px; color: rgba(255,255,255,.85); margin-bottom: 18px; flex-wrap: wrap; }
  .gaia-breadcrumb a { color: rgba(255,255,255,.85); text-underline-offset: 3px; text-decoration: underline; }
  .gaia-breadcrumb .sep { opacity: .5; }
  .detail-hero h1 { font-size: 42px; font-weight: 700; max-width: 760px; }
  .hero-stars { color: var(--gaia-gold); font-size: 17px; margin-top: 10px; letter-spacing: 2px; }
  .hero-loc { display: inline-flex; align-items: center; gap: 8px; margin-top: 14px; background: rgba(255,255,255,.14); padding: 8px 16px; border-radius: 999px; font-size: 14px; }

  /* ---------- Layout ---------- */
  .detail-layout { display: grid; grid-template-columns: 1fr 340px; gap: 34px; padding: 50px 0 20px; align-items: start; }
  .section { padding: 0 0 38px; }
  .section-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 22px; }
  .section-head h2 { font-size: 25px; font-weight: 700; }
  .eyebrow { width: 48px; height: 4px; background: var(--gaia-accent); border-radius: 4px; margin-top: 10px; }
  .desc { font-size: 15px; line-height: 1.9; color: #3a3d48; }

  /* ---------- Facilities ---------- */
  .fac-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
  .fac-item { display: flex; align-items: center; gap: 10px; background: var(--warm); border: 1px solid var(--line); border-radius: 10px; padding: 12px 14px; font-size: 13.5px; }
  .fac-item i { color: var(--gaia-accent); width: 18px; text-align: center; }

  /* ---------- Gallery ---------- */
  .gallery-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
  .gallery-grid img { width: 100%; height: 200px; object-fit: cover; border-radius: 10px; }

  /* ---------- Rooms ---------- */
  .rooms-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; }
  .room-card { border: 1px solid var(--line); border-radius: 14px; overflow: hidden; background: #fff; box-shadow: var(--gaia-shadow); display: block; transition: transform .2s, box-shadow .2s; }
  .room-card:hover { transform: translateY(-3px); box-shadow: var(--gaia-shadow-lg); }
  .room-card .media { aspect-ratio: 16/10; overflow: hidden; }
  .room-card .media img { width: 100%; height: 100%; object-fit: cover; }
  .room-card .body { padding: 16px; }
  .room-card h3 { font-size: 16px; margin-bottom: 6px; }
  .room-card .meta { font-size: 12.5px; color: var(--muted); display: flex; gap: 14px; margin-bottom: 10px; }
  .room-card .price { font-weight: 700; color: var(--gaia-accent); }
  .room-card .price small { font-weight: 400; color: var(--muted); }

  /* ---------- Offers / Reviews ---------- */
  .offer-card { border: 1px solid var(--line); border-radius: 12px; padding: 16px; background: var(--warm); margin-top: 12px; }
  .offer-card h4 { font-size: 16px; margin-bottom: 4px; }
  .offer-card p { color: var(--muted); font-size: 13px; margin: 0 0 8px; line-height: 1.6; }
  .offer-card .price { font-weight: 700; color: var(--gaia-gold-deep); }

  .review-card { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 18px; }
  .review-card .stars { color: var(--gaia-gold); font-size: 13px; margin-bottom: 6px; }
  .review-card h5 { font-size: 14px; display: flex; justify-content: space-between; }
  .review-card h5 span { font-weight: 400; color: var(--muted); font-size: 11.5px; }
  .review-card p { font-size: 13px; color: #42405a; line-height: 1.6; margin: 8px 0 0; }
  .reviews-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }

  /* ---------- Sidebar ---------- */
  .sidebar { position: sticky; top: 90px; display: flex; flex-direction: column; gap: 14px; }
  .book-card { background: var(--warm); border: 1px solid var(--line); border-radius: 16px; padding: 24px; }
  .book-card .from { font-size: 13px; color: var(--muted); }
  .book-card .amount { font-family: 'Playfair Display', serif; font-size: 34px; font-weight: 700; color: var(--gaia-accent); }
  .book-card .per { font-size: 13px; color: var(--muted); }
  .book-card .rate { display: flex; align-items: center; gap: 8px; margin-top: 14px; font-size: 14px; }
  .book-card .rate .stars { color: var(--gaia-gold); }
  .book-card .cta { margin-top: 18px; display: flex; flex-direction: column; gap: 10px; }

  /* ---------- Tour cards ---------- */
  .tour-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
  .tour-card { border: 1px solid var(--line); border-radius: 12px; overflow: hidden; background: #fff; box-shadow: var(--gaia-shadow); display: block; transition: transform .2s; }
  .tour-card:hover { transform: translateY(-3px); }
  .tour-card .media { aspect-ratio: 4/3; overflow: hidden; }
  .tour-card .media img { width: 100%; height: 100%; object-fit: cover; }
  .tour-card .body { padding: 14px; }
  .tour-card h3 { font-size: 14px; line-height: 1.35; margin-bottom: 8px; }
  .tour-card .price { font-weight: 700; color: var(--gaia-accent); font-size: 14px; }

  /* ---------- 404 ---------- */
  .not-found { text-align: center; padding: 110px 24px; }
  .not-found .code { font-family: 'Playfair Display', serif; font-size: 84px; color: var(--gaia-accent); font-weight: 800; line-height: 1; }
  .not-found h1 { font-size: 30px; margin: 18px 0 10px; }
  .not-found p { color: var(--muted); max-width: 480px; margin: 0 auto 28px; }

  @media (max-width: 992px) { .detail-layout { grid-template-columns: 1fr; } .sidebar { position: static; } .fac-grid, .tour-row { grid-template-columns: 1fr 1fr; } }
  @media (max-width: 720px) {
    .detail-hero h1 { font-size: 30px; }
    .gallery-grid, .rooms-grid, .reviews-grid, .fac-grid, .tour-row { grid-template-columns: 1fr; }
    .section { padding-bottom: 30px; }
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
    <a class="gaia-btn" href="<?= gaia_url('index.php') ?>"><?= t('detail.back_to_hotels', 'Back to Hotels') ?></a>
  </section>
<?php else: ?>

  <!-- ================= HERO ================= -->
  <section class="detail-hero">
    <div class="container detail-hero-inner">
      <nav class="gaia-breadcrumb">
        <a href="<?= gaia_url('index.php') ?>"><?= t('detail.home', 'Home') ?></a>
        <span class="sep">/</span>
        <a href="<?= gaia_url('index.php') ?>"><?= t('detail.hotels', 'Hotels') ?></a>
        <span class="sep">/</span>
        <span><?= htmlspecialchars($hotel['name']) ?></span>
      </nav>
      <h1><?= htmlspecialchars($hotel['name']) ?></h1>
      <div class="hero-stars"><?= str_repeat('★', (int)$hotel['star_rating']) ?></div>
      <?php if (!empty($hotel['location'])): ?>
        <span class="hero-loc"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($hotel['location']) ?></span>
      <?php endif; ?>
    </div>
  </section>

  <div class="container">
    <div class="detail-layout">

      <!-- ================= MAIN ================= -->
      <div class="detail-main">

        <!-- Description -->
        <section class="section">
          <div class="section-head"><h2><?= t('hotel.description', 'About the Hotel') ?></h2><div class="eyebrow"></div></div>
          <?php if ($hotel['description']): ?><p class="desc"><?= nl2br(htmlspecialchars($hotel['description'])) ?></p><?php endif; ?>
        </section>

        <!-- Facilities -->
        <?php if (!empty($facilities)): ?>
        <section class="section">
          <div class="section-head"><h2><?= t('hotel.facilities', 'Hotel Facilities') ?></h2><div class="eyebrow"></div></div>
          <div class="fac-grid">
            <?php foreach ($facilities as $f): ?>
              <div class="fac-item"><i class="<?= htmlspecialchars($f['icon'] ?? 'fa-solid fa-check') ?>"></i> <?= htmlspecialchars($f['name']) ?></div>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>

        <!-- Gallery -->
        <?php if (count($gallery) > 1): ?>
        <section class="section">
          <div class="section-head"><h2><?= t('hotel.gallery', 'Gallery') ?></h2><div class="eyebrow"></div></div>
          <div class="gallery-grid">
            <?php foreach ($gallery as $i => $img): ?>
              <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($hotel['name']) ?> — <?= $i + 1 ?>" loading="lazy">
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>

        <!-- Rooms -->
        <?php if (!empty($rooms)): ?>
        <section class="section">
          <div class="section-head"><h2><?= t('hotel.rooms', 'Available Rooms') ?></h2><div class="eyebrow"></div></div>
          <div class="rooms-grid">
            <?php foreach ($rooms as $r): ?>
            <a class="room-card" href="<?= gaia_url('room.php') ?>?id=<?= (int)$r['id'] ?>">
              <div class="media"><img src="<?= htmlspecialchars($r['image_url']) ?>" alt="<?= htmlspecialchars($r['name']) ?>" loading="lazy"></div>
              <div class="body">
                <h3><?= htmlspecialchars($r['name']) ?></h3>
                <div class="meta">
                  <span><i class="fa-solid fa-user"></i> <?= (int)$r['capacity'] ?></span>
                  <span><i class="fa-solid fa-bed"></i> <?= htmlspecialchars($r['beds']) ?></span>
                  <?php if (!empty($r['size_sqm'])): ?><span><i class="fa-solid fa-ruler-combined"></i> <?= (int)$r['size_sqm'] ?> m²</span><?php endif; ?>
                </div>
                <span class="price"><?= $currency ?><?= number_format((float)$r['price'], 0) ?> <small><?= t('hotel.per_night', '/ night') ?></small></span>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>

        <!-- Offers -->
        <?php if (!empty($offers)): ?>
        <section class="section">
          <div class="section-head"><h2><?= t('hotel.offers', 'Hotel Offers') ?></h2><div class="eyebrow"></div></div>
          <?php foreach ($offers as $o): ?>
            <div class="offer-card">
              <h4><?= htmlspecialchars($o['title']) ?></h4>
              <?php if ($o['description']): ?><p><?= htmlspecialchars($o['description']) ?></p><?php endif; ?>
              <span class="price"><?= $currency ?><?= number_format((float)$o['price'], 0) ?></span>
            </div>
          <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <!-- Reviews -->
        <?php if (!empty($reviews)): ?>
        <section class="section">
          <div class="section-head"><h2><?= t('hotel.reviews_title', 'Guest Reviews') ?></h2><div class="eyebrow"></div></div>
          <div class="reviews-grid">
            <?php foreach ($reviews as $r): ?>
              <div class="review-card">
                <div class="stars"><?= str_repeat('★', (int)$r['rating']) ?></div>
                <h5><?= htmlspecialchars($r['reviewer']) ?> <span><?= htmlspecialchars($r['date_label'] ?? '') ?></span></h5>
                <p><?= htmlspecialchars($r['text']) ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>

      </div>

      <!-- ================= SIDEBAR ================= -->
      <aside class="sidebar">
        <div class="book-card">
          <div class="from"><?= t('hotel.from', 'From') ?></div>
          <div class="amount"><?= $currency ?><?= number_format((float)$hotel['price_from'], 0) ?> <span class="per"><?= t('hotel.per_night', '/ night') ?></span></div>
          <div class="rate">
            <span class="stars"><?= str_repeat('★', (int)$hotel['star_rating']) ?></span>
            <?php if ($avgRating > 0): ?>
              <strong><?= number_format($avgRating, 1) ?></strong>
              <span style="color:var(--muted);font-size:13px;">(<?= count($reviews) ?> <?= t('hotel.reviews', 'reviews') ?>)</span>
            <?php endif; ?>
          </div>
          <div class="cta">
            <a class="gaia-btn" href="<?= gaia_url('contact') ?>"><?= t('hotel.quick_booking', 'Quick Booking') ?></a>
            <a class="gaia-btn gaia-btn-ghost" href="https://wa.me/<?= htmlspecialchars(site_setting('contact_whatsapp', '962790123456')) ?>" target="_blank" rel="noopener"><?= t('hotel.contact', 'Contact Hotel') ?></a>
          </div>
        </div>
      </aside>

    </div>

    <!-- ================= RELATED ================= -->
    <?php if (!empty($relatedTours)): ?>
    <section class="section">
      <div class="section-head"><h2><?= t('hotel.tours_here', 'Tours in this Destination') ?></h2><div class="eyebrow"></div></div>
      <div class="tour-row">
        <?php foreach ($relatedTours as $t): ?>
        <a class="tour-card" href="<?= gaia_url('tour.php') ?>?slug=<?= urlencode($t['slug']) ?>">
          <div class="media"><img src="<?= htmlspecialchars($t['image_url']) ?>" alt="<?= htmlspecialchars($t['name']) ?>" loading="lazy"></div>
          <div class="body">
            <h3><?= htmlspecialchars($t['name']) ?></h3>
            <span class="price"><?= t('tour.from', 'From') ?> $<?= number_format((float)$t['base_price'], 0) ?></span>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($nearby)): ?>
    <section class="section">
      <div class="section-head"><h2><?= t('hotel.nearby', 'Nearby Hotels') ?></h2><div class="eyebrow"></div></div>
      <div class="tour-row">
        <?php foreach ($nearby as $n): ?>
        <a class="tour-card" href="<?= gaia_url('hotel.php') ?>?slug=<?= urlencode($n['slug']) ?>">
          <div class="media"><img src="<?= htmlspecialchars($n['image_url']) ?>" alt="<?= htmlspecialchars($n['name']) ?>" loading="lazy"></div>
          <div class="body">
            <h3><?= htmlspecialchars($n['name']) ?></h3>
            <span class="price"><?= t('hotel.from', 'From') ?> $<?= number_format((float)$n['price_from'], 0) ?></span>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

  </div>

<?php endif; ?>

<?php require __DIR__ . '/components/gaia-footer.php'; ?>
<script src="/assets/gaia.js"></script>
</body>
</html>

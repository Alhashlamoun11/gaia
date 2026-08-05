<?php
/**
 * room.php — Room Details page (dynamic)
 * ------------------------------------------------------------
 * Route:  room.php?id={id}
 * Example: room.php?id=15
 *
 * Loads a room from `hotel_rooms` plus its hotel info, gallery,
 * facilities and related rooms. Shares the GAIA header/footer,
 * EN/AR + dynamic SEO. 404 handled gracefully.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/components/gaia-config.php';

$gaia_base          = '';
$gaia_active        = 'hotels';
$gaia_header_style  = 'solid';

$id = (int)($_GET['id'] ?? 0);
$room = null;

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM hotel_rooms WHERE id = :id AND is_active = 1 LIMIT 1");
    $stmt->execute([':id' => $id]);
    $room = $stmt->fetch();
} catch (PDOException $e) {
    $room = null;
}

if (!$room) {
    http_response_code(404);
    $is404 = true;
    $gallery = [];
    $hotel = null;
    $relatedRooms = [];
} else {
    $is404 = false;
    $roomId = (int)$room['id'];

    // Gallery
    $gallery = $room['gallery_urls'] !== null
        ? array_filter(array_map('trim', explode(',', $room['gallery_urls'])))
        : [];
    if (empty($gallery) && $room['image_url']) {
        $gallery = [$room['image_url']];
    }
    if (empty($gallery)) {
        $gallery = ['https://images.unsplash.com/photo-1566665797739-1674de7a421a?w=900&q=80'];
    }

    // Facilities (comma-separated)
    $facilities = $room['facilities'] !== null
        ? array_filter(array_map('trim', explode(',', $room['facilities'])))
        : [];

    // Hotel info
    $hotel = null;
    if ((int)$room['hotel_id'] > 0) {
        $hstmt = $pdo->prepare("SELECT * FROM hotels WHERE id = :id AND is_active = 1 LIMIT 1");
        $hstmt->execute([':id' => (int)$room['hotel_id']]);
        $hotel = $hstmt->fetch() ?: null;
    }

    // Related rooms (same hotel, excluding current)
    $rstmt = $pdo->prepare("SELECT * FROM hotel_rooms WHERE hotel_id = :hid AND id != :rid AND is_active = 1 ORDER BY price LIMIT 3");
    $rstmt->execute([':hid' => (int)$room['hotel_id'], ':rid' => $roomId]);
    $relatedRooms = $rstmt->fetchAll();
}

// Dynamic SEO
if (!$is404) {
    $seoTitle = ($room['name'] ?? 'Room') . ' — GAIA TOURS & TRAVEL';
    $seoDesc  = mb_strimwidth(strip_tags($room['description'] ?? ''), 0, 150, '…');
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
  h1, h2, h3, h4 { font-family: 'Playfair Display', serif; margin: 0; }
  a { text-decoration: none; color: inherit; }
  ul { list-style: none; margin: 0; padding: 0; }
  img { max-width: 100%; display: block; }
  .container { max-width: 1280px; margin: 0 auto; padding: 0 24px; }

  /* ---------- Hero ---------- */
  .detail-hero { position: relative; min-height: 440px; display: flex; align-items: flex-end; background: linear-gradient(180deg, rgba(20,22,40,.42), rgba(20,22,40,.82)), url('<?= htmlspecialchars($gallery[0] ?? '') ?>') center 40%/cover no-repeat; color: #fff; }
  .detail-hero-inner { width: 100%; padding: 150px 0 50px; }
  .gaia-breadcrumb { display: flex; align-items: center; gap: 8px; font-size: 13px; color: rgba(255,255,255,.85); margin-bottom: 18px; flex-wrap: wrap; }
  .gaia-breadcrumb a { color: rgba(255,255,255,.85); text-underline-offset: 3px; text-decoration: underline; }
  .gaia-breadcrumb .sep { opacity: .5; }
  .detail-hero h1 { font-size: 40px; font-weight: 700; max-width: 760px; }
  .hero-price { display: inline-flex; align-items: baseline; gap: 6px; margin-top: 16px; background: rgba(255,255,255,.16); padding: 10px 18px; border-radius: 12px; backdrop-filter: blur(4px); }
  .hero-price .amount { font-family: 'Playfair Display', serif; font-size: 28px; font-weight: 700; }
  .hero-price .per { font-size: 13px; opacity: .85; }

  /* ---------- Layout ---------- */
  .detail-layout { display: grid; grid-template-columns: 1fr 340px; gap: 34px; padding: 46px 0 20px; align-items: start; }
  .section { padding: 0 0 38px; }
  .section-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
  .section-head h2 { font-size: 24px; font-weight: 700; }
  .eyebrow { width: 48px; height: 4px; background: var(--gaia-accent); border-radius: 4px; margin-top: 10px; }
  .desc { font-size: 15px; line-height: 1.9; color: #3a3d48; }

  /* ---------- Specs ---------- */
  .spec-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
  .spec-item { background: var(--warm); border: 1px solid var(--line); border-radius: 12px; padding: 18px; text-align: center; }
  .spec-item i { font-size: 22px; color: var(--gaia-accent); }
  .spec-item b { display: block; margin-top: 8px; font-size: 15px; }
  .spec-item span { font-size: 12px; color: var(--muted); }

  /* ---------- Facilities ---------- */
  .fac-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
  .fac-item { display: flex; align-items: center; gap: 10px; background: var(--warm); border: 1px solid var(--line); border-radius: 10px; padding: 11px 14px; font-size: 13.5px; }
  .fac-item i { color: var(--gaia-accent); width: 18px; text-align: center; }

  /* ---------- Gallery ---------- */
  .gallery-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
  .gallery-grid img { width: 100%; height: 200px; object-fit: cover; border-radius: 10px; }

  /* ---------- Hotel info ---------- */
  .hotel-card { display: flex; gap: 22px; background: var(--warm); border: 1px solid var(--line); border-radius: 14px; overflow: hidden; align-items: center; }
  .hotel-card img { width: 260px; height: 170px; object-fit: cover; flex: none; }
  .hotel-card .body { padding: 20px 20px 20px 0; }
  .hotel-card h3 { font-size: 20px; margin-bottom: 6px; }
  .hotel-card .stars { color: var(--gaia-gold); font-size: 14px; margin-bottom: 6px; }
  .hotel-card p { color: var(--muted); font-size: 13px; line-height: 1.6; margin: 0 0 12px; }

  /* ---------- Related rooms ---------- */
  .rooms-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
  .room-card { border: 1px solid var(--line); border-radius: 12px; overflow: hidden; background: #fff; box-shadow: var(--gaia-shadow); display: block; transition: transform .2s; }
  .room-card:hover { transform: translateY(-3px); }
  .room-card .media { aspect-ratio: 16/10; overflow: hidden; }
  .room-card .media img { width: 100%; height: 100%; object-fit: cover; }
  .room-card .body { padding: 14px; }
  .room-card h3 { font-size: 14.5px; margin-bottom: 8px; }
  .room-card .price { font-weight: 700; color: var(--gaia-accent); font-size: 14px; }

  /* ---------- Sidebar ---------- */
  .sidebar { position: sticky; top: 90px; display: flex; flex-direction: column; gap: 14px; }
  .book-card { background: var(--warm); border: 1px solid var(--line); border-radius: 16px; padding: 24px; }
  .book-card .from { font-size: 13px; color: var(--muted); }
  .book-card .amount { font-family: 'Playfair Display', serif; font-size: 34px; font-weight: 700; color: var(--gaia-accent); }
  .book-card .per { font-size: 13px; color: var(--muted); }
  .book-card .cta { margin-top: 18px; display: flex; flex-direction: column; gap: 10px; }

  /* ---------- 404 ---------- */
  .not-found { text-align: center; padding: 110px 24px; }
  .not-found .code { font-family: 'Playfair Display', serif; font-size: 84px; color: var(--gaia-accent); font-weight: 800; line-height: 1; }
  .not-found h1 { font-size: 30px; margin: 18px 0 10px; }
  .not-found p { color: var(--muted); max-width: 480px; margin: 0 auto 28px; }

  @media (max-width: 992px) { .detail-layout { grid-template-columns: 1fr; } .sidebar { position: static; } .spec-grid, .fac-grid, .rooms-row { grid-template-columns: 1fr 1fr; } }
  @media (max-width: 720px) {
    .detail-hero h1 { font-size: 28px; }
    .gallery-grid, .spec-grid, .fac-grid, .rooms-row { grid-template-columns: 1fr; }
    .hotel-card { flex-direction: column; }
    .hotel-card img { width: 100%; height: 180px; }
    .hotel-card .body { padding: 0 18px 20px; }
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
        <?php if ($hotel): ?>
          <a href="<?= gaia_url('hotel.php') ?>?slug=<?= urlencode($hotel['slug']) ?>"><?= htmlspecialchars($hotel['name']) ?></a>
        <?php else: ?>
          <a href="<?= gaia_url('index.php') ?>"><?= t('detail.hotels', 'Hotels') ?></a>
        <?php endif; ?>
        <span class="sep">/</span>
        <span><?= htmlspecialchars($room['name']) ?></span>
      </nav>
      <h1><?= htmlspecialchars($room['name']) ?></h1>
      <span class="hero-price">
        <span class="amount"><?= $currency ?><?= number_format((float)$room['price'], 0) ?></span>
        <span class="per"><?= t('room.per_night', '/ night') ?></span>
      </span>
    </div>
  </section>

  <div class="container">
    <div class="detail-layout">

      <!-- ================= MAIN ================= -->
      <div class="detail-main">

        <!-- Specs -->
        <section class="section">
          <div class="spec-grid">
            <div class="spec-item"><i class="fa-solid fa-user-group"></i><b><?= (int)$room['capacity'] ?></b><span><?= t('room.capacity', 'Capacity') ?></span></div>
            <div class="spec-item"><i class="fa-solid fa-bed"></i><b><?= htmlspecialchars($room['beds']) ?></b><span><?= t('room.beds', 'Beds') ?></span></div>
            <?php if (!empty($room['size_sqm'])): ?>
            <div class="spec-item"><i class="fa-solid fa-ruler-combined"></i><b><?= (int)$room['size_sqm'] ?> m²</b><span><?= t('room.size', 'Room Size') ?></span></div>
            <?php endif; ?>
          </div>
        </section>

        <!-- Description -->
        <section class="section">
          <div class="section-head"><h2><?= t('room.description', 'About this Room') ?></h2><div class="eyebrow"></div></div>
          <?php if ($room['description']): ?><p class="desc"><?= nl2br(htmlspecialchars($room['description'])) ?></p><?php endif; ?>
        </section>

        <!-- Facilities -->
        <?php if (!empty($facilities)): ?>
        <section class="section">
          <div class="section-head"><h2><?= t('room.facilities', 'Room Facilities') ?></h2><div class="eyebrow"></div></div>
          <div class="fac-grid">
            <?php foreach ($facilities as $f): ?>
              <div class="fac-item"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($f) ?></div>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>

        <!-- Gallery -->
        <?php if (count($gallery) > 1): ?>
        <section class="section">
          <div class="section-head"><h2><?= t('room.gallery', 'Room Gallery') ?></h2><div class="eyebrow"></div></div>
          <div class="gallery-grid">
            <?php foreach ($gallery as $i => $img): ?>
              <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($room['name']) ?> — <?= $i + 1 ?>" loading="lazy">
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>

        <!-- Hotel info -->
        <?php if ($hotel): ?>
        <section class="section">
          <div class="section-head"><h2><?= t('room.hotel_info', 'The Hotel') ?></h2><div class="eyebrow"></div></div>
          <a class="hotel-card" href="<?= gaia_url('hotel.php') ?>?slug=<?= urlencode($hotel['slug']) ?>">
            <?php if ($hotel['image_url']): ?><img src="<?= htmlspecialchars($hotel['image_url']) ?>" alt="<?= htmlspecialchars($hotel['name']) ?>" loading="lazy"><?php endif; ?>
            <div class="body">
              <h3><?= htmlspecialchars($hotel['name']) ?></h3>
              <div class="stars"><?= str_repeat('★', (int)$hotel['star_rating']) ?></div>
              <?php if ($hotel['description']): ?><p><?= mb_strimwidth(strip_tags($hotel['description']), 0, 120, '…') ?></p><?php endif; ?>
              <span class="gaia-btn gaia-btn-sm" style="pointer-events:none;"><?= t('hotel.view_room', 'View Hotel') ?></span>
            </div>
          </a>
        </section>
        <?php endif; ?>

        <!-- Related rooms -->
        <?php if (!empty($relatedRooms)): ?>
        <section class="section">
          <div class="section-head"><h2><?= t('room.related_rooms', 'More Rooms at this Hotel') ?></h2><div class="eyebrow"></div></div>
          <div class="rooms-row">
            <?php foreach ($relatedRooms as $rr): ?>
            <a class="room-card" href="<?= gaia_url('room.php') ?>?id=<?= (int)$rr['id'] ?>">
              <div class="media"><img src="<?= htmlspecialchars($rr['image_url']) ?>" alt="<?= htmlspecialchars($rr['name']) ?>" loading="lazy"></div>
              <div class="body">
                <h3><?= htmlspecialchars($rr['name']) ?></h3>
                <span class="price"><?= $currency ?><?= number_format((float)$rr['price'], 0) ?> <small style="color:var(--muted);font-weight:400;">/ <?= t('room.per_night', 'night') ?></small></span>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>

      </div>

      <!-- ================= SIDEBAR ================= -->
      <aside class="sidebar">
        <div class="book-card">
          <div class="from"><?= t('tour.from', 'From') ?></div>
          <div class="amount"><?= $currency ?><?= number_format((float)$room['price'], 0) ?> <span class="per"><?= t('room.per_night', '/ night') ?></span></div>
          <div class="cta">
            <a class="gaia-btn" href="<?= gaia_url('contact') ?>"><?= t('room.book', 'Book Room') ?></a>
            <a class="gaia-btn gaia-btn-ghost" href="https://wa.me/<?= $whatsapp ?>" target="_blank" rel="noopener"><?= t('room.contact', 'Contact Us') ?></a>
          </div>
        </div>
      </aside>

    </div>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/components/gaia-footer.php'; ?>
<script src="assets/gaia.js"></script>
</body>
</html>

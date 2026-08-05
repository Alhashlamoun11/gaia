<?php
/**
 * events.php — Event Details page (dynamic)
 * ------------------------------------------------------------
 * Route:  events.php?slug={slug}
 * Example: events.php?slug=wadi-rum-desert-gala
 *
 * Loads an event from the `events` table, plus its destination,
 * gallery and related tours. Shares the GAIA header/footer and
 * supports EN/AR + dynamic SEO. 404 handled gracefully.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/components/gaia-config.php';

$gaia_base          = '';
$gaia_active        = 'events';
$gaia_header_style  = 'solid';

$slug = trim($_GET['slug'] ?? '');
$event = null;

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM events WHERE slug = :slug AND is_active = 1 LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    $event = $stmt->fetch();
} catch (PDOException $e) {
    $event = null;
}

if (!$event) {
    http_response_code(404);
    $is404 = true;
} else {
    $is404 = false;

    // Gallery
    $gallery = $event['gallery_urls'] !== null
        ? array_filter(array_map('trim', explode(',', $event['gallery_urls'])))
        : [];
    if (empty($gallery) && $event['image_url']) {
        $gallery = [$event['image_url']];
    }
    if (empty($gallery)) {
        $gallery = ['https://images.unsplash.com/photo-1511578314322-379afb476865?w=900&q=80'];
    }

    // Related destination
    $destination = null;
    if ((int)$event['destination_id'] > 0) {
        $dstmt = $pdo->prepare("SELECT * FROM destinations WHERE id = :id AND is_active = 1 LIMIT 1");
        $dstmt->execute([':id' => (int)$event['destination_id']]);
        $destination = $dstmt->fetch() ?: null;
    }

    // Related tours (trips whose destination maps to this event's destination)
    $relatedTours = [];
    $dstmt = $pdo->prepare(
        "SELECT t.* FROM weroad_trips t
         LEFT JOIN tour_destinations td ON td.id = t.destination_id
         LEFT JOIN destinations d ON LOWER(d.name) = LOWER(td.name)
         WHERE d.id = :dest AND t.is_active = 1
         ORDER BY t.rating DESC LIMIT 4"
    );
    $dstmt->execute([':dest' => (int)$event['destination_id']]);
    $relatedTours = $dstmt->fetchAll();
    if (empty($relatedTours)) {
        // Fallback: top-rated tours
        $relatedTours = $pdo->query("SELECT * FROM weroad_trips WHERE is_active = 1 ORDER BY rating DESC LIMIT 4")->fetchAll();
    }
}

// Dynamic SEO
if (!$is404) {
    $seoTitle = ($event['title'] ?? 'Event') . ' — GAIA TOURS & TRAVEL';
    $seoDesc  = mb_strimwidth(strip_tags($event['description'] ?? ''), 0, 150, '…');
    $seoImage = $gallery[0];
} else {
    $seoTitle = t('detail.not_found', 'Not Found') . ' — GAIA TOURS & TRAVEL';
    $seoDesc  = t('detail.not_found_text', 'The page you are looking for does not exist.');
    $seoImage = '';
}
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
  :root {
    --navy: var(--gaia-navy); --teal: var(--gaia-accent); --ink: var(--gaia-ink);
    --muted: var(--gaia-muted); --line: var(--gaia-line); --warm: var(--gaia-warm);
  }
  * { box-sizing: border-box; }
  body { margin: 0; font-family: 'Inter', sans-serif; color: var(--ink); background: #fff; -webkit-font-smoothing: antialiased; }
  h1, h2, h3, h4 { font-family: 'Playfair Display', serif; margin: 0; }
  a { text-decoration: none; color: inherit; }
  ul { list-style: none; margin: 0; padding: 0; }
  img { max-width: 100%; display: block; }
  .container { max-width: 1280px; margin: 0 auto; padding: 0 24px; }

  /* ---------- Detail hero ---------- */
  .detail-hero {
    position: relative;
    min-height: 480px;
    display: flex;
    align-items: flex-end;
    background: linear-gradient(180deg, rgba(20, 22, 40, .42), rgba(20, 22, 40, .82)), url('<?= htmlspecialchars($gallery[0] ?? '') ?>') center 40%/cover no-repeat;
    color: #fff;
  }
  .detail-hero-inner { width: 100%; padding: 150px 0 56px; }
  .gaia-breadcrumb {
    display: flex; align-items: center; gap: 8px; font-size: 13px; color: rgba(255,255,255,.85);
    margin-bottom: 18px; flex-wrap: wrap;
  }
  .gaia-breadcrumb a { color: rgba(255,255,255,.85); text-underline-offset: 3px; text-decoration: underline; }
  .gaia-breadcrumb .sep { opacity: .5; }
  .detail-hero h1 { font-size: 44px; line-height: 1.12; font-weight: 700; max-width: 760px; }
  .detail-hero-meta { display: flex; flex-wrap: wrap; gap: 18px; margin-top: 22px; font-size: 14px; }
  .detail-hero-meta .meta-item { display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,.14); padding: 8px 16px; border-radius: 999px; backdrop-filter: blur(4px); }

  /* ---------- Sections ---------- */
  .section { padding: 64px 0 30px; }
  .section-head h2 { font-size: 28px; font-weight: 700; }
  .section-head .eyebrow { width: 48px; height: 4px; background: var(--gaia-accent); border-radius: 4px; margin: 12px 0 0; }

  .event-description {
    max-width: 860px; font-size: 15.5px; line-height: 1.9; color: #3a3d48; margin-top: 22px;
  }

  /* ---------- Gallery ---------- */
  .gallery-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 26px; }
  .gallery-grid img { width: 100%; height: 240px; object-fit: cover; border-radius: 12px; }

  /* ---------- Destination card ---------- */
  .dest-card {
    display: flex; gap: 26px; background: var(--warm); border: 1px solid var(--line);
    border-radius: 16px; overflow: hidden; margin-top: 26px; align-items: center;
  }
  .dest-card img { width: 300px; height: 200px; object-fit: cover; flex: none; }
  .dest-card .dest-body { padding: 26px 26px 26px 0; }
  .dest-card h3 { font-size: 22px; margin-bottom: 8px; }
  .dest-card p { color: var(--muted); font-size: 14px; line-height: 1.7; margin: 0; }

  /* ---------- Tour cards ---------- */
  .tour-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-top: 26px; }
  .tour-card { border: 1px solid var(--line); border-radius: 14px; overflow: hidden; background: #fff; box-shadow: var(--gaia-shadow); transition: transform .2s, box-shadow .2s; display: block; }
  .tour-card:hover { transform: translateY(-3px); box-shadow: var(--gaia-shadow-lg); }
  .tour-card .media { aspect-ratio: 4/3; overflow: hidden; }
  .tour-card .media img { width: 100%; height: 100%; object-fit: cover; }
  .tour-card .body { padding: 16px; }
  .tour-card h3 { font-size: 15.5px; line-height: 1.35; margin-bottom: 8px; }
  .tour-card .meta { display: flex; align-items: center; justify-content: space-between; font-size: 12.5px; color: var(--muted); }
  .tour-card .price { font-weight: 700; color: var(--gaia-accent); font-size: 15px; }

  /* ---------- CTA ---------- */
  .cta-band { background: linear-gradient(120deg, var(--gaia-navy), var(--gaia-royal)); border-radius: 18px; padding: 42px 40px; color: #fff; display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap; margin-top: 10px; }
  .cta-band h3 { font-size: 24px; }
  .cta-band p { color: rgba(255,255,255,.8); font-size: 14px; margin: 8px 0 0; }

  /* ---------- 404 ---------- */
  .not-found { text-align: center; padding: 110px 24px; }
  .not-found .code { font-family: 'Playfair Display', serif; font-size: 84px; color: var(--gaia-accent); font-weight: 800; line-height: 1; }
  .not-found h1 { font-size: 30px; margin: 18px 0 10px; }
  .not-found p { color: var(--muted); max-width: 480px; margin: 0 auto 28px; }

  @media (max-width: 992px) { .tour-row { grid-template-columns: 1fr 1fr; } }
  @media (max-width: 720px) {
    .detail-hero h1 { font-size: 30px; }
    .gallery-grid, .tour-row { grid-template-columns: 1fr; }
    .dest-card { flex-direction: column; }
    .dest-card img { width: 100%; height: 200px; }
    .dest-card .dest-body { padding: 0 20px 22px; }
    .section { padding-top: 44px; }
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
    <a class="gaia-btn" href="<?= gaia_url('gaia-night.php') ?>"><?= t('detail.back_to_events', 'Back to Events') ?></a>
  </section>
<?php else: ?>

  <!-- ================= HERO ================= -->
  <section class="detail-hero">
    <div class="container detail-hero-inner">
      <nav class="gaia-breadcrumb">
        <a href="<?= gaia_url('index.php') ?>"><?= t('detail.home', 'Home') ?></a>
        <span class="sep">/</span>
        <a href="<?= gaia_url('gaia-night.php') ?>"><?= t('detail.events', 'Events') ?></a>
        <span class="sep">/</span>
        <span><?= htmlspecialchars($event['title']) ?></span>
      </nav>
      <h1><?= htmlspecialchars($event['title']) ?></h1>
      <div class="detail-hero-meta">
        <?php if (!empty($event['date_label'])): ?>
          <span class="meta-item"><i class="fa-regular fa-calendar"></i> <?= htmlspecialchars($event['date_label']) ?></span>
        <?php endif; ?>
        <?php if (!empty($event['location'])): ?>
          <span class="meta-item"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($event['location']) ?></span>
        <?php endif; ?>
        <?php if (!empty($event['organizer'])): ?>
          <span class="meta-item"><i class="fa-solid fa-user-group"></i> <?= t('event.organizer', 'Organized by') ?>: <?= htmlspecialchars($event['organizer']) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- ================= DESCRIPTION ================= -->
  <section class="section">
    <div class="container">
      <div class="section-head">
        <h2><?= t('event.description', 'About this Event') ?></h2>
        <div class="eyebrow"></div>
      </div>
      <?php if ($event['description']): ?>
        <div class="event-description"><?= nl2br(htmlspecialchars($event['description'])) ?></div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ================= GALLERY ================= -->
  <?php if (count($gallery) > 1): ?>
  <section class="section">
    <div class="container">
      <div class="section-head">
        <h2><?= t('event.gallery', 'Gallery') ?></h2>
        <div class="eyebrow"></div>
      </div>
      <div class="gallery-grid">
        <?php foreach ($gallery as $i => $img): ?>
          <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($event['title']) ?> — <?= $i + 1 ?>">
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ================= RELATED DESTINATION ================= -->
  <?php if ($destination): ?>
  <section class="section">
    <div class="container">
      <div class="section-head">
        <h2><?= t('event.related_destination', 'About the Destination') ?></h2>
        <div class="eyebrow"></div>
      </div>
      <div class="dest-card">
        <?php if ($destination['image_url']): ?>
          <img src="<?= htmlspecialchars($destination['image_url']) ?>" alt="<?= htmlspecialchars($destination['name']) ?>">
        <?php endif; ?>
        <div class="dest-body">
          <h3><?= htmlspecialchars($destination['name']) ?><?= !empty($destination['country']) ? ', ' . htmlspecialchars($destination['country']) : '' ?></h3>
          <?php if ($destination['description']): ?><p><?= htmlspecialchars($destination['description']) ?></p><?php endif; ?>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ================= RELATED TOURS ================= -->
  <?php if (!empty($relatedTours)): ?>
  <section class="section">
    <div class="container">
      <div class="section-head">
        <h2><?= t('event.related_tours', 'Tours in this Destination') ?></h2>
        <div class="eyebrow"></div>
      </div>
      <div class="tour-row">
        <?php foreach ($relatedTours as $t): ?>
        <a class="tour-card" href="<?= gaia_url('tour.php') ?>?slug=<?= urlencode($t['slug']) ?>">
          <div class="media"><img src="<?= htmlspecialchars($t['image_url']) ?>" alt="<?= htmlspecialchars($t['name']) ?>" loading="lazy"></div>
          <div class="body">
            <h3><?= htmlspecialchars($t['name']) ?></h3>
            <div class="meta">
<span><i class="fa-regular fa-clock"></i> <?= t_fmt('tour.duration', ['days' => (int)$t['duration_days']]) ?></span>
              <span class="price"><?= t('tour.from', 'From') ?> $<?= number_format((float)$t['base_price'], 0) ?></span>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ================= CTA ================= -->
  <section class="section">
    <div class="container">
      <div class="cta-band">
        <div>
          <h3><?= htmlspecialchars($event['title']) ?></h3>
          <p><?= t('event.book_cta_sub', 'Limited seats — reserve your spot today.') ?></p>
        </div>
        <a class="gaia-btn gaia-btn-gold" href="<?= gaia_url('contact') ?>"><?= t('event.book_cta', 'Reserve Your Spot') ?></a>
      </div>
    </div>
  </section>

<?php endif; ?>

<?php require __DIR__ . '/components/gaia-footer.php'; ?>
<script src="assets/gaia.js"></script>
</body>
</html>


<?php
/**
 * gaia-night.php — GAIA Night premium landing page (fully dynamic).
 * ------------------------------------------------------------
 * GAIA Night is a SERVICE LINE inside the master GAIA brand.
 * All hero, services, packages, events and stats are loaded from
 * MySQL (banners, night_offerings, trust/build tables) so the
 * future Admin Panel can edit content without touching code.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/components/gaia-config.php';

$gaia_base          = '';
$gaia_active        = 'night';
$gaia_header_style  = 'overlay';

// ---- DB-driven content ----
$nightBanner = gaia_first_banner('night');
$nightServices = gaia_night_offerings('service');
$nightPackages = gaia_night_offerings('package');
$nightEvents   = gaia_night_offerings('event');

// Hero fallbacks (preserve original design)
$nightHeroTitle = $nightBanner && $nightBanner['title'] ? $nightBanner['title'] : 'Jordan\'s refined side, <em>after dark.</em>';
$nightHeroSub   = $nightBanner && $nightBanner['subtitle'] ? $nightBanner['subtitle'] : 'VIP transfers, luxury experiences, night events, private tours and premium packages — delivered by the same trusted GAIA team that runs your day journeys.';
$nightHeroImage = $nightBanner && $nightBanner['image_url'] ? $nightBanner['image_url'] : 'https://images.unsplash.com/photo-1502920917128-1aa500764cbd?w=1920&q=80';
$nightHeroCta   = $nightBanner && $nightBanner['cta_label'] ? $nightBanner['cta_label'] : 'Book an Experience';
$nightHeroCtaUrl = $nightBanner && $nightBanner['cta_url'] ? $nightBanner['cta_url'] : 'index.php';

// Hero stats from banner stats_json
$nightStats = [];
if ($nightBanner && $nightBanner['stats_json']) {
    $decoded = json_decode($nightBanner['stats_json'], true);
    if (is_array($decoded)) {
        $nightStats = $decoded;
    }
}
if (empty($nightStats)) {
    $nightStats = [
        ['value' => '24/7', 'label' => t('night.stat_concierge', 'Concierge')],
        ['value' => '100%', 'label' => t('night.stat_private', 'Private &amp; Discreet')],
        ['value' => '50+', 'label' => t('night.stat_curated', 'Curated Experiences')],
    ];
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags('night', 'GAIA Night — VIP Transfers, Luxury Experiences &amp; Premium Events') ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700;800&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<style>
  /* ---- GAIA Night premium accent layer (same GAIA design language) ---- */
  .night-hero {
    position: relative;
    min-height: 640px;
    display: flex;
    align-items: center;
    background:
      linear-gradient(180deg, rgba(20, 22, 29, 0.45) 0%, rgba(20, 22, 29, 0.35) 45%, rgba(20, 22, 29, 0.92) 100%),
      url('<?= htmlspecialchars($nightHeroImage) ?>') center 40%/cover no-repeat;
    color: #fff;
  }
  .night-hero-inner {
    max-width: 1280px;
    margin: 0 auto;
    padding: 170px 24px 90px;
    width: 100%;
  }
  .night-hero h1 {
    font-size: 50px;
    line-height: 1.1;
    margin: 18px 0 16px;
    color: #fff;
    max-width: 640px;
  }
  .night-hero h1 em {
    font-style: normal;
    background: linear-gradient(120deg, var(--gaia-gold), var(--gaia-gold-light));
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
  }
  .night-hero p {
    font-size: 17px;
    opacity: .92;
    max-width: 540px;
    margin: 0 0 30px;
    line-height: 1.7;
  }
  .night-hero-stats {
    display: flex;
    gap: 38px;
    flex-wrap: wrap;
    margin-top: 26px;
  }
  .night-hero-stats .stat b {
    display: block;
    font-family: var(--gaia-font-serif);
    font-size: 24px;
    color: var(--gaia-gold-light);
  }
  .night-hero-stats .stat span {
    font-size: 12.5px;
    opacity: .8;
    letter-spacing: .04em;
    text-transform: uppercase;
  }

  .night-services-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-top: 40px;
  }
  .night-service-card {
    background: rgba(255, 255, 255, 0.045);
    border: 1px solid rgba(201, 162, 39, 0.22);
    border-radius: var(--gaia-radius);
    padding: 24px;
    display: flex;
    gap: 14px;
    align-items: flex-start;
  }
  .night-service-card .ic {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: linear-gradient(120deg, var(--gaia-gold), var(--gaia-gold-deep));
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex: none;
  }
  .night-service-card strong { display: block; font-size: 15px; margin-bottom: 3px; }
  .night-service-card span { font-size: 12.8px; color: #c9c6d1; line-height: 1.55; }

  .package-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 22px;
    margin-top: 40px;
  }
  .package-card {
    background: #fff;
    border: 1px solid rgba(201, 162, 39, 0.3);
    border-radius: var(--gaia-radius);
    padding: 30px 26px;
    position: relative;
    overflow: hidden;
    transition: transform .2s ease, box-shadow .2s ease;
  }
  .package-card.featured {
    background: linear-gradient(150deg, #1b1e27, #2a2320);
    color: #fff;
    border-color: var(--gaia-gold);
    box-shadow: 0 24px 50px rgba(201, 162, 39, 0.2);
  }
  .package-card:hover { transform: translateY(-4px); box-shadow: var(--gaia-shadow-lg); }
  .package-card.featured:hover { box-shadow: 0 28px 60px rgba(201, 162, 39, 0.28); }
  .package-card .p-flag {
    position: absolute;
    top: 0;
    right: 0;
    background: linear-gradient(120deg, var(--gaia-gold), var(--gaia-gold-deep));
    color: #fff;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
    padding: 8px 18px;
    border-bottom-left-radius: 12px;
  }
  .package-card h3 { font-size: 20px; margin: 6px 0 8px; }
  .package-card.featured h3 { color: #fff; }
  .package-card p { font-size: 13.5px; color: var(--gaia-muted); line-height: 1.6; margin-bottom: 18px; }
  .package-card.featured p { color: #c9c6d1; }
  .package-card ul { list-style: none; margin: 0 0 22px; padding: 0; display: flex; flex-direction: column; gap: 10px; }
  .package-card li { display: flex; gap: 9px; align-items: flex-start; font-size: 13px; }
  .package-card li i { color: var(--gaia-gold-deep); margin-top: 2px; }
  .package-card.featured li i { color: var(--gaia-gold-light); }
  .package-card .price { font-family: var(--gaia-font-serif); font-size: 26px; font-weight: 700; color: var(--gaia-gold-deep); }
  .package-card.featured .price { color: var(--gaia-gold-light); }
  .package-card .per { font-size: 12px; color: var(--gaia-muted); }
  .package-card.featured .per { color: #c9c6d1; }

  .events-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; margin-top: 40px; }
  .event-card { position: relative; border-radius: var(--gaia-radius); overflow: hidden; height: 300px; }
  .event-card img { width: 100%; height: 100%; object-fit: cover; }
  .event-card .ev-overlay {
    position: absolute; left: 0; right: 0; bottom: 0;
    background: linear-gradient(180deg, transparent, rgba(10, 8, 20, 0.92) 75%);
    padding: 70px 20px 20px; color: #fff;
  }
  .event-card .ev-overlay h3 { font-size: 18px; margin: 6px 0 4px; color: #fff; }
  .event-card .ev-overlay span { font-size: 12.5px; color: var(--gaia-gold-light); font-weight: 700; }
  .event-card .ev-overlay p { font-size: 12.5px; color: #d8d6e0; margin: 0; line-height: 1.5; }

  @media (max-width: 1000px) {
    .night-services-row, .package-grid, .events-grid { grid-template-columns: 1fr 1fr; }
  }
  @media (max-width: 640px) {
    .night-services-row, .package-grid, .events-grid { grid-template-columns: 1fr; }
    .night-hero h1 { font-size: 34px; }
  }
</style>
</head>
<body>

<?php require __DIR__ . '/components/gaia-header.php'; ?>

<!-- ================= HERO (dark premium overlay) ================= -->
<section class="night-hero">
  <div class="night-hero-inner">
    <span class="gaia-vip-badge">&#11088; <?= t('night.hero_badge') ?></span>
    <h1><?= $nightHeroTitle ?></h1>
    <p><?= htmlspecialchars($nightHeroSub) ?></p>
    <a class="gaia-btn gaia-btn-gold" href="<?= gaia_url($nightHeroCtaUrl) ?>"><?= t('night.book_experience') ?></a>
    <a class="gaia-btn gaia-btn-ghost" style="margin-left:10px;background:rgba(255,255,255,.08);color:#fff;border-color:rgba(255,255,255,.35);" href="#packages"><?= t('night.view_packages') ?></a>
    <div class="night-hero-stats">
      <?php foreach ($nightStats as $st): ?>
        <div class="stat"><b><?= $st['value'] ?></b><span><?= $st['label'] ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= SERVICES ================= -->
<section class="gaia-section" style="background:var(--gaia-warm);">
  <div class="container">
    <h2 class="gaia-section-title"><?= t('night.services_title') ?></h2>
    <div class="gaia-eyebrow-divider gold"></div>
    <p class="gaia-section-sub"><?= t('night.services_sub') ?></p>
    <div class="night-services-row">
      <?php foreach ($nightServices as $i => $svc): ?>
        <?php if ($i === count($nightServices) - 1): ?>
          <div class="night-service-card gaia-night-hero" style="border:1px solid rgba(201,162,39,.35);">
            <div class="ic"><i class="<?= htmlspecialchars($svc['icon']) ?>"></i></div>
            <div><strong><?= htmlspecialchars($svc['title']) ?></strong><span><?= htmlspecialchars($svc['description']) ?></span></div>
          </div>
        <?php else: ?>
          <div class="night-service-card" style="background:#fff;border:1px solid rgba(201,162,39,.3);">
            <div class="ic"><i class="<?= htmlspecialchars($svc['icon']) ?>"></i></div>
            <div><strong><?= htmlspecialchars($svc['title']) ?></strong><span><?= htmlspecialchars($svc['description']) ?></span></div>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= PREMIUM PACKAGES ================= -->
<section class="gaia-section" id="packages" style="background:var(--gaia-warm-2);">
  <div class="container">
    <h2 class="gaia-section-title"><?= t('night.packages_title') ?></h2>
    <div class="gaia-eyebrow-divider gold"></div>
    <p class="gaia-section-sub"><?= t('night.packages_sub') ?></p>
    <div class="package-grid">
      <?php foreach ($nightPackages as $pkg): ?>
        <?php
          $features = [];
          if (!empty($pkg['features_json'])) {
            $features = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $pkg['features_json'])));
          }
          $isFeatured = (int)$pkg['is_featured'] === 1;
          $perLabel = $pkg['per_label'] ?? (t('night.package_per'));
          if (empty($perLabel)) {
            $perLabel = $pkg['title'] === 'Amman After Dark' ? t('night.package_per_couple') : t('night.package_per');
          }
        ?>
        <div class="package-card <?= $isFeatured ? 'featured' : '' ?>">
          <?php if ($isFeatured && $pkg['badge']): ?><span class="p-flag"><?= htmlspecialchars($pkg['badge']) ?></span><?php endif; ?>
          <h3><?= htmlspecialchars($pkg['title']) ?></h3>
          <p><?= htmlspecialchars($pkg['description']) ?></p>
          <?php if ($features): ?>
          <ul>
            <?php foreach ($features as $feat): ?>
              <li><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($feat) ?></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <span class="price"><?= htmlspecialchars($pkg['price_value']) ?></span> <span class="per"><?= htmlspecialchars($perLabel) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:36px;">
      <a class="gaia-btn gaia-btn-gold" href="<?= gaia_url('index.php') ?>"><?= t('night.book_package') ?></a>
    </div>
  </div>
</section>

<!-- ================= EVENTS ================= -->
<section class="gaia-section" style="background:var(--gaia-warm);">
  <div class="container">
    <h2 class="gaia-section-title"><?= t('night.events_title') ?></h2>
    <div class="gaia-eyebrow-divider gold"></div>
    <p class="gaia-section-sub"><?= t('night.events_sub') ?></p>
    <div class="events-grid">
      <?php foreach ($nightEvents as $ev): ?>
        <a class="event-card" href="<?= gaia_url('events.php') ?>?slug=<?= urlencode($ev['slug'] ?? '') ?>">
          <img src="<?= htmlspecialchars($ev['image_url']) ?>" alt="<?= htmlspecialchars($ev['title']) ?>">
          <div class="ev-overlay"><span><?= htmlspecialchars($ev['badge']) ?></span><h3><?= htmlspecialchars($ev['title']) ?></h3><p><?= htmlspecialchars($ev['description']) ?></p></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= BACK TO TOURS (cross-sell, same brand) ================= -->
<section class="gaia-section" style="padding-top:20px;">
  <div class="container">
    <div class="gaia-cross-sell gaia-cross-sell-night">
      <div class="gaia-cross-icon">&#127757;</div>
      <div class="gaia-cross-body">
        <strong><?= t('night.cross_title') ?></strong>
        <span><?= t('night.cross_text') ?></span>
      </div>
      <a class="gaia-cross-btn" href="<?= gaia_url('weroad/index.php') ?>"><?= t('night.cross_btn') ?></a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/components/gaia-footer.php'; ?>

<script src="/assets/gaia.js"></script>
</body>
</html>


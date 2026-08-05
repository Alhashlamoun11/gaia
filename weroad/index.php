<?php
/**
 * index.php — WeRoad homepage
 * ------------------------------------------------------------
 * Dynamically renders deals, categories, loved trips, and
 * reviews pulled from the MySQL database (weroad_* tables).
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../components/gaia-config.php';

try {
    $pdo = getWeRoadPDO();

    // Last-minute deals (trips with a discount)
    $dealsStmt = $pdo->query(
        "SELECT * FROM weroad_trips
         WHERE is_active = 1 AND discount_percent > 0
         ORDER BY discount_percent DESC
         LIMIT 5"
    );
    $deals = $dealsStmt->fetchAll();

    // Loved adventures / all active trips
    $tripsStmt = $pdo->query(
        "SELECT * FROM weroad_trips
         WHERE is_active = 1
         ORDER BY rating DESC
         LIMIT 4"
    );
    $trips = $tripsStmt->fetchAll();

    // Reviews
    $reviewsStmt = $pdo->query(
        "SELECT * FROM weroad_reviews ORDER BY id LIMIT 3"
    );
    $reviews = $reviewsStmt->fetchAll();

    // Categories (distinct categories with a sample image)
    $categoriesStmt = $pdo->query(
        "SELECT category, name, image_url FROM weroad_trips
         WHERE is_active = 1 AND category IS NOT NULL
         GROUP BY category, name, image_url
         ORDER BY category"
    );
    $categories = $categoriesStmt->fetchAll();

    // DB-driven content blocks
    $toursBanner = gaia_first_banner('tours');
    $why         = gaia_highlights('why');

} catch (PDOException $e) {
    $deals = $trips = $reviews = $categories = [];
    $toursBanner = [];
    $why = [];
    $dbError = 'Database error: ' . $e->getMessage();
}

$pageTitle = seo_title('tours', 'GAIA Tours — Discover Jordan');
$toursHeroTitle = $toursBanner && $toursBanner['title'] ? $toursBanner['title'] : site_setting('tours_hero_title', t('tours.hero_title'));
$toursHeroSub   = $toursBanner && $toursBanner['subtitle'] ? $toursBanner['subtitle'] : site_setting('tours_hero_sub', t('tours.hero_sub'));
$toursHeroImage = $toursBanner && $toursBanner['image_url'] ? $toursBanner['image_url'] : gaia_media_url('hero_tours', 'https://images.unsplash.com/photo-1548786811-dd6e453ccca7?w=1920&q=80');
$toursPromoTitle = site_setting('tours_promo_title', t('tours.promo_title'));
$toursPromoText  = site_setting('tours_promo_text', t('tours.promo_text'));
$toursDealsTitle = site_setting('tours_deals_title', t('tours.deals_title'));
$toursCategoryTitle = site_setting('tours_category_title', t('tours.category_title'));
$toursLovedTitle = site_setting('tours_loved_title', t('tours.loved_title'));
$toursTrustpilotTitle = site_setting('tours_trustpilot_title', t('tours.trustpilot_title'));
$toursCtaTitle = site_setting('tours_cta_title', t('tours.cta_title'));
$toursExploreBtn = site_setting('tours_explore_btn', t('tours.explore_trips'));
$toursBookBtn = site_setting('tours_book_btn', t('tours.book_now'));
$toursSeeAll = site_setting('tours_see_all', t('tours.see_all'));
$toursFromLabel = site_setting('tours_from_label', t('tours.trip_from'));
$toursDaysLabel = site_setting('tours_days_label', t('tours.days'));
$toursNoDeals = site_setting('tours_no_deals', t('tours.no_deals'));
$toursNoCategories = site_setting('tours_no_categories', t('tours.no_categories'));
$toursNoReviews = site_setting('tours_no_reviews', t('tours.no_reviews'));
$toursSearchPlaceholder = site_setting('tours_search_placeholder', t('tours.search_placeholder'));
$toursSearchBtn = site_setting('tours_search_btn', t('tours.search_btn'));
$toursDepositText = site_setting('tours_deposit_text', t('tours.deposit_text'));
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(gaia_current_lang()); ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags('tours', $pageTitle) ?>
<style>
  .hero { background-image: linear-gradient(180deg, rgba(15,15,25,.5), rgba(15,15,25,.75)), url('<?= htmlspecialchars($toursHeroImage) ?>'); }
</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<link rel="stylesheet" href="/weroad/styles.css">
</head>
<body>

<?php
$gaia_base = '../';
$gaia_active = 'tours';
$gaia_header_style = 'solid';
require_once __DIR__ . '/../components/gaia-config.php';
require __DIR__ . '/../components/gaia-header.php';
?>

<section class="hero">
  <div class="wrap hero-inner">
    <h1><?php echo $toursHeroTitle; ?></h1>
    <p><?php echo htmlspecialchars($toursHeroSub); ?></p>
    <form class="hero-search" action="<?= gaia_url('search.php', '../') ?>" method="get">
      <input type="text" name="q" placeholder="<?php echo htmlspecialchars($toursSearchPlaceholder); ?>">
      <button type="submit"><?php echo htmlspecialchars($toursSearchBtn); ?></button>
    </form>
  </div>
</section>

<div class="wrap">
  <div class="promo-banner">
    <div>
      <h3><?php echo htmlspecialchars($toursPromoTitle); ?></h3>
      <p><?php echo htmlspecialchars($toursPromoText); ?> — <?php echo htmlspecialchars($toursDepositText); ?></p>
    </div>
    <a href="<?= gaia_url('search.php', '../') ?>" class="btn btn-primary"><?php echo htmlspecialchars($toursBookBtn); ?></a>
  </div>
</div>

<?php if (isset($dbError)): ?>
  <div class="wrap" style="padding:20px;background:#FFEDEE;border-radius:12px;color:#C94F4F;font-weight:600;">
    <?php echo htmlspecialchars($dbError); ?>
  </div>
<?php endif; ?>

<section class="section">
  <div class="wrap">
    <div class="section-head">
      <h2><?php echo htmlspecialchars($toursDealsTitle); ?></h2>
      <a class="see-all" href="<?= gaia_url('search.php', '../') ?>"><?php echo htmlspecialchars($toursSeeAll); ?> →</a>
    </div>
    <div class="scroll-row">
      <?php if (empty($deals)): ?>
        <p style="color:var(--muted);"><?php echo htmlspecialchars($toursNoDeals); ?></p>
      <?php else: foreach ($deals as $deal): ?>
      <a class="deal-card" href="<?= gaia_url('trip.php', '../') ?>?slug=<?php echo urlencode($deal['slug']); ?>">
        <div class="media">
          <?php if ((float)$deal['discount_percent'] > 0): ?>
            <span class="tag">-<?php echo (int)$deal['discount_percent']; ?>%</span>
          <?php endif; ?>
          <img src="<?php echo htmlspecialchars($deal['image_url']); ?>" alt="<?php echo htmlspecialchars($deal['name']); ?>">
        </div>
        <h4><?php echo htmlspecialchars($deal['name']); ?></h4>
        <div class="price"><?php echo htmlspecialchars($toursFromLabel); ?> <b>$<?php echo number_format((float)$deal['base_price'], 0); ?></b></div>
      </a>
      <?php endforeach; endif; ?>
    </div>
  </div>
</section>

<section class="section-tight">
  <div class="wrap">
    <div class="why-row">
      <?php if (!empty($why)): foreach ($why as $w): ?>
      <div class="why-item">
        <div class="ic"><i class="<?= htmlspecialchars($w['icon'] ?? 'fa-solid fa-circle-check') ?>"></i></div>
        <div><strong><?= htmlspecialchars($w['title']) ?></strong><span><?= htmlspecialchars($w['description']) ?></span></div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="section-head"><h2><?php echo htmlspecialchars($toursCategoryTitle); ?></h2></div>
    <div class="category-grid">
      <?php if (!empty($categories)): foreach ($categories as $cat): ?>
      <a class="category-card" href="<?= gaia_url('search.php', '../') ?>?category=<?php echo urlencode($cat['category']); ?>">
        <img src="<?php echo htmlspecialchars($cat['image_url']); ?>" alt="<?php echo htmlspecialchars($cat['category']); ?>">
        <span><?php echo htmlspecialchars($cat['category']); ?></span>
      </a>
      <?php endforeach; else: ?>
      <p style="color:var(--muted);"><?php echo htmlspecialchars($toursNoCategories); ?></p>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="section-head"><h2><?php echo htmlspecialchars($toursLovedTitle); ?></h2></div>
    <div class="loved-grid">
      <?php if (!empty($trips)): foreach ($trips as $trip): ?>
      <a class="trip-card" href="<?= gaia_url('trip.php', '../') ?>?slug=<?php echo urlencode($trip['slug']); ?>">
        <div class="media"><img src="<?php echo htmlspecialchars($trip['image_url']); ?>" alt="<?php echo htmlspecialchars($trip['name']); ?>"></div>
        <div class="body">
          <h3><?php echo htmlspecialchars($trip['name']); ?></h3>
          <div class="meta-row">
            <span><?php echo str_replace('{days}', (int)$trip['duration_days'], $toursDaysLabel); ?></span>
            <span class="rating"><span class="stars">★</span> <?php echo htmlspecialchars((string)$trip['rating']); ?></span>
          </div>
          <div class="price-row">
            <div><div class="price-label"><?php echo htmlspecialchars($toursFromLabel); ?></div><div class="price">$<?php echo number_format((float)$trip['base_price'], 0); ?></div></div>
          </div>
        </div>
      </a>
      <?php endforeach; endif; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="section-head"><h2><?php echo htmlspecialchars($toursTrustpilotTitle); ?></h2></div>
    <div class="review-grid">
      <?php if (!empty($reviews)): foreach ($reviews as $review): ?>
      <div class="review-card">
        <span class="stars-inline">★★★★★</span>
        <p><?php echo htmlspecialchars($review['text']); ?></p>
        <div class="who">— <?php echo htmlspecialchars($review['author']); ?></div>
      </div>
      <?php endforeach; else: ?>
      <p style="color:var(--muted);"><?php echo htmlspecialchars($toursNoReviews); ?></p>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="final-cta">
      <h2><?php echo htmlspecialchars($toursCtaTitle); ?></h2>
      <a href="<?= gaia_url('search.php', '../') ?>" class="btn btn-primary"><?php echo htmlspecialchars($toursExploreBtn); ?></a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../components/gaia-footer.php'; ?>

<script src="/assets/gaia.js"></script>
<script src="/weroad/script.js"></script>
</body>
</html>


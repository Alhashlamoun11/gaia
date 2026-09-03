<?php
/**
 * search.php — WeRoad search & filter results page
 * ------------------------------------------------------------
 * Queries trips by filters: keyword (q), category, price range,
 * duration days, effort level, and private room availability.
 * All user-facing labels are loaded from the `translations`
 * table (EN/AR) via the shared GAIA bootstrap.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../components/gaia-config.php';

try {
    $pdo = getWeRoadPDO();

    // --- Read & sanitize filter inputs (supports both ?q= & ?destination=, ?start_date= & ?date=) ---
    $destination = trim($_GET['destination'] ?? ($_GET['q'] ?? ''));
    $category    = trim($_GET['category'] ?? ($_GET['region'] ?? ''));
    $minPrice    = (float)($_GET['min_price'] ?? 0);
    $maxPrice    = (float)($_GET['max_price'] ?? 0);
    $maxDays     = (int)($_GET['max_days'] ?? 0);
    $effort      = trim($_GET['effort'] ?? '');
    $startDate   = trim($_GET['date'] ?? ($_GET['start_date'] ?? ''));
    $tag         = trim($_GET['tag'] ?? ($_GET['types'] ?? ($_GET['season'] ?? '')));
    $deals       = !empty($_GET['deals']);

    // Dynamic filter list (for the chips)
    $categories = $pdo->query(
        "SELECT DISTINCT category FROM weroad_trips WHERE is_active = 1 AND category IS NOT NULL ORDER BY category"
    )->fetchAll(PDO::FETCH_COLUMN);

    // --- Build the WHERE clause safely ---
    $where = ['is_active = 1'];
    $params = [];

    if ($destination !== '') {
        $where[] = '(name LIKE :q1 OR tagline LIKE :q2 OR description LIKE :q3 OR category LIKE :q4 OR slug LIKE :q5)';
        $params[':q1'] = '%' . $destination . '%';
        $params[':q2'] = '%' . $destination . '%';
        $params[':q3'] = '%' . $destination . '%';
        $params[':q4'] = '%' . $destination . '%';
        $params[':q5'] = '%' . $destination . '%';
    }
    if ($category !== '' && strtolower($category) !== 'all') {
        $where[] = '(category LIKE :category OR slug LIKE :cat_slug)';
        $params[':category'] = '%' . $category . '%';
        $params[':cat_slug'] = '%' . $category . '%';
    }
    if ($minPrice > 0) {
        $where[] = 'base_price >= :min_price';
        $params[':min_price'] = $minPrice;
    }
    if ($maxPrice > 0) {
        $where[] = 'base_price <= :max_price';
        $params[':max_price'] = $maxPrice;
    }
    if ($maxDays > 0) {
        $where[] = 'duration_days <= :max_days';
        $params[':max_days'] = $maxDays;
    }
    if ($effort !== '') {
        $where[] = 'effort_level = :effort';
        $params[':effort'] = $effort;
    }
    if ($deals) {
        $where[] = 'discount_percent > 0';
    }
    if ($tag !== '' && strtolower($tag) !== 'all') {
        $where[] = '(slug LIKE :tag1 OR name LIKE :tag2 OR description LIKE :tag3)';
        $params[':tag1'] = '%' . $tag . '%';
        $params[':tag2'] = '%' . $tag . '%';
        $params[':tag3'] = '%' . $tag . '%';
    }
    if ($startDate !== '') {
        if (strlen($startDate) === 7) { // YYYY-MM
            $where[] = 'id IN (SELECT DISTINCT trip_id FROM weroad_departures WHERE start_date LIKE :month_date AND is_active = 1)';
            $params[':month_date'] = $startDate . '%';
        } else {
            $where[] = 'id IN (SELECT DISTINCT trip_id FROM weroad_departures WHERE start_date >= :start_date AND is_active = 1)';
            $params[':start_date'] = $startDate;
        }
    }

    $sql = "SELECT * FROM weroad_trips
            WHERE " . implode(' AND ', $where) . "
            ORDER BY rating DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $trips = $stmt->fetchAll();

} catch (PDOException $e) {
    $trips = [];
    $categories = [];
    $dbError = 'Database error: ' . $e->getMessage();
}

$pageTitle = seo_title('tours_search', 'Jordan trips — GAIA Tours');
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(gaia_current_lang()); ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags('tours_search', $pageTitle) ?>
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

<div class="wrap">
  <div class="search-bar">
    <form class="search-row" method="get" action="<?= gaia_url('tours') ?>">
      <div class="search-field grow2">
        <svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-6.5 7-12a7 7 0 10-14 0c0 5.5 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/></svg>
        <span><span class="sub"><?= t('tours.search_where') ?></span><input type="text" name="destination" value="<?php echo htmlspecialchars($destination); ?>" placeholder="<?= t('tours.search_dest_ph') ?>" style="border:none;outline:none;font-family:inherit;font-size:14.5px;font-weight:600;color:var(--text);width:100%;"></span>
      </div>
      <div class="search-field">
        <svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
        <span><span class="sub"><?= t('tours.search_when') ?></span><input type="text" name="date" value="<?php echo htmlspecialchars($startDate); ?>" placeholder="YYYY-MM" onfocus="(this.type='month')" onblur="if(!this.value)this.type='text'" style="border:none;outline:none;font-family:inherit;font-size:14.5px;font-weight:600;color:var(--text);width:100%;background:transparent;"></span>
      </div>
      <button class="search-cta" type="submit">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        <?= t('tours.search_btn') ?>
      </button>
    </form>
  </div>

  <?php if (isset($dbError)): ?>
    <div style="padding:20px;background:#FFEDEE;border-radius:12px;color:#C94F4F;font-weight:600;"><?php echo htmlspecialchars($dbError); ?></div>
  <?php endif; ?>

  <div class="results-tabs">
    <div class="results-meta" style="width: 100%; justify-content: flex-end;">
      <span><?= t_fmt('tours.search_found', ['count' => count($trips)]) ?></span>
    </div>
  </div>

  <div class="results-grid">
    <?php if (empty($trips)): ?>
      <p style="color:var(--muted);"><?= t('tours.search_no_results') ?></p>
    <?php else: foreach ($trips as $trip): ?>
    <a class="trip-card" href="<?= gaia_url('trip.php', '../') ?>?slug=<?php echo urlencode($trip['slug']); ?>">
      <div class="media">
        <img src="<?php echo htmlspecialchars($trip['image_url']); ?>" alt="<?php echo htmlspecialchars($trip['name']); ?>">
      </div>
      <div class="body">
        <h3><?php echo htmlspecialchars($trip['name']); ?></h3>
        <div class="meta-row">
          <span><?= t_fmt('tours.search_days', ['days' => (int)$trip['duration_days']]) ?></span>
          <span class="rating"><span class="stars">★</span> <?php echo htmlspecialchars((string)$trip['rating']); ?> <span style="color:var(--muted); font-weight:500;">(<?php echo (int)$trip['reviews_count']; ?>)</span></span>
        </div>
        <div class="price-row">
          <div>
            <div class="price-label"><?= t('tours.search_from') ?></div>
            <div class="price">$<?php echo number_format((float)$trip['base_price'], 0); ?></div>
          </div>
        </div>
        <div class="see-dates">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
          <?= t('tours.search_see_dates') ?>
        </div>
      </div>
    </a>
    <?php endforeach; endif; ?>
  </div>

  <div class="pagination">
    <span><?= t_fmt('tours.search_show', ['count' => 15]) ?></span>
    <button class="pg-btn" aria-label="<?= t('tours.search_prev') ?>">‹</button>
    <span><?= t_fmt('tours.search_page', ['current' => 1, 'total' => 1]) ?></span>
    <button class="pg-btn" aria-label="<?= t('tours.search_next') ?>">›</button>
  </div>
</div>

<section class="wrap" style="padding-top:40px;">
  <?= gaia_cross_sell('transfers', $gaia_base) ?>
</section>

<?php require __DIR__ . '/../components/gaia-footer.php'; ?>

<script src="/assets/gaia.js"></script>
<script src="/weroad/script.js"></script>
</body>
</html>

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

    // --- Read & sanitize filter inputs ---
    $q        = trim($_GET['q'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $minPrice = (float)($_GET['min_price'] ?? 0);
    $maxPrice = (float)($_GET['max_price'] ?? 0);
    $maxDays  = (int)($_GET['max_days'] ?? 0);
    $effort   = trim($_GET['effort'] ?? '');

    // Dynamic filter list (for the chips)
    $categories = $pdo->query(
        "SELECT DISTINCT category FROM weroad_trips WHERE is_active = 1 AND category IS NOT NULL ORDER BY category"
    )->fetchAll(PDO::FETCH_COLUMN);

    // --- Build the WHERE clause safely ---
    $where = ['is_active = 1'];
    $params = [];

    if ($q !== '') {
        $where[] = '(name LIKE :q OR tagline LIKE :q OR description LIKE :q)';
        $params[':q'] = '%' . $q . '%';
    }
    if ($category !== '') {
        $where[] = 'category = :category';
        $params[':category'] = $category;
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
<link rel="stylesheet" href="styles.css">
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
    <form class="search-row" method="get" action="<?= gaia_url('search.php', '../') ?>">
      <div class="search-field grow2">
        <svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-6.5 7-12a7 7 0 10-14 0c0 5.5 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/></svg>
        <span><span class="sub"><?= t('tours.search_where') ?></span><input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="<?= t('tours.search_dest_ph') ?>" style="border:none;outline:none;font-family:inherit;font-size:14.5px;font-weight:600;color:var(--text);width:100%;"></span>
      </div>
      <div class="search-field">
        <svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
        <span><span class="sub"><?= t('tours.search_when') ?></span><?= t('tours.search_when_ph') ?></span>
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
    <div class="tabline">
      <a class="active"><?= t('tours.search_view_by') ?></a>
      <a><?= t('tours.search_trips_tab') ?></a>
      <a><?= t('tours.search_dates_tab') ?></a>
    </div>
    <div class="results-meta">
      <span><?= t('tours.search_show_maps') ?></span>
      <span class="toggle"></span>
      <span><?= t_fmt('tours.search_found', ['count' => count($trips)]) ?></span>
    </div>
  </div>

  <div class="results-grid">
    <?php if (empty($trips)): ?>
      <p style="color:var(--muted);"><?= t('tours.search_no_results') ?></p>
    <?php else: foreach ($trips as $trip): ?>
    <a class="trip-card" href="<?= gaia_url('trip.php', '../') ?>?slug=<?php echo urlencode($trip['slug']); ?>">
      <div class="media">
        <span class="compare-tag">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="18" rx="1"/><rect x="14" y="3" width="7" height="18" rx="1"/></svg>
          <?= t('tours.search_compare') ?>
        </span>
        <span class="fav" data-fav>
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.5-9.5-9C.5 8 2.5 4 6.5 4c2 0 3.5 1.2 5.5 3.3C14 5.2 15.5 4 17.5 4c4 0 6 4 4 8-2.5 4.5-9.5 9-9.5 9z"/></svg>
        </span>
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
<script src="script.js"></script>
</body>
</html>

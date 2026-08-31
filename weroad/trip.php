<?php
/**
 * trip.php — WeRoad trip detail page
 * ------------------------------------------------------------
 * Loads a trip by slug along with its itinerary, inclusions,
 * optional activities, FAQs, group leaders, and departures.
 * All user-facing labels come from the `translations` table.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../components/gaia-config.php';

$slug = trim($_GET['slug'] ?? '');

try {
    $pdo = getWeRoadPDO();

    // Load the trip
    $tripStmt = $pdo->prepare("SELECT * FROM weroad_trips WHERE slug = :slug AND is_active = 1 LIMIT 1");
    $tripStmt->execute([':slug' => $slug]);
    $trip = $tripStmt->fetch();

    if (!$trip) {
        http_response_code(404);
echo '<!DOCTYPE html><html><head><title>' . t('tours.not_found_title') . '</title><link rel="stylesheet" href="/weroad/styles.css"></head><body>';
        echo '<div class="wrap" style="padding:80px 0;text-align:center;"><h1>' . t('tours.not_found_title') . '</h1><p style="color:var(--muted);">' . t('tours.not_found_text') . '</p><a class="btn btn-primary" href="' . gaia_url('search.php', '../') . '" style="margin-top:20px;">' . t('tours.browse_trips') . '</a></div>';
        echo '</body></html>';
        exit;
    }

    // Gallery images
    $gallery = $trip['gallery_urls'] !== null ? array_filter(array_map('trim', explode(',', $trip['gallery_urls']))) : [];
    if (empty($gallery)) $gallery = [$trip['image_url']];

    // Itinerary
    $itinStmt = $pdo->prepare("SELECT * FROM weroad_trip_itineraries WHERE trip_id = :id ORDER BY day_number");
    $itinStmt->execute([':id' => $trip['id']]);
    $itinerary = $itinStmt->fetchAll();

    // Inclusions
    $incStmt = $pdo->prepare("SELECT * FROM weroad_trip_inclusions WHERE trip_id = :id ORDER BY include_type, id");
    $incStmt->execute([':id' => $trip['id']]);
    $inclusions = $incStmt->fetchAll();
    $included = array_filter($inclusions, fn($i) => $i['include_type'] === 'included');
    $excluded = array_filter($inclusions, fn($i) => $i['include_type'] === 'excluded');

    // Optional activities
    $actStmt = $pdo->prepare("SELECT * FROM weroad_trip_optional_activities WHERE trip_id = :id ORDER BY price");
    $actStmt->execute([':id' => $trip['id']]);
    $activities = $actStmt->fetchAll();

    // FAQs
    $faqStmt = $pdo->prepare("SELECT * FROM weroad_trip_faqs WHERE trip_id = :id ORDER BY faq_group, id");
    $faqStmt->execute([':id' => $trip['id']]);
    $faqs = $faqStmt->fetchAll();

    // Group FAQs by group
    $faqGroups = [];
    foreach ($faqs as $faq) {
        $faqGroups[$faq['faq_group']][] = $faq;
    }
    $faqGroupNames = array_keys($faqGroups);

    // Departures
    $depStmt = $pdo->prepare("SELECT * FROM weroad_departures WHERE trip_id = :id AND is_active = 1 ORDER BY start_date");
    $depStmt->execute([':id' => $trip['id']]);
    $departures = $depStmt->fetchAll();

} catch (PDOException $e) {
    echo '<pre>Error: ' . htmlspecialchars($e->getMessage()) . '</pre>';
    exit;
}

$pageTitle = $trip['name'] . ' — GAIA Tours';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags('tour_detail', $pageTitle) ?>
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

<div class="wrap trip-hero">
  <div class="breadcrumb"><a href="<?= gaia_url('index.php', '../') ?>"><?= t('tours.trip_home', 'Home') ?></a> / <a href="<?= gaia_url('search.php', '../') ?>"><?php echo htmlspecialchars($trip['category'] ?? t('tours.trips_tab', 'Trips')); ?></a> / <?php echo htmlspecialchars($trip['name']); ?></div>
  <h1><?php echo htmlspecialchars($trip['name']); ?></h1>
  <div class="trip-subrow">
    <span class="item"><span class="stars">★</span> <?php echo htmlspecialchars((string)$trip['rating']); ?> (<?php echo t_fmt('tours.trip_reviews', ['count' => number_format((int)$trip['reviews_count'])]) ?>)</span>
    <span class="item"><?= t_fmt('tours.trip_days', ['days' => (int)$trip['duration_days']]) ?></span>
    <span class="item"><?= t_fmt('tours.trip_max_people', ['count' => (int)$trip['max_group_size']]) ?></span>
    <span class="item"><?= t_fmt('tours.trip_effort', ['level' => htmlspecialchars($trip['effort_level'])]) ?></span>
    <div class="trip-actions">
      <button class="icon-btn" data-fav aria-label="Save">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.5-9.5-9C.5 8 2.5 4 6.5 4c2 0 3.5 1.2 5.5 3.3C14 5.2 15.5 4 17.5 4c4 0 6 4 4 8-2.5 4.5-9.5 9-9.5 9z"/></svg>
      </button>
      <button class="icon-btn" aria-label="Share">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 10.6l6.8-3.2M8.6 13.4l6.8 3.2"/></svg>
      </button>
    </div>
  </div>

  <div class="gallery">
    <?php if (!empty($gallery)): ?>
      <img class="g-main" src="<?php echo htmlspecialchars($gallery[0]); ?>" alt="<?php echo htmlspecialchars($trip['name']); ?>">
      <?php for ($i = 1; $i < count($gallery) && $i < 3; $i++): ?>
        <img src="<?php echo htmlspecialchars($gallery[$i]); ?>" alt="<?php echo htmlspecialchars($trip['name']); ?>">
      <?php endfor; ?>
    <?php endif; ?>
  </div>

  <div class="detail-layout">
    <div class="detail-main">

      <div class="fit-box">
        <h3><?= t('tours.trip_is_for_me') ?></h3>
        <div class="fit-grid">
          <div class="fit-row">
            <div class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 5-6 8-6s6.5 2 8 6"/></svg></div>
            <div><b><?= t('tours.trip_age_range') ?></b><span><?php echo htmlspecialchars($trip['age_range']); ?></span></div>
          </div>
          <div class="fit-row">
            <div class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L4 14h6l-1 8 9-12h-6l1-8z"/></svg></div>
            <div><b><?= t('tours.trip_physical_effort') ?></b><span><?php echo ucfirst(htmlspecialchars($trip['effort_level'])); ?></span></div>
          </div>
          <div class="fit-row">
            <div class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="9" width="18" height="11" rx="2"/><path d="M7 9V6a5 5 0 0110 0v3"/></svg></div>
            <div><b><?= t('tours.trip_comfort') ?></b><span><?php echo htmlspecialchars($trip['comfort_level'] ?? 'Standard'); ?></span></div>
          </div>
          <div class="fit-row">
            <div class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21V9l9-6 9 6v12M9 21v-6h6v6"/></svg></div>
            <div><b><?= t('tours.trip_accommodation') ?></b><span><?php echo htmlspecialchars($trip['accommodation'] ?? 'Shared rooms'); ?></span></div>
          </div>
        </div>
      </div>

      <?php if ($trip['description']): ?>
        <p style="color:var(--muted);font-size:14.5px;line-height:1.8;margin-bottom:32px;"><?php echo nl2br(htmlspecialchars($trip['description'])); ?></p>
      <?php endif; ?>

      <h2 class="detail-h2"><?= t('tours.trip_itinerary') ?></h2>

      <?php if (!empty($itinerary)): foreach ($itinerary as $day): ?>
      <details class="day-card" <?php echo $day['day_number'] === 1 ? 'open' : ''; ?>>
        <summary>
          <img class="thumb" src="<?php echo htmlspecialchars($day['image_url']); ?>" alt="">
          <div><span class="day-label"><?= t_fmt('tours.trip_day', ['day' => (int)$day['day_number']]) ?></span><h4><?php echo htmlspecialchars($day['title']); ?></h4></div>
          <svg class="chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
        </summary>
        <div class="day-body"><?php echo nl2br(htmlspecialchars($day['description'])); ?></div>
      </details>
      <?php endforeach; endif; ?>

      <h2 class="detail-h2" style="margin-top:40px;"><?= t('tours.trip_included_title') ?></h2>
      <div class="included-grid">
        <div>
          <h4><?= t('tours.trip_included') ?></h4>
          <ul>
            <?php foreach ($included as $item): ?>
            <li class="yes"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg><?php echo htmlspecialchars($item['item_text']); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div>
          <h4><?= t('tours.trip_not_included') ?></h4>
          <ul>
            <?php foreach ($excluded as $item): ?>
            <li class="no"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6l12 12"/></svg><?php echo htmlspecialchars($item['item_text']); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>

      <h2 class="detail-h2"><?= t('tours.trip_optional') ?></h2>
      <p style="color:var(--muted); font-size:14px; margin-bottom:18px;"><?= t('tours.trip_optional_sub') ?></p>
      <?php if (!empty($activities)): foreach ($activities as $activity): ?>
      <div class="accordion-item">
        <details><summary><?php echo htmlspecialchars($activity['name']); ?> <span class="plus">+</span></summary><p><?php echo htmlspecialchars($activity['description']); ?> <?= t_fmt('tours.trip_optional_price', ['price' => number_format((float)$activity['price'], 0)]) ?></p></details>
      </div>
      <?php endforeach; endif; ?>

      <h2 class="detail-h2" style="margin-top:36px;"><?= t('tours.trip_why') ?></h2>
      <div class="why-row">
        <div class="why-item">
          <div class="ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg></div>
          <div><strong><?= t('tours.trip_confidence') ?></strong><span><?= t('tours.trip_confidence_sub') ?></span></div>
        </div>
        <div class="why-item">
          <div class="ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 5-6 8-6s6.5 2 8 6"/></svg></div>
          <div><strong><?= t('tours.trip_small_groups') ?></strong><span><?= t('tours.trip_small_groups_sub') ?></span></div>
        </div>
      </div>

      <?php if (!empty($faqGroups)): ?>
      <h2 class="detail-h2" style="margin-top:44px;"><?= t('tours.trip_faq_title') ?></h2>
      <div class="loved-tabs faq-tabs">
        <?php $first = true; foreach ($faqGroupNames as $i => $group): ?>
        <button class="<?php echo $first ? 'active' : ''; ?>" data-target="faq-<?php echo $i; ?>"><?php echo htmlspecialchars($group); ?></button>
        <?php $first = false; endforeach; ?>
      </div>
      <?php foreach ($faqGroupNames as $i => $group): ?>
      <div class="faq-group" id="faq-<?php echo $i; ?>" style="<?php echo $i === 0 ? '' : 'display:none;'; ?>">
        <?php foreach ($faqGroups[$group] as $faq): ?>
        <div class="accordion-item"><details><summary><?php echo htmlspecialchars($faq['question']); ?> <span class="plus">+</span></summary><p><?php echo htmlspecialchars($faq['answer']); ?></p></details></div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>

    </div>

    <aside class="price-card">
      <div class="from"><?= t('tours.trip_from') ?></div>
      <div class="amount">$<?php echo number_format((float)$trip['base_price'], 0); ?></div>

      <?php if (!empty($departures)): ?>
        <?php $firstDep = $departures[0]; ?>
        <a href="<?= gaia_url('weroad/booking.php') ?>?trip_id=<?php echo (int)$trip['id']; ?>&departure_id=<?php echo (int)$firstDep['id']; ?>" class="btn btn-primary btn-block"><?= t('tour.book_now', 'Book Now') ?></a>
        <div class="spots"><?= t_fmt('tours.trip_spots_left', ['count' => (int)$firstDep['spots_left'], 'month' => date('F', strtotime($firstDep['start_date']))]) ?></div>
        <div class="info-line">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
          <?= t_fmt('tours.trip_departures', ['count' => count($departures)]) ?>
        </div>
      <?php else: ?>
        <p style="color:var(--muted);font-size:13px;"><?= t('tours.trip_no_departures') ?></p>
      <?php endif; ?>

      <div class="info-line">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 5-6 8-6s6.5 2 8 6"/></svg>
        <?= t_fmt('tours.trip_travellers', ['count' => number_format((int)$trip['reviews_count'])]) ?>
      </div>
      <div class="info-line">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
        <?= t('tours.trip_installments') ?>
      </div>
      <div class="divider"></div>
      <div class="info-line">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4.5 8-11.8A8 8 0 004 10.2C4 17.5 12 22 12 22z"/></svg>
        <?= t('tours.trip_insurance') ?>
      </div>
      <a href="<?= gaia_url('contact') ?>" class="txt-link" style="display:block; text-align:center; margin-top:16px; font-weight:700; font-size:13.5px; color:var(--primary);"><?= t('tours.trip_ask') ?></a>
    </aside>
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

<?php
/**
 * index.php — WeRoad Homepage with Original GAIA Global Header & Logo
 * ------------------------------------------------------------------
 * Connected directly to MySQL database.
 * Uses the authentic global header component (components/gaia-header.php)
 * with the original logo, navigation items, language switcher, WhatsApp,
 * and user auth status.
 * ------------------------------------------------------------------
 */

// Route requests to their respective target pages if invoked under built-in PHP server without router.php
if (!empty($_SERVER['REQUEST_URI'])) {
    $reqPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $trimmed = trim(urldecode($reqPath), '/');
    if ($trimmed !== '' && $trimmed !== 'en' && $trimmed !== 'ar' && $trimmed !== 'index.php') {
        require_once __DIR__ . '/router.php';
        return;
    }
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/components/gaia-config.php';

// Shared header variables for the global GAIA navigation
$gaia_base          = '';
$gaia_active        = 'home';
$gaia_header_style  = 'overlay';

// Retrieve database connection
$pdo = getPDO();

// Current language & direction
$currentLang = gaia_current_lang();
$isAr = ($currentLang === 'ar');
$dir  = gaia_dir();

// ------------------------------------------------------------
// DATABASE QUERIES (Read-Only)
// ------------------------------------------------------------

// 1. Load active trips from DB
$dbTrips = [];
try {
    $stmt = $pdo->query("SELECT * FROM weroad_trips WHERE is_active = 1 ORDER BY rating DESC, reviews_count DESC");
    if ($stmt) {
        $dbTrips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $dbTrips = [];
}

// Helper: Format trip price & discount from DB
if (!function_exists('formatTripPricing')) {
    function formatTripPricing($trip) {
        $base = (float)($trip['base_price'] ?? 0);
        $disc = (float)($trip['discount_percent'] ?? 0);
        $final = $disc > 0 ? round($base * (1 - ($disc / 100))) : round($base);
        return [
            'price'      => '$ ' . number_format($final, 0, '.', ','),
            'old_price'  => $disc > 0 ? '$ ' . number_format($base, 0, '.', ',') : '',
            'discount'   => $disc > 0 ? '-' . round($disc) . '%' : ''
        ];
    }
}

// 2. Load Tour Destinations for Search
$dbDestinations = [];
try {
    $stmt = $pdo->query("SELECT * FROM tour_destinations ORDER BY sort_order ASC, name ASC");
    if ($stmt) {
        $dbDestinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $dbDestinations = [];
}

// 3. Load Reviews from DB (combining weroad_reviews and general reviews)
$dbReviews = [];
try {
    $stmt = $pdo->query("SELECT * FROM weroad_reviews ORDER BY id DESC LIMIT 6");
    if ($stmt) {
        $dbReviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if (count($dbReviews) < 6) {
        $stmt2 = $pdo->query("SELECT author as author_name, rating, comment, '2026-04-10' as date FROM reviews WHERE is_active = 1 LIMIT 6");
        if ($stmt2) {
            $more = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            $dbReviews = array_merge($dbReviews, $more);
        }
    }
} catch (Exception $e) {
    $dbReviews = [];
}

// 4. Load FAQs from DB
$dbFaqs = [];
try {
    $stmt = $pdo->query("SELECT * FROM weroad_trip_faqs GROUP BY question ORDER BY id ASC LIMIT 5");
    if ($stmt) {
        $dbFaqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if (empty($dbFaqs)) {
        $stmt2 = $pdo->query("SELECT * FROM faq WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 5");
        if ($stmt2) {
            $dbFaqs = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Exception $e) {
    $dbFaqs = [];
}

// 5. DB Site Settings
$companyName    = site_setting('company_name', 'GAIA TOURS & TRAVEL');
$companyShort   = site_setting('company_short', 'GAIA');
$contactEmail   = site_setting('contact_email', 'support@gaiatours.com');
$contactPhone   = site_setting('contact_whatsapp', '+962790123456');
$currencySymbol = site_setting('currency_symbol', '$');

// 6. DB Hero Banner
$dbHero = null;
try {
    $bStmt = $pdo->query("SELECT * FROM banners WHERE page IN ('home', 'weroad') AND is_active = 1 LIMIT 1");
    if ($bStmt) $dbHero = $bStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { $dbHero = null; }

$heroTitle = $dbHero ? ($isAr && !empty($dbHero['title_ar']) ? $dbHero['title_ar'] : $dbHero['title']) : 'Discover the world. Make new friends.';
$heroSub   = $dbHero ? ($isAr && !empty($dbHero['subtitle_ar']) ? $dbHero['subtitle_ar'] : $dbHero['subtitle']) : 'Travel in small groups, with people your age';
$heroBg    = ($dbHero && !empty($dbHero['image_url'])) ? $dbHero['image_url'] : 'https://images.unsplash.com/photo-1682687220063-4742bd7fd538?auto=format&fit=crop&w=1920&q=85';

// 7. DB Highlights: Why WeRoad
$dbWhyHighlights = [];
try {
    $wStmt = $pdo->query("SELECT * FROM highlights WHERE section IN ('why', 'home') AND is_active = 1 ORDER BY sort_order ASC LIMIT 4");
    if ($wStmt) $dbWhyHighlights = $wStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $dbWhyHighlights = []; }

if (empty($dbWhyHighlights)) {
    $dbWhyHighlights = [
        ['avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=120&q=80', 'text' => 'Arrive solo. <span class="highlight-pill">Leave with a group.</span>'],
        ['avatar' => 'https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=120&q=80', 'text' => '<span class="highlight-pill">Choose your trip</span> based on your vibe and activity level'],
        ['avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=120&q=80', 'text' => 'The Group Leader handles the plan. <span class="highlight-pill">You live the journey</span>'],
        ['avatar' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=120&q=80', 'text' => 'The people you meet become <span class="highlight-pill">part of your life</span>']
    ];
} else {
    $dbWhyHighlights = array_map(function($h) use ($isAr) {
        $t = ($isAr && !empty($h['title_ar'])) ? $h['title_ar'] : ($h['title'] ?? '');
        $img = !empty($h['image_url']) ? $h['image_url'] : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=120&q=80';
        return ['avatar' => $img, 'text' => $t];
    }, $dbWhyHighlights);
}

// 8. DB Categories / Collections
$collections = [];
try {
    $cStmt = $pdo->query("SELECT * FROM tour_categories WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 5");
    if ($cStmt) {
        $cats = $cStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cats as $cat) {
            $collections[] = [
                'title' => ($isAr && !empty($cat['name_ar'])) ? $cat['name_ar'] : $cat['name'],
                'price' => !empty($cat['price_label']) ? $cat['price_label'] : 'From $ 990',
                'img'   => !empty($cat['image_url']) ? $cat['image_url'] : 'https://images.unsplash.com/photo-1523906834658-6e24ef2386f9?auto=format&fit=crop&w=600&q=80',
                'link'  => '/tours?category=' . urlencode($cat['slug'])
            ];
        }
    }
} catch (Exception $e) { $collections = []; }

if (empty($collections)) {
    $collections = [
        ['title' => $isAr ? 'مثالي لرحلتك الأولى مع غايا' : 'Perfect for your first WeRoad', 'price' => 'From $ 1,043', 'img' => 'https://images.unsplash.com/photo-1523906834658-6e24ef2386f9?auto=format&fit=crop&w=600&q=80', 'link' => '/tours?tag=first-trip'],
        ['title' => $isAr ? 'رحلات أيقونية' : 'Iconic trips', 'price' => 'From $ 1,043', 'img' => 'https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=600&q=80', 'link' => '/tours?tag=iconic'],
        ['title' => $isAr ? 'رحلات قصيرة' : 'Short trips', 'price' => 'From $ 579', 'img' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=600&q=80', 'link' => '/tours?duration=short'],
        ['title' => $isAr ? 'المشي والمسارات الجبلية' : 'Trekking & Hiking', 'price' => 'From $ 335', 'img' => 'https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=600&q=80', 'link' => '/tours?tag=trekking'],
        ['title' => $isAr ? 'رأس السنة' : "New Year's Eve", 'price' => 'From $ 324', 'img' => 'https://images.unsplash.com/photo-1514565131-fce0801e5785?auto=format&fit=crop&w=600&q=80', 'link' => '/tours?tag=new-year'],
    ];
}

// 9. DB Highlights: Confidence & Guarantees
$dbConfidence = [];
try {
    $confStmt = $pdo->query("SELECT * FROM highlights WHERE section IN ('confidence', 'advantages') AND is_active = 1 ORDER BY sort_order ASC LIMIT 4");
    if ($confStmt) {
        $cRows = $confStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cRows as $cr) {
            $dbConfidence[] = [
                'icon'  => !empty($cr['icon']) ? $cr['icon'] : 'fa-regular fa-file-lines',
                'title' => ($isAr && !empty($cr['title_ar'])) ? $cr['title_ar'] : $cr['title'],
                'desc'  => ($isAr && !empty($cr['description_ar'])) ? $cr['description_ar'] : ($cr['description'] ?? ''),
                'badge' => !empty($cr['badge']) ? $cr['badge'] : ''
            ];
        }
    }
} catch (Exception $e) { $dbConfidence = []; }

if (empty($dbConfidence)) {
    $dbConfidence = [
        ['icon' => 'fa-regular fa-file-lines', 'title' => 'Book with $ 0', 'desc' => 'Lock in your spot and only pay once it\'s confirmed', 'badge' => ''],
        ['icon' => 'fa-solid fa-arrows-rotate', 'title' => 'Free changes', 'desc' => 'Switch dates or destination within 31 days, at no extra cost', 'badge' => ''],
        ['icon' => 'fa-solid fa-shield-halved', 'title' => 'Maximum Flexibility', 'desc' => 'New Flexible Cancellation! Cancel up to 24hrs before', 'badge' => 'NEW!'],
        ['icon' => 'fa-regular fa-calendar', 'title' => 'Pay in installments', 'desc' => 'Spread the cost over time, interest-free', 'badge' => '']
    ];
}

// 10. DB Awards & Press Quotes
$dbPress = [];
try {
    $pStmt = $pdo->query("SELECT * FROM awards WHERE is_active = 1 LIMIT 4");
    if ($pStmt) {
        $pRows = $pStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pRows as $pr) {
            $dbPress[] = [
                'logo'  => ($isAr && !empty($pr['label_ar'])) ? $pr['label_ar'] : ($pr['label'] ?? 'THE PRESS'),
                'quote' => ($isAr && !empty($pr['description_ar'])) ? $pr['description_ar'] : ($pr['description'] ?? '"Top rated group travel company for solo adventurers"')
            ];
        }
    }
} catch (Exception $e) { $dbPress = []; }

if (empty($dbPress)) {
    $dbPress = [
        ['logo' => "Reader's Digest", 'quote' => '"70% of Gen Zers are embracing the group travel trend"'],
        ['logo' => 'THE TIMES', 'quote' => '"One of the best tour companies for solo travelers"'],
        ['logo' => 'BBC TRAVEL', 'quote' => '"You don\'t need two weeks off to see the world. Sometimes a weekend is all it takes"'],
        ['logo' => 'TRAVEL+LEISURE', 'quote' => '"There\'s no better place to travel solo than Japan"']
    ];
}

// Behind the scenes video bubbles
$behindTheScenes = [
    ['role' => 'Max, Travel Producer', 'title' => 'Crafted by travel lovers', 'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=240&q=80', 'video_title' => 'How we craft authentic routes'],
    ['role' => 'Clarissa, Regional Manager', 'title' => 'Authentic local experiences', 'avatar' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=240&q=80', 'video_title' => 'Behind our local partnerships'],
    ['role' => 'Gerda, Group Leader', 'title' => 'Made to spark friendships', 'avatar' => 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?auto=format&fit=crop&w=240&q=80', 'video_title' => 'A day in the life of a WeRoad Leader']
];

// Community Stories from Reviews
$stories = [];
if (!empty($dbReviews)) {
    foreach (array_slice($dbReviews, 0, 4) as $idx => $st) {
        $stName = $st['author_name'] ?? 'Traveler';
        $imgs = [
            'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=600&q=80',
            'https://images.unsplash.com/photo-1486870591958-9b9d0d1dda99?auto=format&fit=crop&w=600&q=80',
            'https://images.unsplash.com/photo-1544644181-1484b3fdfc62?auto=format&fit=crop&w=600&q=80',
            'https://images.unsplash.com/photo-1517457373958-b7bdd4587205?auto=format&fit=crop&w=600&q=80'
        ];
        $stories[] = [
            'name'  => $stName,
            'quote' => $st['comment'] ?? '',
            'img'   => $imgs[$idx % count($imgs)],
            'dest'  => 'Group Adventure'
        ];
    }
}
if (empty($stories)) {
    $stories = [
        ['name' => 'Ryan, 37', 'quote' => 'I was alone, but we traveled together. Chile and Bolivia brought us together.', 'img' => 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=600&q=80', 'dest' => 'Chile & Bolivia'],
        ['name' => 'Aurora, 27', 'quote' => 'I remember the moment in Nepal when I realized we had become friends.', 'img' => 'https://images.unsplash.com/photo-1486870591958-9b9d0d1dda99?auto=format&fit=crop&w=600&q=80', 'dest' => 'Nepal Himalayas'],
        ['name' => 'Colin, 33', 'quote' => 'I was out of my comfort zone. But with the group, we felt at home right away.', 'img' => 'https://images.unsplash.com/photo-1544644181-1484b3fdfc62?auto=format&fit=crop&w=600&q=80', 'dest' => 'Thailand Waters'],
        ['name' => 'Adam, 38', 'quote' => "The trip ends, the friendship stays. I couldn't imagine my life without these people anymore.", 'img' => 'https://images.unsplash.com/photo-1517457373958-b7bdd4587205?auto=format&fit=crop&w=600&q=80', 'dest' => 'Greek Islands'],
    ];
}

// Build Autumn Inspo & Recommended lists from DB trips
$autumnInspo = [];
$recommended = [];

if (!empty($dbTrips)) {
    foreach ($dbTrips as $idx => $trip) {
        $pricing = formatTripPricing($trip);
        $tripItem = [
            'id'             => $trip['id'],
            'title'          => ($isAr && !empty($trip['name_ar'])) ? $trip['name_ar'] : $trip['name'],
            'slug'           => $trip['slug'],
            'days'           => $trip['duration_days'] . ' ' . ($isAr ? 'أيام' : 'days'),
            'rating'         => $trip['rating'],
            'price'          => $pricing['price'],
            'old_price'      => $pricing['old_price'],
            'discount'       => $pricing['discount'],
            'img'            => !empty($trip['image_url']) ? $trip['image_url'] : 'https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?auto=format&fit=crop&w=600&q=80',
            'badge'          => ($idx === 3) ? 'winter trend' : ''
        ];

        if ($idx < 6) {
            $autumnInspo[] = $tripItem;
        }
        $recommended[] = $tripItem;
    }
}

// Trustpilot reviews list from DB
$trustpilotList = [];
if (!empty($dbReviews)) {
    foreach ($dbReviews as $r) {
        $name = $r['author_name'] ?? 'Traveler';
        $initial = strtoupper(substr($name, 0, 1));
        $dateStr = !empty($r['date']) ? date('d M Y', strtotime($r['date'])) : '16 Apr 2026';
        $trustpilotList[] = [
            'name'    => $name,
            'initial' => $initial,
            'date'    => $dateStr,
            'text'    => $r['comment'] ?? ''
        ];
    }
}

// Fallback FAQs if DB is empty
if (empty($dbFaqs)) {
    $dbFaqs = [
        ['question' => 'Are flights included?', 'answer' => 'Flights are not included in the package so that you have total flexibility to choose your departure airport, favorite airline, and extend your stay if you wish. We will provide recommended arrival and departure times for your group.'],
        ['question' => "Can I see who's in the group before I book the trip?", 'answer' => "You can see the age range and gender breakdown of booked travelers on each trip date. Once your booking is confirmed, you'll be invited to the dedicated group chat where you can meet your fellow travelers and Group Leader before departure!"],
        ['question' => 'Will I be sharing a room?', 'answer' => 'Yes, our trips are based on twin or triple shared rooms with fellow travelers of the same gender. It is a fantastic way to bond and keeps prices accessible. Single room upgrades may be available on select itineraries.'],
        ['question' => 'Who is the Group Leader and what is their role?', 'answer' => 'A Group Leader is an experienced fellow traveler who handles logistics, coordinates group activities, resolves day-to-day questions, and ensures everyone feels included and has the best time of their lives.'],
        ['question' => 'What does the package include?', 'answer' => 'The package includes all accommodation, internal transport (private vans, ferries, trains), local activities & entrance fees mentioned in the itinerary, travel medical insurance, and 24/7 support from your Group Leader.']
    ];
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>" dir="<?= $dir ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($companyName) ?> — Discover the world. Make new friends.</title>
  <meta name="description" content="Travel in small groups, with people your age. Group adventure tours around the world with certified Group Leaders.">
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700;800&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="/assets/gaia.css">
  
  <style>
    :root {
      --weroad-red: #ff334b;
      --weroad-red-hover: #e0263c;
      --weroad-dark: #111317;
      --weroad-text: #191c21;
      --weroad-muted: #5e6573;
      --weroad-border: #e6e8ec;
      --weroad-bg-light: #f7f9fa;
      --weroad-bg-peach: #fcf4ee;
      --weroad-bg-blue: #ebf5fa;
      --weroad-green: #00b67a;
      --weroad-green-bg: #e2f8eb;
      --weroad-yellow-highlight: #fff1a8;
      --radius-sm: 8px;
      --radius-md: 14px;
      --radius-lg: 20px;
      --radius-full: 9999px;
      --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
      --shadow-md: 0 8px 24px rgba(0,0,0,0.08);
      --shadow-lg: 0 16px 40px rgba(0,0,0,0.14);
      --font-heading: 'Plus Jakarta Sans', sans-serif;
      --font-body: 'Inter', system-ui, -apple-system, sans-serif;
    }

    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body {
      font-family: var(--font-body);
      color: var(--weroad-text);
      background: #ffffff;
      -webkit-font-smoothing: antialiased;
      line-height: 1.5;
      margin: 0;
    }

    a { text-decoration: none; color: inherit; transition: color .2s ease; }
    img { max-width: 100%; display: block; }
    button { font-family: inherit; cursor: pointer; border: none; background: none; }

    .container {
      max-width: 1240px;
      margin: 0 auto;
      padding: 0 24px;
    }

    /* ============================================================
       HERO SECTION (with overlay header support)
       ============================================================ */
    .hero-section {
      position: relative;
      min-height: 580px;
      background: url('https://images.unsplash.com/photo-1682687220063-4742bd7fd538?auto=format&fit=crop&w=1920&q=85') center 35%/cover no-repeat;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 140px 24px 70px;
    }
    .hero-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(180deg, rgba(6,33,65,0.4) 0%, rgba(6,33,65,0.5) 60%, rgba(6,33,65,0.8) 100%);
    }
    .hero-content {
      position: relative;
      z-index: 2;
      max-width: 820px;
      margin: 0 auto;
    }
    .hero-title {
      font-family: var(--font-heading);
      color: #ffffff;
      font-size: 50px;
      font-weight: 800;
      letter-spacing: -0.5px;
      line-height: 1.15;
      margin-bottom: 12px;
      text-shadow: 0 3px 20px rgba(0,0,0,0.35);
    }
    .hero-sub {
      color: #ffffff;
      font-size: 18px;
      font-weight: 500;
      text-shadow: 0 2px 10px rgba(0,0,0,0.3);
      margin-bottom: 36px;
    }

    /* Floating Search Bar */
    .search-box {
      background: #ffffff;
      border-radius: 16px;
      padding: 8px 12px 8px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      max-width: 680px;
      margin: 0 auto;
      box-shadow: 0 20px 45px rgba(0,0,0,0.28);
      text-align: left;
    }
    .search-field {
      flex: 1;
      padding: 6px 12px;
    }
    .search-field label {
      display: block;
      font-size: 11px;
      font-weight: 700;
      color: #8b92a0;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 2px;
    }
    .search-field input, .search-field select {
      border: none;
      outline: none;
      font-size: 16px;
      font-weight: 600;
      color: var(--weroad-dark);
      background: transparent;
      width: 100%;
      font-family: inherit;
      cursor: pointer;
    }
    .search-divider {
      width: 1px;
      height: 36px;
      background: var(--weroad-border);
      margin: 0 8px;
    }
    .search-submit-btn {
      background: var(--weroad-red);
      color: #ffffff;
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 17px;
      transition: background .2s, transform .2s;
      flex-shrink: 0;
    }
    .search-submit-btn:hover {
      background: var(--weroad-red-hover);
      transform: scale(1.04);
    }

    .hero-links {
      margin-top: 18px;
    }
    .perfect-trip-link {
      color: #ffffff;
      font-size: 13.5px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      text-shadow: 0 1px 4px rgba(0,0,0,0.4);
    }
    .perfect-trip-link:hover {
      text-decoration: underline;
    }

    .social-proof-bar {
      margin-top: 26px;
      color: #ffffff;
      font-size: 13px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-wrap: wrap;
      gap: 16px;
      text-shadow: 0 1px 6px rgba(0,0,0,0.5);
    }
    .stars-badge {
      display: inline-flex;
      align-items: center;
      gap: 3px;
      margin-left: 4px;
    }
    .star-box {
      background: var(--weroad-green);
      color: #ffffff;
      width: 16px;
      height: 16px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 10px;
      border-radius: 2px;
    }

    /* Floating Help Widget */
    .floating-help-btn {
      position: fixed;
      bottom: 24px;
      right: 24px;
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: #191c21;
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.25);
      z-index: 99;
      transition: transform .2s;
    }
    .floating-help-btn:hover {
      transform: scale(1.08);
    }

    /* ============================================================
       SECTION: WHY WEROAD
       ============================================================ */
    .why-section {
      padding: 50px 0 40px;
    }
    .section-headline {
      font-family: var(--font-heading);
      font-size: 26px;
      font-weight: 800;
      color: var(--weroad-text);
      margin-bottom: 20px;
    }
    .section-subtitle {
      font-size: 14.5px;
      color: var(--weroad-muted);
      margin-top: -14px;
      margin-bottom: 24px;
    }

    .why-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
    }
    .why-pill-card {
      border: 1px solid var(--weroad-border);
      border-radius: 14px;
      padding: 12px 14px;
      display: flex;
      align-items: center;
      gap: 12px;
      background: #ffffff;
      transition: transform .2s, box-shadow .2s;
    }
    .why-pill-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-sm);
    }
    .why-avatar {
      width: 46px;
      height: 46px;
      border-radius: 50%;
      object-fit: cover;
      flex-shrink: 0;
    }
    .why-text {
      font-size: 12.5px;
      font-weight: 500;
      line-height: 1.35;
      color: #2b303a;
    }
    .highlight-pill {
      background: #daf3e5;
      color: #0b6b3e;
      padding: 2px 6px;
      border-radius: 6px;
      font-weight: 700;
      display: inline;
    }

    /* ============================================================
       SECTION: TOUR CATEGORIES / COLLECTIONS
       ============================================================ */
    .categories-section {
      padding: 20px 0 50px;
    }
    .category-cards-grid {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 16px;
    }
    .cat-card {
      position: relative;
      height: 250px;
      border-radius: 16px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      padding: 16px;
      color: #ffffff;
      box-shadow: var(--shadow-sm);
      transition: transform .3s ease, box-shadow .3s ease;
    }
    .cat-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-lg);
    }
    .cat-card img {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform .5s ease;
    }
    .cat-card:hover img {
      transform: scale(1.06);
    }
    .cat-gradient {
      position: absolute;
      inset: 0;
      background: linear-gradient(180deg, rgba(0,0,0,0) 40%, rgba(0,0,0,0.85) 100%);
    }
    .cat-content {
      position: relative;
      z-index: 2;
    }
    .cat-title {
      font-family: var(--font-heading);
      font-size: 15px;
      font-weight: 700;
      line-height: 1.25;
      margin-bottom: 4px;
    }
    .cat-price {
      font-size: 12px;
      font-weight: 500;
      opacity: 0.9;
    }

    /* ============================================================
       SECTION: BEHIND THE SCENES
       ============================================================ */
    .behind-scenes-section {
      padding: 20px 0 50px;
      text-align: center;
    }
    .behind-scenes-section .section-headline {
      text-align: center;
      margin-bottom: 30px;
    }
    .scenes-grid {
      display: flex;
      justify-content: center;
      gap: 60px;
      flex-wrap: wrap;
    }
    .scene-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      cursor: pointer;
      max-width: 180px;
    }
    .scene-avatar-wrap {
      position: relative;
      width: 96px;
      height: 96px;
      border-radius: 50%;
      padding: 3px;
      background: linear-gradient(135deg, var(--weroad-red) 0%, #ff8a00 100%);
      margin-bottom: 12px;
      transition: transform .25s ease;
    }
    .scene-item:hover .scene-avatar-wrap {
      transform: scale(1.06);
    }
    .scene-avatar-wrap img {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      object-fit: cover;
      background: #fff;
    }
    .scene-play-icon {
      position: absolute;
      bottom: 2px;
      right: 2px;
      width: 26px;
      height: 26px;
      border-radius: 50%;
      background: #111317;
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 10px;
      border: 2px solid #ffffff;
    }
    .scene-title {
      font-size: 13.5px;
      font-weight: 700;
      color: var(--weroad-dark);
      margin-bottom: 2px;
    }
    .scene-role {
      font-size: 11.5px;
      color: var(--weroad-muted);
    }

    /* ============================================================
       SECTION: PERKS PROMO CARD
       ============================================================ */
    .perks-card {
      background: var(--weroad-bg-blue);
      border-radius: 16px;
      padding: 26px 36px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 30px;
      margin: 10px 0 60px;
      border: 1px solid #d8eaf5;
    }
    .perks-left h3 {
      font-family: var(--font-heading);
      font-size: 20px;
      font-weight: 800;
      color: #0e2942;
      line-height: 1.25;
      max-width: 260px;
    }
    .perks-checklist {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .perks-checklist li {
      font-size: 13px;
      color: #274763;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .perks-checklist i {
      color: #0b7aa8;
      font-size: 13px;
    }
    .btn-signup-black {
      background: #111317;
      color: #ffffff;
      padding: 10px 24px;
      border-radius: var(--radius-full);
      font-size: 14px;
      font-weight: 700;
      white-space: nowrap;
      transition: background .2s, transform .2s;
    }
    .btn-signup-black:hover {
      background: #2b303a;
      transform: translateY(-1px);
    }

    /* ============================================================
       SECTION: STORIES / COMMUNITY
       ============================================================ */
    .community-section {
      background: var(--weroad-bg-peach);
      padding: 70px 0 60px;
    }
    .community-headline {
      font-family: var(--font-heading);
      font-size: 28px;
      font-weight: 800;
      color: var(--weroad-dark);
      text-align: left;
      margin-bottom: 8px;
    }
    .community-sub {
      font-size: 15px;
      color: var(--weroad-muted);
      margin-bottom: 36px;
    }
    .highlight-yellow {
      background: var(--weroad-yellow-highlight);
      padding: 2px 6px;
      font-weight: 700;
      color: #2e2600;
      border-radius: 4px;
    }
    .stories-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
      margin-bottom: 50px;
    }
    .story-card {
      background: #ffffff;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: var(--shadow-sm);
      display: flex;
      flex-direction: column;
    }
    .story-img-wrap {
      position: relative;
      height: 220px;
    }
    .story-img-wrap img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .story-play-btn {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: rgba(0,0,0,0.55);
      backdrop-filter: blur(4px);
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      border: 2px solid rgba(255,255,255,0.8);
      transition: background .2s, transform .2s;
    }
    .story-card:hover .story-play-btn {
      background: var(--weroad-red);
      transform: translate(-50%, -50%) scale(1.1);
    }
    .story-body {
      padding: 16px;
      flex: 1;
      display: flex;
      flex-direction: column;
    }
    .story-author {
      font-family: var(--font-heading);
      font-size: 14px;
      font-weight: 700;
      color: var(--weroad-dark);
      margin-bottom: 6px;
    }
    .story-quote {
      font-size: 12.5px;
      color: #4a5160;
      line-height: 1.45;
    }

    /* WeMeet Sub-block */
    .wemeet-block {
      text-align: center;
      max-width: 580px;
      margin: 0 auto;
    }
    .wemeet-avatars {
      display: flex;
      justify-content: center;
      align-items: center;
      margin-bottom: 14px;
    }
    .wemeet-avatars img {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #ffffff;
      margin-left: -10px;
    }
    .wemeet-avatars img:first-child { margin-left: 0; }
    .wemeet-title {
      font-family: var(--font-heading);
      font-size: 16px;
      font-weight: 700;
      margin-bottom: 4px;
    }
    .wemeet-sub {
      font-size: 13px;
      color: var(--weroad-muted);
      margin-bottom: 10px;
    }
    .wemeet-link {
      font-size: 13px;
      font-weight: 700;
      color: var(--weroad-dark);
      text-decoration: underline;
      display: inline-block;
      margin-bottom: 16px;
    }
    .app-badges {
      display: flex;
      justify-content: center;
      gap: 12px;
    }
    .app-badge-btn {
      background: #111317;
      color: #ffffff;
      padding: 6px 14px;
      border-radius: 8px;
      font-size: 11px;
      display: flex;
      align-items: center;
      gap: 6px;
      font-weight: 600;
    }
    .app-badge-btn i { font-size: 14px; }

    /* ============================================================
       SECTION: MOST LOVED ADVENTURES (EDITORIAL BENTO GRID)
       ============================================================ */
    .loved-section {
      padding: 60px 0;
    }
    .bento-grid {
      display: grid;
      grid-template-columns: 1fr 1.35fr;
      gap: 20px;
    }
    .bento-card {
      border-radius: 16px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      box-shadow: var(--shadow-sm);
      background: #ffffff;
    }
    .bento-img {
      position: relative;
      overflow: hidden;
    }
    .bento-card-tall .bento-img {
      height: 400px;
    }
    .bento-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform .5s ease;
    }
    .bento-card:hover .bento-img img {
      transform: scale(1.04);
    }
    .bento-body {
      padding: 16px 18px;
    }
    .bento-price-tag {
      font-size: 11.5px;
      font-weight: 600;
      color: var(--weroad-muted);
      margin-bottom: 4px;
    }
    .bento-desc {
      font-size: 13px;
      color: #2b303a;
      line-height: 1.45;
    }

    .bento-right-col {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .bento-card-wide .bento-img {
      height: 200px;
    }
    .bento-split-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      flex: 1;
    }
    .bento-card-small .bento-img {
      height: 140px;
    }

    /* ============================================================
       SECTION: AUTUMN INSPO & RECOMMENDED CAROUSELS
       ============================================================ */
    .inspo-section {
      padding: 30px 0 50px;
    }
    .inspo-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
    }
    .inspo-title {
      font-family: var(--font-heading);
      font-size: 24px;
      font-weight: 800;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .see-all-link {
      font-size: 13.5px;
      font-weight: 600;
      color: var(--weroad-muted);
      text-decoration: underline;
    }

    .trips-carousel-grid {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 14px;
      overflow-x: auto;
      padding-bottom: 12px;
      scroll-snap-type: x mandatory;
    }
    .trips-carousel-grid::-webkit-scrollbar {
      height: 6px;
    }
    .trips-carousel-grid::-webkit-scrollbar-thumb {
      background: #e2e4e8;
      border-radius: 6px;
    }

    .trip-card {
      min-width: 180px;
      background: #ffffff;
      border-radius: 14px;
      overflow: hidden;
      border: 1px solid var(--weroad-border);
      display: flex;
      flex-direction: column;
      scroll-snap-align: start;
      transition: transform .2s, box-shadow .2s;
    }
    .trip-card:hover {
      transform: translateY(-3px);
      box-shadow: var(--shadow-md);
    }
    .trip-thumb {
      position: relative;
      height: 120px;
      overflow: hidden;
    }
    .trip-thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .trip-fav-btn {
      position: absolute;
      top: 8px;
      right: 8px;
      width: 26px;
      height: 26px;
      border-radius: 50%;
      background: rgba(0,0,0,0.4);
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      cursor: pointer;
      transition: background .2s, color .2s;
    }
    .trip-fav-btn:hover, .trip-fav-btn.active {
      background: #ffffff;
      color: var(--weroad-red);
    }
    .trip-badge-trend {
      position: absolute;
      bottom: 8px;
      left: 8px;
      background: rgba(17,19,23,0.75);
      backdrop-filter: blur(2px);
      color: #ffffff;
      font-size: 9.5px;
      padding: 2px 6px;
      border-radius: 4px;
      text-transform: lowercase;
    }
    .trip-meta-bar {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      padding: 6px 8px;
      background: linear-gradient(180deg, transparent 0%, rgba(0,0,0,0.75) 100%);
      display: flex;
      justify-content: space-between;
      color: #ffffff;
      font-size: 10px;
      font-weight: 600;
    }
    .trip-rating {
      display: flex;
      align-items: center;
      gap: 3px;
    }
    .trip-rating i { color: #f5a623; font-size: 9px; }

    .trip-details {
      padding: 10px;
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .trip-name {
      font-size: 11.5px;
      font-weight: 700;
      line-height: 1.35;
      color: var(--weroad-dark);
      margin-bottom: 8px;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .trip-price-row {
      display: flex;
      align-items: baseline;
      gap: 4px;
      flex-wrap: wrap;
      font-size: 11px;
    }
    .trip-from { color: var(--weroad-muted); }
    .trip-current-price { font-weight: 800; color: var(--weroad-dark); }
    .trip-old-price { color: #9aa0ac; text-decoration: line-through; font-size: 10px; }
    .trip-discount-badge { color: var(--weroad-red); font-weight: 700; font-size: 10px; }

    .carousel-dots {
      display: flex;
      justify-content: center;
      gap: 6px;
      margin-top: 14px;
    }
    .c-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #d0d4dc;
    }
    .c-dot.active {
      background: var(--weroad-red);
      width: 14px;
      border-radius: 4px;
    }

    /* ============================================================
       SECTION: BOOK WITH CONFIDENCE
       ============================================================ */
    .confidence-section {
      padding: 50px 0;
    }
    .confidence-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
    }
    .conf-card {
      border: 1px solid var(--weroad-border);
      border-radius: 14px;
      padding: 20px 18px;
      background: #ffffff;
      position: relative;
    }
    .conf-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 10px;
    }
    .conf-icon {
      font-size: 20px;
      color: var(--weroad-dark);
    }
    .badge-new {
      background: #0f172a;
      color: #ffffff;
      font-size: 9px;
      font-weight: 800;
      padding: 2px 6px;
      border-radius: 4px;
      letter-spacing: 0.5px;
    }
    .conf-title {
      font-family: var(--font-heading);
      font-size: 14.5px;
      font-weight: 800;
      color: var(--weroad-dark);
      margin-bottom: 6px;
    }
    .conf-desc {
      font-size: 12px;
      color: var(--weroad-muted);
      line-height: 1.4;
    }

    /* ============================================================
       SECTION: FAQS ACCORDION
       ============================================================ */
    .faq-section {
      background: #eef7fc;
      padding: 70px 0 60px;
    }
    .faq-section .section-headline {
      text-align: center;
      margin-bottom: 30px;
    }
    .faq-accordion {
      max-width: 740px;
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .faq-card {
      background: #ffffff;
      border-radius: 12px;
      overflow: hidden;
      border: 1px solid #dce8ef;
    }
    .faq-question {
      padding: 16px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      color: var(--weroad-dark);
      transition: background .2s;
    }
    .faq-question:hover {
      background: #f9fbfd;
    }
    .faq-question i {
      font-size: 12px;
      color: var(--weroad-muted);
      transition: transform .25s ease;
    }
    .faq-card.open .faq-question i {
      transform: rotate(180deg);
      color: var(--weroad-red);
    }
    .faq-answer {
      max-height: 0;
      overflow: hidden;
      transition: max-height .3s ease, padding .3s ease;
      font-size: 13px;
      color: #555e6d;
      line-height: 1.55;
      padding: 0 20px;
    }
    .faq-card.open .faq-answer {
      max-height: 200px;
      padding: 0 20px 16px;
    }
    .faq-footer-action {
      text-align: center;
      margin-top: 26px;
    }
    .btn-read-faqs {
      background: #111317;
      color: #ffffff;
      padding: 10px 24px;
      border-radius: var(--radius-full);
      font-size: 13.5px;
      font-weight: 700;
      display: inline-block;
      margin-bottom: 18px;
    }
    .faq-support-row {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      font-size: 13px;
      color: var(--weroad-muted);
    }
    .faq-team-avatars {
      display: flex;
      align-items: center;
    }
    .faq-team-avatars img {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      border: 2px solid #fff;
      margin-left: -6px;
    }
    .faq-team-avatars img:first-child { margin-left: 0; }
    .faq-support-row a {
      font-weight: 700;
      color: var(--weroad-dark);
      text-decoration: underline;
    }

    /* ============================================================
       SECTION: TRUSTPILOT REVIEWS & PRESS
       ============================================================ */
    .reviews-press-section {
      padding: 70px 0 60px;
    }
    .tp-headline {
      text-align: center;
      font-family: var(--font-heading);
      font-size: 24px;
      font-weight: 800;
      margin-bottom: 6px;
    }
    .tp-stars-row {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      font-size: 13.5px;
      font-weight: 600;
      margin-bottom: 34px;
    }
    .tp-green-stars {
      display: flex;
      gap: 2px;
    }
    .tp-box {
      background: var(--weroad-green);
      color: #ffffff;
      width: 18px;
      height: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 10px;
    }

    .reviews-masonry-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 18px;
      margin-bottom: 50px;
    }
    .review-card {
      background: #ffffff;
      border: 1px solid var(--weroad-border);
      border-radius: 14px;
      padding: 18px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .rev-top {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
    }
    .rev-quote-ic {
      font-size: 18px;
      color: var(--weroad-muted);
      font-family: serif;
      font-weight: 800;
    }
    .rev-date {
      font-size: 11px;
      color: #9aa0ac;
    }
    .rev-text {
      font-size: 12.5px;
      color: #383e4a;
      line-height: 1.45;
      margin-bottom: 14px;
      flex: 1;
    }
    .rev-author {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .rev-avatar-circle {
      width: 26px;
      height: 26px;
      border-radius: 50%;
      background: #f0f2f5;
      color: #555e6d;
      font-size: 11px;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .rev-name {
      font-size: 12px;
      font-weight: 600;
      color: var(--weroad-dark);
    }

    /* Press Bar */
    .press-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
    }
    .press-card {
      border: 1px solid var(--weroad-border);
      border-radius: 12px;
      padding: 18px 16px;
      text-align: center;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      background: #fafbfc;
    }
    .press-logo {
      font-family: var(--font-heading);
      font-weight: 900;
      font-size: 14px;
      color: #111317;
      letter-spacing: 0.5px;
      margin-bottom: 8px;
      text-transform: uppercase;
    }
    .press-quote {
      font-size: 11px;
      color: var(--weroad-muted);
      line-height: 1.35;
    }

    /* ============================================================
       SECTION: ADVENTURE CTA BANNER
       ============================================================ */
    .adventure-cta-section {
      padding: 40px 0 0;
    }
    .adventure-banner {
      position: relative;
      border-radius: 20px 20px 0 0;
      overflow: hidden;
      min-height: 320px;
      background: url('https://images.unsplash.com/photo-1509316975850-ff9c5deb0cd9?auto=format&fit=crop&w=1920&q=80') center 60%/cover no-repeat;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 40px 20px;
    }
    .adventure-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(180deg, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.6) 100%);
    }
    .adventure-content {
      position: relative;
      z-index: 2;
    }
    .adventure-title {
      font-family: var(--font-heading);
      font-size: 36px;
      font-weight: 800;
      color: #ffffff;
      margin-bottom: 20px;
      text-shadow: 0 2px 14px rgba(0,0,0,0.4);
    }
    .btn-find-trip {
      background: #111317;
      color: #ffffff;
      padding: 12px 30px;
      border-radius: var(--radius-full);
      font-size: 14.5px;
      font-weight: 700;
      display: inline-block;
      transition: background .2s, transform .2s;
    }
    .btn-find-trip:hover {
      background: #2b303a;
      transform: scale(1.04);
    }

    /* ============================================================
       SECTION: FULL FOOTER
       ============================================================ */
    .weroad-footer {
      background: #0f1115;
      color: #c5c9d3;
      padding: 50px 0 30px;
      font-size: 12.5px;
    }
    .footer-newsletter-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
      padding-bottom: 36px;
      border-bottom: 1px solid #232731;
      margin-bottom: 40px;
      flex-wrap: wrap;
    }
    .newsletter-form {
      display: flex;
      align-items: center;
      gap: 10px;
      flex: 1;
      max-width: 480px;
    }
    .newsletter-form input {
      background: #1c2028;
      border: 1px solid #303746;
      border-radius: var(--radius-full);
      padding: 10px 18px;
      font-size: 13px;
      color: #ffffff;
      outline: none;
      width: 100%;
    }
    .newsletter-form button {
      background: #ffffff;
      color: #0f1115;
      padding: 10px 22px;
      border-radius: var(--radius-full);
      font-size: 13px;
      font-weight: 700;
      white-space: nowrap;
      transition: background .2s;
    }
    .newsletter-form button:hover {
      background: #e2e4e8;
    }
    .footer-socials {
      display: flex;
      gap: 14px;
    }
    .footer-socials a {
      color: #c5c9d3;
      font-size: 16px;
      transition: color .2s;
    }
    .footer-socials a:hover {
      color: #ffffff;
    }
    .newsletter-disclaimer {
      width: 100%;
      font-size: 11px;
      color: #727a8c;
      margin-top: -24px;
      margin-bottom: 20px;
    }
    .newsletter-disclaimer a { color: #a4adbf; text-decoration: underline; }

    .footer-links-grid {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 24px;
      margin-bottom: 40px;
    }
    .footer-col h5 {
      color: #ffffff;
      font-size: 13px;
      font-weight: 700;
      margin-bottom: 14px;
    }
    .footer-col ul {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .footer-col a {
      color: #939aa8;
      font-size: 11.5px;
      transition: color .2s;
    }
    .footer-col a:hover {
      color: #ffffff;
    }

    .footer-legal-bottom {
      border-top: 1px solid #232731;
      padding-top: 30px;
      text-align: center;
      font-size: 11px;
      color: #727a8c;
    }
    .footer-trust-row {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-bottom: 12px;
    }
    .footer-badges-row {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 16px;
      margin-bottom: 12px;
    }
    .abta-logo {
      font-weight: 900;
      font-size: 16px;
      color: #ffffff;
      letter-spacing: 1px;
    }
    .payment-badges {
      display: flex;
      justify-content: center;
      gap: 8px;
      margin-top: 16px;
      flex-wrap: wrap;
    }
    .pay-badge {
      background: #1c2028;
      border: 1px solid #2e3442;
      color: #ffffff;
      font-size: 10px;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 4px;
    }

    /* ============================================================
       RESPONSIVE BREAKPOINTS
       ============================================================ */
    @media (max-width: 1080px) {
      .why-grid, .confidence-grid { grid-template-columns: repeat(2, 1fr); }
      .category-cards-grid { grid-template-columns: repeat(3, 1fr); }
      .stories-grid { grid-template-columns: repeat(2, 1fr); }
      .bento-grid { grid-template-columns: 1fr; }
      .bento-card-tall .bento-img { height: 260px; }
      .reviews-masonry-grid { grid-template-columns: repeat(2, 1fr); }
      .press-grid { grid-template-columns: repeat(2, 1fr); }
      .footer-links-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 768px) {
      .hero-title { font-size: 34px; }
      .hero-sub { font-size: 15px; }
      .search-box { flex-direction: column; gap: 10px; padding: 14px; }
      .search-divider { display: none; }
      .search-submit-btn { width: 100%; border-radius: 10px; height: 42px; }
      .why-grid, .confidence-grid { grid-template-columns: 1fr; }
      .category-cards-grid { grid-template-columns: 1fr 1fr; }
      .scenes-grid { gap: 24px; }
      .perks-card { flex-direction: column; text-align: center; }
      .perks-left h3 { max-width: 100%; }
      .stories-grid { grid-template-columns: 1fr; }
      .bento-split-row { grid-template-columns: 1fr; }
      .reviews-masonry-grid { grid-template-columns: 1fr; }
      .press-grid { grid-template-columns: 1fr; }
      .footer-links-grid { grid-template-columns: repeat(2, 1fr); }
      .footer-newsletter-bar { flex-direction: column; align-items: stretch; }
      .newsletter-form { max-width: 100%; }
    }
  </style>
</head>
<body>

  <!-- ================= GLOBAL GAIA HEADER (ORIGINAL LOGO & LINKS) ================= -->
  <?php require __DIR__ . '/components/gaia-header.php'; ?>

  <!-- ================= HERO SECTION ================= -->
  <section class="hero-section" style="background: url('<?= htmlspecialchars($heroBg) ?>') center 35%/cover no-repeat;">
    <div class="hero-overlay"></div>
    <div class="container hero-content">
      <h1 class="hero-title"><?= htmlspecialchars($heroTitle) ?></h1>
      <p class="hero-sub"><?= htmlspecialchars($heroSub) ?></p>

      <!-- Floating Search Box (DB Connected) -->
      <form class="search-box" action="/tours" method="get">
        <div class="search-field">
          <label>Where?</label>
          <input type="text" name="destination" list="destinationsList" placeholder="Anywhere" autocomplete="off">
          <datalist id="destinationsList">
            <?php foreach ($dbDestinations as $dest): ?>
              <option value="<?= htmlspecialchars($dest['name']) ?>"></option>
            <?php endforeach; ?>
            <option value="Japan"></option>
            <option value="Morocco"></option>
            <option value="Iceland"></option>
            <option value="Thailand"></option>
            <option value="Vietnam"></option>
            <option value="Peru"></option>
          </datalist>
        </div>
        <div class="search-divider"></div>
        <div class="search-field">
          <label>When?</label>
          <input type="text" name="date" placeholder="Whenever" onfocus="(this.type='month')" onblur="(this.type='text')">
        </div>
        <button type="submit" class="search-submit-btn" aria-label="Search trips">
          <i class="fa-solid fa-magnifying-glass"></i>
        </button>
      </form>

      <div class="hero-links">
        <a href="/tours?recommend=1" class="perfect-trip-link">
          Not sure where to go? Describe your perfect trip ✨
        </a>
      </div>

      <div class="social-proof-bar">
        <span>🤝 <strong>Trusted by more than 300,000 travelers</strong> 🎒</span>
        <span style="opacity:0.6">•</span>
        <span>Our rating is <strong>Excellent</strong></span>
        <span class="stars-badge">
          <span class="star-box"><i class="fa-solid fa-star"></i></span>
          <span class="star-box"><i class="fa-solid fa-star"></i></span>
          <span class="star-box"><i class="fa-solid fa-star"></i></span>
          <span class="star-box"><i class="fa-solid fa-star"></i></span>
          <span class="star-box"><i class="fa-solid fa-star"></i></span>
          <strong style="margin-left:4px;">4.7</strong> out of 5
        </span>
      </div>
    </div>
  </section>

  <!-- ================= FLOATING HELP ICON ================= -->
  <a class="floating-help-btn" href="https://wa.me/<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $contactPhone)) ?>" target="_blank" rel="noopener" aria-label="Help & Support" title="Chat on WhatsApp">
    <i class="fa-regular fa-comment-dots"></i>
  </a>

  <!-- ================= SECTION: WHY WEROAD ================= -->
  <section class="why-section">
    <div class="container">
      <h2 class="section-headline">Why WeRoad</h2>
      <div class="why-grid">
        <?php foreach ($dbWhyHighlights as $w): ?>
          <div class="why-pill-card">
            <img src="<?= htmlspecialchars($w['avatar']) ?>" alt="Why WeRoad" class="why-avatar">
            <div class="why-text">
              <?= $w['text'] ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ================= SECTION: TOUR CATEGORIES ================= -->
  <section class="categories-section">
    <div class="container">
      <h2 class="section-headline">The group tour that's right for you</h2>
      <p class="section-subtitle">Itineraries designed for every style, length, and budget</p>

      <div class="category-cards-grid">
        <?php foreach ($collections as $col): ?>
          <a href="<?= htmlspecialchars($col['link']) ?>" class="cat-card">
            <img src="<?= htmlspecialchars($col['img']) ?>" alt="<?= htmlspecialchars($col['title']) ?>" loading="lazy">
            <div class="cat-gradient"></div>
            <div class="cat-content">
              <h3 class="cat-title"><?= htmlspecialchars($col['title']) ?></h3>
              <div class="cat-price"><?= htmlspecialchars($col['price']) ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ================= SECTION: EXCLUSIVE PERKS ================= -->
  <section class="behind-scenes-section">
    <div class="container">
      <!-- Perks Card -->
      <div class="perks-card">
        <div class="perks-left">
          <h3>Join WeRoad and get instant access to exclusive perks</h3>
        </div>
        <ul class="perks-checklist">
          <li><i class="fa-solid fa-check"></i> <span><strong>Instant discounts</strong> with Outsite, Tropicfeel, Sally, and many other partners</span></li>
          <li><i class="fa-solid fa-check"></i> <span><strong>Exclusive promotions</strong> on selected itineraries and destinations</span></li>
          <li><i class="fa-solid fa-check"></i> <span><strong>Set alerts</strong> for the trips you love so you never miss a trip confirmation or deal</span></li>
        </ul>
        <div>
          <a href="/register" class="btn-signup-black">Sign up</a>
        </div>
      </div>
    </div>
  </section>

  <!-- ================= SECTION: COMMUNITY STORIES ================= -->
  <section class="community-section">
    <div class="container">
      <h2 class="community-headline">A trip creates a friendship. And then a thousand reasons to meet again.</h2>
      <p class="community-sub">
        Over <span class="highlight-yellow">300,000 WeRoaders</span> have traveled with us, and <span class="highlight-yellow">60% have already returned</span> for a second trip.
      </p>

      <div class="stories-grid">
        <?php foreach ($stories as $st): ?>
          <div class="story-card">
            <div class="story-img-wrap">
              <img src="<?= htmlspecialchars($st['img']) ?>" alt="<?= htmlspecialchars($st['name']) ?>" loading="lazy">
            </div>
            <div class="story-body">
              <div class="story-author"><?= htmlspecialchars($st['name']) ?></div>
              <p class="story-quote"><?= htmlspecialchars($st['quote']) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    </div>
  </section>

  <!-- ================= SECTION: MOST LOVED ADVENTURES ================= -->
  <section class="loved-section">
    <div class="container">
      <h2 class="section-headline">The most loved adventures</h2>
      <p class="section-subtitle">Top destinations for real adventure</p>

      <div class="bento-grid">
        <!-- Left Big Card: Japan -->
        <a href="/tours/japan-360" class="bento-card bento-card-tall">
          <div class="bento-img">
            <img src="https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?auto=format&fit=crop&w=800&q=80" alt="Japan Autumn" loading="lazy">
          </div>
          <div class="bento-body">
            <div class="bento-price-tag">Starting from $ 2,063</div>
            <p class="bento-desc">Japan in autumn comes alive with fiery red leaves, golden forests and ancient temples wrapped in seasonal magic.</p>
          </div>
        </a>

        <!-- Right Bento Column: Africa + Iceland & Naples -->
        <div class="bento-right-col">
          <!-- Top Card: Africa -->
          <a href="/tours/morocco-imperial-cities" class="bento-card bento-card-wide">
            <div class="bento-img">
              <img src="https://images.unsplash.com/photo-1516426122078-c23e76319801?auto=format&fit=crop&w=800&q=80" alt="African Safari" loading="lazy">
            </div>
            <div class="bento-body">
              <div class="bento-price-tag">Starting from $ 648</div>
              <p class="bento-desc">Africa bursts with wildlife, golden savannahs and unforgettable landscapes. Bold, untamed and made for adventure.</p>
            </div>
          </a>

          <!-- Bottom Split Row -->
          <div class="bento-split-row">
            <a href="/tours/iceland-ring-road" class="bento-card bento-card-small">
              <div class="bento-img">
                <img src="https://images.unsplash.com/photo-1517411032315-54ef2cb783bb?auto=format&fit=crop&w=500&q=80" alt="Iceland" loading="lazy">
              </div>
              <div class="bento-body">
                <div class="bento-price-tag">Starting from $ 985</div>
                <p class="bento-desc">Iceland takes you through glaciers, waterfalls and volcanic landscapes...</p>
              </div>
            </a>
            <a href="/tours/jordan-360" class="bento-card bento-card-small">
              <div class="bento-img">
                <img src="https://images.unsplash.com/photo-1533105079780-92b9be482077?auto=format&fit=crop&w=500&q=80" alt="Naples Italy" loading="lazy">
              </div>
              <div class="bento-body">
                <div class="bento-price-tag">Starting from $ 753</div>
                <p class="bento-desc">Naples brings together pizza, baroque streets and volcanic...</p>
              </div>
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ================= SECTION: AUTUMN INSPO ================= -->
  <section class="inspo-section">
    <div class="container">
      <div class="inspo-header">
        <h2 class="inspo-title">🍂 Autumn Inspo</h2>
        <a href="/tours?season=autumn" class="see-all-link">See trips here</a>
      </div>

      <div class="trips-carousel-grid">
        <?php foreach ($autumnInspo as $t): ?>
          <div class="trip-card">
            <div class="trip-thumb">
              <img src="<?= htmlspecialchars($t['img']) ?>" alt="<?= htmlspecialchars($t['title']) ?>" loading="lazy">
              <button class="trip-fav-btn" onclick="toggleFav(this, event)" aria-label="Favorite"><i class="fa-regular fa-heart"></i></button>
              <?php if (!empty($t['badge'])): ?>
                <div class="trip-badge-trend"><?= htmlspecialchars($t['badge']) ?></div>
              <?php endif; ?>
              <div class="trip-meta-bar">
                <span><?= htmlspecialchars($t['days']) ?></span>
                <span class="trip-rating"><i class="fa-solid fa-star"></i> <?= htmlspecialchars($t['rating']) ?></span>
              </div>
            </div>
            <div class="trip-details">
              <a href="/tours/<?= htmlspecialchars($t['slug']) ?>" class="trip-name"><?= htmlspecialchars($t['title']) ?></a>
              <div class="trip-price-row">
                <span class="trip-from">From</span>
                <span class="trip-current-price"><?= htmlspecialchars($t['price']) ?></span>
                <?php if (!empty($t['old_price'])): ?>
                  <span class="trip-old-price"><?= htmlspecialchars($t['old_price']) ?></span>
                <?php endif; ?>
                <?php if (!empty($t['discount'])): ?>
                  <span class="trip-discount-badge"><?= htmlspecialchars($t['discount']) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="carousel-dots">
        <span class="c-dot active"></span>
        <span class="c-dot"></span>
      </div>
    </div>
  </section>

  <!-- ================= SECTION: RECOMMENDED FOR YOU ================= -->
  <section class="inspo-section" style="padding-top:10px;">
    <div class="container">
      <div class="inspo-header">
        <h2 class="inspo-title" style="font-size:24px;">Recommended for you</h2>
      </div>

      <div class="trips-carousel-grid">
        <?php foreach ($recommended as $t): ?>
          <div class="trip-card">
            <div class="trip-thumb">
              <img src="<?= htmlspecialchars($t['img']) ?>" alt="<?= htmlspecialchars($t['title']) ?>" loading="lazy">
              <button class="trip-fav-btn" onclick="toggleFav(this, event)" aria-label="Favorite"><i class="fa-regular fa-heart"></i></button>
              <div class="trip-meta-bar">
                <span><?= htmlspecialchars($t['days']) ?></span>
                <span class="trip-rating"><i class="fa-solid fa-star"></i> <?= htmlspecialchars($t['rating']) ?></span>
              </div>
            </div>
            <div class="trip-details">
              <a href="/tours/<?= htmlspecialchars($t['slug']) ?>" class="trip-name"><?= htmlspecialchars($t['title']) ?></a>
              <div class="trip-price-row">
                <span class="trip-from">From</span>
                <span class="trip-current-price"><?= htmlspecialchars($t['price']) ?></span>
                <?php if (!empty($t['old_price'])): ?>
                  <span class="trip-old-price"><?= htmlspecialchars($t['old_price']) ?></span>
                <?php endif; ?>
                <?php if (!empty($t['discount'])): ?>
                  <span class="trip-discount-badge"><?= htmlspecialchars($t['discount']) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ================= SECTION: BOOK WITH CONFIDENCE ================= -->
  <section class="confidence-section">
    <div class="container">
      <h2 class="section-headline">Book with confidence</h2>
      <div class="confidence-grid">
        <?php foreach ($dbConfidence as $conf): ?>
          <div class="conf-card">
            <div class="conf-card-header">
              <i class="<?= htmlspecialchars($conf['icon']) ?> conf-icon"></i>
              <?php if (!empty($conf['badge'])): ?>
                <span class="badge-new"><?= htmlspecialchars($conf['badge']) ?></span>
              <?php endif; ?>
            </div>
            <h3 class="conf-title"><?= htmlspecialchars($conf['title']) ?></h3>
            <p class="conf-desc"><?= htmlspecialchars($conf['desc']) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ================= SECTION: 5 QUESTIONS FAQS ================= -->
  <section class="faq-section">
    <div class="container">
      <h2 class="section-headline">5 questions to ask before your WeRoad</h2>
      
      <div class="faq-accordion">
        <?php foreach ($dbFaqs as $i => $faq): ?>
          <?php 
            $qText = ($isAr && !empty($faq['question_ar'])) ? $faq['question_ar'] : $faq['question'];
            $aText = ($isAr && !empty($faq['answer_ar'])) ? $faq['answer_ar'] : $faq['answer'];
          ?>
          <div class="faq-card <?= $i === 0 ? 'open' : '' ?>">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span><?= htmlspecialchars($qText) ?></span>
              <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
              <?= htmlspecialchars($aText) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="faq-footer-action">
        <a href="/about#faqs" class="btn-read-faqs">Read all FAQs</a>
        <div class="faq-support-row">
          <div class="faq-team-avatars">
            <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=80&q=80" alt="Team">
            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=80&q=80" alt="Team">
            <img src="https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=80&q=80" alt="Team">
          </div>
          <span>Do you have any questions? <a href="/contact">Talk to us</a></span>
        </div>
      </div>
    </div>
  </section>

  <!-- ================= SECTION: RATE EXCELLENT ON TRUSTPILOT ================= -->
  <section class="reviews-press-section">
    <div class="container">
      <h2 class="tp-headline">Rate Excellent on Trustpilot</h2>
      <div class="tp-stars-row">
        <div class="tp-green-stars">
          <span class="tp-box"><i class="fa-solid fa-star"></i></span>
          <span class="tp-box"><i class="fa-solid fa-star"></i></span>
          <span class="tp-box"><i class="fa-solid fa-star"></i></span>
          <span class="tp-box"><i class="fa-solid fa-star"></i></span>
          <span class="tp-box"><i class="fa-solid fa-star"></i></span>
        </div>
        <span><strong>4.7 / 5</strong> (19627 reviews)</span>
      </div>

      <div class="reviews-masonry-grid">
        <?php foreach ($trustpilotList as $rev): ?>
          <div class="review-card">
            <div>
              <div class="rev-top">
                <span class="rev-quote-ic">“</span>
                <span class="rev-date"><?= htmlspecialchars($rev['date']) ?></span>
              </div>
              <p class="rev-text"><?= htmlspecialchars($rev['text']) ?></p>
            </div>
            <div class="rev-author">
              <div class="rev-avatar-circle"><?= htmlspecialchars($rev['initial']) ?></div>
              <div class="rev-name"><?= htmlspecialchars($rev['name']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Press Quotes -->
      <div class="press-grid">
        <?php foreach ($dbPress as $pr): ?>
          <div class="press-card">
            <div class="press-logo"><?= htmlspecialchars($pr['logo']) ?></div>
            <p class="press-quote"><?= htmlspecialchars($pr['quote']) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ================= SECTION: READY TO START THE ADVENTURE ================= -->
  <section class="adventure-cta-section">
    <div class="adventure-banner">
      <div class="adventure-overlay"></div>
      <div class="adventure-content">
        <h2 class="adventure-title">Ready to start the adventure?</h2>
        <a href="/tours" class="btn-find-trip">Find your trip</a>
      </div>
    </div>
  </section>

  <!-- ================= UNIFIED GAIA FOOTER ================= -->
  <?php require __DIR__ . '/components/gaia-footer.php'; ?>

  <!-- Interactive JavaScript -->
  <script src="/assets/gaia.js"></script>
  <script>
    // FAQ Toggle
    function toggleFaq(el) {
      const card = el.parentElement;
      const isOpen = card.classList.contains('open');
      document.querySelectorAll('.faq-card').forEach(c => c.classList.remove('open'));
      if (!isOpen) {
        card.classList.add('open');
      }
    }

    // Favorite button toggle
    function toggleFav(btn, e) {
      e.preventDefault();
      e.stopPropagation();
      btn.classList.toggle('active');
      const icon = btn.querySelector('i');
      if (btn.classList.contains('active')) {
        icon.className = 'fa-solid fa-heart';
      } else {
        icon.className = 'fa-regular fa-heart';
      }
    }
  </script>
</body>
</html>

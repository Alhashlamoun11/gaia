<?php
/**
 * admin/seo/index.php
 * ------------------------------------------------------------
 * SEO Management — edit meta title, description, keywords,
 * canonical URL, OG image, Twitter image for every supported page.
 * Uses the existing `seo_meta` table (page, lang, title,
 * meta_description, keywords, og_image, twitter_image,
 * canonical_url, is_active).
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../_bootstrap.php';
require_admin();

$pdo = getPDO();

// Supported pages (page key => human label)
$pages = [
    'home'       => 'Home',
    'tours'      => 'Tours',
    'hotels'     => 'Hotels',
    'rooms'      => 'Rooms',
    'transfers'  => 'Transfers',
    'events'     => 'Events',
    'gaia-night' => 'GAIA Night',
    'about'      => 'About',
    'contact'    => 'Contact',
    'faq'        => 'FAQ',
    'search'     => 'Search',
    'booking'    => 'Booking',
    'checkout'   => 'Checkout',
    'account'    => 'Account',
    'login'      => 'Login',
    'register'   => 'Register',
];

$langs = ['en' => 'English', 'ar' => 'Arabic'];

// Handle POST (save)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) csrf_fail();
    $page = $_POST['page'] ?? '';
    $lang = $_POST['lang'] ?? 'en';
    if (!isset($pages[$page]) || !isset($langs[$lang])) {
        admin_flash('Invalid page or language.', 'error');
        header('Location: ' . admin_url('seo/index.php'));
        exit;
    }

    $data = [
        'title'           => trim($_POST['title'] ?? ''),
        'meta_description'=> trim($_POST['meta_description'] ?? ''),
        'keywords'        => trim($_POST['keywords'] ?? ''),
        'canonical_url'   => trim($_POST['canonical_url'] ?? ''),
        'og_image'        => trim($_POST['og_image'] ?? ''),
        'twitter_image'   => trim($_POST['twitter_image'] ?? ''),
        'is_active'       => !empty($_POST['is_active']) ? 1 : 0,
    ];

    // Check if row exists
    $st = $pdo->prepare("SELECT id FROM seo_meta WHERE page = :p AND lang = :l LIMIT 1");
    $st->execute([':p' => $page, ':l' => $lang]);
    $existing = $st->fetchColumn();

    if ($existing) {
        $set = [];
        $params = [':id' => (int)$existing];
        foreach ($data as $k => $v) {
            $set[] = "`{$k}` = :{$k}";
            $params[":{$k}"] = $v;
        }
        $sql = "UPDATE seo_meta SET " . implode(', ', $set) . " WHERE id = :id";
        $pdo->prepare($sql)->execute($params);
    } else {
        $cols = array_keys($data);
        $ph = ':' . implode(', :', $cols);
        $sql = "INSERT INTO seo_meta (`page`, `lang`, `" . implode('`, `', $cols) . "`) VALUES (:page, :lang, {$ph})";
        $params = [':page' => $page, ':lang' => $lang];
        foreach ($data as $k => $v) {
            $params[":{$k}"] = $v;
        }
        $pdo->prepare($sql)->execute($params);
    }

    admin_flash(t('admin.settings_saved', 'Settings saved successfully.'), 'success');
    header('Location: ' . admin_url('seo/index.php?page=' . urlencode($page) . '&lang=' . urlencode($lang)));
    exit;
}

// Load current values
$activePage = $_GET['page'] ?? 'home';
$activeLang = $_GET['lang'] ?? 'en';
if (!isset($pages[$activePage])) $activePage = 'home';
if (!isset($langs[$activeLang])) $activeLang = 'en';

$row = null;
$st = $pdo->prepare("SELECT * FROM seo_meta WHERE page = :p AND lang = :l LIMIT 1");
$st->execute([':p' => $activePage, ':l' => $activeLang]);
$row = $st->fetch() ?: null;

$account_alerts = admin_flash_peek() ? [admin_flash()] : [];
$admin_page_title = t('admin.seo') . ' — GAIA TOURS & TRAVEL';
$admin_topbar_title = t('admin.seo');
$admin_active = 'seo';
$C = fn($v) => htmlspecialchars((string)($v ?? ''));
?>
<?php require __DIR__ . '/../components/header.php'; ?>
<div class="admin-app">
  <?php require __DIR__ . '/../components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/../components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/../components/alert.php'; ?>

      <div class="card">
        <div class="card-head">
          <h3><i class="fa-solid fa-magnifying-glass-chart"></i> <?= $C(t('admin.seo')) ?></h3>
        </div>

        <!-- Page selector -->
        <div class="tabs" style="flex-wrap:wrap;">
          <?php foreach ($pages as $pk => $pl): ?>
            <a class="tab <?= $activePage === $pk ? 'active' : '' ?>" href="?page=<?= $C($pk) ?>&lang=<?= $C($activeLang) ?>"><?= $C($pl) ?></a>
          <?php endforeach; ?>
        </div>

        <!-- Language selector -->
        <div class="tabs" style="margin-top:8px;">
          <?php foreach ($langs as $lk => $ll): ?>
            <a class="tab <?= $activeLang === $lk ? 'active' : '' ?>" href="?page=<?= $C($activePage) ?>&lang=<?= $C($lk) ?>"><?= $C($ll) ?></a>
          <?php endforeach; ?>
        </div>

        <form method="post" action="index.php" enctype="multipart/form-data" style="margin-top:18px;">
          <?= csrf_field() ?>
          <input type="hidden" name="page" value="<?= $C($activePage) ?>">
          <input type="hidden" name="lang" value="<?= $C($activeLang) ?>">

          <div class="field">
            <label><?= $C(t('admin.meta_title')) ?></label>
            <input type="text" name="title" value="<?= $C($row['title'] ?? '') ?>" placeholder="Page title for search engines">
          </div>

          <div class="field">
            <label><?= $C(t('admin.meta_description')) ?></label>
            <textarea name="meta_description" rows="3" placeholder="Meta description (150-160 chars recommended)"><?= $C($row['meta_description'] ?? '') ?></textarea>
          </div>

          <div class="field">
            <label><?= $C(t('admin.keywords')) ?></label>
            <input type="text" name="keywords" value="<?= $C($row['keywords'] ?? '') ?>" placeholder="keyword1, keyword2, keyword3">
          </div>

          <div class="field">
            <label><?= $C(t('admin.canonical_url')) ?></label>
            <input type="text" name="canonical_url" value="<?= $C($row['canonical_url'] ?? '') ?>" placeholder="https://example.com/page">
          </div>

          <div class="grid-2">
            <div class="field">
              <label><?= $C(t('admin.og_image')) ?></label>
              <?php if (!empty($row['og_image'])): ?>
                <img class="preview-img" src="<?= $C($row['og_image']) ?>" alt=""><br>
              <?php endif; ?>
              <input type="text" name="og_image" value="<?= $C($row['og_image'] ?? '') ?>" placeholder="https://.../og-image.jpg">
            </div>
            <div class="field">
              <label><?= $C(t('admin.twitter_image')) ?></label>
              <?php if (!empty($row['twitter_image'])): ?>
                <img class="preview-img" src="<?= $C($row['twitter_image']) ?>" alt=""><br>
              <?php endif; ?>
              <input type="text" name="twitter_image" value="<?= $C($row['twitter_image'] ?? '') ?>" placeholder="https://.../twitter-card.jpg">
            </div>
          </div>

          <div class="field">
            <label><?= $C(t('admin.status')) ?></label>
            <input type="checkbox" name="is_active" value="1" <?= (int)($row['is_active'] ?? 1) ? 'checked' : '' ?>> <?= $C(t('admin.active')) ?>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn"><?= $C(t('admin.save')) ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../components/footer.php'; ?>
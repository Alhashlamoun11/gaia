<?php
/**
 * page.php — CMS page renderer (About, Contact, etc.)
 * ------------------------------------------------------------
 * Loads page content from the `pages` + `page_sections` tables.
 * Supports EN/AR via ?lang= (URL-prefixed by .htaccess).
 * Includes language switcher, breadcrumbs, and RTL/LTR.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/components/gaia-config.php';

$gaia_base          = '';
$gaia_active        = 'about';
$gaia_header_style  = 'solid';

$slug = trim($_GET['slug'] ?? 'about');

$page = gaia_page($slug);
if (!$page) {
    http_response_code(404);
    $page = [
        'title'   => t('page.not_found', 'Page Not Found'),
        'subtitle'=> '',
        'content' => t('page.not_found_text', 'The page you are looking for does not exist.'),
        'image_url' => null,
    ];
    $slug = '404';
}

$sections = gaia_page_sections($slug);

// Map CMS slug to the navigation active key
$navKeys = ['about' => 'about', 'contact' => 'contact'];
$gaia_active = $navKeys[$slug] ?? 'home';

$pageTitle = $page['title'] . ' — GAIA TOURS & TRAVEL';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(gaia_current_lang()) ?>" dir="<?= gaia_dir() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?= gaia_seo_tags($slug, $pageTitle) ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/assets/gaia.css">
<style>
  /* Page hero */
  .page-hero {
    background: linear-gradient(180deg, rgba(27,42,74,.9), rgba(27,42,74,.75));
    color: #fff;
    padding: 80px 0 56px;
    text-align: center;
  }
  .page-hero h1 { font-size: 38px; font-weight: 700; margin-bottom: 10px; color: #fff; }
  .page-hero p { font-size: 15px; opacity: .85; max-width: 620px; margin: 0 auto; }

  /* Breadcrumbs */
  .gaia-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: rgba(255,255,255,.85);
    justify-content: center;
    margin-bottom: 12px;
    flex-wrap: wrap;
  }
  .gaia-breadcrumb a { color: rgba(255,255,255,.85); text-decoration: underline; }
  .gaia-breadcrumb .sep { opacity: .5; }

  /* Content */
  .page-body { padding: 60px 0 80px; }
  .page-content {
    max-width: 860px;
    margin: 0 auto;
    background: #fff;
    border: 1px solid var(--gaia-line, #e8e6e0);
    border-radius: 18px;
    padding: 44px;
    box-shadow: 0 10px 30px rgba(27,42,74,.08);
    line-height: 1.9;
    font-size: 15px;
    color: var(--gaia-ink, #1c1e26);
  }
  .page-content h1, .page-content h2, .page-content h3 { margin-bottom: 14px; }
  .page-content img { border-radius: 12px; margin: 18px 0; }
  .page-section {
    margin-top: 34px;
    padding-top: 34px;
    border-top: 1px solid var(--gaia-line, #e8e6e0);
  }
  .page-section h2 { font-size: 24px; }
  .page-section h2 + p { color: var(--gaia-muted, #6b7280); }

  /* Contact info from settings */
  .contact-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-top: 26px;
  }
  .contact-card {
    background: #faf7f2;
    border: 1px solid var(--gaia-line, #e8e6e0);
    border-radius: 14px;
    padding: 22px;
    text-align: center;
  }
  .contact-card i { font-size: 24px; color: var(--gaia-accent, #1f6f8f); margin-bottom: 10px; }
  .contact-card strong { display: block; margin-bottom: 4px; }
  .contact-card span { font-size: 13px; color: var(--gaia-muted, #6b7280); }

  @media (max-width: 680px) {
    .contact-grid { grid-template-columns: 1fr; }
    .page-hero h1 { font-size: 28px; }
    .page-content { padding: 24px; }
  }
</style>
</head>
<body>

<?php require __DIR__ . '/components/gaia-header.php'; ?>

<section class="page-hero">
  <div class="container">
    <nav class="gaia-breadcrumb" aria-label="Breadcrumb">
      <a href="<?= gaia_url('index.php') ?>"><?= t('tours.trip_home', 'Home') ?></a>
      <span class="sep"><?= gaia_is_rtl() ? '/' : '/' ?></span>
      <span><?= htmlspecialchars($page['title']) ?></span>
    </nav>
    <h1><?= htmlspecialchars($page['title']) ?></h1>
    <?php if (!empty($page['subtitle'])): ?>
      <p><?= nl2br(htmlspecialchars($page['subtitle'])) ?></p>
    <?php endif; ?>
  </div>
</section>

<div class="page-body">
  <div class="container">
    <div class="page-content">
      <?php if (!empty($page['image_url'])): ?>
        <img src="<?= htmlspecialchars($page['image_url']) ?>" alt="<?= htmlspecialchars($page['title']) ?>">
      <?php endif; ?>

      <?php if (!empty($page['content'])): ?>
        <div><?= nl2br(htmlspecialchars($page['content'])) ?></div>
      <?php endif; ?>

      <!-- Contact-specific dynamic info from settings -->
      <?php if ($slug === 'contact'): ?>
        <div class="contact-grid">
          <div class="contact-card">
            <i class="fa-solid fa-envelope"></i>
            <strong><?= t('section.contact', 'Contact') ?></strong>
            <span><a href="mailto:<?= htmlspecialchars(site_setting('contact_email', 'support@gaiatours.com')) ?>"><?= htmlspecialchars(site_setting('contact_email', 'support@gaiatours.com')) ?></a></span>
          </div>
<div class="contact-card">
            <i class="fa-brands fa-whatsapp"></i>
            <strong><?= t('page.contact_whatsapp', 'WhatsApp') ?></strong>
            <span><a href="https://wa.me/<?= htmlspecialchars(site_setting('contact_whatsapp', '962790123456')) ?>" target="_blank" rel="noopener"><?= htmlspecialchars(site_setting('contact_whatsapp_display', 'WhatsApp 24/7')) ?></a></span>
          </div>
          <div class="contact-card">
            <i class="fa-solid fa-location-dot"></i>
            <strong><?= t('page.contact_location', 'Location') ?></strong>
            <span><?= htmlspecialchars(site_setting('contact_address', 'Amman · Milan · Paris')) ?></span>
          </div>
        </div>
      <?php endif; ?>

      <?php foreach ($sections as $section): ?>
        <div class="page-section">
          <?php if ($section['title']): ?><h2><?= htmlspecialchars($section['title']) ?></h2><?php endif; ?>
          <?php if ($section['subtitle']): ?><p><?= nl2br(htmlspecialchars($section['subtitle'])) ?></p><?php endif; ?>
          <?php if ($section['content']): ?><div><?= nl2br(htmlspecialchars($section['content'])) ?></div><?php endif; ?>
          <?php if ($section['image_url']): ?><img src="<?= htmlspecialchars($section['image_url']) ?>" alt=""><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/components/gaia-footer.php'; ?>

<script src="assets/gaia.js"></script>
</body>
</html>
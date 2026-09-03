<?php
/**
 * page.php — CMS page renderer (About, Contact, Policies, etc.)
 * ------------------------------------------------------------
 * Loads page content from the `pages` + `page_sections` tables.
 * Features an upgraded, premium UI for the Contact page with
 * an interactive inquiry form, 24/7 live channels, and direct WhatsApp.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/components/gaia-config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth/csrf.php';

$gaia_base          = '';
$gaia_active        = 'contact';
$gaia_header_style  = 'solid';

$slug = trim($_GET['slug'] ?? 'about');

$page = gaia_page($slug);
if (!$page) {
    http_response_code(404);
    $page = [
        'title'    => t('page.not_found', 'Page Not Found'),
        'subtitle' => '',
        'content'  => t('page.not_found_text', 'The page you are looking for does not exist.'),
        'image_url'=> null,
    ];
    $slug = '404';
}

$sections = gaia_page_sections($slug);

// Map CMS slug to the navigation active key
$navKeys = ['about' => 'about', 'contact' => 'contact'];
$gaia_active = $navKeys[$slug] ?? 'home';

$pageTitle = $page['title'] . ' — GAIA TOURS & TRAVEL';

// Contact settings
$contactEmail    = site_setting('contact_email', 'support@gaiatours.com');
$contactPhone    = site_setting('contact_phone', '+962 6 500 0000');
$contactWhatsapp = site_setting('contact_whatsapp', '962790123456');
$cleanWhatsapp   = preg_replace('/[^0-9]/', '', $contactWhatsapp);
$contactAddress  = site_setting('contact_address', '5th Circle, Abdoun, Amman, Jordan');

// Handle form submission feedback (simulated or session flash)
$formSuccess = false;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $slug === 'contact') {
    if (csrf_verify()) {
        $formSuccess = true;
    }
}
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
  :root {
    --ink: var(--gaia-ink, #111317);
    --muted: var(--gaia-muted, #6b7280);
    --line: var(--gaia-line, #e5e7eb);
    --teal: var(--gaia-accent, #17c9b3);
    --teal-dark: #129e8d;
    --navy: #0d1730;
    --gold: #d4af37;
    --radius: 16px;
    --shadow: 0 12px 36px rgba(0,0,0,0.06);
  }
  * { box-sizing: border-box; }
  body { margin: 0; font-family: 'Inter', sans-serif; color: var(--ink); background: #f8f9fa; -webkit-font-smoothing: antialiased; }
  h1, h2, h3, h4 { font-family: 'Playfair Display', serif; margin: 0; }
  a { text-decoration: none; color: inherit; }
  
  .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }

  /* Page Hero */
  .page-hero {
    background: linear-gradient(135deg, #0d1730 0%, #16264d 100%);
    color: #ffffff;
    padding: 70px 0 80px;
    text-align: center;
    position: relative;
  }
  .page-hero h1 { font-size: 42px; font-weight: 800; margin-bottom: 12px; color: #fff; letter-spacing: -0.5px; }
  .page-hero p { font-size: 16px; color: #a4b3d6; max-width: 640px; margin: 0 auto; line-height: 1.6; }

  .gaia-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #a4b3d6;
    justify-content: center;
    margin-bottom: 14px;
  }
  .gaia-breadcrumb a { color: #a4b3d6; transition: color .2s; }
  .gaia-breadcrumb a:hover { color: #fff; text-decoration: underline; }
  .gaia-breadcrumb .sep { opacity: .5; }

  /* Generic CMS Content Page */
  .cms-body { padding: 60px 0 80px; }
  .cms-content-card {
    max-width: 900px;
    margin: 0 auto;
    background: #ffffff;
    border: 1px solid var(--line);
    border-radius: var(--radius);
    padding: 48px;
    box-shadow: var(--shadow);
    line-height: 1.85;
    font-size: 15px;
    color: #374151;
  }
  .cms-content-card h2, .cms-content-card h3 { color: #111317; margin-top: 24px; margin-bottom: 12px; }
  .cms-content-card p { margin-bottom: 16px; }

  /* Upgraded Contact Specific Styles */
  .contact-channels-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-top: -40px;
    position: relative;
    z-index: 10;
    margin-bottom: 50px;
  }
  .channel-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 24px 20px;
    box-shadow: 0 16px 32px rgba(0,0,0,0.06);
    border: 1px solid var(--line);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s;
  }
  .channel-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    border-color: var(--teal);
  }
  .channel-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
  .channel-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
  }
  .channel-icon.whatsapp { background: #e8fbf3; color: #10b981; }
  .channel-icon.email { background: #e0f2fe; color: #0284c7; }
  .channel-icon.location { background: #fef3c7; color: #d97706; }
  .channel-icon.phone { background: #ede9fe; color: #7c3aed; }
  
  .status-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 999px;
    background: #ecfdf5;
    color: #059669;
    display: flex;
    align-items: center;
    gap: 4px;
  }
  .status-badge::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #10b981;
    display: inline-block;
  }

  .channel-title { font-size: 16px; font-weight: 700; color: #111317; margin-bottom: 4px; }
  .channel-value { font-size: 14px; font-weight: 600; color: #4b5262; word-break: break-all; margin-bottom: 12px; }
  .channel-action { font-size: 13px; font-weight: 700; color: var(--teal-dark); display: flex; align-items: center; gap: 6px; }

  /* Main Contact Layout: Form + Info Box */
  .contact-main-grid {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr;
    gap: 36px;
    align-items: start;
    margin-bottom: 70px;
  }
  .contact-form-card {
    background: #ffffff;
    border-radius: 20px;
    padding: 40px;
    border: 1px solid var(--line);
    box-shadow: var(--shadow);
  }
  .contact-form-card h2 { font-size: 26px; font-weight: 800; margin-bottom: 8px; color: #111317; }
  .contact-form-card p { font-size: 14px; color: var(--muted); margin-bottom: 28px; }

  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px; }
  .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 18px; }
  .form-group label { font-size: 13px; font-weight: 700; color: #374151; }
  .form-group input, .form-group select, .form-group textarea {
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 12px 16px;
    font-family: inherit;
    font-size: 14px;
    color: #111317;
    background: #fdfdfd;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
  }
  .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    border-color: var(--teal);
    box-shadow: 0 0 0 3px rgba(23,201,179,0.15);
    background: #ffffff;
  }
  .btn-submit-contact {
    background: #111317;
    color: #ffffff;
    border: none;
    border-radius: 12px;
    padding: 14px 32px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background .2s, transform .2s;
  }
  .btn-submit-contact:hover { background: #2b303a; transform: translateY(-1px); }

  /* Info / Highlights Sidebar */
  .contact-sidebar { display: flex; flex-direction: column; gap: 24px; }
  .sidebar-box {
    background: #ffffff;
    border-radius: 18px;
    padding: 30px;
    border: 1px solid var(--line);
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
  }
  .sidebar-box h3 { font-size: 20px; font-weight: 800; margin-bottom: 14px; color: #111317; }
  
  .perks-list { display: flex; flex-direction: column; gap: 16px; }
  .perk-item { display: flex; align-items: flex-start; gap: 14px; }
  .perk-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: rgba(23,201,179,0.12);
    color: #0c7c6d;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
  }
  .perk-text h5 { font-size: 14.5px; font-weight: 700; margin: 0 0 3px; color: #111317; }
  .perk-text p { font-size: 13px; color: var(--muted); margin: 0; line-height: 1.45; }

  .hours-box {
    background: linear-gradient(135deg, #0d1730 0%, #16264d 100%);
    color: #ffffff;
    border-radius: 18px;
    padding: 28px;
  }
  .hours-box h4 { color: #ffffff; font-size: 18px; font-weight: 800; margin-bottom: 10px; }
  .hours-box p { font-size: 13.5px; color: #a4b3d6; margin-bottom: 16px; line-height: 1.5; }
  .btn-wa-live {
    background: #25d366;
    color: #ffffff;
    border-radius: 10px;
    padding: 10px 20px;
    font-weight: 700;
    font-size: 13.5px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: transform .2s;
  }
  .btn-wa-live:hover { transform: scale(1.03); color: #fff; }

  .success-alert {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #065f46;
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
  }

  /* Responsive */
  @media (max-width: 1024px) {
    .contact-channels-grid { grid-template-columns: repeat(2, 1fr); }
    .contact-main-grid { grid-template-columns: 1fr; }
  }
  @media (max-width: 640px) {
    .contact-channels-grid { grid-template-columns: 1fr; }
    .form-row { grid-template-columns: 1fr; }
    .page-hero h1 { font-size: 32px; }
    .contact-form-card { padding: 24px; }
  }
</style>
</head>
<body>

<?php require __DIR__ . '/components/gaia-header.php'; ?>

<!-- Page Hero -->
<section class="page-hero">
  <div class="container">
    <nav class="gaia-breadcrumb" aria-label="Breadcrumb">
      <a href="<?= gaia_url('index.php') ?>"><?= t('tours.trip_home', 'Home') ?></a>
      <span class="sep">/</span>
      <span><?= htmlspecialchars($page['title']) ?></span>
    </nav>
    <h1><?= htmlspecialchars($page['title']) ?></h1>
    <p><?= !empty($page['subtitle']) ? htmlspecialchars($page['subtitle']) : t('page.contact_desc', 'Our dedicated travel specialists and group coordinators are available 24/7 to assist you.') ?></p>
  </div>
</section>

<?php if ($slug === 'contact'): ?>
  <!-- ================= ENHANCED CONTACT PAGE ================= -->
  <div class="container">
    
    <!-- 4 Direct Communication Channels -->
    <div class="contact-channels-grid">
      <!-- 1. WhatsApp -->
      <a href="https://wa.me/<?= htmlspecialchars($cleanWhatsapp) ?>" target="_blank" rel="noopener" class="channel-card">
        <div>
          <div class="channel-header">
            <div class="channel-icon whatsapp"><i class="fa-brands fa-whatsapp"></i></div>
            <span class="status-badge">Online Now</span>
          </div>
          <div class="channel-title">WhatsApp 24/7</div>
          <div class="channel-value">+<?= htmlspecialchars($cleanWhatsapp) ?></div>
        </div>
        <div class="channel-action">Chat on WhatsApp <i class="fa-solid fa-arrow-right"></i></div>
      </a>

      <!-- 2. Email Support -->
      <a href="mailto:<?= htmlspecialchars($contactEmail) ?>" class="channel-card">
        <div>
          <div class="channel-header">
            <div class="channel-icon email"><i class="fa-solid fa-envelope"></i></div>
            <span class="status-badge" style="background:#f0f9ff; color:#0369a1;">&lt; 2h Reply</span>
          </div>
          <div class="channel-title">Email Inquiries</div>
          <div class="channel-value"><?= htmlspecialchars($contactEmail) ?></div>
        </div>
        <div class="channel-action">Send an Email <i class="fa-solid fa-arrow-right"></i></div>
      </a>

      <!-- 3. Direct Phone -->
      <a href="tel:<?= htmlspecialchars($cleanWhatsapp) ?>" class="channel-card">
        <div>
          <div class="channel-header">
            <div class="channel-icon phone"><i class="fa-solid fa-phone"></i></div>
            <span class="status-badge" style="background:#f5f3ff; color:#6d28d9;">Available</span>
          </div>
          <div class="channel-title">Direct Calling</div>
          <div class="channel-value"><?= htmlspecialchars($contactPhone) ?></div>
        </div>
        <div class="channel-action">Call Specialist <i class="fa-solid fa-arrow-right"></i></div>
      </a>

      <!-- 4. Global HQ & Desks -->
      <div class="channel-card">
        <div>
          <div class="channel-header">
            <div class="channel-icon location"><i class="fa-solid fa-location-dot"></i></div>
            <span class="status-badge" style="background:#fffbeb; color:#b45309;">Main Desk</span>
          </div>
          <div class="channel-title">Office Location</div>
          <div class="channel-value" style="font-size:13px;"><?= htmlspecialchars($contactAddress) ?></div>
        </div>
        <div class="channel-action" style="color:var(--muted);">Jordan · Italy · France</div>
      </div>
    </div>

    <!-- Main Grid: Inquiry Form + Sidebar Info -->
    <div class="contact-main-grid">
      <!-- Left: Interactive Form -->
      <div class="contact-form-card">
        <h2>Send Us a Message</h2>
        <p>Fill in your details below and our team will get back to you promptly with recommendations and quotes.</p>

        <?php if ($formSuccess): ?>
          <div class="success-alert">
            <i class="fa-solid fa-circle-check" style="font-size:20px;"></i>
            <div>
              <strong>Thank you for reaching out!</strong>
              <div>Your message has been sent successfully. One of our travel coordinators will contact you shortly.</div>
            </div>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= gaia_url('contact') ?>">
          <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
          
          <div class="form-row">
            <div class="form-group">
              <label for="contactName">Full Name *</label>
              <input type="text" id="contactName" name="name" placeholder="John Doe" required>
            </div>
            <div class="form-group">
              <label for="contactEmail">Email Address *</label>
              <input type="email" id="contactEmail" name="email" placeholder="john@example.com" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="contactPhone">Phone / WhatsApp</label>
              <input type="tel" id="contactPhone" name="phone" placeholder="+1 234 567 8900">
            </div>
            <div class="form-group">
              <label for="inquiryType">Inquiry Topic</label>
              <select id="inquiryType" name="topic">
                <option value="group_tours">WeRoad Group Adventures</option>
                <option value="hotel_booking">Hotels & Desert Bubbles</option>
                <option value="airport_transfers">Airport Transfers & Chauffeur</option>
                <option value="partner_leader">Become a Group Leader / Partner</option>
                <option value="custom_trip">Custom Private Itinerary</option>
                <option value="other">General Support</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label for="contactMessage">How can we help you? *</label>
            <textarea id="contactMessage" name="message" rows="5" placeholder="Tell us about your upcoming travel plans, group size, or questions..." required></textarea>
          </div>

          <button type="submit" class="btn-submit-contact">
            <i class="fa-solid fa-paper-plane"></i> Send Message
          </button>
        </form>
      </div>

      <!-- Right: Sidebar & 24/7 Hours Box -->
      <div class="contact-sidebar">
        <!-- 24/7 Live Desk -->
        <div class="hours-box">
          <span class="gaia-vip-badge" style="margin-bottom:12px;">Instant Response</span>
          <h4>Need Immediate Assistance?</h4>
          <p>Our team is available round-the-clock on WhatsApp to assist with urgent bookings, airport flight delays, or tour confirmations.</p>
          <a href="https://wa.me/<?= htmlspecialchars($cleanWhatsapp) ?>" target="_blank" rel="noopener" class="btn-wa-live">
            <i class="fa-brands fa-whatsapp"></i> Chat on WhatsApp Now
          </a>
        </div>

        <!-- Trust Highlights -->
        <div class="sidebar-box">
          <h3>Why Travel with GAIA?</h3>
          <div class="perks-list">
            <div class="perk-item">
              <div class="perk-icon"><i class="fa-solid fa-user-shield"></i></div>
              <div class="perk-text">
                <h5>Dedicated Coordinator</h5>
                <p>A personal trip leader coordinates your itinerary from departure to arrival.</p>
              </div>
            </div>
            <div class="perk-item">
              <div class="perk-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
              <div class="perk-text">
                <h5>Maximum Flexibility</h5>
                <p>Free date and destination adjustments up to 31 days prior.</p>
              </div>
            </div>
            <div class="perk-item">
              <div class="perk-icon"><i class="fa-solid fa-earth-americas"></i></div>
              <div class="perk-text">
                <h5>Authentic Local Expertise</h5>
                <p>Handcrafted routes created by seasoned solo adventurers and local producers.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>

<?php else: ?>
  <!-- ================= STANDARD CMS PAGE (About, Policies) ================= -->
  <div class="cms-body">
    <div class="container">
      <div class="cms-content-card">
        <?php if (!empty($page['image_url'])): ?>
          <img src="<?= htmlspecialchars($page['image_url']) ?>" alt="<?= htmlspecialchars($page['title']) ?>" style="width:100%; border-radius:14px; margin-bottom:24px;">
        <?php endif; ?>

        <?php if (!empty($page['content'])): ?>
          <div><?= nl2br(htmlspecialchars($page['content'])) ?></div>
        <?php endif; ?>

        <?php foreach ($sections as $section): ?>
          <div style="margin-top:32px; padding-top:28px; border-top:1px solid var(--line);">
            <?php if ($section['title']): ?><h2 style="font-size:22px; margin-bottom:8px;"><?= htmlspecialchars($section['title']) ?></h2><?php endif; ?>
            <?php if ($section['subtitle']): ?><p style="color:var(--muted); font-size:14px; margin-bottom:12px;"><?= nl2br(htmlspecialchars($section['subtitle'])) ?></p><?php endif; ?>
            <?php if ($section['content']): ?><div><?= nl2br(htmlspecialchars($section['content'])) ?></div><?php endif; ?>
            <?php if ($section['image_url']): ?><img src="<?= htmlspecialchars($section['image_url']) ?>" alt="" style="width:100%; border-radius:12px; margin-top:16px;"><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/components/gaia-footer.php'; ?>
<script src="/assets/gaia.js"></script>
</body>
</html>
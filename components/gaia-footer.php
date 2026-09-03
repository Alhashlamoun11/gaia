<?php
/**
 * gaia-footer.php
 * ------------------------------------------------------------
 * Clean, simplified & elegant global footer for GAIA TOURS & TRAVEL.
 * ------------------------------------------------------------
 */
if (!function_exists('gaia_footer_columns')) {
    require_once __DIR__ . '/gaia-config.php';
}

$gaia_base = $gaia_base ?? '';

// Company settings
$gaia_company_short = site_setting('company_short', 'GAIA');
$gaia_logo_mark     = site_setting('company_logo_mark', 'G');
$gaia_tagline       = site_setting('company_tagline', 'TOURS & TRAVEL');
$gaia_about         = site_setting('footer_about', 'Authentic group adventures, luxury desert stays, and premium private airport transfers.');
$gaia_contact_email = site_setting('contact_email', 'support@gaiatours.com');
$gaia_whatsapp      = site_setting('contact_whatsapp', '962790123456');
$gaia_clean_wa      = preg_replace('/[^0-9]/', '', $gaia_whatsapp);
$gaia_whatsapp_disp = site_setting('contact_whatsapp_display', 'WhatsApp 24/7');
$gaia_address       = site_setting('contact_address', 'Amman · Milan · Paris');
$gaia_socials       = gaia_social_links();
$gaia_payment_icons = array_filter(array_map('trim', explode(',', site_setting('payment_icons', 'VISA,Mastercard,AMEX,Apple Pay,Google Pay'))));
$gaia_terms_label   = site_setting('footer_terms_label', t('footer.terms', 'Terms & Conditions'));
$gaia_privacy_label = site_setting('footer_privacy_label', t('footer.privacy', 'Privacy Policy'));
$gaia_refund_label  = site_setting('footer_refund_label', t('footer.refund', 'Refund Policy'));

$gaiaLogoUrl = (isset($gaia_active) && $gaia_active === 'tours')
    ? $gaia_base . '/assets/images/logo-mark.png'
    : $gaia_base . '/assets/images/image.png';
?>
<footer class="gaia-footer-simple <?= gaia_is_rtl() ? 'rtl' : 'ltr' ?>" dir="<?= gaia_dir() ?>">
  <div class="container">
    
    <!-- Main Footer Grid -->
    <div class="footer-simple-grid">
      
      <!-- 1. Brand & WhatsApp -->
      <div class="footer-brand-col">
        <a class="footer-logo" href="<?= gaia_url('index.php', $gaia_base) ?>">
          <img src="<?= htmlspecialchars($gaiaLogoUrl) ?>" alt="<?= htmlspecialchars($gaia_company_short) ?>">
          <span><?= htmlspecialchars($gaia_company_short) ?> <em><?= htmlspecialchars($gaia_tagline) ?></em></span>
        </a>
        <p class="footer-tagline"><?= htmlspecialchars($gaia_about) ?></p>
        
        <a href="https://wa.me/<?= htmlspecialchars($gaia_clean_wa) ?>" target="_blank" rel="noopener" class="footer-wa-btn">
          <i class="fa-brands fa-whatsapp"></i> <?= htmlspecialchars($gaia_whatsapp_disp) ?>
        </a>

        <div class="footer-social-links">
          <?php foreach ($gaia_socials as $s): ?>
            <?php if (preg_match('#^(https?:|//)#i', $s['url'])): ?>
              <a href="<?= htmlspecialchars($s['url']) ?>" aria-label="<?= htmlspecialchars($s['platform']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($s['icon'] ?: $s['platform'][0]) ?></a>
            <?php else: ?>
              <a href="<?= gaia_url($s['url'], $gaia_base) ?>" aria-label="<?= htmlspecialchars($s['platform']) ?>"><?= htmlspecialchars($s['icon'] ?: $s['platform'][0]) ?></a>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- 2. Services & Adventures -->
      <div class="footer-nav-col">
        <h4><?= t('nav.explore', 'Explore') ?></h4>
        <ul>
          <li><a href="<?= gaia_url('weroad/search.php', $gaia_base) ?>">Group Adventures & Tours</a></li>
          <li><a href="<?= gaia_url('search-hotels.php', $gaia_base) ?>">Hotels & Desert Bubbles</a></li>
          <li><a href="<?= gaia_url('transfer-booking.php', $gaia_base) ?>">Airport Transfers & Fleet</a></li>
          <li><a href="<?= gaia_url('gaia-night.php', $gaia_base) ?>">GAIA Night & Events</a></li>
        </ul>
      </div>

      <!-- 3. Company & Partners -->
      <div class="footer-nav-col">
        <h4><?= t('nav.company', 'Company') ?></h4>
        <ul>
          <li><a href="<?= gaia_url('page.php?slug=about', $gaia_base) ?>">About GAIA</a></li>
          <li><a href="<?= gaia_url('partner/apply.php', $gaia_base) ?>">Join as a Partner</a></li>
          <li><a href="<?= gaia_url('partner/apply.php', $gaia_base) ?>">Become a Group Leader</a></li>
          <li><a href="<?= gaia_url('index.php', $gaia_base) ?>#reviews">Customer Reviews</a></li>
        </ul>
      </div>

      <!-- 4. Support & Contact -->
      <div class="footer-nav-col">
        <h4><?= t('section.contact', 'Support') ?></h4>
        <ul>
          <li><a href="<?= gaia_url('page.php?slug=contact', $gaia_base) ?>">Help & Contact Desk</a></li>
          <li><a href="mailto:<?= htmlspecialchars($gaia_contact_email) ?>"><?= htmlspecialchars($gaia_contact_email) ?></a></li>
          <li class="footer-loc"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($gaia_address) ?></li>
        </ul>
      </div>

    </div>

    <!-- Bottom Bar -->
    <div class="footer-simple-bottom">
      <div class="footer-copy">
        <?= t_fmt('footer.copyright', ['year' => date('Y')]) ?>
      </div>

      <div class="footer-legal">
        <a href="<?= gaia_url('terms-and-conditions', $gaia_base) ?>"><?= htmlspecialchars($gaia_terms_label) ?></a>
        <span class="dot">·</span>
        <a href="<?= gaia_url('privacy-policy', $gaia_base) ?>"><?= htmlspecialchars($gaia_privacy_label) ?></a>
        <span class="dot">·</span>
        <a href="<?= gaia_url('refund-policy', $gaia_base) ?>"><?= htmlspecialchars($gaia_refund_label) ?></a>
      </div>

      <div class="footer-pay-badges">
        <?php foreach ($gaia_payment_icons as $pay): ?>
          <span class="pay-pill"><?= htmlspecialchars($pay) ?></span>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</footer>
<?php
/**
 * gaia-footer.php
 * ------------------------------------------------------------
 * UNIFIED GLOBAL FOOTER for the GAIA TOURS & TRAVEL platform.
 *
 * Includes:
 *   - Company overview
 *   - Quick Links
 *   - Tours
 *   - Transfers
 *   - Hotels & Events
 *   - Contact information + WhatsApp
 *   - Social media
 *   - Newsletter signup
 *   - Payment icons + copyright
 *
 * Required before include:
 *   $gaia_base = '' or '../' — relative path back to site root
 * ------------------------------------------------------------
 */

// Ensure bootstrap is loaded (do not require config/db here — pages do)
if (!function_exists('gaia_footer_columns')) {
    require_once __DIR__ . '/gaia-config.php';
}

// Resolve default
$gaia_base = $gaia_base ?? '';

$gaiaFooterCols = gaia_footer_columns($gaia_base);

// DB-driven company settings
$gaia_company_short = site_setting('company_short', 'GAIA');
$gaia_logo_mark     = site_setting('company_logo_mark', 'G');
$gaia_tagline       = site_setting('company_tagline', 'TOURS & TRAVEL');
$gaia_about         = site_setting('footer_about', site_setting('company_about', 'One company. One platform. GAIA Tours & GAIA Night — group tours, hotels, private transfers, night events and VIP experiences, all under one GAIA brand.'));
$gaia_contact_email = site_setting('contact_email', 'support@gaiatours.com');
$gaia_whatsapp      = site_setting('contact_whatsapp', '962790123456');
$gaia_whatsapp_disp = site_setting('contact_whatsapp_display', 'WhatsApp 24/7');
$gaia_address       = site_setting('contact_address', 'Amman · Milan · Paris');
$gaia_socials       = gaia_social_links();
$gaia_payment_icons = array_filter(array_map('trim', explode(',', site_setting('payment_icons', 'VISA,MC,AMEX,Pay,G Pay'))));
$gaia_news_placeholder = site_setting('footer_newsletter_placeholder', t('footer.newsletter_email_placeholder', 'Your email'));
$gaia_terms_label = site_setting('footer_terms_label', t('footer.terms'));
$gaia_privacy_label = site_setting('footer_privacy_label', t('footer.privacy'));
$gaia_cookies_label = site_setting('footer_cookies_label', t('footer.cookies'));
?>
<footer class="gaia-footer <?= gaia_is_rtl() ? 'gaia-footer-rtl' : 'gaia-footer-ltr' ?>" dir="<?= gaia_dir() ?>">
  <div class="gaia-footer-top">
    <div class="gaia-footer-brand">
      <span class="gaia-logo-mark"><?= htmlspecialchars($gaia_logo_mark) ?></span>
      <span class="gaia-logo-text"><?= htmlspecialchars($gaia_company_short) ?> <em><?= $gaia_tagline ?></em></span>
      <p class="gaia-footer-about">
        <?= htmlspecialchars($gaia_about) ?>
      </p>
      <div class="gaia-footer-social">
        <?php foreach ($gaia_socials as $s): ?>
          <?php if (preg_match('#^(https?:|//)#i', $s['url'])): ?>
            <a href="<?= htmlspecialchars($s['url']) ?>" aria-label="<?= htmlspecialchars($s['platform']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($s['icon'] ?: $s['platform'][0]) ?></a>
          <?php else: ?>
            <a href="<?= gaia_url($s['url']) ?>" aria-label="<?= htmlspecialchars($s['platform']) ?>"><?= htmlspecialchars($s['icon'] ?: $s['platform'][0]) ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="gaia-footer-cols">
      <?php foreach ($gaiaFooterCols as $col): ?>
        <div class="gaia-footer-col">
          <h4><?= htmlspecialchars($col['title']) ?></h4>
          <ul>
            <?php foreach ($col['links'] as $link): ?>
              <?php if (preg_match('#^(https?:|//|mailto:)#i', $link['url'])): ?>
                <li><a href="<?= htmlspecialchars($link['url']) ?>"<?= strpos($link['url'], 'http') === 0 ? ' target="_blank" rel="noopener"' : '' ?>><?= htmlspecialchars($link['label']) ?></a></li>
              <?php else: ?>
                <li><a href="<?= gaia_url($link['url'], $gaia_base) ?>"><?= htmlspecialchars($link['label']) ?></a></li>
              <?php endif; ?>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>

      <div class="gaia-footer-col">
        <h4><?= htmlspecialchars(t('section.contact')) ?></h4>
        <ul>
          <li><a href="mailto:<?= htmlspecialchars($gaia_contact_email) ?>"><?= htmlspecialchars($gaia_contact_email) ?></a></li>
          <li><a href="https://wa.me/<?= htmlspecialchars($gaia_whatsapp) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($gaia_whatsapp_disp) ?></a></li>
          <li><?= htmlspecialchars($gaia_address) ?></li>
        </ul>
      </div>
    </div>

    <div class="gaia-footer-news">
      <h4><?= htmlspecialchars(t('footer.newsletter_title')) ?></h4>
      <p><?= htmlspecialchars(t('footer.newsletter_text')) ?></p>
      <form class="gaia-news-form" action="subscribe.php" method="post">
        <input type="email" name="email" placeholder="<?= htmlspecialchars($gaia_news_placeholder) ?>" required>
        <button type="submit"><?= htmlspecialchars(t('footer.newsletter_subscribe')) ?></button>
      </form>
      <small><?= htmlspecialchars(t('footer.newsletter_consent')) ?></small>
    </div>
  </div>

  <div class="gaia-footer-bottom">
    <div class="gaia-footer-pay">
      <?php foreach ($gaia_payment_icons as $pay): ?>
        <span><?= htmlspecialchars($pay) ?></span>
      <?php endforeach; ?>
    </div>
    <div class="gaia-footer-copyright">
      <?= t_fmt('footer.copyright', ['year' => date('Y')]) ?>
      <a href="<?= gaia_url('contact') ?>"><?= htmlspecialchars($gaia_terms_label) ?></a> · <a href="<?= gaia_url('contact') ?>"><?= htmlspecialchars($gaia_privacy_label) ?></a> · <a href="<?= gaia_url('contact') ?>"><?= htmlspecialchars($gaia_cookies_label) ?></a> · <a href="<?= gaia_url('partner/apply.php', $gaia_base) ?>"><?= htmlspecialchars(t('partner.join_title', 'Join as a Partner')) ?></a>
    </div>
  </div>
</footer>
<?php
/**
 * admin/settings.php
 * ------------------------------------------------------------
 * Website settings manager.
 *
 * Edits the existing `settings` table (company, contact, social,
 * currencies, languages, SEO defaults, footer, google maps).
 * NO payment settings (CyberSource already prepared separately).
 * CSRF protected, PDO prepared, output escaped.
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/_bootstrap.php';
require_admin();

$pdo = getPDO();

// Group -> list of setting keys to manage.
$groups = [
    'company' => [
        'company_name', 'company_short', 'company_tagline', 'company_about', 'company_logo_mark', 'logo_image',
    ],
    'contact' => [
        'contact_email', 'contact_phone', 'contact_whatsapp', 'contact_whatsapp_display', 'contact_address', 'contact_hours', 'google_maps',
    ],
    'general' => [
        'currency', 'currency_symbol', 'default_language', 'default_dir', 'site_locales', 'seo_brand_suffix', 'timezone',
    ],
    'social' => [
        'social_facebook', 'social_instagram', 'social_twitter', 'social_youtube', 'social_tiktok', 'social_linkedin',
    ],
    'footer' => [
        'footer_about', 'payment_icons', 'footer_terms_label', 'footer_privacy_label', 'footer_cookies_label', 'footer_newsletter_placeholder',
    ],
    'seo' => [
        'seo_brand_suffix', 'seo_meta_title', 'seo_meta_description', 'seo_meta_keywords', 'seo_og_image', 'seo_twitter_image', 'seo_canonical_url',
    ],
];

// Load current values.
$current = [];
foreach ($pdo->query('SELECT `key`, `value` FROM settings')->fetchAll() as $row) {
    $current[$row['key']] = $row['value'];
}

$alerts = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        csrf_fail();
    }

    $group   = $_POST['group'] ?? '';
    $allowed = array_keys($groups);
    if (!in_array($group, $allowed, true)) {
        $errors[] = 'Invalid settings group.';
    } else {
        $keys = $groups[$group];
        $upd  = $pdo->prepare('UPDATE settings SET `value` = ? WHERE `key` = ?');
        $ins  = $pdo->prepare('INSERT INTO settings (`key`,`value`,`type`,`group`,is_active) VALUES (?,?,?,?,1)');
        foreach ($keys as $k) {
            $val = $_POST[$k] ?? '';
            $val = trim($val);
            // Logo upload override
            if ($k === 'logo_image' && !empty($_FILES['logo_file']['name']) && $_FILES['logo_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $up = CrudService::handleUpload($_FILES['logo_file'], 'logos');
                if ($up !== null) {
                    $val = $up;
                }
            }
            $upd->execute([$val, $k]);
            if ($upd->rowCount() === 0) {
                $type = in_array($k, ['company_about', 'footer_about'], true) ? 'textarea' : 'text';
                $grp  = $group;
                $ins->execute([$k, $val, $type, $grp]);
            }
            $current[$k] = $val;
        }
        $alerts[] = ['type' => 'success', 'msg' => t('admin.settings_saved', 'Settings saved successfully.')];
    }
}

// Active tab for display.
$activeGroup = $_GET['group'] ?? 'company';
if (!in_array($activeGroup, array_keys($groups), true)) {
    $activeGroup = 'company';
}

$account_alerts     = array_merge(array_map(fn($e) => ['type' => 'error', 'msg' => $e], $errors), $alerts);
$admin_page_title   = t('admin.settings') . ' — GAIA TOURS &amp; TRAVEL';
$admin_topbar_title = t('admin.settings');
$admin_active       = 'settings';
?>
<?php require __DIR__ . '/components/header.php'; ?>
<div class="admin-app">
  <?php require __DIR__ . '/components/sidebar.php'; ?>
  <div class="admin-main">
    <?php require __DIR__ . '/components/topbar.php'; ?>
    <div class="admin-content">
      <?php require __DIR__ . '/components/alert.php'; ?>

      <div class="card">
        <div class="tabs">
          <?php foreach ($groups as $gKey => $keys): ?>
            <a class="tab <?= $activeGroup === $gKey ? 'active' : '' ?>" href="?group=<?= htmlspecialchars($gKey) ?>"><?= htmlspecialchars(t('admin.setting_' . $gKey, ucfirst($gKey))) ?></a>
          <?php endforeach; ?>
        </div>

        <form method="post" action="settings.php?group=<?= htmlspecialchars($activeGroup) ?>" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="group" value="<?= htmlspecialchars($activeGroup) ?>">

          <?php if ($activeGroup === 'company'): ?>
            <div class="grid-2">
              <div class="field"><label><?= htmlspecialchars(t('admin.company_name')) ?></label><input type="text" name="company_name" value="<?= htmlspecialchars($current['company_name'] ?? '') ?>"></div>
              <div class="field"><label><?= htmlspecialchars(t('admin.company_short', 'Short Name')) ?></label><input type="text" name="company_short" value="<?= htmlspecialchars($current['company_short'] ?? '') ?>"></div>
            </div>
            <div class="grid-2">
              <div class="field"><label><?= htmlspecialchars(t('admin.company_tagline', 'Tagline')) ?></label><input type="text" name="company_tagline" value="<?= htmlspecialchars($current['company_tagline'] ?? '') ?>"></div>
              <div class="field"><label><?= htmlspecialchars(t('admin.logo_mark', 'Logo Mark')) ?></label><input type="text" name="company_logo_mark" value="<?= htmlspecialchars($current['company_logo_mark'] ?? '') ?>"></div>
            </div>
            <div class="field"><label><?= htmlspecialchars(t('admin.logo')) ?></label>
              <img class="preview-img" id="logoPreview" src="<?= htmlspecialchars(($current['logo_image'] ?? '') ? '/' . ltrim($current['logo_image'], '/') : '') ?>" alt="">
              <input type="hidden" name="logo_image" value="<?= htmlspecialchars($current['logo_image'] ?? '') ?>">
              <input type="file" name="logo_file" accept="image/jpeg,image/png,image/webp" data-preview-target="#logoPreview" style="margin-top:8px;">
              <div class="hint"><?= htmlspecialchars(t('admin.logo_hint', 'Upload a new logo (JPG/PNG/WEBP).')) ?></div>
            </div>
            <div class="field"><label><?= htmlspecialchars(t('admin.company_about', 'About')) ?></label><textarea name="company_about" rows="4"><?= htmlspecialchars($current['company_about'] ?? '') ?></textarea></div>

          <?php elseif ($activeGroup === 'contact'): ?>
            <div class="grid-2">
              <div class="field"><label><?= htmlspecialchars(t('admin.contact_email')) ?></label><input type="email" name="contact_email" value="<?= htmlspecialchars($current['contact_email'] ?? '') ?>"></div>
              <div class="field"><label><?= htmlspecialchars(t('admin.contact_phone')) ?></label><input type="text" name="contact_phone" value="<?= htmlspecialchars($current['contact_phone'] ?? '') ?>"></div>
            </div>
            <div class="grid-2">
              <div class="field"><label><?= htmlspecialchars(t('admin.whatsapp', 'WhatsApp Number')) ?></label><input type="text" name="contact_whatsapp" value="<?= htmlspecialchars($current['contact_whatsapp'] ?? '') ?>"></div>
              <div class="field"><label><?= htmlspecialchars(t('admin.whatsapp_display', 'WhatsApp Label')) ?></label><input type="text" name="contact_whatsapp_display" value="<?= htmlspecialchars($current['contact_whatsapp_display'] ?? '') ?>"></div>
            </div>
            <div class="grid-2">
              <div class="field"><label><?= htmlspecialchars(t('admin.address')) ?></label><input type="text" name="contact_address" value="<?= htmlspecialchars($current['contact_address'] ?? '') ?>"></div>
              <div class="field"><label><?= htmlspecialchars(t('admin.hours', 'Support Hours')) ?></label><input type="text" name="contact_hours" value="<?= htmlspecialchars($current['contact_hours'] ?? '') ?>"></div>
            </div>
            <div class="field"><label><?= htmlspecialchars(t('admin.google_maps')) ?></label><input type="text" name="google_maps" value="<?= htmlspecialchars($current['google_maps'] ?? '') ?>" placeholder="https://maps.google.com/?q=..."></div>

          <?php elseif ($activeGroup === 'general'): ?>
            <div class="grid-2">
              <div class="field"><label><?= htmlspecialchars(t('admin.currencies', 'Currency')) ?></label><input type="text" name="currency" value="<?= htmlspecialchars($current['currency'] ?? '') ?>"></div>
              <div class="field"><label><?= htmlspecialchars(t('admin.currency_symbol', 'Currency Symbol')) ?></label><input type="text" name="currency_symbol" value="<?= htmlspecialchars($current['currency_symbol'] ?? '') ?>"></div>
            </div>
            <div class="grid-2">
              <div class="field"><label><?= htmlspecialchars(t('admin.default_language', 'Default Language')) ?></label>
                <select name="default_language">
                  <option value="en" <?= ($current['default_language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                  <option value="ar" <?= ($current['default_language'] ?? 'en') === 'ar' ? 'selected' : '' ?>>Arabic</option>
                </select>
              </div>
              <div class="field"><label><?= htmlspecialchars(t('admin.languages', 'Enabled Languages')) ?></label><input type="text" name="site_locales" value="<?= htmlspecialchars($current['site_locales'] ?? '') ?>" placeholder="en,ar"></div>
            </div>
            <div class="field"><label><?= htmlspecialchars(t('admin.default_dir', 'Default Direction')) ?></label>
              <select name="default_dir">
                <option value="ltr" <?= ($current['default_dir'] ?? 'ltr') === 'ltr' ? 'selected' : '' ?>>LTR</option>
                <option value="rtl" <?= ($current['default_dir'] ?? 'ltr') === 'rtl' ? 'selected' : '' ?>>RTL</option>
              </select>
            </div>
            <div class="field"><label><?= htmlspecialchars(t('admin.setting_timezone', 'Timezone')) ?></label>
              <select name="timezone">
                <?php
                  $tz = $current['timezone'] ?? 'Asia/Amman';
                  $timezones = ['UTC','Asia/Amman','Europe/London','Europe/Paris','Europe/Berlin','America/New_York','America/Los_Angeles','Asia/Dubai','Asia/Riyadh','Asia/Kuwait','Asia/Qatar','Asia/Baghdad','Africa/Cairo'];
                  foreach ($timezones as $tzOpt): ?>
                    <option value="<?= htmlspecialchars($tzOpt) ?>" <?= $tz === $tzOpt ? 'selected' : '' ?>><?= htmlspecialchars($tzOpt) ?></option>
                  <?php endforeach; ?>
              </select>
            </div>

          <?php elseif ($activeGroup === 'social'): ?>
            <div class="grid-2">
              <div class="field"><label><?= htmlspecialchars(t('admin.facebook')) ?></label><input type="text" name="social_facebook" value="<?= htmlspecialchars($current['social_facebook'] ?? '') ?>" placeholder="https://facebook.com/..."></div>
              <div class="field"><label><?= htmlspecialchars(t('admin.instagram')) ?></label><input type="text" name="social_instagram" value="<?= htmlspecialchars($current['social_instagram'] ?? '') ?>" placeholder="https://instagram.com/..."></div>
            </div>
            <div class="grid-2">
              <div class="field"><label><?= htmlspecialchars(t('admin.twitter')) ?></label><input type="text" name="social_twitter" value="<?= htmlspecialchars($current['social_twitter'] ?? '') ?>" placeholder="https://twitter.com/..."></div>
              <div class="field"><label><?= htmlspecialchars(t('admin.youtube')) ?></label><input type="text" name="social_youtube" value="<?= htmlspecialchars($current['social_youtube'] ?? '') ?>" placeholder="https://youtube.com/..."></div>
            </div>
            <div class="grid-2">
              <div class="field"><label><?= htmlspecialchars(t('admin.tiktok')) ?></label><input type="text" name="social_tiktok" value="<?= htmlspecialchars($current['social_tiktok'] ?? '') ?>" placeholder="https://tiktok.com/..."></div>
              <div class="field"><label><?= htmlspecialchars(t('admin.linkedin')) ?></label><input type="text" name="social_linkedin" value="<?= htmlspecialchars($current['social_linkedin'] ?? '') ?>" placeholder="https://linkedin.com/..."></div>
            </div>

          <?php elseif ($activeGroup === 'footer'): ?>
            <div class="field"><label><?= htmlspecialchars(t('admin.footer_info', 'Footer About')) ?></label><textarea name="footer_about" rows="4"><?= htmlspecialchars($current['footer_about'] ?? '') ?></textarea></div>
            <div class="grid-3">
              <div class="field"><label><?= htmlspecialchars(t('admin.terms_label', 'Terms Label')) ?></label><input type="text" name="footer_terms_label" value="<?= htmlspecialchars($current['footer_terms_label'] ?? '') ?>"></div>
              <div class="field"><label><?= htmlspecialchars(t('admin.privacy_label', 'Privacy Label')) ?></label><input type="text" name="footer_privacy_label" value="<?= htmlspecialchars($current['footer_privacy_label'] ?? '') ?>"></div>
              <div class="field"><label><?= htmlspecialchars(t('admin.cookies_label', 'Cookies Label')) ?></label><input type="text" name="footer_cookies_label" value="<?= htmlspecialchars($current['footer_cookies_label'] ?? '') ?>"></div>
            </div>
            <div class="grid-2">
              <div class="field"><label><?= htmlspecialchars(t('admin.payment_icons', 'Accepted Payments')) ?></label><input type="text" name="payment_icons" value="<?= htmlspecialchars($current['payment_icons'] ?? '') ?>"></div>
              <div class="field"><label><?= htmlspecialchars(t('admin.newsletter_placeholder', 'Newsletter Placeholder')) ?></label><input type="text" name="footer_newsletter_placeholder" value="<?= htmlspecialchars($current['footer_newsletter_placeholder'] ?? '') ?>"></div>
            </div>

          <?php elseif ($activeGroup === 'seo'): ?>
            <div class="field"><label><?= htmlspecialchars(t('admin.seo_defaults', 'SEO Brand Suffix')) ?></label><input type="text" name="seo_brand_suffix" value="<?= htmlspecialchars($current['seo_brand_suffix'] ?? '') ?>"></div>
            <div class="hint"><?= htmlspecialchars(t('admin.seo_hint', 'Appended to page titles, e.g. " — GAIA TOURS & TRAVEL".')) ?></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.meta_title')) ?></label><input type="text" name="seo_meta_title" value="<?= htmlspecialchars($current['seo_meta_title'] ?? '') ?>"></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.meta_description')) ?></label><textarea name="seo_meta_description" rows="3"><?= htmlspecialchars($current['seo_meta_description'] ?? '') ?></textarea></div>
            <div class="field"><label><?= htmlspecialchars(t('admin.keywords')) ?></label><input type="text" name="seo_meta_keywords" value="<?= htmlspecialchars($current['seo_meta_keywords'] ?? '') ?>"></div>
            <div class="grid-2">
              <div class="field"><label><?= htmlspecialchars(t('admin.og_image')) ?></label><input type="text" name="seo_og_image" value="<?= htmlspecialchars($current['seo_og_image'] ?? '') ?>" placeholder="https://.../og-image.jpg"></div>
              <div class="field"><label><?= htmlspecialchars(t('admin.twitter_image')) ?></label><input type="text" name="seo_twitter_image" value="<?= htmlspecialchars($current['seo_twitter_image'] ?? '') ?>" placeholder="https://.../twitter-card.jpg"></div>
            </div>
            <div class="field"><label><?= htmlspecialchars(t('admin.canonical_url')) ?></label><input type="text" name="seo_canonical_url" value="<?= htmlspecialchars($current['seo_canonical_url'] ?? '') ?>" placeholder="https://example.com/"></div>
          <?php endif; ?>

          <div class="form-actions">
            <button type="submit" class="btn"><?= htmlspecialchars(t('admin.save')) ?></button>
          </div>
        </form>
      </div>

    </div>
  </div>
</div>
<?php require __DIR__ . '/components/footer.php'; ?>

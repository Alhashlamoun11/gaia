-- ============================================================
-- GAIA TOURS & TRAVEL — Multilingual & Production Readiness
-- Migration #5
-- ------------------------------------------------------------
-- Adds:
--   * nav_key to footer menu items (for translated labels)
--   * SEO meta for about/contact pages (EN/AR)
--   * Missing translations for page titles, breadcrumbs, etc.
--   * Settings for default language / RTL
-- Safe to run repeatedly.
-- ============================================================
USE gaia_tours;

-- ------------------------------------------------------------
-- 1) Add nav_key column to menu_items if missing
-- ------------------------------------------------------------
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'menu_items'
    AND COLUMN_NAME = 'nav_key'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE menu_items ADD COLUMN nav_key VARCHAR(50) NULL AFTER menu',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 2) Add nav_key to footer menu items for translation
-- ------------------------------------------------------------
UPDATE menu_items SET nav_key = 'home'        WHERE id = 14 AND nav_key IS NULL;
UPDATE menu_items SET nav_key = 'about'       WHERE id = 15 AND nav_key IS NULL;
UPDATE menu_items SET nav_key = 'reviews'     WHERE id = 16 AND nav_key IS NULL;
UPDATE menu_items SET nav_key = 'contact'     WHERE id = 17 AND nav_key IS NULL;
UPDATE menu_items SET nav_key = 'tours'       WHERE id = 18 AND nav_key IS NULL;
UPDATE menu_items SET nav_key = 'destinations' WHERE id = 19 AND nav_key IS NULL;
UPDATE menu_items SET nav_key = 'hotels'      WHERE id = 20 AND nav_key IS NULL;
UPDATE menu_items SET nav_key = 'transfers'   WHERE id = 21 AND nav_key IS NULL;
UPDATE menu_items SET nav_key = 'tours'       WHERE id = 22 AND nav_key IS NULL;
UPDATE menu_items SET nav_key = 'tours'       WHERE id = 23 AND nav_key IS NULL;
UPDATE menu_items SET nav_key = 'night'       WHERE id = 24 AND nav_key IS NULL;
UPDATE menu_items SET nav_key = 'night'       WHERE id = 25 AND nav_key IS NULL;
UPDATE menu_items SET nav_key = 'night'       WHERE id = 26 AND nav_key IS NULL;
UPDATE menu_items SET nav_key = 'night'       WHERE id = 27 AND nav_key IS NULL;
UPDATE menu_items SET nav_key = 'night'       WHERE id = 28 AND nav_key IS NULL;
UPDATE menu_items SET nav_key = 'transfers'   WHERE id = 29 AND nav_key IS NULL;
UPDATE menu_items SET nav_key = 'transfers'   WHERE id = 30 AND nav_key IS NULL;
UPDATE menu_items SET nav_key = 'transfers'   WHERE id = 31 AND nav_key IS NULL;

-- ------------------------------------------------------------
-- 3) SEO meta for about / contact (EN/AR)
-- ------------------------------------------------------------
INSERT IGNORE INTO seo_meta (page, lang, title, meta_description, keywords, og_image, is_active) VALUES
('about', 'en', 'About GAIA — Tours, Transfers & Premium Experiences', 'Learn about GAIA Tours & Travel — group tours, hotels, private transfers, night events and VIP experiences under one trusted brand.', 'gaia, about, tours, transfers, jordan', NULL, 1),
('about', 'ar', 'عن غايا — جولات ونقل وتجارب فاخرة', 'تعرف على غايا جولات والسفر — جولات جماعية وفنادق ونقل خاص وفعاليات ليلية وتجارب VIP تحت علامة واحدة موثوقة.', 'غايا, عن, جولات, نقل, الأردن', NULL, 1),
('contact', 'en', 'Contact GAIA — We''re Here to Help', 'Contact the GAIA team for bookings, questions or support. WhatsApp 24/7.', 'gaia, contact, support, bookings', NULL, 1),
('contact', 'ar', 'اتصل بغايا — نحن هنا للمساعدة', 'تواصل مع فريق غايا للحجوزات أو الأسئلة أو الدعم. واتساب 24/7.', 'غايا, اتصال, دعم, حجوزات', NULL, 1);

-- ------------------------------------------------------------
-- 4) Missing translations for page titles / breadcrumbs
-- ------------------------------------------------------------
INSERT IGNORE INTO translations (`key`, lang, `value`, `group`) VALUES
('nav.about', 'en', 'About', 'nav'),
('nav.about', 'ar', 'عن غايا', 'nav'),
('page.about_title', 'en', 'About GAIA', 'page'),
('page.about_title', 'ar', 'عن غايا', 'page'),
('page.contact_title', 'en', 'Contact', 'page'),
('page.contact_title', 'ar', 'اتصل بنا', 'page'),
('page.home', 'en', 'Home', 'page'),
('page.home', 'ar', 'الرئيسية', 'page'),
('page.breadcrumb_sep', 'en', '/', 'page'),
('page.breadcrumb_sep', 'ar', '/', 'page'),
('page.not_found', 'en', 'Page Not Found', 'page'),
('page.not_found', 'ar', 'الصفحة غير موجودة', 'page'),
('page.not_found_text', 'en', 'The page you are looking for does not exist.', 'page'),
('page.not_found_text', 'ar', 'الصفحة التي تبحث عنها غير موجودة.', 'page'),
('page.contact_email', 'en', 'Email', 'page'),
('page.contact_email', 'ar', 'البريد الإلكتروني', 'page'),
('page.contact_whatsapp', 'en', 'WhatsApp', 'page'),
('page.contact_whatsapp', 'ar', 'واتساب', 'page'),
('page.contact_location', 'en', 'Location', 'page'),
('page.contact_location', 'ar', 'الموقع', 'page');

-- ------------------------------------------------------------
-- 5) Settings for default language / RTL
-- ------------------------------------------------------------
INSERT IGNORE INTO settings (`key`, `value`, `type`, `group`, is_active) VALUES
('default_language', 'en', 'text', 'general', 1),
('default_dir', 'ltr', 'text', 'general', 1),
('site_locales', 'en,ar', 'text', 'general', 1);
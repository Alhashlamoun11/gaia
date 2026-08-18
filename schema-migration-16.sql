-- ============================================================
-- GAIA TOURS & TRAVEL — Admin Panel Complete CMS
-- Migration #16
-- ------------------------------------------------------------
-- Objective: Add contact_messages table, theme settings,
-- copyright, destinations SEO columns, and promo code admin
-- support columns.
-- Safe to run repeatedly (idempotent).
-- ============================================================
USE gaia_tours;

-- ------------------------------------------------------------
-- 1) CONTACT_MESSAGES — inbox for contact form submissions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(200) NOT NULL,
  phone VARCHAR(50) NULL,
  subject VARCHAR(200) NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  replied TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cm_read (is_read),
  INDEX idx_cm_created (created_at)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2) DESTINATIONS — add featured + SEO columns
-- ------------------------------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='destinations' AND COLUMN_NAME='featured');
SET @s := IF(@c=0, 'ALTER TABLE destinations ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0 AFTER image_url', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='destinations' AND COLUMN_NAME='seo_title');
SET @s := IF(@c=0, 'ALTER TABLE destinations ADD COLUMN seo_title VARCHAR(255) NULL AFTER featured', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='destinations' AND COLUMN_NAME='seo_description');
SET @s := IF(@c=0, 'ALTER TABLE destinations ADD COLUMN seo_description VARCHAR(500) NULL AFTER seo_title', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------
-- 3) SETTINGS — theme, copyright, advanced contact
-- ------------------------------------------------------------
INSERT IGNORE INTO settings (`key`, `value`, `type`, `group`, is_active) VALUES
('copyright', '© {year} GAIA TOURS & TRAVEL. All rights reserved.', 'text', 'footer', 1),
('theme_primary', '#1b2a4a', 'text', 'theme', 1),
('theme_accent', '#1f6f8f', 'text', 'theme', 1),
('theme_secondary', '#243761', 'text', 'theme', 1),
('theme_radius', '14px', 'text', 'theme', 1),
('theme_shadow', '0 10px 30px rgba(27,42,74,0.08)', 'text', 'theme', 1),
('theme_warm', '#f4efe6', 'text', 'theme', 1),
('theme_header_style', 'solid', 'text', 'theme', 1),
('theme_footer_style', 'dark', 'text', 'theme', 1),
('contact_map_embed', '', 'textarea', 'contact', 1),
('contact_inquiry_email', '', 'text', 'contact', 1),
('seo_robots', 'index,follow', 'text', 'seo', 1),
('seo_meta_author', 'GAIA TOURS & TRAVEL', 'text', 'seo', 1);

-- ------------------------------------------------------------
-- 4) REVIEWS — add service_type + review language
-- ------------------------------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reviews' AND COLUMN_NAME='service_type');
SET @s := IF(@c=0, 'ALTER TABLE reviews ADD COLUMN service_type VARCHAR(50) NULL DEFAULT ''transfer'' AFTER place', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reviews' AND COLUMN_NAME='reviewer_avatar');
SET @s := IF(@c=0, 'ALTER TABLE reviews ADD COLUMN reviewer_avatar VARCHAR(500) NULL AFTER reviewer', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------
-- 5) PAGES — add navigation section + featured columns if missing
-- ------------------------------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pages' AND COLUMN_NAME='sort_order');
SET @s := IF(@c=0, 'ALTER TABLE pages ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER slug', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------
-- 6) ADMIN TRANSLATION KEYS for new modules
-- ------------------------------------------------------------
INSERT IGNORE INTO translations (`key`, lang, `value`, `group`) VALUES
('admin.pages', 'en', 'Pages', 'admin'),
('admin.pages', 'ar', 'الصفحات', 'admin'),
('admin.menu', 'en', 'Navigation', 'admin'),
('admin.menu', 'ar', 'القائمة', 'admin'),
('admin.newsletter', 'en', 'Newsletter', 'admin'),
('admin.newsletter', 'ar', 'النشرة البريدية', 'admin'),
('admin.messages', 'en', 'Messages', 'admin'),
('admin.messages', 'ar', 'الرسائل', 'admin'),
('admin.promo_codes', 'en', 'Promo Codes', 'admin'),
('admin.promo_codes', 'ar', 'أكواد الخصم', 'admin'),
('admin.destinations', 'en', 'Destinations', 'admin'),
('admin.destinations', 'ar', 'الوجهات', 'admin'),
('admin.subscribers', 'en', 'Subscribers', 'admin'),
('admin.subscribers', 'ar', 'المشتركون', 'admin'),
('admin.export_csv', 'en', 'Export CSV', 'admin'),
('admin.export_csv', 'ar', 'تصدير CSV', 'admin'),
('admin.import', 'en', 'Import', 'admin'),
('admin.import', 'ar', 'استيراد', 'admin'),
('admin.missing_keys', 'en', 'Missing Keys', 'admin'),
('admin.missing_keys', 'ar', 'المفاتيح المفقودة', 'admin'),
('admin.bulk_delete', 'en', 'Bulk Delete', 'admin'),
('admin.bulk_delete', 'ar', 'حذف متعدد', 'admin'),
('admin.theme', 'en', 'Theme', 'admin'),
('admin.theme', 'ar', 'المظهر', 'admin'),
('admin.copyright', 'en', 'Copyright', 'admin'),
('admin.copyright', 'ar', 'حقوق النشر', 'admin'),
('admin.robots', 'en', 'Robots', 'admin'),
('admin.robots', 'ar', 'روبوتات', 'admin'),
('admin.primary', 'en', 'Primary Color', 'admin'),
('admin.primary', 'ar', 'اللون الأساسي', 'admin'),
('admin.accent', 'en', 'Accent Color', 'admin'),
('admin.accent', 'ar', 'اللون المميز', 'admin'),
('admin.header_style', 'en', 'Header Style', 'admin'),
('admin.header_style', 'ar', 'نمط الترويسة', 'admin'),
('admin.footer_style', 'en', 'Footer Style', 'admin'),
('admin.footer_style', 'ar', 'نمط التذييل', 'admin'),
('admin.map_embed', 'en', 'Map Embed', 'admin'),
('admin.map_embed', 'ar', 'خريطة مدمجة', 'admin'),
('admin.inquiry_email', 'en', 'Inquiry Email', 'admin'),
('admin.inquiry_email', 'ar', 'بريد الاستفسار', 'admin');
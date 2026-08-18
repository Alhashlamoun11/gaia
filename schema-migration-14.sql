-- ============================================================
-- GAIA TOURS & TRAVEL — Complete Admin Panel Integration
-- Migration #14
-- ------------------------------------------------------------
-- Objective: Add missing columns, review management tables,
-- and admin translation keys required for full Admin Panel
-- integration across all modules.
--
-- Safe to run repeatedly (idempotent).
-- ============================================================
USE gaia_tours;

-- ------------------------------------------------------------
-- 1) HOTEL_ROOMS — add beds, size_sqm, facilities, gallery_urls
-- ------------------------------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_rooms' AND COLUMN_NAME='beds');
SET @s := IF(@c=0, 'ALTER TABLE hotel_rooms ADD COLUMN beds VARCHAR(60) NULL AFTER name', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_rooms' AND COLUMN_NAME='size_sqm');
SET @s := IF(@c=0, 'ALTER TABLE hotel_rooms ADD COLUMN size_sqm INT UNSIGNED NULL AFTER capacity', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_rooms' AND COLUMN_NAME='facilities');
SET @s := IF(@c=0, 'ALTER TABLE hotel_rooms ADD COLUMN facilities VARCHAR(500) NULL AFTER size_sqm', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_rooms' AND COLUMN_NAME='gallery_urls');
SET @s := IF(@c=0, 'ALTER TABLE hotel_rooms ADD COLUMN gallery_urls TEXT NULL AFTER image_url', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------
-- 2) EVENTS — add slug, gallery_urls, destination_id, sort_order, featured, seo
-- ------------------------------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='slug');
SET @s := IF(@c=0, 'ALTER TABLE events ADD COLUMN slug VARCHAR(180) NULL UNIQUE AFTER title', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='gallery_urls');
SET @s := IF(@c=0, 'ALTER TABLE events ADD COLUMN gallery_urls TEXT NULL AFTER image_url', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='destination_id');
SET @s := IF(@c=0, 'ALTER TABLE events ADD COLUMN destination_id INT UNSIGNED NULL AFTER gallery_urls', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='sort_order');
SET @s := IF(@c=0, 'ALTER TABLE events ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER destination_id', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='featured');
SET @s := IF(@c=0, 'ALTER TABLE events ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0 AFTER sort_order', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='seo_title');
SET @s := IF(@c=0, 'ALTER TABLE events ADD COLUMN seo_title VARCHAR(255) NULL AFTER featured', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='seo_description');
SET @s := IF(@c=0, 'ALTER TABLE events ADD COLUMN seo_description VARCHAR(500) NULL AFTER seo_title', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------
-- 3) HOTEL_REVIEWS & WEROAD_REVIEWS — add is_active column for admin approve/reject
-- ------------------------------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_reviews' AND COLUMN_NAME='is_active');
SET @s := IF(@c=0, 'ALTER TABLE hotel_reviews ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_reviews' AND COLUMN_NAME='is_active');
SET @s := IF(@c=0, 'ALTER TABLE weroad_reviews ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------
-- 4) REVIEWS — add featured column
-- ------------------------------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reviews' AND COLUMN_NAME='featured');
SET @s := IF(@c=0, 'ALTER TABLE reviews ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------
-- 5) HOTEL_FACILITIES — is_active column (may already exist, safe)
-- ------------------------------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_facilities' AND COLUMN_NAME='is_active');
SET @s := IF(@c=0, 'ALTER TABLE hotel_facilities ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------
-- 6) SETTINGS — add timezone, social_links, language settings
-- ------------------------------------------------------------
INSERT IGNORE INTO settings (`key`, `value`, `type`, `group`, is_active) VALUES
('timezone', 'Asia/Amman', 'text', 'general', 1),
('social_facebook', 'https://facebook.com/gaiatours', 'text', 'social', 1),
('social_instagram', 'https://instagram.com/gaiatours', 'text', 'social', 1),
('social_twitter', 'https://twitter.com/gaiatours', 'text', 'social', 1),
('social_youtube', 'https://youtube.com/gaiatours', 'text', 'social', 1),
('social_tiktok', 'https://tiktok.com/@gaiatours', 'text', 'social', 1),
('social_linkedin', 'https://linkedin.com/company/gaiatours', 'text', 'social', 1),
('seo_meta_title', 'GAIA TOURS & TRAVEL — Airport Transfers & Car Rentals', 'text', 'seo', 1),
('seo_meta_description', 'Book your private airport transfer with GAIA. Transparent pricing, professional drivers, real-time flight tracking.', 'text', 'seo', 1),
('seo_meta_keywords', 'gaia, transfer, taxi, airport, car rental, travel, tours', 'text', 'seo', 1),
('seo_og_image', '', 'text', 'seo', 1),
('seo_twitter_image', '', 'text', 'seo', 1),
('seo_canonical_url', '', 'text', 'seo', 1);

-- ------------------------------------------------------------
-- 7) ADMIN TRANSLATION KEYS (EN/AR) for new modules
-- ------------------------------------------------------------
INSERT IGNORE INTO translations (`key`, lang, `value`, `group`) VALUES
-- Homepage CMS
('admin.homepage', 'en', 'Homepage', 'admin'),
('admin.homepage', 'ar', 'الصفحة الرئيسية', 'admin'),
('admin.banners', 'en', 'Banners', 'admin'),
('admin.banners', 'ar', 'اللافتات', 'admin'),
('admin.highlights', 'en', 'Highlights', 'admin'),
('admin.highlights', 'ar', 'أبرز النقاط', 'admin'),
('admin.trust_scores', 'en', 'Trust Scores', 'admin'),
('admin.trust_scores', 'ar', 'درجات الثقة', 'admin'),
('admin.awards', 'en', 'Awards', 'admin'),
('admin.awards', 'ar', 'الجوائز', 'admin'),
('admin.night_offerings', 'en', 'Night Offerings', 'admin'),
('admin.night_offerings', 'ar', 'عروض الليل', 'admin'),
('admin.social_links', 'en', 'Social Links', 'admin'),
('admin.social_links', 'ar', 'روابط التواصل', 'admin'),
('admin.featured', 'en', 'Featured', 'admin'),
('admin.featured', 'ar', 'مميز', 'admin'),
('admin.banner_image', 'en', 'Banner Image', 'admin'),
('admin.banner_image', 'ar', 'صورة اللافتة', 'admin'),
('admin.stats_json', 'en', 'Stats (JSON)', 'admin'),
('admin.stats_json', 'ar', 'الإحصائيات (JSON)', 'admin'),
('admin.page', 'en', 'Page', 'admin'),
('admin.page', 'ar', 'الصفحة', 'admin'),
('admin.score', 'en', 'Score', 'admin'),
('admin.score', 'ar', 'النتيجة', 'admin'),
('admin.per_label', 'en', 'Per Label', 'admin'),
('admin.per_label', 'ar', 'تسمية لكل', 'admin'),
('admin.price_value', 'en', 'Price Value', 'admin'),
('admin.price_value', 'ar', 'قيمة السعر', 'admin'),
('admin.badge', 'en', 'Badge', 'admin'),
('admin.badge', 'ar', 'الشارة', 'admin'),

-- SEO Management
('admin.seo', 'en', 'SEO', 'admin'),
('admin.seo', 'ar', 'تحسين محركات البحث', 'admin'),
('admin.seo_meta', 'en', 'SEO Meta', 'admin'),
('admin.seo_meta', 'ar', 'بيانات تحسين محركات البحث', 'admin'),
('admin.meta_title', 'en', 'Meta Title', 'admin'),
('admin.meta_title', 'ar', 'عنوان ميتا', 'admin'),
('admin.meta_description', 'en', 'Meta Description', 'admin'),
('admin.meta_description', 'ar', 'وصف ميتا', 'admin'),
('admin.keywords', 'en', 'Keywords', 'admin'),
('admin.keywords', 'ar', 'الكلمات المفتاحية', 'admin'),
('admin.canonical_url', 'en', 'Canonical URL', 'admin'),
('admin.canonical_url', 'ar', 'الرابط الأساسي', 'admin'),
('admin.og_image', 'en', 'Open Graph Image', 'admin'),
('admin.og_image', 'ar', 'صورة Open Graph', 'admin'),
('admin.twitter_image', 'en', 'Twitter Card Image', 'admin'),
('admin.twitter_image', 'ar', 'صورة بطاقة تويتر', 'admin'),

-- Translations Management
('admin.translations', 'en', 'Translations', 'admin'),
('admin.translations', 'ar', 'الترجمات', 'admin'),
('admin.translation_key', 'en', 'Translation Key', 'admin'),
('admin.translation_key', 'ar', 'مفتاح الترجمة', 'admin'),
('admin.translation_group', 'en', 'Group', 'admin'),
('admin.translation_group', 'ar', 'المجموعة', 'admin'),
('admin.value', 'en', 'Value', 'admin'),
('admin.value', 'ar', 'القيمة', 'admin'),
('admin.lang', 'en', 'Language', 'admin'),
('admin.lang', 'ar', 'اللغة', 'admin'),

-- Tours sub-entities
('admin.itinerary', 'en', 'Itinerary', 'admin'),
('admin.itinerary', 'ar', 'خط السير', 'admin'),
('admin.included', 'en', 'Included', 'admin'),
('admin.included', 'ar', 'مشمول', 'admin'),
('admin.excluded', 'en', 'Excluded', 'admin'),
('admin.excluded', 'ar', 'غير مشمول', 'admin'),
('admin.optional_activities', 'en', 'Optional Activities', 'admin'),
('admin.optional_activities', 'ar', 'الأنشطة الاختيارية', 'admin'),
('admin.departures', 'en', 'Departures', 'admin'),
('admin.departures', 'ar', 'مواعيد المغادرة', 'admin'),
('admin.trip_faqs', 'en', 'Trip FAQs', 'admin'),
('admin.trip_faqs', 'ar', 'الأسئلة الشائعة للجولة', 'admin'),
('admin.trip_reviews', 'en', 'Trip Reviews', 'admin'),
('admin.trip_reviews', 'ar', 'تقييمات الجولة', 'admin'),
('admin.categories', 'en', 'Categories', 'admin'),
('admin.categories', 'ar', 'الفئات', 'admin'),
('admin.destinations', 'en', 'Destinations', 'admin'),
('admin.destinations', 'ar', 'الوجهات', 'admin'),
('admin.day_number', 'en', 'Day Number', 'admin'),
('admin.day_number', 'ar', 'رقم اليوم', 'admin'),
('admin.include_type', 'en', 'Include Type', 'admin'),
('admin.include_type', 'ar', 'نوع التضمين', 'admin'),
('admin.item_text', 'en', 'Item Text', 'admin'),
('admin.item_text', 'ar', 'نص العنصر', 'admin'),
('admin.start_date', 'en', 'Start Date', 'admin'),
('admin.start_date', 'ar', 'تاريخ البداية', 'admin'),
('admin.end_date', 'en', 'End Date', 'admin'),
('admin.end_date', 'ar', 'تاريخ النهاية', 'admin'),
('admin.spots_left', 'en', 'Spots Left', 'admin'),
('admin.spots_left', 'ar', 'المقاعد المتبقية', 'admin'),
('admin.faq_group', 'en', 'FAQ Group', 'admin'),
('admin.faq_group', 'ar', 'مجموعة الأسئلة', 'admin'),

-- Hotels sub-entities
('admin.facilities', 'en', 'Facilities', 'admin'),
('admin.facilities', 'ar', 'المرافق', 'admin'),
('admin.offers', 'en', 'Offers', 'admin'),
('admin.offers', 'ar', 'العروض', 'admin'),
('admin.amenities', 'en', 'Amenities', 'admin'),
('admin.amenities', 'ar', 'وسائل الراحة', 'admin'),
('admin.hotel_facilities', 'en', 'Hotel Facilities', 'admin'),
('admin.hotel_facilities', 'ar', 'مرافق الفندق', 'admin'),

-- Rooms extended
('admin.beds', 'en', 'Beds', 'admin'),
('admin.beds', 'ar', 'الأسرة', 'admin'),
('admin.size_sqm', 'en', 'Size (m²)', 'admin'),
('admin.size_sqm', 'ar', 'المساحة (م²)', 'admin'),
('admin.room_facilities', 'en', 'Room Facilities', 'admin'),
('admin.room_facilities', 'ar', 'مرافق الغرفة', 'admin'),

-- Media
('admin.media_replace', 'en', 'Replace File', 'admin'),
('admin.media_replace', 'ar', 'استبدال الملف', 'admin'),
('admin.media_folder', 'en', 'Folder', 'admin'),
('admin.media_folder', 'ar', 'المجلد', 'admin'),
('admin.select_image', 'en', 'Select Image', 'admin'),
('admin.select_image', 'ar', 'اختيار صورة', 'admin'),

-- Settings
('admin.setting_social', 'en', 'Social', 'admin'),
('admin.setting_social', 'ar', 'التواصل الاجتماعي', 'admin'),
('admin.setting_timezone', 'en', 'Timezone', 'admin'),
('admin.setting_timezone', 'ar', 'المنطقة الزمنية', 'admin'),
('admin.setting_advanced', 'en', 'Advanced', 'admin'),
('admin.setting_advanced', 'ar', 'متقدم', 'admin'),
('admin.facebook', 'en', 'Facebook', 'admin'),
('admin.facebook', 'ar', 'فيسبوك', 'admin'),
('admin.instagram', 'en', 'Instagram', 'admin'),
('admin.instagram', 'ar', 'إنستغرام', 'admin'),
('admin.twitter', 'en', 'Twitter', 'admin'),
('admin.twitter', 'ar', 'تويتر', 'admin'),
('admin.youtube', 'en', 'YouTube', 'admin'),
('admin.youtube', 'ar', 'يوتيوب', 'admin'),
('admin.tiktok', 'en', 'TikTok', 'admin'),
('admin.tiktok', 'ar', 'تيك توك', 'admin'),
('admin.linkedin', 'en', 'LinkedIn', 'admin'),
('admin.linkedin', 'ar', 'لينكد إن', 'admin'),
('admin.settings_saved', 'en', 'Settings saved successfully.', 'admin'),
('admin.settings_saved', 'ar', 'تم حفظ الإعدادات بنجاح.', 'admin'),

-- Booking status management
('admin.update_status', 'en', 'Update Status', 'admin'),
('admin.update_status', 'ar', 'تحديث الحالة', 'admin'),
('admin.new_status', 'en', 'New Status', 'admin'),
('admin.new_status', 'ar', 'الحالة الجديدة', 'admin'),
('admin.status_updated', 'en', 'Status updated successfully.', 'admin'),
('admin.status_updated', 'ar', 'تم تحديث الحالة بنجاح.', 'admin'),
('admin.status_transition_invalid', 'en', 'Status transition not allowed.', 'admin'),
('admin.status_transition_invalid', 'ar', 'تغيير الحالة غير مسموح به.', 'admin'),
('admin.current_status', 'en', 'Current Status', 'admin'),
('admin.current_status', 'ar', 'الحالة الحالية', 'admin'),

-- Review edit
('admin.edit_review', 'en', 'Edit Review', 'admin'),
('admin.edit_review', 'ar', 'تعديل التقييم', 'admin'),
('admin.review_updated', 'en', 'Review updated.', 'admin'),
('admin.review_updated', 'ar', 'تم تحديث التقييم.', 'admin');

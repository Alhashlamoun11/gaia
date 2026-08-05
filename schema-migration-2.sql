-- ============================================================
-- GAIA TOURS & TRAVEL — Dynamic Data Audit — Migration #2
-- ------------------------------------------------------------
-- Incremental, idempotent migration on top of schema-migration.sql.
-- Adds columns, extra settings, extra seo_meta rows and ALL the
-- translation keys required to make every public page fully
-- database-driven (EN/AR). Safe to run repeatedly.
--
-- NOTE: some statements (ALTER TABLE ... ADD COLUMN) are tolerant
-- on MySQL 8 / MariaDB when wrapped in prepared-statement checks.
-- ============================================================
USE gaia_tours;

-- ------------------------------------------------------------
-- 1) trust_scores: colour hint used by the review box
-- ------------------------------------------------------------
SET @ts_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'trust_scores' AND COLUMN_NAME = 'color');
SET @s := IF(@ts_col = 0, 'ALTER TABLE trust_scores ADD COLUMN color VARCHAR(20) NULL AFTER icon', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE trust_scores SET color = '#34e0a1' WHERE label = 'Tripadvisor' AND (color IS NULL OR color = '');
UPDATE trust_scores SET color = '#1f6f8f' WHERE label = 'Google' AND (color IS NULL OR color = '');

-- ------------------------------------------------------------
-- 2) night_offerings: per-label for packages
-- ------------------------------------------------------------
SET @no_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'night_offerings' AND COLUMN_NAME = 'per_label');
SET @s2 := IF(@no_col = 0, 'ALTER TABLE night_offerings ADD COLUMN per_label VARCHAR(60) NULL AFTER price_value', 'SELECT 1');
PREPARE stmt2 FROM @s2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

UPDATE night_offerings SET per_label = '/ per couple' WHERE type = 'package' AND title = 'Amman After Dark';
UPDATE night_offerings SET per_label = '/ per person'  WHERE type = 'package' AND per_label IS NULL;

-- ------------------------------------------------------------
-- 3) Extra global settings
-- ------------------------------------------------------------
INSERT IGNORE INTO settings (`key`, `value`, `type`, `group`, is_active) VALUES
('default_country', 'Italy', 'text', 'general', 1),
('seo_brand_suffix', ' — GAIA TOURS &amp; TRAVEL', 'text', 'seo', 1),
('newsletter_placeholder', 'Your email', 'text', 'newsletter', 1),
('newsletter_privacy', 'Privacy Policy', 'text', 'newsletter', 1),
('tours_deposit_text', 'starting from as little as $99 deposit', 'textarea', 'tours', 1);

-- ------------------------------------------------------------
-- 4) Extra seo_meta rows for dynamic pages
-- ------------------------------------------------------------
INSERT IGNORE INTO seo_meta (page, lang, title, meta_description, keywords, og_image, is_active) VALUES
('transfers_route', 'en', 'Taxi Transfer — GAIA TOURS &amp; TRAVEL', 'Book a private airport transfer with transparent pricing across Jordan, Italy and beyond.', 'gaia, transfer, taxi, airport, route', NULL, 1),
('transfers_route', 'ar', 'نقل تاكسي — غايا جولات وسفر', 'احجز نقلًا خاصًا من المطار بأسعار واضحة في الأردن وإيطاليا وخارجها.', 'غايا، نقل، تاكسي، مطار', NULL, 1),
('tour_detail', 'en', 'Trip — GAIA Tours', 'Explore a GAIA Tours group trip with a full itinerary, dates and reviews.', 'gaia tours, trip, group tour', NULL, 1),
('tour_detail', 'ar', 'الجولة — غايا جولات', 'استكشف جولة جماعية مع غايا جولات مع خط سير كامل وتواريخ وتقييمات.', 'غايا جولات، جولة', NULL, 1),
('tour_booking', 'en', 'Book your trip — GAIA Tours', 'Complete your trip booking on GAIA Tours.', 'gaia tours, booking, trip', NULL, 1),
('tour_booking', 'ar', 'احجز جولتك — غايا جولات', 'أكمل حجز جولتك على غايا جولات.', 'غايا جولات، حجز، جولة', NULL, 1);

-- ------------------------------------------------------------
-- 5) Translation keys — EN / AR
-- ------------------------------------------------------------
INSERT IGNORE INTO translations (`key`, lang, `value`, `group`) VALUES
-- brand / general
('brand.name', 'en', 'GAIA TOURS &amp; TRAVEL', 'general'),
('brand.name', 'ar', 'غايا جولات وسفر', 'general'),

-- home / transfers search card
('home.search_from', 'en', 'From', 'home'),
('home.search_from', 'ar', 'من', 'home'),
('home.search_to', 'en', 'To', 'home'),
('home.search_to', 'ar', 'إلى', 'home'),
('home.search_date', 'en', 'Date', 'home'),
('home.search_date', 'ar', 'التاريخ', 'home'),
('home.search_passengers', 'en', 'Passengers', 'home'),
('home.search_passengers', 'ar', 'الركاب', 'home'),
('home.search_origin_ph', 'en', 'From (airport, port, address)', 'home'),
('home.search_origin_ph', 'ar', 'من (مطار، ميناء، عنوان)', 'home'),
('home.search_dest_ph', 'en', 'To (airport, port, address)', 'home'),
('home.search_dest_ph', 'ar', 'إلى (مطار، ميناء، عنوان)', 'home'),
('home.search_btn', 'en', 'Search', 'home'),
('home.search_btn', 'ar', 'بحث', 'home'),
('home.reviews_from', 'en', 'from {count}+ reviews', 'home'),
('home.reviews_from', 'ar', 'من {count}+ تقييم', 'home'),
('home.multiplier', 'en', 'multiplier', 'home'),
('home.multiplier', 'ar', 'مضاعف', 'home'),
('home.newsletter_placeholder', 'en', 'Your email', 'home'),
('home.newsletter_placeholder', 'ar', 'بريدك الإلكتروني', 'home'),
('home.newsletter_privacy', 'en', 'Privacy Policy', 'home'),
('home.newsletter_privacy', 'ar', 'سياسة الخصوصية', 'home'),

-- transfers / route page
('transfers.unknown_route', 'en', 'Routes', 'transfers'),
('transfers.unknown_route', 'ar', 'مسارات', 'transfers'),
('transfers.select_date', 'en', 'Select date', 'transfers'),
('transfers.select_date', 'ar', 'اختر التاريخ', 'transfers'),
('transfers.min', 'en', '~{min} min', 'transfers'),
('transfers.min', 'ar', '~{min} دقيقة', 'transfers'),
('transfers.passenger_bags', 'en', '{passengers} passengers · up to {bags} bags', 'transfers'),
('transfers.passenger_bags', 'ar', '{passengers} ركاب · حتى {bags} حقائب', 'transfers'),
('transfers.distance_km', 'en', '{distance} km', 'transfers'),
('transfers.distance_km', 'ar', '{distance} كم', 'transfers'),
('transfers.price_from', 'en', 'From ${price}', 'transfers'),
('transfers.price_from', 'ar', 'من ${price}', 'transfers'),
('transfers.country_suffix', 'en', '{place}, {country}', 'transfers'),
('transfers.country_suffix', 'ar', '{place}، {country}', 'transfers'),

-- checkout page
('checkout.flight_number', 'en', 'Flight number', 'checkout'),
('checkout.flight_number', 'ar', 'رقم الرحلة', 'checkout'),
('checkout.flight_placeholder', 'en', 'e.g. AA 0000', 'checkout'),
('checkout.flight_placeholder', 'ar', 'مثال: AA 0000', 'checkout'),
('checkout.flight_hint', 'en', 'According to the ticket', 'checkout'),
('checkout.flight_hint', 'ar', 'حسب التذكرة', 'checkout'),
('checkout.pickup_date', 'en', 'Pickup date', 'checkout'),
('checkout.pickup_date', 'ar', 'تاريخ الاستلام', 'checkout'),
('checkout.arrival_time', 'en', 'Scheduled arrival time', 'checkout'),
('checkout.arrival_time', 'ar', 'وقت الوصول المقرر', 'checkout'),
('checkout.destination_label', 'en', 'Destination (address or hotel name)', 'checkout'),
('checkout.destination_label', 'ar', 'الوجهة (عنوان أو اسم فندق)', 'checkout'),
('checkout.destination_placeholder', 'en', 'Laguna Hotel', 'checkout'),
('checkout.destination_placeholder', 'ar', 'فندق لاجونا', 'checkout'),
('checkout.name_label', 'en', 'Name and Surname', 'checkout'),
('checkout.name_label', 'ar', 'الاسم واللقب', 'checkout'),
('checkout.name_placeholder', 'en', 'e.g. John Watson', 'checkout'),
('checkout.name_placeholder', 'ar', 'مثال: جون واتسون', 'checkout'),
('checkout.email_label', 'en', 'E-mail', 'checkout'),
('checkout.email_label', 'ar', 'البريد الإلكتروني', 'checkout'),
('checkout.email_placeholder', 'en', 'johnwatson@mail.com',

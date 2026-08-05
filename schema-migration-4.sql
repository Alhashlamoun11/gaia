-- ============================================================
-- GAIA TOURS & TRAVEL — Dynamic Data Audit — Migration #4
-- ------------------------------------------------------------
-- Completes the CMS-ready structure:
--   * media table (centralized image management)
--   * pages + page_sections (About, Contact, etc.)
--   * hotel_offers / hotel_reviews
--   * tour_categories / tour_destinations
--   * transfers_locations / transfer_services / booking_options
--   * ALL missing EN/AR translation keys for the weroad module
--   * extra settings (payment icons, logo, etc.)
-- Safe to run repeatedly.
-- ============================================================
USE gaia_tours;

-- ------------------------------------------------------------
-- 1) media: centralized image management
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS media (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(100) NOT NULL UNIQUE,          -- e.g. 'logo', 'hero_home', 'tour_jordan_1'
  file_path VARCHAR(500) NOT NULL,
  alt_text VARCHAR(255) NULL,
  title VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_cover TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_media_key (`key`),
  INDEX idx_media_active (is_active)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2) pages: CMS page content (About, Contact, etc.)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(150) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  subtitle VARCHAR(500) NULL,
  content TEXT NULL,
  image_url VARCHAR(500) NULL,
  lang VARCHAR(10) NOT NULL DEFAULT 'en',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pages_slug_lang (slug, lang)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3) page_sections: reusable content blocks for pages
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS page_sections (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page_slug VARCHAR(150) NOT NULL,
  section_key VARCHAR(100) NOT NULL,           -- e.g. 'hero', 'features', 'contact_form'
  title VARCHAR(255) NULL,
  subtitle VARCHAR(500) NULL,
  content TEXT NULL,
  image_url VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  INDEX idx_page_sections_page (page_slug, sort_order)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4) hotel_offers: special offers per hotel
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS hotel_offers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hotel_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  image_url VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_hotel_offer_hotel FOREIGN KEY (hotel_id) REFERENCES hotels (id) ON DELETE CASCADE,
  INDEX idx_hotel_offer_hotel (hotel_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5) hotel_reviews: reviews per hotel
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS hotel_reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hotel_id INT UNSIGNED NOT NULL,
  reviewer VARCHAR(100) NOT NULL,
  rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
  text TEXT NOT NULL,
  date_label VARCHAR(50) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_hotel_review_hotel FOREIGN KEY (hotel_id) REFERENCES hotels (id) ON DELETE CASCADE,
  INDEX idx_hotel_review_hotel (hotel_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6) tour_categories: tour categories
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tour_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  description TEXT NULL,
  image_url VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7) tour_destinations: tour destinations
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tour_destinations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  country VARCHAR(100) NULL,
  description TEXT NULL,
  image_url VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 8) transfers_locations: transfer origin/destination locations
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transfers_locations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  type ENUM('airport','city','port','hotel','station','other') NOT NULL DEFAULT 'other',
  country VARCHAR(100) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 9) transfer_services: extra services for transfers
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transfer_services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description VARCHAR(500) NULL,
  icon VARCHAR(100) NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  unit ENUM('flat','per_seat','per_item') NOT NULL DEFAULT 'flat',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 10) booking_options: configurable booking options
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS booking_options (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  description VARCHAR(500) NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 11) Extra settings
-- ------------------------------------------------------------
INSERT IGNORE INTO settings (`key`, `value`, `type`, `group`, is_active) VALUES
('logo_image', 'assets/images/image.png', 'image', 'company', 1),
('logo_mark_text', 'G', 'text', 'company', 1),
('payment_icons', 'VISA,MC,AMEX,Pay,G Pay', 'text', 'footer', 1),
('footer_about', 'One company. One platform. GAIA Tours & GAIA Night — group tours, hotels, private transfers, night events and VIP experiences, all under one GAIA brand.', 'textarea', 'footer', 1),
('footer_terms_label', 'Terms', 'text', 'footer', 1),
('footer_privacy_label', 'Privacy', 'text', 'footer', 1),
('footer_cookies_label', 'Cookies', 'text', 'footer', 1),
('footer_newsletter_placeholder', 'Your email', 'text', 'footer', 1),
('tours_hero_title', 'Discover Jordan.<br>Explore with GAIA Tours.', 'text', 'tours', 1),
('tours_hero_sub', 'Small group tours, family travel and private experiences across Petra, Wadi Rum, the Dead Sea and Amman — one trusted GAIA platform.', 'textarea', 'tours', 1),
('tours_promo_title', 'Waiting for your next adventure?', 'text', 'tours', 1),
('tours_promo_text', 'Book now and split the cost into interest-free installments. Your seat, your squad, your story.', 'textarea', 'tours', 1),
('tours_deals_title', 'Last minute deals', 'text', 'tours', 1),
('tours_category_title', 'The group tour that''s right for you', 'text', 'tours', 1),
('tours_loved_title', 'The most loved adventures', 'text', 'tours', 1),
('tours_trustpilot_title', 'Rated Excellent on Trustpilot', 'text', 'tours', 1),
('tours_cta_title', 'Ready to start the adventure?', 'text', 'tours', 1),
('tours_explore_btn', 'Explore trips', 'text', 'tours', 1),
('tours_book_btn', 'Book now', 'text', 'tours', 1),
('tours_see_all', 'See all', 'text', 'tours', 1),
('tours_from_label', 'From', 'text', 'tours', 1),
('tours_days_label', '{days} days', 'text', 'tours', 1),
('tours_no_deals', 'No deals available right now.', 'text', 'tours', 1),
('tours_no_categories', 'Categories coming soon.', 'text', 'tours', 1),
('tours_no_reviews', 'Reviews coming soon.', 'text', 'tours', 1),
('tours_search_placeholder', 'Where do you want to go?', 'text', 'tours', 1),
('tours_search_btn', 'Search', 'text', 'tours', 1),
('tours_where_label', 'Where', 'text', 'tours', 1),
('tours_when_label', 'When', 'text', 'tours', 1),
('tours_view_by', 'View by', 'text', 'tours', 1),
('tours_trips_tab', 'Trips', 'text', 'tours', 1),
('tours_dates_tab', 'Dates', 'text', 'tours', 1),
('tours_show_maps', 'Show maps', 'text', 'tours', 1),
('tours_results_found', '{count} trip(s) found', 'text', 'tours', 1),
('tours_compare', 'Compare', 'text', 'tours', 1),
('tours_see_dates', 'See departure dates', 'text', 'tours', 1),
('tours_show', 'Show {per}', 'text', 'tours', 1),
('tours_page_of', 'Page {page} of {total}', 'text', 'tours', 1),
('tours_no_results', 'No trips match your filters. Try adjusting your search.', 'text', 'tours', 1),
('tours_trip_home', 'Home', 'text', 'tours', 1),
('tours_trip_reviews', '{rating} ({reviews} reviews)', 'text', 'tours', 1),
('tours_trip_max_people', 'Max {max} people', 'text', 'tours', 1),
('tours_trip_effort', '{effort} effort', 'text', 'tours', 1),
('tours_trip_fit_title', 'Is this trip for me?', 'text', 'tours', 1),
('tours_trip_age', 'Age range', 'text', 'tours', 1),
('tours_trip_physical', 'Physical effort', 'text', 'tours', 1),
('tours_trip_comfort', 'Comfort level', 'text', 'tours', 1),
('tours_trip_accommodation', 'Accommodation', 'text', 'tours', 1),
('tours_trip_itinerary', 'Itinerary', 'text', 'tours', 1),
('tours_day_label', 'Day {day}', 'text', 'tours', 1),
('tours_trip_included', 'What''s included', 'text', 'tours', 1),
('tours_included_list', 'Included', 'text', 'tours', 1),
('tours_not_included_list', 'Not included', 'text', 'tours', 1),
('tours_trip_optional', 'Optional activities', 'text', 'tours', 1),
('tours_trip_optional_sub', 'Bookable directly with your Group Leader during the trip.', 'text', 'tours', 1),
('tours_trip_why', 'Why GAIA Tours', 'text', 'tours', 1),
('tours_trip_faqs', 'FAQs — Frequently Asked Questions', 'text', 'tours', 1),
('tours_trip_from', 'From', 'text', 'tours', 1),
('tours_trip_see_dates', 'See dates', 'text', 'tours', 1),
('tours_trip_spots_left', 'Only {spots} spots left for {month}', 'text', 'tours', 1),
('tours_trip_departures', '{count} departure date(s) available', 'text', 'tours', 1),
('tours_trip_no_departures', 'No upcoming departures.', 'text', 'tours', 1),
('tours_trip_travellers', 'Over {count} GAIA travellers have done this trip', 'text', 'tours', 1),
('tours_trip_installments', 'Interest-free installments', 'text', 'tours', 1),
('tours_trip_insurance', 'Medical & baggage insurance included', 'text', 'tours', 1),
('tours_trip_ask', 'Ask an expert', 'text', 'tours', 1),
('tours_booking_step1', 'Booking', 'text', 'tours', 1),
('tours_booking_step2', 'Travellers details', 'text', 'tours', 1),
('tours_booking_step3', 'Account data', 'text', 'tours', 1),
('tours_booking_step4', 'Payment', 'text', 'tours', 1),
('tours_booking_mandatory', 'Mandatory fields *', 'text', 'tours', 1),
('tours_booking_fullname', 'Full name *', 'text', 'tours', 1),
('tours_booking_email', 'Email *', 'text', 'tours', 1),
('tours_booking_phone', 'Phone', 'text', 'tours', 1),
('tours_booking_who', 'Who are you booking for? *', 'text', 'tours', 1),
('tours_booking_why_gender', 'Why we ask for your gender', 'text', 'tours', 1),
('tours_booking_female', 'FEMALE', 'text', 'tours', 1),
('tours_booking_male', 'MALE', 'text', 'tours', 1),
('tours_booking_included', 'Included in your trip', 'text', 'tours', 1),
('tours_booking_included_sub', 'These options are already included in the price', 'text', 'tours', 1),
('tours_booking_mixed', 'I''m OK with a mixed gender room', 'text', 'tours', 1),
('tours_booking_insurance_title', 'Medical and Baggage Insurance included', 'text', 'tours', 1),
('tours_booking_options', 'Options and extras', 'text', 'tours', 1),
('tours_booking_promo', 'Promo code', 'text', 'tours', 1),
('tours_booking_promo_ph', 'e.g. WEROAD10', 'text', 'tours', 1),
('tours_booking_apply', 'Apply', 'text', 'tours', 1),
('tours_booking_continue', 'Continue', 'text', 'tours', 1),
('tours_booking_your_trip', 'Your trip', 'text', 'tours', 1),
('tours_booking_from', 'From', 'text', 'tours', 1),
('tours_booking_to', 'To', 'text', 'tours', 1),
('tours_booking_available', 'Available', 'text', 'tours', 1),
('tours_booking_trip_price', 'Trip price', 'text', 'tours', 1),
('tours_booking_flex_line', 'Flexible cancellation', 'text', 'tours', 1),
('tours_booking_room_line', 'Private room', 'text', 'tours', 1),
('tours_booking_discount', 'Discount', 'text', 'tours', 1),
('tours_booking_total', 'Total', 'text', 'tours', 1),
('tours_booking_installments', 'Interest-free installments', 'text', 'tours', 1),
('tours_booking_over_travellers', 'Over <strong>{count}</strong> GAIA travellers have already done this trip', 'text', 'tours', 1),
('tours_booking_need_help', 'Do you need help?', 'text', 'tours', 1),
('tours_booking_ask', 'Ask an expert', 'text', 'tours', 1),
('home.partners_title', 'Our Partners', 'text', 'home', 1),
('home.partners_sub', 'Leading companies trust us with their most valuable asset: their clients', 'text', 'home', 1),
('home.faq_title', 'Frequently Asked Questions', 'text', 'home', 1),
('home.read_more', 'Read more', 'text', 'home', 1);

-- ------------------------------------------------------------
-- 12) Missing translation keys (EN/AR) for weroad module
-- ------------------------------------------------------------
INSERT IGNORE INTO translations (`key`, lang, `value`, `group`) VALUES
-- weroad homepage
('tours.hero_title', 'en', 'Discover Jordan.<br>Explore with GAIA Tours.', 'tours'),
('tours.hero_title', 'ar', 'اكتشف الأردن.<br>استكشف مع غايا جولات.', 'tours'),
('tours.hero_sub', 'en', 'Small group tours, family travel and private experiences across Petra, Wadi Rum, the Dead Sea and Amman — one trusted GAIA platform.', 'tours'),
('tours.hero_sub', 'ar', 'جولات جماعية صغيرة وسفر عائلي وتجارب خاصة في البتراء ووادي رم والبحر الميت وعمّان — منصة غايا الموثوقة.', 'tours'),
('tours.promo_title', 'en', 'Waiting for your next adventure?', 'tours'),
('tours.promo_title', 'ar', 'هل تنتظر مغامرتك القادمة؟', 'tours'),
('tours.promo_text', 'en', 'Book now and split the cost into interest-free installments. Your seat, your squad, your story.', 'tours'),
('tours.promo_text', 'ar', 'احجز الآن واقسم التكلفة إلى أقساط بدون فوائد. مقعدك، رفقتك، قصتك.', 'tours'),
('tours.book_now', 'en', 'Book now', 'tours'),
('tours.book_now', 'ar', 'احجز الآن', 'tours'),
('tours.deals_title', 'en', 'Last minute deals', 'tours'),
('tours.deals_title', 'ar', 'عروض اللحظة الأخيرة', 'tours'),
('tours.see_all', 'en', 'See all', 'tours'),
('tours.see_all', 'ar', 'عرض الكل', 'tours'),
('tours.category_title', 'en', 'The group tour that''s right for you', 'tours'),
('tours.category_title', 'ar', 'الجولة الجماعية المناسبة لك', 'tours'),
('tours.loved_title', 'en', 'The most loved adventures', 'tours'),
('tours.loved_title', 'ar', 'المغامرات الأكثر حبًا', 'tours'),
('tours.trustpilot_title', 'en', 'Rated Excellent on Trustpilot', 'tours'),
('tours.trustpilot_title', 'ar', 'مقيم بممتاز على ترستبيلوت', 'tours'),
('tours.cta_title', 'en', 'Ready to start the adventure?', 'tours'),
('tours.cta_title', 'ar', 'مستعد لبدء المغامرة؟', 'tours'),
('tours.explore_trips', 'en', 'Explore trips', 'tours'),
('tours.explore_trips', 'ar', 'استكشف الجولات', 'tours'),
('tours.price_from', 'en', 'From <b>${price}</b>', 'tours'),
('tours.price_from', 'ar', 'من <b>${price}</b>', 'tours'),
('tours.days', 'en', '{days} days', 'tours'),
('tours.days', 'ar', '{days} أيام', 'tours'),
('tours.no_deals', 'en', 'No deals available right now.', 'tours'),
('tours.no_deals', 'ar', 'لا توجد عروض متاحة حاليًا.', 'tours'),
('tours.no_categories', 'en', 'Categories coming soon.', 'tours'),
('tours.no_categories', 'ar', 'الفئات قريبًا.', 'tours'),
('tours.no_reviews', 'en', 'Reviews coming soon.', 'tours'),
('tours.no_reviews', 'ar', 'التقييمات قريبًا.', 'tours'),
('tours.search_placeholder', 'en', 'Where do you want to go?', 'tours'),
('tours.search_placeholder', 'ar', 'إلى أين تريد الذهاب؟', 'tours'),
('tours.search_btn', 'en', 'Search', 'tours'),
('tours.search_btn', 'ar', 'بحث', 'tours'),
('tours.search_where', 'en', 'Where', 'tours'),
('tours.search_where', 'ar', 'إلى أين', 'tours'),
('tours.search_dest', 'en', 'Search destination', 'tours'),
('tours.search_dest', 'ar', 'ابحث عن وجهة', 'tours'),
('tours.search_when', 'en', 'When', 'tours'),
('tours.search_when', 'ar', 'متى', 'tours'),
('tours.view_by', 'en', 'View by', 'tours'),
('tours.view_by', 'ar', 'عرض حسب', 'tours'),
('tours.trips_tab', 'en', 'Trips', 'tours'),
('tours.trips_tab', 'ar', 'الجولات', 'tours'),
('tours.dates_tab', 'en', 'Dates', 'tours'),
('tours.dates_tab', 'ar', 'التواريخ', 'tours'),
('tours.show_maps', 'en', 'Show maps', 'tours'),
('tours.show_maps', 'ar', 'إظهار الخرائط', 'tours'),
('tours.results_found', 'en', '{count} trip(s) found', 'tours'),
('tours.results_found', 'ar', 'تم العثور على {count} جولة', 'tours'),
('tours.compare', 'en', 'Compare', 'tours'),
('tours.compare', 'ar', 'مقارنة', 'tours'),
('tours.see_dates', 'en', 'See departure dates', 'tours'),
('tours.see_dates', 'ar', 'عرض تواريخ المغادرة', 'tours'),
('tours.show', 'en', 'Show {per}', 'tours'),
('tours.show', 'ar', 'عرض {per}', 'tours'),
('tours.page_of', 'en', 'Page {page} of {total}', 'tours'),
('tours.page_of', 'ar', 'صفحة {page} من {total}', 'tours'),
('tours.no_results', 'en', 'No trips match your filters. Try adjusting your search.', 'tours'),
('tours.no_results', 'ar', 'لا توجد جولات تطابق تصفيتك. حاول تعديل بحثك.', 'tours'),
('tours.trip_home', 'en', 'Home', 'tours'),
('tours.trip_home', 'ar', 'الرئيسية', 'tours'),
('tours.trip_reviews', 'en', '{rating} ({reviews} reviews)', 'tours'),
('tours.trip_reviews', 'ar', '{rating} ({reviews} تقييم)', 'tours'),
('tours.trip_max_people', 'en', 'Max {max} people', 'tours'),
('tours.trip_max_people', 'ar', 'بحد أقصى {max} أشخاص', 'tours'),
('tours.trip_effort', 'en', '{effort} effort', 'tours'),
('tours.trip_effort', 'ar', 'مجهود {effort}', 'tours'),
('tours.trip_fit_title', 'en', 'Is this trip for me?', 'tours'),
('tours.trip_fit_title', 'ar', 'هل هذه الجولة مناسبة لي؟', 'tours'),
('tours.trip_age', 'en', 'Age range', 'tours'),
('tours.trip_age', 'ar', 'الفئة العمرية', 'tours'),
('tours.trip_physical', 'en', 'Physical effort', 'tours'),
('tours.trip_physical', 'ar', 'الجهد البدني', 'tours'),
('tours.trip_comfort', 'en', 'Comfort level', 'tours'),
('tours.trip_comfort', 'ar', 'مستوى الراحة', 'tours'),
('tours.trip_accommodation', 'en', 'Accommodation', 'tours'),
('tours.trip_accommodation', 'ar', 'الإقامة', 'tours'),
('tours.trip_itinerary', 'en', 'Itinerary', 'tours'),
('tours.trip_itinerary', 'ar', 'خط السير', 'tours'),
('tours.day_label', 'en', 'Day {day}', 'tours'),
('tours.day_label', 'ar', 'اليوم {day}', 'tours'),
('tours.trip_included', 'en', 'What''s included', 'tours'),
('tours.trip_included', 'ar', 'ما الذي تشمله الجولة', 'tours'),
('tours.included_list', 'en', 'Included', 'tours'),
('tours.included_list', 'ar', 'مشمول', 'tours'),
('tours.not_included_list', 'en', 'Not included', 'tours'),
('tours.not_included_list', 'ar', 'غير مشمول', 'tours'),
('tours.trip_optional', 'en', 'Optional activities', 'tours'),
('tours.trip_optional', 'ar', 'أنشطة اختيارية', 'tours'),
('tours.trip_optional_sub', 'en', 'Bookable directly with your Group Leader during the trip.', 'tours'),
('tours.trip_optional_sub', 'ar', 'يمكن حجزها مباشرة مع قائد المجموعة أثناء الرحلة.', 'tours'),
('tours.trip_why', 'en', 'Why GAIA Tours', 'tours'),
('tours.trip_why', 'ar', 'لماذا غايا جولات', 'tours'),
('tours.trip_faqs', 'en', 'FAQs — Frequently Asked Questions', 'tours'),
('tours.trip_faqs', 'ar', 'الأسئلة الشائعة', 'tours'),
('tours.trip_from', 'en', 'From', 'tours'),
('tours.trip_from', 'ar', 'من', 'tours'),
('tours.trip_see_dates', 'en', 'See dates', 'tours'),
('tours.trip_see_dates', 'ar', 'عرض التواريخ', 'tours'),
('tours.trip_spots_left', 'en', 'Only {spots} spots left for {month}', 'tours'),
('tours.trip_spots_left', 'ar', 'تبقى {spots} مقاعد فقط لشهر {month}', 'tours'),
('tours.trip_departures', 'en', '{count} departure date(s) available', 'tours'),
('tours.trip_departures', 'ar', '{count} تاريخ مغادرة متاح', 'tours'),
('tours.trip_no_departures', 'en', 'No upcoming departures.', 'tours'),
('tours.trip_no_departures', 'ar', 'لا توجد مغادرات قادمة.', 'tours'),
('tours.trip_travellers', 'en', 'Over {count} GAIA travellers have done this trip', 'tours'),
('tours.trip_travellers', 'ar', 'أكثر من {count} مسافر مع غايا قاموا بهذه الجولة', 'tours'),
('tours.trip_installments', 'en', 'Interest-free installments', 'tours'),
('tours.trip_installments', 'ar', 'أقساط بدون فوائد', 'tours'),
('tours.trip_insurance', 'en', 'Medical & baggage insurance included', 'tours'),
('tours.trip_insurance', 'ar', 'تأمين طبي وأمتعة مشمول', 'tours'),
('tours.trip_ask', 'en', 'Ask an expert', 'tours'),
('tours.trip_ask', 'ar', 'اسأل خبيرًا', 'tours'),
('tours.booking_step1', 'en', 'Booking', 'tours'),
('tours.booking_step1', 'ar', 'الحجز', 'tours'),
('tours.booking_step2', 'en', 'Travellers details', 'tours'),
('tours.booking_step2', 'ar', 'تفاصيل المسافرين', 'tours'),
('tours.booking_step3', 'en', 'Account data', 'tours'),
('tours.booking_step3', 'ar', 'بيانات الحساب', 'tours'),
('tours.booking_step4', 'en', 'Payment', 'tours'),
('tours.booking_step4', 'ar', 'الدفع', 'tours'),
('tours.booking_mandatory', 'en', 'Mandatory fields *', 'tours'),
('tours.booking_mandatory', 'ar', 'حقول إلزامية *', 'tours'),
('tours.booking_fullname', 'en', 'Full name *', 'tours'),
('tours.booking_fullname', 'ar', 'الاسم الكامل *', 'tours'),
('tours.booking_email', 'en', 'Email *', 'tours'),
('tours.booking_email', 'ar', 'البريد الإلكتروني *', 'tours'),
('tours.booking_phone', 'en', 'Phone', 'tours'),
('tours.booking_phone', 'ar', 'الهاتف', 'tours'),
('tours.booking_who', 'en', 'Who are you booking for? *', 'tours'),
('tours.booking_who', 'ar', 'لمن تحجز؟ *', 'tours'),
('tours.booking_why_gender', 'en', 'Why we ask for your gender', 'tours'),
('tours.booking_why_gender', 'ar', 'لماذا نسأل عن جنسك', 'tours'),
('tours.booking_female', 'en', 'FEMALE', 'tours'),
('tours.booking_female', 'ar', 'أنثى', 'tours'),
('tours.booking_male', 'en', 'MALE', 'tours'),
('tours.booking_male', 'ar', 'ذكر', 'tours'),
('tours.booking_included', 'en', 'Included in your trip', 'tours'),
('tours.booking_included', 'ar', 'مشمول في جولتك', 'tours'),
('tours.booking_included_sub', 'en', 'These options are already included in the price', 'tours'),
('tours.booking_included_sub', 'ar', 'هذه الخيارات مشمولة بالفعل في السعر', 'tours'),
('tours.booking_mixed', 'en', 'I''m OK with a mixed gender room', 'tours'),
('tours.booking_mixed', 'ar', 'لا مانع لدي من غرفة مختلطة', 'tours'),
('tours.booking_insurance_title', 'en', 'Medical and Baggage Insurance included', 'tours'),
('tours.booking_insurance_title', 'ar', 'تأمين طبي وأمتعة مشمول', 'tours'),
('tours.booking_options', 'en', 'Options and extras', 'tours'),
('tours.booking_options', 'ar', 'الخيارات والإضافات', 'tours'),
('tours.booking_promo', 'en', 'Promo code', 'tours'),
('tours.booking_promo', 'ar', 'رمز الخصم', 'tours'),
('tours.booking_promo_ph', 'en', 'e.g. WEROAD10', 'tours'),
('tours.booking_promo_ph', 'ar', 'مثال: WEROAD10', 'tours'),
('tours.booking_apply', 'en', 'Apply', 'tours'),
('tours.booking_apply', 'ar', 'تطبيق', 'tours'),
('tours.booking_continue', 'en', 'Continue', 'tours'),
('tours.booking_continue', 'ar', 'متابعة', 'tours'),
('tours.booking_your_trip', 'en', 'Your trip', 'tours'),
('tours.booking_your_trip', 'ar', 'جولتك', 'tours'),
('tours.booking_from', 'en', 'From', 'tours'),
('tours.booking_from', 'ar', 'من', 'tours'),
('tours.booking_to', 'en', 'To', 'tours'),
('tours.booking_to', 'ar', 'إلى', 'tours'),
('tours.booking_available', 'en', 'Available', 'tours'),
('tours.booking_available', 'ar', 'متاح', 'tours'),
('tours.booking_trip_price', 'en', 'Trip price', 'tours'),
('tours.booking_trip_price', 'ar', 'سعر الجولة', 'tours'),
('tours.booking_flex_line', 'en', 'Flexible cancellation', 'tours'),
('tours.booking_flex_line', 'ar', 'إلغاء مرن', 'tours'),
('tours.booking_room_line', 'en', 'Private room', 'tours'),
('tours.booking_room_line', 'ar', 'غرفة خاصة', 'tours'),
('tours.booking_discount', 'en', 'Discount', 'tours'),
('tours.booking_discount', 'ar', 'الخصم', 'tours'),
('tours.booking_total', 'en', 'Total', 'tours'),
('tours.booking_total', 'ar', 'الإجمالي', 'tours'),
('tours.booking_installments', 'en', 'Interest-free installments', 'tours'),
('tours.booking_installments', 'ar', 'أقساط بدون فوائد', 'tours'),
('tours.booking_over_travellers', 'en', 'Over <strong>{count}</strong> GAIA travellers have already done this trip', 'tours'),
('tours.booking_over_travellers', 'ar', 'أكثر من <strong>{count}</strong> مسافر مع غايا قاموا بهذه الجولة', 'tours'),
('tours.booking_need_help', 'en', 'Do you need help?', 'tours'),
('tours.booking_need_help', 'ar', 'هل تحتاج مساعدة؟', 'tours'),
('tours.booking_ask', 'en', 'Ask an expert', 'tours'),
('tours.booking_ask', 'ar', 'اسأل خبيرًا', 'tours'),
('home.partners_title', 'en', 'Our Partners', 'home'),
('home.partners_title', 'ar', 'شركاؤنا', 'home'),
('home.partners_sub', 'en', 'Leading companies trust us with their most valuable asset: their clients', 'home'),
('home.partners_sub', 'ar', 'شركات رائدة تثق بنا في أغلى ما تملك: عملاؤها', 'home'),
('home.faq_title', 'en', 'Frequently Asked Questions', 'home'),
('home.faq_title', 'ar', 'الأسئلة الشائعة', 'home'),
('home.read_more', 'en', 'Read more', 'home'),
('home.read_more', 'ar', 'اقرأ المزيد', 'home');

-- ------------------------------------------------------------
-- 13) Seed media entries
-- ------------------------------------------------------------
INSERT IGNORE INTO media (`key`, file_path, alt_text, title, sort_order, is_cover, is_active) VALUES
('logo', 'assets/images/image.png', 'GAIA Tours & Travel logo', 'GAIA Logo', 1, 1, 1),
('logo_mark', 'assets/images/logo-mark.png', 'GAIA mark', 'GAIA Mark', 2, 0, 1),
('hero_home', 'https://images.pexels.com/photos/8872883/pexels-photo-8872883.jpeg?auto=compress&cs=tinysrgb&w=1920', 'Airport transfer hero', 'Home Hero', 1, 1, 1),
('hero_night', 'https://images.unsplash.com/photo-1502920917128-1aa500764cbd?w=1920&q=80', 'GAIA Night hero', 'Night Hero', 1, 1, 1),
('hero_tours', 'https://images.unsplash.com/photo-1548786811-dd6e453ccca7?w=1920&q=80', 'Tours hero', 'Tours Hero', 1, 1, 1);

-- ------------------------------------------------------------
-- 14) Seed pages (About, Contact)
-- ------------------------------------------------------------
INSERT IGNORE INTO pages (slug, title, subtitle, content, image_url, lang, sort_order, is_active) VALUES
('about', 'About GAIA', 'One company. One platform.', 'GAIA Tours & GAIA Night — group tours, hotels, private transfers, night events and VIP experiences, all under one GAIA brand.', NULL, 'en', 1, 1),
('about', 'عن غايا', 'شركة واحدة. منصة واحدة.', 'غايا جولات وغايا نايت — جولات جماعية وفنادق ونقل خاص وفعاليات ليلية وتجارب VIP، كل ذلك تحت علامة غايا.', NULL, 'ar', 1, 1),
('contact', 'Contact', 'We''re here to help.', 'Reach out to our team for bookings, questions or support.', NULL, 'en', 2, 1),
('contact', 'اتصل بنا', 'نحن هنا للمساعدة.', 'تواصل مع فريقنا للحجوزات أو الأسئلة أو الدعم.', NULL, 'ar', 2, 1);

-- ------------------------------------------------------------
-- 15) Seed tour categories
-- ------------------------------------------------------------
INSERT IGNORE INTO tour_categories (name, slug, description, image_url, sort_order, is_active) VALUES
('Middle East', 'middle-east', 'Jordan, Egypt and beyond', 'https://images.unsplash.com/photo-1548786811-dd6e453ccca7?w=500&q=80', 1, 1),
('Asia', 'asia', 'Bali, Vietnam, Cambodia', 'https://images.unsplash.com/photo-1533105079780-92b9be482077?w=500&q=80', 2, 1),
('Africa', 'africa', 'Morocco and North Africa', 'https://images.unsplash.com/photo-1533587851505-d119e13fa0d7?w=500&q=80', 3, 1),
('Europe', 'europe', 'Iceland and European adventures', 'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=500&q=80', 4, 1),
('Latin America', 'latin-america', 'Peru, Machu Picchu and more', 'https://images.unsplash.com/photo-1518709268805-4e9042af2176?w=500&q=80', 5, 1);

-- ------------------------------------------------------------
-- 16) Seed tour destinations
-- ------------------------------------------------------------
INSERT IGNORE INTO tour_destinations (name, slug, country, description, image_url, sort_order, is_active) VALUES
('Jordan', 'jordan', 'Jordan', 'Petra, Wadi Rum, the Dead Sea and Amman.', 'https://images.unsplash.com/photo-1548786811-dd6e453ccca7?w=700&q=80', 1, 1),
('Bali', 'bali', 'Indonesia', 'Beaches, rice terraces and temples.', 'https://images.unsplash.com/photo-1533105079780-92b9be482077?w=700&q=80', 2, 1),
('Morocco', 'morocco', 'Morocco', 'Marrakech, Sahara and the coast.', 'https://images.unsplash.com/photo-1533587851505-d119e13fa0d7?w=700&q=80', 3, 1),
('Iceland', 'iceland', 'Iceland', 'Waterfalls, glaciers and black beaches.', 'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=700&q=80', 4, 1),
('Vietnam', 'vietnam', 'Vietnam', 'Hanoi, Ha Long Bay and more.', 'https://images.unsplash.com/photo-1528181304800-259b08848526?w=700&q=80', 5, 1),
('Peru', 'peru', 'Peru', 'Lima, Cusco and Machu Picchu.', 'https://images.unsplash.com/photo-1518709268805-4e9042af2176?w=700&q=80', 6, 1);

-- ------------------------------------------------------------
-- 17) Seed transfer locations
-- ------------------------------------------------------------
INSERT IGNORE INTO transfers_locations (name, slug, type, country, is_active, sort_order) VALUES
('Milan Bergamo Airport', 'milan-bergamo-airport', 'airport', 'Italy', 1, 1),
('Milan', 'milan', 'city', 'Italy', 1, 2),
('Milan City Centre', 'milan-city-centre', 'city', 'Italy', 1, 3),
('Milan Airport Malpensa', 'milan-airport-malpensa', 'airport', 'Italy', 1, 4),
('Bergamo Airport', 'bergamo-airport', 'airport', 'Italy', 1, 5),
('Rome Airport Fiumicino', 'rome-airport-fiumicino', 'airport', 'Italy', 1, 6),
('Rome', 'rome', 'city', 'Italy', 1, 7),
('Naples International Airport', 'naples-international-airport', 'airport', 'Italy', 1, 8),
('Amalfi', 'amalfi', 'city', 'Italy', 1, 9);

-- ------------------------------------------------------------
-- 18) Seed transfer services
-- ------------------------------------------------------------
INSERT IGNORE INTO transfer_services (name, description, icon, price, unit, is_active, sort_order) VALUES
('Child Seats', 'Seat 9-18 kg · Booster 15-36 kg · Infant seat up to 9 kg', 'fa-solid fa-baby-carriage', 10.00, 'per_seat', 1, 1),
('Extra hour of Waiting', 'The driver waits for you at the airport 2.5h', 'fa-regular fa-clock', 0.00, 'flat', 1, 2),
('Box for Ski equipment', 'Secure and convenient storage', 'fa-solid fa-person-skiing', 0.00, 'flat', 1, 3);

-- ------------------------------------------------------------
-- 19) Seed booking options
-- ------------------------------------------------------------
INSERT IGNORE INTO booking_options (`key`, name, description, price, is_active, sort_order) VALUES
('return_trip', 'Return trip', 'Return trip surcharge', 50.00, 1, 1),
('child_seat', 'Child seats', 'Seat 9-18 kg · Booster 15-36 kg · Infant seat up to 9 kg', 10.00, 1, 2),
('water_bottle', 'Drinking water', 'A bottle of still water (0,5l)', 3.00, 1, 3),
('pet_carrier', 'Pet carrier', 'Kiwitaxi is pet-friendly. Your pet must be kept in a carrier', 15.00, 1, 4),
('women_driver', 'Women Driving Women', 'We''ll prioritize a woman driver for your trip', 20.00, 1, 5),
('flexible_cancellation', 'Flexible Cancellation', 'Cover your trip up to 1 day before departure.', 99.00, 1, 6),
('private_room', 'Private Room', 'A private room just for you or your travel party.', 247.00, 1, 7);
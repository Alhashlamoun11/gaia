-- ============================================================
-- GAIA TOURS & TRAVEL — Dynamic Data Migration
-- ------------------------------------------------------------
-- Adds the CMS-ready tables that let the ENTIRE public site be
-- driven from MySQL. All seed data below mirrors the current
-- hardcoded content so the visual design is 100% unchanged.
--
-- Safe to run repeatedly: all tables use IF NOT EXISTS.
-- ============================================================

USE gaia_tours;

-- ------------------------------------------------------------
-- settings: global site config (company, contact, social, SEO)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(100) NOT NULL UNIQUE,
  `value` TEXT NULL,
  `type` ENUM('text','textarea','image','url','boolean') NOT NULL DEFAULT 'text',
  `group` VARCHAR(50) NOT NULL DEFAULT 'general',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- languages: supported site languages
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS languages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(10) NOT NULL UNIQUE,
  name VARCHAR(50) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- translations: translatable strings (EN/AR)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS translations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(150) NOT NULL,
  lang VARCHAR(10) NOT NULL DEFAULT 'en',
  `value` TEXT NULL,
  `group` VARCHAR(50) NOT NULL DEFAULT 'general',
  UNIQUE KEY uq_transl_key_lang (`key`, lang)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- menu_items: navigation (header + footer) from DB
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS menu_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  menu ENUM('header','footer') NOT NULL DEFAULT 'header',
  nav_key VARCHAR(50) NULL,                -- machine key for active nav (home/tours/transfers/night)
  parent_id INT UNSIGNED NULL DEFAULT 0,
  label VARCHAR(150) NOT NULL,
  url VARCHAR(300) NOT NULL DEFAULT '#',
  sort_order INT NOT NULL DEFAULT 0,
  is_premium TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_menu_items_menu (menu, sort_order)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- seo_meta: per-page SEO metadata
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS seo_meta (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page VARCHAR(100) NOT NULL UNIQUE,
  lang VARCHAR(10) NOT NULL DEFAULT 'en',
  title VARCHAR(255) NULL,
  meta_description VARCHAR(500) NULL,
  keywords VARCHAR(500) NULL,
  og_image VARCHAR(500) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- banners: hero slides / full-width banners
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS banners (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page VARCHAR(50) NOT NULL DEFAULT 'home',
  title VARCHAR(255) NULL,
  subtitle VARCHAR(500) NULL,
  image_url VARCHAR(500) NULL,
  overlay VARCHAR(20) NOT NULL DEFAULT 'dark',
  stats_json TEXT NULL,            -- e.g. [{"value":"1 400 000+","label":"Completed rides"}]
  cta_label VARCHAR(100) NULL,
  cta_url VARCHAR(300) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- highlights: feature cards (Beyond Expectations, Why, Advantages)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS highlights (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section VARCHAR(50) NOT NULL DEFAULT 'expect',   -- expect | why | advantages
  icon VARCHAR(100) NULL,
  title VARCHAR(255) NULL,
  description TEXT NULL,
  image_url VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- events: upcoming events (home + GAIA Night)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  image_url VARCHAR(500) NULL,
  date_label VARCHAR(100) NULL,
  icon VARCHAR(100) NULL,
  location VARCHAR(150) NULL,
  category VARCHAR(50) NOT NULL DEFAULT 'home',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- night_offerings: GAIA Night services, packages, stats, cards
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS night_offerings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type ENUM('service','package','stat','event') NOT NULL DEFAULT 'service',
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  icon VARCHAR(100) NULL,
  image_url VARCHAR(500) NULL,
  badge VARCHAR(50) NULL,
  price_label VARCHAR(50) NULL,
  price_value VARCHAR(50) NULL,
  features_json TEXT NULL,          -- newline or comma separated features
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- trust_scores: review-box scores (Tripadvisor, Google, etc.)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS trust_scores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(50) NOT NULL,
  score VARCHAR(20) NOT NULL,
  count_label VARCHAR(100) NULL,
  icon VARCHAR(100) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- awards: awards / badges shown in review box
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS awards (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  icon VARCHAR(100) NULL,
  label VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- social_links: social media links
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS social_links (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  platform VARCHAR(50) NOT NULL,
  url VARCHAR(300) NOT NULL,
  icon VARCHAR(100) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- destinations: destinations module
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS destinations (
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
-- hotels: hotels module
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS hotels (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL UNIQUE,
  destination_id INT UNSIGNED NULL,
  description TEXT NULL,
  star_rating TINYINT UNSIGNED NOT NULL DEFAULT 3,
  image_url VARCHAR(500) NULL,
  gallery_urls TEXT NULL,
  price_from DECIMAL(10,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hotel_rooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hotel_id INT UNSIGNED NOT NULL,
  name VARCHAR(200) NOT NULL,
  description TEXT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  image_url VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_hotel_room_hotel FOREIGN KEY (hotel_id) REFERENCES hotels (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hotel_facilities (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hotel_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  icon VARCHAR(100) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_hotel_facility_hotel FOREIGN KEY (hotel_id) REFERENCES hotels (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- transfer_extras: add-on prices for transfers (replaces config)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transfer_extras (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  description VARCHAR(500) NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  unit VARCHAR(20) NOT NULL DEFAULT 'flat',  -- flat | per_seat | per_item
  is_rate TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- tour_extras: add-on prices for tours (replaces weroad config)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tour_extras (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  description VARCHAR(500) NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- subscribers: newsletter signups
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subscribers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(200) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

-- ---- languages ----
INSERT IGNORE INTO languages (code, name, is_default, is_active, sort_order) VALUES
('en', 'English', 1, 1, 0),
('ar', 'العربية', 0, 1, 1);

-- ---- settings (company / contact / social / misc) ----
INSERT IGNORE INTO settings (`key`, `value`, `type`, `group`, is_active) VALUES
('company_name', 'GAIA TOURS & TRAVEL', 'text', 'company', 1),
('company_short', 'GAIA', 'text', 'company', 1),
('company_logo_mark', 'G', 'text', 'company', 1),
('company_tagline', 'TOURS &amp; TRAVEL', 'text', 'company', 1),
('company_about', 'One company. One platform. GAIA Tours &amp; GAIA Night — group tours, hotels, private transfers, night events and VIP experiences, all under one GAIA brand.', 'textarea', 'company', 1),
('contact_email', 'support@gaiatours.com', 'text', 'contact', 1),
('contact_whatsapp', '962790123456', 'text', 'contact', 1),
('contact_whatsapp_display', 'WhatsApp 24/7', 'text', 'contact', 1),
('contact_address', 'Amman · Milan · Paris', 'text', 'contact', 1),
('contact_phone', '+962 7 9012 3456', 'text', 'contact', 1),
('contact_hours', '24/7 Support', 'text', 'contact', 1),
('currency', 'USD', 'text', 'general', 1),
('currency_symbol', '$', 'text', 'general', 1),
('return_trip_fee', '50.00', 'text', 'pricing', 1),
('newsletter_title', 'Join our Community', 'text', 'newsletter', 1),
('newsletter_text', 'Exclusive offers and insider travel tips', 'text', 'newsletter', 1),
('newsletter_button', 'Subscribe', 'text', 'newsletter', 1),
('newsletter_consent', 'By clicking Subscribe you consent to the processing of your personal data and agree to the Privacy Policy.', 'textarea', 'newsletter', 1);

-- ---- menu items (header) ----
INSERT IGNORE INTO menu_items (id, menu, nav_key, label, url, sort_order, is_premium, is_active) VALUES
(1,  'header', 'home',        'Home',         'index.php',         1, 0, 1),
(2,  'header', 'tours',       'Tours',        'weroad/search.php', 2, 0, 1),
(3,  'header', 'destinations','Destinations', 'weroad/search.php', 3, 0, 1),
(4,  'header', 'hotels',      'Hotels',       'index.php#hotels',  4, 0, 1),
(5,  'header', 'transfers',   'Transfers',    'index.php#transfers', 5, 0, 1),
(6,  'header', 'night',       'GAIA Night',   'gaia-night.php',    6, 1, 1),
(7,  'header', 'events',      'Events',       'gaia-night.php#events', 7, 0, 1),
(8,  'header', 'reviews',     'Reviews',      'index.php#reviews', 8, 0, 1),
(9,  'header', 'contact',     'Contact',      'index.php#contact', 9, 0, 1);

-- ---- menu items (footer) — parents + children ----
INSERT IGNORE INTO menu_items (id, menu, parent_id, label, url, sort_order, is_active) VALUES
-- Parent columns
(10, 'footer', 0, 'GAIA',        '#',                           1, 1),
(11, 'footer', 0, 'GAIA Tours',  '#',                           2, 1),
(12, 'footer', 0, 'GAIA Night',  '#',                           3, 1),
(13, 'footer', 0, 'Transfers',   '#',                           4, 1),
-- GAIA children
(14, 'footer', 10, 'Home',       'index.php',                   1, 1),
(15, 'footer', 10, 'About GAIA', 'index.php',                   2, 1),
(16, 'footer', 10, 'Reviews',    'index.php#reviews',           3, 1),
(17, 'footer', 10, 'Contact',    'index.php#contact',           4, 1),
-- GAIA Tours children
(18, 'footer', 11, 'All Tours',         'weroad/search.php',    1, 1),
(19, 'footer', 11, 'Destinations',      'weroad/search.php',    2, 1),
(20, 'footer', 11, 'Hotels',            'index.php#hotels',     3, 1),
(21, 'footer', 11, 'Standard Transfers','index.php#transfers',  4, 1),
(22, 'footer', 11, 'Family Travel',     'weroad/search.php',    5, 1),
(23, 'footer', 11, 'Group Travel',      'weroad/search.php',    6, 1),
-- GAIA Night children
(24, 'footer', 12, 'VIP Transfers',      'gaia-night.php#vip',      1, 1),
(25, 'footer', 12, 'Luxury Experiences', 'gaia-night.php#luxury',   2, 1),
(26, 'footer', 12, 'Night Events',       'gaia-night.php#events',   3, 1),
(27, 'footer', 12, 'Private Tours',      'gaia-night.php#private',  4, 1),
(28, 'footer', 12, 'Premium Packages',   'gaia-night.php#packages', 5, 1),
-- Transfers children
(29, 'footer', 13, 'Airport Transfers', 'index.php#transfers',  1, 1),
(30, 'footer', 13, 'Find a Route',      'index.php',            2, 1),
(31, 'footer', 13, 'Checkout',          'checkout.php',         3, 1);

-- ---- seo_meta ----
INSERT IGNORE INTO seo_meta (page, lang, title, meta_description, keywords, og_image, is_active) VALUES
('home', 'en', 'GAIA TOURS &amp; TRAVEL — Airport Transfers &amp; Car Rentals', 'Airport transfers, car rentals, tours and premium experiences across Jordan and beyond. One trusted GAIA platform.', 'gaia, tours, transfers, car rental, jordan', NULL, 1),
('transfers', 'en', 'GAIA — Airport Transfers &amp; Car Rentals', 'Private airport transfers and car rentals with transparent pricing.', 'gaia, transfers, car rental', NULL, 1),
('night', 'en', 'GAIA Night — VIP Transfers, Luxury Experiences &amp; Premium Events', 'GAIA Night is the premium service line of GAIA — VIP transfers, luxury experiences, night events, private tours and premium packages in Jordan.', 'gaia night, vip, luxury, jordan, events', NULL, 1),
('tours', 'en', 'GAIA Tours — Discover Jordan', 'Small group tours, family travel and private experiences across Jordan.', 'gaia tours, jordan, travel, group tours', NULL, 1),
('tours_search', 'en', 'Jordan trips — GAIA Tours', 'Find and book Jordan group tours.', 'gaia tours, jordan trips', NULL, 1),
('checkout', 'en', 'Checkout — GAIA TOURS &amp; TRAVEL', 'Complete your booking securely.', 'gaia, checkout, booking', NULL, 1);

-- ---- banners (hero blocks) ----
INSERT IGNORE INTO banners (page, title, subtitle, image_url, overlay, stats_json, cta_label, cta_url, sort_order, is_active) VALUES
('home', 'Airport transfers.<br>Simpler than ever.', NULL, 'https://images.pexels.com/photos/8872883/pexels-photo-8872883.jpeg?auto=compress&cs=tinysrgb&w=1920', 'dark', '[{"value":"1 400 000+","label":"Completed rides"},{"value":"100+","label":"Countries served"}]', NULL, NULL, 1, 1),
('night', 'Jordan''s refined side, <em>after dark.</em>', 'VIP transfers, luxury experiences, night events, private tours and premium packages — delivered by the same trusted GAIA team that runs your day journeys.', 'https://images.unsplash.com/photo-1502920917128-1aa500764cbd?w=1920&q=80', 'dark', '[{"value":"24/7","label":"Concierge"},{"value":"100%","label":"Private &amp; Discreet"},{"value":"50+","label":"Curated Experiences"}]', 'Book an Experience', 'index.php', 1, 1),
('tours', 'Discover Jordan.<br>Explore with GAIA Tours.', 'Small group tours, family travel and private experiences across Petra, Wadi Rum, the Dead Sea and Amman — one trusted GAIA platform.', NULL, 'dark', NULL, NULL, NULL, 1, 1);

-- ---- highlights (Beyond Expectations / Why / Advantages) ----
INSERT IGNORE INTO highlights (section, icon, title, description, image_url, sort_order, is_active) VALUES
('expect', 'fa-solid fa-check', 'Easy Booking', 'Book your ride in just a few clicks! We''ll handle the rest to ensure a flawless travel experience', 'https://images.pexels.com/photos/8949330/pexels-photo-8949330.jpeg?auto=compress&cs=tinysrgb&w=800', 1, 1),
('expect', 'fa-solid fa-location-dot', 'Seamless Meet &amp; Greet', 'Meet your driver effortlessly. We''ll guide you from the moment you land to make it simple and stress-free', 'https://images.pexels.com/photos/12406341/pexels-photo-12406341.jpeg?auto=compress&cs=tinysrgb&w=800', 2, 1),
('expect', 'fa-solid fa-location-dot', 'Relaxed and Safe Ride', 'Sit back, relax, and enjoy the view from the comfort of your ride, leaving the hustle behind', 'https://images.pexels.com/photos/16232759/pexels-photo-16232759.jpeg?auto=compress&cs=tinysrgb&w=800', 3, 1),
('why', 'fa-solid fa-circle-check', 'Book with confidence', 'Free cancellation up to 30 days before', NULL, 1, 1),
('why', 'fa-solid fa-user', 'Small group sizes', 'Max 20 travellers, always with a leader', NULL, 2, 1),
('why', 'fa-solid fa-star', 'Trips that just work', 'Logistics sorted, so you can explore', NULL, 3, 1),
('advantages', 'fa-solid fa-rotate', 'Transfer is booked in advance', NULL, NULL, 1, 1),
('advantages', 'fa-solid fa-baby-carriage', 'The car can be equipped with a child seat', NULL, NULL, 2, 1),
('advantages', 'fa-solid fa-suitcase', 'Travelling with bulky items', NULL, NULL, 3, 1),
('advantages', 'fa-solid fa-car', 'A car of a chosen class', NULL, NULL, 4, 1),
('advantages', 'fa-solid fa-face-smile', 'Enjoy peace of mind', NULL, NULL, 5, 1);

-- ---- events (home) ----
INSERT IGNORE INTO events (title, description, image_url, date_label, icon, location, category, sort_order, is_active) VALUES
('Wadi Rum Desert Gala', 'An open-air dinner under the stars with live music and Bedouin hospitality.', NULL, NULL, 'fa-solid fa-music', 'Wadi Rum', 'home', 1, 1),
('Amman Rooftop Tasting', 'Curated Jordanian menu with panoramic city views and a private sommelier.', NULL, NULL, 'fa-solid fa-utensils', 'Amman', 'home', 2, 1),
('Petra by Night', 'Experience the candle-lit Treasury on a guided evening walk.', NULL, NULL, 'fa-solid fa-camera', 'Petra', 'home', 3, 1);

-- ---- night_offerings (services) ----
INSERT IGNORE INTO night_offerings (type, title, description, icon, image_url, badge, price_label, price_value, features_json, is_featured, sort_order, is_active) VALUES
('service', 'VIP Transfers', 'Luxury cars, professional chauffeurs, meet &amp; greet at arrivals and refreshments on board.', 'fa-solid fa-car-side', NULL, NULL, NULL, NULL, NULL, 0, 1, 1),
('service', 'Luxury Experiences', 'Private dining, sunset dune dinners, hot-air balloon sunrise and bespoke Jordanian nights.', 'fa-solid fa-champagne-glasses', NULL, NULL, NULL, NULL, NULL, 0, 2, 1),
('service', 'Night Events', 'Rooftop tastings, desert galas, cultural evenings and VIP seats at Amman''s best venues.', 'fa-solid fa-music', NULL, NULL, NULL, NULL, NULL, 0, 3, 1),
('service', 'Private Tours', 'Your own guide and vehicle for Petra, Wadi Rum, the Dead Sea and beyond — on your schedule.', 'fa-solid fa-user-shield', NULL, NULL, NULL, NULL, NULL, 0, 4, 1),
('service', 'Premium Packages', 'Combined stays, transfers and experiences bundled into one seamless, elevated itinerary.', 'fa-solid fa-gem', NULL, NULL, NULL, NULL, NULL, 0, 5, 1),
('service', 'Dedicated Concierge', 'A personal GAIA Night concierge for every booking — from the first car to the last toast.', 'fa-solid fa-headset', NULL, NULL, NULL, NULL, NULL, 0, 6, 1);

-- ---- night_offerings (premium cards on home) ----
INSERT IGNORE INTO night_offerings (type, title, description, icon, image_url, badge, price_label, price_value, features_json, is_featured, sort_order, is_active) VALUES
('card', 'VIP Transfers', 'Chauffeur-driven luxury cars with meet &amp; greet, refreshments and discretion.', NULL, 'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=600&q=80', 'VIP', 'From', '$120', NULL, 0, 1, 1),
('card', 'Luxury Experiences', 'Private dining, sunset dunes and curated Jordanian nights reserved for you.', NULL, 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=600&q=80', 'LUX', 'From', '$180', NULL, 0, 2, 1),
('card', 'Night Events', 'Exclusive access to galas, rooftop parties and cultural evenings in Amman.', NULL, 'https://images.unsplash.com/photo-1511578314322-379afb476865?w=600&q=80', 'NIGHT', 'From', '$90', NULL, 0, 3, 1),
('card', 'Private Tours', 'Your own guide and vehicle through Petra, Wadi Rum and the Dead Sea.', NULL, 'https://images.unsplash.com/photo-1519823551278-64ac92734fb1?w=600&q=80', 'PRIVATE', 'From', '$240', NULL, 0, 4, 1);

-- ---- night_offerings (packages) ----
INSERT IGNORE INTO night_offerings (type, title, description, icon, image_url, badge, price_label, price_value, features_json, is_featured, sort_order, is_active) VALUES
('package', 'Amman After Dark', 'The city lights up after sunset. Discover it in style.', NULL, NULL, NULL, '', '$240', "VIP airport transfer\nRooftop tasting for two\nPrivate driver all evening", 0, 1, 1),
('package', 'Desert Night Deluxe', 'The full Wadi Rum evening — exclusive and breathtaking.', NULL, NULL, 'Best Seller', '', '$690', "Luxury 4x4 desert transfer\nPrivate Bedouin dinner under the stars\nStargazing with an astronomer\nOvernight bubble suite", 1, 2, 1),
('package', 'Petra by Night', 'The Treasury lit by candles — a once-in-a-lifetime evening.', NULL, NULL, NULL, '', '$320', "Private guided evening visit\nVIP transfer from your hotel\nCandle-lit dinner nearby", 0, 3, 1);

-- ---- night_offerings (night events) ----
INSERT IGNORE INTO night_offerings (type, title, description, icon, image_url, badge, price_label, price_value, features_json, is_featured, sort_order, is_active) VALUES
('event', 'Desert Gala', 'An open-air, black-tie dinner under a canopy of stars.', NULL, 'https://images.unsplash.com/photo-1511578314322-379afb476865?w=700&q=80', 'MAR 2025 · WADI RUM', NULL, NULL, NULL, 0, 1, 1),
('event', 'Rooftop Tasting', 'Five courses, two sommeliers, one unforgettable view.', NULL, 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=700&q=80', 'APR 2025 · AMMAN', NULL, NULL, NULL, 0, 2, 1),
('event', 'Petra Candle Walk', 'Follow 1,500 candles to the Treasury with a private guide.', NULL, 'https://images.unsplash.com/photo-1519823551278-64ac92734fb1?w=700&q=80', 'MAY 2025 · PETRA', NULL, NULL, NULL, 0, 3, 1);

-- ---- trust_scores ----
INSERT IGNORE INTO trust_scores (label, score, count_label, icon, sort_order, is_active) VALUES
('Tripadvisor', '4.5', 'from 570+ reviews', 'fa-brands fa-tripadvisor', 1, 1),
('Google', '4.6', 'from 1,590+ reviews', 'fa-solid fa-circle-check', 2, 1);

-- ---- awards ----
INSERT IGNORE INTO awards (icon, label, sort_order, is_active) VALUES
('fa-solid fa-trophy', 'Tripadvisor Travellers'' Choice Awards 2025', 1, 1),
('fa-solid fa-award', 'The Winner of the International Travel Awards 2025: Best Taxi Service', 2, 1);

-- ---- social_links ----
INSERT IGNORE INTO social_links (platform, url, icon, sort_order, is_active) VALUES
('Facebook', '#', 'f', 1, 1),
('Instagram', '#', '◎', 2, 1),
('WhatsApp', 'https://wa.me/962790123456', 'w', 3, 1),
('TikTok', '#', '♪', 4, 1);

-- ---- destinations ----
INSERT IGNORE INTO destinations (name, slug, country, description, image_url, sort_order, is_active) VALUES
('Jordan', 'jordan', 'Jordan', 'Petra, Wadi Rum, the Dead Sea and Amman.', 'https://images.unsplash.com/photo-1548786811-dd6e453ccca7?w=700&q=80', 1, 1),
('Bali', 'bali', 'Indonesia', 'Beaches, rice terraces and temples.', 'https://images.unsplash.com/photo-1533105079780-92b9be482077?w=700&q=80', 2, 1),
('Morocco', 'morocco', 'Morocco', 'Marrakech, Sahara and the coast.', 'https://images.unsplash.com/photo-1533587851505-d119e13fa0d7?w=700&q=80', 3, 1),
('Iceland', 'iceland', 'Iceland', 'Waterfalls, glaciers and black beaches.', 'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=700&q=80', 4, 1);

-- ---- hotels ----
INSERT IGNORE INTO hotels (name, slug, destination_id, description, star_rating, image_url, gallery_urls, price_from, is_active) VALUES
('The St. Regis Amman', 'st-regis-amman', 1, 'A landmark of luxury in the heart of Amman.', 5, 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=700&q=80', NULL, 289.00, 1),
('Wadi Rum Bubble Camp', 'wadi-rum-bubble-camp', 1, 'Sleep under the stars in the desert.', 4, 'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=700&q=80', NULL, 145.00, 1);

-- ---- transfer_extras (replaces config constants) ----
INSERT IGNORE INTO transfer_extras (`key`, name, description, price, unit, is_rate, is_active, sort_order) VALUES
('return_trip_fee', 'Return trip', 'Return trip surcharge', 50.00, 'flat', 0, 1, 1),
('child_seat', 'Child seats', 'Seat 9-18 kg · Booster 15-36 kg · Infant seat up to 9 kg', 10.00, 'per_seat', 0, 1, 2),
('water_bottle', 'Drinking water', 'A bottle of still water (0,5l)', 3.00, 'per_item', 0, 1, 3),
('pet_carrier', 'Pet carrier', 'Kiwitaxi is pet-friendly. Your pet must be kept in a carrier', 15.00, 'flat', 0, 1, 4),
('women_driver', 'Women Driving Women', 'We''ll prioritize a woman driver for your trip', 20.00, 'flat', 0, 1, 5);

-- ---- tour_extras (replaces weroad config) ----
INSERT IGNORE INTO tour_extras (`key`, name, description, price, is_active, sort_order) VALUES
('flexible_cancellation', 'Flexible Cancellation', 'Cover your trip up to 1 day before departure.', 99.00, 1, 1),
('private_room', 'Private Room', 'A private room just for you or your travel party.', 247.00, 1, 2);

-- ---- translations (EN/AR) ----
INSERT IGNORE INTO translations (`key`, lang, `value`, `group`) VALUES
('nav.home', 'en', 'Home', 'nav'),
('nav.home', 'ar', 'الرئيسية', 'nav'),
('nav.tours', 'en', 'Tours', 'nav'),
('nav.tours', 'ar', 'الجولات', 'nav'),
('nav.destinations', 'en', 'Destinations', 'nav'),
('nav.destinations', 'ar', 'الوجهات', 'nav'),
('nav.hotels', 'en', 'Hotels', 'nav'),
('nav.hotels', 'ar', 'الفنادق', 'nav'),
('nav.transfers', 'en', 'Transfers', 'nav'),
('nav.transfers', 'ar', 'النقل', 'nav'),
('nav.night', 'en', 'GAIA Night', 'nav'),
('nav.night', 'ar', 'غايا نايت', 'nav'),
('nav.events', 'en', 'Events', 'nav'),
('nav.events', 'ar', 'الفعاليات', 'nav'),
('nav.reviews', 'en', 'Reviews', 'nav'),
('nav.reviews', 'ar', 'التقييمات', 'nav'),
('nav.contact', 'en', 'Contact', 'nav'),
('nav.contact', 'ar', 'اتصل بنا', 'nav'),
('lang.en', 'en', 'EN', 'lang'),
('lang.en', 'ar', 'EN', 'lang'),
('section.contact', 'en', 'Contact', 'section'),
('section.contact', 'ar', 'اتصل بنا', 'section'),
('footer.newsletter_title', 'en', 'Join our community', 'footer'),
('footer.newsletter_title', 'ar', 'انضم إلى مجتمعنا', 'footer'),
('footer.newsletter_text', 'en', 'Exclusive offers, new tours and travel tips.', 'footer'),
('footer.newsletter_text', 'ar', 'عروض حصرية وجولات جديدة ونصائح سفر.', 'footer'),
('footer.newsletter_consent', 'en', 'By subscribing you agree to our Privacy Policy and Terms.', 'footer'),
('footer.newsletter_consent', 'ar', 'بالتسجيل أنت توافق على سياسة الخصوصية والشروط.', 'footer'),
('footer.newsletter_subscribe', 'en', 'Subscribe', 'footer'),
('footer.newsletter_subscribe', 'ar', 'اشتراك', 'footer'),
('footer.copyright', 'en', '© {year} GAIA. Sub-brands: GAIA Tours &amp; GAIA Night. All rights reserved.', 'footer'),
('footer.copyright', 'ar', '© {year} غايا. العلامات الفرعية: غايا جولات وغايا نايت. جميع الحقوق محفوظة.', 'footer'),
('footer.terms', 'en', 'Terms', 'footer'),
('footer.terms', 'ar', 'الشروط', 'footer'),
('footer.privacy', 'en', 'Privacy', 'footer'),
('footer.privacy', 'ar', 'الخصوصية', 'footer'),
('footer.cookies', 'en', 'Cookies', 'footer'),
('footer.cookies', 'ar', 'ملفات الارتباط', 'footer'),
('header.account', 'en', 'Account', 'header'),
('header.account', 'ar', 'الحساب', 'header'),
('header.start_booking', 'en', 'Start Booking', 'header'),
('header.start_booking', 'ar', 'ابدأ الحجز', 'header'),
('header.whatsapp', 'en', 'WhatsApp', 'header'),
('header.whatsapp', 'ar', 'واتساب', 'header'),
('home.hero_search', 'en', 'Search', 'home'),
('home.hero_search', 'ar', 'بحث', 'home'),
('home.section.expect_title', 'en', 'Transportation Beyond Expectations', 'home'),
('home.section.expect_title', 'ar', 'نقل يتجاوز التوقعات', 'home'),
('home.section.expect_sub', 'en', 'Relax and enjoy the ride, knowing that every detail is managed for your peace of mind', 'home'),
('home.section.expect_sub', 'ar', 'استرخِ واستمتع بالرحلة، مع العلم أن كل التفاصيل تُدار من أجل راحة بالك', 'home'),
('home.section.car_title', 'en', 'Car Classes', 'home'),
('home.section.car_title', 'ar', 'فئات السيارات', 'home'),
('home.section.car_sub', 'en', 'Solo or group transfer? Choose your perfect fit', 'home'),
('home.section.car_sub', 'ar', 'نقل فردي أو جماعي؟ اختر ما يناسبك', 'home'),
('home.section.services_title', 'en', 'Additional Services', 'home'),
('home.section.services_title', 'ar', 'خدمات إضافية', 'home'),
('home.section.services_sub', 'en', 'Customise your ride to suit all your needs', 'home'),
('home.section.services_sub', 'ar', 'خصص رحلتك لتناسب احتياجاتك', 'home'),
('home.section.night_title', 'en', 'Premium Experiences After Dark', 'home'),
('home.section.night_title', 'ar', 'تجارب فاخرة بعد الغروب', 'home'),
('home.section.night_sub', 'en', 'VIP transfers, luxury experiences, night events and private tours — the refined side of the GAIA journey, crafted for those who want more.', 'home'),
('home.section.night_sub', 'ar', 'نقل VIP وتجارب فاخرة وفعاليات ليلية وجولات خاصة — الجانب الراقي من رحلة غايا، صُمم لمن يريد المزيد.', 'home'),
('home.section.night_discover', 'en', 'Discover GAIA Night', 'home'),
('home.section.night_discover', 'ar', 'اكتشف غايا نايت', 'home'),
('home.section.events_title', 'en', 'Upcoming Events', 'home'),
('home.section.events_title', 'ar', 'الفعاليات القادمة', 'home'),
('home.section.events_sub', 'en', 'From cultural evenings to desert galas — join a GAIA event and make memories.', 'home'),
('home.section.events_sub', 'ar', 'من الأمسيات الثقافية إلى حفلات الصحراء — انضم إلى فعالية غايا واصنع ذكريات.', 'home'),
('home.section.reviews_title', 'en', 'Customer Reviews', 'home'),
('home.section.reviews_title', 'ar', 'تقييمات العملاء', 'home'),
('home.section.reviews_sub', 'en', 'See why our customers delighted about their experiences. Discover how we make every trip truly special!', 'home'),
('home.section.reviews_sub', 'ar', 'اكتشف لماذا يسعد عملاؤنا بتجاربهم. شاهد كيف نجعل كل رحلة مميزة حقًا!', 'home'),
('home.section.partners_title', 'en', 'Our Partners', 'home'),
('home.section.partners_title', 'ar', 'شركاؤنا', 'home'),
('home.section.partners_sub', 'en', 'Leading companies trust us with their most valuable asset: their clients', 'home'),
('home.section.partners_sub', 'ar', 'شركات رائدة تثق بنا في أغلى ما تملك: عملاؤها', 'home'),
('home.section.faq_title', 'en', 'Frequently Asked Questions', 'home'),
('home.section.faq_title', 'ar', 'الأسئلة الشائعة', 'home'),
('home.section.advantages_title', 'en', 'Advantages', 'home'),
('home.section.advantages_title', 'ar', 'المزايا', 'home'),
('home.section.advantages_sub', 'en', 'Relax and enjoy the ride, knowing that every detail is managed for your peace of mind', 'home'),
('home.section.advantages_sub', 'ar', 'استرخِ واستمتع بالرحلة، مع العلم أن كل التفاصيل تُدار من أجل راحة بالك', 'home'),
('home.cookie', 'en', 'We use cookies to personalize your experience. Read our Cookie Policy', 'home'),
('home.cookie', 'ar', 'نستخدم ملفات الارتباط لتخصيص تجربتك. اقرأ سياسة ملفات الارتباط', 'home'),
('home.cookie_ok', 'en', 'Ok', 'home'),
('home.cookie_ok', 'ar', 'حسناً', 'home'),
('home.read_more', 'en', 'Read more', 'home'),
('home.read_more', 'ar', 'اقرأ المزيد', 'home'),
('home.newsletter_title', 'en', 'Join our Community', 'home'),
('home.newsletter_title', 'ar', 'انضم إلى مجتمعنا', 'home'),
('home.newsletter_text', 'en', 'Exclusive offers and insider travel tips', 'home'),
('home.newsletter_text', 'ar', 'عروض حصرية ونصائح سفر داخلية', 'home'),
('home.newsletter_subscribe', 'en', 'Subscribe', 'home'),
('home.newsletter_subscribe', 'ar', 'اشتراك', 'home'),
('home.newsletter_consent', 'en', 'By clicking Subscribe you consent to the processing of your personal data and agree to the Privacy Policy', 'home'),
('home.newsletter_consent', 'ar', 'بالنقر على اشتراك فإنك توافق على معالجة بياناتك الشخصية وتوافق على سياسة الخصوصية', 'home'),
('cross.tours_title', 'en', 'Popular Tours at your destination', 'cross'),
('cross.tours_title', 'ar', 'جولات شائعة في وجهتك', 'cross'),
('cross.tours_text', 'en', 'Explore group tours, experiences and destinations.', 'cross'),
('cross.tours_text', 'ar', 'استكشف الجولات الجماعية والتجارب والوجهات.', 'cross'),
('cross.tours_btn', 'en', 'Explore Tours', 'cross'),
('cross.tours_btn', 'ar', 'استكشف الجولات', 'cross'),
('cross.transfers_title', 'en', 'Need an airport transfer?', 'cross'),
('cross.transfers_title', 'ar', 'تحتاج نقلًا من المطار؟', 'cross'),
('cross.transfers_text', 'en', 'Book a private transfer to your hotel or destination.', 'cross'),
('cross.transfers_text', 'ar', 'احجز نقلًا خاصًا إلى فندقك أو وجهتك.', 'cross'),
('cross.transfers_btn', 'en', 'Book Private Transfer', 'cross'),
('cross.transfers_btn', 'ar', 'احجز نقلًا خاصًا', 'cross'),
('cross.night_title', 'en', 'GAIA Night — Premium Experiences', 'cross'),
('cross.night_title', 'ar', 'غايا نايت — تجارب فاخرة', 'cross'),
('cross.night_text', 'en', 'VIP transfers, luxury experiences, night events and private tours.', 'cross'),
('cross.night_text', 'ar', 'نقل VIP وتجارب فاخرة وفعاليات ليلية وجولات خاصة.', 'cross'),
('cross.night_btn', 'en', 'Discover GAIA Night', 'cross'),
('cross.night_btn', 'ar', 'اكتشف غايا نايت', 'cross'),
('transfers.page_title_pattern', 'en', 'Taxi Transfer from {origin} to {destination}', 'transfers'),
('transfers.page_title_pattern', 'ar', 'نقل تاكسي من {origin} إلى {destination}', 'transfers'),
('transfers.best_choice', 'en', 'Best Choice', 'transfers'),
('transfers.best_choice', 'ar', 'الخيار الأفضل', 'transfers'),
('transfers.feature_1', 'en', 'One booking', 'transfers'),
('transfers.feature_1', 'ar', 'حجز واحد', 'transfers'),
('transfers.feature_2', 'en', 'Easy coordination', 'transfers'),
('transfers.feature_2', 'ar', 'تنسيق سهل', 'transfers'),
('transfers.feature_3', 'en', 'Lower cost per passenger', 'transfers'),
('transfers.feature_3', 'ar', 'تكلفة أقل لكل راكب', 'transfers'),
('transfers.choose_class', 'en', 'Choose a Car class', 'transfers'),
('transfers.choose_class', 'ar', 'اختر فئة السيارة', 'transfers'),
('transfers.route_info', 'en', 'Route information', 'transfers'),
('transfers.route_info', 'ar', 'معلومات المسار', 'transfers'),
('transfers.label_route', 'en', 'Route', 'transfers'),
('transfers.label_route', 'ar', 'المسار', 'transfers'),
('transfers.label_distance', 'en', 'Distance', 'transfers'),
('transfers.label_distance', 'ar', 'المسافة', 'transfers'),
('transfers.label_travel_time', 'en', 'Travel Time', 'transfers'),
('transfers.label_travel_time', 'ar', 'زمن الرحلة', 'transfers'),
('transfers.label_price', 'en', 'Price', 'transfers'),
('transfers.label_price', 'ar', 'السعر', 'transfers'),
('transfers.from', 'en', 'From', 'transfers'),
('transfers.from', 'ar', 'من', 'transfers'),
('checkout.step_request', 'en', 'Request', 'checkout'),
('checkout.step_request', 'ar', 'طلب', 'checkout'),
('checkout.step_checkout', 'en', 'Checkout', 'checkout'),
('checkout.step_checkout', 'ar', 'الدفع', 'checkout'),
('checkout.step_payment', 'en', 'Payment', 'checkout'),
('checkout.step_payment', 'ar', 'الدفع', 'checkout'),
('checkout.title', 'en', 'Checkout', 'checkout'),
('checkout.title', 'ar', 'الدفع', 'checkout'),
('checkout.book_title', 'en', 'Step 1. Book', 'checkout'),
('checkout.book_title', 'ar', 'الخطوة 1. الحجز', 'checkout'),
('checkout.passengers_title', 'en', 'Step 2. Passengers', 'checkout'),
('checkout.passengers_title', 'ar', 'الخطوة 2. الركاب', 'checkout'),
('checkout.additionally_title', 'en', 'Step 3. Additionally', 'checkout'),
('checkout.additionally_title', 'ar', 'الخطوة 3. إضافات', 'checkout'),
('checkout.order_summary', 'en', 'Order summary', 'checkout'),
('checkout.order_summary', 'ar', 'ملخص الطلب', 'checkout'),
('checkout.total', 'en', 'Total', 'checkout'),
('checkout.total', 'ar', 'الإجمالي', 'checkout'),
('checkout.continue', 'en', 'Continue', 'checkout'),
('checkout.continue', 'ar', 'متابعة', 'checkout'),
('checkout.promo_code', 'en', 'Promo code', 'checkout'),
('checkout.promo_code', 'ar', 'رمز الخصم', 'checkout'),
('checkout.apply', 'en', 'Apply', 'checkout'),
('checkout.apply', 'ar', 'تطبيق', 'checkout'),
('checkout.consent', 'en', 'By clicking Continue, you consent to the processing of your personal data and agree to the Privacy Policy', 'checkout'),
('checkout.consent', 'ar', 'بالنقر على متابعة، فإنك توافق على معالجة بياناتك الشخصية وتوافق على سياسة الخصوصية', 'checkout'),
('checkout.return_trip', 'en', 'Return trip', 'checkout'),
('checkout.return_trip', 'ar', 'رحلة العودة', 'checkout'),
('checkout.child_seats', 'en', 'Child seats', 'checkout'),
('checkout.child_seats', 'ar', 'مقاعد أطفال', 'checkout'),
('checkout.water', 'en', 'Drinking water', 'checkout'),
('checkout.water', 'ar', 'مياه شرب', 'checkout'),
('checkout.pets', 'en', 'I am travelling with pets', 'checkout'),
('checkout.pets', 'ar', 'أسافر مع حيوانات أليفة', 'checkout'),
('checkout.women_driver', 'en', 'Women Driving Women', 'checkout'),
('checkout.women_driver', 'ar', 'سائقات للنساء', 'checkout'),
('checkout.base_fare', 'en', 'Base fare', 'checkout'),
('checkout.base_fare', 'ar', 'التكلفة الأساسية', 'checkout'),
('checkout.return_trip_line', 'en', 'Return trip', 'checkout'),
('checkout.return_trip_line', 'ar', 'رحلة العودة', 'checkout'),
('checkout.child_seats_line', 'en', 'Child seats', 'checkout'),
('checkout.child_seats_line', 'ar', 'مقاعد أطفال', 'checkout'),
('checkout.water_line', 'en', 'Water', 'checkout'),
('checkout.water_line', 'ar', 'مياه', 'checkout'),
('checkout.pet_line', 'en', 'Pet carrier', 'checkout'),
('checkout.pet_line', 'ar', 'حامل حيوانات', 'checkout'),
('checkout.women_line', 'en', 'Women driver', 'checkout'),
('checkout.women_line', 'ar', 'سائقة', 'checkout'),
('checkout.promo_discount', 'en', 'Promo discount', 'checkout'),
('checkout.promo_discount', 'ar', 'خصم الرمز', 'checkout'),
('night.hero_badge', 'en', 'GAIA Night', 'night'),
('night.hero_badge', 'ar', 'غايا نايت', 'night'),
('night.services_title', 'en', 'The GAIA Night Services', 'night'),
('night.services_title', 'ar', 'خدمات غايا نايت', 'night'),
('night.services_sub', 'en', 'One brand, one standard of care — elevated for the evening and for guests who expect more.', 'night'),
('night.services_sub', 'ar', 'علامة واحدة، ومعيار واحد من الرعاية — مرفوع للمساء وللضيوف الذين يتوقعون المزيد.', 'night'),
('night.packages_title', 'en', 'Premium Packages', 'night'),
('night.packages_title', 'ar', 'باقات فاخرة', 'night'),
('night.packages_sub', 'en', 'Hand-crafted GAIA Night itineraries that pair luxury transport with unforgettable evenings.', 'night'),
('night.packages_sub', 'ar', 'مسارات غايا نايت المصممة بعناية التي تجمع بين النقل الفاخر وأمسيات لا تُنسى.', 'night'),
('night.events_title', 'en', 'Signature Night Events', 'night'),
('night.events_title', 'ar', 'فعاليات ليلية مميزة', 'night'),
('night.events_sub', 'en', 'A rotating calendar of exclusive GAIA Night gatherings across Jordan.', 'night'),
('night.events_sub', 'ar', 'تقويم متجدد لتجمعات غايا نايت الحصرية في جميع أنحاء الأردن.', 'night'),
('night.book_experience', 'en', 'Book an Experience', 'night'),
('night.book_experience', 'ar', 'احجز تجربة', 'night'),
('night.view_packages', 'en', 'View Packages', 'night'),
('night.view_packages', 'ar', 'عرض الباقات', 'night'),
('night.book_package', 'en', 'Book a Premium Package', 'night'),
('night.book_package', 'ar', 'احجز باقة فاخرة', 'night'),
('night.package_per', 'en', '/ per person', 'night'),
('night.package_per', 'ar', '/ لكل شخص', 'night'),
('night.package_per_couple', 'en', '/ per couple', 'night'),
('night.package_per_couple', 'ar', '/ لكل زوجين', 'night'),
('night.cross_title', 'en', 'Prefer the golden hour?', 'night'),
('night.cross_title', 'ar', 'تفضل الساعة الذهبية؟', 'night'),
('night.cross_text', 'en', 'Explore GAIA Tours — group tours, destinations, hotels and standard transfers.', 'night'),
('night.cross_text', 'ar', 'استكشف غايا جولات — جولات جماعية ووجهات وفنادق ونقل قياسي.', 'night'),
('night.cross_btn', 'en', 'Explore GAIA Tours', 'night'),
('night.cross_btn', 'ar', 'استكشف غايا جولات', 'night');

-- ============================================================
-- WeRoad Tour Booking Platform — Database Schema
-- Plain MySQL DDL. Uses the SAME database as the KiwiTaxi
-- system (gaia_tours) but all tables are prefixed with `weroad_`
-- to avoid any name clashes.
--
-- This script creates all tables, indexes, and sample seed data
-- for the Jordan 360° trip and related content so the platform
-- works out-of-the-box.
-- ============================================================

-- The parent database is created by the root schema.sql. We only
-- make sure it exists and select it.
CREATE DATABASE IF NOT EXISTS gaia_tours
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE gaia_tours;

-- ------------------------------------------------------------
-- Table: weroad_trips
-- The core trip catalogue.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS weroad_trips (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(160) NOT NULL UNIQUE,
  tagline VARCHAR(255) NULL,
  description TEXT NULL,
  duration_days INT UNSIGNED NOT NULL DEFAULT 1,
  max_group_size INT UNSIGNED NOT NULL DEFAULT 20,
  age_range VARCHAR(50) NOT NULL DEFAULT '18–39',
  effort_level ENUM('easy','moderate','hard') NOT NULL DEFAULT 'moderate',
  comfort_level VARCHAR(150) NULL,
  accommodation VARCHAR(150) NULL,
  category VARCHAR(80) NULL,
  base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  rating DECIMAL(3,1) NOT NULL DEFAULT 0,
  reviews_count INT UNSIGNED NOT NULL DEFAULT 0,
  image_url VARCHAR(500) NULL,
  gallery_urls TEXT NULL,          -- comma-separated image URLs
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_weroad_trips_active (is_active),
  INDEX idx_weroad_trips_category (category),
  INDEX idx_weroad_trips_effort (effort_level)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: weroad_trip_itineraries
-- Day-by-day itinerary for each trip.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS weroad_trip_itineraries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trip_id INT UNSIGNED NOT NULL,
  day_number INT UNSIGNED NOT NULL DEFAULT 1,
  title VARCHAR(150) NOT NULL,
  description TEXT NULL,
  image_url VARCHAR(500) NULL,
  CONSTRAINT fk_weroad_itinerary_trip
    FOREIGN KEY (trip_id) REFERENCES weroad_trips (id) ON DELETE CASCADE,
  UNIQUE KEY uq_weroad_itinerary_day (trip_id, day_number),
  INDEX idx_weroad_itinerary_trip (trip_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: weroad_trip_inclusions
-- What's included / excluded on each trip.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS weroad_trip_inclusions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trip_id INT UNSIGNED NOT NULL,
  include_type ENUM('included','excluded') NOT NULL DEFAULT 'included',
  item_text VARCHAR(255) NOT NULL,
  CONSTRAINT fk_weroad_inclusion_trip
    FOREIGN KEY (trip_id) REFERENCES weroad_trips (id) ON DELETE CASCADE,
  INDEX idx_weroad_inclusion_trip (trip_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: weroad_trip_optional_activities
-- Optional extras bookable during the trip.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS weroad_trip_optional_activities (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trip_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_weroad_activity_trip
    FOREIGN KEY (trip_id) REFERENCES weroad_trips (id) ON DELETE CASCADE,
  INDEX idx_weroad_activity_trip (trip_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: weroad_trip_faqs
-- FAQ groups shown on the trip detail page.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS weroad_trip_faqs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trip_id INT UNSIGNED NOT NULL,
  faq_group VARCHAR(80) NOT NULL DEFAULT 'About this trip',
  question VARCHAR(255) NOT NULL,
  answer TEXT NOT NULL,
  CONSTRAINT fk_weroad_faq_trip
    FOREIGN KEY (trip_id) REFERENCES weroad_trips (id) ON DELETE CASCADE,
  INDEX idx_weroad_faq_trip (trip_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: weroad_departures
-- Concrete trip start dates with their own price and spots.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS weroad_departures (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trip_id INT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
  spots_total INT UNSIGNED NOT NULL DEFAULT 20,
  spots_left INT UNSIGNED NOT NULL DEFAULT 20,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_weroad_departure_trip
    FOREIGN KEY (trip_id) REFERENCES weroad_trips (id) ON DELETE CASCADE,
  INDEX idx_weroad_departure_trip (trip_id),
  INDEX idx_weroad_departure_date (start_date)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: weroad_reviews
-- Customer reviews (shown on homepage / trip detail).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS weroad_reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trip_id INT UNSIGNED NULL,
  author VARCHAR(100) NOT NULL,
  rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
  text TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_weroad_review_trip
    FOREIGN KEY (trip_id) REFERENCES weroad_trips (id) ON DELETE SET NULL,
  INDEX idx_weroad_review_trip (trip_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: weroad_promo_codes
-- Optional promo codes for the booking flow.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS weroad_promo_codes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  discount_type ENUM('percent','flat') NOT NULL DEFAULT 'percent',
  discount_value DECIMAL(10,2) NOT NULL DEFAULT 0,
  max_uses INT UNSIGNED NOT NULL DEFAULT 100,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  valid_from DATE NULL,
  valid_until DATE NULL,
  INDEX idx_weroad_promo_code (code),
  INDEX idx_weroad_promo_active (is_active)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: weroad_bookings
-- The main booking record with full price breakdown.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS weroad_bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_code VARCHAR(20) NOT NULL UNIQUE,
  trip_id INT UNSIGNED NULL,
  departure_id INT UNSIGNED NULL,
  promo_code_id INT UNSIGNED NULL,

  -- Customer / traveller details
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL,
  phone VARCHAR(50) NULL,

  -- Traveller composition
  travellers_female INT UNSIGNED NOT NULL DEFAULT 0,
  travellers_male INT UNSIGNED NOT NULL DEFAULT 0,
  mixed_room TINYINT(1) NOT NULL DEFAULT 0,

  -- Options / extras
  flexible_cancellation TINYINT(1) NOT NULL DEFAULT 0,
  private_room TINYINT(1) NOT NULL DEFAULT 0,
  comments TEXT NULL,

  -- Price breakdown (base price is the departure price)
  base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  flexible_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  private_room_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_price DECIMAL(10,2) NOT NULL DEFAULT 0,

  status ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_weroad_booking_trip
    FOREIGN KEY (trip_id) REFERENCES weroad_trips (id) ON DELETE SET NULL,
  CONSTRAINT fk_weroad_booking_departure
    FOREIGN KEY (departure_id) REFERENCES weroad_departures (id) ON DELETE SET NULL,
  CONSTRAINT fk_weroad_booking_promo
    FOREIGN KEY (promo_code_id) REFERENCES weroad_promo_codes (id) ON DELETE SET NULL,

  INDEX idx_weroad_booking_email (email),
  INDEX idx_weroad_booking_status (status)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: weroad_booking_extras
-- Line items for add-ons on a booking.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS weroad_booking_extras (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL,
  extra_name VARCHAR(100) NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_weroad_extra_booking
    FOREIGN KEY (booking_id) REFERENCES weroad_bookings (id) ON DELETE CASCADE,
  INDEX idx_weroad_extra_booking (booking_id)
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

-- ---- Trips ----
INSERT INTO weroad_trips
  (name, slug, tagline, description, duration_days, max_group_size, age_range,
   effort_level, comfort_level, accommodation, category, base_price, discount_percent,
   rating, reviews_count, image_url, gallery_urls, is_active) VALUES
(
  'Jordan 360°: Petra, Wadi Rum and the Dead Sea',
  'jordan-360',
  'Petra, Wadi Rum and the Dead Sea',
  'Explore the rose-red city of Petra, sleep under the stars in the Wadi Rum desert, and float in the mineral-rich Dead Sea — all with a local Group Leader and a small group of fellow travellers.',
  8, 20, '18–39', 'moderate',
  'Hotels, desert camp & one night bus',
  'Shared twin rooms, mixed gender OK',
  'Middle East', 1350.00, 15.00,
  4.8, 8367,
  'https://images.unsplash.com/photo-1548786811-dd6e453ccca7?w=700&q=80',
  'https://images.unsplash.com/photo-1548786811-dd6e453ccca7?w=900&q=80,https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=500&q=80,https://images.unsplash.com/photo-1509316785289-025f5b846b35?w=500&q=80',
  1
),
(
  'Bali Explorer',
  'bali-explorer',
  'Beaches, rice terraces and temples',
  'A vibrant journey through Bali''s lush rice terraces, sacred temples and stunning coastline.',
  9, 16, '18–39', 'moderate',
  'Hotels & villas',
  'Shared twin rooms, mixed gender OK',
  'Asia', 1190.00, 10.00,
  4.7, 3120,
  'https://images.unsplash.com/photo-1533105079780-92b9be482077?w=500&q=80',
  'https://images.unsplash.com/photo-1533105079780-92b9be482077?w=900&q=80',
  1
),
(
  'Morocco Discovery',
  'morocco-discovery',
  'Marrakech, Sahara and the coast',
  'From the souks of Marrakech to the Sahara dunes and the Atlantic coast — a kaleidoscope of colour and culture.',
  7, 16, '18–39', 'moderate',
  'Riads & desert camp',
  'Shared twin rooms, mixed gender OK',
  'Africa', 990.00, 20.00,
  4.6, 2580,
  'https://images.unsplash.com/photo-1533587851505-d119e13fa0d7?w=500&q=80',
  'https://images.unsplash.com/photo-1533587851505-d119e13fa0d7?w=900&q=80',
  1
),
(
  'Iceland Ring Road',
  'iceland-ring-road',
  'Waterfalls, glaciers and black beaches',
  'Circumnavigate Iceland on the famous Ring Road chasing waterfalls, glaciers and volcanic landscapes.',
  10, 14, '18–39', 'hard',
  'Guesthouses & camps',
  'Shared twin rooms, mixed gender OK',
  'Europe', 1690.00, 0,
  4.9, 1740,
  'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=500&q=80',
  'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=900&q=80',
  1
),
(
  'Vietnam & Cambodia',
  'vietnam-cambodia',
  'From Hanoi to Angkor Wat',
  'A classic South-East Asia loop through Vietnam''s northern highlights and Cambodia''s ancient temples.',
  12, 16, '18–39', 'moderate',
  'Hotels & homestays',
  'Shared twin rooms, mixed gender OK',
  'Asia', 1450.00, 8.00,
  4.8, 4200,
  'https://images.unsplash.com/photo-1528181304800-259b08848526?w=500&q=80',
  'https://images.unsplash.com/photo-1528181304800-259b08848526?w=900&q=80',
  1
),
(
  'Peru & Machu Picchu',
  'peru-machu-picchu',
  'Lima, Cusco and the Inca citadel',
  'Follow the Inca trail to the lost city of Machu Picchu, explore Cusco and taste your way through Lima.',
  10, 14, '18–39', 'hard',
  'Hotels',
  'Shared twin rooms, mixed gender OK',
  'Latin America', 1890.00, 0,
  4.8, 2150,
  'https://images.unsplash.com/photo-1518709268805-4e9042af2176?w=500&q=80',
  'https://images.unsplash.com/photo-1518709268805-4e9042af2176?w=900&q=80',
  1
);

-- ---- Itinerary (Jordan 360°) ----
INSERT INTO weroad_trip_itineraries (trip_id, day_number, title, description, image_url) VALUES
(1, 1, 'Meet in Amman', 'Arrive in Amman and meet your Group Leader and fellow travellers at the welcome dinner. A gentle first night to settle in before the adventure begins.', 'https://images.unsplash.com/photo-1548786811-dd6e453ccca7?w=150&q=80'),
(1, 2, 'West Bank exploring Petra', 'Drive south to Petra and walk the Siq at golden hour to reach the Treasury for the first time — one of the New Seven Wonders of the World.', 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150&q=80'),
(1, 3, 'Petra & Little Petra', 'A full day exploring the wider Petra archaeological park, plus a visit to the quieter Siq al-Barid, known as Little Petra.', 'https://images.unsplash.com/photo-1539768942893-daf53e448371?w=150&q=80'),
(1, 4, 'Into the Wadi Rum desert', '4x4 jeep tour through the red dunes and rock bridges of Wadi Rum, followed by a night under the stars at a Bedouin desert camp.', 'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=150&q=80'),
(1, 5, 'Wadi Rum sunrise trek', 'An early sunrise hike up a rock formation before travelling on toward the Dead Sea coast.', 'https://images.unsplash.com/photo-1451337516015-6b6e9a44a8a3?w=150&q=80'),
(1, 6, 'Float in the Dead Sea', 'A free day to relax at the lowest point on Earth — float in the mineral-rich water and try the famous mud.', 'https://images.unsplash.com/photo-1509316785289-025f5b846b35?w=150&q=80'),
(1, 7, 'Back to Amman', 'Return to Amman with time to explore the Citadel and downtown souks before the farewell dinner.', 'https://images.unsplash.com/photo-1518709268805-4e9042af2176?w=150&q=80'),
(1, 8, 'Goodbye Amman', 'Trip ends after breakfast. Transfers to the airport are not included but easy to arrange.', 'https://images.unsplash.com/photo-1539768942893-daf53e448371?w=150&q=80');

-- ---- Inclusions (Jordan 360°) ----
INSERT INTO weroad_trip_inclusions (trip_id, include_type, item_text) VALUES
(1, 'included', '7 nights'' accommodation'),
(1, 'included', 'All transport during the trip'),
(1, 'included', 'English-speaking Group Leader'),
(1, 'included', 'Medical & baggage insurance'),
(1, 'excluded', 'International flights'),
(1, 'excluded', 'Jordan entry visa'),
(1, 'excluded', 'Meals not specified in the itinerary');

-- ---- Optional activities (Jordan 360°) ----
INSERT INTO weroad_trip_optional_activities (trip_id, name, description, price) VALUES
(1, 'Petra by Night', 'Walk the candlelit Siq to the Treasury for an evening performance. Approx. $20 per person.', 20.00),
(1, 'Hot air balloon over Wadi Rum', 'Sunrise flight over the desert, weather permitting. Approx. $170 per person.', 170.00);

-- ---- FAQs (Jordan 360°) ----
INSERT INTO weroad_trip_faqs (trip_id, faq_group, question, answer) VALUES
(1, 'About this trip', 'What''s the group size on this trip?', 'Groups are usually between 12 and 20 travellers, plus one Group Leader.'),
(1, 'About this trip', 'Is single accommodation available?', 'Yes — add the Private Room option at checkout for an additional fee.'),
(1, 'About this trip', 'How fit do I need to be?', 'This trip is rated moderate: some walking on uneven terrain and one sunrise hike, no technical climbing.'),
(1, 'About WeRoad', 'What is WeRoad?', 'WeRoad designs small-group adventure trips for travellers who want to explore without planning logistics themselves.'),
(1, 'About WeRoad', 'Can I travel solo?', 'Most WeRoaders travel solo — there''s no single supplement and rooms are shared with fellow travellers.'),
(1, 'About Jordan', 'What''s the best time to visit Jordan?', 'Spring (March–May) and autumn (September–November) offer the most comfortable temperatures.'),
(1, 'About Jordan', 'Is Jordan safe for travellers?', 'Jordan is considered one of the most stable and welcoming countries in the region.');

-- ---- Departures (Jordan 360°) ----
INSERT INTO weroad_departures (trip_id, start_date, end_date, price, currency, spots_total, spots_left, is_active) VALUES
(1, '2026-08-30', '2026-09-06', 1188.00, 'EUR', 20, 4, 1),
(1, '2026-09-13', '2026-09-20', 1188.00, 'EUR', 20, 12, 1),
(1, '2026-10-04', '2026-10-11', 1245.00, 'EUR', 20, 19, 1),
(1, '2026-11-01', '2026-11-08', 1150.00, 'EUR', 20, 20, 1);

-- ---- Reviews ----
INSERT INTO weroad_reviews (trip_id, author, rating, text) VALUES
(1, 'Marta, Jordan 360°', 5, 'My third trip with WeRoad and still the best way I''ve found to travel solo without actually being alone.'),
(2, 'Dario, Morocco Discovery', 5, 'The Group Leader made the whole thing feel effortless. Came back with ten new friends and a phone full of photos.'),
(3, 'Aisha, Peru & Machu Picchu', 5, 'Booking was simple, the installment plan helped a lot, and the itinerary was better paced than I expected.');

-- ---- Promo codes ----
INSERT INTO weroad_promo_codes (code, discount_type, discount_value, max_uses, used_count, is_active, valid_from, valid_until) VALUES
('WEROAD10', 'percent', 10.00, 100, 0, 1, '2024-01-01', '2026-12-31'),
('JOIN5',    'flat',     5.00,  200, 0, 1, '2024-01-01', '2026-12-31');


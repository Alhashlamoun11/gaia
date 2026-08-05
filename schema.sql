-- ============================================================
-- Car Rental / Transfer System — Database Schema
-- Plain MySQL DDL. No frameworks, no libraries.
-- This script creates the database, all tables, indexes,
-- and sample seed data so the system works out-of-the-box.
-- ============================================================

-- Create the database (change these credentials if needed)
CREATE DATABASE IF NOT EXISTS gaia_tours
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE gaia_tours;

-- ------------------------------------------------------------
-- Table: car_classes
-- Available car/vehicle classes with their passenger & luggage
-- limits. Each class has a price multiplier applied to the
-- route's base price.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS car_classes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  passenger_capacity INT UNSIGNED NOT NULL DEFAULT 4,
  luggage_capacity INT UNSIGNED NOT NULL DEFAULT 3,
  price_multiplier DECIMAL(4,2) NOT NULL DEFAULT 1.00,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cc_capacity (passenger_capacity),
  INDEX idx_cc_active (is_active)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: routes
-- Origin/Destination pairs with distance and base price.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS routes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  origin VARCHAR(150) NOT NULL,
  destination VARCHAR(150) NOT NULL,
  distance_km DECIMAL(8,2) NOT NULL DEFAULT 0,
  travel_time_min INT UNSIGNED NOT NULL DEFAULT 0,
  base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_route_origin (origin),
  INDEX idx_route_destination (destination),
  INDEX idx_route_active (is_active),
  UNIQUE KEY uq_route_pair (origin, destination)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: promo_codes
-- Discount codes. Discount can be a percentage (%) or a flat
-- amount (USD). max_uses limits total redemptions.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS promo_codes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  discount_type ENUM('percent','flat') NOT NULL DEFAULT 'percent',
  discount_value DECIMAL(10,2) NOT NULL DEFAULT 0,
  max_uses INT UNSIGNED NOT NULL DEFAULT 1,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  valid_from DATE NULL,
  valid_until DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_promo_code (code),
  INDEX idx_promo_active (is_active)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: bookings
-- The main booking record. Stores the full trip, passenger
-- details and the calculated price breakdown.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_code VARCHAR(20) NOT NULL UNIQUE,
  route_id INT UNSIGNED NULL,
  car_class_id INT UNSIGNED NULL,
  promo_code_id INT UNSIGNED NULL,

  -- Trip info
  origin VARCHAR(150) NOT NULL,
  destination VARCHAR(150) NOT NULL,
  pickup_date DATE NOT NULL,
  pickup_time TIME NULL,
  passenger_count INT UNSIGNED NOT NULL DEFAULT 1,

  -- Flight details
  flight_number VARCHAR(20) NULL,
  arrival_time TIME NULL,
  destination_address VARCHAR(255) NULL,

  -- Options
  return_trip TINYINT(1) NOT NULL DEFAULT 0,

  -- Passenger details
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL,
  phone VARCHAR(50) NOT NULL,

  -- Extras
  child_seats INT UNSIGNED NOT NULL DEFAULT 0,
  water_bottles INT UNSIGNED NOT NULL DEFAULT 0,
  pet_carrier TINYINT(1) NOT NULL DEFAULT 0,
  women_driver TINYINT(1) NOT NULL DEFAULT 0,
  comments TEXT NULL,

  -- Price breakdown
  base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  price_multiplier DECIMAL(4,2) NOT NULL DEFAULT 1.00,
  return_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  extras_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_price DECIMAL(10,2) NOT NULL DEFAULT 0,

  status ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_booking_route FOREIGN KEY (route_id)
    REFERENCES routes (id) ON DELETE SET NULL,
  CONSTRAINT fk_booking_car FOREIGN KEY (car_class_id)
    REFERENCES car_classes (id) ON DELETE SET NULL,
  CONSTRAINT fk_booking_promo FOREIGN KEY (promo_code_id)
    REFERENCES promo_codes (id) ON DELETE SET NULL,

  INDEX idx_booking_email (email),
  INDEX idx_booking_date (pickup_date),
  INDEX idx_booking_status (status)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: booking_extras
-- Line items for each add-on selected on a booking.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS booking_extras (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL,
  extra_name VARCHAR(100) NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_price DECIMAL(10,2) NOT NULL DEFAULT 0,

  CONSTRAINT fk_extra_booking FOREIGN KEY (booking_id)
    REFERENCES bookings (id) ON DELETE CASCADE,

  INDEX idx_extra_booking (booking_id)
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Car classes (Economy, Business, SUV, Minivan)
INSERT INTO car_classes (name, slug, passenger_capacity, luggage_capacity, price_multiplier, is_active) VALUES
('Economy',   'economy',   4,  3, 1.00, 1),
('Comfort',   'comfort',   4,  3, 1.15, 1),
('Business',  'business',  3,  3, 1.40, 1),
('SUV',       'suv',       4,  4, 1.30, 1),
('Minivan',   'minivan',   7,  6, 1.60, 1),
('Minibus 19', 'minibus19',19, 19, 4.50, 1);

-- Routes (using the sample route from the frontend)
INSERT INTO routes (origin, destination, distance_km, travel_time_min, base_price, is_active) VALUES
('Milan Bergamo Airport', 'Milan',             56.0, 50, 196.00, 1),
('Milan Bergamo Airport', 'Milan City Centre', 56.0, 50, 196.00, 1),
('Milan', 'Milan Bergamo Airport',            56.0, 50, 196.00, 1),
('Milan Airport Malpensa', 'Milan',            46.0, 45, 164.00, 1),
('Bergamo Airport', 'Milan',                   56.0, 50, 196.00, 1),
('Rome Airport Fiumicino', 'Rome',             30.0, 40, 120.00, 1),
('Naples International Airport', 'Amalfi',     60.0, 70, 180.00, 1);

-- Promo codes
INSERT INTO promo_codes (code, discount_type, discount_value, max_uses, used_count, is_active, valid_from, valid_until) VALUES
('WELCOME10', 'percent', 10.00, 100, 0, 1, '2024-01-01', '2026-12-31'),
('SAVE20',    'percent', 20.00, 50,  0, 1, '2024-01-01', '2026-12-31'),
('FLAT5',     'flat',     5.00, 200, 0, 1, '2024-01-01', '2026-12-31'),
('EXPIRED',   'percent', 15.00, 10,  0, 0, '2023-01-01', '2023-12-31');

-- ------------------------------------------------------------
-- Table: reviews
-- Customer reviews shown on the home / route pages.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reviewer VARCHAR(100) NOT NULL,
  rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
  place VARCHAR(150) NOT NULL,
  date_label VARCHAR(50) NOT NULL,
  text TEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reviews_active (is_active)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: faq
-- Frequently Asked Questions for the home page.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS faq (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(50) NOT NULL DEFAULT 'Booking',
  question VARCHAR(255) NOT NULL,
  answer TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_faq_active (is_active)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: services
-- Additional services featured on the home page.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  icon VARCHAR(100) NOT NULL DEFAULT 'fa-solid fa-car',
  title VARCHAR(150) NOT NULL,
  description VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_services_active (is_active)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: partners
-- Partner logos shown on the home page.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS partners (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  icon VARCHAR(100) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed reviews
INSERT INTO reviews (reviewer, rating, place, date_label, text, is_active) VALUES
('Johnny', 5, 'Playa Blanca', '18 Jul 2026', 'Fantastic service. The driver was waiting for us on arrival at the airport, and helped us with our luggage. I was really impressed and happy with the whole experience.', 1),
('Jan Löfgren', 5, 'Lloret de Mar', '09 Jul 2026', 'Calm and safe car driver, was even earlier than expected, very clean and nice car. Good and direct communication via WhatsApp.', 1),
('Fereno and Misty Reznik', 5, 'Torea de Mar', '09 Jul 2026', 'Driver helpful, friendly and on time which was very important to us. We felt safe and in great hands. Loved the communication the day before.', 1),
('Peter', 5, 'Albena', '03 Jul 2026', 'Excellent service! We booked a transfer from Varna Airport to Maritim Hotel Paradise Blue in Albena, and everything went perfectly.', 1),
('Karina Steenbuch', 5, 'Milan Bergamo Airport', '08 Sep 2017', 'The driver did not arrive until almost 12:00 due to traffic, but we were informed all the time, so that was good. Very nice driver and vehicle.', 1),
('Jonathan', 5, 'Milan Bergamo Airport', '13 Apr 2017', 'Best drive ever! Even WiFi on board so that I could work on my presentation.', 1);

-- Seed FAQ
INSERT INTO faq (category, question, answer, sort_order, is_active) VALUES
('Booking', 'How do I book a transfer?', 'Choose your route, pick a date and car class, then confirm your details and payment — you will get an instant booking confirmation by email.', 1, 1),
('Booking', 'What pick-up time should I specify for transfer?', 'For airport arrivals, specify your flight landing time — we track your flight and adjust automatically for delays.', 2, 1),
('Booking', 'How far in advance should I book a transfer?', 'We recommend booking at least 24 hours in advance to guarantee car availability, though last-minute bookings are often possible.', 3, 1),
('Booking', 'How do I know if my order for transfer has been accepted?', 'You will receive a confirmation email with your driver details once a driver has been assigned to your ride.', 4, 1),
('Booking', 'How do I enter my address correctly on a booking form?', 'Start typing your address and select the matching suggestion from the dropdown to ensure accurate pin placement.', 5, 1);

-- Seed services
INSERT INTO services (icon, title, description, sort_order, is_active) VALUES
('fa-solid fa-baby-carriage', 'Child Seats', 'Seat 9-18 kg · Booster 15-36 kg · Infant seat up to 9 kg', 1, 1),
('fa-regular fa-clock', 'Extra hour of Waiting', 'The driver waits for you at the airport 2.5h', 2, 1),
('fa-solid fa-person-skiing', 'Box for Ski equipment', 'Secure and convenient storage', 3, 1);

-- Seed partners
INSERT INTO partners (name, icon, sort_order, is_active) VALUES
('mozio', NULL, 1, 1),
('Trip.com', NULL, 2, 1),
('RATE HAWK', 'fa-solid fa-tag', 3, 1),
('HQ', NULL, 4, 1),
('Hertz', NULL, 5, 1);

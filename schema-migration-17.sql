-- ============================================================
-- GAIA TOURS & TRAVEL
-- Schema migration #17 (Hotel Partner Portal)
-- ------------------------------------------------------------
-- Objective: Add fields required for Hotel Partner Portal.
-- Safe to run repeatedly (idempotent).
-- ============================================================
USE gaia_tours;

-- ------------------------------------------------------------
-- 1) HOTELS — New operational fields
-- ------------------------------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotels' AND COLUMN_NAME='phone');
SET @s := IF(@c=0, 'ALTER TABLE hotels ADD COLUMN phone VARCHAR(50) NULL AFTER location', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotels' AND COLUMN_NAME='email');
SET @s := IF(@c=0, 'ALTER TABLE hotels ADD COLUMN email VARCHAR(150) NULL AFTER phone', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotels' AND COLUMN_NAME='website');
SET @s := IF(@c=0, 'ALTER TABLE hotels ADD COLUMN website VARCHAR(300) NULL AFTER email', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotels' AND COLUMN_NAME='check_in_time');
SET @s := IF(@c=0, 'ALTER TABLE hotels ADD COLUMN check_in_time VARCHAR(20) NULL DEFAULT \'14:00\' AFTER website', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotels' AND COLUMN_NAME='check_out_time');
SET @s := IF(@c=0, 'ALTER TABLE hotels ADD COLUMN check_out_time VARCHAR(20) NULL DEFAULT \'12:00\' AFTER check_in_time', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotels' AND COLUMN_NAME='cancellation_policy');
SET @s := IF(@c=0, 'ALTER TABLE hotels ADD COLUMN cancellation_policy TEXT NULL AFTER check_out_time', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------
-- 2) HOTEL_NOTIFICATIONS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS hotel_notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hotel_id INT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  type ENUM('booking_new', 'booking_cancelled', 'booking_confirmed', 'review_new', 'system') NOT NULL DEFAULT 'system',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_h_notif_hotel FOREIGN KEY (hotel_id) REFERENCES hotels (id) ON DELETE CASCADE,
  INDEX idx_h_notif_hotel (hotel_id),
  INDEX idx_h_notif_read (is_read)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3) TRANSLATION KEYS
-- ------------------------------------------------------------
INSERT IGNORE INTO translations (`key`, lang, `value`, `group`) VALUES
('hotel.dashboard', 'en', 'Dashboard', 'hotel'),
('hotel.profile', 'en', 'Profile', 'hotel'),
('hotel.rooms', 'en', 'Rooms', 'hotel'),
('hotel.availability', 'en', 'Availability', 'hotel'),
('hotel.bookings', 'en', 'Bookings', 'hotel'),
('hotel.reviews', 'en', 'Reviews', 'hotel'),
('hotel.notifications', 'en', 'Notifications', 'hotel'),
('hotel.check_in_time', 'en', 'Check-in Time', 'hotel'),
('hotel.check_out_time', 'en', 'Check-out Time', 'hotel'),
('hotel.cancellation_policy', 'en', 'Cancellation Policy', 'hotel'),
('hotel.save_changes', 'en', 'Save Changes', 'hotel'),
('hotel.room_name', 'en', 'Room Name', 'hotel'),
('hotel.room_type', 'en', 'Room Type', 'hotel'),
('hotel.capacity', 'en', 'Capacity', 'hotel'),
('hotel.price', 'en', 'Price', 'hotel'),
('hotel.status', 'en', 'Status', 'hotel'),
('hotel.actions', 'en', 'Actions', 'hotel'),
('hotel.available', 'en', 'Available', 'hotel'),
('hotel.booked', 'en', 'Booked', 'hotel'),
('hotel.booking_ref', 'en', 'Booking Ref', 'hotel'),
('hotel.guest', 'en', 'Guest', 'hotel'),
('hotel.check_in', 'en', 'Check-in', 'hotel'),
('hotel.check_out', 'en', 'Check-out', 'hotel'),
('hotel.amount', 'en', 'Amount', 'hotel'),
('hotel.pending', 'en', 'Pending', 'hotel'),
('hotel.confirmed', 'en', 'Confirmed', 'hotel'),
('hotel.cancelled', 'en', 'Cancelled', 'hotel'),
('hotel.completed', 'en', 'Completed', 'hotel'),
('hotel.view_booking', 'en', 'View Booking', 'hotel'),
('hotel.update_status', 'en', 'Update Status', 'hotel'),
('hotel.unauthorized', 'en', 'Unauthorized Access', 'hotel');

-- ============================================================
-- GAIA TOURS & TRAVEL — Booking Ownership System
-- Migration #10
-- ------------------------------------------------------------
-- Objective: Every booking belongs to a customer account.
--
-- Ensures each booking table has:
--   * user_id       INT UNSIGNED NULL  -> users.id (FK, ON DELETE SET NULL)
--   * guest_name / guest_email / guest_phone  (guest identity)
--   * index on user_id + guest_email
--
-- Tables (guaranteed idempotent — safe to run repeatedly):
--   * bookings            (transfers)
--   * weroad_bookings     (tours)
--   * taxi_bookings       (taxis)
--   * hotel_bookings      (hotels)
--   * event_bookings      (events)
--
-- The `payments` table already carries payments.user_id,
-- payments.booking_type and payments.booking_id for future
-- CyberSource integration (no gateway implemented here).
-- ============================================================
USE gaia_tours;

-- Helper: add a column if it does not exist.
-- We reuse the PREPARE/EXECUTE pattern already used in this repo.

-- ============================================================
-- 1) bookings (transfers)
-- ============================================================
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='user_id');
SET @s := IF(@c=0, 'ALTER TABLE bookings ADD COLUMN user_id INT UNSIGNED NULL AFTER car_class_id', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='guest_name');
SET @s := IF(@c=0, 'ALTER TABLE bookings ADD COLUMN guest_name VARCHAR(150) NULL AFTER user_id', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='guest_email');
SET @s := IF(@c=0, 'ALTER TABLE bookings ADD COLUMN guest_email VARCHAR(150) NULL AFTER guest_name', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='guest_phone');
SET @s := IF(@c=0, 'ALTER TABLE bookings ADD COLUMN guest_phone VARCHAR(50) NULL AFTER guest_email', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Backfill guest fields from existing passenger columns
UPDATE bookings SET guest_name = full_name, guest_email = email, guest_phone = phone
WHERE guest_name IS NULL OR guest_name = '';

-- Indexes
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND INDEX_NAME='idx_bookings_user');
SET @s := IF(@c=0, 'ALTER TABLE bookings ADD INDEX idx_bookings_user (user_id)', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Foreign key (users.id) — ON DELETE SET NULL keeps booking history
SET @c := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND CONSTRAINT_NAME='fk_booking_user');
SET @s := IF(@c=0, 'ALTER TABLE bookings ADD CONSTRAINT fk_booking_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ============================================================
-- 2) weroad_bookings (tours)
-- ============================================================
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_bookings' AND COLUMN_NAME='user_id');
SET @s := IF(@c=0, 'ALTER TABLE weroad_bookings ADD COLUMN user_id INT UNSIGNED NULL AFTER departure_id', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_bookings' AND COLUMN_NAME='guest_name');
SET @s := IF(@c=0, 'ALTER TABLE weroad_bookings ADD COLUMN guest_name VARCHAR(150) NULL AFTER user_id', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_bookings' AND COLUMN_NAME='guest_email');
SET @s := IF(@c=0, 'ALTER TABLE weroad_bookings ADD COLUMN guest_email VARCHAR(150) NULL AFTER guest_name', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_bookings' AND COLUMN_NAME='guest_phone');
SET @s := IF(@c=0, 'ALTER TABLE weroad_bookings ADD COLUMN guest_phone VARCHAR(50) NULL AFTER guest_email', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE weroad_bookings SET guest_name = full_name, guest_email = email, guest_phone = phone
WHERE guest_name IS NULL OR guest_name = '';

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_bookings' AND INDEX_NAME='idx_wb_user');
SET @s := IF(@c=0, 'ALTER TABLE weroad_bookings ADD INDEX idx_wb_user (user_id)', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='weroad_bookings' AND CONSTRAINT_NAME='fk_wb_user');
SET @s := IF(@c=0, 'ALTER TABLE weroad_bookings ADD CONSTRAINT fk_wb_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ============================================================
-- 3) taxi_bookings (taxis)
-- ============================================================
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='taxi_bookings' AND COLUMN_NAME='user_id');
SET @s := IF(@c=0, 'ALTER TABLE taxi_bookings ADD COLUMN user_id INT UNSIGNED NULL AFTER booking_code', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='taxi_bookings' AND COLUMN_NAME='guest_name');
SET @s := IF(@c=0, 'ALTER TABLE taxi_bookings ADD COLUMN guest_name VARCHAR(150) NULL AFTER user_id', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='taxi_bookings' AND COLUMN_NAME='guest_email');
SET @s := IF(@c=0, 'ALTER TABLE taxi_bookings ADD COLUMN guest_email VARCHAR(150) NULL AFTER guest_name', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='taxi_bookings' AND COLUMN_NAME='guest_phone');
SET @s := IF(@c=0, 'ALTER TABLE taxi_bookings ADD COLUMN guest_phone VARCHAR(50) NULL AFTER guest_email', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='taxi_bookings' AND INDEX_NAME='idx_tb_user');
SET @s := IF(@c=0, 'ALTER TABLE taxi_bookings ADD INDEX idx_tb_user (user_id)', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='taxi_bookings' AND CONSTRAINT_NAME='fk_tb_user');
SET @s := IF(@c=0, 'ALTER TABLE taxi_bookings ADD CONSTRAINT fk_tb_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ============================================================
-- 4) hotel_bookings (hotels)
-- ============================================================
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_bookings' AND COLUMN_NAME='user_id');
SET @s := IF(@c=0, 'ALTER TABLE hotel_bookings ADD COLUMN user_id INT UNSIGNED NULL AFTER room_id', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_bookings' AND INDEX_NAME='idx_hb_user');
SET @s := IF(@c=0, 'ALTER TABLE hotel_bookings ADD INDEX idx_hb_user (user_id)', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='hotel_bookings' AND CONSTRAINT_NAME='fk_hb_user');
SET @s := IF(@c=0, 'ALTER TABLE hotel_bookings ADD CONSTRAINT fk_hb_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ============================================================
-- 5) event_bookings (events)
-- ============================================================
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_bookings' AND COLUMN_NAME='user_id');
SET @s := IF(@c=0, 'ALTER TABLE event_bookings ADD COLUMN user_id INT UNSIGNED NULL AFTER event_id', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_bookings' AND INDEX_NAME='idx_eb_user');
SET @s := IF(@c=0, 'ALTER TABLE event_bookings ADD INDEX idx_eb_user (user_id)', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='event_bookings' AND CONSTRAINT_NAME='fk_eb_user');
SET @s := IF(@c=0, 'ALTER TABLE event_bookings ADD CONSTRAINT fk_eb_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ============================================================
-- 6) Account / booking translations (EN/AR)
-- ============================================================
INSERT IGNORE INTO translations (`key`, lang, `value`, `group`) VALUES
('account.header_my_account', 'en', 'My Account', 'account'),
('account.header_my_account', 'ar', 'حسابي', 'account'),
('account.header_my_bookings', 'en', 'My Bookings', 'account'),
('account.header_my_bookings', 'ar', 'حجوزاتي', 'account'),
('account.booking_reference', 'en', 'Booking reference', 'account'),
('account.booking_reference', 'ar', 'مرجع الحجز', 'account'),
('account.booking_date', 'en', 'Booking date', 'account'),
('account.booking_date', 'ar', 'تاريخ الحجز', 'account'),
('account.service_name', 'en', 'Service', 'account'),
('account.service_name', 'ar', 'الخدمة', 'account'),
('account.amount', 'en', 'Amount', 'account'),
('account.amount', 'ar', 'المبلغ', 'account'),
('account.actions', 'en', 'Actions', 'account'),
('account.actions', 'ar', 'إجراءات', 'account'),
('account.view_details', 'en', 'View details', 'account'),
('account.view_details', 'ar', 'عرض التفاصيل', 'account'),
('account.booking_details', 'en', 'Booking details', 'account'),
('account.booking_details', 'ar', 'تفاصيل الحجز', 'account'),
('account.service_details', 'en', 'Service information', 'account'),
('account.service_details', 'ar', 'معلومات الخدمة', 'account'),
('account.price_breakdown', 'en', 'Price breakdown', 'account'),
('account.price_breakdown', 'ar', 'تفاصيل السعر', 'account'),
('account.booking_not_found', 'en', 'Booking not found.', 'account'),
('account.booking_not_found', 'ar', 'الحجز غير موجود.', 'account'),
('account.unauthorized', 'en', 'You are not authorised to view this booking.', 'account'),
('account.unauthorized', 'ar', 'غير مصرح لك بعرض هذا الحجز.', 'account'),
('account.booking_claimed', 'en', 'Your previous bookings have been linked to your new account.', 'account'),
('account.booking_claimed', 'ar', 'تم ربط حجوزاتك السابقة بحسابك الجديد.', 'account'),
('account.total_bookings', 'en', 'Total bookings', 'account'),
('account.total_bookings', 'ar', 'إجمالي الحجوزات', 'account'),
('account.guest', 'en', 'Guest booking', 'account'),
('account.guest', 'ar', 'حجز كضيف', 'account'),
('account.back_to_bookings', 'en', 'Back to my bookings', 'account'),
('account.back_to_bookings', 'ar', 'العودة إلى حجوزاتي', 'account'),
('account.section_tours', 'en', 'Tours', 'account'),
('account.section_tours', 'ar', 'الجولات', 'account'),
('account.section_hotels', 'en', 'Hotels', 'account'),
('account.section_hotels', 'ar', 'الفنادق', 'account'),
('account.section_events', 'en', 'Events', 'account'),
('account.section_events', 'ar', 'الفعاليات', 'account'),
('account.section_transfers', 'en', 'Transfers', 'account'),
('account.section_transfers', 'ar', 'النقل', 'account');

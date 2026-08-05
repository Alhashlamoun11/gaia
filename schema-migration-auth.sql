-- ============================================================
-- GAIA TOURS & TRAVEL — Authentication & Account System
-- Migration #AUTH
-- ------------------------------------------------------------
-- Implements:
--   * roles (super_admin / hotel_manager / customer)
--   * permissions + role_permissions (future RBAC)
--   * users (auth + profile + status)
--   * hotels_users (hotel manager assignment)
--   * user_preferences (customer saved preferences)
--   * password_resets / login_attempts / remember_tokens
--   * payments (future CyberSource readiness — NO gateway now)
--   * hotel_bookings / event_bookings / taxi_bookings (ownership)
--   * ALTER existing bookings + weroad_bookings to add ownership
--
-- Safe to run repeatedly (idempotent).
-- ============================================================
USE gaia_tours;

-- ============================================================
-- 1) ROLES
-- ============================================================
CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO roles (name, slug) VALUES
('Super Admin',     'super_admin'),
('Hotel Manager',   'hotel_manager'),
('Customer',        'customer')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ============================================================
-- 2) PERMISSIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO permissions (name, slug) VALUES
('Manage Website',    'manage_website'),
('Manage Tours',      'manage_tours'),
('Manage Hotels',     'manage_hotels'),
('Manage Rooms',      'manage_rooms'),
('Manage Events',     'manage_events'),
('Manage Bookings',   'manage_bookings'),
('Manage Users',      'manage_users'),
('Manage Payments',   'manage_payments')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ============================================================
-- 3) ROLE PERMISSIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Super Admin gets every permission
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'super_admin';

-- ============================================================
-- 4) USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(50) NULL,
  country VARCHAR(100) NULL,
  preferred_language VARCHAR(10) NOT NULL DEFAULT 'en',
  password_hash VARCHAR(255) NOT NULL,
  avatar VARCHAR(500) NULL,
  role_id INT UNSIGNED NOT NULL DEFAULT 3,
  status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  email_verified_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id),
  INDEX idx_users_email (email),
  INDEX idx_users_role (role_id),
  INDEX idx_users_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- 5) HOTELS_USERS (hotel manager assignment)
-- ============================================================
CREATE TABLE IF NOT EXISTS hotels_users (
  hotel_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (hotel_id, user_id),
  CONSTRAINT fk_hu_hotel FOREIGN KEY (hotel_id) REFERENCES hotels (id) ON DELETE CASCADE,
  CONSTRAINT fk_hu_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  INDEX idx_hu_user (user_id)
) ENGINE=InnoDB;

-- ============================================================
-- 6) USER_PREFERENCES (customer saved preferences)
-- ============================================================
CREATE TABLE IF NOT EXISTS user_preferences (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  pref_key VARCHAR(80) NOT NULL,
  pref_value TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_up_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  UNIQUE KEY uq_up_user_key (user_id, pref_key)
) ENGINE=InnoDB;

-- ============================================================
-- 7) PASSWORD RESETS
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  INDEX idx_pr_token (token_hash)
) ENGINE=InnoDB;

-- ============================================================
-- 8) LOGIN ATTEMPTS (throttling)
-- ============================================================
CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL,
  INDEX idx_la_email (email),
  INDEX idx_la_ip (ip_address),
  INDEX idx_la_time (attempted_at)
) ENGINE=InnoDB;

-- ============================================================
-- 9) REMEMBER TOKENS
-- ============================================================
CREATE TABLE IF NOT EXISTS remember_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  selector VARCHAR(64) NOT NULL,
  token_hash VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  UNIQUE KEY uq_rt_selector (selector),
  INDEX idx_rt_user (user_id)
) ENGINE=InnoDB;

-- ============================================================
-- 10) PAYMENTS (future CyberSource readiness — NO gateway now)
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  booking_type ENUM('transfer','tour','hotel','event','taxi') NOT NULL,
  booking_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  currency VARCHAR(3) NOT NULL DEFAULT 'USD',
  gateway VARCHAR(50) NULL,
  transaction_reference VARCHAR(190) NULL,
  status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pay_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  INDEX idx_pay_user (user_id),
  INDEX idx_pay_type (booking_type, booking_id),
  INDEX idx_pay_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- 11) HOTEL BOOKINGS (ownership)
-- ============================================================
CREATE TABLE IF NOT EXISTS hotel_bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_code VARCHAR(20) NOT NULL UNIQUE,
  hotel_id INT UNSIGNED NULL,
  room_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  guest_name VARCHAR(150) NOT NULL,
  guest_email VARCHAR(150) NOT NULL,
  guest_phone VARCHAR(50) NULL,
  check_in_date DATE NOT NULL,
  check_out_date DATE NOT NULL,
  guests INT UNSIGNED NOT NULL DEFAULT 2,
  total_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_hb_hotel FOREIGN KEY (hotel_id) REFERENCES hotels (id) ON DELETE SET NULL,
  CONSTRAINT fk_hb_room FOREIGN KEY (room_id) REFERENCES hotel_rooms (id) ON DELETE SET NULL,
  CONSTRAINT fk_hb_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  INDEX idx_hb_user (user_id),
  INDEX idx_hb_email (guest_email),
  INDEX idx_hb_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- 12) EVENT BOOKINGS (ownership)
-- ============================================================
CREATE TABLE IF NOT EXISTS event_bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_code VARCHAR(20) NOT NULL UNIQUE,
  event_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  guest_name VARCHAR(150) NOT NULL,
  guest_email VARCHAR(150) NOT NULL,
  guest_phone VARCHAR(50) NULL,
  tickets INT UNSIGNED NOT NULL DEFAULT 1,
  total_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_eb_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE SET NULL,
  CONSTRAINT fk_eb_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  INDEX idx_eb_user (user_id),
  INDEX idx_eb_email (guest_email),
  INDEX idx_eb_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- 13) TAXI BOOKINGS (ownership)
-- ============================================================
CREATE TABLE IF NOT EXISTS taxi_bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_code VARCHAR(20) NOT NULL UNIQUE,
  user_id INT UNSIGNED NULL,
  guest_name VARCHAR(150) NOT NULL,
  guest_email VARCHAR(150) NOT NULL,
  guest_phone VARCHAR(50) NULL,
  origin VARCHAR(150) NOT NULL,
  destination VARCHAR(150) NOT NULL,
  pickup_date DATE NOT NULL,
  pickup_time TIME NULL,
  total_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tb_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  INDEX idx_tb_user (user_id),
  INDEX idx_tb_email (guest_email),
  INDEX idx_tb_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- 14) ALTER EXISTING BOOKINGS + WEROAD_BOOKINGS (ownership)
-- ============================================================
-- bookings (transfers)
SET @b_user := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='user_id');
SET @s := IF(@b_user=0, 'ALTER TABLE bookings ADD COLUMN user_id INT UNSIGNED NULL AFTER car_class_id', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @b_guest_name := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='guest_name');
SET @s := IF(@b_guest_name=0, 'ALTER TABLE bookings ADD COLUMN guest_name VARCHAR(150) NULL AFTER user_id', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @b_guest_email := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='guest_email');
SET @s := IF(@b_guest_email=0, 'ALTER TABLE bookings ADD COLUMN guest_email VARCHAR(150) NULL AFTER guest_name', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @b_guest_phone := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='guest_phone');
SET @s := IF(@b_guest_phone=0, 'ALTER TABLE bookings ADD COLUMN guest_phone VARCHAR(50) NULL AFTER guest_email', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Backfill guest fields from existing full_name/email/phone
UPDATE bookings SET guest_name = full_name, guest_email = email, guest_phone = phone WHERE guest_name IS NULL;

-- weroad_bookings (tours)
SET @w_user := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_bookings' AND COLUMN_NAME='user_id');
SET @s := IF(@w_user=0, 'ALTER TABLE weroad_bookings ADD COLUMN user_id INT UNSIGNED NULL AFTER departure_id', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @w_guest_name := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_bookings' AND COLUMN_NAME='guest_name');
SET @s := IF(@w_guest_name=0, 'ALTER TABLE weroad_bookings ADD COLUMN guest_name VARCHAR(150) NULL AFTER user_id', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @w_guest_email := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_bookings' AND COLUMN_NAME='guest_email');
SET @s := IF(@w_guest_email=0, 'ALTER TABLE weroad_bookings ADD COLUMN guest_email VARCHAR(150) NULL AFTER guest_name', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @w_guest_phone := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_bookings' AND COLUMN_NAME='guest_phone');
SET @s := IF(@w_guest_phone=0, 'ALTER TABLE weroad_bookings ADD COLUMN guest_phone VARCHAR(50) NULL AFTER guest_email', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE weroad_bookings SET guest_name = full_name, guest_email = email, guest_phone = phone WHERE guest_name IS NULL;

-- ============================================================
-- 15) DEFAULT ADMIN USER (super_admin, password: Admin@12345)
--     Change this immediately after first login.
--     The hash below is a valid bcrypt hash of "Admin@12345".
-- ============================================================
INSERT INTO users (first_name, last_name, email, phone, preferred_language, password_hash, role_id, status, email_verified_at)
SELECT 'GAIA', 'Admin', 'admin@gaiatours.com', '+962790000000', 'en',
'$2y$10$MGdek90Hm92g/8e8NCqiiOib0kuASU.Yh3R7.z0sHBYPn/iAVgneW',
       (SELECT id FROM roles WHERE slug = 'super_admin'), 'active', NOW()
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@gaiatours.com');

-- ============================================================
-- 16) AUTH / ACCOUNT TRANSLATIONS (EN/AR)
-- ============================================================
INSERT IGNORE INTO translations (`key`, lang, `value`, `group`) VALUES
-- ---- AUTH GENERAL ----
('auth.login', 'en', 'Login', 'auth'),
('auth.login', 'ar', 'تسجيل الدخول', 'auth'),
('auth.register', 'en', 'Register', 'auth'),
('auth.register', 'ar', 'إنشاء حساب', 'auth'),
('auth.logout', 'en', 'Logout', 'auth'),
('auth.logout', 'ar', 'تسجيل الخروج', 'auth'),
('auth.forgot_password', 'en', 'Forgot password?', 'auth'),
('auth.forgot_password', 'ar', 'نسيت كلمة المرور؟', 'auth'),
('auth.email', 'en', 'Email address', 'auth'),
('auth.email', 'ar', 'البريد الإلكتروني', 'auth'),
('auth.password', 'en', 'Password', 'auth'),
('auth.password', 'ar', 'كلمة المرور', 'auth'),
('auth.confirm_password', 'en', 'Confirm password', 'auth'),
('auth.confirm_password', 'ar', 'تأكيد كلمة المرور', 'auth'),
('auth.first_name', 'en', 'First name', 'auth'),
('auth.first_name', 'ar', 'الاسم الأول', 'auth'),
('auth.last_name', 'en', 'Last name', 'auth'),
('auth.last_name', 'ar', 'اسم العائلة', 'auth'),
('auth.phone', 'en', 'Phone', 'auth'),
('auth.phone', 'ar', 'الهاتف', 'auth'),
('auth.country', 'en', 'Country', 'auth'),
('auth.country', 'ar', 'الدولة', 'auth'),
('auth.preferred_language', 'en', 'Preferred language', 'auth'),
('auth.preferred_language', 'ar', 'اللغة المفضلة', 'auth'),
('auth.remember_me', 'en', 'Remember me', 'auth'),
('auth.remember_me', 'ar', 'تذكرني', 'auth'),
('auth.submit', 'en', 'Submit', 'auth'),
('auth.submit', 'ar', 'إرسال', 'auth'),
('auth.no_account', 'en', 'Don''t have an account?', 'auth'),
('auth.no_account', 'ar', 'ليس لديك حساب؟', 'auth'),
('auth.have_account', 'en', 'Already have an account?', 'auth'),
('auth.have_account', 'ar', 'لديك حساب بالفعل؟', 'auth'),
('auth.invalid_credentials', 'en', 'Invalid email or password.', 'auth'),
('auth.invalid_credentials', 'ar', 'بريد إلكتروني أو كلمة مرور غير صحيحة.', 'auth'),
('auth.account_inactive', 'en', 'Your account is inactive or suspended.', 'auth'),
('auth.account_inactive', 'ar', 'حسابك غير نشط أو موقوف.', 'auth'),
('auth.email_required', 'en', 'A valid email is required.', 'auth'),
('auth.email_required', 'ar', 'يجب إدخال بريد إلكتروني صحيح.', 'auth'),
('auth.password_required', 'en', 'Password is required.', 'auth'),
('auth.password_required', 'ar', 'كلمة المرور مطلوبة.', 'auth'),
('auth.password_min', 'en', 'Password must be at least 8 characters.', 'auth'),
('auth.password_min', 'ar', 'يجب أن تكون كلمة المرور 8 أحرف على الأقل.', 'auth'),
('auth.password_mismatch', 'en', 'Passwords do not match.', 'auth'),
('auth.password_mismatch', 'ar', 'كلمتا المرور غير متطابقتين.', 'auth'),
('auth.email_taken', 'en', 'This email is already registered.', 'auth'),
('auth.email_taken', 'ar', 'هذا البريد الإلكتروني مسجل بالفعل.', 'auth'),
('auth.register_success', 'en', 'Your account has been created. Welcome!', 'auth'),
('auth.register_success', 'ar', 'تم إنشاء حسابك بنجاح. مرحباً بك!', 'auth'),
('auth.login_success', 'en', 'Welcome back!', 'auth'),
('auth.login_success', 'ar', 'مرحباً بعودتك!', 'auth'),
('auth.logout_success', 'en', 'You have been logged out.', 'auth'),
('auth.logout_success', 'ar', 'تم تسجيل خروجك.', 'auth'),
('auth.too_many_attempts', 'en', 'Too many login attempts. Please try again later.', 'auth'),
('auth.too_many_attempts', 'ar', 'محاولات تسجيل دخول كثيرة. حاول مرة أخرى لاحقاً.', 'auth'),
('auth.reset_link_sent', 'en', 'If that email exists, a reset link has been sent.', 'auth'),
('auth.reset_link_sent', 'ar', 'إذا كان البريد الإلكتروني موجوداً، فقد تم إرسال رابط إعادة التعيين.', 'auth'),
('auth.invalid_reset_token', 'en', 'This reset link is invalid or has expired.', 'auth'),
('auth.invalid_reset_token', 'ar', 'رابط إعادة التعيين هذا غير صالح أو منتهي.', 'auth'),
('auth.reset_password_title', 'en', 'Reset password', 'auth'),
('auth.reset_password_title', 'ar', 'إعادة تعيين كلمة المرور', 'auth'),
('auth.reset_success', 'en', 'Your password has been reset. Please login.', 'auth'),
('auth.reset_success', 'ar', 'تم إعادة تعيين كلمة المرور. يرجى تسجيل الدخول.', 'auth'),

-- ---- ACCOUNT ----
('account.dashboard', 'en', 'My Account', 'account'),
('account.dashboard', 'ar', 'حسابي', 'account'),
('account.profile', 'en', 'Profile', 'account'),
('account.profile', 'ar', 'الملف الشخصي', 'account'),
('account.my_bookings', 'en', 'My Bookings', 'account'),
('account.my_bookings', 'ar', 'حجوزاتي', 'account'),
('account.claim_booking', 'en', 'Claim a Booking', 'account'),
('account.claim_booking', 'ar', 'استعادة حجز', 'account'),
('account.welcome', 'en', 'Welcome, {name}', 'account'),
('account.welcome', 'ar', 'مرحباً، {name}', 'account'),
('account.profile_summary', 'en', 'Profile summary', 'account'),
('account.profile_summary', 'ar', 'ملخص الملف الشخصي', 'account'),
('account.recent_bookings', 'en', 'Recent bookings', 'account'),
('account.recent_bookings', 'ar', 'الحجوزات الأخيرة', 'account'),
('account.upcoming_bookings', 'en', 'Upcoming bookings', 'account'),
('account.upcoming_bookings', 'ar', 'الحجوزات القادمة', 'account'),
('account.account_status', 'en', 'Account status', 'account'),
('account.account_status', 'ar', 'حالة الحساب', 'account'),
('account.active', 'en', 'Active', 'account'),
('account.active', 'ar', 'نشط', 'account'),
('account.inactive', 'en', 'Inactive', 'account'),
('account.inactive', 'ar', 'غير نشط', 'account'),
('account.no_bookings', 'en', 'You have no bookings yet.', 'account'),
('account.no_bookings', 'ar', 'لا توجد حجوزات بعد.', 'account'),
('account.profile_updated', 'en', 'Your profile has been updated.', 'account'),
('account.profile_updated', 'ar', 'تم تحديث ملفك الشخصي.', 'account'),
('account.save_changes', 'en', 'Save changes', 'account'),
('account.save_changes', 'ar', 'حفظ التغييرات', 'account'),
('account.avatar', 'en', 'Avatar', 'account'),
('account.avatar', 'ar', 'الصورة الرمزية', 'account'),
('account.booking_code', 'en', 'Booking code', 'account'),
('account.booking_code', 'ar', 'رمز الحجز', 'account'),
('account.status', 'en', 'Status', 'account'),
('account.status', 'ar', 'الحالة', 'account'),
('account.total', 'en', 'Total', 'account'),
('account.total', 'ar', 'الإجمالي', 'account'),
('account.type', 'en', 'Type', 'account'),
('account.type', 'ar', 'النوع', 'account'),
('account.date', 'en', 'Date', 'account'),
('account.date', 'ar', 'التاريخ', 'account'),
('account.claim_title', 'en', 'Claim a guest booking', 'account'),
('account.claim_title', 'ar', 'استعادة حجز ضيف', 'account'),
('account.claim_text', 'en', 'Enter the email and booking code from your guest confirmation to attach it to your account.', 'account'),
('account.claim_text', 'ar', 'أدخل البريد الإلكتروني ورمز الحجز من تأكيد ضيفك لربطه بحسابك.', 'account'),
('account.claim_success', 'en', 'Booking {code} has been attached to your account.', 'account'),
('account.claim_success', 'ar', 'تم ربط الحجز {code} بحسابك.', 'account'),
('account.claim_not_found', 'en', 'No matching guest booking was found.', 'account'),
('account.claim_not_found', 'ar', 'لم يتم العثور على حجز ضيف مطابق.', 'account'),
('account.booking_type_transfer', 'en', 'Transfer', 'account'),
('account.booking_type_transfer', 'ar', 'نقل', 'account'),
('account.booking_type_tour', 'en', 'Tour', 'account'),
('account.booking_type_tour', 'ar', 'جولة', 'account'),
('account.booking_type_hotel', 'en', 'Hotel', 'account'),
('account.booking_type_hotel', 'ar', 'فندق', 'account'),
('account.booking_type_event', 'en', 'Event', 'account'),
('account.booking_type_event', 'ar', 'فعالية', 'account'),
('account.booking_type_taxi', 'en', 'Taxi', 'account'),
('account.booking_type_taxi', 'ar', 'تاكسي', 'account'),
('account.status_pending', 'en', 'Pending', 'account'),
('account.status_pending', 'ar', 'قيد الانتظار', 'account'),
('account.status_confirmed', 'en', 'Confirmed', 'account'),
('account.status_confirmed', 'ar', 'مؤكد', 'account'),
('account.status_cancelled', 'en', 'Cancelled', 'account'),
('account.status_cancelled', 'ar', 'ملغي', 'account'),
('account.booking_summary', 'en', 'Booking summary', 'account'),
('account.booking_summary', 'ar', 'ملخص الحجز', 'account'),
('account.role_customer', 'en', 'Customer', 'account'),
('account.role_customer', 'ar', 'عميل', 'account'),
('account.role_hotel_manager', 'en', 'Hotel Manager', 'account'),
('account.role_hotel_manager', 'ar', 'مدير فندق', 'account'),
('account.role_super_admin', 'en', 'Super Admin', 'account'),
('account.role_super_admin', 'ar', 'مدير النظام', 'account');

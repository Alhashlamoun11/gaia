-- ============================================================
-- GAIA TOURS & TRAVEL
-- Phase 0: Hotel Partner Onboarding & Role Separation
-- Schema migration #18
-- ============================================================
USE gaia_tours;

-- ------------------------------------------------------------
-- 1) ADMIN ROLE
-- ------------------------------------------------------------
INSERT INTO roles (name, slug) VALUES ('Admin', 'admin')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ------------------------------------------------------------
-- 2) PARTNER APPLICATIONS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS partner_applications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference_code VARCHAR(20) NOT NULL UNIQUE,
  user_id INT UNSIGNED NULL,
  `type` VARCHAR(50) NOT NULL DEFAULT 'hotel',
  `status` ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  
  -- Account Info
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL,
  phone VARCHAR(50) NULL,
  password_hash VARCHAR(255) NULL,
  
  -- Hotel Info
  hotel_name VARCHAR(200) NOT NULL,
  description TEXT NULL,
  address VARCHAR(255) NULL,
  city VARCHAR(120) NULL,
  country VARCHAR(120) NULL,
  hotel_phone VARCHAR(50) NULL,
  hotel_email VARCHAR(150) NULL,
  website VARCHAR(300) NULL,
  check_in_time TIME NULL,
  check_out_time TIME NULL,
  cancellation_policy TEXT NULL,
  facilities TEXT NULL,
  main_image VARCHAR(500) NULL,
  
  -- Admin Review Info
  admin_notes TEXT NULL,
  rejection_reason TEXT NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  CONSTRAINT fk_partner_app_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_partner_app_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL,
  INDEX idx_partner_app_email (email),
  INDEX idx_partner_app_status (status)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3) TRANSLATIONS FOR PARTNER FLOW
-- ------------------------------------------------------------
INSERT IGNORE INTO translations (`key`, lang, `value`, `group`) VALUES
('partner.join_title', 'en', 'Join as a Partner', 'partner'),
('partner.join_title', 'ar', 'انضم كشريك', 'partner'),
('partner.account_info', 'en', 'Account Information', 'partner'),
('partner.account_info', 'ar', 'معلومات الحساب', 'partner'),
('partner.hotel_info', 'en', 'Hotel Information', 'partner'),
('partner.hotel_info', 'ar', 'معلومات الفندق', 'partner'),
('partner.hotel_name', 'en', 'Hotel Name', 'partner'),
('partner.hotel_name', 'ar', 'اسم الفندق', 'partner'),
('partner.description', 'en', 'Description', 'partner'),
('partner.description', 'ar', 'الوصف', 'partner'),
('partner.address', 'en', 'Address', 'partner'),
('partner.address', 'ar', 'العنوان', 'partner'),
('partner.city', 'en', 'City', 'partner'),
('partner.city', 'ar', 'المدينة', 'partner'),
('partner.country', 'en', 'Country', 'partner'),
('partner.country', 'ar', 'الدولة', 'partner'),
('partner.hotel_phone', 'en', 'Hotel Phone', 'partner'),
('partner.hotel_phone', 'ar', 'هاتف الفندق', 'partner'),
('partner.hotel_email', 'en', 'Hotel Email', 'partner'),
('partner.hotel_email', 'ar', 'البريد الإلكتروني للفندق', 'partner'),
('partner.website', 'en', 'Website', 'partner'),
('partner.website', 'ar', 'الموقع الإلكتروني', 'partner'),
('partner.check_in_time', 'en', 'Check-in Time', 'partner'),
('partner.check_in_time', 'ar', 'وقت تسجيل الدخول', 'partner'),
('partner.check_out_time', 'en', 'Check-out Time', 'partner'),
('partner.check_out_time', 'ar', 'وقت تسجيل الخروج', 'partner'),
('partner.cancellation_policy', 'en', 'Cancellation Policy', 'partner'),
('partner.cancellation_policy', 'ar', 'سياسة الإلغاء', 'partner'),
('partner.facilities', 'en', 'Facilities', 'partner'),
('partner.facilities', 'ar', 'المرافق', 'partner'),
('partner.main_image', 'en', 'Main Image', 'partner'),
('partner.main_image', 'ar', 'الصورة الرئيسية', 'partner'),
('partner.apply_success', 'en', 'Your partner application has been submitted. Reference: {ref}', 'partner'),
('partner.apply_success', 'ar', 'تم تقديم طلب الشراكة الخاص بك. المرجع: {ref}', 'partner'),
('admin.partners.applications', 'en', 'Partner Applications', 'admin'),
('admin.partners.applications', 'ar', 'طلبات الشراكة', 'admin');

-- ------------------------------------------------------------
-- 4) AUDIT LOGS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  application_id INT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_user (user_id),
  INDEX idx_audit_app (application_id)
) ENGINE=InnoDB;

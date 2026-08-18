-- ============================================================
-- GAIA TOURS & TRAVEL — Customer Portal
-- Migration #13
-- ------------------------------------------------------------
-- Objective: Add all database structures required by the
-- complete customer portal (dashboard, bookings, payments,
-- invoices, profile, security, notifications, favorites).
--
-- Implements:
--   * users — add city, address, nationality profile columns
--   * user_sessions — recent/current login sessions for the
--     Security page (current session + logout-all placeholder)
--   * notifications — booking / payment / system notifications
--     (DB-ready, NO email sending)
--   * favorites — wishlist placeholder for tours/hotels/events
--   * EN/AR translations for all new customer-portal UI strings
--
-- Safe to run repeatedly (idempotent).
-- ============================================================
USE gaia_tours;

-- ============================================================
-- 1) USERS — additional profile fields
-- ============================================================
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='city');
SET @s := IF(@c=0, 'ALTER TABLE users ADD COLUMN city VARCHAR(120) NULL AFTER country', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='address');
SET @s := IF(@c=0, 'ALTER TABLE users ADD COLUMN address VARCHAR(255) NULL AFTER city', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='nationality');
SET @s := IF(@c=0, 'ALTER TABLE users ADD COLUMN nationality VARCHAR(120) NULL AFTER address', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ============================================================
-- 2) USER SESSIONS (recent/current login sessions)
--    Used by the Security page. session_hash is a SHA-256 of the
--    PHP session id so we can identify the current device without
--    storing the raw session id (mitigates session-hijack risk).
-- ============================================================
CREATE TABLE IF NOT EXISTS user_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  session_hash CHAR(64) NOT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  device VARCHAR(120) NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_activity DATETIME NULL,
  CONSTRAINT fk_us_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  UNIQUE KEY uq_us_hash (session_hash),
  INDEX idx_us_user (user_id),
  INDEX idx_us_last (last_activity)
) ENGINE=InnoDB;

-- ============================================================
-- 3) NOTIFICATIONS (booking / payment / system)
--    DB-ready. No email sending in this phase.
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type ENUM('booking','payment','system') NOT NULL DEFAULT 'system',
  title VARCHAR(190) NOT NULL,
  message TEXT NULL,
  link VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_not_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  INDEX idx_not_user (user_id),
  INDEX idx_not_user_read (user_id, is_read),
  INDEX idx_not_created (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- 4) FAVORITES (wishlist placeholder)
--    item_type: tour | hotel | event  (future expansion)
-- ============================================================
CREATE TABLE IF NOT EXISTS favorites (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  item_type ENUM('tour','hotel','event') NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  UNIQUE KEY uq_fav_user_item (user_id, item_type, item_id),
  INDEX idx_fav_user (user_id),
  INDEX idx_fav_item (item_type, item_id)
) ENGINE=InnoDB;

-- ============================================================
-- 5) CUSTOMER PORTAL TRANSLATIONS (EN/AR)
-- ============================================================
INSERT IGNORE INTO translations (`key`, lang, `value`, `group`) VALUES

-- ---- DASHBOARD ---- 
('account.overview',                 'en', 'Overview', 'account'),
('account.overview',                 'ar', 'نظرة عامة', 'account'),
('account.welcome_back',             'en', 'Welcome back, {name}', 'account'),
('account.welcome_back',             'ar', 'مرحباً بعودتك، {name}', 'account'),
('account.member_since',             'en', 'Member since', 'account'),
('account.member_since',             'ar', 'عضو منذ', 'account'),
('account.latest_bookings',          'en', 'Latest Bookings', 'account'),
('account.latest_bookings',          'ar', 'أحدث الحجوزات', 'account'),
('account.pending_payment',          'en', 'Pending Payment', 'account'),
('account.pending_payment',          'ar', 'دفع معلق', 'account'),
('account.recent_invoices',          'en', 'Recent Invoices', 'account'),
('account.recent_invoices',          'ar', 'الفواتير الأخيرة', 'account'),
('account.view_all',                 'en', 'View all', 'account'),
('account.view_all',                 'ar', 'عرض الكل', 'account'),
('account.book_tour',                'en', 'Book a Tour', 'account'),
('account.book_tour',                'ar', 'احجز جولة', 'account'),
('account.book_hotel',               'en', 'Book a Hotel', 'account'),
('account.book_hotel',               'ar', 'احجز فندقاً', 'account'),
('account.book_transfer',            'en', 'Book a Transfer', 'account'),
('account.book_transfer',            'ar', 'احجز نقل', 'account'),
('account.book_event',               'en', 'Book an Event', 'account'),
('account.book_event',               'ar', 'احجز فعالية', 'account'),
('account.edit_profile',             'en', 'Edit Profile', 'account'),
('account.edit_profile',             'ar', 'تعديل الملف الشخصي', 'account'),
('account.change_password',          'en', 'Change Password', 'account'),
('account.change_password',          'ar', 'تغيير كلمة المرور', 'account'),
('account.booking_history',          'en', 'Booking History', 'account'),
('account.booking_history',          'ar', 'سجل الحجوزات', 'account'),
('account.wishlist',                 'en', 'Wishlist', 'account'),
('account.wishlist',                 'ar', 'قائمة الأمنيات', 'account'),
('account.notification_center',      'en', 'Notification Center', 'account'),
('account.notification_center',      'ar', 'مركز الإشعارات', 'account'),
('account.my_profile',               'en', 'My Profile', 'account'),
('account.my_profile',               'ar', 'ملفي الشخصي', 'account'),
('account.phone',                    'en', 'Phone', 'account'),
('account.phone',                    'ar', 'الهاتف', 'account'),
('account.nationality',              'en', 'Nationality', 'account'),
('account.nationality',              'ar', 'الجنسية', 'account'),
('account.nationality_ph',           'en', 'Nationality', 'account'),
('account.nationality_ph',           'ar', 'الجنسية', 'account'),
('account.city',                     'en', 'City', 'account'),
('account.city',                     'ar', 'المدينة', 'account'),
('account.address',                  'en', 'Address', 'account'),
('account.address',                  'ar', 'العنوان', 'account'),
('account.country',                  'en', 'Country', 'account'),
('account.country',                  'ar', 'الدولة', 'account'),
('account.email_immutable',          'en', 'Email cannot be changed.', 'account'),
('account.email_immutable',          'ar', 'لا يمكن تغيير البريد الإلكتروني.', 'account'),

-- ---- BOOKINGS (cards / tabs / filters / sort) ----
('account.all',                      'en', 'All', 'account'),
('account.all',                      'ar', 'الكل', 'account'),
('account.rooms',                    'en', 'Rooms', 'account'),
('account.rooms',                    'ar', 'الغرف', 'account'),
('account.sort_newest',              'en', 'Newest', 'account'),
('account.sort_newest',              'ar', 'الأحدث', 'account'),
('account.sort_oldest',              'en', 'Oldest', 'account'),
('account.sort_oldest',              'ar', 'الأقدم', 'account'),
('account.sort_highest',             'en', 'Highest price', 'account'),
('account.sort_highest',             'ar', 'الأعلى سعراً', 'account'),
('account.sort_lowest',              'en', 'Lowest price', 'account'),
('account.sort_lowest',              'ar', 'الأقل سعراً', 'account'),
('account.search_booking',           'en', 'Search booking reference', 'account'),
('account.search_booking',           'ar', 'ابحث برقم الحجز', 'account'),
('account.invoice_status',           'en', 'Invoice', 'account'),
('account.invoice_status',           'ar', 'الفاتورة', 'account'),
('account.download_invoice',         'en', 'Download Invoice', 'account'),
('account.download_invoice',         'ar', 'تحميل الفاتورة', 'account'),
('account.cancel_booking',           'en', 'Cancel', 'account'),
('account.cancel_booking',           'ar', 'إلغاء', 'account'),
('account.cancel_confirm',           'en', 'Are you sure you want to cancel this booking?', 'account'),
('account.cancel_confirm',           'ar', 'هل أنت متأكد من رغبتك في إلغاء هذا الحجز؟', 'account'),
('account.cancelled',                'en', 'Cancelled', 'account'),
('account.cancelled',                'ar', 'ملغي', 'account'),
('account.cancel_success',           'en', 'Booking has been cancelled.', 'account'),
('account.cancel_success',           'ar', 'تم إلغاء الحجز.', 'account'),
('account.cancel_failed',            'en', 'Could not cancel this booking.', 'account'),
('account.cancel_failed',            'ar', 'تعذر إلغاء هذا الحجز.', 'account'),
('account.cannot_cancel',            'en', 'This booking cannot be cancelled.', 'account'),
('account.cannot_cancel',            'ar', 'لا يمكن إلغاء هذا الحجز.', 'account'),
('account.no_bookings_found',        'en', 'No bookings match your filters.', 'account'),
('account.no_bookings_found',        'ar', 'لا توجد حجوزات مطابقة لعوامل التصفية.', 'account'),
('account.booking_date_label',       'en', 'Date', 'account'),
('account.booking_date_label',       'ar', 'التاريخ', 'account'),
('account.quick_view',               'en', 'View Details', 'account'),
('account.quick_view',               'ar', 'عرض التفاصيل', 'account'),

-- ---- BOOKING DETAILS (actions) ----
('account.contact_support',          'en', 'Contact Support', 'account'),
('account.contact_support',          'ar', 'اتصل بالدعم', 'account'),
('account.view_hotel',               'en', 'View Hotel', 'account'),
('account.view_hotel',               'ar', 'عرض الفندق', 'account'),
('account.view_tour',                'en', 'View Tour', 'account'),
('account.view_tour',                'ar', 'عرض الجولة', 'account'),
('account.view_transfer',            'en', 'View Transfer', 'account'),
('account.view_transfer',            'ar', 'عرض النقل', 'account'),
('account.view_event',               'en', 'View Event', 'account'),
('account.view_event',               'ar', 'عرض الفعالية', 'account'),
('account.status_history',           'en', 'Status History', 'account'),
('account.status_history',           'ar', 'سجل الحالة', 'account'),
('account.print_booking',            'en', 'Print', 'account'),
('account.print_booking',            'ar', 'طباعة', 'account'),
('account.booking_info',             'en', 'Booking Information', 'account'),
('account.booking_info',             'ar', 'معلومات الحجز', 'account'),
('account.service_info',             'en', 'Service Information', 'account'),
('account.service_info',             'ar', 'معلومات الخدمة', 'account'),

-- ---- PAYMENTS ----
('account.gateway',                  'en', 'Gateway', 'account'),
('account.gateway',                  'ar', 'بوابة الدفع', 'account'),
('account.retry_payment',            'en', 'Retry Payment', 'account'),
('account.retry_payment',            'ar', 'إعادة محاولة الدفع', 'account'),
('account.cybersource_ready',        'en', 'Online payment will be enabled soon via a secure gateway.', 'account'),
('account.cybersource_ready',        'ar', 'سيتم تفعيل الدفع الإلكتروني قريباً عبر بوابة آمنة.', 'account'),
('account.payment_ref',              'en', 'Payment Reference', 'account'),
('account.payment_ref',              'ar', 'مرجع الدفع', 'account'),
('account.no_payment_records',       'en', 'No payment records yet.', 'account'),
('account.no_payment_records',       'ar', 'لا توجد سجلات دفع بعد.', 'account'),

-- ---- INVOICES ----
('account.search_invoice',           'en', 'Search invoices', 'account'),
('account.search_invoice',           'ar', 'ابحث في الفواتير', 'account'),
('account.invoice_number',           'en', 'Invoice Number', 'account'),
('account.invoice_number',           'ar', 'رقم الفاتورة', 'account'),
('account.download_pdf',             'en', 'Download PDF', 'account'),
('account.download_pdf',             'ar', 'تحميل PDF', 'account'),
('account.pdf_placeholder',          'en', 'PDF download will be available soon.', 'account'),
('account.pdf_placeholder',          'ar', 'سيتوفر تحميل PDF قريباً.', 'account'),
('account.no_invoices_found',        'en', 'No invoices found.', 'account'),
('account.no_invoices_found',        'ar', 'لا توجد فواتير.', 'account'),
('account.total_invoices',           'en', 'Total invoices: {count}', 'account'),
('account.total_invoices',           'ar', 'إجمالي الفواتير: {count}', 'account'),

-- ---- SECURITY ----
('account.recent_sessions',          'en', 'Recent Sessions', 'account'),
('account.recent_sessions',          'ar', 'الجلسات الأخيرة', 'account'),
('account.current_session',          'en', 'Current Session', 'account'),
('account.current_session',          'ar', 'الجلسة الحالية', 'account'),
('account.logout_all',               'en', 'Logout All Devices', 'account'),
('account.logout_all',               'ar', 'تسجيل الخروج من جميع الأجهزة', 'account'),
('account.logout_all_placeholder',   'en', 'This will sign you out of every device. Available soon.', 'account'),
('account.logout_all_placeholder',   'ar', 'سيؤدي هذا إلى تسجيل خروجك من جميع الأجهزة. متاح قريباً.', 'account'),
('account.password_strength',        'en', 'Password Strength', 'account'),
('account.password_strength',        'ar', 'قوة كلمة المرور', 'account'),
('account.strength_weak',            'en', 'Weak', 'account'),
('account.strength_weak',            'ar', 'ضعيفة', 'account'),
('account.strength_fair',            'en', 'Fair', 'account'),
('account.strength_fair',            'ar', 'متوسطة', 'account'),
('account.strength_good',            'en', 'Good', 'account'),
('account.strength_good',            'ar', 'جيدة', 'account'),
('account.strength_strong',          'en', 'Strong', 'account'),
('account.strength_strong',          'ar', 'قوية', 'account'),
('account.strength_very_strong',     'en', 'Very Strong', 'account'),
('account.strength_very_strong',     'ar', 'قوية جداً', 'account'),
('account.ip_address',               'en', 'IP Address', 'account'),
('account.ip_address',               'ar', 'عنوان IP', 'account'),
('account.device',                   'en', 'Device', 'account'),
('account.device',                   'ar', 'الجهاز', 'account'),
('account.last_active',              'en', 'Last Active', 'account'),
('account.last_active',              'ar', 'آخر نشاط', 'account'),
('account.this_device',              'en', 'This device', 'account'),
('account.this_device',              'ar', 'هذا الجهاز', 'account'),
('account.signed_out',               'en', 'Signed out', 'account'),
('account.signed_out',               'ar', 'تم تسجيل الخروج', 'account'),

-- ---- NOTIFICATIONS ----
('account.notifications',            'en', 'Notifications', 'account'),
('account.notifications',            'ar', 'الإشعارات', 'account'),
('account.notification_center',      'en', 'Notification Center', 'account'),
('account.notification_center',      'ar', 'مركز الإشعارات', 'account'),
('account.mark_all_read',            'en', 'Mark all as read', 'account'),
('account.mark_all_read',            'ar', 'تحديد الكل كمقروء', 'account'),
('account.unread',                   'en', 'Unread', 'account'),
('account.unread',                   'ar', 'غير مقروء', 'account'),
('account.no_notifications',         'en', 'You have no notifications yet.', 'account'),
('account.no_notifications',         'ar', 'لا توجد إشعارات بعد.', 'account'),
('account.notif_booking',            'en', 'Booking Updates', 'account'),
('account.notif_booking',            'ar', 'تحديثات الحجز', 'account'),
('account.notif_payment',            'en', 'Payment Updates', 'account'),
('account.notif_payment',            'ar', 'تحديثات الدفع', 'account'),
('account.notif_system',             'en', 'System Notifications', 'account'),
('account.notif_system',             'ar', 'إشعارات النظام', 'account'),
('account.marked_all_read',          'en', 'All notifications marked as read.', 'account'),
('account.marked_all_read',          'ar', 'تم تحديد جميع الإشعارات كمقروءة.', 'account'),

-- ---- FAVORITES ----
('account.favorites',                'en', 'Favorites', 'account'),
('account.favorites',                'ar', 'المفضلة', 'account'),
('account.total_favorites',          'en', 'Total favorites: {count}', 'account'),
('account.total_favorites',          'ar', 'إجمالي المفضلة: {count}', 'account'),
('account.delete',                   'en', 'Delete', 'account'),
('account.delete',                   'ar', 'حذف', 'account'),
('account.favorite_tours',           'en', 'Tours', 'account'),
('account.favorite_tours',           'ar', 'الجولات', 'account'),
('account.favorite_hotels',          'en', 'Hotels', 'account'),
('account.favorite_hotels',          'ar', 'الفنادق', 'account'),
('account.favorite_events',          'en', 'Events', 'account'),
('account.favorite_events',          'ar', 'الفعاليات', 'account'),
('account.no_favorites',             'en', 'You have no favorites yet. Save tours, hotels and events you love.', 'account'),
('account.no_favorites',             'ar', 'لا توجد مفضلات بعد. احفظ الجولات والفنادق والفعاليات التي تحبها.', 'account'),
('account.favorites_placeholder',    'en', 'Favorites are ready. Save items and they will appear here.', 'account'),
('account.favorites_placeholder',    'ar', 'المفضلة جاهزة. احفظ العناصر وستظهر هنا.', 'account'),

-- ---- BREADCRUMBS / COMMON ----
('account.home',                     'en', 'Home', 'account'),
('account.home',                     'ar', 'الرئيسية', 'account'),
('account.back',                     'en', 'Back', 'account'),
('account.back',                     'ar', 'رجوع', 'account'),
('account.not_now',                  'en', 'Not now', 'account'),
('account.not_now',                  'ar', 'ليس الآن', 'account'),
('account.you_are_here',             'en', 'You are here:', 'account'),
('account.you_are_here',             'ar', 'أنت هنا:', 'account');

-- ============================================================
-- GAIA TOURS & TRAVEL — Simple Admin Panel (CMS)
-- Schema migration #ADMIN
-- ------------------------------------------------------------
-- Objective: Add the small set of columns the Admin Panel needs
-- that are not yet present in the existing content tables, plus
-- a handful of admin translation keys.
--
-- Safe to run repeatedly (idempotent).
-- ============================================================
USE gaia_tours;

-- ------------------------------------------------------------
-- 1) HOTELS — city, country, featured, SEO
-- ------------------------------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotels' AND COLUMN_NAME='city');
SET @s := IF(@c=0, 'ALTER TABLE hotels ADD COLUMN city VARCHAR(120) NULL AFTER destination_id', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotels' AND COLUMN_NAME='country');
SET @s := IF(@c=0, 'ALTER TABLE hotels ADD COLUMN country VARCHAR(120) NULL AFTER city', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotels' AND COLUMN_NAME='featured');
SET @s := IF(@c=0, 'ALTER TABLE hotels ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0 AFTER star_rating', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotels' AND COLUMN_NAME='seo_title');
SET @s := IF(@c=0, 'ALTER TABLE hotels ADD COLUMN seo_title VARCHAR(255) NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotels' AND COLUMN_NAME='seo_description');
SET @s := IF(@c=0, 'ALTER TABLE hotels ADD COLUMN seo_description VARCHAR(500) NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------
-- 2) HOTEL_ROOMS — capacity, availability
-- ------------------------------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_rooms' AND COLUMN_NAME='capacity');
SET @s := IF(@c=0, 'ALTER TABLE hotel_rooms ADD COLUMN capacity INT UNSIGNED NOT NULL DEFAULT 2', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_rooms' AND COLUMN_NAME='availability');
SET @s := IF(@c=0, 'ALTER TABLE hotel_rooms ADD COLUMN availability INT UNSIGNED NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------
-- 3) EVENTS — price, capacity
-- ------------------------------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='price');
SET @s := IF(@c=0, 'ALTER TABLE events ADD COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='capacity');
SET @s := IF(@c=0, 'ALTER TABLE events ADD COLUMN capacity INT UNSIGNED NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------
-- 4) PARTNERS — website
-- ------------------------------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='partners' AND COLUMN_NAME='website');
SET @s := IF(@c=0, 'ALTER TABLE partners ADD COLUMN website VARCHAR(300) NULL AFTER name', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------
-- 5) WEROAD_TRIPS — featured, SEO
-- ------------------------------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_trips' AND COLUMN_NAME='featured');
SET @s := IF(@c=0, 'ALTER TABLE weroad_trips ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_trips' AND COLUMN_NAME='seo_title');
SET @s := IF(@c=0, 'ALTER TABLE weroad_trips ADD COLUMN seo_title VARCHAR(255) NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_trips' AND COLUMN_NAME='seo_description');
SET @s := IF(@c=0, 'ALTER TABLE weroad_trips ADD COLUMN seo_description VARCHAR(500) NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------
-- 6) ADMIN TRANSLATION KEYS (EN only; AR optional)
-- ------------------------------------------------------------
INSERT IGNORE INTO translations (`key`, lang, `value`, `group`) VALUES
('admin.dashboard', 'en', 'Dashboard', 'admin'),
('admin.dashboard', 'ar', 'لوحة التحكم', 'admin'),
('admin.users', 'en', 'Users', 'admin'),
('admin.users', 'ar', 'المستخدمون', 'admin'),
('admin.bookings', 'en', 'Bookings', 'admin'),
('admin.bookings', 'ar', 'الحجوزات', 'admin'),
('admin.hotels', 'en', 'Hotels', 'admin'),
('admin.rooms', 'en', 'Rooms', 'admin'),
('admin.tours', 'en', 'Tours', 'admin'),
('admin.transfers', 'en', 'Transfers', 'admin'),
('admin.events', 'en', 'Events', 'admin'),
('admin.reviews', 'en', 'Reviews', 'admin'),
('admin.faq', 'en', 'FAQ', 'admin'),
('admin.partners', 'en', 'Partners', 'admin'),
('admin.services', 'en', 'Services', 'admin'),
('admin.media', 'en', 'Media', 'admin'),
('admin.settings', 'en', 'Settings', 'admin'),
('admin.profile', 'en', 'Profile', 'admin'),
('admin.logout', 'en', 'Logout', 'admin'),
('admin.view_site', 'en', 'View Site', 'admin'),
('admin.search', 'en', 'Search', 'admin'),
('admin.save', 'en', 'Save', 'admin'),
('admin.cancel', 'en', 'Cancel', 'admin'),
('admin.delete', 'en', 'Delete', 'admin'),
('admin.edit', 'en', 'Edit', 'admin'),
('admin.create', 'en', 'Create', 'admin'),
('admin.actions', 'en', 'Actions', 'admin'),
('admin.status', 'en', 'Status', 'admin'),
('admin.active', 'en', 'Active', 'admin'),
('admin.inactive', 'en', 'Inactive', 'admin'),
('admin.add_new', 'en', 'Add New', 'admin'),
('admin.back_to_list', 'en', 'Back to list', 'admin'),
('admin.no_records', 'en', 'No records found.', 'admin'),
('admin.name', 'en', 'Name', 'admin'),
('admin.title', 'en', 'Title', 'admin'),
('admin.price', 'en', 'Price', 'admin'),
('admin.created', 'en', 'Created', 'admin'),
('admin.updated', 'en', 'Updated', 'admin'),
('admin.image', 'en', 'Image', 'admin'),
('admin.description', 'en', 'Description', 'admin'),
('admin.featured', 'en', 'Featured', 'admin'),
('admin.sort_order', 'en', 'Sort Order', 'admin'),
('admin.website', 'en', 'Website', 'admin'),
('admin.phone', 'en', 'Phone', 'admin'),
('admin.email', 'en', 'Email', 'admin'),
('admin.role', 'en', 'Role', 'admin'),
('admin.revenue', 'en', 'Revenue', 'admin'),
('admin.total_bookings', 'en', 'Total Bookings', 'admin'),
('admin.pending_payments', 'en', 'Pending Payments', 'admin'),
('admin.currency', 'en', 'Currency', 'admin'),
('admin.company_name', 'en', 'Company Name', 'admin'),
('admin.contact_email', 'en', 'Contact Email', 'admin'),
('admin.contact_phone', 'en', 'Contact Phone', 'admin'),
('admin.address', 'en', 'Address', 'admin'),
('admin.logo', 'en', 'Logo', 'admin'),
('admin.social_links', 'en', 'Social Links', 'admin'),
('admin.languages', 'en', 'Languages', 'admin'),
('admin.seo_defaults', 'en', 'SEO Defaults', 'admin'),
('admin.footer_info', 'en', 'Footer Information', 'admin'),
('admin.contact_info', 'en', 'Contact Information', 'admin'),
('admin.google_maps', 'en', 'Google Maps', 'admin'),
('admin.currencies', 'en', 'Currencies', 'admin'),
('admin.change_password', 'en', 'Change Password', 'admin'),
('admin.current_password', 'en', 'Current Password', 'admin'),
('admin.new_password', 'en', 'New Password', 'admin'),
('admin.confirm_password', 'en', 'Confirm Password', 'admin'),
('admin.avatar', 'en', 'Avatar', 'admin'),
('admin.category', 'en', 'Category', 'admin'),
('admin.question', 'en', 'Question', 'admin'),
('admin.answer', 'en', 'Answer', 'admin'),
('admin.icon', 'en', 'Icon', 'admin'),
('admin.capacity', 'en', 'Capacity', 'admin'),
('admin.availability', 'en', 'Availability', 'admin'),
('admin.stars', 'en', 'Stars', 'admin'),
('admin.destination', 'en', 'Destination', 'admin'),
('admin.location', 'en', 'Location', 'admin'),
('admin.date', 'en', 'Date', 'admin'),
('admin.rating', 'en', 'Rating', 'admin'),
('admin.reviewer', 'en', 'Reviewer', 'admin'),
('admin.approve', 'en', 'Approve', 'admin'),
('admin.reject', 'en', 'Reject', 'admin'),
('admin.upload', 'en', 'Upload', 'admin'),
('admin.file_path', 'en', 'File Path', 'admin'),
('admin.alt_text', 'en', 'Alt Text', 'admin'),
('admin.key', 'en', 'Key', 'admin'),
('admin.routes', 'en', 'Routes', 'admin'),
('admin.car_classes', 'en', 'Car Classes', 'admin'),
('admin.transfer_services', 'en', 'Transfer Services', 'admin'),
('admin.locations', 'en', 'Locations', 'admin'),
('admin.extras', 'en', 'Extras', 'admin'),
('admin.origin', 'en', 'Origin', 'admin'),
('admin.destination_field', 'en', 'Destination', 'admin'),
('admin.distance', 'en', 'Distance', 'admin'),
('admin.travel_time', 'en', 'Travel Time', 'admin'),
('admin.base_price', 'en', 'Base Price', 'admin'),
('admin.passenger_capacity', 'en', 'Passenger Capacity', 'admin'),
('admin.luggage_capacity', 'en', 'Luggage Capacity', 'admin'),
('admin.price_multiplier', 'en', 'Price Multiplier', 'admin'),
('admin.unit', 'en', 'Unit', 'admin'),
('admin.booking_reference', 'en', 'Booking Reference', 'admin'),
('admin.customer', 'en', 'Customer', 'admin'),
('admin.service', 'en', 'Service', 'admin'),
('admin.amount', 'en', 'Amount', 'admin'),
('admin.payment_status', 'en', 'Payment Status', 'admin'),
('admin.invoice_status', 'en', 'Invoice Status', 'admin'),
('admin.created_date', 'en', 'Created Date', 'admin'),
('admin.view', 'en', 'View', 'admin'),
('admin.deactivate', 'en', 'Deactivate', 'admin'),
('admin.activate', 'en', 'Activate', 'admin'),
('admin.latest_bookings', 'en', 'Latest Bookings', 'admin'),
('admin.latest_users', 'en', 'Latest Users', 'admin'),
('admin.latest_reviews', 'en', 'Latest Reviews', 'admin'),
('admin.latest_payments', 'en', 'Latest Payments', 'admin'),
('admin.latest_contacts', 'en', 'Latest Contacts', 'admin'),
('admin.approve_review', 'en', 'Review approved.', 'admin'),
('admin.cancel_booking_confirm', 'en', 'Are you sure you want to cancel this booking?', 'admin'),
('admin.delete_confirm', 'en', 'Are you sure you want to delete this record?', 'admin');

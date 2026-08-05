-- ============================================================
-- GAIA TOURS & TRAVEL — Dynamic Detail Pages (Events/Hotels/Rooms)
-- Migration #8
-- ------------------------------------------------------------
-- Enables the four new dynamic detail pages:
--   * events.php?slug=...   (Event Details)
--   * hotel.php?slug=...    (Hotel Details)
--   * room.php?id=...       (Room Details)
--   * tour.php?slug=...     (Tour Details)
--
-- Adds:
--   * slug/organizer/destination/gallery columns to events
--   * location/avg_rating to hotels
--   * slug/capacity/beds/size/facilities/gallery to hotel_rooms
--   * destination_id to weroad_trips
--   * slug to night_offerings (event cards link to events.php)
--   * seo_meta unique fix (page+lang) + new SEO rows (EN/AR)
--   * EN/AR translation keys for all detail-page labels
--   * seed data: clean events, hotel rooms/facilities/offers/reviews
-- Safe to run repeatedly (idempotent).
-- ============================================================
USE gaia_tours;

-- ------------------------------------------------------------
-- 0) FIX seo_meta unique constraint so AR rows can coexist
--    Old: page UNIQUE. New: UNIQUE(page, lang).
-- ------------------------------------------------------------
-- Drop the old single-column unique index on `page` if it still exists
-- (it was created via `page VARCHAR(100) NOT NULL UNIQUE` in migration 1).
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='seo_meta'
  AND NON_UNIQUE=0 AND INDEX_NAME='page');
SET @sql0a := IF(@idx > 0, 'ALTER TABLE seo_meta DROP INDEX `page`', 'SELECT 1');
PREPARE st0 FROM @sql0a; EXECUTE st0; DEALLOCATE PREPARE st0;
SET @sql0b := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='seo_meta'
    AND NON_UNIQUE=0 AND INDEX_NAME='uq_seo_page_lang') = 0,
  'ALTER TABLE seo_meta ADD UNIQUE KEY uq_seo_page_lang (page, lang)',
  'SELECT 1');
PREPARE st0b FROM @sql0b; EXECUTE st0b; DEALLOCATE PREPARE st0b;

-- ------------------------------------------------------------
-- 1) EVENTS — add slug, organizer, destination_id, gallery_urls
-- ------------------------------------------------------------
SET @e_slug := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='slug');
SET @s := IF(@e_slug=0, 'ALTER TABLE events ADD COLUMN slug VARCHAR(200) NULL AFTER title', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @e_org := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='organizer');
SET @s := IF(@e_org=0, 'ALTER TABLE events ADD COLUMN organizer VARCHAR(150) NULL AFTER description', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @e_dest := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='destination_id');
SET @s := IF(@e_dest=0, 'ALTER TABLE events ADD COLUMN destination_id INT UNSIGNED NULL AFTER location', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @e_gal := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='gallery_urls');
SET @s := IF(@e_gal=0, 'ALTER TABLE events ADD COLUMN gallery_urls TEXT NULL AFTER image_url', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Deduplicate events (the 3 seed events were inserted many times).
-- Keep the lowest id per title, delete the rest.
DELETE e1 FROM events e1
  INNER JOIN events e2
    ON e1.title = e2.title AND e1.id > e2.id;

-- Backfill slugs for the now-clean events
UPDATE events SET slug = 'wadi-rum-desert-gala' WHERE title = 'Wadi Rum Desert Gala' AND (slug IS NULL OR slug='');
UPDATE events SET slug = 'amman-rooftop-tasting' WHERE title = 'Amman Rooftop Tasting' AND (slug IS NULL OR slug='');
UPDATE events SET slug = 'petra-by-night'         WHERE title = 'Petra by Night'         AND (slug IS NULL OR slug='');

-- Prevent future duplicate slugs
SET @e_slug_uq := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND NON_UNIQUE=0 AND INDEX_NAME='uq_events_slug');
SET @s := IF(@e_slug_uq=0, 'ALTER TABLE events ADD UNIQUE KEY uq_events_slug (slug)', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Backfill organizers, destinations, galleries, dates
UPDATE events SET organizer = 'GAIA Night Events',            destination_id = 1, date_label = COALESCE(date_label,'Mar 2026 · Wadi Rum'),
  gallery_urls = 'https://images.unsplash.com/photo-1511578314322-379afb476865?w=900&q=80,https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=700&q=80,https://images.unsplash.com/photo-1548786811-dd6e453ccca7?w=700&q=80'
  WHERE slug='wadi-rum-desert-gala';
UPDATE events SET organizer = 'GAIA Night Events',            destination_id = 1, date_label = COALESCE(date_label,'Apr 2026 · Amman'),
  gallery_urls = 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=900&q=80,https://images.unsplash.com/photo-1511578314322-379afb476865?w=700&q=80,https://images.unsplash.com/photo-1502920917128-1aa500764cbd?w=700&q=80'
  WHERE slug='amman-rooftop-tasting';
UPDATE events SET organizer = 'GAIA Tours & Travel',          destination_id = 1, date_label = COALESCE(date_label,'May 2026 · Petra'),
  gallery_urls = 'https://images.unsplash.com/photo-1519823551278-64ac92734fb1?w=900&q=80,https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=700&q=80,https://images.unsplash.com/photo-1539768942893-daf53e448371?w=700&q=80'
  WHERE slug='petra-by-night';

-- Ensure every event has an image fallback
UPDATE events SET image_url = 'https://images.unsplash.com/photo-1511578314322-379afb476865?w=700&q=80' WHERE slug='wadi-rum-desert-gala' AND (image_url IS NULL OR image_url='');
UPDATE events SET image_url = 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=700&q=80' WHERE slug='amman-rooftop-tasting' AND (image_url IS NULL OR image_url='');
UPDATE events SET image_url = 'https://images.unsplash.com/photo-1519823551278-64ac92734fb1?w=700&q=80' WHERE slug='petra-by-night' AND (image_url IS NULL OR image_url='');

-- ------------------------------------------------------------
-- 2) HOTELS — add location, avg_rating; backfill gallery
-- ------------------------------------------------------------
SET @h_loc := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotels' AND COLUMN_NAME='location');
SET @s := IF(@h_loc=0, 'ALTER TABLE hotels ADD COLUMN location VARCHAR(150) NULL AFTER destination_id', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @h_avg := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotels' AND COLUMN_NAME='avg_rating');
SET @s := IF(@h_avg=0, 'ALTER TABLE hotels ADD COLUMN avg_rating DECIMAL(3,1) NOT NULL DEFAULT 0 AFTER star_rating', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE hotels SET location = 'Amman, Jordan',         avg_rating = 4.8,
  gallery_urls = 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=900&q=80,https://images.unsplash.com/photo-1566073771259-6a8506099945?w=700&q=80,https://images.unsplash.com/photo-1582719508461-905c673771fd?w=700&q=80'
  WHERE slug='st-regis-amman';
UPDATE hotels SET location = 'Wadi Rum, Jordan',      avg_rating = 4.6,
  gallery_urls = 'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=900&q=80,https://images.unsplash.com/photo-1518623489648-a173ef7824f3?w=700&q=80,https://images.unsplash.com/photo-1548786811-dd6e453ccca7?w=700&q=80'
  WHERE slug='wadi-rum-bubble-camp';

-- ------------------------------------------------------------
-- 3) HOTEL_ROOMS — add slug, capacity, beds, size_sqm, facilities, gallery_urls
-- ------------------------------------------------------------
SET @r_slug := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_rooms' AND COLUMN_NAME='slug');
SET @s := IF(@r_slug=0, 'ALTER TABLE hotel_rooms ADD COLUMN slug VARCHAR(200) NULL AFTER name', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @r_cap := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_rooms' AND COLUMN_NAME='capacity');
SET @s := IF(@r_cap=0, 'ALTER TABLE hotel_rooms ADD COLUMN capacity INT UNSIGNED NOT NULL DEFAULT 2 AFTER description', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @r_beds := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_rooms' AND COLUMN_NAME='beds');
SET @s := IF(@r_beds=0, 'ALTER TABLE hotel_rooms ADD COLUMN beds VARCHAR(100) NULL AFTER capacity', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @r_size := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_rooms' AND COLUMN_NAME='size_sqm');
SET @s := IF(@r_size=0, 'ALTER TABLE hotel_rooms ADD COLUMN size_sqm INT UNSIGNED NULL AFTER beds', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @r_fac := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_rooms' AND COLUMN_NAME='facilities');
SET @s := IF(@r_fac=0, 'ALTER TABLE hotel_rooms ADD COLUMN facilities TEXT NULL AFTER description', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @r_gal := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_rooms' AND COLUMN_NAME='gallery_urls');
SET @s := IF(@r_gal=0, 'ALTER TABLE hotel_rooms ADD COLUMN gallery_urls TEXT NULL AFTER image_url', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------
-- 4) SEED hotel rooms / facilities / offers / reviews
-- ------------------------------------------------------------
INSERT IGNORE INTO hotel_rooms (hotel_id, slug, name, description, capacity, beds, size_sqm, facilities, price, image_url, gallery_urls, sort_order, is_active) VALUES
(1, 'st-regis-deluxe-room', 'Deluxe King Room', 'An elegant room with premium bedding, a marble bathroom and city skyline views.', 2, '1 King bed', 42, 'Free Wi-Fi,Air conditioning,Flat-screen TV,Minibar,In-room safe,Coffee maker', 289.00, 'https://images.unsplash.com/photo-1566665797739-1674de7a421a?w=700&q=80', 'https://images.unsplash.com/photo-1566665797739-1674de7a421a?w=700&q=80,https://images.unsplash.com/photo-1566073771259-6a8506099945?w=700&q=80', 1, 1),
(1, 'st-regis-premier-suite', 'Premier Suite', 'A spacious suite with a separate living area, dining corner and panoramic Amman views.', 4, '1 King bed + 1 sofa bed', 68, 'Free Wi-Fi,Air conditioning,Room service,Living area,Minibar,Bathtub', 540.00, 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=700&q=80', 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=700&q=80,https://images.unsplash.com/photo-1590490360182-c33d57733427?w=700&q=80', 2, 1),
(2, 'bubble-junior-bubble', 'Junior Bubble Suite', 'A transparent bubble suite with a comfortable double bed and a private terrace under the stars.', 2, '1 Double bed', 24, 'Panoramic view,Heating,Private terrace,En-suite shower', 145.00, 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=700&q=80', 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=700&q=80,https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=700&q=80', 1, 1),
(2, 'bubble-family-tent', 'Family Desert Tent', 'A spacious bedouin-style tent with two double beds, shared bathroom and a desert-view deck.', 5, '2 Double beds', 40, 'Desert view,Heating,Shared bathroom,Deck,Tea set', 210.00, 'https://images.unsplash.com/photo-1508873696983-2dfd5898f08b?w=700&q=80', 'https://images.unsplash.com/photo-1508873696983-2dfd5898f08b?w=700&q=80,https://images.unsplash.com/photo-1548786811-dd6e453ccca7?w=700&q=80', 2, 1);

-- Backfill any missing room slugs
UPDATE hotel_rooms SET slug = LOWER(REPLACE(name,' ','-')) WHERE (slug IS NULL OR slug='');

INSERT IGNORE INTO hotel_facilities (hotel_id, name, icon, sort_order) VALUES
(1, 'Free Wi-Fi', 'fa-solid fa-wifi', 1),
(1, 'Swimming Pool', 'fa-solid fa-water-ladder', 2),
(1, 'Spa & Fitness', 'fa-solid fa-spa', 3),
(1, '24/7 Room Service', 'fa-solid fa-concierge-bell', 4),
(1, 'Restaurant', 'fa-solid fa-utensils', 5),
(1, 'Valet Parking', 'fa-solid fa-square-parking', 6),
(2, 'Desert Views', 'fa-solid fa-mountain-sun', 1),
(2, 'Stargazing Deck', 'fa-solid fa-star', 2),
(2, 'Bedouin Dinner', 'fa-solid fa-fire', 3),
(2, '4x4 Tours Desk', 'fa-solid fa-car', 4),
(2, 'Free Breakfast', 'fa-solid fa-mug-saucer', 5);

INSERT IGNORE INTO hotel_offers (hotel_id, title, description, price, image_url, sort_order, is_active) VALUES
(1, 'Weekend Escape', 'Stay 2 nights and enjoy a complimentary spa treatment and late checkout.', 489.00, 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=700&q=80', 1, 1),
(1, 'Suite Upgrade Package', 'Includes a Premier Suite upgrade, airport transfer and a welcome dinner for two.', 720.00, 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=700&q=80', 2, 1),
(2, 'Desert Night Stay', 'Overnight bubble stay with a private Bedouin dinner and sunrise 4x4 tour.', 260.00, 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=700&q=80', 1, 1),
(2, 'Stargazing Package', 'Bubble suite, astronomer-led stargazing and telescope hire for the evening.', 190.00, 'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=700&q=80', 2, 1);

INSERT IGNORE INTO hotel_reviews (hotel_id, reviewer, rating, text, date_label, is_active) VALUES
(1, 'Sara M.', 5, 'Impeccable service and a stunning view over Amman. The room was spotless and the staff went above and beyond.', 'Aug 2026', 1),
(1, 'Omar K.', 4, 'Beautiful hotel with an excellent location. Breakfast was world-class, rooms are large and comfortable.', 'Jul 2026', 1),
(1, 'Elena R.', 5, 'The spa and pool are fantastic. A truly luxurious stay in the heart of the city.', 'Jun 2026', 1),
(2, 'Luca B.', 5, 'Sleeping under the stars was unforgettable. The staff arranged a perfect Bedouin dinner for us.', 'Sep 2026', 1),
(2, 'Yara H.', 4, 'A magical desert experience. Bubble was cozy and the stargazing deck is a highlight.', 'Aug 2026', 1);

-- ------------------------------------------------------------
-- 5) WEROAD_TRIPS — add destination_id; backfill
-- ------------------------------------------------------------
SET @t_dest := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_trips' AND COLUMN_NAME='destination_id');
SET @s := IF(@t_dest=0, 'ALTER TABLE weroad_trips ADD COLUMN destination_id INT UNSIGNED NULL AFTER category', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE weroad_trips SET destination_id = 1 WHERE slug='jordan-360';
UPDATE weroad_trips SET destination_id = 2 WHERE slug='bali-explorer';
UPDATE weroad_trips SET destination_id = 3 WHERE slug='morocco-discovery';
UPDATE weroad_trips SET destination_id = 4 WHERE slug='iceland-ring-road';
UPDATE weroad_trips SET destination_id = 5 WHERE slug='vietnam-cambodia';
UPDATE weroad_trips SET destination_id = 6 WHERE slug='peru-machu-picchu';

-- ------------------------------------------------------------
-- 6) NIGHT_OFFERINGS — add slug for event cards
-- ------------------------------------------------------------
SET @n_slug := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='night_offerings' AND COLUMN_NAME='slug');
SET @s := IF(@n_slug=0, 'ALTER TABLE night_offerings ADD COLUMN slug VARCHAR(200) NULL AFTER title', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE night_offerings SET slug = 'wadi-rum-desert-gala' WHERE type='event' AND title='Desert Gala';
UPDATE night_offerings SET slug = 'amman-rooftop-tasting' WHERE type='event' AND title='Rooftop Tasting';
UPDATE night_offerings SET slug = 'petra-by-night'       WHERE type='event' AND title='Petra Candle Walk';

-- ------------------------------------------------------------
-- 7) SEO META — new pages (EN/AR)
-- ------------------------------------------------------------
INSERT IGNORE INTO seo_meta (page, lang, title, meta_description, keywords, og_image, is_active) VALUES
('events', 'en', 'Events — GAIA Tours & Travel', 'Explore GAIA events: desert galas, rooftop tastings and Petra by night across Jordan.', 'gaia, events, jordan, gala, tasting', NULL, 1),
('events', 'ar', 'الفعاليات — غايا جولات وسفر', 'استكشف فعاليات غايا: حفلات الصحراء والتذوق على الأسطح والبتراء ليلاً في الأردن.', 'غايا, فعاليات, الأردن', NULL, 1),
('event_detail', 'en', 'Event — GAIA Tours & Travel', 'Discover a GAIA event: details, gallery, organizer and related tours.', 'gaia, event, jordan', NULL, 1),
('event_detail', 'ar', 'الفعالية — غايا جولات وسفر', 'اكتشف فعالية غايا: التفاصيل والمعرض والمنظم والجولات ذات الصلة.', 'غايا, فعالية, الأردن', NULL, 1),
('hotels', 'en', 'Hotels — GAIA Tours & Travel', 'Stay in style with GAIA hotels across Jordan: luxury stays and desert bubbles.', 'gaia, hotels, jordan, luxury', NULL, 1),
('hotels', 'ar', 'الفنادق — غايا جولات وسفر', 'أقم بأناقة مع فنادق غايا في الأردن: إقامات فاخرة وفقاعات صحراوية.', 'غايا, فنادق, الأردن', NULL, 1),
('hotel_detail', 'en', 'Hotel — GAIA Tours & Travel', 'Hotel details, rooms, facilities, offers and reviews with GAIA.', 'gaia, hotel, jordan, rooms', NULL, 1),
('hotel_detail', 'ar', 'الفندق — غايا جولات وسفر', 'تفاصيل الفندق والغرف والمرافق والعروض والتقييمات مع غايا.', 'غايا, فندق, الأردن, غرف', NULL, 1),
('room_detail', 'en', 'Room — GAIA Tours & Travel', 'Room details, capacity, beds, size, price and facilities.', 'gaia, room, hotel, booking', NULL, 1),
('room_detail', 'ar', 'الغرفة — غايا جولات وسفر', 'تفاصيل الغرفة والسعة والأسرّة والمساحة والسعر والمرافق.', 'غايا, غرفة, فندق, حجز', NULL, 1),
('tour_detail_root', 'en', 'Tour — GAIA Tours & Travel', 'Explore a GAIA group tour: itinerary, inclusions, departures and reviews.', 'gaia tours, trip, group tour', NULL, 1),
('tour_detail_root', 'ar', 'الجولة — غايا جولات وسفر', 'استكشف جولة غايا الجماعية: خط السير والمشمولات والمغادرات والتقييمات.', 'غايا جولات, جولة', NULL, 1);

-- ------------------------------------------------------------
-- 8) TRANSLATIONS (EN/AR) for detail pages
-- ------------------------------------------------------------
INSERT IGNORE INTO translations (`key`, lang, `value`, `group`) VALUES
-- ---- BREAKDOWN / BREADCRUMB ----
('detail.home', 'en', 'Home', 'detail'),
('detail.home', 'ar', 'الرئيسية', 'detail'),
('detail.events', 'en', 'Events', 'detail'),
('detail.events', 'ar', 'الفعاليات', 'detail'),
('detail.hotels', 'en', 'Hotels', 'detail'),
('detail.hotels', 'ar', 'الفنادق', 'detail'),
('detail.tours', 'en', 'Tours', 'detail'),
('detail.tours', 'ar', 'الجولات', 'detail'),
('detail.not_found', 'en', 'Not Found', 'detail'),
('detail.not_found', 'ar', 'غير موجود', 'detail'),
('detail.not_found_text', 'en', 'The page you are looking for does not exist or has been removed.', 'detail'),
('detail.not_found_text', 'ar', 'الصفحة التي تبحث عنها غير موجودة أو تمت إزالتها.', 'detail'),
('detail.back_to_events', 'en', 'Back to Events', 'detail'),
('detail.back_to_events', 'ar', 'العودة إلى الفعاليات', 'detail'),
('detail.back_to_hotels', 'en', 'Back to Hotels', 'detail'),
('detail.back_to_hotels', 'ar', 'العودة إلى الفنادق', 'detail'),
('detail.back_to_tours', 'en', 'Browse Tours', 'detail'),
('detail.back_to_tours', 'ar', 'تصفح الجولات', 'detail'),

-- ---- EVENT ----
('event.date', 'en', 'Date', 'event'),
('event.date', 'ar', 'التاريخ', 'event'),
('event.location', 'en', 'Location', 'event'),
('event.location', 'ar', 'الموقع', 'event'),
('event.organizer', 'en', 'Organized by', 'event'),
('event.organizer', 'ar', 'ينظمه', 'event'),
('event.gallery', 'en', 'Gallery', 'event'),
('event.gallery', 'ar', 'معرض الصور', 'event'),
('event.related_destination', 'en', 'About the Destination', 'event'),
('event.related_destination', 'ar', 'عن الوجهة', 'event'),
('event.related_tours', 'en', 'Tours in this Destination', 'event'),
('event.related_tours', 'ar', 'جولات في هذه الوجهة', 'event'),
('event.book_cta', 'en', 'Reserve Your Spot', 'event'),
('event.book_cta', 'ar', 'احجز مقعدك', 'event'),
('event.description', 'en', 'About this Event', 'event'),
('event.description', 'ar', 'عن هذه الفعالية', 'event'),

-- ---- HOTEL ----
('hotel.from', 'en', 'From', 'hotel'),
('hotel.from', 'ar', 'يبدأ من', 'hotel'),
('hotel.per_night', 'en', '/ night', 'hotel'),
('hotel.per_night', 'ar', '/ ليلة', 'hotel'),
('hotel.reviews', 'en', 'reviews', 'hotel'),
('hotel.reviews', 'ar', 'تقييم', 'hotel'),
('hotel.description', 'en', 'About the Hotel', 'hotel'),
('hotel.description', 'ar', 'عن الفندق', 'hotel'),
('hotel.facilities', 'en', 'Hotel Facilities', 'hotel'),
('hotel.facilities', 'ar', 'مرافق الفندق', 'hotel'),
('hotel.gallery', 'en', 'Gallery', 'hotel'),
('hotel.gallery', 'ar', 'معرض الصور', 'hotel'),
('hotel.rooms', 'en', 'Available Rooms', 'hotel'),
('hotel.rooms', 'ar', 'الغرف المتاحة', 'hotel'),
('hotel.offers', 'en', 'Hotel Offers', 'hotel'),
('hotel.offers', 'ar', 'عروض الفندق', 'hotel'),
('hotel.reviews_title', 'en', 'Guest Reviews', 'hotel'),
('hotel.reviews_title', 'ar', 'تقييمات الضيوف', 'hotel'),
('hotel.view_room', 'en', 'View Room', 'hotel'),
('hotel.view_room', 'ar', 'عرض الغرفة', 'hotel'),
('hotel.quick_booking', 'en', 'Quick Booking', 'hotel'),
('hotel.quick_booking', 'ar', 'حجز سريع', 'hotel'),
('hotel.contact', 'en', 'Contact Hotel', 'hotel'),
('hotel.contact', 'ar', 'تواصل مع الفندق', 'hotel'),
('hotel.nearby', 'en', 'Nearby Hotels', 'hotel'),
('hotel.nearby', 'ar', 'فنادق قريبة', 'hotel'),
('hotel.tours_here', 'en', 'Tours in this Destination', 'hotel'),
('hotel.tours_here', 'ar', 'جولات في هذه الوجهة', 'hotel'),
('hotel.book_now', 'en', 'Book Now', 'hotel'),
('hotel.book_now', 'ar', 'احجز الآن', 'hotel'),

-- ---- ROOM ----
('room.capacity', 'en', 'Capacity', 'room'),
('room.capacity', 'ar', 'السعة', 'room'),
('room.beds', 'en', 'Beds', 'room'),
('room.beds', 'ar', 'الأسرّة', 'room'),
('room.size', 'en', 'Room Size', 'room'),
('room.size', 'ar', 'مساحة الغرفة', 'room'),
('room.per_night', 'en', '/ night', 'room'),
('room.per_night', 'ar', '/ ليلة', 'room'),
('room.facilities', 'en', 'Room Facilities', 'room'),
('room.facilities', 'ar', 'مرافق الغرفة', 'room'),
('room.description', 'en', 'About this Room', 'room'),
('room.description', 'ar', 'عن هذه الغرفة', 'room'),
('room.book', 'en', 'Book Room', 'room'),
('room.book', 'ar', 'احجز الغرفة', 'room'),
('room.contact', 'en', 'Contact Us', 'room'),
('room.contact', 'ar', 'اتصل بنا', 'room'),
('room.hotel_info', 'en', 'The Hotel', 'room'),
('room.hotel_info', 'ar', 'الفندق', 'room'),
('room.related_rooms', 'en', 'More Rooms at this Hotel', 'room'),
('room.related_rooms', 'ar', 'غرف أخرى في هذا الفندق', 'room'),
('room.gallery', 'en', 'Room Gallery', 'room'),
('room.gallery', 'ar', 'معرض الغرفة', 'room'),
('room.people', 'en', 'guests', 'room'),
('room.people', 'ar', 'ضيوف', 'room'),

-- ---- TOUR ----
('tour.overview', 'en', 'Overview', 'tour'),
('tour.overview', 'ar', 'نظرة عامة', 'tour'),
('tour.itinerary', 'en', 'Itinerary', 'tour'),
('tour.itinerary', 'ar', 'خط السير', 'tour'),
('tour.included', 'en', 'Included', 'tour'),
('tour.included', 'ar', 'مشمول', 'tour'),
('tour.excluded', 'en', 'Not included', 'tour'),
('tour.excluded', 'ar', 'غير مشمول', 'tour'),
('tour.gallery', 'en', 'Gallery', 'tour'),
('tour.gallery', 'ar', 'معرض الصور', 'tour'),
('tour.optional', 'en', 'Optional Activities', 'tour'),
('tour.optional', 'ar', 'أنشطة اختيارية', 'tour'),
('tour.departures', 'en', 'Upcoming Departures', 'tour'),
('tour.departures', 'ar', 'المغادرات القادمة', 'tour'),
('tour.reviews', 'en', 'Reviews', 'tour'),
('tour.reviews', 'ar', 'التقييمات', 'tour'),
('tour.faq', 'en', 'Frequently Asked Questions', 'tour'),
('tour.faq', 'ar', 'الأسئلة الشائعة', 'tour'),
('tour.related', 'en', 'Related Tours', 'tour'),
('tour.related', 'ar', 'جولات ذات صلة', 'tour'),
('tour.day', 'en', 'Day {day}', 'tour'),
('tour.day', 'ar', 'اليوم {day}', 'tour'),
('tour.duration', 'en', '{days} days', 'tour'),
('tour.duration', 'ar', '{days} أيام', 'tour'),
('tour.difficulty', 'en', '{level} difficulty', 'tour'),
('tour.difficulty', 'ar', 'صعوبة {level}', 'tour'),
('tour.group_size', 'en', 'Max {max} people', 'tour'),
('tour.group_size', 'ar', 'بحد أقصى {max} أشخاص', 'tour'),
('tour.from', 'en', 'From', 'tour'),
('tour.from', 'ar', 'من', 'tour'),
('tour.book_now', 'en', 'Book Now', 'tour'),
('tour.book_now', 'ar', 'احجز الآن', 'tour'),
('tour.available', 'en', 'Available', 'tour'),
('tour.available', 'ar', 'متاح', 'tour'),
('tour.next_departure', 'en', 'Next Departure', 'tour'),
('tour.next_departure', 'ar', 'المغادرة القادمة', 'tour'),
('tour.spots_left', 'en', '{spots} seats left', 'tour'),
('tour.spots_left', 'ar', 'تبقى {spots} مقاعد', 'tour'),
('tour.rating', 'en', '{rating} ({count} reviews)', 'tour'),
('tour.rating', 'ar', '{rating} ({count} تقييم)', 'tour'),
('tour.optional_price', 'en', 'Approx. ${price} per person', 'tour'),
('tour.optional_price', 'ar', 'تقريبًا ${price} لكل شخص', 'tour'),
('tour.browse', 'en', 'Browse all tours', 'tour'),
('tour.browse', 'ar', 'تصفح جميع الجولات', 'tour'),
('tour.contact', 'en', 'Ask an expert', 'tour'),
('tour.contact', 'ar', 'اسأل خبيرًا', 'tour'),
('tour.reviews_title', 'en', 'What travellers say', 'tour'),
('tour.reviews_title', 'ar', 'ماذا يقول المسافرون', 'tour'),
('tour.departures_sub', 'en', 'Pick a date that fits your plans.', 'tour'),
('tour.departures_sub', 'ar', 'اختر تاريخًا يناسب خططك.', 'tour');

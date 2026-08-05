-- ============================================================
-- GAIA TOURS & TRAVEL — Multilingual Wiring + Link Audit
-- Migration #6
-- ------------------------------------------------------------
-- 1) Fix header/footer menu URLs that point to dead anchors or
--    placeholder pages (Phase 2 link audit).
-- 2) Deactivate social links that use placeholder '#' URLs.
-- 3) Add ALL missing EN/AR translation keys referenced by the
--    public templates (Phase 1 multilingual wiring).
-- Safe to run repeatedly (idempotent).
-- ============================================================
USE gaia_tours;

-- ------------------------------------------------------------
-- 1) HEADER menu fixes (dead #anchors -> existing pages)
-- ------------------------------------------------------------
UPDATE menu_items SET url = 'index.php'              WHERE id = 4  AND url = 'index.php#hotels';      -- Hotels   -> home
UPDATE menu_items SET url = 'route.php'              WHERE id = 5  AND url = 'index.php#transfers';   -- Transfers-> transfer-booking
UPDATE menu_items SET url = 'gaia-night.php'         WHERE id = 7  AND url = 'gaia-night.php#events';  -- Events   -> GAIA Night
UPDATE menu_items SET url = 'page.php?slug=contact'  WHERE id = 9  AND url = 'index.php#contact';     -- Contact  -> contact page

-- ------------------------------------------------------------
-- 2) FOOTER menu fixes
-- ------------------------------------------------------------
UPDATE menu_items SET url = 'page.php?slug=about'   WHERE id = 15 AND url = 'index.php';              -- About GAIA -> about page
UPDATE menu_items SET url = 'index.php'             WHERE id = 16 AND url = 'index.php#reviews';      -- Reviews  -> home
UPDATE menu_items SET url = 'page.php?slug=contact' WHERE id = 17 AND url = 'index.php#contact';      -- Contact  -> contact page
UPDATE menu_items SET url = 'index.php'             WHERE id = 20 AND url = 'index.php#hotels';       -- Hotels   -> home
UPDATE menu_items SET url = 'route.php'             WHERE id = 21 AND url = 'index.php#transfers';    -- Standard Transfers -> transfer-booking
UPDATE menu_items SET url = 'gaia-night.php'        WHERE id = 24 AND url = 'gaia-night.php#vip';     -- VIP Transfers -> GAIA Night
UPDATE menu_items SET url = 'gaia-night.php'        WHERE id = 25 AND url = 'gaia-night.php#luxury';  -- Luxury Experiences -> GAIA Night
UPDATE menu_items SET url = 'gaia-night.php'        WHERE id = 26 AND url = 'gaia-night.php#events';  -- Night Events -> GAIA Night
UPDATE menu_items SET url = 'gaia-night.php'        WHERE id = 27 AND url = 'gaia-night.php#private'; -- Private Tours -> GAIA Night
UPDATE menu_items SET url = 'route.php'             WHERE id = 29 AND url = 'index.php#transfers';    -- Airport Transfers -> transfer-booking
UPDATE menu_items SET url = 'route.php'             WHERE id = 30 AND url = 'index.php';              -- Find a Route -> transfer-booking
UPDATE menu_items SET url = 'route.php'             WHERE id = 31 AND url = 'checkout.php';           -- Checkout -> transfer-booking (flow: route -> checkout)

-- ------------------------------------------------------------
-- 3) Deactivate placeholder '#' social links (keep WhatsApp)
-- ------------------------------------------------------------
UPDATE social_links SET is_active = 0 WHERE url = '#' AND is_active = 1;

-- ------------------------------------------------------------
-- 4) MISSING TRANSLATIONS (EN/AR)
-- ------------------------------------------------------------
INSERT IGNORE INTO translations (`key`, lang, `value`, `group`) VALUES
-- ============ HOME ============
('home.hero_title', 'en', 'Airport transfers.<br>Simpler than ever.', 'home'),
('home.hero_title', 'ar', 'نقل من المطار.<br>أبسط من أي وقت مضى.', 'home'),
('home.hero_sub', 'en', 'Private airport transfers and car rentals with transparent pricing.', 'home'),
('home.hero_sub', 'ar', 'نقل خاص من المطار وتأجير سيارات بأسعار شفافة.', 'home'),
('home.stat_rides', 'en', 'Completed rides', 'home'),
('home.stat_rides', 'ar', 'رحلة مكتملة', 'home'),
('home.stat_countries', 'en', 'Countries served', 'home'),
('home.stat_countries', 'ar', 'دولة نخدمها', 'home'),
('home.search_from', 'en', 'From', 'home'),
('home.search_from', 'ar', 'من', 'home'),
('home.search_to', 'en', 'To', 'home'),
('home.search_to', 'ar', 'إلى', 'home'),
('home.search_date', 'en', 'Date', 'home'),
('home.search_date', 'ar', 'التاريخ', 'home'),
('home.search_passengers', 'en', 'Passengers', 'home'),
('home.search_passengers', 'ar', 'الركاب', 'home'),
('home.search_origin_ph', 'en', 'Airport or city', 'home'),
('home.search_origin_ph', 'ar', 'مطار أو مدينة', 'home'),
('home.search_dest_ph', 'en', 'Destination', 'home'),
('home.search_dest_ph', 'ar', 'الوجهة', 'home'),
('home.search_btn', 'en', 'Search', 'home'),
('home.search_btn', 'ar', 'بحث', 'home'),
('home.cookie_policy', 'en', 'Read our Cookie Policy', 'home'),
('home.cookie_policy', 'ar', 'اقرأ سياسة ملفات الارتباط', 'home'),
('home.reviews_from', 'en', 'from {count} reviews', 'home'),
('home.reviews_from', 'ar', 'من {count} تقييم', 'home'),
('home.multiplier', 'en', 'multiplier', 'home'),
('home.multiplier', 'ar', 'مضاعف', 'home'),
('home.section.news_title', 'en', 'Join our Community', 'home'),
('home.section.news_title', 'ar', 'انضم إلى مجتمعنا', 'home'),
('home.section.news_sub', 'en', 'Exclusive offers and insider travel tips', 'home'),
('home.section.news_sub', 'ar', 'عروض حصرية ونصائح سفر داخلية', 'home'),
('home.section.news_placeholder', 'en', 'Your email', 'home'),
('home.section.news_placeholder', 'ar', 'بريدك الإلكتروني', 'home'),
('home.section.news_btn', 'en', 'Subscribe', 'home'),
('home.section.news_btn', 'ar', 'اشتراك', 'home'),
('home.section.news_consent', 'en', 'By clicking Subscribe you consent to the processing of your personal data and agree to the Privacy Policy.', 'home'),
('home.section.news_consent', 'ar', 'بالنقر على اشتراك فإنك توافق على معالجة بياناتك الشخصية وتوافق على سياسة الخصوصية.', 'home'),
('home.section.news_privacy', 'en', 'Privacy Policy', 'home'),
('home.section.news_privacy', 'ar', 'سياسة الخصوصية', 'home'),
('home.section.night_btn', 'en', 'Discover GAIA Night', 'home'),
('home.section.night_btn', 'ar', 'اكتشف غايا نايت', 'home'),

-- ============ GAIA NIGHT ============
('night.hero_title', 'en', 'Jordan''s refined side, <em>after dark.</em>', 'night'),
('night.hero_title', 'ar', 'الجانب الراقي من الأردن، <em>بعد الغروب.</em>', 'night'),
('night.hero_sub', 'en', 'VIP transfers, luxury experiences, night events, private tours and premium packages — delivered by the same trusted GAIA team that runs your day journeys.', 'night'),
('night.hero_sub', 'ar', 'نقل VIP وتجارب فاخرة وفعاليات ليلية وجولات خاصة وباقات فاخرة — يقدمها فريق غايا الموثوق نفسه الذي يدير رحلاتك النهارية.', 'night'),
('night.stat_concierge', 'en', 'Concierge', 'night'),
('night.stat_concierge', 'ar', 'كونسيرج', 'night'),
('night.stat_private', 'en', 'Private &amp; Discreet', 'night'),
('night.stat_private', 'ar', 'خاص وسري', 'night'),
('night.stat_curated', 'en', 'Curated Experiences', 'night'),
('night.stat_curated', 'ar', 'تجارب منتقاة', 'night'),

-- ============ TRANSFERS ============
('transfers.select_date', 'en', 'Select date', 'transfers'),
('transfers.select_date', 'ar', 'اختر التاريخ', 'transfers'),
('transfers.min', 'en', '{min} min', 'transfers'),
('transfers.min', 'ar', '{min} دقيقة', 'transfers'),
('transfers.country_suffix', 'en', '{place}, {country}', 'transfers'),
('transfers.country_suffix', 'ar', '{place}، {country}', 'transfers'),
('transfers.distance_km', 'en', '{distance} km', 'transfers'),
('transfers.distance_km', 'ar', '{distance} كم', 'transfers'),
('transfers.price_from', 'en', 'From ${price}', 'transfers'),
('transfers.price_from', 'ar', 'ابتداءً من ${price}', 'transfers'),
('transfers.no_routes', 'en', 'No routes available.', 'transfers'),
('transfers.no_routes', 'ar', 'لا توجد مسارات متاحة.', 'transfers'),
('transfers.passenger_bags', 'en', '{passengers} passengers · {bags} bags', 'transfers'),
('transfers.passenger_bags', 'ar', '{passengers} ركاب · {bags} حقائب', 'transfers'),

-- ============ CHECKOUT ============
('checkout.flight_number', 'en', 'Flight number', 'checkout'),
('checkout.flight_number', 'ar', 'رقم الرحلة', 'checkout'),
('checkout.flight_placeholder', 'en', 'e.g. AA 0000', 'checkout'),
('checkout.flight_placeholder', 'ar', 'مثال: AA 0000', 'checkout'),
('checkout.flight_hint', 'en', 'According to the ticket', 'checkout'),
('checkout.flight_hint', 'ar', 'وفقًا للتذكرة', 'checkout'),
('checkout.pickup_date', 'en', 'Pickup date', 'checkout'),
('checkout.pickup_date', 'ar', 'تاريخ الاستلام', 'checkout'),
('checkout.arrival_time', 'en', 'Scheduled arrival time', 'checkout'),
('checkout.arrival_time', 'ar', 'وقت الوصول المقرر', 'checkout'),
('checkout.destination_label', 'en', 'Destination (address or hotel name)', 'checkout'),
('checkout.destination_label', 'ar', 'الوجهة (العنوان أو اسم الفندق)', 'checkout'),
('checkout.destination_placeholder', 'en', 'Laguna Hotel', 'checkout'),
('checkout.destination_placeholder', 'ar', 'فندق لاغونا', 'checkout'),
('checkout.name_label', 'en', 'Name and Surname', 'checkout'),
('checkout.name_label', 'ar', 'الاسم واللقب', 'checkout'),
('checkout.name_placeholder', 'en', 'e.g. John Watson', 'checkout'),
('checkout.name_placeholder', 'ar', 'مثال: جون واتسون', 'checkout'),
('checkout.email_label', 'en', 'E-mail', 'checkout'),
('checkout.email_label', 'ar', 'البريد الإلكتروني', 'checkout'),
('checkout.email_placeholder', 'en', 'johnwatson@mail.com', 'checkout'),
('checkout.email_placeholder', 'ar', 'johnwatson@mail.com', 'checkout'),
('checkout.email_hint', 'en', 'We will send a booking confirmation, voucher, and reminder to this e-mail address', 'checkout'),
('checkout.email_hint', 'ar', 'سنرسل تأكيد الحجز والقسيمة والتذكير إلى عنوان البريد الإلكتروني هذا', 'checkout'),
('checkout.phone_label', 'en', 'Phone number', 'checkout'),
('checkout.phone_label', 'ar', 'رقم الهاتف', 'checkout'),
('checkout.phone_placeholder', 'en', '+962 7 9012 3456', 'checkout'),
('checkout.phone_placeholder', 'ar', '+962 7 9012 3456', 'checkout'),
('checkout.phone_hint', 'en', 'We need it for urgent communication with you. It must be available on the day of the transfer', 'checkout'),
('checkout.phone_hint', 'ar', 'نحتاجه للتواصل العاجل معك. يجب أن يكون متاحًا في يوم النقل', 'checkout'),
('checkout.passengers_label', 'en', 'Passengers', 'checkout'),
('checkout.passengers_label', 'ar', 'الركاب', 'checkout'),
('checkout.passengers_hint', 'en', 'Including children and infants', 'checkout'),
('checkout.passengers_hint', 'ar', 'بما في ذلك الأطفال والرضع', 'checkout'),
('checkout.child_seats_hint', 'en', '+${price} per seat', 'checkout'),
('checkout.child_seats_hint', 'ar', '+${price} لكل مقعد', 'checkout'),
('checkout.water_desc', 'en', 'A bottle of still water (0,5l)', 'checkout'),
('checkout.water_desc', 'ar', 'زجاجة مياه معدنية (0.5 لتر)', 'checkout'),
('checkout.water_price', 'en', '+${price}', 'checkout'),
('checkout.water_price', 'ar', '+${price}', 'checkout'),
('checkout.pets_desc', 'en', '+${price}', 'checkout'),
('checkout.pets_desc', 'ar', '+${price}', 'checkout'),
('checkout.women_driver_desc', 'en', '+${price}', 'checkout'),
('checkout.women_driver_desc', 'ar', '+${price}', 'checkout'),
('checkout.comments_label', 'en', 'Comments to the order', 'checkout'),
('checkout.comments_label', 'ar', 'تعليقات على الطلب', 'checkout'),
('checkout.comments_placeholder', 'en', 'e.g. Non-Standard luggage', 'checkout'),
('checkout.comments_placeholder', 'ar', 'مثال: أمتعة غير قياسية', 'checkout'),
('checkout.promo_placeholder', 'en', 'e.g. HELLO', 'checkout'),
('checkout.promo_placeholder', 'ar', 'مثال: HELLO', 'checkout'),
('checkout.promo_enter', 'en', 'Enter a promo code.', 'checkout'),
('checkout.promo_enter', 'ar', 'أدخل رمز الخصم.', 'checkout'),
('checkout.promo_validate_error', 'en', 'Could not validate promo code.', 'checkout'),
('checkout.promo_validate_error', 'ar', 'تعذر التحقق من رمز الخصم.', 'checkout'),

-- ============ TOURS ============
('tours.deposit_text', 'en', 'starting from as little as $99 deposit', 'tours'),
('tours.deposit_text', 'ar', 'تبدأ من دفعة مقدمة تبلغ 99 دولارًا فقط', 'tours'),
('tours.search_when_ph', 'en', '01 Aug – 31 Aug', 'tours'),
('tours.search_when_ph', 'ar', '01 أغسطس – 31 أغسطس', 'tours'),
('tours.not_found_title', 'en', 'Trip not found', 'tours'),
('tours.not_found_title', 'ar', 'الجولة غير موجودة', 'tours'),
('tours.not_found_text', 'en', 'The trip you are looking for does not exist.', 'tours'),
('tours.not_found_text', 'ar', 'الجولة التي تبحث عنها غير موجودة.', 'tours'),
('tours.browse_trips', 'en', 'Browse trips', 'tours'),
('tours.browse_trips', 'ar', 'تصفح الجولات', 'tours'),
('tours.trip_optional_price', 'en', 'Approx. ${price} per person.', 'tours'),
('tours.trip_optional_price', 'ar', 'تقريبًا ${price} لكل شخص.', 'tours'),
('tours.booking_mixed_desc', 'en', 'In a mixed gender room, you might be sharing with other travellers of different genders, but don''t worry: you each get your own single bed. The "mixed" part just means fellow travellers from your group, not random strangers!', 'tours'),
('tours.booking_mixed_desc', 'ar', 'في غرفة مختلطة، قد تشارك مع مسافرين آخرين من جنس مختلف، لكن لا تقلق: لكل منكم سرير فردي خاص. المقصود بـ"مختلط" هو زملاؤك في المجموعة فقط، وليس غرباء!', 'tours'),
('tours.booking_insurance_text', 'en', 'The price includes Medical and Baggage Insurance which covers all services included in the GAIA trip. International flights and any arrangements booked independently outside of the GAIA trip are excluded. Any pre-existing medical condition is also excluded.', 'tours'),
('tours.booking_insurance_text', 'ar', 'السعر يشمل التأمين الطبي وتأمين الأمتعة الذي يغطي جميع الخدمات المشمولة في رحلة غايا. الرحلات الجوية الدولية وأي ترتيبات محجوزة بشكل مستقل خارج رحلة غايا غير مشمولة، وكذلك أي حالة طبية سابقة.', 'tours'),
('tours.booking_more_info', 'en', 'Find more information here', 'tours'),
('tours.booking_more_info', 'ar', 'اعثر على مزيد من المعلومات هنا', 'tours'),
('tours.booking_flex_title', 'en', 'Flexible Cancellation', 'tours'),
('tours.booking_flex_title', 'ar', 'إلغاء مرن', 'tours'),
('tours.booking_flex_desc', 'en', 'Want more flexibility with your booking? Purchase our Flexible Cancellation to cover your trip up to 1 day before departure.', 'tours'),
('tours.booking_flex_desc', 'ar', 'تريد مزيدًا من المرونة في حجزك؟ اشترِ خيار الإلغاء المرن لتغطية رحلتك حتى يوم واحد قبل المغادرة.', 'tours'),
('tours.booking_whats_covered', 'en', 'What''s covered?', 'tours'),
('tours.booking_whats_covered', 'ar', 'ماذا يشمل؟', 'tours'),
('tours.booking_room_title', 'en', 'Private Room', 'tours'),
('tours.booking_room_title', 'ar', 'غرفة خاصة', 'tours'),
('tours.booking_room_desc', 'en', 'Add this option if you want a private room just for you or for you and the person you are travelling with.', 'tours'),
('tours.booking_room_desc', 'ar', 'أضف هذا الخيار إذا كنت تريد غرفة خاصة لك فقط أو لك وللشخص المسافر معك.', 'tours'),
('tours.booking_what_it_means', 'en', 'What does it mean?', 'tours'),
('tours.booking_what_it_means', 'ar', 'ماذا يعني ذلك؟', 'tours'),
('tours.booking_extra_price', 'en', '+ €{price}', 'tours'),
('tours.booking_extra_price', 'ar', '+ {price} €', 'tours'),
('tours.promo_enter', 'en', 'Enter a promo code.', 'tours'),
('tours.promo_enter', 'ar', 'أدخل رمز الخصم.', 'tours'),
('tours.promo_applied', 'en', 'Promo applied! -€{amount}', 'tours'),
('tours.promo_applied', 'ar', 'تم تطبيق الرمز! -{amount} €', 'tours'),
('tours.promo_validate_error', 'en', 'Could not validate promo.', 'tours'),
('tours.promo_validate_error', 'ar', 'تعذر التحقق من الرمز.', 'tours');


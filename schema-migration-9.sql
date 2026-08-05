-- ============================================================
-- GAIA TOURS & TRAVEL — Arabic Content Columns for Detail Pages
-- Migration #9
-- ------------------------------------------------------------
-- Adds Arabic (_ar) content columns to the detail-page tables
-- so each page can display the correct language based on
-- ?lang=ar / /ar/ URL prefix.
--
-- Tables updated:
--   * events            — title_ar, description_ar, location_ar, organizer_ar, date_label_ar
--   * hotels            — name_ar, description_ar, location_ar
--   * hotel_rooms       — name_ar, description_ar, beds_ar, facilities_ar
--   * hotel_facilities  — name_ar
--   * hotel_offers      — title_ar, description_ar
--   * hotel_reviews     — reviewer_ar, text_ar
--   * destinations      — name_ar, description_ar
--   * weroad_trips      — name_ar, tagline_ar, description_ar, age_range_ar, comfort_level_ar, accommodation_ar, effort_level_ar
--   * weroad_trip_itineraries — title_ar, description_ar
--   * weroad_trip_inclusions  — item_text_ar
--   * weroad_trip_optional_activities — name_ar, description_ar
--   * weroad_trip_faqs  — faq_group_ar, question_ar, answer_ar
--   * weroad_reviews    — author_ar, text_ar
-- Safe to run repeatedly (idempotent).
-- ============================================================
USE gaia_tours;

-- Helper: add a VARCHAR/TEXT column if it does not exist
-- (implemented as a stored procedure call below)

-- ------------------------------------------------------------
-- 1) EVENTS
-- ------------------------------------------------------------
SET @e_title_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='title_ar');
SET @s := IF(@e_title_ar=0, 'ALTER TABLE events ADD COLUMN title_ar VARCHAR(255) NULL AFTER title', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @e_desc_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='description_ar');
SET @s := IF(@e_desc_ar=0, 'ALTER TABLE events ADD COLUMN description_ar TEXT NULL AFTER description', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @e_loc_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='location_ar');
SET @s := IF(@e_loc_ar=0, 'ALTER TABLE events ADD COLUMN location_ar VARCHAR(150) NULL AFTER location', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @e_org_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='organizer_ar');
SET @s := IF(@e_org_ar=0, 'ALTER TABLE events ADD COLUMN organizer_ar VARCHAR(150) NULL AFTER organizer', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @e_date_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='date_label_ar');
SET @s := IF(@e_date_ar=0, 'ALTER TABLE events ADD COLUMN date_label_ar VARCHAR(100) NULL AFTER date_label', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE events SET
  title_ar = CASE slug
    WHEN 'wadi-rum-desert-gala' THEN 'حفلة وادي رم الصحراوية'
    WHEN 'amman-rooftop-tasting' THEN 'تذوق طعام على سطح عمّان'
    WHEN 'petra-by-night' THEN 'البتراء ليلاً'
    ELSE title_ar
  END,
  description_ar = CASE slug
    WHEN 'wadi-rum-desert-gala' THEN 'عشاء في الهواء الطلق تحت النجوم مع موسيقى حية وضيافة بدوية.'
    WHEN 'amman-rooftop-tasting' THEN 'قائمة أردنية مختارة مع إطلالات بانورامية على المدينة وسوميلير خاص.'
    WHEN 'petra-by-night' THEN 'استمتع بزيارة الخزنة المضاءة بالشموع في جولة مسائية مصحوبة بمرشد.'
    ELSE description_ar
  END,
  location_ar = CASE slug
    WHEN 'wadi-rum-desert-gala' THEN 'وادي رم'
    WHEN 'amman-rooftop-tasting' THEN 'عمّان'
    WHEN 'petra-by-night' THEN 'البتراء'
    ELSE location_ar
  END,
  organizer_ar = CASE slug
    WHEN 'wadi-rum-desert-gala' THEN 'فعاليات غايا الليلية'
    WHEN 'amman-rooftop-tasting' THEN 'فعاليات غايا الليلية'
    WHEN 'petra-by-night' THEN 'غايا جولات وسفر'
    ELSE organizer_ar
  END,
  date_label_ar = CASE slug
    WHEN 'wadi-rum-desert-gala' THEN 'مارس 2026 · وادي رم'
    WHEN 'amman-rooftop-tasting' THEN 'أبريل 2026 · عمّان'
    WHEN 'petra-by-night' THEN 'مايو 2026 · البتراء'
    ELSE date_label_ar
  END
WHERE slug IN ('wadi-rum-desert-gala','amman-rooftop-tasting','petra-by-night');

-- ------------------------------------------------------------
-- 2) HOTELS
-- ------------------------------------------------------------
SET @h_name_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotels' AND COLUMN_NAME='name_ar');
SET @s := IF(@h_name_ar=0, 'ALTER TABLE hotels ADD COLUMN name_ar VARCHAR(200) NULL AFTER name', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @h_desc_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotels' AND COLUMN_NAME='description_ar');
SET @s := IF(@h_desc_ar=0, 'ALTER TABLE hotels ADD COLUMN description_ar TEXT NULL AFTER description', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @h_loc_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotels' AND COLUMN_NAME='location_ar');
SET @s := IF(@h_loc_ar=0, 'ALTER TABLE hotels ADD COLUMN location_ar VARCHAR(150) NULL AFTER location', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE hotels SET
  name_ar = CASE slug
    WHEN 'st-regis-amman' THEN 'سانت ريجيس عمان'
    WHEN 'wadi-rum-bubble-camp' THEN 'مخيم وادي رم الفقاعي'
    ELSE name_ar
  END,
  description_ar = CASE slug
    WHEN 'st-regis-amman' THEN 'فندق فاخر من فئة الخمس نجوم في قلب عمّان، يقدم غرفاً أنيقة وسبا عالمي المستوى ومطاعم راقية.'
    WHEN 'wadi-rum-bubble-camp' THEN 'مخيم فقاعي فاخر في صحراء وادي رم مع إطلالات على النجوم ووجبات بدوية تقليدية.'
    ELSE description_ar
  END,
  location_ar = CASE slug
    WHEN 'st-regis-amman' THEN 'عمّان، الأردن'
    WHEN 'wadi-rum-bubble-camp' THEN 'وادي رم، الأردن'
    ELSE location_ar
  END
WHERE slug IN ('st-regis-amman','wadi-rum-bubble-camp');

-- ------------------------------------------------------------
-- 3) HOTEL_ROOMS
-- ------------------------------------------------------------
SET @r_name_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_rooms' AND COLUMN_NAME='name_ar');
SET @s := IF(@r_name_ar=0, 'ALTER TABLE hotel_rooms ADD COLUMN name_ar VARCHAR(200) NULL AFTER name', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @r_desc_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_rooms' AND COLUMN_NAME='description_ar');
SET @s := IF(@r_desc_ar=0, 'ALTER TABLE hotel_rooms ADD COLUMN description_ar TEXT NULL AFTER description', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @r_beds_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_rooms' AND COLUMN_NAME='beds_ar');
SET @s := IF(@r_beds_ar=0, 'ALTER TABLE hotel_rooms ADD COLUMN beds_ar VARCHAR(100) NULL AFTER beds', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @r_fac_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_rooms' AND COLUMN_NAME='facilities_ar');
SET @s := IF(@r_fac_ar=0, 'ALTER TABLE hotel_rooms ADD COLUMN facilities_ar TEXT NULL AFTER facilities', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE hotel_rooms SET
  name_ar = CASE slug
    WHEN 'st-regis-deluxe-room' THEN 'غرفة ديلوكس كينج'
    WHEN 'st-regis-premier-suite' THEN 'جناح بريميير'
    WHEN 'bubble-junior-bubble' THEN 'جناح الفقاعة الصغير'
    WHEN 'bubble-family-tent' THEN 'خيمة عائلية صحراوية'
    ELSE name_ar
  END,
  description_ar = CASE slug
    WHEN 'st-regis-deluxe-room' THEN 'غرفة أنيقة بفراش فاخر وحمام رخامي وإطلالات على أفق المدينة.'
    WHEN 'st-regis-premier-suite' THEN 'جناح واسع بمنطقة معيشة منفصلة وركن طعام وإطلالات بانورامية على عمّان.'
    WHEN 'bubble-junior-bubble' THEN 'جناح فقاعي شفاف مع سرير مزدوج مريح وتراس خاص تحت النجوم.'
    WHEN 'bubble-family-tent' THEN 'خيمة بدوية واسعة بسريرين مزدوجين وحمام مشترك وسطح بإطلالة صحراوية.'
    ELSE description_ar
  END,
  beds_ar = CASE slug
    WHEN 'st-regis-deluxe-room' THEN 'سرير كينج واحد'
    WHEN 'st-regis-premier-suite' THEN 'سرير كينج + سرير أريكة'
    WHEN 'bubble-junior-bubble' THEN 'سرير مزدوج واحد'
    WHEN 'bubble-family-tent' THEN 'سريران مزدوجان'
    ELSE beds_ar
  END,
  facilities_ar = CASE slug
    WHEN 'st-regis-deluxe-room' THEN 'واي فاي مجاني،تكييف هواء،تلفاز شاشة مسطحة،ميني بار،خزنة،آلة قهوة'
    WHEN 'st-regis-premier-suite' THEN 'واي فاي مجاني،تكييف هواء،خدمة الغرف،منطقة معيشة،ميني بار،حوض استحمام'
    WHEN 'bubble-junior-bubble' THEN 'إطلالة بانورامية،تدفئة،تراس خاص،دش خاص'
    WHEN 'bubble-family-tent' THEN 'إطلالة صحراوية،تدفئة،حمام مشترك،سطح،طقم شاي'
    ELSE facilities_ar
  END
WHERE slug IN ('st-regis-deluxe-room','st-regis-premier-suite','bubble-junior-bubble','bubble-family-tent');

-- ------------------------------------------------------------
-- 4) HOTEL_FACILITIES
-- ------------------------------------------------------------
SET @f_name_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_facilities' AND COLUMN_NAME='name_ar');
SET @s := IF(@f_name_ar=0, 'ALTER TABLE hotel_facilities ADD COLUMN name_ar VARCHAR(150) NULL AFTER name', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE hotel_facilities SET name_ar = CASE name
  WHEN 'Free Wi-Fi' THEN 'واي فاي مجاني'
  WHEN 'Swimming Pool' THEN 'مسبح'
  WHEN 'Spa & Fitness' THEN 'سبا ولياقة بدنية'
  WHEN '24/7 Room Service' THEN 'خدمة غرف على مدار الساعة'
  WHEN 'Restaurant' THEN 'مطعم'
  WHEN 'Valet Parking' THEN 'موقف سيارات'
  WHEN 'Desert Views' THEN 'إطلالات صحراوية'
  WHEN 'Stargazing Deck' THEN 'سطح لمشاهدة النجوم'
  WHEN 'Bedouin Dinner' THEN 'عشاء بدوي'
  WHEN '4x4 Tours Desk' THEN 'مكتب جولات الدفع الرباعي'
  WHEN 'Free Breakfast' THEN 'فطور مجاني'
  ELSE name_ar
END WHERE name_ar IS NULL;

-- ------------------------------------------------------------
-- 5) HOTEL_OFFERS
-- ------------------------------------------------------------
SET @o_title_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_offers' AND COLUMN_NAME='title_ar');
SET @s := IF(@o_title_ar=0, 'ALTER TABLE hotel_offers ADD COLUMN title_ar VARCHAR(200) NULL AFTER title', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @o_desc_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_offers' AND COLUMN_NAME='description_ar');
SET @s := IF(@o_desc_ar=0, 'ALTER TABLE hotel_offers ADD COLUMN description_ar TEXT NULL AFTER description', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE hotel_offers SET
  title_ar = CASE title
    WHEN 'Weekend Escape' THEN 'عطلة نهاية الأسبوع'
    WHEN 'Suite Upgrade Package' THEN 'باقة ترقية الجناح'
    WHEN 'Desert Night Stay' THEN 'إقامة ليلة صحراوية'
    WHEN 'Stargazing Package' THEN 'باقة مشاهدة النجوم'
    ELSE title_ar
  END,
  description_ar = CASE title
    WHEN 'Weekend Escape' THEN 'أمكث ليلتين واستمتع بجلسة سبا مجانية وخروج متأخر.'
    WHEN 'Suite Upgrade Package' THEN 'يشمل ترقية لجناح بريميير ونقل من المطار وعشاء ترحيبي لشخصين.'
    WHEN 'Desert Night Stay' THEN 'إقامة ليلة في فقاعة مع عشاء بدوي خاص وجولة شروق بالدفع الرباعي.'
    WHEN 'Stargazing Package' THEN 'جناح فقاعي ومشاهدة نجوم بقيادة فلكي واستئجار تلسكوب للمساء.'
    ELSE description_ar
  END
WHERE title IN ('Weekend Escape','Suite Upgrade Package','Desert Night Stay','Stargazing Package');

-- ------------------------------------------------------------
-- 6) HOTEL_REVIEWS
-- ------------------------------------------------------------
SET @rv_rev_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_reviews' AND COLUMN_NAME='reviewer_ar');
SET @s := IF(@rv_rev_ar=0, 'ALTER TABLE hotel_reviews ADD COLUMN reviewer_ar VARCHAR(100) NULL AFTER reviewer', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @rv_text_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hotel_reviews' AND COLUMN_NAME='text_ar');
SET @s := IF(@rv_text_ar=0, 'ALTER TABLE hotel_reviews ADD COLUMN text_ar TEXT NULL AFTER text', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE hotel_reviews SET
  reviewer_ar = CASE reviewer
    WHEN 'Sara M.' THEN 'سارة م.'
    WHEN 'Omar K.' THEN 'عمر ك.'
    WHEN 'Elena R.' THEN 'إيلينا ر.'
    WHEN 'Luca B.' THEN 'لوكا ب.'
    WHEN 'Yara H.' THEN 'يارا ه.'
    ELSE reviewer_ar
  END,
  text_ar = CASE text
    WHEN 'Impeccable service and a stunning view over Amman. The room was spotless and the staff went above and beyond.' THEN 'خدمة مثالية وإطلالة خلابة على عمّان. كانت الغرفة نظيفة تماماً والموظفون قدموا أفضل ما لديهم.'
    WHEN 'Beautiful hotel with an excellent location. Breakfast was world-class, rooms are large and comfortable.' THEN 'فندق جميل بموقع ممتاز. كان الفطور عالمياً والغرف واسعة ومريحة.'
    WHEN 'The spa and pool are fantastic. A truly luxurious stay in the heart of the city.' THEN 'السبا والمسبح رائعان. إقامة فاخرة حقاً في قلب المدينة.'
    WHEN 'Sleeping under the stars was unforgettable. The staff arranged a perfect Bedouin dinner for us.' THEN 'النوم تحت النجوم كان تجربة لا تُنسى. نظم الموظفون لنا عشاءً بدوياً مثالياً.'
    WHEN 'A magical desert experience. Bubble was cozy and the stargazing deck is a highlight.' THEN 'تجربة صحراوية ساحرة. كانت الفقاعة دافئة وسطح مشاهدة النجوم هو الأبرز.'
    ELSE text_ar
  END
WHERE reviewer IN ('Sara M.','Omar K.','Elena R.','Luca B.','Yara H.');

-- ------------------------------------------------------------
-- 7) DESTINATIONS
-- ------------------------------------------------------------
SET @d_name_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='destinations' AND COLUMN_NAME='name_ar');
SET @s := IF(@d_name_ar=0, 'ALTER TABLE destinations ADD COLUMN name_ar VARCHAR(150) NULL AFTER name', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @d_desc_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='destinations' AND COLUMN_NAME='description_ar');
SET @s := IF(@d_desc_ar=0, 'ALTER TABLE destinations ADD COLUMN description_ar TEXT NULL AFTER description', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE destinations SET
  name_ar = CASE slug
    WHEN 'jordan' THEN 'الأردن'
    WHEN 'bali' THEN 'بالي'
    WHEN 'morocco' THEN 'المغرب'
    WHEN 'iceland' THEN 'آيسلندا'
    ELSE name_ar
  END,
  description_ar = CASE slug
    WHEN 'jordan' THEN 'اكتشف البتراء ووادي رم والبحر الميت — كنوز الأردن الخالدة.'
    WHEN 'bali' THEN 'شواطئ خلابة ومدرجات أرز ومعابد مقدسة في جزيرة الآلهة.'
    WHEN 'morocco' THEN 'من أسواق مراكش إلى كثبان الصحراء وساحل المحيط الأطلسي.'
    WHEN 'iceland' THEN 'شلالات وأنهار جليدية وشواطئ سوداء على طريق الدائرة.'
    ELSE description_ar
  END
WHERE slug IN ('jordan','bali','morocco','iceland');

-- ------------------------------------------------------------
-- 8) WEROAD_TRIPS
-- ------------------------------------------------------------
SET @t_name_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_trips' AND COLUMN_NAME='name_ar');
SET @s := IF(@t_name_ar=0, 'ALTER TABLE weroad_trips ADD COLUMN name_ar VARCHAR(150) NULL AFTER name', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @t_tagline_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_trips' AND COLUMN_NAME='tagline_ar');
SET @s := IF(@t_tagline_ar=0, 'ALTER TABLE weroad_trips ADD COLUMN tagline_ar VARCHAR(255) NULL AFTER tagline', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @t_desc_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_trips' AND COLUMN_NAME='description_ar');
SET @s := IF(@t_desc_ar=0, 'ALTER TABLE weroad_trips ADD COLUMN description_ar TEXT NULL AFTER description', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @t_age_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_trips' AND COLUMN_NAME='age_range_ar');
SET @s := IF(@t_age_ar=0, 'ALTER TABLE weroad_trips ADD COLUMN age_range_ar VARCHAR(50) NULL AFTER age_range', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @t_comfort_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_trips' AND COLUMN_NAME='comfort_level_ar');
SET @s := IF(@t_comfort_ar=0, 'ALTER TABLE weroad_trips ADD COLUMN comfort_level_ar VARCHAR(150) NULL AFTER comfort_level', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @t_accom_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_trips' AND COLUMN_NAME='accommodation_ar');
SET @s := IF(@t_accom_ar=0, 'ALTER TABLE weroad_trips ADD COLUMN accommodation_ar VARCHAR(150) NULL AFTER accommodation', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @t_effort_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_trips' AND COLUMN_NAME='effort_level_ar');
SET @s := IF(@t_effort_ar=0, 'ALTER TABLE weroad_trips ADD COLUMN effort_level_ar VARCHAR(50) NULL AFTER effort_level', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE weroad_trips SET
  name_ar = CASE slug
    WHEN 'jordan-360' THEN 'الأردن 360°: البتراء ووادي رم والبحر الميت'
    WHEN 'bali-explorer' THEN 'مستكشف بالي'
    WHEN 'morocco-discovery' THEN 'اكتشاف المغرب'
    WHEN 'iceland-ring-road' THEN 'طريق آيسلندا الدائري'
    WHEN 'vietnam-cambodia' THEN 'فيتنام وكمبوديا'
    WHEN 'peru-machu-picchu' THEN 'بيرو وماتشو بيتشو'
    ELSE name_ar
  END,
  tagline_ar = CASE slug
    WHEN 'jordan-360' THEN 'البتراء ووادي رم والبحر الميت'
    WHEN 'bali-explorer' THEN 'شواطئ ومدرجات أرز ومعابد'
    WHEN 'morocco-discovery' THEN 'مراكش والصحراء والساحل'
    WHEN 'iceland-ring-road' THEN 'شلالات وأنهار جليدية وشواطئ سوداء'
    WHEN 'vietnam-cambodia' THEN 'من هانوي إلى أنغكور وات'
    WHEN 'peru-machu-picchu' THEN 'ليما وكوسكو وقلعة الإنكا'
    ELSE tagline_ar
  END,
  description_ar = CASE slug
    WHEN 'jordan-360' THEN 'استكشف المدينة الوردية البتراء، ونم تحت النجوم في صحراء وادي رم، واستمتع بالطفو في البحر الميت الغني بالمعادن — كل ذلك مع قائد مجموعة محلي ومجموعة صغيرة من المسافرين.'
    WHEN 'bali-explorer' THEN 'رحلة نابضة عبر مدرجات الأرز الخصبة والمعابد المقدسة والساحل الخلاب في بالي.'
    WHEN 'morocco-discovery' THEN 'من أسواق مراكش إلى كثبان الصحراء الكبرى وساحل المحيط الأطلسي — مشهد من الألوان والثقافة.'
    WHEN 'iceland-ring-road' THEN 'طواف حول آيسلندا على طريق الدائرة الشهير بحثاً عن الشلالات والأنهار الجليدية والمناظر البركانية.'
    WHEN 'vietnam-cambodia' THEN 'حلقة جنوب شرق آسيا الكلاسيكية عبر أبرز معالم شمال فيتنام ومعابد كمبوديا القديمة.'
    WHEN 'peru-machu-picchu' THEN 'اتبع طريق الإنكا إلى مدينة ماتشو بيتشو المفقودة واستكشف كوسكو وتذوق طعام ليما.'
    ELSE description_ar
  END,
  age_range_ar = CASE slug
    WHEN 'jordan-360' THEN '18–39'
    WHEN 'bali-explorer' THEN '18–39'
    WHEN 'morocco-discovery' THEN '18–39'
    WHEN 'iceland-ring-road' THEN '18–39'
    WHEN 'vietnam-cambodia' THEN '18–39'
    WHEN 'peru-machu-picchu' THEN '18–39'
    ELSE age_range_ar
  END,
  comfort_level_ar = CASE slug
    WHEN 'jordan-360' THEN 'فنادق ومخيم صحراوي وليلة حافلة'
    WHEN 'bali-explorer' THEN 'فنادق وفيلات'
    WHEN 'morocco-discovery' THEN 'رياضات ومخيم صحراوي'
    WHEN 'iceland-ring-road' THEN 'بيوت ضيافة ومخيمات'
    WHEN 'vietnam-cambodia' THEN 'فنادق ومنازل محلية'
    WHEN 'peru-machu-picchu' THEN 'فنادق'
    ELSE comfort_level_ar
  END,
  accommodation_ar = CASE slug
    WHEN 'jordan-360' THEN 'غرف توأم مشتركة، مختلطة الجنسين'
    WHEN 'bali-explorer' THEN 'غرف توأم مشتركة، مختلطة الجنسين'
    WHEN 'morocco-discovery' THEN 'غرف توأم مشتركة، مختلطة الجنسين'
    WHEN 'iceland-ring-road' THEN 'غرف توأم مشتركة، مختلطة الجنسين'
    WHEN 'vietnam-cambodia' THEN 'غرف توأم مشتركة، مختلطة الجنسين'
    WHEN 'peru-machu-picchu' THEN 'غرف توأم مشتركة، مختلطة الجنسين'
    ELSE accommodation_ar
  END,
  effort_level_ar = CASE slug
    WHEN 'jordan-360' THEN 'متوسط'
    WHEN 'bali-explorer' THEN 'متوسط'
    WHEN 'morocco-discovery' THEN 'متوسط'
    WHEN 'iceland-ring-road' THEN 'صعب'
    WHEN 'vietnam-cambodia' THEN 'متوسط'
    WHEN 'peru-machu-picchu' THEN 'صعب'
    ELSE effort_level_ar
  END
WHERE slug IN ('jordan-360','bali-explorer','morocco-discovery','iceland-ring-road','vietnam-cambodia','peru-machu-picchu');

-- ------------------------------------------------------------
-- 9) WEROAD_TRIP_ITINERARIES
-- ------------------------------------------------------------
SET @i_title_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_trip_itineraries' AND COLUMN_NAME='title_ar');
SET @s := IF(@i_title_ar=0, 'ALTER TABLE weroad_trip_itineraries ADD COLUMN title_ar VARCHAR(150) NULL AFTER title', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @i_desc_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_trip_itineraries' AND COLUMN_NAME='description_ar');
SET @s := IF(@i_desc_ar=0, 'ALTER TABLE weroad_trip_itineraries ADD COLUMN description_ar TEXT NULL AFTER description', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE weroad_trip_itineraries SET
  title_ar = CASE day_number
    WHEN 1 THEN 'اللقاء في عمّان'
    WHEN 2 THEN 'استكشاف الضفة الغربية للبتراء'
    WHEN 3 THEN 'البتراء والبتراء الصغيرة'
    WHEN 4 THEN 'إلى صحراء وادي رم'
    WHEN 5 THEN 'مشي شروق الشمس في وادي رم'
    WHEN 6 THEN 'الطفو في البحر الميت'
    WHEN 7 THEN 'العودة إلى عمّان'
    WHEN 8 THEN 'وداعاً عمّان'
    ELSE title_ar
  END,
  description_ar = CASE day_number
    WHEN 1 THEN 'وصل إلى عمّان وقابل قائد مجموعتك وزملائك المسافرين في عشاء الترحيب. ليلة هادئة للاسترخاء قبل بدء المغامرة.'
    WHEN 2 THEN 'اتجه جنوباً إلى البتراء وسِر في السيق عند الساعة الذهبية للوصول إلى الخزنة لأول مرة — إحدى عجائب الدنيا السبع الجديدة.'
    WHEN 3 THEN 'يوم كامل لاستكشاف حديقة البتراء الأثرية الواسعة، بالإضافة إلى زيارة سيق البريد الأكثر هدوءاً المعروف بالبتراء الصغيرة.'
    WHEN 4 THEN 'جولة بالسيارات الدفع الرباعي عبر الكثبان الحمراء والجسور الصخرية في وادي رم، تليها ليلة تحت النجوم في مخيم بدوي.'
    WHEN 5 THEN 'مشي باكراً لتسلق تشكيل صخري عند شروق الشمس قبل التوجه نحو ساحل البحر الميت.'
    WHEN 6 THEN 'يوم حر للاسترخاء في أدنى نقطة على سطح الأرض — اطفو في الماء الغني بالمعادن وجرب الطين الشهير.'
    WHEN 7 THEN 'العودة إلى عمّان مع وقت لاستكشاف القلعة وأسواق وسط المدينة قبل عشاء الوداع.'
    WHEN 8 THEN 'تنتهي الرحلة بعد الفطور. النقل إلى المطار غير مشمول ويسهل ترتيبه.'
    ELSE description_ar
  END
WHERE trip_id = 1;

-- ------------------------------------------------------------
-- 10) WEROAD_TRIP_INCLUSIONS
-- ------------------------------------------------------------
SET @inc_text_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_trip_inclusions' AND COLUMN_NAME='item_text_ar');
SET @s := IF(@inc_text_ar=0, 'ALTER TABLE weroad_trip_inclusions ADD COLUMN item_text_ar VARCHAR(255) NULL AFTER item_text', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE weroad_trip_inclusions SET item_text_ar = CASE item_text
  WHEN '7 nights'' accommodation' THEN 'إقامة 7 ليالٍ'
  WHEN 'All transport during the trip' THEN 'جميع وسائل النقل خلال الرحلة'
  WHEN 'English-speaking Group Leader' THEN 'قائد مجموعة ناطق بالإنجليزية'
  WHEN 'Medical & baggage insurance' THEN 'تأمين طبي وأمتعة'
  WHEN 'International flights' THEN 'الرحلات الجوية الدولية'
  WHEN 'Jordan entry visa' THEN 'تأشيرة دخول الأردن'
  WHEN 'Meals not specified in the itinerary' THEN 'وجبات غير محددة في خط السير'
  ELSE item_text_ar
END WHERE item_text_ar IS NULL;

-- ------------------------------------------------------------
-- 11) WEROAD_TRIP_OPTIONAL_ACTIVITIES
-- ------------------------------------------------------------
SET @a_name_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_trip_optional_activities' AND COLUMN_NAME='name_ar');
SET @s := IF(@a_name_ar=0, 'ALTER TABLE weroad_trip_optional_activities ADD COLUMN name_ar VARCHAR(150) NULL AFTER name', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @a_desc_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_trip_optional_activities' AND COLUMN_NAME='description_ar');
SET @s := IF(@a_desc_ar=0, 'ALTER TABLE weroad_trip_optional_activities ADD COLUMN description_ar TEXT NULL AFTER description', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE weroad_trip_optional_activities SET
  name_ar = CASE name
    WHEN 'Petra by Night' THEN 'البتراء ليلاً'
    WHEN 'Hot air balloon over Wadi Rum' THEN 'منطاد الهواء الساخن فوق وادي رم'
    ELSE name_ar
  END,
  description_ar = CASE name
    WHEN 'Petra by Night' THEN 'سِر في السيق المضاء بالشموع إلى الخزنة لأداء مسائي. حوالي 20 دولاراً للشخص.'
    WHEN 'Hot air balloon over Wadi Rum' THEN 'رحلة طيران عند شروق الشمس فوق الصحراء، حسب الطقس. حوالي 170 دولاراً للشخص.'
    ELSE description_ar
  END
WHERE name IN ('Petra by Night','Hot air balloon over Wadi Rum');

-- ------------------------------------------------------------
-- 12) WEROAD_TRIP_FAQS
-- ------------------------------------------------------------
SET @faq_group_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_trip_faqs' AND COLUMN_NAME='faq_group_ar');
SET @s := IF(@faq_group_ar=0, 'ALTER TABLE weroad_trip_faqs ADD COLUMN faq_group_ar VARCHAR(80) NULL AFTER faq_group', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @faq_q_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_trip_faqs' AND COLUMN_NAME='question_ar');
SET @s := IF(@faq_q_ar=0, 'ALTER TABLE weroad_trip_faqs ADD COLUMN question_ar VARCHAR(255) NULL AFTER question', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @faq_a_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_trip_faqs' AND COLUMN_NAME='answer_ar');
SET @s := IF(@faq_a_ar=0, 'ALTER TABLE weroad_trip_faqs ADD COLUMN answer_ar TEXT NULL AFTER answer', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE weroad_trip_faqs SET
  faq_group_ar = CASE faq_group
    WHEN 'About this trip' THEN 'عن هذه الرحلة'
    WHEN 'About WeRoad' THEN 'عن وايرود'
    WHEN 'About Jordan' THEN 'عن الأردن'
    ELSE faq_group_ar
  END,
  question_ar = CASE question
    WHEN 'What''s the group size on this trip?' THEN 'ما هو حجم المجموعة في هذه الرحلة؟'
    WHEN 'Is single accommodation available?' THEN 'هل تتوفر إقامة فردية؟'
    WHEN 'How fit do I need to be?' THEN 'كم مستوى اللياقة المطلوب؟'
    WHEN 'What is WeRoad?' THEN 'ما هي وايرود؟'
    WHEN 'Can I travel solo?' THEN 'هل يمكنني السفر بمفردي؟'
    WHEN 'What''s the best time to visit Jordan?' THEN 'ما أفضل وقت لزيارة الأردن؟'
    WHEN 'Is Jordan safe for travellers?' THEN 'هل الأردن آمن للمسافرين؟'
    ELSE question_ar
  END,
  answer_ar = CASE question
    WHEN 'What''s the group size on this trip?' THEN 'عادة ما تكون المجموعات بين 12 و20 مسافراً، بالإضافة إلى قائد مجموعة واحد.'
    WHEN 'Is single accommodation available?' THEN 'نعم — أضف خيار الغرفة الخاصة عند الدفع مقابل رسوم إضافية.'
    WHEN 'How fit do I need to be?' THEN 'تصنف هذه الرحلة بأنها متوسطة: بعض المشي على تضاريس غير مستوية ومشي واحد عند شروق الشمس، دون تسلق فني.'
    WHEN 'What is WeRoad?' THEN 'تصمم وايرود رحلات مغامرة جماعية صغيرة للمسافرين الذين يريدون الاستكشاف دون التخطيط للخدمات اللوجستية بأنفسهم.'
    WHEN 'Can I travel solo?' THEN 'معظم مسافري وايرود يسافرون منفردين — لا توجد رسوم إضافية للفرد الواحد والغرف مشتركة مع زملائك المسافرين.'
    WHEN 'What''s the best time to visit Jordan?' THEN 'يوفر الربيع (مارس-مايو) والخريف (سبتمبر-نوفمبر) درجات الحرارة الأكثر راحة.'
    WHEN 'Is Jordan safe for travellers?' THEN 'يعتبر الأردن من أكثر الدول استقراراً وترحيباً في المنطقة.'
    ELSE answer_ar
  END
WHERE faq_group IN ('About this trip','About WeRoad','About Jordan');

-- ------------------------------------------------------------
-- 13) WEROAD_REVIEWS
-- ------------------------------------------------------------
SET @w_author_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_reviews' AND COLUMN_NAME='author_ar');
SET @s := IF(@w_author_ar=0, 'ALTER TABLE weroad_reviews ADD COLUMN author_ar VARCHAR(100) NULL AFTER author', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @w_text_ar := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weroad_reviews' AND COLUMN_NAME='text_ar');
SET @s := IF(@w_text_ar=0, 'ALTER TABLE weroad_reviews ADD COLUMN text_ar TEXT NULL AFTER text', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE weroad_reviews SET
  author_ar = CASE author
    WHEN 'Marta, Jordan 360°' THEN 'مارتا، الأردن 360°'
    WHEN 'Dario, Morocco Discovery' THEN 'داريو، اكتشاف المغرب'
    WHEN 'Aisha, Peru & Machu Picchu' THEN 'عائشة، بيرو وماتشو بيتشو'
    ELSE author_ar
  END,
  text_ar = CASE text
    WHEN 'My third trip with WeRoad and still the best way I''ve found to travel solo without actually being alone.' THEN 'رحلتي الثالثة مع وايرود وما زالت أفضل طريقة وجدتها للسفر منفرداً دون أن أكون وحيداً فعلاً.'
    WHEN 'The Group Leader made the whole thing feel effortless. Came back with ten new friends and a phone full of photos.' THEN 'جعل قائد المجموعة كل شيء يبدو سهلاً. عدت بعشرة أصدقاء جدد وهاتف مليء بالصور.'
    WHEN 'Booking was simple, the installment plan helped a lot, and the itinerary was better paced than I expected.' THEN 'كان الحجز بسيطاً وخطة الأقساط ساعدت كثيراً وكان خط السير أفضل مما توقعت.'
    ELSE text_ar
  END
WHERE author IN ('Marta, Jordan 360°','Dario, Morocco Discovery','Aisha, Peru & Machu Picchu');
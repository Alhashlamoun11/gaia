<?php
require __DIR__ . '/db.php';
$pdo = getPDO();

$translations = [
    ['lang' => 'en', 'key' => 'admin.banners', 'value' => 'Banners'],
    ['lang' => 'ar', 'key' => 'admin.banners', 'value' => 'اللافتات'],
    
    ['lang' => 'en', 'key' => 'admin.highlights', 'value' => 'Highlights'],
    ['lang' => 'ar', 'key' => 'admin.highlights', 'value' => 'الميزات'],
    
    ['lang' => 'en', 'key' => 'admin.pickup_locations', 'value' => 'Pickup Locations'],
    ['lang' => 'ar', 'key' => 'admin.pickup_locations', 'value' => 'نقاط الالتقاء'],
    
    ['lang' => 'en', 'key' => 'admin.trust_scores', 'value' => 'Trust Scores'],
    ['lang' => 'ar', 'key' => 'admin.trust_scores', 'value' => 'نقاط الثقة'],
    
    ['lang' => 'en', 'key' => 'admin.awards', 'value' => 'Awards'],
    ['lang' => 'ar', 'key' => 'admin.awards', 'value' => 'الجوائز'],
    
    ['lang' => 'en', 'key' => 'admin.night_offerings', 'value' => 'Night Offerings'],
    ['lang' => 'ar', 'key' => 'admin.night_offerings', 'value' => 'عروض السهرة']
];

$stmt = $pdo->prepare("INSERT IGNORE INTO translations (lang, `key`, `value`, `group`) VALUES (?, ?, ?, 'admin')");
foreach ($translations as $t) {
    $stmt->execute([$t['lang'], $t['key'], $t['value']]);
}

// Ensure footer links use translations instead of hardcoded English in settings
$pdo->exec("DELETE FROM settings WHERE `key` IN ('footer_terms_label', 'footer_privacy_label', 'footer_refund_label')");

echo "Missing translations inserted successfully and footer settings cleared so they use translations!\n";

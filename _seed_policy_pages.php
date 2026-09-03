<?php
require_once __DIR__ . '/db.php';

$db = getPDO();

$pages = [
    [
        'slug' => 'terms-and-conditions',
        'title' => 'Terms and Conditions',
        'subtitle' => 'Please read these terms and conditions carefully before using our services.',
        'content' => "1. Introduction\nWelcome to GAIA Tours & Travel. These terms and conditions outline the rules and regulations for the use of our platform and services.\n\n2. Booking & Payments\nAll bookings are subject to availability. Full payment or an agreed deposit must be made at the time of booking to secure your reservation. Prices are subject to change without prior notice, but confirmed bookings will not be affected.\n\n3. Cancellations & Modifications\nIf you need to cancel or modify your booking, please refer to our Refund Policy. We reserve the right to cancel any tour or service due to unforeseen circumstances (e.g., extreme weather), in which case a full refund or alternative will be offered.\n\n4. Liability\nGAIA Tours & Travel acts as an agent for third-party providers (hotels, transport, etc.). We are not liable for any injuries, losses, or damages incurred during your trip, except where such loss is directly caused by our negligence.\n\n5. Governing Law\nThese terms shall be governed by and construed in accordance with the laws of the jurisdiction in which GAIA Tours operates.",
    ],
    [
        'slug' => 'privacy-policy',
        'title' => 'Privacy Policy',
        'subtitle' => 'Your privacy is important to us. This policy explains how we collect, use, and protect your data.',
        'content' => "1. Information We Collect\nWe collect personal information that you provide to us when you make a booking, subscribe to our newsletter, or contact us. This includes your name, email address, phone number, and payment details.\n\n2. How We Use Your Information\nYour information is used to process your bookings, communicate with you regarding your trip, and improve our services. We may also send you promotional emails if you have opted in to receive them.\n\n3. Data Protection\nWe implement strict security measures to ensure your personal data is protected against unauthorized access, alteration, or disclosure. We do not sell your personal information to third parties.\n\n4. Cookies\nOur website uses cookies to enhance your browsing experience. You can choose to disable cookies in your browser settings, though this may affect the functionality of our platform.\n\n5. Contact Us\nIf you have any questions about this Privacy Policy, please contact us at support@gaiatours.com.",
    ],
    [
        'slug' => 'refund-policy',
        'title' => 'Refund Policy',
        'subtitle' => 'Our cancellation and refund terms for all tours, transfers, and services.',
        'content' => "1. General Cancellation Rules\nWe understand that travel plans can change. You may cancel your booking by contacting our support team. Refunds are processed based on the timeframe of your cancellation.\n\n2. Transfers & Day Tours\n• More than 48 hours before pickup: 100% full refund.\n• 24 to 48 hours before pickup: 50% refund.\n• Less than 24 hours before pickup: No refund.\n\n3. Hotel Bookings & Multi-Day Tours\nHotel and multi-day tour cancellations are subject to the specific policies of the accommodation or tour operator. Please refer to your booking confirmation for exact details. Generally, a minimum of 14 days notice is required for a full refund on these packages.\n\n4. No-Shows\nIf you fail to show up for your scheduled transfer or tour without prior notice, no refund will be issued.\n\n5. Processing Time\nApproved refunds are processed to your original method of payment within 5-10 business days.",
    ],
];

try {
    foreach ($pages as $p) {
        $stmt = $db->prepare("SELECT id FROM pages WHERE slug = ? AND lang = 'en'");
        $stmt->execute([$p['slug']]);
        
        if ($stmt->rowCount() > 0) {
            // Update existing
            $update = $db->prepare("UPDATE pages SET title = ?, subtitle = ?, content = ? WHERE slug = ? AND lang = 'en'");
            $update->execute([$p['title'], $p['subtitle'], $p['content'], $p['slug']]);
            echo "Page '{$p['slug']}' updated with plain text successfully!<br>";
        } else {
            // Insert new
            $insert = $db->prepare("INSERT INTO pages (slug, title, subtitle, content, lang, is_active) VALUES (?, ?, ?, ?, 'en', 1)");
            $insert->execute([$p['slug'], $p['title'], $p['subtitle'], $p['content']]);
            echo "Page '{$p['slug']}' created successfully!<br>";
        }
    }
    
    echo "<br><b>All pages have been fixed! Please refresh your policy page to see the clean text.</b>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

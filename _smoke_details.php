<?php
/**
 * _smoke_details.php — Smoke test for the four dynamic detail pages.
 * ------------------------------------------------------------
 * Verifies that each detail page:
 *   - Returns HTTP 200 for valid slugs/IDs
 *   - Contains key content markers (title, hero, sections) in EN/AR
 *   - Returns HTTP 404 for invalid slugs/IDs
 *
 * Run:  php _smoke_details.php
 * ------------------------------------------------------------
 */

$base = 'http://localhost:8000';

$tests = [
    // ---- Event Details ----
    [
        'name'   => 'Event EN',
        'url'    => '/events/wadi-rum-desert-gala',
        'expect' => 200,
        'markers' => ['Wadi Rum Desert Gala', 'About this Event', 'Gallery', 'Reserve Your Spot'],
    ],
    [
        'name'   => 'Event AR',
        'url'    => '/ar/events/wadi-rum-desert-gala',
        'expect' => 200,
        'markers' => ['حفلة وادي رم الصحراوية', 'عشاء في الهواء الطلق', 'وادي رم', 'فعاليات غايا الليلية'],
    ],
    [
        'name'   => 'Event 404',
        'url'    => '/events/does-not-exist',
        'expect' => 404,
        'markers' => ['404', 'Not Found'],
    ],

    // ---- Hotel Details ----
    [
        'name'   => 'Hotel EN',
        'url'    => '/hotels/st-regis-amman',
        'expect' => 200,
        'markers' => ['The St. Regis Amman', 'About the Hotel', 'Hotel Facilities', 'Available Rooms', 'Guest Reviews'],
    ],
    [
        'name'   => 'Hotel AR',
        'url'    => '/ar/hotels/st-regis-amman',
        'expect' => 200,
        'markers' => ['سانت ريجيس عمان', 'فندق فاخر من فئة الخمس نجوم', 'عمّان، الأردن', 'واي فاي مجاني', 'مسبح'],
    ],
    [
        'name'   => 'Hotel 404',
        'url'    => '/hotels/does-not-exist',
        'expect' => 404,
        'markers' => ['404', 'Not Found'],
    ],

    // ---- Room Details ----
    [
        'name'   => 'Room EN',
        'url'    => '/rooms/1',
        'expect' => 200,
        'markers' => ['Deluxe King Room', 'About this Room', 'Room Facilities', 'Book Room'],
    ],
    [
        'name'   => 'Room AR',
        'url'    => '/ar/rooms/1',
        'expect' => 200,
        'markers' => ['غرفة ديلوكس كينج', 'غرفة أنيقة بفراش فاخر', 'سرير كينج واحد', 'واي فاي مجاني'],
    ],
    [
        'name'   => 'Room 404',
        'url'    => '/rooms/99999',
        'expect' => 404,
        'markers' => ['404', 'Not Found'],
    ],

    // ---- Tour Details ----
    [
        'name'   => 'Tour EN',
        'url'    => '/tours/jordan-360',
        'expect' => 200,
        'markers' => ['Jordan 360', 'Overview', 'Itinerary', 'Upcoming Departures', 'Book Now'],
    ],
    [
        'name'   => 'Tour AR',
        'url'    => '/ar/tours/jordan-360',
        'expect' => 200,
        'markers' => ['الأردن 360°', 'استكشف المدينة الوردية', 'اللقاء في عمّان', 'إقامة 7 ليالٍ', 'البتراء ليلاً'],
    ],
    [
        'name'   => 'Tour 404',
        'url'    => '/tours/does-not-exist',
        'expect' => 404,
        'markers' => ['404', 'Not Found'],
    ],
];

$pass = 0;
$fail = 0;

foreach ($tests as $t) {
    // Use ignore_errors so we can read the body even on 404 responses
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);
    $html = @file_get_contents($base . $t['url'], false, $ctx);
    $code = 0;
    if (isset($http_response_header[0])) {
        preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m);
        $code = (int)$m[1];
    }

    $statusOk = ($code === $t['expect']);
    $markersOk = true;
    $missing = [];
    foreach ($t['markers'] as $marker) {
        if (mb_strpos((string)$html, $marker) === false) {
            $markersOk = false;
            $missing[] = $marker;
        }
    }

    $ok = $statusOk && $markersOk;
    if ($ok) {
        $pass++;
        echo "PASS  {$t['name']}  (code={$code})\n";
    } else {
        $fail++;
        echo "FAIL  {$t['name']}  (code={$code}, expected={$t['expect']})";
        if (!$statusOk) echo " [status mismatch]";
        if (!$markersOk) echo " [missing: " . implode(', ', $missing) . "]";
        echo "\n";
    }
}

echo "\n=== Smoke test results: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
<?php
$base = 'http://localhost:8000';
$routes = [
    // ---- Existing routes ----
    '/about'      => ['page', 'About GAIA'],
    '/contact'    => ['page', 'Contact'],
    '/events'     => ['night', 'GAIA Night'],
    '/reviews'    => ['home', 'Reviews'],
    '/hotels'     => ['home', 'Hotels'],
    '/en/events'  => ['night', 'GAIA Night'],
    '/ar/reviews' => ['home', 'GAIA'],
    '/ar/contact' => ['page', 'Contact'],
    '/en/hotels'  => ['home', 'Hotels'],

    // ---- Detail pages: Events ----
    '/events/wadi-rum-desert-gala'   => ['event', 'Wadi Rum Desert Gala'],
    '/en/events/wadi-rum-desert-gala' => ['event', 'Wadi Rum Desert Gala'],
    '/ar/events/wadi-rum-desert-gala' => ['event', 'حفلة وادي رم الصحراوية'],

    // ---- Detail pages: Hotels ----
    '/hotels/st-regis-amman'   => ['hotel', 'The St. Regis Amman'],
    '/en/hotels/st-regis-amman' => ['hotel', 'The St. Regis Amman'],
    '/ar/hotels/st-regis-amman' => ['hotel', 'سانت ريجيس عمان'],

    // ---- Detail pages: Rooms ----
    '/rooms/1'   => ['room', 'Deluxe King Room'],
    '/en/rooms/1' => ['room', 'Deluxe King Room'],
    '/ar/rooms/1' => ['room', 'غرفة ديلوكس كينج'],

    // ---- Detail pages: Tours ----
    '/tours/jordan-360'   => ['tour', 'Jordan 360'],
    '/en/tours/jordan-360' => ['tour', 'Jordan 360'],
    '/ar/tours/jordan-360' => ['tour', 'الأردن 360°'],

    // ---- Invalid IDs / slugs (should 404) ----
    '/events/does-not-exist' => ['404', 'Not Found'],
    '/hotels/does-not-exist' => ['404', 'Not Found'],
    '/rooms/99999'           => ['404', 'Not Found'],
    '/tours/does-not-exist'  => ['404', 'Not Found'],
];

foreach ($routes as $path => [$type, $marker]) {
    // Use ignore_errors so we can read the body even on 404 responses
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);
    $html = @file_get_contents($base . $path, false, $ctx);
    $code = 0;
    if (isset($http_response_header[0])) {
        preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m);
        $code = (int)$m[1];
    }
    $hasMarker = mb_strpos((string)$html, $marker) !== false;
    $expectedCode = ($type === '404') ? 404 : 200;
    $ok = ($code === $expectedCode && $hasMarker) ? 'OK' : 'FAIL';
    echo sprintf("%-32s %-5s code=%d type=%s marker[%s]=%s\n", $path, $ok, $code, $type, $marker, $hasMarker ? 'found' : 'MISSING');
}
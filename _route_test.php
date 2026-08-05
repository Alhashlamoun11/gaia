<?php
$base = 'http://localhost:8000';
$routes = [
    '/about'      => ['page', 'About GAIA'],
    '/contact'    => ['page', 'Contact'],
    '/events'     => ['night', 'GAIA Night'],
    '/reviews'    => ['home', 'Reviews'],
    '/hotels'     => ['home', 'Hotels'],
    '/en/events'  => ['night', 'GAIA Night'],
    '/ar/reviews' => ['home', 'تقييمات'],
    '/ar/contact' => ['page', 'اتصل بنا'],
    '/en/hotels'  => ['home', 'Hotels'],
];

foreach ($routes as $path => [$type, $marker]) {
    $ctx = stream_context_create(['http' => ['timeout' => 15]]);
    $html = @file_get_contents($base . $path, false, $ctx);
    $code = 0;
    if (isset($http_response_header[0])) {
        preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m);
        $code = (int)$m[1];
    }
    $hasMarker = mb_strpos((string)$html, $marker) !== false;
    $ok = ($code === 200 && $hasMarker) ? 'OK' : 'FAIL';
    echo sprintf("%-14s %-5s code=%d type=%s marker[%s]=%s\n", $path, $ok, $code, $type, $marker, $hasMarker ? 'found' : 'MISSING');
}

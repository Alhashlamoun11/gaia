<?php
function test_route($uri) {
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['SCRIPT_NAME'] = '/router.php';
    $_GET = [];
    
    ob_start();
    $res = include __DIR__ . '/../router.php';
    $output = ob_get_clean();
    
    echo "URI: $uri\n";
    echo "Return value: " . var_export($res, true) . "\n";
    echo "Output length: " . strlen($output) . "\n";
    echo "First 100 chars of output: " . substr(trim($output), 0, 100) . "\n";
    echo "----------------------------------------\n";
}

if (isset($argv[1])) {
    test_route($argv[1]);
}

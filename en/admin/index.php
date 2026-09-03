<?php
$lang = 'en';
$qs = $_SERVER['QUERY_STRING'] ?? '';
$target = '/admin/index.php?lang=' . $lang . ($qs ? '&' . $qs : '');
header('Location: ' . $target, true, 302);
exit;

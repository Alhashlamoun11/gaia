<?php
$lang = 'en';
$qs = $_SERVER['QUERY_STRING'] ?? '';
$target = '/admin/login.php?lang=' . $lang . ($qs ? '&' . $qs : '');
header('Location: ' . $target, true, 302);
exit;

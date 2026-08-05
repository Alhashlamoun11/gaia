<?php
$hash = '$2y$10$MGdek90Hm92g/8e8NCqiiOib0kuASU.Yh3R7.z0sHBYPn/iAVgneW';
$pass = 'Admin@12345';
var_dump(password_verify($pass, $hash));

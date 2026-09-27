<?php
return [
    ['GET',  '/attendance',          __DIR__ . '/pages/index.php'],
    ['GET',  '/attendance/inside',   __DIR__ . '/pages/inside.php'],
    ['GET',  '/attendance/log',      __DIR__ . '/pages/log.php'],
    ['POST', '/attendance/checkin',  __DIR__ . '/pages/checkin.php'],
    ['POST', '/attendance/checkout', __DIR__ . '/pages/checkout.php'],
];
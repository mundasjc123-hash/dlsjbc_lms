<?php
return [
    ['GET',  '/attendance',          __DIR__ . '/pages/index.php'],
    ['POST', '/attendance/checkin',  __DIR__ . '/pages/checkin.php'],
    ['POST', '/attendance/checkout', __DIR__ . '/pages/checkout.php'],
];
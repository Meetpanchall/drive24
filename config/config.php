<?php
declare(strict_types=1);

/** DRIVE24 configuration - point these at your MySQL server. */
return [
    'db' => [
        'host'    => getenv('DB_HOST') ?: '127.0.0.1',
        'port'    => getenv('DB_PORT') ?: '3306',
        'name'    => getenv('DB_NAME') ?: 'drive24',
        'user'    => getenv('DB_USER') ?: 'root',
        'pass'    => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
        'charset' => 'utf8mb4',
    ],
    'app' => ['name' => 'DRIVE24', 'debug' => getenv('APP_DEBUG') === '1'],
    // Razorpay payment gateway - TEST keys below work out of the box.
    // Override with RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET (or RAZORPAY_SECRET) env vars,
    // or paste LIVE keys here when going production.
    'razorpay' => [
        'key_id'     => getenv('RAZORPAY_KEY_ID') ?: 'rzp_test_SWgdnbEihzyE7h',
        'key_secret' => getenv('RAZORPAY_KEY_SECRET') ?: (getenv('RAZORPAY_SECRET') ?: '0CkeTvviOmEisbIcpnyuMIev'),
    ],
];

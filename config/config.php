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
];

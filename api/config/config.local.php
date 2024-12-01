<?php

use App\LogLevel;

return [
    'database' => [
        'host' => 'localhost',
        'username' => 'splk',
        'password' => 'splk',
        'dbname' => 'splk',
    ],
    'auth' => [
        'jwt_secret' => 'my_little_secret',
    ],
    'pathIndex' => [
        'table' => 0,
        'id' => 1,
    ],
    'log' => [
        'path' => __DIR__ . '/../../log/api/app.log',
        'level' => LogLevel::DEBUG,
    ],
];

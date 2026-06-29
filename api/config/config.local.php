<?php

use App\LogLevel;

return [
    'database' => [
        'host' => $_ENV['DB_HOST'] ?? 'localhost:8997',
        'username' => $_ENV['DB_USERNAME'] ?? 'splk',
        'password' => $_ENV['DB_PASSWORD'] ?? 'splk',
        'dbname' => $_ENV['DB_NAME'] ?? 'splk',
    ],
    'auth' => [
        'jwt_secret' => $_ENV['JWT_SECRET'] ?? 'my_little_secret',
    ],
    'pathIndex' => [
        'table' => 0,
        'id' => 1,
    ],
    'log' => [
        'path' => __DIR__ . '/' . ($_ENV['LOG_PATH'] ?? '../../log/api/app.log'),
        'level' => LogLevel::tryFrom($_ENV['LOG_LEVEL'] ?? 'DEBUG') ?? LogLevel::DEBUG,
    ],
];

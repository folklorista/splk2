<?php

use App\LogLevel;

// Validate required JWT_SECRET
if (empty($_ENV['JWT_SECRET'])) {
    throw new \Exception(
        "Critical Configuration Error: JWT_SECRET environment variable is not set.\n" .
        "This is required for API security.\n" .
        "Please add JWT_SECRET to your .env file.\n" .
        "Example: JWT_SECRET=your-very-long-random-secret-at-least-32-chars"
    );
}

return [
    'database' => [
        'host' => $_ENV['DB_HOST'] ?? 'localhost:8997',
        'username' => $_ENV['DB_USERNAME'] ?? 'splk',
        'password' => $_ENV['DB_PASSWORD'] ?? 'splk',
        'dbname' => $_ENV['DB_NAME'] ?? 'splk',
    ],
    'auth' => [
        'jwt_secret' => $_ENV['JWT_SECRET'],
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

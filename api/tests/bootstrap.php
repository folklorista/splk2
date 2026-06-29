<?php
/**
 * PHPUnit Bootstrap File
 *
 * Setup test environment
 */

// Load composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables if .env.test exists
$envTestFile = __DIR__ . '/../.env.test';
if (file_exists($envTestFile)) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..', '.env.test');
    $dotenv->load();
}

// Skip API check for Unit tests (they don't need running API)
$testDir = $_SERVER['argv'][1] ?? '';
$isUnitTest = strpos($testDir, 'Unit') !== false || strpos($testDir, 'tests/Unit') !== false;

// Ensure API is running (skip for unit tests)
if (!$isUnitTest && php_sapi_name() === 'cli') {
    $apiUrl = 'http://localhost:8000';
    $ch = curl_init($apiUrl . '/login');
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['email' => 'test@test.com', 'password' => 'test']),
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "ERROR: Cannot connect to API at $apiUrl\n";
        echo "Make sure API is running: php -S localhost:8000 -t public\n";
        exit(1);
    }

    echo "✓ API is running at $apiUrl\n";
}

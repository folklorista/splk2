<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$env = $_ENV['APP_ENV'] ?? 'local';
$config = require __DIR__ . '/config/config.' . ($env === 'production' ? 'production' : 'local') . '.php';

try {
    $dsn = "mysql:host={$config['database']['host']};dbname={$config['database']['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['database']['username'], $config['database']['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check audit_logs table structure
    $stmt = $pdo->prepare("DESCRIBE audit_logs");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "audit_logs table structure:\n";
    echo "================================\n";
    foreach ($columns as $col) {
        echo "{$col['Field']}: {$col['Type']} {$col['Null']}\n";
    }

    // Check if old_values and new_values exist
    $hasOldValues = false;
    $hasNewValues = false;

    foreach ($columns as $col) {
        if ($col['Field'] === 'old_values') $hasOldValues = true;
        if ($col['Field'] === 'new_values') $hasNewValues = true;
    }

    echo "\n================================\n";
    echo "old_values column: " . ($hasOldValues ? "✓ EXISTS" : "✗ MISSING") . "\n";
    echo "new_values column: " . ($hasNewValues ? "✓ EXISTS" : "✗ MISSING") . "\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

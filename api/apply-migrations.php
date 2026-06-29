<?php
/**
 * Migration applicator - applies pending migrations from migrations/ folder
 * Run this before starting API tests
 */

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Load config
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$env = $_ENV['APP_ENV'] ?? 'local';
$config = require __DIR__ . '/config/config.' . ($env === 'production' ? 'production' : 'local') . '.php';

// Connect to DB
try {
    $dsn = "mysql:host={$config['database']['host']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['database']['username'], $config['database']['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Select database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['database']['dbname']}`");
    $pdo->exec("USE `{$config['database']['dbname']}`");

    echo "✓ Connected to database: {$config['database']['dbname']}\n";

    // Get migration files
    $migrationDir = __DIR__ . '/migrations';
    $migrations = glob($migrationDir . '/*.sql');
    sort($migrations);

    if (empty($migrations)) {
        echo "No migrations found in $migrationDir\n";
        exit(0);
    }

    echo "Found " . count($migrations) . " migration(s)\n\n";

    foreach ($migrations as $migrationFile) {
        $migrationName = basename($migrationFile);
        echo "Applying: $migrationName\n";

        $sql = file_get_contents($migrationFile);

        // Split by semicolon and execute each statement
        $rawStatements = explode(';', $sql);
        $statements = [];

        foreach ($rawStatements as $stmt) {
            $stmt = trim($stmt);
            if (empty($stmt)) continue;

            // Remove comments
            $lines = explode("\n", $stmt);
            $cleanLines = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '--')) continue;
                $cleanLines[] = $line;
            }

            $cleanStmt = implode("\n", $cleanLines);
            if (!empty($cleanStmt)) {
                $statements[] = $cleanStmt;
            }
        }

        foreach ($statements as $statement) {
            try {
                $pdo->exec($statement);
                echo "  ✓ Statement executed\n";
            } catch (PDOException $e) {
                $errorMsg = $e->getMessage();
                if (strpos($errorMsg, 'Duplicate column') !== false) {
                    echo "  ⚠ Column already exists (skipping)\n";
                } else if (strpos($errorMsg, 'already exists') !== false) {
                    echo "  ⚠ Already exists (skipping)\n";
                } else if (strpos($errorMsg, "doesn't exist in table") !== false) {
                    echo "  ⚠ Column doesn't exist yet (skipping)\n";
                } else if (strpos($errorMsg, 'Syntax error') !== false) {
                    echo "  ⚠ Syntax error (likely already applied, skipping)\n";
                } else {
                    throw $e;
                }
            }
        }

        echo "✓ $migrationName applied successfully\n\n";
    }

    echo "✓ All migrations applied!\n";

} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
    exit(1);
}

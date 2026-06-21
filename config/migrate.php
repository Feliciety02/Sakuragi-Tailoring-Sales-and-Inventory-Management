<?php
/**
 * Simple migration runner
 * Usage: php config/migrate.php
 * 
 * Creates a migrations_log table to track applied migrations
 * and runs any pending SQL files from config/migrations/
 */

require_once __DIR__ . '/db_connect.php';

// Create migrations log table if not exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS migrations_log (
        migration_id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Get applied migrations
$applied = $pdo->query("SELECT filename FROM migrations_log")
    ->fetchAll(PDO::FETCH_COLUMN);

// Get migration files
$files = glob(__DIR__ . '/migrations/*.sql');
sort($files);

$count = 0;
foreach ($files as $file) {
    $filename = basename($file);
    if (in_array($filename, $applied, true)) {
        echo "Skipped (already applied): $filename\n";
        continue;
    }

    $sql = file_get_contents($file);
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => !empty($s) && !str_starts_with($s, '--')
    );

    try {
        foreach ($statements as $stmt) {
            $pdo->exec($stmt);
        }
        $pdo->prepare("INSERT INTO migrations_log (filename) VALUES (?)")
            ->execute([$filename]);
        echo "Applied: $filename\n";
        $count++;
    } catch (PDOException $e) {
        echo "Error applying $filename: " . $e->getMessage() . "\n";
        exit(1);
    }
}

if ($count === 0) {
    echo "All migrations are up to date.\n";
} else {
    echo "Applied $count migration(s) successfully.\n";
}

<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$lockName = 'santafe_schema_migrations';
$lock = $conn->prepare('SELECT GET_LOCK(?, 10)');
$lock->bind_param('s', $lockName);
$lock->execute();
if ((int)$lock->get_result()->fetch_row()[0] !== 1) {
    fwrite(STDERR, "Could not acquire the migration lock.\n");
    exit(1);
}

try {
    $conn->query("CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(100) PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $applied = [];
    $result = $conn->query('SELECT version FROM schema_migrations');
    while ($row = $result->fetch_assoc()) $applied[$row['version']] = true;

    $files = glob(__DIR__ . '/../database/migrations/*.php') ?: [];
    sort($files, SORT_STRING);
    foreach ($files as $file) {
        $version = pathinfo($file, PATHINFO_FILENAME);
        if (isset($applied[$version])) continue;
        $migration = require $file;
        if (!is_callable($migration)) throw new RuntimeException("Migration {$version} is not callable.");
        echo "Applying {$version}... ";
        $migration($conn);
        $stmt = $conn->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
        $stmt->bind_param('s', $version);
        $stmt->execute();
        echo "done\n";
    }
    echo "Database is up to date.\n";
} finally {
    $release = $conn->prepare('SELECT RELEASE_LOCK(?)');
    $release->bind_param('s', $lockName);
    $release->execute();
}


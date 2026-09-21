<?php
require_once __DIR__ . '/env_loader.php';
load_env(__DIR__ . '/../../.env');
// Shared hosting connection settings. Environment DB_* settings take precedence
// in the application and backup runner. This file performs no database queries.
function agila_database_config(): array
{
    $config = [];
    foreach (['host' => 'DB_HOST', 'user' => 'DB_USER', 'password' => 'DB_PASS', 'database' => 'DB_NAME'] as $key => $variable) {
        $value = getenv($variable);
        if ($value === false) {
            $value = $_ENV[$variable] ?? $_SERVER[$variable] ?? null;
        }
        if ($value === null || $value === '') {
            throw new RuntimeException('Missing required database environment variable: ' . $variable);
        }
        $config[$key] = $value;
    }
    $config['port'] = (int)(getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? 3306));
    return $config;
}

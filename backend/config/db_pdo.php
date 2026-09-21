<?php
require_once __DIR__ . '/env_loader.php';
load_env(__DIR__ . '/../../.env');

function getPdoConnection(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => true];
    $host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? '');
    if ($host) {
        $port = (int)(getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? 3306));
        $name = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? 'defaultdb');
        $user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? 'root');
        $pass = getenv('DB_PASS');
        if ($pass === false) $pass = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? '';
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, $options);
        return $pdo;
    }
    $local = in_array($_SERVER['HTTP_HOST'] ?? '127.0.0.1', ['localhost','127.0.0.1','::1'], true);
    if ($local) {
        foreach ([3307,3306] as $port) {
            try { $pdo = new PDO("mysql:host=127.0.0.1;port=$port;dbname=santafe_beach_club;charset=utf8mb4", 'root', '', $options); return $pdo; }
            catch (PDOException $e) { /* Try the next local MySQL port. */ }
        }
    }
    throw new RuntimeException('Database unavailable. Configure DB_HOST, DB_PORT, DB_NAME, DB_USER and DB_PASS.');
}

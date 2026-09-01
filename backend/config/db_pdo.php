<?php

function getPdoConnection(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $is_local_env = in_array($_SERVER['HTTP_HOST'] ?? '127.0.0.1', ['localhost', '127.0.0.1', '::1']);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => true,
    ];

    if ($is_local_env) {
        try {
            $host = '127.0.0.1';
            $port = 3307;
            $db   = 'santafe_beach_club';
            $user = 'root';
            $pass = '';
            $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, $options);
            return $pdo;
        } catch (PDOException $e) {
            // Fallback to live host if local is down
        }
    }

    // Live InfinityFree Database
    $host = 'sql111.infinityfree.com';
    $port = 3306;
    $db   = 'if0_42717273_santafebeachclub_db';
    $user = 'if0_42717273';
    $pass = 'ndAuPvlRiQVG';
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, $options);
    return $pdo;
}

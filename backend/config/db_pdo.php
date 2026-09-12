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

    // Try Agila Hosting MySQL
    try {
        $host = 'localhost';
        $db   = 'justinebo4ek_db';
        $user = 'justinebo4ek_user';
        $pass = 'oWDtO(BCPrI6pM&%';
        $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Fallback failed, throw error
        throw new PDOException("No database connection available. Agila: " . $e->getMessage());
    }
}

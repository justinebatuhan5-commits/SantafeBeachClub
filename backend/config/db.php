<?php
require_once __DIR__ . '/env_loader.php';
load_env(__DIR__ . '/../../.env');
require_once __DIR__ . '/../helpers/error_handler.php';
require_once __DIR__ . '/../helpers/business_time_helper.php';
require_once __DIR__ . '/../helpers/password_helper.php';

// Connection bootstrap only. Schema and catalog changes belong in
// backend/scripts/migrate.php and must never be triggered by an HTTP request.
$is_local_env = in_array($_SERVER['HTTP_HOST'] ?? '127.0.0.1', ['localhost', '127.0.0.1', '::1'], true);
mysqli_report(MYSQLI_REPORT_OFF);
$conn = null;
$cloud_connect_error = null;

$env_host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? ($_SERVER['DB_HOST'] ?? null));
if ($env_host) {
    $env_user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? ($_SERVER['DB_USER'] ?? 'root'));
    $env_pass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? ($_SERVER['DB_PASS'] ?? ''));
    $env_name = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? ($_SERVER['DB_NAME'] ?? 'defaultdb'));
    $env_port = (int)(getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? ($_SERVER['DB_PORT'] ?? 3306)));
    $conn = mysqli_init();
    if ($conn) {
        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
        $conn->ssl_set(null, null, null, null, null);
        if (!@$conn->real_connect($env_host, $env_user, $env_pass, $env_name, $env_port, null, MYSQLI_CLIENT_SSL)) {
            @$conn->real_connect($env_host, $env_user, $env_pass, $env_name, $env_port);
        }
        if (!$conn->connect_error) $dbname = $env_name;
        else $cloud_connect_error = $conn->connect_error;
    }
}

if (!$env_host && (!$conn || $conn->connect_error) && $is_local_env) {
    $conn = @new mysqli('127.0.0.1', 'root', '', 'santafe_beach_club', 3307);
    if (!$conn || $conn->connect_error) $conn = @new mysqli('127.0.0.1', 'root', '', 'santafe_beach_club', 3306);
    if ($conn && !$conn->connect_error) $dbname = 'santafe_beach_club';
}

if (!$env_host && !$is_local_env && (!$conn || $conn->connect_error)) {
    require_once __DIR__ . '/agila_database.php';
    $config = agila_database_config();
    $candidate = @new mysqli($config['host'], $config['user'], $config['password'], $config['database']);
    if ($candidate && !$candidate->connect_error) {
        $conn = $candidate;
        $dbname = $config['database'];
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    if (!$conn || $conn->connect_error) {
        $error = $conn ? $conn->connect_error : ($cloud_connect_error ?? 'Unable to connect to database host');
        throw new mysqli_sql_exception('Connection failed: ' . $error . ' (Configure DB_HOST or use local XAMPP)');
    }
    @$conn->select_db($dbname);
    try {
        $propertyTimezone = sf_get_property_timezone_setting($conn);
    } catch (mysqli_sql_exception $e) {
        // A brand-new database can be connected only so the CLI migrator can
        // create its schema. HTTP requests still perform no schema writes.
        $propertyTimezone = SF_DEFAULT_PROPERTY_TIMEZONE;
    }
    date_default_timezone_set($propertyTimezone);
    $offset = (new DateTimeImmutable('now', new DateTimeZone($propertyTimezone)))->format('P');
    $conn->query("SET time_zone = '" . $conn->real_escape_string($offset) . "'");
} catch (mysqli_sql_exception $e) {
    http_response_code(503);
    echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1.0'><title>Database Connection Error</title></head><body><main><h1>MySQL Database Offline</h1><p>Cannot connect to MySQL. "
        . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        . "</p><p>Verify the database credentials and run deployment migrations from the command line.</p></main></body></html>";
    exit;
}

require_once __DIR__ . '/../services/discount_service.php';

function log_activity($conn, $username, $action, $details = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare('INSERT INTO activity_logs (admin_username, action, details, ip_address) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssss', $username, $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
}
?>

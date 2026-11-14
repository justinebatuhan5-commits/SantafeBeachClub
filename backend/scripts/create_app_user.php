<?php
/**
 * create_app_user.php — One-time setup script
 * Creates a restricted MySQL user for the application.
 * Run ONCE then DELETE this file immediately.
 */

$host = '127.0.0.1';
$port = 3307;
$dbname = 'santafe_beach_club';

// Connect as root to create the new user
$conn = new mysqli($host, 'root', '', '', $port);
if ($conn->connect_error) {
    die("Root connection failed: " . $conn->connect_error . PHP_EOL);
}

$app_user = 'santafe_app';
$app_pass = 'Sf@Beach2024!Secure';

// Drop if already exists (safe re-run)
$conn->query("DROP USER IF EXISTS '{$app_user}'@'127.0.0.1'");
$conn->query("DROP USER IF EXISTS '{$app_user}'@'localhost'");

// Create user with password
$conn->query("CREATE USER '{$app_user}'@'127.0.0.1' IDENTIFIED BY '{$app_pass}'");
$conn->query("CREATE USER '{$app_user}'@'localhost'  IDENTIFIED BY '{$app_pass}'");

// Grant ONLY the privileges the app actually needs on just this one database
$privs = "SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES";
$conn->query("GRANT {$privs} ON `{$dbname}`.* TO '{$app_user}'@'127.0.0.1'");
$conn->query("GRANT {$privs} ON `{$dbname}`.* TO '{$app_user}'@'localhost'");

$conn->query("FLUSH PRIVILEGES");

// Verify
$result = $conn->query("SHOW GRANTS FOR '{$app_user}'@'localhost'");
echo "=== Grants for {$app_user} ===" . PHP_EOL;
while ($row = $result->fetch_row()) {
    echo $row[0] . PHP_EOL;
}

// Test connection with new user
$test = new mysqli($host, $app_user, $app_pass, $dbname, $port);
if ($test->connect_error) {
    die(PHP_EOL . "[FAIL] Test connection FAILED: " . $test->connect_error . PHP_EOL);
}

echo PHP_EOL . "[OK] App user '{$app_user}' created and verified successfully." . PHP_EOL;
echo "[OK] Test connection to '{$dbname}' — OK" . PHP_EOL;
echo PHP_EOL . "[WARNING] DELETE this file immediately after running!" . PHP_EOL;

$test->close();
$conn->close();

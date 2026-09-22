<?php
/**
 * One-time initial administrator setup.
 *
 * Run only from a protected server shell:
 *   php backend/scripts/create_initial_admin.php
 *
 * The script refuses to run over HTTP and refuses to add an administrator when
 * one already exists. After setup, manage users from the authenticated admin UI.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

require_once __DIR__ . '/../config/db.php';

$existing = $conn->query('SELECT id FROM administrators LIMIT 1');
if ($existing && $existing->num_rows > 0) {
    exit("An administrator already exists. Use the authenticated admin interface to manage users.\n");
}

fwrite(STDOUT, 'Administrator email: ');
$email = trim((string) fgets(STDIN));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("A valid email address is required.\n");
}

fwrite(STDOUT, 'Administrator password: ');
$password = rtrim((string) fgets(STDIN), "\r\n");
$passwordError = pw_validate($password);
if ($passwordError !== null) {
    exit($passwordError . "\n");
}

$hash = pw_hash($password);
$stmt = $conn->prepare('INSERT INTO administrators (username, password, email) VALUES (?, ?, ?)');
$stmt->bind_param('sss', $email, $hash, $email);
$stmt->execute();
$stmt->close();

fwrite(STDOUT, "Initial administrator created successfully.\n");

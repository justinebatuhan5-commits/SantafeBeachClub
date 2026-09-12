<?php
/**
 * db_restore_test.php — Backup Restoration Test Script
 * Tests that a backup file can be fully restored to a test database and verified.
 * Run manually when you want to verify backup integrity.
 *
 * Usage: php backend/scripts/db_restore_test.php [backup_file.sql]
 *        If no file specified, uses the most recent backup.
 */

$host     = '127.0.0.1';
$port     = 3307;
$user     = 'root';
$pass     = '';
$mysqldump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
$mysql    = 'C:\\xampp\\mysql\\bin\\mysql.exe';
$backup_dir = __DIR__ . '/../logs/backups/';
$test_db  = 'santafe_restore_test';

echo "=== Santa Fe Beach Club — Backup Restoration Test ===" . PHP_EOL;
echo "[" . date('Y-m-d H:i:s') . "] Starting restoration test..." . PHP_EOL;

// ── 1. Find backup file ──────────────────────────────────────────────────────
if (!empty($argv[1]) && file_exists($argv[1])) {
    $backup_file = $argv[1];
} else {
    $files = glob($backup_dir . 'santafe_*.sql');
    if (empty($files)) {
        die("[ERROR] No backup files found in {$backup_dir}. Run db_backup.php first.\n");
    }
    usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
    $backup_file = $files[0];
}

$size_kb = round(filesize($backup_file) / 1024, 1);
echo "[+] Using backup: " . basename($backup_file) . " ({$size_kb} KB)" . PHP_EOL;

// ── 2. Verify backup file integrity ─────────────────────────────────────────
echo "[*] Verifying backup file integrity..." . PHP_EOL;
$content = file_get_contents($backup_file, false, null, 0, 200);
if (strpos($content, 'MariaDB dump') === false && strpos($content, 'MySQL dump') === false && strpos($content, 'mysqldump') === false) {
    die("[ERROR] Backup file does not appear to be a valid mysqldump file.\n");
}
echo "[+] PASS: File header looks like a valid mysqldump." . PHP_EOL;

// ── 3. Create test database ──────────────────────────────────────────────────
echo "[*] Creating test database: {$test_db}..." . PHP_EOL;
$conn = @new mysqli($host, $user, $pass, '', $port);
if ($conn->connect_error) {
    die("[ERROR] Could not connect to MySQL: " . $conn->connect_error . "\n");
}
$conn->query("DROP DATABASE IF EXISTS `{$test_db}`");
$conn->query("CREATE DATABASE `{$test_db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "[+] Test database '{$test_db}' created." . PHP_EOL;

// ── 4. Restore the backup into the test database ─────────────────────────────
echo "[*] Restoring backup into test database..." . PHP_EOL;
$cmd = escapeshellarg($mysql)
     . " -h {$host} -P {$port} -u {$user}"
     . " {$test_db}"
     . " < " . escapeshellarg($backup_file)
     . " 2>&1";

exec($cmd, $output, $exit_code);
if ($exit_code !== 0) {
    echo "[ERROR] Restore failed:\n" . implode("\n", $output) . "\n";
    $conn->query("DROP DATABASE IF EXISTS `{$test_db}`");
    exit(1);
}
echo "[+] PASS: Backup successfully restored to test database." . PHP_EOL;

// ── 5. Verify restored data integrity ────────────────────────────────────────
echo "[*] Verifying restored data..." . PHP_EOL;
$conn->select_db($test_db);

$checks = [
    'rooms'          => 'SELECT COUNT(*) AS c FROM rooms',
    'administrators' => 'SELECT COUNT(*) AS c FROM administrators',
    'receptionists'  => 'SELECT COUNT(*) AS c FROM receptionists',
    'bookings'       => 'SELECT COUNT(*) AS c FROM bookings',
];

$all_ok = true;
foreach ($checks as $table => $sql) {
    $result = $conn->query($sql);
    if (!$result) {
        echo "  [-] FAIL: Table '{$table}' missing or unreadable.\n";
        $all_ok = false;
    } else {
        $row = $result->fetch_assoc();
        echo "  [+] PASS: Table '{$table}' has {$row['c']} record(s)." . PHP_EOL;
    }
}

// ── 6. Admin password hashes still valid (bcrypt) ────────────────────────────
$adminRes = $conn->query("SELECT username, LEFT(password, 4) AS prefix FROM administrators LIMIT 1");
if ($adminRes && $adminRes->num_rows > 0) {
    $admin = $adminRes->fetch_assoc();
    if ($admin['prefix'] === '$2y$') {
        echo "  [+] PASS: Admin password hashes verified (bcrypt format intact)." . PHP_EOL;
    } else {
        echo "  [-] FAIL: Admin password hashes appear corrupted." . PHP_EOL;
        $all_ok = false;
    }
}

// ── 7. Clean up test database ────────────────────────────────────────────────
echo "[*] Cleaning up test database..." . PHP_EOL;
$conn->query("DROP DATABASE IF EXISTS `{$test_db}`");
$conn->close();
echo "[+] Test database dropped." . PHP_EOL;

// ── 8. Final result ──────────────────────────────────────────────────────────
echo PHP_EOL;
if ($all_ok) {
    echo "✅ RESTORATION TEST PASSED — Backup is valid and restorable." . PHP_EOL;
} else {
    echo "❌ RESTORATION TEST FAILED — Review errors above." . PHP_EOL;
    exit(1);
}

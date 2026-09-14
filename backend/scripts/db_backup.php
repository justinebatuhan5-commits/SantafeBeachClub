<?php
/**
 * db_backup.php — Automated Database Backup Script
 * Run manually or schedule via cron / Windows Task Scheduler.
 *
 * Usage:
 *   php backend/scripts/db_backup.php
 *
 * Retention: Keeps the last 30 daily backups automatically.
 */

$host     = '127.0.0.1';
$port     = 3306;
$user     = 'root';
$pass     = '';
$dbname   = 'santafe_beach_club';

// Suppress strict exceptions during port detection
mysqli_report(MYSQLI_REPORT_OFF);
$testConn = @new mysqli($host, $user, $pass, $dbname, 3306);
if ($testConn && !$testConn->connect_error) {
    $port = 3306;
    $testConn->close();
} else {
    $testConn2 = @new mysqli($host, $user, $pass, $dbname, 3307);
    if ($testConn2 && !$testConn2->connect_error) {
        $port = 3307;
        $testConn2->close();
    }
}
$mysqldump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
$backup_dir = __DIR__ . '/../logs/backups/';

// Ensure backup directory exists
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

$timestamp   = date('Ymd_His');
$backup_file = $backup_dir . "santafe_{$timestamp}.sql";

// Build mysqldump command (password-free for root with no password)
$cmd = escapeshellarg($mysqldump)
     . " -h {$host} -P {$port} -u {$user}"
     . ($pass !== '' ? ' -p' . escapeshellarg($pass) : '')
     . " --no-tablespaces --single-transaction --routines --triggers"
     . " " . escapeshellarg($dbname)
     . " --result-file=" . escapeshellarg($backup_file)
     . " 2>&1";

exec($cmd, $output, $exit_code);

if ($exit_code === 0 && file_exists($backup_file) && filesize($backup_file) > 0) {
    $size_kb = round(filesize($backup_file) / 1024, 1);
    echo "[" . date('Y-m-d H:i:s') . "] ✅ Backup created: {$backup_file} ({$size_kb} KB)" . PHP_EOL;
} else {
    echo "[" . date('Y-m-d H:i:s') . "] ❌ Backup FAILED. Exit: {$exit_code}" . PHP_EOL;
    echo implode(PHP_EOL, $output) . PHP_EOL;
    exit(1);
}

// ── Retention: delete backups older than 30 days ──────────────────────────
$max_age_days = 30;
$cutoff = time() - ($max_age_days * 86400);
$old_files = glob($backup_dir . 'santafe_*.sql');
$deleted = 0;

if ($old_files) {
    foreach ($old_files as $file) {
        if (filemtime($file) < $cutoff) {
            unlink($file);
            $deleted++;
        }
    }
}

if ($deleted > 0) {
    echo "[" . date('Y-m-d H:i:s') . "] 🗑️  Purged {$deleted} backup(s) older than {$max_age_days} days." . PHP_EOL;
}

// ── Summary ────────────────────────────────────────────────────────────────
$all_backups = glob($backup_dir . 'santafe_*.sql');
$count = count($all_backups ?: []);
echo "[" . date('Y-m-d H:i:s') . "] 📦 Total backups on disk: {$count}" . PHP_EOL;

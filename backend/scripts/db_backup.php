<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../helpers/backup_helper.php';
try {
    if (in_array('--diagnose', $argv, true)) {
        $config = backup_database_config();
        $directory = backup_directory();
        $binary = getenv('BACKUP_MYSQLDUMP') ?: (PHP_OS_FAMILY === 'Windows' ? 'C:/xampp/mysql/bin/mysqldump.exe' : '/usr/bin/mysqldump');
        if (!function_exists('proc_open')) throw new RuntimeException('proc_open is disabled for this PHP runtime.');
        if (!is_file($binary) || !is_executable($binary)) throw new RuntimeException('Set BACKUP_MYSQLDUMP to the hosting server\'s executable path.');
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $connection = mysqli_init();
            $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
            $connection->real_connect($config['host'], $config['user'], $config['password'], $config['database'], $config['port']);
            $connection->close();
        } catch (Throwable $e) {
            throw new RuntimeException('Cannot connect to the backup database. Check the hosting database settings.');
        }
        echo "PHP: " . PHP_VERSION . "\nPrivate backup directory: " . $directory
            . "\nDatabase connection: OK\nmysqldump: available\n"
            . "Run without --diagnose to verify an actual export and its database privileges.\n";
        exit; 
    }
    echo json_encode(backup_run(in_array('--check', $argv, true)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Backup failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

<?php
// Integration test: creates only randomly named disposable databases and temp files.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../backend/helpers/backup_helper.php';
function check_backup(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo 'PASS: ' . $message . PHP_EOL;
}
$id = bin2hex(random_bytes(6));
$source = 'sf_backup_test_' . $id;
$restore = 'sf_backup_restore_' . $id;
$root = sys_get_temp_dir() . '/sf_backup_test_' . $id;
mkdir($root, 0700);
$connection = null;
try {
    // Never use a remote database for these tests.
    putenv('DB_HOST');
    $config = backup_database_config();
    $connection = new mysqli($config['host'], $config['user'], $config['password'], '', $config['port']);
    $connection->query("CREATE DATABASE `$source` CHARACTER SET utf8mb4");
    $connection->query("CREATE DATABASE `$restore` CHARACTER SET utf8mb4");
    $connection->select_db($source);
    $connection->query('CREATE TABLE fixture (id INT PRIMARY KEY, body TEXT, binary_data BLOB) ENGINE=InnoDB');
    $body = "Booking 'quote' \\ newline\n" . '日本語';
    $binary = "\0\x01\xff";
    $stmt = $connection->prepare('INSERT INTO fixture VALUES (1, ?, ?)');
    $stmt->bind_param('ss', $body, $binary);
    $stmt->execute();
    foreach (['DB_HOST' => $config['host'], 'DB_PORT' => $config['port'], 'DB_USER' => $config['user'],
        'DB_PASS' => $config['password'], 'DB_NAME' => $source, 'PRIVATE_STORAGE_DIR' => $root . '/private',
        'PRIVATE_STORAGE_DOCUMENT_ROOT' => dirname(__DIR__)] as $key => $value) putenv($key . '=' . $value);
    $first = backup_run(false);
    check_backup($first['status'] === 'created' && $first['backup']['reason'] === 'manual', 'Initial backup succeeds');
    $dir = backup_directory();
    check_backup(backup_run(true)['status'] === 'automatic_backups_disabled', 'Retired automatic scheduler does not create backups');
    $entry = $first['backup'];
    $sql = file_get_contents($dir . '/' . $entry['name']);
    $connection->select_db($restore);
    $connection->multi_query($sql);
    do { if ($result = $connection->store_result()) $result->free(); } while ($connection->more_results() && $connection->next_result());
    $row = $connection->query('SELECT * FROM fixture')->fetch_assoc();
    check_backup($row['body'] === $body && $row['binary_data'] === $binary, 'SQL restores Unicode, quotes and binary data into a separate database');
    check_backup(hash_file('sha256', $dir . '/' . $entry['name']) === $entry['sha256'], 'Manifest checksum matches the export');
    $lock = fopen($dir . '/backup.lock', 'c');
    flock($lock, LOCK_EX);
    check_backup(backup_run(false)['status'] === 'busy', 'Concurrent backups are blocked');
    flock($lock, LOCK_UN); fclose($lock);
    $before = backup_read_state($dir);
    putenv('BACKUP_MYSQLDUMP=' . $root . '/missing.exe');
    try { backup_run(false); throw new LogicException('Expected failure'); }
    catch (RuntimeException $e) { check_backup(str_contains($e->getMessage(), 'mysqldump'), 'Missing dump executable reports failure'); }
    $after = backup_read_state($dir);
    check_backup($before['last_success'] === $after['last_success'] && !empty($after['last_error']), 'Failure preserves the last successful backup and records an error');
    putenv('BACKUP_MYSQLDUMP');
    putenv('DB_NAME=' . $source . '_missing');
    $countBefore = count(backup_entries($dir));
    try { backup_run(false); throw new LogicException('Expected export failure'); }
    catch (RuntimeException $e) { check_backup(str_contains($e->getMessage(), 'export failed'), 'mysqldump failure is reported'); }
    check_backup(count(backup_entries($dir)) === $countBefore, 'Failed export is not published as a backup');
    check_backup(count(glob($dir . '/*.partial')) === 0 && count(glob($dir . '/credentials_*')) === 0, 'No partial exports or credential files remain');
    putenv('DB_NAME=' . $source);
    $old = $entry;
    $old['created_at'] = time() - 31 * 86400;
    file_put_contents($dir . '/' . $old['name'] . '.json', json_encode($old));
    backup_run(false);
    check_backup(!is_file($dir . '/' . $old['name']) && count(backup_entries($dir)) > 0, 'Retention removes an expired backup only after a successful replacement');
} finally {
    if ($connection) {
        foreach ([$source, $restore] as $database) {
            if (!preg_match('/\Asf_backup_(test|restore)_[a-f0-9]{12}\z/', $database)) throw new RuntimeException('Unsafe test database name');
            $connection->query("DROP DATABASE IF EXISTS `$database`");
        }
        $connection->close();
    }
    $resolved = realpath($root);
    if ($resolved && private_path_within($resolved, realpath(sys_get_temp_dir())) && basename($resolved) === 'sf_backup_test_' . $id) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
        rmdir($resolved);
    }
}

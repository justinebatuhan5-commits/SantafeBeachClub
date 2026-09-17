<?php
require_once __DIR__ . '/private_storage_helper.php';

function backup_directory(): string
{
    $root = private_storage_root(true);
    $path = $root . '/backups';
    if (!is_dir($path) && !mkdir($path, 0700) && !is_dir($path)) throw new RuntimeException('Cannot create backup directory.');
    $resolved = realpath($path);
    if (!$resolved || !private_path_within($resolved, $root)) throw new RuntimeException('Invalid backup directory.');
    return $resolved;
}

function backup_read_state(string $directory): array
{
    if (!is_file($directory . '/state.json')) return [];
    $state = json_decode(file_get_contents($directory . '/state.json'), true);
    if (!is_array($state)) throw new RuntimeException('Backup state is unreadable.');
    return $state;
}

function backup_write_state(string $directory, array $state): void
{
    if (file_put_contents($directory . '/state.tmp', json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false
        || !rename($directory . '/state.tmp', $directory . '/state.json')) throw new RuntimeException('Cannot save backup status.');
}

function backup_database_config(): array
{
    if (getenv('DB_HOST')) return ['host' => getenv('DB_HOST'), 'port' => (int)(getenv('DB_PORT') ?: 3306),
        'user' => getenv('DB_USER') ?: 'root', 'password' => getenv('DB_PASS') ?: '', 'database' => getenv('DB_NAME') ?: 'defaultdb'];
    if (PHP_OS_FAMILY !== 'Windows') {
        require_once __DIR__ . '/../config/agila_database.php';
        return agila_database_config();
    }
    // Match local db.php without invoking its schema migrations.
    foreach ([3307, 3306] as $port) {
        try {
            $connection = mysqli_init();
            $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
            if (@$connection->real_connect('127.0.0.1', 'root', '', 'santafe_beach_club', $port)) {
                $connection->close();
                return ['host' => '127.0.0.1', 'port' => $port, 'user' => 'root', 'password' => '', 'database' => 'santafe_beach_club'];
            }
        } catch (mysqli_sql_exception $e) { /* Try the other local port. */ }
    }
    throw new RuntimeException('Local database is unavailable. Start MySQL or configure DB_HOST.');
}

function backup_option_value(string $value): string
{
    return '"' . strtr($value, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\r" => '\\r']) . '"';
}

function backup_export(string $directory, string $reason, string $sourceHash): array
{
    $config = backup_database_config();
    if (!function_exists('proc_open')) {
        throw new RuntimeException('The host disables proc_open. Ask Agila to enable it for PHP CLI backups, then run the backup script through Cron Jobs.');
    }
    $binary = getenv('BACKUP_MYSQLDUMP') ?: (PHP_OS_FAMILY === 'Windows' ? 'C:/xampp/mysql/bin/mysqldump.exe' : '/usr/bin/mysqldump');
    if (!is_file($binary)) throw new RuntimeException('mysqldump is unavailable. Configure BACKUP_MYSQLDUMP.');
    $name = 'santafe_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.sql';
    $target = $directory . '/' . $name;
    $temporary = $target . '.partial';
    $credentials = tempnam($directory, 'credentials_');
    if ($credentials === false) throw new RuntimeException('Cannot create backup credentials file.');
    @chmod($credentials, 0600);
    $errors = $target . '.errors';
    try {
        $options = "[client]\n";
        foreach (['host', 'port', 'user', 'password'] as $key) $options .= $key . '=' . backup_option_value((string)$config[$key]) . "\n";
        if (file_put_contents($credentials, $options) === false) throw new RuntimeException('Cannot write backup options.');
        // Array arguments bypass shell interpretation; the password stays off the process command line.
        $command = [$binary, '--defaults-extra-file=' . $credentials, '--single-transaction', '--quick', '--hex-blob',
            '--routines', '--triggers', '--events', '--no-tablespaces', '--default-character-set=utf8mb4',
            '--result-file=' . $temporary, '--', $config['database']];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $errors, 'a'], 2 => ['file', $errors, 'a']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Cannot start mysqldump.');
        fclose($pipes[0]);
        $exit = proc_close($process);
        clearstatcache(true, $temporary);
        if ($exit !== 0 || !is_file($temporary) || filesize($temporary) === 0) {
            throw new RuntimeException('Database export failed (exit ' . $exit . '). Check database permissions and configuration.');
        }
        $handle = fopen($temporary, 'rb');
        fseek($handle, max(0, filesize($temporary) - 2048));
        $tail = stream_get_contents($handle);
        fclose($handle);
        if (strpos($tail, '-- Dump completed on ') === false) throw new RuntimeException('Database export did not complete.');
        $entry = ['name' => $name, 'created_at' => time(), 'reason' => $reason, 'bytes' => filesize($temporary),
            'sha256' => hash_file('sha256', $temporary), 'source_hash' => $sourceHash];
        if (!rename($temporary, $target)) throw new RuntimeException('Cannot finalize backup.');
        if (file_put_contents($target . '.json', json_encode($entry, JSON_THROW_ON_ERROR)) === false) throw new RuntimeException('Cannot save backup manifest.');
        return $entry;
    } finally {
        foreach ([$credentials, $temporary, $errors] as $path) if (is_file($path)) unlink($path);
    }
}

function backup_entries(string $directory): array
{
    $entries = [];
    foreach (glob($directory . '/santafe_*.sql.json') ?: [] as $manifest) {
        $entry = json_decode(file_get_contents($manifest), true);
        if (!is_array($entry) || !preg_match('/\Asantafe_\d{8}_\d{6}_[a-f0-9]{8}\.sql\z/', $entry['name'] ?? '')) continue;
        $path = $directory . '/' . $entry['name'];
        if ($manifest !== $path . '.json' || !is_file($path) || is_link($path) || filesize($path) === 0 || filesize($path) !== ($entry['bytes'] ?? -1)) continue;
        $entries[] = $entry;
    }
    usort($entries, static fn($a, $b) => $b['created_at'] <=> $a['created_at']);
    return $entries;
}

function backup_run(bool $onlyIfDue = true, string $manualReason = 'manual'): array
{
    // The former daily/code-change scheduler is retired. PC requests are explicit.
    if ($onlyIfDue) return ['status' => 'automatic_backups_disabled'];
    $directory = backup_directory();
    $lock = fopen($directory . '/backup.lock', 'c');
    if (!$lock) throw new RuntimeException('Cannot open backup lock.');
    if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); return ['status' => 'busy']; }
    $state = [];
    try {
        $state = backup_read_state($directory);
        $now = time();
        $state['last_check'] = $now;
        $state['last_attempt'] = $now;
        backup_write_state($directory, $state);
        $entry = backup_export($directory, $manualReason, '');
        $state['last_success'] = time();
        $state['last_backup'] = $entry['name'];
        $state['last_error'] = null;
        backup_write_state($directory, $state);
        // Prune completed backups only after successfully saving a replacement.
        foreach (backup_entries($directory) as $old) {
            if ($old['created_at'] < time() - 30 * 86400 && $old['name'] !== $entry['name']) {
                unlink($directory . '/' . $old['name']);
                unlink($directory . '/' . $old['name'] . '.json');
            }
        }
        return ['status' => 'created', 'backup' => $entry];
    } catch (Throwable $e) {
        $state['last_error'] = $e->getMessage();
        $state['last_error_at'] = time();
        backup_write_state($directory, $state);
        throw $e;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

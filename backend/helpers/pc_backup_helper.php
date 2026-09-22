<?php
require_once __DIR__ . '/backup_helper.php';

function pc_backup_read(): array
{
    $path = backup_directory() . '/pc-client.json';
    if (!is_file($path)) return [];
    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data)) throw new RuntimeException('PC backup configuration is unreadable.');
    return $data;
}

function pc_backup_write(array $data): void
{
    $directory = backup_directory();
    $temp = tempnam($directory, 'pc_config_');
    if (!$temp) throw new RuntimeException('Cannot save PC backup configuration.');
    try {
        chmod($temp, 0600);
        if (file_put_contents($temp, json_encode($data, JSON_THROW_ON_ERROR)) === false
            || !rename($temp, $directory . '/pc-client.json')) throw new RuntimeException('Cannot save PC backup configuration.');
    } finally { if (is_file($temp)) unlink($temp); }
}

function pc_backup_lock()
{
    $lock = fopen(backup_directory() . '/pc-client.lock', 'c');
    if (!$lock) throw new RuntimeException('Cannot lock PC backup configuration.');
    if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); throw new RuntimeException('A PC backup request is running. Try again shortly.'); }
    return $lock;
}

function pc_backup_pair(string $website): array
{
    $website = rtrim(trim($website), '/');
    $url = parse_url($website);
    if (!$url || ($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['user']) || isset($url['pass'])
        || isset($url['query']) || isset($url['fragment']) || preg_match('/[\x00-\x20]/', $website)) {
        throw new RuntimeException('Enter the HTTPS address of your website, including its project subfolder if applicable.');
    }
    $lock = pc_backup_lock();
    try {
        $token = bin2hex(random_bytes(32));
        pc_backup_write(['token_hash' => hash('sha256', $token), 'created_at' => time()]);
        return ['endpoint' => $website . '/backend/api/pc_backup.php', 'token' => $token, 'interval_hours' => 6];
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}

function pc_backup_authorized(string $token, array $state): bool
{
    return preg_match('/\A[a-f0-9]{64}\z/', $token) && !empty($state['token_hash']) && hash_equals($state['token_hash'], hash('sha256', $token));
}

function pc_backup_revoke(): void
{
    $lock = pc_backup_lock();
    try { pc_backup_write([]); } finally { flock($lock, LOCK_UN); fclose($lock); }
}

function pc_backup_acknowledge(string $token, string $requestId, string $sha256): void
{
    if (!preg_match('/\A[a-f0-9]{32}\z/', $requestId) || !preg_match('/\A[a-f0-9]{64}\z/', $sha256)) {
        throw new InvalidArgumentException('Invalid backup acknowledgement.');
    }
    $lock = pc_backup_lock();
    try {
        $state = pc_backup_read();
        if (!pc_backup_authorized($token, $state)) throw new RuntimeException('PC backup access denied.');
        $entry = $state['last_backup'] ?? [];
        if (($state['request_id'] ?? '') !== $requestId || !hash_equals($entry['sha256'] ?? '', $sha256)) {
            throw new RuntimeException('Backup acknowledgement does not match the latest download.');
        }
        $state['pc_saved_at'] = time();
        $state['pc_saved_name'] = $entry['name'];
        $state['pc_saved_sha256'] = $sha256;
        pc_backup_write($state);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Build a portable bundle containing the SQL snapshot and all private uploads. */
function pc_backup_bundle(array $databaseEntry): array
{
    $backupDirectory = backup_directory();
    $databaseName = $databaseEntry['name'] ?? '';
    if (!preg_match('/\Asantafe_\d{8}_\d{6}_[a-f0-9]{8}\.sql\z/', $databaseName)) {
        throw new RuntimeException('Invalid database backup entry.');
    }
    $databasePath = $backupDirectory . '/' . $databaseName;
    if (!is_file($databasePath) || is_link($databasePath)) throw new RuntimeException('Database backup is unavailable.');

    $bundleName = substr($databaseName, 0, -4) . '_complete.tar';
    $bundlePath = $backupDirectory . '/' . $bundleName;
    $temporary = $bundlePath . '.partial.tar';
    if (is_file($temporary)) unlink($temporary);

    try {
        $archive = new PharData($temporary);
        $archive->addFile($databasePath, 'database.sql');
        $privateRoot = private_storage_root();
        foreach (['discount_ids', 'receipts', 'avatars'] as $category) {
            $directory = $privateRoot . DIRECTORY_SEPARATOR . $category;
            if (!is_dir($directory)) continue;
            foreach (new DirectoryIterator($directory) as $file) {
                if (!$file->isFile() || $file->isLink()) continue;
                $resolved = $file->getRealPath();
                if (!$resolved || !private_path_within($resolved, $directory)) continue;
                $archive->addFile($resolved, 'private/' . $category . '/' . $file->getFilename());
            }
        }
        $archive->addFromString('RESTORE.json', json_encode([
            'created_at' => gmdate(DATE_ATOM),
            'database_file' => 'database.sql',
            'private_directories' => ['discount_ids', 'receipts', 'avatars'],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        unset($archive);
        if (!is_file($temporary) || filesize($temporary) === 0 || !rename($temporary, $bundlePath)) {
            throw new RuntimeException('Cannot finalize the complete backup bundle.');
        }
        return ['name' => $bundleName, 'bytes' => filesize($bundlePath), 'sha256' => hash_file('sha256', $bundlePath)];
    } finally {
        if (is_file($temporary)) unlink($temporary);
    }
}

/** Retries with the same request ID reuse the completed export after network failure. */
function pc_backup_prepare(string $token, string $requestId, ?callable $export = null): array
{
    if (!preg_match('/\A[a-f0-9]{32}\z/', $requestId)) throw new InvalidArgumentException('Invalid backup request ID.');
    $lock = pc_backup_lock();
    try {
        $state = pc_backup_read();
        if (!pc_backup_authorized($token, $state)) throw new RuntimeException('PC backup access denied.');
        $entry = $state['last_backup'] ?? [];
        if (($state['request_id'] ?? '') !== $requestId || empty($entry)) {
            if (time() - ($state['last_created'] ?? 0) < 300) throw new RuntimeException('A backup was created recently. Retry in five minutes.');
            $result = $export ? $export() : backup_run(false, 'pc_download');
            if (($result['status'] ?? '') !== 'created') throw new RuntimeException('Database export is busy. Retry shortly.');
            $entry = pc_backup_bundle($result['backup']);
            $state['last_backup'] = $entry;
            $state['request_id'] = $requestId;
            $state['last_created'] = time();
            pc_backup_write($state);
            foreach (glob(backup_directory() . '/santafe_*_complete.tar') ?: [] as $oldBundle) {
                if ($oldBundle !== backup_directory() . '/' . $entry['name'] && filemtime($oldBundle) < time() - 30 * 86400) {
                    unlink($oldBundle);
                }
            }
        }
        if (!preg_match('/\Asantafe_\d{8}_\d{6}_[a-f0-9]{8}_complete\.tar\z/', $entry['name'] ?? '')) throw new RuntimeException('Invalid backup file.');
        $path = backup_directory() . '/' . $entry['name'];
        if (!is_file($path) || is_link($path) || !hash_equals($entry['sha256'], hash_file('sha256', $path))) throw new RuntimeException('Backup verification failed.');
        return $entry;
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}

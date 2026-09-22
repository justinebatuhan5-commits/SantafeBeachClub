<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../backend/helpers/pc_backup_helper.php';
function pc_check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); echo 'PASS: ' . $message . PHP_EOL; }
$id = bin2hex(random_bytes(6));
$root = sys_get_temp_dir() . '/sf_pc_test_' . $id;
mkdir($root, 0700);
putenv('PRIVATE_STORAGE_DIR=' . $root . '/private');
putenv('PRIVATE_STORAGE_DOCUMENT_ROOT=' . dirname(__DIR__));
try {
    try { pc_backup_pair('http://example.test'); throw new LogicException('HTTP accepted'); }
    catch (RuntimeException $e) { pc_check(str_contains($e->getMessage(), 'HTTPS'), 'Insecure website URLs are rejected'); }
    $pair = pc_backup_pair('https://example.test/hotel/');
    pc_check($pair['endpoint'] === 'https://example.test/hotel/backend/api/pc_backup.php', 'Website subfolders are preserved');
    pc_check(!str_contains(file_get_contents(backup_directory() . '/pc-client.json'), $pair['token']), 'The server stores only a hash of the PC key');
    pc_check(pc_backup_authorized($pair['token'], pc_backup_read()) && !pc_backup_authorized(str_repeat('0', 64), pc_backup_read()), 'Only the paired key is accepted');
    mkdir($root . '/private/discount_ids', 0700, true);
    file_put_contents($root . '/private/discount_ids/test-id.png', 'private image fixture');
    $exports = 0;
    $export = static function () use (&$exports) {
        $exports++;
        $name = 'santafe_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.sql';
        $path = backup_directory() . '/' . $name;
        file_put_contents($path, '-- test SQL snapshot');
        return ['status' => 'created', 'backup' => ['name' => $name, 'sha256' => hash_file('sha256', $path), 'bytes' => filesize($path)]];
    };
    $request = bin2hex(random_bytes(16));
    $entry = pc_backup_prepare($pair['token'], $request, $export);
    $bundle = new PharData(backup_directory() . '/' . $entry['name']);
    pc_check(isset($bundle['database.sql']) && isset($bundle['private/discount_ids/test-id.png']) && isset($bundle['RESTORE.json']), 'Complete bundle contains the database, private uploads and restore manifest');
    unset($bundle);
    pc_backup_acknowledge($pair['token'], $request, $entry['sha256']);
    $acknowledged = pc_backup_read();
    pc_check(!empty($acknowledged['pc_saved_at']) && $acknowledged['pc_saved_name'] === $entry['name'], 'Verified PC download acknowledgement is recorded');
    pc_check($entry === pc_backup_prepare($pair['token'], $request, $export) && $exports === 1, 'Retrying a download reuses its complete backup bundle');
    try { pc_backup_prepare($pair['token'], bin2hex(random_bytes(16)), $export); throw new LogicException('No rate limit'); }
    catch (RuntimeException $e) { pc_check(str_contains($e->getMessage(), 'five minutes'), 'Rapid fresh exports are limited'); }
    file_put_contents(backup_directory() . '/' . $entry['name'], 'changed');
    try { pc_backup_prepare($pair['token'], $request, $export); throw new LogicException('Tamper accepted'); }
    catch (RuntimeException $e) { pc_check(str_contains($e->getMessage(), 'verification'), 'Modified backup bundles are rejected'); }
    $newPair = pc_backup_pair('https://example.test/hotel');
    pc_check(!pc_backup_authorized($pair['token'], pc_backup_read()) && pc_backup_authorized($newPair['token'], pc_backup_read()), 'Re-pairing invalidates the old access key');
    pc_backup_revoke();
    pc_check(!pc_backup_authorized($newPair['token'], pc_backup_read()), 'Revocation stops PC access');
    pc_check(is_file(backup_directory() . '/' . $entry['name']), 'Revoking access preserves existing backups');
} finally {
    $resolved = realpath($root);
    if ($resolved && private_path_within($resolved, realpath(sys_get_temp_dir())) && basename($resolved) === 'sf_pc_test_' . $id) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($resolved);
    }
}

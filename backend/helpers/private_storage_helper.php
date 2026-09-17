<?php
require_once __DIR__ . '/../config/env_loader.php';
load_env(__DIR__ . '/../../.env');

function private_path_within(string $path, string $base): bool
{
    $path = rtrim(str_replace('\\', '/', $path), '/');
    $base = rtrim(str_replace('\\', '/', $base), '/');
    if (PHP_OS_FAMILY === 'Windows') {
        $path = strtolower($path);
        $base = strtolower($base);
    }
    return $path === $base || str_starts_with($path, $base . '/');
}

/** Resolve storage outside both the application and the server document root. */
function private_storage_root(bool $create = false): string
{
    $project = realpath(__DIR__ . '/../..');
    $documentRoot = realpath(getenv('PRIVATE_STORAGE_DOCUMENT_ROOT') ?: (($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname($project)));
    if ($documentRoot === false) {
        throw new RuntimeException('Cannot resolve the document root.');
    }
    $configured = getenv('PRIVATE_STORAGE_DIR') ?: dirname($documentRoot) . '/SantaBeachClub-private';
    if (!preg_match('~^(?:[A-Za-z]:[/\\\\]|/)~', $configured) || preg_match('~(?:^|[/\\\\])\.\.(?:[/\\\\]|$)~', $configured)) {
        throw new RuntimeException('Private storage requires an absolute path without parent segments.');
    }
    // Resolve the existing ancestor before creating anything, including symlinks.
    $ancestor = $configured;
    while (!file_exists($ancestor) && dirname($ancestor) !== $ancestor) {
        $ancestor = dirname($ancestor);
    }
    $resolvedAncestor = realpath($ancestor);
    if ($resolvedAncestor === false || private_path_within($resolvedAncestor, $documentRoot) || private_path_within($resolvedAncestor, $project)) {
        throw new RuntimeException('Private storage must be outside the web root.');
    }
    if (!is_dir($configured) && $create && !mkdir($configured, 0700, true) && !is_dir($configured)) {
        throw new RuntimeException('Cannot create private storage.');
    }
    $root = realpath($configured);
    if ($root === false || !is_dir($root) || private_path_within($root, $documentRoot) || private_path_within($root, $project)) {
        throw new RuntimeException('Private storage is unavailable or inside the web root.');
    }
    return $root;
}

function private_storage_directory(string $kind, bool $create = false): string
{
    if (!in_array($kind, ['receipts', 'avatars'], true)) {
        throw new RuntimeException('Invalid storage category.');
    }
    $root = private_storage_root($create);
    $directory = $root . DIRECTORY_SEPARATOR . $kind;
    if (!is_dir($directory) && $create && !mkdir($directory, 0700) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create storage category.');
    }
    $resolved = realpath($directory);
    if ($resolved === false || !is_dir($resolved) || !private_path_within($resolved, $root)) {
        throw new RuntimeException('Invalid storage category path.');
    }
    return $resolved;
}

function private_storage_file(string $key): ?string
{
    if (!preg_match('~\A(receipts|avatars)/([a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp|gif|pdf))\z~', $key, $parts)) {
        return null;
    }
    $directory = private_storage_directory($parts[1]);
    $path = realpath($directory . DIRECTORY_SEPARATOR . $parts[2]);
    return $path !== false && is_file($path) && private_path_within($path, $directory) ? $path : null;
}

function store_private_receipt(string $temporaryFile, string $filename): bool
{
    if (!preg_match('~\A[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|pdf)\z~', $filename)) return false;
    try {
        $destination = private_storage_directory('receipts', true) . DIRECTORY_SEPARATOR . $filename;
        return !file_exists($destination) && move_uploaded_file($temporaryFile, $destination);
    } catch (RuntimeException $e) {
        error_log('Private receipt storage is unavailable.');
        return false;
    }
}

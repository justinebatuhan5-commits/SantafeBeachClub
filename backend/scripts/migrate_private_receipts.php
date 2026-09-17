<?php
/** CLI only. Preview: php backend/scripts/migrate_private_receipts.php
 * Apply: php backend/scripts/migrate_private_receipts.php --apply
 * Copies, verifies SHA-256, then removes the old public copy. Database keys stay valid.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../helpers/private_storage_helper.php';
$apply = in_array('--apply', $argv, true);
$project = realpath(__DIR__ . '/../..');
$sources = [$project . '/storage/receipts', $project . '/frontend/uploads/receipts'];
$destination = $apply ? private_storage_directory('receipts', true) : null;
$count = 0;
foreach ($sources as $source) {
    $source = realpath($source);
    if ($source === false) continue;
    if (!private_path_within($source, $project)) throw new RuntimeException('Unsafe migration source.');
    foreach (new DirectoryIterator($source) as $entry) {
        if ($entry->isDot() || $entry->getFilename() === '.htaccess') continue;
        $name = $entry->getFilename();
        $from = $entry->getRealPath();
        if ($entry->isLink() || !$entry->isFile() || !private_path_within($from, $source)
            || !preg_match('~\A[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|pdf)\z~', $name)) {
            throw new RuntimeException('Unexpected file in receipt storage; review before migrating.');
        }
        if ($apply) {
            $to = $destination . DIRECTORY_SEPARATOR . $name;
            if (is_link($to)) throw new RuntimeException('Unsafe migration destination.');
            if (!file_exists($to)) {
                // Exclusive creation: never overwrite an existing receipt.
                $output = fopen($to, 'x+b');
                if ($output === false) throw new RuntimeException('Cannot create receipt copy.');
                $input = fopen($from, 'rb');
                if ($input === false) { fclose($output); throw new RuntimeException('Cannot read receipt.'); }
                stream_copy_to_stream($input, $output);
                fclose($input);
                fclose($output);
            }
            $resolved = realpath($to);
            if ($resolved === false || !private_path_within($resolved, $destination)
                || hash_file('sha256', $from) !== hash_file('sha256', $resolved)) {
                throw new RuntimeException('Receipt verification failed; original preserved.');
            }
            if (!unlink($from)) throw new RuntimeException('Copy verified but original could not be removed.');
        }
        $count++;
    }
}
echo $apply ? "Moved $count receipts to $destination\n" : "Ready to move $count receipts. Run with --apply to create private storage and migrate.\n";

<?php
require_once __DIR__ . '/../backend/helpers/private_storage_helper.php';
function check_storage(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$fixture = sys_get_temp_dir() . '/sbc-storage-test-' . bin2hex(random_bytes(8));
mkdir($fixture, 0700);
mkdir($fixture . '/www', 0700);
$oldRoot = getenv('PRIVATE_STORAGE_DIR');
$oldDocument = getenv('PRIVATE_STORAGE_DOCUMENT_ROOT');
try {
    putenv('PRIVATE_STORAGE_DOCUMENT_ROOT=' . $fixture . '/www');
    putenv('PRIVATE_STORAGE_DIR=' . $fixture . '/www/uploads');
    $rejected = false;
    try { private_storage_root(true); } catch (RuntimeException $e) { $rejected = true; }
    check_storage($rejected && !is_dir($fixture . '/www/uploads'), 'Web-root storage must fail before creation.');
    putenv('PRIVATE_STORAGE_DIR=' . $fixture . '/private');
    $directory = private_storage_directory('receipts', true);
    file_put_contents($directory . '/rcpt_test.jpg', 'receipt fixture');
    check_storage(private_storage_file('receipts/rcpt_test.jpg') === realpath($directory . '/rcpt_test.jpg'), 'Receipt resolution failed.');
    foreach (['../config.php', 'receipts/../../config.php', 'receipts\\rcpt_test.jpg', '/receipts/rcpt_test.jpg', 'receipts/test.php', 'receipts/test.jpg/other', "receipts/test.jpg\0", 'receipts/missing.jpg'] as $key) {
        check_storage(private_storage_file($key) === null, 'Unsafe or missing key was accepted.');
    }
    check_storage(!private_path_within($fixture . '/private-other/file', $fixture . '/private'), 'Sibling prefix must not count as containment.');
    check_storage(!store_private_receipt($directory . '/rcpt_test.jpg', 'other.jpg'), 'Non-HTTP source must not be stored as an upload.');
    check_storage(is_file($directory . '/rcpt_test.jpg'), 'Rejected upload changed source.');
    echo "Private storage checks passed: external location, path boundaries, traversal, missing files, upload provenance.\n";
} finally {
    putenv($oldRoot === false ? 'PRIVATE_STORAGE_DIR' : 'PRIVATE_STORAGE_DIR=' . $oldRoot);
    putenv($oldDocument === false ? 'PRIVATE_STORAGE_DOCUMENT_ROOT' : 'PRIVATE_STORAGE_DOCUMENT_ROOT=' . $oldDocument);
    if (is_file($fixture . '/private/receipts/rcpt_test.jpg')) unlink($fixture . '/private/receipts/rcpt_test.jpg');
    if (is_dir($fixture . '/private/receipts')) rmdir($fixture . '/private/receipts');
    if (is_dir($fixture . '/private')) rmdir($fixture . '/private');
    rmdir($fixture . '/www');
    rmdir($fixture);
}

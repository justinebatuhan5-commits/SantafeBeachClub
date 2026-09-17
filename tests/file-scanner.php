<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../backend/helpers/file_scanner.php';
function scanner_check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
$tmp = tempnam(sys_get_temp_dir(), 'sbc_scan_');
try {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
    file_put_contents($tmp, $png);
    scanner_check(strpos($png, "\0") !== false && scan_uploaded_file($tmp, 'png')['safe'], 'PNG containing normal null bytes is accepted');
    scanner_check(!scan_uploaded_file($tmp, 'jpg')['safe'], 'Image extension mismatch is rejected');
    $jpeg = file_get_contents(__DIR__ . '/../frontend/assets/logo.jpg');
    file_put_contents($tmp, $jpeg);
    scanner_check(strpos($jpeg, "\0") !== false && scan_uploaded_file($tmp, 'jpg')['safe'], 'JPEG containing normal null bytes is accepted');
    scanner_check(scan_uploaded_file($tmp, 'jpeg')['safe'], 'JPEG extension alias is accepted');
    foreach (["MZ\0fake executable", "PK\x03\x04\0fake archive", 'not an image'] as $invalid) {
        file_put_contents($tmp, $invalid);
        scanner_check(!scan_uploaded_file($tmp, 'png')['safe'], 'Non-image content is rejected');
    }
    file_put_contents($tmp, $png . '<?php echo "unsafe";');
    scanner_check(!scan_uploaded_file($tmp, 'png')['safe'], 'Image with PHP payload is rejected');
    file_put_contents($tmp, "%PDF-1.4\n%\0binary\n%%EOF");
    scanner_check(scan_uploaded_file($tmp, 'pdf')['safe'], 'PDF binary content remains accepted by signature scanner');
    file_put_contents($tmp, '%PDF-1.4 /JavaScript (alert)');
    scanner_check(!scan_uploaded_file($tmp, 'pdf')['safe'], 'PDF JavaScript remains rejected');
} finally { unlink($tmp); }

<?php
/**
 * file_scanner.php — Basic malware / dangerous-content detector
 *
 * Reads the actual file bytes and checks for known dangerous signatures.
 * This is a defence-in-depth layer on top of MIME and extension validation.
 * It is NOT a replacement for a full antivirus engine (e.g. ClamAV), but
 * it catches the most common web-shell / script-injection attack vectors.
 */

/**
 * Scan an uploaded file for potentially malicious content.
 *
 * @param  string $tmpPath  Path to the temporary uploaded file (from $_FILES[…]['tmp_name'])
 * @param  string $fileExt  Lowercased file extension, e.g. 'jpg', 'pdf'
 * @return array{safe: bool, reason: string|null}
 */
function scan_uploaded_file(string $tmpPath, string $fileExt): array
{
    if (!file_exists($tmpPath) || !is_readable($tmpPath)) {
        return ['safe' => false, 'reason' => 'Temporary file not readable'];
    }

    // Read first 8 KB for header / signature checks (covers most injection points)
    $handle = fopen($tmpPath, 'rb');
    $header = fread($handle, 8192);
    fclose($handle);

    // Binary image/PDF contents legitimately contain null bytes. Validate the
    // image format instead; null-byte path checks must not inspect file contents.
    $imageTypes = ['jpg' => IMAGETYPE_JPEG, 'jpeg' => IMAGETYPE_JPEG,
        'png' => IMAGETYPE_PNG, 'gif' => IMAGETYPE_GIF, 'webp' => IMAGETYPE_WEBP];
    if (isset($imageTypes[$fileExt])) {
        $image = @getimagesize($tmpPath);
        if ($image === false || $image[2] !== $imageTypes[$fileExt]) {
            return ['safe' => false, 'reason' => 'Invalid image or image format does not match its extension'];
        }
    }

    // ── 2. PHP / server-side script injection ───────────────────────────────
    // Catches web shells and code injections hidden inside renamed files.
    $dangerousPatterns = [
        '<?php', '<?=', '<%@', '<% ', '<asp:', '<jsp:',
        'eval(',      // dynamic code execution
        'base64_decode(',  // obfuscated payloads
        'system(',    // OS command execution
        'passthru(',
        'exec(',
        'shell_exec(',
        'popen(',
        'proc_open(',
        'assert(',
    ];
    foreach ($dangerousPatterns as $pattern) {
        if (stripos($header, $pattern) !== false) {
            return ['safe' => false, 'reason' => "Dangerous code pattern detected: \"$pattern\""];
        }
    }

    // ── 3. Executable binary signatures ────────────────────────────────────
    // Checks the real magic bytes — catches executables renamed to images.
    $execSignatures = [
        "\x4D\x5A"             => 'Windows PE/EXE (MZ header)',
        "\x7F\x45\x4C\x46"    => 'Linux ELF binary',
        "\xCE\xFA\xED\xFE"    => 'macOS Mach-O binary (32-bit)',
        "\xCF\xFA\xED\xFE"    => 'macOS Mach-O binary (64-bit)',
        "\xCA\xFE\xBA\xBE"    => 'Java class file',
    ];
    foreach ($execSignatures as $sig => $label) {
        if (strncmp($header, $sig, strlen($sig)) === 0) {
            return ['safe' => false, 'reason' => "Executable file signature detected: $label"];
        }
    }

    // ── 4. ZIP / archive disguised as an image ──────────────────────────────
    // Archives can bundle malicious payloads; we never expect ZIP uploads.
    if (strncmp($header, "\x50\x4B\x03\x04", 4) === 0) {
        return ['safe' => false, 'reason' => 'Archive/ZIP file detected (disguised as image)'];
    }

    // ── 5. PDF-specific: embedded JavaScript ────────────────────────────────
    // PDFs with embedded /JavaScript can execute code in some PDF viewers.
    if ($fileExt === 'pdf') {
        $fullContent = file_get_contents($tmpPath);
        if (stripos($fullContent, '/JavaScript') !== false ||
            stripos($fullContent, '/JS ')        !== false) {
            return ['safe' => false, 'reason' => 'PDF contains embedded JavaScript'];
        }
    }

    return ['safe' => true, 'reason' => null];
}

<?php
/**
 * Admin PC backups page controller.
 *
 * Handles backup pairing, script downloads, SQL downloads, access revocation,
 * status polling, and storage diagnostics.
 */

/**
 * Escapes HTML values for backup views.
 */
function sfbc_pc_html($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Processes POST backup actions and streams file downloads or redirects.
 */
function sfbc_backups_handle_post(): string
{
    require_csrf_token();
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'pair') {
            $pair = pc_backup_pair(is_string($_POST['website'] ?? null) ? $_POST['website'] : '');
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="santabeachclub-pc.json"');
            echo json_encode($pair, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($action === 'revoke') {
            pc_backup_revoke();
            header('Location: admin_backups', true, 303);
            exit;
        }
        if ($action === 'installer' || $action === 'downloader') {
            $name = $action === 'installer' ? 'install_pc_backup.ps1' : 'download_pc_backup.ps1';
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            readfile(dirname(__DIR__) . '/scripts/' . $name);
            exit;
        }
        if ($action === 'download') {
            foreach (backup_entries(backup_directory()) as $entry) {
                if ($entry['name'] !== ($_POST['name'] ?? '')) {
                    continue;
                }
                $path = backup_directory() . '/' . $entry['name'];
                if (!hash_equals($entry['sha256'], hash_file('sha256', $path))) {
                    throw new RuntimeException('Backup verification failed.');
                }
                header('Content-Type: application/sql');
                header('Content-Disposition: attachment; filename="' . $entry['name'] . '"');
                header('Content-Length: ' . filesize($path));
                readfile($path);
                exit;
            }
            http_response_code(404);
            exit('Backup not found.');
        }
        http_response_code(400);
        exit('Unknown action.');
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

/**
 * Builds administrator storage diagnostic entries when an error occurs.
 */
function sfbc_backups_diagnostics(): array
{
    $projectRoot        = dirname(__DIR__, 2);
    $diagnosticProject  = realpath($projectRoot);
    $diagnosticEnv      = $projectRoot . '/.env';
    $diagnosticStorage  = getenv('PRIVATE_STORAGE_DIR') ?: '';
    $diagnosticDocument = getenv('PRIVATE_STORAGE_DOCUMENT_ROOT') ?: ($_SERVER['DOCUMENT_ROOT'] ?? '');

    return [
        'Website project folder'          => $diagnosticProject ?: 'Cannot resolve',
        '.env file'                       => @is_file($diagnosticEnv) ? (@is_readable($diagnosticEnv) ? 'Found and readable' : 'Found but not readable') : 'Not found as a file beside backend and frontend',
        'Configured private folder'       => $diagnosticStorage ?: 'Not loaded; using default',
        'Configured document root'        => $diagnosticDocument ?: 'Not available',
        'Resolved private folder'         => $diagnosticStorage ? (@realpath($diagnosticStorage) ?: 'Cannot resolve: missing folder, permissions or hosting restriction') : 'No configured path',
        'Private folder writable by PHP'  => $diagnosticStorage && @is_writable($diagnosticStorage) ? 'Yes' : 'No / cannot access',
        'PHP allowed paths (open_basedir)' => ini_get('open_basedir') ?: 'No restriction reported',
    ];
}

/**
 * Handles backup requests and returns view data for admin_backups.
 */
function sfbc_handle_admin_backups_page(): array
{
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $error = sfbc_backups_handle_post();
    }

    $state = [];
    $entries = [];
    try {
        $state = pc_backup_read();
        $entries = backup_entries(backup_directory());
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    $countdown = [
        'enabled'         => !empty($state['token_hash']),
        'lastCreated'     => (int)($state['last_created'] ?? 0),
        // Estimate only: Windows owns the trigger and may have a different local schedule.
        'intervalSeconds' => 6 * 60 * 60,
        'serverNow'       => time(),
        'available'       => $error === '',
        'pcSavedAt'       => (int)($state['pc_saved_at'] ?? 0),
        'pcSavedName'     => (string)($state['pc_saved_name'] ?? ''),
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['backup_status'])) {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode($countdown);
        exit;
    }

    return [
        'error'       => $error,
        'state'       => $state,
        'entries'     => $entries,
        'countdown'   => $countdown,
        'diagnostics' => $error ? sfbc_backups_diagnostics() : [],
        'csrfField'   => csrf_field(),
    ];
}

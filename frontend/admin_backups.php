<?php
require_once __DIR__ . '/../backend/helpers/admin_auth_check.php';
require_once __DIR__ . '/../backend/helpers/pc_backup_helper.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'pair') {
            $pair = pc_backup_pair(is_string($_POST['website'] ?? null) ? $_POST['website'] : '');
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="santabeachclub-pc.json"');
            echo json_encode($pair, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); exit;
        }
        if ($action === 'revoke') { pc_backup_revoke(); header('Location: admin_backups', true, 303); exit; }
        if ($action === 'installer' || $action === 'downloader') {
            $name = $action === 'installer' ? 'install_pc_backup.ps1' : 'download_pc_backup.ps1';
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            readfile(__DIR__ . '/../backend/scripts/' . $name); exit;
        }
        if ($action === 'download') {
            foreach (backup_entries(backup_directory()) as $entry) {
                if ($entry['name'] !== ($_POST['name'] ?? '')) continue;
                $path = backup_directory() . '/' . $entry['name'];
                if (!hash_equals($entry['sha256'], hash_file('sha256', $path))) throw new RuntimeException('Backup verification failed.');
                header('Content-Type: application/sql');
                header('Content-Disposition: attachment; filename="' . $entry['name'] . '"');
                header('Content-Length: ' . filesize($path)); readfile($path); exit;
            }
            http_response_code(404); exit('Backup not found.');
        }
        http_response_code(400); exit('Unknown action.');
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
$state = []; $entries = [];
try { $state = pc_backup_read(); $entries = backup_entries(backup_directory()); }
catch (Throwable $e) { $error = $e->getMessage(); }
function pc_html($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PC Database Backups — Santa Fe Beach Club</title><link rel="stylesheet" href="assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/assets/css/admin.css'); ?>">
<style>.backup-card{background:var(--card-bg,#fff);border:1px solid var(--border,#ddd);border-radius:14px;padding:24px;margin-bottom:20px}.backup-card p,.backup-card li{line-height:1.8}.backup-actions{display:flex;gap:12px;flex-wrap:wrap;margin:18px 0}.backup-card input{padding:10px;max-width:100%;width:420px}.backup-table{width:100%;border-collapse:collapse}.backup-table td,.backup-table th{padding:12px;text-align:left;border-bottom:1px solid var(--border,#ddd)}.backup-scroll{overflow:auto}</style>
</head><body>
<?php $active_page = 'backups'; include __DIR__ . '/partials/_sidebar.php'; ?>
<main class="admin-main"><?php $page_title = 'PC Database Backups'; $page_subtitle = 'Download your hosted database to your computer every six hours.'; include __DIR__ . '/partials/_page_header.php'; ?>
<div class="admin-body">
<section class="backup-card"><h2>Automatic backups to your PC</h2>
<p>Your Windows task requests a fresh database backup from this website every six hours and saves a verified SQL file in your Documents folder. Your PC must be on, awake, connected to the internet, and signed in. Missed runs are checked when you sign in again.</p>
<p><?= !empty($state['token_hash']) ? 'A PC access key is enabled. Complete the Windows installation to start downloads.' : 'No PC access key is enabled yet.' ?></p>
<?php if (!empty($state['last_created'])): ?><p>Last backup requested by the PC: <?= pc_html(date('M d, Y H:i:s', $state['last_created'])) ?>. Check the PC download log to confirm it was saved.</p><?php endif; ?>
<?php if ($error): ?><p role="alert"><?= pc_html($error) ?></p>
<details open><summary>Storage diagnostics (administrator only)</summary>
<p>These settings help identify a missing configuration file, incorrect path or hosting restriction. No passwords or access keys are shown.</p>
<dl>
<?php
$diagnosticProject = realpath(__DIR__ . '/..');
$diagnosticEnv = __DIR__ . '/../.env';
$diagnosticStorage = getenv('PRIVATE_STORAGE_DIR') ?: '';
$diagnosticDocument = getenv('PRIVATE_STORAGE_DOCUMENT_ROOT') ?: ($_SERVER['DOCUMENT_ROOT'] ?? '');
$diagnostics = [
    'Website project folder' => $diagnosticProject ?: 'Cannot resolve',
    '.env file' => @is_file($diagnosticEnv) ? (@is_readable($diagnosticEnv) ? 'Found and readable' : 'Found but not readable') : 'Not found as a file beside backend and frontend',
    'Configured private folder' => $diagnosticStorage ?: 'Not loaded; using default',
    'Configured document root' => $diagnosticDocument ?: 'Not available',
    'Resolved private folder' => $diagnosticStorage ? (@realpath($diagnosticStorage) ?: 'Cannot resolve: missing folder, permissions or hosting restriction') : 'No configured path',
    'Private folder writable by PHP' => $diagnosticStorage && @is_writable($diagnosticStorage) ? 'Yes' : 'No / cannot access',
    'PHP allowed paths (open_basedir)' => ini_get('open_basedir') ?: 'No restriction reported',
];
foreach ($diagnostics as $label => $value): ?>
<dt><strong><?= pc_html($label) ?></strong></dt><dd style="overflow-wrap:anywhere"><?= pc_html($value) ?></dd>
<?php endforeach; ?>
</dl></details>
<?php endif; ?>
<ol><li>Enter your live Agila website address and download the connection file. Generating a new file replaces the previous access key.</li>
<li>Download both Windows scripts below into the same folder as the connection file.</li>
<li>Right-click the installer and choose <strong>Run with PowerShell</strong>. It creates the six-hour task and attempts the first download.</li></ol>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="pair"><label for="website">Agila website address</label><br><input type="url" id="website" name="website" placeholder="https://your-website.com" pattern="https://.*" required><button class="btn btn-primary" type="submit">Download connection file</button></form>
<div class="backup-actions">
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="installer"><button class="btn btn-secondary">Download installer</button></form>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="downloader"><button class="btn btn-secondary">Download downloader</button></form>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="revoke"><button class="btn btn-secondary">Revoke PC access</button></form>
</div><p>The connection file is a private access key. After successful installation, remove that downloaded file; Windows stores the key encrypted for your account. SQL backups include database records, not uploaded images or website source files.</p>
</section><section class="backup-card"><h2>Existing server copies</h2><p>Existing backups are preserved. Server copies are retained for 30 days after a successful new export. PC downloads remain until you remove them.</p>
<div class="backup-scroll"><table class="backup-table"><thead><tr><th>Created</th><th>Size</th><th>Download</th></tr></thead><tbody>
<?php foreach ($entries as $entry): ?><tr><td><?= pc_html(date('M d, Y H:i:s', $entry['created_at'])) ?></td><td><?= pc_html(round($entry['bytes'] / 1024, 1)) ?> KB</td><td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="download"><input type="hidden" name="name" value="<?= pc_html($entry['name']) ?>"><button class="btn btn-secondary">Download SQL</button></form></td></tr><?php endforeach; ?>
<?php if (!$entries): ?><tr><td colspan="3">No completed backups yet.</td></tr><?php endif; ?>
</tbody></table></div></section></div></main><script src="assets/js/sidebar-toggle.js"></script></body></html>

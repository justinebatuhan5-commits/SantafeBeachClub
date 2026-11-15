<?php
$error       = $backupData['error'];
$state       = $backupData['state'];
$entries     = $backupData['entries'];
$countdown   = $backupData['countdown'];
$diagnostics = $backupData['diagnostics'];
$csrfField   = $backupData['csrfField'];
?>
<div class="admin-body backup-page">
    <section class="backup-hero">
        <div>
            <span class="backup-eyebrow">COMPLETE BACKUPS</span>
            <h2>A little peace of mind.<br>A copy on your PC.</h2>
            <p>Keep your database and private guest uploads together with scheduled downloads to your Windows computer.</p>
            <a class="backup-hero-link" href="#backup-setup">Set up your PC <span aria-hidden="true">&#8599;</span></a>
        </div>
        <div class="backup-illustration" aria-hidden="true">
            <svg viewBox="0 0 280 180" fill="none"><circle cx="140" cy="90" r="79" stroke="currentColor" stroke-opacity=".15"/><circle cx="140" cy="90" r="58" stroke="currentColor" stroke-opacity=".12"/><rect x="96" y="72" width="133" height="83" rx="10" fill="#f5ece5"/><rect x="104" y="80" width="117" height="65" rx="5" fill="#563c31"/><path d="M153 156v10m-23 0h46" stroke="#f5ece5" stroke-width="6" stroke-linecap="round"/><ellipse cx="77" cy="44" rx="35" ry="13" fill="#e5bd93"/><path d="M42 44v45c0 17 70 17 70 0V44" fill="#d3a475"/><ellipse cx="77" cy="44" rx="35" ry="13" fill="#f3d6b4"/><path d="M42 65c0 17 70 17 70 0m-70 21c0 17 70 17 70 0" stroke="#8b603f" stroke-width="2"/><path d="M143 44h26a13 13 0 0 1 13 13v47m-10-10 10 10 10-10" stroke="#e5bd93" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><path d="m150 115 9 9 18-19" stroke="#f3d6b4" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span>YOUR DATABASE. SAFELY COPIED.</span>
        </div>
    </section>

    <div class="backup-stats">
        <div class="backup-stat">
            <span class="backup-stat-label">PC access key</span>
            <strong><i class="backup-dot <?= !empty($state['token_hash']) ? 'is-enabled' : '' ?>"></i><?= !empty($state['token_hash']) ? 'Enabled' : 'Not connected' ?></strong>
            <span><?= !empty($state['token_hash']) ? 'Complete setup on your Windows PC' : 'Start with the connection file below' ?></span>
        </div>
        <div class="backup-stat">
            <span class="backup-stat-label">Download schedule</span>
            <strong>Every 6 hours</strong>
            <span>While your PC is awake and signed in</span>
        </div>
        <div class="backup-stat">
            <span class="backup-stat-label">Last PC download</span>
            <strong id="backup-pc-saved-time"><?= !empty($state['pc_saved_at']) ? sfbc_pc_html(date('M d, Y · H:i', $state['pc_saved_at'])) : 'Not confirmed yet' ?></strong>
            <span id="backup-pc-saved-name"><?= !empty($state['pc_saved_name']) ? sfbc_pc_html($state['pc_saved_name']) : 'Waiting for the PC to save and verify a bundle' ?></span>
            <button type="button" class="btn btn-secondary" id="backup-check-status">Check download status</button>
        </div>
    </div>

    <section class="backup-countdown" aria-label="Estimated next automatic backup">
        <div>
            <span class="backup-eyebrow">NEXT AUTOMATIC BACKUP · ESTIMATED</span>
            <h2 id="backup-countdown-status">Checking schedule…</h2>
            <p id="backup-countdown-note">Based on the last PC request and the six-hour interval. Windows controls the actual run time.</p>
        </div>
        <div class="backup-clock" role="timer" aria-label="Estimated time remaining" aria-live="off">
            <div><strong id="backup-hours">--</strong><span>HOURS</span></div>
            <b aria-hidden="true">:</b>
            <div><strong id="backup-minutes">--</strong><span>MINUTES</span></div>
            <b aria-hidden="true">:</b>
            <div><strong id="backup-seconds">--</strong><span>SECONDS</span></div>
        </div>
    </section>

    <script type="application/json" id="backup-countdown-data"><?= json_encode($countdown, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script src="assets/js/backup-countdown.js?v=<?= filemtime(__DIR__ . '/../../assets/js/backup-countdown.js') ?>" defer></script>

    <section class="backup-card" id="backup-setup">
        <div class="backup-section-heading">
            <div>
                <span class="backup-eyebrow">GET CONNECTED</span>
                <h2>Three steps to automatic backups</h2>
                <p>A one-time setup for your Windows computer.</p>
            </div>
            <span class="backup-tag">Windows · SQL</span>
        </div>

        <?php if ($error): ?>
            <p role="alert"><?= sfbc_pc_html($error) ?></p>
            <details open>
                <summary>Storage diagnostics (administrator only)</summary>
                <p>These settings help identify a missing configuration file, incorrect path or hosting restriction. No passwords or access keys are shown.</p>
                <dl>
                    <?php foreach ($diagnostics as $label => $value): ?>
                        <dt><strong><?= sfbc_pc_html($label) ?></strong></dt>
                        <dd style="overflow-wrap:anywhere"><?= sfbc_pc_html($value) ?></dd>
                    <?php endforeach; ?>
                </dl>
            </details>
        <?php endif; ?>

        <div class="backup-steps">
            <article class="backup-step">
                <span class="backup-step-number">01</span>
                <h3>Connect your website</h3>
                <p>Enter your live Agila address to create a private connection file.</p>
                <form method="post" class="backup-connect-form">
                    <?= $csrfField ?>
                    <input type="hidden" name="action" value="pair">
                    <label for="website">Agila website address</label>
                    <input type="url" id="website" name="website" placeholder="https://your-website.com" pattern="https://.*" aria-describedby="backup-key-help" required>
                    <button class="btn btn-primary" type="submit">Download connection file <span aria-hidden="true">&#8595;</span></button>
                </form>
                <p class="backup-help" id="backup-key-help">A new connection file replaces your previous access key.</p>
            </article>

            <article class="backup-step">
                <span class="backup-step-number">02</span>
                <h3>Get the Windows files</h3>
                <p>Save both scripts in the same folder as your connection file.</p>
                <div class="backup-actions">
                    <form method="post"><?= $csrfField ?><input type="hidden" name="action" value="installer"><button class="btn btn-secondary">Download installer</button></form>
                    <form method="post"><?= $csrfField ?><input type="hidden" name="action" value="downloader"><button class="btn btn-secondary">Download downloader</button></form>
                </div>
                <span class="backup-file-note">2 PowerShell files · .ps1</span>
            </article>

            <article class="backup-step">
                <span class="backup-step-number">03</span>
                <h3>Turn on the schedule</h3>
                <p>Right-click the installer and select <strong>Run with PowerShell</strong>.</p>
                <div class="backup-run-note">
                    <span aria-hidden="true">&gt;_</span>
                    <div><strong>Run with PowerShell</strong><small>install_pc_backup.ps1</small></div>
                </div>
                <p>This creates the six-hour task and attempts your first download. Verified complete TAR bundles are saved in your Documents folder.</p>
            </article>
        </div>

        <div class="backup-notice">
            <strong>Keep your PC ready</strong>
            <p>Your computer must be on, awake, online, and signed in. Missed runs are checked when you sign in again.</p>
        </div>

        <div class="backup-security">
            <div>
                <strong>Your connection file is private</strong>
                <p>After installation, remove the downloaded connection file. Windows stores the key encrypted for your account.</p>
            </div>
            <form method="post"><?= $csrfField ?><input type="hidden" name="action" value="revoke"><button class="btn btn-secondary backup-revoke">Revoke PC access</button></form>
        </div>
    </section>

    <section class="backup-card backup-history">
        <div class="backup-section-heading">
            <div>
                <span class="backup-eyebrow">BACKUP LIBRARY</span>
                <h2>Server copies <span class="backup-count"><?= count($entries) ?></span></h2>
                <p>Retained for 30 days after a successful new export. PC copies stay until you remove them.</p>
            </div>
            <span class="backup-tag">SQL database records</span>
        </div>

        <div class="backup-scroll">
            <table class="backup-table">
                <thead>
                    <tr><th scope="col">Created</th><th scope="col">File size</th><th scope="col">Download</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td>
                                <div class="backup-file">
                                    <span class="backup-file-icon" aria-hidden="true">SQL</span>
                                    <div>
                                        <strong><?= sfbc_pc_html(date('M d, Y', $entry['created_at'])) ?></strong>
                                        <small><?= sfbc_pc_html(date('H:i:s', $entry['created_at'])) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?= sfbc_pc_html(round($entry['bytes'] / 1024, 1)) ?> KB</td>
                            <td>
                                <form method="post">
                                    <?= $csrfField ?>
                                    <input type="hidden" name="action" value="download">
                                    <input type="hidden" name="name" value="<?= sfbc_pc_html($entry['name']) ?>">
                                    <button class="btn btn-secondary">Download SQL <span aria-hidden="true">&#8595;</span></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$entries): ?>
                        <tr>
                            <td colspan="3" class="backup-empty">
                                <strong>Your backup library starts here</strong>
                                <p>Complete the PC setup above. Completed server copies will appear here after a backup is requested.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p class="backup-history-note">Server history lists SQL snapshots. Automatic PC downloads are complete TAR bundles containing the database plus private IDs, receipts and avatars. Website source files are not included.</p>
    </section>
</div>

<?php
$message     = $sessionsData['message'];
$currentHash = $sessionsData['currentHash'];
$sessions    = $sessionsData['sessions'];
$csrfField   = $sessionsData['csrfField'];
?>
<div class="admin-body">
    <div class="wrap">
        <?php if ($message): ?>
            <div class="notice"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php foreach ($sessions as $s):
            $current = hash_equals($currentHash, $s['session_hash']);
        ?>
            <section class="card">
                <div>
                    <strong><?= htmlspecialchars(sfbc_session_device_label((string)$s['user_agent'])) ?></strong>
                    <?php if ($current): ?><span class="current">This device</span><?php endif; ?>
                    <div class="meta">
                        IP <?= htmlspecialchars($s['ip_address'] ?: 'Unknown') ?> · Last active <?= htmlspecialchars($s['last_seen_at']) ?> · Signed in <?= htmlspecialchars($s['created_at']) ?>
                    </div>
                </div>
                <?php if (!$current): ?>
                    <form method="post">
                        <?= $csrfField ?>
                        <input type="hidden" name="action" value="revoke">
                        <input type="hidden" name="session_id" value="<?= (int)$s['id'] ?>">
                        <button class="btn outline" type="submit">Sign Out</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
</div>
